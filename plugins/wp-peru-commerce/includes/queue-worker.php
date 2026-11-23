<?php
/**
 * IMP-10 — worker recuperable mediante WP-Cron / invocación administrativa.
 *
 * Un lease es (id, attempt_count, vencimiento en next_attempt_at). Las
 * llamadas externas se hacen DESPUÉS del COMMIT de claim, nunca dentro
 * de Transaction::run. No promete exactly-once ni puntualidad de cron.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Queue_Worker
{
    public const HOOK = 'perucommerce_queue_tick';

    private const SCHEDULE = 'perucommerce_five_minutes';
    private const LEASE_SECONDS = 300;
    private const MAX_ATTEMPTS = 5;
    private const BACKOFF_SECONDS = [60, 300, 900, 3600];
    private const MAX_BATCH = 6;

    /** @var callable|null Reconciliador MP: GET real fuera de transacción. */
    private static $inboxHandler = null;

    /** @var callable|null Transporte de correo: devuelve aceptación, no entrega. */
    private static $emailHandler = null;

    public static function registerHooks(): void
    {
        \add_filter('cron_schedules', [self::class, 'schedules']);
        \add_action(self::HOOK, [self::class, 'cronTick']);
        \add_action('init', [self::class, 'ensureScheduled'], 20);
    }

    /** @param array<string, array<string,mixed>> $schedules
     * @return array<string, array<string,mixed>>
     */
    public static function schedules(array $schedules): array
    {
        if (!isset($schedules[self::SCHEDULE])) {
            $schedules[self::SCHEDULE] = [
                'interval' => 300,
                'display' => 'PeruCommerce: revisión de trabajos pendientes',
            ];
        }
        return $schedules;
    }

    /** El plugin no registra handlers ficticios: sin integración, no envía nada. */
    public static function registerInboxHandler(callable $handler): void
    {
        if (null !== self::$inboxHandler) {
            throw new \LogicException('pc_inbox_handler_duplicate');
        }
        self::$inboxHandler = $handler;
    }

    public static function registerEmailHandler(callable $handler): void
    {
        if (null !== self::$emailHandler) {
            throw new \LogicException('pc_email_handler_duplicate');
        }
        self::$emailHandler = $handler;
    }

    /**
     * Registro idempotente. Cada instalación/sitio conserva un solo schedule.
     * El cron se activa solo al contar con handler y esquema verificado; no
     * se requiere visita puntual para validar holds, dinero o plazos legales.
     */
    public static function ensureScheduled(): bool
    {
        if (null === self::$inboxHandler && null === self::$emailHandler) {
            return false;
        }
        if (!self::ready()) {
            return false;
        }
        if (false !== \wp_next_scheduled(self::HOOK)) {
            return true;
        }
        return true === \wp_schedule_event(
            \time() + 60, self::SCHEDULE, self::HOOK, [], true
        );
    }

    public static function unschedule(): void
    {
        \wp_clear_scheduled_hook(self::HOOK);
    }

    /** Acción interna WP-Cron: errores controlados, nunca confirmación de negocio. */
    public static function cronTick(): void
    {
        try {
            self::drain();
        } catch (\Throwable $ignored) {
            // Datos y fases de cola conservados; reintento/manual autorizado.
        }
    }

    /**
     * Alternativa de recuperación sin dependencia de visitas ni REST nuevo.
     * Un futuro panel puede invocarlo con nonce y capability específica.
     *
     * @return array{inbox:int,outbox:int}
     */
    public static function runForAdmin(): array
    {
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-roles.php';
        if ('A' !== Access_Roles::currentRole()
            || !\current_user_can('manage_options')
            || (\function_exists('is_network_admin') && \is_network_admin())) {
            throw new Transaction_Failure('pc_queue_admin_denied');
        }
        // El futuro endpoint administrativo deberá aportar además nonce
        // y finalidad de recuperación. No crear un endpoint en IMP-11.
        return self::drain();
    }

    /** @return array{inbox:int,outbox:int} */
    private static function drain(): array
    {
        Bootstrap::loadQueue();
        $count = ['inbox' => 0, 'outbox' => 0];
        if (!self::ready()) {
            return $count;
        }
        foreach (['inbox', 'outbox'] as $kind) {
            $handler = 'inbox' === $kind ? self::$inboxHandler : self::$emailHandler;
            if (null === $handler) {
                continue;
            }
            for ($i = 0; $i < self::MAX_BATCH; ++$i) {
                $lease = Transaction::run(
                    static fn (Transaction_Context $tx): ?array => self::claimNext($tx, $kind),
                    true
                );
                if (null === $lease) {
                    break;
                }
                ++$count[$kind];
                try {
                    Transaction::assertOutside();
                    // Handler debe verificar otra vez cuenta/recurso/estado MP,
                    // o resolver destinatario autorizado y plantilla desde DB.
                    // Nunca devolver secretos ni el texto completo de error.
                    $outcome = $handler($lease['job']);
                } catch (\Throwable $ignored) {
                    $outcome = ['result' => 'retry', 'error_code' => 'pc_worker_exception'];
                }
                $outcome = self::normalizeOutcome($kind, $outcome);
                // Si COMMIT de finish es ambiguo, no volver a enviar aquí.
                Transaction::run(
                    static function (Transaction_Context $tx) use ($kind, $lease, $outcome): void {
                        self::finish($tx, $kind, $lease, $outcome);
                    }
                );
            }
        }
        return $count;
    }

    private static function ready(): bool
    {
        try {
            $env = new Environment();
            return Maintenance::ready() && $env->has('database') && $env->has('schema');
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * Estado "processing" con next_*_at = fecha límite del lease.
     * Los vencidos pueden reclamarse tras caída, aumentando intento.
     * Locks FOR UPDATE: sin SELECT sin fila como fundamento de exclusión.
     *
     * @return array{id:string,attempt:int,job:array<string,mixed>}|null
     */
    private static function claimNext(Transaction_Context $tx, string $kind): ?array
    {
        $inbox = 'inbox' === $kind;
        $table = self::table($kind);
        $counter = $inbox ? 'retry_count' : 'attempt_count';
        $due = $inbox ? 'next_retry_at' : 'next_attempt_at';
        $columns = $inbox
            ? 'id, provider, HEX(provider_account_key) AS account_digest, environment, '
                . 'resource_id, action_code, HEX(payload_digest) AS payload_digest, '
                . 'technical_phase, retry_count, next_retry_at'
            : 'id, event_code, entity_type, entity_id, audience_code, channel, '
                . 'template_version, destination_ref, safe_payload, technical_phase, '
                . 'attempt_count, next_attempt_at, row_version';

        for ($scan = 0; $scan < self::MAX_BATCH; ++$scan) {
            $rows = $tx->rows(
                'SELECT ' . $columns . ' FROM %i WHERE '
                    . ($inbox ? '' : 'channel = %s AND ')
                    . '((technical_phase IN (%s, %s) AND ' . $due . ' <= UTC_TIMESTAMP(6)) '
                    . 'OR (technical_phase = %s AND ' . $due . ' <= UTC_TIMESTAMP(6))) '
                    . 'ORDER BY ' . $due . ', id LIMIT 1 FOR UPDATE',
                $inbox ? [$table, 'pending', 'retry', 'processing']
                    : [$table, 'email', 'pending', 'retry', 'processing']
            );
            if ([] === $rows) {
                return null;
            }
            $row = $rows[0];
            $id = Domain_Validation::sqlId((string) $row['id']);
            $attempt = (int) $row[$counter];
            if ($attempt >= self::MAX_ATTEMPTS) {
                // Un último intento pudo haber producido efectos externos antes
                // de caer: conservar "final_failed" para conciliación humana.
                $sql = 'UPDATE %i SET technical_phase = %s, ' . $due
                    . ' = NULL, last_error_code = %s'
                    . ($inbox ? '' : ', row_version = row_version + 1')
                    . ' WHERE id = %s AND technical_phase = %s AND ' . $counter . ' = %d';
                if (1 !== $tx->execute($sql, [$table, 'final_failed', 'pc_lease_exhausted',
                    $id, (string) $row['technical_phase'], $attempt])) {
                    throw new Transaction_Failure('pc_queue_claim_conflict');
                }
                continue;
            }

            $updated = $tx->execute(
                'UPDATE %i SET technical_phase = %s, ' . $counter
                    . ' = ' . $counter . ' + 1, '
                    . $due . ' = DATE_ADD(UTC_TIMESTAMP(6), INTERVAL '
                    . self::LEASE_SECONDS . ' SECOND), last_error_code = NULL'
                    . ($inbox ? '' : ', row_version = row_version + 1')
                    . ' WHERE id = %s AND technical_phase = %s AND ' . $counter . ' = %d',
                [$table, 'processing', $id, (string) $row['technical_phase'], $attempt]
            );
            if (1 !== $updated) {
                throw new Transaction_Failure('pc_queue_claim_conflict');
            }

            $job = $inbox ? [
                'inbox_id' => $id,
                'provider' => (string) $row['provider'],
                'account_digest' => \strtolower((string) $row['account_digest']),
                'environment' => (string) $row['environment'],
                'resource_id' => (string) $row['resource_id'],
                'action_code' => (string) $row['action_code'],
                'payload_digest' => \strtolower((string) $row['payload_digest']),
            ] : [
                'outbox_id' => $id,
                'event_code' => (string) $row['event_code'],
                'entity_type' => (string) $row['entity_type'],
                'entity_id' => (string) $row['entity_id'],
                'audience' => (string) $row['audience_code'],
                'channel' => (string) $row['channel'],
                'template_version' => (string) $row['template_version'],
                'destination_ref' => (string) $row['destination_ref'],
                'safe_payload' => (string) $row['safe_payload'],
            ];
            if (!$inbox && 'email' !== $job['channel']) {
                throw new Transaction_Failure('pc_queue_channel_denied');
            }

            return ['id' => $id, 'attempt' => $attempt + 1, 'job' => $job];
        }
        // No bloquear el cron por más trabajos exhaustos de lo que permite
        // un lote. El siguiente tick retoma desde el índice de próximos.
        return null;
    }

    /**
     * El handler devuelve solo resultado técnico tras procesar FUERA de SQL.
     * No acepta texto de error del transporte ni afirma entrega efectiva.
     *
     * @return array{result:string,error_code:?string}
     */
    private static function normalizeOutcome(string $kind, mixed $outcome): array
    {
        $allowed = 'inbox' === $kind
            ? ['processed', 'retry', 'final_failed']
            : ['accepted', 'needs_evidence', 'retry', 'final_failed'];
        if (!\is_array($outcome) || !\is_string($outcome['result'] ?? null)
            || !\in_array($outcome['result'], $allowed, true)) {
            return ['result' => 'retry', 'error_code' => 'pc_worker_invalid_result'];
        }
        $code = $outcome['error_code'] ?? null;
        // Mensajes crudos pueden contener PII, IP, secretos o ruta privada.
        // Solo códigos internos de categoría expresamente permitidos.
        if (!\in_array($code, [
            null, 'pc_worker_exception', 'pc_provider_unavailable',
            'pc_provider_uncertain', 'pc_provider_conflict',
            'pc_transport_unavailable', 'pc_transport_uncertain',
            'pc_recipient_invalid', 'pc_worker_invalid_result',
            'pc_manual_review_required',
        ], true)) {
            $code = 'pc_worker_invalid_result';
        }
        if ('retry' === $outcome['result'] && null === $code) {
            $code = 'pc_worker_exception';
        }
        if (\in_array($outcome['result'], ['accepted', 'processed'], true) && null !== $code) {
            return ['result' => 'retry', 'error_code' => 'pc_worker_invalid_result'];
        }
        if ('final_failed' === $outcome['result'] && null === $code) {
            $code = 'pc_manual_review_required';
        }
        return ['result' => $outcome['result'], 'error_code' => $code];
    }

    /**
     * CAS del lease: un worker vencido no puede modificar una fila reclamada
     * por otro. "accepted" indica solo transporte aceptado; para Libro se
     * conserva "needs_evidence", nunca se infiere entrega a una persona.
     *
     * @param array{id:string,attempt:int,job:array<string,mixed>} $lease
     * @param array{result:string,error_code:?string} $outcome
     */
    private static function finish(
        Transaction_Context $tx, string $kind, array $lease, array $outcome
    ): void {
        $inbox = 'inbox' === $kind;
        $table = self::table($kind);
        $counter = $inbox ? 'retry_count' : 'attempt_count';
        $due = $inbox ? 'next_retry_at' : 'next_attempt_at';
        $id = Domain_Validation::sqlId($lease['id']);
        $attempt = (int) $lease['attempt'];

        $row = $tx->rows(
            'SELECT technical_phase, ' . $counter . ', ' . $due
                . ' FROM %i WHERE id = %s FOR UPDATE',
            [$table, $id]
        );
        if (1 !== \count($row) || 'processing' !== $row[0]['technical_phase']
            || $attempt !== (int) $row[0][$counter]) {
            throw new Transaction_Failure('pc_queue_lease_lost');
        }

        $result = $outcome['result'];
        if ('retry' === $result && $attempt >= self::MAX_ATTEMPTS) {
            $result = 'final_failed';
        }
        $backoff = self::BACKOFF_SECONDS[\min($attempt - 1, \count(self::BACKOFF_SECONDS) - 1)];
        $next = 'retry' === $result
            ? 'DATE_ADD(UTC_TIMESTAMP(6), INTERVAL ' . $backoff . ' SECOND)' : 'NULL';
        $status = $result;

        $extra = '';
        if ($inbox && 'processed' === $result) {
            $extra = ', processed_at = UTC_TIMESTAMP(6)';
        }
        if (!$inbox) {
            $extra = ', row_version = row_version + 1';
            if (\in_array($result, ['accepted', 'needs_evidence'], true)) {
                $extra .= ', accepted_at = UTC_TIMESTAMP(6)';
            }
            if (\in_array($result, ['accepted', 'final_failed'], true)) {
                $extra .= ', completed_at = UTC_TIMESTAMP(6)';
            }
        }

        $errorSql = null === $outcome['error_code'] ? 'NULL' : '%s';
        $args = [$table, $status];
        if (null !== $outcome['error_code']) {
            $args[] = $outcome['error_code'];
        }
        $args[] = $id;
        $args[] = 'processing';
        $args[] = $attempt;

        $updated = $tx->execute(
            'UPDATE %i SET technical_phase = %s, ' . $due . ' = ' . $next
                . ', last_error_code = ' . $errorSql . $extra
                . ' WHERE id = %s AND technical_phase = %s AND '
                . $counter . ' = %d AND ' . $due . ' > UTC_TIMESTAMP(6)',
            $args
        );
        if (1 !== $updated) {
            throw new Transaction_Failure('pc_queue_lease_lost');
        }
    }

    private static function table(string $kind): string
    {
        return match ($kind) {
            'inbox' => Queue_Inbox::table(),
            'outbox' => Queue_Outbox::name('pc_outbox'),
            default => throw new Transaction_Failure('pc_queue_kind_denied'),
        };
    }
}
