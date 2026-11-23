<?php
/**
 * IMP-27 — bóveda MP en wp_options, exclusiva de la instalación WordPress.
 *
 * No suma tablas al contrato de 39. El valor en SQL es solo un sobre
 * AES-256-GCM; cada rotación conserva versiones históricas para conciliación.
 * La clave estable PERUCOMMERCE_MP_CREDENTIALS_KEY se provisiona FUERA del
 * repositorio, con copia segura junto al respaldo. Nunca se autogenera.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Credentials
{
    private const OPTION = 'perucommerce_mp_credentials_v1';
    private const MAX_OPTION_BYTES = 320000;
    private const MAX_HISTORY = 16;

    /** Lee y verifica el sobre cifrado utilizando exclusivamente mysqli del TX. */
    public static function readWithin(Transaction_Context $tx, bool $forUpdate = false): array
    {
        $rows = $tx->rows(
            'SELECT option_value,autoload FROM %i WHERE option_name = %s'
                . ($forUpdate ? ' FOR UPDATE' : ''),
            [self::table(),self::OPTION]
        );
        if ([] === $rows) {
            return [
                'row_version'=>'1','active'=>['test'=>null,'production'=>null],
                'entries'=>[],'raw'=>null,
            ];
        }
        if (1 !== \count($rows)
            || !\is_string($rows[0]['option_value'] ?? null)
            || !\in_array((string)($rows[0]['autoload'] ?? ''),['off','no'],true)
            || \strlen($rows[0]['option_value']) > self::MAX_OPTION_BYTES) {
            throw new Transaction_Failure('pc_mp_vault_invalid');
        }
        $raw = (string) $rows[0]['option_value'];
        try {
            $outer = \json_decode($raw,true,12,\JSON_THROW_ON_ERROR);
            if (!\is_array($outer) || \array_keys($outer) !==
                ['row_version','iv','tag','ciphertext']) {
                throw new \UnexpectedValueException();
            }
            $version = Domain_Validation::sqlId($outer['row_version']);
            $iv = self::decode($outer['iv'],12);
            $tag = self::decode($outer['tag'],16);
            $encrypted = self::decode($outer['ciphertext'],null);
            $plain = \openssl_decrypt(
                $encrypted,'aes-256-gcm',self::key(),\OPENSSL_RAW_DATA,
                $iv,$tag,self::aad($version)
            );
            if (!\is_string($plain) || '' === $plain
                || \strlen($plain) > 240000) {
                throw new \UnexpectedValueException();
            }
            $inside = \json_decode($plain,true,16,\JSON_THROW_ON_ERROR);
            self::validateInside($inside);
            if ($inside['row_version'] !== $version) {
                throw new \UnexpectedValueException();
            }
            return $inside + ['raw'=>$raw];
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_mp_vault_invalid');
        }
    }

    /**
     * Nuevo cifrado atómico bajo el mismo TX que idempotencia y auditoría.
     * No borra tokens anteriores (pueden respaldar pagos en conciliación).
     *
     * @return array{row_version:string,account_digest_hex:string,environment:string}
     */
    public static function rotateWithin(
        Transaction_Context $tx,array $record,string $environment,
        string $accessToken,?string $webhookSecret
    ): array {
        self::validateInput($environment,$accessToken,$webhookSecret);
        self::validateInside($record);
        $version = $record['row_version'];
        $entries = $record['entries'];
        if (\count($entries) >= self::MAX_HISTORY) {
            // Nunca eliminar una credencial antigua que todavía pueda
            // necesitarse para reconciliar una order histórica.
            throw new Transaction_Failure('pc_mp_history_full');
        }
        $id = (string)(\count($entries) + 1);
        $fingerprint = self::fingerprint($environment,$accessToken);
        $entries[] = [
            'id'=>$id,'environment'=>$environment,
            'account_digest_hex'=>$fingerprint,
            'access_token'=>$accessToken,
            'webhook_secret'=>$webhookSecret,
        ];
        $active = $record['active'];
        $active[$environment] = $id;
        $nextVersion = Settings_Store::nextVersion($version);
        $next = [
            'row_version'=>$nextVersion,
            'active'=>$active,
            'entries'=>$entries,
        ];
        self::validateInside($next);
        $encoded = self::seal($next);
        if (null === ($record['raw'] ?? null)) {
            if (1 !== $tx->execute(
                'INSERT INTO %i (option_name,option_value,autoload) '
                    . 'VALUES (%s,%s,%s)',
                [self::table(),self::OPTION,$encoded,'off']
            )) {
                throw new Transaction_Failure('pc_mp_vault_conflict');
            }
        } elseif (1 !== $tx->execute(
            'UPDATE %i SET option_value = %s WHERE option_name = %s '
                . 'AND BINARY option_value = BINARY %s AND autoload IN (%s,%s)',
            [self::table(),$encoded,self::OPTION,$record['raw'],'off','no']
        )) {
            throw new Transaction_Failure('pc_mp_vault_conflict');
        }
        return [
            'row_version'=>$nextVersion,
            'account_digest_hex'=>$fingerprint,
            'environment'=>self::providerEnvironment($environment),
        ];
    }

    /** Solo un estado de configuración: no afirma cuenta ni pago verificables. */
    public static function configuredWithin(Transaction_Context $tx): bool
    {
        $record = self::readWithin($tx);
        return null !== $record['active']['test']
            || null !== $record['active']['production'];
    }

    /**
     * Uso interno por adaptador después de COMMIT. Devuelve secretos SOLO
     * en memoria PHP; los controladores REST jamás serializan este resultado.
     */
    public static function current(string $environment): array
    {
        return Transaction::run(static function (Transaction_Context $tx) use ($environment): array {
            $record = self::readWithin($tx);
            $entryId = $record['active'][$environment] ?? null;
            if (!\is_string($entryId)) {
                throw new Transaction_Failure('pc_mp_credentials_not_ready');
            }
            return self::resolve($record,$entryId,$environment);
        });
    }

    /**
     * Recuperación de operación antigua: usa la credencial exacta que la
     * preparó, NO la nueva activa tras una rotación.
     */
    public static function forDigest(string $digestHex,string $providerEnvironment): array
    {
        if (!\preg_match('/\A[a-f0-9]{64}\z/D',$digestHex)
            || !\in_array($providerEnvironment,['sandbox','production'],true)) {
            throw new Transaction_Failure('pc_mp_credentials_not_ready');
        }
        $environment = 'sandbox' === $providerEnvironment ? 'test' : 'production';
        return Transaction::run(static function (Transaction_Context $tx) use (
            $digestHex,$environment
        ): array {
            $record = self::readWithin($tx);
            for ($i=\count($record['entries'])-1; $i>=0; --$i) {
                $entry = $record['entries'][$i];
                if ($entry['environment'] === $environment
                    && \hash_equals($entry['account_digest_hex'],$digestHex)) {
                    return self::resolve($record,$entry['id'],$environment);
                }
            }
            throw new Transaction_Failure('pc_mp_credentials_not_ready');
        });
    }

    /**
     * Verificación de webhooks solamente en el servidor. Incluye claves
     * anteriores conservadas para operaciones en conciliación; nunca
     * expone Access Token, token secreto o webhook secret al frontend.
     *
     * @return list<array{account_digest:string,environment:string,secret:string,active:bool}>
     */
    public static function webhookCandidates(): array
    {
        Transaction::assertOutside();
        return Transaction::run(static function (Transaction_Context $tx): array {
            $record=self::readWithin($tx);
            $result=[];
            foreach ($record['entries'] as $entry) {
                if (!\is_string($entry['webhook_secret']??null)
                    || ''===$entry['webhook_secret']) {
                    continue;
                }
                $environment=$entry['environment'];
                $result[]=[
                    'account_digest'=>$entry['account_digest_hex'],
                    'environment'=>self::providerEnvironment($environment),
                    'secret'=>$entry['webhook_secret'],
                    'active'=>$record['active'][$environment] === $entry['id'],
                ];
            }
            return $result;
        });
    }

    /** No confundir con el webhook probado en cuenta real P-C. */
    public static function webhookConfigured(): bool
    {
        try {
            return []!==self::webhookCandidates();
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /** Enrutador MP: `test` del DTO se normaliza a sandbox interno. */
    /** Invalidar caché WP fuera de SQL, también tras COMMIT incierto. */
    public static function invalidateCache(): void
    {
        \wp_cache_delete(self::OPTION, 'options');
        \wp_cache_delete('notoptions', 'options');
        \wp_cache_delete('alloptions', 'options');
    }

    public static function providerEnvironment(string $environment): string
    {
        return match ($environment) {
            'test'=>'sandbox',
            'production'=>'production',
            default=>throw new Transaction_Failure('pc_mp_credentials_invalid'),
        };
    }

    /** Dato local de correlación, NO certifica identidad de cuenta MP. */
    private static function fingerprint(string $environment,string $token): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        return \hash_hmac('sha256',
            'pc-mp-credential-v1:' . \get_current_blog_id() . ':' . $wpdb->prefix
                . ':' . $environment . ':' . $token,
            self::key()
        );
    }

    private static function resolve(array $record,string $id,string $environment): array
    {
        foreach ($record['entries'] as $entry) {
            if ($entry['id'] === $id && $entry['environment'] === $environment) {
                return [
                    'environment'=>self::providerEnvironment($environment),
                    'account_digest_hex'=>$entry['account_digest_hex'],
                    'access_token'=>$entry['access_token'],
                    'webhook_secret'=>$entry['webhook_secret'],
                    'vault_row_version'=>$record['row_version'],
                ];
            }
        }
        throw new Transaction_Failure('pc_mp_vault_invalid');
    }

    private static function validateInput(
        string $environment,string $accessToken,?string $webhookSecret
    ): void {
        if (!\in_array($environment,['test','production'],true)
            || !\preg_match('/\A[A-Za-z0-9._~+\/=\-]{16,4096}\z/D',$accessToken)
            || (null !== $webhookSecret
                && !\preg_match('/\A[\x21-\x7E]{16,4096}\z/D',$webhookSecret))) {
            throw new Transaction_Failure('pc_mp_credentials_invalid');
        }
    }

    private static function validateInside(mixed $record): void
    {
        if (!\is_array($record)
            || !\is_string($record['row_version'] ?? null)
            || !\is_array($record['active'] ?? null)
            || !\is_array($record['entries'] ?? null)
            || !\array_is_list($record['entries'])
            || \count($record['entries']) > self::MAX_HISTORY
            || \array_keys($record['active']) !== ['test','production']) {
            throw new Transaction_Failure('pc_mp_vault_invalid');
        }
        Domain_Validation::sqlId($record['row_version']);
        foreach ($record['entries'] as $i=>$entry) {
            if (!\is_array($entry)
                || \array_keys($entry) !== [
                    'id','environment','account_digest_hex','access_token','webhook_secret']
                || ($entry['id'] ?? null) !== (string)($i+1)
                || !\is_string($entry['access_token'] ?? null)
                || !\is_string($entry['environment'] ?? null)
                || !\is_string($entry['account_digest_hex'] ?? null)
                || !\preg_match('/\A[a-f0-9]{64}\z/D',$entry['account_digest_hex'])) {
                throw new Transaction_Failure('pc_mp_vault_invalid');
            }
            self::validateInput(
                $entry['environment'],$entry['access_token'],$entry['webhook_secret']
            );
            if (!\hash_equals(
                $entry['account_digest_hex'],
                self::fingerprint($entry['environment'],$entry['access_token'])
            )) {
                throw new Transaction_Failure('pc_mp_vault_invalid');
            }
        }
        foreach ($record['active'] as $environment=>$id) {
            if (null === $id) {
                continue;
            }
            if (!\is_string($id)) {
                throw new Transaction_Failure('pc_mp_vault_invalid');
            }
            $index = (int)$id - 1;
            if ($id !== (string)($index+1)
                || !isset($record['entries'][$index])
                || $record['entries'][$index]['environment'] !== $environment) {
                throw new Transaction_Failure('pc_mp_vault_invalid');
            }
        }
    }

    private static function seal(array $inside): string
    {
        $version = $inside['row_version'];
        try {
            $plain = \json_encode(
                $inside,\JSON_THROW_ON_ERROR|\JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE
            );
            if (!\is_string($plain) || \strlen($plain)>240000) {
                throw new \UnexpectedValueException();
            }
            $iv = \random_bytes(12);
            $tag = '';
            $encrypted = \openssl_encrypt(
                $plain,'aes-256-gcm',self::key(),\OPENSSL_RAW_DATA,
                $iv,$tag,self::aad($version),16
            );
            if (!\is_string($encrypted) || \strlen($tag)!==16) {
                throw new \UnexpectedValueException();
            }
            $record = \json_encode([
                'row_version'=>$version,'iv'=>\base64_encode($iv),
                'tag'=>\base64_encode($tag),'ciphertext'=>\base64_encode($encrypted),
            ],\JSON_THROW_ON_ERROR);
            if (!\is_string($record) || \strlen($record)>self::MAX_OPTION_BYTES) {
                throw new \UnexpectedValueException();
            }
            return $record;
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_mp_vault_not_ready');
        }
    }

    private static function decode(mixed $value,?int $length): string
    {
        if (!\is_string($value) || '' === $value
            || \strlen($value) > self::MAX_OPTION_BYTES) {
            throw new Transaction_Failure('pc_mp_vault_invalid');
        }
        $raw = \base64_decode($value,true);
        if (!\is_string($raw) || (null !== $length && \strlen($raw)!==$length)) {
            throw new Transaction_Failure('pc_mp_vault_invalid');
        }
        return $raw;
    }

    private static function aad(string $version): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        return 'pc-mp-credentials-v1:' . \get_current_blog_id() . ':'
            . $wpdb->prefix . ':' . self::OPTION . ':' . $version;
    }

    private static function key(): string
    {
        if (!\defined('PERUCOMMERCE_MP_CREDENTIALS_KEY')
            || !\is_string(PERUCOMMERCE_MP_CREDENTIALS_KEY)
            || !\preg_match('/\A[a-f0-9]{64}\z/D',PERUCOMMERCE_MP_CREDENTIALS_KEY)) {
            throw new Transaction_Failure('pc_mp_vault_not_ready');
        }
        $key = \hex2bin(PERUCOMMERCE_MP_CREDENTIALS_KEY);
        if (!\is_string($key) || \strlen($key)!==32) {
            throw new Transaction_Failure('pc_mp_vault_not_ready');
        }
        return $key;
    }

    private static function table(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb || !\is_string($wpdb->options)
            || '' === $wpdb->options) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        return $wpdb->options;
    }
}
