<?php
/**
 * IMP-25 — emisión inicial irreversible de seguimiento privado.
 *
 * 256 bits de entropía; en SQL sólo HMAC por instalación/pedido, 90 días.
 * El secreto no entra en idempotencia, outbox, auditoría, logs ni replays.
 * La página de seguimiento debe configurarse desde WordPress fuera del TX;
 * IMP-31 implementa el canje por cookie y las lecturas privadas.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Checkout_Tracking
{
    private static ?string $pageUrl = null;

    /** Llamar fuera de Transaction::run: home_url puede consultar WP options. */
    public static function configurePageUrl(string $url): void
    {
        Transaction::assertOutside();
        $home = \home_url('/');
        $parsed = \wp_parse_url($url);
        $site = \wp_parse_url($home);
        if (!\is_array($parsed) || !\is_array($site)
            || ($parsed['scheme'] ?? '') !== 'https'
            || ($site['scheme'] ?? '') !== 'https'
            || !\is_string($parsed['host'] ?? null)
            || \strtolower($parsed['host']) !== \strtolower((string)($site['host'] ?? ''))
            || ($parsed['port'] ?? null) !== ($site['port'] ?? null)
            || isset($parsed['user']) || isset($parsed['pass'])
            || isset($parsed['query']) || isset($parsed['fragment'])) {
            throw new Transaction_Failure('pc_tracking_url_not_ready');
        }
        if (null !== self::$pageUrl && self::$pageUrl !== $url) {
            throw new \LogicException('pc_tracking_page_url_changed');
        }
        self::$pageUrl = $url;
    }

    /**
     * El pedido COD existe y todos sus recursos están comprometidos.
     * Invocar antes de completar idempotencia y dentro del mismo COMMIT.
     *
     * @return array{tracking_link_issued:bool,tracking_url_once:?string,created:bool}
     */
    public static function issueCashWithin(
        Transaction_Context $tx, Event_Actor $actor,
        string $operationId, string $orderId
    ): array {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        $order = $tx->lockedOrder($orderId);
        if ('cash' !== $order['payment_method'] || 'CONFIRMADO' !== $order['status']) {
            throw new Transaction_Failure('pc_tracking_order_invalid');
        }
        $lines = self::linesWithin($tx,$orderId);
        foreach ($lines as $line) {
            if ('digital' === $line['kind']) {
                throw new Transaction_Failure('pc_tracking_order_invalid');
            }
            if ('physical' === $line['kind']) {
                $rows = $tx->rows(
                    'SELECT id FROM %i WHERE order_line_id = %s '
                        . 'AND sellable_id = %s AND committed_units = %s',
                    [Orders_Domain::table('pc_stock_commitments'),$line['id'],
                        $line['sellable_id'],$line['quantity']]
                );
            } else {
                $rows = $tx->rows(
                    'SELECT id FROM %i WHERE order_id = %s AND order_line_id = %s',
                    [Orders_Domain::table('pc_appointments'),$orderId,$line['id']]
                );
            }
            if (1 !== \count($rows)) {
                throw new Transaction_Failure('pc_tracking_resource_incomplete');
            }
        }
        return self::issueWithin($tx,$actor,$operationId,$orderId,false);
    }

    /**
     * MP sin dinero acreditado todavía: sólo con intento oficial verificado
     * de la misma operación y holds vigentes para CADA recurso reservable.
     */
    public static function issueMpWithin(
        Transaction_Context $tx, Event_Actor $actor,
        string $operationId, string $orderId, string $paymentAttemptId
    ): array {
        $actor->assertCurrent();
        Checkout_Appointments::verifiedAttemptWithin(
            $tx,$operationId,$orderId,$paymentAttemptId
        );
        foreach (self::linesWithin($tx,$orderId) as $line) {
            if ('digital' === $line['kind']) {
                continue;
            }
            $kind = 'service' === $line['kind'] ? 'slot' : 'stock';
            $rows = $tx->rows(
                'SELECT id FROM %i WHERE order_id = %s AND order_line_id = %s '
                    . 'AND payment_attempt_id = %s AND resource_kind = %s '
                    . 'AND state = %s AND expires_at > UTC_TIMESTAMP(6)',
                [Agenda_Repository::table('pc_holds'),$orderId,$line['id'],
                    $paymentAttemptId,$kind,'VIGENTE']
            );
            if (1 !== \count($rows)) {
                throw new Transaction_Failure('pc_tracking_resource_incomplete');
            }
        }
        return self::issueWithin($tx,$actor,$operationId,$orderId,true);
    }

    private static function issueWithin(
        Transaction_Context $tx, Event_Actor $actor,
        string $operationId, string $orderId, bool $external
    ): array {
        if (null === self::$pageUrl) {
            throw new Transaction_Failure('pc_tracking_url_not_ready');
        }
        $orderId = Domain_Validation::sqlId($orderId);
        $tx->lockedOrder($orderId);
        $existing = $tx->rows(
            'SELECT id FROM %i WHERE order_id = %s AND credential_kind = %s '
                . 'ORDER BY id ASC FOR UPDATE',
            [self::table(),$orderId,'tracking']
        );
        if ([] !== $existing) {
            // Incluso si revocado/vencido: nunca reemitir sin verificación R8-58.
            return [
                'tracking_link_issued'=>true,
                'tracking_url_once'=>null,
                'created'=>false,
            ];
        }
        // La parte pública identifica solo la fila de pedido a buscar.
        // No concede acceso: el material secreto sigue siendo 256 bits
        // aleatorios y se autentica contra el HMAC del pedido/sitio.
        // Formato nuevo prelaunch pc1.<order_id>.<base64url-256-bit>.
        $secret = 'pc1.' . $orderId . '.'
            . \rtrim(\strtr(\base64_encode(\random_bytes(32)),'+/','-_'),'=');
        $key = \wp_salt('auth');
        if (!\is_string($key) || \strlen($key) < 32) {
            throw new Transaction_Failure('pc_tracking_secret_unavailable');
        }
        $digest = \hash_hmac(
            'sha256',
            'pc-tracking-v1:' . \get_current_blog_id() . ':' . $orderId . ':' . $secret,
            $key
        );
        if (1 !== $tx->execute(
            'INSERT INTO %i (order_id,credential_kind,token_hash,'
                . 'issued_at,expires_at) VALUES '
                . '(%s,%s,UNHEX(%s),UTC_TIMESTAMP(6),'
                . 'DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 90 DAY))',
            [self::table(),$orderId,'tracking',$digest]
        )) {
            throw new Transaction_Failure('pc_tracking_issue_failed');
        }

        if (!$external) {
            Event_Journal::auditWithin(
                $tx,$actor,$operationId,'order',$orderId,'tracking_link_issued'
            );
        } else {
            // La operación MP ya está en external_pending; no crear un
            // evento genérico con requirePendingOperation(prepared).
            // Este único hecho auditable no modifica estados ni fondos.
            $op = $tx->lockedOperation($operationId);
            if (!\in_array((string) $op['technical_phase'],
                    ['external_pending','reconcile'],true)) {
                throw new Transaction_Failure('pc_tracking_operation_invalid');
            }
            if (1 !== $tx->execute(
                'INSERT INTO %i (entity_type,entity_id,operation_code,'
                    . 'actor_kind,request_id,occurred_at) VALUES '
                    . '(%s,%s,%s,%s,UNHEX(%s),UTC_TIMESTAMP(6))',
                [Orders_Domain::table('pc_audit_log'),'order',$orderId,
                    'tracking_link_issued',$actor->kind(),
                    Event_Journal::correlationHex($operationId)]
            )) {
                throw new Transaction_Failure('pc_tracking_issue_failed');
            }
        }
        return [
            'tracking_link_issued'=>true,
            'tracking_url_once'=>self::$pageUrl . '#tracking=' . $secret,
            'created'=>true,
        ];
    }

    /**
     * Sólo invocar tras COMMIT inequívoco. Nunca en replay ni desde workers.
     * wp_mail(true) significa aceptación por el transporte, NO recepción.
     */
    public static function sendInitialEmailAfterCommit(
        array $issue, ?string $email
    ): bool {
        Transaction::assertOutside();
        if (true !== ($issue['created'] ?? false)
            || !\is_string($issue['tracking_url_once'] ?? null)
            || null === $email || false === \is_email($email)) {
            return false;
        }
        // No copiar a outbox, action scheduler, opciones o registro de errores.
        try {
            return true === \wp_mail(
                $email,
                'Seguimiento de tu pedido',
                "Este es el enlace privado para seguir tu pedido. "
                    . "No lo compartas. Caduca a los 90 días.\n\n"
                    . $issue['tracking_url_once'] . "\n\n"
                    . "Si pierdes el enlace, solicita recuperación verificada "
                    . "al comercio.\n"
            );
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    private static function table(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        return Schema::tableNames($wpdb)['pc_guest_sessions'];
    }

    private static function linesWithin(Transaction_Context $tx,string $orderId): array
    {
        $rows = $tx->rows(
            'SELECT id,sellable_id,kind,quantity FROM %i '
                . 'WHERE order_id = %s ORDER BY id ASC LIMIT 101',
            [Orders_Domain::table('pc_order_lines'),$orderId]
        );
        if ([] === $rows || \count($rows) > 100) {
            throw new Transaction_Failure('pc_tracking_order_invalid');
        }
        foreach ($rows as $line) {
            $tx->lockedOrderLine(Domain_Validation::sqlId((string) $line['id']));
        }
        return $rows;
    }
}
