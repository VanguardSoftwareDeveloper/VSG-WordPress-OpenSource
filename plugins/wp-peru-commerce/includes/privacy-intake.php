<?php
/**
 * IMP-50 — ingesta ARCO de WordPress Privacy confirmado y canal manual
 * legítimo. No es un nuevo endpoint REST ni exige cuenta/correo al titular.
 * La confirmación WP prueba el email, NO identidad legal ni pedido.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Privacy_Intake
{
    private static bool $hooks=false;
    private static bool $verifiers=false;
    /** @var array{id:string,digest:string}|null */
    private static ?array $active=null;
    private const MAX_SNAPSHOT=24000;

    public static function registerHooks():void
    {
        if(self::$hooks){return;}
        self::$hooks=true;
        \add_action('user_request_action_confirmed',
            [self::class,'onWordPressConfirmed'],20,1);
        require_once PERUCOMMERCE_PLUGIN_DIR
            .'includes/privacy-wordpress.php';
        Privacy_WordPress::registerHooks();
    }

    public static function registerVerifiers():void
    {
        if(self::$verifiers){return;}
        Event_Actor::registerPrivacySystemVerifier(
            [self::class,'verifiesActor']
        );
        Idempotency::registerPrivacySystemScopeVerifier(
            [self::class,'verifiesScope']
        );
        self::$verifiers=true;
    }

    public static function verifiesActor(string $digest,string $purpose):bool
    {
        return 'privacy_intake_wp'===$purpose
            && null!==self::$active
            && \hash_equals(self::$active['digest'],$digest);
    }

    public static function verifiesScope(array $scope):bool
    {
        return null!==self::$active
            && 'system'===($scope['actor_kind']??null)
            && 'privacy_wp'===($scope['resource_kind']??null)
            && self::$active['id']===($scope['resource_id']??null)
            && 'privacy_intake_wp'===($scope['purpose']??null)
            && \is_string($scope['actor_ref']??null)
            && \hash_equals(self::$active['digest'],$scope['actor_ref']);
    }

    /**
     * Solo ejecuta en el hook core o invocación manual interna: la fuente
     * WP_User_Request se lee FUERA de Transaction_Context.
     * Si P-C aún no está disponible, WP conserva su solicitud original.
     */
    public static function onWordPressConfirmed(int $wpRequestId):void
    {
        try{
            Bootstrap::loadPrivacy();
            if(!Module_Gates::allows('privacy','intake')
                || $wpRequestId<1 || !\function_exists('wp_get_user_request')){
                return;
            }
            $source=\wp_get_user_request($wpRequestId);
            if(!$source instanceof \WP_User_Request
                || $source->ID!==$wpRequestId
                || 'request-confirmed'!==$source->status
                || !\in_array($source->action_name,[
                    'export_personal_data','remove_personal_data'
                ],true)
                || !\is_int($source->created_timestamp)
                || $source->created_timestamp<=0
                || $source->created_timestamp>\time()+300
                || !\is_string($source->email)){
                return;
            }
            $email=Domain_Validation::email($source->email);
            $type='export_personal_data'===$source->action_name
                ?'access':'cancellation';
            $sourceKey=self::sourceKey('wp', (string)$wpRequestId);
            $scopeDigest=\hash_hmac('sha256',
                'pc-privacy-wp-v1:'.\get_current_blog_id().':'
                    .$wpRequestId.':'.$source->action_name,\wp_salt('auth'));
            self::$active=['id'=>(string)$wpRequestId,'digest'=>$scopeDigest];
            try{
                $actor=Event_Actor::privacyConfirmed($scopeDigest);
                $scope=[
                    'actor_kind'=>'system','actor_ref'=>$scopeDigest,
                    'resource_kind'=>'privacy_wp',
                    'resource_id'=>(string)$wpRequestId,
                    'purpose'=>'privacy_intake_wp',
                ];
                $originalDate=\gmdate('Y-m-d H:i:s', $source->created_timestamp)
                    .'.000000';
                $data=[
                    'request_type'=>$type,
                    'received_channel'=>'wordpress_confirmed',
                    'received_at'=>$originalDate,
                    'source_key'=>$sourceKey,
                    'contact_kind'=>'email',
                    'contact_value'=>$email,
                    'applicant_name'=>null,
                    'request_summary'=>'Solicitud de privacidad confirmada por correo mediante WordPress.',
                    'source_id'=>(string)$wpRequestId,
                    'source_status'=>'request-confirmed',
                    'identity_status'=>'pending',
                ];
                Transaction::run(static function(Transaction_Context $tx)
                    use($scope,$actor,$data):void{
                    $claim=Idempotency::claimWithin(
                        $tx,$scope,'privacy_intake_wp',
                        'wp_privacy_intake_confirmed_v1',
                        ['source_key'=>$data['source_key'],
                            'type'=>$data['request_type']]
                    );
                    if('replay'===$claim['status']){
                        if('privacy_request'!==($claim['entity_type']??null)
                            || 201!==($claim['response_code']??null)){
                            throw new Transaction_Failure('pc_privacy_intake_conflict');
                        }
                        return;
                    }
                    if('new'!==$claim['status']){
                        throw new Transaction_Failure('pc_privacy_intake_conflict');
                    }
                    self::insertWithin(
                        $tx,$actor,$claim['operation_id'],$data
                    );
                },true);
            }finally{self::$active=null;}
        }catch(\Throwable $ignored){
            // Solicitud core subsiste: se puede importar bajo control A.
            // No bloquear el flujo de confirmación de WordPress.
        }
    }

    /**
     * Canal administrativo documentado, no API pública alternativa.
     * La prueba del origen/contacto se verifica por P-C ANTES del TX.
     * Puede ingresar sin email, compra o cuenta.
     */
    public static function receiveManual(array $body):array
    {
        Bootstrap::loadPrivacy();
        if(!Privacy_Admin::staffReady()
            || !Module_Gates::allows('privacy','intake')){
            throw new Transaction_Failure('pc_privacy_denied');
        }
        self::validateManual($body);
        $input=$body;
        unset($input['idempotency_key']);
        Privacy_Legal::requireSource($input);
        $actor=Event_Actor::staff(['perucommerce_privacy_manage']);
        $contact=$body['contact'];
        $sourceKey=self::sourceKey(
            'manual',$body['source_reference'].':'.$body['received_channel']
        );
        $received=Business_Time::toDatabase(
            Business_Time::fromApi($body['received_at_utc'])
        );
        $data=[
            'request_type'=>$body['request_type'],
            'received_channel'=>$body['received_channel'],
            'received_at'=>$received,'source_key'=>$sourceKey,
            'contact_kind'=>$contact['kind'],
            'contact_value'=>$contact['value'],
            'applicant_name'=>$body['applicant_name']??null,
            'request_summary'=>$body['request_summary'],
            'source_id'=>null,
            'source_status'=>'verified_channel_intake',
            'identity_status'=>'pending',
        ];
        $payload=['source_sha256'=>\hash('sha256',self::encode($input,64000))];
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'system','resource_id'=>'0',
            'purpose'=>'privacy_manual_intake',
        ];
        return Transaction::run(static function(Transaction_Context $tx)
            use($actor,$scope,$data,$body,$payload):array{
            $claim=Idempotency::claimWithin(
                $tx,$scope,'privacy_manual_intake',
                $body['idempotency_key'],$payload
            );
            if('replay'===$claim['status']){
                if('privacy_request'!==($claim['entity_type']??null)
                    || 201!==($claim['response_code']??null)){
                    throw new Transaction_Failure('pc_privacy_intake_conflict');
                }
                return ['id'=>$claim['entity_id'],
                    'operation_id'=>$claim['operation_id'],
                    'result'=>'existing'];
            }
            if('new'!==$claim['status']){
                throw new Transaction_Failure('pc_privacy_intake_conflict');
            }
            $id=self::insertWithin(
                $tx,$actor,$claim['operation_id'],$data
            );
            return ['id'=>$id,'operation_id'=>$claim['operation_id'],
                'result'=>'recorded_pending_identity'];
        },true);
    }

    /**
     * Identidad civil/representación requiere evidencia privada del caso
     * y comprobación de legitimidad real; el control de email WP no basta.
     */
    public static function verifyIdentity(
        string $id,string $expectedVersion,string $ref,string $key
    ):array {
        Bootstrap::loadPrivacy();
        if(!Privacy_Admin::staffReady()
            || !Module_Gates::allows('privacy','verify')
            || !Module_Gates::allows('privacy','evidence')
            || !Privacy_Legal::identityReady()){
            throw new Transaction_Failure('pc_privacy_denied');
        }
        $id=Domain_Validation::sqlId($id);
        $expectedVersion=Domain_Validation::sqlId($expectedVersion);
        $meta=Transaction::run(
            static fn(Transaction_Context $tx):array=>
                Privacy_Evidence::entryWithin($tx,$id,$ref)
        );
        Privacy_Evidence::verifiedPath($meta);
        $digest=\hash('sha256',self::encode($meta,8000));
        $actor=Event_Actor::staff(['perucommerce_privacy_manage']);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'privacy_request','resource_id'=>$id,
            'purpose'=>'privacy_identity_verify'
        ];
        $payload=['evidence_sha256'=>$digest,'expected_version'=>$expectedVersion];
        return Transaction::run(static function(Transaction_Context $tx)use(
            $id,$expectedVersion,$ref,$key,$digest,$actor,$scope,$payload
        ):array{
            $claim=Idempotency::claimWithin(
                $tx,$scope,'privacy_identity_verify',$key,$payload
            );
            if(!\in_array($claim['status'],['new','replay'],true)){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $row=Privacy_Admin::rowWithin($tx,$id,true);
            if(null===$row){throw new Transaction_Failure('pc_privacy_not_found');}
            if('replay'===$claim['status']){
                if('privacy_request'!==($claim['entity_type']??null)
                    || $id!==($claim['entity_id']??null)
                    || 200!==($claim['response_code']??null)){
                    throw new Transaction_Failure('pc_privacy_conflict');
                }
                return ['id'=>$id,'operation_id'=>$claim['operation_id'],
                    'row_version'=>Settings_Store::nextVersion($expectedVersion)];
            }
            if((string)$row['row_version']!==$expectedVersion
                || null!==$row['verified_at']){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $entry=Privacy_Evidence::entryWithin($tx,$id,$ref);
            if($digest!==\hash('sha256',self::encode($entry,8000))
                || !\in_array($entry['purpose'],[
                    'identity','representation'
                ],true)){
                throw new Transaction_Failure('pc_privacy_identity_unverified');
            }
            Privacy_Legal::requireIdentity(
                ['request_snapshot'=>Privacy_Admin::decode(
                    $row['request_snapshot'],48000),
                    'applicant_reference'=>Privacy_Admin::decode(
                        $row['applicant_reference'],20000)],
                $entry
            );
            if(1!==$tx->execute(
                'UPDATE %i SET identity_evidence_ref=%s,'
                    .'verified_at=UTC_TIMESTAMP(6),'
                    .'row_version=row_version+1 WHERE id=%s '
                    .'AND verified_at IS NULL AND row_version=%s',
                [Privacy_Admin::table(),$ref,$id,$expectedVersion]
            )){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],'privacy_request',$id,
                'privacy_identity_verified','verified_legal_subject',
                \hash('sha256',$expectedVersion),\hash('sha256',$ref)
            );
            Queue_Outbox::privacyEventWithin(
                $tx,$actor,$claim['operation_id'],$id,
                'privacy_identity_verified'
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'privacy_request',$id,200
            );
            return ['id'=>$id,'operation_id'=>$claim['operation_id'],
                'row_version'=>Settings_Store::nextVersion($expectedVersion)];
        },true);
    }

    private static function insertWithin(
        Transaction_Context $tx,Event_Actor $actor,string $op,array $d
    ):string {
        $state=Settings_Store::readWithin($tx,true);
        if(null===$state['raw']){
            throw new Transaction_Failure('pc_privacy_intake_not_ready');
        }
        // Rechazar duplicación entre distintos administradores/roots
        // serializados por la MISMA fila de settings del sitio.
        $seen=$tx->rows(
            "SELECT id FROM %i WHERE JSON_UNQUOTE(JSON_EXTRACT("
                ."applicant_reference,'$.source_key'))=%s LIMIT 2",
            [Privacy_Admin::table(),$d['source_key']]
        );
        if([]!==$seen){
            throw new Transaction_Failure('pc_privacy_intake_conflict');
        }
        $now=$tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
        if(1!==\count($now)
            || !\is_string($now[0]['now_utc'])
            || $d['received_at']>(string)$now[0]['now_utc']){
            throw new Transaction_Failure('pc_privacy_intake_invalid');
        }
        $due=null;$calendar=null;
        if(Privacy_Calendar::ready()){
            $calc=Privacy_Calendar::dueFor($d['received_at'],
                $d['request_type'],['source_kind'=>$d['received_channel']]);
            $due=$calc['due_at'];$calendar=$calc['calendar_version'];
        }
        $who=[
            'schema_version'=>1,
            'site_id'=>(string)\get_current_blog_id(),
            'site_digest'=>self::siteDigest(),
            'source_key'=>$d['source_key'],
            'source_kind'=>$d['received_channel'],
            'source_id'=>$d['source_id'],
            'contact_kind'=>$d['contact_kind'],
            'contact_value'=>$d['contact_value'],
            'applicant_name'=>$d['applicant_name'],
        ];
        $request=[
            'schema_version'=>1,
            'request_type'=>$d['request_type'],
            'request_summary'=>$d['request_summary'],
            'received_channel'=>$d['received_channel'],
            'source_status'=>$d['source_status'],
            'identity_status'=>'pending',
            'calendar_version'=>$calendar,
            'retention_inventory_version'=>null,
        ];
        if(1!==$tx->execute(
            'INSERT INTO %i (applicant_reference,request_snapshot,'
                .'request_type,received_channel,received_at,'
                .'identity_evidence_ref,evidence_manifest,verified_at,'
                .'due_at,resolved_at,response_channel,response_evidence_ref,'
                .'result_reference,legal_basis_code,row_version) '
                .'VALUES (%s,%s,%s,%s,%s,NULL,NULL,NULL,'
                .($due===null?'NULL':'%s')
                .',NULL,NULL,NULL,NULL,NULL,1)',
            \array_merge(
                [Privacy_Admin::table(),
                    self::encode($who,self::MAX_SNAPSHOT),
                    self::encode($request,self::MAX_SNAPSHOT),
                    $d['request_type'],$d['received_channel'],$d['received_at']],
                $due===null?[]:[$due]
            )
        )){
            throw new Transaction_Failure('pc_privacy_intake_invalid');
        }
        $rows=$tx->rows('SELECT LAST_INSERT_ID() AS id');
        if(1!==\count($rows)){
            throw new Transaction_Failure('pc_privacy_intake_invalid');
        }
        $id=Domain_Validation::sqlId((string)$rows[0]['id']);
        Event_Journal::auditWithin($tx,$actor,$op,'privacy_request',$id,
            'privacy_request_received','identity_review_pending',
            null,\hash('sha256',$d['source_key'].':'.$d['request_type'])
        );
        Queue_Outbox::privacyEventWithin(
            $tx,$actor,$op,$id,'privacy_request_received'
        );
        Idempotency::completeLocalWithin(
            $tx,$op,'privacy_request',$id,201
        );
        return $id;
    }

    private static function validateManual(array $body):void
    {
        $required=['request_type','received_channel','received_at_utc',
            'contact','request_summary','source_reference','idempotency_key'];
        $allowed=[...$required,'applicant_name'];
        if([]!==\array_diff($required,\array_keys($body))
            || []!==\array_diff(\array_keys($body),$allowed)
            || !\in_array($body['request_type']??null,[
                'access','rectification','cancellation','opposition'
            ],true)
            || !\in_array($body['received_channel']??null,[
                'in_person','signed_letter','phone_verified','email_verified'
            ],true)
            || !\is_array($body['contact']??null)
            || \count($body['contact'])!==2
            || []!==\array_diff(\array_keys($body['contact']),[
                'kind','value'
            ])
            || !\is_string($body['contact']['value']??null)
            || !\in_array($body['contact']['kind']??null,
                ['email','phone','postal_address'],true)
            || !\is_string($body['received_at_utc']??null)
            || !\is_string($body['idempotency_key']??null)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        foreach(['request_summary'=>5000,'source_reference'=>128,
                'idempotency_key'=>128,'applicant_name'=>200] as $key=>$limit){
            if(!isset($body[$key])){continue;}
            $v=$body[$key];
            if(!\is_string($v)||''===\trim($v)
                || !\mb_check_encoding($v,'UTF-8')
                || \mb_strlen($v,'UTF-8')>$limit
                || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$v)){
                throw new Rest_Failure('pc_field_invalid',422,false,$key);
            }
        }
        if(\strlen($body['idempotency_key'])<16
            || \preg_match('/[\x00-\x20\x7F]/',
                $body['idempotency_key'])){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try{Business_Time::fromApi($body['received_at_utc']);}
        catch(\Throwable $ignored){
            throw new Rest_Failure('pc_field_invalid',422,false,'received_at_utc');
        }
        $contact=$body['contact'];
        if('email'===$contact['kind']){
            try{Domain_Validation::email($contact['value']);}
            catch(\Throwable $ignored){
                throw new Rest_Failure('pc_field_invalid',422,false,'contact');
            }
        }elseif('phone'===$contact['kind']){
            try{Domain_Validation::phone($contact['value']);}
            catch(\Throwable $ignored){
                throw new Rest_Failure('pc_field_invalid',422,false,'contact');
            }
        }elseif(''===\trim($contact['value'])
            || \mb_strlen($contact['value'],'UTF-8')>500
            || \preg_match('/[\x00-\x1F\x7F]/u',$contact['value'])){
            throw new Rest_Failure('pc_field_invalid',422,false,'contact');
        }
    }

    private static function sourceKey(string $kind,string $id):string
    {
        $salt=\wp_salt('auth');
        if(!\is_string($salt)||\strlen($salt)<32){
            throw new Transaction_Failure('pc_privacy_intake_not_ready');
        }
        return \hash_hmac('sha256',
            'privacy-source-v1:'.\get_current_blog_id().':'.$kind.':'.$id,
            $salt
        );
    }

    public static function siteDigest():string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){
            throw new Transaction_Failure('pc_privacy_intake_not_ready');
        }
        return \hash('sha256',$wpdb->prefix.':'.\get_current_blog_id());
    }

    public static function encode(array $value,int $limit=64000):string
    {
        try{$s=\json_encode($value,\JSON_THROW_ON_ERROR
            |\JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES);}
        catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_privacy_intake_invalid');
        }
        if(!\is_string($s)||\strlen($s)>$limit){
            throw new Transaction_Failure('pc_privacy_intake_invalid');
        }
        return $s;
    }
}
