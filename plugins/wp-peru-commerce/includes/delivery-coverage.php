<?php
/**
 * IMP-19 — R8-16/51/52, cobertura distrital y tarifa PEN única por pedido.
 * Los cambios afectan solo pc_delivery_rates; los snapshots de pedidos y
 * revisiones de dirección existentes no se recalculan ni se sobrescriben.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Delivery_Coverage
{
    /** @return array<string,mixed> UbigeoPage */
    public static function publicPage(array $query): array
    {
        self::readiness('read');
        $page = Rest_Pagination::window($query);
        $q = self::search($query['q'] ?? '');
        $advertise = self::publicOffersDelivery();

        return Transaction::run(static function (Transaction_Context $tx) use (
            $page, $q, $advertise
        ): array {
            Coverage_Version::readWithin($tx);
            $dataset = Ubigeo_Catalog::activeWithin($tx);
            $advertise = $advertise && Coverage_Version::reviewedWithin(
                $tx, \strtolower($dataset['checksum'])
            );
            $args = [Ubigeo_Catalog::table('pc_ubigeo'),
                Ubigeo_Catalog::table('pc_delivery_rates'), $dataset['id']];
            $sql = 'SELECT u.code, u.department_name, u.province_name, '
                . 'u.district_name, r.enabled, r.fee_cents '
                . 'FROM %i u LEFT JOIN %i r ON r.ubigeo_code = u.code '
                . 'WHERE u.version_id = %s AND u.valid = 1 ';
            // INSTR usa el texto como literal, no interpreta % o _ como
            // wildcard ni cambia un código de seis caracteres a número.
            if ('' !== $q) {
                $sql .= 'AND (INSTR(u.code, %s) > 0 '
                    . 'OR INSTR(LOWER(u.department_name), LOWER(%s)) > 0 '
                    . 'OR INSTR(LOWER(u.province_name), LOWER(%s)) > 0 '
                    . 'OR INSTR(LOWER(u.district_name), LOWER(%s)) > 0) ';
                \array_push($args, $q, $q, $q, $q);
            }
            $sql .= 'ORDER BY u.code ASC LIMIT %d OFFSET %d LOCK IN SHARE MODE';
            \array_push($args, $page['fetch'], $page['offset']);
            $rows = $tx->rows($sql, $args);
            $items = [];
            foreach ($rows as $row) {
                $code = Ubigeo_Catalog::code((string) $row['code']);
                $active = $advertise && '1' === (string) $row['enabled'];
                $item = [
                    'code' => $code,
                    'department' => (string) $row['department_name'],
                    'province' => (string) $row['province_name'],
                    'district' => (string) $row['district_name'],
                    'delivery_available' => $active,
                ];
                if ($active) {
                    if (null === $row['fee_cents']) {
                        throw new Transaction_Failure('pc_coverage_invariant_invalid');
                    }
                    $item['delivery_fee_cents'] = Money::toApi(
                        Money::fromApi((string) $row['fee_cents'])
                    );
                }
                $items[] = $item;
            }
            $response = Rest_Pagination::envelope($items, $page);
            $response['dataset_version'] = (string) $dataset['dataset_version'];
            return $response;
        });
    }

    /** R8-51: versiones y reglas solo para A. */
    public static function adminView(): array
    {
        self::administrator();
        self::readiness('read');
        return Transaction::run(static function (Transaction_Context $tx): array {
            $version = Coverage_Version::readWithin($tx);
            $dataset = Ubigeo_Catalog::activeWithin($tx);
            return self::viewWithin($tx, $version, $dataset);
        });
    }

    /** @param array<string,mixed> $body
     * @return array<string,mixed> CoverageView
     */
    public static function patch(array $body): array
    {
        self::administrator();
        self::readiness('write');
        $expected = Domain_Validation::sqlId($body['expected_version']);
        $key = $body['idempotency_key'];
        $entries = $body['entries'] ?? [];
        if (!\is_array($entries) || !\array_is_list($entries) || \count($entries) > 2500) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'entries');
        }
        $normalized = [];
        foreach ($entries as $entry) {
            if (!\is_array($entry) || !\is_string($entry['ubigeo_code'] ?? null)
                || !\is_bool($entry['enabled'] ?? null)
                || !\is_string($entry['fee_cents'] ?? null)) {
                throw new Rest_Failure('pc_field_invalid', 422, false, 'entries');
            }
            try {
                $code = Ubigeo_Catalog::code($entry['ubigeo_code']);
                $fee = Money::toApi(Money::fromApi($entry['fee_cents']));
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_field_invalid', 422, false, 'entries');
            }
            if (isset($normalized[$code])) {
                throw new Rest_Failure('pc_field_invalid', 422, false, 'entries');
            }
            $normalized[$code] = [
                'ubigeo_code' => $code,
                'enabled' => $entry['enabled'],
                'fee_cents' => $fee,
            ];
        }
        \ksort($normalized, \SORT_STRING);
        $scope = [
            'actor_kind' => 'staff',
            'actor_ref' => (string) \get_current_user_id(),
            'resource_kind' => 'coverage',
            'resource_id' => '1',
            'purpose' => 'coverage_patch',
        ];
        $actor = Event_Actor::staff(['perucommerce_settings_manage']);
        // El DTO permite hasta 2500 tarifas (~200 KiB), mientras el
        // registro de idempotencia limita su payload canónico a 64 KiB.
        // El hash SHA-256 del conjunto completo mantiene la vinculación
        // estricta de la clave sin persistir ni truncar cambios.
        $payload = [
            'entries_sha256' => \hash('sha256',
                \json_encode(\array_values($normalized), \JSON_THROW_ON_ERROR)),
            'entries_count' => \count($normalized),
            'expected_version' => $expected,
        ];

        return Transaction::run(static function (Transaction_Context $tx) use (
            $scope, $actor, $key, $payload, $expected, $normalized
        ): array {
            $claim = Idempotency::claimWithin(
                $tx, $scope, 'coverage_patch', $key, $payload
            );
            if ('replay' === $claim['status']) {
                $current = Coverage_Version::readWithin($tx);
                if ('coverage' !== $claim['entity_type']
                    || $claim['entity_id'] !== $current) {
                    throw new Transaction_Failure('pc_coverage_conflict');
                }
                return self::viewWithin(
                    $tx, $current, Ubigeo_Catalog::activeWithin($tx)
                );
            }
            if ('new' !== $claim['status']) {
                throw new Transaction_Failure('pc_coverage_conflict');
            }
            $version = Coverage_Version::ensureWithin($tx);
            if ($version !== $expected) {
                throw new Transaction_Failure('pc_coverage_conflict');
            }
            $dataset = Ubigeo_Catalog::activeWithin($tx);
            self::requireCodesWithin($tx, \array_keys($normalized), $dataset['id']);
            $old = self::viewWithin($tx, $version, $dataset);

            $table = Ubigeo_Catalog::table('pc_delivery_rates');
            // Hasta 2500 distritos caben en 25 sentencias compactas en
            // vez de 2500 round-trips bajo un mismo COMMIT/lock global.
            // VALUES() conserva compatibilidad MySQL/MariaDB del contrato
            // actual y no reescribe tarifas de distritos omitidos.
            $actorId = (string) \get_current_user_id();
            foreach (\array_chunk(\array_values($normalized), 100) as $batch) {
                $markers = \implode(',', \array_fill(
                    0, \count($batch), '(%s, %d, %s, 1, %s, UTC_TIMESTAMP(6))'
                ));
                $args = [$table];
                foreach ($batch as $item) {
                    \array_push($args, $item['ubigeo_code'],
                        $item['enabled'] ? 1 : 0, $item['fee_cents'], $actorId);
                }
                $changed = $tx->execute(
                    'INSERT INTO %i (ubigeo_code, enabled, fee_cents, row_version, '
                        . 'updated_by, updated_at) VALUES ' . $markers
                        . ' ON DUPLICATE KEY UPDATE '
                        . 'enabled = VALUES(enabled), fee_cents = VALUES(fee_cents), '
                        . 'row_version = row_version + 1, updated_by = VALUES(updated_by), '
                        . 'updated_at = UTC_TIMESTAMP(6)',
                    $args
                );
                if ($changed < \count($batch)) {
                    throw new Transaction_Failure('pc_coverage_invariant_invalid');
                }
            }
            // Importar otro dataset revoca la aprobación previa.
            // Solo un PATCH con al menos una tarifa explícita puede
            // aprobar el conjunto INEI. entries=[] es un no-op seguro,
            // NO una aceptación implícita de nueva jurisdicción.
            $newVersion = [] === $normalized ? $version
                : Coverage_Version::incrementWithin(
                    $tx, $version, \strtolower($dataset['checksum'])
                );
            Event_Journal::auditWithin(
                $tx, $actor, $claim['operation_id'], 'coverage', '1',
                [] === $normalized
                    ? 'coverage_patch_noop' : 'coverage_rates_changed',
                'delivery_settings',
                \hash('sha256', \json_encode($old, \JSON_THROW_ON_ERROR)),
                \hash('sha256', \json_encode([
                    'version' => $newVersion, 'changes' => \array_values($normalized),
                    'dataset' => $dataset['dataset_version'],
                ], \JSON_THROW_ON_ERROR))
            );
            Idempotency::completeLocalWithin(
                $tx, $claim['operation_id'], 'coverage', $newVersion, 200
            );
            return self::viewWithin($tx, $newVersion, $dataset);
        }, true);
    }

    /**
     * Tarifa autoritativa bajo la MISMA transacción de la cotización/pedido.
     * El llamante congela fee/dataset_version/coverage_version en el quote
     * y vuelve a comprobarlos antes de enviar a MP o aceptar efectivo.
     * Nunca aplicar tarifa más de una vez por pedido.
     *
     * @return array{ubigeo_code:string,fee_cents:string,dataset_version:string,coverage_version:string}
     */
    public static function feeWithin(Transaction_Context $tx, string $ubigeo): array
    {
        $version = Coverage_Version::readWithin($tx);
        $dataset = Ubigeo_Catalog::activeWithin($tx);
        if (!Coverage_Version::reviewedWithin(
            $tx, \strtolower($dataset['checksum'])
        )) {
            throw new Transaction_Failure('pc_delivery_not_ready');
        }
        Ubigeo_Catalog::districtWithin($tx, $ubigeo, $dataset['id']);
        $rows = $tx->rows(
            'SELECT fee_cents, enabled FROM %i '
                . 'WHERE ubigeo_code = %s LOCK IN SHARE MODE',
            [Ubigeo_Catalog::table('pc_delivery_rates'), $ubigeo]
        );
        if (1 !== \count($rows) || '1' !== (string) $rows[0]['enabled']) {
            throw new Transaction_Failure('pc_delivery_not_covered');
        }
        $fee = Money::toApi(Money::fromApi((string) $rows[0]['fee_cents']));
        return [
            'ubigeo_code' => $ubigeo,
            'fee_cents' => $fee,
            'dataset_version' => (string) $dataset['dataset_version'],
            'coverage_version' => $version,
        ];
    }

    /** Pago/recojo no llaman proveedor de mapas ni importan datasets. */
    public static function feeForCheckout(
        string $mode, ?string $code, ?string $address, bool $hasPhysicalGoods
    ): array {
        if (\in_array($mode, ['pickup','none'], true)) {
            return ['fee_cents' => '0', 'mode' => $mode];
        }
        if ('delivery' !== $mode || !$hasPhysicalGoods
            || !\is_string($code) || !\is_string($address)
            || '' === \trim($address) || !\mb_check_encoding($address, 'UTF-8')
            || \mb_strlen($address, 'UTF-8') > 500
            || \preg_match('/[\x00-\x1F\x7F]/', $address)) {
            throw new Rest_Failure('pc_field_invalid', 422);
        }
        $code = Ubigeo_Catalog::code($code);
        self::readiness('write');
        if (!self::publicOffersDelivery()) {
            throw new Rest_Failure('pc_module_not_ready', 423, true);
        }
        Bootstrap::loadSettings();
        return Transaction::run(static function (Transaction_Context $tx) use ($code): array {
            $s = Settings_Store::readWithin($tx)['settings'];
            if (true !== $s['delivery_enabled']) {
                throw new Transaction_Failure('pc_delivery_not_ready');
            }
            return self::feeWithin($tx, $code) + ['mode' => 'delivery'];
        });
    }

    /**
     * Señal para R8-01/checkout: solo existe cobertura autorizable si la
     * versión territorial real fue revisada por A tras la última importación.
     * No sustituye comprobar zona/tarifa concreta en la cotización final.
     */
    public static function activeReviewReady(): bool
    {
        if (!Module_Gates::allows('delivery', 'read')) {
            return false;
        }
        try {
            return Transaction::run(static function (Transaction_Context $tx): bool {
                Coverage_Version::readWithin($tx);
                $dataset = Ubigeo_Catalog::activeWithin($tx);
                return Coverage_Version::reviewedWithin(
                    $tx, \strtolower($dataset['checksum'])
                );
            });
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    private static function viewWithin(
        Transaction_Context $tx, string $version, array $dataset
    ): array {
        $rows = $tx->rows(
            'SELECT r.ubigeo_code, r.enabled, r.fee_cents FROM %i r '
                . 'INNER JOIN %i u ON u.code = r.ubigeo_code AND u.version_id = %s '
                . 'AND u.valid = 1 ORDER BY r.ubigeo_code LIMIT 2501',
            [Ubigeo_Catalog::table('pc_delivery_rates'),
                Ubigeo_Catalog::table('pc_ubigeo'), $dataset['id']]
        );
        if (\count($rows) > 2500) {
            throw new Transaction_Failure('pc_coverage_invariant_invalid');
        }
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'ubigeo_code' => Ubigeo_Catalog::code((string) $row['ubigeo_code']),
                'enabled' => '1' === (string) $row['enabled'],
                'fee_cents' => Money::toApi(Money::fromApi((string) $row['fee_cents'])),
            ];
        }
        return [
            'items' => $items, 'row_version' => $version,
            'dataset_version' => (string) $dataset['dataset_version'],
        ];
    }

    /** @param list<string> $codes */
    private static function requireCodesWithin(
        Transaction_Context $tx, array $codes, string $datasetId
    ): void {
        foreach (\array_chunk($codes, 250) as $batch) {
            $marks = \implode(',', \array_fill(0, \count($batch), '%s'));
            $found = $tx->rows(
                'SELECT code FROM %i WHERE version_id = %s AND valid = 1 '
                    . 'AND code IN (' . $marks . ')',
                \array_merge([Ubigeo_Catalog::table('pc_ubigeo'), $datasetId], $batch)
            );
            if (\count($found) !== \count($batch)) {
                throw new Transaction_Failure('pc_ubigeo_invalid');
            }
        }
    }

    private static function search(mixed $query): string
    {
        if (!\is_string($query) || !\mb_check_encoding($query, 'UTF-8')
            || \mb_strlen($query, 'UTF-8') > 120
            || \preg_match('/[\x00-\x1F\x7F]/', $query)) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'q');
        }
        return \trim($query);
    }

    private static function administrator(): void
    {
        if ('A' !== Access_Roles::currentRole()
            || !\current_user_can('perucommerce_settings_manage')) {
            throw new Rest_Failure('pc_access_denied', 403);
        }
    }

    private static function readiness(string $action): void
    {
        if (!Module_Gates::allows('delivery', $action)) {
            throw new Rest_Failure('pc_module_not_ready', 423, true);
        }
    }

    private static function publicOffersDelivery(): bool
    {
        if (!Module_Gates::allows('delivery', 'write')
            || !Rest_Router::isOperationReady('R8-05')
            || !Rest_Router::isOperationReady('R8-16')
            || (!Rest_Router::isOperationReady('R8-06')
                && !Rest_Router::isOperationReady('R8-07'))) {
            return false;
        }
        try {
            Bootstrap::loadBootstrapApi();
            $bootstrap = Bootstrap_Api_Service::publicResponse();
            return true === ($bootstrap['supports_delivery'] ?? false);
        } catch (\Throwable $ignored) {
            return false;
        }
    }
}
