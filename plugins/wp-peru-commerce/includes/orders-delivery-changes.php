<?php
/**
 * IMP-43 — R8-28: propuesta/aprobación de dirección para un pedido existente.
 *
 * La dirección original, la tarifa vendida y las revisiones ya escritas son
 * inmutables. O solo propone; A con financial_adjust aprueba si la ubicación
 * sigue cubierta. Un incremento de tarifa no se cobra automáticamente:
 * requiere un flujo comercial nuevo con consentimiento verificable.
 *
 * La solicitud invitada R8-12 conserva su propia legitimación. La entrada
 * R8-28 es una acción ADMINISTRATIVA, no un bypass de sesión/OTP invitado.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Delivery_Changes
{
    private const OP = 'order_delivery_change';
    private const PROPOSE = 'propose';
    private const APPROVE = 'approve';
    private const MAX_REVISIONS = 200;

    /**
     * Prueba para Access_Control::conditional(R8-28), derivada de
     * roles/capabilities WP; nunca confiar en "decision" del navegador.
     */
    public static function guard(array $ctx, ?string $role): array
    {
        $decision=self::decision($role);
        if (null===$decision) { return []; }
        try {
            $orderId=Domain_Validation::sqlId($ctx['path']['id']??'');
            $body=self::input($ctx['body']??null);
            $exists=Transaction::run(
                static function(Transaction_Context $tx) use($orderId):bool {
                    $rows=$tx->rows(
                        'SELECT status,original_delivery_snapshot FROM %i '
                            .'WHERE id=%s LIMIT 2',
                        [Orders_Domain::table('pc_orders'),$orderId]
                    );
                    if (1!==\count($rows) || 'CONFIRMADO'!==$rows[0]['status']) {
                        return false;
                    }
                    $snapshot=Orders_Domain::deliverySnapshot(
                        $rows[0]['original_delivery_snapshot']
                    );
                    return 'delivery'===($snapshot['mode']??null);
                }
            );
            if (!$exists) { return []; }
            return [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,'decision'=>$decision,
            ];
        } catch (\Throwable $ignored) { return []; }
    }

    /** @return array<string,string> MutationResult */
    public static function change(string $orderId,array $body): array
    {
        $decision=self::decision(Access_Roles::currentRole());
        if (null===$decision) {
            throw new Transaction_Failure('pc_delivery_change_denied');
        }
        $orderId=Domain_Validation::sqlId($orderId);
        $body=self::input($body);
        $actor=Event_Actor::staff([
            self::APPROVE===$decision
                ? 'perucommerce_orders_financial_adjust'
                : 'perucommerce_orders_fulfill'
        ]);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'order',
            'resource_id'=>$orderId,
            'purpose'=>self::OP,
        ];
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $orderId,$body,$decision,$scope,$actor
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OP,$body['idempotency_key'],$body
                );
                if (!\in_array($claim['status'],['new','replay'],true)) {
                    throw new Transaction_Failure('pc_delivery_change_conflict');
                }
                $locked=$tx->lockOrder($orderId);
                if ('replay'===$claim['status']) {
                    return self::replayWithin(
                        $tx,$claim,$orderId,$body
                    );
                }
                if ((string)$locked['row_version']!==$body['expected_version']
                    || 'CONFIRMADO'!==$locked['status']) {
                    throw new Transaction_Failure('pc_delivery_change_conflict');
                }
                $order=self::orderWithin($tx,$orderId,$locked);
                self::requirePhysicalPendingWithin($tx,$orderId);
                // Una instalación no habilitada para delivery no puede
                // crear/aprobar una dirección no cubierta. El verificador
                // ubigeo/licencia + locks P-C se reutiliza de IMP-19.
                if (!Module_Gates::allows('delivery','write')) {
                    throw new Transaction_Failure('pc_delivery_change_not_ready');
                }
                $settings=Settings_Store::readWithin($tx)['settings'];
                if (true!==($settings['delivery_enabled']??false)) {
                    throw new Transaction_Failure('pc_delivery_change_not_ready');
                }
                // Bloquea el ancla de cobertura mientras se lee la
                // tarifa/dataset: R8-52 no debe modificarla a mitad
                // de un cambio de dirección ya validado.
                Coverage_Version::readWithin($tx,true);
                $quote=Delivery_Coverage::feeWithin(
                    $tx,$body['new_ubigeo']
                );
                $newFee=Money::fromApi($quote['fee_cents']);
                $revisions=self::revisionsWithin($tx,$orderId,
                    $order['revision_id']);
                $current=$revisions['current']??$order['original'];
                $oldFee=$current['fee'];
                if ($body['new_ubigeo']===$current['ubigeo_code']
                    && $body['new_address']===$current['address']) {
                    throw new Transaction_Failure('pc_delivery_change_noop');
                }
                // A partir de una entrega DESPACHADA/ENTREGADA solo
                // procede incidencia logística, no reescribir destino.
                $payment=self::moneyWithin($tx,$orderId,
                    $order['payment_method']);
                $sensitive=$payment['ever_credited']
                    || $body['new_ubigeo']!==$current['ubigeo_code']
                    || $newFee!==$oldFee;
                if (self::PROPOSE===$decision) {
                    // O solo propone, incluso para cambio de texto
                    // sin costo: no confundir propuesta con autorización.
                    if (null!==$revisions['pending']) {
                        throw new Transaction_Failure(
                            'pc_delivery_change_proposal_pending'
                        );
                    }
                } else {
                    if ($payment['ambiguous']) {
                        throw new Transaction_Failure('pc_delivery_change_money_review');
                    }
                    // D-106: una tarifa mayor no genera un segundo
                    // cargo silencioso, ni a COD aún no cobrado.
                    if ($newFee>$oldFee) {
                        throw new Transaction_Failure('pc_delivery_change_higher_fee');
                    }
                    // A con financial_adjust es quien aprueba cuando
                    // hay dinero, distrito o costo distinto. No aceptar
                    // un booleano de O como autorización financiera.
                    if ($sensitive
                        && !\current_user_can(
                            'perucommerce_orders_financial_adjust'
                        )) {
                        throw new Transaction_Failure('pc_delivery_change_denied');
                    }
                }

                $pending=$revisions['pending'];
                $candidateMatch=false;
                if (null!==$pending) {
                    $candidateMatch=
                        $pending['ubigeo_code']===$body['new_ubigeo']
                        && $pending['address']===$body['new_address'];
                    if (self::APPROVE===$decision && $candidateMatch
                        && ($pending['fee']!==$newFee
                            || $pending['coverage_version']
                                !==$quote['coverage_version']
                            || $pending['dataset_version']
                                !==$quote['dataset_version'])) {
                        // La propuesta anterior cambió bajo el nuevo
                        // tarifario/INEI: no aprobar un costo distinto
                        // del que vio el operador/comprador.
                        throw new Transaction_Failure(
                            'pc_delivery_change_quote_changed'
                        );
                    }
                }

                $nextNumber=$revisions['last_number']+1;
                if ($nextNumber>4294967295
                    || $nextNumber>self::MAX_REVISIONS) {
                    throw new Transaction_Failure('pc_delivery_change_invariant');
                }
                $snapshot=[
                    'mode'=>'delivery',
                    'ubigeo_code'=>$body['new_ubigeo'],
                    'address'=>$body['new_address'],
                    'fee_cents'=>Money::toApi($newFee),
                    'dataset_version'=>$quote['dataset_version'],
                    'coverage_version'=>$quote['coverage_version'],
                    'request_kind'=>self::PROPOSE===$decision
                        ? 'staff_proposal'
                        : ($candidateMatch ? 'approval_of_staff_proposal'
                            : 'admin_direct_approval'),
                    'requested_by_user_id'=>(string)\get_current_user_id(),
                    'reason'=>$body['reason'],
                    'source_order_version'=>$body['expected_version'],
                    'previous_approved_revision_id'=>$order['revision_id'],
                ];
                if (self::APPROVE===$decision && $candidateMatch) {
                    $snapshot['proposal_revision_id']=$pending['id'];
                    $snapshot['proposal_reason']=$pending['reason'];
                    $snapshot['proposal_user_id']=$pending['requested_by'];
                }
                $revisionId=self::insertRevisionWithin(
                    $tx,$orderId,$order['revision_id'],$nextNumber,
                    $body['new_ubigeo'],$newFee,$snapshot,$decision
                );

                if (self::APPROVE===$decision) {
                    // Si la nueva tarifa es menor, registrar crédito
                    // comercial real en el mismo TX. No significa un
                    // reembolso realizado; R8-27 documenta dinero salido.
                    if ($newFee<$oldFee) {
                        self::creditWithin(
                            $tx,$claim['operation_id'],$orderId,
                            $revisionId,$oldFee,$newFee,$order,$body,
                            $payment
                        );
                    }
                    // El puntero operativo apunta EXCLUSIVAMENTE
                    // a revisiones aprobadas e inmutables.
                    if (1!==$tx->execute(
                        'UPDATE %i SET current_delivery_revision_id=%s '
                            .'WHERE id=%s AND row_version=%s AND status=%s',
                        [Orders_Domain::table('pc_orders'),$revisionId,
                            $orderId,$body['expected_version'],'CONFIRMADO']
                    )) {
                        throw new Transaction_Failure('pc_delivery_change_conflict');
                    }
                }
                $newOrderVersion=$tx->advanceVersion(
                    'pc_orders',$orderId,$body['expected_version']
                );
                $code=self::APPROVE===$decision
                    ? 'delivery_change_approved'
                    : 'delivery_change_proposed';
                Event_Journal::auditWithin(
                    $tx,$actor,$claim['operation_id'],'order',$orderId,
                    $code,'delivery_revision',
                    \hash('sha256',\json_encode([
                        'previous_revision_id'=>$order['revision_id'],
                        'fee_cents'=>$oldFee,
                        'row_version'=>$body['expected_version'],
                    ],\JSON_THROW_ON_ERROR)),
                    \hash('sha256',\json_encode([
                        'new_revision_id'=>$revisionId,
                        'new_order_version'=>$newOrderVersion,
                        'snapshot_sha256'=>\hash('sha256',
                            self::encode($snapshot)),
                    ],\JSON_THROW_ON_ERROR))
                );
                Queue_Outbox::essentialWithin(
                    $tx,$actor,$claim['operation_id'],$orderId,
                    $code,$orderId
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],
                    'delivery_revision',$revisionId,200
                );
                return self::result(
                    $claim['operation_id'],$revisionId,$newOrderVersion,$decision
                );
            },true
        );
    }

    /**
     * Pedido original histórico y referencia de aprobación vigente;
     * jamás se recalcula el precio vendido desde las tarifas actuales.
     */
    private static function orderWithin(
        Transaction_Context $tx,string $orderId,array $locked
    ): array {
        $rows=$tx->rows(
            'SELECT status,payment_method,currency,delivery_cents,total_cents,'
                .'original_delivery_snapshot,current_delivery_revision_id,'
                .'confirmed_at,row_version FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($rows)
            || (string)$rows[0]['row_version']!==(string)$locked['row_version']
            || (string)$rows[0]['status']!==(string)$locked['status']
            || (string)$rows[0]['payment_method']!==(string)$locked['payment_method']
            || 'CONFIRMADO'!==$rows[0]['status']
            || 'PEN'!==$rows[0]['currency']
            || null===$rows[0]['confirmed_at']) {
            throw new Transaction_Failure('pc_delivery_change_conflict');
        }
        $original=Orders_Domain::deliverySnapshot(
            $rows[0]['original_delivery_snapshot']
        );
        if ('delivery'!==($original['mode']??null)
            || !isset($original['fee_cents'])
            || !\is_string($original['fee_cents'])) {
            throw new Transaction_Failure('pc_delivery_change_invariant');
        }
        $fee=Money::fromApi((string)$rows[0]['delivery_cents']);
        if (Money::fromApi($original['fee_cents'])!==$fee) {
            throw new Transaction_Failure('pc_delivery_change_invariant');
        }
        $total=Money::fromApi((string)$rows[0]['total_cents']);
        if ($total<$fee || $total<1) {
            throw new Transaction_Failure('pc_delivery_change_invariant');
        }
        return [
            'original'=>[
                'ubigeo_code'=>$original['ubigeo_code'],
                'address'=>$original['address'],
                'fee'=>$fee,
            ],
            'revision_id'=>null===$rows[0]['current_delivery_revision_id']
                ? null : Domain_Validation::sqlId(
                    (string)$rows[0]['current_delivery_revision_id']
                ),
            'payment_method'=>$rows[0]['payment_method'],
            'total'=>$total,
            'original_fee'=>$fee,
        ];
    }

    /** Ningún desvío después del despacho, entrega o pedido sin físico. */
    private static function requirePhysicalPendingWithin(
        Transaction_Context $tx,string $orderId
    ): void {
        $lines=$tx->rows(
            'SELECT kind,fulfillment_state FROM %i WHERE order_id=%s '
                .'ORDER BY id LIMIT 101',
            [Orders_Domain::table('pc_order_lines'),$orderId]
        );
        if ([]===$lines || \count($lines)>100) {
            throw new Transaction_Failure('pc_delivery_change_invariant');
        }
        $active=0;
        foreach ($lines as $line) {
            if ('physical'!==$line['kind']) { continue; }
            if (\in_array($line['fulfillment_state'],[
                'DESPACHADO','ENTREGADO',
            ],true)) {
                throw new Transaction_Failure('pc_delivery_change_ineligible');
            }
            if (\in_array($line['fulfillment_state'],[
                'PENDIENTE','PREPARANDO','LISTO',
            ],true)) { ++$active; }
            elseif ('CANCELADO'!==$line['fulfillment_state']) {
                throw new Transaction_Failure('pc_delivery_change_invariant');
            }
        }
        if (0===$active) {
            throw new Transaction_Failure('pc_delivery_change_ineligible');
        }
    }

    /**
     * Las propuestas/las aprobaciones se INSERTAN, no se actualiza una
     * propuesta como si nunca hubiese sido propuesta. Última revisión
     * aprobada debe coincidir con el puntero actual del pedido.
     */
    private static function revisionsWithin(
        Transaction_Context $tx,string $orderId,?string $currentId
    ): array {
        $rows=$tx->rows(
            'SELECT id,previous_revision_id,revision_number,ubigeo_code,'
                .'address_snapshot,fee_cents,approved_by,approved_at '
                .'FROM %i WHERE order_id=%s '
                .'ORDER BY revision_number DESC LIMIT 201 FOR UPDATE',
            [Orders_Domain::table('pc_delivery_revisions'),$orderId]
        );
        if (\count($rows)>self::MAX_REVISIONS) {
            throw new Transaction_Failure('pc_delivery_change_invariant');
        }
        $lastNumber=0;$latestApproved=null;$pending=null;
        $current=null;$approvedNumber=0;
        foreach ($rows as $row) {
            $id=Domain_Validation::sqlId((string)$row['id']);
            $number=Domain_Validation::sqlId((string)$row['revision_number']);
            $no=(int)$number;
            if ($no<1 || $no>4294967295) {
                throw new Transaction_Failure('pc_delivery_change_invariant');
            }
            $lastNumber=\max($lastNumber,$no);
            if ((null===$row['approved_by'])!==(null===$row['approved_at'])) {
                throw new Transaction_Failure('pc_delivery_change_invariant');
            }
            $raw=Orders_Domain::decode(
                (string)$row['address_snapshot'],3072
            );
            $fee=Money::fromApi((string)$row['fee_cents']);
            if (($raw['mode']??null)!=='delivery'
                || ($raw['ubigeo_code']??null)!==(string)$row['ubigeo_code']
                || ($raw['fee_cents']??null)!==(string)$fee
                || !\is_string($raw['address']??null)
                || !\mb_check_encoding($raw['address'],'UTF-8')
                || ''===\trim($raw['address'])
                || \mb_strlen($raw['address'],'UTF-8')>500
                || !\is_string($raw['reason']??null)
                || !\mb_check_encoding($raw['reason'],'UTF-8')
                || ''===\trim($raw['reason'])
                || \mb_strlen($raw['reason'],'UTF-8')>1000
                || !\is_string($raw['requested_by_user_id']??null)
                || !\is_string($raw['source_order_version']??null)
                || !\is_string($raw['coverage_version']??null)
                || !\is_string($raw['dataset_version']??null)
                || ''===$raw['dataset_version']
                || \mb_strlen($raw['dataset_version'],'UTF-8')>80) {
                throw new Transaction_Failure('pc_delivery_change_invariant');
            }
            Domain_Validation::sqlId($raw['requested_by_user_id']);
            Domain_Validation::sqlId($raw['source_order_version']);
            Coverage_Version::version($raw['coverage_version']);
            if (null!==$row['approved_by']) {
                Domain_Validation::sqlId((string)$row['approved_by']);
            }
            $view=[
                'id'=>$id,'revision_number'=>$no,
                'previous_revision_id'=>$row['previous_revision_id'],
                'ubigeo_code'=>(string)$row['ubigeo_code'],
                'address'=>$raw['address'],
                'fee'=>$fee,
                'reason'=>$raw['reason'],
                'requested_by'=>$raw['requested_by_user_id']??null,
                'coverage_version'=>$raw['coverage_version']??null,
                'dataset_version'=>$raw['dataset_version']??null,
            ];
            if (null!==$row['approved_at']) {
                if (null===$latestApproved) { $latestApproved=$id; }
                if ($id===$currentId) {
                    $current=$view;$approvedNumber=$no;
                }
            } elseif (null===$pending) {
                $pending=$view;
            }
        }
        if ($latestApproved!==$currentId
            || (null!==$currentId && null===$current)) {
            throw new Transaction_Failure('pc_delivery_change_invariant');
        }
        if (null!==$pending
            && ($pending['revision_number']<=$approvedNumber
                || $pending['previous_revision_id']!==$currentId)) {
            // Propuestas anteriores a una aprobación posterior quedaron
            // supersedidas; la última histórica no bloquea nuevas.
            $pending=null;
        }
        return [
            'current'=>$current,
            'pending'=>$pending,
            'last_number'=>$lastNumber,
        ];
    }

    /**
     * Acreditación histórica + disputas: un saldo neto corregido a cero
     * nunca devuelve al operador permiso de aprobación.
     */
    private static function moneyWithin(
        Transaction_Context $tx,string $orderId,string $method
    ): array {
        $events=$tx->rows(
            'SELECT kind,provider,amount_cents FROM %i '
                .'WHERE order_id=%s ORDER BY id LIMIT 201',
            [Orders_Domain::table('pc_payment_events'),$orderId]
        );
        if (\count($events)>200) {
            throw new Transaction_Failure('pc_delivery_change_money_review');
        }
        $count=0;$ambiguous=false;
        foreach ($events as $event) {
            $amount=Money::fromSignedLedger((string)$event['amount_cents']);
            if ('initial_charge'===$event['kind']
                && $amount>0 && $event['provider']===$method) {
                ++$count;
            } else {
                $ambiguous=true;
            }
        }
        if ($count>1) { $ambiguous=true; }
        if ('mercado_pago'===$method) {
            $attempts=$tx->rows(
                'SELECT state FROM %i WHERE order_id=%s '
                    .'ORDER BY id LIMIT 51',
                [Orders_Domain::table('pc_payment_attempts'),$orderId]
            );
            if (\count($attempts)>50) { $ambiguous=true; }
            $approved=false;
            foreach ($attempts as $a) {
                if ('APROBADO'===$a['state']) { $approved=true; }
                elseif ('REQUIERE_CONCILIACION'===$a['state']) {
                    $ambiguous=true;
                }
            }
            if ($approved && 0===$count
                || 1===$count && !$approved) {
                $ambiguous=true;
            }
        } elseif ('cash'!==$method) {
            $ambiguous=true;
        }
        return ['ever_credited'=>$count>0,'ambiguous'=>$ambiguous];
    }

    /** Obligación comercial real, no dinero entregado al comprador. */
    private static function creditWithin(
        Transaction_Context $tx,string $operationId,string $orderId,
        string $revisionId,int $oldFee,int $newFee,
        array $order,array $body,array $payment
    ): void {
        if ('A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_orders_financial_adjust')) {
            throw new Transaction_Failure('pc_delivery_change_denied');
        }
        $adjustments=$tx->rows(
            'SELECT delta_cents FROM %i WHERE order_id=%s '
                .'ORDER BY id LIMIT 201',
            [Orders_Domain::table('pc_order_adjustments'),$orderId]
        );
        if (\count($adjustments)>200) {
            throw new Transaction_Failure('pc_delivery_change_money_review');
        }
        $due=$order['total'];
        foreach ($adjustments as $a) {
            $due=Money::addChecked(
                $due,Money::fromSignedLedger((string)$a['delta_cents'])
            );
        }
        $delta=$newFee-$oldFee;
        $after=Money::addChecked($due,$delta);
        if ($delta>=0 || $after<0 || $after>$order['total']) {
            throw new Transaction_Failure('pc_delivery_change_money_review');
        }
        $basis=self::encode([
            'type'=>'delivery_fee_reduction',
            'revision_id'=>$revisionId,
            'previous_revision_id'=>$order['revision_id'],
            'old_fee_cents'=>(string)$oldFee,
            'new_fee_cents'=>(string)$newFee,
            'delta_cents'=>Money::toLedger($delta),
            'obligation_before_cents'=>(string)$due,
            'obligation_after_cents'=>(string)$after,
            'historically_credited'=>$payment['ever_credited'],
            'approved_by_user_id'=>(string)\get_current_user_id(),
            'reason'=>$body['reason'],
        ],6500);
        if (1!==$tx->execute(
            'INSERT INTO %i (order_id,order_line_id,case_id,reason_code,'
                .'delta_cents,approved_by,original_basis_snapshot,'
                .'created_at,idempotency_id) VALUES '
                .'(%s,NULL,NULL,%s,%s,%s,%s,UTC_TIMESTAMP(6),%s)',
            [Orders_Domain::table('pc_order_adjustments'),$orderId,
                'delivery_fee_reduced',Money::toLedger($delta),
                (string)\get_current_user_id(),$basis,$operationId]
        )) {
            throw new Transaction_Failure('pc_delivery_change_conflict');
        }
    }

    private static function insertRevisionWithin(
        Transaction_Context $tx,string $orderId,?string $previous,
        int $revisionNo,string $ubigeo,int $fee,
        array $snapshot,string $decision
    ): string {
        $snapshotJson=self::encode($snapshot,3072);
        if (self::PROPOSE===$decision) {
            $sql='INSERT INTO %i (order_id,previous_revision_id,revision_number,'
                .'ubigeo_code,address_snapshot,fee_cents,approved_by,'
                .'approved_at,created_at,case_id) VALUES '
                .'(%s,'.(null===$previous?'NULL':'%s')
                .',%d,%s,%s,%s,NULL,NULL,UTC_TIMESTAMP(6),NULL)';
            $args=\array_merge(
                [Orders_Domain::table('pc_delivery_revisions'),$orderId],
                null===$previous?[]:[$previous],
                [$revisionNo,$ubigeo,$snapshotJson,
                 Money::toApi($fee)]
            );
        } else {
            $sql='INSERT INTO %i (order_id,previous_revision_id,revision_number,'
                .'ubigeo_code,address_snapshot,fee_cents,approved_by,'
                .'approved_at,created_at,case_id) VALUES '
                .'(%s,'.(null===$previous?'NULL':'%s')
                .',%d,%s,%s,%s,%s,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)';
            $args=\array_merge(
                [Orders_Domain::table('pc_delivery_revisions'),$orderId],
                null===$previous?[]:[$previous],
                [$revisionNo,$ubigeo,$snapshotJson,Money::toApi($fee),
                    (string)\get_current_user_id()]
            );
        }
        if (1!==$tx->execute($sql,$args)) {
            throw new Transaction_Failure('pc_delivery_change_conflict');
        }
        $rows=$tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_delivery_change_invariant');
        }
        return Domain_Validation::sqlId((string)$rows[0]['id']);
    }

    /** Nunca persistir un enlace o nota cruda en logs/audit general. */
    private static function encode(array $input,int $max=3072): string
    {
        $raw=\json_encode($input,
            \JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE|\JSON_THROW_ON_ERROR);
        if (!\is_string($raw) || \strlen($raw)>$max
            || !\mb_check_encoding($raw,'UTF-8')) {
            throw new Transaction_Failure('pc_delivery_change_invariant');
        }
        return $raw;
    }

    private static function input(mixed $body): array
    {
        if (!\is_array($body) || \array_is_list($body)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $fields=['new_ubigeo','new_address','reason',
            'expected_version','idempotency_key'];
        $keys=\array_keys($body);
        \sort($keys,\SORT_STRING);
        \sort($fields,\SORT_STRING);
        if ($keys!==$fields) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try {
            if (!\is_string($body['new_ubigeo'])
                || !\is_string($body['expected_version'])
                || !\is_string($body['idempotency_key'])) {
                throw new \InvalidArgumentException();
            }
            Ubigeo_Catalog::code($body['new_ubigeo']);
            Domain_Validation::sqlId($body['expected_version']);
            self::text($body['new_address'],500);
            self::text($body['reason'],1000);
            if (\strlen($body['idempotency_key'])<16
                || \strlen($body['idempotency_key'])>128
                || !\mb_check_encoding($body['idempotency_key'],'UTF-8')
                || \preg_match('/[\x00-\x20\x7F]/',
                    $body['idempotency_key'])) {
                throw new \InvalidArgumentException();
            }
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return $body;
    }

    private static function text(mixed $value,int $max): void
    {
        if (!\is_string($value) || ''===\trim($value)
            || !\mb_check_encoding($value,'UTF-8')
            || \mb_strlen($value,'UTF-8')>$max
            || \preg_match('/[\x00-\x1F\x7F]/u',$value)) {
            throw new \InvalidArgumentException();
        }
    }

    private static function decision(?string $role): ?string
    {
        if ($role!==Access_Roles::currentRole()
            || !\in_array($role,['A','O'],true)
            || (int)\get_current_blog_id()<1
            || (\function_exists('is_network_admin')&&\is_network_admin())
            || !Module_Gates::allows('delivery','change')) {
            return null;
        }
        if ('A'===$role
            && \current_user_can('perucommerce_orders_financial_adjust')) {
            return self::APPROVE;
        }
        return \current_user_can('perucommerce_orders_fulfill')
            ? self::PROPOSE : null;
    }

    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $orderId,array $body
    ): array {
        if ('delivery_revision'!==($claim['entity_type']??null)
            || !\is_string($claim['entity_id']??null)
            || 200!==($claim['response_code']??null)) {
            throw new Transaction_Failure('pc_delivery_change_conflict');
        }
        $revisionId=Domain_Validation::sqlId($claim['entity_id']);
        $rows=$tx->rows(
            'SELECT id,ubigeo_code,address_snapshot,approved_by '
                .'FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
            [Orders_Domain::table('pc_delivery_revisions'),
                $revisionId,$orderId]
        );
        if (1!==\count($rows)
            || $rows[0]['ubigeo_code']!==$body['new_ubigeo']) {
            throw new Transaction_Failure('pc_delivery_change_conflict');
        }
        $snap=Orders_Domain::decode((string)$rows[0]['address_snapshot'],3072);
        if (($snap['address']??null)!==$body['new_address']
            || ($snap['reason']??null)!==$body['reason']) {
            throw new Transaction_Failure('pc_delivery_change_conflict');
        }
        $audits=$tx->rows(
            'SELECT operation_code FROM %i WHERE entity_type=%s '
                .'AND entity_id=%s AND request_id=UNHEX(%s) '
                .'AND actor_kind=%s AND actor_user_id=%s '
                .'AND operation_code IN (%s,%s) LIMIT 2',
            [Orders_Domain::table('pc_audit_log'),
                'order',$orderId,
                Event_Journal::correlationHex($claim['operation_id']),
                'staff',(string)\get_current_user_id(),
                'delivery_change_proposed','delivery_change_approved']
        );
        if (1!==\count($audits)) {
            throw new Transaction_Failure('pc_delivery_change_conflict');
        }
        $decision=$audits[0]['operation_code']
            ==='delivery_change_approved'
                ? self::APPROVE : self::PROPOSE;
        if ((null===$rows[0]['approved_by'])!==(self::PROPOSE===$decision)) {
            throw new Transaction_Failure('pc_delivery_change_conflict');
        }
        return self::result($claim['operation_id'],$revisionId,
            Settings_Store::nextVersion($body['expected_version']),$decision);
    }

    private static function result(
        string $op,string $revisionId,string $version,string $decision
    ): array {
        return [
            'operation_id'=>$op,
            'entity_id'=>$revisionId,
            'row_version'=>$version,
            'result'=>self::PROPOSE===$decision
                ? 'delivery_change_proposed':'delivery_change_approved',
            // El estado del pedido NO cambia: las etiquetas de
            // propuesta/aprobación pertenecen únicamente a "result".
            'public_status'=>'CONFIRMADO',
        ];
    }
}
