<?php
/**
 * IMP-06 — instantes UTC, calendario local explícito y plazos aprobados.
 *
 * No usa timezone_default_get ni interpreta horas locales sin offset:
 * las franjas ambiguas (DST) requieren verificación de agenda en IMP-18.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Business_Time
{
    public const ADMISSION_SECONDS = 60;
    public const PAYMENT_HOLD_SECONDS = 15 * 60;
    public const OTP_SECONDS = 10 * 60;
    public const OTP_MAX_ATTEMPTS = 5;
    public const ACTION_GRANT_SECONDS = 5 * 60;
    public const GUEST_SESSION_IDLE_SECONDS = 30 * 60;
    public const DIGITAL_TOKEN_SECONDS = 24 * 60 * 60;
    public const TRACKING_DAYS = 90;

    public static function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * OpenAPI date-time: RFC3339 con offset explícito y hasta seis decimales.
     * Rechaza fechas imposibles, leap seconds, espacios y offsets implícitos.
     */
    public static function fromApi(string $value): \DateTimeImmutable
    {
        if (!\preg_match('/\A([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2})'
            . '(?:\.([0-9]{1,6}))?(Z|[+-](?:[01][0-9]|2[0-3]):[0-5][0-9])\z/D', $value, $match)) {
            throw new \InvalidArgumentException('pc_invalid_instant');
        }
        $normalized = $match[1] . '.' . \str_pad($match[2] ?? '', 6, '0', \STR_PAD_RIGHT)
            . ('Z' === $match[3] ? '+00:00' : $match[3]);

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s.uP', $normalized, new \DateTimeZone('UTC')
        );
        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $date
            || (false !== $errors && (0 !== $errors['error_count'] || 0 !== $errors['warning_count']))
            || (int) $date->format('Y') < 1000
            || $date->format('Y-m-d\TH:i:s.uP') !== $normalized) {
            throw new \InvalidArgumentException('pc_invalid_instant');
        }
        return self::dbRange($date);
    }

    /** SQL DATETIME(6) se interpreta SOLO como UTC, nunca en zona de PHP/SQL. */
    public static function fromDatabase(string $value): \DateTimeImmutable
    {
        if (!\preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}\z/D', $value)) {
            throw new \InvalidArgumentException('pc_invalid_db_utc');
        }
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s.u', $value, new \DateTimeZone('UTC')
        );
        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $date
            || (false !== $errors && (0 !== $errors['error_count'] || 0 !== $errors['warning_count']))
            || (int) $date->format('Y') < 1000
            || $date->format('Y-m-d H:i:s.u') !== $value) {
            throw new \InvalidArgumentException('pc_invalid_db_utc');
        }
        return self::dbRange($date);
    }

    public static function toDatabase(\DateTimeImmutable $instant): string
    {
        return self::dbRange($instant)->format('Y-m-d H:i:s.u');
    }

    public static function toApi(\DateTimeImmutable $instant): string
    {
        return self::dbRange($instant)->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * No usar un offset fijo en vez de zona IANA del comercio: el calendario
     * puede tener cambios históricos/DST y requiere zona explícita en snapshots.
     */
    public static function businessZone(string $zoneId): \DateTimeZone
    {
        if ('' === $zoneId || \strlen($zoneId) > 64
            || !\in_array($zoneId, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL), true)) {
            throw new \InvalidArgumentException('pc_invalid_business_timezone');
        }
        return new \DateTimeZone($zoneId);
    }

    /** Fecha local civil YYYY-MM-DD, sin convertirla en un instante implícito. */
    public static function localDate(string $date): string
    {
        if (!\preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/D', $date, $parts)
            || (int) $parts[1] < 1000
            || !\checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new \InvalidArgumentException('pc_invalid_business_date');
        }
        return $date;
    }

    public static function dateInBusinessZone(\DateTimeImmutable $instant, string $zoneId): string
    {
        return self::localDate(self::dbRange($instant)->setTimezone(self::businessZone($zoneId))->format('Y-m-d'));
    }

    /** Los calendarios de servicios validan sus buffers/solapes después. */
    public static function requireInterval(\DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        if ($start >= $end) {
            throw new \InvalidArgumentException('pc_invalid_time_interval');
        }
    }

    /** Expiración de un hecho en UTC; no confundir con conservación legal. */
    public static function expiresAfterSeconds(\DateTimeImmutable $issuedAt, int $seconds): \DateTimeImmutable
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('pc_invalid_ttl');
        }
        return self::dbRange($issuedAt->setTimezone(new \DateTimeZone('UTC'))
            ->add(new \DateInterval('PT' . $seconds . 'S')));
    }

    public static function expiresTracking(\DateTimeImmutable $issuedAt): \DateTimeImmutable
    {
        return self::dbRange($issuedAt->setTimezone(new \DateTimeZone('UTC'))
            ->add(new \DateInterval('P' . self::TRACKING_DAYS . 'D')));
    }

    private static function dbRange(\DateTimeImmutable $instant): \DateTimeImmutable
    {
        $utc = $instant->setTimezone(new \DateTimeZone('UTC'));
        $year = (int) $utc->format('Y');
        if ($year < 1000 || $year > 9999) {
            throw new \InvalidArgumentException('pc_invalid_db_utc');
        }
        return $utc;
    }
}
