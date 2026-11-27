<?php
/**
 * IMP-14 — R8-01 / R8-19 sin secretos ni estado comercial supuesto.
 *
 * No se añade campo rest_url al DTO: el consumidor WordPress obtiene la
 * base API mediante rest_url('peru-commerce/v1') en la fase de assets
 * (IMP-20), sin hardcodear /wp-json ni ignorar subdirectorios.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Bootstrap_Api_Service
{
    /** @var callable|null El módulo Libro registra la página WP publicada. */
    private static $nativeComplaintsPage = null;

    /**
     * Capability UI -> operaciones efectivamente registradas; un gate de
     * dominio listo NO implica que una ruta concreta ya tenga controlador.
     * La UI no es un permiso de backend ni garantiza todos los subflujos.
     *
     * @var array<string,list<string>>
     */
    private const CAPABILITY_OPERATIONS = [
        'perucommerce_appointments_manage' => ['R8-36', 'R8-37', 'R8-38', 'R8-53'],
        'perucommerce_cash_correct' => ['R8-25'],
        'perucommerce_cash_movements_record' => ['R8-75'],
        'perucommerce_cash_record' => ['R8-24'],
        'perucommerce_cash_session_operate' => ['R8-73', 'R8-74', 'R8-76'],
        'perucommerce_cash_session_review' => ['R8-77', 'R8-78', 'R8-79'],
        'perucommerce_catalog_edit' => ['R8-31', 'R8-32', 'R8-33'],
        'perucommerce_catalog_hide' => ['R8-34'],
        'perucommerce_catalog_publish' => ['R8-34'],
        'perucommerce_complaints_manage' => ['R8-41', 'R8-42', 'R8-65'],
        'perucommerce_digital_files_manage' => ['R8-48', 'R8-60', 'R8-61', 'R8-62', 'R8-63'],
        'perucommerce_external_documents_record' => ['R8-80', 'R8-81'],
        'perucommerce_integrations_manage' => ['R8-47'],
        'perucommerce_notifications_operate' => ['R8-39', 'R8-40'],
        'perucommerce_orders_cancel_unpaid' => ['R8-23'],
        'perucommerce_orders_financial_adjust' => ['R8-26', 'R8-69', 'R8-70'],
        'perucommerce_orders_fulfill' => ['R8-22', 'R8-28', 'R8-55'],
        'perucommerce_orders_read' => ['R8-20', 'R8-21'],
        'perucommerce_pos_sell' => ['R8-71', 'R8-72', 'R8-82'],
        'perucommerce_privacy_manage' => ['R8-43', 'R8-44', 'R8-50', 'R8-66'],
        'perucommerce_reconciliation_manage' => ['R8-29', 'R8-30'],
        'perucommerce_refunds_record_external' => ['R8-27', 'R8-83'],
        'perucommerce_settings_manage' => ['R8-45', 'R8-46'],
        'perucommerce_stock_manage' => ['R8-35'],
    ];

    /**
     * Un futuro módulo nativo proporciona un ID de página; comprobará su
     * publicación/sitio y se obtendrá el permalink real de WordPress.
     * No inventar /reclamaciones ni enlazar un endpoint POST como página.
     */
    public static function registerNativeComplaintsPage(callable $resolver): void
    {
        if (null !== self::$nativeComplaintsPage) {
            throw new \LogicException('pc_bootstrap_complaints_resolver_duplicate');
        }
        self::$nativeComplaintsPage = $resolver;
    }

    /** @return array<string,mixed> PublicBootstrap, sin datos de contacto. */
    public static function publicResponse(): array
    {
        $snapshot = self::safeSnapshot();
        $settings = $snapshot['settings'];
        $business = $settings['business'];
        $hasNotices = $snapshot['legal_ready'];

        // No basta activar un booleano. Cobro/stock/citas/UBIGEO se anuncian
        // solo si el proveedor y los locks realmente pasaron los gates P-C.
        $quote = Rest_Router::isOperationReady('R8-05');
        $checkout = $hasNotices && $settings['payment_enabled'] && $quote;
        $mp = $checkout && Rest_Router::isOperationReady('R8-06');
        $cash = $checkout && Rest_Router::isOperationReady('R8-07');
        $delivery = ($mp || $cash) && $settings['delivery_enabled']
            && Rest_Router::isOperationReady('R8-16')
            && Module_Gates::allows('delivery', 'write');
        if ($delivery) {
            // IMP-19: la versión INEI importada puede ser nueva y haber
            // invalidado la cobertura previa. No anunciar delivery hasta
            // su revisión A con R8-52, aunque delivery_enabled siga true.
            Bootstrap::loadDelivery();
            $delivery = Delivery_Coverage::activeReviewReady();
        }

        $methods = [];
        if ($mp) {
            $methods[] = 'mercado_pago';
        }
        if ($cash) {
            $methods[] = 'cash';
        }

        $response = [
            'business_name' => self::safeName($business['business_name'] ?? null),
            'locale' => 'es-PE',
            'currency' => 'PEN',
            'supports_pickup' => $mp || $cash,
            'supports_delivery' => $delivery,
            'payment_methods' => $methods,
        ];

        if ($hasNotices) {
            $response['policy_version'] = $settings['policy_version_id'];
            $response['privacy_version'] = $settings['privacy_version_id'];
            $response['policy_notice'] = $snapshot['policy_notice'];
            $response['privacy_notice'] = $snapshot['privacy_notice'];
        }

        $complaintsUrl = self::complaintsUrl($business);
        if (null !== $complaintsUrl) {
            $response['complaints_url'] = $complaintsUrl;
        }

        // IMP-20: proyección única de proveedor realmente autorizado.
        // R8-01 entrega datos; cargar Leaflet/tiles es decisión futura del
        // usuario sobre una superficie pública segura, nunca este GET.
        $publicMap = Map_Adapter::publicConfig($settings['map_tiles']);
        if (null !== $publicMap) {
            $response['map_tiles'] = $publicMap;
        }
        return $response;
    }

    /** @return array<string,mixed> AdminBootstrap filtrado por IMP-11/12. */
    public static function adminResponse(): array
    {
        $role = Access_Roles::currentRole();
        if (!\in_array($role, ['A', 'O'], true)) {
            throw new \RuntimeException('pc_access_denied');
        }
        $user = \get_current_user_id();
        if (!\is_int($user) || $user < 1) {
            throw new \RuntimeException('pc_access_denied');
        }
        // R8-19 no necesita cargar textos legales ni credenciales. Incluso
        // con políticas pendientes, la sesión A/O puede abrir su panel.
        $settings = Transaction::run(
            static fn (Transaction_Context $tx): array =>
                Settings_Store::readWithin($tx)['settings']
        );
        return [
            'user_id' => (string) $user,
            'role_label' => $role,
            'ui_capabilities' => self::readyCapabilities($role),
            'business_name' => self::safeName($settings['business']['business_name'] ?? null),
        ];
    }

    /** @return array{settings:array<string,mixed>,legal_ready:bool,policy_notice:?string,privacy_notice:?string} */
    private static function safeSnapshot(): array
    {
        return Transaction::run(static function (Transaction_Context $tx): array {
            // No traer contactos, credenciales, cuerpo de pedido o info MP.
            $settings = Settings_Store::readWithin($tx)['settings'];
            $terms = null;
            $privacy = null;
            if (null !== $settings['policy_version_id']
                && null !== $settings['privacy_version_id']) {
                try {
                    $terms = Settings_Store::policyWithin(
                        $tx, $settings['policy_version_id'], 'commercial_terms'
                    );
                    $privacy = Settings_Store::policyWithin(
                        $tx, $settings['privacy_version_id'], 'privacy_notice'
                    );
                } catch (\Throwable $ignored) {
                    // No se anuncian condiciones huérfanas/inválidas.
                    $terms = null;
                    $privacy = null;
                }
            }
            $ready = \is_string($terms) && '' !== \trim($terms)
                && \mb_strlen($terms, 'UTF-8') <= 30000
                && \is_string($privacy) && '' !== \trim($privacy)
                && \mb_strlen($privacy, 'UTF-8') <= 30000
                && 'Comercio sin configurar' !== ($settings['business']['business_name'] ?? null);
            return [
                'settings' => $settings,
                'legal_ready' => $ready,
                'policy_notice' => $ready ? $terms : null,
                'privacy_notice' => $ready ? $privacy : null,
            ];
        });
    }

    /** @param array<string,mixed> $business */
    private static function complaintsUrl(array $business): ?string
    {
        if (($business['complaint_mode'] ?? null) === 'native') {
            if (null === self::$nativeComplaintsPage) {
                return null;
            }
            try {
                $id = (self::$nativeComplaintsPage)();
                if (!\is_int($id) || $id < 1
                    || 'page' !== \get_post_type($id)
                    || 'publish' !== \get_post_status($id)) {
                    return null;
                }
                $url = \get_permalink($id);
                return \is_string($url) && self::sameSiteHttps($url)
                    ? $url : null;
            } catch (\Throwable $ignored) {
                return null;
            }
        }

        if (($business['complaint_mode'] ?? null) !== 'external'
            || !\is_string($business['complaint_url'] ?? null)) {
            return null;
        }
        try {
            // Ya se validó al guardar, pero un verificador real debe seguir
            // presente en cada lectura pública de una opción histórica.
            Settings_Validation::business($business);
            $url = $business['complaint_url'];
            if (\str_starts_with($url, '/')) {
                $url = \home_url($url);
                return self::sameSiteHttps($url) ? $url : null;
            }
            // El proveedor externo fue autorizado por su propio verificador.
            return \str_starts_with($url, 'https://') ? $url : null;
        } catch (\Throwable $ignored) {
            return null;
        }
    }

    private static function sameSiteHttps(string $url): bool
    {
        $site = \wp_parse_url(\home_url('/'));
        $target = \wp_parse_url($url);
        if (!\is_array($site) || !\is_array($target)
            || ($target['scheme'] ?? null) !== 'https'
            || ($site['scheme'] ?? null) !== 'https'
            || \strtolower($site['host'] ?? '') !== \strtolower($target['host'] ?? '')
            || ($site['port'] ?? 443) !== ($target['port'] ?? 443)
            || isset($target['user']) || isset($target['pass'])
            || isset($target['fragment'])) {
            return false;
        }
        $root = \rtrim($site['path'] ?? '/', '/') . '/';
        $path = $target['path'] ?? '/';
        return \str_starts_with($path, $root);
    }

    private static function safeName(mixed $name): string
    {
        if (!\is_string($name) || 'Comercio sin configurar' === $name
            || '' === \trim($name) || !\mb_check_encoding($name, 'UTF-8')
            || \mb_strlen($name, 'UTF-8') > 200
            || \preg_match('/[\x00-\x1F\x7F]/', $name)) {
            $name = \get_bloginfo('name');
        }
        if (!\is_string($name) || '' === \trim($name)
            || !\mb_check_encoding($name, 'UTF-8')
            || \mb_strlen($name, 'UTF-8') > 200
            || \preg_match('/[\x00-\x1F\x7F]/', $name)) {
            return 'Comercio';
        }
        return $name;
    }

    /** @return list<string> */
    private static function readyCapabilities(string $role): array
    {
        $candidates = 'A' === $role
            ? Access_Roles::adminCapabilities() : Access_Roles::operatorCapabilities();
        $usable = [];
        foreach ($candidates as $capability) {
            if (!\current_user_can($capability)) {
                continue;
            }
            $operations = self::CAPABILITY_OPERATIONS[$capability] ?? [];
            foreach ($operations as $operationId) {
                if (Rest_Router::isOperationReady($operationId)) {
                    $usable[] = $capability;
                    break;
                }
            }
        }
        return $usable;
    }
}
