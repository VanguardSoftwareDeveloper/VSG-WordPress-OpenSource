<?php
/**
 * IMP-27 — adaptador exclusivamente Checkout Pro Orders API (Perú).
 *
 * POST y GET solo via WordPress HTTP fuera de cualquier transacción.
 * La creación recibe una solicitud inmutable pre-HTTP de Idempotency.
 * No acredita dinero, no cambia estados E-01, no ejecuta refunds ni
 * confirma pedidos por la URL retornada al navegador.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_HTTP_Failure extends \RuntimeException
{
    public function __construct(
        string $code,
        public readonly bool $outcomeUncertain
    ) { parent::__construct($code); }
}

final class MP_Orders_Client
{
    private const URL = 'https://api.mercadopago.com/v1/orders';
    private const MAX_RESPONSE_BYTES = 131072;

    /**
     * Construcción pura desde Quote_Service::quoteWithin(...,true).
     * El contexto se cifra en pc_idempotency por IMP-08 ANTES del HTTP.
     * Ningún campo del cliente es autoridad de precio/tarifa.
     */
    public static function frozenContext(array $verified): array
    {
        $quote = $verified['quote'] ?? null;
        $order = $verified['order'] ?? null;
        if (!\is_array($quote) || !\is_array($order)
            || 'PEN' !== ($quote['currency'] ?? null)
            || !\is_array($quote['lines'] ?? null)
            || !\array_is_list($quote['lines'])
            || !\is_array($order['lines'] ?? null)
            || !\array_is_list($order['lines'])
            || \count($quote['lines']) !== \count($order['lines'])
            || [] === $quote['lines'] || \count($quote['lines'])>100) {
            throw new Transaction_Failure('pc_mp_context_invalid');
        }
        $items = [];
        $lines = [];
        $subtotals = [];
        foreach ($quote['lines'] as $i=>$line) {
            $original = $order['lines'][$i] ?? null;
            if (!\is_array($line) || !\is_array($original)
                || !\is_string($line['sellable_id'] ?? null)
                || !\is_int($line['quantity'] ?? null)
                || !\is_string($line['unit_price_cents'] ?? null)
                || !\is_string($line['title'] ?? null)
                || !\mb_check_encoding($line['title'],'UTF-8')
                || '' === \trim($line['title'])
                || \mb_strlen($line['title'],'UTF-8')>200
                || ($original['sellable_id'] ?? null) !== $line['sellable_id']
                || ($original['quantity'] ?? null) !== $line['quantity']
                || ($original['unit_price_cents'] ?? null) !== $line['unit_price_cents']) {
                throw new Transaction_Failure('pc_mp_context_invalid');
            }
            $id = Domain_Validation::sqlId($line['sellable_id']);
            $unit = Money::fromApi($line['unit_price_cents']);
            if ($unit<1 || $line['quantity']<1) {
                throw new Transaction_Failure('pc_mp_context_invalid');
            }
            $lineTotal = Money::lineSubtotal($unit,$line['quantity']);
            if (($line['subtotal_cents'] ?? null) !== Money::toApi($lineTotal)) {
                throw new Transaction_Failure('pc_mp_context_invalid');
            }
            $subtotals[] = $lineTotal;
            $items[] = [
                'title'=>$line['title'],
                'quantity'=>$line['quantity'],
                'unit_price'=>Money::toProviderDecimal($unit),
            ];
            $lines[] = [
                'sellable_id'=>$id,'quantity'=>$line['quantity'],
                'unit_price_cents'=>Money::toApi($unit),
            ];
        }
        $delivery = $quote['delivery_cents'] ?? null;
        $total = $quote['total_cents'] ?? null;
        if (!\is_string($delivery) || !\is_string($total)
            || !\is_string($quote['quote_fingerprint'] ?? null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$quote['quote_fingerprint'])
            || !\is_string($quote['policy_version'] ?? null)
            || !\is_string($quote['privacy_version'] ?? null)) {
            throw new Transaction_Failure('pc_mp_context_invalid');
        }
        $deliveryCents = Money::fromApi($delivery);
        $totalCents = Money::fromApi($total);
        if (Money::orderTotal($subtotals,$deliveryCents)!==$totalCents
            || $quote['subtotal_cents'] !==
                Money::toApi(Money::orderTotal($subtotals,0))) {
            throw new Transaction_Failure('pc_mp_context_invalid');
        }
        if ($deliveryCents>0) {
            $items[] = [
                'title'=>'Entrega','quantity'=>1,
                'unit_price'=>Money::toProviderDecimal($deliveryCents),
            ];
        }
        $provider = [
            'type'=>'online','processing_mode'=>'manual',
            'total_amount'=>Money::toProviderDecimal($totalCents),
            'expiration_time'=>'PT15M',
            'items'=>$items,
        ];
        $email = $order['buyer_email'] ?? null;
        if (null !== $email) {
            if (!\is_string($email) || !\is_email($email)) {
                throw new Transaction_Failure('pc_mp_context_invalid');
            }
            $provider['payer'] = ['email'=>$email];
        }
        // Físico sin correo: NO incluir payer, sin email inventado. Esta
        // modalidad y PT15M requieren validación real en cuenta Perú P-C.
        return [
            'currency'=>'PEN',
            'total_cents'=>$total,
            'delivery_cents'=>$delivery,
            'quote_fingerprint'=>$quote['quote_fingerprint'],
            'policy_version'=>$quote['policy_version'],
            'privacy_version'=>$quote['privacy_version'],
            'lines'=>$lines,
            'provider_request'=>$provider,
        ];
    }

    /**
     * POST una sola vez: marca la raíz RECONCILE en un COMMIT previo al
     * envío. Si falla TCP/servidor/COMMIT no hay POST ciego posterior:
     * buscar external_reference o GET de ID comprobado.
     *
     * @param array<string,mixed> $recovery Resultado interno de Idempotency::recoveryContext
     */
    public static function createPrepared(array $recovery): array
    {
        Transaction::assertOutside();
        [$credential,$context,$opId] = self::acceptRecovery($recovery);
        // Validar el cuerpo exacto ANTES de marcar la fase irreversible.
        $body = self::encode($context['provider_request']);
        // Cuando la rotación de claves deja el token anterior disponible,
        // conservar exactamente ese token para esta operación ya vinculada.
        $phase = Transaction::run(
            static function (Transaction_Context $tx) use ($opId,$credential): string {
                $root = $tx->lockOperation($opId);
                if ('external_pending' !== ($root['technical_phase'] ?? null)) {
                    throw new MP_HTTP_Failure('pc_mp_create_already_attempted',true);
                }
                $rows = $tx->rows(
                    'SELECT operation_type,HEX(provider_account_key) AS digest,'
                        . 'provider_environment FROM %i WHERE id = %s',
                    [Orders_Domain::table('pc_idempotency'),$opId]
                );
                if (1 !== \count($rows)
                    || 'checkout_mp' !== $rows[0]['operation_type']
                    || !\hash_equals($credential['account_digest_hex'],
                        \strtolower((string)$rows[0]['digest']))
                    || $credential['environment'] !== $rows[0]['provider_environment']) {
                    throw new MP_HTTP_Failure('pc_mp_create_context_invalid',true);
                }
                Idempotency::requireReconciliationWithin($tx,$opId);
                return 'reconcile';
            }
        );
        if ('reconcile' !== $phase) {
            throw new MP_HTTP_Failure('pc_mp_create_context_invalid',true);
        }
        $raw = self::send(
            'POST',self::URL,$credential['access_token'],$body,
            $recovery['provider_idempotency_key']
        );
        if ($raw['status'] !== 201) {
            // Aun 400/409/423 pueden referirse a una orden existente.
            // Nunca rotar X-Idempotency-Key ni crear otra order por error.
            throw new MP_HTTP_Failure('pc_mp_create_reconcile_required',true);
        }
        $verified = self::validatedOrder($raw['json'],$context,true);
        if (null === $verified['checkout_url']) {
            throw new MP_HTTP_Failure('pc_mp_response_unverified',true);
        }
        return $verified;
    }

    /** GET oficial por ID de order recuperado de fuente persistida. */
    public static function getOrder(array $recovery,string $orderId): array
    {
        Transaction::assertOutside();
        [$credential,$context] = self::acceptRecovery($recovery);
        $id = self::orderId($orderId);
        $raw = self::send(
            'GET',self::URL . '/' . $id,$credential['access_token']
        );
        if (200 !== $raw['status']) {
            throw new MP_HTTP_Failure('pc_mp_get_unverified',false);
        }
        return self::validatedOrder($raw['json'],$context,false,$id);
    }

    /**
     * GET por referencia inmutable para conciliar POST incierto, con rango
     * obligatorio RFC3339 <=30 días. Un resultado vacío NO prueba que nunca
     * se creó fuera de esa ventana ni autoriza un segundo POST.
     */
    public static function searchReference(
        array $recovery,\DateTimeImmutable $startUtc,\DateTimeImmutable $endUtc
    ): array {
        Transaction::assertOutside();
        [$credential,$context] = self::acceptRecovery($recovery);
        $start = $startUtc->setTimezone(new \DateTimeZone('UTC'));
        $end = $endUtc->setTimezone(new \DateTimeZone('UTC'));
        if ($end <= $start || $end->getTimestamp() - $start->getTimestamp() > 2592000
            || $start > new \DateTimeImmutable('now',new \DateTimeZone('UTC'))) {
            throw new MP_HTTP_Failure('pc_mp_search_window_invalid',false);
        }
        $query = \http_build_query([
            'begin_date'=>$start->format('Y-m-d\TH:i:s\Z'),
            'end_date'=>$end->format('Y-m-d\TH:i:s\Z'),
            'external_reference'=>$recovery['external_reference'],
            'limit'=>'10','offset'=>'0',
        ],'', '&',\PHP_QUERY_RFC3986);
        $raw = self::send(
            'GET',self::URL . '?' . $query,$credential['access_token']
        );
        if (200 !== $raw['status'] || !\is_array($raw['json']['data'] ?? null)
            || !\array_is_list($raw['json']['data'])
            || \count($raw['json']['data'])>10) {
            throw new MP_HTTP_Failure('pc_mp_search_unverified',false);
        }
        $paging = $raw['json']['paging'] ?? null;
        $total = $paging['total'] ?? null;
        if (!\is_array($paging) || (!\is_int($total) && !\is_string($total))
            || !\preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/D',(string)$total)
            || (int)$total>1) {
            // Más de una order con la referencia impide elegir por intuición.
            throw new MP_HTTP_Failure('pc_mp_search_ambiguous',false);
        }
        $matches = [];
        foreach ($raw['json']['data'] as $order) {
            $matches[] = self::validatedOrder($order,$context,false);
        }
        if (\count($matches)>1) {
            throw new MP_HTTP_Failure('pc_mp_search_ambiguous',false);
        }
        return $matches;
    }

    /**
     * El contexto es el aprobado y cifrado por Idempotency, no un body
     * arbitrario del navegador; la consulta de Vault recupera la credencial
     * de su misma huella/ambiente, incluso si fue rotada después.
     */
    private static function acceptRecovery(array $recovery): array
    {
        if (!\is_string($recovery['operation_id'] ?? null)
            || !\is_string($recovery['provider_account_digest'] ?? null)
            || !\is_string($recovery['environment'] ?? null)
            || !\is_string($recovery['provider_idempotency_key'] ?? null)
            || !\is_string($recovery['external_reference'] ?? null)
            || !\is_array($recovery['checkout_context'] ?? null)
            || !\preg_match('/\A[0-9a-f]{64}\z/D',
                $recovery['provider_account_digest'])
            || !\preg_match('/\A[0-9a-f-]{36}\z/D',
                $recovery['provider_idempotency_key'])
            || !\preg_match('/\Apc-[a-f0-9]{32}\z/D',
                $recovery['external_reference'])) {
            throw new MP_HTTP_Failure('pc_mp_context_unverified',false);
        }
        $id = Domain_Validation::sqlId($recovery['operation_id']);
        $context = $recovery['checkout_context'];
        if (($context['provider_request']['external_reference'] ?? null)
            !== $recovery['external_reference']
            || ($context['currency'] ?? null) !== 'PEN'
            || !\is_array($context['provider_request'] ?? null)) {
            throw new MP_HTTP_Failure('pc_mp_context_unverified',false);
        }
        // Cuenta/autorización de recuperación se comprobaron ANTES, en
        // Idempotency::recoveryContext(*) (no conceder acceso por el array).
        $credential = MP_Credentials::forDigest(
            $recovery['provider_account_digest'],$recovery['environment']
        );
        return [$credential,$context,$id];
    }

    /**
     * WP HTTP con URL fija HTTPS y sin redirecciones. Nunca aceptar
     * respuestas/cabeceras de Mercado Pago en logs, errores o REST públicos.
     * El cuerpo de error no sale de este método.
     *
     * @return array{status:int,json:array<string,mixed>}
     */
    private static function send(
        string $method,string $url,string $accessToken,
        ?string $body=null,?string $idempotencyKey=null
    ): array {
        Transaction::assertOutside();
        if (!\in_array($method,['POST','GET'],true)
            || !\str_starts_with($url,self::URL)
            || !\function_exists('wp_safe_remote_request')) {
            throw new MP_HTTP_Failure('pc_mp_transport_not_ready',false);
        }
        $headers = [
            'Accept'=>'application/json',
            'Authorization'=>'Bearer ' . $accessToken,
        ];
        if ('POST' === $method) {
            if (!\is_string($idempotencyKey)
                || !\preg_match(
                    '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',
                    $idempotencyKey
                ) || !\is_string($body)) {
                throw new MP_HTTP_Failure('pc_mp_request_invalid',false);
            }
            $headers['X-Idempotency-Key']=$idempotencyKey;
            $headers['Content-Type']='application/json';
        }
        try {
            $response = \wp_safe_remote_request($url,[
                'method'=>$method,'headers'=>$headers,
                'body'=>$body ?? '', 'timeout'=>10,
                'redirection'=>0,'sslverify'=>true,'blocking'=>true,
                'reject_unsafe_urls'=>true,
                'limit_response_size'=>self::MAX_RESPONSE_BYTES,
            ]);
            if (\is_wp_error($response) || !\is_array($response)) {
                throw new \UnexpectedValueException();
            }
            $status = \wp_remote_retrieve_response_code($response);
            $raw = \wp_remote_retrieve_body($response);
            if (!\is_int($status) || !\is_string($raw)
                || \strlen($raw)>self::MAX_RESPONSE_BYTES) {
                throw new \UnexpectedValueException();
            }
            if (200 === $status || 201 === $status) {
                $parsed = \json_decode($raw,true,20,\JSON_THROW_ON_ERROR);
                if (!\is_array($parsed) || \array_is_list($parsed)) {
                    throw new \UnexpectedValueException();
                }
                return ['status'=>$status,'json'=>$parsed];
            }
            return ['status'=>$status,'json'=>[]];
        } catch (\Throwable $ignored) {
            throw new MP_HTTP_Failure('pc_mp_transport_uncertain','POST'===$method);
        }
    }

    /**
     * Verifica pertenencia, moneda y total antes de exponer un dato
     * saneado a IMP-28/30. Un status processed NO acredita por sí mismo.
     * @return array<string,mixed>
     */
    private static function validatedOrder(
        mixed $raw,array $frozen,bool $created,?string $expectedId=null
    ): array {
        if (!\is_array($raw)
            || !\is_string($raw['id'] ?? null)
            || !\is_string($raw['external_reference'] ?? null)
            || ($raw['external_reference'] !==
                ($frozen['provider_request']['external_reference'] ?? null))
            || ($raw['type'] ?? null) !== 'online'
            || ($raw['processing_mode'] ?? null) !== 'manual'
            || ($raw['currency'] ?? null) !== 'PEN'
            || ($raw['country_code'] ?? null) !== 'PER'
            || !\is_string($raw['total_amount'] ?? null)
            || !\is_string($raw['total_paid_amount'] ?? null)
            || !\is_string($raw['status'] ?? null)
            || !\in_array($raw['status'],[
                'created','processing','processed','action_required',
                'failed','canceled','refunded'
            ],true)
            || !\is_string($raw['user_id'] ?? null)
            && !\is_int($raw['user_id'] ?? null)
            || !\preg_match('/\A[1-9][0-9]{0,19}\z/D',(string)$raw['user_id'])
            || !\is_string($raw['expiration_time'] ?? null)
            || $raw['expiration_time'] !== 'PT15M') {
            throw new MP_HTTP_Failure('pc_mp_response_unverified',$created);
        }
        $id = self::orderId($raw['id']);
        if (null !== $expectedId && !\hash_equals($expectedId,$id)) {
            throw new MP_HTTP_Failure('pc_mp_response_unverified',false);
        }
        try {
            $amount = Money::fromDisplayDecimal($raw['total_amount']);
            $paid = Money::fromDisplayDecimal($raw['total_paid_amount']);
            if ($amount !== Money::fromApi($frozen['total_cents'])
                || $paid > $amount) {
                throw new \UnexpectedValueException();
            }
        } catch (\Throwable $ignored) {
            throw new MP_HTTP_Failure('pc_mp_response_unverified',$created);
        }
        // Si MP devuelve los ítems, cotejar exactamente los pactados
        // (incluido Entrega), sin fiarse únicamente del total global.
        $expectedItems = $frozen['provider_request']['items'] ?? null;
        if (!\is_array($expectedItems) || !\array_is_list($expectedItems)
            || [] === $expectedItems || \count($expectedItems)>101
            || ($created && !\is_array($raw['items'] ?? null))) {
            throw new MP_HTTP_Failure('pc_mp_response_unverified',$created);
        }
        if (isset($raw['items'])) {
            if (!\is_array($raw['items']) || !\array_is_list($raw['items'])
                || \count($raw['items'])!==\count($expectedItems)) {
                throw new MP_HTTP_Failure('pc_mp_response_unverified',$created);
            }
            foreach ($expectedItems as $i=>$item) {
                $returned = $raw['items'][$i];
                try {
                    if (!\is_array($returned)
                        || ($returned['title'] ?? null) !== $item['title']
                        || (int)($returned['quantity'] ?? -1) !== $item['quantity']
                        || Money::fromDisplayDecimal(
                            (string)($returned['unit_price'] ?? '')
                        ) !== Money::fromDisplayDecimal($item['unit_price'])) {
                        throw new \UnexpectedValueException();
                    }
                } catch (\Throwable $ignored) {
                    throw new MP_HTTP_Failure('pc_mp_response_unverified',$created);
                }
            }
        }
        $detail = $raw['status_detail'] ?? null;
        if (null !== $detail
            && (!\is_string($detail)
                || !\preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D',$detail))) {
            throw new MP_HTTP_Failure('pc_mp_response_unverified',$created);
        }
        $checkoutUrl = null;
        if (isset($raw['checkout_url'])) {
            $url = $raw['checkout_url'];
            $parsed = \is_string($url) ? \wp_parse_url($url) : null;
            if (!\is_array($parsed)
                || ($parsed['scheme'] ?? null) !== 'https'
                || ($parsed['host'] ?? null) !== 'www.mercadopago.com.pe'
                || isset($parsed['port']) || isset($parsed['user'])
                || isset($parsed['pass']) || isset($parsed['fragment'])
                || !\is_string($parsed['path'] ?? null)
                || !\str_starts_with($parsed['path'],'/checkout/')
                || ($parsed['query'] ?? null) !== 'order_id=' . $id) {
                throw new MP_HTTP_Failure('pc_mp_response_unverified',$created);
            }
            $checkoutUrl = $url;
        }
        if ($created && ('created' !== $raw['status'] || null === $checkoutUrl)) {
            throw new MP_HTTP_Failure('pc_mp_response_unverified',true);
        }
        // El estado agregado processed NO acredita por sí solo. Exigir
        // una transacción única, identificada y acreditada por el proveedor.
        // IMP-29 verifica además el webhook y sus transacciones antes de
        // invocar al aplicador financiero, nunca desde R8-06 del navegador.
        $paymentProof=null;
        if ('processed'===$raw['status']
            && 'accredited'===($raw['status_detail']??null)
            && $paid===$amount
            && \is_array($raw['transactions']['payments']??null)
            && \array_is_list($raw['transactions']['payments'])
            && 1===\count($raw['transactions']['payments'])) {
            $payment=$raw['transactions']['payments'][0];
            if (\is_array($payment)
                && \is_string($payment['id']??null)
                && \preg_match('/\APAY[A-Z0-9]{12,61}\z/D',$payment['id'])
                && 'processed'===($payment['status']??null)
                && 'accredited'===($payment['status_detail']??null)
                && \is_string($payment['amount']??null)
                && \is_string($payment['paid_amount']??null)) {
                try {
                    if (Money::fromDisplayDecimal($payment['amount'])===$amount
                        && Money::fromDisplayDecimal($payment['paid_amount'])===$amount) {
                        $paymentProof=[
                            'provider_payment_id'=>$payment['id'],
                            'amount_cents'=>Money::toApi($amount),
                            // Hora de VERIFICACIÓN GET, no inventar fecha
                            // de pago no confirmada en el recurso original.
                            'verified_at_utc'=>Business_Time::toApi(Business_Time::nowUtc()),
                        ];
                    }
                } catch (\Throwable $ignored) {
                    // No hay prueba de acreditación utilizable, no inventar.
                }
            }
        }
        return [
            'verified_payment'=>$paymentProof,
            'provider_order_id'=>$id,
            'seller_user_id'=>(string)$raw['user_id'],
            'status'=>$raw['status'],
            'status_detail'=>$detail,
            'currency'=>'PEN','country_code'=>'PER',
            'amount_cents'=>Money::toApi($amount),
            'paid_cents'=>Money::toApi($paid),
            'checkout_url'=>$checkoutUrl,
        ];
    }

    private static function orderId(string $id): string
    {
        if (!\preg_match('/\AORD[A-Z0-9]{16,61}\z/D',$id)) {
            throw new MP_HTTP_Failure('pc_mp_order_id_invalid',false);
        }
        return $id;
    }

    private static function encode(array $body): string
    {
        if (($body['type'] ?? null)!=='online'
            || ($body['processing_mode'] ?? null)!=='manual'
            || ($body['expiration_time'] ?? null)!=='PT15M'
            || !\is_string($body['external_reference'] ?? null)
            || !\preg_match('/\Apc-[a-f0-9]{32}\z/D',$body['external_reference'])
            || !\is_string($body['total_amount'] ?? null)
            || !\is_array($body['items'] ?? null)) {
            throw new MP_HTTP_Failure('pc_mp_request_invalid',false);
        }
        try {
            $json = \json_encode(
                $body,\JSON_THROW_ON_ERROR|\JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES
            );
            if (!\is_string($json) || \strlen($json)>65536) {
                throw new \UnexpectedValueException();
            }
            return $json;
        } catch (\Throwable $ignored) {
            throw new MP_HTTP_Failure('pc_mp_request_invalid',false);
        }
    }
}
