<?php
/**
 * IMP-28 — único efecto financiero para pago MP autorizado externamente.
 *
 * SERVICIO INTERNO, no REST. Implica un actor system autenticado por
 * IMP-29/30 y un root crediticio nuevo en fase prepared. Requiere el GET
 * oficial ya validado (incluye payments[0] processed/accredited por
 * importe exacto), además de una prueba de origen emitida por la capa
 * de webhook/worker. No basta el retorno del navegador ni status=processed.
 *
 * Sin SourceVerifier registrado, permanece fail-closed: los webhooks
 * firmados y conciliación posterior conectarán la fuente en IMP-29/30.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Credit_Application
{
    /** @var callable|null Autorización por fuente firmada (IMP-29/30). */
    private static $sourceVerifier=null;
    /** @var \WeakMap<Event_Actor,array<string,mixed>>|null */
    private static ?\WeakMap $active=null;
    private static bool $wired=false;

    public static function registerSourceVerifier(callable $verifier): void
    {
        if (null!==self::$sourceVerifier) {
            throw new \LogicException('pc_mp_credit_source_duplicate');
        }
        self::$sourceVerifier=$verifier;
    }

    /**
     * Conectar validadores del ledger, recursos y derechos sin originar
     * autorización propia. Se exige un actor system legítimo y evidencia
     * externa validada por sourceVerifier al invocar applyWithin().
     */
    public static function register(): void
    {
        if (self::$wired) { return; }
        Transaction::assertOutside();
        Bootstrap::loadCheckout();
        Bootstrap::loadDigitalRights();
        Bootstrap::loadEvents();
        self::$active=new \WeakMap();
        Financial_Events::registerEvidenceVerifier(
            static function (array $fact,string $orderId,Event_Actor $actor): bool {
                return null!==self::$active && isset(self::$active[$actor])
                    && self::$active[$actor]['order_id']===$orderId
                    && self::$active[$actor]['fact']===$fact;
            }
        );
        Checkout_Coordinator::registerPaidVerifier(
            [self::class,'verifiedCreditWithin']
        );
        Digital_Rights::registerCreditVerifier(
            static function (
                Transaction_Context $tx,Event_Actor $actor,string $operationId,
                string $orderId,array $credit
            ): bool {
                return self::verifiedCreditWithin(
                    $tx,$actor,$operationId,$orderId,
                    (string)($credit['id']??''),(string)($credit['payment_attempt_id']??'')
                );
            }
        );
        self::$wired=true;
    }

    /**
     * Precondición de IMP-29/30: root de acreditación separado y
     * autenticado por firma/worker de confianza; lockOperation(root)
     * previamente adquirido. El checkout del comprador JAMÁS lo llama.
     *
     * Un fallo de holds/agenda revierte dinero+recursos+derechos juntos,
     * dejando la cuenta externa para conciliación de pago tardío IMP-30.
     *
     * @return array{order_id:string,credit_event_id:string,rights:int}
     */
    public static function applyWithin(
        Transaction_Context $tx,Event_Actor $actor,
        string $creditOperationId,array $recovery,array $verified
    ): array {
        if (!self::$wired || null===self::$sourceVerifier) {
            throw new Transaction_Failure('pc_mp_credit_source_not_ready');
        }
        $creditOperationId=Event_Journal::requirePendingOperation(
            $tx,$creditOperationId
        );
        $actor->assertCurrent();
        $orderId=Domain_Validation::sqlId((string)($recovery['order_id']??''));
        $rootId=Domain_Validation::sqlId((string)($recovery['operation_id']??''));
        $payment=$verified['verified_payment']??null;
        if ('system'!==$actor->kind()
            || $rootId===$creditOperationId
            || null===self::$sourceVerifier
            || !\is_array($payment)
            || 'processed'!==($verified['status']??null)
            || 'accredited'!==($verified['status_detail']??null)
            || ($verified['paid_cents']??null)!==($verified['amount_cents']??null)
            || ($payment['amount_cents']??null)!==($verified['amount_cents']??null)
            || !\is_string($payment['provider_payment_id']??null)
            || !\preg_match('/\APAY[A-Z0-9]{12,61}\z/D',
                $payment['provider_payment_id'])
            || !\is_string($payment['verified_at_utc']??null)
            || !\is_string($verified['provider_order_id']??null)
            || !\is_string($recovery['provider_account_digest']??null)
            || !\is_string($recovery['environment']??null)
            || !\is_string($recovery['external_reference']??null)) {
            throw new Transaction_Failure('pc_mp_credit_unverified');
        }
        try {
            if (true!==(self::$sourceVerifier)(
                $actor,$creditOperationId,$recovery,$verified
            )) {
                throw new \UnexpectedValueException();
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_mp_credit_unverified');
        }
        $order=$tx->lockOrder($orderId);
        if ('PENDIENTE_PAGO'!==(string)$order['status']
            || 'mercado_pago'!==(string)$order['payment_method']) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        // No adquirir un lockOperation del checkout viejo después del
        // pedido (inversión). Leer sus datos inmutables con la fila de
        // pedido bloqueada; raíces originales se confirmaron antes del HTTP.
        $link=$tx->rows(
            'SELECT entity_type,entity_id,provider_external_reference,'
                . 'provider_environment,HEX(provider_account_key) AS digest,'
                . 'provider_idempotency_key '
                . 'FROM %i WHERE id = %s AND operation_type = %s',
            [Orders_Domain::table('pc_idempotency'),$rootId,'checkout_mp']
        );
        $orderAmounts=$tx->rows(
            'SELECT total_cents,currency FROM %i WHERE id = %s',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($link) || 1!==\count($orderAmounts)
            || 'order'!==$link[0]['entity_type']
            || (string)$link[0]['entity_id']!==$orderId
            || $link[0]['provider_external_reference']!==$recovery['external_reference']
            || $link[0]['provider_environment']!==$recovery['environment']
            || !\hash_equals(
                $recovery['provider_account_digest'],
                \strtolower((string)$link[0]['digest'])
            )
            || $orderAmounts[0]['currency']!=='PEN'
            || (string)$orderAmounts[0]['total_cents']!==$verified['amount_cents']) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        $attempt=$tx->rows(
            'SELECT id,state,amount_cents,mp_idempotency_key,row_version '
                . 'FROM %i WHERE order_id = %s AND provider = %s '
                . 'AND provider_account_key = UNHEX(%s) AND environment = %s '
                . 'AND provider_order_id = %s FOR UPDATE',
            [Orders_Domain::table('pc_payment_attempts'),$orderId,
                'mercado_pago',$recovery['provider_account_digest'],
                $recovery['environment'],$verified['provider_order_id']]
        );
        if (1!==\count($attempt)
            || !\in_array($attempt[0]['state'],['CREADO','PENDIENTE'],true)
            || (string)$attempt[0]['amount_cents']!==$verified['amount_cents']
            || $attempt[0]['mp_idempotency_key']!==$link[0]['provider_idempotency_key']) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        $attemptId=Domain_Validation::sqlId((string)$attempt[0]['id']);
        $existing=$tx->rows(
            'SELECT id FROM %i WHERE order_id = %s AND kind = %s LIMIT 1',
            [Orders_Domain::table('pc_payment_events'),$orderId,'initial_charge']
        );
        if ([]!==$existing) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        $fact=[
            'kind'=>'initial_charge',
            'amount_cents'=>$verified['amount_cents'],
            'currency'=>'PEN','provider'=>'mercado_pago',
            'account_digest'=>$recovery['provider_account_digest'],
            'environment'=>$recovery['environment'],
            // Identificador oficial único del pago, no la referencia del
            // carrito; evita acreditar dos veces el mismo pago.
            'external_reference'=>$payment['provider_payment_id'],
            'verification_reference'=>$verified['provider_order_id']
                . ':' . $payment['provider_payment_id'],
            'occurred_at_utc'=>$payment['verified_at_utc'],
            'payment_attempt_id'=>$attemptId,
            'source_payment_event_id'=>null,
        ];
        self::$active[$actor]=[
            'operation_id'=>$creditOperationId,
            'order_id'=>$orderId,'attempt_id'=>$attemptId,
            'payment_id'=>$payment['provider_payment_id'],
            'amount'=>$verified['amount_cents'],
            'event_id'=>null,'fact'=>$fact,
        ];
        try {
            $creditEvent=Financial_Events::appendWithin(
                $tx,$actor,$creditOperationId,$orderId,$fact
            );
            self::$active[$actor]['event_id']=$creditEvent;
            if (1!==$tx->execute(
                'UPDATE %i SET state = %s,provider_payment_id = %s,'
                    . 'row_version = row_version + 1 '
                    . 'WHERE id = %s AND order_id = %s AND state IN (%s,%s) '
                    . 'AND provider_payment_id IS NULL AND row_version = %s',
                [Orders_Domain::table('pc_payment_attempts'),'APROBADO',
                    $payment['provider_payment_id'],$attemptId,$orderId,
                    'CREADO','PENDIENTE',(string)$attempt[0]['row_version']]
            )) {
                throw new Transaction_Failure('pc_mp_credit_conflict');
            }
            if (1!==$tx->execute(
                'UPDATE %i SET status = %s,confirmed_at = UTC_TIMESTAMP(6),'
                    . 'row_version = row_version + 1,updated_at = UTC_TIMESTAMP(6) '
                    . 'WHERE id = %s AND status = %s AND row_version = %s',
                [Orders_Domain::table('pc_orders'),'CONFIRMADO',$orderId,
                    'PENDIENTE_PAGO',(string)$order['row_version']]
            )) {
                throw new Transaction_Failure('pc_mp_credit_conflict');
            }
            // Refrescar la instantánea bloqueada antes de los requisitos
            // CONFIRMADO que reutilizan los mismos locks en dominio.
            $tx->lockOrder($orderId);
            Checkout_Coordinator::confirmPaidResourcesWithin(
                $tx,$actor,$creditOperationId,$orderId,$attemptId,$creditEvent
            );
            $digital=$tx->rows(
                'SELECT id FROM %i WHERE order_id = %s AND kind = %s LIMIT 1',
                [Orders_Domain::table('pc_order_lines'),$orderId,'digital']
            );
            $rights=[]===$digital ? [] : Digital_Rights::grantForOrderWithin(
                $tx,$actor,$creditOperationId,$orderId,$creditEvent
            );
            Event_Journal::auditWithin(
                $tx,$actor,$creditOperationId,'order',$orderId,
                'mp_initial_credit_verified','provider_accredited'
            );
            Queue_Outbox::essentialWithin(
                $tx,$actor,$creditOperationId,$orderId,
                'mp_payment_confirmed',$orderId
            );
            if([]!==$rights){
                Queue_Outbox::essentialWithin(
                    $tx,$actor,$creditOperationId,$orderId,
                    'digital_access_enabled',$orderId
                );
            }
            Queue_Outbox::serviceConfirmedWithin(
                $tx,$actor,$creditOperationId,$orderId,'mp_initial_credit_verified'
            );
            Orders_Finalization::afterWithin(
                $tx,$actor,$creditOperationId,$orderId
            );
            Idempotency::completeLocalWithin(
                $tx,$creditOperationId,'order',$orderId,200
            );
            return [
                'order_id'=>$orderId,'credit_event_id'=>$creditEvent,
                'rights'=>\count($rights),
            ];
        } finally {
            if (null!==self::$active && isset(self::$active[$actor])) {
                unset(self::$active[$actor]);
            }
        }
    }

    /**
     * IMP-30: pago MP REAL pero sin recursos reservables. Registra una
     * única acreditación inmutable y un caso abierto, NO CONFIRMA pedido,
     * NO reanima hold vencido, NO concede derechos ni crea cita.
     * Solamente el worker de webhook firmado llama después de un GET
     * autoritativo; requiere el mismo sourceVerifier de applyWithin.
     */
    public static function recordUnallocatedWithin(
        Transaction_Context $tx,Event_Actor $actor,string $creditOp,
        array $recovery,array $verified,string $reason
    ): string {
        if (!self::$wired || null===self::$sourceVerifier
            || 'system'!==$actor->kind()
            || !\in_array($reason,[
                'mp_hold_expired','mp_stock_unavailable',
                'mp_slot_unavailable','mp_resources_missing'
            ],true)) {
            throw new Transaction_Failure('pc_mp_credit_unverified');
        }
        $creditOp=Event_Journal::requirePendingOperation($tx,$creditOp);
        $actor->assertCurrent();
        $orderId=Domain_Validation::sqlId((string)($recovery['order_id']??''));
        $root=Domain_Validation::sqlId((string)($recovery['operation_id']??''));
        $payment=$verified['verified_payment']??null;
        if ($root===$creditOp || !\is_array($payment)
            || 'processed'!==($verified['status']??null)
            || 'accredited'!==($verified['status_detail']??null)
            || ($verified['paid_cents']??null)!==($verified['amount_cents']??null)
            || ($payment['amount_cents']??null)!==($verified['amount_cents']??null)
            || !\is_string($payment['provider_payment_id']??null)
            || !\preg_match('/\APAY[A-Z0-9]{12,61}\z/D',
                $payment['provider_payment_id'])
            || !\is_string($payment['verified_at_utc']??null)
            || !\is_string($recovery['provider_account_digest']??null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',
                $recovery['provider_account_digest'])
            || !\in_array(($recovery['environment']??null),
                ['sandbox','production'],true)
            || !\is_string($recovery['external_reference']??null)) {
            throw new Transaction_Failure('pc_mp_credit_unverified');
        }
        try {
            if (true!==(self::$sourceVerifier)(
                $actor,$creditOp,$recovery,$verified
            )) {
                throw new \UnexpectedValueException();
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_mp_credit_unverified');
        }
        $locked=$tx->lockOrder($orderId);
        if (!\in_array((string)$locked['status'],
                ['PENDIENTE_PAGO','VENCIDO'],true)
            || 'mercado_pago'!==(string)$locked['payment_method']) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        $links=$tx->rows(
            'SELECT entity_type,entity_id,provider_external_reference,'
                . 'provider_environment,provider_idempotency_key,'
                . 'LOWER(HEX(provider_account_key)) AS digest '
                . 'FROM %i WHERE id = %s AND operation_type = %s',
            [Orders_Domain::table('pc_idempotency'),$root,'checkout_mp']
        );
        $totals=$tx->rows(
            'SELECT total_cents,currency FROM %i WHERE id = %s',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($links) || 1!==\count($totals)
            || 'order'!==$links[0]['entity_type']
            || (string)$links[0]['entity_id']!==$orderId
            || $links[0]['provider_external_reference']!==$recovery['external_reference']
            || $links[0]['provider_environment']!==$recovery['environment']
            || $links[0]['digest']!==$recovery['provider_account_digest']
            || 'PEN'!==$totals[0]['currency']
            || (string)$totals[0]['total_cents']!==$verified['amount_cents']) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        $attempts=$tx->rows(
            'SELECT id,state,amount_cents,mp_idempotency_key,row_version '
                . 'FROM %i WHERE order_id = %s AND provider = %s '
                . 'AND provider_account_key = UNHEX(%s) AND environment = %s '
                . 'AND provider_order_id = %s FOR UPDATE',
            [Orders_Domain::table('pc_payment_attempts'),$orderId,
                'mercado_pago',$recovery['provider_account_digest'],
                $recovery['environment'],$verified['provider_order_id']]
        );
        if (1!==\count($attempts)
            || !\in_array($attempts[0]['state'],
                ['CREADO','PENDIENTE','REQUIERE_CONCILIACION'],true)
            || (string)$attempts[0]['amount_cents']!==$verified['amount_cents']
            || $attempts[0]['mp_idempotency_key']!==$links[0]['provider_idempotency_key']) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        $attemptId=Domain_Validation::sqlId((string)$attempts[0]['id']);
        if ([]!==$tx->rows(
            'SELECT id FROM %i WHERE order_id = %s AND kind = %s LIMIT 1',
            [Orders_Domain::table('pc_payment_events'),$orderId,'initial_charge']
        )) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        // Solo en ausencia de recursos completos: si todo todavía está
        // retenido, el aplicador estándar, NO este camino, debe confirmarlos.
        $lines=$tx->rows(
            'SELECT id,kind FROM %i WHERE order_id = %s ORDER BY id LIMIT 101',
            [Orders_Domain::table('pc_order_lines'),$orderId]
        );
        if ([]===$lines || \count($lines)>100) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        $allHeld=true;
        $hasReservable=false;
        foreach ($lines as $line) {
            if ('digital'===$line['kind']) { continue; }
            $hasReservable=true;
            $holds=$tx->rows(
                'SELECT id FROM %i WHERE order_line_id = %s '
                    . 'AND payment_attempt_id = %s AND state = %s '
                    . 'AND expires_at > UTC_TIMESTAMP(6) LIMIT 2',
                [Orders_Domain::table('pc_holds'),(string)$line['id'],
                    $attemptId,'VIGENTE']
            );
            if (1!==\count($holds)) { $allHeld=false; }
        }
        if ($hasReservable && $allHeld
            && 'PENDIENTE_PAGO'===(string)$locked['status']) {
            throw new Transaction_Failure('pc_mp_credit_conflict');
        }
        $fact=[
            'kind'=>'initial_charge','amount_cents'=>$verified['amount_cents'],
            'currency'=>'PEN','provider'=>'mercado_pago',
            'account_digest'=>$recovery['provider_account_digest'],
            'environment'=>$recovery['environment'],
            'external_reference'=>$payment['provider_payment_id'],
            'verification_reference'=>$verified['provider_order_id']
                . ':' . $payment['provider_payment_id'],
            'occurred_at_utc'=>$payment['verified_at_utc'],
            'payment_attempt_id'=>$attemptId,
            'source_payment_event_id'=>null,
        ];
        self::$active[$actor]=[
            'operation_id'=>$creditOp,'order_id'=>$orderId,
            'attempt_id'=>$attemptId,'payment_id'=>$payment['provider_payment_id'],
            'amount'=>$verified['amount_cents'],'event_id'=>null,'fact'=>$fact,
        ];
        try {
            Financial_Events::appendWithin($tx,$actor,$creditOp,$orderId,$fact);
            if (1!==$tx->execute(
                'UPDATE %i SET state = %s,provider_payment_id = %s,'
                    . 'row_version = row_version + 1 '
                    . 'WHERE id = %s AND order_id = %s AND state IN (%s,%s,%s) '
                    . 'AND provider_payment_id IS NULL AND row_version = %s',
                [Orders_Domain::table('pc_payment_attempts'),'APROBADO',
                    $payment['provider_payment_id'],$attemptId,$orderId,
                    'CREADO','PENDIENTE','REQUIERE_CONCILIACION',
                    (string)$attempts[0]['row_version']]
            )) {
                throw new Transaction_Failure('pc_mp_credit_conflict');
            }
            $caseId=MP_Reconciliation_Cases::openWithin(
                $tx,$actor,$creditOp,$root,$orderId,$reason,
                $verified['provider_order_id']
            );
            Idempotency::completeLocalWithin(
                $tx,$creditOp,'case',$caseId,200
            );
            return $caseId;
        } finally {
            if (isset(self::$active[$actor])) {
                unset(self::$active[$actor]);
            }
        }
    }

    /**
     * IMP-30: permitir que la conciliación administrativa complete derechos
     * digitales ya pagados SIN generar un nuevo cobro. Solo durante el
     * callback autorizado de R8-30 y con un GET MP validado previamente.
     * Reutiliza el verificador SQL de crédito de IMP-28.
     */
    public static function withReconciledCreditWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $orderId,string $attemptId,string $creditEventId,
        array $verified,callable $work
    ): mixed {
        if (!self::$wired || 'staff'!==$actor->kind()
            || !\current_user_can('perucommerce_reconciliation_manage')) {
            throw new Transaction_Failure('pc_mp_credit_unverified');
        }
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        $tx->lockedOrder($orderId);
        $payment=$verified['verified_payment']??null;
        if ('processed'!==($verified['status']??null)
            || 'accredited'!==($verified['status_detail']??null)
            || !\is_array($payment)
            || ($payment['amount_cents']??null)!==($verified['amount_cents']??null)
            || ($verified['amount_cents']??null)!==($verified['paid_cents']??null)
            || !\is_string($payment['provider_payment_id']??null)) {
            throw new Transaction_Failure('pc_mp_credit_unverified');
        }
        $attemptId=Domain_Validation::sqlId($attemptId);
        $creditEventId=Domain_Validation::sqlId($creditEventId);
        $events=$tx->rows(
            'SELECT e.id,e.amount_cents,e.provider,e.kind,e.external_reference,'
                . 'a.state,a.provider_payment_id FROM %i e '
                . 'INNER JOIN %i a ON a.id=e.payment_attempt_id '
                . 'AND a.order_id=e.order_id '
                . 'WHERE e.id=%s AND e.order_id=%s AND e.payment_attempt_id=%s',
            [Orders_Domain::table('pc_payment_events'),
                Orders_Domain::table('pc_payment_attempts'),$creditEventId,
                $orderId,$attemptId]
        );
        if (1!==\count($events)
            || 'mercado_pago'!==$events[0]['provider']
            || 'initial_charge'!==$events[0]['kind']
            || 'APROBADO'!==$events[0]['state']
            || (string)$events[0]['amount_cents']!==$verified['amount_cents']
            || $events[0]['provider_payment_id']!==$payment['provider_payment_id']
            || $events[0]['external_reference']!==$payment['provider_payment_id']) {
            throw new Transaction_Failure('pc_mp_credit_unverified');
        }
        self::$active[$actor]=[
            'operation_id'=>$operationId,'order_id'=>$orderId,
            'attempt_id'=>$attemptId,
            'payment_id'=>$payment['provider_payment_id'],
            'amount'=>$verified['amount_cents'],
            'event_id'=>$creditEventId,'fact'=>[],
        ];
        try {
            return $work();
        } finally {
            unset(self::$active[$actor]);
        }
    }

    /** Evidencia ligada a este actor, operación y evento del MISMO TX. */
    public static function verifiedCreditWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $orderId,string $eventId,string $paymentAttemptId
    ): bool {
        if (null===self::$active || !isset(self::$active[$actor])) {
            return false;
        }
        $expected=self::$active[$actor];
        if ($expected['operation_id']!==$operationId
            || $expected['order_id']!==$orderId
            || $expected['attempt_id']!==$paymentAttemptId
            || $expected['event_id']!==$eventId) {
            return false;
        }
        $rows=$tx->rows(
            'SELECT e.id,e.amount_cents,e.provider,e.kind,e.payment_attempt_id,'
                . 'a.provider_payment_id,a.state '
                . 'FROM %i e INNER JOIN %i a '
                . 'ON a.id = e.payment_attempt_id AND a.order_id = e.order_id '
                . 'WHERE e.id = %s AND e.order_id = %s',
            [Orders_Domain::table('pc_payment_events'),
                Orders_Domain::table('pc_payment_attempts'),$eventId,$orderId]
        );
        return 1===\count($rows)
            && $rows[0]['provider']==='mercado_pago'
            && $rows[0]['kind']==='initial_charge'
            && (string)$rows[0]['amount_cents']===$expected['amount']
            && (string)$rows[0]['payment_attempt_id']===$paymentAttemptId
            && $rows[0]['provider_payment_id']===$expected['payment_id']
            && $rows[0]['state']==='APROBADO';
    }
}
