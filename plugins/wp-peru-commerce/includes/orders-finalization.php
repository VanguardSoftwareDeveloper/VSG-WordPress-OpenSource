<?php
/**
 * IMP-46: derivados E-01 y cierre O-07/O-06 del pedido.
 *
 * Servicio interno: solo se invoca dentro del COMMIT de una operación
 * legitimada que YA bloqueó pc_orders e idempotencia. No REST, no pagos,
 * no reembolso, no cambio de fulfillment ni restauración de recursos.
 */
declare(strict_types=1);
namespace PeruCommerce;
if (!\defined('ABSPATH')) { exit; }

final class Orders_Finalization
{
    private const LIMIT_LINES=100;
    private const LIMIT_EVENTS=200;
    private const LIMIT_RIGHTS=200;

    /** Devuelve la nueva versión de pedido al cerrar, o NULL sin cambio. */
    public static function afterWithin(
        Transaction_Context $tx,Event_Actor $actor,
        string $operationId,string $orderId
    ): ?string {
        $orderId=Domain_Validation::sqlId($orderId);
        $operationId=Event_Journal::requirePendingOperation($tx,$operationId);
        $tx->lockedOrder($orderId);
        $actor->assertCurrent();
        $root=self::orderWithin($tx,$orderId);
        if ('CONFIRMADO'!==$root['status'] || null!==$root['closed_at']) {
            return null;
        }
        $derived=self::deriveWithin($tx,$orderId,$root);
        if (!$derived['eligible']) { return null; }

        $target=$derived['all_cancelled']?'CANCELADO':'FINALIZADO';
        $before=(string)$root['row_version'];
        $next=Settings_Store::nextVersion($before);
        if (1!==$tx->execute(
            'UPDATE %i SET status=%s,closed_at=UTC_TIMESTAMP(6),'
                .'row_version=row_version+1,updated_at=UTC_TIMESTAMP(6) '
                .'WHERE id=%s AND status=%s AND row_version=%s '
                .'AND confirmed_at IS NOT NULL AND closed_at IS NULL',
            [Orders_Domain::table('pc_orders'),$target,$orderId,
                'CONFIRMADO',$before]
        )) {
            throw new Transaction_Failure('pc_order_finalize_conflict');
        }
        Event_Journal::auditWithin(
            $tx,$actor,$operationId,'order',$orderId,
            'FINALIZADO'===$target?'order_finalized':'order_cancelled',
            'FINALIZADO'===$target?'all_obligations_settled':'all_lines_cancelled_settled',
            \hash('sha256','CONFIRMADO:'.$before),
            \hash('sha256',$target.':'.$next.':'.$derived['fact_hash'])
        );
        return $next;
    }

    /**
     * Derivados internos de solo cálculo; nunca agregar columnas o
     * propiedades a DTO cerrados de OpenAPI por inferencia.
     */
    public static function deriveWithin(
        Transaction_Context $tx,string $orderId,?array $root=null
    ): array {
        $orderId=Domain_Validation::sqlId($orderId);
        $tx->lockedOrder($orderId);
        $root??=self::orderWithin($tx,$orderId);
        $lines=self::linesWithin($tx,$orderId);
        $cases=self::casesWithin($tx,$orderId);
        $money=self::moneyWithin($tx,$orderId,$root);
        $eligible='CONFIRMADO'===$root['status']
            && null!==$root['confirmed_at'] && null===$root['closed_at']
            && $lines['all_resolved'] && !$cases['open'] && $money['settled']
            && ($lines['performed'] || ($lines['all_cancelled'] && $money['owed']===0));
        return [
            'eligible'=>$eligible,
            'all_cancelled'=>$lines['all_cancelled'],
            'cancellation_partial'=>$lines['cancelled'] && $lines['performed'],
            'fulfillment_partial'=>$lines['performed'] && !$lines['all_resolved'],
            'has_incident'=>$cases['open'],
            'balance_pending'=>!$money['settled'],
            'refund_partial'=>$money['refund_partial'],
            'fact_hash'=>\hash('sha256',\implode(':',[
                $orderId,(string)$root['row_version'],
                $lines['signature'],$cases['signature'],$money['signature']
            ])),
        ];
    }

    private static function orderWithin(
        Transaction_Context $tx,string $orderId
    ): array {
        $r=$tx->rows(
            'SELECT status,payment_method,currency,total_cents,confirmed_at,'
                .'closed_at,row_version FROM %i WHERE id=%s '
                .'LIMIT 2 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($r)
            || !\in_array((string)$r[0]['status'],Orders_Domain::STATES,true)
            || !\in_array((string)$r[0]['payment_method'],
                ['cash','mercado_pago'],true)
            || 'PEN'!==$r[0]['currency']) {
            throw new Transaction_Failure('pc_order_finalize_invalid');
        }
        Domain_Validation::sqlId((string)$r[0]['row_version']);
        return $r[0];
    }

    private static function incompleteLines(): array
    {
        return ['all_resolved'=>false,'all_cancelled'=>false,'performed'=>false,
            'cancelled'=>false,'signature'=>'incomplete_lines'];
    }

    private static function units(mixed $raw): ?int
    {
        if (!\is_string($raw) || !\preg_match('/\A(?:0|[1-9][0-9]*)\z/D',$raw)) {
            return null;
        }
        try { $n=Money::fromApi($raw); }
        catch (\Throwable $ignored) { return null; }
        return $n<=100000?$n:null;
    }

    private static function linesWithin(
        Transaction_Context $tx,string $orderId
    ): array {
        $lines=$tx->rows(
            'SELECT id,kind,sellable_id,quantity,fulfillment_state,'
                .'catalog_snapshot FROM %i WHERE order_id=%s ORDER BY id '
                .'LIMIT 101 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_order_lines'),$orderId]
        );
        if ([]===$lines || \count($lines)>self::LIMIT_LINES) {
            return self::incompleteLines();
        }
        $stocks=$tx->rows(
            'SELECT c.order_line_id,c.sellable_id,c.committed_units,'
                .'c.fulfilled_units,c.released_units FROM %i c '
                .'INNER JOIN %i l ON l.id=c.order_line_id '
                .'WHERE l.order_id=%s LIMIT 101 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_stock_commitments'),
                Orders_Domain::table('pc_order_lines'),$orderId]
        );
        $appointments=$tx->rows(
            'SELECT id,order_line_id,cancelled_at,completed_at,no_show_at,'
                .'outcome_evidence_ref FROM %i WHERE order_id=%s LIMIT 2 '
                .'LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_appointments'),$orderId]
        );
        $rights=$tx->rows(
            'SELECT r.id,r.order_line_id,r.file_id,r.revoked_at,'
                .'EXISTS(SELECT 1 FROM %i a WHERE a.entity_type=%s '
                .'AND a.entity_id=r.id AND a.operation_code=%s) AS granted_audit '
                .'FROM %i r INNER JOIN %i l ON l.id=r.order_line_id '
                .'WHERE l.order_id=%s ORDER BY r.order_line_id,r.file_id '
                .'LIMIT 201 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_audit_log'),
                'download_right','digital_right_granted',
                Orders_Domain::table('pc_download_rights'),
                Orders_Domain::table('pc_order_lines'),$orderId]
        );
        if (\count($stocks)>100 || \count($appointments)>1
            || \count($rights)>self::LIMIT_RIGHTS) {
            return self::incompleteLines();
        }
        $byStock=[];$byRight=[];
        foreach($stocks as $s){
            $id=(string)$s['order_line_id'];
            if(isset($byStock[$id])) { return self::incompleteLines(); }
            $byStock[$id]=$s;
        }
        foreach($rights as $right){
            $id=(string)$right['order_line_id'];$file=(string)$right['file_id'];
            if(isset($byRight[$id][$file])) { return self::incompleteLines(); }
            $byRight[$id][$file]=$right;
        }
        $seen=[];$all=true;$performed=false;$cancelled=false;
        $serviceId=null;
        foreach($lines as $l){
            $id=(string)$l['id'];$kind=(string)$l['kind'];
            $state=(string)$l['fulfillment_state'];$seen[$id]=true;
            try{ $qty=Orders_Domain::quantity((string)$l['quantity']); }
            catch(\Throwable $ignored){ return self::incompleteLines(); }
            $complete=false;
            if('physical'===$kind){
                if (isset($byStock[$id]) && !isset($byRight[$id])
                    && \in_array($state,['ENTREGADO','CANCELADO'],true)) {
                    $c=$byStock[$id];
                    $total=self::units($c['committed_units']);
                    $out=self::units($c['fulfilled_units']);
                    $released=self::units($c['released_units']);
                    $complete=null!==$total && null!==$out && null!==$released
                        && $total>0 && $total<=$qty
                        && $out+$released===$total
                        && (string)$c['sellable_id']===(string)$l['sellable_id']
                        && ('ENTREGADO'===$state
                            ? ($out>0 && self::auditExistsWithin(
                                $tx,'order_line',$id,'physical_delivered'
                            )) : $out===0);
                }
            } elseif('service'===$kind){
                if(null!==$serviceId){ return self::incompleteLines(); }
                $serviceId=$id;
                if($qty===1 && !isset($byStock[$id]) && !isset($byRight[$id])
                    && 1===\count($appointments)
                    && (string)$appointments[0]['order_line_id']===$id){
                    $a=$appointments[0];
                    $complete=('REALIZADA'===$state
                        && null!==$a['completed_at']
                        && null===$a['cancelled_at']
                        && null===$a['no_show_at']
                        && \is_string($a['outcome_evidence_ref'])
                        && ''!==$a['outcome_evidence_ref'])
                        || ('CANCELADA'===$state
                            && null!==$a['cancelled_at']
                            && null===$a['completed_at']
                            && null===$a['no_show_at']);
                    if($complete){
                        $complete=self::historyWithin(
                            $tx,(string)$a['id']
                        ) && ('REALIZADA'!==$state || self::auditExistsWithin(
                            $tx,'appointment',(string)$a['id'],
                            'appointment_outcome_recorded'
                        ));
                    }
                }
                // NO_ASISTIO exige revisión y derechos D-93:
                // no se deduce cierre de una observación sin expediente.
            } elseif('digital'===$kind){
                if(!isset($byStock[$id])){
                    if('CANCELADO'===$state){
                        $complete=!isset($byRight[$id]);
                    }elseif('HABILITADO'===$state){
                        try {
                            $sold=Orders_Domain::catalogSnapshot(
                                (string)$l['catalog_snapshot'],'digital'
                            );
                            $files=$sold['files']??[[
                                'file_id'=>$sold['file_id']??null,
                            ]];
                            $ids=[];$complete=\is_array($files)
                                && []!==$files && \count($files)<=100
                                && \count($byRight[$id]??[])===\count($files);
                            if($complete){
                                foreach($files as $f){
                                    $fileId=$f['file_id']??null;
                                    if(!\is_string($fileId)
                                        || isset($ids[$fileId])
                                        || !isset($byRight[$id][$fileId])
                                        || null!==$byRight[$id][$fileId]['revoked_at']
                                        || '1'!==(string)$byRight[$id][$fileId]['granted_audit']){
                                        $complete=false;break;
                                    }
                                    $ids[$fileId]=true;
                                }
                            }
                        } catch(\Throwable $ignored){$complete=false;}
                    }
                }
            }
            if(!$complete){ $all=false;continue; }
            if(\in_array($state,['CANCELADO','CANCELADA'],true)){
                $cancelled=true;
            }else{
                $performed=true;
            }
        }
        foreach(\array_keys($byStock) as $id){
            if(!isset($seen[$id])){ $all=false; }
        }
        foreach(\array_keys($byRight) as $id){
            if(!isset($seen[$id])){ $all=false; }
        }
        if(null===$serviceId && []!==$appointments){$all=false;}
        return [
            'all_resolved'=>$all,
            'all_cancelled'=>$all && !$performed && $cancelled,
            'performed'=>$performed,'cancelled'=>$cancelled,
            'signature'=>\hash('sha256',\json_encode(
                [$lines,$stocks,$appointments,$rights],\JSON_THROW_ON_ERROR
            )),
        ];
    }

    /** No inferir cumplimiento de un estado sin evento de fuente real.
     * Esta auditoría no sustituye prueba humana P-C de la evidencia física. */
    private static function auditExistsWithin(
        Transaction_Context $tx,string $kind,string $id,string $code
    ): bool {
        $rows=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s LIMIT 2 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_audit_log'),$kind,
                Domain_Validation::sqlId($id),$code]
        );
        return 1===\count($rows);
    }

    /** Exigir continuidad de la intención de comunicación para V-08.
     * No se afirma recepción de correo, sólo la constancia en outbox.
     */
    private static function historyWithin(
        Transaction_Context $tx,string $appointmentId
    ): bool {
        global $wpdb;
        if (!$wpdb instanceof \wpdb){return false;}
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
        $tables=Schema::tableNames($wpdb);
        $history=$tx->rows(
            'SELECT h.id,h.communication_outbox_id,'
                .'o.event_code,o.entity_id,o.channel,o.audience_code '
                .'FROM %i h LEFT JOIN %i o ON o.id=h.communication_outbox_id '
                .'WHERE h.appointment_id=%s ORDER BY h.id LIMIT 201 '
                .'LOCK IN SHARE MODE',
            [$tables['pc_appointment_history'],$tables['pc_outbox'],
                $appointmentId]
        );
        if(\count($history)>200){return false;}
        foreach($history as $h){
            if(null===$h['communication_outbox_id']
                || 'service_rescheduled'!==$h['event_code']
                || (string)$h['entity_id']!==$appointmentId
                || 'buyer'!==$h['audience_code'] || 'email'!==$h['channel']){
                return false;
            }
        }
        return true;
    }

    private static function casesWithin(
        Transaction_Context $tx,string $orderId
    ): array {
        $cases=$tx->rows(
            'SELECT id,case_type,case_phase,resolved_at FROM %i '
                .'WHERE order_id=%s ORDER BY id LIMIT 201 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_cases'),$orderId]
        );
        if(\count($cases)>self::LIMIT_EVENTS){
            return ['open'=>true,'signature'=>'case_overflow'];
        }
        $terminal=[
            'physical_partial_quantity_adjustment'=>[
                'closed_rejected','closed_withdrawn','closed_adjusted',
            ],
            'logistics_non_delivery'=>['resolved'],
            'digital_access_dispute'=>['closed_access_maintained'],
            'mp_reconciliation'=>['closed_reassigned','closed_no_charge'],
        ];
        $open=false;
        foreach($cases as $c){
            $type=(string)$c['case_type'];
            if(null===$c['resolved_at']
                || !\in_array((string)$c['case_phase'],$terminal[$type]??[],true)){
                $open=true;break;
            }
            if('logistics_non_delivery'===$type){
                $events=$tx->rows(
                    'SELECT event_code,decision_code FROM %i WHERE case_id=%s '
                        .'ORDER BY occurred_at DESC,id DESC LIMIT 1 '
                        .'LOCK IN SHARE MODE',
                    [Orders_Domain::table('pc_case_events'),(string)$c['id']]
                );
                if(1!==\count($events)
                    || 'logistics_case_resolved'!==$events[0]['event_code']
                    || 'delivery_verified'!==$events[0]['decision_code']){
                    $open=true;break;
                }
            }
        }
        return [
            'open'=>$open,
            'signature'=>\hash('sha256',\json_encode($cases,\JSON_THROW_ON_ERROR))
        ];
    }

    private static function moneyWithin(
        Transaction_Context $tx,string $orderId,array $order
    ): array {
        $blocked=['settled'=>false,'owed'=>null,'refund_partial'=>false,
            'signature'=>'money_unresolved'];
        try{$original=Money::fromApi((string)$order['total_cents']);}
        catch(\Throwable $ignored){return $blocked;}
        if($original<1){return $blocked;}
        $adjustments=$tx->rows(
            'SELECT delta_cents FROM %i WHERE order_id=%s ORDER BY id '
                .'LIMIT 201 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_order_adjustments'),$orderId]
        );
        $events=$tx->rows(
            'SELECT id,kind,amount_cents,provider,payment_attempt_id,'
                .'source_payment_event_id,correction_evidence_snapshot,'
                .'refund_evidence_snapshot FROM %i WHERE order_id=%s '
                .'ORDER BY id LIMIT 201 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_payment_events'),$orderId]
        );
        $attempts=$tx->rows(
            'SELECT id,state,provider,amount_cents,currency FROM %i '
                .'WHERE order_id=%s ORDER BY id LIMIT 201 '
                .'LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_payment_attempts'),$orderId]
        );
        if(\count($adjustments)>self::LIMIT_EVENTS || []===$events
            || \count($events)>self::LIMIT_EVENTS
            || \count($attempts)>self::LIMIT_EVENTS){return $blocked;}
        try{
            $owed=$original;
            foreach($adjustments as $change){
                $owed=Money::addChecked(
                    $owed,Money::fromSignedLedger((string)$change['delta_cents'])
                );
            }
            if($owed<0 || $owed>$original){return $blocked;}
            $paid=0;$initial=null;$initialAmount=null;$initialAttempt=null;
            $refunded=0;$provider=(string)$order['payment_method'];
            foreach($events as $event){
                $kind=(string)$event['kind'];
                $amount=Money::fromSignedLedger((string)$event['amount_cents']);
                if($event['provider']!==$provider){return $blocked;}
                if('initial_charge'===$kind){
                    if(null!==$initial || $amount<1
                        || null!==$event['source_payment_event_id']
                        || ('cash'===$provider && null!==$event['payment_attempt_id'])
                        || ('mercado_pago'===$provider && null===$event['payment_attempt_id'])){
                        return $blocked;
                    }
                    $initial=(string)$event['id'];
                    $initialAmount=$amount;
                    $initialAttempt=$event['payment_attempt_id'];
                }elseif('cash_correction'===$kind){
                    if('cash'!==$provider || null===$initial
                        || null!==$event['payment_attempt_id']
                        || (string)$event['source_payment_event_id']!==$initial
                        || 0===$amount
                        || !\is_string($event['correction_evidence_snapshot'])
                        || ''===$event['correction_evidence_snapshot']){
                        return $blocked;
                    }
                }elseif('external_refund'===$kind){
                    if(null===$initial || $amount>=0 || $amount===\PHP_INT_MIN
                        || (string)$event['source_payment_event_id']!==$initial
                        || !\is_string($event['refund_evidence_snapshot'])
                        || ''===$event['refund_evidence_snapshot']){
                        return $blocked;
                    }
                    $refunded=Money::addChecked($refunded,-$amount);
                }else{
                    // Chargeback/cargo tardío u otro evento sin conciliación
                    // completa nunca habilita O-07.
                    return $blocked;
                }
                $paid=Money::addChecked($paid,$amount);
            }
            if(null===$initial || $paid<0 || $paid>$original
                || $refunded>$initialAmount
                || ('mercado_pago'===$provider && $initialAmount!==$original)){
                return $blocked;
            }
            if('cash'===$provider){
                if([]!==$attempts){return $blocked;}
            }else{
                $approved=0;
                foreach($attempts as $a){
                    if('mercado_pago'!==$a['provider'] || 'PEN'!==$a['currency']){
                        return $blocked;
                    }
                    if('APROBADO'===$a['state']){
                        ++$approved;
                        if((string)$a['id']!==(string)$initialAttempt
                            || Money::fromApi((string)$a['amount_cents'])!==$original){
                            return $blocked;
                        }
                    }elseif(!\in_array($a['state'],[
                        'RECHAZADO','CANCELADO_PROVEEDOR'
                    ],true)){
                        return $blocked;
                    }
                }
                if(1!==$approved){return $blocked;}
            }
            // Ni un hold/admisión real vigente puede quedar sin atender.
            $holds=$tx->rows(
                'SELECT id FROM %i WHERE order_id=%s AND state=%s '
                    .'AND expires_at>UTC_TIMESTAMP(6) LIMIT 1 LOCK IN SHARE MODE',
                [self::tableExtra('pc_holds'),$orderId,'VIGENTE']
            );
            // pc_resource_admissions NO tiene columna order_id. Su enlace
            // comercial durable es la raíz checkout_mp de idempotencia,
            // cuyo entity_type/order_id se congela ANTES de HTTP.
            $admissions=$tx->rows(
                'SELECT a.id FROM %i a INNER JOIN %i i '
                    .'ON i.id=a.idempotency_id '
                    .'WHERE i.operation_type=%s AND i.entity_type=%s '
                    .'AND i.entity_id=%s AND a.consumed_at IS NULL '
                    .'AND a.released_at IS NULL AND '
                    .'a.expires_at>UTC_TIMESTAMP(6) '
                    .'LIMIT 1 LOCK IN SHARE MODE',
                [self::tableExtra('pc_resource_admissions'),
                    Orders_Domain::table('pc_idempotency'),
                    'checkout_mp','order',$orderId]
            );
            if([]!==$holds || []!==$admissions){return $blocked;}
            return [
                'settled'=>$paid===$owed,
                'owed'=>$owed,
                'refund_partial'=>$refunded>0 && $refunded<$initialAmount,
                'signature'=>\hash('sha256',\json_encode(
                    [$adjustments,$events,$attempts,$owed,$paid],\JSON_THROW_ON_ERROR
                ))
            ];
        }catch(\Throwable $ignored){return $blocked;}
    }

    private static function tableExtra(string $suffix): string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){
            throw new Transaction_Failure('pc_order_finalize_invalid');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
        return Schema::tableNames($wpdb)[$suffix];
    }

    /** Replay debe devolver la versión emitida por la misma operación
     * si O-07/O-06 incrementó una segunda vez pc_orders. */
    public static function replayVersionWithin(
        Transaction_Context $tx,string $orderId,string $operationId,
        string $expectedVersion
    ): string {
        $base=Settings_Store::nextVersion($expectedVersion);
        $events=$tx->rows(
            'SELECT operation_code FROM %i '
                .'WHERE entity_type=%s AND entity_id=%s AND request_id=UNHEX(%s) '
                .'AND operation_code IN (%s,%s) LIMIT 2',
            [Orders_Domain::table('pc_audit_log'),'order',$orderId,
                Event_Journal::correlationHex($operationId),
                'order_finalized','order_cancelled']
        );
        return []===$events ? $base : Settings_Store::nextVersion($base);
    }
}
