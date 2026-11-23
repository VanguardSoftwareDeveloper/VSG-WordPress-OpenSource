<?php
/**
 * IMP-14 — controladores reales R8-01 y R8-19.
 *
 * Prioridad 5 en rest_api_init: antes de Rest_Router::registerRoutes() (10).
 * Bootstrap público no recibe nonce ni identidad de cliente; el administrativo
 * conserva cookie WP, nonce CSRF y rol A/O en Access_Control (IMP-11).
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Bootstrap_Api_Controller
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadBootstrapApi();

        // Son lecturas inocuas de configuración, NO prueba de venta,
        // cobro, agenda ni entrega. Requieren instalación/schema/HTTPS.
        Module_Gates::registerOperation('bootstrap', 'read', static fn (): bool => true);
        Module_Gates::registerOperation('bootstrap', 'staff', static fn (): bool => true);

        Rest_Router::bind(
            'R8-01',
            static fn (array $_context, \WP_REST_Request $_request): array =>
                ['status' => 200, 'body' => Bootstrap_Api_Service::publicResponse()],
            [self::class, 'publicGuard']
        );
        Rest_Router::bind(
            'R8-19',
            static fn (array $_context, \WP_REST_Request $_request): array =>
                ['status' => 200, 'body' => Bootstrap_Api_Service::adminResponse()],
            [self::class, 'staffGuard']
        );
    }

    /** @param array<string,mixed> $_context */
    public static function publicGuard(array $_context, ?string $role): array
    {
        if (null !== $role || !self::installedSite()) {
            return [];
        }
        return ['authorized' => true];
    }

    /** @param array<string,mixed> $_context */
    public static function staffGuard(array $_context, ?string $role): array
    {
        if (!\in_array($role, ['A', 'O'], true) || !self::installedSite()
            || Access_Roles::currentRole() !== $role) {
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

    private static function installedSite(): bool
    {
        try {
            $id = \get_current_blog_id();
            $env = new Environment();
            return \is_int($id) && $id > 0
                && $env->has('platform') && $env->has('https')
                && $env->has('database') && $env->has('schema');
        } catch (\Throwable $ignored) {
            return false;
        }
    }
}
