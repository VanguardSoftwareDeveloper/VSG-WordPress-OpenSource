<?php
/**
 * IMP-19 — R8-16 (público), R8-51 y R8-52 (solo A).
 *
 * Importación del artefacto y validación de licencia NO son endpoints:
 * sin proveedor real de evidencia "ubigeo" no se publican estas rutas.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Delivery_Controller
{
    private static bool $gates = false;
    private static bool $registered = false;

    /** Registrar handlers sin fabricar evidencia de dataset, licencia ni locks. */
    public static function registerGates(): void
    {
        if (self::$gates) {
            return;
        }
        self::$gates = true;
        Module_Gates::registerOperation(
            'delivery', 'read',
            static fn (): bool => Coverage_Version::optionsTransactional()
        );
        Module_Gates::registerOperation(
            'delivery', 'write',
            static fn (): bool => Coverage_Version::optionsTransactional()
        );
    }

    /** El router registra rutas solo si todos los gates están verificados. */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadDelivery();
        Rest_Router::bind(
            'R8-16',
            static fn (array $ctx, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Delivery_Coverage::publicPage($ctx['query']),
            ],
            [self::class, 'publicGuard']
        );
        Rest_Router::bind(
            'R8-51',
            static fn (array $_ctx, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Delivery_Coverage::adminView(),
            ],
            [self::class, 'staffGuard']
        );
        Rest_Router::bind(
            'R8-52',
            static fn (array $ctx, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Delivery_Coverage::patch($ctx['body']),
            ],
            [self::class, 'staffGuard']
        );
    }

    public static function publicGuard(array $_context, ?string $role): array
    {
        if (null !== $role || !self::siteReady()) {
            return [];
        }
        return ['authorized' => true];
    }

    public static function staffGuard(array $_context, ?string $role): array
    {
        if ('A' !== $role || 'A' !== Access_Roles::currentRole()
            || !\current_user_can('perucommerce_settings_manage')
            || !self::siteReady()) {
            return [];
        }
        try {
            // Prueba de objeto de servidor, no un flag declarado por cliente.
            $ready = Transaction::run(static function (Transaction_Context $tx): bool {
                Coverage_Version::readWithin($tx);
                Ubigeo_Catalog::activeWithin($tx);
                return true;
            });
            if (!$ready) {
                return [];
            }
        } catch (\Throwable $ignored) {
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

    private static function siteReady(): bool
    {
        return \function_exists('get_current_blog_id')
            && (int) \get_current_blog_id() > 0;
    }
}
