<?php
/**
 * IMP-48 — R8-15: ingreso público real, folio local serializado,
 * snapshot legal inmutable y ComplaintReceiptCopy sin consulta pública.
 *
 * El calendario de 15 días HÁBILES no puede adivinar feriados J-01:
 * IMP-49 calcula due_at con calendario verificado, no días corridos.
 * La fecha de ingreso SIEMPRE es UTC del mismo motor SQL.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Complaint_Service
{
    private const OP='complaint_submit';
    private const SCOPE='principal_v1';
    private const SNAPSHOT_MAX=120000;

    /**
     * La página del shortcode no inicia una presentación inválida si aún
     * faltan identidad jurídica/privacidad verificables del comercio.
     * No crea fila de settings ni escribe SQL en una visita GET.
     */
    public static function readyForPage():bool
    {
        if(!Module_Gates::allows('complaints','write')){return false;}
        try{
            return Transaction::run(
                static function(Transaction_Context $tx):bool {
                    $state=Settings_Store::readWithin($tx);
                    if(null===$state['raw']){return false;}
                    self::merchant($state['settings']);
                    if(!Complaint_Calendar::ready()){return false;}
                    $policy=Settings_Store::policyWithin(
                        $tx,(string)$state['settings']['privacy_version_id'],
                        'privacy_notice'
                    );
                    return ''!==\trim($policy);
                }
            );
        }catch(\Throwable $ignored){return false;}
    }

    /** @return array{status:int,body:array<string,mixed>} */
    public static function create(array $body):array
    {
        if(!Module_Gates::allows('complaints','write')){
            throw new Transaction_Failure('pc_complaint_not_ready');
        }
        $digest=Complaint_Form::verifiedDigest();
        self::validate($body);
        $actor=Event_Actor::complaintForm($digest);
        $scope=[
            'actor_kind'=>'form','actor_ref'=>$digest,
            'resource_kind'=>'complaint_form','resource_id'=>'0',
            'purpose'=>self::OP,
        ];
        // El formulario permite hasta 10k+5k caracteres Unicode.
        // La raíz idempotente limita el JSON a 64 KiB; usar una huella
        // exacta de los campos, NO el texto legal, en request_hash.
        // La fuente completa se conserva exclusivamente en snapshots
        // privados del expediente, nunca en pc_idempotency.
        $fingerprint=$body;
        unset($fingerprint['idempotency_key']);
        try{
            $packed=\json_encode($fingerprint,
                \JSON_THROW_ON_ERROR|\JSON_UNESCAPED_UNICODE
                |\JSON_UNESCAPED_SLASHES);
        }catch(\Throwable $ignored){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $payload=['complaint_sha256'=>\hash('sha256',$packed)];
        return Transaction::run(static function(Transaction_Context $tx) use(
            $body,$scope,$payload,$actor
        ):array{
            $claim=Idempotency::claimWithin(
                $tx,$scope,self::OP,$body['idempotency_key'],$payload
            );
            if(!\in_array($claim['status'],['new','replay'],true)){
                throw new Transaction_Failure('pc_complaint_conflict');
            }
            if('replay'===$claim['status']){
                return ['status'=>201,'body'=>self::replayWithin($tx,$claim)];
            }

            // Ancla de serialización SIN tabla 40: la fila de settings
            // versionados se toma FOR UPDATE. No cambiar los settings.
            $state=Settings_Store::readWithin($tx,true);
            if(null===$state['raw']){
                throw new Transaction_Failure('pc_complaint_not_ready');
            }
            $merchant=self::merchant($state['settings']);
            Complaint_Legal::requireWithin($merchant,$body);
            $privacy=(string)$state['settings']['privacy_version_id'];
            // La política realmente publicada debe ser recuperable desde
            // pc_policy_versions; una preferencia no es base de privacidad.
            $notice=Settings_Store::policyWithin(
                $tx,$privacy,'privacy_notice'
            );
            if(''===\trim($notice)){
                throw new Transaction_Failure('pc_complaint_not_ready');
            }

            $seq=self::nextSequenceWithin($tx);
            $clock=$tx->rows('SELECT UTC_TIMESTAMP(6) AS submitted_at');
            if(1!==\count($clock)
                || !\is_string($clock[0]['submitted_at'])){
                throw new Transaction_Failure('pc_complaint_invariant_invalid');
            }
            $submitted=(string)$clock[0]['submitted_at'];
            $deadline=Complaint_Calendar::dueFor($submitted,$merchant);
            $merchantJson=self::encode($merchant);
            $partyJson=self::encode([
                'claimant'=>$body['claimant'],
                'representative'=>$body['representative']??null,
            ]);
            $complaintJson=self::encode([
                'kind'=>$body['kind'],
                'subject'=>$body['subject'],
                'subject_description'=>$body['subject_description']??null,
                'claimed_amount_cents'=>$body['claimed_amount_cents']??null,
                'description'=>$body['description'],
                'request'=>$body['request'],
                'transaction_reference'=>$body['transaction_reference']??null,
                'privacy_notice_version'=>$privacy,
                'legal_calendar_version'=>$deadline['calendar_version'],
            ]);
            if(1!==$tx->execute(
                'INSERT INTO %i (sequence_scope,sequence_number,submitted_at,'
                    .'due_at,merchant_snapshot,complainant_snapshot,'
                    .'complaint_snapshot,evidence_reference,evidence_manifest,'
                    .'responded_at,response_channel,response_delivered_at,'
                    .'response_snapshot,response_evidence,row_version) '
                    .'VALUES (%s,%s,%s,%s,%s,%s,%s,NULL,NULL,'
                    .'NULL,NULL,NULL,NULL,NULL,1)',
                [self::table(),self::SCOPE,$seq,$submitted,
                    $deadline['due_at'],$merchantJson,$partyJson,$complaintJson]
            )){
                throw new Transaction_Failure('pc_complaint_invariant_invalid');
            }
            $last=$tx->rows('SELECT LAST_INSERT_ID() AS id');
            if(1!==\count($last)){
                throw new Transaction_Failure('pc_complaint_invariant_invalid');
            }
            $id=Domain_Validation::sqlId((string)$last[0]['id']);

            // Sin PII ni texto libre en bitácora. El evento es prueba de
            // recepción local en SQL, no de entrega de email.
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],'complaint',$id,
                'complaint_submitted','public_legal_form',null,
                \hash('sha256',$id.':'.$seq.':'.$submitted.':'
                    .\hash('sha256',$merchantJson.$partyJson.$complaintJson))
            );
            Queue_Outbox::complaintReceivedWithin(
                $tx,$actor,$claim['operation_id'],$id
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'complaint',$id,201
            );
            return ['status'=>201,'body'=>self::receipt([
                'sequence_number'=>$seq,
                'submitted_at'=>$submitted,
                'merchant_snapshot'=>$merchantJson,
                'complainant_snapshot'=>$partyJson,
                'complaint_snapshot'=>$complaintJson,
                'due_at'=>$deadline['due_at'],
            ])];
        },true);
    }

    private static function replayWithin(
        Transaction_Context $tx,array $claim
    ):array {
        if('complaint'!==($claim['entity_type']??null)
            || 201!==($claim['response_code']??null)
            || !\is_string($claim['entity_id']??null)){
            throw new Transaction_Failure('pc_complaint_conflict');
        }
        $id=Domain_Validation::sqlId($claim['entity_id']);
        $audit=$tx->rows(
            'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                .'AND operation_code=%s AND actor_kind=%s '
                .'AND request_id=UNHEX(%s) LIMIT 2',
            [self::auditTable(),'complaint',$id,
                'complaint_submitted','form',
                Event_Journal::correlationHex($claim['operation_id'])]
        );
        $rows=$tx->rows(
            'SELECT sequence_number,submitted_at,due_at,'
                .'merchant_snapshot,complainant_snapshot,complaint_snapshot '
                .'FROM %i WHERE id=%s AND sequence_scope=%s LIMIT 2',
            [self::table(),$id,self::SCOPE]
        );
        if(1!==\count($audit)||1!==\count($rows)){
            throw new Transaction_Failure('pc_complaint_conflict');
        }
        // El mismo formulario sigue siendo dueño del replay solamente
        // si la cookie y la cabecera CSRF siguen vigentes (fuera y dentro
        // del TX); sin lookup por folio ni por email.
        Complaint_Form::verifiedDigest();
        return self::receipt($rows[0]);
    }

    /** @return array<string,mixed> */
    private static function receipt(array $row):array
    {
        $merchant=self::decode((string)$row['merchant_snapshot']);
        $parties=self::decode((string)$row['complainant_snapshot']);
        $original=self::decode((string)$row['complaint_snapshot']);
        $submitted=Business_Time::fromDatabase((string)$row['submitted_at']);
        $year=$submitted->format('Y');
        $seq=Domain_Validation::sqlId((string)$row['sequence_number']);
        $number=\str_pad($seq,9,'0',\STR_PAD_LEFT).'-'.$year;

        if(!\is_array($parties['claimant']??null)
            || !\in_array($original['kind']??null,['reclamo','queja'],true)
            || !\in_array($original['subject']??null,['producto','servicio'],true)
            || !\is_string($original['description']??null)
            || !\is_string($original['request']??null)
            || !\is_string($merchant['merchant_name']??null)){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        $copy=[
            'receipt_number'=>$number,
            'submitted_at'=>Business_Time::toApi($submitted),
            'merchant_snapshot'=>$merchant,
            'kind'=>$original['kind'],
            'claimant'=>$parties['claimant'],
            'subject'=>$original['subject'],
            'description'=>$original['description'],
            'request'=>$original['request'],
        ];
        if(\is_array($parties['representative']??null)){
            $copy['representative']=$parties['representative'];
        }
        if(\is_string($original['subject_description']??null)){
            $copy['subject_description']=$original['subject_description'];
        }
        if(\is_string($original['claimed_amount_cents']??null)){
            $copy['claimed_amount_cents']=$original['claimed_amount_cents'];
        }
        if(\is_string($row['due_at']??null)){
            $copy['due_at']=Business_Time::toApi(
                Business_Time::fromDatabase($row['due_at'])
            );
        }
        return [
            'receipt_number'=>$number,
            'submitted_at'=>$copy['submitted_at'],
            'merchant_name'=>$merchant['merchant_name'],
            'receipt_copy'=>$copy,
        ];
    }

    /**
     * UQ(scope,number) + la fila de settings bloqueada = secuencia
     * serializada entre submissions de la misma instalación/establecimiento.
     * La lectura FOR UPDATE es actual incluso bajo REPEATABLE READ.
     */
    private static function nextSequenceWithin(Transaction_Context $tx):string
    {
        $rows=$tx->rows(
            'SELECT sequence_number FROM %i WHERE sequence_scope=%s '
                .'ORDER BY sequence_number DESC LIMIT 1 FOR UPDATE',
            [self::table(),self::SCOPE]
        );
        if([]===$rows){return '1';}
        if(1!==\count($rows)){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        return Settings_Store::nextVersion(
            Domain_Validation::sqlId((string)$rows[0]['sequence_number'])
        );
    }

    /** Datos del proveedor DESDE CONFIGURACIÓN, jamás desde el visitante. */
    private static function merchant(array $settings):array
    {
        $business=$settings['business']??null;
        if(!\is_array($business)
            || 'native'!==($business['complaint_mode']??null)
            || !\is_string($business['legal_name']??null)
            || !\is_string($business['tax_id_ruc']??null)
            || !\is_string($business['premises_address']??null)
            || !\is_string($settings['privacy_version_id']??null)){
            throw new Transaction_Failure('pc_complaint_not_ready');
        }
        $name=\trim($business['legal_name']);
        $address=\trim($business['premises_address']);
        if(''===$name || ''===$address
            || \mb_strlen($name,'UTF-8')>200
            || \mb_strlen($address,'UTF-8')>500
            || \preg_match('/[\x00-\x1F\x7F]/u',$name.$address)
            || !self::validRuc($business['tax_id_ruc'])){
            throw new Transaction_Failure('pc_complaint_not_ready');
        }
        Domain_Validation::sqlId($settings['privacy_version_id']);
        $merchant=['merchant_name'=>$name,
            'tax_id'=>$business['tax_id_ruc'],'address'=>$address];
        $contact=$business['business_contact']??null;
        if(\is_string($contact) && ''!==\trim($contact)
            && \mb_strlen($contact,'UTF-8')<=200
            && !\preg_match('/[\x00-\x1F\x7F]/u',$contact)){
            $merchant['contact']=$contact;
        }
        return $merchant;
    }

    private static function validRuc(string $value):bool
    {
        if(!\preg_match('/\A[0-9]{11}\z/D',$value)){return false;}
        $weights=[5,4,3,2,7,6,5,4,3,2];$sum=0;
        foreach($weights as $i=>$weight){
            $sum+=(int)$value[$i]*$weight;
        }
        return ((11-($sum%11))%10)===(int)$value[10];
    }

    private static function validate(array $body):void
    {
        if(!\in_array($body['kind']??null,['reclamo','queja'],true)
            || !\in_array($body['subject']??null,['producto','servicio'],true)
            || !\is_array($body['claimant']??null)
            || !\is_string($body['description']??null)
            || !\is_string($body['request']??null)
            || !\is_string($body['idempotency_key']??null)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $required=['kind','subject','claimant','description','request','idempotency_key'];
        $allowed=[...$required,'subject_description','claimed_amount_cents',
            'transaction_reference','representative'];
        if([]!==\array_diff($required,\array_keys($body))
            || []!==\array_diff(\array_keys($body),$allowed)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        self::text($body['description'],10000,'description');
        self::text($body['request'],5000,'request');
        if(isset($body['subject_description'])){
            self::text($body['subject_description'],1000,'subject_description');
        } else {
            throw new Rest_Failure('pc_field_invalid',422,false,'subject_description');
        }
        if(isset($body['transaction_reference'])){
            self::text($body['transaction_reference'],100,'transaction_reference');
            if(\str_contains($body['transaction_reference'],'://')){
                throw new Rest_Failure('pc_field_invalid',422,false,'transaction_reference');
            }
        }
        if(isset($body['claimed_amount_cents'])){
            if(!\is_string($body['claimed_amount_cents'])){
                throw new Rest_Failure('pc_field_invalid',422);
            }
            try{Money::fromApi($body['claimed_amount_cents']);}
            catch(\Throwable $ignored){
                throw new Rest_Failure('pc_field_invalid',422,false,'claimed_amount_cents');
            }
        }
        self::party($body['claimant'],'claimant');
        if(isset($body['representative'])){
            if(!\is_array($body['representative'])){
                throw new Rest_Failure('pc_field_invalid',422,false,'representative');
            }
            self::party($body['representative'],'representative');
        }
        $key=$body['idempotency_key'];
        if(!\mb_check_encoding($key,'UTF-8')
            || \mb_strlen($key,'UTF-8')<16 || \mb_strlen($key,'UTF-8')>128
            || \preg_match('/[\x00-\x20\x7F]/',$key)){
            throw new Rest_Failure('pc_field_invalid',422,false,'idempotency_key');
        }
    }

    private static function party(array $person,string $field):void
    {
        $required=['full_name','identification_kind','identification_value','address'];
        $allowed=[...$required,'phone','email'];
        if(\array_is_list($person)
            || []!==\array_diff($required,\array_keys($person))
            || []!==\array_diff(\array_keys($person),$allowed)){
            throw new Rest_Failure('pc_field_invalid',422,false,$field);
        }
        self::text($person['full_name']??null,200,$field.'.full_name');
        self::text($person['address']??null,500,$field.'.address');
        self::text($person['identification_kind']??null,24,
            $field.'.identification_kind');
        self::text($person['identification_value']??null,32,
            $field.'.identification_value');
        if(!\preg_match('/\A[A-Za-z0-9_-]{1,24}\z/D',
                $person['identification_kind'])
            || !\preg_match('/\A[A-Za-z0-9_-]{2,32}\z/D',
                $person['identification_value'])){
            throw new Rest_Failure('pc_field_invalid',422,false,$field);
        }
        $phone=$person['phone']??null;
        $email=$person['email']??null;
        if(null===$phone && null===$email){
            throw new Rest_Failure('pc_field_invalid',422,false,$field.'.phone');
        }
        if(null!==$phone){
            if(!\is_string($phone)){
                throw new Rest_Failure('pc_field_invalid',422,false,$field.'.phone');
            }
            try{Domain_Validation::phone($phone);}
            catch(\Throwable $ignored){
                throw new Rest_Failure('pc_field_invalid',422,false,$field.'.phone');
            }
        }
        if(null!==$email){
            if(!\is_string($email)){
                throw new Rest_Failure('pc_field_invalid',422,false,$field.'.email');
            }
            try{Domain_Validation::email($email);}
            catch(\Throwable $ignored){
                throw new Rest_Failure('pc_field_invalid',422,false,$field.'.email');
            }
        }
    }

    private static function text(mixed $value,int $max,string $field):void
    {
        if(!\is_string($value)||''===\trim($value)
            || !\mb_check_encoding($value,'UTF-8')
            || \mb_strlen($value,'UTF-8')>$max
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)){
            throw new Rest_Failure('pc_field_invalid',422,false,$field);
        }
    }

    private static function encode(array $value):string
    {
        try{
            $json=\json_encode($value,\JSON_THROW_ON_ERROR
                |\JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES);
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        if(!\is_string($json)||\strlen($json)>self::SNAPSHOT_MAX){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        return $json;
    }

    private static function decode(string $json):array
    {
        try{$data=\json_decode($json,true,15,\JSON_THROW_ON_ERROR);}
        catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        if(!\is_array($data)||\array_is_list($data)){
            throw new Transaction_Failure('pc_complaint_invariant_invalid');
        }
        return $data;
    }

    private static function table():string
    {
        return self::name('pc_complaints');
    }

    private static function auditTable():string
    {
        return self::name('pc_audit_log');
    }

    private static function name(string $suffix):string
    {
        global $wpdb;
        if(!$wpdb instanceof \wpdb){
            throw new Transaction_Failure('pc_complaint_not_ready');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
        return Schema::tableNames($wpdb)[$suffix];
    }
}
