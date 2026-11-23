<?php
/**
 * IMP-28 — persistencia del intento MP con identidad de order verificada.
 *
 * Ningún request de navegador crea intentos. El único origen admitido es
 * MP_Orders_Client validado DESPUÉS de una operación congelada por
 * Idempotency. last_verified_at se fija UNA vez: no renovar 15 minutos
 * por GET, replay, cambio de token ni reintento.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Payment_Attempts
{
    /**
     * El coordinador ya bloqueó operation (10) y order (20). Insertar
     * antes de bloquear líneas/stock/agenda para no invertir anclas.
     * @return string SqlId del intento, existente o creado exactamente una vez
     */
    public static function recordCreatedWithin(
        Transaction_Context $tx,string $operationId,string $orderId,
        array $recovery,array $verified
    ): string {
        $operationId=Domain_Validation::sqlId($operationId);
        $orderId=Domain_Validation::sqlId($orderId);
        $op=$tx->lockedOperation($operationId);
        $order=$tx->lockedOrder($orderId);
        if (!\in_array((string)$op['technical_phase'],
            ['reconcile','external_pending'],true)
            || 'PENDIENTE_PAGO' !== (string)$order['status']
            || 'mercado_pago' !== (string)$order['payment_method']
            || ($recovery['operation_id']??null)!==$operationId
            || ($recovery['order_id']??null)!==$orderId
            || !\is_string($recovery['provider_account_digest']??null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$recovery['provider_account_digest'])
            || !\in_array(($recovery['environment']??null),['sandbox','production'],true)
            || !\is_string($recovery['provider_idempotency_key']??null)
            || !\preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D',
                $recovery['provider_idempotency_key'])
            || !\is_string($recovery['external_reference']??null)
            || !\preg_match('/\Apc-[a-f0-9]{32}\z/D',$recovery['external_reference'])
            || !\is_array($recovery['checkout_context']??null)
            || !\is_string($verified['provider_order_id']??null)
            || !\preg_match('/\AORD[A-Z0-9]{16,61}\z/D',$verified['provider_order_id'])
            || ($verified['status']??null)!=='created'
            || ($verified['status_detail']??null)!=='created'
            || ($verified['paid_cents']??null)!=='0'
            || ($verified['currency']??null)!=='PEN'
            || ($verified['country_code']??null)!=='PER'
            || ($verified['amount_cents']??null)!==
                ($recovery['checkout_context']['total_cents']??null)
            || !\is_string($verified['checkout_url']??null)) {
            throw new Transaction_Failure('pc_mp_attempt_unverified');
        }

        // El root congela también la cuenta. No basta el token del browser.
        $link=$tx->rows(
            'SELECT operation_type,entity_type,entity_id,technical_phase,'
                . 'HEX(provider_account_key) AS account_hex,provider_environment,'
                . 'provider_idempotency_key,provider_external_reference '
                . 'FROM %i WHERE id = %s',
            [Orders_Domain::table('pc_idempotency'),$operationId]
        );
        $amount=$tx->rows(
            'SELECT total_cents,currency FROM %i WHERE id = %s',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($link) || 1!==\count($amount)
            || 'checkout_mp'!==$link[0]['operation_type']
            || 'order'!==$link[0]['entity_type']
            || (string)$link[0]['entity_id']!==$orderId
            || !\hash_equals($recovery['provider_account_digest'],
                \strtolower((string)$link[0]['account_hex']))
            || $link[0]['provider_environment']!==$recovery['environment']
            || !\hash_equals((string)$link[0]['provider_idempotency_key'],
                $recovery['provider_idempotency_key'])
            || !\hash_equals((string)$link[0]['provider_external_reference'],
                $recovery['external_reference'])
            || 'PEN'!==$amount[0]['currency']
            || (string)$amount[0]['total_cents']!==$verified['amount_cents']) {
            throw new Transaction_Failure('pc_mp_attempt_unverified');
        }
        $rows=$tx->rows(
            'SELECT id,order_id,provider_order_id,amount_cents,currency,state,'
                . 'last_verified_at FROM %i WHERE provider = %s '
                . 'AND provider_account_key = UNHEX(%s) AND environment = %s '
                . 'AND mp_idempotency_key = %s FOR UPDATE',
            [Orders_Domain::table('pc_payment_attempts'),
                'mercado_pago',$recovery['provider_account_digest'],
                $recovery['environment'],$recovery['provider_idempotency_key']]
        );
        if ([]!==$rows) {
            if (1!==\count($rows)
                || (string)$rows[0]['order_id']!==$orderId
                || !\hash_equals((string)$rows[0]['provider_order_id'],
                    $verified['provider_order_id'])
                || (string)$rows[0]['amount_cents']!==$verified['amount_cents']
                || $rows[0]['currency']!=='PEN'
                || !\in_array($rows[0]['state'],['CREADO','PENDIENTE'],true)
                || null===$rows[0]['last_verified_at']) {
                throw new Transaction_Failure('pc_mp_attempt_conflict');
            }
            return Domain_Validation::sqlId((string)$rows[0]['id']);
        }
        if (1!==$tx->execute(
            'INSERT INTO %i (order_id,provider,provider_account_key,'
                . 'environment,provider_order_id,provider_payment_id,'
                . 'mp_idempotency_key,amount_cents,currency,state,'
                . 'checkout_url_reference,created_at,last_verified_at,row_version) '
                . 'VALUES (%s,%s,UNHEX(%s),%s,%s,NULL,%s,%s,%s,%s,NULL,'
                . 'UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),1)',
            [Orders_Domain::table('pc_payment_attempts'),
                $orderId,'mercado_pago',$recovery['provider_account_digest'],
                $recovery['environment'],$verified['provider_order_id'],
                $recovery['provider_idempotency_key'],$verified['amount_cents'],
                'PEN','CREADO']
        )) {
            throw new Transaction_Failure('pc_mp_attempt_insert_failed');
        }
        $last=$tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1!==\count($last)) {
            throw new Transaction_Failure('pc_mp_attempt_insert_failed');
        }
        return Domain_Validation::sqlId((string)$last[0]['id']);
    }

    /**
     * IMP-30: asociar una order hallada por búsqueda OFICIAL al root MP
     * antiguo sin intento local. Siempre estado REQUIERE_CONCILIACION,
     * sin URL, admisión nueva, hold ni pago acreditado. El GET no
     * reconstruye el instante original del intento de pago.
     */
    public static function recoverUnheldWithin(
        Transaction_Context $tx,array $recovery,array $official
    ): string {
        $root=Domain_Validation::sqlId($recovery['operation_id']);
        $orderId=Domain_Validation::sqlId($recovery['order_id']);
        $tx->lockedOperation($root);
        $order=$tx->lockedOrder($orderId);
        if ('PENDIENTE_PAGO'!==(string)$order['status']
            || 'mercado_pago'!==(string)$order['payment_method']
            || !\is_string($official['provider_order_id']??null)
            || !\preg_match('/\AORD[A-Z0-9]{16,61}\z/D',
                $official['provider_order_id'])
            || ($official['currency']??null)!=='PEN'
            || ($official['country_code']??null)!=='PER'
            || !\is_string($official['amount_cents']??null)
            || $official['amount_cents']!==
                ($recovery['checkout_context']['total_cents']??null)
            || !\is_string($recovery['provider_account_digest']??null)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',
                $recovery['provider_account_digest'])
            || !\in_array(($recovery['environment']??null),
                ['sandbox','production'],true)
            || !\is_string($recovery['provider_idempotency_key']??null)) {
            throw new Transaction_Failure('pc_mp_attempt_unverified');
        }
        $links=$tx->rows(
            'SELECT operation_type,entity_type,entity_id,'
                . 'LOWER(HEX(provider_account_key)) AS account_digest,'
                . 'provider_environment,provider_idempotency_key,'
                . 'provider_external_reference FROM %i WHERE id=%s',
            [Orders_Domain::table('pc_idempotency'),$root]
        );
        $total=$tx->rows(
            'SELECT total_cents,currency FROM %i WHERE id=%s',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($links) || 1!==\count($total)
            || 'checkout_mp'!==$links[0]['operation_type']
            || 'order'!==$links[0]['entity_type']
            || (string)$links[0]['entity_id']!==$orderId
            || $links[0]['account_digest']!==$recovery['provider_account_digest']
            || $links[0]['provider_environment']!==$recovery['environment']
            || $links[0]['provider_idempotency_key']!==
                $recovery['provider_idempotency_key']
            || $links[0]['provider_external_reference']!==
                $recovery['external_reference']
            || 'PEN'!==$total[0]['currency']
            || (string)$total[0]['total_cents']!==$official['amount_cents']) {
            throw new Transaction_Failure('pc_mp_attempt_unverified');
        }
        $table=Orders_Domain::table('pc_payment_attempts');
        $existing=$tx->rows(
            'SELECT id,order_id,provider_order_id FROM %i WHERE provider=%s '
                . 'AND provider_account_key=UNHEX(%s) AND environment=%s '
                . 'AND mp_idempotency_key=%s FOR UPDATE',
            [$table,'mercado_pago',$recovery['provider_account_digest'],
                $recovery['environment'],$recovery['provider_idempotency_key']]
        );
        if ([]!==$existing) {
            if (1!==\count($existing)
                || (string)$existing[0]['order_id']!==$orderId
                || $existing[0]['provider_order_id']!==
                    $official['provider_order_id']) {
                throw new Transaction_Failure('pc_mp_attempt_conflict');
            }
            return Domain_Validation::sqlId((string)$existing[0]['id']);
        }
        if (1!==$tx->execute(
            'INSERT INTO %i (order_id,provider,provider_account_key,'
                . 'environment,provider_order_id,provider_payment_id,'
                . 'mp_idempotency_key,amount_cents,currency,state,'
                . 'checkout_url_reference,created_at,last_verified_at,row_version) '
                . 'VALUES (%s,%s,UNHEX(%s),%s,%s,NULL,%s,%s,%s,%s,NULL,'
                . 'UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),1)',
            [$table,$orderId,'mercado_pago',
                $recovery['provider_account_digest'],$recovery['environment'],
                $official['provider_order_id'],
                $recovery['provider_idempotency_key'],
                $official['amount_cents'],'PEN','REQUIERE_CONCILIACION']
        )) {
            throw new Transaction_Failure('pc_mp_attempt_insert_failed');
        }
        $last=$tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1!==\count($last)) {
            throw new Transaction_Failure('pc_mp_attempt_insert_failed');
        }
        return Domain_Validation::sqlId((string)$last[0]['id']);
    }

    /**
     * Leer resultado local sin recuperar secretos ni aceptar IDs del
     * navegador. Requiere bloqueo raíz en operación y pedido en ese orden.
     * NO prolongar holds ni reclamar pago por ausencia de evento.
     */
    public static function snapshotWithin(
        Transaction_Context $tx,string $operationId,string $orderId
    ): array {
        $operationId=Domain_Validation::sqlId($operationId);
        $orderId=Domain_Validation::sqlId($orderId);
        $tx->lockedOperation($operationId);
        $order=$tx->lockOrder($orderId);
        if ('mercado_pago'!==(string)$order['payment_method']) {
            throw new Transaction_Failure('pc_mp_attempt_conflict');
        }
        $link=$tx->rows(
            'SELECT entity_type,entity_id,provider_idempotency_key,'
                . 'HEX(provider_account_key) AS account_hex,provider_environment '
                . 'FROM %i WHERE id = %s AND operation_type = %s',
            [Orders_Domain::table('pc_idempotency'),$operationId,'checkout_mp']
        );
        if (1!==\count($link) || 'order'!==$link[0]['entity_type']
            || (string)$link[0]['entity_id']!==$orderId
            || !\is_string($link[0]['provider_idempotency_key'])
            || !\is_string($link[0]['account_hex'])) {
            throw new Transaction_Failure('pc_mp_attempt_conflict');
        }
        $rows=$tx->rows(
            'SELECT id,provider_order_id,amount_cents,currency,state,'
                . 'last_verified_at FROM %i WHERE provider = %s '
                . 'AND provider_account_key = UNHEX(%s) AND environment = %s '
                . 'AND mp_idempotency_key = %s AND order_id = %s FOR UPDATE',
            [Orders_Domain::table('pc_payment_attempts'),'mercado_pago',
                \strtolower((string)$link[0]['account_hex']),
                $link[0]['provider_environment'],$link[0]['provider_idempotency_key'],
                $orderId]
        );
        if ([]===$rows) {
            return ['attempt_id'=>null,'provider_order_id'=>null,'ready'=>false,
                'tracking_link_issued'=>false,'expires_at'=>null];
        }
        if (1!==\count($rows) || null===$rows[0]['last_verified_at']
            || !\is_string($rows[0]['provider_order_id'])
            || 'PEN'!==$rows[0]['currency']) {
            throw new Transaction_Failure('pc_mp_attempt_conflict');
        }
        $attemptId=Domain_Validation::sqlId((string)$rows[0]['id']);
        $origin=$tx->rows(
            'SELECT total_cents,currency FROM %i WHERE id = %s',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($origin)
            || (string)$origin[0]['total_cents']!==(string)$rows[0]['amount_cents']
            || 'PEN'!==$origin[0]['currency']) {
            throw new Transaction_Failure('pc_mp_attempt_conflict');
        }
        $deadline=$tx->rows(
            'SELECT DATE_ADD(last_verified_at,INTERVAL 15 MINUTE) AS expiry, '
                . 'UTC_TIMESTAMP(6) AS now_utc FROM %i WHERE id = %s',
            [Orders_Domain::table('pc_payment_attempts'),$attemptId]
        );
        if (1!==\count($deadline)
            || !\is_string($deadline[0]['expiry']??null)
            || !\is_string($deadline[0]['now_utc']??null)) {
            throw new Transaction_Failure('pc_mp_attempt_conflict');
        }
        $expiresAt=$deadline[0]['expiry'];
        $ready='PENDIENTE_PAGO'===(string)$order['status']
            && \in_array($rows[0]['state'],['CREADO','PENDIENTE'],true)
            && $expiresAt>$deadline[0]['now_utc'];
        $lines=$tx->rows(
            'SELECT id,kind FROM %i WHERE order_id = %s ORDER BY id LIMIT 101',
            [Orders_Domain::table('pc_order_lines'),$orderId]
        );
        if ([]===$lines || \count($lines)>100) {
            throw new Transaction_Failure('pc_mp_attempt_conflict');
        }
        foreach ($lines as $line) {
            if ('digital'===$line['kind']) { continue; }
            $kind='service'===$line['kind']?'slot':'stock';
            $holds=$tx->rows(
                'SELECT expires_at FROM %i WHERE order_id = %s '
                    . 'AND order_line_id = %s AND payment_attempt_id = %s '
                    . 'AND resource_kind = %s AND state = %s '
                    . 'AND expires_at > UTC_TIMESTAMP(6)',
                [Agenda_Repository::table('pc_holds'),$orderId,
                    (string)$line['id'],$attemptId,$kind,'VIGENTE']
            );
            if (1!==\count($holds)
                || (string)$holds[0]['expires_at']!==$expiresAt) {
                $ready=false;
                break;
            }
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        $guests=$tx->rows(
            'SELECT id FROM %i WHERE order_id = %s '
                . 'AND credential_kind = %s LIMIT 2',
            [Schema::tableNames($wpdb)['pc_guest_sessions'],
                $orderId,'tracking']
        );
        if (\count($guests)>1) {
            throw new Transaction_Failure('pc_mp_attempt_conflict');
        }
        $issued=1===\count($guests);
        return [
            'attempt_id'=>$attemptId,
            'provider_order_id'=>(string)$rows[0]['provider_order_id'],
            'ready'=>$ready && $issued,
            'tracking_link_issued'=>$issued,
            'expires_at'=>$ready ? Business_Time::toApi(
                Business_Time::fromDatabase($expiresAt)
            ) : null,
        ];
    }

    /** Fecha de raíz durable, para búsqueda acotada de POST perdido. */
    public static function operationCreatedAtWithin(
        Transaction_Context $tx,string $operationId,string $orderId
    ): \DateTimeImmutable {
        $tx->lockedOperation($operationId);
        $tx->lockOrder($orderId);
        $rows=$tx->rows(
            'SELECT created_at,entity_id FROM %i '
                . 'WHERE id = %s AND operation_type = %s',
            [Orders_Domain::table('pc_idempotency'),$operationId,'checkout_mp']
        );
        if (1!==\count($rows) || (string)$rows[0]['entity_id']!==$orderId) {
            throw new Transaction_Failure('pc_mp_attempt_conflict');
        }
        return Business_Time::fromDatabase((string)$rows[0]['created_at']);
    }
}
