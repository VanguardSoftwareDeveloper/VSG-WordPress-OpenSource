<?php
/**
 * IMP-44 — rutas administrativas R8-55/56/57, sin mutaciones E-01.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Logistics_Incidents_Controller
{
    private static bool $gates = false;
    private static bool $registered = false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates = true;
        // Registrar casos de pedidos anteriores no reabre checkout ni
        // requiere cuenta MP activa, pero sí esquema/locks P-C reales.
        Module_Gates::registerOperation(
            'orders','logistics_cases',static fn():bool=>true
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered = true;
        Bootstrap::loadLogisticsIncidents();
        Rest_Router::bind(
            'R8-55',
            static fn(array $ctx,\WP_REST_Request $_request):array=>
                Logistics_Incidents::open(
                    $ctx['path']['id'],$ctx['path']['line_id'],$ctx['body']
                ),
            [Logistics_Incidents::class,'guardOpen']
        );
        Rest_Router::bind(
            'R8-56',
            static fn(array $ctx,\WP_REST_Request $_request):array=>
                Logistics_Incidents::propose($ctx['path']['id'],$ctx['body']),
            [Logistics_Incidents::class,'guardPropose']
        );
        Rest_Router::bind(
            'R8-57',
            static fn(array $ctx,\WP_REST_Request $_request):array=>
                Logistics_Incidents::resolve($ctx['path']['id'],$ctx['body']),
            [Logistics_Incidents::class,'guardResolve']
        );
    }
}
