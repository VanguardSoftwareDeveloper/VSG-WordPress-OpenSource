<?php
/**
 * IMP-22 — vocabulario E-01 y validación de snapshots inmutables.
 *
 * Los DTO públicos se construyen desde columnas históricas y listas
 * permitidas, nunca desde el catálogo/precio/settings ACTUALES ni JSON crudo
 * enviado por un comprador.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Domain
{
    public const STATES = [
        'PENDIENTE_PAGO', 'CONFIRMADO', 'FINALIZADO', 'CANCELADO', 'VENCIDO',
    ];

    public const LINE_STATES = [
        'physical' => ['PENDIENTE', 'PREPARANDO', 'LISTO', 'DESPACHADO', 'ENTREGADO', 'CANCELADO'],
        'digital' => ['BLOQUEADO', 'HABILITADO', 'CANCELADO', 'REVOCADO'],
        'service' => ['PENDIENTE_CONFIRMACION', 'CONFIRMADA', 'EXPIRADA', 'REALIZADA', 'CANCELADA', 'NO_ASISTIO'],
    ];

    private const TABLES = [
        'pc_orders', 'pc_order_lines', 'pc_stock_commitments',
        'pc_policy_versions', 'pc_payment_attempts', 'pc_payment_events',
        'pc_order_adjustments',
        'pc_appointments', 'pc_delivery_revisions', 'pc_cases', 'pc_case_events',
        'pc_download_rights', 'pc_external_documents', 'pc_audit_log',
        'pc_idempotency', 'pc_sellables', 'pc_digital_assignments',
        'pc_digital_files',
    ];

    public static function table(string $suffix): string
    {
        if (!\in_array($suffix, self::TABLES, true)) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        return Schema::tableNames($wpdb)[$suffix];
    }

    public static function state(string $state): string
    {
        if (!\in_array($state, self::STATES, true)) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        return $state;
    }

    public static function lineState(string $kind, string $state): string
    {
        if (!isset(self::LINE_STATES[$kind])
            || !\in_array($state, self::LINE_STATES[$kind], true)) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        return $state;
    }

    /** El SQL se almacena UTC DATETIME(6), nunca zona de PHP/WordPress. */
    public static function utc(string $database): string
    {
        return Business_Time::toApi(Business_Time::fromDatabase($database));
    }

    /** Canonizar importes sin floats. */
    public static function money(string $value): string
    {
        try {
            return Money::toApi(Money::fromApi($value));
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
    }

    /** SQL ID canónico, con rechazo seguro de cero/overflow. */
    public static function id(string $value): string
    {
        try {
            return Domain_Validation::sqlId($value);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
    }

    /** El nombre original no es una representación válida del catálogo actual. */
    public static function catalogSnapshot(string $raw, string $kind): array
    {
        // IMP-24: hasta 100 slots digitales con hash/versiones pueden
        // superar 12 KiB; el contrato físico es LONGTEXT, pero se acota
        // a 40 KiB. No extender los límites de customer/PII/servicios.
        $s = self::decode($raw, 'digital' === $kind ? 40000 : 12000);
        if (!\is_string($s['title'] ?? null)
            || '' === \trim($s['title'])
            || \mb_strlen($s['title'], 'UTF-8') > 200
            || ($s['kind'] ?? null) !== $kind) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        if ('service' === $kind) {
            foreach (['service_duration_minutes','service_buffer_before_minutes',
                'service_buffer_after_minutes'] as $field) {
                $value = $s[$field] ?? null;
                if (!\is_int($value) || $value < 0 || $value > 1440
                    || ('service_duration_minutes' === $field && $value < 1)) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
            }
        }
        if ('digital' === $kind) {
            // IMP-24: una variante puede incluir varios slots de archivos
            // vendidos. El snapshot nuevo congela TODOS los file_id,
            // versiones de asignación y hashes (nunca rutas o tokens).
            // Se preserva lectura del snapshot singular de IMP-22.
            if (\array_key_exists('files',$s)) {
                if (isset($s['file_id']) || isset($s['slot_key'])
                    || !\is_array($s['files'])
                    || !\array_is_list($s['files']) || [] === $s['files']
                    || \count($s['files']) > 100) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
                $slots = [];
                $fileIds = [];
                foreach ($s['files'] as $file) {
                    if (!\is_array($file) || \count($file) !== 4
                        || \array_diff(\array_keys($file),
                            ['slot_key','file_id','row_version','sha256']) !== []
                        || !\is_string($file['slot_key'] ?? null)
                        || !\is_string($file['file_id'] ?? null)
                        || !\is_string($file['row_version'] ?? null)
                        || !\is_string($file['sha256'] ?? null)
                        || !\preg_match('/\A[a-z0-9][a-z0-9_-]{0,99}\z/D',
                            $file['slot_key'])
                        || !\preg_match('/\A[a-f0-9]{64}\z/D',$file['sha256'])) {
                        throw new Transaction_Failure('pc_orders_invariant_invalid');
                    }
                    self::id($file['file_id']);
                    self::id($file['row_version']);
                    if (isset($slots[$file['slot_key']])
                        || isset($fileIds[$file['file_id']])) {
                        throw new Transaction_Failure('pc_orders_invariant_invalid');
                    }
                    $slots[$file['slot_key']] = true;
                    $fileIds[$file['file_id']] = true;
                }
            } else {
                if (!\is_string($s['file_id'] ?? null)
                    || !\is_string($s['slot_key'] ?? null)) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
                self::id($s['file_id']);
                if (!\preg_match('/\A[a-z0-9][a-z0-9_-]{0,99}\z/D',
                    $s['slot_key'])) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
            }
        }
        return $s;
    }

    /** Snapshot de contacto limitado: no credenciales/IDs fiscales ni tracking. */
    public static function customerSnapshot(string $raw): array
    {
        $s = self::decode($raw, 4096);
        $allowed = ['recipient', 'phone', 'email'];
        foreach ($s as $key => $value) {
            if (!\in_array($key, $allowed, true)
                || !\is_string($value) || !\mb_check_encoding($value, 'UTF-8')) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
        }
        if (isset($s['recipient'])
            && ('' === \trim($s['recipient']) || \mb_strlen($s['recipient'], 'UTF-8') > 160)) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        return $s;
    }

    /**
     * El original solo cambia mediante revisiones inmutables aprobadas.
     * Nunca deducir código/tarifa desde el pin o geocodificación.
     */
    public static function deliverySnapshot(?string $raw): ?array
    {
        if (null === $raw) {
            return null;
        }
        $s = self::decode($raw, 3072);
        if (!\in_array($s['mode'] ?? null, ['pickup','delivery','none'], true)) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        if ('delivery' === $s['mode']) {
            if (!\is_string($s['ubigeo_code'] ?? null)
                || !\preg_match('/\A[0-9]{6}\z/D', $s['ubigeo_code'])
                || !\is_string($s['address'] ?? null)
                || '' === \trim($s['address'])
                || \mb_strlen($s['address'], 'UTF-8') > 500) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
        }
        return $s;
    }

    /** JSON opaco de BD: no devolverlo íntegro. */
    public static function decode(string $raw, int $limit = 12000): array
    {
        if (\strlen($raw) < 2 || \strlen($raw) > $limit) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        try {
            $decoded = \json_decode($raw, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        if (!\is_array($decoded) || \array_is_list($decoded)) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        return $decoded;
    }

    public static function quantity(string $units): int
    {
        if (!\preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $units)
            || \strlen($units) > 6 || (int) $units > 100000) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        return (int) $units;
    }

    /**
     * Extraer contadores de compromiso sin inventar cero cuando se perdió
     * una fila que debía existir después de la confirmación.
     */
    public static function physicalQuantities(array $order, array $line, ?array $commitment): array
    {
        if (null === $commitment) {
            $preconfirmation = \in_array($order['status'], ['PENDIENTE_PAGO','VENCIDO'], true)
                || ('CANCELADO' === $order['status'] && null === $order['confirmed_at']);
            if (!$preconfirmation) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            return ['committed_units'=>0,'released_units'=>0,
                'stock_out_units'=>0,'pending_stock_out_units'=>0];
        }
        if ((string) $commitment['sellable_id'] !== (string) $line['sellable_id']) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        $count = self::quantity((string) $line['quantity']);
        $committed = self::quantity((string) $commitment['committed_units']);
        $out = self::quantity((string) $commitment['fulfilled_units']);
        $released = self::quantity((string) $commitment['released_units']);
        if ($committed < 1 || $committed > $count || $out + $released > $committed) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        return [
            'committed_units' => $committed,
            'released_units' => $released,
            'stock_out_units' => $out,
            'pending_stock_out_units' => $committed - $released - $out,
        ];
    }
}
