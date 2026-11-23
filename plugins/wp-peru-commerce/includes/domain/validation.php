<?php
/**
 * IMP-06 — validaciones compartidas sin convertir contacto en autenticación.
 *
 * OpenAPI 0.12, E-07 (docs/20) y contrato físico (docs/23).
 * Los errores nunca incluyen correo, teléfono, documento ni valores originales.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Domain_Validation
{
    private const MAX_SQL_UINT64 = '18446744073709551615';

    /** SqlId canónico, BIGINT UNSIGNED como string incluso sobre PHP_INT_MAX. */
    public static function sqlId(string $value): string
    {
        if (!\preg_match('/\A[1-9][0-9]*\z/D', $value)
            || !self::withinBound($value, self::MAX_SQL_UINT64)) {
            throw new \InvalidArgumentException('pc_invalid_sql_id');
        }
        return $value;
    }

    /** UnitsCount OpenAPI: decimal canónico, no mayor que BIGINT firmado. */
    public static function unitsCount(string $value): int
    {
        if (!\preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value)
            || !self::withinBound($value, (string) \PHP_INT_MAX)) {
            throw new \InvalidArgumentException('pc_invalid_units');
        }
        return (int) $value;
    }

    /** Código INEI de seis cifras como texto, nunca convertir a entero. */
    public static function ubigeo(string $code): string
    {
        if (!\preg_match('/\A[0-9]{6}\z/D', $code)) {
            throw new \InvalidArgumentException('pc_invalid_ubigeo');
        }
        return $code;
    }

    public static function sellableKind(string $kind): string
    {
        if (!\in_array($kind, ['physical', 'digital', 'service'], true)) {
            throw new \InvalidArgumentException('pc_invalid_product_kind');
        }
        return $kind;
    }

    /**
     * El vendedor puede no usar SKU. La cadena vacía se almacena como NULL
     * para respetar UQ(sku), sin forzar uppercase ni alterar caracteres.
     */
    public static function sku(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        return self::safeText($value, 100, 'pc_invalid_sku');
    }

    /** Nombre de visualización; no sirve como prueba de identidad. */
    public static function displayName(string $value): string
    {
        return self::safeText($value, 160, 'pc_invalid_display_name');
    }

    /**
     * Validación formal de correo; no cambia mayúsculas de la parte local,
     * no genera uno ficticio y no prueba que el comprador controle el buzón.
     */
    public static function email(string $value): string
    {
        if ('' === $value || \strlen($value) > 254
            || 0 !== \preg_match('/[\x00-\x20\x7F]/', $value)
            || false === \filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('pc_invalid_email');
        }
        return $value;
    }

    /**
     * Preserva el teléfono informado: no agrega +51, no elimina signos ni
     * lo declara verificado. El vínculo real exige prueba en IMP-31/32/34/40.
     */
    public static function phone(string $value): string
    {
        if ('' === $value || \strlen($value) > 32
            || !\preg_match('/\A\+?[0-9 ()-]+\z/D', $value)
            || !\preg_match('/[0-9]/', $value)) {
            throw new \InvalidArgumentException('pc_invalid_phone');
        }
        return $value;
    }

    /**
     * La lista de tipos proviene de vendibles leídos por el servidor.
     * Físicos requieren teléfono; digitales y servicios requieren correo.
     * Los campos que no se necesitan no se inventan ni se exigen por defecto.
     *
     * @param list<string> $serverKinds
     */
    public static function contactForKinds(array $serverKinds, ?string $email, ?string $phone): void
    {
        if ([] === $serverKinds || !\array_is_list($serverKinds) || \count($serverKinds) > 100) {
            throw new \InvalidArgumentException('pc_invalid_cart_lines');
        }
        $needsEmail = false;
        $needsPhone = false;
        foreach ($serverKinds as $kind) {
            if (!\is_string($kind)) {
                throw new \InvalidArgumentException('pc_invalid_product_kind');
            }
            self::sellableKind($kind);
            $needsEmail = $needsEmail || 'digital' === $kind || 'service' === $kind;
            $needsPhone = $needsPhone || 'physical' === $kind;
        }
        if (($needsEmail && null === $email) || ($needsPhone && null === $phone)) {
            throw new \InvalidArgumentException('pc_missing_contact');
        }
        if (null !== $email) {
            self::email($email);
        }
        if (null !== $phone) {
            self::phone($phone);
        }
    }

    /** Contrato de servicios OpenAPI: minutos enteros, un recurso por cita. */
    public static function serviceTiming(int $duration, int $bufferBefore, int $bufferAfter): void
    {
        if ($duration < 1 || $duration > 1440
            || $bufferBefore < 0 || $bufferBefore > 1440
            || $bufferAfter < 0 || $bufferAfter > 1440) {
            throw new \InvalidArgumentException('pc_invalid_service_timing');
        }
    }

    private static function safeText(string $value, int $limit, string $error): string
    {
        if ('' === $value || \trim($value) !== $value
            || !\mb_check_encoding($value, 'UTF-8')
            || \mb_strlen($value, 'UTF-8') > $limit
            || \preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new \InvalidArgumentException($error);
        }
        return $value;
    }

    private static function withinBound(string $value, string $max): bool
    {
        return \strlen($value) < \strlen($max)
            || (\strlen($value) === \strlen($max) && \strcmp($value, $max) <= 0);
    }
}
