<?php
/**
 * IMP-07 — acceso SQL transaccional y orden fijo de bloqueo.
 *
 * Las operaciones de negocio deben utilizar ESTA conexión y callback: no
 * escribir por $wpdb, no llamar HTTP/SMTP y no hacer DDL dentro del callback.
 * El contexto no concede permisos ni valida transiciones E-01.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Transaction_Context
{
    private const VERSIONED = [
        'pc_orders' => ['id', true],
        'pc_order_lines' => ['id', true],
        'pc_cases' => ['id', false],
        'pc_sellables' => ['id', true],
        'pc_stock_commitments' => ['id', true],
        'pc_schedule_revisions' => ['resource_id', true],
        'pc_calendar_days' => ['id', false],
        'pc_appointments' => ['id', true],
    ];

    private \wpdb $db;
    private \mysqli $link;
    private int $connectionId;
    private bool $open = false;
    private bool $committing = false;
    private int $stage = 0;

    /** @var array<int,string> */
    private array $lastKey = [];

    /** @var array<string,array<string,array<string,mixed>>> */
    private array $locked = [];

    /** @var array<string,string> */
    private array $names;

    public function __construct(\wpdb $db)
    {
        if (!$db->dbh instanceof \mysqli) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        $this->db = $db;
        $this->link = $db->dbh;
        $this->connectionId = \mysqli_thread_id($this->link);
        // El inventario proviene del contrato DDL, nunca del navegador.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        $this->names = Schema::tableNames($db);
    }

    public function begin(): void
    {
        if ($this->open) {
            throw new Transaction_Failure('pc_nested_transaction_denied');
        }
        $this->queryDirect('START TRANSACTION', false);
        $this->open = true;
        // Bloqueo compartido en wp_options ANTES de cualquier ancla comercial.
        // Mantenimiento toma bloqueo exclusivo y espera a los TX anteriores.
        Maintenance::guardWithin($this);
    }

    public function commit(): void
    {
        $this->requireOpen();
        $this->committing = true;
        try {
            $this->queryDirect('COMMIT');
            $this->open = false;
        } catch (\Throwable $ignored) {
            // La respuesta de COMMIT puede perderse DESPUÉS de haber confirmado.
            // No relanzar el negocio ni tratar el resultado como un rollback seguro.
            throw new Transaction_Failure('pc_commit_outcome_uncertain', false, true);
        }
    }

    public function rollbackAfter(\Throwable $reason): Transaction_Failure
    {
        $failure = $reason instanceof Transaction_Failure
            ? $reason : new Transaction_Failure('pc_transaction_aborted');
        if (!$this->open) {
            return $failure;
        }

        $uncertain = $this->committing || $failure->isOutcomeUncertain();
        try {
            $this->queryDirect('ROLLBACK');
            $this->open = false;
        } catch (\Throwable $ignored) {
            $uncertain = true;
        }

        if ($uncertain) {
            // Si no se pudo certificar rollback, abandonar la conexión para
            // impedir que el resto del request siga escribiendo en ese TX.
            try {
                \mysqli_close($this->link);
            } catch (\Throwable $ignored) {
            }
            return new Transaction_Failure('pc_transaction_outcome_uncertain', false, true);
        }
        return $failure;
    }

    /**
     * SOLO SELECT, sin múltiples sentencias; los parámetros van por wpdb::prepare
     * y el SQL se ejecuta sobre mysqli sin reconexión automática de wpdb.
     *
     * @param list<int|string> $args
     * @return list<array<string,mixed>>
     */
    public function rows(string $format, array $args = []): array
    {
        if (!\preg_match('/\ASELECT\s/i', $format)) {
            throw new Transaction_Failure('pc_transaction_sql_denied');
        }
        $result = $this->queryDirect($this->prepare($format, $args));
        if (!$result instanceof \mysqli_result) {
            throw new Transaction_Failure('pc_transaction_sql_failed');
        }

        $rows = [];
        try {
            while (null !== ($row = \mysqli_fetch_assoc($result))) {
                if (!\is_array($row)) {
                    throw new Transaction_Failure('pc_transaction_sql_failed');
                }
                $rows[] = $row;
            }
        } catch (\mysqli_sql_exception $ignored) {
            throw new Transaction_Failure('pc_transaction_sql_failed');
        } finally {
            \mysqli_free_result($result);
        }
        return $rows;
    }

    /**
     * Sólo DML local INSERT/UPDATE. No DELETE histórico, DDL, BEGIN ni COMMIT
     * desde consumidores; las mutaciones siguen requiriendo permisos, CAS y
     * evento/auditoría atómicos en su fase responsable.
     *
     * @param list<int|string> $args
     */
    public function execute(string $format, array $args = []): int
    {
        if (!\preg_match('/\A(?:INSERT|UPDATE)\s/i', $format)) {
            throw new Transaction_Failure('pc_transaction_sql_denied');
        }
        $result = $this->queryDirect($this->prepare($format, $args));
        if (true !== $result) {
            throw new Transaction_Failure('pc_transaction_sql_failed');
        }
        $affected = \mysqli_affected_rows($this->link);
        if (!\is_int($affected) || $affected < 0) {
            throw new Transaction_Failure('pc_transaction_sql_failed');
        }
        return $affected;
    }

    /** Bloqueo raíz de una operación YA insertada por IMP-08. Una fila ausente no protege nada. */
    public function lockOperation(string $id): array
    {
        $id = Domain_Validation::sqlId($id);
        $this->advanceStage(10, $id);
        $row = $this->exactlyOne($this->rows(
            'SELECT id, scope_hash, key_hash, request_hash, technical_phase '
                . 'FROM %i WHERE id = %s FOR UPDATE',
            [$this->name('pc_idempotency'), $id]
        ));
        $this->remember('pc_idempotency', $id, $row);
        return $row;
    }

    /**
     * Recupera la raíz de idempotencia YA bloqueada por claimWithin() o
     * lockOperation(), sin pedir un nuevo FOR UPDATE después de bloquear
     * pedido/línea/stock. Impide invertir la precedencia de IMP-07.
     */
    public function lockedOperation(string $id): array
    {
        $id = Domain_Validation::sqlId($id);
        return $this->requireLocked('pc_idempotency', $id);
    }

    /**
     * Inspecta solo filas bloqueadas previamente por el recorrido de IMP-07.
     * No adquiere un lock tardío que pudiera invertir el orden transaccional.
     */
    public function lockedOrder(string $id): array
    {
        return $this->requireLocked('pc_orders', Domain_Validation::sqlId($id));
    }

    public function lockedOrderLine(string $id): array
    {
        return $this->requireLocked('pc_order_lines', Domain_Validation::sqlId($id));
    }

    public function lockedCase(string $id): array
    {
        return $this->requireLocked('pc_cases', Domain_Validation::sqlId($id));
    }

    public function lockedSellable(string $id): array
    {
        return $this->requireLocked('pc_sellables', Domain_Validation::sqlId($id));
    }

    public function lockedAppointment(string $id): array
    {
        return $this->requireLocked('pc_appointments', Domain_Validation::sqlId($id));
    }

    public function lockOrder(string $orderId): array
    {
        $orderId = Domain_Validation::sqlId($orderId);
        $this->advanceStage(20, $orderId);
        $row = $this->exactlyOne($this->rows(
            'SELECT id, row_version, status, payment_method FROM %i WHERE id = %s FOR UPDATE',
            [$this->name('pc_orders'), $orderId]
        ));
        $this->remember('pc_orders', $orderId, $row);
        return $row;
    }

    public function lockOrderLine(string $orderId, string $lineId): array
    {
        $orderId = Domain_Validation::sqlId($orderId);
        $lineId = Domain_Validation::sqlId($lineId);
        $this->requireLocked('pc_orders', $orderId);
        $this->advanceStage(30, $lineId);
        $row = $this->exactlyOne($this->rows(
            'SELECT id, order_id, sellable_id, kind, quantity, fulfillment_state, row_version '
                . 'FROM %i WHERE order_id = %s AND id = %s FOR UPDATE',
            [$this->name('pc_order_lines'), $orderId, $lineId]
        ));
        $this->remember('pc_order_lines', $lineId, $row);
        return $row;
    }

    /**
     * Solicitud invitada asociada a pedido, sin order_line_id obligatorio.
     * El pedido debe estar bloqueado, y el caso se bloquea antes de stock.
     */
    public function lockCaseForOrder(string $orderId, string $caseId): array
    {
        $orderId = Domain_Validation::sqlId($orderId);
        $caseId = Domain_Validation::sqlId($caseId);
        $this->requireLocked('pc_orders', $orderId);
        $this->advanceStage(40, $caseId);
        $row = $this->exactlyOne($this->rows(
            'SELECT id, order_id, order_line_id, case_type, case_phase, row_version '
                . 'FROM %i WHERE id = %s AND order_id = %s FOR UPDATE',
            [$this->name('pc_cases'), $caseId, $orderId]
        ));
        $this->remember('pc_cases', $caseId, $row);
        return $row;
    }

    /**
     * D-200: orden inmutable pedido -> línea -> caso antes de tocar stock/ledger.
     */
    public function lockCaseForLine(string $orderId, string $lineId, string $caseId): array
    {
        $orderId = Domain_Validation::sqlId($orderId);
        $lineId = Domain_Validation::sqlId($lineId);
        $caseId = Domain_Validation::sqlId($caseId);
        $this->requireLocked('pc_orders', $orderId);
        $this->requireLocked('pc_order_lines', $lineId);
        $this->advanceStage(40, $caseId);
        $row = $this->exactlyOne($this->rows(
            'SELECT id, order_id, order_line_id, case_type, case_phase, row_version '
                . 'FROM %i WHERE id = %s AND order_id = %s AND order_line_id = %s FOR UPDATE',
            [$this->name('pc_cases'), $caseId, $orderId, $lineId]
        ));
        $this->remember('pc_cases', $caseId, $row);
        return $row;
    }

    /** Caso técnico MP prepedido con source_operation_id, no inventar order_id. */
    public function lockCaseForOperation(string $operationId, string $caseId): array
    {
        $operationId = Domain_Validation::sqlId($operationId);
        $caseId = Domain_Validation::sqlId($caseId);
        $this->requireLocked('pc_idempotency', $operationId);
        $this->advanceStage(40, $caseId);
        $row = $this->exactlyOne($this->rows(
            'SELECT id, source_operation_id, case_type, case_phase, row_version '
                . 'FROM %i WHERE id = %s AND source_operation_id = %s AND order_id IS NULL FOR UPDATE',
            [$this->name('pc_cases'), $caseId, $operationId]
        ));
        $this->remember('pc_cases', $caseId, $row);
        return $row;
    }

    /**
     * Las variantes tienen fila estable aunque no haya holds. Orden numérico
     * real de BIGINT UNSIGNED (sin cast a int ni orden lexicográfico erróneo).
     *
     * @param list<string> $sellableIds
     * @return array<string,array<string,mixed>>
     */
    public function lockSellables(array $sellableIds): array
    {
        if ([] === $sellableIds || !\array_is_list($sellableIds)
            || \count($sellableIds) > 100) {
            throw new Transaction_Failure('pc_lock_selection_invalid');
        }
        $ids = [];
        foreach ($sellableIds as $id) {
            if (!\is_string($id)) {
                throw new Transaction_Failure('pc_lock_selection_invalid');
            }
            $ids[] = Domain_Validation::sqlId($id);
        }
        $ids = \array_values(\array_unique($ids));
        \usort($ids, [self::class, 'compareId']);

        $locked = [];
        foreach ($ids as $id) {
            $this->advanceStage(50, $id);
            $row = $this->exactlyOne($this->rows(
                'SELECT id, kind, active, track_stock, stock_on_hand, row_version '
                    . 'FROM %i WHERE id = %s FOR UPDATE',
                [$this->name('pc_sellables'), $id]
            ));
            $this->remember('pc_sellables', $id, $row);
            $locked[$id] = $row;
        }
        return $locked;
    }

    /**
     * Compromiso físico, siempre después de bloquear su línea y su variante.
     * Salidas/ajustes se implementan sobre esta garantía en IMP-17/37/41.
     */
    public function lockStockCommitment(string $lineId, string $sellableId): array
    {
        $lineId = Domain_Validation::sqlId($lineId);
        $sellableId = Domain_Validation::sqlId($sellableId);
        $this->requireLocked('pc_order_lines', $lineId);
        $this->requireLocked('pc_sellables', $sellableId);
        $this->advanceStage(55, $lineId);
        $row = $this->exactlyOne($this->rows(
            'SELECT id, order_line_id, sellable_id, committed_units, fulfilled_units, '
                . 'released_units, row_version FROM %i '
                . 'WHERE order_line_id = %s AND sellable_id = %s FOR UPDATE',
            [$this->name('pc_stock_commitments'), $lineId, $sellableId]
        ));
        $this->remember('pc_stock_commitments', (string) $row['id'], $row);
        return $row;
    }

    public function lockResource(string $resourceId): array
    {
        $resourceId = Domain_Validation::sqlId($resourceId);
        if ('1' !== $resourceId) {
            throw new Transaction_Failure('pc_resource_out_of_scope');
        }
        $this->advanceStage(60, $resourceId);
        $row = $this->exactlyOne($this->rows(
            'SELECT resource_id, active_revision_no, timezone, row_version '
                . 'FROM %i WHERE resource_id = %s FOR UPDATE',
            [$this->name('pc_schedule_revisions'), $resourceId]
        ));
        $this->remember('pc_schedule_revisions', $resourceId, $row);
        return $row;
    }

    /**
     * Crear primero ancla UQ(resource_id,local_date) y bloquearla incluso si
     * el día estaba vacío. El llamante aporta TODOS los días tocados, con
     * buffers, antes de buscar intersecciones [inicio,fin).
     *
     * @param list<string> $localDates
     * @return array<string,array<string,mixed>>
     */
    public function lockCalendarDays(string $resourceId, array $localDates): array
    {
        $resourceId = Domain_Validation::sqlId($resourceId);
        if ([] === $localDates || !\array_is_list($localDates)) {
            throw new Transaction_Failure('pc_lock_selection_invalid');
        }
        if (!isset($this->locked['pc_schedule_revisions'][$resourceId])) {
            $this->lockResource($resourceId);
        }
        $days = [];
        foreach ($localDates as $date) {
            if (!\is_string($date)) {
                throw new Transaction_Failure('pc_lock_selection_invalid');
            }
            $days[] = Business_Time::localDate($date);
        }
        $days = \array_values(\array_unique($days));
        \sort($days, \SORT_STRING);

        $locked = [];
        foreach ($days as $date) {
            $this->advanceStage(70, $date);
            $this->execute(
                'INSERT INTO %i (resource_id, local_date, created_at, row_version) '
                    . 'VALUES (%s, %s, UTC_TIMESTAMP(6), 1) '
                    . 'ON DUPLICATE KEY UPDATE id = id',
                [$this->name('pc_calendar_days'), $resourceId, $date]
            );
            $row = $this->exactlyOne($this->rows(
                'SELECT id, resource_id, local_date, row_version '
                    . 'FROM %i WHERE resource_id = %s AND local_date = %s FOR UPDATE',
                [$this->name('pc_calendar_days'), $resourceId, $date]
            ));
            $this->remember('pc_calendar_days', (string) $row['id'], $row);
            $locked[$date] = $row;
        }
        return $locked;
    }

    /**
     * Días locales para intervalo [start,end) UTC. El caller ya aplicó buffers.
     *
     * @return list<string>
     */
    public static function datesTouched(
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        string $timeZoneId
    ): array {
        Business_Time::requireInterval($startUtc, $endUtc);
        $zone = Business_Time::businessZone($timeZoneId);
        $start = $startUtc->setTimezone($zone)->format('Y-m-d');
        $last = $endUtc->modify('-1 microsecond')->setTimezone($zone)->format('Y-m-d');
        $dates = [];
        // Iterar fechas civiles en UTC después de obtener los límites
        // locales evita que un día sin medianoche/noon por DST se salte.
        $day = new \DateTimeImmutable($start . ' 00:00:00', new \DateTimeZone('UTC'));
        while ($day->format('Y-m-d') <= $last) {
            $dates[] = $day->format('Y-m-d');
            $day = $day->modify('+1 day');
        }
        return $dates;
    }

    /**
     * CAS de un recurso YA bloqueado. No es un set_status general ni valida
     * cambios de precio, dinero, stock, cita o caso: el consumidor los escribe
     * y audita por separado dentro del MISMO callback/COMMIT.
     */
    public function advanceVersion(string $suffix, string $id, string $expectedVersion): string
    {
        if (!isset(self::VERSIONED[$suffix])) {
            throw new Transaction_Failure('pc_version_target_denied');
        }
        $id = Domain_Validation::sqlId($id);
        $expectedVersion = Domain_Validation::sqlId($expectedVersion);
        $row = $this->requireLocked($suffix, $id);
        if ((string) ($row['row_version'] ?? '') !== $expectedVersion) {
            throw new Transaction_Failure('pc_version_conflict');
        }

        [$pk, $hasUpdatedAt] = self::VERSIONED[$suffix];
        $sql = 'UPDATE %i SET row_version = row_version + 1'
            . ($hasUpdatedAt ? ', updated_at = UTC_TIMESTAMP(6)' : '')
            . ' WHERE ' . $pk . ' = %s AND row_version = %s';
        if (1 !== $this->execute($sql, [$this->name($suffix), $id, $expectedVersion])) {
            throw new Transaction_Failure('pc_version_conflict');
        }

        $updated = $this->exactlyOne($this->rows(
            'SELECT row_version FROM %i WHERE ' . $pk . ' = %s FOR UPDATE',
            [$this->name($suffix), $id]
        ));
        $version = (string) $updated['row_version'];
        $this->locked[$suffix][$id]['row_version'] = $version;
        return $version;
    }

    /**
     * Los registros de cita se buscan DESPUÉS de anclar todos los días afectados.
     * Si no existe cita, la exclusión sigue viniendo de los días y UQ(order_id).
     */
    public function lockAppointmentForOrder(string $orderId): ?array
    {
        $orderId = Domain_Validation::sqlId($orderId);
        $this->requireLocked('pc_orders', $orderId);
        if (empty($this->locked['pc_calendar_days'])) {
            throw new Transaction_Failure('pc_calendar_days_not_locked');
        }
        $this->advanceStage(80, $orderId);
        $rows = $this->rows(
            'SELECT id, order_id, order_line_id, resource_id, starts_at_utc, '
                . 'ends_at_utc, row_version FROM %i WHERE order_id = %s FOR UPDATE',
            [$this->name('pc_appointments'), $orderId]
        );
        if ([] === $rows) {
            return null;
        }
        $row = $this->exactlyOne($rows);
        $this->remember('pc_appointments', (string) $row['id'], $row);
        return $row;
    }

    /**
     * Protección de consumidores con HTTP/SMTP: se llama desde adaptadores
     * antes de E/S externa, jamás dentro de un callback SQL.
     */
    public static function compareId(string $a, string $b): int
    {
        $len = \strlen($a) <=> \strlen($b);
        return 0 !== $len ? $len : \strcmp($a, $b);
    }

    private function advanceStage(int $stage, string $key): void
    {
        $this->requireOpen();
        if ($stage < $this->stage
            || ($stage === $this->stage
                && isset($this->lastKey[$stage])
                && ($stage === 70
                    ? \strcmp($key, $this->lastKey[$stage])
                    : self::compareId($key, $this->lastKey[$stage])) < 0)) {
            throw new Transaction_Failure('pc_lock_order_conflict');
        }
        $this->stage = $stage;
        $this->lastKey[$stage] = $key;
    }

    private function name(string $suffix): string
    {
        if (!isset($this->names[$suffix])) {
            throw new Transaction_Failure('pc_transaction_table_denied');
        }
        return $this->names[$suffix];
    }

    /** @param array<string,mixed> $row */
    private function remember(string $suffix, string $id, array $row): void
    {
        $this->locked[$suffix][$id] = $row;
    }

    /** @return array<string,mixed> */
    private function requireLocked(string $suffix, string $id): array
    {
        if (!isset($this->locked[$suffix][$id])) {
            throw new Transaction_Failure('pc_lock_prerequisite_missing');
        }
        return $this->locked[$suffix][$id];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private function exactlyOne(array $rows): array
    {
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_record_missing_or_conflict');
        }
        return $rows[0];
    }

    /** @param list<int|string> $args */
    private function prepare(string $format, array $args): string
    {
        if (!\array_is_list($args) || \str_contains($format, ';')
            || \preg_match('/%(?:\d+\$)?f/', $format)
            || \preg_match('/(?:\/\*|--|\x23)/', $format)) {
            throw new Transaction_Failure('pc_transaction_sql_denied');
        }
        foreach ($args as $arg) {
            if (!\is_int($arg) && !\is_string($arg)) {
                throw new Transaction_Failure('pc_transaction_sql_denied');
            }
        }
        if ([] === $args && !\str_contains($format, '%')) {
            return $format;
        }
        $query = $this->db->prepare($format, $args);
        if (!\is_string($query) || '' === $query) {
            throw new Transaction_Failure('pc_transaction_sql_denied');
        }
        return $query;
    }

    private function requireOpen(): void
    {
        if (!$this->open || $this->committing) {
            throw new Transaction_Failure('pc_transaction_not_open');
        }
    }

    private function sameConnection(): void
    {
        if ($this->db->dbh !== $this->link) {
            throw new Transaction_Failure('pc_transaction_connection_lost', false, true);
        }
        try {
            if (\mysqli_thread_id($this->link) !== $this->connectionId) {
                throw new Transaction_Failure('pc_transaction_connection_lost', false, true);
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_transaction_connection_lost', false, true);
        }
    }

    /** No usa wpdb::query(): WordPress puede reconectar y perder el BEGIN. */
    private function queryDirect(string $sql, bool $needsOpen = true): \mysqli_result|bool
    {
        if ($needsOpen && !$this->open) {
            throw new Transaction_Failure('pc_transaction_not_open');
        }
        $this->sameConnection();
        try {
            $result = \mysqli_query($this->link, $sql);
        } catch (\mysqli_sql_exception $error) {
            throw self::dbFailure((int) $error->getCode());
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_transaction_connection_lost', false, true);
        }
        if (false === $result) {
            throw self::dbFailure(\mysqli_errno($this->link));
        }
        $this->sameConnection();
        return $result;
    }

    private static function dbFailure(int $errno): Transaction_Failure
    {
        return match ($errno) {
            1213 => new Transaction_Failure('pc_deadlock', true),
            1205 => new Transaction_Failure('pc_lock_timeout', true),
            1062 => new Transaction_Failure('pc_unique_conflict'),
            2006, 2013 => new Transaction_Failure('pc_transaction_connection_lost', false, true),
            default => new Transaction_Failure('pc_transaction_sql_failed'),
        };
    }
}
