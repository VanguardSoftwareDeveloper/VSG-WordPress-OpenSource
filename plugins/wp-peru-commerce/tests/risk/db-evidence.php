<?php
/**
 * IMP-69 — evidencia SQL de solo lectura DESPUÉS de ejecutar escenarios P-C.
 *
 * Futuro (solo staging aislado con autorización):
 *   PC_RISK_ALLOW_DB_READ=I_CONFIRM_ISOLATED_STAGING wp eval-file tests/risk/db-evidence.php
 *
 * No instala, no actualiza, no inicia TX, no limpia datos y no consulta MP.
 * Los contadores NO prueban ausencia de carreras: deben compararse junto a
 * un before/after de dos sesiones concurrentes y correlación con proveedor.
 */
declare(strict_types=1);

if (!\defined('WP_CLI') || !WP_CLI
    || \getenv('PC_RISK_ALLOW_DB_READ') !== 'I_CONFIRM_ISOLATED_STAGING'
    || !\defined('ABSPATH')) {
    throw new \RuntimeException('pc_risk_db_requires_explicit_wp_cli_staging');
}

$host = \parse_url((string) \home_url('/'), \PHP_URL_HOST);
if (!\is_string($host) || !\in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    throw new \RuntimeException('pc_risk_db_denied_nonlocal_site');
}

global $wpdb;
if (!$wpdb instanceof \wpdb || !$wpdb->dbh instanceof \mysqli) {
    throw new \RuntimeException('pc_risk_db_connection_unavailable');
}
// El sitio instalado debe corresponder al plugin actualmente cargado; no
// inferir paths alternativos ni asumir otra instalación/versión.
if (!\defined('PERUCOMMERCE_PLUGIN_DIR')
    || !\is_file(PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php')) {
    throw new \RuntimeException('pc_risk_plugin_not_loaded');
}
require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';

$tables = \PeruCommerce\Schema::tableNames($wpdb);

/** Consulta SELECT con identificadores preparados por wpdb. Devuelve SOLO un conteo. */
function riskCount(\wpdb $db, string $sql, array $arguments): int
{
    $prepared = $db->prepare($sql, ...$arguments);
    if (!\is_string($prepared)) {
        throw new \RuntimeException('pc_risk_prepare_failed');
    }
    $previous = $db->suppress_errors(true);
    try {
        $count = $db->get_var($prepared);
        if ('' !== $db->last_error || !\is_string($count) && !\is_int($count)) {
            throw new \RuntimeException('pc_risk_query_failed');
        }
        if (!\ctype_digit((string) $count)) {
            throw new \RuntimeException('pc_risk_invalid_counter');
        }
        return (int) $count;
    } finally {
        $db->suppress_errors($previous);
    }
}

$counts = [];
$counts['order_money_inconsistent'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM %i WHERE currency <> %s OR total_cents <> subtotal_cents + delivery_cents',
    [$tables['pc_orders'], 'PEN']);
$counts['duplicate_initial_charge_orders'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM (SELECT order_id FROM %i WHERE kind = %s GROUP BY order_id HAVING COUNT(*) > 1) AS duplicates',
    [$tables['pc_payment_events'], 'initial_charge']);
$counts['stock_commitments_broken'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM %i WHERE fulfilled_units > committed_units '
        . 'OR released_units > committed_units - fulfilled_units',
    [$tables['pc_stock_commitments']]);
$counts['duplicate_appointments_per_order'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM (SELECT order_id FROM %i GROUP BY order_id HAVING COUNT(*) > 1) AS duplicates',
    [$tables['pc_appointments']]);
$counts['overlapping_live_appointments'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM %i a INNER JOIN %i b ON a.id < b.id '
        . 'AND a.resource_id = b.resource_id AND a.starts_at_utc < b.ends_at_utc '
        . 'AND b.starts_at_utc < a.ends_at_utc '
        . 'WHERE a.cancelled_at IS NULL AND b.cancelled_at IS NULL',
    [$tables['pc_appointments'], $tables['pc_appointments']]);
$counts['holds_exceeding_original_15m'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM %i h INNER JOIN %i a ON a.id = h.payment_attempt_id '
        . 'WHERE a.last_verified_at IS NOT NULL '
        . 'AND h.expires_at > DATE_ADD(a.last_verified_at, INTERVAL 15 MINUTE)',
    [$tables['pc_holds'], $tables['pc_payment_attempts']]);
$counts['rights_beyond_explicit_limit'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM %i WHERE optional_limit IS NOT NULL AND download_count > optional_limit',
    [$tables['pc_download_rights']]);
$counts['tokens_longer_than_24h'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM %i WHERE expires_at > DATE_ADD(issued_at, INTERVAL 24 HOUR)',
    [$tables['pc_download_tokens']]);
$counts['idempotency_scope_duplicates'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM (SELECT scope_hash,key_hash FROM %i '
        . 'GROUP BY scope_hash,key_hash HAVING COUNT(*) > 1) AS duplicates',
    [$tables['pc_idempotency']]);
$counts['external_refunds_without_source'] = riskCount($wpdb,
    'SELECT COUNT(*) FROM %i WHERE kind = %s AND source_payment_event_id IS NULL',
    [$tables['pc_payment_events'], 'external_refund']);

$violations = [];
foreach ($counts as $key => $count) {
    if ($count !== 0) {
        $violations[] = $key;
    }
}
\WP_CLI::log((string) \wp_json_encode([
    'schema' => 'perucommerce-imp69-db-snapshot-v1',
    'result' => [] === $violations ? 'no_local_violation_found' : 'local_violation_detected',
    'site_id' => \get_current_blog_id(),
    'checks' => $counts,
    'violations' => $violations,
    'mp_externally_verified' => false,
    'concurrency_verified' => false,
    'restore_verified' => false,
]));
if ([] !== $violations) {
    \WP_CLI::error('pc_risk_local_invariants_broken');
}
