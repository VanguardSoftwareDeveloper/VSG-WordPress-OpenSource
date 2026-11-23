<?php
/**
 * IMP-34 — R8-58 reemisión de seguimiento protegida.
 *
 * Solamente administrador A con lectura y privacidad, nonce WordPress,
 * objeto existente y evidencia de identidad REAL. El guard puede
 * reconocer una clave ya reclamada del mismo scope para replay, pero
 * no permite crear un segundo token sin prueba nueva.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Tracking_Reissue_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        Module_Gates::registerOperation(
            'tracking','reissue',static fn():bool=>true
        );
        // No registra un verificador ficticio ni acredita identidad por
        // tener nombre/folio/teléfono. El servicio deniega por defecto.
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadTrackingReissue();
        Rest_Router::bind(
            'R8-58',
            static fn(array $ctx,\WP_REST_Request $_request): array=>[
                'status'=>200,
                'body'=>Tracking_Reissue::reissue(
                    $ctx['path']['id'],$ctx['body']
                ),
            ],
            [self::class,'guard']
        );
    }

    public static function guard(array $ctx,?string $role): array
    {
        return Tracking_Reissue::guard($ctx,$role);
    }
}
