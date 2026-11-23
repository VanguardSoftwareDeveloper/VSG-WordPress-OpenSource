<?php
/**
 * IMP-52 — R8-64. Registro manual append-only, exclusivo A.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class External_Documents_Controller
{
    private static bool $gates = false;
    private static bool $registered = false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates = true;
        // Documentar pedidos anteriores no depende de habilitar checkout.
        Module_Gates::registerOperation('documents','write',static fn():bool=>true);
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered = true;
        Bootstrap::loadExternalDocuments();
        Rest_Router::bind(
            'R8-64',
            static fn(array $ctx,\WP_REST_Request $_request):array => [
                'status'=>201,
                'body'=>External_Documents::record($ctx['path']['id'],$ctx['body']),
            ],
            [External_Documents::class,'guard']
        );
    }
}
