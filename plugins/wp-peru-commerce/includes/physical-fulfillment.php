<?php
/**
 * IMP-37 — transiciones F-02…F-06 de la línea física (R8-22).
 *
 * Bloqueos: raíz idempotente → pedido → línea → vendible → compromiso.
 * Sólo F-04 despacho y F-05 recojo registran salida del stock vigente.
 * F-06 recepción posterior a despacho NO vuelve a descontar unidades.
 * No cambia dinero, agenda, tarifas, casos, derechos ni pedido original.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Physical_Fulfillment
{
    private const OPERATION='physical_fulfillment';
    private const TRANSITIONS=[
        'PENDIENTE'=>['PREPARANDO','LISTO'],
        'PREPARANDO'=>['LISTO'],
        'LISTO'=>['DESPACHADO','ENTREGADO'],
        'DESPACHADO'=>['ENTREGADO'],
    ];
    private const EVENTS=[
        'PREPARANDO'=>'physical_preparing',
        'LISTO'=>'physical_ready',
        'DESPACHADO'=>'physical_dispatched',
        'ENTREGADO'=>'physical_delivered',
    ];

    /** Guard en lectura: objeto propio, rol y forma; conflictos al handler. */
    public static function guard(array $ctx,?string $role): array
    {
        if (!\in_array($role,['A','O'],true)
            || Access_Roles::currentRole()!==$role
            || !\current_user_can('perucommerce_orders_fulfill')
            || (int)\get_current_blog_id()<1
            || !Module_Gates::allows('physical','fulfill')) {
            return [];
        }
        try {
            $orderId=Domain_Validation::sqlId($ctx['path']['id']??'');
            $lineId=Domain_Validation::sqlId($ctx['path']['line_id']??'');
            self::input($ctx['body']??null);
            $found=Transaction::run(
                static function(Transaction_Context $tx) use($orderId,$lineId):bool {
                    $rows=$tx->rows(
                        'SELECT l.id,l.kind,l.fulfillment_state,o.status '
                            .'FROM %i l INNER JOIN %i o ON o.id=l.order_id '
                            .'WHERE l.id=%s AND l.order_id=%s LIMIT 2',
                        [Orders_Domain::table('pc_order_lines'),
                            Orders_Domain::table('pc_orders'),$lineId,$orderId]
                    );
                    return 1===\count($rows)
                        && 'physical'===$rows[0]['kind']
                        && \in_array((string)$rows[0]['fulfillment_state'],
                            Orders_Domain::LINE_STATES['physical'],true)
                        && \in_array((string)$rows[0]['status'],
                            Orders_Domain::STATES,true);
                }
            );
            if (!$found) { return []; }
            return [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,
            ];
        } catch (\Throwable $ignored) { return []; }
    }

    /** @return array<string,string> */
    public static function change(
        string $orderId,string $lineId,array $body
    ): array {
        self::staff();
        $orderId=Domain_Validation::sqlId($orderId);
        $lineId=Domain_Validation::sqlId($lineId);
        $body=self::input($body);
        $actor=Event_Actor::staff(['perucommerce_orders_fulfill']);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'order_line',
            'resource_id'=>$lineId,
            'purpose'=>self::OPERATION,
        ];
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $orderId,$lineId,$body,$scope,$actor
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OPERATION,$body['idempotency_key'],
                    \array_merge(['order_id'=>$orderId,'line_id'=>$lineId],$body)
                );
                if (!\in_array($claim['status'],['new','replay'],true)) {
                    throw new Transaction_Failure('pc_physical_conflict');
                }
                $order=$tx->lockOrder($orderId);
                $line=$tx->lockOrderLine($orderId,$lineId);
                if ((string)$line['order_id']!==$orderId
                    || 'physical'!==(string)$line['kind']) {
                    throw new Transaction_Failure('pc_physical_denied');
                }
                // Replay auténtico NO reevalúa el estado actual para volver
                // a enviar avisos, ni vuelve a modificar cantidad o dinero.
                if ('replay'===$claim['status']) {
                    return self::replayWithin($tx,$claim,$lineId,$body);
                }

                if ('CONFIRMADO'!==(string)$order['status']
                    || (string)$line['row_version']!==$body['expected_version']
                    || !self::transition(
                        (string)$line['fulfillment_state'],$body['target']
                    )) {
                    throw new Transaction_Failure('pc_physical_conflict');
                }
                $mode=self::modeWithin($tx,$orderId);
                if ($body['target']==='DESPACHADO' && $mode!=='delivery'
                    || $body['target']==='ENTREGADO' && (
                        ('LISTO'===$line['fulfillment_state']
                            && $mode!=='pickup')
                        || ('DESPACHADO'===$line['fulfillment_state']
                            && $mode!=='delivery')
                    )) {
                    throw new Transaction_Failure('pc_physical_conflict');
                }
                if (\in_array($body['target'],['DESPACHADO','ENTREGADO'],true)
                    && !isset($body['evidence'])) {
                    throw new Transaction_Failure('pc_physical_evidence_required');
                }

                $sellableId=Domain_Validation::sqlId(
                    (string)$line['sellable_id']
                );
                $tx->lockSellables([$sellableId]);
                $commitment=$tx->lockStockCommitment($lineId,$sellableId);
                $counts=Inventory_Reservations::physicalQuantitiesWithin(
                    $tx,$orderId,$lineId,$sellableId
                );
                $originalRows=$tx->rows(
                    'SELECT quantity FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
                    [Orders_Domain::table('pc_order_lines'),$lineId,$orderId]
                );
                if (1!==\count($originalRows)) {
                    throw new Transaction_Failure('pc_physical_invariant_invalid');
                }
                $original=Orders_Domain::quantity(
                    (string)$originalRows[0]['quantity']
                );
                if ($counts['committed_units']<1
                    || $counts['committed_units']>$original
                    || $counts['released_units']+$counts['stock_out_units']
                        >$counts['committed_units']
                    || (string)$commitment['order_line_id']!==$lineId
                    || (string)$commitment['sellable_id']!==$sellableId) {
                    throw new Transaction_Failure('pc_physical_invariant_invalid');
                }
                $outbound=$body['target']==='DESPACHADO'
                    || ($body['target']==='ENTREGADO'
                        && 'LISTO'===(string)$line['fulfillment_state']);
                if ($outbound) {
                    if ($counts['pending_stock_out_units']<1
                        || $counts['stock_out_units']!==0) {
                        throw new Transaction_Failure('pc_physical_conflict');
                    }
                    $next=Inventory_Reservations::stockOutWithin(
                        $tx,$actor,$claim['operation_id'],
                        $orderId,$lineId,$sellableId,
                        'DESPACHADO'===$body['target']?'dispatch':'pickup',
                        $body['expected_version']
                    );
                } else {
                    // Preparación/listo: compromiso todavía retenido y
                    // no enviado; recepción F-06: ya salió, sin pendiente.
                    if ($body['target']==='ENTREGADO') {
                        if ($counts['pending_stock_out_units']!==0
                            || $counts['stock_out_units']<1) {
                            throw new Transaction_Failure('pc_physical_conflict');
                        }
                    } elseif ($counts['pending_stock_out_units']<1
                        || $counts['stock_out_units']!==0) {
                        throw new Transaction_Failure('pc_physical_conflict');
                    }
                    $next=$tx->advanceVersion(
                        'pc_order_lines',$lineId,$body['expected_version']
                    );
                }
                if (1!==$tx->execute(
                    'UPDATE %i SET fulfillment_state=%s '
                        .'WHERE id=%s AND order_id=%s AND kind=%s '
                        .'AND fulfillment_state=%s AND row_version=%s',
                    [Orders_Domain::table('pc_order_lines'),
                        $body['target'],$lineId,$orderId,'physical',
                        (string)$line['fulfillment_state'],$next]
                )) {
                    throw new Transaction_Failure('pc_physical_conflict');
                }

                $evidenceHash=isset($body['evidence'])
                    ? \hash('sha256',$body['evidence']) : null;
                $outAfter=$counts['stock_out_units']
                    + ($outbound ? $counts['pending_stock_out_units'] : 0);
                Event_Journal::auditWithin(
                    $tx,$actor,$claim['operation_id'],'order_line',$lineId,
                    self::EVENTS[$body['target']],'physical_fulfillment',
                    \hash('sha256',\implode(':',[
                        (string)$line['fulfillment_state'],
                        $body['expected_version'],
                        (string)$counts['pending_stock_out_units']
                    ])),
                    \hash('sha256',\implode(':',[
                        $body['target'],$next,
                        (string)$outAfter,
                        $evidenceHash??'none'
                    ]))
                );
                // PANEL + EMAIL si habilitado, con snapshot de preferencia
                // del comercio en esta misma transacción; ningún envío SMTP.
                Queue_Outbox::routinePhysicalWithin(
                    $tx,$claim['operation_id'],$orderId,$lineId,$body['target']
                );
                Orders_Finalization::afterWithin(
                    $tx,$actor,$claim['operation_id'],$orderId
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'order_line',$lineId,200
                );
                return self::result($claim['operation_id'],$lineId,$body['target'],$next);
            },true
        );
    }

    /** Sólo se devuelve el resultado de ESTA operación, sin otro efecto. */
    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $lineId,array $body
    ): array {
        if ('order_line'!==($claim['entity_type']??null)
            || $lineId!==($claim['entity_id']??null)
            || 200!==($claim['response_code']??null)) {
            throw new Transaction_Failure('pc_physical_conflict');
        }
        $events=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s AND actor_kind=%s '
                .'AND actor_user_id=%s AND request_id=UNHEX(%s) LIMIT 2',
            [Orders_Domain::table('pc_audit_log'),
                'order_line',$lineId,self::EVENTS[$body['target']],
                'staff',(string)\get_current_user_id(),
                Event_Journal::correlationHex($claim['operation_id'])]
        );
        if (1!==\count($events)) {
            throw new Transaction_Failure('pc_physical_conflict');
        }
        return self::result(
            $claim['operation_id'],$lineId,$body['target'],
            Settings_Store::nextVersion($body['expected_version'])
        );
    }

    private static function modeWithin(
        Transaction_Context $tx,string $orderId
    ): string {
        $rows=$tx->rows(
            'SELECT original_delivery_snapshot,current_delivery_revision_id '
                .'FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_physical_invariant_invalid');
        }
        $delivery=Orders_Domain::deliverySnapshot(
            $rows[0]['original_delivery_snapshot']
        );
        $mode=$delivery['mode']??null;
        if (!\in_array($mode,['pickup','delivery'],true)) {
            throw new Transaction_Failure('pc_physical_invariant_invalid');
        }
        if (null!==$rows[0]['current_delivery_revision_id']) {
            if ('delivery'!==$mode) {
                throw new Transaction_Failure('pc_physical_invariant_invalid');
            }
            $revision=$tx->rows(
                'SELECT id FROM %i WHERE id=%s AND order_id=%s '
                    .'AND approved_at IS NOT NULL LIMIT 2',
                [Orders_Domain::table('pc_delivery_revisions'),
                    (string)$rows[0]['current_delivery_revision_id'],$orderId]
            );
            if (1!==\count($revision)) {
                throw new Transaction_Failure('pc_physical_invariant_invalid');
            }
        }
        return $mode;
    }

    private static function transition(string $previous,string $target): bool
    {
        return \in_array($target,self::TRANSITIONS[$previous]??[],true);
    }

    /** RestValidator verifica el OpenAPI; el dominio comprueba semántica. */
    private static function input(mixed $body): array
    {
        if (!\is_array($body) || \array_is_list($body)
            || \count($body)<3 || \count($body)>4
            || !\is_string($body['target']??null)
            || !isset(self::EVENTS[$body['target']])
            || !\is_string($body['expected_version']??null)
            || !\is_string($body['idempotency_key']??null)
            || \strlen($body['idempotency_key'])<16
            || \strlen($body['idempotency_key'])>128
            || !\mb_check_encoding($body['idempotency_key'],'UTF-8')
            || \preg_match('/[\x00-\x20\x7F]/u',$body['idempotency_key'])
            || []!==\array_diff(\array_keys($body),
                ['target','expected_version','idempotency_key','evidence'])
            || []!==\array_diff(['target','expected_version','idempotency_key'],
                \array_keys($body))) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        Domain_Validation::sqlId($body['expected_version']);
        if (\array_key_exists('evidence',$body)) {
            $e=$body['evidence'];
            if (!\is_string($e) || ''===\trim($e)
                || !\mb_check_encoding($e,'UTF-8')
                || \mb_strlen($e,'UTF-8')>500
                || \preg_match('/[\x00-\x1F\x7F]/u',$e)
                || \str_contains($e,'://')) {
                throw new Rest_Failure('pc_field_invalid',422,false,'evidence');
            }
        }
        return $body;
    }

    private static function staff(): void
    {
        if (!\in_array(Access_Roles::currentRole(),['A','O'],true)
            || !\current_user_can('perucommerce_orders_fulfill')
            || (int)\get_current_blog_id()<1
            || !Module_Gates::allows('physical','fulfill')) {
            throw new Transaction_Failure('pc_physical_denied');
        }
    }

    private static function result(
        string $operationId,string $lineId,string $target,string $version
    ): array {
        return [
            'operation_id'=>$operationId,'entity_id'=>$lineId,
            'row_version'=>$version,'result'=>'updated',
            'public_status'=>$target,
        ];
    }
}
