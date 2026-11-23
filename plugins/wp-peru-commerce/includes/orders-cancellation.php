<?php
/**
 * IMP-39 — cancelación ordinaria de línea completa R8-23.
 *
 * Una solicitud no autoriza devolución ni reingreso de stock físico salido.
 * D-64: operador sólo sin acreditación HISTÓRICA y sin efecto monetario.
 * El ajuste comercial es distinto del reintegro externo comprobado.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Cancellation
{
    private const OP = 'order_line_cancel';
    private const ELIGIBLE=[
        'physical'=>['PENDIENTE','PREPARANDO','LISTO'],
        'service'=>['CONFIRMADA'],
    ];

    public static function guard(array $ctx,?string $role): array
    {
        if (!self::staffAllowed($role)) { return []; }
        try {
            $orderId=Domain_Validation::sqlId($ctx['path']['id']??'');
            $lineId=Domain_Validation::sqlId($ctx['path']['line_id']??'');
            $body=self::input($ctx['body']??null);
            $record=Transaction::run(
                static function(Transaction_Context $tx) use($orderId,$lineId):array {
                    $rows=$tx->rows(
                        'SELECT l.kind,l.fulfillment_state,l.row_version,'
                        .'o.status,o.payment_method FROM %i l '
                        .'INNER JOIN %i o ON o.id=l.order_id '
                        .'WHERE l.order_id=%s AND l.id=%s LIMIT 2',
                        [Orders_Domain::table('pc_order_lines'),
                         Orders_Domain::table('pc_orders'),$orderId,$lineId]
                    );
                    if (1!==\count($rows)) {
                        throw new Transaction_Failure('pc_cancel_denied');
                    }
                    $history=self::financialHistoryWithin($tx,$orderId);
                    $remaining=self::remainingWithin($tx,$orderId,$lineId);
                    return [
                        'line'=>$rows[0],
                        'money'=>$history,
                        'remaining'=>$remaining,
                    ];
                }
            );
            $line=$record['line'];
            $eligible=self::eligible((string)$line['kind'],
                (string)$line['fulfillment_state'])
                && 'CONFIRMADO'===(string)$line['status'];
            $money=$record['money'];
            $financialRequired=$money['ever_credited']
                || $money['has_adjustments']
                || $record['remaining']>0;
            $scope=self::scope($lineId);
            $exists=Idempotency::keyExistsForScope(
                $scope,self::OP,$body['idempotency_key'],
                self::payload($orderId,$lineId,$body)
            );
            if (!$exists && (!$eligible
                || $body['expected_version']!==(string)$line['row_version'])) {
                return [];
            }
            $allowed='A'===$role
                ? \current_user_can('perucommerce_orders_financial_adjust')
                    || (!$financialRequired
                        && \current_user_can('perucommerce_orders_cancel_unpaid'))
                : (!$money['ever_credited'] && !$financialRequired
                    && \current_user_can('perucommerce_orders_cancel_unpaid'));
            if (!$allowed) { return []; }
            return [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,
                'pending_eligible_line'=>true,
                'ever_credited'=>$money['ever_credited'],
                'financial_adjustment_required'=>$financialRequired,
            ];
        } catch (\Throwable $ignored) { return []; }
    }

    public static function cancel(
        string $orderId,string $lineId,array $body
    ): array {
        $role=Access_Roles::currentRole();
        if (!self::staffAllowed($role)) {
            throw new Transaction_Failure('pc_cancel_denied');
        }
        $orderId=Domain_Validation::sqlId($orderId);
        $lineId=Domain_Validation::sqlId($lineId);
        $body=self::input($body);
        $actor=Event_Actor::staff([
            'A'===$role && \current_user_can('perucommerce_orders_financial_adjust')
                ? 'perucommerce_orders_financial_adjust'
                : 'perucommerce_orders_cancel_unpaid'
        ]);
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $orderId,$lineId,$body,$role,$actor
            ):array {
                $scope=self::scope($lineId);
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OP,$body['idempotency_key'],
                    self::payload($orderId,$lineId,$body)
                );
                if (!\in_array($claim['status'],['new','replay'],true)) {
                    throw new Transaction_Failure('pc_cancel_conflict');
                }
                $order=$tx->lockOrder($orderId);
                $line=$tx->lockOrderLine($orderId,$lineId);
                if ('replay'===$claim['status']) {
                    return self::replayWithin(
                        $tx,$claim,$lineId,$body
                    );
                }
                if ('CONFIRMADO'!==(string)$order['status']
                    || (string)$line['row_version']!==$body['expected_version']
                    || !self::eligible(
                        (string)$line['kind'],(string)$line['fulfillment_state']
                    )) {
                    throw new Transaction_Failure('pc_cancel_conflict');
                }
                if (isset($body['case_id'])) {
                    // El expediente es una finalidad diferente (R8-12
                    // guest, R8-55 logística o R8-69 parcial). Sin ruta
                    // que resuelva esa clase, no consumirlo por ID solo.
                    throw new Transaction_Failure('pc_cancel_case_unverified');
                }
                $history=self::financialHistoryWithin($tx,$orderId);
                $others=self::remainingWithin($tx,$orderId,$lineId);
                $hasFinancialEffect=$history['ever_credited']
                    || $history['has_adjustments'] || $others>0;
                if ('O'===$role && (
                        $history['ever_credited'] || $hasFinancialEffect
                        || !\current_user_can('perucommerce_orders_cancel_unpaid')
                    ) || 'A'===$role && $hasFinancialEffect
                        && !\current_user_can('perucommerce_orders_financial_adjust')) {
                    throw new Transaction_Failure('pc_cancel_denied');
                }
                if ($history['ambiguous']) {
                    // Cualquier pago incierto, reembolso o contracargo
                    // requiere conciliación separada antes de liberar.
                    throw new Transaction_Failure('pc_cancel_money_unresolved');
                }
                $orderData=self::orderWithin($tx,$orderId);
                $kind=(string)$line['kind'];
                if ('physical'===$kind) {
                    $step=self::physicalWithin(
                        $tx,$actor,$claim['operation_id'],$orderId,
                        $lineId,$line,$body,$orderData,$others
                    );
                } else {
                    $step=self::serviceWithin(
                        $tx,$actor,$claim['operation_id'],$orderId,
                        $lineId,$line,$body,$orderData
                    );
                }
                $adjustCents=$step['adjust_cents'];
                // Un importe comercial aprobado NO implica dinero
                // devuelto. R8-27 acredita exclusivamente el resultado
                // externo comprobado; ninguna devolución API.
                if ($hasFinancialEffect) {
                    if ('A'!==$role || null===$adjustCents
                        || $adjustCents>=0) {
                        throw new Transaction_Failure('pc_cancel_money_unresolved');
                    }
                    self::adjustWithin(
                        $tx,$claim['operation_id'],$orderId,$lineId,
                        $adjustCents,$body,$step['basis']
                    );
                }
                $newVersion=$step['line_version'];
                $oldState=(string)$line['fulfillment_state'];
                $newState='service'===$kind?'CANCELADA':'CANCELADO';
                if (1!==$tx->execute(
                    'UPDATE %i SET fulfillment_state=%s WHERE '
                        .'id=%s AND order_id=%s AND kind=%s '
                        .'AND fulfillment_state=%s AND row_version=%s',
                    [Orders_Domain::table('pc_order_lines'),
                        $newState,$lineId,$orderId,$kind,$oldState,
                        $newVersion]
                )) {
                    throw new Transaction_Failure('pc_cancel_conflict');
                }
                // Si es la última línea cancelable y jamás hubo dinero,
                // O-06 cierra pedido sin un reintegro inventado.
                if (0===$others && !$history['ever_credited']) {
                    $currentOrder=$tx->lockedOrder($orderId);
                    if (1!==$tx->execute(
                        'UPDATE %i SET status=%s,closed_at=UTC_TIMESTAMP(6) '
                            .'WHERE id=%s AND status=%s AND row_version=%s',
                        [Orders_Domain::table('pc_orders'),
                            'CANCELADO',$orderId,'CONFIRMADO',
                            (string)$currentOrder['row_version']]
                    )) {
                        throw new Transaction_Failure('pc_cancel_conflict');
                    }
                    $tx->advanceVersion('pc_orders',$orderId,
                        (string)$currentOrder['row_version']);
                } else {
                    $tx->advanceVersion('pc_orders',$orderId,
                        (string)$order['row_version']);
                }
                Event_Journal::auditWithin(
                    $tx,$actor,$claim['operation_id'],'order_line',$lineId,
                    'line_cancelled', 'eligible_cancellation',
                    \hash('sha256',$oldState.':'.$body['expected_version']),
                    \hash('sha256',$newState.':'.$newVersion.':'
                        .\hash('sha256',$body['reason']))
                );
                Queue_Outbox::essentialWithin(
                    $tx,$actor,$claim['operation_id'],$orderId,
                    'line_cancelled',$lineId
                );
                if('service'===$kind){
                    Queue_Outbox::serviceCancelledWithin(
                        $tx,$actor,$claim['operation_id'],$orderId,
                        $lineId,(string)$step['basis']['appointment_id']
                    );
                }
                Orders_Finalization::afterWithin(
                    $tx,$actor,$claim['operation_id'],$orderId
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'order_line',$lineId,200
                );
                return self::result(
                    $claim['operation_id'],$lineId,$newVersion,$newState
                );
            },true
        );
    }

    /** Salida física previa prohíbe liberar. No reposición inventada. */
    private static function physicalWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $orderId,string $lineId,array $line,array $body,
        array $order,int $others
    ): array {
        if (!Module_Gates::allows('inventory','write')) {
            throw new Transaction_Failure('pc_cancel_not_ready');
        }
        $sellableId=Domain_Validation::sqlId((string)$line['sellable_id']);
        $tx->lockSellables([$sellableId]);
        $counts=Inventory_Reservations::physicalQuantitiesWithin(
            $tx,$orderId,$lineId,$sellableId
        );
        if ($counts['stock_out_units']!==0
            || $counts['pending_stock_out_units']<1
            || $counts['committed_units']<1) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        // Última línea física de un envío: la tarifa no tiene regla
        // universal de refund. No decidir legalmente el cargo por
        // inferencia o texto aportado; fail-closed hasta expediente A.
        $lastPhysical=self::lastPhysicalWithin($tx,$orderId,$lineId);
        if ($lastPhysical
            && 'delivery'===$order['delivery_mode']
            && $order['delivery_cents']>0) {
            throw new Transaction_Failure('pc_cancel_delivery_review');
        }
        $pending=$counts['pending_stock_out_units'];
        // No reusar quantity original después de ajustes parciales.
        $version=Inventory_Reservations::releasePendingWithin(
            $tx,$actor,$operationId,$orderId,$lineId,$sellableId,
            $pending,'cancel_line',$body['expected_version']
        );
        // Si existe reducción histórica previa, solo una evaluación A
        // de R8-26 puede determinar importe final correcto sin duplicar.
        $already=self::adjustmentsForLineWithin($tx,$orderId,$lineId);
        if ([]!==$already) {
            throw new Transaction_Failure('pc_cancel_money_unresolved');
        }
        $units=$tx->rows(
            'SELECT unit_price_cents FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
            [Orders_Domain::table('pc_order_lines'),$lineId,$orderId]
        );
        if (1!==\count($units)) {
            throw new Transaction_Failure('pc_cancel_invariant_invalid');
        }
        $unit=Money::fromApi((string)$units[0]['unit_price_cents']);
        $credit=Money::lineSubtotal($unit,$pending);
        return [
            'line_version'=>$version,
            'adjust_cents'=>-$credit,
            'basis'=>[
                'kind'=>'physical',
                'cancelled_pending_units'=>$pending,
                'released_before'=>$counts['released_units'],
                'original_state'=>(string)$line['fulfillment_state'],
                'last_physical'=>$lastPhysical,
                'delivery_review'=>'no_fee_or_not_last',
            ],
        ];
    }

    private static function serviceWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $orderId,string $lineId,array $line,array $body,
        array $order
    ): array {
        if ('CONFIRMADA'!==(string)$line['fulfillment_state']) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        if (!Module_Gates::allows('appointments','write')) {
            throw new Transaction_Failure('pc_cancel_not_ready');
        }
        $sellableId=Domain_Validation::sqlId((string)$line['sellable_id']);
        $tx->lockSellables([$sellableId]);
        $service=Checkout_Appointments::fromOrderLineWithin(
            $tx,$orderId,$lineId
        );
        // El snapshot de venta conserva la franja ORIGINAL incluso tras
        // R8-38. Para cancelar debe bloquearse la franja EFECTIVA actual,
        // no un horario histórico ya liberado.
        $current=$tx->rows(
            'SELECT id,starts_at_utc,ends_at_utc,local_timezone '
                .'FROM %i WHERE order_id=%s AND order_line_id=%s LIMIT 2',
            [Agenda_Repository::table('pc_appointments'),$orderId,$lineId]
        );
        if (1!==\count($current)) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        $anchor=$tx->lockResource(Agenda_Time::RESOURCE);
        $zone=(string)$anchor['timezone'];
        if ((string)$current[0]['local_timezone']!==$zone
            || $service['slot']['timezone']!==$zone) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        $start=Business_Time::fromDatabase((string)$current[0]['starts_at_utc']);
        $end=Business_Time::fromDatabase((string)$current[0]['ends_at_utc']);
        // Días para locking acotados por duración pactada: una fila
        // corrupta no debe provocar enumeración ilimitada de fechas.
        $initialStart=Business_Time::fromApi($service['slot']['starts_at_utc']);
        $initialEnd=Business_Time::fromApi($service['slot']['ends_at_utc']);
        $contractDuration=$initialEnd->getTimestamp()-$initialStart->getTimestamp();
        if ($contractDuration<60 || $contractDuration>86400
            || 0!==$contractDuration%60
            || $end->getTimestamp()-$start->getTimestamp()!==$contractDuration) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        $from=Agenda_Time::addMinutes($start,-$service['before_minutes']);
        $to=Agenda_Time::addMinutes($end,$service['after_minutes']);
        $tx->lockCalendarDays(
            Agenda_Time::RESOURCE,
            Transaction_Context::datesTouched($from,$to,$zone)
        );
        $appointment=$tx->lockAppointmentForOrder($orderId);
        if (null===$appointment
            || (string)$appointment['order_line_id']!==$lineId
            || (string)$appointment['id']!==(string)$current[0]['id']
            || (string)$appointment['starts_at_utc']!==(string)$current[0]['starts_at_utc']
            || (string)$appointment['ends_at_utc']!==(string)$current[0]['ends_at_utc']) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        $row=$tx->rows(
            'SELECT id,cancelled_at,completed_at,no_show_at,row_version '
                .'FROM %i WHERE id=%s AND order_id=%s AND order_line_id=%s '
                .'FOR UPDATE',
            [Orders_Domain::table('pc_appointments'),
                (string)$appointment['id'],$orderId,$lineId]
        );
        $now=$tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
        if (1!==\count($row) || 1!==\count($now)
            || null!==$row[0]['cancelled_at']
            || null!==$row[0]['completed_at']
            || null!==$row[0]['no_show_at']
            || (string)$row[0]['row_version']
                !==(string)$appointment['row_version']
            || (string)$appointment['starts_at_utc']
                <=(string)$now[0]['now_utc']) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        $appointmentId=Domain_Validation::sqlId(
            (string)$appointment['id']
        );
        $av=$tx->advanceVersion(
            'pc_appointments',$appointmentId,
            (string)$appointment['row_version']
        );
        if (1!==$tx->execute(
            'UPDATE %i SET cancelled_at=UTC_TIMESTAMP(6) '
                .'WHERE id=%s AND order_id=%s AND cancelled_at IS NULL '
                .'AND completed_at IS NULL AND no_show_at IS NULL '
                .'AND row_version=%s',
            [Orders_Domain::table('pc_appointments'),
                $appointmentId,$orderId,$av]
        )) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        Event_Journal::auditWithin(
            $tx,$actor,$operationId,'appointment',$appointmentId,
            'appointment_cancelled','line_cancelled',
            \hash('sha256',(string)$appointment['starts_at_utc']),
            \hash('sha256',$av.':'.$lineId)
        );
        if ([]!==self::adjustmentsForLineWithin($tx,$orderId,$lineId)) {
            throw new Transaction_Failure('pc_cancel_money_unresolved');
        }
        $amount=self::subtotalWithin($tx,$orderId,$lineId);
        $next=$tx->advanceVersion(
            'pc_order_lines',$lineId,$body['expected_version']
        );
        return [
            'line_version'=>$next,
            'adjust_cents'=>-$amount,
            'basis'=>[
                'kind'=>'service',
                'original_state'=>(string)$line['fulfillment_state'],
                'appointment_id'=>$appointmentId,
                'appointment_version'=>$av,
            ],
        ];
    }

    private static function adjustWithin(
        Transaction_Context $tx,string $op,string $orderId,string $lineId,
        int $amount,array $body,array $basis
    ): void {
        if ($amount>=0
            || !\current_user_can('perucommerce_orders_financial_adjust')
            || 'A'!==Access_Roles::currentRole()) {
            throw new Transaction_Failure('pc_cancel_denied');
        }
        // Reconciliar el monto total de obligaciones ya ajustadas.
        // No permitir un ajuste que deje crédito comercial negativo.
        $root=$tx->rows(
            'SELECT total_cents FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        $existing=$tx->rows(
            'SELECT delta_cents FROM %i WHERE order_id=%s ORDER BY id LIMIT 201',
            [Orders_Domain::table('pc_order_adjustments'),$orderId]
        );
        if (1!==\count($root) || \count($existing)>200) {
            throw new Transaction_Failure('pc_cancel_money_unresolved');
        }
        $total=Money::fromApi((string)$root[0]['total_cents']);
        $current=$total;
        foreach ($existing as $entry) {
            $current=Money::addChecked(
                $current,Money::fromSignedLedger((string)$entry['delta_cents'])
            );
        }
        $after=Money::addChecked($current,$amount);
        if ($after<0 || $after>$total) {
            throw new Transaction_Failure('pc_cancel_money_unresolved');
        }

        $snapshot=\json_encode([
            'operation'=>'line_cancel',
            'basis'=>$basis,
            'reason'=>$body['reason'],
            'line_id'=>$lineId,
            'delta_cents'=>Money::toLedger($amount),
        ],\JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES|\JSON_THROW_ON_ERROR);
        if (!\is_string($snapshot) || \strlen($snapshot)>6500) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        if (1!==$tx->execute(
            'INSERT INTO %i (order_id,order_line_id,case_id,reason_code,'
                .'delta_cents,approved_by,original_basis_snapshot,'
                .'created_at,idempotency_id) VALUES '
                .'(%s,%s,NULL,%s,%s,%s,%s,UTC_TIMESTAMP(6),%s)',
            [Orders_Domain::table('pc_order_adjustments'),
                $orderId,$lineId,'line_cancelled',
                Money::toLedger($amount),
                (string)\get_current_user_id(),$snapshot,$op]
        )) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
    }

    private static function financialHistoryWithin(
        Transaction_Context $tx,string $orderId
    ): array {
        $events=$tx->rows(
            'SELECT kind,provider,amount_cents FROM %i WHERE order_id=%s '
                .'ORDER BY id LIMIT 201',
            [Orders_Domain::table('pc_payment_events'),$orderId]
        );
        if (\count($events)>200) {
            throw new Transaction_Failure('pc_cancel_money_unresolved');
        }
        $credited=false;$ambiguous=false;
        foreach ($events as $event) {
            $amount=Money::fromSignedLedger((string)$event['amount_cents']);
            if ('initial_charge'===$event['kind']
                && $amount>0
                && \in_array($event['provider'],['cash','mercado_pago'],true)) {
                $credited=true;
            } else {
                $ambiguous=true;
            }
        }
        // Un ajuste ya existente también prohíbe al operador la baja.
        $adjustments=$tx->rows(
            'SELECT id FROM %i WHERE order_id=%s LIMIT 1',
            [Orders_Domain::table('pc_order_adjustments'),$orderId]
        );
        return [
            'ever_credited'=>$credited,
            'ambiguous'=>$ambiguous,
            'has_adjustments'=>[]!==$adjustments,
        ];
    }

    /** Cualquier otra línea activa, no suponer que pedido es sólo físico. */
    private static function remainingWithin(
        Transaction_Context $tx,string $orderId,string $lineId
    ): int {
        $rows=$tx->rows(
            'SELECT kind,fulfillment_state FROM %i WHERE order_id=%s '
                .'AND id<>%s ORDER BY id LIMIT 101',
            [Orders_Domain::table('pc_order_lines'),$orderId,$lineId]
        );
        if (\count($rows)>100) {
            throw new Transaction_Failure('pc_cancel_invariant_invalid');
        }
        $remaining=0;
        foreach ($rows as $row) {
            $state=(string)$row['fulfillment_state'];
            if (!\in_array($state,
                ['CANCELADO','CANCELADA'],true)) {
                ++$remaining;
            }
        }
        return $remaining;
    }

    private static function lastPhysicalWithin(
        Transaction_Context $tx,string $orderId,string $lineId
    ): bool {
        $rows=$tx->rows(
            'SELECT id FROM %i WHERE order_id=%s AND kind=%s AND id<>%s '
                .'AND fulfillment_state<>%s LIMIT 1',
            [Orders_Domain::table('pc_order_lines'),
                $orderId,'physical',$lineId,'CANCELADO']
        );
        return []===$rows;
    }

    private static function subtotalWithin(
        Transaction_Context $tx,string $orderId,string $lineId
    ): int {
        $rows=$tx->rows(
            'SELECT subtotal_cents FROM %i WHERE id=%s AND order_id=%s',
            [Orders_Domain::table('pc_order_lines'),$lineId,$orderId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_cancel_invariant_invalid');
        }
        return Money::fromApi((string)$rows[0]['subtotal_cents']);
    }

    private static function adjustmentsForLineWithin(
        Transaction_Context $tx,string $orderId,string $lineId
    ): array {
        return $tx->rows(
            'SELECT id FROM %i WHERE order_id=%s AND order_line_id=%s LIMIT 1',
            [Orders_Domain::table('pc_order_adjustments'),$orderId,$lineId]
        );
    }

    private static function orderWithin(
        Transaction_Context $tx,string $orderId
    ): array {
        $rows=$tx->rows(
            'SELECT payment_method,delivery_cents,original_delivery_snapshot '
                .'FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_cancel_invariant_invalid');
        }
        $snap=Orders_Domain::deliverySnapshot(
            $rows[0]['original_delivery_snapshot']
        );
        return [
            'delivery_mode'=>$snap['mode']??'none',
            'delivery_cents'=>Money::fromApi(
                (string)$rows[0]['delivery_cents']
            ),
        ];
    }

    /** No aceptar razón como prueba suficiente de una devolución legal. */
    private static function input(mixed $body): array
    {
        if (!\is_array($body) || \array_is_list($body)
            || \count($body)<3 || \count($body)>4
            || []!==\array_diff(['expected_version','reason','idempotency_key'],
                \array_keys($body))
            || []!==\array_diff(\array_keys($body),[
                'expected_version','reason','idempotency_key','case_id'
            ])) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try {
            Domain_Validation::sqlId($body['expected_version']);
            if (isset($body['case_id'])) {
                Domain_Validation::sqlId($body['case_id']);
            }
            if (!\is_string($body['reason'])
                || ''===\trim($body['reason'])
                || !\mb_check_encoding($body['reason'],'UTF-8')
                || \mb_strlen($body['reason'],'UTF-8')>1000
                || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
                    $body['reason'])
                || !\is_string($body['idempotency_key'])
                || \strlen($body['idempotency_key'])<16
                || \strlen($body['idempotency_key'])>128
                || !\mb_check_encoding($body['idempotency_key'],'UTF-8')
                || \preg_match('/[\x00-\x20\x7F]/',
                    $body['idempotency_key'])) {
                throw new \UnexpectedValueException();
            }
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return $body;
    }

    private static function eligible(string $kind,string $state): bool
    {
        return \in_array($state,self::ELIGIBLE[$kind]??[],true);
    }

    private static function staffAllowed(?string $role): bool
    {
        return \in_array($role,['A','O'],true)
            && $role===Access_Roles::currentRole()
            && (int)\get_current_blog_id()>0
            && Module_Gates::allows('orders','cancel')
            && ('A'===$role
                ? (\current_user_can('perucommerce_orders_cancel_unpaid')
                    || \current_user_can('perucommerce_orders_financial_adjust'))
                : \current_user_can('perucommerce_orders_cancel_unpaid'));
    }

    private static function scope(string $lineId): array
    {
        return [
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'order_line',
            'resource_id'=>$lineId,
            'purpose'=>self::OP,
        ];
    }

    private static function payload(
        string $orderId,string $lineId,array $body
    ): array {
        return \array_merge([
            'order_id'=>$orderId,'line_id'=>$lineId,
        ],$body);
    }

    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $lineId,array $body
    ): array {
        if ('order_line'!==($claim['entity_type']??null)
            || $lineId!==($claim['entity_id']??null)
            || 200!==($claim['response_code']??null)) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        $audit=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s AND request_id=UNHEX(%s) '
                .'AND actor_kind=%s AND actor_user_id=%s LIMIT 2',
            [Orders_Domain::table('pc_audit_log'),'order_line',$lineId,
                'line_cancelled',Event_Journal::correlationHex($claim['operation_id']),
                'staff',(string)\get_current_user_id()]
        );
        if (1!==\count($audit)) {
            throw new Transaction_Failure('pc_cancel_conflict');
        }
        return self::result(
            $claim['operation_id'],$lineId,
            Settings_Store::nextVersion($body['expected_version']),
            'cancelled'
        );
    }

    private static function result(
        string $operationId,string $lineId,string $version,string $state
    ): array {
        return [
            'operation_id'=>$operationId,
            'entity_id'=>$lineId,
            'row_version'=>$version,
            'result'=>'cancelled',
            'public_status'=>$state,
        ];
    }
}
