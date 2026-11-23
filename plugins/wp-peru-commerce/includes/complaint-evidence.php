<?php
/**
 * IMP-49 — evidencias legales privadas vinculadas a pc_complaints.
 *
 * El contrato NO tiene endpoint para upload público. Se leen solo
 * manifiestos ya incorporados por un canal administrativo confiable; nunca
 * se trata una referencia opaca como autorización o path de filesystem.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Complaint_Evidence
{
    public const MAX_ITEMS=20;
    private const MIME=['application/pdf','image/jpeg','image/png'];
    private const PURPOSES=[
        'claimant_identity','representation','response_delivery',
        'case_document','original_complaint',
    ];

    /** @return list<array<string,mixed>> */
    public static function manifestWithin(
        Transaction_Context $tx,string $caseId
    ): array {
        $caseId=Domain_Validation::sqlId($caseId);
        $rows=$tx->rows(
            'SELECT evidence_manifest FROM %i WHERE id=%s AND sequence_scope=%s '
                .'LIMIT 2',
            [self::table('pc_complaints'),$caseId,'principal_v1']
        );
        if(1!==\count($rows)){
            throw new Transaction_Failure('pc_complaint_not_found');
        }
        $raw=$rows[0]['evidence_manifest'];
        if(null===$raw){return [];}
        if(!\is_string($raw)||\strlen($raw)>60000){
            throw new Transaction_Failure('pc_complaint_manifest_invalid');
        }
        try{$manifest=\json_decode($raw,true,16,\JSON_THROW_ON_ERROR);}
        catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_complaint_manifest_invalid');
        }
        if(!\is_array($manifest) || \array_keys($manifest)!==['version','items']
            || 1!==$manifest['version']
            || !\is_array($manifest['items'])
            || !\array_is_list($manifest['items'])
            || \count($manifest['items'])>self::MAX_ITEMS){
            throw new Transaction_Failure('pc_complaint_manifest_invalid');
        }
        $seen=[];$output=[];
        foreach($manifest['items'] as $entry){
            if(!\is_array($entry)
                || \array_keys($entry)!==[
                    'ref','complaint_id','site_id','site_digest','purpose',
                    'source','storage_key','sha256','byte_size','mime',
                    'filename','added_at_utc','channel','delivered_at_utc',
                    'response_sha256'
                ]){
                throw new Transaction_Failure('pc_complaint_manifest_invalid');
            }
            $ref=$entry['ref'];
            if(!\is_string($ref)
                || !\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D',$ref)
                || isset($seen[$ref])
                || $entry['complaint_id']!==$caseId
                || $entry['site_id']!==(string)\get_current_blog_id()
                || $entry['site_digest']!==self::siteDigest()
                || !\in_array($entry['purpose'],self::PURPOSES,true)
                || !\in_array($entry['source'],[
                    'verified_admin_import','verified_legal_channel'
                ],true)
                || !\is_string($entry['storage_key'])
                || !\preg_match('/\Apc-[a-f0-9]{16}-[a-f0-9]{48}\.blob\z/D',
                    $entry['storage_key'])
                || !\is_string($entry['sha256'])
                || !\preg_match('/\A[a-f0-9]{64}\z/D',$entry['sha256'])
                || !\is_string($entry['byte_size'])
                || !\preg_match('/\A[1-9][0-9]{0,7}\z/D',$entry['byte_size'])
                || (int)$entry['byte_size']>20971520
                || !\in_array($entry['mime'],self::MIME,true)
                || !\is_string($entry['filename'])
                || !\preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,179}\z/D',
                    $entry['filename'])
                || \str_contains($entry['filename'],'..')
                || !\is_string($entry['added_at_utc'])
                || !self::utcValid($entry['added_at_utc'])
                || !self::deliveryFieldsValid($entry)){
                throw new Transaction_Failure('pc_complaint_manifest_invalid');
            }
            $seen[$ref]=true;
            $output[]=$entry;
        }
        return $output;
    }

    public static function entryWithin(
        Transaction_Context $tx,string $caseId,string $ref
    ): array {
        if(!\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D',$ref)){
            throw new Transaction_Failure('pc_complaint_not_found');
        }
        foreach(self::manifestWithin($tx,$caseId) as $entry){
            if(\hash_equals($entry['ref'],$ref)){return $entry;}
        }
        throw new Transaction_Failure('pc_complaint_not_found');
    }

    /** Link visible por R8-65, nunca acceso a binario por conocerlo. */
    public static function referencesWithin(
        Transaction_Context $tx,string $caseId
    ): array {
        return \array_map(
            static fn(array $e):string=>$e['ref'],
            self::manifestWithin($tx,$caseId)
        );
    }

    /**
     * Punto de ingreso EXCLUSIVAMENTE PHP para un canal administrativo
     * legítimo, sin POST nuevo. El archivo debe estar ya almacenado en
     * PERUCOMMERCE_PRIVATE_DIR por un importador fiable y con procedencia
     * revisada. No crea binarios, permisos ni cuentas.
     *
     * Requiere además una prueba de origen registrada en el instalador,
     * independiente de comprobar bytes y extensión de archivo.
     */
    private static $importVerifier=null;

    public static function registerImportVerifier(callable $check):void
    {
        if(null!==self::$importVerifier){
            throw new \LogicException('pc_complaint_import_verifier_duplicate');
        }
        self::$importVerifier=$check;
    }

    /** @return array{operation_id:string,entity_id:string,row_version:string,evidence_ref:string} */
    public static function attachPrivate(
        string $caseId,string $expectedVersion,string $key,array $file
    ):array {
        // Punto interno invocable sin estar en rest_api_init: preparar
        // clases/capabilities antes de resolver la fuente de evidencia.
        Bootstrap::loadComplaintAdmin();
        if(!Complaint_Admin::staffReady(Access_Roles::currentRole())
            || null===self::$importVerifier
            || !Module_Gates::allows('complaints','evidence')){
            throw new Transaction_Failure('pc_complaint_denied');
        }
        $caseId=Domain_Validation::sqlId($caseId);
        $expectedVersion=Domain_Validation::sqlId($expectedVersion);
        $wanted=['purpose','storage_key','sha256','byte_size','mime',
            'filename','channel','delivered_at_utc','response_sha256'];
        if(\count($file)!==\count($wanted)
            || []!==\array_diff(\array_keys($file),$wanted)
            || !\is_string($file['purpose']??null)
            || !\in_array($file['purpose'],self::PURPOSES,true)
            || !\is_string($file['filename']??null)
            || !\preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,179}\z/D',
                $file['filename'])
            || \str_contains($file['filename'],'..')
            || !\is_string($file['sha256']??null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$file['sha256'])
            || !\is_string($file['byte_size']??null)
            || !\preg_match('/\A[1-9][0-9]{0,7}\z/D',$file['byte_size'])
            || (int)$file['byte_size']>20971520
            || !\in_array($file['mime']??null,self::MIME,true)
            || !self::deliveryFieldsValid($file)){
            throw new Transaction_Failure('pc_complaint_manifest_invalid');
        }
        // E/S FILESYSTEM y procedencia únicamente FUERA del callback SQL.
        self::verifiedPath($file);
        try{
            if(true!==(self::$importVerifier)($caseId,$file)){
                throw new \UnexpectedValueException();
            }
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_complaint_manifest_invalid');
        }
        $packed=\json_encode([$caseId,$expectedVersion,$file],
            \JSON_THROW_ON_ERROR|\JSON_UNESCAPED_SLASHES);
        $actor=Event_Actor::staff(['perucommerce_complaints_manage']);
        $scope=['actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'complaint','resource_id'=>$caseId,
            'purpose'=>'complaint_evidence_attach'];
        return Transaction::run(static function(Transaction_Context $tx)use(
            $caseId,$expectedVersion,$key,$file,$packed,$actor,$scope
        ):array{
            $claim=Idempotency::claimWithin($tx,$scope,
                'complaint_evidence_attach',$key,
                ['manifest_sha256'=>\hash('sha256',$packed)]
            );
            if(!\in_array($claim['status'],['new','replay'],true)){
                throw new Transaction_Failure('pc_complaint_response_conflict');
            }
            $ref='pc_'.\substr(\hash('sha256',
                self::siteDigest().':'.$claim['operation_id']),0,48);
            $case=Complaint_Admin::rowWithin($tx,$caseId,true);
            if(null===$case){
                throw new Transaction_Failure('pc_complaint_not_found');
            }
            $items=self::manifestWithin($tx,$caseId);
            if('replay'===$claim['status']){
                if('complaint'!==($claim['entity_type']??null)
                    || $caseId!==($claim['entity_id']??null)
                    || 200!==($claim['response_code']??null)
                    || !\in_array($ref,\array_column($items,'ref'),true)){
                    throw new Transaction_Failure('pc_complaint_response_conflict');
                }
                return ['operation_id'=>$claim['operation_id'],
                    'entity_id'=>$caseId,'evidence_ref'=>$ref,
                    'row_version'=>Settings_Store::nextVersion($expectedVersion)];
            }
            if((string)$case['row_version']!==$expectedVersion
                || \count($items)>=self::MAX_ITEMS){
                throw new Transaction_Failure('pc_complaint_response_conflict');
            }
            foreach($items as $existing){
                if($existing['storage_key']===$file['storage_key']
                    || $existing['ref']===$ref){
                    throw new Transaction_Failure('pc_complaint_response_conflict');
                }
            }
            $clock=$tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
            if(1!==\count($clock)){
                throw new Transaction_Failure('pc_complaint_manifest_invalid');
            }
            $added=Business_Time::toApi(Business_Time::fromDatabase(
                (string)$clock[0]['now_utc']
            ));
            $items[]=[
                'ref'=>$ref,'complaint_id'=>$caseId,
                'site_id'=>(string)\get_current_blog_id(),
                'site_digest'=>self::siteDigest(),'purpose'=>$file['purpose'],
                'source'=>'verified_admin_import',
                'storage_key'=>$file['storage_key'],'sha256'=>$file['sha256'],
                'byte_size'=>$file['byte_size'],'mime'=>$file['mime'],
                'filename'=>$file['filename'],'added_at_utc'=>$added,
                'channel'=>$file['channel'],
                'delivered_at_utc'=>$file['delivered_at_utc'],
                'response_sha256'=>$file['response_sha256'],
            ];
            $json=\json_encode(['version'=>1,'items'=>$items],
                \JSON_THROW_ON_ERROR|\JSON_UNESCAPED_SLASHES);
            if(!\is_string($json)||\strlen($json)>60000
                || 1!==$tx->execute(
                    'UPDATE %i SET evidence_manifest=%s,'
                        .'row_version=row_version+1 '
                        .'WHERE id=%s AND sequence_scope=%s AND row_version=%s',
                    [self::table('pc_complaints'),$json,
                        $caseId,'principal_v1',$expectedVersion]
                )){
                throw new Transaction_Failure('pc_complaint_response_conflict');
            }
            $next=Settings_Store::nextVersion($expectedVersion);
            Event_Journal::auditWithin($tx,$actor,$claim['operation_id'],
                'complaint',$caseId,'complaint_evidence_linked',
                'admin_private_evidence',
                \hash('sha256',$expectedVersion),
                \hash('sha256',$ref.':'.$file['sha256'].':'.$next)
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'complaint',$caseId,200
            );
            return ['operation_id'=>$claim['operation_id'],
                'entity_id'=>$caseId,'evidence_ref'=>$ref,
                'row_version'=>$next];
        },true);
    }

    /** Verificación física FUERA de Transaction::run. */
    public static function verifiedPath(array $entry):string
    {
        Transaction::assertOutside();
        require_once PERUCOMMERCE_PLUGIN_DIR
            .'includes/digital-private-storage.php';
        $path=Digital_Private_Storage::verify(
            $entry['storage_key'],$entry['sha256'],$entry['byte_size']
        );
        // El manifiesto declara MIME, pero esa declaración NO es prueba.
        // Revalidar tipo real antes de habilitar streaming binario legal.
        if(!\function_exists('finfo_open')){
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        $finfo=\finfo_open(\FILEINFO_MIME_TYPE);
        if(false===$finfo){
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        try{$mime=\finfo_file($finfo,$path);}
        finally{\finfo_close($finfo);}
        if(!\is_string($mime) || !\in_array($mime,self::MIME,true)
            || $mime!==$entry['mime']){
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        return $path;
    }

    private static function deliveryFieldsValid(array $entry):bool
    {
        if('response_delivery'===$entry['purpose']){
            return \in_array($entry['channel'],['email','letter'],true)
                && \is_string($entry['delivered_at_utc'])
                && self::utcValid($entry['delivered_at_utc'])
                && \is_string($entry['response_sha256'])
                && 1===\preg_match('/\A[a-f0-9]{64}\z/D',
                    $entry['response_sha256']);
        }
        return null===$entry['channel']
            && null===$entry['delivered_at_utc']
            && null===$entry['response_sha256'];
    }

    private static function utcValid(string $value):bool
    {
        try{
            $instant=Business_Time::fromApi($value);
            return \str_ends_with($value,'Z')
                && Business_Time::toApi($instant)===$value;
        }catch(\Throwable $ignored){return false;}
    }

    private static function siteDigest():string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){throw new Transaction_Failure('pc_complaint_manifest_invalid');}
        return \hash('sha256',$wpdb->prefix.':'.\get_current_blog_id());
    }

    private static function table(string $name):string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){
            throw new Transaction_Failure('pc_complaint_manifest_invalid');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
        return Schema::tableNames($wpdb)[$name];
    }
}
