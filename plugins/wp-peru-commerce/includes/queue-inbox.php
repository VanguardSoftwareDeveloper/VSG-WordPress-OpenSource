<?php
/**
 * IMP-10 — inbox durable de avisos de Mercado Pago, sin confirmar pagos.
 *
 * El validador de firma/cuenta lo aportará el módulo MP. Sin verificación
 * real NO se guarda ni se responde recepción exitosa. El cuerpo HTTP
 * original NUNCA se almacena ni se publica; solo digest y referencia opaca.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Queue_Inbox
{
    private const MAX_BODY_BYTES = 65536;

    /** @var callable|null Autenticación real de firma/cuenta/recurso. */
    private static $authenticator = null;

    public static function registerAuthenticator(callable $authenticator): void
    {
        if (null !== self::$authenticator) {
            throw new \LogicException('pc_inbox_authenticator_duplicate');
        }
        self::$authenticator = $authenticator;
    }

    /**
     * Debe invocarse después de validar tamaño y encabezados HTTP desde el
     * controlador REST. La autenticación ocurre ANTES del START TRANSACTION;
     * el ACK HTTP solo se construye tras COMMIT exitoso.
     *
     * El autenticador recibe los encabezados/cuerpo originales y debe
     * comprobar firma oficial x-signature, x-request-id, account/environment,
     * resource y una identidad estable de evento. No basta que payload.status
     * diga "approved" ni que el navegador haya regresado a la tienda.
     *
     * @param array<string,mixed> $headers
     * @return array{id:string,status:string}
     */
    public static function persist(string $rawBody, array $headers): array
    {
        if ('' === $rawBody || \strlen($rawBody) > self::MAX_BODY_BYTES
            || null === self::$authenticator) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        try {
            $verified = (self::$authenticator)($headers, $rawBody);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        $notice = self::verifiedNotice($verified);
        $digest = \hash('sha256', $rawBody);
        $dedup = \hash('sha256', \json_encode([
            'pc-webhook-v1', $notice['provider'], $notice['account_digest'],
            $notice['environment'], $notice['event_identity'],
            $notice['resource_id'], $notice['action_code'],
        ], \JSON_THROW_ON_ERROR));

        return Transaction::run(static function (Transaction_Context $tx) use ($notice, $digest, $dedup): array {
            $table = self::table();
            $prior = self::find($tx, $notice, $dedup);
            if ([] !== $prior) {
                self::sameNotice($prior[0], $notice);
                return ['id' => Domain_Validation::sqlId((string) $prior[0]['id']), 'status' => 'duplicate'];
            }

            // ON DUPLICATE garantiza la misma raíz aun ante carreras entre
            // webhooks. No modifica ni reinicia un evento ya recibido.
            $affected = $tx->execute(
                'INSERT INTO %i (provider, provider_account_key, environment, '
                    . 'event_dedup_hash, resource_id, action_code, signature_verified_at, '
                    . 'payload_digest, technical_phase, received_at, next_retry_at, retry_count) '
                    . 'VALUES (%s, UNHEX(%s), %s, UNHEX(%s), %s, %s, UTC_TIMESTAMP(6), '
                    . 'UNHEX(%s), %s, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), 0) '
                    . 'ON DUPLICATE KEY UPDATE id = id',
                [$table, $notice['provider'], $notice['account_digest'],
                    $notice['environment'], $dedup, $notice['resource_id'],
                    $notice['action_code'], $digest, 'pending']
            );
            if ($affected < 0) {
                throw new Transaction_Failure('pc_inbox_persist_failed');
            }
            $rows = self::find($tx, $notice, $dedup);
            if (1 !== \count($rows)) {
                throw new Transaction_Failure('pc_inbox_persist_failed');
            }
            self::sameNotice($rows[0], $notice);
            return [
                'id' => Domain_Validation::sqlId((string) $rows[0]['id']),
                // FOUND_ROWS puede informar 1 también para una fila
                // existente: no afirmar "nueva" por affected_rows.
                'status' => 'persisted',
            ];
        }, true);
    }

    /**
     * @return array{provider:string,account_digest:string,environment:string,event_identity:string,resource_id:string,action_code:string}
     */
    private static function verifiedNotice(mixed $verified): array
    {
        if (!\is_array($verified)) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        $names = ['provider','account_digest','environment','event_identity','resource_id','action_code'];
        $keys = \array_keys($verified);
        \sort($keys, \SORT_STRING);
        \sort($names, \SORT_STRING);
        if ($keys !== $names) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        foreach ($names as $name) {
            if (!\is_string($verified[$name])) {
                throw new Transaction_Failure('pc_webhook_unverified');
            }
        }
        if ('mercado_pago' !== $verified['provider']
            || !\preg_match('/\A[a-f0-9]{64}\z/D', $verified['account_digest'])
            || !\in_array($verified['environment'], ['sandbox', 'production'], true)
            || !\preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/D', $verified['event_identity'])
            || !\preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/D', $verified['resource_id'])
            || !\preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $verified['action_code'])) {
            throw new Transaction_Failure('pc_webhook_unverified');
        }
        return $verified;
    }

    /** @param array<string,string> $notice
     * @return list<array<string,mixed>>
     */
    private static function find(Transaction_Context $tx, array $notice, string $dedup): array
    {
        return $tx->rows(
            'SELECT id, provider, HEX(provider_account_key) AS account_digest, '
                . 'environment, resource_id, action_code, HEX(payload_digest) AS payload_digest, '
                . 'technical_phase FROM %i WHERE provider = %s '
                . 'AND provider_account_key = UNHEX(%s) AND environment = %s '
                . 'AND event_dedup_hash = UNHEX(%s) FOR UPDATE',
            [self::table(), $notice['provider'], $notice['account_digest'],
                $notice['environment'], $dedup]
        );
    }

    /** @param array<string,mixed> $row
     * @param array<string,string> $notice
     */
    private static function sameNotice(array $row, array $notice): void
    {
        if ((string) $row['provider'] !== $notice['provider']
            || \strtolower((string) $row['account_digest']) !== $notice['account_digest']
            || (string) $row['environment'] !== $notice['environment']
            || (string) $row['resource_id'] !== $notice['resource_id']
            || (string) $row['action_code'] !== $notice['action_code']) {
            throw new Transaction_Failure('pc_inbox_dedup_conflict');
        }
        // No comparar payload_digest: el proveedor puede cambiar metadatos
        // de entrega entre reenvíos del mismo evento identificado y firmado.
    }

    public static function table(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_inbox_schema_missing');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        return Schema::tableNames($wpdb)['pc_webhook_inbox'];
    }
}
