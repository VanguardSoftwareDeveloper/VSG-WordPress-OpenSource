<?php
/**
 * IMP-51 — R8-50: exportación ARCO acotada a un expediente verificado.
 * No es PER-09, no sirve ficheros públicos ni infiere identidad del texto
 * verified_identity. La custodia y los verificadores P-C son obligatorios.
 */
declare(strict_types=1);
namespace PeruCommerce;
if (!\defined('ABSPATH')) { exit; }

final class Privacy_Export
{
    private const OP='privacy_export';
    private const WORKER='privacy_export_worker';
    private const HOOK='perucommerce_privacy_export_tick';
    private const MAX_BYTES=2097152;
    private static $scopeVerifier=null;
    private static $dataProvider=null;
    private static $dataVerifier=null;
    private static bool $hooks=false;
    private static bool $verifiers=false;
    private static ?array $active=null;

    /**
     * Integración P-C: verifica vínculo titular/expediente/pedido sobre datos
     * server-side. No basta la declaración "verified_identity" del POST.
     */
    public static function registerScopeVerifier(callable $check):void
    {
        if(null!==self::$scopeVerifier){throw new \LogicException('pc_export_scope_duplicate');}
        self::$scopeVerifier=$check;
    }

    /**
     * Constructor de datos del titular: fuente verificada, nunca búsqueda
     * comercial por coincidencia de email. Debe devolver records acotados.
     */
    public static function registerDataProvider(callable $provider):void
    {
        if(null!==self::$dataProvider){throw new \LogicException('pc_export_provider_duplicate');}
        self::$dataProvider=$provider;
    }

    /** Verificación independiente de pertenencia de cada registro exportado. */
    public static function registerDataVerifier(callable $check):void
    {
        if(null!==self::$dataVerifier){throw new \LogicException('pc_export_data_duplicate');}
        self::$dataVerifier=$check;
    }

    public static function ready():bool
    {
        return null!==self::$scopeVerifier && null!==self::$dataProvider
            && null!==self::$dataVerifier && Privacy_Legal::identityReady();
    }

    public static function registerHooks():void
    {
        if(self::$hooks){return;}
        self::$hooks=true;
        \add_action(self::HOOK,[self::class,'cronTick'],10,2);
    }

    public static function registerVerifiers():void
    {
        if(self::$verifiers){return;}
        self::$verifiers=true;
        Event_Actor::registerPrivacyExportVerifier(
            static fn(string $ref,string $purpose):bool=>
                self::workerVerified($ref,$purpose)
        );
        Idempotency::registerPrivacyExportScopeVerifier(
            static fn(array $scope):bool=>
                'system'===($scope['actor_kind']??null)
                && 'privacy_export'===($scope['resource_kind']??null)
                && self::workerVerified(
                    (string)($scope['actor_ref']??''),
                    (string)($scope['purpose']??'')
                )
                && ($scope['resource_id']??null)===(self::$active['id']??null)
        );
    }

    private static function workerVerified(string $ref,string $purpose):bool
    {
        return 'privacy_export_worker'===$purpose
            && null!==self::$active
            && ($ref===(self::$active['digest']??null))
            && (string)\get_current_blog_id()===(self::$active['site_id']??null);
    }

    public static function guard(array $ctx,?string $role):array
    {
        if('A'!==$role || !Privacy_Admin::staffReady()
            || !Module_Gates::allows('privacy','write')){return [];}
        try{
            $body=$ctx['body']??[];
            self::validate($body);
            $e=self::identity($body['privacy_request_id']);
            self::scope($e,$body['scope'],$body['order_id']??null,
                $body['verified_identity']);
            return [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,'purpose_checked'=>true,
                'legal_case_verified'=>true,
                'subject_legitimacy_verified'=>true,
            ];
        }catch(\Throwable $ignored){return [];}
    }

    public static function accept(array $body):array
    {
        if(!Privacy_Admin::staffReady() || !self::ready()
            || !Module_Gates::allows('privacy','write')
            || !Module_Gates::allows('privacy','evidence')){
            throw new Transaction_Failure('pc_privacy_not_ready');
        }
        self::validate($body);
        $id=$body['privacy_request_id'];
        $preview=self::identity($id);
        self::scope($preview,$body['scope'],$body['order_id']??null,
            $body['verified_identity']);
        $proofDigest=\hash('sha256',Privacy_Intake::encode(
            $preview['proof'],12000
        ));
        $actor=Event_Actor::staff(['perucommerce_privacy_manage']);
        $scope=[
            'actor_kind'=>'staff','actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'privacy_request','resource_id'=>$id,
            'purpose'=>self::OP,
        ];
        $payload=$body;unset($payload['idempotency_key']);
        $hash=['request_sha256'=>\hash('sha256',
            Privacy_Intake::encode($payload,16000))];
        $ack=Transaction::run(static function(Transaction_Context $tx)
            use($body,$id,$actor,$scope,$hash,$proofDigest):array {
            $claim=Idempotency::claimWithin($tx,$scope,self::OP,
                $body['idempotency_key'],$hash);
            if(!\in_array($claim['status'],['new','replay'],true)){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $row=Privacy_Admin::rowWithin($tx,$id,true);
            if(null===$row){throw new Transaction_Failure('pc_privacy_not_found');}
            $result=Privacy_Admin::resultDocument($row['result_reference']);
            if('replay'===$claim['status']){
                if(($claim['entity_type']??null)!=='privacy_request'
                    || ($claim['entity_id']??null)!==$id
                    || ($claim['response_code']??null)!==202){
                    throw new Transaction_Failure('pc_privacy_conflict');
                }
                $op=self::operation($result,$claim['operation_id']);
                return self::ack($id,$op);
            }
            if((string)$row['row_version']!==$body['expected_version']
                || null===$row['verified_at']
                || !\is_string($row['identity_evidence_ref'])
                || \count($result['export_operations'])>=50
                || \count(Privacy_Evidence::manifestWithin($tx,$id))>=20){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $proof=Privacy_Evidence::entryWithin(
                $tx,$id,$row['identity_evidence_ref']
            );
            if(!\hash_equals($proofDigest,
                \hash('sha256',Privacy_Intake::encode($proof,12000)))){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $source=Privacy_Admin::decode($row['applicant_reference'],24000);
            $request=Privacy_Admin::decode($row['request_snapshot'],48000);
            Privacy_Legal::requireIdentity([
                'applicant_reference'=>$source,'request_snapshot'=>$request
            ],$proof);
            self::scope([
                'id'=>$id,'row'=>$row,'source'=>$source,
                'request'=>$request,'proof'=>$proof
            ],$body['scope'],$body['order_id']??null,
                $body['verified_identity']);
            $date=$tx->rows('SELECT UTC_TIMESTAMP(6) AS created_at,'
                .'DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 1 DAY) AS expires_at');
            if(1!==\count($date)){throw new Transaction_Failure('pc_privacy_conflict');}
            $created=Business_Time::toApi(
                Business_Time::fromDatabase($date[0]['created_at']));
            $expires=Business_Time::toApi(
                Business_Time::fromDatabase($date[0]['expires_at']));
            foreach($result['export_operations'] as $existing){
                if(($existing['phase']??null)==='processing'
                    || ($existing['phase']??null)==='pending'){
                    throw new Transaction_Failure('pc_privacy_conflict');
                }
            }
            $op=[
                'operation_id'=>$claim['operation_id'],
                'phase'=>'pending','scope'=>$body['scope'],
                'order_id'=>$body['order_id']??null,
                'identity_ref'=>$proof['ref'],
                'requested_by'=>(string)\get_current_user_id(),
                'created_at_utc'=>$created,'expires_at_utc'=>$expires,
                'reason_digest'=>\hash('sha256',$body['reason']),
                'declaration_digest'=>\hash('sha256',$body['verified_identity']),
                'evidence_ref'=>null,'failure_code'=>null,
                'lease_until_utc'=>null,'lease_digest'=>null,
            ];
            $result['export_operations'][]=$op;
            if(1!==$tx->execute('UPDATE %i SET result_reference=%s,'
                .'row_version=row_version+1 WHERE id=%s AND row_version=%s',
                [Privacy_Admin::table(),Privacy_Intake::encode($result,85000),
                    $id,$body['expected_version']])){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            Event_Journal::auditWithin($tx,$actor,$claim['operation_id'],
                'privacy_request',$id,'privacy_export_requested',
                'verified_subject_export',
                \hash('sha256',$body['expected_version']),
                \hash('sha256',$body['scope'].':'.($body['order_id']??'0')));
            Queue_Outbox::privacyEventWithin($tx,$actor,
                $claim['operation_id'],$id,'privacy_export_requested');
            Idempotency::completeLocalWithin($tx,$claim['operation_id'],
                'privacy_request',$id,202);
            return self::ack($id,$op);
        },true);
        self::schedule($id,$ack['operation_id'],$ack['expires_at_utc']);
        return $ack;
    }

    private static function schedule(string $id,string $op,string $expires):void
    {
        // El cron no concede acceso. Si WP-Cron está deshabilitado, la
        // operación queda visible como pending, nunca como ready ficticio.
        foreach([60,600,1800] as $delay){
            \wp_schedule_single_event(\time()+$delay,self::HOOK,[$id,$op],true);
        }
        $ttl=Business_Time::fromApi($expires)->getTimestamp()-\time();
        \wp_schedule_single_event(\time()+\max(60,$ttl+60),
            self::HOOK,[$id,$op],true);
    }

    /** Tarea interna en segundo proceso; también recupera lease caducado. */
    public static function cronTick(string $id,string $op):void
    {
        if(!\function_exists('wp_doing_cron') || !\wp_doing_cron()){return;}
        try{
            Bootstrap::loadPrivacy();
            if(!Module_Gates::allows('privacy','write')
                || !Module_Gates::allows('privacy','evidence')){return;}
            $id=Domain_Validation::sqlId($id);
            $op=Domain_Validation::sqlId($op);
            self::$active=[
                'id'=>$id,'digest'=>self::workerDigest($id,$op),
                'site_id'=>(string)\get_current_blog_id(),
            ];
            $claimed=self::claim($id,$op);
            if(null===$claimed){return;}
            if(($claimed['expired']??false)===true){
                self::expire($id,$op);
                return;
            }
            $file=null;
            try{
                $proof=$claimed['proof'];
                Privacy_Evidence::verifiedPath($proof);
                Privacy_Legal::requireIdentity([
                    'applicant_reference'=>$claimed['source'],
                    'request_snapshot'=>$claimed['request']
                ],$proof);
                self::scope($claimed,$claimed['operation']['scope'],
                    $claimed['operation']['order_id'],null);
                $context=[
                    'privacy_request_id'=>$id,
                    'scope'=>$claimed['operation']['scope'],
                    'order_id'=>$claimed['operation']['order_id'],
                    'identity_evidence_ref'=>$proof['ref'],
                    'source'=>$claimed['source'],
                    'request'=>$claimed['request'],
                ];
                $records=self::provide($context);
                $date=Business_Time::toApi(Business_Time::nowUtc());
                $json=Privacy_Intake::encode([
                    'format'=>'perucommerce-privacy-export-v1',
                    'privacy_request_id'=>$id,
                    'scope'=>$context['scope'],
                    'order_id'=>$context['order_id'],
                    'generated_at_utc'=>$date,
                    'records'=>$records['records'],
                ],self::MAX_BYTES);
                $file=self::storeJson($json);
                self::finish($id,$op,$claimed,$file);
            }catch(\Throwable $ignored){
                // No incluir identidad, cuerpo, excepción ni rutas en logs.
                try{self::fail($id,$op,$claimed);}catch(\Throwable $ignored){}
            }
        }catch(\Throwable $ignored){
            // Estado durable, sin dar por terminada una ejecución incierta.
        }finally{
            self::$active=null;
        }
    }

    /** Devuelve solo operaciones vinculadas al sitio y la misma solicitud. */
    private static function claim(string $id,string $op):?array
    {
        return Transaction::run(static function(Transaction_Context $tx)
            use($id,$op):?array {
            $row=Privacy_Admin::rowWithin($tx,$id,true);
            if(null===$row){return null;}
            $result=Privacy_Admin::resultDocument($row['result_reference']);
            $idx=self::index($result,$op);
            if(null===$idx){return null;}
            $entry=$result['export_operations'][$idx];
            $now=Business_Time::nowUtc();
            if(Business_Time::fromApi($entry['expires_at_utc'])<=$now){
                return ['expired'=>true];
            }
            if(!\in_array($entry['phase'],['pending','processing'],true)){
                return null;
            }
            if($entry['phase']==='processing'
                && \is_string($entry['lease_until_utc'])
                && Business_Time::fromApi($entry['lease_until_utc'])>$now){
                return null;
            }
            if(null===$row['verified_at']
                || $row['identity_evidence_ref']!==$entry['identity_ref']){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $proof=Privacy_Evidence::entryWithin($tx,$id,$entry['identity_ref']);
            $lease=\bin2hex(\random_bytes(32));
            $entry['phase']='processing';
            $entry['lease_digest']=\hash('sha256',$lease);
            $entry['lease_until_utc']=Business_Time::toApi($now->modify('+5 minutes'));
            $result['export_operations'][$idx]=$entry;
            if(1!==$tx->execute('UPDATE %i SET result_reference=%s,'
                .'row_version=row_version+1 WHERE id=%s AND row_version=%s',
                [Privacy_Admin::table(),Privacy_Intake::encode($result,85000),
                $id,$row['row_version']])){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            return [
                'expired'=>false,'id'=>$id,'operation'=>$entry,'lease'=>$lease,
                'source'=>Privacy_Admin::decode($row['applicant_reference'],24000),
                'request'=>Privacy_Admin::decode($row['request_snapshot'],48000),
                'proof'=>$proof,
            ];
        },true);
    }

    private static function finish(
        string $id,string $op,array $claimed,array $file
    ):void {
        Transaction::run(static function(Transaction_Context $tx)
            use($id,$op,$claimed,$file):void {
            $row=Privacy_Admin::rowWithin($tx,$id,true);
            if(null===$row){throw new Transaction_Failure('pc_privacy_conflict');}
            $result=Privacy_Admin::resultDocument($row['result_reference']);
            $idx=self::index($result,$op);
            if(null===$idx){throw new Transaction_Failure('pc_privacy_conflict');}
            $entry=$result['export_operations'][$idx];
            if($entry['phase']!=='processing'
                || !\hash_equals((string)$entry['lease_digest'],
                    \hash('sha256',$claimed['lease']))
                || Business_Time::fromApi($entry['expires_at_utc'])
                    <=Business_Time::nowUtc()
                || $row['identity_evidence_ref']!==$entry['identity_ref']){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $ref='pv_'.\substr(\hash('sha256',
                Privacy_Intake::siteDigest().':'.$op),0,48);
            $manifestEntry=[
                'ref'=>$ref,'privacy_request_id'=>$id,
                'site_id'=>(string)\get_current_blog_id(),
                'site_digest'=>Privacy_Intake::siteDigest(),
                'purpose'=>'export','source'=>'verified_export',
                'storage_key'=>$file['key'],'sha256'=>$file['sha256'],
                'byte_size'=>(string)$file['size'],'mime'=>$file['mime'],
                'filename'=>$file['filename'],
                'added_at_utc'=>Business_Time::toApi(Business_Time::nowUtc()),
                'delivery_channel'=>null,'delivered_at_utc'=>null,
                'resolution_hash'=>null,
                'export_operation_id'=>$op,
                'expires_at_utc'=>$entry['expires_at_utc'],
            ];
            $manifest=Privacy_Evidence::exportManifestWithin(
                $tx,$id,$manifestEntry
            );
            $entry['phase']='ready';$entry['evidence_ref']=$ref;
            $entry['lease_digest']=null;$entry['lease_until_utc']=null;
            $result['export_operations'][$idx]=$entry;
            if(1!==$tx->execute(
                'UPDATE %i SET evidence_manifest=%s,result_reference=%s,'
                    .'row_version=row_version+1 WHERE id=%s AND row_version=%s',
                [Privacy_Admin::table(),$manifest,
                    Privacy_Intake::encode($result,85000),
                    $id,$row['row_version']])){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            self::record($tx,$id,$op,'privacy_export_ready',
                \hash('sha256',$ref.':'.$file['sha256']));
        },true);
    }

    private static function fail(string $id,string $op,array $claimed):void
    {
        Transaction::run(static function(Transaction_Context $tx)
            use($id,$op,$claimed):void {
            $row=Privacy_Admin::rowWithin($tx,$id,true);
            if(null===$row){return;}
            $result=Privacy_Admin::resultDocument($row['result_reference']);
            $idx=self::index($result,$op);
            if(null===$idx){return;}
            $entry=$result['export_operations'][$idx];
            if('processing'!==$entry['phase']
                || !\hash_equals((string)$entry['lease_digest'],
                    \hash('sha256',$claimed['lease']))){return;}
            $entry['phase']='failed';$entry['failure_code']='pc_export_failed';
            $entry['lease_digest']=null;$entry['lease_until_utc']=null;
            $result['export_operations'][$idx]=$entry;
            if(1!==$tx->execute('UPDATE %i SET result_reference=%s,'
                    .'row_version=row_version+1 WHERE id=%s AND row_version=%s',
                [Privacy_Admin::table(),Privacy_Intake::encode($result,85000),
                    $id,$row['row_version']])){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            self::record($tx,$id,$op,'privacy_export_failed',
                \hash('sha256','failure:'.$op));
        },true);
    }

    private static function expire(string $id,string $op):void
    {
        $key=Transaction::run(static function(Transaction_Context $tx)
            use($id,$op):?array {
            $row=Privacy_Admin::rowWithin($tx,$id,true);
            if(null===$row){return null;}
            $result=Privacy_Admin::resultDocument($row['result_reference']);
            $idx=self::index($result,$op);
            if(null===$idx){return null;}
            $entry=$result['export_operations'][$idx];
            if(Business_Time::fromApi($entry['expires_at_utc'])
                >Business_Time::nowUtc()){return null;}
            $found=null;
            if(\is_string($entry['evidence_ref'])){
                $found=Privacy_Evidence::entryWithin(
                    $tx,$id,$entry['evidence_ref']
                );
                if($found['purpose']!=='export'
                    || $found['export_operation_id']!==$op){
                    throw new Transaction_Failure('pc_privacy_conflict');
                }
            }
            if($entry['phase']!=='expired'){
                $entry['phase']='expired';
                // Mantener ref solo en metadata interna para purga recuperable;
                // R8-66 oculta la referencia una vez vencida.
                $entry['lease_digest']=null;$entry['lease_until_utc']=null;
                $result['export_operations'][$idx]=$entry;
                if(1!==$tx->execute('UPDATE %i SET result_reference=%s,'
                        .'row_version=row_version+1 WHERE id=%s AND row_version=%s',
                    [Privacy_Admin::table(),Privacy_Intake::encode($result,85000),
                        $id,$row['row_version']])){
                    throw new Transaction_Failure('pc_privacy_conflict');
                }
                self::record($tx,$id,$op,'privacy_export_expired',
                    \hash('sha256','expired:'.$op));
            }
            return $found;
        },true);
        if(\is_array($key)){
            // Solo el blob efímero export; no borrar evidencias ARCO legales.
            try{
                require_once PERUCOMMERCE_PLUGIN_DIR.'includes/digital-private-storage.php';
                $path=Digital_Private_Storage::resolve($key['storage_key']);
                if(\is_file($path) && !\is_link($path)){@\unlink($path);}
            }catch(\Throwable $ignored){}
        }
    }

    /** Evento interno raíz propia por estado terminal; nunca reutilizar el POST 202. */
    private static function record(
        Transaction_Context $tx,string $id,string $sourceOp,
        string $code,string $digest
    ):void {
        $ref=self::workerDigest($id,$sourceOp);
        $actor=Event_Actor::system($ref,self::WORKER);
        $scope=[
            'actor_kind'=>'system','actor_ref'=>$ref,
            'resource_kind'=>'privacy_export','resource_id'=>$id,
            'purpose'=>self::WORKER,
        ];
        $claim=Idempotency::claimWithin($tx,$scope,
            self::WORKER,\hash('sha256',$sourceOp.':'.$code),
            ['source_operation'=>$sourceOp,'code'=>$code]);
        if('new'!==$claim['status']){
            throw new Transaction_Failure('pc_privacy_conflict');
        }
        Event_Journal::auditWithin($tx,$actor,$claim['operation_id'],
            'privacy_request',$id,$code,'private_subject_export',
            \hash('sha256',$sourceOp),$digest);
        Queue_Outbox::privacyEventWithin(
            $tx,$actor,$claim['operation_id'],$id,$code
        );
        Idempotency::completeLocalWithin($tx,$claim['operation_id'],
            'privacy_request',$id,200);
    }

    private static function provide(array $ctx):array
    {
        try{$data=(self::$dataProvider)($ctx);}
        catch(\Throwable $ignored){throw new Transaction_Failure('pc_export_failed');}
        if(!\is_array($data) || !\is_array($data['records']??null)
            || !\array_is_list($data['records'])
            || \count($data['records'])<1 || \count($data['records'])>100
            || ($ctx['scope']==='order_legal_copy'
                && ($data['order_id']??null)!==$ctx['order_id'])){
            throw new Transaction_Failure('pc_export_failed');
        }
        foreach($data['records'] as $rec){
            if(!\is_array($rec) || !\is_string($rec['source']??null)
                || !\preg_match('/\A[a-z][a-z0-9_]{2,39}\z/D',$rec['source'])
                || !\is_string($rec['record_id']??null)
                || !\preg_match('/\A[1-9][0-9]*\z/D',$rec['record_id'])
                || !\is_array($rec['data']??null)){
                throw new Transaction_Failure('pc_export_failed');
            }
            if($ctx['scope']==='order_legal_copy'
                && ($rec['source']!=='order'
                    || $rec['record_id']!==$ctx['order_id'])){
                throw new Transaction_Failure('pc_export_failed');
            }
        }
        try{
            if(true!==(self::$dataVerifier)([
                'context'=>$ctx,'records'=>$data['records']
            ])){throw new \UnexpectedValueException();}
        }catch(\Throwable $ignored){throw new Transaction_Failure('pc_export_failed');}
        return $data;
    }

    private static function storeJson(string $json):array
    {
        Transaction::assertOutside();
        $dir=Digital_Private_Storage::root();
        $temp=@\tempnam($dir,'.pc-export-');
        if(!\is_string($temp)){throw new Transaction_Failure('pc_export_failed');}
        try{
            if(!@\chmod($temp,0600)
                || \file_put_contents($temp,$json,\LOCK_EX)!==\strlen($json)){
                throw new Transaction_Failure('pc_export_failed');
            }
            $mime=(new \finfo(\FILEINFO_MIME_TYPE))->file($temp);
            if(!\in_array($mime,['application/json','text/plain'],true)){
                throw new Transaction_Failure('pc_export_failed');
            }
            return Digital_Private_Storage::store([
                'tmp_name'=>$temp,'size'=>\strlen($json),
                'sha256'=>\hash('sha256',$json),
                'mime'=>$mime,'filename'=>'privacy-export.json',
            ]);
        }finally{@\unlink($temp);}
    }

    private static function identity(string $id):array
    {
        $e=Transaction::run(static function(Transaction_Context $tx)
            use($id):array {
            $row=Privacy_Admin::rowWithin($tx,$id);
            if(null===$row || null===$row['verified_at']
                || !\is_string($row['identity_evidence_ref'])){
                throw new Transaction_Failure('pc_privacy_identity_unverified');
            }
            return [
                'id'=>$id,'row'=>$row,
                'source'=>Privacy_Admin::decode($row['applicant_reference'],24000),
                'request'=>Privacy_Admin::decode($row['request_snapshot'],48000),
                'proof'=>Privacy_Evidence::entryWithin(
                    $tx,$id,$row['identity_evidence_ref']),
            ];
        });
        if(!\in_array($e['proof']['purpose'],['identity','representation'],true)){
            throw new Transaction_Failure('pc_privacy_identity_unverified');
        }
        Privacy_Evidence::verifiedPath($e['proof']);
        Privacy_Legal::requireIdentity([
            'applicant_reference'=>$e['source'],
            'request_snapshot'=>$e['request'],
        ],$e['proof']);
        return $e;
    }

    private static function scope(
        array $case,string $scope,?string $orderId,?string $declaration
    ):void {
        if(!self::ready()){
            throw new Transaction_Failure('pc_privacy_not_ready');
        }
        try{
            if(true!==(self::$scopeVerifier)([
                'privacy_request_id'=>$case['id']??null,
                'source'=>$case['source'],
                'request'=>$case['request'],
                'identity_evidence'=>$case['proof'],
                'scope'=>$scope,'order_id'=>$orderId,
                'declared_identity'=>$declaration,
            ])){throw new \UnexpectedValueException();}
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_privacy_scope_unverified');
        }
    }

    private static function validate(array $body):void
    {
        if(!\is_array($body)
            || []!==\array_diff([
                'scope','reason','verified_identity','idempotency_key',
                'privacy_request_id','expected_version',
            ],\array_keys($body))
            || []!==\array_diff(\array_keys($body),[
                'scope','reason','verified_identity','idempotency_key',
                'privacy_request_id','expected_version','order_id'
            ])
            || !\in_array($body['scope']??null,[
                'subject_verified','order_legal_copy'
            ],true)
            || !\is_string($body['reason']??null)
            || !\is_string($body['verified_identity']??null)
            || !\is_string($body['idempotency_key']??null)
            || !\is_string($body['privacy_request_id']??null)
            || !\is_string($body['expected_version']??null)
            || ($body['scope']==='order_legal_copy'
                && !\is_string($body['order_id']??null))
            || ($body['scope']==='subject_verified'
                && \array_key_exists('order_id',$body))){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        foreach(['reason'=>1000,'verified_identity'=>200,
            'idempotency_key'=>128] as $name=>$max){
            $value=$body[$name];
            if(!\mb_check_encoding($value,'UTF-8')
                || ''===\trim($value)
                || \mb_strlen($value,'UTF-8')>$max
                || \preg_match('/[\x00-\x1F\x7F]/u',$value)){
                throw new Rest_Failure('pc_field_invalid',422,false,$name);
            }
        }
        if(\mb_strlen($body['idempotency_key'],'UTF-8')<16
            || \preg_match('/[\x00-\x20\x7F]/',$body['idempotency_key'])){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        foreach(['privacy_request_id','expected_version','order_id'] as $f){
            if(!isset($body[$f])){continue;}
            try{Domain_Validation::sqlId($body[$f]);}
            catch(\Throwable $ignored){
                throw new Rest_Failure('pc_field_invalid',422,false,$f);
            }
        }
    }

    public static function publicOperations(array $result,string $id):array
    {
        $out=[];
        foreach($result['export_operations'] as $op){
            if(!\is_array($op)||!isset($op['operation_id'],$op['phase'],
                $op['expires_at_utc']) || !\is_string($op['operation_id'])){
                throw new Transaction_Failure('pc_privacy_invariant_invalid');
            }
            $phase=$op['phase'];
            if(!\in_array($phase,[
                'pending','processing','ready','failed','expired'
            ],true)){
                throw new Transaction_Failure('pc_privacy_invariant_invalid');
            }
            $expired=Business_Time::fromApi($op['expires_at_utc'])
                <=Business_Time::nowUtc();
            $status=$expired?'expired':($phase==='processing'
                ?'pending':$phase);
            $item=['operation_id'=>$op['operation_id'],'status'=>$status,
                'expires_at_utc'=>$op['expires_at_utc']];
            if($status==='ready' && \is_string($op['evidence_ref'])){
                $item['evidence_ref']=$op['evidence_ref'];
            }
            if($status==='failed'){$item['failure_code']='pc_export_failed';}
            $out[]=$item;
        }
        return $out;
    }

    public static function exportValidWithin(
        Transaction_Context $tx,string $id,string $ref
    ):bool {
        $row=Privacy_Admin::rowWithin($tx,$id);
        if(null===$row){return false;}
        $result=Privacy_Admin::resultDocument($row['result_reference']);
        foreach(self::publicOperations($result,$id) as $op){
            if($op['status']==='ready'
                && ($op['evidence_ref']??null)===$ref){
                $e=Privacy_Evidence::entryWithin($tx,$id,$ref);
                return $e['purpose']==='export'
                    && $e['export_operation_id']===$op['operation_id']
                    && $e['expires_at_utc']===$op['expires_at_utc'];
            }
        }
        return false;
    }

    private static function workerDigest(string $id,string $op):string
    {
        return \hash('sha256','pc-privacy-export-worker:'
            .Privacy_Intake::siteDigest().':'.$id.':'.$op);
    }

    private static function operation(array $result,string $id):array
    {
        $idx=self::index($result,$id);
        if(null===$idx){throw new Transaction_Failure('pc_privacy_conflict');}
        return $result['export_operations'][$idx];
    }

    private static function index(array $result,string $id):?int
    {
        foreach($result['export_operations'] as $i=>$op){
            if(($op['operation_id']??null)===$id){return $i;}
        }
        return null;
    }

    private static function ack(string $id,array $op):array
    {
        return [
            'operation_id'=>$op['operation_id'],'accepted'=>true,
            'privacy_request_id'=>$id,
            'expires_at_utc'=>$op['expires_at_utc'],
        ];
    }
}
