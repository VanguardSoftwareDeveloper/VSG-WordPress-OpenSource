<?php
/**
 * IMP-39 — R8-23; cancelación de línea elegible, no refund API.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Cancellation_Controller
{
    private static bool $registered=false;
    private static bool $gates=false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        Module_Gates::registerOperation(
            'orders','cancel',static fn():bool=>true
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadOrdersCancellation();
        Rest_Router::bind(
            'R8-23',
            static fn(array $ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,
                'body'=>Orders_Cancellation::cancel(
                    $ctx['path']['id'],$ctx['path']['line_id'],$ctx['body']
                ),
            ],
            [Orders_Cancellation::class,'guard']
        );
    }
}
