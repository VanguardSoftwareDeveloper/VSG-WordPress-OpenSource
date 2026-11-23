<?php
/**
 * IMP-22 — proyección mínima de expedientes vinculados a un pedido.
 *
 * IMP-33: los casos guest de R8-12 se exponen sólo al administrador A
 * según capability específica por finalidad. Los demás tipos siguen
 * cerrados; nunca heredan permisos del catálogo de casos.
 * Un resumen no habilita decisión, ajuste financiero ni reembolso.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Cases
{
    private const FINANCIAL_CASE = 'physical_partial_quantity_adjustment';
    private const DIGITAL_CASE = 'digital_access_dispute';
    private const LOGISTICS_CASE = 'logistics_non_delivery';
    /** Solo casos efectivamente creados por R8-12, sin compatibilidad genérica. */
    private const GUEST_CAPABILITIES=[
        'guest_reissue_tracking'=>'perucommerce_privacy_manage',
        'guest_delivery_change'=>'perucommerce_orders_fulfill',
        'guest_request_refund'=>'perucommerce_orders_financial_adjust',
        'guest_reissue_download'=>'perucommerce_digital_files_manage',
        'guest_resend_notification'=>'perucommerce_notifications_operate',
    ];
    private const MAX_CASES = 200;
    private const PER_PAGE = 50;

    public static function allowed(string $caseType): bool
    {
        if ('A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_orders_read')) {
            return false;
        }
        if (self::FINANCIAL_CASE===$caseType) {
            return \current_user_can('perucommerce_orders_financial_adjust');
        }
        if (self::DIGITAL_CASE===$caseType) {
            return \current_user_can('perucommerce_digital_files_manage');
        }
        if (self::LOGISTICS_CASE===$caseType) {
            return \current_user_can('perucommerce_orders_fulfill');
        }
        return isset(self::GUEST_CAPABILITIES[$caseType])
            && \current_user_can(self::GUEST_CAPABILITIES[$caseType]);
    }

    public static function isGuestType(string $caseType): bool
    {
        return isset(self::GUEST_CAPABILITIES[$caseType]);
    }

    public static function canReadAny(): bool
    {
        if ('A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_orders_read')) {
            return false;
        }
        if (\current_user_can('perucommerce_orders_financial_adjust')
            || \current_user_can('perucommerce_digital_files_manage')
            || \current_user_can('perucommerce_orders_fulfill')) {
            return true;
        }
        foreach (self::GUEST_CAPABILITIES as $capability) {
            if (\current_user_can($capability)) { return true; }
        }
        return false;
    }

    /**
     * @return list<array<string,mixed>> AdminCaseSummary, SOLO A autorizado.
     */
    public static function summariesWithin(Transaction_Context $tx, string $orderId): array
    {
        $orderId = Orders_Domain::id($orderId);
        $rows = $tx->rows(
            'SELECT id, order_line_id, case_type, case_phase, reason_code, '
                . 'row_version, opened_at FROM %i WHERE order_id = %s '
                . 'ORDER BY opened_at, id LIMIT 201 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_cases'), $orderId]
        );
        if (\count($rows) > self::MAX_CASES) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        $result = [];
        foreach ($rows as $case) {
            if (!self::allowed((string) $case['case_type'])) {
                // No filtrar a O ni conceder acceso por la simple existencia.
                continue;
            }
            $result[] = self::summary($case);
        }
        return $result;
    }

    /**
     * Detalle de expediente elegido para este pedido, con historia paginada
     * hasta 50 hechos. No exponer snapshot bruto ni rutas a evidencias.
     */
    public static function detailWithin(
        Transaction_Context $tx, string $orderId, string $caseId,
        int $page
    ): array {
        $orderId = Orders_Domain::id($orderId);
        $caseId = Orders_Domain::id($caseId);
        if ($page < 1 || $page > 100000) {
            throw new Transaction_Failure('pc_orders_field_invalid');
        }
        $rows = $tx->rows(
            'SELECT id, order_id, order_line_id, case_type, case_phase, '
                . 'reason_code, row_version, opened_at, evidence_snapshot '
                . 'FROM %i WHERE id = %s AND order_id = %s LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_cases'), $caseId, $orderId]
        );
        if (1 !== \count($rows) || !self::allowed((string) $rows[0]['case_type'])) {
            throw new Transaction_Failure('pc_orders_not_found');
        }
        $case = $rows[0];
        // D-200 exige línea física en caso parcial staff. IMP-33
        // exige exactamente NULL para el caso guest por pedido; nunca
        // adivinar línea ni permitir que se decida como ajuste R8-26.
        if (self::isGuestType((string)$case['case_type'])) {
            if (null!==$case['order_line_id']) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
        } else {
            if (!\in_array((string)$case['case_type'],[
                    self::FINANCIAL_CASE,self::DIGITAL_CASE,
                    self::LOGISTICS_CASE,
                ],true) || null===$case['order_line_id']) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            $line = $tx->rows(
                'SELECT id, kind FROM %i WHERE id = %s AND order_id = %s',
                [Orders_Domain::table('pc_order_lines'),
                    (string)$case['order_line_id'],$orderId]
            );
            $requiredKind=self::DIGITAL_CASE===(string)$case['case_type']
                ? 'digital' : 'physical';
            if (1!==\count($line) || $requiredKind!==(string)$line[0]['kind']) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
        }

        $offset = ($page - 1) * self::PER_PAGE;
        $events = $tx->rows(
            'SELECT id, event_code, decision_code, actor_kind, '
                . 'occurred_at, evidence_ref, safe_payload_snapshot '
                . 'FROM %i WHERE case_id = %s '
                . 'ORDER BY occurred_at, id LIMIT 51 OFFSET %d LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_case_events'), $caseId, $offset]
        );
        $hasMore = \count($events) > self::PER_PAGE;
        if ($hasMore) {
            \array_pop($events);
        }
        $out = [
            'case_id' => $caseId,
            'row_version' => Orders_Domain::id((string) $case['row_version']),
            'case_type' => (string) $case['case_type'],
            'case_phase' => (string) $case['case_phase'],
            'reason_code' => (string) $case['reason_code'],
            'opened_at' => Orders_Domain::utc((string) $case['opened_at']),
            'events' => \array_map(static function (array $e) use ($case): array {
                if (!\in_array((string) $e['actor_kind'], ['guest','staff','system'], true)) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
                $event = [
                    'event_id' => Orders_Domain::id((string) $e['id']),
                    'event_code' => (string) $e['event_code'],
                    'occurred_at' => Orders_Domain::utc((string) $e['occurred_at']),
                    'actor_kind' => (string) $e['actor_kind'],
                ];
                if (null !== $e['decision_code']) {
                    $event['decision_code'] = (string) $e['decision_code'];
                }
                // Exponer exclusivamente el motivo de investigación al
                // administrador habilitado para ESTE tipo de expediente.
                if (self::LOGISTICS_CASE === (string)$case['case_type']) {
                    if (null !== $e['evidence_ref']) {
                        $event['evidence_ref'] = (string)$e['evidence_ref'];
                    }
                    if (null !== $e['safe_payload_snapshot']) {
                        $safe = Orders_Domain::decode(
                            (string)$e['safe_payload_snapshot'],10000
                        );
                        if (\is_string($safe['reason'] ?? null)
                            && '' !== \trim($safe['reason'])
                            && \mb_strlen($safe['reason'],'UTF-8')<=1000) {
                            $event['safe_summary']=$safe['reason'];
                        }
                    }
                }
                // R8-60 necesita EXACTAMENTE la referencia de la decisión
                // R8-61 que autorizó esta revocación. La expone solo para el
                // caso digital tipado, resuelto, con permiso A de ese caso;
                // no revelar notas/evidencia de otras fases, invitado u O.
                if (self::DIGITAL_CASE === (string)$case['case_type']
                    && 'closed_revocation_authorized' === (string)$case['case_phase']
                    && 'digital_case_decided' === (string)$e['event_code']
                    && 'authorize_revocation' === (string)$e['decision_code']
                    && 'staff' === (string)$e['actor_kind']) {
                    $ref = $e['evidence_ref'];
                    if (!\is_string($ref) || '' === \trim($ref)
                        || \strlen($ref) > 500
                        || \str_contains($ref, '://')
                        || \preg_match('/[\x00-\x1F\x7F]/', $ref)) {
                        throw new Transaction_Failure('pc_orders_invariant_invalid');
                    }
                    $event['evidence_ref'] = $ref;
                }
                // Para los demás casos, jamás retornar el snapshot íntegro.
                return $event;
            }, $events),
            'events_page' => $page,
            'events_per_page' => self::PER_PAGE,
            'events_has_more' => $hasMore,
        ];
        // El primer evento es origen alternativo legítimo si el snapshot
        // inicial de pc_cases no existe; nunca usar el ÚLTIMO evento para
        // inventar qué solicitó el comprador originalmente.
        $first = $tx->rows(
            'SELECT actor_kind, occurred_at, safe_payload_snapshot FROM %i '
                . 'WHERE case_id = %s ORDER BY occurred_at, id LIMIT 1 LOCK IN SHARE MODE',
            [Orders_Domain::table('pc_case_events'), $caseId]
        );
        $originalRaw = $case['evidence_snapshot'] ?? null;
        if (null === $originalRaw && 1 === \count($first)) {
            $originalRaw = $first[0]['safe_payload_snapshot'];
        }
        if (null !== $originalRaw) {
            if (1 === \count($first)
                && \in_array($first[0]['actor_kind'], ['staff','guest','system'], true)) {
                $original = Orders_Domain::decode((string) $originalRaw, 10000);
                $allowed = [
                    'purpose','reason','requested_ubigeo','requested_address',
                    'case_details','evidence_ref','requested_affected_units',
                    'manual_verification_basis',
                ];
                $response = [
                    'source_kind' => $first[0]['actor_kind'],
                    'recorded_at' => Orders_Domain::utc((string) $first[0]['occurred_at']),
                ];
                foreach ($allowed as $name) {
                    if (\array_key_exists($name, $original)) {
                        $response[$name] = $original[$name];
                    }
                }
                // Validar tipos y límites del OpenAPI antes de exponer un
                // campo originalmente guardado por otro consumidor.
                $dto = (object) $response;
                try {
                    Rest_Validator::namedObject('AdminCaseOriginalRequest', $dto);
                } catch (\Throwable $ignored) {
                    // El fallo proviene del registro histórico, no de un
                    // formulario del administrador. Nunca devolver 422 aquí.
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
                $out['original_request'] = $response;
            }
        }

        // Lectura de expediente A con rastro mínimo de finalidad fija:
        // la URL no incorpora campo purpose adicional al OpenAPI R8-21.
        $tx->execute(
            'INSERT INTO %i (entity_type, entity_id, operation_code, actor_kind, '
                . 'actor_user_id, reason_code, safe_after_hash, occurred_at) '
                . 'VALUES (%s,%s,%s,%s,%s,%s,UNHEX(%s),UTC_TIMESTAMP(6))',
            [Orders_Domain::table('pc_audit_log'),
                'case', $caseId, 'order_case_detail_read', 'staff',
                (string) \get_current_user_id(), 'case_review',
                \hash('sha256', 'pc-case-read:' . $orderId . ':' . $caseId . ':'
                    . (string) \get_current_user_id())]
        );
        return $out;
    }

    private static function summary(array $case): array
    {
        $row = [
            'case_id' => Orders_Domain::id((string) $case['id']),
            'case_type' => (string) $case['case_type'],
            'case_phase' => (string) $case['case_phase'],
            'reason_code' => (string) $case['reason_code'],
            'row_version' => Orders_Domain::id((string) $case['row_version']),
            'opened_at' => Orders_Domain::utc((string) $case['opened_at']),
        ];
        if (null !== $case['order_line_id']) {
            $row['order_line_id'] = Orders_Domain::id((string) $case['order_line_id']);
        }
        return $row;
    }
}
