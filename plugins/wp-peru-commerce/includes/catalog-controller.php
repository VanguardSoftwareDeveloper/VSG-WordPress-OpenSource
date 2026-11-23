<?php
/**
 * IMP-15: catálogo administrativo R8-31…34; no expone el CPT ni el stock al público.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Catalog_Controller
{
    private static bool $registered = false;
    private static bool $readRegistered = false;
    public const POST_TYPE = 'pc_product';

    /** El catálogo HTML/indexable y sus endpoints públicos llegan en IMP-16. */
    public static function registerPostType(): void
    {
        \register_post_type(self::POST_TYPE, [
            'label' => 'Productos PeruCommerce',
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => false,
            'show_in_rest' => false,
            'show_in_nav_menus' => false,
            'rewrite' => false,
            'query_var' => false,
            'can_export' => false,
            // Ningún rol nativo WP administra estas fichas por post.php,
            // XML-RPC ni la API core. Solo R8-31…34 y capabilities propias.
            'map_meta_cap' => false,
            'capabilities' => [
                'edit_post' => 'do_not_allow',
                'read_post' => 'do_not_allow',
                'delete_post' => 'do_not_allow',
                'edit_posts' => 'do_not_allow',
                'edit_others_posts' => 'do_not_allow',
                'delete_posts' => 'do_not_allow',
                'delete_others_posts' => 'do_not_allow',
                'publish_posts' => 'do_not_allow',
                'read_private_posts' => 'do_not_allow',
                'create_posts' => 'do_not_allow',
            ],
            'supports' => ['title', 'editor'],
            'delete_with_user' => false,
        ]);

        // IMP-16: también debe funcionar en PHP/shortcodes, fuera de REST.
        // La lectura solo se habilita si las tablas WordPress son InnoDB
        // y el CPT existe; los demás checks siguen en Module_Gates.
        if (!self::$readRegistered) {
            self::$readRegistered = true;
            Module_Gates::registerOperation('catalog', 'read', static function (): bool {
                if (!\post_type_exists(self::POST_TYPE)) {
                    return false;
                }
                require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/catalog-service.php';
                return Catalog_Service::wordpressTablesTransactional();
            });
        }
    }

    /** Vincular solo consumidores implementados, antes del router REST (prioridad 10). */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadCatalog();

        Module_Gates::registerOperation('catalog', 'write', static fn (): bool =>
            \post_type_exists(self::POST_TYPE)
            && Catalog_Service::wordpressTablesTransactional()
        );

        Rest_Router::bind(
            'R8-31',
            static fn (array $ctx, \WP_REST_Request $_req): array => [
                'status' => 200,
                'body' => Catalog_Service::listing($ctx['query']),
            ],
            static fn (array $ctx, ?string $role): array => self::guard('R8-31', $ctx, $role)
        );
        Rest_Router::bind(
            'R8-32',
            static fn (array $ctx, \WP_REST_Request $_req): array => [
                'status' => 201,
                'body' => Catalog_Service::create($ctx['body']),
            ],
            static fn (array $ctx, ?string $role): array => self::guard('R8-32', $ctx, $role)
        );
        Rest_Router::bind(
            'R8-33',
            static fn (array $ctx, \WP_REST_Request $_req): array => [
                'status' => 200,
                'body' => Catalog_Service::patch($ctx['path']['id'], $ctx['body']),
            ],
            static fn (array $ctx, ?string $role): array => self::guard('R8-33', $ctx, $role)
        );
        Rest_Router::bind(
            'R8-34',
            static fn (array $ctx, \WP_REST_Request $_req): array => [
                'status' => 200,
                'body' => Catalog_Service::visibility($ctx['path']['id'], $ctx['body']),
            ],
            static fn (array $ctx, ?string $role): array => self::guard('R8-34', $ctx, $role)
        );
    }

    /** El guard no recibe pruebas del cliente: rol, sitio y recurso se verifican en servidor. */
    private static function guard(string $operation, array $context, ?string $role): array
    {
        if (!\in_array($role, ['A', 'O'], true)
            || Access_Roles::currentRole() !== $role
            || !\function_exists('get_current_blog_id')
            || (int) \get_current_blog_id() <= 0
            || !\post_type_exists(self::POST_TYPE)) {
            return [];
        }

        try {
            if (\in_array($operation, ['R8-33', 'R8-34'], true)
                && (!\is_string($context['path']['id'] ?? null)
                    || !Catalog_Service::exists($context['path']['id']))) {
                return [];
            }
            $proof = [
                'authorized' => true,
                'same_site' => true,
                'resource_checked' => true,
                'state_checked' => true,
                'field_checked' => true,
            ];
            // IMP-61: la proyección de slots exige propósito verificado
            // en este guard, además de A + catálogo + archivos privados.
            // El servicio selecciona únicamente productos de este CPT/sitio.
            if ('A' === $role
                && \current_user_can('perucommerce_catalog_edit')
                && \current_user_can('perucommerce_digital_files_manage')) {
                $proof['digital_purpose_verified'] = true;
            }
            if ('R8-34' === $operation) {
                $visibility = $context['body']['visibility'] ?? null;
                $proof['visibility_action'] = match ($visibility) {
                    'hidden' => 'hide',
                    'published' => 'publish',
                    default => '',
                };
                if ('O' === $role && 'hide' !== $proof['visibility_action']) {
                    return [];
                }
            }
            return $proof;
        } catch (\Throwable $ignored) {
            return [];
        }
    }
}
