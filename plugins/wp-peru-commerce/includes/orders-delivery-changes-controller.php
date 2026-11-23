<?php
/**
 * IMP-43 — R8-28 cambios de dirección: O propone, A aprueba.
 * El destino original, presupuesto y revisiones históricas se preservan.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Delivery_Changes_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        Module_Gates::registerOperation(
            'delivery','change',
            // Requiere Ubigeo REAL, licencia/versión/revisión P-C y
            // locks al mismo nivel que R8-52, sin abrir checkout nuevo.
            static fn():bool=>Module_Gates::allows('delivery','write')
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadOrdersDeliveryChanges();
        Rest_Router::bind(
            'R8-28',
            static fn(array $ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,
                'body'=>Orders_Delivery_Changes::change(
                    $ctx['path']['id'],$ctx['body']
                ),
            ],
            [Orders_Delivery_Changes::class,'guard']
        );
    }
}
