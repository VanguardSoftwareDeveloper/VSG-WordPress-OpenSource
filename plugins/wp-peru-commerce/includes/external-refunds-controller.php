<?php
/**
 * IMP-42: R8-27, solo REGISTRO de resultado externo ya comprobado por A.
 * No HTTP de refund ni efecto monetario en pasarela/caja.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class External_Refunds_Controller
{
    private static bool $registered=false;
    private static bool $gates=false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        // Registrar un reembolso efectivamente ocurrido de una venta
        // anterior no depende de permitir nuevos checkouts ni credenciales
        // MP actuales. Requiere persistencia/locks reales P-C.
        Module_Gates::registerOperation(
            'refunds','record',static fn():bool=>true
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadExternalRefunds();
        External_Refunds::register();
        Rest_Router::bind(
            'R8-27',
            static fn(array $ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,
                'body'=>External_Refunds::record(
                    $ctx['path']['id'],$ctx['body']
                ),
            ],
            [External_Refunds::class,'guard']
        );
    }
}
