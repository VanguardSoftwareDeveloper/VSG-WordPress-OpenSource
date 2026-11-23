<?php
/**
 * IMP-69 — contrato ejecutable sin WordPress, DB ni test framework.
 *
 * Futuro (previa autorización P-C): php tests/risk/domain.php
 * NO ejecutado durante IMP-69. Usa únicamente datos ficticios.
 * Este script prueba funciones reales de dominio, nunca mocks de dinero.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(2);
}

define('ABSPATH', __DIR__ . '/../../');
require_once ABSPATH . 'includes/domain/money.php';
require_once ABSPATH . 'includes/domain/business-time.php';
require_once ABSPATH . 'includes/domain/validation.php';

use PeruCommerce\Money;
use PeruCommerce\Business_Time;
use PeruCommerce\Domain_Validation;

function same(mixed $actual, mixed $expected, string $what): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($what . ': resultado distinto de expectativa independiente.');
    }
}

function denied(callable $action, string $error, string $what): void
{
    try {
        $action();
    } catch (InvalidArgumentException $exception) {
        same($exception->getMessage(), $error, $what . ': código');
        return;
    }
    throw new RuntimeException($what . ': se aceptó entrada prohibida.');
}

$data = json_decode((string) file_get_contents(__DIR__ . '/fixtures.json'), true, 32, JSON_THROW_ON_ERROR);
same($data['fictional'] ?? null, true, 'Fixture ficticio');
same($data['site']['environment'] ?? null, 'synthetic_only', 'No usar credencial/ambiente real');

same(Money::CURRENCY, 'PEN', 'Moneda');
same(Money::fromApi('1599'), 1599, 'PEN centimos');
same(Money::fromDisplayDecimal('15.99'), 1599, 'Formulario decimal exacto');
same(Money::fromDisplayDecimal('15.9'), 1590, 'Decimal con un dígito');
same(Money::fromDisplayDecimal('15'), 1500, 'Decimal entero');
same(Money::toProviderDecimal(3798), '37.98', 'Decimal MP');
same(Money::toApi(3798), '3798', 'API centimos como string');
same(Money::fromSignedLedger('-3798'), -3798, 'Corrección negativa');
same(Money::effectiveUnitPrice(1599, 1200, true), 1200, 'Oferta activa');
same(Money::effectiveUnitPrice(1599, 1200, false), 1599, 'Oferta inactiva');
same(Money::lineSubtotal(1599, 2), 3198, 'Subtotal por cantidad');
same(Money::orderTotal([3198], 600), 3798, 'Efectivo + entrega');
same(Money::orderTotal([3198, 1800, 4500], 0), 9498, 'Carrito mixto');
same(Money::addChecked(-3798, 3798), 0, 'Ledger exacto');
same(Money::fromSignedLedger((string) PHP_INT_MIN), PHP_INT_MIN, 'Mínimo ledger');

foreach (['01', '-0', '-1', '1e3', '1.5', ' 5', '+5', '9223372036854775808'] as $value) {
    denied(static fn () => Money::fromApi($value), 'pc_invalid_money_cents', 'MoneyCents ' . $value);
}
foreach (['00.1', '-1.00', '1e3', '12.345', '1,00'] as $value) {
    denied(static fn () => Money::fromDisplayDecimal($value), 'pc_invalid_money_decimal', 'Decimal ' . $value);
}
denied(static fn () => Money::effectiveUnitPrice(1599, 1600, true), 'pc_invalid_sale_price', 'Oferta superior');
denied(static fn () => Money::effectiveUnitPrice(1599, 0, true), 'pc_invalid_effective_price', 'Oferta cero');
denied(static fn () => Money::lineSubtotal(PHP_INT_MAX, 2), 'pc_money_overflow', 'Overflow carrito');
denied(static fn () => Money::addChecked(PHP_INT_MAX, 1), 'pc_money_overflow', 'Overflow ledger');
denied(static fn () => Money::orderTotal([], 0), 'pc_invalid_cart_lines', 'Carrito vacío');
denied(static fn () => Money::requireCartQuantity(100001), 'pc_invalid_quantity', 'Cantidad excedida');

$products = [];
foreach ($data['sellables'] as $sellable) {
    $products[$sellable['id']] = $sellable;
}
foreach ($data['carts'] as $cart) {
    $subtotals = [];
    $kinds = [];
    foreach ($cart['items'] as $item) {
        $sellable = $products[$item['sellable_id']];
        $subtotals[] = Money::lineSubtotal(Money::fromApi($sellable['price_cents']), $item['quantity']);
        $kinds[] = $sellable['kind'];
    }
    same(
        Money::toApi(Money::orderTotal($subtotals, Money::fromApi($cart['delivery_cents']))),
        $cart['expected_total_cents'],
        'Cart ' . $cart['id']
    );
    Domain_Validation::contactForKinds($kinds, $cart['contact']['email'], $cart['contact']['phone']);
}
denied(
    static fn () => Domain_Validation::contactForKinds(['digital'], null, '+51999999999'),
    'pc_missing_contact',
    'Digital requiere email'
);
denied(
    static fn () => Domain_Validation::contactForKinds(['physical'], 'buyer@example.invalid', null),
    'pc_missing_contact',
    'Físico requiere teléfono'
);
Domain_Validation::contactForKinds(['physical'], null, '+51999999999');
Domain_Validation::contactForKinds(['service'], 'buyer@example.invalid', null);
same(Domain_Validation::ubigeo('010101'), '010101', 'UBIGEO conserva cero inicial');
same(Domain_Validation::sqlId('18446744073709551615'), '18446744073709551615', 'SQL bigint unsigned');
denied(static fn () => Domain_Validation::ubigeo('10101'), 'pc_invalid_ubigeo', 'UBIGEO inválido');
denied(static fn () => Domain_Validation::sqlId('0'), 'pc_invalid_sql_id', 'SqlId cero');
denied(static fn () => Domain_Validation::sqlId('18446744073709551616'), 'pc_invalid_sql_id', 'SqlId overflow');

$issued = Business_Time::fromApi($data['clock']['fixed_utc']);
same(Business_Time::toApi($issued), '2027-06-15T18:00:00.000000Z', 'UTC canónico');
same(Business_Time::toDatabase($issued), '2027-06-15 18:00:00.000000', 'UTC DB');
same(Business_Time::toApi(Business_Time::fromApi('2027-06-15T13:00:00-05:00')),
    '2027-06-15T18:00:00.000000Z', 'Offset explícito');
same(Business_Time::dateInBusinessZone($issued, 'America/Lima'), '2027-06-15', 'Fecha del comercio');
same(Business_Time::toApi(Business_Time::expiresAfterSeconds($issued, Business_Time::ADMISSION_SECONDS)),
    '2027-06-15T18:01:00.000000Z', 'Admisión 60s');
same(Business_Time::toApi(Business_Time::expiresAfterSeconds($issued, Business_Time::PAYMENT_HOLD_SECONDS)),
    '2027-06-15T18:15:00.000000Z', 'Hold 15m');
same(Business_Time::toApi(Business_Time::expiresAfterSeconds($issued, Business_Time::OTP_SECONDS)),
    '2027-06-15T18:10:00.000000Z', 'OTP 10m');
same(Business_Time::toApi(Business_Time::expiresAfterSeconds($issued, Business_Time::ACTION_GRANT_SECONDS)),
    '2027-06-15T18:05:00.000000Z', 'Grant 5m');
denied(static fn () => Business_Time::fromApi('2027-06-15T18:00:00'),
    'pc_invalid_instant', 'UTC sin offset');
denied(static fn () => Business_Time::fromApi('2027-02-30T18:00:00Z'),
    'pc_invalid_instant', 'Fecha imposible');
denied(static fn () => Business_Time::localDate('2027-02-30'),
    'pc_invalid_business_date', 'Fecha local imposible');

fwrite(STDOUT, "IMP-69 dominio: invariantes Money/Business_Time/Domain_Validation comprobables.\n");
