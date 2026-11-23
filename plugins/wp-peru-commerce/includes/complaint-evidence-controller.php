<?php
/**
 * IMP-49 — R8-67: lectura binaria de evidencia legal, solo A.
 * Ruta especial porque Rest_Router entrega únicamente JSON. Ni URL
 * permanente, ni upload público, ni trazas/response con storage_key.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Complaint_Evidence_Controller
{
    private static bool $registered=false;
    /** @var \WeakMap<\WP_REST_Request,array<string,mixed>>|null */
    private static ?\WeakMap $prepared=null;

    public static function register():void
    {
        if(self::$registered){return;}
        self::$registered=true;
        Bootstrap::loadComplaintAdmin();
        Access_Control::registerGuard('R8-67',[self::class,'guard']);
        if(!Module_Gates::allows('complaints','evidence')){return;}
        Access_Control::requireRoute(
            'R8-67','GET','/admin/complaints/{id}/evidence/{evidence_ref}'
        );
        if(true!==\register_rest_route(
            Rest_Contract::NAMESPACE,
            '/admin/complaints/(?P<id>[1-9][0-9]*)/evidence/'
                .'(?P<evidence_ref>[A-Za-z0-9_-]{16,128})',
            [
                'methods'=>'GET',
                'permission_callback'=>[self::class,'authorize'],
                'callback'=>[self::class,'handle'],
            ],false
        )){
            throw new \LogicException('pc_complaint_evidence_route_invalid');
        }
        \add_filter('rest_pre_serve_request',
            [self::class,'streamPrepared'],10,4);
    }

    public static function guard(array $ctx,?string $role):array
    {
        if(!Complaint_Admin::staffReady($role)
            || !Module_Gates::allows('complaints','evidence')){
            return [];
        }
        try{
            [$id,$ref,$purpose]=self::params($ctx);
            $entry=Transaction::run(
                static fn(Transaction_Context $tx):array=>
                    Complaint_Evidence::entryWithin($tx,$id,$ref)
            );
            Complaint_Evidence::verifiedPath($entry);
            return [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,'purpose_checked'=>true,
                'evidence_manifest_verified'=>true,
                'private_storage_verified'=>true,
            ];
        }catch(\Throwable $ignored){return [];}
    }

    public static function authorize(\WP_REST_Request $request):true|\WP_Error
    {
        try{
            if(!Module_Gates::allows('complaints','evidence')){
                throw new Transaction_Failure('pc_complaint_not_ready');
            }
            Rest_Throttle::check('R8-67',$request);
            Access_Control::require('R8-67',self::context($request),
                $request->get_header('X-WP-Nonce'));
            return true;
        }catch(\Throwable $error){
            return Rest_Errors::fromThrowable('R8-67',$error);
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
            Access_Control::require('R8-67',$ctx,
                $request->get_header('X-WP-Nonce'));
            [$id,$ref,$purpose]=self::params($ctx);
            $entry=Transaction::run(
                static fn(Transaction_Context $tx):array=>
                    Complaint_Evidence::entryWithin($tx,$id,$ref)
            );
            $path=Complaint_Evidence::verifiedPath($entry);
            // La bitácora acredita la LECTURA AUTORIZADA preparada, no
            // asegura que un navegador descargó todos los bytes.
            Transaction::run(static function(Transaction_Context $tx)
                use($id,$ref,$purpose):void {
                Complaint_Admin::auditReadWithin(
                    $tx,$id,'complaint_evidence_access_prepared',
                    'legal_evidence_review',
                    \hash('sha256',$purpose."\0".$ref)
                );
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
            return Rest_Errors::fromThrowable('R8-67',$error);
        }
    }

    /** Never route JSON body through a binary-success response. */
    public static function streamPrepared(
        bool $served,\WP_HTTP_Response $response,
        \WP_REST_Request $request,\WP_REST_Server $_server
    ):bool{
        if($served||null===self::$prepared
            || !isset(self::$prepared[$request])
            || 200!==$response->get_status()){
            return $served;
        }
        $prepared=self::$prepared[$request];
        unset(self::$prepared[$request]);
        $file=false;
        try{
            $ctx=self::context($request);
            Access_Control::require('R8-67',$ctx,
                $request->get_header('X-WP-Nonce'));
            [$id,$ref,$purpose]=self::params($ctx);
            if($id!==$prepared['id'] || $ref!==$prepared['ref']
                || $purpose!==$prepared['purpose']){
                throw new Transaction_Failure('pc_complaint_denied');
            }
            $entry=Transaction::run(
                static fn(Transaction_Context $tx):array=>
                    Complaint_Evidence::entryWithin($tx,$id,$ref)
            );
            $path=Complaint_Evidence::verifiedPath($entry);
            if($path!==$prepared['path']
                || $entry['byte_size']!==$prepared['size']
                || $entry['filename']!==$prepared['filename']){
                throw new Transaction_Failure('pc_complaint_manifest_invalid');
            }
            $file=@\fopen($path,'rb');
            if(false===$file){throw new \RuntimeException();}
            $stat=\fstat($file);
            if(!\is_array($stat)
                || (string)$stat['size']!==$prepared['size']
                || \headers_sent()){
                throw new \RuntimeException();
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
        $name=$prepared['filename'];
        \status_header(200);
        \header('Content-Type: application/octet-stream');
        \header('Content-Disposition: attachment; filename="'.$name.'"');
        \header('Content-Length: '.$prepared['size']);
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

    private static function context(\WP_REST_Request $request):array
    {
        if('GET'!==$request->get_method()
            || ''!==$request->get_body()){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return Rest_Validator::request($request,'R8-67');
    }

    /** @return array{string,string,string} */
    private static function params(array $ctx):array
    {
        $id=Domain_Validation::sqlId((string)($ctx['path']['id']??''));
        $ref=$ctx['path']['evidence_ref']??null;
        $purpose=$ctx['query']['purpose']??null;
        if(!\is_string($ref)
            || !\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D',$ref)
            || !\is_string($purpose)
            || !\preg_match('/\A[a-z][a-z0-9_]{2,99}\z/D',$purpose)
            || !\in_array($purpose,[
                'legal_case_review','legal_response_evidence',
                'legal_case_audit',
            ],true)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return [$id,$ref,$purpose];
    }
}
