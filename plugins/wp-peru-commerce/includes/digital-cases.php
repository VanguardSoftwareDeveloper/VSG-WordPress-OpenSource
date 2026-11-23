<?php
/**
 * IMP-36 — expedientes digitales y R8-61.
 *
 * Las decisiones son hechos de revisión. No llaman a R8-60, no cambian
 * derechos/stock/pedido y no asientan ajustes ni reembolsos. Para un pedido
 * multiarchivo se exige un caso tipado y ligado a un derecho preciso.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Cases
{
    private const TYPE='digital_access_dispute';
    private const GUEST_TYPE='guest_request_refund';
    private const OPEN='open_review';
    private const OP='digital_case_decide';
    private const OPEN_OP='digital_case_open';
    private const PHASES=[
        'maintain_access'=>'closed_access_maintained',
        'authorize_revocation'=>'closed_revocation_authorized',
        'financial_review'=>'closed_financial_review',
    ];

    /**
     * R8-61: guard por recurso real. Una raíz idempotente anterior solo
     * permite evaluar replay/409; nunca es permiso para otra decisión.
     */
    public static function guard(array $context,?string $role): array
    {
        if ('A'!==$role || !self::staffReady()) { return []; }
        try {
            $caseId=Domain_Validation::sqlId($context['path']['id']??'');
            $body=self::validated($context['body']??null);
            $case=Transaction::run(
                static fn(Transaction_Context $tx):array=>
                    self::scopeWithin($tx,$caseId)
            );
            $prior=Idempotency::keyExistsForScope(
                self::scope($caseId),self::OP,$body['idempotency_key'],$body
            );
            if (!$prior && (
                self::OPEN!==$case['case_phase']
                || null!==$case['resolved_at']
                || (string)$case['row_version']!==$body['expected_version']
            )) { return []; }
            if (!$prior) {
                Transaction::run(static function(Transaction_Context $tx)
                    use($case):void {
                    Digital_Download::rightWithin($tx,$case['right_id']);
                });
            }
            return [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,'digital_case_scope_verified'=>true,
            ];
        } catch (\Throwable $ignored) { return []; }
    }

    /** R8-61: exactamente un cambio durable, retorno seguro en replay. */
    public static function decide(string $caseId,array $body): array
    {
        self::admin();
        $caseId=Domain_Validation::sqlId($caseId);
        $body=self::validated($body);
        $scope=self::scope($caseId);
        $actor=Event_Actor::staff(['perucommerce_digital_files_manage']);
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $caseId,$body,$scope,$actor
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OP,$body['idempotency_key'],$body
                );
                if (!\in_array($claim['status'],['new','replay'],true)) {
                    throw new Transaction_Failure('pc_digital_case_conflict');
                }
                // Lectura de pertenencia antes del orden de locks.
                $pre=self::scopeWithin($tx,$caseId);
                $orderId=$pre['order_id'];
                $lineId=$pre['line_id'];
                $tx->lockOrder($orderId);
                $tx->lockOrderLine($orderId,$lineId);
                $locked=self::lockCaseWithin($tx,$pre);
                $case=self::scopeWithin($tx,$caseId);
                if ($case['right_id']!==$pre['right_id']
                    || $case['file_id']!==$pre['file_id']
                    || $case['order_id']!==$orderId
                    || (string)$locked['row_version']!==(string)$case['row_version']) {
                    throw new Transaction_Failure('pc_digital_case_conflict');
                }
                if ('replay'===$claim['status']) {
                    $expected=self::PHASES[$body['decision']];
                    $version=Settings_Store::nextVersion($body['expected_version']);
                    $rows=$tx->rows(
                        'SELECT decision_code FROM %i WHERE case_id=%s '
                            .'AND idempotency_id=%s AND event_code=%s '
                            .'AND actor_kind=%s LIMIT 2',
                        [Orders_Domain::table('pc_case_events'),$caseId,
                            $claim['operation_id'],'digital_case_decided','staff']
                    );
                    if ('case'!==($claim['entity_type']??null)
                        || ($claim['entity_id']??null)!==$caseId
                        || 200!==($claim['response_code']??null)
                        || $case['case_phase']!==$expected
                        || (string)$case['row_version']!==$version
                        || 1!==\count($rows)
                        || $rows[0]['decision_code']!==$body['decision']) {
                        throw new Transaction_Failure('pc_digital_case_conflict');
                    }
                    return self::result(
                        $caseId,$claim['operation_id'],$expected,$version
                    );
                }
                if (self::OPEN!==$case['case_phase']
                    || null!==$case['resolved_at']
                    || (string)$case['row_version']!==$body['expected_version']
                    || (string)$locked['row_version']!==$body['expected_version']) {
                    throw new Transaction_Failure('pc_digital_case_conflict');
                }
                // Una resolución sobre derecho no habilitado, revocado,
                // impago o archivo reemplazado deja expediente intacto.
                $right=Digital_Download::rightWithin($tx,$case['right_id'],true);
                if ($right['order_id']!==$orderId
                    || $right['order_line_id']!==$lineId
                    || $right['file_id']!==$case['file_id']) {
                    throw new Transaction_Failure('pc_digital_case_conflict');
                }
                $newPhase=self::PHASES[$body['decision']];
                $newVersion=$tx->advanceVersion(
                    'pc_cases',$caseId,$body['expected_version']
                );
                if (1!==$tx->execute(
                    'UPDATE %i SET case_phase=%s,resolved_at=UTC_TIMESTAMP(6) '
                        .'WHERE id=%s AND order_id=%s AND case_phase=%s '
                        .'AND row_version=%s AND resolved_at IS NULL',
                    [Orders_Domain::table('pc_cases'),$newPhase,$caseId,
                        $orderId,self::OPEN,$newVersion]
                )) {
                    throw new Transaction_Failure('pc_digital_case_conflict');
                }

                // Texto y referencia documentales SOLO en expediente
                // protegido; auditoría general contiene hashes, nunca PII.
                $detail=\json_encode([
                    'right_id'=>$case['right_id'],
                    'file_id'=>$case['file_id'],
                    'decision'=>$body['decision'],
                    'reason'=>$body['reason'],
                ],\JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES|\JSON_THROW_ON_ERROR);
                if (!\is_string($detail) || \strlen($detail)>3500
                    || 1!==$tx->execute(
                        'INSERT INTO %i (case_id,idempotency_id,event_code,'
                            .'decision_code,actor_kind,actor_user_id,'
                            .'evidence_ref,safe_payload_snapshot,occurred_at) '
                            .'VALUES (%s,%s,%s,%s,%s,%s,%s,%s,UTC_TIMESTAMP(6))',
                        [Orders_Domain::table('pc_case_events'),$caseId,
                            $claim['operation_id'],'digital_case_decided',
                            $body['decision'],'staff',
                            (string)\get_current_user_id(),
                            $body['evidence_ref'],$detail]
                    )) {
                    throw new Transaction_Failure('pc_digital_case_invalid');
                }
                Event_Journal::auditWithin(
                    $tx,$actor,$claim['operation_id'],
                    'case',$caseId,'digital_case_decided',
                    $body['decision'],
                    \hash('sha256',$body['expected_version'].':'.self::OPEN),
                    \hash('sha256',$newVersion.':'.$newPhase.':'
                        .\hash('sha256',$body['evidence_ref']))
                );
                Orders_Finalization::afterWithin(
                    $tx,$actor,$claim['operation_id'],$orderId
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'case',$caseId,200
                );
                return self::result(
                    $caseId,$claim['operation_id'],$newPhase,$newVersion
                );
            },true
        );
    }

    /**
     * Puerto interno, SIN ruta REST: crear caso específico de un archivo
     * desde un R8-12 guest_request_refund auténtico. Hace falta para
     * pedidos mixtos o con varios archivos: el ID de pedido no identifica
     * qué derecho se está reclamando.
     *
     * No consume el caso guest ni decide un refund; conserva ambos expedientes.
     */
    public static function openFromGuest(
        string $guestCaseId,string $rightId,string $reviewReason,
        string $idempotencyKey
    ): array {
        self::admin();
        if (!\current_user_can('perucommerce_orders_read')) {
            throw new Transaction_Failure('pc_digital_case_denied');
        }
        $guestCaseId=Domain_Validation::sqlId($guestCaseId);
        $rightId=Domain_Validation::sqlId($rightId);
        self::safeText($reviewReason,1000,'pc_digital_case_input_invalid');
        if (!\is_string($idempotencyKey)
            || !\mb_check_encoding($idempotencyKey,'UTF-8')
            || \mb_strlen($idempotencyKey,'UTF-8')<16
            || \mb_strlen($idempotencyKey,'UTF-8')>128
            || \preg_match('/[\x00-\x20\x7F]/',$idempotencyKey)) {
            throw new Transaction_Failure('pc_digital_case_input_invalid');
        }
        $scope=[
            'actor_kind'=>'staff','actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'digital_right','resource_id'=>$rightId,
            'purpose'=>self::OPEN_OP,
        ];
        $payload=[
            'source_case_id'=>$guestCaseId,'right_id'=>$rightId,
            'reason_sha256'=>\hash('sha256',$reviewReason),
        ];
        $actor=Event_Actor::staff([
            'perucommerce_digital_files_manage','perucommerce_orders_read'
        ]);
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $scope,$payload,$idempotencyKey,$rightId,$guestCaseId,
                $reviewReason,$actor
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OPEN_OP,$idempotencyKey,$payload
                );
                if ('replay'===$claim['status']) {
                    $caseId=Domain_Validation::sqlId(
                        (string)($claim['entity_id']??'')
                    );
                    if ('case'!==($claim['entity_type']??null)
                        || 200!==($claim['response_code']??null)) {
                        throw new Transaction_Failure('pc_digital_case_conflict');
                    }
                    $case=self::scopeWithin($tx,$caseId);
                    if ($case['case_type']!==self::TYPE
                        || $case['right_id']!==$rightId
                        || $case['source_guest_case_id']!==$guestCaseId) {
                        throw new Transaction_Failure('pc_digital_case_conflict');
                    }
                    return [
                        'case_id'=>$caseId,
                        'operation_id'=>$claim['operation_id'],
                        'row_version'=>(string)$case['row_version'],
                    ];
                }
                if ('new'!==$claim['status']) {
                    throw new Transaction_Failure('pc_digital_case_conflict');
                }
                $right=Digital_Download::rightWithin($tx,$rightId);
                $orderId=$right['order_id'];
                $lineId=$right['order_line_id'];
                $tx->lockOrder($orderId);
                $tx->lockOrderLine($orderId,$lineId);
                $source=$tx->lockCaseForOrder($orderId,$guestCaseId);
                if ($source['case_type']!==self::GUEST_TYPE
                    || $source['case_phase']!==self::OPEN
                    || null!==$source['order_line_id']
                    || !self::sourceGuestWithin($tx,$guestCaseId,$orderId)) {
                    throw new Transaction_Failure('pc_digital_case_denied');
                }
                $others=$tx->rows(
                    'SELECT id,evidence_snapshot FROM %i WHERE order_id=%s '
                        .'AND order_line_id=%s AND case_type=%s '
                        .'AND case_phase=%s ORDER BY id LIMIT 201',
                    [Orders_Domain::table('pc_cases'),$orderId,$lineId,
                        self::TYPE,self::OPEN]
                );
                if (\count($others)>200) {
                    throw new Transaction_Failure('pc_digital_case_conflict');
                }
                foreach ($others as $other) {
                    $snap=Orders_Domain::decode(
                        (string)$other['evidence_snapshot'],10000
                    );
                    if (($snap['right_id']??null)===$rightId) {
                        throw new Transaction_Failure('pc_digital_case_conflict');
                    }
                }
                $snapshot=\json_encode([
                    'purpose'=>'request_refund',
                    'reason'=>$reviewReason,
                    'case_details'=>'Derecho digital '.$rightId.'; solicitud invitada '.$guestCaseId,
                    'right_id'=>$rightId,'file_id'=>$right['file_id'],
                    'source_guest_case_id'=>$guestCaseId,
                ],\JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES|\JSON_THROW_ON_ERROR);
                if (!\is_string($snapshot) || \strlen($snapshot)>6500
                    || 1!==$tx->execute(
                        'INSERT INTO %i (order_id,order_line_id,case_type,'
                            .'source_operation_id,case_phase,reason_code,'
                            .'created_by,assigned_to,evidence_snapshot,'
                            .'opened_at,resolved_at,row_version) '
                            .'VALUES (%s,%s,%s,NULL,%s,%s,%s,NULL,%s,'
                            .'UTC_TIMESTAMP(6),NULL,1)',
                        [Orders_Domain::table('pc_cases'),$orderId,$lineId,
                            self::TYPE,self::OPEN,'buyer_digital_request',
                            (string)\get_current_user_id(),$snapshot]
                    )) {
                    throw new Transaction_Failure('pc_digital_case_invalid');
                }
                $last=$tx->rows('SELECT LAST_INSERT_ID() AS id');
                if (1!==\count($last)) {
                    throw new Transaction_Failure('pc_digital_case_invalid');
                }
                $caseId=Domain_Validation::sqlId((string)$last[0]['id']);
                $tx->lockCaseForLine($orderId,$lineId,$caseId);
                // Comprobar el derecho original bajo lock DESPUÉS de
                // las filas de pedido, línea y casos (orden E-05).
                $lockedRight=Digital_Download::rightWithin($tx,$rightId,true);
                if ($lockedRight['order_id']!==$orderId
                    || $lockedRight['order_line_id']!==$lineId
                    || $lockedRight['file_id']!==$right['file_id']) {
                    throw new Transaction_Failure('pc_digital_case_conflict');
                }
                Event_Journal::caseWithin(
                    $tx,$actor,$claim['operation_id'],$caseId,
                    'digital_case_opened',null,'buyer_digital_request'
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'case',$caseId,200
                );
                return [
                    'case_id'=>$caseId,
                    'operation_id'=>$claim['operation_id'],
                    'row_version'=>'1',
                ];
            },true
        );
    }

    /** Autorizador interno de IMP-23/R8-60: jamás decide el caso. */
    public static function verifiesRevocationWithin(
        Transaction_Context $tx,array $case,array $event,array $right
    ): bool {
        try {
            if (!self::staffReady()
                || ($event['event_code']??null)!=='digital_case_decided'
                || ($event['decision_code']??null)!=='authorize_revocation'
                || ($case['case_phase']??null)!==
                    self::PHASES['authorize_revocation']
                || null===($case['resolved_at']??null)
                || ($event['actor_kind']??null)!=='staff'
                || null===($event['actor_user_id']??null)
                || null===($event['idempotency_id']??null)
                || !\is_string($event['safe_payload_snapshot']??null)) {
                return false;
            }
            $caseId=Domain_Validation::sqlId((string)$case['id']);
            $data=Orders_Domain::decode(
                $event['safe_payload_snapshot'],3500
            );
            if (($data['right_id']??null)!==(string)$right['id']
                || ($data['file_id']??null)!==(string)$right['file_id']
                || ($data['decision']??null)!=='authorize_revocation'
                || !\is_string($data['reason']??null)
                || ''===\trim($data['reason'])) {
                return false;
            }
            $root=$tx->rows(
                'SELECT id FROM %i WHERE id=%s AND operation_type=%s '
                    .'AND technical_phase=%s AND entity_type=%s '
                    .'AND entity_id=%s AND response_code=%d LIMIT 2',
                [Orders_Domain::table('pc_idempotency'),
                    Domain_Validation::sqlId((string)$event['idempotency_id']),
                    self::OP,'complete','case',
                    $caseId,200]
            );
            if (1!==\count($root)) { return false; }
            $scoped=self::scopeWithin($tx,$caseId);
            return $scoped['right_id']===(string)$right['id']
                && $scoped['file_id']===(string)$right['file_id']
                && $scoped['order_id']===(string)$right['order_id']
                && ($scoped['case_phase']??null)===$case['case_phase'];
        } catch (\Throwable $ignored) { return false; }
    }

    /** Identifica caso y derecho exactos con procedencia autenticada. */
    private static function scopeWithin(
        Transaction_Context $tx,string $caseId
    ): array {
        $rows=$tx->rows(
            'SELECT id,order_id,order_line_id,case_type,case_phase,'
                .'row_version,resolved_at,evidence_snapshot,source_operation_id,'
                .'created_by FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_cases'),$caseId]
        );
        if (1!==\count($rows) || null===$rows[0]['order_id']
            || null!==$rows[0]['source_operation_id']) {
            throw new Transaction_Failure('pc_digital_case_denied');
        }
        $case=$rows[0];
        $orderId=Domain_Validation::sqlId((string)$case['order_id']);
        $rightId=null;$fileId=null;$lineId=null;$sourceId=null;
        if (self::TYPE===(string)$case['case_type']) {
            if (null===$case['order_line_id']
                || null===$case['created_by']
                || !\is_string($case['evidence_snapshot'])) {
                throw new Transaction_Failure('pc_digital_case_denied');
            }
            $snap=Orders_Domain::decode(
                $case['evidence_snapshot'],10000
            );
            if (($snap['purpose']??null)!=='request_refund') {
                throw new Transaction_Failure('pc_digital_case_denied');
            }
            $rightId=Domain_Validation::sqlId(
                (string)($snap['right_id']??'')
            );
            $fileId=Domain_Validation::sqlId(
                (string)($snap['file_id']??'')
            );
            $lineId=Domain_Validation::sqlId(
                (string)$case['order_line_id']
            );
            $sourceId=Domain_Validation::sqlId(
                (string)($snap['source_guest_case_id']??'')
            );
            if (!self::sourceGuestWithin($tx,$sourceId,$orderId)
                || !self::staffOriginWithin(
                    $tx,$caseId,(string)$case['created_by']
                )) {
                throw new Transaction_Failure('pc_digital_case_denied');
            }
        } elseif (self::GUEST_TYPE===(string)$case['case_type']) {
            if (null!==$case['order_line_id']
                || null!==$case['created_by']
                || !self::sourceGuestWithin($tx,$caseId,$orderId)) {
                throw new Transaction_Failure('pc_digital_case_denied');
            }
            // No elegir arbitrariamente un archivo de un pedido mixto.
            // Sólo resolver directamente el caso guest de 1 archivo
            // y exclusivamente digital; los demás usan caso tipado.
            $notDigital=$tx->rows(
                'SELECT id FROM %i WHERE order_id=%s AND kind<>%s LIMIT 1',
                [Orders_Domain::table('pc_order_lines'),$orderId,'digital']
            );
            $rights=$tx->rows(
                'SELECT r.id,r.file_id,r.order_line_id '
                    .'FROM %i r INNER JOIN %i l ON l.id=r.order_line_id '
                    .'WHERE l.order_id=%s AND l.kind=%s ORDER BY r.id LIMIT 2',
                [Orders_Domain::table('pc_download_rights'),
                    Orders_Domain::table('pc_order_lines'),
                    $orderId,'digital']
            );
            if ([]!==$notDigital || 1!==\count($rights)) {
                throw new Transaction_Failure('pc_digital_case_denied');
            }
            $rightId=Domain_Validation::sqlId((string)$rights[0]['id']);
            $fileId=Domain_Validation::sqlId((string)$rights[0]['file_id']);
            $lineId=Domain_Validation::sqlId((string)$rights[0]['order_line_id']);
            $sourceId=$caseId;
        } else {
            throw new Transaction_Failure('pc_digital_case_denied');
        }
        $owned=$tx->rows(
            'SELECT r.id FROM %i r INNER JOIN %i l '
                .'ON l.id=r.order_line_id WHERE r.id=%s AND r.file_id=%s '
                .'AND r.order_line_id=%s AND l.order_id=%s AND l.kind=%s LIMIT 2',
            [Orders_Domain::table('pc_download_rights'),
                Orders_Domain::table('pc_order_lines'),$rightId,$fileId,
                $lineId,$orderId,'digital']
        );
        if (1!==\count($owned)) {
            throw new Transaction_Failure('pc_digital_case_denied');
        }
        return \array_merge($case,[
            'id'=>$caseId,'order_id'=>$orderId,'line_id'=>$lineId,
            'right_id'=>$rightId,'file_id'=>$fileId,
            'source_guest_case_id'=>$sourceId,
        ]);
    }

    /** Probar R8-12 real, original inmutable y primer evento guest. */
    private static function sourceGuestWithin(
        Transaction_Context $tx,string $guestCaseId,string $orderId
    ): bool {
        $rows=$tx->rows(
            'SELECT c.evidence_snapshot FROM %i c '
                .'INNER JOIN %i e ON e.case_id=c.id '
                .'AND e.event_code=%s AND e.actor_kind=%s '
                .'INNER JOIN %i i ON i.id=e.idempotency_id '
                .'AND i.operation_type=%s AND i.technical_phase=%s '
                .'AND i.entity_type=%s AND i.entity_id=c.id AND i.response_code=%d '
                .'WHERE c.id=%s AND c.order_id=%s AND c.case_type=%s '
                .'AND c.order_line_id IS NULL AND c.source_operation_id IS NULL '
                .'AND c.created_by IS NULL AND e.actor_user_id IS NULL '
                .'AND NOT EXISTS '
                .'(SELECT 1 FROM %i older WHERE older.case_id=c.id '
                .'AND older.id<e.id) LIMIT 2',
            [Orders_Domain::table('pc_cases'),
                Orders_Domain::table('pc_case_events'),
                'guest_request_opened','guest',
                Orders_Domain::table('pc_idempotency'),
                'guest_case_request','complete','case',202,
                $guestCaseId,$orderId,self::GUEST_TYPE,
                Orders_Domain::table('pc_case_events')]
        );
        if (1!==\count($rows)
            || !\is_string($rows[0]['evidence_snapshot'])) {
            return false;
        }
        try {
            $original=Orders_Domain::decode(
                $rows[0]['evidence_snapshot'],10000
            );
            // R8-12 permite reason opcional; la procedencia se prueba por
            // root idempotente+primer evento, no por texto que el comprador
            // pudo omitir legítimamente.
            return ($original['purpose']??null)==='request_refund';
        } catch (\Throwable $ignored) { return false; }
    }

    /** Prueba del primer evento staff de creación del caso tipado. */
    private static function staffOriginWithin(
        Transaction_Context $tx,string $caseId,string $staffId
    ): bool {
        $rows=$tx->rows(
            'SELECT e.id FROM %i e INNER JOIN %i i '
                .'ON i.id=e.idempotency_id AND i.operation_type=%s '
                .'AND i.technical_phase=%s AND i.entity_type=%s '
                .'AND i.entity_id=e.case_id AND i.response_code=%d '
                .'WHERE e.case_id=%s AND e.event_code=%s '
                .'AND e.actor_kind=%s AND e.actor_user_id=%s '
                .'AND NOT EXISTS (SELECT 1 FROM %i older '
                .'WHERE older.case_id=e.case_id AND older.id<e.id) LIMIT 2',
            [Orders_Domain::table('pc_case_events'),
                Orders_Domain::table('pc_idempotency'),
                self::OPEN_OP,'complete','case',200,$caseId,
                'digital_case_opened','staff',$staffId,
                Orders_Domain::table('pc_case_events')]
        );
        return 1===\count($rows);
    }

    private static function lockCaseWithin(
        Transaction_Context $tx,array $scope
    ): array {
        return self::TYPE===$scope['case_type']
            ? $tx->lockCaseForLine(
                $scope['order_id'],$scope['line_id'],(string)$scope['id']
            )
            : $tx->lockCaseForOrder(
                $scope['order_id'],(string)$scope['id']
            );
    }

    private static function validated(mixed $body): array
    {
        if (!\is_array($body) || \array_is_list($body)
            || \count($body)!==5) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $keys=\array_keys($body);
        $expected=['decision','evidence_ref','reason',
            'expected_version','idempotency_key'];
        \sort($keys,\SORT_STRING);\sort($expected,\SORT_STRING);
        if ($keys!==$expected
            || !\is_string($body['decision'])
            || !isset(self::PHASES[$body['decision']])
            || !\is_string($body['expected_version'])
            || !\is_string($body['idempotency_key'])
            || !\mb_check_encoding($body['idempotency_key'],'UTF-8')
            || \mb_strlen($body['idempotency_key'],'UTF-8')<16
            || \mb_strlen($body['idempotency_key'],'UTF-8')>128
            || \preg_match('/[\x00-\x20\x7F]/',$body['idempotency_key'])) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        Domain_Validation::sqlId($body['expected_version']);
        self::safeText($body['reason'],1000,'pc_field_invalid');
        self::safeText($body['evidence_ref'],500,'pc_field_invalid');
        // El consumidor R8-60 exige una referencia no-URL, sin controles
        // y con límite de 500 bytes. Rechazar aquí la evidencia incompatible
        // evita aprobar un caso cuya autorización no se podrá aplicar.
        if (\strlen($body['evidence_ref'])>500
            || \preg_match('/[\x00-\x1F\x7F]/u',$body['evidence_ref'])
            || \str_contains($body['evidence_ref'],'://')) {
            throw new Rest_Failure('pc_field_invalid',422,false,'evidence_ref');
        }
        return $body;
    }

    private static function safeText(
        mixed $value,int $max,string $failure
    ): void {
        if (!\is_string($value) || ''===\trim($value)
            || !\mb_check_encoding($value,'UTF-8')
            || \mb_strlen($value,'UTF-8')>$max
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)) {
            if ('pc_field_invalid'===$failure) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
            throw new Transaction_Failure($failure);
        }
    }

    private static function staffReady(): bool
    {
        return 'A'===Access_Roles::currentRole()
            && \current_user_can('perucommerce_digital_files_manage')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin')&&\is_network_admin())
            && Module_Gates::allows('digital','cases');
    }

    private static function admin(): void
    {
        if (!self::staffReady()) {
            throw new Transaction_Failure('pc_digital_case_denied');
        }
    }

    private static function scope(string $caseId): array
    {
        return [
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'case',
            'resource_id'=>$caseId,
            'purpose'=>self::OP,
        ];
    }

    private static function result(
        string $caseId,string $operationId,string $phase,string $version
    ): array {
        return [
            'case_id'=>$caseId,'operation_id'=>$operationId,
            'case_phase'=>$phase,'row_version'=>$version,
        ];
    }
}
