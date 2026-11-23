<?php
/**
 * IMP-37 — R8-22 POST /admin/orders/{id}/lines/{line_id}/fulfillment.
 * Usa Rest_Router/Access_Control/OpenAPI existentes y el gate de
 * cumplimiento ya vendido, independiente de habilitar nuevos checkouts.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Physical_Fulfillment_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        Module_Gates::registerOperation(
            'physical','fulfill',
            static function():bool {
                // Se puede completar una venta existente si se apagó
                // checkout; no permitirlo sin inventario/locks verdaderos.
                return \post_type_exists(Catalog_Controller::POST_TYPE)
                    && Module_Gates::allows('inventory','write');
            }
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadPhysicalFulfillment();
        Rest_Router::bind(
            'R8-22',
            static fn(array $ctx,\WP_REST_Request $_request):array => [
                'status'=>200,
                'body'=>Physical_Fulfillment::change(
                    $ctx['path']['id'],$ctx['path']['line_id'],$ctx['body']
                ),
            ],
            [Physical_Fulfillment::class,'guard']
        );
    }
}
