<?php
/**
 * IMP-17 — POST R8-35 /admin/stock/adjustments.
 *
 * Solo personal A/O con capability perucommerce_stock_manage. No exponer
 * operaciones de hold, confirmación, salida o cancelación al cliente:
 * sus consumidores externos se registran en fases de pago/pedidos.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Inventory_Controller
{
    private static bool $gates = false;
    private static bool $registered = false;

    /**
     * Disponible para REST, workers y flujos comerciales internos.
     * El gate no se declara listo por estar registrado: exige prueba real
     * de locks y schema en el entorno antes de aceptar escrituras.
     */
    public static function registerGates(): void
    {
        if (self::$gates) {
            return;
        }
        self::$gates = true;
        Module_Gates::registerOperation('inventory', 'write', static function (): bool {
            if (!\post_type_exists(Catalog_Controller::POST_TYPE)) {
                return false;
            }
            Bootstrap::loadCatalog();
            return Catalog_Service::wordpressTablesTransactional();
        });
    }

    /** Prioridad 5: enlazar controlador antes de Rest_Router prioridad 10. */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadInventory();
        Rest_Router::bind(
            'R8-35',
            static fn (array $context, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Inventory_Service::adjust($context['body']),
            ],
            [self::class, 'guard']
        );
    }

    /** @param array<string,mixed> $context */
    public static function guard(array $context, ?string $role): array
    {
        if (!\in_array($role, ['A', 'O'], true)
            || Access_Roles::currentRole() !== $role
            || !\current_user_can('perucommerce_stock_manage')
            || !\function_exists('get_current_blog_id')
            || (int) \get_current_blog_id() <= 0) {
            return [];
        }
        $body = $context['body'] ?? [];
        $id = $body['sellable_id'] ?? null;
        $delta = $body['delta_units'] ?? null;
        if (!\is_string($id) || !\is_int($delta)
            || !\is_string($body['expected_version'] ?? null)
            || !\is_string($body['reason'] ?? null)
            || '' === \trim($body['reason'])) {
            return [];
        }
        if (!Inventory_Service::existsForStaff($id)) {
            return [];
        }
        return [
            'authorized' => true,
            'same_site' => true,
            'resource_checked' => true,
            'state_checked' => true,
            'field_checked' => true,
        ];
    }
}
