<?php
/**
 * IMP-51 — R8-68: evidencia legal y exportación ARCO binaria.
 * Guard del mismo expediente, sitio, archivo y finalidad en cada lectura.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Privacy_Evidence_Controller
{
    private static bool $registered=false;
    /** @var \WeakMap<\WP_REST_Request,array<string,mixed>>|null */
    private static ?\WeakMap $prepared=null;

    public static function register():void
    {
        if(self::$registered){return;}
        self::$registered=true;
        Bootstrap::loadPrivacy();
        Access_Control::registerGuard('R8-68',[self::class,'guard']);
        if(!Module_Gates::allows('privacy','evidence')){return;}
        Access_Control::requireRoute('R8-68','GET',
            '/admin/privacy-requests/{id}/evidence/{evidence_ref}');
        if(true!==\register_rest_route(Rest_Contract::NAMESPACE,
            '/admin/privacy-requests/(?P<id>[1-9][0-9]*)/evidence/'
                .'(?P<evidence_ref>[A-Za-z0-9_-]{16,128})',
            ['methods'=>'GET',
                'permission_callback'=>[self::class,'authorize'],
                'callback'=>[self::class,'handle']],false)){
            throw new \LogicException('pc_privacy_evidence_route_invalid');
        }
        \add_filter('rest_pre_serve_request',
            [self::class,'streamPrepared'],10,4);
    }

    public static function guard(array $ctx,?string $role):array
    {
        if('A'!==$role || !Privacy_Admin::staffReady()
            || !Module_Gates::allows('privacy','evidence')){return [];}
        try{
            [$id,$ref,$purpose]=self::params($ctx);
            $entry=Transaction::run(
                static function(Transaction_Context $tx)use(
                    $id,$ref,$purpose
                ):array{
                    $e=Privacy_Evidence::entryWithin($tx,$id,$ref);
                    self::checkWithin($tx,$id,$ref,$purpose,$e);
                    return $e;
                }
            );
            Privacy_Evidence::verifiedPath($entry);
            return [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,'purpose_checked'=>true,
                'legal_case_verified'=>true,
                'evidence_manifest_verified'=>true,
                'private_storage_verified'=>true,
            ];
        }catch(\Throwable $ignored){return [];}
    }

    public static function authorize(\WP_REST_Request $request):true|\WP_Error
    {
        try{
            if(!Module_Gates::allows('privacy','evidence')){
                throw new Transaction_Failure('pc_privacy_not_ready');
            }
            Rest_Throttle::check('R8-68',$request);
            Access_Control::require('R8-68',self::context($request),
                $request->get_header('X-WP-Nonce'));
            return true;
        }catch(\Throwable $error){
            return Rest_Errors::fromThrowable('R8-68',$error);
        }
    }

    public static function handle(
        \WP_REST_Request $request
    ):\WP_REST_Response|\WP_Error {
        try{
            if(null!==$request->get_header('range')){
                throw new Rest_Failure('pc_field_invalid',422);
            }
            $ctx=self::context($request);
            Access_Control::require('R8-68',$ctx,
                $request->get_header('X-WP-Nonce'));
            [$id,$ref,$purpose]=self::params($ctx);
            $entry=Transaction::run(static function(Transaction_Context $tx)
                use($id,$ref,$purpose):array{
                $e=Privacy_Evidence::entryWithin($tx,$id,$ref);
                self::checkWithin($tx,$id,$ref,$purpose,$e);
                return $e;
            });
            $path=Privacy_Evidence::verifiedPath($entry);
            Transaction::run(static function(Transaction_Context $tx)
                use($id,$ref,$purpose):void{
                $e=Privacy_Evidence::entryWithin($tx,$id,$ref);
                self::checkWithin($tx,$id,$ref,$purpose,$e);
                Privacy_Admin::auditReadWithin($tx,$id,
                    'privacy_evidence_access_prepared',
                    'legal_evidence_review',
                    \hash('sha256',$purpose."\0".$ref));
            });
            self::$prepared??=new \WeakMap();
            self::$prepared[$request]=[
                'id'=>$id,'ref'=>$ref,'purpose'=>$purpose,
                'path'=>$path,'size'=>$entry['byte_size'],
                'filename'=>$entry['filename'],
            ];
            $response=new \WP_REST_Response(['stream_pending'=>true],200);
            $response->header('Cache-Control','private, no-store');
            return $response;
        }catch(\Throwable $error){
            return Rest_Errors::fromThrowable('R8-68',$error);
        }
    }

    public static function streamPrepared(
        bool $served,\WP_HTTP_Response $response,
        \WP_REST_Request $request,\WP_REST_Server $_server
    ):bool {
        if($served || null===self::$prepared
            || !isset(self::$prepared[$request])
            || 200!==$response->get_status()){
            return $served;
        }
        $saved=self::$prepared[$request];
        unset(self::$prepared[$request]);
        $file=false;
        try{
            $ctx=self::context($request);
            Access_Control::require('R8-68',$ctx,
                $request->get_header('X-WP-Nonce'));
            [$id,$ref,$purpose]=self::params($ctx);
            if($saved['id']!==$id || $saved['ref']!==$ref
                || $saved['purpose']!==$purpose){
                throw new Transaction_Failure('pc_privacy_denied');
            }
            $entry=Transaction::run(static function(Transaction_Context $tx)
                use($id,$ref,$purpose):array{
                $e=Privacy_Evidence::entryWithin($tx,$id,$ref);
                self::checkWithin($tx,$id,$ref,$purpose,$e);
                return $e;
            });
            $path=Privacy_Evidence::verifiedPath($entry);
            if($path!==$saved['path']
                || $entry['byte_size']!==$saved['size']
                || $entry['filename']!==$saved['filename']){
                throw new Transaction_Failure('pc_privacy_manifest_invalid');
            }
            $file=@\fopen($path,'rb');
            if(false===$file){throw new \RuntimeException();}
            $stat=\fstat($file);
            if(!\is_array($stat) || (string)$stat['size']!==$saved['size']
                || \headers_sent()){throw new \RuntimeException();}
            // Revisión final de caducidad, incluso si venció desde el guard.
            if('export'===$entry['purpose']
                && Business_Time::fromApi($entry['expires_at_utc'])
                    <=Business_Time::nowUtc()){
                throw new Transaction_Failure('pc_privacy_export_expired');
            }
        }catch(\Throwable $ignored){
            if(false!==$file){\fclose($file);}
            \status_header(503);
            \header('Cache-Control: private, no-store');
            \header('X-Content-Type-Options: nosniff');
            \header('Content-Type: application/json; charset=utf-8');
            echo '{"code":"pc_dependency_unavailable"}';
            return true;
        }
        \status_header(200);
        \header('Content-Type: application/octet-stream');
        \header('Content-Disposition: attachment; filename="'.$saved['filename'].'"');
        \header('Content-Length: '.$saved['size']);
        \header('Cache-Control: private, no-store, max-age=0');
        \header('X-Content-Type-Options: nosniff');
        \header('Referrer-Policy: no-referrer');
        \header('X-Robots-Tag: noindex, noarchive');
        \header('Accept-Ranges: none');
        \header('X-Frame-Options: DENY');
        \header('Content-Security-Policy: sandbox');
        while(!\feof($file)){
            $chunk=\fread($file,65536);
            if(!\is_string($chunk)||''===$chunk){break;}
            echo $chunk;
            \flush();
        }
        \fclose($file);
        return true;
    }

    private static function checkWithin(
        Transaction_Context $tx,string $id,string $ref,
        string $purpose,array $entry
    ):void {
        if('export'===$entry['purpose']){
            if($purpose!=='privacy_export_download'
                || !Privacy_Export::exportValidWithin($tx,$id,$ref)){
                throw new Transaction_Failure('pc_privacy_export_expired');
            }
        }else{
            $permitted=match($purpose){
                'privacy_identity_review'=>['identity','representation'],
                'privacy_resolution_review'=>['resolution','delivery'],
                'privacy_case_audit'=>[
                    'identity','representation','resolution','delivery'
                ],
                default=>[],
            };
            if(!\in_array($entry['purpose'],$permitted,true)){
                throw new Transaction_Failure('pc_privacy_denied');
            }
        }
    }

    private static function context(\WP_REST_Request $request):array
    {
        if('GET'!==$request->get_method()
            || ''!==$request->get_body()){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return Rest_Validator::request($request,'R8-68');
    }

    private static function params(array $ctx):array
    {
        $id=Domain_Validation::sqlId((string)($ctx['path']['id']??''));
        $ref=$ctx['path']['evidence_ref']??null;
        $purpose=$ctx['query']['purpose']??null;
        if(!\is_string($ref)
            || !\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D',$ref)
            || !\is_string($purpose)
            || !\in_array($purpose,[
                'privacy_export_download','privacy_identity_review',
                'privacy_resolution_review','privacy_case_audit'
            ],true)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return [$id,$ref,$purpose];
    }
}
