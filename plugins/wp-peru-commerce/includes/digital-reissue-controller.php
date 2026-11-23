<?php
/** IMP-35 — registro R8-59; política de staff/nonce de IMP-11/12. */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Reissue_Controller
{
    private static bool $registered=false;

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadDigitalAccess();
        Rest_Router::bind(
            'R8-59',
            static fn(array $ctx,\WP_REST_Request $_request):array => [
                'status'=>200,
                'body'=>Digital_Reissue::reissue(
                    $ctx['path']['id'],$ctx['body']
                ),
            ],
            [Digital_Reissue::class,'guard']
        );
    }
}
