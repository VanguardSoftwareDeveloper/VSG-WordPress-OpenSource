<?php
/**
 * IMP-50 — índice append-only de incidentes de privacidad confirmados
 * por canal administrativo; sin datos personales en pc_audit_log,
 * sin tabla extra, cron legal ni notificación automática a ANPD.
 *
 * Los documentos completos quedan bajo custodia de la fuente que verifica
 * el hecho; esta bitácora registra solo categoría, instante y digest.
 */
declare(strict_types=1);
namespace PeruCommerce;
if(!\defined('ABSPATH')){exit;}

final class Privacy_Incident
{
    private static $verifier=null;
    private const KINDS=[
        'unauthorized_access','possible_disclosure',
        'data_integrity','availability_event','retention_issue',
    ];

    public static function registerVerifiedSource(callable $check):void
    {
        if(null!==self::$verifier){
            throw new \LogicException('pc_privacy_incident_source_duplicate');
        }
        self::$verifier=$check;
    }

    /**
     * API INTERNA A, sin POST REST nuevo. El verificador externo debe
     * certificar la fuente y clasificación; no aprobar por mero "true"
     * del navegador ni afirmar automáticamente incidente reportable.
     */
    public static function record(array $input):array
    {
        Bootstrap::loadPrivacy();
        if(!Privacy_Admin::staffReady()
            || !Module_Gates::allows('privacy','incidents')
            || null===self::$verifier){
            throw new Transaction_Failure('pc_privacy_denied');
        }
        $keys=['kind','source_ref','detected_at_utc',
            'evidence_sha256','idempotency_key'];
        if(\count($input)!==\count($keys)
            || []!==\array_diff(\array_keys($input),$keys)
            || !\in_array($input['kind']??null,self::KINDS,true)
            || !\is_string($input['source_ref']??null)
            || !\preg_match('/\A[A-Za-z0-9_.-]{16,128}\z/D',
                $input['source_ref'])
            || !\is_string($input['evidence_sha256']??null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$input['evidence_sha256'])
            || !\is_string($input['detected_at_utc']??null)
            || !\is_string($input['idempotency_key']??null)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try{$date=Business_Time::fromApi($input['detected_at_utc']);}
        catch(\Throwable $ignored){throw new Rest_Failure('pc_field_invalid',422);}
        $key=$input['idempotency_key'];
        if(!\mb_check_encoding($key,'UTF-8')
            || \mb_strlen($key,'UTF-8')<16
            || \mb_strlen($key,'UTF-8')>128
            || \preg_match('/[\x00-\x20\x7F]/',$key)){
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try{
            if(true!==(self::$verifier)(
                ['kind'=>$input['kind'],'source_ref'=>$input['source_ref'],
                 'detected_at_utc'=>$input['detected_at_utc'],
                 'evidence_sha256'=>$input['evidence_sha256']]
            )){
                throw new \UnexpectedValueException();
            }
        }catch(\Throwable $ignored){
            throw new Transaction_Failure('pc_privacy_incident_unverified');
        }
        $actor=Event_Actor::staff(['perucommerce_privacy_manage']);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'system','resource_id'=>'0',
            'purpose'=>'privacy_incident_review',
        ];
        $digest=\hash('sha256',
            \get_current_blog_id().':'.$input['source_ref'].':'
                .$input['kind'].':'.$input['evidence_sha256']);
        $payload=['incident_digest'=>$digest];
        $site=Domain_Validation::sqlId((string)\get_current_blog_id());
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $actor,$scope,$input,$site,$digest,$payload
            ):array{
                $claim=Idempotency::claimWithin(
                    $tx,$scope,'privacy_incident_review',
                    $input['idempotency_key'],$payload
                );
                if('replay'===$claim['status']){
                    if('site'!==($claim['entity_type']??null)
                        || $site!==($claim['entity_id']??null)
                        || 200!==($claim['response_code']??null)){
                        throw new Transaction_Failure('pc_privacy_incident_unverified');
                    }
                    return ['operation_id'=>$claim['operation_id'],
                        'result'=>'already_recorded'];
                }
                if('new'!==$claim['status']){
                    throw new Transaction_Failure('pc_privacy_incident_unverified');
                }
                $settings=Settings_Store::readWithin($tx,true);
                if(null===$settings['raw']){
                    throw new Transaction_Failure('pc_privacy_incident_unverified');
                }
                global $wpdb;
                if(!$wpdb instanceof \wpdb){
                    throw new Transaction_Failure('pc_privacy_incident_unverified');
                }
                require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
                $table=Schema::tableNames($wpdb)['pc_audit_log'];
                $duplicate=$tx->rows(
                    'SELECT id FROM %i WHERE entity_type=%s AND entity_id=%s '
                        .'AND operation_code=%s AND safe_after_hash=UNHEX(%s) '
                        .'LIMIT 2',
                    [$table,'site',$site,'privacy_incident_recorded',$digest]
                );
                if([]!==$duplicate){
                    throw new Transaction_Failure('pc_privacy_incident_unverified');
                }
                $now=$tx->rows('SELECT UTC_TIMESTAMP(6) AS now_utc');
                if(1!==\count($now)
                    || Business_Time::fromApi(
                        $input['detected_at_utc']
                    )>Business_Time::fromDatabase($now[0]['now_utc'])){
                    throw new Transaction_Failure('pc_privacy_incident_unverified');
                }
                Event_Journal::auditWithin(
                    $tx,$actor,$claim['operation_id'],'site',$site,
                    'privacy_incident_recorded',$input['kind'],null,$digest
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'site',$site,200
                );
                return ['operation_id'=>$claim['operation_id'],
                    'result'=>'recorded_for_review'];
            },true
        );
    }
    /**
     * Lectura INTERNA de incidencias verificadas: sin expediente/PII,
     * solo categoría e instante de la bitácora append-only. No es REST.
     */
    public static function listingInternal(int $limit=25):array
    {
        Bootstrap::loadPrivacy();
        if(!Privacy_Admin::staffReady()
            || !Module_Gates::allows('privacy','incidents')
            || $limit<1 || $limit>100){
            throw new Transaction_Failure('pc_privacy_denied');
        }
        $site=Domain_Validation::sqlId((string)\get_current_blog_id());
        return Transaction::run(
            static function(Transaction_Context $tx)use($site,$limit):array{
                global $wpdb;
                if(!$wpdb instanceof \wpdb){
                    throw new Transaction_Failure('pc_privacy_not_ready');
                }
                require_once PERUCOMMERCE_PLUGIN_DIR.'includes/schema.php';
                $table=Schema::tableNames($wpdb)['pc_audit_log'];
                $rows=$tx->rows(
                    'SELECT id,reason_code,occurred_at,'
                        .'HEX(safe_after_hash) AS source_digest '
                        .'FROM %i WHERE entity_type=%s AND entity_id=%s '
                        .'AND operation_code=%s '
                        .'ORDER BY occurred_at DESC,id DESC LIMIT %d',
                    [$table,'site',$site,'privacy_incident_recorded',$limit]
                );
                $out=[];
                foreach($rows as $r){
                    if(!\in_array($r['reason_code'],self::KINDS,true)
                        || !\is_string($r['source_digest'])
                        || !\preg_match('/\A[a-fA-F0-9]{64}\z/D',
                            $r['source_digest'])){
                        throw new Transaction_Failure('pc_privacy_invariant_invalid');
                    }
                    $out[]=[
                        'id'=>Domain_Validation::sqlId((string)$r['id']),
                        'kind'=>$r['reason_code'],
                        'occurred_at'=>Business_Time::toApi(
                            Business_Time::fromDatabase($r['occurred_at'])
                        ),
                        'source_digest'=>\strtolower($r['source_digest']),
                    ];
                }
                Privacy_Admin::auditReadWithin(
                    $tx,$site,'privacy_incident_list_read',
                    'incident_review',\hash('sha256',$limit.':'.$site)
                );
                return $out;
            }
        );
    }

}
