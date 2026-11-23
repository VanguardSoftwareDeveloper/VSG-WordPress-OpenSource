<?php
/** IMP-49 — Libro administrativo R8-41/42/65, no pc_cases comercial. */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Complaint_Admin
{
    private const SCOPE='principal_v1';
    private const OP='complaint_respond';

    public static function staffReady(?string $role):bool
    {
        return 'A'===$role
            && 'A'===Access_Roles::currentRole()
            && \current_user_can('perucommerce_complaints_manage')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin') && \is_network_admin());
    }

    public static function guard(string $op,array $ctx,?string $role):array
    {
        if(!self::staffReady($role)){return [];}
        try{
            if('R8-41'!==$op){
                $id=$ctx['path']['id']??null;
                if(!\is_string($id)){return [];}
                $id=Domain_Validation::sqlId($id);
                $exists=Transaction::run(
                    static fn(Transaction_Context $tx):bool=>
                        self::existsWithin($tx,$id)
                );
                if(!$exists){return [];}
            }
            return ['authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,'purpose_checked'=>true,
                'legal_case_verified'=>true];
        }catch(\Throwable $ignored){return [];}
    }

    public static function listing(array $query):array
    {
        self::requireStaff();
        $page=Rest_Pagination::window($query);
        return Transaction::run(static function(Transaction_Context $tx)
            use($page):array{
            $rows=$tx->rows(
                'SELECT id,sequence_number,submitted_at,responded_at,'
                    ."JSON_UNQUOTE(JSON_EXTRACT(complaint_snapshot,'$.kind')) AS kind "
                    .'FROM %i WHERE sequence_scope=%s '
                    .'ORDER BY submitted_at DESC,id DESC LIMIT %d OFFSET %d',
                [self::table('pc_complaints'),self::SCOPE,
                    $page['fetch'],$page['offset']]
            );
            $items=[];
            foreach($rows as $r){
                if(!\in_array($r['kind']??null,
                    ['reclamo','queja'],true)){
                    throw new Transaction_Failure('pc_complaint_invariant_invalid');
                }
                $item=[
                    'id'=>Domain_Validation::sqlId((string)$r['id']),
                    'receipt_number'=>self::folio($r),
                    'kind'=>$r['kind'],
                    'submitted_at'=>self::api($r['submitted_at']),
                ];
                if(\is_string($r['responded_at'])){
                    $item['responded_at']=self::api($r['responded_at']);
                }
                $items[]=$item;
            }
            self::auditReadWithin($tx,(string)\get_current_blog_id(),
                'complaint_list_read','legal_list',
                \hash('sha256',$page['page'].':'.$page['per_page']));
            return Rest_Pagination::envelope($items,$page);
        });
    }

    public static function detail(string $id):array
    {
        self::requireStaff();
        $id=Domain_Validation::sqlId($id);
        return Transaction::run(
            static function(Transaction_Context $tx)use($id):array{
                $r=self::rowWithin($tx,$id,false);
                if(null===$r){throw new Transaction_Failure('pc_complaint_not_found');}
                $out=self::projectWithin($tx,$r);
                self::auditReadWithin($tx,$id,
                    'complaint_detail_read','legal_case_review',
                    \hash('sha256','view:'.$id));
                return $out;
            }
        );
    }

    public static function respond(string $id,array $input):array
    {
        self::requireStaff();
        $id=Domain_Validation::sqlId($id);
        self::input($input);
        // El expediente puede declarar una referencia válida mientras el
        // binario físico ya no existe. Verificar integridad y MIME real
        // ANTES de la transacción: jamás hacer I/O de archivo dentro de SQL.
        $evidenceDigest=null;
        if(isset($input['evidence'])){
            if(!Module_Gates::allows('complaints','evidence')){
                throw new Transaction_Failure('pc_complaint_not_ready');
            }
            $entry=Transaction::run(
                static fn(Transaction_Context $tx):array=>
                    Complaint_Evidence::entryWithin(
                        $tx,$id,$input['evidence']
                    )
            );
            Complaint_Evidence::verifiedPath($entry);
            $evidenceDigest=\hash('sha256',self::encode($entry,12000));
        }
        $actor=Event_Actor::staff(['perucommerce_complaints_manage']);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'complaint','resource_id'=>$id,
            'purpose'=>self::OP,
        ];
        $copy=$input;unset($copy['idempotency_key']);
        $payload=['response_hash'=>\hash('sha256',self::encode($copy,120000))];
        return Transaction::run(
            static function(Transaction_Context $tx)
            use($id,$input,$actor,$scope,$payload,$evidenceDigest):array{
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OP,$input['idempotency_key'],$payload
                );
                if(!\in_array($claim['status'],['new','replay'],true)){
                    throw new Transaction_Failure('pc_complaint_response_conflict');
                }
                $row=self::rowWithin($tx,$id,true);
                if(null===$row){throw new Transaction_Failure('pc_complaint_not_found');}
                if('replay'===$claim['status']){
                    return self::replayWithin($tx,$id,$input,$claim);
                }
                if((string)$row['row_version']!==$input['expected_version']
                    || null!==$row['responded_at']){
                    throw new Transaction_Failure('pc_complaint_response_conflict');
                }
                $merchant=self::decode((string)$row['merchant_snapshot'],16000);
                $submitted=(string)$row['submitted_at'];
                $due=\is_string($row['due_at'])
                    ?(string)$row['due_at']
                    :Complaint_Calendar::dueFor($submitted,$merchant)['due_at'];
                $original=$row['response_snapshot'];
                if(\is_string($original)){
                    $draft=self::decode($original,120000);
                    if(($draft['response_text']??null)!==$input['response_text']){
                        throw new Transaction_Failure('pc_complaint_response_conflict');
                    }
                }else{
                    $time=$tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
                    if(1!==\count($time)){
                        throw new Transaction_Failure('pc_complaint_invariant_invalid');
                    }
                    $draft=[
                        'response_text'=>$input['response_text'],
                        'prepared_by'=>(string)\get_current_user_id(),
                        'prepared_at_utc'=>$time[0]['now_utc'],
                    ];
                }
                $proof=null;
                if(isset($input['evidence'])){
                    $entry=Complaint_Evidence::entryWithin(
                        $tx,$id,$input['evidence']
                    );
                    if(null===$evidenceDigest || !\hash_equals(
                        $evidenceDigest,\hash('sha256',self::encode($entry,12000))
                    )){
                        throw new Transaction_Failure('pc_complaint_manifest_invalid');
                    }
                    $proof=Complaint_Legal::requireDeliveryEvidenceWithin(
                        $tx,$id,$entry,$input['response_text'],
                        $merchant,$submitted
                    );
                }elseif(\is_string($original)){
                    // No permitir segundo "envío" sin prueba de canal.
                    throw new Transaction_Failure('pc_complaint_response_conflict');
                }
                $proven=null!==$proof;
                $args=[self::table('pc_complaints'),
                    self::encode($draft,120000)];
                if($proven){
                    $args[]=$proof['channel'];
                    $args[]=Business_Time::toDatabase(
                        Business_Time::fromApi($proof['delivered_at_utc'])
                    );
                    $args[]=self::encode([
                        'evidence_ref'=>$proof['ref'],
                        'channel'=>$proof['channel'],
                        'delivered_at_utc'=>$proof['delivered_at_utc'],
                        'verifier_version'=>$proof['verifier_version'],
                        'verified_by'=>(string)\get_current_user_id(),
                    ],12000);
                }
                \array_push($args,$due,$id,self::SCOPE,
                    $input['expected_version']);
                if(1!==$tx->execute(
                    'UPDATE %i SET response_snapshot=%s,'
                        .'responded_at='.($proven?'UTC_TIMESTAMP(6)':'NULL').','
                        .'response_channel='.($proven?'%s':'NULL').','
                        .'response_delivered_at='.($proven?'%s':'NULL').','
                        .'response_evidence='.($proven?'%s':'NULL').','
                        .'due_at=COALESCE(due_at,%s),'
                        .'row_version=row_version+1 WHERE id=%s '
                        .'AND sequence_scope=%s AND row_version=%s '
                        .'AND responded_at IS NULL',
                    $args
                )){
                    throw new Transaction_Failure('pc_complaint_response_conflict');
                }
                $next=Settings_Store::nextVersion($input['expected_version']);
                $event=$proven
                    ?'complaint_response_evidenced':'complaint_response_prepared';
                Event_Journal::auditWithin(
                    $tx,$actor,$claim['operation_id'],'complaint',$id,
                    $event,'legal_response',
                    \hash('sha256',$id.':'.$input['expected_version']),
                    \hash('sha256',$event.':'.$next.':'
                        .\hash('sha256',$input['response_text']).':'
                        .($proof['ref']??'no_delivery'))
                );
                Queue_Outbox::complaintResponseWithin(
                    $tx,$actor,$claim['operation_id'],$id,$event
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'complaint',$id,200
                );
                return self::receipt($claim['operation_id'],$id,$next,$event);
            },true
        );
    }

    /** El guard solo lee existencia; no transporta PII del expediente. */
    public static function existsWithin(
        Transaction_Context $tx,string $id
    ):bool{
        $rows=$tx->rows(
            'SELECT id FROM %i WHERE id=%s AND sequence_scope=%s LIMIT 2',
            [self::table('pc_complaints'),
                Domain_Validation::sqlId($id),self::SCOPE]
        );
        return 1===\count($rows);
    }

    public static function rowWithin(
        Transaction_Context $tx,string $id,bool $lock=false
    ):?array {
        $rows=$tx->rows(
            'SELECT id,sequence_scope,sequence_number,submitted_at,due_at,'
                .'merchant_snapshot,complainant_snapshot,complaint_snapshot,'
                .'evidence_reference,evidence_manifest,responded_at,'
                .'response_channel,response_delivered_at,response_snapshot,'
                .'response_evidence,row_version FROM %i '
                .'WHERE id=%s AND sequence_scope=%s LIMIT 2'
                .($lock?' FOR UPDATE':''),
            [self::table('pc_complaints'),
                Domain_Validation::sqlId($id),self::SCOPE]
        );
        return 1===\count($rows)?$rows[0]:null;
    }

    public static function folio(array $r):string
    {
        $id=Domain_Validation::sqlId((string)$r['sequence_number']);
        return \str_pad($id,9,'0',\STR_PAD_LEFT).'-'
            .Business_Time::fromDatabase((string)$r['submitted_at'])->format('Y');
    }

    private static function projectWithin(Transaction_Context $tx,array $r):array
    {
        $id=Domain_Validation::sqlId((string)$r['id']);
        $merchant=self::decode((string)$r['merchant_snapshot'],16000);
        $parties=self::decode((string)$r['complainant_snapshot'],120000);
        $c=self::decode((string)$r['complaint_snapshot'],120000);
        if(!\is_array($parties['claimant']??null)
            || !\is_string($merchant['merchant_name']??null)
            || !\in_array($c['kind']??null,['reclamo','queja'],true)
            || !\in_array($c['subject']??null,['producto','servicio'],true)
            || !\is_string($c['description']??null)
            || !\is_string($c['request']??null)){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        $out=[
            'id'=>$id,'receipt_number'=>self::folio($r),
            'kind'=>$c['kind'],'submitted_at'=>self::api($r['submitted_at']),
            'row_version'=>Domain_Validation::sqlId((string)$r['row_version']),
            'claimant'=>$parties['claimant'],'subject'=>$c['subject'],
            'description'=>$c['description'],'request'=>$c['request'],
            'merchant'=>['name'=>$merchant['merchant_name']],
        ];
        foreach(['tax_id','address'] as $f){
            if(\is_string($merchant[$f]??null)){
                $out['merchant'][$f]=$merchant[$f];
            }
        }
        if(\is_array($parties['representative']??null)){
            $out['representative']=$parties['representative'];
        }
        foreach(['subject_description','claimed_amount_cents',
                 'privacy_notice_version'] as $f){
            if(\is_string($c[$f]??null)){$out[$f]=$c[$f];}
        }
        $due=$r['due_at'];
        if(null===$due && Complaint_Calendar::ready()){
            $due=Complaint_Calendar::dueFor(
                $r['submitted_at'],$merchant)['due_at'];
        }
        if(\is_string($due)){$out['due_at']=self::api($due);}
        if(\is_string($r['responded_at'])){
            $out['responded_at']=self::api($r['responded_at']);
        }
        if(\is_string($r['response_channel'])){
            $out['response_channel']=$r['response_channel'];
        }
        if(\is_string($r['response_delivered_at'])){
            $out['delivery_confirmed_at']=self::api($r['response_delivered_at']);
        }
        if(\is_string($r['response_snapshot'])){
            $draft=self::decode($r['response_snapshot'],120000);
            if(\is_string($draft['response_text']??null)){
                $out['response_text']=$draft['response_text'];
            }
        }
        $entries=Complaint_Evidence::manifestWithin($tx,$id);
        if([]!==$entries){
            $out['attachments']=\array_map(
                static fn(array $e):string=>$e['ref'],$entries
            );
        }
        if(\is_string($r['response_evidence'])){
            $x=self::decode($r['response_evidence'],12000);
            foreach($entries as $e){
                if($e['ref']===($x['evidence_ref']??null)){
                    $out['response_evidence_ref']=$e['ref'];
                    break;
                }
            }
        }
        return $out;
    }

    private static function replayWithin(
        Transaction_Context $tx,string $id,array $body,array $claim
    ):array{
        if('complaint'!==($claim['entity_type']??null)
            || $id!==($claim['entity_id']??null)
            || 200!==($claim['response_code']??null)){
            throw new Transaction_Failure('pc_complaint_response_conflict');
        }
        $found=$tx->rows(
            'SELECT operation_code FROM %i WHERE entity_type=%s '
                .'AND entity_id=%s AND actor_kind=%s AND actor_user_id=%s '
                .'AND request_id=UNHEX(%s) '
                .'AND operation_code IN (%s,%s) LIMIT 2',
            [self::table('pc_audit_log'),'complaint',$id,'staff',
                (string)\get_current_user_id(),
                Event_Journal::correlationHex($claim['operation_id']),
                'complaint_response_prepared','complaint_response_evidenced']
        );
        if(1!==\count($found)){
            throw new Transaction_Failure('pc_complaint_response_conflict');
        }
        return self::receipt($claim['operation_id'],$id,
            Settings_Store::nextVersion($body['expected_version']),
            (string)$found[0]['operation_code']);
    }

    public static function auditReadWithin(
        Transaction_Context $tx,string $id,string $code,
        string $reason,string $digest
    ):void{
        if(!\preg_match('/\A[a-f0-9]{64}\z/D',$digest)){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        if(1!==$tx->execute(
            'INSERT INTO %i (entity_type,entity_id,operation_code,'
                .'actor_kind,actor_user_id,request_id,reason_code,'
                .'safe_after_hash,occurred_at) '
                .'VALUES (%s,%s,%s,%s,%s,NULL,%s,UNHEX(%s),UTC_TIMESTAMP(6))',
            [self::table('pc_audit_log'),'complaint',
                Domain_Validation::sqlId($id),$code,'staff',
                (string)\get_current_user_id(),$reason,$digest]
        )){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
    }

    public static function decode(string $json,int $max):array
    {
        if(\strlen($json)>$max){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        try{$v=\json_decode($json,true,16,\JSON_THROW_ON_ERROR);}
        catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        if(!\is_array($v)||\array_is_list($v)){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        return $v;
    }

    private static function encode(array $obj,int $max):string
    {
        try{$s=\json_encode($obj,\JSON_THROW_ON_ERROR
            |\JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE);}
        catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        if(!\is_string($s)||\strlen($s)>$max){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        return $s;
    }

    private static function input(array $body):void
    {
        if(!\is_string($body['response_text']??null)
            || !\is_string($body['expected_version']??null)
            || !\is_string($body['idempotency_key']??null)
            || \count($body)<3 || \count($body)>4
            || []!==\array_diff(\array_keys($body),[
                'response_text','expected_version','idempotency_key','evidence'
            ])){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $text=$body['response_text'];
        $key=$body['idempotency_key'];
        if(''===\trim($text)||!\mb_check_encoding($text,'UTF-8')
            || \mb_strlen($text,'UTF-8')>20000
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$text)
            || !\mb_check_encoding($key,'UTF-8')
            || \mb_strlen($key,'UTF-8')<16||\mb_strlen($key,'UTF-8')>128
            || \preg_match('/[\x00-\x20\x7F]/u',$key)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try{Domain_Validation::sqlId($body['expected_version']);}
        catch(\Throwable $ignored){
            throw new Rest_Failure('pc_field_invalid',422,false,'expected_version');
        }
        if(isset($body['evidence']) && (!\is_string($body['evidence'])
            || !\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D',
                $body['evidence']))){
            throw new Rest_Failure('pc_field_invalid',422,false,'evidence');
        }
    }

    private static function requireStaff():void
    {
        if(!self::staffReady(Access_Roles::currentRole())){
            throw new Transaction_Failure('pc_complaint_denied');
        }
    }

    private static function api(string $date):string
    {
        return Business_Time::toApi(Business_Time::fromDatabase($date));
    }

    private static function table(string $suffix):string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){
            throw new Transaction_Failure('pc_complaint_not_ready');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
        return Schema::tableNames($wpdb)[$suffix];
    }

    private static function receipt(
        string $op,string $id,string $version,string $event
    ):array{
        return ['operation_id'=>$op,'entity_id'=>$id,'row_version'=>$version,
            'result'=>$event,
            'public_status'=>'complaint_response_prepared'===$event
                ?'awaiting_delivery_evidence':'delivery_evidence_registered'];
    }
}
