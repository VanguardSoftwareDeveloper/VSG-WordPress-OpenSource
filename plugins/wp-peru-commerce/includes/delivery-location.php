<?php
/**
 * IMP-20 — contrato de entrega y dirección manual independiente de tiles.
 *
 * La selección manual nunca usa geolocalización ni deduce UBIGEO a partir
 * del pin. R8-05/06/07 futuros deberán comprobar distrito, cobertura,
 * inventario y tarifa en servidor antes de confirmar una compra.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Delivery_Location
{
    /**
     * Normalizar datos que después se validarán contra Delivery (OpenAPI).
     * Aquí no se consultan mapas, tarifas, ubicación ni credenciales.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalize(array $input): array
    {
        $mode = $input['mode'] ?? null;
        if (!\in_array($mode, ['delivery', 'pickup', 'none'], true)) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'mode');
        }
        foreach (\array_keys($input) as $field) {
            if (!\in_array($field, [
                'mode', 'ubigeo_code', 'address', 'reference', 'coordinates',
            ], true)) {
                throw new Rest_Failure('pc_field_invalid', 422, false, (string) $field);
            }
        }
        if ('delivery' !== $mode) {
            if (1 !== \count($input)) {
                // No retener ubicación PII que no aplica al recojo ni
                // al servicio presencial/digital sin transporte.
                throw new Rest_Failure('pc_field_invalid', 422, false, 'mode');
            }
            return ['mode' => $mode];
        }

        $code = $input['ubigeo_code'] ?? null;
        if (!\is_string($code)) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'ubigeo_code');
        }
        try {
            require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/ubigeo-catalog.php';
            $code = Ubigeo_Catalog::code($code);
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'ubigeo_code');
        }
        $address = self::safeText($input['address'] ?? null, 500, 'address', false);
        $output = [
            'mode' => 'delivery',
            'ubigeo_code' => $code,
            'address' => $address,
        ];

        if (\array_key_exists('reference', $input)) {
            $reference = self::safeText($input['reference'], 250, 'reference', true);
            if ('' !== $reference) {
                $output['reference'] = $reference;
            }
        }
        if (\array_key_exists('coordinates', $input)) {
            $point = $input['coordinates'];
            $keys = \is_array($point) ? \array_keys($point) : [];
            \sort($keys, \SORT_STRING);
            // Los miembros de un objeto JSON no tienen orden contractual.
            if (!\is_array($point) || $keys !== ['lat', 'lng']
                || (!\is_int($point['lat']) && !\is_float($point['lat']))
                || (!\is_int($point['lng']) && !\is_float($point['lng']))
                || !\is_finite((float) $point['lat'])
                || !\is_finite((float) $point['lng'])
                || $point['lat'] < -90 || $point['lat'] > 90
                || $point['lng'] < -180 || $point['lng'] > 180) {
                throw new Rest_Failure('pc_field_invalid', 422, false, 'coordinates');
            }
            // No modificar el distrito ni la tarifa usando estas cifras.
            $output['coordinates'] = [
                'lat' => $point['lat'],
                'lng' => $point['lng'],
            ];
        }
        return $output;
    }

    /**
     * Fuente pública local del selector futuro. Este método NO llama REST
     * ni ejecuta búsquedas por sí solo; se resuelve con rest_url() real.
     * En instalaciones sin dataset verificado, R8-16 continúa cerrado.
     */
    public static function districtLookupUrl(): string
    {
        return \rest_url('peru-commerce/v1/public/ubigeo');
    }

    /** 
     * HTML básico reutilizable por el consumidor PHP/Angular posterior.
     * No incluye formulario propio, cotización ni botón de compra.
     * El consumidor de IMP-58 debe sustituir el código libre por selección
     * accesible de departamento/provincia/distrito usando R8-16.
     *
     * @param string $prefix Identificador corto del root real del checkout
     */
    public static function manualFields(string $prefix = 'pc-location'): string
    {
        if (!\preg_match('/\A[a-z][a-z0-9-]{0,50}\z/D', $prefix)) {
            throw new \InvalidArgumentException('pc_location_id_invalid');
        }
        $id = \esc_attr($prefix);
        return '<fieldset class="pc-location-manual" data-pc-location-manual>'
            . '<legend>Dirección de entrega</legend>'
            . '<p>Ingresa la dirección por escrito. El mapa es opcional y no modifica la tarifa.</p>'
            . '<label for="' . $id . '-ubigeo">Código UBIGEO del distrito (6 dígitos)</label>'
            . '<input id="' . $id . '-ubigeo" name="delivery[ubigeo_code]"'
            . ' type="text" inputmode="numeric" pattern="[0-9]{6}"'
            . ' minlength="6" maxlength="6" autocomplete="off" required>'
            . '<label for="' . $id . '-address">Dirección</label>'
            . '<input id="' . $id . '-address" name="delivery[address]"'
            . ' type="text" autocomplete="street-address" maxlength="500" required>'
            . '<label for="' . $id . '-reference">Referencia (opcional)</label>'
            . '<input id="' . $id . '-reference" name="delivery[reference]"'
            . ' type="text" maxlength="250">'
            . '</fieldset>';
    }

    private static function safeText(
        mixed $value, int $max, string $field, bool $optional
    ): string {
        if (!\is_string($value) || !\mb_check_encoding($value, 'UTF-8')
            || \mb_strlen($value, 'UTF-8') > $max
            || \preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new Rest_Failure('pc_field_invalid', 422, false, $field);
        }
        $clean = \trim($value);
        if (!$optional && '' === $clean) {
            throw new Rest_Failure('pc_field_invalid', 422, false, $field);
        }
        return $clean;
    }
}
