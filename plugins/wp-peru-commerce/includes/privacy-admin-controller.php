<?php
/** IMP-50/51 — R8-43/44/50/66; R8-68 usa controlador binario. */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Privacy_Admin_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    public static function registerGates():void
    {
        if(self::$gates){return;}
        self::$gates=true;
        Bootstrap::loadPrivacy();
        Module_Gates::registerOperation(
            'privacy','read',static fn():bool=>true
        );
        Module_Gates::registerOperation(
            'privacy','intake',static fn():bool=>true
        );
        Module_Gates::registerOperation(
            'privacy','incidents',static fn():bool=>true
        );
        Module_Gates::registerOperation(
            'privacy','verify',
            static fn():bool=>Privacy_Legal::identityReady()
        );
        Module_Gates::registerOperation(
            'privacy','resolve',
            static fn():bool=>Privacy_Legal::resolutionReady()
        );
        Module_Gates::registerOperation(
            'privacy','evidence',static fn():bool=>true
        );
        Module_Gates::registerOperation(
            'privacy','write',static fn():bool=>Privacy_Export::ready()
        );
    }

    public static function register():void
    {
        if(self::$registered){return;}
        self::$registered=true;
        Bootstrap::loadPrivacy();
        Rest_Router::bind(
            'R8-43',
            static fn(array $ctx,\WP_REST_Request $_req):array=>[
                'status'=>200,
                'body'=>Privacy_Admin::listing($ctx['query'])
            ],
            static fn(array $ctx,?string $role):array=>
                Privacy_Admin::guard('R8-43',$ctx,$role)
        );
        Rest_Router::bind(
            'R8-44',
            static fn(array $ctx,\WP_REST_Request $_req):array=>[
                'status'=>200,
                'body'=>Privacy_Admin::resolve(
                    $ctx['path']['id'],$ctx['body']
                )
            ],
            static fn(array $ctx,?string $role):array=>
                Privacy_Admin::guard('R8-44',$ctx,$role)
        );
        Rest_Router::bind(
            'R8-50',
            static fn(array $ctx,\WP_REST_Request $_req):array=>[
                'status'=>202,
                'body'=>Privacy_Export::accept($ctx['body'])
            ],
            static fn(array $ctx,?string $role):array=>
                Privacy_Export::guard($ctx,$role)
        );
        Rest_Router::bind(
            'R8-66',
            static fn(array $ctx,\WP_REST_Request $_req):array=>[
                'status'=>200,
                'body'=>Privacy_Admin::detail($ctx['path']['id'])
            ],
            static fn(array $ctx,?string $role):array=>
                Privacy_Admin::guard('R8-66',$ctx,$role)
        );
    }
}
