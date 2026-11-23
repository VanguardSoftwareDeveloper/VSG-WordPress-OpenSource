<?php
/**
 * IMP-25 — coordinador interno único de checkout COD/MP.
 *
 * Sin endpoints, sin llamadas HTTP y sin envío SMTP. R8-07 (IMP-26) y R8-06
 * (IMP-28) proveerán identidad validada, credenciales/pago y presentación.
 * Las operaciones retornan de Transaction::run sólo tras COMMIT inequívoco.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Checkout_Coordinator
{
    /** @var \WeakMap<Transaction_Context,array<string,mixed>>|null */
    private static ?\WeakMap $prepared = null;

    /** @var callable|null Verificador de evento financiero MP oficial. */
    private static $paidVerifier = null;

    /** Registrar SOLO un verificador de pedido basado en contexto transaccional. */
    public static function register(): void
    {
        if (null !== self::$prepared) {
            return;
        }
        self::$prepared = new \WeakMap();
        Orders_Persistence::registerPreparedVerifier(
            static function (
                Transaction_Context $tx,Event_Actor $actor,
                string $operationId,array $verified
            ): bool {
                if (self::$prepared === null || !isset(self::$prepared[$tx])) {
                    return false;
                }
                $expected = self::$prepared[$tx];
                return $expected['actor'] === $actor
                    && $expected['operation_id'] === $operationId
                    && $expected['order'] === $verified;
            }
        );
    }

    /**
     * Reclamación idempotente -> recotización -> pedido/locks -> segunda
     * recotización -> ocupación de recursos -> respuesta segura.
     *
     * Para MP, $provider incluye solo cuenta/ambiente de servidor; el
     * contexto exacto se deriva de la SEGUNDA quote bajo locks. Si no está,
     * se aborta el TX: NUNCA se persisten admisiones sin vínculo pre-HTTP.
     *
     * @param array<string,string> $scope Form HMAC/cookie legitimada
     * @param array<string,mixed> $body CheckoutRequest OpenAPI
     * @param array<string,mixed>|null $provider Datos propios del servidor MP
     */
    public static function prepareWithin(
        Transaction_Context $tx, Event_Actor $actor, array $scope,
        array $body, string $method, array $capabilities,
        ?array $provider = null
    ): array {
        self::register();
        $actor->assertCurrent();
        if (!\in_array($method,['cash','mercado_pago'],true)
            || 'form' !== $actor->kind()
            || !Module_Gates::allows('checkout','write')
            || !Module_Gates::allows('orders','write')) {
            throw new Transaction_Failure('pc_checkout_not_ready');
        }
        $operationType = 'cash' === $method ? 'checkout_cash' : 'checkout_mp';
        if (($scope['actor_kind'] ?? null) !== 'form'
            || ($scope['resource_kind'] ?? null) !== 'checkout'
            || ($scope['resource_id'] ?? null) !== '0'
            || ($scope['purpose'] ?? null) !== $operationType
            || !\is_string($scope['actor_ref'] ?? null)
            || !$actor->matchesCheckoutScope($scope['actor_ref'],$operationType)) {
            throw new Transaction_Failure('pc_checkout_scope_invalid');
        }
        self::validateRequest($body);
        // $capabilities debe venir de Quote_Service::capabilities() ANTES
        // de Transaction::run; nunca consultar readiness/REST durante SQL.
        if (!\is_array($capabilities['payment_methods'] ?? null)
            || !\in_array($method,$capabilities['payment_methods'],true)) {
            throw new Transaction_Failure('pc_checkout_not_ready');
        }
        $claim = Idempotency::claimWithin(
            $tx,$scope,$operationType,$body['idempotency_key'],$body
        );
        if ('new' !== $claim['status']) {
            // 'pending', 'replay' y 'reconcile' NO ejecutan efectos nuevos.
            return ['status'=>$claim['status'],'claim'=>$claim];
        }
        $operationId = $claim['operation_id'];
        Event_Journal::requirePendingOperation($tx,$operationId);
        $request = Quote_Service::normalize([
            'lines'=>$body['lines'],
            'delivery'=>$body['delivery'] ?? ['mode'=>'none'],
            'contact'=>$body['contact'],
        ]);
        $first = Quote_Service::quoteWithin($tx,$request,$capabilities,true);
        self::assertConfirmedQuote($first['quote'],$body);
        self::eligibleMethod($method,$first['order']);
        $order = $first['order'];
        $order['payment_method'] = $method;
        $order['status'] = 'cash' === $method ? 'CONFIRMADO' : 'PENDIENTE_PAGO';

        self::$prepared[$tx] = [
            'actor'=>$actor,'operation_id'=>$operationId,'order'=>$order,
        ];
        try {
            $created = Orders_Persistence::insertWithin(
                $tx,$actor,$operationId,$order
            );
        } finally {
            unset(self::$prepared[$tx]);
        }
        $orderId = $created['id'];
        $lines = self::lockedLinesWithin($tx,$orderId);
        self::matchLines($lines,$order['lines']);
        $service = $first['service'];
        $interval = null;
        if (null !== $service) {
            $interval = Checkout_Appointments::lockSlotWithin($tx,$service);
        }
        // Bajo anclas exclusivas: precio, versiones, stock, slot, políticas,
        // digital y tarifa tienen que coincidir con el click del comprador.
        $second = Quote_Service::quoteWithin($tx,$request,$capabilities,true);
        self::assertConfirmedQuote($second['quote'],$body);
        if ($first['order'] !== $second['order']
            || $service !== $second['service']) {
            throw new Transaction_Failure('pc_checkout_quote_changed');
        }
        foreach ($order['lines'] as $entry) {
            if ('physical' === $entry['catalog_snapshot']['kind']) {
                Inventory_Balance::demandWithin(
                    $tx,$entry['sellable_id'],$entry['quantity']
                );
            }
        }

        $admissions = [];
        if ('cash' === $method) {
            foreach ($order['lines'] as $entry) {
                $sellableId = $entry['sellable_id'];
                if ('physical' === $entry['catalog_snapshot']['kind']) {
                    Inventory_Reservations::commitCashWithin(
                        $tx,$actor,$operationId,$orderId,
                        $lines[$sellableId]['id'],$sellableId
                    );
                }
            }
            if (null !== $service) {
                Checkout_Appointments::commitCashWithin(
                    $tx,$actor,$operationId,$orderId,
                    $lines[$service['sellable_id']]['id'],$service,$interval
                );
            }
            Event_Journal::auditWithin(
                $tx,$actor,$operationId,'order',$orderId,
                'checkout_cash_confirmed','payment_pending'
            );
            Queue_Outbox::essentialWithin(
                $tx,$actor,$operationId,$orderId,
                'checkout_cash_confirmed',$orderId
            );
            Queue_Outbox::serviceConfirmedWithin(
                $tx,$actor,$operationId,$orderId,'checkout_cash_confirmed'
            );
            $tracking = Checkout_Tracking::issueCashWithin(
                $tx,$actor,$operationId,$orderId
            );
            Idempotency::completeLocalWithin(
                $tx,$operationId,'order',$orderId,201
            );
            return [
                'status'=>'new','operation_id'=>$operationId,
                'order_id'=>$orderId,'order_number'=>$created['order_number'],
                'amount_due_cents'=>$second['quote']['total_cents'],
                'buyer_email'=>$second['order']['buyer_email'],
                'tracking'=>$tracking,
            ];
        }

        foreach ($order['lines'] as $entry) {
            $sellableId = $entry['sellable_id'];
            if ('physical' === $entry['catalog_snapshot']['kind']) {
                $admissions[$sellableId] = Inventory_Reservations::admitWithin(
                    $tx,$actor,$operationId,$sellableId,$entry['quantity']
                );
            }
        }
        if (null !== $service) {
            $admissions['service'] = Checkout_Appointments::admitWithin(
                $tx,$actor,$operationId,$orderId,
                $lines[$service['sellable_id']]['id'],$service,$interval
            );
        }
        if (!\is_array($provider)
            || !\is_string($provider['account_digest_hex'] ?? null)
            || !\is_string($provider['environment'] ?? null)) {
            throw new Transaction_Failure('pc_checkout_mp_not_ready');
        }
        // El adaptador trabaja sobre la cotización recálculada con anclas
        // exclusivas, nunca sobre un JSON de navegador o quote anterior.
        $frozen = MP_Orders_Client::frozenContext($second);
        self::assertProviderMatchesQuote($frozen,$second['quote']);
        // Vincular cuenta, clave MP, external_reference y body exacto
        // ANTES de COMMIT/HTTP; un segundo POST recupera/conciliará, no rota.
        $binding = Idempotency::bindProviderWithin(
            $tx,$operationId,$provider['account_digest_hex'],
            $provider['environment'],$frozen,$orderId
        );
        return [
            'status'=>'new','operation_id'=>$operationId,
            'order_id'=>$orderId,'order_number'=>$created['order_number'],
            'admissions'=>$admissions,'provider_binding'=>$binding,
            'tracking'=>['tracking_link_issued'=>false,'tracking_url_once'=>null],
        ];
    }

    /**
     * Segunda transacción MP DESPUÉS de consultar provider Orders fuera de SQL.
     * El consumidor crea un intento verificado; sin intento y admit 60s vigente
     * no hay hold ni URL. Un timeout/COMMIT ambiguo se concilia, no reintenta.
     *
     * No acepta listas de admisiones/hold del navegador.
     */
    public static function promoteMpWithin(
        Transaction_Context $tx, Event_Actor $actor, string $operationId,
        string $orderId, array $recovery, array $verified
    ): array {
        $actor->assertCurrent();
        $operationId = Domain_Validation::sqlId($operationId);
        $orderId = Domain_Validation::sqlId($orderId);
        $tx->lockedOperation($operationId);
        $tx->lockOrder($orderId);
        // Registrar/recuperar el intento en el mismo root antes de
        // líneas, vendibles y agenda; sólo un GET/POST MP validado fuera SQL.
        $paymentAttemptId = MP_Payment_Attempts::recordCreatedWithin(
            $tx,$operationId,$orderId,$recovery,$verified
        );
        Checkout_Appointments::verifiedAttemptWithin(
            $tx,$operationId,$orderId,$paymentAttemptId
        );
        $lines = self::lockPersistedLinesWithin($tx,$orderId);
        $serviceLine = null;
        foreach ($lines as $line) {
            if ('service' === $line['kind']) {
                if (null !== $serviceLine) {
                    throw new Transaction_Failure('pc_checkout_service_invalid');
                }
                $serviceLine = $line;
            }
        }
        $service = null;
        $interval = null;
        if (null !== $serviceLine) {
            $service = Checkout_Appointments::fromOrderLineWithin(
                $tx,$orderId,$serviceLine['id']
            );
            $interval = Checkout_Appointments::lockSlotWithin($tx,$service);
        }
        $holds = [];
        foreach ($lines as $line) {
            if ('physical' !== $line['kind']) {
                continue;
            }
            $rows = $tx->rows(
                'SELECT id FROM %i WHERE idempotency_id = %s '
                    . 'AND sellable_id = %s FOR UPDATE',
                [Inventory_Balance::table('pc_resource_admissions'),
                    $operationId,$line['sellable_id']]
            );
            if (1 !== \count($rows)) {
                throw new Transaction_Failure('pc_checkout_admission_missing');
            }
            $holds[$line['sellable_id']] =
                Inventory_Reservations::admissionToHoldWithin(
                    $tx,$operationId,(string) $rows[0]['id'],$paymentAttemptId,
                    $orderId,$line['id'],$line['sellable_id']
                );
        }
        if (null !== $service) {
            $holds['service'] = Checkout_Appointments::admissionToHoldWithin(
                $tx,$operationId,$orderId,$serviceLine['id'],
                $paymentAttemptId,$service,$interval
            );
        }
        $tracking = Checkout_Tracking::issueMpWithin(
            $tx,$actor,$operationId,$orderId,$paymentAttemptId
        );
        return [
            'order_id'=>$orderId,'payment_attempt_id'=>$paymentAttemptId,
            'holds'=>$holds,'tracking'=>$tracking,
        ];
    }

    /**
     * Confirmación de RECURSOS tras pago MP acreditado. Un consumidor de
     * IMP-28/30 registra primero ledger/estado E-01 y reclama una NUEVA
     * operación idempotente prepared; este método no acredita dinero.
     * El consumidor finaliza idempotencia después de derechos/outbox/auditoría.
     */
    public static function confirmPaidResourcesWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $orderId,string $paymentAttemptId,string $verifiedCreditEventId
    ): array {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        $orderId = Domain_Validation::sqlId($orderId);
        $paymentAttemptId = Domain_Validation::sqlId($paymentAttemptId);
        $creditEvent = Domain_Validation::sqlId($verifiedCreditEventId);
        if (null === self::$paidVerifier) {
            throw new Transaction_Failure('pc_checkout_credit_unverified');
        }
        $order = $tx->lockOrder($orderId);
        if ('mercado_pago' !== (string) $order['payment_method']
            || 'CONFIRMADO' !== (string) $order['status']) {
            throw new Transaction_Failure('pc_checkout_credit_unverified');
        }
        $lines = self::lockPersistedLinesWithin($tx,$orderId);
        try {
            if (true !== (self::$paidVerifier)(
                $tx,$actor,$operationId,$orderId,$creditEvent,$paymentAttemptId
            )) {
                throw new Transaction_Failure('pc_checkout_credit_unverified');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_checkout_credit_unverified');
        }
        $serviceLine = null;
        foreach ($lines as $line) {
            if ('service' === $line['kind']) {
                $serviceLine = $line;
            }
        }
        $service = null;
        $interval = null;
        if (null !== $serviceLine) {
            $service = Checkout_Appointments::fromOrderLineWithin(
                $tx,$orderId,$serviceLine['id']
            );
            $interval = Checkout_Appointments::lockSlotWithin($tx,$service);
        }
        $committed = [];
        foreach ($lines as $line) {
            if ('physical' !== $line['kind']) {
                continue;
            }
            $hold = self::holdWithin(
                $tx,$orderId,$line['id'],$paymentAttemptId,'stock'
            );
            $committed[$line['sellable_id']] =
                Inventory_Reservations::commitHoldWithin(
                    $tx,$actor,$operationId,$hold,$orderId,
                    $line['id'],$line['sellable_id']
                );
        }
        if (null !== $service) {
            $hold = self::holdWithin(
                $tx,$orderId,$serviceLine['id'],$paymentAttemptId,'slot'
            );
            $committed['service'] = Checkout_Appointments::commitPaidWithin(
                $tx,$actor,$operationId,$orderId,$serviceLine['id'],
                $hold,$creditEvent,$service,$interval
            );
        }
        return ['order_id'=>$orderId,'commitments'=>$committed];
    }

    public static function registerPaidVerifier(callable $verifier): void
    {
        if (null !== self::$paidVerifier) {
            throw new \LogicException('pc_checkout_paid_verifier_duplicate');
        }
        Checkout_Appointments::registerFundsVerifier($verifier);
        self::$paidVerifier = $verifier;
    }

    /** El adaptador no puede cobrar otro total/catálogo con el mismo opId. */
    private static function assertProviderMatchesQuote(array $frozen,array $quote): void
    {
        if (($frozen['currency'] ?? null) !== 'PEN'
            || ($frozen['total_cents'] ?? null) !== $quote['total_cents']
            || ($frozen['delivery_cents'] ?? null) !== $quote['delivery_cents']
            || ($frozen['quote_fingerprint'] ?? null) !== $quote['quote_fingerprint']
            || ($frozen['policy_version'] ?? null) !== $quote['policy_version']
            || ($frozen['privacy_version'] ?? null) !== $quote['privacy_version']
            || !\is_array($frozen['lines'] ?? null)
            || \count($frozen['lines']) !== \count($quote['lines'])) {
            throw new Transaction_Failure('pc_checkout_mp_context_invalid');
        }
        foreach ($quote['lines'] as $index => $line) {
            $f = $frozen['lines'][$index] ?? null;
            if (!\is_array($f)
                || ($f['sellable_id'] ?? null) !== $line['sellable_id']
                || ($f['quantity'] ?? null) !== $line['quantity']
                || ($f['unit_price_cents'] ?? null) !== $line['unit_price_cents']) {
                throw new Transaction_Failure('pc_checkout_mp_context_invalid');
            }
        }
    }

    private static function validateRequest(array $body): void
    {
        $allowed = [
            'quote_fingerprint','lines','delivery','contact','idempotency_key',
            'policy_version','privacy_version',
        ];
        if ([] !== \array_diff(\array_keys($body),$allowed)
            || !\is_string($body['quote_fingerprint'] ?? null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$body['quote_fingerprint'])
            || !\is_array($body['lines'] ?? null)
            || !\is_array($body['contact'] ?? null)
            || !\is_string($body['idempotency_key'] ?? null)
            || !\is_string($body['policy_version'] ?? null)
            || !\is_string($body['privacy_version'] ?? null)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'checkout');
        }
        Domain_Validation::sqlId($body['policy_version']);
        Domain_Validation::sqlId($body['privacy_version']);
    }

    private static function assertConfirmedQuote(array $quote,array $body): void
    {
        if (!\hash_equals($quote['quote_fingerprint'],$body['quote_fingerprint'])
            || $quote['policy_version'] !== $body['policy_version']
            || $quote['privacy_version'] !== $body['privacy_version']
            || true !== $quote['requires_confirmation']) {
            throw new Transaction_Failure('pc_checkout_quote_changed');
        }
    }

    private static function eligibleMethod(string $method,array $order): void
    {
        $digital = false;
        $service = false;
        foreach ($order['lines'] as $line) {
            $kind = $line['catalog_snapshot']['kind'];
            $digital = $digital || 'digital' === $kind;
            $service = $service || 'service' === $kind;
        }
        if ('cash' === $method
            && ($digital || ($service && 'delivery' === $order['delivery']['mode']))) {
            throw new Transaction_Failure('pc_checkout_method_ineligible');
        }
    }

    private static function lockedLinesWithin(Transaction_Context $tx,string $orderId): array
    {
        $rows = $tx->rows(
            'SELECT id,sellable_id,kind,quantity FROM %i WHERE order_id = %s '
                . 'ORDER BY id ASC LIMIT 101',
            [Orders_Domain::table('pc_order_lines'),$orderId]
        );
        if ([] === $rows || \count($rows) > 100) {
            throw new Transaction_Failure('pc_checkout_order_invalid');
        }
        $out = [];
        foreach ($rows as $line) {
            $id = Domain_Validation::sqlId((string) $line['id']);
            $locked = $tx->lockedOrderLine($id);
            if ((string) $locked['order_id'] !== $orderId
                || (string) $locked['sellable_id'] !== (string) $line['sellable_id']
                || isset($out[$line['sellable_id']])) {
                throw new Transaction_Failure('pc_checkout_order_invalid');
            }
            $out[$line['sellable_id']] = $line;
        }
        return $out;
    }

    private static function matchLines(array $persisted,array $verified): void
    {
        if (\count($persisted) !== \count($verified)) {
            throw new Transaction_Failure('pc_checkout_order_invalid');
        }
        foreach ($verified as $entry) {
            $line = $persisted[$entry['sellable_id']] ?? null;
            if (!\is_array($line)
                || (int) $line['quantity'] !== $entry['quantity']
                || $line['kind'] !== $entry['catalog_snapshot']['kind']) {
                throw new Transaction_Failure('pc_checkout_order_invalid');
            }
        }
    }

    /** Reconstruye orden de locks E-05 en operaciones posteriores. */
    private static function lockPersistedLinesWithin(
        Transaction_Context $tx,string $orderId
    ): array {
        $rows = $tx->rows(
            'SELECT id,sellable_id,kind,quantity FROM %i WHERE order_id = %s '
                . 'ORDER BY id ASC LIMIT 101',
            [Orders_Domain::table('pc_order_lines'),$orderId]
        );
        if ([] === $rows || \count($rows) > 100) {
            throw new Transaction_Failure('pc_checkout_order_invalid');
        }
        foreach ($rows as $row) {
            $tx->lockOrderLine($orderId,(string) $row['id']);
        }
        $tx->lockSellables(\array_map(
            static fn (array $row): string => (string) $row['sellable_id'],
            $rows
        ));
        return $rows;
    }

    private static function holdWithin(
        Transaction_Context $tx,string $orderId,string $lineId,
        string $paymentAttemptId,string $kind
    ): string {
        $rows = $tx->rows(
            'SELECT id FROM %i WHERE order_id = %s AND order_line_id = %s '
                . 'AND payment_attempt_id = %s AND resource_kind = %s '
                . 'AND state = %s AND expires_at > UTC_TIMESTAMP(6)',
            [Agenda_Repository::table('pc_holds'),$orderId,$lineId,
                $paymentAttemptId,$kind,'VIGENTE']
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_checkout_hold_missing');
        }
        return Domain_Validation::sqlId((string) $rows[0]['id']);
    }
}
