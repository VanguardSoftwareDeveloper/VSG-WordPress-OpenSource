<?php
/**
 * IMP-17 — saldo físico y disponibilidad bajo fila ancla pc_sellables.
 *
 * Aritmética en unidades enteras: existencias - compromisos pendientes
 * - holds vigentes - admisiones activas. Una salida física disminuye
 * existencias y aumenta fulfilled_units en la MISMA transacción, por lo
 * que no se descuenta dos veces. No publica cantidades al comprador.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Inventory_Balance
{
    private const TABLES = [
        'pc_sellables', 'pc_stock_movements', 'pc_stock_commitments',
        'pc_holds', 'pc_resource_admissions', 'pc_order_lines',
        'pc_orders', 'pc_payment_attempts', 'pc_idempotency',
    ];

    public static function table(string $suffix): string
    {
        if (!\in_array($suffix, self::TABLES, true)) {
            throw new Transaction_Failure('pc_inventory_table_denied');
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        $names = Schema::tableNames($wpdb);
        if (!\is_string($names[$suffix] ?? null)) {
            throw new Transaction_Failure('pc_inventory_table_denied');
        }
        return $names[$suffix];
    }

    /**
     * Requiere lockSellables() previo. No hacer SELECT normal + INSERT de
     * reservas en otra transacción: una variante sin reservas también tiene
     * una fila ancla bloqueable.
     *
     * @return array{sellable_id:string,track_stock:bool,on_hand_units:int,
     *   committed_units:int,held_units:int,admitted_units:int,available_units:?int}
     */
    public static function within(Transaction_Context $tx, string $sellableId): array
    {
        $sellableId = Domain_Validation::sqlId($sellableId);
        $sellable = $tx->lockedSellable($sellableId);
        if ('physical' !== (string) $sellable['kind']) {
            throw new Transaction_Failure('pc_inventory_ineligible');
        }
        $onHand = self::units($sellable['stock_on_hand']);

        $commitments = self::units(self::aggregate($tx,
            'SELECT COALESCE(SUM(committed_units - fulfilled_units - released_units),0) AS units '
                . 'FROM %i WHERE sellable_id = %s',
            [self::table('pc_stock_commitments'), $sellableId]
        ));
        $held = self::units(self::aggregate($tx,
            'SELECT COALESCE(SUM(quantity),0) AS units FROM %i WHERE sellable_id = %s '
                . 'AND resource_kind = %s AND state = %s AND expires_at > UTC_TIMESTAMP(6)',
            [self::table('pc_holds'), $sellableId, 'stock', 'VIGENTE']
        ));
        $admitted = self::units(self::aggregate($tx,
            'SELECT COALESCE(SUM(quantity),0) AS units FROM %i WHERE sellable_id = %s '
                . 'AND consumed_at IS NULL AND released_at IS NULL '
                . 'AND expires_at > UTC_TIMESTAMP(6)',
            [self::table('pc_resource_admissions'), $sellableId]
        ));

        $reserved = self::safeAdd(self::safeAdd($commitments, $held), $admitted);
        $tracked = '1' === (string) $sellable['track_stock'];
        if ($tracked && $reserved > $onHand) {
            // No ocultar sobreventa/corrupción como disponibilidad cero.
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
        return [
            'sellable_id' => $sellableId,
            'track_stock' => $tracked,
            'on_hand_units' => $onHand,
            'committed_units' => $commitments,
            'held_units' => $held,
            'admitted_units' => $admitted,
            'available_units' => $tracked ? $onHand - $reserved : null,
        ];
    }

    public static function demandWithin(
        Transaction_Context $tx, string $sellableId, int $quantity
    ): array {
        self::quantity($quantity);
        $locked = $tx->lockedSellable(Domain_Validation::sqlId($sellableId));
        if ('1' !== (string) $locked['active']) {
            throw new Transaction_Failure('pc_inventory_unavailable');
        }
        $snapshot = self::within($tx, $sellableId);
        if (null !== $snapshot['available_units']
            && $snapshot['available_units'] < $quantity) {
            throw new Transaction_Failure('pc_inventory_unavailable');
        }
        return $snapshot;
    }

    /**
     * Lectura pública en lotes de variantes físicas YA obtenidas bajo
     * LOCK IN SHARE MODE por el lector del catálogo. No intentar elevar
     * esos locks a FOR UPDATE: dos lectores concurrentes se bloquearían
     * al intentar el upgrade. Todas las escrituras mantienen la misma
     * fila sellable como ancla exclusiva.
     *
     * No devuelve existencias, solo elegibilidad categórica por ID.
     * Fuera de la transacción/lock del caller NO es una cotización.
     *
     * @param list<array<string,mixed>> $rows
     * @return array<string,bool>
     */
    public static function publicAvailabilityWithin(
        Transaction_Context $tx, array $rows
    ): array {
        $available = [];
        $tracked = [];
        foreach ($rows as $row) {
            if ('physical' !== ($row['kind'] ?? null)
                || '1' !== (string) ($row['active'] ?? '0')) {
                continue;
            }
            $id = Domain_Validation::sqlId((string) ($row['id'] ?? ''));
            if (isset($available[$id])) {
                throw new Transaction_Failure('pc_inventory_invariant_invalid');
            }
            if ('0' === (string) ($row['track_stock'] ?? '')) {
                $available[$id] = true;
            } elseif ('1' === (string) ($row['track_stock'] ?? '')) {
                $available[$id] = false;
                $tracked[$id] = self::units($row['stock_on_hand'] ?? null);
            } else {
                throw new Transaction_Failure('pc_inventory_invariant_invalid');
            }
        }

        // Tres agregados por lote de 500 ID. Evita 3*N consultas cuando
        // WordPress muestra muchas fichas con 100 variantes cada una.
        $reserved = [];
        foreach (\array_chunk(\array_keys($tracked), 500) as $batch) {
            $marks = \implode(',', \array_fill(0, \count($batch), '%s'));
            $q = [
                [
                    'SELECT sellable_id, COALESCE(SUM(committed_units - fulfilled_units '
                        . '- released_units),0) AS units FROM %i '
                        . 'WHERE sellable_id IN (' . $marks . ') GROUP BY sellable_id',
                    self::table('pc_stock_commitments'),
                    [],
                ],
                [
                    'SELECT sellable_id, COALESCE(SUM(quantity),0) AS units FROM %i '
                        . 'WHERE sellable_id IN (' . $marks . ') '
                        . 'AND resource_kind = %s AND state = %s '
                        . 'AND expires_at > UTC_TIMESTAMP(6) GROUP BY sellable_id',
                    self::table('pc_holds'),
                    ['stock', 'VIGENTE'],
                ],
                [
                    'SELECT sellable_id, COALESCE(SUM(quantity),0) AS units FROM %i '
                        . 'WHERE sellable_id IN (' . $marks . ') '
                        . 'AND consumed_at IS NULL AND released_at IS NULL '
                        . 'AND expires_at > UTC_TIMESTAMP(6) GROUP BY sellable_id',
                    self::table('pc_resource_admissions'),
                    [],
                ],
            ];
            foreach ($q as [$sql, $table, $extra]) {
                foreach ($tx->rows($sql, \array_merge([$table], $batch, $extra)) as $record) {
                    $id = Domain_Validation::sqlId((string) $record['sellable_id']);
                    if (!\array_key_exists($id, $tracked)) {
                        throw new Transaction_Failure('pc_inventory_invariant_invalid');
                    }
                    $reserved[$id] = self::safeAdd(
                        $reserved[$id] ?? 0, self::units($record['units'])
                    );
                }
            }
        }
        foreach ($tracked as $id => $onHand) {
            if (($reserved[$id] ?? 0) > $onHand) {
                throw new Transaction_Failure('pc_inventory_invariant_invalid');
            }
            $available[$id] = $onHand > ($reserved[$id] ?? 0);
        }
        return $available;
    }

    public static function quantity(int $quantity): int
    {
        if ($quantity < 1 || $quantity > 100000) {
            throw new Transaction_Failure('pc_inventory_ineligible');
        }
        return $quantity;
    }

    public static function units(mixed $raw): int
    {
        if (!\is_string($raw) && !\is_int($raw)) {
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
        try {
            return Domain_Validation::unitsCount((string) $raw);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
    }

    public static function safeAdd(int $a, int $b): int
    {
        if ($a < 0 || $b < 0 || $a > \PHP_INT_MAX - $b) {
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
        return $a + $b;
    }

    public static function lastInsertId(Transaction_Context $tx): string
    {
        $rows = $tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_inventory_write_failed');
        }
        return Domain_Validation::sqlId((string) $rows[0]['id']);
    }

    /** Aggregate de origen propio, siempre dentro del lock ancla. */
    private static function aggregate(Transaction_Context $tx, string $sql, array $args): string
    {
        $rows = $tx->rows($sql, $args);
        if (1 !== \count($rows) || !isset($rows[0]['units'])) {
            throw new Transaction_Failure('pc_inventory_invariant_invalid');
        }
        return (string) $rows[0]['units'];
    }
}
