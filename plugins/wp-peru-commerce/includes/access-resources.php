<?php
/**
 * IMP-11 — consultas mínimas de pertenencia sobre las tablas del mismo sitio.
 * No conceden autorización por sí mismas ni devuelven información personal.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Access_Resources
{
    /** @var callable|null Verificador real de manifiesto y almacenamiento privado. */
    private static $evidenceVerifier = null;

    public static function registerEvidenceVerifier(callable $verifier): void
    {
        if (null !== self::$evidenceVerifier) {
            throw new \LogicException('pc_access_evidence_verifier_duplicate');
        }
        self::$evidenceVerifier = $verifier;
    }

    public static function sameSite(int $siteId): bool
    {
        return $siteId > 0 && $siteId === \get_current_blog_id()
            && (new Environment())->has('schema');
    }

    /** @return array<string,string>|null */
    public static function order(Transaction_Context $tx, string $id): ?array
    {
        return self::one($tx->rows(
            'SELECT id, status, row_version FROM %i WHERE id = %s',
            [self::table('pc_orders'), Domain_Validation::sqlId($id)]
        ), ['id','status','row_version']);
    }

    /** @return array<string,string>|null */
    public static function orderLine(Transaction_Context $tx, string $orderId, string $lineId): ?array
    {
        if (null === self::order($tx, $orderId)) {
            return null;
        }
        return self::one($tx->rows(
            'SELECT id, order_id, kind, fulfillment_state, row_version FROM %i '
                . 'WHERE id = %s AND order_id = %s',
            [self::table('pc_order_lines'), Domain_Validation::sqlId($lineId),
                Domain_Validation::sqlId($orderId)]
        ), ['id','order_id','kind','fulfillment_state','row_version']);
    }

    /** @return array<string,string>|null */
    public static function caseForOrder(
        Transaction_Context $tx, string $orderId, string $caseId
    ): ?array {
        if (null === self::order($tx, $orderId)) {
            return null;
        }
        $rows = $tx->rows(
            'SELECT id, order_id, order_line_id, case_type, case_phase, row_version '
                . 'FROM %i WHERE id = %s AND order_id = %s',
            [self::table('pc_cases'), Domain_Validation::sqlId($caseId),
                Domain_Validation::sqlId($orderId)]
        );
        $row = self::one($rows, ['id','order_id','case_type','case_phase','row_version']);
        if (null !== $row && null !== $rows[0]['order_line_id']) {
            $lineId = (string) $rows[0]['order_line_id'];
            if (null === self::orderLine($tx, $orderId, $lineId)) {
                return null;
            }
            $row['order_line_id'] = $lineId;
        }
        return $row;
    }

    /** @return array<string,string>|null */
    public static function caseForLine(
        Transaction_Context $tx, string $orderId, string $lineId, string $caseId
    ): ?array {
        if (null === self::orderLine($tx, $orderId, $lineId)) {
            return null;
        }
        return self::one($tx->rows(
            'SELECT id, order_id, order_line_id, case_type, case_phase, row_version '
                . 'FROM %i WHERE id = %s AND order_id = %s AND order_line_id = %s',
            [self::table('pc_cases'), Domain_Validation::sqlId($caseId),
                Domain_Validation::sqlId($orderId), Domain_Validation::sqlId($lineId)]
        ), ['id','order_id','order_line_id','case_type','case_phase','row_version']);
    }

    /**
     * Expedientes legales son independientes de compras; no inventar order_id.
     *
     * @return array<string,string>|null
     */
    public static function legalCase(Transaction_Context $tx, string $kind, string $id): ?array
    {
        $table = match ($kind) {
            'complaint' => 'pc_complaints',
            'privacy' => 'pc_privacy_requests',
            default => throw new \InvalidArgumentException('pc_access_resource_invalid'),
        };
        return self::one($tx->rows(
            'SELECT id, row_version FROM %i WHERE id = %s',
            [self::table($table), Domain_Validation::sqlId($id)]
        ), ['id','row_version']);
    }

    /**
     * La referencia de evidencia privada no es una credencial. Comprobar
     * caso, instalación, propósito, manifest y almacenamiento; si el último
     * verificador no está registrado la respuesta siempre es false.
     */
    public static function legalEvidence(
        Transaction_Context $tx, string $kind, string $caseId,
        string $evidenceRef, string $purpose
    ): bool {
        if (null === self::$evidenceVerifier
            || null === self::legalCase($tx, $kind, $caseId)
            || !\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D', $evidenceRef)
            || !\preg_match('/\A[a-z][a-z0-9_]{1,39}\z/D', $purpose)) {
            return false;
        }
        try {
            return true === (self::$evidenceVerifier)(
                $tx, $kind, $caseId, $evidenceRef, $purpose
            );
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * Solo vinculación física derecho->línea->pedido, NO autorización de
     * descarga ni comprobación de token de invitado.
     *
     * @return array<string,string>|null
     */
    public static function digitalRight(
        Transaction_Context $tx, string $orderId, string $rightId
    ): ?array {
        $rows = $tx->rows(
            'SELECT r.id, r.file_id, r.order_line_id, r.row_version, r.revoked_at '
                . 'FROM %i AS r INNER JOIN %i AS l ON l.id = r.order_line_id '
                . 'WHERE r.id = %s AND l.order_id = %s',
            [self::table('pc_download_rights'), self::table('pc_order_lines'),
                Domain_Validation::sqlId($rightId), Domain_Validation::sqlId($orderId)]
        );
        if (1 !== \count($rows) || null !== $rows[0]['revoked_at']) {
            return null;
        }
        return self::one($rows, ['id','file_id','order_line_id','row_version']);
    }

    /** @return array<string,string>|null */
    public static function notification(Transaction_Context $tx, string $id): ?array
    {
        return self::one($tx->rows(
            'SELECT id, entity_type, entity_id, audience_code, channel, event_code '
                . 'FROM %i WHERE id = %s',
            [self::table('pc_outbox'), Domain_Validation::sqlId($id)]
        ), ['id','entity_type','entity_id','audience_code','channel','event_code']);
    }

    /** @param list<array<string,mixed>> $rows
     * @param list<string> $fields
     * @return array<string,string>|null
     */
    private static function one(array $rows, array $fields): ?array
    {
        if ([] === $rows) {
            return null;
        }
        if (1 !== \count($rows)) {
            throw new \RuntimeException('pc_access_resource_invalid');
        }
        $result = [];
        foreach ($fields as $field) {
            $value = $rows[0][$field] ?? null;
            if (!\is_string($value) && !\is_int($value)) {
                throw new \RuntimeException('pc_access_resource_invalid');
            }
            $result[$field] = (string) $value;
        }
        return $result;
    }

    private static function table(string $suffix): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb || !\in_array($suffix, [
            'pc_orders', 'pc_order_lines', 'pc_cases', 'pc_complaints',
            'pc_privacy_requests', 'pc_download_rights', 'pc_outbox',
        ], true)) {
            throw new \RuntimeException('pc_access_resource_invalid');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        $names = Schema::tableNames($wpdb);
        return $names[$suffix];
    }
}
