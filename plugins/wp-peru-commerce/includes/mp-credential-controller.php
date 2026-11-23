<?php
/**
 * IMP-27 — R8-47 PUT /admin/payment-credentials.
 * Solo administrador A + nonce CSRF + integrations_manage; jamás GET de
 * token ni devolución de secreto en MutationResult.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Credential_Controller
{
    private static bool $registered=false;

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadMp();
        Rest_Router::bind(
            'R8-47',
            static fn (array $ctx,\WP_REST_Request $_req): array => [
                'status'=>200,
                'body'=>MP_Credential_Service::rotate($ctx['body']),
            ],
            [self::class,'guard']
        );
    }

    /**
     * R8-47 no depende de mp_orders probado para CONFIGURAR secretos.
     * La publicación depende de settings.write; payments.write sigue
     * fail-closed hasta P-C/IMP-28, sin autorización implícita por rol WP.
     */
    public static function guard(array $ctx,?string $role): array
    {
        if ('A' !== $role
            || !\current_user_can('perucommerce_integrations_manage')
            || !\function_exists('get_current_blog_id')
            || (int)\get_current_blog_id()<1
            || (\function_exists('is_network_admin') && \is_network_admin())) {
            return [];
        }
        try {
            $valid = Transaction::run(static function (Transaction_Context $tx): bool {
                $settings = Settings_Store::readWithin($tx);
                $vault = MP_Credentials::readWithin($tx);
                return \is_string($settings['settings']['row_version'] ?? null)
                    && \is_string($vault['row_version'] ?? null);
            });
            return $valid ? [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,
            ] : [];
        } catch (\Throwable $ignored) {
            return [];
        }
    }
}
