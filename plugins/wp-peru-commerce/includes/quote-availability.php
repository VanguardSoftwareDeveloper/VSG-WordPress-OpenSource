<?php
/**
 * IMP-24 — disponibilidad ORIENTATIVA de físicos, digitales y servicios.
 *
 * Se ejecuta en la MISMA transacción de cotización que lee precios, settings
 * y UBIGEO. No crea holds, admissions, citas ni derechos digitales.
 * Todas las operaciones comerciales revalidan recursos bajo sus propios
 * locks al pulsar Pagar; una cotización nunca concede disponibilidad.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Quote_Availability
{
    /**
     * Comprueba cantidad REAL solicitada, no solo stock > 0.
     * Los productos de inventario opcional siguen sin límite de existencias
     * pero requieren los controles operativos de checkout.
     *
     * Las filas de pc_sellables se leyeron bajo LOCK IN SHARE MODE y todos
     * los escritores las bloquean FOR UPDATE antes de modificar compromisos.
     *
     * @param array<string,array<string,mixed>> $rows
     * @param array<string,int> $quantities
     */
    public static function physicalWithin(
        Transaction_Context $tx, array $rows, array $quantities
    ): void {
        $tracked = [];
        foreach ($quantities as $id => $requested) {
            $row = $rows[$id] ?? null;
            if (!\is_array($row) || 'physical' !== (string) $row['kind']
                || $requested < 1 || $requested > 100000) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            if ('0' === (string) $row['track_stock']) {
                continue;
            }
            if ('1' !== (string) $row['track_stock']) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            $tracked[$id] = [
                'on_hand' => Inventory_Balance::units($row['stock_on_hand']),
                'requested' => $requested,
            ];
        }
        if ([] === $tracked) {
            return;
        }
        $ids = \array_keys($tracked);
        $marks = \implode(',', \array_fill(0, \count($ids), '%s'));
        $sources = [
            [
                'SELECT sellable_id, COALESCE(SUM(committed_units - fulfilled_units '
                    . '- released_units),0) AS units FROM %i '
                    . 'WHERE sellable_id IN (' . $marks . ') GROUP BY sellable_id',
                Inventory_Balance::table('pc_stock_commitments'), [],
            ],
            [
                'SELECT sellable_id, COALESCE(SUM(quantity),0) AS units FROM %i '
                    . 'WHERE sellable_id IN (' . $marks . ') '
                    . 'AND resource_kind = %s AND state = %s '
                    . 'AND expires_at > UTC_TIMESTAMP(6) GROUP BY sellable_id',
                Inventory_Balance::table('pc_holds'), ['stock', 'VIGENTE'],
            ],
            [
                'SELECT sellable_id, COALESCE(SUM(quantity),0) AS units FROM %i '
                    . 'WHERE sellable_id IN (' . $marks . ') '
                    . 'AND consumed_at IS NULL AND released_at IS NULL '
                    . 'AND expires_at > UTC_TIMESTAMP(6) GROUP BY sellable_id',
                Inventory_Balance::table('pc_resource_admissions'), [],
            ],
        ];
        $reserved = [];
        foreach ($sources as [$sql, $table, $extra]) {
            foreach ($tx->rows($sql, \array_merge([$table], $ids, $extra)) as $row) {
                $id = Domain_Validation::sqlId((string) $row['sellable_id']);
                if (!isset($tracked[$id])) {
                    throw new Transaction_Failure('pc_quote_invariant_invalid');
                }
                $reserved[$id] = Inventory_Balance::safeAdd(
                    $reserved[$id] ?? 0,
                    Inventory_Balance::units($row['units'])
                );
            }
        }
        foreach ($tracked as $id => $quantity) {
            $used = $reserved[$id] ?? 0;
            if ($used > $quantity['on_hand']) {
                // No presentar una corrupción/oversell como "sin stock".
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            if ($quantity['requested'] > $quantity['on_hand'] - $used) {
                throw new Transaction_Failure('pc_quote_unavailable');
            }
        }
    }

    /**
     * Snapshots de TODOS los slots activos de la variante digital.
     * Un slot vendible sin archivo privado íntegro en SQL no se ofrece.
     * Se usa únicamente hash/ID/versión; jamás private_storage_key o URL.
     *
     * @param list<string> $ids
     * @return array<string,list<array{slot_key:string,file_id:string,row_version:string,sha256:string}>>
     */
    public static function digitalWithin(Transaction_Context $tx, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $marks = \implode(',', \array_fill(0, \count($ids), '%s'));
        global $wpdb;
        $tables = Schema::tableNames($wpdb);
        $rows = $tx->rows(
            'SELECT a.sellable_id, a.slot_key, a.active_file_id, a.row_version, '
                . 'HEX(f.content_hash) AS file_sha256, f.byte_size, f.archived_at, '
                . 'f.owner_product_post_id AS file_product_id, '
                . 's.product_post_id AS sellable_product_id '
                . 'FROM %i a LEFT JOIN %i f ON f.id = a.active_file_id '
                . 'AND f.sellable_id = a.sellable_id '
                . 'INNER JOIN %i s ON s.id = a.sellable_id '
                . 'WHERE a.sellable_id IN (' . $marks . ') '
                . 'ORDER BY a.sellable_id,a.slot_key LIMIT 201 LOCK IN SHARE MODE',
            \array_merge([
                $tables['pc_digital_assignments'], $tables['pc_digital_files'],
                $tables['pc_sellables'],
            ], $ids)
        );
        // AdminOrder.digital_rights admite como máximo 200 derechos.
        // Rechazar un carrito que no podría proyectar todos los archivos
        // de las variantes vendidas, nunca truncar silenciosamente.
        if (\count($rows) > 200) {
            throw new Transaction_Failure('pc_quote_unavailable');
        }
        $result = [];
        foreach ($rows as $row) {
            $id = Domain_Validation::sqlId((string) $row['sellable_id']);
            if (!\in_array($id, $ids, true)) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            $key = (string) $row['slot_key'];
            $sha = \strtolower((string) $row['file_sha256']);
            if (!\preg_match('/\A[a-z0-9][a-z0-9_-]{0,99}\z/D', $key)
                || null !== $row['archived_at']
                || null === $row['file_product_id']
                || (string) $row['file_product_id'] !== (string) $row['sellable_product_id']
                || !\preg_match('/\A[a-f0-9]{64}\z/D', $sha)
                || (!\is_string($row['byte_size']) && !\is_int($row['byte_size']))
                || !\ctype_digit((string) $row['byte_size'])
                || '0' === (string) $row['byte_size']
                || isset($result[$id][$key])) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            $result[$id][$key] = [
                'slot_key' => $key,
                'file_id' => Domain_Validation::sqlId((string) $row['active_file_id']),
                'row_version' => Domain_Validation::sqlId((string) $row['row_version']),
                'sha256' => $sha,
            ];
            if (\count($result[$id]) > 100) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
        }
        $canonical = [];
        foreach ($ids as $id) {
            if (empty($result[$id])) {
                // No "vender" digital sin al menos un binario activo.
                throw new Transaction_Failure('pc_quote_unavailable');
            }
            \ksort($result[$id], \SORT_STRING);
            $canonical[$id] = \array_values($result[$id]);
        }
        return $canonical;
    }

    /**
     * Confirmar coincidencia exacta con una franja del horario semanal
     * (incluye madrugada heredada del día anterior), buffers, excepciones,
     * citas, holds y admisiones. No adquiere cupo ni publica "reservado".
     *
     * @param array<string,mixed> $sellable
     * @param array<string,mixed> $selection
     * @return array<string,string> Metadatos de agenda para fingerprint
     */
    public static function serviceWithin(
        Transaction_Context $tx, array $sellable, array $selection
    ): array {
        if (!\is_string($selection['starts_at_utc'] ?? null)
            || !\is_string($selection['ends_at_utc'] ?? null)
            || (isset($selection['timezone'])
                && !\is_string($selection['timezone']))) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'lines.slot');
        }
        try {
            $start = Business_Time::fromApi($selection['starts_at_utc']);
            $end = Business_Time::fromApi($selection['ends_at_utc']);
            Business_Time::requireInterval($start, $end);
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'lines.slot');
        }
        if ($start < Business_Time::nowUtc()) {
            throw new Transaction_Failure('pc_quote_unavailable');
        }

        $duration = self::minutes($sellable['service_duration_minutes'], 1);
        $before = self::minutes($sellable['service_buffer_before_minutes'], 0);
        $after = self::minutes($sellable['service_buffer_after_minutes'], 0);
        if ($end != Agenda_Time::addMinutes($start, $duration)) {
            throw new Transaction_Failure('pc_quote_unavailable');
        }

        $anchor = Agenda_Repository::anchorWithin($tx);
        $timezone = (string) $anchor['timezone'];
        if (isset($selection['timezone']) && $selection['timezone'] !== $timezone) {
            throw new Transaction_Failure('pc_quote_unavailable');
        }
        $date = Business_Time::dateInBusinessZone($start,$timezone);
        $prior = Agenda_Time::day($date,-1);
        $prev = Agenda_Repository::scheduleWithin($tx,$prior,$anchor);
        $current = Agenda_Repository::scheduleWithin($tx,$date,$anchor);

        $occupiedStart = Agenda_Time::addMinutes($start,-$before);
        $occupiedEnd = Agenda_Time::addMinutes($end,$after);
        $step = ($duration + $before + $after) * 60;
        $candidates = [];
        foreach ([[$prior,$prev],[$date,$current]] as [$day,$schedule]) {
            foreach (Agenda_Time::windows(
                $schedule['weekly_rules'],$day,$timezone
            ) as [$open,$close]) {
                $first = Agenda_Time::addMinutes($open,$before);
                $delta = $start->getTimestamp() - $first->getTimestamp();
                if ($delta >= 0 && 0 === $delta % $step
                    && $occupiedEnd <= $close && $occupiedStart >= $open) {
                    $candidates[] = [
                        'resource_id' => Agenda_Time::RESOURCE,
                        'schedule_revision' => (string) $schedule['active_revision_no'],
                        'schedule_row_version' => (string) $schedule['row_version'],
                        'schedule_effective_from' => (string) $schedule['effective_from'],
                        'timezone' => $timezone,
                        'starts_at_utc' => Business_Time::toApi($start),
                        'ends_at_utc' => Business_Time::toApi($end),
                    ];
                }
            }
        }
        if (1 !== \count($candidates)) {
            throw new Transaction_Failure('pc_quote_unavailable');
        }
        foreach (Agenda_Repository::busyWithin($tx,$occupiedStart,$occupiedEnd)
            as [$busyFrom,$busyTo]) {
            if (Agenda_Time::intersects(
                $occupiedStart,$occupiedEnd,$busyFrom,$busyTo
            )) {
                throw new Transaction_Failure('pc_quote_unavailable');
            }
        }
        return $candidates[0];
    }

    private static function minutes(mixed $value,int $min): int
    {
        if (!\is_int($value) && !\is_string($value)
            || !\ctype_digit((string) $value)
            || \strlen((string) $value) > 4) {
            throw new Transaction_Failure('pc_quote_invariant_invalid');
        }
        $n = (int) $value;
        if ($n < $min || $n > 1440) {
            throw new Transaction_Failure('pc_quote_invariant_invalid');
        }
        return $n;
    }
}
