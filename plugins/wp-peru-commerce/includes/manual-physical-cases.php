<?php
/**
 * IMP-40 — D-199/D-200: expedientes de reduccion fisica parcial R8-69/70.
 *
 * Solo el administrador A comprueba manualmente el control del telefono
 * ORIGINAL y un dato PRIVADO del pedido; el servidor comprueba que ese
 * canal/dato estan disponibles, la declaracion estructurada y la trazabilidad
 * del actor. No acepta un caller ID, un booleano aislado ni un dato literal
 * de identidad. La veracidad de la comprobacion humana requiere P-C.
 *
 * Ninguna de estas operaciones cambia estado comercial, stock, cita,
 * importe, derechos o reembolsos. R8-26 es el unico consumidor futuro de
 * una solicitud abierta para realizar el ajuste y cerrarla con su evento.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Manual_Physical_Cases
{
    private const TYPE='physical_partial_quantity_adjustment';
    private const OPEN='open_review';
    private const OPEN_OP='manual_case_open';
    private const CLOSE_OP='manual_case_close';
    private const REASON='manual_partial_request';
    private const PHASES=[
        'reject'=>'closed_rejected',
        'buyer_withdrawn'=>'closed_withdrawn',
    ];
    private const EVENTS=[
        'reject'=>'manual_case_rejected',
        'buyer_withdrawn'=>'manual_case_withdrawn',
    ];
    private const CAPABILITIES=[
        'perucommerce_orders_read','perucommerce_orders_financial_adjust',
    ];

    /** R8-69: guard sin writes, revalidado por la mutacion bajo locks. */
    public static function guardOpen(array $ctx,?string $role): array
    {
        if (!self::adminReady($role)) { return []; }
        try {
            $orderId=Domain_Validation::sqlId($ctx['path']['id']??'');
            $body=self::openInput($ctx['body']??null);
            $lineId=$body['line_id'];
            $valid=Transaction::run(
                static function(Transaction_Context $tx) use($orderId,$lineId,$body):bool {
                    $rows=$tx->rows(
                        'SELECT id,kind FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
                        [Orders_Domain::table('pc_order_lines'),$lineId,$orderId]
                    );
                    return 1===\count($rows)
                        && 'physical'===$rows[0]['kind']
                        && self::verificationWithin($tx,$orderId,$body['verification']);
                }
            );
            return $valid ? self::proof(true) : [];
        } catch (\Throwable $ignored) { return []; }
    }

    /** R8-70: retiro solo tras NUEVA verificacion del canal original. */
    public static function guardClose(array $ctx,?string $role): array
    {
        if (!self::adminReady($role)) { return []; }
        try {
            $orderId=Domain_Validation::sqlId($ctx['path']['id']??'');
            $caseId=Domain_Validation::sqlId($ctx['path']['case_id']??'');
            $body=self::closeInput($ctx['body']??null);
            $valid=Transaction::run(
                static function(Transaction_Context $tx) use($orderId,$caseId,$body):bool {
                    $rows=$tx->rows(
                        'SELECT c.order_line_id,l.kind FROM %i c '
                            .'INNER JOIN %i l ON l.id=c.order_line_id '
                            .'AND l.order_id=c.order_id '
                            .'WHERE c.id=%s AND c.order_id=%s AND c.case_type=%s '
                            .'AND c.source_operation_id IS NULL LIMIT 2',
                        [Orders_Domain::table('pc_cases'),
                            Orders_Domain::table('pc_order_lines'),$caseId,
                            $orderId,self::TYPE]
                    );
                    if (1!==\count($rows) || 'physical'!==$rows[0]['kind']) {
                        return false;
                    }
                    return $body['decision']==='reject'
                        || self::verificationWithin(
                            $tx,$orderId,$body['withdrawal_verification']
                        );
                }
            );
            if (!$valid) { return []; }
            return \array_merge(self::proof(false),[
                'decision'=>$body['decision'],
                'reason_verified'=>true,
                'withdrawal_reverified'=>$body['decision']==='buyer_withdrawn',
                'buyer_identity_verified'=>$body['decision']==='buyer_withdrawn',
            ]);
        } catch (\Throwable $ignored) { return []; }
    }

    /** 201 nuevo; 200 replay historico, incluso si despues se cerro. */
    public static function open(string $orderId,array $body): array
    {
        self::admin();
        $orderId=Domain_Validation::sqlId($orderId);
        $body=self::openInput($body);
        $lineId=$body['line_id'];
        $scope=self::scope($lineId,self::OPEN_OP);
        $payload=\array_merge(['order_id'=>$orderId],$body);
        $actor=Event_Actor::staff(self::CAPABILITIES);
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $orderId,$lineId,$body,$scope,$payload,$actor
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OPEN_OP,$body['idempotency_key'],$payload
                );
                if (!\in_array($claim['status'],['new','replay'],true)) {
                    throw new Transaction_Failure('pc_manual_case_conflict');
                }
                $order=$tx->lockOrder($orderId);
                $line=$tx->lockOrderLine($orderId,$lineId);
                if ('physical'!==(string)$line['kind']) {
                    throw new Transaction_Failure('pc_manual_case_denied');
                }
                if ($claim['status']==='replay') {
                    return [
                        'status'=>200,
                        'body'=>self::openedReplayWithin(
                            $tx,$claim,$orderId,$lineId
                        ),
                    ];
                }
                if ('CONFIRMADO'!==(string)$order['status']
                    || (string)$order['row_version']!==$body['expected_order_version']
                    || (string)$line['row_version']!==$body['expected_line_version']
                    || !\in_array((string)$line['fulfillment_state'],[
                        'PENDIENTE','PREPARANDO','LISTO',
                    ],true)
                    || !self::verificationWithin(
                        $tx,$orderId,$body['verification']
                    )) {
                    throw new Transaction_Failure('pc_manual_case_conflict');
                }
                $counts=self::physicalCountsWithin($tx,$order,$line);
                if ($body['requested_affected_units']<1
                    || $body['requested_affected_units']
                        >=$counts['pending_stock_out_units']
                    || $counts['stock_out_units']!==0) {
                    throw new Transaction_Failure('pc_manual_case_conflict');
                }
                $existing=$tx->rows(
                    'SELECT id FROM %i WHERE order_id=%s AND order_line_id=%s '
                        .'AND case_type=%s AND case_phase=%s LIMIT 2',
                    [Orders_Domain::table('pc_cases'),$orderId,$lineId,
                        self::TYPE,self::OPEN]
                );
                if ([]!==$existing) {
                    throw new Transaction_Failure('pc_manual_case_already_open');
                }
                $snapshot=self::encode([
                    'purpose'=>self::TYPE,
                    'reason'=>$body['reason'],
                    'requested_affected_units'=>$body['requested_affected_units'],
                    'manual_verification_basis'=>'registered_phone_plus_nonpublic_order_fact',
                    'verification'=>[
                        'contact_control_method'=>$body['verification']['contact_control_method'],
                        'nonpublic_fact_category'=>$body['verification']['nonpublic_fact_category'],
                        'safe_audit_note'=>$body['verification']['safe_audit_note'],
                        'registered_phone_control_confirmed'=>true,
                        'nonpublic_order_fact_confirmed'=>true,
                    ],
                    'line_id'=>$lineId,
                    'order_id'=>$orderId,
                ],6000);
                if (1!==$tx->execute(
                    'INSERT INTO %i (order_id,order_line_id,case_type,source_operation_id,'
                        .'case_phase,reason_code,created_by,assigned_to,evidence_snapshot,'
                        .'opened_at,resolved_at,row_version) VALUES '
                        .'(%s,%s,%s,NULL,%s,%s,%s,NULL,%s,UTC_TIMESTAMP(6),NULL,1)',
                    [Orders_Domain::table('pc_cases'),$orderId,$lineId,self::TYPE,
                        self::OPEN,self::REASON,(string)\get_current_user_id(),$snapshot]
                )) {
                    throw new Transaction_Failure('pc_manual_case_invalid');
                }
                $caseId=self::lastId($tx);
                $tx->lockCaseForLine($orderId,$lineId,$caseId);
                Event_Journal::caseWithin(
                    $tx,$actor,$claim['operation_id'],$caseId,
                    'manual_case_opened',null,self::REASON,null,[
                        'requested_affected_units'=>$body['requested_affected_units'],
                        'contact_control_method'=>$body['verification']['contact_control_method'],
                        'nonpublic_fact_category'=>$body['verification']['nonpublic_fact_category'],
                    ]
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'case',$caseId,201
                );
                return ['status'=>201,'body'=>self::openedWithin(
                    $tx,$caseId,$orderId,$lineId,$claim['operation_id']
                )];
            },true
        );
    }

    /** 200 cierre o replay. No existe "approve" en R8-70. */
    public static function close(string $orderId,string $caseId,array $body): array
    {
        self::admin();
        $orderId=Domain_Validation::sqlId($orderId);
        $caseId=Domain_Validation::sqlId($caseId);
        $body=self::closeInput($body);
        $scope=self::scope($caseId,self::CLOSE_OP);
        $payload=\array_merge(['order_id'=>$orderId,'case_id'=>$caseId],$body);
        $actor=Event_Actor::staff(self::CAPABILITIES);
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $orderId,$caseId,$body,$scope,$payload,$actor
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::CLOSE_OP,$body['idempotency_key'],$payload
                );
                if (!\in_array($claim['status'],['new','replay'],true)) {
                    throw new Transaction_Failure('pc_manual_case_conflict');
                }
                // Leer la relacion sin adquirir lock de caso antes de pedido
                // y linea (E-05). Se revalida todo bajo FOR UPDATE despues.
                $found=$tx->rows(
                    'SELECT order_line_id FROM %i WHERE id=%s AND order_id=%s '
                        .'AND case_type=%s AND source_operation_id IS NULL LIMIT 2',
                    [Orders_Domain::table('pc_cases'),$caseId,$orderId,self::TYPE]
                );
                if (1!==\count($found) || null===$found[0]['order_line_id']) {
                    throw new Transaction_Failure('pc_manual_case_denied');
                }
                $lineId=Domain_Validation::sqlId((string)$found[0]['order_line_id']);
                $tx->lockOrder($orderId);
                $line=$tx->lockOrderLine($orderId,$lineId);
                $case=$tx->lockCaseForLine($orderId,$lineId,$caseId);
                if ('physical'!==(string)$line['kind']
                    || !self::legitimateOriginWithin(
                        $tx,$orderId,$lineId,$caseId
                    )) {
                    throw new Transaction_Failure('pc_manual_case_denied');
                }
                if ('replay'===$claim['status']) {
                    return self::closedReplayWithin(
                        $tx,$claim,$orderId,$lineId,$caseId,$body
                    );
                }
                if ($case['case_phase']!==self::OPEN
                    || (string)$case['row_version']!==$body['expected_case_version']) {
                    throw new Transaction_Failure('pc_manual_case_conflict');
                }
                $full=self::caseWithin($tx,$caseId);
                if (null!==$full['resolved_at']
                    || null!==$full['source_operation_id']) {
                    throw new Transaction_Failure('pc_manual_case_conflict');
                }
                $verification=null;
                if ('buyer_withdrawn'===$body['decision']) {
                    $verification=$body['withdrawal_verification'];
                    if (!self::verificationWithin($tx,$orderId,$verification)) {
                        throw new Transaction_Failure('pc_manual_case_identity_unverified');
                    }
                    // Un retiro no puede limitarse a copiar la misma
                    // declaracion antigua de verificacion de apertura.
                    $original=Orders_Domain::decode(
                        (string)$full['evidence_snapshot'],6000
                    );
                    if (!\is_array($original['verification']??null)
                        || $original['verification']['safe_audit_note']
                            ===$verification['safe_audit_note']) {
                        throw new Transaction_Failure('pc_manual_case_identity_unverified');
                    }
                }
                // R8-26 puede haber terminado el expediente; incluso una
                // referencia a ajuste aplicada previamente debe bloquear.
                $adjust=$tx->rows(
                    'SELECT id FROM %i WHERE case_id=%s AND order_id=%s LIMIT 2',
                    [Orders_Domain::table('pc_order_adjustments'),$caseId,$orderId]
                );
                if ([]!==$adjust) {
                    throw new Transaction_Failure('pc_manual_case_conflict');
                }
                $phase=self::PHASES[$body['decision']];
                $version=$tx->advanceVersion(
                    'pc_cases',$caseId,$body['expected_case_version']
                );
                if (1!==$tx->execute(
                    'UPDATE %i SET case_phase=%s,resolved_at=UTC_TIMESTAMP(6) '
                        .'WHERE id=%s AND order_id=%s AND order_line_id=%s '
                        .'AND case_type=%s AND case_phase=%s AND row_version=%s '
                        .'AND source_operation_id IS NULL AND resolved_at IS NULL',
                    [Orders_Domain::table('pc_cases'),$phase,$caseId,$orderId,
                        $lineId,self::TYPE,self::OPEN,$version]
                )) {
                    throw new Transaction_Failure('pc_manual_case_conflict');
                }
                $safe=['decision'=>$body['decision'],
                    'decision_reason'=>$body['reason'],
                    'case_row_version'=>$version];
                if (null!==$verification) {
                    $safe['contact_control_method']=$verification['contact_control_method'];
                    $safe['nonpublic_fact_category']=$verification['nonpublic_fact_category'];
                    $safe['withdrawal_safe_note']=$verification['safe_audit_note'];
                }
                Event_Journal::caseWithin(
                    $tx,$actor,$claim['operation_id'],$caseId,
                    self::EVENTS[$body['decision']],$body['decision'],
                    $body['decision']==='reject'
                        ? 'manual_case_rejected' : 'manual_case_withdrawn',
                    null,$safe
                );
                Orders_Finalization::afterWithin(
                    $tx,$actor,$claim['operation_id'],$orderId
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'case',$caseId,200
                );
                return self::closedWithin(
                    $tx,$caseId,$orderId,$lineId,
                    $claim['operation_id'],$phase,$version
                );
            },true
        );
    }

    /** Lectura protegida R8-21 puede recuperar un caso cerrado sin reabrirlo. */
    private static function openedReplayWithin(
        Transaction_Context $tx,array $claim,string $orderId,string $lineId
    ): array {
        if ('case'!==($claim['entity_type']??null)
            || 201!==($claim['response_code']??null)
            || !\is_string($claim['entity_id']??null)) {
            throw new Transaction_Failure('pc_manual_case_conflict');
        }
        $caseId=Domain_Validation::sqlId($claim['entity_id']);
        $rows=$tx->rows(
            'SELECT c.id,c.opened_at FROM %i c '
                .'INNER JOIN %i e ON e.case_id=c.id '
                .'WHERE c.id=%s AND c.order_id=%s AND c.order_line_id=%s '
                .'AND c.case_type=%s AND c.source_operation_id IS NULL '
                .'AND c.created_by=%s AND e.idempotency_id=%s '
                .'AND e.event_code=%s AND e.actor_kind=%s '
                .'AND e.actor_user_id=%s LIMIT 2',
            [Orders_Domain::table('pc_cases'),Orders_Domain::table('pc_case_events'),
                $caseId,$orderId,$lineId,self::TYPE,(string)\get_current_user_id(),
                $claim['operation_id'],'manual_case_opened','staff',
                (string)\get_current_user_id()]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_manual_case_conflict');
        }
        return self::openedResult(
            $caseId,$orderId,$lineId,$claim['operation_id'],
            (string)$rows[0]['opened_at']
        );
    }

    private static function closedReplayWithin(
        Transaction_Context $tx,array $claim,string $orderId,
        string $lineId,string $caseId,array $body
    ): array {
        $phase=self::PHASES[$body['decision']];
        $version=Settings_Store::nextVersion($body['expected_case_version']);
        $e=$tx->rows(
            'SELECT e.id FROM %i e WHERE e.case_id=%s '
                .'AND e.idempotency_id=%s AND e.event_code=%s '
                .'AND e.decision_code=%s AND e.actor_kind=%s '
                .'AND e.actor_user_id=%s LIMIT 2',
            [Orders_Domain::table('pc_case_events'),$caseId,
                $claim['operation_id'],self::EVENTS[$body['decision']],
                $body['decision'],'staff',(string)\get_current_user_id()]
        );
        $case=self::caseWithin($tx,$caseId);
        if ('case'!==($claim['entity_type']??null)
            || $caseId!==($claim['entity_id']??null)
            || 200!==($claim['response_code']??null)
            || 1!==\count($e)
            || (string)$case['case_phase']!==$phase
            || (string)$case['row_version']!==$version
            || null===$case['resolved_at']) {
            throw new Transaction_Failure('pc_manual_case_conflict');
        }
        return self::closedResult(
            $caseId,$orderId,$lineId,$phase,$version,
            $claim['operation_id'],(string)$case['resolved_at']
        );
    }

    private static function openedWithin(
        Transaction_Context $tx,string $caseId,string $orderId,
        string $lineId,string $operationId
    ): array {
        $rows=$tx->rows(
            'SELECT opened_at FROM %i WHERE id=%s AND order_id=%s '
                .'AND order_line_id=%s LIMIT 2',
            [Orders_Domain::table('pc_cases'),$caseId,$orderId,$lineId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_manual_case_invalid');
        }
        return self::openedResult(
            $caseId,$orderId,$lineId,$operationId,(string)$rows[0]['opened_at']
        );
    }

    private static function closedWithin(
        Transaction_Context $tx,string $caseId,string $orderId,
        string $lineId,string $operationId,string $phase,string $version
    ): array {
        $rows=$tx->rows(
            'SELECT resolved_at FROM %i WHERE id=%s AND order_id=%s '
                .'AND order_line_id=%s AND case_phase=%s AND row_version=%s LIMIT 2',
            [Orders_Domain::table('pc_cases'),$caseId,$orderId,$lineId,$phase,$version]
        );
        if (1!==\count($rows) || null===$rows[0]['resolved_at']) {
            throw new Transaction_Failure('pc_manual_case_invalid');
        }
        return self::closedResult(
            $caseId,$orderId,$lineId,$phase,$version,$operationId,
            (string)$rows[0]['resolved_at']
        );
    }

    private static function openedResult(
        string $caseId,string $orderId,string $lineId,
        string $op,string $openedAt
    ): array {
        return [
            'case_id'=>$caseId,'order_id'=>$orderId,'order_line_id'=>$lineId,
            'case_phase'=>self::OPEN,'row_version'=>'1',
            'operation_id'=>$op,'opened_at_utc'=>Orders_Domain::utc($openedAt)
        ];
    }

    private static function closedResult(
        string $caseId,string $orderId,string $lineId,string $phase,
        string $version,string $op,string $closedAt
    ): array {
        return [
            'case_id'=>$caseId,'order_id'=>$orderId,'order_line_id'=>$lineId,
            'case_phase'=>$phase,'row_version'=>$version,
            'operation_id'=>$op,'resolved_at_utc'=>Orders_Domain::utc($closedAt)
        ];
    }

    /** Unicamente datos originales, nunca phone/OTP/factor literal en JSON. */
    private static function verificationWithin(
        Transaction_Context $tx,string $orderId,array $verify
    ): bool {
        if (!self::verificationShape($verify)) { return false; }
        $rows=$tx->rows(
            'SELECT buyer_phone,customer_snapshot,original_delivery_snapshot,'
                .'total_cents,order_number FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($rows) || !\is_string($rows[0]['buyer_phone'])
            || !\preg_match('/\A\+?[0-9() -]{6,32}\z/D',
                $rows[0]['buyer_phone'])) {
            return false;
        }
        $customer=Orders_Domain::customerSnapshot(
            (string)$rows[0]['customer_snapshot']
        );
        if (($customer['phone']??null)!==$rows[0]['buyer_phone']) {
            return false;
        }
        if ($verify['nonpublic_fact_category']==='private_delivery_detail') {
            $delivery=Orders_Domain::deliverySnapshot(
                $rows[0]['original_delivery_snapshot']
            );
            return ($delivery['mode']??null)==='delivery'
                && \is_string($delivery['address']??null)
                && ''!==\trim($delivery['address']);
        }
        // Un pedido confirmado tiene importe/numero historicos
        // privados para confirmar verbalmente con su comprador real;
        // la aplicacion NO recibe ni registra la respuesta literal.
        return Money::fromApi((string)$rows[0]['total_cents'])>0
            && \is_string($rows[0]['order_number'])
            && ''!==$rows[0]['order_number'];
    }

    /**
     * Prueba interna compartida con R8-26: procedencia R8-69 efectiva,
     * snapshot/solicitud original y evento staff ligado al root completo.
     * Solo usar despues de bloquear pedido, linea y caso del mismo sitio.
     */
    public static function legitimateOriginWithin(
        Transaction_Context $tx,string $orderId,string $lineId,string $caseId
    ): bool {
        $case=self::caseWithin($tx,$caseId);
        if ($case['case_type']!==self::TYPE
            || (string)$case['order_id']!==$orderId
            || (string)$case['order_line_id']!==$lineId
            || null!==$case['source_operation_id']
            || null===$case['created_by']
            || !\is_string($case['evidence_snapshot'])) {
            return false;
        }
        $snapshot=Orders_Domain::decode((string)$case['evidence_snapshot'],6000);
        if (($snapshot['purpose']??null)!==self::TYPE
            || ($snapshot['manual_verification_basis']??null)
                !=='registered_phone_plus_nonpublic_order_fact'
            || ($snapshot['line_id']??null)!==$lineId
            || ($snapshot['order_id']??null)!==$orderId
            || !\is_int($snapshot['requested_affected_units']??null)
            || $snapshot['requested_affected_units']<1
            || !self::verificationShape($snapshot['verification']??null)) {
            return false;
        }
        $rows=$tx->rows(
            'SELECT e.actor_user_id,e.safe_payload_snapshot,i.operation_type,'
                .'i.technical_phase,i.entity_type,i.entity_id '
                .'FROM %i e INNER JOIN %i i ON i.id=e.idempotency_id '
                .'WHERE e.case_id=%s AND e.event_code=%s '
                .'AND e.actor_kind=%s ORDER BY e.occurred_at,e.id LIMIT 2',
            [Orders_Domain::table('pc_case_events'),
                Orders_Domain::table('pc_idempotency'),$caseId,
                'manual_case_opened','staff']
        );
        if (1!==\count($rows)
            || (string)$rows[0]['actor_user_id']
                !==(string)$case['created_by']
            || $rows[0]['operation_type']!==self::OPEN_OP
            || $rows[0]['technical_phase']!=='complete'
            || $rows[0]['entity_type']!=='case'
            || (string)$rows[0]['entity_id']!==$caseId
            || !\is_string($rows[0]['safe_payload_snapshot'])) {
            return false;
        }
        $event=Orders_Domain::decode((string)$rows[0]['safe_payload_snapshot'],1000);
        return ($event['requested_affected_units']??null)
                ===$snapshot['requested_affected_units']
            && ($event['contact_control_method']??null)
                ===$snapshot['verification']['contact_control_method']
            && ($event['nonpublic_fact_category']??null)
                ===$snapshot['verification']['nonpublic_fact_category'];
    }

    private static function caseWithin(Transaction_Context $tx,string $caseId): array
    {
        $rows=$tx->rows(
            'SELECT id,order_id,order_line_id,case_type,case_phase,'
                .'source_operation_id,created_by,evidence_snapshot,'
                .'resolved_at,row_version FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_cases'),$caseId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_manual_case_invalid');
        }
        return $rows[0];
    }

    private static function physicalCountsWithin(
        Transaction_Context $tx,array $order,array $line
    ): array {
        $rows=$tx->rows(
            'SELECT sellable_id,committed_units,fulfilled_units,released_units '
                .'FROM %i WHERE order_line_id=%s AND sellable_id=%s LIMIT 2',
            [Orders_Domain::table('pc_stock_commitments'),
                (string)$line['id'],(string)$line['sellable_id']]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_manual_case_conflict');
        }
        return Orders_Domain::physicalQuantities($order,$line,$rows[0]);
    }

    private static function openInput(mixed $body): array
    {
        $required=['line_id','purpose','requested_affected_units','reason',
            'verification','expected_order_version','expected_line_version',
            'idempotency_key'];
        self::keys($body,$required);
        if ($body['purpose']!==self::TYPE
            || !\is_int($body['requested_affected_units'])
            || $body['requested_affected_units']<1
            || $body['requested_affected_units']>100000
            || !self::verificationShape($body['verification'])) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        self::sqlId($body['line_id']);
        self::sqlId($body['expected_order_version']);
        self::sqlId($body['expected_line_version']);
        self::reason($body['reason']);
        self::idempotencyKey($body['idempotency_key']);
        return $body;
    }

    private static function closeInput(mixed $body): array
    {
        if (!\is_array($body) || \array_is_list($body)
            || !\is_string($body['decision']??null)
            || !isset(self::PHASES[$body['decision']])) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        if ($body['decision']==='reject') {
            self::keys($body,['decision','reason','expected_case_version',
                'idempotency_key']);
        } else {
            self::keys($body,['decision','reason','expected_case_version',
                'idempotency_key','withdrawal_verification']);
            if (!self::verificationShape($body['withdrawal_verification'])) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
        }
        self::sqlId($body['expected_case_version']);
        self::reason($body['reason']);
        self::idempotencyKey($body['idempotency_key']);
        return $body;
    }

    private static function verificationShape(mixed $input): bool
    {
        if (!\is_array($input) || \array_is_list($input)) {
            return false;
        }
        $keys=['contact_control_method','registered_phone_control_confirmed',
            'nonpublic_order_fact_confirmed','nonpublic_fact_category',
            'safe_audit_note'];
        if (!self::sameKeys($input,$keys)
            || !\in_array($input['contact_control_method'],[
                'outbound_call_to_registered_phone',
                'verified_reply_from_registered_phone',
            ],true)
            || true!==$input['registered_phone_control_confirmed']
            || true!==$input['nonpublic_order_fact_confirmed']
            || !\in_array($input['nonpublic_fact_category'],[
                'private_delivery_detail','private_order_detail',
            ],true)
            || !\is_string($input['safe_audit_note'])
            || !\mb_check_encoding($input['safe_audit_note'],'UTF-8')
            || \mb_strlen($input['safe_audit_note'],'UTF-8')<8
            || \mb_strlen($input['safe_audit_note'],'UTF-8')>400
            || \preg_match('/[\x00-\x1F\x7F]/u',$input['safe_audit_note'])
            // Las notas de atestación nunca son el valor literal
            // del segundo factor, un número de teléfono, DNI u OTP.
            || \preg_match('/[0-9@]/u',$input['safe_audit_note'])
            || \str_contains($input['safe_audit_note'],'://')) {
            return false;
        }
        return true;
    }

    private static function keys(mixed $data,array $expected): void
    {
        if (!\is_array($data) || \array_is_list($data)
            || !self::sameKeys($data,$expected)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
    }

    private static function sameKeys(array $data,array $expected): bool
    {
        $a=\array_keys($data);
        \sort($a,\SORT_STRING);
        \sort($expected,\SORT_STRING);
        return $a===$expected;
    }

    private static function sqlId(mixed $value): void
    {
        if (!\is_string($value)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try {
            Domain_Validation::sqlId($value);
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
    }

    private static function reason(mixed $value): void
    {
        if (!\is_string($value) || ''===\trim($value)
            || !\mb_check_encoding($value,'UTF-8')
            || \mb_strlen($value,'UTF-8')>1000
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
    }

    private static function idempotencyKey(mixed $value): void
    {
        if (!\is_string($value) || \strlen($value)<16 || \strlen($value)>128
            || !\mb_check_encoding($value,'UTF-8')
            || \preg_match('/[\x00-\x20\x7F]/u',$value)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
    }

    private static function encode(array $data,int $limit): string
    {
        $json=\json_encode($data,
            \JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES|\JSON_THROW_ON_ERROR);
        if (!\is_string($json) || \strlen($json)>$limit) {
            throw new Transaction_Failure('pc_manual_case_invalid');
        }
        return $json;
    }

    private static function lastId(Transaction_Context $tx): string
    {
        $rows=$tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_manual_case_invalid');
        }
        return Domain_Validation::sqlId((string)$rows[0]['id']);
    }

    private static function adminReady(?string $role): bool
    {
        return 'A'===$role
            && 'A'===Access_Roles::currentRole()
            && \current_user_can('perucommerce_orders_read')
            && \current_user_can('perucommerce_orders_financial_adjust')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin')&&\is_network_admin())
            && Module_Gates::allows('orders','manual_cases');
    }

    private static function admin(): void
    {
        if (!self::adminReady(Access_Roles::currentRole())) {
            throw new Transaction_Failure('pc_manual_case_denied');
        }
    }

    private static function proof(bool $opening): array
    {
        $base=[
            'authorized'=>true,'same_site'=>true,
            'resource_checked'=>true,'state_checked'=>true,
            'field_checked'=>true,'purpose_checked'=>true,
            'physical_partial_case'=>true,
            'case_scope_verified'=>true,
        ];
        if ($opening) {
            $base['buyer_identity_verified']=true;
        }
        return $base;
    }

    private static function scope(string $resourceId,string $operation): array
    {
        return [
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>self::CLOSE_OP===$operation?'case':'order_line',
            'resource_id'=>$resourceId,
            'purpose'=>$operation,
        ];
    }
}
