<?php
/**
 * IMP-19 — identidad territorial y procedencia de una versión INEI local.
 *
 * El catálogo no nace del mapa ni del cliente. El gate "ubigeo" exige
 * además una verificación externa del artefacto/licencia/corte real; estas
 * comprobaciones SQL no certifican por sí solas vigencia territorial.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Ubigeo_Catalog
{
    public const PROVENANCE_PREFIX = 'perucommerce_ubigeo_source_v1_';

    public static function table(string $suffix): string
    {
        if (!\in_array($suffix, ['pc_ubigeo_versions','pc_ubigeo','pc_delivery_rates'], true)) {
            throw new Transaction_Failure('pc_ubigeo_invariant_invalid');
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        return Schema::tableNames($wpdb)[$suffix];
    }

    /**
     * Punto de integración P-C: un verificador REAL (externo a esta
     * fase) revalida el hash/artefacto oficial, licencia individual,
     * atribución y vigencia frente a la procedencia activa.
     *
     * No registrar por defecto: la presencia de metadatos SQL o una
     * casilla WordPress no demuestra que el dataset sea vigente/legal.
     */
    public static function registerCurrentDatasetVerifier(callable $verifier): void
    {
        Module_Gates::registerEvidence('ubigeo', static function () use ($verifier): bool {
            try {
                // La lectura de tablas y opción se confirma antes de
                // entregar al verificador. Ningún HTTP dentro de SQL.
                $active = Transaction::run(static fn (Transaction_Context $tx): array =>
                    self::activeWithin($tx)
                );
                return true === $verifier([
                    'dataset_id' => $active['id'],
                    'dataset_sha256' => \strtolower($active['checksum']),
                    'dataset_version' => $active['dataset_version'],
                    'source_url' => $active['source_url'],
                    'published_on' => $active['published_on'],
                    'provenance' => $active['provenance'],
                ]);
            } catch (\Throwable $ignored) {
                return false;
            }
        });
    }

    /** No convertir a int ni aplicar ltrim: 010101 es un ID de texto. */
    public static function code(string $code): string
    {
        if (!\preg_match('/\A[0-9]{6}\z/D', $code)
            || '00' === \substr($code, 0, 2)
            || '00' === \substr($code, 2, 2)
            || '00' === \substr($code, 4, 2)) {
            throw new Transaction_Failure('pc_ubigeo_invalid');
        }
        return $code;
    }

    /** @return array<string,mixed> cabecera de la única versión activa. */
    public static function activeWithin(Transaction_Context $tx): array
    {
        $rows = $tx->rows(
            'SELECT id, source_name, source_url, dataset_version, '
                . 'HEX(dataset_hash) AS checksum, published_on '
                . 'FROM %i WHERE is_current = 1 ORDER BY id DESC LIMIT 2',
            [self::table('pc_ubigeo_versions')]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_ubigeo_not_ready');
        }
        $v = $rows[0];
        $v['id'] = Domain_Validation::sqlId((string) $v['id']);
        if (!\is_string($v['checksum'])
            || !\preg_match('/\A[A-F0-9]{64}\z/D', $v['checksum'])
            || !\is_string($v['dataset_version'])
            || '' === \trim($v['dataset_version'])
            || \mb_strlen($v['dataset_version'], 'UTF-8') > 80) {
            throw new Transaction_Failure('pc_ubigeo_invariant_invalid');
        }
        // El registro de procedencia incluye la licencia del RECURSO, no
        // la inferencia de licencia a partir de una ficha distinta.
        $proof = $tx->rows(
            'SELECT option_value, autoload FROM %i WHERE option_name = %s',
            [self::options(), self::PROVENANCE_PREFIX . \strtolower($v['checksum'])]
        );
        if (1 !== \count($proof) || !\in_array((string) $proof[0]['autoload'], ['off','no'], true)
            || !\is_string($proof[0]['option_value'])) {
            throw new Transaction_Failure('pc_ubigeo_not_ready');
        }
        try {
            $meta = \json_decode($proof[0]['option_value'], true, 16, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_ubigeo_not_ready');
        }
        if (!\is_array($meta)
            || ($meta['sha256'] ?? null) !== \strtolower($v['checksum'])
            || ($meta['source_url'] ?? null) !== $v['source_url']
            || !\is_string($meta['license_reference'] ?? null)
            || '' === $meta['license_reference']
            || !\is_string($meta['release_review_reference'] ?? null)
            || '' === $meta['release_review_reference']) {
            throw new Transaction_Failure('pc_ubigeo_not_ready');
        }
        // No hay bandera "licensed=true" tomada de un formulario. El
        // proveedor del gate debe volver a verificar esos referentes.
        $v['provenance'] = $meta;
        return $v;
    }

    /** Requiere versión INEI activa y distrito válido de la misma versión. */
    public static function districtWithin(
        Transaction_Context $tx, string $code, string $versionId
    ): array {
        $code = self::code($code);
        $rows = $tx->rows(
            'SELECT code, department_name, province_name, district_name '
                . 'FROM %i WHERE version_id = %s AND code = %s AND valid = 1',
            [self::table('pc_ubigeo'), Domain_Validation::sqlId($versionId), $code]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_ubigeo_invalid');
        }
        return $rows[0];
    }

    public static function options(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        return $wpdb->options;
    }

    /** Archivo de entrada normalizado, SIN interpolación SQL ni float. */
    public static function names(array $row): array
    {
        $out = [];
        foreach (['department','province','district'] as $field) {
            $name = $row[$field] ?? null;
            if (!\is_string($name) || '' === \trim($name)
                || !\mb_check_encoding($name, 'UTF-8')
                || \mb_strlen($name, 'UTF-8') > 120
                || \preg_match('/[\x00-\x1F\x7F<>]/', $name)) {
                throw new Transaction_Failure('pc_ubigeo_invalid');
            }
            $out[$field] = \trim($name);
        }
        return $out;
    }
}
