<?php
/**
 * IMP-18 — reglas locales, DST e intervalos semiabiertos de una agenda.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Agenda_Time
{
    public const RESOURCE = '1';
    private const WEEK_MINUTES = 10080;

    public static function day(string $date, int $offset = 0): string
    {
        Business_Time::localDate($date);
        $day = new \DateTimeImmutable($date . ' 00:00:00', new \DateTimeZone('UTC'));
        return Business_Time::localDate($day->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d'));
    }

    /** Hora de pared local a UTC. Rechazar hora inexistente o doble por DST. */
    public static function wallUtc(string $date, string $hhmm, string $timezone): \DateTimeImmutable
    {
        Business_Time::localDate($date);
        if (!\preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/D', $hhmm)) {
            throw new Transaction_Failure('pc_agenda_field_invalid');
        }
        $zone = Business_Time::businessZone($timezone);
        $wall = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i', $date . ' ' . $hhmm, new \DateTimeZone('UTC')
        );
        if (!$wall instanceof \DateTimeImmutable
            || $wall->format('Y-m-d H:i') !== $date . ' ' . $hhmm) {
            throw new Transaction_Failure('pc_agenda_field_invalid');
        }
        $ts = $wall->getTimestamp();
        $offsets = [$zone->getOffset($wall)];
        $transitions = $zone->getTransitions($ts - 172800, $ts + 172800);
        if (\is_array($transitions)) {
            foreach ($transitions as $entry) {
                if (\is_int($entry['offset'] ?? null)) {
                    $offsets[] = $entry['offset'];
                }
            }
        }
        $matches = [];
        foreach (\array_unique($offsets) as $offset) {
            $utc = (new \DateTimeImmutable('@' . ($ts - $offset)))
                ->setTimezone(new \DateTimeZone('UTC'));
            if ($utc->setTimezone($zone)->format('Y-m-d H:i') === $date . ' ' . $hhmm) {
                $matches[$utc->format('U')] = $utc;
            }
        }
        if (1 !== \count($matches)) {
            // El usuario debe elegir otro horario en una transición DST.
            throw new Transaction_Failure('pc_agenda_time_ambiguous');
        }
        return \array_values($matches)[0];
    }

    public static function dayBounds(string $date, string $timezone): array
    {
        return [
            self::wallUtc($date, '00:00', $timezone),
            self::wallUtc(self::day($date, 1), '00:00', $timezone),
        ];
    }

    /**
     * La semana ISO del cliente (1..7) se normaliza a domingo=0 SQL;
     * 7 días vacíos tienen una ventana inactiva por día, recuperable
     * como revisión. Un índice de ventana solo es único por weekday.
     */
    public static function normalizeRules(array $input): array
    {
        if ([] === $input || \count($input) > 28 || !\array_is_list($input)) {
            throw new Transaction_Failure('pc_agenda_field_invalid');
        }
        $byDay = [];
        foreach ($input as $rule) {
            if (!\is_array($rule)
                || !\is_int($rule['weekday'] ?? null)
                || !\is_int($rule['window_index'] ?? null)
                || !\is_bool($rule['enabled'] ?? null)
                || !\is_int($rule['closes_day_offset'] ?? null)) {
                throw new Transaction_Failure('pc_agenda_field_invalid');
            }
            $iso = $rule['weekday'];
            $index = $rule['window_index'];
            $offset = $rule['closes_day_offset'];
            if ($iso < 1 || $iso > 7 || $index < 0 || $index > 27
                || !\in_array($offset, [0, 1], true)
                || isset($byDay[$iso][$index])) {
                throw new Transaction_Failure('pc_agenda_field_invalid');
            }
            $enabled = $rule['enabled'];
            if ($enabled) {
                $from = $rule['opens_local'] ?? null;
                $to = $rule['closes_local'] ?? null;
                if (!\is_string($from) || !\is_string($to)
                    || !\preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/D', $from)
                    || !\preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/D', $to)) {
                    throw new Transaction_Failure('pc_agenda_field_invalid');
                }
                $start = self::minutes($from);
                $end = self::minutes($to) + $offset * 1440;
                if ($end <= $start || $end - $start > 1440) {
                    throw new Transaction_Failure('pc_agenda_field_invalid');
                }
            } else {
                if (\array_key_exists('opens_local', $rule)
                    || \array_key_exists('closes_local', $rule)
                    || 0 !== $offset) {
                    throw new Transaction_Failure('pc_agenda_field_invalid');
                }
                $from = null;
                $to = null;
            }
            $byDay[$iso][$index] = [
                'weekday' => $iso, 'window_index' => $index,
                'enabled' => $enabled, 'closes_day_offset' => $offset,
            ];
            if ($enabled) {
                $byDay[$iso][$index]['opens_local'] = $from;
                $byDay[$iso][$index]['closes_local'] = $to;
            }
        }
        // R8-53 permite hasta 28 entradas totales, sin exigir una fila
        // por día. Los días omitidos quedan cerrados; rellenarlos aquí
        // rechazaría solicitudes válidas que ya usan las 28 ventanas.
        $normalized = [];
        $periods = [];
        foreach ($byDay as $iso => $set) {
            \ksort($set, \SORT_NUMERIC);
            foreach ($set as $window) {
                $normalized[] = $window;
                if ($window['enabled']) {
                    $start = ($iso - 1) * 1440 + self::minutes($window['opens_local']);
                    $end = ($iso - 1) * 1440 + self::minutes($window['closes_local'])
                        + $window['closes_day_offset'] * 1440;
                    $periods[] = [$start, $end];
                }
            }
        }
        if (\count($normalized) > 28) {
            throw new Transaction_Failure('pc_agenda_field_invalid');
        }
        \usort($periods, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        foreach ($periods as $i => [$start, $end]) {
            if ($i > 0 && $start < $periods[$i - 1][1]) {
                throw new Transaction_Failure('pc_agenda_overlap');
            }
        }
        if (\count($periods) > 1
            && $periods[0][0] + self::WEEK_MINUTES < $periods[\count($periods) - 1][1]) {
            throw new Transaction_Failure('pc_agenda_overlap');
        }
        return $normalized;
    }

    /** Revisiones completas y cronológicas, incluso si la fecha cae en sábado noche. */
    public static function windows(array $rules, string $anchorDate, string $timezone): array
    {
        $weekday = (int) (new \DateTimeImmutable($anchorDate, new \DateTimeZone('UTC')))->format('N');
        $windows = [];
        foreach ($rules as $rule) {
            if (!$rule['enabled'] || $rule['weekday'] !== $weekday) {
                continue;
            }
            $from = self::wallUtc($anchorDate, $rule['opens_local'], $timezone);
            $endDate = self::day($anchorDate, $rule['closes_day_offset']);
            $until = self::wallUtc($endDate, $rule['closes_local'], $timezone);
            if ($until <= $from) {
                throw new Transaction_Failure('pc_agenda_time_ambiguous');
            }
            $windows[] = [$from, $until];
        }
        return $windows;
    }

    public static function intersects(
        \DateTimeImmutable $aStart, \DateTimeImmutable $aEnd,
        \DateTimeImmutable $bStart, \DateTimeImmutable $bEnd
    ): bool {
        return $aStart < $bEnd && $bStart < $aEnd;
    }

    public static function addMinutes(\DateTimeImmutable $instant, int $minutes): \DateTimeImmutable
    {
        $utc = $instant->setTimezone(new \DateTimeZone('UTC'));
        return $utc->modify(($minutes < 0 ? '' : '+') . $minutes . ' minutes');
    }

    private static function minutes(string $hhmm): int
    {
        return (int) \substr($hhmm, 0, 2) * 60 + (int) \substr($hhmm, 3, 2);
    }
}
