<?php
/**
 * IMP-18 — selección versionada del horario, lecturas de citas/cierres y
 * solapamientos del recurso único sobre el esquema actual de 39 tablas.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Agenda_Repository
{
    private const TABLES = [
        'pc_schedule_revisions', 'pc_schedule_rules', 'pc_schedule_blocks',
        'pc_calendar_days', 'pc_appointments', 'pc_order_lines', 'pc_holds',
        'pc_resource_admissions', 'pc_sellables',
    ];

    public static function table(string $name): string
    {
        if (!\in_array($name, self::TABLES, true)) {
            throw new Transaction_Failure('pc_agenda_invariant_invalid');
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        $names = Schema::tableNames($wpdb);
        return $names[$name];
    }

    /**
     * El lector comparte el ancla con los mutadores (FOR UPDATE) para no
     * mezclar revisiones/slots de fechas distintas en READ COMMITTED.
     */
    public static function anchorWithin(Transaction_Context $tx): array
    {
        $rows = $tx->rows(
            'SELECT resource_id, row_version, active_revision_no, timezone '
                . 'FROM %i WHERE resource_id = %s LOCK IN SHARE MODE',
            [self::table('pc_schedule_revisions'), Agenda_Time::RESOURCE]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        $record = $rows[0];
        Business_Time::businessZone((string) $record['timezone']);
        Domain_Validation::sqlId((string) $record['row_version']);
        if (!\preg_match('/\A(?:0|[1-9][0-9]*)\z/D', (string) $record['active_revision_no'])) {
            throw new Transaction_Failure('pc_agenda_invariant_invalid');
        }
        return $record;
    }

    /** Devuelve la revisión efectiva por fecha, no la última revisión futura. */
    public static function scheduleWithin(
        Transaction_Context $tx, string $date, array $anchor
    ): array {
        $date = Business_Time::localDate($date);
        $resource = Agenda_Time::RESOURCE;
        if ((string) $anchor['resource_id'] !== $resource) {
            throw new Transaction_Failure('pc_agenda_invariant_invalid');
        }
        $rev = (string) $anchor['active_revision_no'];
        if ('0' === $rev) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        $query = $tx->rows(
            'SELECT revision_no, MIN(valid_from) AS effective_from '
                . 'FROM %i WHERE resource_id = %s AND revision_no <= %s '
                . 'AND valid_from <= %s GROUP BY revision_no '
                . 'ORDER BY revision_no DESC LIMIT 1',
            [self::table('pc_schedule_rules'), $resource, $rev, $date]
        );
        if (1 !== \count($query)) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        $effective = Business_Time::localDate((string) $query[0]['effective_from']);
        $version = Domain_Validation::sqlId((string) $query[0]['revision_no']);
        $rows = $tx->rows(
            'SELECT weekday, window_index, enabled, opens_local, closes_local, '
                . 'closes_day_offset, valid_from, valid_to FROM %i '
                . 'WHERE resource_id = %s AND revision_no = %s '
                . 'ORDER BY weekday ASC, window_index ASC',
            [self::table('pc_schedule_rules'), $resource, $version]
        );
        if ([] === $rows || \count($rows) > 28) {
            throw new Transaction_Failure('pc_agenda_invariant_invalid');
        }
        $rules = [];
        foreach ($rows as $row) {
            if ((string) $row['valid_from'] !== $effective
                || null !== $row['valid_to']) {
                throw new Transaction_Failure('pc_agenda_invariant_invalid');
            }
            $sqlDay = (int) $row['weekday'];
            if ($sqlDay < 0 || $sqlDay > 6 || !\in_array((string) $row['enabled'], ['0','1'], true)) {
                throw new Transaction_Failure('pc_agenda_invariant_invalid');
            }
            $enabled = '1' === (string) $row['enabled'];
            $rule = [
                'weekday' => 0 === $sqlDay ? 7 : $sqlDay,
                'window_index' => (int) $row['window_index'],
                'enabled' => $enabled,
                'closes_day_offset' => (int) $row['closes_day_offset'],
            ];
            if ($enabled) {
                if (!\is_string($row['opens_local']) || !\is_string($row['closes_local'])) {
                    throw new Transaction_Failure('pc_agenda_invariant_invalid');
                }
                $rule['opens_local'] = self::timeString($row['opens_local']);
                $rule['closes_local'] = self::timeString($row['closes_local']);
            } elseif (null !== $row['opens_local'] || null !== $row['closes_local']) {
                throw new Transaction_Failure('pc_agenda_invariant_invalid');
            }
            $rules[] = $rule;
        }
        $normalized = Agenda_Time::normalizeRules($rules);
        if (\count($normalized) !== \count($rules)) {
            throw new Transaction_Failure('pc_agenda_invariant_invalid');
        }
        return [
            'resource_id' => $resource,
            'row_version' => (string) $anchor['row_version'],
            'active_revision_no' => $version,
            'effective_from' => $effective,
            'timezone' => (string) $anchor['timezone'],
            'weekly_rules' => $normalized,
        ];
    }

    /** Recupera todos los cierres (incluidos retirados) para la fecha local. */
    public static function closuresWithin(
        Transaction_Context $tx, \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd
    ): array {
        $rows = $tx->rows(
            'SELECT id, starts_at_utc, ends_at_utc, reason_code, row_version, active '
                . 'FROM %i WHERE resource_id = %s AND starts_at_utc < %s '
                . 'AND ends_at_utc > %s ORDER BY starts_at_utc, id LIMIT 101',
            [self::table('pc_schedule_blocks'), Agenda_Time::RESOURCE,
                Business_Time::toDatabase($dayEnd), Business_Time::toDatabase($dayStart)]
        );
        if (\count($rows) > 100) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        return \array_map(static fn (array $r): array => [
            'starts_at_utc' => Business_Time::toApi(Business_Time::fromDatabase((string) $r['starts_at_utc'])),
            'ends_at_utc' => Business_Time::toApi(Business_Time::fromDatabase((string) $r['ends_at_utc'])),
            'closure_id' => Domain_Validation::sqlId((string) $r['id']),
            'row_version' => Domain_Validation::sqlId((string) $r['row_version']),
            'active' => '1' === (string) $r['active'],
            'reason_code' => (string) $r['reason_code'],
        ], $rows);
    }

    public static function appointmentsWithin(
        Transaction_Context $tx, \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd
    ): array {
        $rows = $tx->rows(
            'SELECT id, starts_at_utc, ends_at_utc, row_version, cancelled_at, '
                . 'completed_at, no_show_at FROM %i '
                . 'WHERE resource_id = %s AND starts_at_utc < %s '
                . 'AND ends_at_utc > %s ORDER BY starts_at_utc, id LIMIT 101',
            [self::table('pc_appointments'), Agenda_Time::RESOURCE,
                Business_Time::toDatabase($dayEnd), Business_Time::toDatabase($dayStart)]
        );
        if (\count($rows) > 100) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        $out = [];
        foreach ($rows as $r) {
            $state = null !== $r['cancelled_at'] ? 'CANCELADA'
                : (null !== $r['completed_at'] ? 'REALIZADA'
                : (null !== $r['no_show_at'] ? 'NO_ASISTIO' : 'CONFIRMADA'));
            $out[] = [
                'appointment_id' => Domain_Validation::sqlId((string) $r['id']),
                'starts_at_utc' => Business_Time::toApi(Business_Time::fromDatabase((string) $r['starts_at_utc'])),
                'ends_at_utc' => Business_Time::toApi(Business_Time::fromDatabase((string) $r['ends_at_utc'])),
                'state' => $state,
                'row_version' => Domain_Validation::sqlId((string) $r['row_version']),
            ];
        }
        return $out;
    }

    /**
     * Intervalos ocupados sin secretos/identidades. Capturar a partir de
     * snapshots históricos de buffers; el buffer actual del catálogo no
     * reescribe una cita vendida. Sin snapshot no prometer disponibilidad.
     *
     * @return list<array{0:\DateTimeImmutable,1:\DateTimeImmutable}>
     */
    public static function busyWithin(
        Transaction_Context $tx, \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc, ?string $exceptClosureId = null,
        ?string $exceptAppointmentId = null
    ): array {
        Business_Time::requireInterval($startUtc, $endUtc);
        $from = Business_Time::toDatabase(Agenda_Time::addMinutes($startUtc, -1440));
        $to = Business_Time::toDatabase(Agenda_Time::addMinutes($endUtc, 1440));
        $start = Business_Time::toDatabase($startUtc);
        $end = Business_Time::toDatabase($endUtc);
        $occupied = [];

        $appointmentArgs = [self::table('pc_appointments'),
            self::table('pc_order_lines'),Agenda_Time::RESOURCE,$to,$from];
        $appointmentSql = 'SELECT a.starts_at_utc, a.ends_at_utc, l.catalog_snapshot '
            . 'FROM %i a INNER JOIN %i l ON l.id = a.order_line_id '
            . 'WHERE a.resource_id = %s AND a.cancelled_at IS NULL '
            . 'AND a.completed_at IS NULL AND a.no_show_at IS NULL '
            . 'AND a.starts_at_utc < %s AND a.ends_at_utc > %s';
        // R8-38: excluir ÚNICAMENTE la cita propia mientras se verifica
        // la franja nueva; se mantiene ocupada y bloqueada hasta el COMMIT.
        if (null !== $exceptAppointmentId) {
            $appointmentSql .= ' AND a.id <> %s';
            $appointmentArgs[] = Domain_Validation::sqlId($exceptAppointmentId);
        }
        $appointments = $tx->rows(
            $appointmentSql.' LIMIT 1001',$appointmentArgs
        );
        if (\count($appointments) > 1000) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        foreach ($appointments as $r) {
            $snap = self::bufferSnapshot((string) $r['catalog_snapshot']);
            $a = Agenda_Time::addMinutes(
                Business_Time::fromDatabase((string) $r['starts_at_utc']), -$snap[0]
            );
            $b = Agenda_Time::addMinutes(
                Business_Time::fromDatabase((string) $r['ends_at_utc']), $snap[1]
            );
            if (Agenda_Time::intersects($startUtc, $endUtc, $a, $b)) {
                $occupied[] = [$a, $b];
            }
        }

        $holds = $tx->rows(
            'SELECT slot_start_utc, slot_end_utc FROM %i '
                . 'WHERE resource_id = %s AND state = %s '
                . 'AND expires_at > UTC_TIMESTAMP(6) '
                . 'AND slot_start_utc < %s AND slot_end_utc > %s LIMIT 1001',
            [self::table('pc_holds'), Agenda_Time::RESOURCE, 'VIGENTE', $end, $start]
        );
        if (\count($holds) > 1000) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        foreach ($holds as $r) {
            $occupied[] = [
                Business_Time::fromDatabase((string) $r['slot_start_utc']),
                Business_Time::fromDatabase((string) $r['slot_end_utc']),
            ];
        }
        $admissions = $tx->rows(
            'SELECT slot_start_utc, slot_end_utc FROM %i '
                . 'WHERE resource_id = %s AND consumed_at IS NULL '
                . 'AND released_at IS NULL AND expires_at > UTC_TIMESTAMP(6) '
                . 'AND slot_start_utc < %s AND slot_end_utc > %s LIMIT 1001',
            [self::table('pc_resource_admissions'), Agenda_Time::RESOURCE, $end, $start]
        );
        if (\count($admissions) > 1000) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        foreach ($admissions as $r) {
            $occupied[] = [
                Business_Time::fromDatabase((string) $r['slot_start_utc']),
                Business_Time::fromDatabase((string) $r['slot_end_utc']),
            ];
        }
        $args = [self::table('pc_schedule_blocks'), Agenda_Time::RESOURCE, $end, $start];
        $sql = 'SELECT id, starts_at_utc, ends_at_utc FROM %i '
            . 'WHERE resource_id = %s AND active = 1 '
            . 'AND starts_at_utc < %s AND ends_at_utc > %s';
        if (null !== $exceptClosureId) {
            $sql .= ' AND id <> %s';
            $args[] = Domain_Validation::sqlId($exceptClosureId);
        }
        $sql .= ' LIMIT 1001';
        $closures = $tx->rows($sql, $args);
        if (\count($closures) > 1000) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        foreach ($closures as $r) {
            $occupied[] = [
                Business_Time::fromDatabase((string) $r['starts_at_utc']),
                Business_Time::fromDatabase((string) $r['ends_at_utc']),
            ];
        }
        return $occupied;
    }

    /** SQL TIME from MySQL returns HH:MM:SS (seconds must be exactly zero). */
    private static function timeString(string $sql): string
    {
        if (!\preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:00\z/D', $sql)) {
            throw new Transaction_Failure('pc_agenda_invariant_invalid');
        }
        return \substr($sql, 0, 5);
    }

    /** Exige buffers inmutables del pedido, también para revisar nuevos horarios. */
    public static function bufferSnapshot(string $json): array
    {
        try {
            $raw = \json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
            $before = $raw['service_buffer_before_minutes'] ?? null;
            $after = $raw['service_buffer_after_minutes'] ?? null;
            if (!\is_int($before) || !\is_int($after)
                || $before < 0 || $before > 1440 || $after < 0 || $after > 1440) {
                throw new \UnexpectedValueException();
            }
            return [$before, $after];
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
    }
}
