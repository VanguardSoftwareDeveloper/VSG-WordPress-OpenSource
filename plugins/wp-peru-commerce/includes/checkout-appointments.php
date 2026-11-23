<?php
/**
 * IMP-25 — exclusión transaccional del recurso de servicio único.
 *
 * Los consumidores deben bloquear operación -> pedido -> líneas -> variantes
 * antes de esta clase. Aquí se toma recurso -> TODOS los días locales; nunca
 * se hace HTTP, correo o DDL. Los intervalos de admisión/hold INCLUYEN buffers,
 * mientras que pc_appointments conserva la hora de atención original.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Checkout_Appointments
{
    /** @var callable|null Validador real de fondos del consumidor MP (IMP-28/30). */
    private static $fundsVerifier = null;

    public static function registerFundsVerifier(callable $verifier): void
    {
        if (null !== self::$fundsVerifier) {
            throw new \LogicException('pc_checkout_funds_verifier_duplicate');
        }
        self::$fundsVerifier = $verifier;
    }

    /**
     * Adquiere las anclas estables para el intervalo más buffers. Requiere
     * línea/variante ya bloqueadas; el caller comprueba la misma cotización
     * autoritativa otra vez DESPUÉS de adquirir estas anclas.
     *
     * @return array{start:string,end:string,occupied_start:string,occupied_end:string,timezone:string}
     */
    public static function lockSlotWithin(
        Transaction_Context $tx, array $service
    ): array {
        $id = Domain_Validation::sqlId((string) ($service['sellable_id'] ?? ''));
        $sellable = $tx->lockedSellable($id);
        if ('service' !== (string) $sellable['kind']
            || !\is_array($service['slot'] ?? null)
            || !\is_string($service['slot']['starts_at_utc'] ?? null)
            || !\is_string($service['slot']['ends_at_utc'] ?? null)
            || !\is_string($service['slot']['timezone'] ?? null)) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
        $before = $service['before_minutes'] ?? null;
        $after = $service['after_minutes'] ?? null;
        if (!\is_int($before) || !\is_int($after)
            || $before < 0 || $before > 1440 || $after < 0 || $after > 1440) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
        $start = Business_Time::fromApi($service['slot']['starts_at_utc']);
        $end = Business_Time::fromApi($service['slot']['ends_at_utc']);
        Business_Time::requireInterval($start, $end);
        $zone = Business_Time::businessZone($service['slot']['timezone']);
        $occupiedStart = Agenda_Time::addMinutes($start, -$before);
        $occupiedEnd = Agenda_Time::addMinutes($end, $after);
        $anchor = $tx->lockResource(Agenda_Time::RESOURCE);
        if ((string) $anchor['timezone'] !== $zone->getName()) {
            throw new Transaction_Failure('pc_checkout_slot_changed');
        }
        $tx->lockCalendarDays(
            Agenda_Time::RESOURCE,
            Transaction_Context::datesTouched($occupiedStart, $occupiedEnd, $zone->getName())
        );
        return [
            'start'=>Business_Time::toDatabase($start),
            'end'=>Business_Time::toDatabase($end),
            'occupied_start'=>Business_Time::toDatabase($occupiedStart),
            'occupied_end'=>Business_Time::toDatabase($occupiedEnd),
            'timezone'=>$zone->getName(),
        ];
    }

    /** Snapshots históricos, nunca duración/buffers de catálogo ACTUAL. */
    public static function fromOrderLineWithin(
        Transaction_Context $tx, string $orderId, string $lineId
    ): array {
        $line = $tx->lockedOrderLine($lineId);
        if ((string) $line['order_id'] !== $orderId || 'service' !== (string) $line['kind']) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
        $rows = $tx->rows(
            'SELECT catalog_snapshot FROM %i WHERE id = %s AND order_id = %s',
            [Orders_Domain::table('pc_order_lines'),$lineId,$orderId]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
        $snap = Orders_Domain::catalogSnapshot((string) $rows[0]['catalog_snapshot'],'service');
        if (!\is_string($snap['service_start_utc'] ?? null)
            || !\is_string($snap['service_end_utc'] ?? null)
            || !\is_string($snap['service_timezone'] ?? null)) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
        return [
            'sellable_id'=>(string) $line['sellable_id'],
            'slot'=>[
                'starts_at_utc'=>$snap['service_start_utc'],
                'ends_at_utc'=>$snap['service_end_utc'],
                'timezone'=>$snap['service_timezone'],
            ],
            'before_minutes'=>$snap['service_buffer_before_minutes'],
            'after_minutes'=>$snap['service_buffer_after_minutes'],
        ];
    }

    /** Admisión de 60 s antes de HTTP, única por operación/recurso. */
    public static function admitWithin(
        Transaction_Context $tx, Event_Actor $actor,
        string $operationId, string $orderId, string $lineId,
        array $service, array $interval
    ): string {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        self::requireLine($tx,$orderId,$lineId,$service['sellable_id']);
        self::requireFreeWithin($tx,$interval);
        $rows = $tx->rows(
            'SELECT id FROM %i WHERE idempotency_id = %s AND resource_id = %s LIMIT 1',
            [Agenda_Repository::table('pc_resource_admissions'),
                $operationId,Agenda_Time::RESOURCE]
        );
        if ([] !== $rows) {
            throw new Transaction_Failure('pc_checkout_admission_duplicate');
        }
        if (1 !== $tx->execute(
            'INSERT INTO %i (idempotency_id,resource_id,quantity,'
                . 'slot_start_utc,slot_end_utc,created_at,expires_at) '
                . 'VALUES (%s,%s,NULL,%s,%s,UTC_TIMESTAMP(6),'
                . 'DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND))',
            [Agenda_Repository::table('pc_resource_admissions'),
                $operationId,Agenda_Time::RESOURCE,
                $interval['occupied_start'],$interval['occupied_end']]
        )) {
            throw new Transaction_Failure('pc_checkout_admission_failed');
        }
        $id = self::lastId($tx);
        Event_Journal::auditWithin($tx,$actor,$operationId,'order',$orderId,
            'service_admission_created','checkout_mp',null,
            \hash('sha256',$lineId.':'.$id));
        return $id;
    }

    /** Efecto de contraentrega: cita confirmada, importe todavía impago. */
    public static function commitCashWithin(
        Transaction_Context $tx, Event_Actor $actor,
        string $operationId, string $orderId, string $lineId,
        array $service, array $interval
    ): string {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        self::requireLine($tx,$orderId,$lineId,$service['sellable_id']);
        if ('cash' !== (string) $tx->lockedOrder($orderId)['payment_method']
            || 'CONFIRMADO' !== (string) $tx->lockedOrder($orderId)['status']
            || 'CONFIRMADA' !== (string) $tx->lockedOrderLine($lineId)['fulfillment_state']) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
        self::requireFreeWithin($tx,$interval);
        return self::insertAppointmentWithin($tx,$actor,$operationId,$orderId,$lineId,$interval,'cash');
    }

    /**
     * MP: convierte admisión vigente en hold desde la fecha del intento
     * verificado, jamás desde el momento del segundo POST.
     */
    public static function admissionToHoldWithin(
        Transaction_Context $tx, string $operationId, string $orderId,
        string $lineId, string $paymentAttemptId, array $service, array $interval
    ): string {
        self::requireLine($tx,$orderId,$lineId,$service['sellable_id']);
        $attempt = self::verifiedAttemptWithin($tx,$operationId,$orderId,$paymentAttemptId);
        $admissions = $tx->rows(
            'SELECT id,slot_start_utc,slot_end_utc,created_at,expires_at,'
                . 'consumed_at,released_at FROM %i WHERE idempotency_id = %s '
                . 'AND resource_id = %s FOR UPDATE',
            [Agenda_Repository::table('pc_resource_admissions'),
                $operationId,Agenda_Time::RESOURCE]
        );
        if (1 !== \count($admissions)) {
            throw new Transaction_Failure('pc_checkout_admission_missing');
        }
        $admission = $admissions[0];
        $id = Domain_Validation::sqlId((string) $admission['id']);
        if (null !== $admission['consumed_at'] || null !== $admission['released_at']
            || (string) $admission['slot_start_utc'] !== $interval['occupied_start']
            || (string) $admission['slot_end_utc'] !== $interval['occupied_end']
            || (string) $attempt['last_verified_at'] < (string) $admission['created_at']) {
            throw new Transaction_Failure('pc_checkout_admission_invalid');
        }
        $now = self::nowWithin($tx);
        if ((string) $admission['expires_at'] <= $now
            || (string) $attempt['last_verified_at'] > $now) {
            throw new Transaction_Failure('pc_checkout_admission_expired');
        }
        if ([] !== $tx->rows(
            'SELECT id FROM %i WHERE order_line_id = %s '
                . 'AND resource_kind = %s LIMIT 1',
            [Agenda_Repository::table('pc_holds'),$lineId,'slot']
        )) {
            throw new Transaction_Failure('pc_checkout_hold_duplicate');
        }
        if (1 !== $tx->execute(
            'UPDATE %i SET consumed_at = UTC_TIMESTAMP(6) WHERE id = %s '
                . 'AND consumed_at IS NULL AND released_at IS NULL '
                . 'AND expires_at > UTC_TIMESTAMP(6)',
            [Agenda_Repository::table('pc_resource_admissions'),$id]
        )) {
            throw new Transaction_Failure('pc_checkout_admission_expired');
        }
        if (1 !== $tx->execute(
            'INSERT INTO %i (payment_attempt_id,order_id,order_line_id,'
                . 'resource_kind,resource_id,slot_start_utc,slot_end_utc,'
                . 'state,started_at,expires_at) SELECT %s,%s,%s,%s,%s,%s,%s,%s,'
                . 'last_verified_at,DATE_ADD(last_verified_at,INTERVAL 15 MINUTE) '
                . 'FROM %i WHERE id = %s AND order_id = %s '
                . 'AND last_verified_at IS NOT NULL AND '
                . 'DATE_ADD(last_verified_at,INTERVAL 15 MINUTE) > UTC_TIMESTAMP(6)',
            [Agenda_Repository::table('pc_holds'),$paymentAttemptId,
                $orderId,$lineId,'slot',Agenda_Time::RESOURCE,
                $interval['occupied_start'],$interval['occupied_end'],'VIGENTE',
                Orders_Domain::table('pc_payment_attempts'),$paymentAttemptId,$orderId]
        )) {
            throw new Transaction_Failure('pc_checkout_hold_expired');
        }
        return self::lastId($tx);
    }

    /**
     * Confirmación MP sólo con acreditación comprobada por adaptador.
     * Un nuevo evento idempotente prepared coordina pedido/ledger/stock/cita;
     * un hold vencido no puede transformarse en cita por esta vía.
     */
    public static function commitPaidWithin(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        string $orderId, string $lineId, string $holdId,
        string $verifiedCreditEventId, array $service, array $interval
    ): string {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        self::requireLine($tx,$orderId,$lineId,$service['sellable_id']);
        if (null === self::$fundsVerifier
            || 'CONFIRMADO' !== (string) $tx->lockedOrder($orderId)['status']
            || 'mercado_pago' !== (string) $tx->lockedOrder($orderId)['payment_method']
            || 'PENDIENTE_CONFIRMACION' !==
                (string) $tx->lockedOrderLine($lineId)['fulfillment_state']) {
            throw new Transaction_Failure('pc_checkout_credit_unverified');
        }
        $creditEvent = Domain_Validation::sqlId($verifiedCreditEventId);
        $holdId = Domain_Validation::sqlId($holdId);
        $holds = $tx->rows(
            'SELECT id,payment_attempt_id,state,expires_at,slot_start_utc,slot_end_utc '
                . 'FROM %i WHERE id = %s AND order_id = %s '
                . 'AND order_line_id = %s AND resource_kind = %s FOR UPDATE',
            [Agenda_Repository::table('pc_holds'),$holdId,$orderId,$lineId,'slot']
        );
        if (1 !== \count($holds)
            || 'VIGENTE' !== $holds[0]['state']
            || (string) $holds[0]['expires_at'] <= self::nowWithin($tx)
            || (string) $holds[0]['slot_start_utc'] !== $interval['occupied_start']
            || (string) $holds[0]['slot_end_utc'] !== $interval['occupied_end']) {
            throw new Transaction_Failure('pc_checkout_hold_expired');
        }
        try {
            if (true !== (self::$fundsVerifier)(
                $tx,$actor,$operationId,$orderId,$creditEvent,
                (string) $holds[0]['payment_attempt_id']
            )) {
                throw new Transaction_Failure('pc_checkout_credit_unverified');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_checkout_credit_unverified');
        }
        // El hold deja de ocupar capacidad justo cuando nace la cita.
        // La misma transacción revisa de nuevo el resto de ocupaciones.
        if (1 !== $tx->execute(
            'UPDATE %i SET state = %s,committed_at = UTC_TIMESTAMP(6) '
                . 'WHERE id = %s AND state = %s '
                . 'AND expires_at > UTC_TIMESTAMP(6)',
            [Agenda_Repository::table('pc_holds'),'COMPROMETIDA',$holdId,'VIGENTE']
        )) {
            throw new Transaction_Failure('pc_checkout_hold_expired');
        }
        self::requireFreeWithin($tx,$interval);
        $created = self::insertAppointmentWithin(
            $tx,$actor,$operationId,$orderId,$lineId,$interval,'mercado_pago'
        );
        $tx->advanceVersion(
            'pc_order_lines',$lineId,(string) $tx->lockedOrderLine($lineId)['row_version']
        );
        if (1 !== $tx->execute(
            'UPDATE %i SET fulfillment_state = %s WHERE id = %s '
                . 'AND order_id = %s AND fulfillment_state = %s',
            [Orders_Domain::table('pc_order_lines'),'CONFIRMADA',$lineId,$orderId,
                'PENDIENTE_CONFIRMACION']
        )) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
        return $created;
    }

    /**
     * IMP-30: crear la PRIMERA cita efectiva de un pedido MP conciliado.
     * Nunca modificar un appointment anterior ni reutilizar un hold vencido.
     * El caller acredita evento financiero y acuerdo real del comprador.
     */
    public static function firstReconciledWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $orderId,string $lineId,array $service,array $interval
    ): string {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        self::requireLine($tx,$orderId,$lineId,$service['sellable_id']);
        if ('staff'!==$actor->kind()
            || !\current_user_can('perucommerce_reconciliation_manage')
            || !\in_array((string)$tx->lockedOrder($orderId)['status'],
                ['PENDIENTE_PAGO','VENCIDO'],true)
            || 'mercado_pago'!==(string)$tx->lockedOrder($orderId)['payment_method']
            || 'PENDIENTE_CONFIRMACION'!==
                (string)$tx->lockedOrderLine($lineId)['fulfillment_state']) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
        $now=self::nowWithin($tx);
        if ($interval['start'] <= $now || $interval['end'] <= $now
            || []!==$tx->rows(
                'SELECT id FROM %i WHERE order_id = %s OR order_line_id = %s LIMIT 1',
                [Agenda_Repository::table('pc_appointments'),$orderId,$lineId]
            )
            || []!==$tx->rows(
                'SELECT id FROM %i WHERE order_line_id = %s AND state = %s '
                    . 'AND expires_at > UTC_TIMESTAMP(6) LIMIT 1',
                [Agenda_Repository::table('pc_holds'),$lineId,'VIGENTE']
            )) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }

        // La agenda ACTUAL es la autoridad para una nueva franja. No usar
        // reglas de una venta histórica para inventar disponibilidad.
        $start=Business_Time::fromDatabase($interval['start']);
        $end=Business_Time::fromDatabase($interval['end']);
        $occupiedStart=Business_Time::fromDatabase($interval['occupied_start']);
        $occupiedEnd=Business_Time::fromDatabase($interval['occupied_end']);
        $zone=$interval['timezone'];
        $anchor=Agenda_Repository::anchorWithin($tx);
        if ($anchor['timezone']!==$zone) {
            throw new Transaction_Failure('pc_checkout_slot_changed');
        }
        $date=Business_Time::dateInBusinessZone($start,$zone);
        $previousDate=Agenda_Time::day($date,-1);
        $previous=Agenda_Repository::scheduleWithin($tx,$previousDate,$anchor);
        $current=Agenda_Repository::scheduleWithin($tx,$date,$anchor);
        $duration=(int)($end->getTimestamp()-$start->getTimestamp());
        $before=$service['before_minutes']*60;
        $after=$service['after_minutes']*60;
        $step=$duration+$before+$after;
        if ($duration<60 || $step<60 || 0!==$duration%60) {
            throw new Transaction_Failure('pc_checkout_slot_unavailable');
        }
        $fits=0;
        foreach ([[$previousDate,$previous],[$date,$current]] as [$day,$schedule]) {
            foreach (Agenda_Time::windows($schedule['weekly_rules'],$day,$zone)
                as [$open,$close]) {
                $first=Agenda_Time::addMinutes($open,$service['before_minutes']);
                $delta=$start->getTimestamp()-$first->getTimestamp();
                if ($delta>=0 && 0===$delta%$step
                    && $occupiedStart >= $open && $occupiedEnd <= $close) {
                    ++$fits;
                }
            }
        }
        if (1!==$fits) {
            throw new Transaction_Failure('pc_checkout_slot_unavailable');
        }
        self::requireFreeWithin($tx,$interval);
        $appointment=self::insertAppointmentWithin(
            $tx,$actor,$operationId,$orderId,$lineId,
            $interval,'mp_reconciliation'
        );
        $version=$tx->advanceVersion(
            'pc_order_lines',$lineId,
            (string)$tx->lockedOrderLine($lineId)['row_version']
        );
        if (1!==$tx->execute(
            'UPDATE %i SET fulfillment_state = %s WHERE id = %s '
                . 'AND order_id = %s AND kind = %s '
                . 'AND fulfillment_state = %s AND row_version = %s',
            [Orders_Domain::table('pc_order_lines'),'CONFIRMADA',
                $lineId,$orderId,'service','PENDIENTE_CONFIRMACION',$version]
        )) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
        return $appointment;
    }

    /**
     * Limpieza opcional de hold vencido. La disponibilidad YA ignora vencidos;
     * el caller debe adquirir previamente op -> orden -> línea -> vendible ->
     * recurso -> días mediante lockSlotWithin, con intervalo histórico.
     */
    public static function expireHoldWithin(
        Transaction_Context $tx,string $holdId,string $orderId,
        string $lineId,array $service,array $interval
    ): bool {
        self::requireLine($tx,$orderId,$lineId,$service['sellable_id']);
        $hold = self::holdForTransitionWithin(
            $tx,$holdId,$orderId,$lineId,$interval
        );
        if ('VENCIDA' === $hold['state']) {
            return false;
        }
        if ('VIGENTE' !== $hold['state']) {
            throw new Transaction_Failure('pc_checkout_hold_invalid');
        }
        return 1 === $tx->execute(
            'UPDATE %i SET state = %s,released_at = UTC_TIMESTAMP(6) '
                . 'WHERE id = %s AND state = %s AND expires_at <= UTC_TIMESTAMP(6)',
            [Agenda_Repository::table('pc_holds'),'VENCIDA',$holdId,'VIGENTE']
        );
    }

    /**
     * Rechazo/cancelación comprobados por proveedor, o plazo local vencido.
     * Nunca informar «cancelado por MP» sólo porque el hold caducó.
     */
    public static function releaseHoldWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $holdId,string $orderId,string $lineId,
        array $service,array $interval
    ): bool {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        self::requireLine($tx,$orderId,$lineId,$service['sellable_id']);
        $hold = self::holdForTransitionWithin(
            $tx,$holdId,$orderId,$lineId,$interval
        );
        if (\in_array($hold['state'],['LIBERADA','VENCIDA'],true)) {
            return false;
        }
        if ('VIGENTE' !== $hold['state']) {
            throw new Transaction_Failure('pc_checkout_hold_invalid');
        }
        $expired = (string) $hold['expires_at'] <= self::nowWithin($tx);
        $rejected = null !== $hold['last_verified_at']
            && \in_array($hold['attempt_state'],
                ['RECHAZADO','CANCELADO_PROVEEDOR'],true);
        if (!$expired && !$rejected) {
            throw new Transaction_Failure('pc_checkout_hold_invalid');
        }
        $state = $expired ? 'VENCIDA' : 'LIBERADA';
        if (1 !== $tx->execute(
            'UPDATE %i SET state = %s,released_at = UTC_TIMESTAMP(6) '
                . 'WHERE id = %s AND state = %s',
            [Agenda_Repository::table('pc_holds'),$state,$holdId,'VIGENTE']
        )) {
            throw new Transaction_Failure('pc_checkout_hold_invalid');
        }
        Event_Journal::auditWithin(
            $tx,$actor,$operationId,'order',$orderId,
            'service_hold_released',$expired ? 'local_expiry' : 'provider_rejected'
        );
        return true;
    }

    private static function holdForTransitionWithin(
        Transaction_Context $tx,string $holdId,string $orderId,
        string $lineId,array $interval
    ): array {
        $holdId = Domain_Validation::sqlId($holdId);
        $rows = $tx->rows(
            'SELECT h.id,h.state,h.expires_at,h.slot_start_utc,h.slot_end_utc,'
                . 'a.state AS attempt_state,a.last_verified_at '
                . 'FROM %i h INNER JOIN %i a '
                . 'ON a.id = h.payment_attempt_id AND a.order_id = h.order_id '
                . 'WHERE h.id = %s AND h.order_id = %s AND h.order_line_id = %s '
                . 'AND h.resource_kind = %s FOR UPDATE',
            [Agenda_Repository::table('pc_holds'),
                Orders_Domain::table('pc_payment_attempts'),
                $holdId,$orderId,$lineId,'slot']
        );
        if (1 !== \count($rows)
            || (string) $rows[0]['slot_start_utc'] !== $interval['occupied_start']
            || (string) $rows[0]['slot_end_utc'] !== $interval['occupied_end']) {
            throw new Transaction_Failure('pc_checkout_hold_invalid');
        }
        return $rows[0];
    }

    /** Valida la misma cuenta, clave, orden y ambiente antes del hold. */
    public static function verifiedAttemptWithin(
        Transaction_Context $tx, string $operationId,
        string $orderId, string $paymentAttemptId
    ): array {
        $operation = $tx->lockedOperation($operationId);
        $order = $tx->lockedOrder($orderId);
        if (!\in_array((string) $operation['technical_phase'],
                ['external_pending','reconcile'],true)
            || 'PENDIENTE_PAGO' !== (string) $order['status']
            || 'mercado_pago' !== (string) $order['payment_method']) {
            throw new Transaction_Failure('pc_checkout_attempt_invalid');
        }
        $rows = $tx->rows(
            'SELECT id,provider,provider_order_id,mp_idempotency_key,'
                . 'HEX(provider_account_key) AS account_hex,environment,'
                . 'state,last_verified_at,amount_cents,currency '
                . 'FROM %i WHERE id = %s AND order_id = %s FOR UPDATE',
            [Orders_Domain::table('pc_payment_attempts'),
                $paymentAttemptId,$orderId]
        );
        $links = $tx->rows(
            'SELECT operation_type,provider_idempotency_key,provider_environment,'
                . 'HEX(provider_account_key) AS account_hex '
                . 'FROM %i WHERE id = %s',
            [Orders_Domain::table('pc_idempotency'),$operationId]
        );
        $totals = $tx->rows(
            'SELECT total_cents,currency FROM %i WHERE id = %s',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1 !== \count($rows) || 1 !== \count($links)
            || 1 !== \count($totals)) {
            throw new Transaction_Failure('pc_checkout_attempt_invalid');
        }
        $attempt = $rows[0];
        $link = $links[0];
        if ('checkout_mp' !== $link['operation_type']
            || 'mercado_pago' !== $attempt['provider']
            || !\in_array($attempt['state'],['CREADO','PENDIENTE'],true)
            || null === $attempt['last_verified_at']
            || !\is_string($attempt['provider_order_id'])
            || '' === $attempt['provider_order_id']
            || !\is_string($link['provider_idempotency_key'])
            || !\hash_equals((string) $link['provider_idempotency_key'],
                (string) $attempt['mp_idempotency_key'])
            || !\hash_equals((string) $link['account_hex'],
                (string) $attempt['account_hex'])
            || $link['provider_environment'] !== $attempt['environment']
            || (string) $totals[0]['total_cents'] !== (string) $attempt['amount_cents']
            || 'PEN' !== $attempt['currency']
            || 'PEN' !== $totals[0]['currency']) {
            throw new Transaction_Failure('pc_checkout_attempt_invalid');
        }
        return $attempt;
    }

    private static function requireLine(
        Transaction_Context $tx,string $orderId,string $lineId,string $sellableId
    ): void {
        $orderId = Domain_Validation::sqlId($orderId);
        $lineId = Domain_Validation::sqlId($lineId);
        $sellableId = Domain_Validation::sqlId($sellableId);
        $order = $tx->lockedOrder($orderId);
        $line = $tx->lockedOrderLine($lineId);
        $sellable = $tx->lockedSellable($sellableId);
        if ('service' !== (string) $line['kind']
            || 'service' !== (string) $sellable['kind']
            || (string) $line['order_id'] !== $orderId
            || (string) $line['sellable_id'] !== $sellableId
            || !\in_array($order['status'],['CONFIRMADO','PENDIENTE_PAGO'],true)) {
            throw new Transaction_Failure('pc_checkout_service_invalid');
        }
    }

    private static function requireFreeWithin(
        Transaction_Context $tx,array $interval
    ): void {
        $start = Business_Time::fromDatabase($interval['occupied_start']);
        $end = Business_Time::fromDatabase($interval['occupied_end']);
        if ([] !== Agenda_Repository::busyWithin($tx,$start,$end)) {
            throw new Transaction_Failure('pc_checkout_slot_unavailable');
        }
    }

    private static function insertAppointmentWithin(
        Transaction_Context $tx,Event_Actor $actor,string $op,
        string $orderId,string $lineId,array $interval,string $reason
    ): string {
        if ([] !== $tx->rows(
            'SELECT id FROM %i WHERE order_id = %s OR order_line_id = %s LIMIT 1',
            [Agenda_Repository::table('pc_appointments'),$orderId,$lineId]
        )) {
            throw new Transaction_Failure('pc_checkout_appointment_duplicate');
        }
        if (1 !== $tx->execute(
            'INSERT INTO %i (order_id,order_line_id,resource_id,'
                . 'starts_at_utc,ends_at_utc,local_timezone,row_version,'
                . 'created_at,updated_at) VALUES '
                . '(%s,%s,%s,%s,%s,%s,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))',
            [Agenda_Repository::table('pc_appointments'),$orderId,$lineId,
                Agenda_Time::RESOURCE,$interval['start'],$interval['end'],
                $interval['timezone']]
        )) {
            throw new Transaction_Failure('pc_checkout_appointment_failed');
        }
        $id = self::lastId($tx);
        Event_Journal::auditWithin($tx,$actor,$op,'appointment',$id,
            'appointment_checkout_created',$reason);
        return $id;
    }

    private static function nowWithin(Transaction_Context $tx): string
    {
        $rows = $tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_checkout_clock_invalid');
        }
        return (string) $rows[0]['now_utc'];
    }

    private static function lastId(Transaction_Context $tx): string
    {
        $rows = $tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_checkout_insert_failed');
        }
        return Domain_Validation::sqlId((string) $rows[0]['id']);
    }
}
