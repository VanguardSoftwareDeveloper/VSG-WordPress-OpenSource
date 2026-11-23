<?php
/**
 * IMP-06 — dinero comercial PEN en céntimos enteros, sin floats ni motor fiscal.
 *
 * Contratos: E04-A/B/C (docs/17), docs/23 §2 y OpenAPI 0.12 MoneyCents.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Money
{
    public const CURRENCY = 'PEN';
    private const MAX_CART_LINES = 100;
    private const MAX_QUANTITY = 100000;

    /**
     * OpenAPI MoneyCents: string decimal canónica NO negativa.
     * Los límites efectivos respetan BIGINT firmado/PHP de 64 bits.
     */
    public static function fromApi(string $value): int
    {
        if (!\preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value)
            || !self::withinUnsignedBound($value, (string) \PHP_INT_MAX)) {
            throw new \InvalidArgumentException('pc_invalid_money_cents');
        }
        return (int) $value;
    }

    /**
     * El ledger admite ajustes negativos, no "-0", espacios ni exponenciales.
     */
    public static function fromSignedLedger(string $value): int
    {
        if (!\preg_match('/\A(?:0|-?[1-9][0-9]*)\z/D', $value)) {
            throw new \InvalidArgumentException('pc_invalid_signed_cents');
        }
        $negative = '-' === $value[0];
        $magnitude = $negative ? \substr($value, 1) : $value;
        $max = $negative ? \substr((string) \PHP_INT_MIN, 1) : (string) \PHP_INT_MAX;
        if (!self::withinUnsignedBound($magnitude, $max)) {
            throw new \InvalidArgumentException('pc_money_overflow');
        }
        return (int) $value;
    }

    /**
     * Formulario del comerciante: "12", "12.3" o "12.34" PEN.
     * Nunca usar float, locale, separadores de miles ni redondeo implícito.
     */
    public static function fromDisplayDecimal(string $value): int
    {
        if (!\preg_match('/\A(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?\z/D', $value, $match)) {
            throw new \InvalidArgumentException('pc_invalid_money_decimal');
        }

        $whole = $match[1];
        $fraction = \str_pad($match[2] ?? '', 2, '0', \STR_PAD_RIGHT);
        $wholeLimit = \intdiv(\PHP_INT_MAX, 100);
        if (!self::withinUnsignedBound($whole, (string) $wholeLimit)) {
            throw new \InvalidArgumentException('pc_money_overflow');
        }

        $integer = (int) $whole;
        $fractionCents = (int) $fraction;
        if ($integer === $wholeLimit && $fractionCents > \PHP_INT_MAX % 100) {
            throw new \InvalidArgumentException('pc_money_overflow');
        }
        return $integer * 100 + $fractionCents;
    }

    public static function toApi(int $cents): string
    {
        self::requireNonnegative($cents);
        return (string) $cents;
    }

    public static function toLedger(int $cents): string
    {
        return (string) $cents;
    }

    /**
     * MP Orders recibe decimal exacto en PEN, nunca un número PHP float.
     * El adaptador HTTP valida además el total y sus ítems en IMP-27/28.
     */
    public static function toProviderDecimal(int $cents): string
    {
        self::requireNonnegative($cents);
        return (string) \intdiv($cents, 100) . '.' . \str_pad((string) ($cents % 100), 2, '0', \STR_PAD_LEFT);
    }

    /**
     * Precio real de un vendible al cotizar/publicar; una oferta activa
     * superior al precio normal o precio final cero jamás se corrige a mano.
     */
    public static function effectiveUnitPrice(int $regularCents, ?int $saleCents, bool $saleActive): int
    {
        self::requireNonnegative($regularCents);
        if (null !== $saleCents) {
            self::requireNonnegative($saleCents);
        }
        if ($saleActive && (null === $saleCents || $saleCents > $regularCents)) {
            throw new \InvalidArgumentException('pc_invalid_sale_price');
        }
        $chosen = $saleActive ? $saleCents : $regularCents;
        if (!\is_int($chosen) || $chosen <= 0) {
            throw new \InvalidArgumentException('pc_invalid_effective_price');
        }
        return $chosen;
    }

    public static function lineSubtotal(int $unitPriceCents, int $quantity): int
    {
        if ($unitPriceCents <= 0) {
            throw new \InvalidArgumentException('pc_invalid_effective_price');
        }
        self::requireCartQuantity($quantity);
        if ($unitPriceCents > \intdiv(\PHP_INT_MAX, $quantity)) {
            throw new \InvalidArgumentException('pc_money_overflow');
        }
        return $unitPriceCents * $quantity;
    }

    /**
     * Solo líneas y una tarifa de delivery ya determinada por el servidor.
     * No inventar IGV, cupones, comisiones o un segundo cargo aquí.
     *
     * @param list<int> $lineSubtotalsCents
     */
    public static function orderTotal(array $lineSubtotalsCents, int $deliveryCents): int
    {
        if (!\array_is_list($lineSubtotalsCents)
            || [] === $lineSubtotalsCents
            || \count($lineSubtotalsCents) > self::MAX_CART_LINES) {
            throw new \InvalidArgumentException('pc_invalid_cart_lines');
        }

        self::requireNonnegative($deliveryCents);
        $total = 0;
        foreach ($lineSubtotalsCents as $lineSubtotal) {
            if (!\is_int($lineSubtotal) || $lineSubtotal <= 0) {
                throw new \InvalidArgumentException('pc_invalid_line_subtotal');
            }
            $total = self::addChecked($total, $lineSubtotal);
        }
        return self::addChecked($total, $deliveryCents);
    }

    /**
     * Suma firmada segura para movimientos/ajustes; no redondear ni cambiar
     * el signo antes de aplicar las reglas financieras de fases posteriores.
     */
    public static function addChecked(int $left, int $right): int
    {
        if (($right > 0 && $left > \PHP_INT_MAX - $right)
            || ($right < 0 && $left < \PHP_INT_MIN - $right)) {
            throw new \InvalidArgumentException('pc_money_overflow');
        }
        return $left + $right;
    }

    public static function requireCartQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new \InvalidArgumentException('pc_invalid_quantity');
        }
    }

    public static function requireNonnegative(int $cents): void
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('pc_invalid_money_cents');
        }
    }

    private static function withinUnsignedBound(string $value, string $bound): bool
    {
        return \strlen($value) < \strlen($bound)
            || (\strlen($value) === \strlen($bound) && \strcmp($value, $bound) <= 0);
    }
}
