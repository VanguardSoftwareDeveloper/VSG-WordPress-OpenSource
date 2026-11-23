<?php
/**
 * IMP-41 — R8-26 /admin/orders/{id}/adjustments.
 *
 * Ruta administrativa A-only: una reducción parcial requiere un
 * expediente R8-69 y cierre R8-26 inseparables del ajuste/compromiso.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Adjustments_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        // Ajustar pedidos existentes no supone aceptar nuevas ventas
        // ni disponer de MP o refund API. La reducción física demanda
        // además inventory.write en el consumidor y locks P-C.
        Module_Gates::registerOperation(
            'orders','adjust',static fn():bool=>true
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadOrdersAdjustments();
        Rest_Router::bind(
            'R8-26',
            static fn(array $ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,
                'body'=>Orders_Adjustments::adjust(
                    $ctx['path']['id'],$ctx['body']
                ),
            ],
            [Orders_Adjustments::class,'guard']
        );
    }
}
