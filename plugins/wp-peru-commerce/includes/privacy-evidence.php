<?php
/**
 * IMP-50 — manifiesto ARCO con evidencia privada por titular/expediente.
 * No es una ruta de subida ni autorización por referencia; el archivo
 * proviene de un canal administrativo verificado y fuera del webroot.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Privacy_Evidence
{
    private const MAX=20;
    private const MIME=['application/pdf','image/jpeg','image/png'];
    private const PURPOSES=['identity','representation','resolution','delivery','export'];
    private static $importVerifier=null;

    public static function registerImportVerifier(callable $check):void
    {
        if(null!==self::$importVerifier){
            throw new \LogicException('pc_privacy_import_verifier_duplicate');
        }
        self::$importVerifier=$check;
    }

    public static function manifestWithin(
        Transaction_Context $tx,string $id
    ):array {
        $r=Privacy_Admin::rowWithin($tx,$id);
        if(null===$r){throw new Transaction_Failure('pc_privacy_not_found');}
        if(null===$r['evidence_manifest']){return [];}
        $raw=$r['evidence_manifest'];
        if(!\is_string($raw)||\strlen($raw)>75000){
            throw new Transaction_Failure('pc_privacy_manifest_invalid');
        }
        try{$x=\json_decode($raw,true,16,\JSON_THROW_ON_ERROR);}
        catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_privacy_manifest_invalid');
        }
        if(!\is_array($x) || ($x['version']??null)!==1
            || \count($x)!==2 || !\is_array($x['items']??null)
            || !\array_is_list($x['items'])
            || \count($x['items'])>self::MAX){
            throw new Transaction_Failure('pc_privacy_manifest_invalid');
        }
        $refs=[];$items=[];
        foreach($x['items'] as $e){
            self::validateEntry($e,$id);
            if(isset($refs[$e['ref']])){
                throw new Transaction_Failure('pc_privacy_manifest_invalid');
            }
            $refs[$e['ref']]=true;$items[]=$e;
        }
        return $items;
    }

    public static function entryWithin(
        Transaction_Context $tx,string $id,string $ref
    ):array {
        if(!\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D',$ref)){
            throw new Transaction_Failure('pc_privacy_not_found');
        }
        foreach(self::manifestWithin($tx,$id) as $entry){
            if(\hash_equals($entry['ref'],$ref)){return $entry;}
        }
        throw new Transaction_Failure('pc_privacy_not_found');
    }

    public static function verifiedPath(array $entry):string
    {
        Transaction::assertOutside();
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/digital-private-storage.php';
        $path=Digital_Private_Storage::verify(
            $entry['storage_key'],$entry['sha256'],$entry['byte_size']
        );
        if(!\function_exists('finfo_open')){
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        $f=\finfo_open(\FILEINFO_MIME_TYPE);
        if(false===$f){throw new Transaction_Failure('pc_private_storage_unavailable');}
        try{$mime=\finfo_file($f,$path);}
        finally{\finfo_close($f);}
        if(!\is_string($mime) || $mime!==$entry['mime']
            || !\in_array($mime,'export'===$entry['purpose']
                ?['application/json','text/plain']:self::MIME,true)){
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        return $path;
    }

    /** 
     * API PHP INTERNA exclusiva A, no endpoint REST.
     * El importador confiable coloca el archivo primero y comprueba
     * origen/contenido de identidad sin almacenar documentos en wp_media.
     */
    public static function attachPrivate(
        string $id,string $expectedVersion,string $key,array $file
    ):array {
        Bootstrap::loadPrivacy();
        if(!Privacy_Admin::staffReady()
            || !Module_Gates::allows('privacy','evidence')
            || null===self::$importVerifier){
            throw new Transaction_Failure('pc_privacy_denied');
        }
        $id=Domain_Validation::sqlId($id);
        $expectedVersion=Domain_Validation::sqlId($expectedVersion);
        if(!\is_array($file) || \count($file)!==9
            || []!==\array_diff(\array_keys($file),[
                'purpose','storage_key','sha256','byte_size','mime',
                'filename','delivery_channel','delivered_at_utc',
                'resolution_hash'
            ])){
            throw new Transaction_Failure('pc_privacy_manifest_invalid');
        }
        $entry=[
            'ref'=>'pv_'.\str_repeat('a',48),
            'privacy_request_id'=>$id,
            'site_id'=>(string)\get_current_blog_id(),
            'site_digest'=>Privacy_Intake::siteDigest(),
            'purpose'=>$file['purpose'],
            'source'=>'verified_admin_import',
            'storage_key'=>$file['storage_key'],
            'sha256'=>$file['sha256'],
            'byte_size'=>$file['byte_size'],
            'mime'=>$file['mime'],
            'filename'=>$file['filename'],
            'added_at_utc'=>Business_Time::toApi(Business_Time::nowUtc()),
            'delivery_channel'=>$file['delivery_channel'],
            'delivered_at_utc'=>$file['delivered_at_utc'],
            'resolution_hash'=>$file['resolution_hash'],
        ];
        self::validateEntry($entry,$id);
        self::verifiedPath($entry);
        try{
            if(true!==(self::$importVerifier)(
                ['privacy_request_id'=>$id,'file'=>$file]
            )){
                throw new \UnexpectedValueException();
            }
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_privacy_manifest_invalid');
        }
        $actor=Event_Actor::staff(['perucommerce_privacy_manage']);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'privacy_request','resource_id'=>$id,
            'purpose'=>'privacy_evidence_attach',
        ];
        $payload=['manifest_hash'=>\hash('sha256',
            Privacy_Intake::encode([$expectedVersion,$file],12000))];
        return Transaction::run(static function(Transaction_Context $tx) use(
            $id,$expectedVersion,$key,$file,$actor,$scope,$payload
        ):array {
            $claim=Idempotency::claimWithin(
                $tx,$scope,'privacy_evidence_attach',$key,$payload
            );
            if(!\in_array($claim['status'],['new','replay'],true)){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $row=Privacy_Admin::rowWithin($tx,$id,true);
            if(null===$row){throw new Transaction_Failure('pc_privacy_not_found');}
            $ref='pv_'.\substr(\hash('sha256',
                Privacy_Intake::siteDigest().':'.$claim['operation_id']),0,48);
            $old=self::manifestWithin($tx,$id);
            if('replay'===$claim['status']){
                if('privacy_request'!==($claim['entity_type']??null)
                    || $id!==($claim['entity_id']??null)
                    || 200!==($claim['response_code']??null)
                    || !\in_array($ref,\array_column($old,'ref'),true)){
                    throw new Transaction_Failure('pc_privacy_conflict');
                }
                return ['id'=>$id,'evidence_ref'=>$ref,
                    'operation_id'=>$claim['operation_id'],
                    'row_version'=>Settings_Store::nextVersion($expectedVersion)];
            }
            if((string)$row['row_version']!==$expectedVersion
                || \count($old)>=self::MAX){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            foreach($old as $e){
                if($e['storage_key']===$file['storage_key']
                    || $e['ref']===$ref){
                    throw new Transaction_Failure('pc_privacy_conflict');
                }
            }
            $now=$tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
            if(1!==\count($now)){
                throw new Transaction_Failure('pc_privacy_manifest_invalid');
            }
            $entry=[
                'ref'=>$ref,'privacy_request_id'=>$id,
                'site_id'=>(string)\get_current_blog_id(),
                'site_digest'=>Privacy_Intake::siteDigest(),
                'purpose'=>$file['purpose'],
                'source'=>'verified_admin_import',
                'storage_key'=>$file['storage_key'],
                'sha256'=>$file['sha256'],'byte_size'=>$file['byte_size'],
                'mime'=>$file['mime'],'filename'=>$file['filename'],
                'added_at_utc'=>Business_Time::toApi(
                    Business_Time::fromDatabase($now[0]['now_utc'])
                ),
                'delivery_channel'=>$file['delivery_channel'],
                'delivered_at_utc'=>$file['delivered_at_utc'],
                'resolution_hash'=>$file['resolution_hash'],
            ];
            self::validateEntry($entry,$id);
            $old[]=$entry;
            $saved=Privacy_Intake::encode(['version'=>1,'items'=>$old],75000);
            if(1!==$tx->execute(
                'UPDATE %i SET evidence_manifest=%s,row_version=row_version+1 '
                    .'WHERE id=%s AND row_version=%s',
                [Privacy_Admin::table(),$saved,$id,$expectedVersion]
            )){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],'privacy_request',$id,
                'privacy_evidence_linked','private_legal_file',
                \hash('sha256',$expectedVersion),
                \hash('sha256',$ref.':'.$file['sha256'])
            );
            Queue_Outbox::privacyEventWithin(
                $tx,$actor,$claim['operation_id'],$id,'privacy_evidence_linked'
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'privacy_request',$id,200
            );
            return ['id'=>$id,'evidence_ref'=>$ref,
                'operation_id'=>$claim['operation_id'],
                'row_version'=>Settings_Store::nextVersion($expectedVersion)];
        },true);
    }

    private static function validateEntry(mixed $e,string $id):void
    {
        if(!\is_array($e)){throw new Transaction_Failure('pc_privacy_manifest_invalid');}
        $export=($e['purpose']??null)==='export';
        $fields=[
            'ref','privacy_request_id','site_id','site_digest',
            'purpose','source','storage_key','sha256','byte_size',
            'mime','filename','added_at_utc','delivery_channel',
            'delivered_at_utc','resolution_hash'
        ];
        if($export){$fields[]='export_operation_id';$fields[]='expires_at_utc';}
        if(\count($e)!==\count($fields)
            || []!==\array_diff(\array_keys($e),$fields)
            || !\is_string($e['ref']??null)
            || !\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D',$e['ref'])
            || ($e['privacy_request_id']??null)!==$id
            || ($e['site_id']??null)!==(string)\get_current_blog_id()
            || ($e['site_digest']??null)!==Privacy_Intake::siteDigest()
            || !\in_array($e['purpose']??null,self::PURPOSES,true)
            || ($export?'verified_export':'verified_admin_import')!==($e['source']??null)
            || !\is_string($e['storage_key']??null)
            || !\preg_match('/\Apc-[a-f0-9]{16}-[a-f0-9]{48}\.blob\z/D',
                $e['storage_key'])
            || !\is_string($e['sha256']??null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$e['sha256'])
            || !\is_string($e['byte_size']??null)
            || !\preg_match('/\A[1-9][0-9]{0,7}\z/D',$e['byte_size'])
            || (int)$e['byte_size']>($export?2097152:20971520)
            || !\in_array($e['mime']??null,
                $export?['application/json','text/plain']:self::MIME,true)
            || ($export && (!\is_string($e['export_operation_id']??null)
                || !\preg_match('/\A[1-9][0-9]*\z/D',$e['export_operation_id'])
                || !\is_string($e['expires_at_utc']??null)
                || !self::validUtc($e['expires_at_utc'])
                || !\str_ends_with($e['filename']??'','.json')))
            || !\is_string($e['filename']??null)
            || !\preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,179}\z/D',
                $e['filename'])
            || \str_contains($e['filename'],'..')
            || !\is_string($e['added_at_utc']??null)
            || !self::validUtc($e['added_at_utc'])
            || !self::deliveryValid($e)){
            throw new Transaction_Failure('pc_privacy_manifest_invalid');
        }
    }

    private static function deliveryValid(array $e):bool
    {
        if('delivery'===$e['purpose']){
            return \in_array($e['delivery_channel'],[
                'email','postal_letter','in_person'
            ],true)
                && \is_string($e['delivered_at_utc'])
                && self::validUtc($e['delivered_at_utc'])
                && \is_string($e['resolution_hash'])
                && 1===\preg_match('/\A[a-f0-9]{64}\z/D',
                    $e['resolution_hash']);
        }
        return null===$e['delivery_channel']
            && null===$e['delivered_at_utc']
            && null===$e['resolution_hash'];
    }

    private static function validUtc(string $date):bool
    {
        try{
            return \str_ends_with($date,'Z')
                && Business_Time::toApi(
                    Business_Time::fromApi($date)
                )===$date;
        }catch(\Throwable $ignored){return false;}
    }


    /** Agregar exportación ya verificada, sin modificar evidencias originales. */
    public static function exportManifestWithin(
        Transaction_Context $tx,string $id,array $entry
    ):string {
        if(($entry['purpose']??null)!=='export'){
            throw new Transaction_Failure('pc_privacy_manifest_invalid');
        }
        self::validateEntry($entry,$id);
        $existing=self::manifestWithin($tx,$id);
        if(\count($existing)>=self::MAX){
            throw new Transaction_Failure('pc_privacy_conflict');
        }
        foreach($existing as $other){
            if($other['ref']===$entry['ref']
                || $other['storage_key']===$entry['storage_key']){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
        }
        $existing[]=$entry;
        return Privacy_Intake::encode(
            ['version'=>1,'items'=>$existing],75000
        );
    }

    public static function referencesWithin(
        Transaction_Context $tx,string $id
    ):array {
        return \array_map(
            static fn(array $e):string=>$e['ref'],
            self::manifestWithin($tx,$id)
        );
    }
}
