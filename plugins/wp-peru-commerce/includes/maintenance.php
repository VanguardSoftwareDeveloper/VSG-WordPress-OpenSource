<?php
/**
 * IMP-68 — barrera de actualización y recuperación por instalación.
 *
 * No crea backups, restaura datos, modifica pagos ni decide que una cuenta
 * externa está conciliada. La salida del bloqueo exige un verificador REAL
 * aportado por la instalación; no se acepta una casilla del administrador.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Maintenance
{
    public const OPTION = 'perucommerce_maintenance_v1';
    private const BLOCK_ACTION = 'perucommerce_maintenance_block';
    private const RELEASE_ACTION = 'perucommerce_maintenance_release';
    private const NONCE = 'perucommerce_maintenance_control';

    /** @var callable|null Verificador independiente de respaldo/restauración, MP, caja y revocaciones. */
    private static $releaseVerifier = null;

    public static function registerReleaseVerifier(callable $verifier): void
    {
        if (null !== self::$releaseVerifier) {
            throw new \LogicException('pc_maintenance_verifier_duplicate');
        }
        self::$releaseVerifier = $verifier;
    }

    public static function registerAdminHooks(): void
    {
        \add_action('admin_notices', [self::class, 'adminNotice']);
        \add_action('admin_post_' . self::BLOCK_ACTION, [self::class, 'blockAction']);
        \add_action('admin_post_' . self::RELEASE_ACTION, [self::class, 'releaseAction']);
    }

    /** Instalada con estado cerrado incluso si se sustituyó el ZIP sin activar. */
    public static function blockedRecord(): string
    {
        return self::encode('blocked', '');
    }

    /**
     * Comprobación sin caché de WP. La lectura aislada protege los gates, pero
     * la garantía frente a una carrera es el LOCK IN SHARE MODE dentro del TX.
     */
    public static function ready(): bool
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb || !$wpdb->dbh instanceof \mysqli) {
            return false;
        }

        try {
            $sql = $wpdb->prepare(
                'SELECT option_value FROM %i WHERE option_name = %s',
                $wpdb->options,
                self::OPTION
            );
            $result = \mysqli_query($wpdb->dbh, $sql);
            if (!$result instanceof \mysqli_result) {
                return false;
            }
            try {
                $rows = \mysqli_fetch_all($result, \MYSQLI_ASSOC);
            } finally {
                \mysqli_free_result($result);
            }
            return 1 === \count($rows) && self::openRecord($rows[0]['option_value'] ?? null);
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * Operaciones no transaccionales (por ejemplo, streaming de un archivo
     * privado): revalidan la barrera antes y después de su trabajo.
     * No sustituye la espera operativa de peticiones FS en vuelo antes del backup.
     */
    public static function assertWritable(): void
    {
        if (!self::ready()) {
            throw new Transaction_Failure('pc_maintenance_blocked');
        }
    }

    /**
     * Cada TX de dominio bloquea en compartido la MISMA fila que el operador
     * actualiza en exclusivo al entrar a mantenimiento. Un cambio espera a
     * que terminen los TX previos; ningún TX posterior atraviesa la barrera.
     */
    public static function guardWithin(Transaction_Context $tx): void
    {
        global $wpdb;
        $rows = $tx->rows(
            'SELECT option_value FROM %i WHERE option_name = %s LOCK IN SHARE MODE',
            [$wpdb->options, self::OPTION]
        );
        if (1 !== \count($rows) || !self::openRecord($rows[0]['option_value'] ?? null)) {
            throw new Transaction_Failure('pc_maintenance_blocked');
        }
    }

    private static function epoch(): string
    {
        if (!\defined('PERUCOMMERCE_RECOVERY_EPOCH')
            || !\is_string(PERUCOMMERCE_RECOVERY_EPOCH)
            || 1 !== \preg_match('/\A[a-f0-9]{64}\z/D', PERUCOMMERCE_RECOVERY_EPOCH)) {
            throw new \RuntimeException('pc_recovery_epoch_unavailable');
        }
        return PERUCOMMERCE_RECOVERY_EPOCH;
    }

    private static function installationDigest(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new \RuntimeException('pc_maintenance_unverified');
        }
        return \hash('sha256', (string) $wpdb->dbname . ':' . (string) $wpdb->prefix);
    }

    private static function openRecord(mixed $raw): bool
    {
        if (!\is_string($raw) || \strlen($raw) > 1024) {
            return false;
        }
        try {
            $entry = \json_decode($raw, true, 8, \JSON_THROW_ON_ERROR);
            return \is_array($entry)
                && 1 === ($entry['version'] ?? null)
                && 'open' === ($entry['state'] ?? null)
                && ($entry['site_id'] ?? null) === \get_current_blog_id()
                && \is_string($entry['installation_digest'] ?? null)
                && \hash_equals(self::installationDigest(), $entry['installation_digest'])
                && \is_string($entry['epoch_digest'] ?? null)
                && \hash_equals(\hash('sha256', self::epoch()), $entry['epoch_digest']);
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    private static function encode(string $state, string $epoch): string
    {
        return (string) \wp_json_encode([
            'version' => 1,
            'state' => $state,
            'site_id' => \get_current_blog_id(),
            'installation_digest' => self::installationDigest(),
            // El valor externo no se copia a wp_options ni al backup SQL.
            'epoch_digest' => '' === $epoch ? '' : \hash('sha256', $epoch),
            'changed_at_utc' => \gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    public static function adminNotice(): void
    {
        if (!\current_user_can('manage_options')
            || (\function_exists('is_network_admin') && \is_network_admin())) {
            return;
        }
        $ready = self::ready();
        $result = isset($_GET['pc_maintenance_result']) && \is_string($_GET['pc_maintenance_result'])
            ? \sanitize_key(\wp_unslash($_GET['pc_maintenance_result'])) : '';

        if ($ready && '' === $result) {
            // No crear ruido en wp-admin cuando el comercio ya fue liberado.
            return;
        }

        echo '<div class="notice notice-warning"><p>'
            . \esc_html($ready
                ? 'PeruCommerce: barrera operativa abierta solo para este punto de recuperación.'
                : 'PeruCommerce: operaciones mutables bloqueadas por actualización/recuperación. Lecturas compatibles y canales legales se evalúan por separado.')
            . '</p>';
        if ('' !== $result) {
            echo '<p>' . \esc_html('Resultado técnico: ' . self::publicCode($result)) . '</p>';
        }
        self::button(self::BLOCK_ACTION, 'Bloquear operaciones para mantenimiento');
        if (!$ready && null !== self::$releaseVerifier) {
            self::button(self::RELEASE_ACTION, 'Verificar y reabrir operaciones');
        }
        if (!$ready && null === self::$releaseVerifier) {
            echo '<p>' . \esc_html('No hay verificador independiente de restauración/conciliación conectado. La reapertura permanece bloqueada.') . '</p>';
        }
        echo '</div>';
    }

    private static function button(string $action, string $label): void
    {
        echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . \esc_attr($action) . '">';
        \wp_nonce_field(self::NONCE);
        \submit_button($label, 'secondary', 'submit', false);
        echo '</form>';
    }

    public static function blockAction(): void
    {
        self::requireAdmin();
        $result = 'pc_maintenance_blocked';
        try {
            self::withInstallLock(static function (\wpdb $db): void {
                self::writeState($db, self::blockedRecord());
            });
        } catch (\Throwable $ignored) {
            $result = 'pc_maintenance_failed';
        }
        self::redirect($result);
    }

    public static function releaseAction(): void
    {
        self::requireAdmin();
        $result = 'pc_maintenance_unverified';
        try {
            $epoch = self::epoch();
            if (null === self::$releaseVerifier) {
                throw new \RuntimeException('pc_maintenance_unverified');
            }
            self::withInstallLock(static function (\wpdb $db) use ($epoch): void {
                // La verificación se hace CON el lock de instalación; el
                // upgrade no puede terminar o empezar entre prueba y reapertura.
                // Un callback externo debe contrastar pruebas de verdad actual,
                // no reciclar un snapshot de aprobación anterior.
                $evidence = (self::$releaseVerifier)();
                self::requireReleaseEvidence($evidence, $epoch);
                $environment = new Environment();
                foreach (['platform', 'commerce_php', 'https', 'database', 'schema'] as $requirement) {
                    if (!$environment->has($requirement)) {
                        throw new \RuntimeException('pc_maintenance_unverified');
                    }
                }
                require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
                require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema-inspector.php';
                (new Schema_Inspector($db))->verifyAll();

                // El cambio atómico de una fila es la barrera de salida.
                // Sin evidencia externa probada no se llama a este UPDATE.
                self::writeState($db, self::encode('open', $epoch), true);
            });
            $result = 'pc_maintenance_released';
        } catch (\Throwable $ignored) {
            // Una respuesta/commit incierto nunca autoriza repetir efectos
            // comerciales; el estado se comprueba de nuevo por cada TX.
        }
        self::redirect($result);
    }

    /** La evidencia es un informe VERIFICADO, no texto/checkbox del navegador. */
    private static function requireReleaseEvidence(mixed $proof, string $epoch): void
    {
        if (!\is_array($proof)
            || true !== ($proof['verified'] ?? false)
            || ($proof['site_id'] ?? null) !== \get_current_blog_id()
            || ($proof['installation_digest'] ?? null) !== self::installationDigest()
            || ($proof['schema_version'] ?? null) !== PERUCOMMERCE_SCHEMA_VERSION
            || ($proof['plugin_version'] ?? null) !== PERUCOMMERCE_VERSION
            || ($proof['recovery_epoch_digest'] ?? null) !== \hash('sha256', $epoch)
            || !\is_string($proof['manifest_id'] ?? null)
            || 1 !== \preg_match('/\A[a-f0-9]{64}\z/D', $proof['manifest_id'])
            || !\is_array($proof['components'] ?? null)) {
            throw new \RuntimeException('pc_maintenance_unverified');
        }
        foreach ([
            'database', 'private_files', 'configuration', 'external_cutoff',
            'mp_and_cash_reconciled', 'resources_consistent', 'revocations_reviewed',
            'queue_reviewed', 'assets_compatible',
        ] as $component) {
            if (true !== ($proof['components'][$component] ?? false)) {
                throw new \RuntimeException('pc_maintenance_unverified');
            }
        }
        $cutoff = $proof['cutoff_utc'] ?? null;
        if (!\is_string($cutoff) || 1 !== \preg_match(
            '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D', $cutoff
        )) {
            throw new \RuntimeException('pc_maintenance_unverified');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $cutoff, new \DateTimeZone('UTC'));
        if (false === $parsed || $parsed->format('Y-m-d\TH:i:s\Z') !== $cutoff
            || $parsed->getTimestamp() > \time() + 300) {
            throw new \RuntimeException('pc_maintenance_unverified');
        }
    }

    private static function requireAdmin(): void
    {
        if (!\current_user_can('manage_options')
            || (\function_exists('is_network_admin') && \is_network_admin())) {
            \wp_die('No autorizado.', '', ['response' => 403]);
        }
        \check_admin_referer(self::NONCE);
    }

    private static function withInstallLock(callable $operation): void
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb || !$wpdb->dbh instanceof \mysqli) {
            throw new \RuntimeException('pc_maintenance_failed');
        }
        // Mismo lock de sesión que Installer: nunca liberar mantenimiento
        // mientras un upgrade DDL siga ejecutándose en otra petición.
        $lock = 'pc_install_' . \substr(\hash(
            'sha256', (string) $wpdb->dbname . ':' . (string) $wpdb->prefix
        ), 0, 40);
        $sql = $wpdb->prepare('SELECT GET_LOCK(%s, 0) AS acquired', $lock);
        $result = \mysqli_query($wpdb->dbh, $sql);
        if (!$result instanceof \mysqli_result) {
            throw new \RuntimeException('pc_maintenance_failed');
        }
        try {
            $row = \mysqli_fetch_assoc($result);
        } finally {
            \mysqli_free_result($result);
        }
        if (!\is_array($row) || '1' !== (string) ($row['acquired'] ?? '')) {
            throw new \RuntimeException('pc_maintenance_locked');
        }
        try {
            $operation($wpdb);
        } finally {
            // Si se perdió la conexión, no se presume que la barrera cambió.
            try {
                \mysqli_query($wpdb->dbh, $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
            } catch (\Throwable $ignored) {
            }
        }
    }

    private static function writeState(\wpdb $db, string $value, bool $requireExisting = false): void
    {
        // Un callback externo podría reabrir la conexión WP sin conservar
        // su GET_LOCK. Comprobar propiedad efectiva EN ESTA conexión antes
        // de mutar el marcador de mantenimiento.
        $name = 'pc_install_' . \substr(\hash(
            'sha256', (string) $db->dbname . ':' . (string) $db->prefix
        ), 0, 40);
        $ownership = \mysqli_query($db->dbh, $db->prepare(
            'SELECT IS_USED_LOCK(%s) AS owner_id, CONNECTION_ID() AS connection_id',
            $name
        ));
        if (!$ownership instanceof \mysqli_result) {
            throw new \RuntimeException('pc_maintenance_locked');
        }
        try {
            $row = \mysqli_fetch_assoc($ownership);
        } finally {
            \mysqli_free_result($ownership);
        }
        if (!\is_array($row) || null === ($row['owner_id'] ?? null)
            || (string) $row['owner_id'] !== (string) ($row['connection_id'] ?? '')) {
            throw new \RuntimeException('pc_maintenance_locked');
        }
        if ($requireExisting) {
            $sql = $db->prepare(
                'UPDATE %i SET option_value = %s, autoload = %s WHERE option_name = %s',
                $db->options, $value, 'off', self::OPTION
            );
        } else {
            $sql = $db->prepare(
                'INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s) '
                    . 'ON DUPLICATE KEY UPDATE option_value = %s, autoload = %s',
                $db->options, self::OPTION, $value, 'off', $value, 'off'
            );
        }
        $ok = \mysqli_query($db->dbh, $sql);
        if (true !== $ok || ($requireExisting && 1 !== \mysqli_affected_rows($db->dbh))) {
            throw new \RuntimeException('pc_maintenance_failed');
        }
        if (\function_exists('wp_cache_delete')) {
            \wp_cache_delete(self::OPTION, 'options');
            \wp_cache_delete('alloptions', 'options');
        }
    }

    private static function redirect(string $result): void
    {
        \wp_safe_redirect(\add_query_arg(
            'pc_maintenance_result', self::publicCode($result), \admin_url('plugins.php')
        ));
        exit;
    }

    private static function publicCode(string $code): string
    {
        return \in_array($code, [
            'pc_maintenance_blocked', 'pc_maintenance_failed',
            'pc_maintenance_unverified', 'pc_maintenance_released',
        ], true) ? $code : 'pc_maintenance_failed';
    }
}
