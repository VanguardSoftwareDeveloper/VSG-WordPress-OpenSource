<?php
/**
 * IMP-41 — R8-26: ajuste comercial inmutable, con reducción parcial
 * física exclusiva de un expediente R8-69 legítimo y todavía abierto.
 *
 * No efectúa ni presume cobro, reembolso, entrega o revocación; los
 * snapshots del pedido y la cantidad histórica nunca se reescriben.
 * E-05: raíz idempotente -> pedido -> línea -> caso -> variante -> compromiso.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Adjustments
{
    private const OP='order_financial_adjust';
    private const CASE_TYPE='physical_partial_quantity_adjustment';
    private const CASE_OPEN='open_review';
    private const CASE_CLOSED='closed_adjusted';
    private const UNIT_EFFECT='release_unfulfilled_units';
    private const REASON_PARTIAL='physical_partial_adjusted';
    private const REASON_MONEY='monetary_adjusted';

    /** El guard no concede legitimación por un ID de caso o un nonce. */
    public static function guard(array $ctx,?string $role): array
    {
        if (!self::staffReady($role)) { return []; }
        try {
            $orderId=Domain_Validation::sqlId($ctx['path']['id']??'');
            $body=self::input($ctx['body']??null);
            $valid=Transaction::run(
                static function(Transaction_Context $tx) use($orderId,$body):bool {
                    $orders=$tx->rows(
                        'SELECT id FROM %i WHERE id=%s LIMIT 2',
                        [Orders_Domain::table('pc_orders'),$orderId]
                    );
                    if (1!==\count($orders)) { return false; }
                    if (isset($body['line_id'])) {
                        $rows=$tx->rows(
                            'SELECT kind FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
                            [Orders_Domain::table('pc_order_lines'),
                                $body['line_id'],$orderId]
                        );
                        if (1!==\count($rows)) {
                            return false;
                        }
                    }
                    if (isset($body['case_id'])) {
                        $rows=$tx->rows(
                            'SELECT case_type,order_line_id FROM %i '
                                .'WHERE id=%s AND order_id=%s LIMIT 2',
                            [Orders_Domain::table('pc_cases'),
                                $body['case_id'],$orderId]
                        );
                        if (1!==\count($rows)) { return false; }
                        if (isset($body['affected_units'])) {
                            return $rows[0]['case_type']===self::CASE_TYPE
                                && (string)$rows[0]['order_line_id']
                                    ===$body['line_id']
                                && \current_user_can('perucommerce_orders_read');
                        }
                        return $rows[0]['case_type']==='guest_request_refund'
                            && null===$rows[0]['order_line_id']
                            && \current_user_can('perucommerce_orders_read');
                    }
                    return true;
                }
            );
            return $valid?[
                'authorized'=>true,
                'same_site'=>true,
                'resource_checked'=>true,
                'state_checked'=>true,
                'field_checked'=>true,
                'partial_case_scope_verified'=>isset($body['affected_units']),
            ]:[];
        } catch (\Throwable $ignored) { return []; }
    }

    public static function adjust(string $orderId,array $body): array
    {
        if (!self::staffReady(Access_Roles::currentRole())) {
            throw new Transaction_Failure('pc_adjust_denied');
        }
        $orderId=Domain_Validation::sqlId($orderId);
        $body=self::input($body);
        $partial=isset($body['affected_units']);
        $actor=Event_Actor::staff(['perucommerce_orders_financial_adjust']);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'order',
            'resource_id'=>$orderId,
            'purpose'=>self::OP,
        ];
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $orderId,$body,$partial,$actor,$scope
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OP,$body['idempotency_key'],$body
                );
                if (!\in_array($claim['status'],['new','replay'],true)) {
                    throw new Transaction_Failure('pc_adjust_conflict');
                }
                $order=$tx->lockOrder($orderId);
                $line=null;$case=null;
                if (isset($body['line_id'])) {
                    $line=$tx->lockOrderLine($orderId,$body['line_id']);
                }
                if ($partial && null!==$line
                    && 'physical'!==(string)$line['kind']) {
                    throw new Rest_Failure('pc_business_ineligible',422,
                        false,'affected_units');
                }
                if (isset($body['case_id'])) {
                    if ($partial && null!==$line) {
                        $case=$tx->lockCaseForLine(
                            $orderId,$body['line_id'],$body['case_id']
                        );
                    } else {
                        $case=$tx->lockCaseForOrder(
                            $orderId,$body['case_id']
                        );
                    }
                }
                if ('replay'===$claim['status']) {
                    return self::replayWithin(
                        $tx,$claim,$orderId,$body,$partial
                    );
                }

                $orderInfo=self::orderWithin($tx,$orderId,$order);
                if ((string)$order['row_version']!==$body['expected_version']
                    || null===$orderInfo['confirmed_at']
                    || !\in_array($orderInfo['status'],[
                        'CONFIRMADO','FINALIZADO','CANCELADO',
                    ],true)) {
                    throw new Transaction_Failure('pc_adjust_conflict');
                }
                if ($partial && 'CONFIRMADO'!==$orderInfo['status']) {
                    throw new Transaction_Failure('pc_adjust_conflict');
                }

                $payment=self::accreditationWithin(
                    $tx,$orderId,$orderInfo['payment_method'],
                    $orderInfo['total_cents']
                );
                $amount=Money::fromSignedLedger($body['delta_cents']);
                if (0===$amount) {
                    throw new Transaction_Failure('pc_adjust_input_invalid');
                }
                $snapshotBasis=[
                    'type'=>$partial?'physical_partial':'monetary_only',
                    'order_id'=>$orderId,
                    'line_id'=>$body['line_id']??null,
                    'case_id'=>$body['case_id']??null,
                    'order_version'=>$body['expected_version'],
                    'historically_credited'=>$payment['credited'],
                    'source_credit_event_id'=>$payment['credit_id'],
                ];

                if ($partial) {
                    if (null===$case || null===$line) {
                        throw new Transaction_Failure('pc_adjust_case_denied');
                    }
                    $snapshotBasis=\array_merge($snapshotBasis,
                        self::validatePartialWithin(
                            $tx,$orderId,$line,$case,$body,$amount
                        )
                    );
                } elseif (null!==$case) {
                    // La vía exclusivamente monetaria NO hereda un caso
                    // R8-69 ni cierra su cantidad sin E-05/consentimiento.
                    self::validateMoneyCaseWithin(
                        $tx,$orderId,$case,$body
                    );
                }

                $balances=self::balanceWithin(
                    $tx,$orderId,$orderInfo['total_cents'],
                    $body['line_id']??null
                );
                $newObligation=Money::addChecked($balances['order'],$amount);
                if ($newObligation<0
                    || $newObligation>$orderInfo['total_cents']) {
                    throw new Transaction_Failure('pc_adjust_amount_conflict');
                }
                if (null!==$balances['line']) {
                    $newLine=Money::addChecked($balances['line'],$amount);
                    if ($newLine<0 || $newLine>$balances['line_original']) {
                        throw new Transaction_Failure('pc_adjust_amount_conflict');
                    }
                }
                $snapshotBasis['obligation_before_cents']=(string)$balances['order'];
                $snapshotBasis['obligation_after_cents']=(string)$newObligation;
                $snapshotBasis['delta_cents']=Money::toLedger($amount);
                $snapshotBasis['reason']=$body['reason'];

                // Una reducción de unidades NO se convierte en retorno
                // físico; solo se libera el compromiso todavía pendiente.
                $newLineVersion=null;
                if ($partial) {
                    if (!Module_Gates::allows('inventory','write')) {
                        throw new Transaction_Failure('pc_adjust_not_ready');
                    }
                    $sellableId=Domain_Validation::sqlId(
                        (string)$line['sellable_id']
                    );
                    $tx->lockSellables([$sellableId]);
                    $tx->lockStockCommitment($body['line_id'],$sellableId);
                    $counts=Inventory_Reservations::physicalQuantitiesWithin(
                        $tx,$orderId,$body['line_id'],$sellableId
                    );
                    if ($counts['stock_out_units']!==0
                        || $counts['committed_units']<1
                        || $body['affected_units']>=$counts['pending_stock_out_units']) {
                        throw new Transaction_Failure('pc_adjust_ineligible');
                    }
                    $newLineVersion=Inventory_Reservations::releasePendingWithin(
                        $tx,$actor,$claim['operation_id'],$orderId,
                        $body['line_id'],$sellableId,
                        $body['affected_units'],'financial_partial',
                        (string)$line['row_version']
                    );
                    $snapshotBasis['committed_units']=$counts['committed_units'];
                    $snapshotBasis['released_before']=$counts['released_units'];
                    $snapshotBasis['stock_out_before']=$counts['stock_out_units'];
                    $snapshotBasis['pending_before']=$counts['pending_stock_out_units'];
                    $snapshotBasis['pending_after']=$counts['pending_stock_out_units']
                        -$body['affected_units'];
                    $snapshotBasis['new_line_version']=$newLineVersion;
                }

                $snapshot=self::encode($snapshotBasis);
                $reasonCode=$partial?self::REASON_PARTIAL:self::REASON_MONEY;
                if (1!==$tx->execute(
                    'INSERT INTO %i (order_id,order_line_id,case_id,reason_code,'
                        .'delta_cents,approved_by,original_basis_snapshot,'
                        .'created_at,idempotency_id) VALUES '
                        .'(%s,'
                        .(isset($body['line_id'])?'%s':'NULL').','
                        .(isset($body['case_id'])?'%s':'NULL').','
                        .'%s,%s,%s,%s,UTC_TIMESTAMP(6),%s)',
                    \array_merge(
                        [Orders_Domain::table('pc_order_adjustments'),$orderId],
                        isset($body['line_id'])?[$body['line_id']]:[],
                        isset($body['case_id'])?[$body['case_id']]:[],
                        [$reasonCode,$body['delta_cents'],
                         (string)\get_current_user_id(),$snapshot,
                         $claim['operation_id']]
                    )
                )) {
                    throw new Transaction_Failure('pc_adjust_write_failed');
                }
                $adjustmentId=self::lastId($tx);

                if ($partial) {
                    $caseId=$body['case_id'];
                    $caseVersion=$tx->advanceVersion(
                        'pc_cases',$caseId,$body['expected_case_version']
                    );
                    if (1!==$tx->execute(
                        'UPDATE %i SET case_phase=%s,resolved_at=UTC_TIMESTAMP(6) '
                            .'WHERE id=%s AND order_id=%s AND order_line_id=%s '
                            .'AND case_type=%s AND case_phase=%s AND row_version=%s '
                            .'AND source_operation_id IS NULL AND resolved_at IS NULL',
                        [Orders_Domain::table('pc_cases'),self::CASE_CLOSED,
                            $caseId,$orderId,$body['line_id'],
                            self::CASE_TYPE,self::CASE_OPEN,$caseVersion]
                    )) {
                        throw new Transaction_Failure('pc_adjust_conflict');
                    }
                    Event_Journal::caseWithin(
                        $tx,$actor,$claim['operation_id'],$caseId,
                        'manual_case_adjusted',null,self::REASON_PARTIAL,
                        null,[
                            'affected_units'=>$body['affected_units'],
                            'unit_effect'=>self::UNIT_EFFECT,
                            'case_row_version'=>$caseVersion,
                        ]
                    );
                }
                $newOrderVersion=$tx->advanceVersion(
                    'pc_orders',$orderId,$body['expected_version']
                );
                Event_Journal::auditWithin(
                    $tx,$actor,$claim['operation_id'],'order',$orderId,
                    'order_financial_adjusted',$reasonCode,
                    \hash('sha256',$body['expected_version'].':'
                        .(string)$balances['order']),
                    \hash('sha256',$newOrderVersion.':'.$adjustmentId.':'
                        .$snapshot)
                );
                Queue_Outbox::essentialWithin(
                    $tx,$actor,$claim['operation_id'],$orderId,
                    'order_financial_adjusted',$orderId
                );
                $closed=Orders_Finalization::afterWithin(
                    $tx,$actor,$claim['operation_id'],$orderId
                );
                if(null!==$closed){$newOrderVersion=$closed;}
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'adjustment',$adjustmentId,200
                );
                return self::result(
                    $claim['operation_id'],$adjustmentId,$newOrderVersion,$partial
                );
            },true
        );
    }

    /**
     * El original R8-69 contiene solicitud privada, no permiso para
     * cambiar unilateralmente cantidad/importe. Se valida bajo case lock.
     */
    private static function validatePartialWithin(
        Transaction_Context $tx,string $orderId,array $line,
        array $case,array $body,int $amount
    ): array {
        $lineId=$body['line_id'];$caseId=$body['case_id'];
        if (!\current_user_can('perucommerce_orders_read')
            || (string)$line['order_id']!==$orderId
            || 'physical'!==(string)$line['kind']
            || !\in_array((string)$line['fulfillment_state'],[
                'PENDIENTE','PREPARANDO','LISTO',
            ],true)
            || (string)$case['order_id']!==$orderId
            || (string)$case['order_line_id']!==$lineId
            || (string)$case['case_type']!==self::CASE_TYPE
            || (string)$case['case_phase']!==self::CASE_OPEN
            || (string)$case['row_version']!==$body['expected_case_version']
            || !Manual_Physical_Cases::legitimateOriginWithin(
                $tx,$orderId,$lineId,$caseId
            )) {
            throw new Transaction_Failure('pc_adjust_case_denied');
        }
        $cases=$tx->rows(
            'SELECT evidence_snapshot,source_operation_id,resolved_at '
                .'FROM %i WHERE id=%s AND order_id=%s AND order_line_id=%s LIMIT 2',
            [Orders_Domain::table('pc_cases'),$caseId,$orderId,$lineId]
        );
        if (1!==\count($cases)
            || null!==$cases[0]['source_operation_id']
            || null!==$cases[0]['resolved_at']
            || !\is_string($cases[0]['evidence_snapshot'])) {
            throw new Transaction_Failure('pc_adjust_case_denied');
        }
        $original=Orders_Domain::decode(
            (string)$cases[0]['evidence_snapshot'],6000
        );
        if (($original['purpose']??null)!==self::CASE_TYPE
            || ($original['line_id']??null)!==$lineId
            || ($original['order_id']??null)!==$orderId
            || ($original['requested_affected_units']??null)
                !==$body['affected_units']
            || ($original['manual_verification_basis']??null)
                !=='registered_phone_plus_nonpublic_order_fact') {
            throw new Transaction_Failure('pc_adjust_case_denied');
        }
        $duplicates=$tx->rows(
            'SELECT id FROM %i WHERE order_id=%s AND case_id=%s LIMIT 2',
            [Orders_Domain::table('pc_order_adjustments'),$orderId,$caseId]
        );
        if ([]!==$duplicates) {
            throw new Transaction_Failure('pc_adjust_conflict');
        }
        $detail=$tx->rows(
            'SELECT quantity,unit_price_cents,subtotal_cents '
                .'FROM %i WHERE id=%s AND order_id=%s AND kind=%s LIMIT 2',
            [Orders_Domain::table('pc_order_lines'),$lineId,$orderId,'physical']
        );
        if (1!==\count($detail)) {
            throw new Transaction_Failure('pc_adjust_invariant_invalid');
        }
        $quantity=Orders_Domain::quantity((string)$detail[0]['quantity']);
        $unit=Money::fromApi((string)$detail[0]['unit_price_cents']);
        if ($unit<1 || Money::lineSubtotal($unit,$quantity)
                !==Money::fromApi((string)$detail[0]['subtotal_cents'])) {
            throw new Transaction_Failure('pc_adjust_invariant_invalid');
        }
        $historicalValue=Money::lineSubtotal(
            $unit,$body['affected_units']
        );
        if ($amount!==-$historicalValue
            || $body['unit_effect']!==self::UNIT_EFFECT) {
            // No prorratear segun saldo ni modificar consentimiento.
            throw new Transaction_Failure('pc_adjust_amount_conflict');
        }
        return [
            'case_version_before'=>$body['expected_case_version'],
            'requested_affected_units'=>$body['affected_units'],
            'unit_effect'=>self::UNIT_EFFECT,
            'historical_unit_price_cents'=>(string)$unit,
            'historical_line_quantity'=>$quantity,
            'line_version_before'=>(string)$line['row_version'],
            'case_origin'=>'verified_staff_R8_69',
        ];
    }

    /** Caso monetario opcional: solo refund guest original legitimado. */
    private static function validateMoneyCaseWithin(
        Transaction_Context $tx,string $orderId,array $case,array $body
    ): void {
        if (!\current_user_can('perucommerce_orders_read')
            || (string)$case['order_id']!==$orderId
            || $case['case_type']!=='guest_request_refund'
            || null!==$case['order_line_id']
            || $case['case_phase']!==self::CASE_OPEN) {
            throw new Transaction_Failure('pc_adjust_case_denied');
        }
        $proof=$tx->rows(
            'SELECT c.created_by,c.source_operation_id,e.actor_kind,'
                .'e.idempotency_id,i.operation_type,i.technical_phase,'
                .'i.entity_type,i.entity_id FROM %i c '
                .'INNER JOIN %i e ON e.case_id=c.id '
                .'INNER JOIN %i i ON i.id=e.idempotency_id '
                .'WHERE c.id=%s AND c.order_id=%s AND c.case_type=%s '
                .'AND e.event_code=%s ORDER BY e.occurred_at,e.id LIMIT 2',
            [Orders_Domain::table('pc_cases'),
                Orders_Domain::table('pc_case_events'),
                Orders_Domain::table('pc_idempotency'),
                $body['case_id'],$orderId,'guest_request_refund',
                'guest_request_opened']
        );
        if (1!==\count($proof) || null!==$proof[0]['created_by']
            || null!==$proof[0]['source_operation_id']
            || $proof[0]['actor_kind']!=='guest'
            || $proof[0]['operation_type']!=='guest_case_request'
            || $proof[0]['technical_phase']!=='complete'
            || $proof[0]['entity_type']!=='case'
            || (string)$proof[0]['entity_id']!==$body['case_id']) {
            throw new Transaction_Failure('pc_adjust_case_denied');
        }
        $prior=$tx->rows(
            'SELECT id FROM %i WHERE order_id=%s AND case_id=%s LIMIT 2',
            [Orders_Domain::table('pc_order_adjustments'),
                $orderId,$body['case_id']]
        );
        if ([]!==$prior) {
            throw new Transaction_Failure('pc_adjust_conflict');
        }
    }

    /**
     * Recalcula obligaciones de snapshots originales + SOLO ajustes
     * comerciales, NO dinero recibido/reintegrado de pc_payment_events.
     */
    private static function balanceWithin(
        Transaction_Context $tx,string $orderId,int $original,
        ?string $lineId
    ): array {
        if ($original<1) {
            throw new Transaction_Failure('pc_adjust_invariant_invalid');
        }
        $rows=$tx->rows(
            'SELECT order_line_id,delta_cents FROM %i '
                .'WHERE order_id=%s ORDER BY id LIMIT 201',
            [Orders_Domain::table('pc_order_adjustments'),$orderId]
        );
        if (\count($rows)>200) {
            throw new Transaction_Failure('pc_adjust_invariant_invalid');
        }
        $current=$original;
        $lineCurrent=null;$lineOriginal=null;
        if (null!==$lineId) {
            $lines=$tx->rows(
                'SELECT subtotal_cents FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
                [Orders_Domain::table('pc_order_lines'),$lineId,$orderId]
            );
            if (1!==\count($lines)) {
                throw new Transaction_Failure('pc_adjust_invariant_invalid');
            }
            $lineOriginal=Money::fromApi((string)$lines[0]['subtotal_cents']);
            $lineCurrent=$lineOriginal;
        }
        foreach ($rows as $r) {
            $delta=Money::fromSignedLedger((string)$r['delta_cents']);
            $current=Money::addChecked($current,$delta);
            if (null!==$lineId && null!==$r['order_line_id']
                && (string)$r['order_line_id']===$lineId) {
                $lineCurrent=Money::addChecked($lineCurrent,$delta);
            }
        }
        if ($current<0 || $current>$original
            || (null!==$lineCurrent
                && ($lineCurrent<0 || $lineCurrent>$lineOriginal))) {
            throw new Transaction_Failure('pc_adjust_invariant_invalid');
        }
        return [
            'order'=>$current,
            'line'=>$lineCurrent,
            'line_original'=>$lineOriginal,
        ];
    }

    private static function orderWithin(
        Transaction_Context $tx,string $orderId,array $locked
    ): array {
        $r=$tx->rows(
            'SELECT status,payment_method,currency,total_cents,confirmed_at,'
                .'row_version FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($r)
            || (string)$r[0]['status']!==(string)$locked['status']
            || (string)$r[0]['row_version']!==(string)$locked['row_version']
            || (string)$r[0]['payment_method']
                !==(string)$locked['payment_method']
            || $r[0]['currency']!=='PEN') {
            throw new Transaction_Failure('pc_adjust_invariant_invalid');
        }
        return [
            'status'=>(string)$r[0]['status'],
            'payment_method'=>(string)$r[0]['payment_method'],
            'total_cents'=>Money::fromApi((string)$r[0]['total_cents']),
            'confirmed_at'=>$r[0]['confirmed_at'],
        ];
    }

    /**
     * Comprobar acreditación inicial histórica sin confundir el ledger
     * monetario comercial con una devolución/balance del proveedor.
     * MP confirmado exige intento APROBADO y evento único real; COD
     * confirmado admite deuda presencial todavía no cobrada.
     */
    private static function accreditationWithin(
        Transaction_Context $tx,string $orderId,string $paymentMethod,int $total
    ): array {
        if (!\in_array($paymentMethod,['cash','mercado_pago'],true)) {
            throw new Transaction_Failure('pc_adjust_money_unresolved');
        }
        $rows=$tx->rows(
            'SELECT id,provider,amount_cents,payment_attempt_id '
                .'FROM %i WHERE order_id=%s AND kind=%s ORDER BY id LIMIT 3',
            [Orders_Domain::table('pc_payment_events'),$orderId,'initial_charge']
        );
        if (\count($rows)>1) {
            throw new Transaction_Failure('pc_adjust_money_unresolved');
        }
        if ([]===$rows) {
            if ('mercado_pago'===$paymentMethod) {
                throw new Transaction_Failure('pc_adjust_money_unresolved');
            }
            return ['credited'=>false,'credit_id'=>null];
        }
        $event=$rows[0];
        $amount=Money::fromSignedLedger((string)$event['amount_cents']);
        if ($event['provider']!==$paymentMethod
            || $amount<=0 || $amount>$total) {
            throw new Transaction_Failure('pc_adjust_money_unresolved');
        }
        $creditId=Domain_Validation::sqlId((string)$event['id']);
        if ('mercado_pago'===$paymentMethod) {
            if ($amount!==$total
                || null===$event['payment_attempt_id']) {
                throw new Transaction_Failure('pc_adjust_money_unresolved');
            }
            $attempt=$tx->rows(
                'SELECT state FROM %i WHERE id=%s AND order_id=%s '
                    .'AND provider=%s LIMIT 2',
                [Orders_Domain::table('pc_payment_attempts'),
                    (string)$event['payment_attempt_id'],
                    $orderId,'mercado_pago']
            );
            if (1!==\count($attempt)
                || 'APROBADO'!==$attempt[0]['state']) {
                throw new Transaction_Failure('pc_adjust_money_unresolved');
            }
        } elseif (null!==$event['payment_attempt_id']) {
            throw new Transaction_Failure('pc_adjust_money_unresolved');
        }
        return ['credited'=>true,'credit_id'=>$creditId];
    }

    /** Replay de origen fiable: nunca escribe segundo ajuste ni stock. */
    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $orderId,
        array $body,bool $partial
    ): array {
        if ('adjustment'!==($claim['entity_type']??null)
            || !\is_string($claim['entity_id']??null)
            || 200!==($claim['response_code']??null)) {
            throw new Transaction_Failure('pc_adjust_conflict');
        }
        $id=Domain_Validation::sqlId($claim['entity_id']);
        $rows=$tx->rows(
            'SELECT id,order_id,order_line_id,case_id,reason_code,delta_cents,'
                .'approved_by FROM %i WHERE id=%s AND order_id=%s '
                .'AND idempotency_id=%s LIMIT 2',
            [Orders_Domain::table('pc_order_adjustments'),
                $id,$orderId,$claim['operation_id']]
        );
        if (1!==\count($rows)
            || (string)$rows[0]['order_id']!==$orderId
            || (string)$rows[0]['approved_by']
                !==(string)\get_current_user_id()
            || (string)$rows[0]['delta_cents']!==$body['delta_cents']
            || (null===$rows[0]['order_line_id']?null:(string)$rows[0]['order_line_id'])
                !==($body['line_id']??null)
            || (null===$rows[0]['case_id']?null:(string)$rows[0]['case_id'])
                !==($body['case_id']??null)
            || $rows[0]['reason_code']!==($partial
                ? self::REASON_PARTIAL:self::REASON_MONEY)) {
            throw new Transaction_Failure('pc_adjust_conflict');
        }
        if ($partial) {
            $events=$tx->rows(
                'SELECT id FROM %i WHERE case_id=%s AND idempotency_id=%s '
                    .'AND event_code=%s AND actor_kind=%s AND actor_user_id=%s LIMIT 2',
                [Orders_Domain::table('pc_case_events'),
                    $body['case_id'],$claim['operation_id'],
                    'manual_case_adjusted','staff',
                    (string)\get_current_user_id()]
            );
            $case=$tx->rows(
                'SELECT case_phase,row_version,resolved_at FROM %i '
                    .'WHERE id=%s AND order_id=%s AND order_line_id=%s LIMIT 2',
                [Orders_Domain::table('pc_cases'),
                    $body['case_id'],$orderId,$body['line_id']]
            );
            if (1!==\count($events) || 1!==\count($case)
                || $case[0]['case_phase']!==self::CASE_CLOSED
                || (string)$case[0]['row_version']!==Settings_Store::nextVersion(
                    $body['expected_case_version']
                )
                || null===$case[0]['resolved_at']) {
                throw new Transaction_Failure('pc_adjust_conflict');
            }
        }
        return self::result(
            $claim['operation_id'],$id,
            Orders_Finalization::replayVersionWithin(
                $tx,$orderId,$claim['operation_id'],$body['expected_version']
            ),$partial
        );
    }

    private static function input(mixed $body): array
    {
        if (!\is_array($body) || \array_is_list($body)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $required=['delta_cents','reason','expected_version','idempotency_key'];
        $allowed=\array_merge($required,[
            'line_id','case_id','affected_units','unit_effect',
            'expected_case_version',
        ]);
        if ([]!==\array_diff($required,\array_keys($body))
            || []!==\array_diff(\array_keys($body),$allowed)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $hasUnits=\array_key_exists('affected_units',$body);
        $hasEffect=\array_key_exists('unit_effect',$body);
        $hasCaseVersion=\array_key_exists('expected_case_version',$body);
        if (($hasUnits||$hasEffect||$hasCaseVersion)
            && (!($hasUnits&&$hasEffect&&$hasCaseVersion)
                || !isset($body['line_id'],$body['case_id']))
            || ($hasUnits
                && (!\is_int($body['affected_units'])
                    || $body['affected_units']<1
                    || $body['affected_units']>100000
                    || $body['unit_effect']!==self::UNIT_EFFECT))) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try {
            foreach (['line_id','case_id','expected_version','expected_case_version'] as $id) {
                if (\array_key_exists($id,$body)) {
                    if (!\is_string($body[$id])) {
                        throw new \InvalidArgumentException();
                    }
                    Domain_Validation::sqlId($body[$id]);
                }
            }
            if (!\is_string($body['delta_cents'])
                || 0===Money::fromSignedLedger($body['delta_cents'])
                || !\is_string($body['reason'])
                || ''===\trim($body['reason'])
                || !\mb_check_encoding($body['reason'],'UTF-8')
                || \mb_strlen($body['reason'],'UTF-8')>1000
                || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
                    $body['reason'])
                || !\is_string($body['idempotency_key'])
                || !\mb_check_encoding($body['idempotency_key'],'UTF-8')
                || \strlen($body['idempotency_key'])<16
                || \strlen($body['idempotency_key'])>128
                || \preg_match('/[\x00-\x20\x7F]/',$body['idempotency_key'])) {
                throw new \InvalidArgumentException();
            }
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return $body;
    }

    private static function staffReady(?string $role): bool
    {
        return 'A'===$role
            && 'A'===Access_Roles::currentRole()
            && \current_user_can('perucommerce_orders_financial_adjust')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin')&&\is_network_admin())
            && Module_Gates::allows('orders','adjust');
    }

    private static function encode(array $snapshot): string
    {
        $raw=\json_encode($snapshot,
            \JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES|\JSON_THROW_ON_ERROR);
        if (!\is_string($raw) || \strlen($raw)>6500) {
            throw new Transaction_Failure('pc_adjust_write_failed');
        }
        return $raw;
    }

    private static function lastId(Transaction_Context $tx): string
    {
        $row=$tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1!==\count($row)) {
            throw new Transaction_Failure('pc_adjust_write_failed');
        }
        return Domain_Validation::sqlId((string)$row[0]['id']);
    }

    private static function result(
        string $operationId,string $adjustmentId,string $version,bool $partial
    ): array {
        return [
            'operation_id'=>$operationId,
            'entity_id'=>$adjustmentId,
            'row_version'=>$version,
            'result'=>$partial?'partial_units_adjusted':'monetary_adjusted',
            'public_status'=>'obligation_adjusted',
        ];
    }
}
