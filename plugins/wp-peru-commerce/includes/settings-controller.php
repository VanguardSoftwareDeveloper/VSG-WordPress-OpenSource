<?php
/**
 * IMP-13 — consumidores reales R8-45 GET /admin/settings y
 * R8-46 PATCH /admin/settings (OpenAPI 0.13.0-draft).
 *
 * Se conectan en rest_api_init prioridad 5 antes del enrutador (10).
 * Permisos/DTO de IMP-11/12 y Module_Gates permanecen fail-closed.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Settings_Controller
{
    private static bool $registered = false;

    /**
     * Durante el hook rest_api_init temprano: no cargar ni preparar estas
     * rutas en vistas públicas WP, wp-admin, Cron o módulos no solicitados.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        Bootstrap::loadSettings();

        Module_Gates::registerOperation('settings', 'read', static fn (): bool => true);
        Module_Gates::registerOperation('settings', 'write', static fn (): bool => true);

        Rest_Router::bind(
            'R8-45',
            static function (array $context, \WP_REST_Request $request): array {
                return ['status' => 200, 'body' => Settings_Service::read()];
            },
            [self::class, 'guard']
        );
        Rest_Router::bind(
            'R8-46',
            static function (array $context, \WP_REST_Request $request): array {
                return [
                    'status' => 200,
                    'body' => Settings_Service::update($context['body']),
                ];
            },
            [self::class, 'guard']
        );
    }

    /**
     * El recurso singular es la opción del sitio WP actual, comprobada en
     * SQL, no un identificador del navegador. El PATCH revalida bajo CAS.
     *
     * @param array<string,mixed> $context Ya validado por Rest_Validator.
     * @return array<string,bool> Pruebas categóricas del guard IMP-11.
     */
    public static function guard(array $context, ?string $role): array
    {
        if ('A' !== $role
            || !\current_user_can('perucommerce_settings_manage')
            || !\function_exists('get_current_blog_id')) {
            return [];
        }
        try {
            $siteId = \get_current_blog_id();
            if (!\is_int($siteId) || $siteId <= 0
                || !(new Environment())->has('schema')) {
                return [];
            }
            $valid = Transaction::run(static function (Transaction_Context $tx): bool {
                $record = Settings_Store::readWithin($tx);
                return \is_string($record['settings']['row_version'] ?? null);
            });
            if (!$valid) {
                return [];
            }
            return [
                'authorized' => true,
                'same_site' => true,
                'resource_checked' => true,
                'state_checked' => true,
                'field_checked' => true,
            ];
        } catch (\Throwable $ignored) {
            return [];
        }
    }
}
