<?php
/**
 * IMP-29 — autenticador de Webhooks Orders (no IPN ni Preferences).
 *
 * Firma oficial: HMAC-SHA256 del manifest
 * "id:<data.id en minusculas>;request-id:<x-request-id>;ts:<ts>;".
 * El body NO está cubierto por ese manifest, por eso solo se usa para
 * corroborar la referencia y el ambiente; jamás para acreditar dinero.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Webhook_Auth
{
    private const MAX_BODY_BYTES=65536;
    private const MAX_SKEW_SECONDS=300;

    /** Requiere la prueba real para Access_Control actor=provider. */
    public static function identity(string $operationId,array $context): bool
    {
        if ('R8-17'!==$operationId
            || !\is_array($context['provider_auth']??null)) {
            return false;
        }
        try {
            self::authenticate($context['provider_auth'],
                $context['provider_auth']['raw_body']??'');
            return true;
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * Autenticador de Queue_Inbox::persist(). No persiste secretos ni
     * contenido bruto. Si varias credenciales verifican la firma, resolver
     * solamente contra el intento local del mismo order/entorno; de otro
     * modo rechazar un ambiguo y dejar conciliación manual.
     *
     * @param array<string,mixed> $headers
     * @return array{provider:string,account_digest:string,environment:string,event_identity:string,resource_id:string,action_code:string}
     */
    public static function authenticate(array $headers,mixed $rawBody): array
    {
        Transaction::assertOutside();
        $signature=$headers['x_signature']??null;
        $requestId=$headers['x_request_id']??null;
        $query=$headers['query']??null;
        if (!\is_string($rawBody) || ''===$rawBody
            || \strlen($rawBody)>self::MAX_BODY_BYTES
            || !\is_string($signature) || \strlen($signature)>512
            || !\preg_match('/\Ats=([0-9]{10}|[0-9]{13}),v1=([a-f0-9]{64})\z/D',
                $signature,$sig)
            || !\is_string($requestId)
            || !\preg_match('/\A[A-Za-z0-9-]{16,128}\z/D',$requestId)
            || !\is_array($query) || \count($query)>4
            || ($query['type']??null)!=='order'
            || !\is_string($query['data.id']??null)
            || !\preg_match('/\AORD[A-Z0-9]{16,61}\z/D',$query['data.id'])
        ) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        foreach (\array_keys($query) as $name) {
            if (!\in_array($name,['rest_route','type','data.id'],true)) {
                throw new Transaction_Failure('pc_webhook_unverified');
            }
        }
        $ts=$sig[1];
        $time=10===\strlen($ts)?(int)$ts:\intdiv((int)$ts,1000);
        if (\abs(\time()-$time)>self::MAX_SKEW_SECONDS) {
            throw new Transaction_Failure('pc_webhook_signature_expired');
        }
        try {
            $body=\json_decode($rawBody,true,16,
                \JSON_THROW_ON_ERROR|\JSON_BIGINT_AS_STRING);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        if (!\is_array($body) || \array_is_list($body)
            || ($body['type']??null)!=='order'
            || !\is_array($body['data']??null)
            || ($body['data']['id']??null)!==$query['data.id']
            || !\is_bool($body['live_mode']??null)) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        $action=$body['action']??'order.notification';
        if (!\is_string($action)
            || !\preg_match('/\Aorder\.[a-z][a-z0-9_]{0,57}\z/D',$action)
            || \strlen($action)>64) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        $id=$body['id']??null;
        if (null!==$id && !\is_string($id) && !\is_int($id)) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        $eventId=null===$id?$requestId:(string)$id;
        if (!\preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/D',$eventId)) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        $environment=$body['live_mode']?'production':'sandbox';
        // La referencia de order va en el manifest en MINÚSCULAS.
        $manifest='id:' . \strtolower($query['data.id'])
            . ';request-id:' . $requestId . ';ts:' . $ts . ';';
        $matched=[];
        foreach (MP_Credentials::webhookCandidates() as $candidate) {
            $digest=\hash_hmac('sha256',$manifest,$candidate['secret']);
            if ($candidate['environment']===$environment
                && \hash_equals($digest,$sig[2])) {
                $key=$candidate['account_digest'].':'.$candidate['environment'];
                $matched[$key]=[
                    'account_digest'=>$candidate['account_digest'],
                    'environment'=>$candidate['environment'],
                    'active'=>$candidate['active'],
                ];
            }
        }
        if ([]===$matched) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        $matched=\array_values($matched);
        if (1===\count($matched)) {
            $selected=$matched[0];
        } else {
            $selected=self::selectAccount($matched,$query['data.id']);
        }
        return [
            'provider'=>'mercado_pago',
            'account_digest'=>$selected['account_digest'],
            'environment'=>$selected['environment'],
            'event_identity'=>$eventId,
            'resource_id'=>$query['data.id'],
            'action_code'=>$action,
        ];
    }

    /** El identificador de order se resuelve por cuenta original, no IP. */
    private static function selectAccount(array $matches,string $orderId): array
    {
        $linked=Transaction::run(static function(Transaction_Context $tx) use ($orderId): array {
            return $tx->rows(
                'SELECT LOWER(HEX(provider_account_key)) AS account_digest, '
                    . 'environment FROM %i WHERE provider = %s '
                    . 'AND provider_order_id = %s LIMIT 17',
                [Orders_Domain::table('pc_payment_attempts'),
                    'mercado_pago',$orderId]
            );
        });
        $choices=[];
        foreach ($matches as $candidate) {
            foreach ($linked as $row) {
                if ($candidate['environment']===$row['environment']
                    && \hash_equals($candidate['account_digest'],
                        (string)$row['account_digest'])) {
                    $choices[]=$candidate;
                    break;
                }
            }
        }
        if (1===\count($choices)) {
            return $choices[0];
        }
        if ([]===$choices) {
            $active=\array_values(\array_filter($matches,
                static fn(array $entry):bool=>true===$entry['active']));
            if (1===\count($active)) {
                return $active[0];
            }
        }
        throw new Transaction_Failure('pc_webhook_account_ambiguous');
    }
}
