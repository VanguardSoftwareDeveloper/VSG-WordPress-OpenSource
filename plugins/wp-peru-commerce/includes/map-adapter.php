<?php
/**
 * IMP-20 — adaptador de proveedor de mapa opcional.
 *
 * La configuración procede de SettingsPatch.map_tiles (R8-46); su
 * proyección pública es PublicBootstrap.map_tiles (R8-01). No se crea
 * otro REST, backend HTTP, SDK ni licencia implícita del proveedor.
 *
 * Ningún dato de comprador, coordenada, dirección ni token se envía al
 * verificador: este comprueba solo host, plantilla, atribución y las
 * condiciones de privacidad/CSP/licencia de la instalación.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Map_Adapter
{
    public const SCRIPT_MODULE = 'peru-commerce/map-optin';

    private static bool $hooks = false;
    private static bool $registered = false;

    /** @var callable|null Evidencia real del origen, privacidad, licencia y CSP. */
    private static $providerApproval = null;

    /** Un único aprobador real de esta instalación; no aceptar flag del panel. */
    public static function registerProviderApproval(callable $approval): void
    {
        if (null !== self::$providerApproval) {
            throw new \LogicException('pc_map_approval_duplicate');
        }
        self::$providerApproval = $approval;
    }

    public static function registerHooks(): void
    {
        if (self::$hooks) {
            return;
        }
        self::$hooks = true;
        \add_action('init', [self::class, 'register'], 9);
        // Solo registra el módulo local. Nunca lo encola globalmente.
        \add_action('wp_enqueue_scripts', [self::class, 'registerClientModule'], 8);
    }

    /**
     * Enlace con la validación ya existente de R8-45/46.
     * No declarar maps.read listo si falta verificador externo real.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/settings-validation.php';

        Settings_Validation::registerTileVerifier(
            static function (
                string $host, string $template, string $attributionUrl,
                string $attributionText
            ): bool {
                if (null === self::$providerApproval
                    || !self::validProvider($host, $template, $attributionUrl, $attributionText)) {
                    return false;
                }
                try {
                    return true === (self::$providerApproval)(
                        $host, $template, $attributionUrl, $attributionText
                    );
                } catch (\Throwable $ignored) {
                    return false;
                }
            }
        );

        Module_Gates::registerOperation('maps', 'read', static fn (): bool =>
            null !== self::$providerApproval
        );
    }

    /**
     * Sólo la capa OSM Standard conocida usa un contrato literal; otros
     * hosts requieren que el aprobador verifique explícitamente su licencia
     * y CSP. No construir URLs a partir de datos de geolocalización.
     */
    public static function validProvider(
        string $host, string $template, string $attributionUrl,
        string $attributionText
    ): bool {
        if ('' === $host || '' === $template || '' === $attributionText
            || '' === $attributionUrl
            || \mb_strlen($attributionText, 'UTF-8') > 500
            || !\mb_check_encoding($attributionText, 'UTF-8')
            || \preg_match('/[\x00-\x1F\x7F<>]/', $attributionText)
            || !\str_starts_with($template, 'https://')
            || !\str_starts_with($attributionUrl, 'https://')) {
            return false;
        }
        if ('tile.openstreetmap.org' === \strtolower($host)) {
            return 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' === $template
                && 'https://www.openstreetmap.org/copyright' === $attributionUrl
                && \str_contains($attributionText, 'OpenStreetMap');
        }
        // Los demás proveedores solo pasan por el aprobador y por
        // Settings_Validation::safeHttps; ninguno se habilita por defecto.
        return true;
    }

    /**
     * Contrato exclusivo de navegador, no ordena descargar ningún tile.
     * Se llama desde R8-01 y consumidores públicos futuros.
     *
     * @return array<string,mixed>|null
     */
    public static function publicConfig(array $settings): ?array
    {
        if (true !== ($settings['enabled'] ?? false)
            || !Module_Gates::allows('maps', 'read')) {
            return null;
        }
        try {
            Settings_Validation::map($settings);
        } catch (\Throwable $ignored) {
            return null;
        }
        return [
            'enabled' => true,
            'tile_url_template' => (string) $settings['tile_url_template'],
            'attribution_text' => (string) $settings['attribution_text'],
            'attribution_url' => (string) $settings['attribution_url'],
        ];
    }

    /**
     * Scripts y estilos de Leaflet NO se añaden en IMP-20: el manifest
     * local de Angular y la revisión BSD-2 de IMP-58/66 deben proporcionar
     * un loader real. El módulo es un adaptador ESM sin dependencias.
     */
    public static function registerClientModule(): void
    {
        if (\function_exists('wp_register_script_module')) {
            \wp_register_script_module(
                self::SCRIPT_MODULE,
                \plugins_url('assets/js/map-optin.js', PERUCOMMERCE_PLUGIN_FILE),
                [],
                PERUCOMMERCE_VERSION
            );
        }
    }
}
