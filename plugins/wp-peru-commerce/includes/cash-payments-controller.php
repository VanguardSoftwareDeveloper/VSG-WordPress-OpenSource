<?php
/**
 * IMP-38 — R8-24 cobro neto presencial y R8-25 corrección histórica.
 * Exclusivamente REST staff de WordPress con nonce y reglas E-04/E-05.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Cash_Payments_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        // Registrar efectivo para pedidos existentes no implica reabrir
        // checkout, crear otra venta ni autorizar MP o refund API.
        Module_Gates::registerOperation(
            'cash','write',static fn():bool=>true
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadCashPayments();
        Cash_Payments::register();
        Rest_Router::bind(
            'R8-24',
            static fn(array $ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,
                'body'=>Cash_Payments::receipt(
                    $ctx['path']['id'],$ctx['body']
                ),
            ],
            static fn(array $ctx,?string $role):array=>
                Cash_Payments::guard('cash_receipt',$ctx,$role)
        );
        Rest_Router::bind(
            'R8-25',
            static fn(array $ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,
                'body'=>Cash_Payments::correction(
                    $ctx['path']['id'],$ctx['body']
                ),
            ],
            static fn(array $ctx,?string $role):array=>
                Cash_Payments::guard('cash_correction',$ctx,$role)
        );
    }
}
