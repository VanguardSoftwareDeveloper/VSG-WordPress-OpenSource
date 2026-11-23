<?php
/**
 * IMP-19 — puerto de importación de artefactos INEI verificados.
 *
 * NO se incluye un XLSX/ZIP distrital ni se infiere su licencia por el
 * nombre: un adaptador de confianza futuro revisa el archivo EXACTO,
 * derechos de redistribución, actualidad y jerarquía. Sin adaptador, el
 * importador deniega; jamás simula un catálogo 2026 disponible.
 *
 * No hay ruta REST adicional de importación en V1.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Ubigeo_Importer
{
    private const MIN_DISTRICTS = 1000;
    private const MAX_DISTRICTS = 3000;
    private const MAX_SOURCE_BYTES = 12_000_000;

    /** @var callable|null Revisor de bytes oficiales, licencia, vigencia y parseo. */
    private static $verifiedSourceReader = null;

    /**
     * Integración privada: el revisor debe devolver registros provenientes
     * exclusivamente del mismo artefacto recibido, junto con referencias
     * a evidencia verificable de derechos/fecha/corte. No aceptar el flag
     * "verified" enviado por UI ni compartir este método vía endpoint.
     */
    public static function registerVerifiedSourceReader(callable $reader): void
    {
        if (null !== self::$verifiedSourceReader) {
            throw new \LogicException('pc_ubigeo_reader_duplicate');
        }
        self::$verifiedSourceReader = $reader;
    }

    /**
     * Puerto interno que requiere un revisor REAL y privilegio A.
     * @param array<string,mixed> $source Descriptor revisado, no payload REST
     * @return array{dataset_id:string,dataset_version:string,coverage_version:string}
     */
    public static function import(string $originalBytes, array $source, string $idempotencyKey): array
    {
        if ('A' !== Access_Roles::currentRole()
            || !\current_user_can('perucommerce_settings_manage')
            || !Module_Gates::allows('inventory','write')
            || !Coverage_Version::optionsTransactional()
            || null === self::$verifiedSourceReader) {
            throw new Transaction_Failure('pc_ubigeo_not_ready');
        }
        if ('' === $originalBytes || \strlen($originalBytes) > self::MAX_SOURCE_BYTES
            || !\is_string($source['source_url'] ?? null)
            || !\is_string($source['dataset_version'] ?? null)
            || !\is_string($source['source_name'] ?? null)
            || 'INEI' !== $source['source_name']
            || !\preg_match(
                '~\Ahttps://www\.datosabiertos\.gob\.pe/dataset/[^?#\s]{1,430}\z~D',
                $source['source_url']
            )
            || !\mb_check_encoding($source['dataset_version'], 'UTF-8')
            || \mb_strlen($source['dataset_version'], 'UTF-8') < 1
            || \mb_strlen($source['dataset_version'], 'UTF-8') > 80
            || \preg_match('/[\x00-\x1F\x7F]/', $source['dataset_version'])
            || \strlen($idempotencyKey) < 16 || \strlen($idempotencyKey) > 128) {
            throw new Transaction_Failure('pc_ubigeo_invalid');
        }
        $published = $source['published_on'] ?? null;
        if (null !== $published) {
            try {
                if (!\is_string($published)) {
                    throw new \UnexpectedValueException();
                }
                Business_Time::localDate($published);
            } catch (\Throwable $ignored) {
                throw new Transaction_Failure('pc_ubigeo_invalid');
            }
        }
        $source['published_on'] = $published;
        $checksum = \hash('sha256', $originalBytes);
        // Este callback es la única frontera capaz de comprobar la
        // equivalencia XLSX/ZIP original → filas, y procedencia/licencia.
        // Si falta o falla, no se acepta el archivo ni se habilita gate.
        try {
            $review = (self::$verifiedSourceReader)($originalBytes, $source);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_ubigeo_not_ready');
        }
        if (!\is_array($review)
            || !\is_array($review['districts'] ?? null)
            || !\is_array($review['evidence'] ?? null)) {
            throw new Transaction_Failure('pc_ubigeo_not_ready');
        }
        $proof = $review['evidence'];
        if (($proof['sha256'] ?? null) !== $checksum
            || ($proof['source_url'] ?? null) !== $source['source_url']
            || !\is_string($proof['license_reference'] ?? null)
            || '' === \trim($proof['license_reference'])
            || !\is_string($proof['release_review_reference'] ?? null)
            || '' === \trim($proof['release_review_reference'])
            || !\is_string($proof['license_id'] ?? null)
            || '' === \trim($proof['license_id'])
            || !\is_string($proof['attribution'] ?? null)
            || '' === \trim($proof['attribution'])
            || \strlen($proof['attribution']) > 1200
            || \strlen($proof['license_reference']) > 500
            || \strlen($proof['release_review_reference']) > 500
            || \strlen($proof['license_id']) > 100
            || !\mb_check_encoding($proof['attribution'], 'UTF-8')
            || \strlen(\json_encode($proof, \JSON_THROW_ON_ERROR)) > 6000) {
            throw new Transaction_Failure('pc_ubigeo_not_ready');
        }
        $records = self::normalize($review['districts']);
        // No guardar el archivo ni nombres de personas o secretos en
        // snapshots comerciales. Referencias sirven para auditoría/release.
        $proof = \array_intersect_key($proof, \array_flip([
            'sha256','source_url','license_reference','license_id',
            'release_review_reference','attribution',
        ]));
        $payload = [
            'dataset_hash' => $checksum, 'version' => $source['dataset_version'],
            'url' => $source['source_url'],
            'record_count' => \count($records),
            'license_review' => $proof['license_reference'],
            'release_review' => $proof['release_review_reference'],
        ];
        $scope = [
            'actor_kind' => 'staff',
            'actor_ref' => (string) \get_current_user_id(),
            'resource_kind' => 'ubigeo',
            'resource_id' => '1',
            'purpose' => 'ubigeo_import',
        ];
        $actor = Event_Actor::staff(['perucommerce_settings_manage']);
        // Ya validado: el contenido original nunca pasa a SQL ni se
        // registra en auditoría o request_hash, solo digest/metadata.
        return Transaction::run(static function (Transaction_Context $tx) use (
            $source, $proof, $records, $scope, $payload, $idempotencyKey,
            $actor, $checksum
        ): array {
            $claim = Idempotency::claimWithin(
                $tx, $scope, 'ubigeo_import', $idempotencyKey, $payload
            );
            if ('replay' === $claim['status']) {
                if ('ubigeo' !== $claim['entity_type'] || !\is_string($claim['entity_id'])) {
                    throw new Transaction_Failure('pc_ubigeo_conflict');
                }
                // El mismo orden de locks que el importador nuevo y que
                // las consultas públicas: cobertura -> versión INEI.
                $currentVersion = Coverage_Version::readWithin($tx);
                $current = Ubigeo_Catalog::activeWithin($tx);
                if ($current['id'] !== $claim['entity_id']
                    || \strtolower($current['checksum']) !== $checksum) {
                    throw new Transaction_Failure('pc_ubigeo_conflict');
                }
                return [
                    'dataset_id' => $current['id'],
                    'dataset_version' => $current['dataset_version'],
                    'coverage_version' => $currentVersion,
                ];
            }
            if ('new' !== $claim['status']) {
                throw new Transaction_Failure('pc_ubigeo_conflict');
            }
            $coverageVersion = Coverage_Version::ensureWithin($tx);
            $old = $tx->rows(
                'SELECT id, HEX(dataset_hash) AS checksum '
                    . 'FROM %i WHERE is_current = 1 LIMIT 2 FOR UPDATE',
                [Ubigeo_Catalog::table('pc_ubigeo_versions')]
            );
            if (\count($old) > 1) {
                throw new Transaction_Failure('pc_ubigeo_invariant_invalid');
            }
            if ([] !== $old && \strtolower((string) $old[0]['checksum']) === $checksum) {
                throw new Transaction_Failure('pc_ubigeo_conflict');
            }
            if ([] !== $tx->rows(
                'SELECT id FROM %i WHERE dataset_hash = UNHEX(%s) LIMIT 1',
                [Ubigeo_Catalog::table('pc_ubigeo_versions'), $checksum]
            )) {
                // No reactivar una versión histórica por efecto colateral.
                throw new Transaction_Failure('pc_ubigeo_conflict');
            }
            if (1 !== $tx->execute(
                'INSERT INTO %i (source_name, source_url, dataset_version, dataset_hash, '
                    . 'published_on, imported_at, is_current) '
                    . 'VALUES (%s, %s, %s, UNHEX(%s), NULLIF(%s,%s), UTC_TIMESTAMP(6), 0)',
                [Ubigeo_Catalog::table('pc_ubigeo_versions'),
                    $source['source_name'], $source['source_url'],
                    $source['dataset_version'], $checksum,
                    $source['published_on'] ?? '', '']
            )) {
                throw new Transaction_Failure('pc_ubigeo_invariant_invalid');
            }
            $last = $tx->rows('SELECT LAST_INSERT_ID() AS id');
            $datasetId = Domain_Validation::sqlId((string) ($last[0]['id'] ?? ''));
            foreach (\array_chunk($records, 100) as $batch) {
                $values = [];
                $args = [Ubigeo_Catalog::table('pc_ubigeo')];
                foreach ($batch as $row) {
                    $values[] = '(%s, %s, %s, %s, %s, 1)';
                    \array_push($args, $datasetId, $row['code'],
                        $row['department'], $row['province'], $row['district']);
                }
                if (\count($batch) !== $tx->execute(
                    'INSERT INTO %i (version_id, code, department_name, province_name, '
                        . 'district_name, valid) VALUES ' . \implode(',', $values),
                    $args
                )) {
                    throw new Transaction_Failure('pc_ubigeo_invariant_invalid');
                }
            }
            $encoded = \json_encode($proof, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
            $option = Ubigeo_Catalog::PROVENANCE_PREFIX . $checksum;
            if (1 !== $tx->execute(
                'INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)',
                [Ubigeo_Catalog::options(), $option, $encoded, 'off']
            )) {
                throw new Transaction_Failure('pc_ubigeo_invariant_invalid');
            }
            if ([] !== $old && 1 !== $tx->execute(
                'UPDATE %i SET is_current = 0 WHERE id = %s AND is_current = 1',
                [Ubigeo_Catalog::table('pc_ubigeo_versions'), $old[0]['id']]
            )) {
                throw new Transaction_Failure('pc_ubigeo_conflict');
            }
            if (1 !== $tx->execute(
                'UPDATE %i SET is_current = 1 WHERE id = %s AND is_current = 0',
                [Ubigeo_Catalog::table('pc_ubigeo_versions'), $datasetId]
            )) {
                throw new Transaction_Failure('pc_ubigeo_conflict');
            }
            // La cobertura conserva sus reglas previas, pero incrementa
            // revisión global para forzar recotización/revalidación de
            // códigos que pudieron cambiar de estado oficial.
            $newVersion = Coverage_Version::incrementWithin($tx, $coverageVersion);
            Event_Journal::auditWithin(
                $tx, $actor, $claim['operation_id'], 'ubigeo',
                $datasetId, 'ubigeo_version_imported', 'dataset_reviewed',
                [] === $old ? null : \strtolower((string) $old[0]['checksum']),
                $checksum
            );
            Idempotency::completeLocalWithin(
                $tx, $claim['operation_id'], 'ubigeo', $datasetId, 200
            );
            return [
                'dataset_id' => $datasetId,
                'dataset_version' => $source['dataset_version'],
                'coverage_version' => $newVersion,
            ];
        }, true);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{code:string,department:string,province:string,district:string}>
     */
    private static function normalize(array $rows): array
    {
        if (!\array_is_list($rows)
            || \count($rows) < self::MIN_DISTRICTS
            || \count($rows) > self::MAX_DISTRICTS) {
            throw new Transaction_Failure('pc_ubigeo_invalid');
        }
        $out = [];
        $departments = [];
        $provinces = [];
        foreach ($rows as $row) {
            if (!\is_array($row) || !\is_string($row['code'] ?? null)) {
                throw new Transaction_Failure('pc_ubigeo_invalid');
            }
            $code = Ubigeo_Catalog::code($row['code']);
            if (isset($out[$code])) {
                throw new Transaction_Failure('pc_ubigeo_invalid');
            }
            $names = Ubigeo_Catalog::names($row);
            $dept = \substr($code, 0, 2);
            $prov = \substr($code, 0, 4);
            if (isset($departments[$dept]) && $departments[$dept] !== $names['department']
                || isset($provinces[$prov]) && $provinces[$prov] !== $names['province']) {
                throw new Transaction_Failure('pc_ubigeo_invalid');
            }
            $departments[$dept] = $names['department'];
            $provinces[$prov] = $names['province'];
            $out[$code] = ['code' => $code] + $names;
        }
        \ksort($out, \SORT_STRING);
        return \array_values($out);
    }
}
