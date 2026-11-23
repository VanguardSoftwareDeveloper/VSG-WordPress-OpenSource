<?php
/**
 * IMP-19 — control CAS del conjunto de tarifas sin agregar tabla #40.
 *
 * wp_options guarda JSON en una única fila estable InnoDB/no-autoload:
 * row_version + dataset SHA-256 revisado por el administrador.
 * Importar una fuente nueva limpia la aprobación aunque existan tarifas
 * previas: no ofrecer cobertura sin revalidación humana y evidencia INEI.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Coverage_Version
{
    public const OPTION = 'perucommerce_coverage_version_v1';

    public static function optionsTransactional(): bool
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return false;
        }
        $old = $wpdb->suppress_errors(true);
        $hide = $wpdb->hide_errors();
        try {
            $db = $wpdb->get_var('SELECT DATABASE()');
            if (!\is_string($db) || '' === $db) {
                return false;
            }
            $sql = $wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES '
                    . 'WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
                $db, $wpdb->options
            );
            $rows = $wpdb->get_results($sql, ARRAY_A);
            return \is_array($rows) && 1 === \count($rows)
                && 'InnoDB' === ($rows[0]['ENGINE'] ?? null)
                && '' === $wpdb->last_error;
        } catch (\Throwable $ignored) {
            return false;
        } finally {
            $wpdb->suppress_errors($old);
            if ($hide) {
                $wpdb->show_errors(true);
            }
        }
    }

    public static function readWithin(
        Transaction_Context $tx, bool $forUpdate = false
    ): string {
        return self::recordWithin($tx, $forUpdate)['version'];
    }

    /** Sin hash del dataset actual aprobado, solo existe lectura informativa. */
    public static function reviewedWithin(Transaction_Context $tx, string $datasetHash): bool
    {
        self::hash($datasetHash);
        $record = self::recordWithin($tx, false);
        return $record['approved_hash'] === $datasetHash;
    }

    /**
     * Falta de opción: GET falla cerrado; solo el importador/PATCH pueden
     * crear la fila por primera vez, bajo locks y operación idempotente.
     */
    public static function ensureWithin(Transaction_Context $tx): string
    {
        $initial = self::encode(['row_version' => '1', 'approved_dataset_hash' => null]);
        $n = $tx->execute(
            'INSERT INTO %i (option_name, option_value, autoload) '
                . 'VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_name = option_name',
            [Ubigeo_Catalog::options(), self::OPTION, $initial, 'off']
        );
        if ($n < 0) {
            throw new Transaction_Failure('pc_coverage_conflict');
        }
        return self::readWithin($tx, true);
    }

    /**
     * El llamante ya tiene el lock de esta fila (FOR UPDATE).
     * - importador: approvedHash=NULL, invalida cobertura anterior
     * - R8-52: hash de la versión INEI activa, aprobación administrativa
     */
    public static function incrementWithin(
        Transaction_Context $tx, string $current, ?string $approvedHash = null
    ): string {
        $current = self::version($current);
        if (null !== $approvedHash) {
            self::hash($approvedHash);
        }
        $record = self::recordWithin($tx, true);
        if ($record['version'] !== $current || $current === (string) \PHP_INT_MAX) {
            throw new Transaction_Failure('pc_coverage_conflict');
        }
        $next = (string) ((int) $current + 1);
        $encoded = self::encode([
            'row_version' => $next,
            'approved_dataset_hash' => $approvedHash,
        ]);
        if (1 !== $tx->execute(
            'UPDATE %i SET option_value = %s WHERE option_name = %s '
                . 'AND BINARY option_value = BINARY %s AND autoload IN (%s, %s)',
            [Ubigeo_Catalog::options(), $encoded, self::OPTION,
                $record['raw'], 'off', 'no']
        )) {
            throw new Transaction_Failure('pc_coverage_conflict');
        }
        return $next;
    }

    public static function version(string $value): string
    {
        $v = Domain_Validation::sqlId($value);
        if (\strlen($v) > \strlen((string) \PHP_INT_MAX)
            || (\strlen($v) === \strlen((string) \PHP_INT_MAX)
                && \strcmp($v, (string) \PHP_INT_MAX) > 0)) {
            throw new Transaction_Failure('pc_coverage_conflict');
        }
        return $v;
    }

    /** @return array{version:string,approved_hash:?string,raw:string} */
    private static function recordWithin(
        Transaction_Context $tx, bool $forUpdate
    ): array {
        $rows = $tx->rows(
            'SELECT option_value, autoload FROM %i WHERE option_name = %s '
                . ($forUpdate ? 'FOR UPDATE' : 'LOCK IN SHARE MODE'),
            [Ubigeo_Catalog::options(), self::OPTION]
        );
        if (1 !== \count($rows)
            || !\in_array((string) ($rows[0]['autoload'] ?? ''), ['off','no'], true)
            || !\is_string($rows[0]['option_value'] ?? null)
            || \strlen($rows[0]['option_value']) > 256) {
            throw new Transaction_Failure('pc_coverage_not_ready');
        }
        $raw = $rows[0]['option_value'];
        try {
            $data = \json_decode($raw, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_coverage_not_ready');
        }
        if (!\is_array($data)
            || \array_keys($data) !== ['row_version','approved_dataset_hash']
            || !\is_string($data['row_version'])
            || (null !== $data['approved_dataset_hash']
                && !\is_string($data['approved_dataset_hash']))) {
            throw new Transaction_Failure('pc_coverage_not_ready');
        }
        $version = self::version($data['row_version']);
        if (null !== $data['approved_dataset_hash']) {
            self::hash($data['approved_dataset_hash']);
        }
        return [
            'version' => $version,
            'approved_hash' => $data['approved_dataset_hash'],
            'raw' => $raw,
        ];
    }

    private static function hash(string $sha): void
    {
        if (!\preg_match('/\A[a-f0-9]{64}\z/D', $sha)) {
            throw new Transaction_Failure('pc_coverage_not_ready');
        }
    }

    private static function encode(array $data): string
    {
        return \json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
