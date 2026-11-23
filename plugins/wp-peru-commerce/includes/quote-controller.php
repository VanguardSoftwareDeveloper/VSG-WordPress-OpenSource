<?php
/**
 * IMP-24 — R8-05 POST /public/quotes.
 *
 * La ruta pública NO consume idempotency_key, no llama a Mercado Pago,
 * no escribe en la BD y no crea recurso, cita, hold, derecho ni pedido.
 * Sólo un consumidor comercial futuro puede confirmar tras recotizar.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Quote_Controller
{
    private static bool $gates = false;
    private static bool $registered = false;

    public static function registerGates(): void
    {
        if (self::$gates) {
            return;
        }
        self::$gates = true;
        // El nombre histórico del gate es checkout.write aunque R8-05
        // sea lectura comercial POST; NO habilita R8-06/07 ni orders.write.
        Module_Gates::registerOperation('checkout','write',static function (): bool {
            if (!\post_type_exists(Catalog_Controller::POST_TYPE)) {
                return false;
            }
            Bootstrap::loadCatalog();
            return Catalog_Service::wordpressTablesTransactional();
        });
    }

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadQuote();
        Rest_Router::bind(
            'R8-05',
            static function (array $ctx, \WP_REST_Request $_request): array {
                $quote = Quote_Service::quote($ctx['body']);
                // Cookie efímera sólo same-origin, después de la cotización.
                // No crea reserva, pedido, hold ni sesión autenticada.
                Checkout_Form::issueForQuote();
                return ['status'=>200,'body'=>$quote];
            },
            [self::class,'publicGuard']
        );
    }

    public static function publicGuard(array $_ctx, ?string $role): array
    {
        if (null !== $role || !\function_exists('get_current_blog_id')
            || (int) \get_current_blog_id() < 1
            || !\post_type_exists(Catalog_Controller::POST_TYPE)) {
            return [];
        }
        return ['authorized'=>true];
    }
}
