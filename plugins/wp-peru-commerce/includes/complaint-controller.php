<?php
/**
 * IMP-48 — R8-15 Libro nativo, sin cuenta ni pedido.
 * Guard público: cookie+CSRF propio, instalación y configuración legal.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Complaint_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;

    public static function registerGates():void
    {
        if(self::$gates){return;}
        self::$gates=true;
        Bootstrap::loadComplaints();
        Module_Gates::registerOperation(
            'complaints','write',
            static fn():bool=>Complaint_Legal::ready()
                && Complaint_Calendar::ready()
        );
    }

    public static function register():void
    {
        if(self::$registered){return;}
        self::$registered=true;
        Bootstrap::loadComplaints();
        Rest_Router::bind(
            'R8-15',
            static fn(array $ctx,\WP_REST_Request $_request):array=>
                Complaint_Service::create($ctx['body']),
            [self::class,'publicGuard']
        );
    }

    public static function publicGuard(array $_ctx,?string $role):array
    {
        if(null!==$role || !Module_Gates::allows('complaints','write')){
            return [];
        }
        try {
            Complaint_Form::verifiedDigest();
            return ['authorized'=>true,'same_site'=>true];
        }catch(\Throwable $ignored){
            return [];
        }
    }
}
