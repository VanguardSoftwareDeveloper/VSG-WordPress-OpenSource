<?php
/**
 * IMP-28 — R8-06 POST /public/checkout/mercado-pago.
 *
 * Solo se publica si MP Orders, HTTPS, motor/locks y cuenta peruana
 * tienen evidencia P-C auténtica. Configurar token nunca basta.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Checkout_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    /** Gate financiero, independiente del de R8-47 (settings.write). */
    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        Module_Gates::registerOperation('payments','write',static function (): bool {
            if (!Module_Gates::allows('checkout','write')
                || !Module_Gates::allows('orders','write')) {
                return false;
            }
            try {
                Bootstrap::loadMp();
                $entry=MP_Credentials::current('production');
                return 'production'===($entry['environment']??null)
                    && \is_string($entry['account_digest_hex']??null)
                    && \is_string($entry['access_token']??null);
            } catch (\Throwable $ignored) {
                return false;
            }
        });
        // IMPORTANTE: NO registrar evidencia mp_orders aquí. Solo las
        // comprobaciones P-C sobre una cuenta real autorizada pueden
        // satisfacer el requisito de Module_Gates::payments.write.
    }

    /** Prioridad 5; Rest_Router registra rutas en prioridad 10. */
    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadCheckout();
        Bootstrap::loadMp();
        Checkout_Form::registerVerifiers();
        if (!Module_Gates::allows('payments','write')) {
            return;
        }
        try {
            Checkout_Tracking::configurePageUrl(
                Checkout_COD_Controller::trackingPage()
            );
        } catch (\Throwable $ignored) {
            return;
        }
        Rest_Router::bind(
            'R8-06',
            static fn (array $ctx,\WP_REST_Request $_request): array =>
                MP_Checkout_Service::confirm($ctx['body']),
            [self::class,'publicGuard']
        );
    }

    /** La cookie HMAC solamente delimita idempotencia del formulario. */
    public static function publicGuard(array $_ctx,?string $role): array
    {
        if (null!==$role || !\function_exists('get_current_blog_id')
            || (int)\get_current_blog_id()<1
            || !\post_type_exists(Catalog_Controller::POST_TYPE)
            || !Module_Gates::allows('payments','write')) {
            return [];
        }
        try {
            Checkout_Form::verifiedDigest();
            return ['authorized'=>true];
        } catch (\Throwable $ignored) {
            return [];
        }
    }
}
