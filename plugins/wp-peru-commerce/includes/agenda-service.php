<?php
/**
 * IMP-18 — lecturas R8-04/R8-36 del recurso único.
 *
 * R8-04 no retiene citas; todos los resultados son orientativos. R8-36
 * proyecta únicamente ID, versión, instantes y estado, sin contactos,
 * datos fiscales ni evidencia sensible.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Agenda_Service
{
    private const MAX_SLOTS = 100;

    /** @return array{service_id:string,date:string,slots:list<array<string,mixed>>} */
    public static function slots(string $sellableId, string $date): array
    {
        self::requireReady();
        $sellableId = Domain_Validation::sqlId($sellableId);
        $date = Business_Time::localDate($date);
        $bookingEnabled = self::bookingEnabled();
        return Transaction::run(static function (Transaction_Context $tx) use (
            $sellableId, $date, $bookingEnabled
        ): array {
            // Primero resolver el vendible (etapa catálogo), DESPUÉS el
            // ancla de agenda. La lectura no toma locks de escritura:
            // evita inversión recurso -> vendible frente al checkout mixto.
            global $wpdb;
            $rows = $tx->rows(
                'SELECT s.id, s.price_cents, s.sale_price_cents, s.sale_active, '
                    . 's.service_duration_minutes, s.service_buffer_before_minutes, '
                    . 's.service_buffer_after_minutes FROM %i s INNER JOIN %i p '
                    . 'ON p.ID = s.product_post_id '
                    . 'WHERE s.id = %s AND s.kind = %s AND s.active = 1 '
                    . 'AND p.post_type = %s AND p.post_status = %s LIMIT 1',
                [Agenda_Repository::table('pc_sellables'), $wpdb->posts,
                    $sellableId, 'service', Catalog_Controller::POST_TYPE, 'publish']
            );
            if (1 !== \count($rows)) {
                throw new Transaction_Failure('pc_agenda_not_found');
            }
            $service = $rows[0];
            $anchor = Agenda_Repository::anchorWithin($tx);
            $zoneId = (string) $anchor['timezone'];
            $zone = Business_Time::businessZone($zoneId);
            $duration = self::minutes($service['service_duration_minutes'], 1);
            $before = self::minutes($service['service_buffer_before_minutes'], 0);
            $after = self::minutes($service['service_buffer_after_minutes'], 0);
            try {
                Money::effectiveUnitPrice(
                    Money::fromApi((string) $service['price_cents']),
                    null === $service['sale_price_cents'] ? null
                        : Money::fromApi((string) $service['sale_price_cents']),
                    '1' === (string) $service['sale_active']
                );
            } catch (\Throwable $ignored) {
                // Un servicio incompleto no permite ofrecer franjas.
                return ['service_id' => $sellableId, 'date' => $date, 'slots' => []];
            }

            $prevDate = Agenda_Time::day($date, -1);
            $current = Agenda_Repository::scheduleWithin($tx, $date, $anchor);
            $previous = Agenda_Repository::scheduleWithin($tx, $prevDate, $anchor);
            $windows = \array_merge(
                Agenda_Time::windows($previous['weekly_rules'], $prevDate, $zoneId),
                Agenda_Time::windows($current['weekly_rules'], $date, $zoneId)
            );
            \usort($windows, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
            if ([] === $windows) {
                return ['service_id' => $sellableId, 'date' => $date, 'slots' => []];
            }
            [$dayStart, $dayEnd] = Agenda_Time::dayBounds($date, $zoneId);
            $busy = Agenda_Repository::busyWithin(
                $tx, Agenda_Time::addMinutes($dayStart, -2880),
                Agenda_Time::addMinutes($dayEnd, 2880)
            );
            $now = Business_Time::nowUtc();
            $step = $duration + $before + $after;
            $slots = [];
            $seen = [];
            foreach ($windows as [$open, $close]) {
                $candidate = Agenda_Time::addMinutes($open, $before);
                while (true) {
                    $finish = Agenda_Time::addMinutes($candidate, $duration);
                    $occupiedStart = Agenda_Time::addMinutes($candidate, -$before);
                    $occupiedEnd = Agenda_Time::addMinutes($finish, $after);
                    if ($occupiedEnd > $close) {
                        break;
                    }
                    $local = $candidate->setTimezone($zone)->format('Y-m-d');
                    if ($local === $date && $candidate >= $now) {
                        $key = Business_Time::toDatabase($candidate);
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            // Se pueden presentar horarios informativos
                            // sin vender una reserva hasta tener P-C + cobro.
                            $free = $bookingEnabled;
                            foreach ($busy as [$busyStart, $busyEnd]) {
                                if (Agenda_Time::intersects(
                                    $occupiedStart, $occupiedEnd, $busyStart, $busyEnd
                                )) {
                                    $free = false;
                                    break;
                                }
                            }
                            $slots[] = [
                                'starts_at_utc' => Business_Time::toApi($candidate),
                                'ends_at_utc' => Business_Time::toApi($finish),
                                'timezone' => $zoneId,
                                'available' => $free,
                            ];
                        }
                    }
                    $candidate = Agenda_Time::addMinutes($candidate, $step);
                    if ($candidate >= $dayEnd && $occupiedEnd >= $dayEnd
                        || \count($slots) > 300) {
                        break;
                    }
                }
            }
            \usort($slots, static fn (array $a, array $b): int =>
                \strcmp($a['starts_at_utc'], $b['starts_at_utc']));
            // Contrato máximo 100. La interfaz muestra primeras franjas
            // cronológicas; ninguna es reserva ni promesa de cupo.
            return [
                'service_id' => $sellableId,
                'date' => $date,
                'slots' => \array_slice($slots, 0, self::MAX_SLOTS),
            ];
        });
    }

    /** @return array<string,mixed> AgendaView */
    public static function adminView(array $query): array
    {
        self::requireReady();
        if (!\in_array(Access_Roles::currentRole(), ['A','O'], true)
            || !\current_user_can('perucommerce_appointments_manage')) {
            throw new Rest_Failure('pc_access_denied', 403);
        }
        $resource = $query['resource_id'] ?? Agenda_Time::RESOURCE;
        if (!\is_string($resource) || $resource !== Agenda_Time::RESOURCE) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'resource_id');
        }
        $date = Business_Time::localDate($query['date']);
        return Transaction::run(static function (Transaction_Context $tx) use ($date): array {
            $anchor = Agenda_Repository::anchorWithin($tx);
            $week = Agenda_Repository::scheduleWithin($tx, $date, $anchor);
            [$start, $end] = Agenda_Time::dayBounds($date, $anchor['timezone']);
            return [
                'resource_id' => Agenda_Time::RESOURCE,
                'date' => $date,
                'timezone' => $anchor['timezone'],
                'weekly_schedule' => $week,
                'appointments' => Agenda_Repository::appointmentsWithin($tx, $start, $end),
                'closures' => Agenda_Repository::closuresWithin($tx, $start, $end),
            ];
        });
    }

    /** No anunciar "available" si aún no hay checkout real y conforme. */
    private static function bookingEnabled(): bool
    {
        if (!Module_Gates::allows('appointments', 'write')
            || !Rest_Router::isOperationReady('R8-05')) {
            return false;
        }
        try {
            Bootstrap::loadBootstrapApi();
            $boot = Bootstrap_Api_Service::publicResponse();
            return [] !== ($boot['payment_methods'] ?? []);
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    public static function requireReady(): void
    {
        if (!Module_Gates::allows('appointments', 'read')) {
            throw new Rest_Failure('pc_module_not_ready', 423, true);
        }
    }

    private static function minutes(mixed $value, int $min): int
    {
        if (!\is_int($value) && !\is_string($value)
            || !\ctype_digit((string) $value)
            || \strlen((string) $value) > 4) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        $value = (int) $value;
        if ($value < $min || $value > 1440) {
            throw new Transaction_Failure('pc_agenda_not_ready');
        }
        return $value;
    }
}
