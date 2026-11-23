<?php
/**
 * IMP-16 — rutas públicas R8-02 y R8-03 (OpenAPI 0.12.0-draft).
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Catalog_Public_Controller
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadPublicCatalog();

        Rest_Router::bind(
            'R8-02',
            static fn (array $context, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Catalog_Public_Service::listing($context['query']),
            ],
            [self::class, 'guard']
        );
        Rest_Router::bind(
            'R8-03',
            static fn (array $context, \WP_REST_Request $_request): array => [
                'status' => 200,
                'body' => Catalog_Public_Service::detail($context['path']['id']),
            ],
            [self::class, 'guard']
        );
    }

    /** El guard no convierte nonce, usuario o stock en requisitos públicos. */
    public static function guard(array $_context, ?string $role): array
    {
        if (null !== $role || !\function_exists('get_current_blog_id')
            || (int) \get_current_blog_id() <= 0
            || !\post_type_exists(Catalog_Controller::POST_TYPE)) {
            return [];
        }
        return ['authorized' => true];
    }
}
