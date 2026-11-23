<?php
/**
 * IMP-18 — R8-37 cierres puntuales y R8-53 horario semanal versionado.
 *
 * Ninguna mutación reprograma citas ni crea pagos/holds. Debe guardarse en
 * una transacción corta con idempotencia, locks, CAS y auditoría inmutable.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Agenda_Mutations
{
    public static function closure(array $body): array
    {
        self::staff();
        self::resource($body['resource_id'] ?? null);
        $action = $body['action'] ?? null;
        if (!\in_array($action, ['create','update','retire'], true)) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'action');
        }
        $reasonHash = self::reason($body['reason'] ?? null);
        $oldId = null;
        $version = null;
        if ('create' !== $action) {
            $oldId = Domain_Validation::sqlId($body['closure_id']);
            $version = Domain_Validation::sqlId($body['expected_version']);
        }
        $new = null;
        if ('retire' !== $action) {
            $new = self::interval($body['starts_at'], $body['ends_at']);
        }
        $scope = self::scope('agenda_closure');
        $payload = $body;
        $key = $body['idempotency_key'];
        unset($payload['idempotency_key']);
        $actor = Event_Actor::staff(['perucommerce_appointments_manage']);

        return Transaction::run(static function (Transaction_Context $tx) use (
            $scope, $payload, $key, $actor, $action, $reasonHash, $oldId, $version, $new
        ): array {
            $claim = Idempotency::claimWithin(
                $tx, $scope, 'agenda_closure', $key, $payload
            );
            if ('replay' === $claim['status']) {
                if ('closure' !== $claim['entity_type']) {
                    throw new Transaction_Failure('pc_idempotency_result_invalid');
                }
                return [
                    'operation_id' => $claim['operation_id'],
                    'entity_id' => $claim['entity_id'],
                    'result' => 'already_applied',
                ];
            }
            self::newClaim($claim);
            $anchor = $tx->lockResource(Agenda_Time::RESOURCE);
            $zone = (string) $anchor['timezone'];
            Business_Time::businessZone($zone);
            $old = null;
            if (null !== $oldId) {
                $rows = $tx->rows(
                    'SELECT id, resource_id, starts_at_utc, ends_at_utc, '
                        . 'active, row_version FROM %i '
                        . 'WHERE id = %s AND resource_id = %s',
                    [Agenda_Repository::table('pc_schedule_blocks'), $oldId,
                        Agenda_Time::RESOURCE]
                );
                if (1 !== \count($rows)) {
                    throw new Transaction_Failure('pc_agenda_not_found');
                }
                $old = $rows[0];
                if ((string) $old['row_version'] !== $version
                    || '1' !== (string) $old['active']) {
                    throw new Transaction_Failure('pc_agenda_conflict');
                }
            }
            $days = [];
            if (null !== $old) {
                $days = \array_merge($days, Transaction_Context::datesTouched(
                    Business_Time::fromDatabase((string) $old['starts_at_utc']),
                    Business_Time::fromDatabase((string) $old['ends_at_utc']), $zone
                ));
            }
            if (null !== $new) {
                $days = \array_merge(
                    $days, Transaction_Context::datesTouched($new[0], $new[1], $zone)
                );
            }
            if ([] === $days || \count(\array_unique($days)) > 366) {
                throw new Transaction_Failure('pc_agenda_field_invalid');
            }
            $tx->lockCalendarDays(Agenda_Time::RESOURCE, \array_values(\array_unique($days)));
            if (null !== $old) {
                $confirm = $tx->rows(
                    'SELECT id, row_version, active FROM %i '
                        . 'WHERE id = %s AND resource_id = %s FOR UPDATE',
                    [Agenda_Repository::table('pc_schedule_blocks'), $oldId,
                        Agenda_Time::RESOURCE]
                );
                if (1 !== \count($confirm)
                    || (string) $confirm[0]['row_version'] !== $version
                    || '1' !== (string) $confirm[0]['active']) {
                    throw new Transaction_Failure('pc_agenda_conflict');
                }
            }
            if (null !== $new) {
                // R8-37 no altera una cita ya vendida ni crea un cierre
                // que intersecta holds/admisiones/otro cierre activo.
                foreach (Agenda_Repository::busyWithin($tx, $new[0], $new[1], $oldId) as $busy) {
                    if (Agenda_Time::intersects($new[0], $new[1], $busy[0], $busy[1])) {
                        throw new Transaction_Failure('pc_agenda_overlap');
                    }
                }
            }
            $table = Agenda_Repository::table('pc_schedule_blocks');
            if ('create' === $action) {
                if (1 !== $tx->execute(
                    'INSERT INTO %i (resource_id, starts_at_utc, ends_at_utc, '
                        . 'reason_code, actor_user_id, created_at, row_version, active) '
                        . 'VALUES (%s, %s, %s, %s, %s, UTC_TIMESTAMP(6), 1, 1)',
                    [$table, Agenda_Time::RESOURCE,
                        Business_Time::toDatabase($new[0]), Business_Time::toDatabase($new[1]),
                        'staff_closure', (string) \get_current_user_id()]
                )) {
                    throw new Transaction_Failure('pc_agenda_invariant_invalid');
                }
                $closureId = self::insertId($tx);
                $next = '1';
            } else {
                $closureId = $oldId;
                $next = self::next($version);
                if ('update' === $action) {
                    $affected = $tx->execute(
                        'UPDATE %i SET starts_at_utc = %s, ends_at_utc = %s, '
                            . 'reason_code = %s, actor_user_id = %s, row_version = row_version + 1 '
                            . 'WHERE id = %s AND resource_id = %s AND active = 1 AND row_version = %s',
                        [$table, Business_Time::toDatabase($new[0]),
                            Business_Time::toDatabase($new[1]), 'staff_closure',
                            (string) \get_current_user_id(), $oldId,
                            Agenda_Time::RESOURCE, $version]
                    );
                } else {
                    $affected = $tx->execute(
                        'UPDATE %i SET active = 0, actor_user_id = %s, '
                            . 'row_version = row_version + 1 '
                            . 'WHERE id = %s AND resource_id = %s AND active = 1 AND row_version = %s',
                        [$table, (string) \get_current_user_id(), $oldId,
                            Agenda_Time::RESOURCE, $version]
                    );
                }
                if (1 !== $affected) {
                    throw new Transaction_Failure('pc_version_conflict');
                }
            }
            Event_Journal::auditWithin(
                $tx, $actor, $claim['operation_id'], 'closure', $closureId,
                'agenda_closure_' . $action, 'staff_schedule_change',
                null === $old ? null : \hash('sha256', \json_encode($old, \JSON_THROW_ON_ERROR)),
                \hash('sha256', $reasonHash . ':' . $closureId . ':' . $next .
                    (null === $new ? ':retired' : ':' . Business_Time::toDatabase($new[0])
                        . ':' . Business_Time::toDatabase($new[1])))
            );
            Idempotency::completeLocalWithin(
                $tx, $claim['operation_id'], 'closure', $closureId, 200
            );
            return [
                'operation_id' => $claim['operation_id'],
                'entity_id' => $closureId,
                'row_version' => $next,
                'result' => 'create' === $action ? 'closure_created'
                    : ('update' === $action ? 'closure_updated' : 'closure_retired'),
            ];
        }, true);
    }

    public static function schedule(array $body): array
    {
        self::staff();
        self::resource($body['resource_id'] ?? null);
        $expected = Domain_Validation::sqlId($body['expected_version']);
        $effective = Business_Time::localDate($body['effective_from']);
        $rules = Agenda_Time::normalizeRules($body['weekly_rules']);
        $reasonHash = self::reason($body['reason']);
        $key = $body['idempotency_key'];
        $scope = self::scope('agenda_schedule');
        $payload = $body;
        unset($payload['idempotency_key']);
        $actor = Event_Actor::staff(['perucommerce_appointments_manage']);

        return Transaction::run(static function (Transaction_Context $tx) use (
            $scope, $payload, $key, $actor, $expected, $effective, $rules, $reasonHash
        ): array {
            $claim = Idempotency::claimWithin(
                $tx, $scope, 'agenda_schedule', $key, $payload
            );
            $anchor = $tx->lockResource(Agenda_Time::RESOURCE);
            if ('replay' === $claim['status']) {
                if ('agenda_revision' !== $claim['entity_type']
                    || $claim['entity_id'] !== Agenda_Time::RESOURCE
                    || (string) $anchor['row_version'] !== self::next($expected)) {
                    throw new Transaction_Failure('pc_agenda_conflict');
                }
                return [
                    'resource_id' => Agenda_Time::RESOURCE,
                    'row_version' => (string) $anchor['row_version'],
                    'effective_from' => $effective,
                    'operation_id' => $claim['operation_id'],
                ];
            }
            self::newClaim($claim);
            if ((string) $anchor['row_version'] !== $expected
                || '0' === (string) $anchor['active_revision_no']) {
                throw new Transaction_Failure('pc_version_conflict');
            }
            $zone = (string) $anchor['timezone'];
            Business_Time::businessZone($zone);
            $today = Business_Time::dateInBusinessZone(Business_Time::nowUtc(), $zone);
            if ($effective < $today) {
                throw new Transaction_Failure('pc_agenda_field_invalid');
            }
            $oldRevision = Domain_Validation::sqlId((string) $anchor['active_revision_no']);
            $last = $tx->rows(
                'SELECT MIN(valid_from) AS first_date, MAX(valid_from) AS last_date '
                    . 'FROM %i WHERE resource_id = %s AND revision_no = %s',
                [Agenda_Repository::table('pc_schedule_rules'),
                    Agenda_Time::RESOURCE, $oldRevision]
            );
            if (1 !== \count($last) || null === $last[0]['first_date']
                || $last[0]['first_date'] !== $last[0]['last_date']
                || $effective < (string) $last[0]['first_date']) {
                // No insertar una revisión intermedia que adelante otra
                // futura; hacerlo destruiría la historia por fecha.
                throw new Transaction_Failure('pc_agenda_conflict');
            }

            self::validateFutureAppointments($tx, $effective, $rules, $zone);

            $revision = self::next($oldRevision);
            foreach ($rules as $rule) {
                if (1 !== $tx->execute(
                    'INSERT INTO %i (resource_id, revision_no, window_index, weekday, '
                        . 'opens_local, closes_local, closes_day_offset, enabled, '
                        . 'valid_from, valid_to, row_version, created_at) '
                        . 'VALUES (%s, %s, %d, %d, NULLIF(%s,%s), NULLIF(%s,%s), '
                        . '%d, %d, %s, NULL, 1, UTC_TIMESTAMP(6))',
                    [Agenda_Repository::table('pc_schedule_rules'),
                        Agenda_Time::RESOURCE, $revision, $rule['window_index'],
                        7 === $rule['weekday'] ? 0 : $rule['weekday'],
                        ($rule['opens_local'] ?? '') === '' ? '' : $rule['opens_local'] . ':00',
                        '', ($rule['closes_local'] ?? '') === '' ? '' : $rule['closes_local'] . ':00',
                        '', $rule['closes_day_offset'], $rule['enabled'] ? 1 : 0, $effective]
                )) {
                    throw new Transaction_Failure('pc_agenda_invariant_invalid');
                }
            }
            $newVersion = $tx->advanceVersion(
                'pc_schedule_revisions', Agenda_Time::RESOURCE, $expected
            );
            if (1 !== $tx->execute(
                'UPDATE %i SET active_revision_no = %s, updated_by = %s '
                    . 'WHERE resource_id = %s AND active_revision_no = %s AND row_version = %s',
                [Agenda_Repository::table('pc_schedule_revisions'),
                    $revision, (string) \get_current_user_id(), Agenda_Time::RESOURCE,
                    $oldRevision, $newVersion]
            )) {
                throw new Transaction_Failure('pc_version_conflict');
            }
            Event_Journal::auditWithin(
                $tx, $actor, $claim['operation_id'], 'schedule',
                Agenda_Time::RESOURCE, 'agenda_schedule_revised',
                'weekly_schedule_change',
                \hash('sha256', $expected . ':' . $oldRevision),
                \hash('sha256', \json_encode($rules, \JSON_THROW_ON_ERROR) . ':'
                    . $effective . ':' . $reasonHash)
            );
            Idempotency::completeLocalWithin(
                $tx, $claim['operation_id'], 'agenda_revision',
                Agenda_Time::RESOURCE, 200
            );
            return [
                'resource_id' => Agenda_Time::RESOURCE,
                'row_version' => $newVersion,
                'effective_from' => $effective,
                'operation_id' => $claim['operation_id'],
            ];
        }, true);
    }

    /**
     * No eliminar ni desplazar citas prometidas. El snapshot histórico de
     * buffers debe existir; si un pedido anterior no lo conserva, denegar
     * la revisión en lugar de fingir que su buffer actual es cero.
     */
    private static function validateFutureAppointments(
        Transaction_Context $tx, string $effective, array $rules,
        string $zone
    ): void {
        $startUtc = Agenda_Time::wallUtc($effective, '00:00', $zone);
        $bufferedFrom = Business_Time::toDatabase(
            Agenda_Time::addMinutes($startUtc, -1440)
        );
        $appointments = $tx->rows(
            'SELECT a.starts_at_utc, a.ends_at_utc, l.catalog_snapshot '
                . 'FROM %i a INNER JOIN %i l ON l.id = a.order_line_id '
                . 'WHERE a.resource_id = %s AND a.cancelled_at IS NULL '
                . 'AND a.completed_at IS NULL AND a.no_show_at IS NULL '
                . 'AND a.ends_at_utc > %s ORDER BY a.starts_at_utc LIMIT 1001',
            [Agenda_Repository::table('pc_appointments'),
                Agenda_Repository::table('pc_order_lines'), Agenda_Time::RESOURCE,
                $bufferedFrom]
        );
        if (\count($appointments) > 1000) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        $tz = Business_Time::businessZone($zone);
        foreach ($appointments as $a) {
            [$before, $after] = Agenda_Repository::bufferSnapshot(
                (string) $a['catalog_snapshot']
            );
            $serviceStart = Business_Time::fromDatabase((string) $a['starts_at_utc']);
            $from = Agenda_Time::addMinutes($serviceStart, -$before);
            $end = Agenda_Time::addMinutes(
                Business_Time::fromDatabase((string) $a['ends_at_utc']), $after
            );
            // El inicio del servicio (no el buffer del día anterior)
            // determina qué revisiones afectan una cita futura.
            if ($serviceStart->setTimezone($tz)->format('Y-m-d') < $effective) {
                continue;
            }
            $date = $from->setTimezone($tz)->format('Y-m-d');
            $windows = \array_merge(
                Agenda_Time::windows($rules, $date, $zone),
                Agenda_Time::windows($rules, Agenda_Time::day($date, -1), $zone)
            );
            $inside = false;
            foreach ($windows as [$open, $close]) {
                if ($open <= $from && $end <= $close) {
                    $inside = true;
                    break;
                }
            }
            if (!$inside) {
                throw new Transaction_Failure('pc_agenda_conflict');
            }
        }

        // Mientras haya un intento con slot en curso, aplazar la nueva
        // revisión. Los consumidores de checkout tomarán el mismo ancla.
        foreach (['pc_holds' => 'state = %s AND expires_at > UTC_TIMESTAMP(6)',
            'pc_resource_admissions' =>
                'consumed_at IS NULL AND released_at IS NULL AND expires_at > UTC_TIMESTAMP(6)'] as $table => $extra) {
            $args = [Agenda_Repository::table($table), Agenda_Time::RESOURCE,
                $bufferedFrom];
            if ('pc_holds' === $table) {
                $args[] = 'VIGENTE';
            }
            $rows = $tx->rows(
                'SELECT id FROM %i WHERE resource_id = %s AND slot_end_utc > %s '
                    . 'AND ' . $extra . ' LIMIT 1',
                $args
            );
            if ([] !== $rows) {
                throw new Transaction_Failure('pc_agenda_conflict');
            }
        }
    }

    private static function interval(mixed $start, mixed $end): array
    {
        if (!\is_string($start) || !\is_string($end)) {
            throw new Rest_Failure('pc_field_invalid', 422);
        }
        try {
            $a = Business_Time::fromApi($start);
            $b = Business_Time::fromApi($end);
            Business_Time::requireInterval($a, $b);
            if (\count(Transaction_Context::datesTouched($a, $b, 'America/Lima')) > 366) {
                throw new \UnexpectedValueException();
            }
            return [$a, $b];
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid', 422);
        }
    }

    private static function resource(mixed $id): void
    {
        if (!\is_string($id) || $id !== Agenda_Time::RESOURCE) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'resource_id');
        }
    }

    private static function staff(): void
    {
        if (!\in_array(Access_Roles::currentRole(), ['A','O'], true)
            || !\current_user_can('perucommerce_appointments_manage')
            || !Module_Gates::allows('appointments', 'write')) {
            throw new Rest_Failure('pc_access_denied', 403);
        }
    }

    private static function reason(mixed $text): string
    {
        if (!\is_string($text) || '' === \trim($text)
            || \mb_strlen($text, 'UTF-8') > 1000) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'reason');
        }
        return \hash('sha256', \trim($text));
    }

    private static function scope(string $purpose): array
    {
        return [
            'actor_kind' => 'staff',
            'actor_ref' => (string) \get_current_user_id(),
            'resource_kind' => 'agenda',
            'resource_id' => Agenda_Time::RESOURCE,
            'purpose' => $purpose,
        ];
    }

    private static function newClaim(array $claim): void
    {
        if ('new' !== $claim['status']) {
            throw new Transaction_Failure('pc_idempotency_conflict');
        }
    }

    private static function next(string $value): string
    {
        $digits = \str_split(Domain_Validation::sqlId($value));
        for ($i = \count($digits) - 1; $i >= 0; --$i) {
            if ($digits[$i] === '9') {
                $digits[$i] = '0';
            } else {
                $digits[$i] = (string) ((int) $digits[$i] + 1);
                return Domain_Validation::sqlId(\implode('', $digits));
            }
        }
        return Domain_Validation::sqlId('1' . \implode('', $digits));
    }

    private static function insertId(Transaction_Context $tx): string
    {
        $rows = $tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_agenda_invariant_invalid');
        }
        return Domain_Validation::sqlId((string) $rows[0]['id']);
    }
}
