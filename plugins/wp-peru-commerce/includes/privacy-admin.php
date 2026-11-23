<?php
/**
 * IMP-50 — R8-43/44/66: listado ARCO, caso individual y resolución.
 * Solo A + perucommerce_privacy_manage; no pc_cases comercial ni
 * borrado automático de pedidos, Libro, facturas, derechos o fondos.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Privacy_Admin
{
    private const OP='privacy_resolve';
    private const TYPES=['access','rectification','cancellation','opposition'];
    private const DECISIONS=[
        'fulfilled','partially_fulfilled','rejected',
        'retained_due_to_legal_obligation',
    ];
    private const SCOPE=1;

    public static function staffReady():bool
    {
        return 'A'===Access_Roles::currentRole()
            && \current_user_can('perucommerce_privacy_manage')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin')
                && \is_network_admin());
    }

    public static function guard(string $op,array $ctx,?string $role):array
    {
        if('A'!==$role || !self::staffReady()){return [];}
        try{
            if('R8-43'!==$op){
                $id=$ctx['path']['id']??null;
                if(!\is_string($id)){return [];}
                $id=Domain_Validation::sqlId($id);
                $valid=Transaction::run(
                    static fn(Transaction_Context $tx):bool=>
                        self::existsWithin($tx,$id)
                );
                if(!$valid){return [];}
            }
            return [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,'purpose_checked'=>true,
                'legal_case_verified'=>true,
            ];
        }catch(\Throwable $ignored){return [];}
    }

    public static function listing(array $query):array
    {
        self::requireStaff();
        $window=Rest_Pagination::window($query);
        return Transaction::run(static function(Transaction_Context $tx)
            use($window):array{
            $rows=$tx->rows(
                'SELECT id,request_type,received_at,due_at,verified_at '
                    .'FROM %i WHERE JSON_UNQUOTE(JSON_EXTRACT('
                    ."applicant_reference,'$.site_id'))=%s "
                    .'AND JSON_UNQUOTE(JSON_EXTRACT('
                    ."applicant_reference,'$.site_digest'))=%s "
                    .'ORDER BY received_at DESC,id DESC LIMIT %d OFFSET %d',
                [self::table(),(string)\get_current_blog_id(),
                    Privacy_Intake::siteDigest(),
                    $window['fetch'],$window['offset']]
            );
            $entries=[];
            foreach($rows as $row){
                $type=$row['request_type'];
                if(!\in_array($type,self::TYPES,true)){
                    throw new Transaction_Failure('pc_privacy_invariant_invalid');
                }
                $item=[
                    'id'=>Domain_Validation::sqlId((string)$row['id']),
                    'request_type'=>$type,
                    'received_at'=>self::api($row['received_at']),
                    'has_verified_identity'=>null!==$row['verified_at'],
                ];
                if(\is_string($row['due_at'])){
                    $item['due_at']=self::api($row['due_at']);
                }
                $entries[]=$item;
            }
            self::auditReadWithin($tx,
                (string)\get_current_blog_id(),'privacy_list_read',
                'legal_list',
                \hash('sha256',$window['page'].':'.$window['per_page']));
            return Rest_Pagination::envelope($entries,$window);
        });
    }

    public static function detail(string $id):array
    {
        self::requireStaff();
        $id=Domain_Validation::sqlId($id);
        return Transaction::run(static function(Transaction_Context $tx)
            use($id):array{
            $row=self::rowWithin($tx,$id);
            if(null===$row){throw new Transaction_Failure('pc_privacy_not_found');}
            $out=self::projectWithin($tx,$row);
            self::auditReadWithin($tx,$id,'privacy_detail_read',
                'legal_case_review',\hash('sha256','view:'.$id));
            return $out;
        });
    }

    /**
     * La respuesta sin evidencia es propuesta escrita (no resolved_at).
     * Con prueba verificable del MISMO caso/canal/decisión puede finalizarse
     * sin eliminar por inferencia registros de retención obligatoria.
     */
    public static function resolve(string $id,array $input):array
    {
        self::requireStaff();
        if(!Module_Gates::allows('privacy','resolve')
            || !Privacy_Legal::resolutionReady()){
            throw new Transaction_Failure('pc_privacy_not_ready');
        }
        $id=Domain_Validation::sqlId($id);
        self::validate($input);
        $proofDigest=null;
        if(isset($input['evidence'])){
            if(!Module_Gates::allows('privacy','evidence')){
                throw new Transaction_Failure('pc_privacy_not_ready');
            }
            $e=Transaction::run(
                static fn(Transaction_Context $tx):array=>
                    Privacy_Evidence::entryWithin($tx,$id,$input['evidence'])
            );
            Privacy_Evidence::verifiedPath($e);
            $proofDigest=\hash('sha256',Privacy_Intake::encode($e,12000));
        }
        $actor=Event_Actor::staff(['perucommerce_privacy_manage']);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'privacy_request','resource_id'=>$id,
            'purpose'=>self::OP,
        ];
        $payload=$input;unset($payload['idempotency_key']);
        $fingerprint=['decision_sha256'=>\hash('sha256',
            Privacy_Intake::encode($payload,16000))];
        return Transaction::run(static function(Transaction_Context $tx)
            use($id,$input,$actor,$scope,$fingerprint,$proofDigest):array{
            $claim=Idempotency::claimWithin(
                $tx,$scope,self::OP,$input['idempotency_key'],$fingerprint
            );
            if(!\in_array($claim['status'],['new','replay'],true)){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $row=self::rowWithin($tx,$id,true);
            if(null===$row){throw new Transaction_Failure('pc_privacy_not_found');}
            if('replay'===$claim['status']){
                return self::replayWithin($tx,$claim,$id,
                    $input['expected_version']);
            }
            if((string)$row['row_version']!==$input['expected_version']
                || null!==$row['resolved_at']
                || null===$row['verified_at']
                || !\is_string($row['identity_evidence_ref'])){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $identity=Privacy_Evidence::entryWithin($tx,$id,
                $row['identity_evidence_ref']);
            if(!\in_array($identity['purpose'],[
                'identity','representation'
            ],true)){
                throw new Transaction_Failure('pc_privacy_identity_unverified');
            }
            $source=self::decode($row['applicant_reference'],24000);
            $request=self::decode($row['request_snapshot'],48000);
            $case=['id'=>$id,
                'request_type'=>$row['request_type'],
                'received_at'=>self::api($row['received_at']),
                'verified_at'=>self::api($row['verified_at']),
                'source_kind'=>$source['source_kind']??null,
                'source_key'=>$source['source_key']??null,
                'identity_evidence_ref'=>$identity['ref'],
                'source_summary'=>$request['request_summary']??null];
            $decision=[
                'resolution'=>$input['resolution'],
                'legal_basis'=>$input['legal_basis'],
            ];
            $retention=Privacy_Legal::retentionReview($case,$decision);
            Privacy_Legal::requireResolution($case,[
                ...$decision,
                'retention_inventory_version'=>$retention['inventory_version'],
                'retained_categories'=>$retention['retain_categories'],
                'retention_basis_code'=>$retention['basis_code'],
            ]);
            // Estado original del expediente, sin mover solicitud ni fecha.
            $result=self::resultDocument($row['result_reference']);
            $previous=$result['resolution'];
            if(null!==$previous && (
                ($previous['resolution']??null)!==$input['resolution']
                || ($previous['legal_basis']??null)!==$input['legal_basis']
                || ($previous['identity_evidence_ref']??null)!==$identity['ref']
            )){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $clock=$tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
            if(1!==\count($clock)
                || !\is_string($clock[0]['now_utc'])){
                throw new Transaction_Failure('pc_privacy_invariant_invalid');
            }
            $preparedAt=$previous['prepared_at_utc']??self::api(
                $clock[0]['now_utc']
            );
            $preparedBy=$previous['prepared_by']??(string)\get_current_user_id();
            $phase='prepared';
            $proof=null;
            if(isset($input['evidence'])){
                $e=Privacy_Evidence::entryWithin(
                    $tx,$id,$input['evidence']
                );
                if(null===$proofDigest
                    || !\hash_equals($proofDigest,
                        \hash('sha256',Privacy_Intake::encode($e,12000)))
                    || 'delivery'!==$e['purpose']
                    || !\hash_equals(
                        (string)$e['resolution_hash'],
                        \hash('sha256',$input['resolution'].':'
                            .$input['legal_basis'])
                    )){
                    throw new Transaction_Failure('pc_privacy_delivery_unverified');
                }
                try{
                    $at=Business_Time::fromApi(
                        $e['delivered_at_utc']
                    );
                    if($at<Business_Time::fromDatabase(
                        $row['received_at'])
                        || $at>Business_Time::fromDatabase(
                            $clock[0]['now_utc']
                        )){
                        throw new \UnexpectedValueException();
                    }
                }catch(\Throwable $ignored){
                    throw new Transaction_Failure('pc_privacy_delivery_unverified');
                }
                Privacy_Legal::requireDelivery(
                    $case,['proof'=>$e,'decision'=>$decision]
                );
                $proof=$e;$phase='evidenced';
            }elseif(null!==$previous){
                // Sin evidencia no repetir una preparación como finalización.
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $result['resolution']=[
                'phase'=>$phase,
                'resolution'=>$input['resolution'],
                'legal_basis'=>$input['legal_basis'],
                'retention_basis_code'=>$retention['basis_code'],
                'inventory_version'=>$retention['inventory_version'],
                'retain_categories'=>$retention['retain_categories'],
                'identity_evidence_ref'=>$identity['ref'],
                'prepared_at_utc'=>$preparedAt,
                'prepared_by'=>$preparedBy,
                'resolution_evidence_ref'=>$proof['ref']??null,
                'delivery_channel'=>$proof['delivery_channel']??null,
                'delivered_at_utc'=>$proof['delivered_at_utc']??null,
            ];
            $due=\is_string($row['due_at'])?$row['due_at']:null;
            if(null===$due && Privacy_Calendar::ready()){
                $due=Privacy_Calendar::dueFor(
                    $row['received_at'],$row['request_type'],
                    ['source_kind'=>$row['received_channel']]
                )['due_at'];
            }
            $args=[
                self::table(),Privacy_Intake::encode($result,85000),
                $retention['basis_code'],
            ];
            if(null!==$due){$args[]=$due;}
            if(null!==$proof){
                $args[]=$proof['delivery_channel'];
                $args[]=$proof['ref'];
            }
            \array_push($args,$id,$input['expected_version']);
            if(1!==$tx->execute(
                'UPDATE %i SET result_reference=%s,legal_basis_code=%s,'
                    .'due_at='.($due===null?'NULL':'%s').','
                    .'resolved_at='.($proof===null?'NULL':'UTC_TIMESTAMP(6)').','
                    .'response_channel='.($proof===null?'NULL':'%s').','
                    .'response_evidence_ref='.($proof===null?'NULL':'%s').','
                    .'row_version=row_version+1 WHERE id=%s '
                    .'AND row_version=%s AND resolved_at IS NULL',
                $args
            )){
                throw new Transaction_Failure('pc_privacy_conflict');
            }
            $next=Settings_Store::nextVersion($input['expected_version']);
            $event=$proof===null
                ?'privacy_resolution_prepared'
                :'privacy_resolution_evidenced';
            Event_Journal::auditWithin($tx,$actor,$claim['operation_id'],
                'privacy_request',$id,$event,$retention['basis_code'],
                \hash('sha256',$input['expected_version']),
                \hash('sha256',$event.':'.$next.':'
                    .\hash('sha256',$input['resolution'].':'.$input['legal_basis']))
            );
            Queue_Outbox::privacyEventWithin(
                $tx,$actor,$claim['operation_id'],$id,$event
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'privacy_request',$id,200
            );
            return self::result($claim['operation_id'],$id,$next,$event);
        },true);
    }

    /** Scope: tabla física por blog más huella/ID de sitio en origen. */
    public static function rowWithin(
        Transaction_Context $tx,string $id,bool $lock=false
    ):?array {
        $rows=$tx->rows(
            'SELECT id,applicant_reference,request_snapshot,request_type,'
                .'received_channel,received_at,identity_evidence_ref,'
                .'evidence_manifest,verified_at,due_at,resolved_at,'
                .'response_channel,response_evidence_ref,result_reference,'
                .'legal_basis_code,row_version FROM %i WHERE id=%s LIMIT 2'
                .($lock?' FOR UPDATE':''),
            [self::table(),Domain_Validation::sqlId($id)]
        );
        if(1!==\count($rows)){return null;}
        $source=self::decode((string)$rows[0]['applicant_reference'],24000);
        if(($source['site_id']??null)!==(string)\get_current_blog_id()
            || ($source['site_digest']??null)!==Privacy_Intake::siteDigest()
            || !\is_string($source['source_key']??null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',
                $source['source_key'])){
            throw new Transaction_Failure('pc_privacy_invariant_invalid');
        }
        return $rows[0];
    }

    public static function existsWithin(
        Transaction_Context $tx,string $id
    ):bool {
        $rows=$tx->rows(
            'SELECT id FROM %i WHERE id=%s '
                .'AND JSON_UNQUOTE(JSON_EXTRACT('
                ."applicant_reference,'$.site_id'))=%s "
                .'AND JSON_UNQUOTE(JSON_EXTRACT('
                ."applicant_reference,'$.site_digest'))=%s LIMIT 2",
            [self::table(),Domain_Validation::sqlId($id),
                (string)\get_current_blog_id(),Privacy_Intake::siteDigest()]
        );
        return 1===\count($rows);
    }

    public static function projectWithin(
        Transaction_Context $tx,array $row
    ):array {
        $id=Domain_Validation::sqlId((string)$row['id']);
        $source=self::decode($row['applicant_reference'],24000);
        $request=self::decode($row['request_snapshot'],48000);
        if(!\in_array($row['request_type'],self::TYPES,true)
            || ($request['request_type']??null)!==$row['request_type']){
            throw new Transaction_Failure('pc_privacy_invariant_invalid');
        }
        $out=[
            'id'=>$id,'request_type'=>$row['request_type'],
            'received_at'=>self::api($row['received_at']),
            'row_version'=>Domain_Validation::sqlId(
                (string)$row['row_version']
            ),
            'verification_status'=>null!==$row['verified_at']
                ?'verified':(''!==($row['identity_evidence_ref']??'')
                    ?'insufficient':'pending'),
        ];
        if(\is_string($row['verified_at'])){
            $out['verified_at']=self::api($row['verified_at']);
        }
        if(\is_string($source['applicant_name']??null)){
            $out['applicant_name']=$source['applicant_name'];
        }
        if(\is_string($request['request_summary']??null)){
            $out['request_summary']=$request['request_summary'];
        }
        $out['channel']=$row['received_channel'];
        $due=$row['due_at'];
        if(null===$due && Privacy_Calendar::ready()){
            $due=Privacy_Calendar::dueFor(
                $row['received_at'],$row['request_type'],
                ['source_kind'=>$row['received_channel']]
            )['due_at'];
        }
        if(\is_string($due)){$out['due_at']=self::api($due);}
        $evidence=Privacy_Evidence::manifestWithin($tx,$id);
        if([]!==$evidence){
            $out['evidence_refs']=\array_values(\array_map(
                static fn(array $e):string=>$e['ref'],
                \array_filter($evidence,static fn(array $e):bool=>
                    $e['purpose']!=='export' ||
                    Business_Time::fromApi($e['expires_at_utc'])
                        >Business_Time::nowUtc())
            ));
        }
        if(\is_string($row['identity_evidence_ref'])){
            $found=false;
            foreach($evidence as $e){
                if($e['ref']===$row['identity_evidence_ref']
                    && \in_array($e['purpose'],[
                        'identity','representation'
                    ],true)){
                    $found=true;break;
                }
            }
            if(!$found){throw new Transaction_Failure('pc_privacy_invariant_invalid');}
            $out['identity_evidence_ref']=$row['identity_evidence_ref'];
        }
        if(\is_string($row['resolved_at'])){
            $out['resolved_at']=self::api($row['resolved_at']);
        }
        if(\is_string($row['response_channel'])){
            $out['response_channel']=$row['response_channel'];
        }
        if(\is_string($row['response_evidence_ref'])){
            if(!\in_array($row['response_evidence_ref'],
                \array_column($evidence,'ref'),true)){
                throw new Transaction_Failure('pc_privacy_invariant_invalid');
            }
            $out['response_evidence_ref']=$row['response_evidence_ref'];
        }
        if(\is_string($row['result_reference'])){
            $result=self::resultDocument($row['result_reference']);
            $resolution=$result['resolution'];
            if(\is_array($resolution)){
                $out['decision']=$resolution['resolution'];
                $out['legal_basis']=$resolution['legal_basis'];
            }
            $exports=Privacy_Export::publicOperations($result,$id);
            foreach($exports as $operation){
                if($operation['status']==='ready'
                    && !\in_array($operation['evidence_ref']??null,
                        \array_column($evidence,'ref'),true)){
                    throw new Transaction_Failure('pc_privacy_invariant_invalid');
                }
            }
            $out['export_operations']=$exports;
        }
        return $out;
    }

    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $id,string $version
    ):array {
        if('privacy_request'!==($claim['entity_type']??null)
            || $id!==($claim['entity_id']??null)
            || 200!==($claim['response_code']??null)){
            throw new Transaction_Failure('pc_privacy_conflict');
        }
        $audit=$tx->rows(
            'SELECT operation_code FROM %i WHERE entity_type=%s '
                .'AND entity_id=%s AND actor_kind=%s AND actor_user_id=%s '
                .'AND request_id=UNHEX(%s) AND operation_code IN (%s,%s) '
                .'LIMIT 2',
            [self::auditTable(),'privacy_request',$id,'staff',
                (string)\get_current_user_id(),
                Event_Journal::correlationHex($claim['operation_id']),
                'privacy_resolution_prepared','privacy_resolution_evidenced']
        );
        if(1!==\count($audit)){
            throw new Transaction_Failure('pc_privacy_conflict');
        }
        return self::result($claim['operation_id'],$id,
            Settings_Store::nextVersion($version),
            (string)$audit[0]['operation_code']);
    }

    public static function resultDocument(?string $raw):array
    {
        if(null===$raw){
            return ['version'=>1,'resolution'=>null,'export_operations'=>[]];
        }
        $value=self::decode($raw,85000);
        if(($value['version']??null)!==1
            || !\array_key_exists('resolution',$value)
            || !\is_array($value['export_operations']??null)
            || !\array_is_list($value['export_operations'])
            || \count($value['export_operations'])>50){
            throw new Transaction_Failure('pc_privacy_invariant_invalid');
        }
        return $value;
    }

    private static function validate(array $input):void
    {
        $mandatory=['resolution','legal_basis','expected_version','idempotency_key'];
        if([]!==\array_diff($mandatory,\array_keys($input))
            || []!==\array_diff(\array_keys($input),[
                ...$mandatory,'evidence'
            ]) || \count($input)>5
            || !\in_array($input['resolution']??null,
                self::DECISIONS,true)
            || !\is_string($input['legal_basis']??null)
            || !\is_string($input['expected_version']??null)
            || !\is_string($input['idempotency_key']??null)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $basis=$input['legal_basis'];
        $key=$input['idempotency_key'];
        if(''===\trim($basis)
            || !\mb_check_encoding($basis,'UTF-8')
            || \mb_strlen($basis,'UTF-8')>400
            || \preg_match('/[\x00-\x1F\x7F]/u',$basis)
            || !\mb_check_encoding($key,'UTF-8')
            || \mb_strlen($key,'UTF-8')<16 || \mb_strlen($key,'UTF-8')>128
            || \preg_match('/[\x00-\x20\x7F]/',$key)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try{Domain_Validation::sqlId($input['expected_version']);}
        catch(\Throwable $ignored){
            throw new Rest_Failure('pc_field_invalid',422,false,'expected_version');
        }
        if(isset($input['evidence'])
            && (!\is_string($input['evidence'])
                || !\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D',
                    $input['evidence']))){
            throw new Rest_Failure('pc_field_invalid',422,false,'evidence');
        }
    }

    public static function auditReadWithin(
        Transaction_Context $tx,string $id,string $code,
        string $reason,string $digest
    ):void{
        if(!\preg_match('/\A[a-f0-9]{64}\z/D',$digest)){
            throw new Transaction_Failure('pc_privacy_invariant_invalid');
        }
        $scope=\in_array($code,[
            'privacy_list_read','privacy_incident_list_read'
        ],true)?'site':'privacy_request';
        if(1!==$tx->execute(
            'INSERT INTO %i (entity_type,entity_id,operation_code,'
                .'actor_kind,actor_user_id,request_id,reason_code,'
                .'safe_after_hash,occurred_at) '
                .'VALUES (%s,%s,%s,%s,%s,NULL,%s,UNHEX(%s),UTC_TIMESTAMP(6))',
            [self::auditTable(),$scope,
                Domain_Validation::sqlId($id),$code,'staff',
                (string)\get_current_user_id(),$reason,$digest]
        )){
            throw new Transaction_Failure('pc_privacy_invariant_invalid');
        }
    }

    public static function decode(string $raw,int $limit):array
    {
        if(\strlen($raw)>$limit){
            throw new Transaction_Failure('pc_privacy_invariant_invalid');
        }
        try{$v=\json_decode($raw,true,18,\JSON_THROW_ON_ERROR);}
        catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_privacy_invariant_invalid');
        }
        if(!\is_array($v)||\array_is_list($v)){
            throw new Transaction_Failure('pc_privacy_invariant_invalid');
        }
        return $v;
    }

    public static function table():string
    {
        return self::name('pc_privacy_requests');
    }
    private static function auditTable():string
    {
        return self::name('pc_audit_log');
    }
    private static function name(string $name):string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){
            throw new Transaction_Failure('pc_privacy_not_ready');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
        return Schema::tableNames($wpdb)[$name];
    }
    private static function api(string $date):string
    {return Business_Time::toApi(Business_Time::fromDatabase($date));}
    private static function result(
        string $op,string $id,string $version,string $event
    ):array{
        return [
            'operation_id'=>$op,'entity_id'=>$id,
            'row_version'=>$version,'result'=>$event,
            'public_status'=>'privacy_resolution_prepared'===$event
                ?'awaiting_delivery_evidence':'legal_decision_evidenced',
        ];
    }
    private static function requireStaff():void
    {
        if(!self::staffReady()){
            throw new Transaction_Failure('pc_privacy_denied');
        }
    }
}
