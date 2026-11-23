<?php
/**
 * IMP-29 — worker de inbox MP, GET oficial y aplicación protegida de
 * cobro único. Corre DESPUÉS de ACK durable; el payload webhook jamás
 * constituye una acreditación ni se reutiliza como order JSON confiable.
 *
 * No reintenta POST ni reanima holds vencidos. Si falta el intento local,
 * el GET es inconsistente o el pago llegó tarde, conserva el hecho como
 * retry/final_failed para conciliación de IMP-30.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Webhook_Worker
{
    private static bool $registered=false;
    private static bool $authorizers=false;
    /** @var array<string,mixed>|null Prueba estrictamente efímera por job. */
    private static ?array $active=null;

    /** Invocado en init, sin abrir conexiones ni hacer GET. */
    public static function registerHooks(): void
    {
        if (self::$registered) { return; }
        Bootstrap::loadQueue();
        Queue_Worker::registerInboxHandler([self::class,'process']);
        self::$registered=true;
    }

    /** Autoridades privadas solo mientras un worker tiene una inbox legítima. */
    private static function registerAuthorizers(): void
    {
        if (self::$authorizers) { return; }
        Bootstrap::loadReconciliation();
        Event_Actor::registerSystemVerifier([self::class,'verifiesActor']);
        Idempotency::registerSystemScopeVerifier([self::class,'verifiesScope']);
        Idempotency::registerRecoveryAuthorizer([self::class,'verifiesRecovery']);
        MP_Credit_Application::registerSourceVerifier([self::class,'verifiesSource']);
        MP_Credit_Application::register();
        self::$authorizers=true;
    }

    /**
     * El Queue_Worker ya reclamó un lease de pc_webhook_inbox. No confiar
     * en el array job sin comprobar las mismas columnas en DB.
     *
     * @return array{result:string,error_code:?string}
     */
    public static function process(array $job): array
    {
        // WP-Cron no pasa por rest_api_init; cargar los proveedores antes
        // de consultar las readiness gates en tareas en segundo plano.
        Bootstrap::loadMp();
        if (!Module_Gates::allows('payments','webhook')) {
            return self::retry('pc_provider_unavailable');
        }
        try {
            self::registerAuthorizers();
            $lookup=self::locate($job);
            if (null===$lookup) {
                // Un order MP puede llegar ANTES del COMMIT local del
                // intento; la notificación sigue durable hasta IMP-30.
                return self::retry('pc_provider_uncertain');
            }
            $context=[
                'inbox_id'=>$job['inbox_id'],
                'account_digest'=>$job['account_digest'],
                'environment'=>$job['environment'],
                'resource_id'=>$job['resource_id'],
                'operation_id'=>$lookup['operation_id'],
                'order_id'=>$lookup['order_id'],
                'actor_ref'=>null,'actor'=>null,'recovery'=>null,
                'verified'=>null,'credit_operation_id'=>null,
            ];
            self::$active=$context;
            $recovery=Idempotency::recoveryContextForSystem(
                $lookup['operation_id'],$job['account_digest'],$job['environment']
            );
            if (($recovery['order_id']??null)!==$lookup['order_id']) {
                return self::manual();
            }
            // NO obtener estado del cuerpo webhook ni de return_url.
            $verified=MP_Orders_Client::getOrder($recovery,$job['resource_id']);
            if (($verified['provider_order_id']??null)!==$job['resource_id']
                || ($verified['amount_cents']??null)!==
                    ($recovery['checkout_context']['total_cents']??null)) {
                return self::manual();
            }
            self::$active['recovery']=$recovery;
            self::$active['verified']=$verified;
            if ('processed'!==($verified['status']??null)) {
                // Estados intermedios solo informan actividad. Rechazos,
                // cancelaciones o refunds exigen transición/revisión propia
                // de IMP-30: NO silenciar el hecho como "procesado".
                if (\in_array($verified['status']??null,
                    ['failed','canceled','refunded'],true)) {
                    return self::markCase('mp_provider_terminal');
                }
                return ['result'=>'processed','error_code'=>null];
            }
            // Solo la integración MP efectivamente habilitada por P-C
            // puede registrar dinero; el ACK del webhook es independiente.
            if (!Module_Gates::allows('payments','write')) {
                return self::retry('pc_provider_unavailable');
            }
            $payment=$verified['verified_payment']??null;
            if (!\is_array($payment)
                || !\is_string($payment['provider_payment_id']??null)
                || !\is_string($payment['amount_cents']??null)) {
                return self::markCase('mp_payment_ambiguous');
            }
            $already=self::alreadyCredited($lookup['order_id'],$verified);
            if (true===$already) {
                // ACK del worker anterior se perdió tras COMMIT exitoso.
                return ['result'=>'processed','error_code'=>null];
            }
            if (false===$already) {
                return self::markCase('mp_payment_conflict');
            }
            $actorRef=\hash_hmac('sha256',
                'pc-mp-credit-worker-v1:' . $job['account_digest'] . ':'
                    . $job['environment'] . ':' . $lookup['order_id'] . ':'
                    . $payment['provider_payment_id'],
                \wp_salt('auth')
            );
            self::$active['actor_ref']=$actorRef;
            self::$active['actor_purpose']='mp_webhook_credit';
            self::$active['scope_purpose']='mp_initial_credit';
            self::$active['recovery']=$recovery;
            self::$active['verified']=$verified;
            $actor=Event_Actor::system($actorRef,'mp_webhook_credit');
            self::$active['actor']=$actor;
            $scope=[
                'actor_kind'=>'system','actor_ref'=>$actorRef,
                'resource_kind'=>'order','resource_id'=>$lookup['order_id'],
                'purpose'=>'mp_initial_credit',
            ];
            $key=\hash('sha256','pc-initial-credit-v1:'
                . $job['account_digest'] . ':' . $job['environment']
                . ':' . $lookup['order_id'] . ':'
                . $payment['provider_payment_id']);
            $payload=[
                'provider_order_id'=>$verified['provider_order_id'],
                'provider_payment_id'=>$payment['provider_payment_id'],
                'amount_cents'=>$verified['amount_cents'],
            ];
            self::$active['credit_scope']=$scope;
            self::$active['credit_key']=$key;
            self::$active['credit_payload']=$payload;
            $outcome=Transaction::run(
                static function (Transaction_Context $tx) use (
                    $scope,$key,$payload,$actor,$recovery,$verified
                ): string {
                    $claim=Idempotency::claimWithin(
                        $tx,$scope,'mp_initial_credit',$key,$payload
                    );
                    if ('replay'===$claim['status']) {
                        return 'replayed';
                    }
                    if ('new'!==$claim['status']) {
                        throw new Transaction_Failure('pc_mp_credit_pending');
                    }
                    self::$active['credit_operation_id']=$claim['operation_id'];
                    MP_Credit_Application::applyWithin(
                        $tx,$actor,$claim['operation_id'],$recovery,$verified
                    );
                    return 'applied';
                },true
            );
            if ('replayed'===$outcome) {
                return true===self::alreadyCredited(
                    $lookup['order_id'],$verified
                ) ? ['result'=>'processed','error_code'=>null] : self::manual();
            }
            return ['result'=>'processed','error_code'=>null];
        } catch (MP_HTTP_Failure $ignored) {
            return self::retry('pc_provider_unavailable');
        } catch (Transaction_Failure $error) {
            // Dos inbox distintas pueden aplicar el MISMO pago casi a la
            // vez; si otra ganó el COMMIT, confirmar asiento/estado real
            // antes de clasificar el duplicado como incidencia.
            try {
                if (null!==self::$active
                    && \is_array(self::$active['verified']??null)
                    && true===self::alreadyCredited(
                        self::$active['order_id'],self::$active['verified']
                    )) {
                    return ['result'=>'processed','error_code'=>null];
                }
            } catch (\Throwable $ignored) {
                // Un segundo error no confirma fondos ni cancela el job.
            }
            // El pago auténtico pudo llegar tras vencer el hold. En ese
            // caso se registra el dinero como NO ASIGNADO y abre caso,
            // nunca un pedido/stock/cita confirmados ficticiamente.
            if (\in_array($error->getMessage(),[
                'pc_checkout_hold_expired','pc_inventory_hold_expired',
                'pc_checkout_admission_expired','pc_inventory_admission_expired',
                'pc_checkout_hold_missing','pc_checkout_slot_unavailable',
                'pc_digital_right_not_ready','pc_mp_credit_conflict',
            ],true) && null!==self::$active
                && \is_array(self::$active['verified']??null)) {
                return self::recordLatePayment($error->getMessage());
            }
            if (\in_array($error->getMessage(),[
                'pc_checkout_credit_unverified',
                'pc_inventory_transition_invalid',
            ],true)) {
                return self::manual();
            }
            return self::retry('pc_provider_uncertain');
        } catch (\Throwable $ignored) {
            return self::retry('pc_provider_uncertain');
        } finally {
            self::$active=null;
        }
    }

    /**
     * Evento terminal/contradictorio: caso auditable, jamás crédito o refund.
     * El GET ya fue autenticado mediante la misma cuenta e inbox firmada.
     */
    private static function markCase(string $reason): array
    {
        if (null===self::$active
            || !\is_array(self::$active['verified']??null)
            || !\is_array(self::$active['recovery']??null)
            || !\in_array($reason,[
                'mp_provider_terminal','mp_payment_ambiguous',
                'mp_payment_conflict',
            ],true)) {
            return self::manual();
        }
        try {
            $ref=\hash_hmac('sha256',
                'pc-mp-case-worker-v1:'.self::$active['account_digest'].':'
                    .self::$active['environment'].':'
                    .self::$active['operation_id'].':'.$reason,
                \wp_salt('auth')
            );
            self::$active['actor_ref']=$ref;
            self::$active['actor_purpose']='mp_reconciliation_case';
            self::$active['scope_purpose']='mp_case_open';
            $actor=Event_Actor::system($ref,'mp_reconciliation_case');
            self::$active['actor']=$actor;
            $scope=[
                'actor_kind'=>'system','actor_ref'=>$ref,
                'resource_kind'=>'order',
                'resource_id'=>self::$active['order_id'],
                'purpose'=>'mp_case_open',
            ];
            $key=\hash('sha256','pc-mp-case-v1:'
                .self::$active['operation_id'].':'
                .self::$active['verified']['provider_order_id'].':'.$reason);
            $payload=[
                'provider_order_id'=>self::$active['verified']['provider_order_id'],
                'reason'=>$reason,
            ];
            $result=Transaction::run(
                static function(Transaction_Context $tx) use(
                    $scope,$key,$payload,$actor,$reason
                ): bool {
                    $claim=Idempotency::claimWithin(
                        $tx,$scope,'mp_reconciliation_case',$key,$payload
                    );
                    if ('replay'===$claim['status']) {
                        return 'case'===$claim['entity_type'];
                    }
                    if ('new'!==$claim['status']) {
                        throw new Transaction_Failure('pc_mp_case_conflict');
                    }
                    $orderId=self::$active['order_id'];
                    $tx->lockOrder($orderId);
                    $caseId=MP_Reconciliation_Cases::openWithin(
                        $tx,$actor,$claim['operation_id'],
                        self::$active['operation_id'],$orderId,$reason,
                        self::$active['verified']['provider_order_id']
                    );
                    Idempotency::completeLocalWithin(
                        $tx,$claim['operation_id'],'case',$caseId,200
                    );
                    return true;
                },true
            );
            return $result
                ? ['result'=>'processed','error_code'=>null]
                : self::manual();
        } catch (\Throwable $ignored) {
            return self::manual();
        }
    }

    /**
     * Recuperación separada tras ROLLBACK del intento de confirmar recursos.
     * La misma clave/ámbito impide dos asientos por pago tardío duplicado.
     */
    private static function recordLatePayment(string $sourceCode): array
    {
        if (null===self::$active
            || !\is_array(self::$active['credit_scope']??null)
            || !\is_array(self::$active['credit_payload']??null)
            || !\is_string(self::$active['credit_key']??null)
            || !\is_array(self::$active['recovery']??null)
            || !\is_array(self::$active['verified']??null)
            || !(self::$active['actor'] instanceof Event_Actor)) {
            return self::manual();
        }
        $reason=match($sourceCode) {
            'pc_checkout_hold_expired','pc_inventory_hold_expired',
            'pc_checkout_admission_expired','pc_inventory_admission_expired'
                => 'mp_hold_expired',
            'pc_checkout_slot_unavailable' => 'mp_slot_unavailable',
            default => 'mp_resources_missing',
        };
        try {
            $outcome=Transaction::run(
                static function(Transaction_Context $tx) use($reason): string {
                    $claim=Idempotency::claimWithin(
                        $tx,self::$active['credit_scope'],
                        'mp_initial_credit',self::$active['credit_key'],
                        self::$active['credit_payload']
                    );
                    if ('replay'===$claim['status']) { return 'replay'; }
                    if ('new'!==$claim['status']) {
                        throw new Transaction_Failure('pc_mp_credit_pending');
                    }
                    self::$active['credit_operation_id']=$claim['operation_id'];
                    MP_Credit_Application::recordUnallocatedWithin(
                        $tx,self::$active['actor'],$claim['operation_id'],
                        self::$active['recovery'],self::$active['verified'],
                        $reason
                    );
                    return 'recorded';
                },true
            );
            if ('recorded'===$outcome
                || 'replay'===$outcome
                    && true===self::alreadyCredited(
                        self::$active['order_id'],self::$active['verified']
                    )) {
                return ['result'=>'processed','error_code'=>null];
            }
        } catch (\Throwable $ignored) {
            // COMMIT incierto se reanaliza por ledger/caso; nunca segundo
            // pago ni afirmación "no cobrado".
            try {
                if (true===self::alreadyCredited(
                    self::$active['order_id'],self::$active['verified']
                )) {
                    return ['result'=>'processed','error_code'=>null];
                }
            } catch (\Throwable $ignoredAgain) {
            }
        }
        return self::manual();
    }

    /**
     * Exige correlación local: order_id oficial + ambiente/cuenta + raíz
     * idempotente ORIGINAL de la misma operación checkout_mp.
     */
    private static function locate(array $job): ?array
    {
        if (($job['provider']??null)!=='mercado_pago'
            || !\is_string($job['inbox_id']??null)
            || !\is_string($job['account_digest']??null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$job['account_digest'])
            || !\in_array(($job['environment']??null),['sandbox','production'],true)
            || !\is_string($job['resource_id']??null)
            || !\preg_match('/\AORD[A-Z0-9]{16,61}\z/D',$job['resource_id'])
            || !\is_string($job['action_code']??null)
            || !\preg_match('/\Aorder\.[a-z][a-z0-9_]{0,57}\z/D',
                $job['action_code'])
            || !\is_string($job['payload_digest']??null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$job['payload_digest'])) {
            throw new Transaction_Failure('pc_webhook_job_invalid');
        }
        $id=Domain_Validation::sqlId($job['inbox_id']);
        return Transaction::run(static function (Transaction_Context $tx) use ($job,$id): ?array {
            $inbox=$tx->rows(
                'SELECT technical_phase,provider,'
                    . 'LOWER(HEX(provider_account_key)) AS account_digest,'
                    . 'environment,resource_id,action_code,'
                    . 'LOWER(HEX(payload_digest)) AS payload_digest,'
                    . 'signature_verified_at FROM %i WHERE id = %s',
                [Queue_Inbox::table(),$id]
            );
            if (1!==\count($inbox)
                || 'processing'!==$inbox[0]['technical_phase']
                || 'mercado_pago'!==$inbox[0]['provider']
                || null===$inbox[0]['signature_verified_at']
                || $inbox[0]['account_digest']!==$job['account_digest']
                || $inbox[0]['environment']!==$job['environment']
                || $inbox[0]['resource_id']!==$job['resource_id']
                || $inbox[0]['action_code']!==$job['action_code']
                || $inbox[0]['payload_digest']!==$job['payload_digest']) {
                throw new Transaction_Failure('pc_webhook_job_invalid');
            }
            $rows=$tx->rows(
                'SELECT a.order_id,i.id AS operation_id '
                    . 'FROM %i a INNER JOIN %i i '
                    . 'ON i.operation_type = %s '
                    . 'AND i.provider_account_key = a.provider_account_key '
                    . 'AND i.provider_environment = a.environment '
                    . 'AND i.provider_idempotency_key = a.mp_idempotency_key '
                    . 'AND i.entity_type = %s AND i.entity_id = a.order_id '
                    . 'WHERE a.provider = %s AND a.provider_account_key = UNHEX(%s) '
                    . 'AND a.environment = %s AND a.provider_order_id = %s LIMIT 2',
                [
                    Orders_Domain::table('pc_payment_attempts'),
                    Orders_Domain::table('pc_idempotency'),
                    'checkout_mp','order','mercado_pago',
                    $job['account_digest'],$job['environment'],$job['resource_id'],
                ]
            );
            if ([]===$rows) {
                return null;
            }
            if (1!==\count($rows)) {
                throw new Transaction_Failure('pc_webhook_job_invalid');
            }
            return [
                'operation_id'=>Domain_Validation::sqlId(
                    (string)$rows[0]['operation_id']),
                'order_id'=>Domain_Validation::sqlId(
                    (string)$rows[0]['order_id']),
            ];
        });
    }

    /**
     * null = aún no acreditado; true = mismo pago confirmado (idempotente);
     * false = otro pago o evento incoherente, exige revisión administrativa.
     */
    private static function alreadyCredited(
        string $orderId,array $verified
    ): ?bool {
        return Transaction::run(static function(Transaction_Context $tx) use (
            $orderId,$verified
        ): ?bool {
            $rows=$tx->rows(
                'SELECT e.id,e.amount_cents,e.external_reference,e.provider,'
                    . 'a.provider_payment_id,a.state,o.status '
                    . 'FROM %i e INNER JOIN %i a '
                    . 'ON a.id = e.payment_attempt_id AND a.order_id = e.order_id '
                    . 'INNER JOIN %i o ON o.id = e.order_id '
                    . 'WHERE e.order_id = %s AND e.kind = %s LIMIT 2',
                [
                    Orders_Domain::table('pc_payment_events'),
                    Orders_Domain::table('pc_payment_attempts'),
                    Orders_Domain::table('pc_orders'),$orderId,'initial_charge',
                ]
            );
            if ([]===$rows) { return null; }
            if (1!==\count($rows)) { return false; }
            $paymentId=$verified['verified_payment']['provider_payment_id'];
            $matches='mercado_pago'===$rows[0]['provider']
                && $rows[0]['external_reference']===$paymentId
                && $rows[0]['provider_payment_id']===$paymentId
                && (string)$rows[0]['amount_cents']===$verified['amount_cents']
                && $rows[0]['state']==='APROBADO';
            if (!$matches) { return false; }
            if (\in_array($rows[0]['status'],['CONFIRMADO','FINALIZADO'],true)) {
                return true;
            }
            if (!\in_array($rows[0]['status'],['PENDIENTE_PAGO','VENCIDO'],true)) {
                return false;
            }
            $cases=$tx->rows(
                'SELECT id FROM %i WHERE case_type=%s AND order_id=%s '
                    . 'AND case_phase=%s AND source_operation_id IS NOT NULL LIMIT 2',
                [Orders_Domain::table('pc_cases'),
                    MP_Reconciliation_Cases::KIND,$orderId,
                    MP_Reconciliation_Cases::OPEN]
            );
            return 1===\count($cases);
        });
    }

    public static function verifiesRecovery(
        string $operationId,string $accountDigest,string $environment
    ): bool {
        return null!==self::$active
            && self::$active['operation_id']===$operationId
            && self::$active['account_digest']===$accountDigest
            && self::$active['environment']===$environment;
    }

    public static function verifiesActor(string $ref,string $purpose): bool
    {
        return null!==self::$active
            && \in_array($purpose,
                ['mp_webhook_credit','mp_reconciliation_case'],true)
            && (self::$active['actor_purpose']??null)===$purpose
            && \is_string(self::$active['actor_ref'])
            && \hash_equals(self::$active['actor_ref'],$ref);
    }

    public static function verifiesScope(array $scope): bool
    {
        return null!==self::$active
            && ($scope['actor_kind']??null)==='system'
            && ($scope['resource_kind']??null)==='order'
            && \in_array(($scope['purpose']??null),
                ['mp_initial_credit','mp_case_open'],true)
            && ($scope['purpose']??null)===(self::$active['scope_purpose']??null)
            && ($scope['resource_id']??null)===self::$active['order_id']
            && ($scope['actor_ref']??null)===self::$active['actor_ref'];
    }

    /** Validación final en memoria del GET asociado a la inbox firmada. */
    public static function verifiesSource(
        Event_Actor $actor,string $operationId,
        array $recovery,array $verified
    ): bool {
        return null!==self::$active
            && self::$active['actor']===$actor
            && self::$active['order_id']===($recovery['order_id']??null)
            && self::$active['operation_id']===($recovery['operation_id']??null)
            && \is_array(self::$active['recovery'])
            && self::$active['recovery']===$recovery
            && \is_array(self::$active['verified'])
            && self::$active['verified']===$verified
            && self::$active['credit_operation_id']===$operationId
            && $operationId!==self::$active['operation_id'];
    }

    private static function manual(): array
    {
        return ['result'=>'final_failed','error_code'=>'pc_manual_review_required'];
    }

    private static function retry(string $code): array
    {
        return ['result'=>'retry','error_code'=>$code];
    }
}
