<?php
/**
 * IMP-31 — R8-08 POST /guest/sessions y R8-09 GET /guest/order.
 *
 * El guard nunca recibe IDs de pedido del navegador: R8-09 toma
 * pertenencia de cookie HttpOnly/Secure verificada en SQL y vuelve
 * a comprobar fuente, revocación y expiración en el mismo TX de lectura.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Guest_Controller
{
    private static bool $registered=false;
    private static bool $gatesRegistered=false;

    /** El expediente guest puede recibirse aunque se deshabilite
     * el checkout comercial; no es una mutación de stock ni dinero. */
    public static function registerGates(): void
    {
        if (self::$gatesRegistered) { return; }
        self::$gatesRegistered=true;
        Module_Gates::registerOperation(
            'guest','requests',static fn():bool=>true
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadGuest();
        Guest_Requests::registerVerifiers();
        Access_Control::registerIdentityVerifier(
            'guest',[Guest_Sessions::class,'authorize']
        );
        Rest_Router::bind(
            'R8-08',
            static fn(array $context,\WP_REST_Request $_request): array=>[
                'status'=>200,
                'body'=>Guest_Sessions::exchange($context['body']),
            ],
            static fn(array $context,?string $role): array =>
                self::guard('R8-08',$context,$role)
        );
        Rest_Router::bind(
            'R8-09',
            static fn(array $_context,\WP_REST_Request $_request): array=>[
                'status'=>200,
                'body'=>Guest_Order::detail(),
            ],
            static fn(array $context,?string $role): array =>
                self::guard('R8-09',$context,$role)
        );
        // OTP sólo bajo la misma sesión guest de IMP-31 y Origin
        // estricto. Estos endpoints no autorizan mutaciones de pedidos.
        Rest_Router::bind(
            'R8-10',
            static fn(array $context,\WP_REST_Request $_request): array=>[
                'status'=>202,
                'body'=>Guest_Challenges::request($context['body']),
            ],
            static fn(array $context,?string $role): array =>
                self::guard('R8-10',$context,$role)
        );
        Rest_Router::bind(
            'R8-11',
            static fn(array $context,\WP_REST_Request $_request): array=>[
                'status'=>200,
                'body'=>Guest_Challenges::verify($context['body']),
            ],
            static fn(array $context,?string $role): array =>
                self::guard('R8-11',$context,$role)
        );
        Rest_Router::bind(
            'R8-12',
            static fn(array $context,\WP_REST_Request $_request): array=>[
                'status'=>202,
                'body'=>Guest_Requests::submit($context['body']),
            ],
            static fn(array $context,?string $role): array =>
                self::guard('R8-12',$context,$role)
        );
    }

    /** Únicamente pruebas server-side, jamás flags suministrados por JS. */
    public static function guard(string $operationId,array $context,?string $role): array
    {
        if (null!==$role || (int)\get_current_blog_id()<1) {
            return [];
        }
        // El controlador REST ya conoce operación por el contexto de
        // Access_Control. Identificarla sin aceptar una propiedad de
        // autorización del request: R8-08 solo body de tracking_secret.
        if ('R8-08'===$operationId) {
            if (!Guest_Sessions::authorize('R8-08',$context)) { return []; }
            return [
                'authorized'=>true,'resource_checked'=>true,
                'state_checked'=>true,'field_checked'=>true,
            ];
        }
        if ('R8-12'===$operationId) {
            return Guest_Requests::guard($context['body']??[],$role);
        }
        if (!\in_array($operationId,['R8-09','R8-10','R8-11'],true)
            || !Guest_Sessions::authorize($operationId,$context)) {
            return [];
        }
        $proof=[
            'authorized'=>true,'resource_checked'=>true,
            'state_checked'=>true,'field_checked'=>true,
        ];
        if ('R8-09'===$operationId) {
            $proof['guest_order_ownership_verified']=true;
        }
        // R8-10/11 verifican sesión, no validan OTP en permission_callback.
        // La verificación real y el CAS se hacen dentro de sus handlers.
        return $proof;
    }
}
