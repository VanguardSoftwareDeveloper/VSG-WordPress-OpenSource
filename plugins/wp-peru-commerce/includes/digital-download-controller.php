<?php
/**
 * IMP-35 — rutas R8-13 y R8-14 y streaming binario privado.
 * R8-14 es ruta especial: Rest_Router no serializa bytes como JSON.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Download_Controller
{
    private static bool $registered=false;
    /** @var \WeakMap<\WP_REST_Request,array>|null */
    private static ?\WeakMap $prepared=null;

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadDigitalAccess();
        Event_Actor::registerDigitalVerifier([Digital_Download::class,'verifiesActor']);
        Digital_Rights::registerTransferVerifier(
            [Digital_Download::class,'verifiesTransferWithin']
        );
        Access_Control::registerIdentityVerifier(
            'digital_token',
            static fn(string $op,array $ctx):bool =>
                Digital_Download::authorize($op,$ctx)
        );
        Access_Control::registerIdentityVerifier(
            'digital_session',
            static fn(string $op,array $ctx):bool =>
                Digital_Download::authorize($op,$ctx)
        );
        Rest_Router::bind(
            'R8-13',
            static fn(array $ctx,\WP_REST_Request $_request):array => [
                'status'=>200,'body'=>Digital_Download::exchange($ctx['body'])
            ],
            [self::class,'guardToken']
        );
        Access_Control::registerGuard('R8-14',[self::class,'guardStream']);
        if (!Module_Gates::allows('digital','read')) { return; }
        Access_Control::requireRoute(
            'R8-14','GET','/guest/digital/{right_id}/file'
        );
        if (true!==\register_rest_route(
            Rest_Contract::NAMESPACE,
            '/guest/digital/(?P<right_id>[1-9][0-9]*)/file',
            [
                'methods'=>'GET',
                'permission_callback'=>[self::class,'authorizeStream'],
                'callback'=>[self::class,'handleStream'],
            ],false
        )) {
            throw new \LogicException('pc_digital_route_failed');
        }
        \add_filter('rest_pre_serve_request',[self::class,'streamPrepared'],10,4);
    }

    public static function guardToken(array $ctx,?string $role): array
    {
        if (null!==$role || (int)\get_current_blog_id()<1
            || !Digital_Download::authorize('R8-13',$ctx)) {
            return [];
        }
        return [
            'authorized'=>true,'resource_checked'=>true,
            'state_checked'=>true,'field_checked'=>true,
        ];
    }

    public static function guardStream(array $ctx,?string $role): array
    {
        if (null!==$role || (int)\get_current_blog_id()<1
            || !Module_Gates::allows('digital','read')
            || !Digital_Download::authorize('R8-14',$ctx)) {
            return [];
        }
        // private_storage_verified: procede del gate P-C/almacenamiento
        // comprobado, NO de una opción enviada por el navegador.
        return [
            'authorized'=>true,'resource_checked'=>true,
            'state_checked'=>true,'field_checked'=>true,
            'digital_right_verified'=>true,'private_storage_verified'=>true,
        ];
    }

    public static function authorizeStream(
        \WP_REST_Request $request
    ): true|\WP_Error {
        try {
            if (!Module_Gates::allows('digital','read')) {
                throw new Rest_Failure('pc_module_not_ready',423);
            }
            if (null!==$request->get_header('range')) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
            Rest_Throttle::check('R8-14',$request);
            $context=Rest_Validator::request($request,'R8-14');
            Access_Control::require('R8-14',$context);
            return true;
        } catch (\Throwable $error) {
            return Rest_Errors::fromThrowable('R8-14',$error);
        }
    }

    public static function handleStream(
        \WP_REST_Request $request
    ): \WP_REST_Response|\WP_Error {
        try {
            if (!Module_Gates::allows('digital','read')) {
                throw new Rest_Failure('pc_module_not_ready',423);
            }
            if (null!==$request->get_header('range')) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
            $context=Rest_Validator::request($request,'R8-14');
            Access_Control::require('R8-14',$context);
            $prepared=Digital_Download::prepare($context['path']['right_id']);
            self::$prepared??=new \WeakMap();
            self::$prepared[$request]=$prepared;
            $response=new \WP_REST_Response(['stream_pending'=>true],200);
            $response->header('Cache-Control','private, no-store');
            $response->header('Referrer-Policy','no-referrer');
            return $response;
        } catch (\Throwable $error) {
            return Rest_Errors::fromThrowable('R8-14',$error);
        }
    }

    /** La cuota ya quedó confirmada antes de entrar a este hook. */
    public static function streamPrepared(
        bool $served,\WP_HTTP_Response $response,
        \WP_REST_Request $request,\WP_REST_Server $_server
    ): bool {
        if (null===self::$prepared || !isset(self::$prepared[$request])) {
            return $served;
        }
        $prepared=self::$prepared[$request];
        unset(self::$prepared[$request]);
        $handle=$prepared['handle'];
        if ($served || 200!==$response->get_status() || !\is_resource($handle)) {
            if (\is_resource($handle)) { \fclose($handle); }
            return $served;
        }
        $name=\preg_replace('/[^A-Za-z0-9._-]/','_',$prepared['filename']);
        if (!\is_string($name) || ''===$name
            || '.'===$name || '..'===$name) { $name='archivo.bin'; }
        \status_header(200);
        \header('Content-Type: application/octet-stream');
        \header('Content-Disposition: attachment; filename="'.$name.'"');
        \header('Content-Length: '.$prepared['size']);
        \header('Cache-Control: private, no-store, max-age=0');
        \header('Pragma: no-cache');
        \header('X-Content-Type-Options: nosniff');
        \header('Referrer-Policy: no-referrer');
        \header('X-Robots-Tag: noindex, nofollow');
        \header('Accept-Ranges: none');
        try {
            while (!\feof($handle)) {
                $chunk=\fread($handle,65536);
                if (!\is_string($chunk) || ''===$chunk) { break; }
                echo $chunk;
                \flush();
            }
        } finally {
            \fclose($handle);
        }
        return true;
    }
}
