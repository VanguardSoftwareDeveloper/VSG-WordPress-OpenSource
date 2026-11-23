<?php
/**
 * IMP-17 — admisión, hold, compromiso, liberación y salida de stock.
 *
 * Funciones internas para consumidores R8-06/07/22/23/26/30 futuros.
 * No crean rutas nuevas, no contactan al proveedor y NO se invocan desde
 * el navegador sin que el consumidor valide pago/estado/permiso.
 *
 * Orden exigido de locks: idempotency -> order -> line -> sellables
 * (ascendentes) -> commitment. Ante recurso inexistente no basta
 * SELECT ... FOR UPDATE: la variante estable es el ancla obligatoria.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Inventory_Reservations
{
    /**
     * Etapa pre-HTTP MP, 60 segundos fijos, sin extender vencimiento.
     * Exige operación idempotente preparada y variante física ya bloqueada.
     */
    public static function admitWithin(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        string $sellableId, int $quantity
    ): string {
        self::pending($tx, $actor, $operationId);
        $sellableId = Domain_Validation::sqlId($sellableId);
        Inventory_Balance::demandWithin($tx, $sellableId, $quantity);

        if ([] !== $tx->rows(
            'SELECT id FROM %i WHERE idempotency_id = %s AND sellable_id = %s LIMIT 1',
            [Inventory_Balance::table('pc_resource_admissions'), $operationId, $sellableId]
        )) {
            throw new Transaction_Failure('pc_inventory_duplicate');
        }

        if (1 !== $tx->execute(
            'INSERT INTO %i (idempotency_id, sellable_id, quantity, '
                . 'created_at, expires_at) '
                . 'VALUES (%s, %s, %d, UTC_TIMESTAMP(6), '
                . 'DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 60 SECOND))',
            [Inventory_Balance::table('pc_resource_admissions'),
                $operationId, $sellableId, $quantity]
        )) {
            throw new Transaction_Failure('pc_inventory_write_failed');
        }
        $admissionId = Inventory_Balance::lastInsertId($tx);
        Event_Journal::auditWithin(
            $tx, $actor, $operationId, 'sellable', $sellableId,
            'stock_admission_created', null, null,
            self::digest($admissionId . ':' . $quantity)
        );
        return $admissionId;
    }

    /**
     * Aborto local antes de contactar a MP: consumo cesa inmediatamente.
     * No usar después de HTTP ambiguo; el adaptador debe conciliar primero.
     * El opId es el MISMO dueño idempotente preparado de la admisión.
     */
    public static function releaseAdmissionBeforeHttpWithin(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        string $admissionId, string $sellableId
    ): bool {
        self::pending($tx, $actor, $operationId);
        $admissionId = Domain_Validation::sqlId($admissionId);
        $sellableId = Domain_Validation::sqlId($sellableId);
        $tx->lockedSellable($sellableId);
        $rows = $tx->rows(
            'SELECT id, consumed_at, released_at FROM %i '
                . 'WHERE id = %s AND idempotency_id = %s AND sellable_id = %s FOR UPDATE',
            [Inventory_Balance::table('pc_resource_admissions'),
                $admissionId, $operationId, $sellableId]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_inventory_not_found');
        }
        if (null !== $rows[0]['released_at']) {
            return false;
        }
        if (null !== $rows[0]['consumed_at']) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        if (1 !== $tx->execute(
            'UPDATE %i SET released_at = UTC_TIMESTAMP(6) '
                . 'WHERE id = %s AND consumed_at IS NULL AND released_at IS NULL',
            [Inventory_Balance::table('pc_resource_admissions'), $admissionId]
        )) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        Event_Journal::auditWithin(
            $tx, $actor, $operationId, 'sellable', $sellableId,
            'stock_admission_released', null,
            self::digest($admissionId), null
        );
        return true;
    }

    /**
     * Tras crear/verificar MP *fuera* de SQL, convierte la admisión vigente
     * en hold comercial de 15 min. El tiempo inicial procede del intento
     * verificado por servidor, nunca del reloj del visitante. Este método
     * valida el vínculo local, no sustituye validar Orders/payer/importe.
     *
     * Requiere operation (raíz de la admisión), order, line y sellable locks
     * ya obtenidos en ese orden por el consumidor de pagos.
     */
    public static function admissionToHoldWithin(
        Transaction_Context $tx, string $operationId, string $admissionId,
        string $paymentAttemptId, string $orderId, string $lineId,
        string $sellableId
    ): string {
        self::module();
        $operationId = Domain_Validation::sqlId($operationId);
        $admissionId = Domain_Validation::sqlId($admissionId);
        $paymentAttemptId = Domain_Validation::sqlId($paymentAttemptId);
        $line = self::line($tx, $orderId, $lineId, $sellableId);
        Inventory_Balance::within($tx, $sellableId);
        $operation = $tx->lockedOperation($operationId);
        if (!\in_array((string) $operation['technical_phase'], ['external_pending', 'reconcile'], true)
            || 'PENDIENTE_PAGO' !== (string) $tx->lockedOrder($orderId)['status']) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        $admission = $tx->rows(
            'SELECT id, sellable_id, quantity, expires_at, consumed_at, released_at '
                . 'FROM %i WHERE id = %s AND idempotency_id = %s '
                . 'AND sellable_id = %s FOR UPDATE',
            [Inventory_Balance::table('pc_resource_admissions'),
                $admissionId, $operationId, $sellableId]
        );
        if (1 !== \count($admission)
            || null !== $admission[0]['consumed_at']
            || null !== $admission[0]['released_at']
            || Inventory_Balance::units($admission[0]['quantity'])
                !== Inventory_Balance::units($line['quantity'])) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }

        // El proveedor debe haber sido verificado por el adaptador MP.
        // Un URL retornado al navegador no representa prueba de pago.
        $attempt = $tx->rows(
            'SELECT id, state, provider, provider_order_id, last_verified_at, '
                . 'mp_idempotency_key, environment, HEX(provider_account_key) AS account_hex '
                . 'FROM %i WHERE id = %s AND order_id = %s FOR UPDATE',
            [Inventory_Balance::table('pc_payment_attempts'), $paymentAttemptId, $orderId]
        );
        // El intento debe vincularse a la MISMA operación inmutable de
        // checkout y a la misma cuenta/ambiente MP. Un ID de intento
        // insertado por otra operación no puede consumir esta admisión.
        $link = $tx->rows(
            'SELECT operation_type, provider_idempotency_key, provider_environment, '
                . 'HEX(provider_account_key) AS account_hex '
                . 'FROM %i WHERE id = %s',
            [Inventory_Balance::table('pc_idempotency'), $operationId]
        );
        if (1 !== \count($attempt) || 1 !== \count($link)
            || 'checkout_mp' !== (string) $link[0]['operation_type']
            || !\is_string($link[0]['provider_idempotency_key'])
            || !\hash_equals((string) $link[0]['provider_idempotency_key'],
                (string) $attempt[0]['mp_idempotency_key'])
            || !\hash_equals((string) $link[0]['account_hex'],
                (string) $attempt[0]['account_hex'])
            || !\is_string($link[0]['provider_environment'])
            || $link[0]['provider_environment'] !== $attempt[0]['environment']
            || 'mercado_pago' !== (string) $attempt[0]['provider']
            || !\in_array($attempt[0]['state'], ['CREADO', 'PENDIENTE'], true)
            || !\is_string($attempt[0]['provider_order_id'])
            || '' === $attempt[0]['provider_order_id']
            || null === $attempt[0]['last_verified_at']) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        $time = $tx->rows(
            'SELECT UTC_TIMESTAMP(6) AS now_utc'
        );
        if (1 !== \count($time)
            || (string) $admission[0]['expires_at'] <= (string) $time[0]['now_utc']
            || (string) $attempt[0]['last_verified_at'] > (string) $time[0]['now_utc']) {
            throw new Transaction_Failure('pc_inventory_admission_expired');
        }
        if ([] !== $tx->rows(
            'SELECT id FROM %i WHERE order_line_id = %s AND resource_kind = %s '
                . 'AND state IN (%s, %s) LIMIT 1',
            [Inventory_Balance::table('pc_holds'), $lineId, 'stock',
                'VIGENTE', 'COMPROMETIDA']
        )) {
            throw new Transaction_Failure('pc_inventory_duplicate');
        }

        // La admisión sale del contador en el mismo COMMIT donde entra hold.
        if (1 !== $tx->execute(
            'UPDATE %i SET consumed_at = UTC_TIMESTAMP(6) WHERE id = %s '
                . 'AND consumed_at IS NULL AND released_at IS NULL '
                . 'AND expires_at > UTC_TIMESTAMP(6)',
            [Inventory_Balance::table('pc_resource_admissions'), $admissionId]
        )) {
            throw new Transaction_Failure('pc_inventory_admission_expired');
        }
        if (1 !== $tx->execute(
            'INSERT INTO %i (payment_attempt_id, order_id, order_line_id, '
                . 'resource_kind, sellable_id, quantity, state, started_at, expires_at) '
                . 'SELECT %s, %s, %s, %s, %s, %d, %s, last_verified_at, '
                . 'DATE_ADD(last_verified_at, INTERVAL 15 MINUTE) '
                . 'FROM %i WHERE id = %s AND order_id = %s '
                . 'AND last_verified_at IS NOT NULL '
                . 'AND DATE_ADD(last_verified_at, INTERVAL 15 MINUTE) > UTC_TIMESTAMP(6)',
            [Inventory_Balance::table('pc_holds'), $paymentAttemptId,
                $orderId, $lineId, 'stock', $sellableId,
                Inventory_Balance::units($line['quantity']), 'VIGENTE',
                Inventory_Balance::table('pc_payment_attempts'),
                $paymentAttemptId, $orderId]
        )) {
            throw new Transaction_Failure('pc_inventory_admission_expired');
        }
        return Inventory_Balance::lastInsertId($tx);
    }

    /**
     * Contraentrega: nunca crea hold MP ni acredita dinero. Llamar con order
     * ya confirmado por checkout y con locks order/line/sellable.
     */
    public static function commitCashWithin(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        string $orderId, string $lineId, string $sellableId
    ): string {
        self::pending($tx, $actor, $operationId);
        $line = self::line($tx, $orderId, $lineId, $sellableId);
        $order = $tx->lockedOrder($orderId);
        if ('CONFIRMADO' !== (string) $order['status']
            || 'cash' !== (string) $order['payment_method']) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        return self::createCommitment($tx, $actor, $operationId, $line, $sellableId, false);
    }

    /**
     * IMP-30: nuevo compromiso tras pago externo YA acreditado y
     * expediente de conciliación legítimo. No revivir hold expirado.
     * El caller exige GET oficial, evento financiero, CAS de pedido y
     * locks ordenados; aquí se vuelve a comprobar stock real vigente.
     */
    public static function commitReconciledWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $orderId,string $lineId,string $sellableId
    ): string {
        self::pending($tx,$actor,$operationId);
        $line=self::line($tx,$orderId,$lineId,$sellableId);
        $order=$tx->lockedOrder($orderId);
        if ('staff'!==$actor->kind()
            || !\current_user_can('perucommerce_reconciliation_manage')
            || !\in_array((string)$order['status'],['PENDIENTE_PAGO','VENCIDO'],true)
            || 'mercado_pago'!==(string)$order['payment_method']) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        $live=$tx->rows(
            'SELECT id FROM %i WHERE order_line_id = %s '
                . 'AND state = %s AND expires_at > UTC_TIMESTAMP(6) LIMIT 1',
            [Inventory_Balance::table('pc_holds'),$lineId,'VIGENTE']
        );
        if ([]!==$live) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        return self::createCommitment(
            $tx,$actor,$operationId,$line,$sellableId,false,
            'mp_reconciliation'
        );
    }

    /**
     * Confirmación MP: la verificación del dinero/pedido pertenece al
     * consumidor de pagos; aquí se exige pedido CONFIRMADO, hold local
     * no expirado y ausencia de compromiso previo. La transición hold
     * VIGENTE -> COMPROMETIDA y el compromiso son atómicos.
     */
    public static function commitHoldWithin(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        string $holdId, string $orderId, string $lineId, string $sellableId
    ): string {
        self::pending($tx, $actor, $operationId);
        $line = self::line($tx, $orderId, $lineId, $sellableId);
        $order = $tx->lockedOrder($orderId);
        if ('CONFIRMADO' !== (string) $order['status']
            || 'mercado_pago' !== (string) $order['payment_method']) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        $holdId = Domain_Validation::sqlId($holdId);
        $holds = $tx->rows(
            'SELECT id, state, quantity, expires_at FROM %i WHERE id = %s '
                . 'AND order_id = %s AND order_line_id = %s AND sellable_id = %s '
                . 'AND resource_kind = %s FOR UPDATE',
            [Inventory_Balance::table('pc_holds'), $holdId, $orderId, $lineId, $sellableId,
                'stock']
        );
        if (1 !== \count($holds)
            || 'VIGENTE' !== $holds[0]['state']
            || Inventory_Balance::units($holds[0]['quantity'])
                !== Inventory_Balance::units($line['quantity'])) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        $expiry = $tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
        if (1 !== \count($expiry)
            || (string) $holds[0]['expires_at'] <= (string) $expiry[0]['now_utc']) {
            throw new Transaction_Failure('pc_inventory_hold_expired');
        }

        $commitmentId = self::createCommitment(
            $tx, $actor, $operationId, $line, $sellableId, true
        );
        if (1 !== $tx->execute(
            'UPDATE %i SET state = %s, committed_at = UTC_TIMESTAMP(6) '
                . 'WHERE id = %s AND state = %s AND expires_at > UTC_TIMESTAMP(6)',
            [Inventory_Balance::table('pc_holds'), 'COMPROMETIDA', $holdId, 'VIGENTE']
        )) {
            throw new Transaction_Failure('pc_inventory_hold_expired');
        }
        return $commitmentId;
    }

    /**
     * Liberación lógica del hold vencido, no cancelación de Mercado Pago.
     * El consumidor de recuperación audita por su propia idempotencia.
     */
    public static function expireHoldWithin(
        Transaction_Context $tx, string $holdId, string $sellableId
    ): bool {
        self::module();
        $holdId = Domain_Validation::sqlId($holdId);
        $sellableId = Domain_Validation::sqlId($sellableId);
        $tx->lockedSellable($sellableId);
        $rows = $tx->rows(
            'SELECT id, state, expires_at FROM %i WHERE id = %s '
                . 'AND sellable_id = %s AND resource_kind = %s FOR UPDATE',
            [Inventory_Balance::table('pc_holds'), $holdId, $sellableId, 'stock']
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_inventory_not_found');
        }
        if ('VENCIDA' === $rows[0]['state']) {
            return false;
        }
        if ('VIGENTE' !== $rows[0]['state']) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        return 1 === $tx->execute(
            'UPDATE %i SET state = %s WHERE id = %s AND state = %s '
                . 'AND expires_at <= UTC_TIMESTAMP(6)',
            [Inventory_Balance::table('pc_holds'), 'VENCIDA', $holdId, 'VIGENTE']
        );
    }

    /**
     * Liberar hold solo si el proveedor dejó constancia de rechazo o
     * cancelación, o está vencido. NO afirmar cancelación externa porque
     * el reloj local venció; la transición conserva evidencia MP.
     * Requiere operación separada preparada, locks order/line/sellable y
     * autorización del consumidor de pagos sobre la orden.
     */
    public static function releaseHoldWithin(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        string $holdId, string $orderId, string $lineId, string $sellableId
    ): bool {
        self::pending($tx, $actor, $operationId);
        self::line($tx, $orderId, $lineId, $sellableId);
        $holdId = Domain_Validation::sqlId($holdId);
        $rows = $tx->rows(
            'SELECT h.id, h.state, h.expires_at, a.state AS attempt_state, '
                . 'a.last_verified_at FROM %i h INNER JOIN %i a '
                . 'ON a.id = h.payment_attempt_id AND a.order_id = h.order_id '
                . 'WHERE h.id = %s AND h.order_id = %s AND h.order_line_id = %s '
                . 'AND h.sellable_id = %s AND h.resource_kind = %s FOR UPDATE',
            [Inventory_Balance::table('pc_holds'),
                Inventory_Balance::table('pc_payment_attempts'),
                $holdId, $orderId, $lineId, $sellableId, 'stock']
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_inventory_not_found');
        }
        $hold = $rows[0];
        if ('LIBERADA' === $hold['state'] || 'VENCIDA' === $hold['state']) {
            return false;
        }
        if ('VIGENTE' !== $hold['state']) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }

        $now = $tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
        if (1 !== \count($now)) {
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
        $expired = (string) $hold['expires_at'] <= (string) $now[0]['now_utc'];
        $verifiedRejection = null !== $hold['last_verified_at']
            && \in_array($hold['attempt_state'], [
                'RECHAZADO', 'CANCELADO_PROVEEDOR',
            ], true);
        if (!$expired && !$verifiedRejection) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        $target = $expired ? 'VENCIDA' : 'LIBERADA';
        if (1 !== $tx->execute(
            'UPDATE %i SET state = %s, released_at = UTC_TIMESTAMP(6) '
                . 'WHERE id = %s AND state = %s',
            [Inventory_Balance::table('pc_holds'), $target, $holdId, 'VIGENTE']
        )) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }
        Event_Journal::auditWithin(
            $tx, $actor, $operationId, 'sellable', $sellableId,
            'stock_hold_released',
            $expired ? 'hold_expired' : 'provider_rejected',
            self::digest($holdId), self::digest($target)
        );
        return true;
    }

    /**
     * Liberar compromiso previo a salida por orden autorizado de
     * cancelación completa o expediente de reducción parcial.
     * NO repone stock ni modifica dinero/caso por sí mismo.
     *
     * La transacción llamante cambia estados y ajusta obligaciones
     * financieras/caso en el MISMO COMMIT; no invocar por separado.
     */
    public static function releasePendingWithin(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        string $orderId, string $lineId, string $sellableId,
        int $units, string $reason, string $expectedLineVersion
    ): string {
        self::pending($tx, $actor, $operationId);
        $line = self::line($tx, $orderId, $lineId, $sellableId);
        $expectedLineVersion = Domain_Validation::sqlId($expectedLineVersion);
        if ((string) $line['row_version'] !== $expectedLineVersion
            || !\in_array($reason, ['cancel_line', 'financial_partial'], true)) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }

        $commitment = $tx->lockStockCommitment($lineId, $sellableId);
        $pending = self::pendingUnits($commitment);
        Inventory_Balance::quantity($units);
        if ($units > $pending
            || ('financial_partial' === $reason && $units >= $pending)
            || ('cancel_line' === $reason && $units !== $pending)) {
            throw new Transaction_Failure('pc_inventory_ineligible');
        }
        $newReleased = Inventory_Balance::safeAdd(
            Inventory_Balance::units($commitment['released_units']), $units
        );
        $cId = Domain_Validation::sqlId((string) $commitment['id']);
        $cVer = Domain_Validation::sqlId((string) $commitment['row_version']);
        if (1 !== $tx->execute(
            'UPDATE %i SET released_units = %s WHERE id = %s '
                . 'AND released_units = %s AND row_version = %s',
            [Inventory_Balance::table('pc_stock_commitments'),
                (string) $newReleased, $cId,
                (string) $commitment['released_units'], $cVer]
        )) {
            throw new Transaction_Failure('pc_version_conflict');
        }
        $tx->advanceVersion('pc_stock_commitments', $cId, $cVer);
        $version = $tx->advanceVersion('pc_order_lines', $lineId, $expectedLineVersion);
        Event_Journal::auditWithin(
            $tx, $actor, $operationId, 'sellable', $sellableId,
            'stock_commitment_released', $reason,
            self::digest((string) $pending),
            self::digest((string) ($pending - $units))
        );
        return $version;
    }

    /**
     * F-04 despacho o F-05 recojo: una sola salida física por unidades
     * pendientes, sin restar por F-06 (entrega posterior del despachado).
     * La transición de fulfillment_state pertenece a R8-22 en el mismo TX.
     */
    public static function stockOutWithin(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        string $orderId, string $lineId, string $sellableId,
        string $kind, string $expectedLineVersion
    ): string {
        self::pending($tx, $actor, $operationId);
        $line = self::line($tx, $orderId, $lineId, $sellableId);
        if (!\in_array($kind, ['dispatch', 'pickup'], true)
            || 'LISTO' !== (string) $line['fulfillment_state']
            || 'CONFIRMADO' !== (string) $tx->lockedOrder($orderId)['status']
            || (string) $line['row_version'] !== $expectedLineVersion) {
            throw new Transaction_Failure('pc_inventory_transition_invalid');
        }

        $commitment = $tx->lockStockCommitment($lineId, $sellableId);
        $units = self::pendingUnits($commitment);
        Inventory_Balance::quantity($units);
        $cId = Domain_Validation::sqlId((string) $commitment['id']);
        $cVer = Domain_Validation::sqlId((string) $commitment['row_version']);
        $fulfilled = Inventory_Balance::safeAdd(
            Inventory_Balance::units($commitment['fulfilled_units']), $units
        );

        if (1 !== $tx->execute(
            'UPDATE %i SET fulfilled_units = %s WHERE id = %s '
                . 'AND fulfilled_units = %s AND row_version = %s',
            [Inventory_Balance::table('pc_stock_commitments'),
                (string) $fulfilled, $cId, (string) $commitment['fulfilled_units'], $cVer]
        )) {
            throw new Transaction_Failure('pc_version_conflict');
        }
        $tx->advanceVersion('pc_stock_commitments', $cId, $cVer);
        $locked = $tx->lockedSellable($sellableId);
        if ('1' === (string) $locked['track_stock']) {
            $oldBalance = Inventory_Balance::units($locked['stock_on_hand']);
            if ($oldBalance < $units) {
                throw new Transaction_Failure('pc_inventory_unavailable');
            }
            $newBalance = $oldBalance - $units;
            $sellableVersion = (string) $locked['row_version'];
            if (1 !== $tx->execute(
                'UPDATE %i SET stock_on_hand = %s WHERE id = %s '
                    . 'AND stock_on_hand = %s AND row_version = %s',
                [Inventory_Balance::table('pc_sellables'), (string) $newBalance,
                    $sellableId, (string) $oldBalance, $sellableVersion]
            )) {
                throw new Transaction_Failure('pc_version_conflict');
            }
            $tx->advanceVersion('pc_sellables', $sellableId, $sellableVersion);
            Event_Journal::stockWithin(
                $tx, $actor, $operationId, $sellableId, -$units, $newBalance,
                'physical_stock_out', $lineId,
                'dispatch' === $kind ? 'physical_dispatch' : 'physical_pickup'
            );
        }
        $lineVersion = $tx->advanceVersion('pc_order_lines', $lineId, $expectedLineVersion);
        Event_Journal::auditWithin(
            $tx, $actor, $operationId, 'sellable', $sellableId,
            'physical_stock_out_recorded',
            'dispatch' === $kind ? 'physical_dispatch' : 'physical_pickup',
            self::digest((string) ($fulfilled - $units)),
            self::digest((string) $fulfilled)
        );
        return $lineVersion;
    }

    /**
     * Lectura interna de cuatro contadores E-01; no asumir 0 si una línea
     * confirmada perdió su compromiso. No devolver stock global al guest.
     */
    public static function physicalQuantitiesWithin(
        Transaction_Context $tx, string $orderId, string $lineId,
        string $sellableId
    ): array {
        $line = self::line($tx, $orderId, $lineId, $sellableId);
        $rows = $tx->rows(
            'SELECT committed_units, fulfilled_units, released_units '
                . 'FROM %i WHERE order_line_id = %s AND sellable_id = %s',
            [Inventory_Balance::table('pc_stock_commitments'), $lineId, $sellableId]
        );
        if ([] === $rows) {
            if ('PENDIENTE_PAGO' === (string) $tx->lockedOrder($orderId)['status']
                || 'VENCIDO' === (string) $tx->lockedOrder($orderId)['status']) {
                return [
                    'committed_units' => 0, 'released_units' => 0,
                    'stock_out_units' => 0, 'pending_stock_out_units' => 0,
                ];
            }
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
        $row = $rows[0];
        $total = Inventory_Balance::units($row['committed_units']);
        $out = Inventory_Balance::units($row['fulfilled_units']);
        $released = Inventory_Balance::units($row['released_units']);
        if ($total > Inventory_Balance::units($line['quantity'])
            || Inventory_Balance::safeAdd($out, $released) > $total) {
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
        return [
            'committed_units' => $total, 'released_units' => $released,
            'stock_out_units' => $out,
            'pending_stock_out_units' => $total - $released - $out,
        ];
    }

    private static function createCommitment(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        array $line, string $sellableId, bool $fromHold,
        string $reason = ''
    ): string {
        $lineId = (string) $line['id'];
        $quantity = Inventory_Balance::units($line['quantity']);
        Inventory_Balance::quantity($quantity);
        if ([] !== $tx->rows(
            'SELECT id FROM %i WHERE order_line_id = %s LIMIT 1',
            [Inventory_Balance::table('pc_stock_commitments'), $lineId]
        )) {
            throw new Transaction_Failure('pc_inventory_duplicate');
        }
        // Comprobar capacidad ANTES de insertar el compromiso: durante
        // hold -> commitment ambas filas coexisten en este TX, y una
        // lectura intermedia las contaría dos veces aun siendo atómicas.
        if ($fromHold) {
            Inventory_Balance::within($tx, $sellableId);
        } else {
            Inventory_Balance::demandWithin($tx, $sellableId, $quantity);
        }
        if (1 !== $tx->execute(
            'INSERT INTO %i (order_line_id, sellable_id, committed_units, '
                . 'fulfilled_units, released_units, created_at, updated_at, row_version) '
                . 'VALUES (%s, %s, %s, 0, 0, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), 1)',
            [Inventory_Balance::table('pc_stock_commitments'), $lineId,
                $sellableId, (string) $quantity]
        )) {
            throw new Transaction_Failure('pc_inventory_write_failed');
        }
        $commitmentId = Inventory_Balance::lastInsertId($tx);
        // El orden del consumidor tiene la línea bloqueada antes de stock.
        $tx->advanceVersion('pc_order_lines', $lineId, (string) $line['row_version']);
        Event_Journal::auditWithin(
            $tx, $actor, $operationId, 'sellable', $sellableId,
            'physical_commitment_created',
            ''!==$reason ? $reason : ($fromHold ? 'mp_hold_committed' : 'cash_committed'),
            null, self::digest($lineId . ':' . $quantity)
        );
        return $commitmentId;
    }

    private static function line(
        Transaction_Context $tx, string $orderId, string $lineId,
        string $sellableId
    ): array {
        $orderId = Domain_Validation::sqlId($orderId);
        $lineId = Domain_Validation::sqlId($lineId);
        $sellableId = Domain_Validation::sqlId($sellableId);
        $tx->lockedOrder($orderId);
        $line = $tx->lockedOrderLine($lineId);
        $locked = $tx->lockedSellable($sellableId);
        if ((string) $line['order_id'] !== $orderId
            || (string) $line['sellable_id'] !== $sellableId
            || 'physical' !== (string) $line['kind']
            || 'physical' !== (string) $locked['kind']) {
            throw new Transaction_Failure('pc_inventory_ineligible');
        }
        return $line;
    }

    private static function pendingUnits(array $commitment): int
    {
        $total = Inventory_Balance::units($commitment['committed_units']);
        $fulfilled = Inventory_Balance::units($commitment['fulfilled_units']);
        $released = Inventory_Balance::units($commitment['released_units']);
        if (Inventory_Balance::safeAdd($fulfilled, $released) > $total) {
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
        return $total - $fulfilled - $released;
    }

    private static function pending(
        Transaction_Context $tx, Event_Actor $actor, string $operationId
    ): void {
        self::module();
        Event_Journal::requirePendingOperation($tx, $operationId);
        $actor->assertCurrent();
    }

    private static function module(): void
    {
        if (!Module_Gates::allows('inventory', 'write')) {
            throw new Transaction_Failure('pc_inventory_not_ready');
        }
    }

    private static function digest(string $safe): string
    {
        return \hash('sha256', $safe);
    }
}
