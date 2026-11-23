<?php
/**
 * IMP-40 — rutas R8-69 y R8-70 de caso fisico parcial D-199/D-200.
 * Solo A con orders_read Y orders_financial_adjust + nonce WP real.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Manual_Physical_Cases_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        // Un expediente de venta existente no necesita habilitar
        // checkout, MP, stock.write ni servicio de notificaciones.
        Module_Gates::registerOperation(
            'orders','manual_cases',static fn():bool=>true
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadManualPhysicalCases();
        Rest_Router::bind(
            'R8-69',
            static fn(array $ctx,\WP_REST_Request $_request):array=>
                Manual_Physical_Cases::open(
                    $ctx['path']['id'],$ctx['body']
                ),
            [Manual_Physical_Cases::class,'guardOpen']
        );
        Rest_Router::bind(
            'R8-70',
            static fn(array $ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,
                'body'=>Manual_Physical_Cases::close(
                    $ctx['path']['id'],$ctx['path']['case_id'],$ctx['body']
                ),
            ],
            [Manual_Physical_Cases::class,'guardClose']
        );
    }
}
