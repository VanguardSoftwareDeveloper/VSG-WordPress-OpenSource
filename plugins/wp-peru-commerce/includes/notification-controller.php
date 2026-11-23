<?php
/** IMP-47 — REST R8-39/40, session+nonce por el enrutador existente. */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Notification_Controller
{
    private static bool $gates=false;
    private static bool $routes=false;

    public static function registerGates():void
    {
        if(self::$gates){return;}
        self::$gates=true;
        Module_Gates::registerOperation('notifications','read',
            static fn():bool => true);
        Module_Gates::registerOperation('notifications','write',
            static fn():bool => true);
    }

    public static function register():void
    {
        if(self::$routes){return;}
        self::$routes=true;
        Bootstrap::loadNotifications();
        Rest_Router::bind('R8-39',
            static fn(array $ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,'body'=>Notification_Service::listing($ctx['query'])
            ],
            [Notification_Service::class,'guardList']);
        Rest_Router::bind('R8-40',
            static fn(array $ctx,\WP_REST_Request $_request):array=>[
                'status'=>200,'body'=>Notification_Service::resend(
                    $ctx['path']['id'],$ctx['body']
                )
            ],
            [Notification_Service::class,'guardResend']);
    }
}
