<?php
/**
 * IMP-09 — escritura append-only de eventos de caso, stock, cita y auditoría.
 *
 * Nunca se invoca fuera del MISMO Transaction::run() que contiene la mutación
 * de estado, el evento y el resultado idempotente. No abre transacciones ni
 * autoriza operaciones por la mera posesión de un ID de recurso.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Event_Journal
{
    private const CASE_PHASES = [
        'manual_case_opened' => 'open_review',
        'manual_case_rejected' => 'closed_rejected',
        'manual_case_withdrawn' => 'closed_withdrawn',
        'manual_case_adjusted' => 'closed_adjusted',
    ];

    /**
     * Auditoría atómica de un hecho autorizado. Los before/after contienen
     * EXCLUSIVAMENTE hashes SHA-256 de hechos técnicos mínimos y permitidos
     * por el consumidor, nunca PII, OTP, URL, token ni cuerpo de petición.
     *
     * @return string ID de auditoría en notación SqlId
     */
    public static function auditWithin(
        Transaction_Context $tx,
        Event_Actor $actor,
        string $operationId,
        string $entityType,
        string $entityId,
        string $operationCode,
        ?string $reasonCode = null,
        ?string $beforeSha256 = null,
        ?string $afterSha256 = null
    ): string {
        self::requirePendingOperation($tx, $operationId);
        $actor->assertCurrent();
        $entityId = Domain_Validation::sqlId($entityId);
        self::code($entityType, 32);
        self::code($operationCode, 48);
        if (null !== $reasonCode) {
            self::code($reasonCode, 40);
        }
        foreach ([$beforeSha256, $afterSha256] as $digest) {
            if (null !== $digest && !\preg_match('/\A[a-f0-9]{64}\z/D', $digest)) {
                throw new Transaction_Failure('pc_audit_invalid_digest');
            }
        }

        $fields = ['entity_type', 'entity_id', 'operation_code', 'actor_kind',
            'request_id', 'occurred_at'];
        $values = ['%s', '%s', '%s', '%s', 'UNHEX(%s)', 'UTC_TIMESTAMP(6)'];
        $args = [$entityType, $entityId, $operationCode, $actor->kind(),
            self::correlationHex($operationId)];

        self::optional($fields, $values, $args, 'actor_user_id', $actor->userId());
        self::optional($fields, $values, $args, 'reason_code', $reasonCode);
        self::optional($fields, $values, $args, 'safe_before_hash', $beforeSha256, 'UNHEX(%s)');
        self::optional($fields, $values, $args, 'safe_after_hash', $afterSha256, 'UNHEX(%s)');

        self::insert($tx, 'pc_audit_log', $fields, $values, $args);
        return self::lastId($tx);
    }

    /**
     * Para lectura protegida de un expediente, IMP-11 registrará un guard
     * real por actor/capability/caso/finalidad. Denegado hasta entonces.
     */
    private static $readAuthorizer = null;

    public static function registerReadAuthorizer(callable $verifier): void
    {
        if (null !== self::$readAuthorizer) {
            throw new \LogicException('pc_audit_read_verifier_duplicate');
        }
        self::$readAuthorizer = $verifier;
    }

    /**
     * GET sensible: no hay necesariamente idempotency key mutadora.
     * La escritura de auditoría se confirma dentro del TX de la lectura
     * autorizada, sin exponer resultado a otras identidades.
     */
    public static function protectedReadWithin(
        Transaction_Context $tx,
        Event_Actor $actor,
        string $entityType,
        string $entityId,
        string $operationCode,
        string $purpose
    ): void {
        $actor->assertCurrent();
        self::code($entityType, 32);
        self::code($operationCode, 48);
        self::code($purpose, 40);
        $entityId = Domain_Validation::sqlId($entityId);
        if (null === self::$readAuthorizer) {
            throw new Transaction_Failure('pc_audit_read_unverified');
        }
        try {
            if (true !== (self::$readAuthorizer)($actor, $entityType, $entityId, $purpose)) {
                throw new Transaction_Failure('pc_audit_read_unverified');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_audit_read_unverified');
        }

        $fields = ['entity_type','entity_id','operation_code','actor_kind',
            'request_id','reason_code','occurred_at'];
        $values = ['%s','%s','%s','%s','UNHEX(%s)','%s','UTC_TIMESTAMP(6)'];
        $args = [$entityType,$entityId,$operationCode,$actor->kind(),
            \bin2hex(\random_bytes(16)), $purpose];
        self::optional($fields, $values, $args, 'actor_user_id', $actor->userId());
        self::insert($tx, 'pc_audit_log', $fields, $values, $args);
    }

    /**
     * Un evento inmutable por operación. Al cerrar manual_case_* se verifica
     * fase realmente persistida; un "closed_adjusted" no acredita refund.
     * Evento + audit deben entrar bajo el mismo COMMIT de la mutación del caso.
     *
     * @param array<string,int|string|bool>|null $safePayload Metadatos restringidos
     * @return string ID del evento
     */
    public static function caseWithin(
        Transaction_Context $tx,
        Event_Actor $actor,
        string $operationId,
        string $caseId,
        string $eventCode,
        ?string $decisionCode = null,
        ?string $reasonCode = null,
        ?string $evidenceRef = null,
        ?array $safePayload = null
    ): string {
        self::requirePendingOperation($tx, $operationId);
        $actor->assertCurrent();
        $caseId = Domain_Validation::sqlId($caseId);
        $locked = $tx->lockedCase($caseId);
        self::code($eventCode, 48);
        if (null !== $decisionCode) {
            self::code($decisionCode, 64);
        }
        if (null !== $reasonCode) {
            self::code($reasonCode, 40);
        }

        if (isset(self::CASE_PHASES[$eventCode])) {
            // D-199/D-200: estas acciones son solo A con TODAS las
            // capabilities indicadas, aunque un controlador haya fallado.
            if ('staff' !== $actor->kind()
                || !\current_user_can('perucommerce_orders_read')
                || !\current_user_can('perucommerce_orders_financial_adjust')) {
                throw new Transaction_Failure('pc_case_event_actor_denied');
            }
            $rows = $tx->rows(
                'SELECT case_type, case_phase, created_by FROM %i WHERE id = %s',
                [self::table('pc_cases'), $caseId]
            );
            if (1 !== \count($rows)
                || 'physical_partial_quantity_adjustment' !== $rows[0]['case_type']
                || self::CASE_PHASES[$eventCode] !== $rows[0]['case_phase']) {
                throw new Transaction_Failure('pc_case_event_state_conflict');
            }
            if (null === $reasonCode) {
                throw new Transaction_Failure('pc_case_event_reason_required');
            }
            if ('manual_case_opened' === $eventCode
                && (string) $rows[0]['created_by'] !== $actor->userId()) {
                throw new Transaction_Failure('pc_case_event_actor_denied');
            }
            if ('manual_case_rejected' === $eventCode && 'reject' !== $decisionCode
                || 'manual_case_withdrawn' === $eventCode && 'buyer_withdrawn' !== $decisionCode) {
                throw new Transaction_Failure('pc_case_event_decision_invalid');
            }
            if (('manual_case_opened' === $eventCode
                    && (null === $safePayload
                        || !isset($safePayload['requested_affected_units'],
                            $safePayload['contact_control_method'], $safePayload['nonpublic_fact_category'])))
                || ('manual_case_withdrawn' === $eventCode
                    && (null === $safePayload
                        || !isset($safePayload['contact_control_method'],
                            $safePayload['nonpublic_fact_category'])))
                || ('manual_case_adjusted' === $eventCode
                    && (null === $safePayload || !isset($safePayload['affected_units'])))) {
                throw new Transaction_Failure('pc_case_event_payload_invalid');
            }
            // Motivo íntegro en el evento privado de rechazo/retiro,
            // no en auditoría genérica o en la respuesta al operador.
            if (\in_array($eventCode,['manual_case_rejected','manual_case_withdrawn'],true)
                && (null===$safePayload
                    || !isset($safePayload['decision_reason'],
                        $safePayload['case_row_version'])
                    || ('manual_case_withdrawn'===$eventCode
                        && !isset($safePayload['withdrawal_safe_note'])))) {
                throw new Transaction_Failure('pc_case_event_payload_invalid');
            }
        }
        if (!\in_array($eventCode,['manual_case_rejected','manual_case_withdrawn'],true)
            && null!==$safePayload
            && (isset($safePayload['decision_reason'])
                || isset($safePayload['withdrawal_safe_note']))) {
            throw new Transaction_Failure('pc_case_event_payload_invalid');
        }
        if ($locked['id'] !== $caseId && (string) $locked['id'] !== $caseId) {
            throw new Transaction_Failure('pc_case_event_scope_invalid');
        }
        if (null !== $evidenceRef
            && !\preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D', $evidenceRef)) {
            throw new Transaction_Failure('pc_case_event_evidence_invalid');
        }
        $safe = self::casePayload($safePayload);

        $columns = ['case_id', 'idempotency_id', 'event_code', 'actor_kind', 'occurred_at'];
        $holders = ['%s', '%s', '%s', '%s', 'UTC_TIMESTAMP(6)'];
        $args = [$caseId, Domain_Validation::sqlId($operationId), $eventCode, $actor->kind()];
        self::optional($columns, $holders, $args, 'actor_user_id', $actor->userId());
        self::optional($columns, $holders, $args, 'decision_code', $decisionCode);
        self::optional($columns, $holders, $args, 'evidence_ref', $evidenceRef);
        self::optional($columns, $holders, $args, 'safe_payload_snapshot', $safe);
        self::insert($tx, 'pc_case_events', $columns, $holders, $args);
        $eventId = self::lastId($tx);

        // Append del evento + auditoría indivisibles. No llamar completeLocal
        // antes de esta escritura; una falla fuerza rollback de todo el TX.
        self::auditWithin(
            $tx, $actor, $operationId, 'case', $caseId, $eventCode, $reasonCode
        );
        return $eventId;
    }

    /**
     * Saldo físico ya actualizado por consumidor bajo lock sellable + CAS.
     * Verifica que el saldo persistido coincida y registra movimiento/audit
     * en la MISMA transacción. Nunca permite rectificar editando un movimiento.
     */
    public static function stockWithin(
        Transaction_Context $tx,
        Event_Actor $actor,
        string $operationId,
        string $sellableId,
        int $deltaUnits,
        int $balanceAfter,
        string $sourceType,
        ?string $sourceId,
        string $reasonCode
    ): string {
        self::requirePendingOperation($tx, $operationId);
        $actor->assertCurrent();
        if ('guest' === $actor->kind()) {
            throw new Transaction_Failure('pc_stock_event_actor_denied');
        }
        $sellableId = Domain_Validation::sqlId($sellableId);
        $locked = $tx->lockedSellable($sellableId);
        self::code($sourceType, 32);
        self::code($reasonCode, 40);
        if (0 === $deltaUnits || $balanceAfter < 0) {
            throw new Transaction_Failure('pc_stock_event_invalid');
        }
        $previous = Money::fromApi((string) $locked['stock_on_hand']);
        if (Money::addChecked($previous, $deltaUnits) !== $balanceAfter) {
            throw new Transaction_Failure('pc_stock_event_balance_mismatch');
        }
        if (null !== $sourceId) {
            $sourceId = Domain_Validation::sqlId($sourceId);
        }
        $rows = $tx->rows(
            'SELECT stock_on_hand FROM %i WHERE id = %s',
            [self::table('pc_sellables'), $sellableId]
        );
        if (1 !== \count($rows) || (string) $balanceAfter !== (string) $rows[0]['stock_on_hand']) {
            throw new Transaction_Failure('pc_stock_event_balance_mismatch');
        }

        // Aunque no haya UQ condicional por source_type, el lock exclusivo
        // de pc_idempotency impide duplicación en replays concurrentes.
        $existing = $tx->rows(
            'SELECT id FROM %i WHERE idempotency_id = %s AND sellable_id = %s '
                . 'AND source_type = %s '
                . (null === $sourceId ? 'AND source_id IS NULL' : 'AND source_id = %s'),
            \array_merge([self::table('pc_stock_movements'),
                $operationId, $sellableId, $sourceType],
                null === $sourceId ? [] : [$sourceId])
        );
        if ([] !== $existing) {
            throw new Transaction_Failure('pc_stock_event_duplicate');
        }

        $cols = ['sellable_id','delta_units','balance_after','source_type',
            'idempotency_id','reason_code','occurred_at'];
        $holders = ['%s','%s','%s','%s','%s','%s','UTC_TIMESTAMP(6)'];
        $args = [$sellableId, (string) $deltaUnits, (string) $balanceAfter,
            $sourceType, $operationId, $reasonCode];
        self::optional($cols, $holders, $args, 'source_id', $sourceId);
        self::optional($cols, $holders, $args, 'actor_user_id', $actor->userId());
        self::insert($tx, 'pc_stock_movements', $cols, $holders, $args);
        $movementId = self::lastId($tx);

        self::auditWithin($tx, $actor, $operationId, 'sellable', $sellableId,
            'stock_movement_recorded', $reasonCode);
        return $movementId;
    }

    /**
     * Reprogramación de una misma cita: el caller debe bloquear orden,
     * recurso y días antes de la fila cita; la modificación se hace antes
     * de registrar historia. Solo staff, porque el DDL exige WP user ID.
     */
    public static function appointmentWithin(
        Transaction_Context $tx,
        Event_Actor $actor,
        string $operationId,
        string $appointmentId,
        string $previousStartUtc,
        string $previousEndUtc,
        string $newStartUtc,
        string $newEndUtc,
        string $reasonCode,
        ?string $outboxId = null
    ): string {
        self::requirePendingOperation($tx, $operationId);
        $actor->assertCurrent();
        if ('staff' !== $actor->kind() || null === $actor->userId()) {
            throw new Transaction_Failure('pc_appointment_event_actor_denied');
        }
        $appointmentId = Domain_Validation::sqlId($appointmentId);
        $locked = $tx->lockedAppointment($appointmentId);
        self::code($reasonCode, 40);
        if (null !== $outboxId) {
            $outboxId = Domain_Validation::sqlId($outboxId);
        }

        $previousStart = Business_Time::fromDatabase($previousStartUtc);
        $previousEnd = Business_Time::fromDatabase($previousEndUtc);
        $nextStart = Business_Time::fromDatabase($newStartUtc);
        $nextEnd = Business_Time::fromDatabase($newEndUtc);
        Business_Time::requireInterval($previousStart, $previousEnd);
        Business_Time::requireInterval($nextStart, $nextEnd);
        if (($previousStartUtc === $newStartUtc && $previousEndUtc === $newEndUtc)
            || (string) $locked['starts_at_utc'] !== $previousStartUtc
            || (string) $locked['ends_at_utc'] !== $previousEndUtc) {
            throw new Transaction_Failure('pc_appointment_history_conflict');
        }
        $rows = $tx->rows(
            'SELECT starts_at_utc, ends_at_utc FROM %i WHERE id = %s',
            [self::table('pc_appointments'), $appointmentId]
        );
        if (1 !== \count($rows) || (string) $rows[0]['starts_at_utc'] !== $newStartUtc
            || (string) $rows[0]['ends_at_utc'] !== $newEndUtc) {
            throw new Transaction_Failure('pc_appointment_history_conflict');
        }

        $cols = ['appointment_id','previous_start_utc','previous_end_utc',
            'new_start_utc','new_end_utc','actor_user_id','reason_code','occurred_at'];
        $holders = ['%s','%s','%s','%s','%s','%s','%s','UTC_TIMESTAMP(6)'];
        $args = [$appointmentId,$previousStartUtc,$previousEndUtc,
            $newStartUtc,$newEndUtc,$actor->userId(),$reasonCode];
        self::optional($cols, $holders, $args, 'communication_outbox_id', $outboxId);
        self::insert($tx, 'pc_appointment_history', $cols, $holders, $args);
        $historyId = self::lastId($tx);
        self::auditWithin($tx, $actor, $operationId, 'appointment', $appointmentId,
            'appointment_rescheduled', $reasonCode);
        return $historyId;
    }

    /** @return string Estado de operación root ya bloqueado en IMP-08. */
    public static function requirePendingOperation(Transaction_Context $tx, string $id): string
    {
        $id = Domain_Validation::sqlId($id);
        $row = $tx->lockedOperation($id);
        // La fila cacheada por lockOperation() refleja el INICIO del TX.
        // completeLocalWithin() puede haber cambiado la fase en esta misma
        // transacción: consultar estado vivo impide eventos tras completar.
        $actual = $tx->rows(
            'SELECT technical_phase FROM %i WHERE id = %s',
            [self::table('pc_idempotency'), $id]
        );
        if ('prepared' !== (string) ($row['technical_phase'] ?? '')
            || 1 !== \count($actual)
            || 'prepared' !== (string) $actual[0]['technical_phase']) {
            throw new Transaction_Failure('pc_event_operation_not_pending');
        }
        return $id;
    }

    public static function code(string $code, int $maxLength): string
    {
        if ($maxLength < 1 || $maxLength > 64
            || \strlen($code) > $maxLength
            || !\preg_match('/\A[a-z][a-z0-9_]*\z/D', $code)) {
            throw new Transaction_Failure('pc_event_code_invalid');
        }
        return $code;
    }

    public static function correlationHex(string $operationId): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_audit_context_missing');
        }
        $operationId = Domain_Validation::sqlId($operationId);
        return \substr(\hash('sha256',
            'pc-audit-v1:' . \get_current_blog_id() . ':' . $wpdb->prefix . ':' . $operationId
        ), 0, 32);
    }

    /**
     * Para un nuevo evento de caso solo se persisten hechos categóricos
     * aprobados. Sin texto libre, teléfono, DNI, dirección ni OTP.
     *
     * @param array<string,int|string|bool>|null $payload
     */
    private static function casePayload(?array $payload): ?string
    {
        if (null === $payload) {
            return null;
        }
        if ([] === $payload || \count($payload) > 9 || \array_is_list($payload)) {
            throw new Transaction_Failure('pc_case_event_payload_invalid');
        }
        foreach ($payload as $key => $value) {
            switch ($key) {
                case 'requested_affected_units':
                case 'affected_units':
                    if (!\is_int($value) || $value < 1 || $value > 100000) {
                        throw new Transaction_Failure('pc_case_event_payload_invalid');
                    }
                    break;
                case 'contact_control_method':
                    if (!\in_array($value, ['outbound_call_to_registered_phone',
                        'verified_reply_from_registered_phone'], true)) {
                        throw new Transaction_Failure('pc_case_event_payload_invalid');
                    }
                    break;
                case 'nonpublic_fact_category':
                    if (!\in_array($value, ['private_delivery_detail','private_order_detail'], true)) {
                        throw new Transaction_Failure('pc_case_event_payload_invalid');
                    }
                    break;
                case 'unit_effect':
                    if ('release_unfulfilled_units' !== $value) {
                        throw new Transaction_Failure('pc_case_event_payload_invalid');
                    }
                    break;
                case 'decision':
                    if (!\in_array($value, ['reject','buyer_withdrawn'], true)) {
                        throw new Transaction_Failure('pc_case_event_payload_invalid');
                    }
                    break;
                case 'case_row_version':
                    if (!\is_string($value)) {
                        throw new Transaction_Failure('pc_case_event_payload_invalid');
                    }
                    Domain_Validation::sqlId($value);
                    break;
                // Campos textuales solo de un evento D-200, protegido
                // por permisos A; motivo completo no entra en audit.
                case 'decision_reason':
                case 'withdrawal_safe_note':
                    $max='decision_reason'===$key?1000:400;
                    $min='decision_reason'===$key?1:8;
                    if (!\is_string($value) || ''===\trim($value)
                        || !\mb_check_encoding($value,'UTF-8')
                        || \mb_strlen($value,'UTF-8')<$min
                        || \mb_strlen($value,'UTF-8')>$max
                        || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)
                        || ('withdrawal_safe_note'===$key
                            && (\preg_match('/[\x00-\x1F\x7F]/u',$value)
                                || \str_contains($value,'://')
                                || \preg_match('/[0-9@]/u',$value)))) {
                        throw new Transaction_Failure('pc_case_event_payload_invalid');
                    }
                    break;
                default:
                    throw new Transaction_Failure('pc_case_event_payload_invalid');
            }
        }
        \ksort($payload, \SORT_STRING);
        return \json_encode($payload, \JSON_THROW_ON_ERROR);
    }

    /**
     * Sólo nombres de columnas fijos en el servidor; ninguna etiqueta SQL
     * viene de HTTP. No guardar VARCHAR opcionales como cadena vacía.
     *
     * @param list<string> $columns
     * @param list<string> $holders
     * @param list<string> $args
     */
    private static function optional(
        array &$columns, array &$holders, array &$args,
        string $column, ?string $value, string $placeholder = '%s'
    ): void {
        if (null !== $value) {
            $columns[] = $column;
            $holders[] = $placeholder;
            $args[] = $value;
        }
    }

    /**
     * @param list<string> $columns
     * @param list<string> $placeholders
     * @param list<string> $args
     */
    private static function insert(
        Transaction_Context $tx,
        string $tableSuffix,
        array $columns,
        array $placeholders,
        array $args
    ): void {
        if ([] === $columns || \count($columns) !== \count($placeholders)
            || \count($args) !== \count($placeholders) - \count(\array_filter($placeholders,
                static fn (string $part): bool => 'UTC_TIMESTAMP(6)' === $part))) {
            throw new Transaction_Failure('pc_event_insert_invalid');
        }
        $query = 'INSERT INTO %i (' . \implode(', ', $columns) . ') VALUES ('
            . \implode(', ', $placeholders) . ')';
        $params = \array_merge([self::table($tableSuffix)], $args);
        if (1 !== $tx->execute($query, $params)) {
            throw new Transaction_Failure('pc_event_insert_failed');
        }
    }

    private static function table(string $suffix): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_event_context_missing');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        $names = Schema::tableNames($wpdb);
        if (!\in_array($suffix, ['pc_idempotency','pc_audit_log','pc_case_events','pc_cases',
            'pc_sellables','pc_stock_movements','pc_appointments','pc_appointment_history'], true)
            || !isset($names[$suffix])) {
            throw new Transaction_Failure('pc_event_table_denied');
        }
        return $names[$suffix];
    }

    private static function lastId(Transaction_Context $tx): string
    {
        $rows = $tx->rows('SELECT LAST_INSERT_ID() AS inserted_id');
        if (1 !== \count($rows)
            || (!\is_string($rows[0]['inserted_id']) && !\is_int($rows[0]['inserted_id']))) {
            throw new Transaction_Failure('pc_event_insert_failed');
        }
        return Domain_Validation::sqlId((string) $rows[0]['inserted_id']);
    }
}
