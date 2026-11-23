<?php
/**
 * IMP-30 — R8-30 resolución verificable de caso MP con CAS, nuevo
 * inventario y primera cita cuando existe acuerdo real validado.
 * Nunca POST a Mercado Pago, refund API, reactivación de holds o
 * acreditación derivada de un formulario administrativo.
 */
declare(strict_types=1);
namespace PeruCommerce;
if (!\defined('ABSPATH')) { exit; }

final class MP_Reconciliation_Service
{
    /** @var callable|null Verificador real de consentimiento registrado. */
    private static $agreementVerifier=null;

    public static function registerBuyerAgreementVerifier(callable $verifier): void
    {
        if (null!==self::$agreementVerifier) {
            throw new \LogicException('pc_reconciliation_agreement_duplicate');
        }
        self::$agreementVerifier=$verifier;
    }

    /** GET oficial FUERA SQL; precondiciones en dos TX cortas. */
    public static function resolve(string $caseId,array $body): array
    {
        MP_Reconciliation_Cases::administrator();
        $caseId=Domain_Validation::sqlId($caseId);
        $resolution=$body['resolution']??null;
        $expected=$body['expected_version']??null;
        $evidence=$body['evidence']??null;
        $key=$body['idempotency_key']??null;
        if (!\is_string($resolution)
            || !\in_array($resolution,['close_no_charge','reassign_resources'],true)
            || !\is_string($expected) || !\is_string($evidence)
            || !\is_string($key) || \strlen($evidence)>500
            || ''===\trim($evidence)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        Domain_Validation::sqlId($expected);
        // Evidencia es referencia documental, no prueba de fondos.
        if (\preg_match('/[\x00-\x1F\x7F]/',$evidence)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $case=self::readCase($caseId);
        $actor=Event_Actor::staff(['perucommerce_reconciliation_manage']);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'case','resource_id'=>$caseId,
            'purpose'=>'mp_reconciliation_resolution',
        ];
        if (MP_Reconciliation_Cases::OPEN!==$case['case_phase']) {
            // Recuperar un resultado propio conocido SIN repetir GET ni
            // exigir que la cuenta externa siga disponible en un replay.
            $saved=Idempotency::lookup(
                $scope,'mp_reconciliation_resolution',$key,$body
            );
            if ('replay'!==$saved['status']
                || 'case'!==$saved['entity_type']
                || $saved['entity_id']!==$caseId) {
                throw new Transaction_Failure('pc_mp_reconciliation_conflict');
            }
            return self::result($saved['operation_id'],$caseId,
                $case['row_version'],'replayed');
        }
        $orderId=$case['order_id'];
        if (null===$orderId) {
            // Casos MP prepedido: no adivinar pedido ni inventar derechos.
            throw new Transaction_Failure('pc_mp_reconciliation_preorder');
        }
        if ('reassign_resources'===$resolution
            && ($body['order_id']??null)!==$orderId) {
            throw new Transaction_Failure('pc_mp_reconciliation_scope');
        }
        if ('close_no_charge'===$resolution
            && (isset($body['new_service_start_at_utc'])
                || isset($body['new_service_end_at_utc'])
                || isset($body['buyer_agreement_evidence']))) {
            throw new Rest_Failure('pc_field_invalid',422);
        }

        $recovery=Idempotency::recoveryContextForAdmin(
            $case['source_operation_id']
        );
        if ($recovery['order_id']!==$orderId) {
            throw new Transaction_Failure('pc_mp_reconciliation_scope');
        }
        $providerId=self::providerOrderId($orderId,$recovery);
        // Contrastar estado y fondo de la order contra MP, sin
        // interpolación ni credenciales recibidas del navegador.
        $official=MP_Orders_Client::getOrder($recovery,$providerId);
        $isCredit='processed'===($official['status']??null)
            && 'accredited'===($official['status_detail']??null)
            && \is_array($official['verified_payment']??null)
            && ($official['paid_cents']??null)===($official['amount_cents']??null);
        if ('reassign_resources'===$resolution && !$isCredit
            || 'close_no_charge'===$resolution
                && (!\in_array(($official['status']??null),
                    ['failed','canceled'],true)
                    || '0'!==($official['paid_cents']??null))) {
            throw new Transaction_Failure('pc_mp_reconciliation_evidence');
        }
        if ('reassign_resources'===$resolution) {
            // Registrar solo los verificadores SQL, nunca un pago ni un
            // sourceVerifier ficticio. El dinero procede de ledger+GET.
            MP_Credit_Application::register();
        }
        $state=Transaction::run(static function(Transaction_Context $tx) use(
            $scope,$key,$body,$actor,$caseId,$expected,$orderId,
            $resolution,$official,$recovery,$evidence
        ): array {
            $claim=Idempotency::claimWithin(
                $tx,$scope,'mp_reconciliation_resolution',$key,$body
            );
            if ('replay'===$claim['status']) {
                $current=$tx->rows(
                    'SELECT case_phase,row_version FROM %i WHERE id = %s '
                        . 'AND case_type = %s',
                    [Orders_Domain::table('pc_cases'),$caseId,
                        MP_Reconciliation_Cases::KIND]
                );
                if (1!==\count($current)
                    || 'case'!==$claim['entity_type']
                    || $caseId!==$claim['entity_id']
                    || !\in_array($current[0]['case_phase'],[
                        MP_Reconciliation_Cases::CLOSED_NO_CHARGE,
                        MP_Reconciliation_Cases::CLOSED_REASSIGNED],true)) {
                    throw new Transaction_Failure('pc_mp_reconciliation_conflict');
                }
                return self::result($claim['operation_id'],$caseId,
                    (string)$current[0]['row_version'],'replayed');
            }
            if ('new'!==$claim['status']) {
                throw new Transaction_Failure('pc_mp_reconciliation_pending');
            }
            $op=$claim['operation_id'];
            $order=$tx->lockOrder($orderId);
            if ('mercado_pago'!==(string)$order['payment_method']) {
                throw new Transaction_Failure('pc_mp_reconciliation_conflict');
            }
            // Antes de caso (stage40), tomar líneas (stage30), para
            // conservar siempre op -> pedido -> líneas -> caso -> stock.
            $entries=$tx->rows(
                'SELECT id,sellable_id,kind,quantity,fulfillment_state '
                    . 'FROM %i WHERE order_id = %s ORDER BY id LIMIT 101',
                [Orders_Domain::table('pc_order_lines'),$orderId]
            );
            if ([]===$entries || \count($entries)>100) {
                throw new Transaction_Failure('pc_mp_reconciliation_conflict');
            }
            foreach ($entries as $entry) {
                $tx->lockOrderLine($orderId,(string)$entry['id']);
            }
            $locked=$tx->lockCaseForOrder($orderId,$caseId);
            if (MP_Reconciliation_Cases::KIND!==$locked['case_type']
                || MP_Reconciliation_Cases::OPEN!==$locked['case_phase']
                || (string)$locked['row_version']!==$expected) {
                throw new Transaction_Failure('pc_mp_reconciliation_conflict');
            }
            $source=$tx->rows(
                'SELECT source_operation_id FROM %i WHERE id = %s',
                [Orders_Domain::table('pc_cases'),$caseId]
            );
            if (1!==\count($source)
                || (string)$source[0]['source_operation_id']!==
                    $recovery['operation_id']) {
                throw new Transaction_Failure('pc_mp_reconciliation_scope');
            }
            if ('close_no_charge'===$resolution) {
                if (!\in_array((string)$order['status'],
                    ['PENDIENTE_PAGO','VENCIDO'],true)
                    || []!==$tx->rows(
                        'SELECT id FROM %i WHERE order_id = %s LIMIT 1',
                        [Orders_Domain::table('pc_payment_events'),$orderId]
                    )
                    || []!==$tx->rows(
                        'SELECT a.id FROM %i a WHERE a.order_id=%s LIMIT 1',
                        [Orders_Domain::table('pc_appointments'),$orderId]
                    )
                    || []!==$tx->rows(
                        'SELECT c.id FROM %i c INNER JOIN %i l '
                            . 'ON l.id=c.order_line_id '
                            . 'WHERE l.order_id=%s LIMIT 1',
                        [Orders_Domain::table('pc_stock_commitments'),
                            Orders_Domain::table('pc_order_lines'),$orderId]
                    )
                    || []!==$tx->rows(
                        'SELECT h.id FROM %i h WHERE h.order_id=%s '
                            . 'AND h.state=%s AND h.expires_at>UTC_TIMESTAMP(6) '
                            . 'LIMIT 1',
                        [Orders_Domain::table('pc_holds'),$orderId,'VIGENTE']
                    )) {
                    throw new Transaction_Failure('pc_mp_reconciliation_conflict');
                }
                $attempt=$tx->rows(
                    'SELECT id,row_version,state FROM %i '
                        . 'WHERE order_id = %s AND provider_order_id = %s '
                        . 'AND provider = %s AND provider_account_key=UNHEX(%s) '
                        . 'AND environment=%s AND mp_idempotency_key=%s FOR UPDATE',
                    [Orders_Domain::table('pc_payment_attempts'),$orderId,
                        $official['provider_order_id'],'mercado_pago',
                        $recovery['provider_account_digest'],
                        $recovery['environment'],
                        $recovery['provider_idempotency_key']]
                );
                if (1!==\count($attempt)
                    || !\in_array($attempt[0]['state'],
                        ['CREADO','PENDIENTE','REQUIERE_CONCILIACION'],true)) {
                    throw new Transaction_Failure('pc_mp_reconciliation_conflict');
                }
                $attemptState='canceled'===$official['status']
                    ? 'CANCELADO_PROVEEDOR' : 'RECHAZADO';
                if (1!==$tx->execute(
                    'UPDATE %i SET state = %s,row_version=row_version+1 '
                        . 'WHERE id = %s AND row_version = %s '
                        . 'AND state IN (%s,%s,%s)',
                    [Orders_Domain::table('pc_payment_attempts'),$attemptState,
                        (string)$attempt[0]['id'],
                        (string)$attempt[0]['row_version'],
                        'CREADO','PENDIENTE','REQUIERE_CONCILIACION']
                )) {
                    throw new Transaction_Failure('pc_mp_reconciliation_conflict');
                }
                $next=MP_Reconciliation_Cases::CLOSED_NO_CHARGE;
            } else {
                self::reassignWithin(
                    $tx,$actor,$op,$orderId,$entries,$official,
                    $recovery,$body
                );
                if (\is_string($body['buyer_agreement_evidence']??null)) {
                    Event_Journal::auditWithin(
                        $tx,$actor,$op,'case',$caseId,
                        'mp_buyer_agreement_verified','new_service_slot',
                        null,\hash('sha256',$body['buyer_agreement_evidence'])
                    );
                }
                $next=MP_Reconciliation_Cases::CLOSED_REASSIGNED;
            }
            $version=$tx->advanceVersion('pc_cases',$caseId,$expected);
            if (1!==$tx->execute(
                'UPDATE %i SET case_phase = %s,resolved_at=UTC_TIMESTAMP(6) '
                    . 'WHERE id = %s AND case_type = %s '
                    . 'AND case_phase = %s AND row_version = %s',
                [Orders_Domain::table('pc_cases'),$next,$caseId,
                    MP_Reconciliation_Cases::KIND,
                    MP_Reconciliation_Cases::OPEN,$version]
            )) {
                throw new Transaction_Failure('pc_mp_reconciliation_conflict');
            }
            Event_Journal::caseWithin(
                $tx,$actor,$op,$caseId,'mp_reconciliation_resolved',
                $resolution,'mp_resolution',\hash('sha256',$evidence)
            );
            Orders_Finalization::afterWithin($tx,$actor,$op,$orderId);
            Idempotency::completeLocalWithin($tx,$op,'case',$caseId,200);
            return self::result($op,$caseId,$version,'resolved');
        },true);
        return $state;
    }

    /**
     * Solo con el crédito ya registrado y verificado por GET oficial.
     * Todos los recursos SE REASIGNAN desde disponibilidad actual, no
     * desde las admisiones/holds de hace 15 minutos.
     */
    private static function reassignWithin(
        Transaction_Context $tx,Event_Actor $actor,string $op,
        string $orderId,array $entries,array $official,
        array $recovery,array $body
    ): void {
        if (!Module_Gates::allows('payments','write')
            || !\in_array((string)$tx->lockedOrder($orderId)['status'],
                ['PENDIENTE_PAGO','VENCIDO'],true)) {
            throw new Transaction_Failure('pc_mp_reconciliation_not_ready');
        }
        $attempts=$tx->rows(
            'SELECT id,state,provider_payment_id,amount_cents,environment,'
                . 'LOWER(HEX(provider_account_key)) AS digest FROM %i '
                . 'WHERE order_id = %s AND provider = %s '
                . 'AND provider_order_id = %s FOR UPDATE',
            [Orders_Domain::table('pc_payment_attempts'),
                $orderId,'mercado_pago',$official['provider_order_id']]
        );
        $credits=$tx->rows(
            'SELECT id,kind,provider,amount_cents,external_reference,'
                . 'payment_attempt_id FROM %i WHERE order_id = %s '
                . 'AND kind = %s LIMIT 2',
            [Orders_Domain::table('pc_payment_events'),
                $orderId,'initial_charge']
        );
        $proof=$official['verified_payment'];
        if (1!==\count($attempts) || 1!==\count($credits)
            || 'APROBADO'!==$attempts[0]['state']
            || $attempts[0]['provider_payment_id']!==$proof['provider_payment_id']
            || $attempts[0]['digest']!==$recovery['provider_account_digest']
            || $attempts[0]['environment']!==$recovery['environment']
            || $credits[0]['kind']!=='initial_charge'
            || $credits[0]['provider']!=='mercado_pago'
            || $credits[0]['external_reference']!==$proof['provider_payment_id']
            || (string)$credits[0]['amount_cents']!==$official['amount_cents']
            || (string)$credits[0]['payment_attempt_id']!==
                (string)$attempts[0]['id']) {
            throw new Transaction_Failure('pc_mp_reconciliation_evidence');
        }
        $physical=[];$service=null;$digital=false;
        foreach ($entries as $line) {
            if ('physical'===$line['kind']) { $physical[]=$line; }
            if ('service'===$line['kind']) {
                if (null!==$service) {
                    throw new Transaction_Failure('pc_mp_reconciliation_conflict');
                }
                $service=$line;
            }
            if ('digital'===$line['kind']) { $digital=true; }
        }
        if ([]!==$physical && !Module_Gates::allows('inventory','write')
            || null!==$service && !Module_Gates::allows('appointments','write')
            || $digital && !Module_Gates::allows('digital','write')) {
            throw new Transaction_Failure('pc_mp_reconciliation_not_ready');
        }
        $newStart=$body['new_service_start_at_utc']??null;
        $newEnd=$body['new_service_end_at_utc']??null;
        $agreement=$body['buyer_agreement_evidence']??null;
        if (null!==$service) {
            if (!\is_string($newStart) || !\is_string($newEnd)
                || !\is_string($agreement) || ''===\trim($agreement)
                || null===self::$agreementVerifier) {
                throw new Transaction_Failure('pc_mp_reconciliation_agreement');
            }
            try {
                if (true!==(self::$agreementVerifier)(
                    $orderId,(string)$service['id'],$newStart,$newEnd,$agreement
                )) {
                    throw new \UnexpectedValueException();
                }
            } catch (\Throwable $ignored) {
                throw new Transaction_Failure('pc_mp_reconciliation_agreement');
            }
        } elseif (null!==$newStart || null!==$newEnd || null!==$agreement) {
            throw new Rest_Failure('pc_field_invalid',422);
        }

        $ids=\array_column($entries,'sellable_id');
        $tx->lockSellables($ids);
        $serviceSnapshot=null;$interval=null;
        if (null!==$service) {
            $serviceSnapshot=Checkout_Appointments::fromOrderLineWithin(
                $tx,$orderId,(string)$service['id']
            );
            $originalStart=Business_Time::fromApi(
                $serviceSnapshot['slot']['starts_at_utc']
            );
            $originalEnd=Business_Time::fromApi(
                $serviceSnapshot['slot']['ends_at_utc']
            );
            $start=Business_Time::fromApi($newStart);
            $end=Business_Time::fromApi($newEnd);
            if ($end->getTimestamp()-$start->getTimestamp() !==
                $originalEnd->getTimestamp()-$originalStart->getTimestamp()) {
                throw new Transaction_Failure('pc_mp_reconciliation_agreement');
            }
            $serviceSnapshot['slot']['starts_at_utc']=$newStart;
            $serviceSnapshot['slot']['ends_at_utc']=$newEnd;
            $interval=Checkout_Appointments::lockSlotWithin(
                $tx,$serviceSnapshot
            );
        }
        // El crédito previo es inmutable; solo habilitar el verificador de
        // derechos digitales DENTRO de esta operación autorizada.
        MP_Credit_Application::withReconciledCreditWithin(
            $tx,$actor,$op,$orderId,(string)$attempts[0]['id'],
            (string)$credits[0]['id'],$official,
            static function() use (
                $tx,$actor,$op,$orderId,$physical,$service,
                $serviceSnapshot,$interval,$digital,$credits
            ): void {
                foreach ($physical as $line) {
                    Inventory_Reservations::commitReconciledWithin(
                        $tx,$actor,$op,$orderId,
                        (string)$line['id'],(string)$line['sellable_id']
                    );
                }
                if (null!==$service) {
                    Checkout_Appointments::firstReconciledWithin(
                        $tx,$actor,$op,$orderId,(string)$service['id'],
                        $serviceSnapshot,$interval
                    );
                }
                $order=$tx->lockedOrder($orderId);
                if (1!==$tx->execute(
                    'UPDATE %i SET status=%s,confirmed_at=UTC_TIMESTAMP(6),'
                        . 'row_version=row_version+1,updated_at=UTC_TIMESTAMP(6) '
                        . 'WHERE id=%s AND status=%s AND row_version=%s',
                    [Orders_Domain::table('pc_orders'),'CONFIRMADO',$orderId,
                        (string)$order['status'],(string)$order['row_version']]
                )) {
                    throw new Transaction_Failure('pc_mp_reconciliation_conflict');
                }
                if ($digital) {
                    Digital_Rights::grantForOrderWithin(
                        $tx,$actor,$op,$orderId,
                        (string)$credits[0]['id']
                    );
                }
            }
        );
    }

    private static function readCase(string $caseId): array
    {
        return Transaction::run(static function(Transaction_Context $tx) use($caseId): array {
            $c=$tx->rows(
                'SELECT id,order_id,source_operation_id,case_phase,'
                    . 'row_version FROM %i WHERE id=%s AND case_type=%s',
                [Orders_Domain::table('pc_cases'),$caseId,
                    MP_Reconciliation_Cases::KIND]
            );
            if (1!==\count($c) || null===$c[0]['source_operation_id']) {
                throw new Transaction_Failure('pc_mp_case_not_found');
            }
            return [
                'case_phase'=>(string)$c[0]['case_phase'],
                'row_version'=>(string)$c[0]['row_version'],
                'order_id'=>null===$c[0]['order_id']?null
                    :Domain_Validation::sqlId((string)$c[0]['order_id']),
                'source_operation_id'=>Domain_Validation::sqlId(
                    (string)$c[0]['source_operation_id']
                ),
            ];
        });
    }

    private static function providerOrderId(string $orderId,array $recovery): string
    {
        return Transaction::run(static function(Transaction_Context $tx) use(
            $orderId,$recovery
        ): string {
            $rows=$tx->rows(
                'SELECT provider_order_id FROM %i WHERE order_id=%s '
                    . 'AND provider=%s AND provider_account_key=UNHEX(%s) '
                    . 'AND environment=%s AND mp_idempotency_key=%s',
                [Orders_Domain::table('pc_payment_attempts'),$orderId,
                    'mercado_pago',$recovery['provider_account_digest'],
                    $recovery['environment'],$recovery['provider_idempotency_key']]
            );
            if (1!==\count($rows)
                || !\is_string($rows[0]['provider_order_id']??null)
                || !\preg_match('/\AORD[A-Z0-9]{16,61}\z/D',
                    $rows[0]['provider_order_id'])) {
                throw new Transaction_Failure('pc_mp_reconciliation_evidence');
            }
            return $rows[0]['provider_order_id'];
        });
    }

    private static function result(
        string $operationId,string $caseId,string $version,string $result
    ): array {
        return [
            'operation_id'=>$operationId,
            'entity_id'=>$caseId,
            'row_version'=>$version,
            'result'=>$result,
            'public_status'=>'case_recorded',
        ];
    }
}
