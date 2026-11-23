<?php
/** IMP-49 — R8-41/42/65, lector administrativo sin UI WP adicional. */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Complaint_Admin_Controller
{
    private static bool $gates=false;
    private static bool $routes=false;

    public static function registerGates():void
    {
        if(self::$gates){return;}
        self::$gates=true;
        Module_Gates::registerOperation('complaints','read',
            static fn():bool=>true);
        Module_Gates::registerOperation('complaints','respond',
            static fn():bool=>true);
        Module_Gates::registerOperation('complaints','evidence',
            static fn():bool=>true);
    }

    public static function register():void
    {
        if(self::$routes){return;}
        self::$routes=true;
        Bootstrap::loadComplaintAdmin();
        Rest_Router::bind('R8-41',
            static fn(array $ctx,\WP_REST_Request $_req):array=>[
                'status'=>200,'body'=>Complaint_Admin::listing($ctx['query'])
            ],
            static fn(array $ctx,?string $role):array=>
                Complaint_Admin::guard('R8-41',$ctx,$role));
        Rest_Router::bind('R8-42',
            static fn(array $ctx,\WP_REST_Request $_req):array=>[
                'status'=>200,'body'=>Complaint_Admin::respond(
                    $ctx['path']['id'],$ctx['body']
                ),
            ],
            static fn(array $ctx,?string $role):array=>
                Complaint_Admin::guard('R8-42',$ctx,$role));
        Rest_Router::bind('R8-65',
            static fn(array $ctx,\WP_REST_Request $_req):array=>[
                'status'=>200,'body'=>Complaint_Admin::detail(
                    $ctx['path']['id']
                ),
            ],
            static fn(array $ctx,?string $role):array=>
                Complaint_Admin::guard('R8-65',$ctx,$role));
    }
}
