<?php
/**
 * IMP-13 — registro CAS de configuración propia en wp_options, sin tabla 40.
 *
 * El dato comercial es JSON con row_version. El compare-and-swap usa el
 * valor anterior BYTE A BYTE en InnoDB, en la misma Transaction::run() que
 * pc_policy_versions, pc_idempotency y pc_audit_log. NUNCA update_option()
 * ni $wpdb->query() dentro del callback, que romperían el contrato IMP-07.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Settings_Store
{
    public const OPTION = 'perucommerce_commerce_settings_v1';
    private const MAX_OPTION_BYTES = 90000;

    /** Defaults inocuos para el panel, NO identidad jurídica verificada. */
    public static function defaults(): array
    {
        return [
            'row_version' => '1',
            'business' => [
                'business_name' => 'Comercio sin configurar',
                'complaint_mode' => 'native',
            ],
            'fiscal' => ['regime' => 'unconfigured', 'external_document_types' => []],
            'delivery_enabled' => false,
            'payment_enabled' => false,
            'digital_download_limit' => null,
            'map_tiles' => ['enabled' => false],
            'routine_buyer_emails_enabled' => true,
            'policy_version_id' => null,
            'privacy_version_id' => null,
        ];
    }

    /**
     * @return array{settings:array<string,mixed>,raw:?string}
     */
    public static function readWithin(Transaction_Context $tx, bool $forUpdate = false): array
    {
        $result = $tx->rows(
            'SELECT option_value, autoload FROM %i WHERE option_name = %s'
                . ($forUpdate ? ' FOR UPDATE' : ''),
            [self::optionsTable(), self::OPTION]
        );
        if ([] === $result) {
            return ['settings' => self::defaults(), 'raw' => null];
        }
        if (1 !== \count($result)
            || !\is_string($result[0]['option_value'] ?? null)
            || !\in_array((string) ($result[0]['autoload'] ?? ''), ['off', 'no'], true)
            || \strlen($result[0]['option_value']) > self::MAX_OPTION_BYTES) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        $raw = $result[0]['option_value'];
        try {
            $settings = \json_decode($raw, true, 24, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $ignored) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        if (!\is_array($settings)) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        self::validateStored($settings);
        return ['settings' => $settings, 'raw' => $raw];
    }

    /**
     * Inserción inicial concurrente sin perder el estado previo. Sin PATCH
     * exitoso la lectura seguirá entregando defaults administrativos.
     *
     * @return array{settings:array<string,mixed>,raw:?string}
     */
    public static function ensureWithin(Transaction_Context $tx): array
    {
        $initial = self::encode(self::defaults());
        $affected = $tx->execute(
            'INSERT INTO %i (option_name, option_value, autoload) '
                . 'VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_name = option_name',
            [self::optionsTable(), self::OPTION, $initial, 'off']
        );
        if ($affected < 0) {
            throw new Transaction_Failure('pc_settings_cas_failed');
        }
        $current = self::readWithin($tx, true);
        if (null === $current['raw']) {
            throw new Transaction_Failure('pc_settings_cas_failed');
        }
        return $current;
    }

    /**
     * Requiere fila ya bloqueada por FOR UPDATE y raw original exacto.
     * Un update_option() directo de otro componente NO participa de este
     * CAS; solo el escritor de PeruCommerce está autorizado para la clave.
     *
     * @param array<string,mixed> $settings
     */
    public static function compareAndSwapWithin(
        Transaction_Context $tx, string $oldRaw, array $settings
    ): void {
        self::validateStored($settings);
        $next = self::encode($settings);
        if (1 !== $tx->execute(
            'UPDATE %i SET option_value = %s WHERE option_name = %s '
                . 'AND BINARY option_value = BINARY %s AND autoload IN (%s, %s)',
            [self::optionsTable(), $next, self::OPTION, $oldRaw, 'off', 'no']
        )) {
            throw new Transaction_Failure('pc_settings_version_conflict');
        }
    }

    /**
     * Prepara la nueva versión inmutable solo si cambió su texto (comparación
     * de bytes, no trim ni normalización). Se confirma en el mismo SQL COMMIT
     * que el CAS de la opción y el evento/auditoría.
     */
    public static function publishPolicyWithin(
        Transaction_Context $tx, string $kind, string $body
    ): string {
        if (!\in_array($kind, ['commercial_terms', 'privacy_notice'], true)) {
            throw new Transaction_Failure('pc_policy_kind_invalid');
        }
        if ('' === $body || \strlen($body) > 120000
            || !\mb_check_encoding($body, 'UTF-8')
            || \mb_strlen($body, 'UTF-8') > 30000
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $body)) {
            throw new Transaction_Failure('pc_policy_text_invalid');
        }
        $versionCode = 'v1-' . \bin2hex(\random_bytes(16));
        $sha = \hash('sha256', $body);
        if (1 !== $tx->execute(
            'INSERT INTO %i (policy_kind, version_code, body_snapshot, '
                . 'content_hash, effective_from, created_at) '
                . 'VALUES (%s, %s, %s, UNHEX(%s), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))',
            [self::policyTable(), $kind, $versionCode, $body, $sha]
        )) {
            throw new Transaction_Failure('pc_policy_append_failed');
        }
        $rows = $tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_policy_append_failed');
        }
        return Domain_Validation::sqlId((string) $rows[0]['id']);
    }

    /** Recuperar EXACTAMENTE el texto pactado, nunca el texto actual. */
    public static function policyWithin(
        Transaction_Context $tx, string $id, string $kind
    ): string {
        $rows = $tx->rows(
            'SELECT body_snapshot, HEX(content_hash) AS checksum '
                . 'FROM %i WHERE id = %s AND policy_kind = %s',
            [self::policyTable(), Domain_Validation::sqlId($id), $kind]
        );
        if (1 !== \count($rows) || !\is_string($rows[0]['body_snapshot'])
            || !\is_string($rows[0]['checksum'])
            || !\hash_equals(\strtolower($rows[0]['checksum']),
                \hash('sha256', $rows[0]['body_snapshot']))) {
            throw new Transaction_Failure('pc_policy_snapshot_invalid');
        }
        return $rows[0]['body_snapshot'];
    }

    /** Eleva solo la versión del agregado, preservando string BIGINT U64. */
    public static function nextVersion(string $version): string
    {
        Domain_Validation::sqlId($version);
        $digits = \str_split($version);
        for ($i = \count($digits) - 1; $i >= 0; --$i) {
            if ('9' !== $digits[$i]) {
                $digits[$i] = (string) ((int) $digits[$i] + 1);
                $result = \implode('', $digits);
                Domain_Validation::sqlId($result);
                return $result;
            }
            $digits[$i] = '0';
        }
        $result = '1' . \implode('', $digits);
        Domain_Validation::sqlId($result);
        return $result;
    }

    private static bool $queueReaderConnected = false;

    /**
     * Integración oficial de settings con IMP-10. La decisión se toma dentro
     * de la transacción del primer evento; nunca durante el reintento email.
     */
    public static function connectQueueReader(): void
    {
        if (self::$queueReaderConnected) {
            return;
        }
        Queue_Outbox::registerRoutinePolicyReader(
            static function (Transaction_Context $tx, string $_orderId, string $_lineId): array {
                return self::routineDecisionWithin($tx);
            }
        );
        self::$queueReaderConnected = true;
    }

    /** El lector de Queue_Outbox usa la misma vista SQL del hecho. */
    public static function routineDecisionWithin(Transaction_Context $tx): array
    {
        $state = self::readWithin($tx)['settings'];
        return [
            'enabled' => $state['routine_buyer_emails_enabled'],
            'settings_row_version' => $state['row_version'],
        ];
    }

    /**
     * Un cambio SQL directo exige invalidar caches WP tras commit/resultado
     * incierto. Ningún lector PeruCommerce usa get_option() para esta clave.
     */
    public static function invalidateCache(): void
    {
        \wp_cache_delete(self::OPTION, 'options');
        \wp_cache_delete('notoptions', 'options');
        \wp_cache_delete('alloptions', 'options');
    }

    /** @param array<string,mixed> $settings */
    public static function encode(array $settings): string
    {
        try {
            $json = \json_encode(
                $settings,
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
            );
        } catch (\JsonException $ignored) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        if (!\is_string($json) || \strlen($json) > self::MAX_OPTION_BYTES) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        return $json;
    }

    /** @param array<string,mixed> $s */
    private static function validateStored(array $s): void
    {
        $required = [
            'row_version', 'business', 'fiscal', 'delivery_enabled',
            'payment_enabled', 'digital_download_limit', 'map_tiles',
            'routine_buyer_emails_enabled', 'policy_version_id', 'privacy_version_id',
        ];
        foreach ($required as $key) {
            if (!\array_key_exists($key, $s)) {
                throw new Transaction_Failure('pc_settings_record_invalid');
            }
        }
        foreach ($s as $key => $_) {
            if (!\in_array($key, [...$required, 'mail_sender', 'pos'], true)) {
                throw new Transaction_Failure('pc_settings_record_invalid');
            }
        }
        if (!\is_string($s['row_version']) || !\is_bool($s['delivery_enabled'])
            || !\is_bool($s['payment_enabled'])
            || !\is_bool($s['routine_buyer_emails_enabled'])
            || !\is_array($s['business']) || !\is_array($s['fiscal'])
            || !\is_array($s['map_tiles'])
            || (null !== $s['digital_download_limit']
                && (!\is_int($s['digital_download_limit'])
                    || $s['digital_download_limit'] < 1
                    || $s['digital_download_limit'] > 100000))) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        Domain_Validation::sqlId($s['row_version']);
        foreach (['policy_version_id', 'privacy_version_id'] as $idKey) {
            if (null !== $s[$idKey]) {
                if (!\is_string($s[$idKey])) {
                    throw new Transaction_Failure('pc_settings_record_invalid');
                }
                Domain_Validation::sqlId($s[$idKey]);
            }
        }
        if (isset($s['mail_sender']) && !\is_array($s['mail_sender'])) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        if (\array_key_exists('pos', $s)) {
            self::validatePos($s['pos']);
        }
    }

    /**
     * D-216: ajustes POS persistidos. Ausentes equivalen a desactivado y sin
     * cajas; nunca se completan en defaults() para no reescribir la opción.
     *
     * @param array<string,mixed> $s
     * @return array{enabled:bool,registers:list<array{code:string,label:string}>}
     */
    public static function posState(array $s): array
    {
        return $s['pos'] ?? ['enabled' => false, 'registers' => []];
    }

    /**
     * Regla de negocio (no puerta de seguridad): POS activo y caja configurada.
     *
     * @param array<string,mixed> $s
     */
    public static function posRegisterActive(array $s, string $code): bool
    {
        $pos = self::posState($s);
        return true === $pos['enabled']
            && \in_array($code, \array_column($pos['registers'], 'code'), true);
    }

    /** Etiqueta visible de caja: UTF-8, 1 a 80 caracteres, sin controles ni solo espacios. */
    public static function posLabelValid(mixed $label): bool
    {
        return \is_string($label)
            && \mb_check_encoding($label, 'UTF-8')
            && '' !== \trim($label)
            && \mb_strlen($label, 'UTF-8') <= 80
            && 1 !== \preg_match('/[\x00-\x1F\x7F]/', $label);
    }

    private static function validatePos(mixed $pos): void
    {
        if (!\is_array($pos) || 2 !== \count($pos)
            || !\is_bool($pos['enabled'] ?? null)
            || !\is_array($pos['registers'] ?? null)
            || !\array_is_list($pos['registers'])
            || \count($pos['registers']) > 20) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        $codes = [];
        foreach ($pos['registers'] as $register) {
            if (!\is_array($register) || 2 !== \count($register)
                || !\is_string($register['code'] ?? null)
                || 1 !== \preg_match('/\A[a-z0-9_-]{1,32}\z/D', $register['code'])
                || !self::posLabelValid($register['label'] ?? null)
                || isset($codes[$register['code']])) {
                throw new Transaction_Failure('pc_settings_record_invalid');
            }
            $codes[$register['code']] = true;
        }
    }

    private static function optionsTable(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb || !\is_string($wpdb->options) || '' === $wpdb->options) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        return $wpdb->options;
    }

    private static function policyTable(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_settings_record_invalid');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        return Schema::tableNames($wpdb)['pc_policy_versions'];
    }
}
