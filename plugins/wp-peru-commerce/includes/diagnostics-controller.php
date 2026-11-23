<?php
/**
 * IMP-53: R8-49, solo A. No realiza chequeos externos ni mutaciones.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Diagnostics_Controller
{
    private static bool $registered=false;

    public static function register():void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadDiagnostics();
        Rest_Router::bind(
            'R8-49',
            static fn(array $_ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,'body'=>Diagnostics_Service::read()
            ],
            static function(array $_ctx,?string $role):array {
                if ('A'!==$role || 'A'!==Access_Roles::currentRole()
                    || !\current_user_can('perucommerce_settings_manage')
                    || (int)\get_current_blog_id()<1) {
                    return [];
                }
                return [
                    'authorized'=>true,'same_site'=>true,
                    'resource_checked'=>true,'state_checked'=>true,
                    'field_checked'=>true,
                ];
            }
        );
    }
}
