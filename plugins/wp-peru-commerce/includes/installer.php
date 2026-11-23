<?php
/**
 * IMP-05: instalación controlada, journal y recuperación del esquema.
 * Nunca se ejecuta DDL desde el frontend, plugins_loaded o una actualización ZIP.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Installer
{
    private const ACTION = 'perucommerce_install_schema';
    private const NONCE = 'perucommerce_install_schema_nonce';

    private const STATE = 'perucommerce_migration_state';
    private const SCHEMA = 'perucommerce_schema_version';
    private const VERIFIED = 'perucommerce_schema_verified_version';
    private const PLUGIN = 'perucommerce_plugin_version';

    /** @var callable|null Verificador real de backup coherente, integrado en fase posterior. */
    private static $backupVerifier = null;

    private \wpdb $db;
    private Schema_Inspector $inspector;
    private string $lockName;

    public static function registerAdminHooks(): void
    {
        \add_action('admin_notices', [self::class, 'adminNotice']);
        \add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
    }

    /**
     * Se registra en el archivo principal, fuera de plugins_loaded.
     * Activar el plugin no ejecuta DDL y no sobreescribe datos o estado previo.
     */
    public static function activate(bool $networkWide = false): void
    {
        if ($networkWide) {
            \wp_die('PeruCommerce no admite activación en red.', '', ['response' => 403]);
        }
        if (!\current_user_can('activate_plugins')) {
            \wp_die('No autorizado.', '', ['response' => 403]);
        }

        // Únicamente en primera activación; un reingreso conserva el journal.
        \add_option(self::STATE, 'incomplete', '', false);
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/maintenance.php';
        \add_option(Maintenance::OPTION, Maintenance::blockedRecord(), '', false);
    }

    public static function deactivate(): void
    {
        // Conserva pedidos, tablas, opciones, verificadores, evidencias y derechos.
    }

    /**
     * No aceptar una casilla del panel como prueba de backup. Para upgrades,
     * el verificador real debe comprobar restaurabilidad y coherencia de DB,
     * binarios privados, configuración, versión y corte/conciliación.
     */
    public static function registerBackupVerifier(callable $verifier): void
    {
        if (null !== self::$backupVerifier) {
            throw new \LogicException('pc_backup_verifier_duplicate');
        }
        self::$backupVerifier = $verifier;
    }

    public static function adminNotice(): void
    {
        if (!\current_user_can('manage_options') || (\function_exists('is_network_admin') && \is_network_admin())) {
            return;
        }

        try {
            $installer = self::create();
            if (null === $installer) {
                return;
            }
            $state = $installer->marker(self::STATE);
            $version = $installer->marker(self::SCHEMA);
            $verified = $installer->marker(self::VERIFIED);
            $plugin = $installer->marker(self::PLUGIN);
        } catch (\Throwable $ignored) {
            // No inutilizar wp-admin si el motor falla al leer un diagnóstico.
            echo '<div class="notice notice-error"><p>'
                . \esc_html('PeruCommerce: no se pudo comprobar el estado del esquema; comercio bloqueado.')
                . '</p></div>';
            return;
        }
        $message = isset($_GET['pc_install_result']) && \is_string($_GET['pc_install_result'])
            ? \sanitize_key(\wp_unslash($_GET['pc_install_result'])) : '';
        if ('complete' === $state && PERUCOMMERCE_SCHEMA_VERSION === $version
            && PERUCOMMERCE_SCHEMA_VERSION === $verified && PERUCOMMERCE_VERSION === $plugin) {
            if ('pc_install_complete' === $message) {
                echo '<div class="notice notice-success"><p>'
                    . \esc_html('PeruCommerce: migración estructural registrada. La habilitación comercial sigue sujeta a las verificaciones de cada módulo.')
                    . '</p></div>';
            }
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>PeruCommerce:</strong> ';
        echo \esc_html('El esquema no está verificado para esta versión. Las operaciones comerciales permanecen bloqueadas.');
        if ('' !== $version && PERUCOMMERCE_SCHEMA_VERSION !== $version) {
            echo ' ' . \esc_html('La actualización requiere una migración de versión compatible y respaldo verificable.');
        } elseif ('' !== $version && PERUCOMMERCE_VERSION !== $plugin) {
            echo ' ' . \esc_html('Antes de actualizar una instalación existente se exige copia coherente comprobada.');
        }
        if ('' !== $message) {
            echo ' ' . \esc_html('Diagnóstico: ' . self::publicCode($message) . '.');
        }
        echo '</p><form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . \esc_attr(self::ACTION) . '">';
        \wp_nonce_field(self::NONCE);
        \submit_button('Verificar e instalar o reanudar esquema', 'secondary', 'submit', false);
        echo '</form></div>';
    }

    public static function handle(): void
    {
        if (!\current_user_can('manage_options') || (\function_exists('is_network_admin') && \is_network_admin())) {
            \wp_die('No autorizado.', '', ['response' => 403]);
        }
        \check_admin_referer(self::NONCE);

        $result = 'pc_install_failed';
        try {
            $installer = self::create();
            if (null === $installer) {
                throw new \RuntimeException('pc_db_unavailable');
            }
            $installer->installOrResume();
            $result = 'pc_install_complete';
        } catch (\Throwable $error) {
            $result = self::publicCode($error->getMessage());
        }
        \wp_safe_redirect(\add_query_arg(
            'pc_install_result', $result, \admin_url('plugins.php')
        ));
        exit;
    }

    private static function publicCode(string $code): string
    {
        $allowed = [
            'pc_install_complete', 'pc_install_failed', 'pc_db_unavailable',
            'pc_install_preflight', 'pc_install_locked', 'pc_backup_required',
            'pc_backup_unverified', 'pc_schema_upgrade_unsupported', 'pc_schema_upgrade_failed',
            'pc_schema_unowned_existing', 'pc_schema_version_conflict',
            'pc_schema_create_failed', 'pc_schema_fk_failed',
            'pc_schema_seed_failed', 'pc_schema_verify_failed',
            'pc_schema_marker_failed', 'pc_schema_journal_failed',
            'pc_schema_engine_mismatch', 'pc_schema_table_missing',
            'pc_schema_columns_mismatch', 'pc_schema_charset_mismatch',
            'pc_schema_index_mismatch', 'pc_schema_check_mismatch',
            'pc_schema_fk_mismatch', 'pc_schema_fk_missing',
            'pc_schema_anchor_invalid', 'pc_schema_metadata_unavailable',
        ];
        return \in_array($code, $allowed, true) ? $code : 'pc_install_failed';
    }

    private static function create(): ?self
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return null;
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema-inspector.php';
        return new self($wpdb);
    }

    private function __construct(\wpdb $db)
    {
        $this->db = $db;
        $this->inspector = new Schema_Inspector($db);
        $this->lockName = 'pc_install_' . \substr(\hash(
            'sha256', (string) $db->dbname . ':' . (string) $db->prefix
        ), 0, 40);
    }

    private function installOrResume(): void
    {
        $this->preflight();
        $rows = $this->inspector->rows($this->db->prepare(
            'SELECT GET_LOCK(%s, 0) AS acquired', $this->lockName
        ));
        if (1 !== \count($rows) || '1' !== (string) $rows[0]['acquired']) {
            throw new \RuntimeException('pc_install_locked');
        }

        try {
            $this->runExclusive();
        } finally {
            // Bloqueo de sesión: el COMMIT de DDL no lo libera, solo RELEASE_LOCK.
            try {
                $this->inspector->rows($this->db->prepare(
                    'SELECT RELEASE_LOCK(%s) AS released', $this->lockName
                ));
            } catch (\Throwable $ignored) {
                // La conexión termina/recupera el lock; no declarar desbloqueo comprobado.
            }
        }
    }

    private function preflight(): void
    {
        $environment = new Environment();
        foreach (['platform', 'commerce_php', 'database', 'https'] as $requirement) {
            if (!$environment->has($requirement)) {
                throw new \RuntimeException('pc_install_preflight');
            }
        }

        // WordPress options conserva versiones/estado: requiere integridad transaccional.
        $rows = $this->inspector->rows($this->db->prepare(
            'SELECT ENGINE FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $this->db->options
        ));
        if (1 !== \count($rows) || 'innodb' !== \strtolower((string) $rows[0]['ENGINE'])) {
            throw new \RuntimeException('pc_install_preflight');
        }

        $page = $this->inspector->rows('SELECT @@innodb_page_size AS size');
        if (1 !== \count($page) || (int) $page[0]['size'] < 16384) {
            // No reducir silenciosamente las dos UQ largas de docs/23.
            throw new \RuntimeException('pc_install_preflight');
        }
        // Rechaza charset/colación incompatible y prefijos de >64 caracteres.
        $definitions = Schema::tableStatements($this->db);
        if (39 !== \count($definitions) || 56 !== \count(Schema::foreignKeyStatements($this->db))) {
            throw new \RuntimeException('pc_install_preflight');
        }
    }

    private function runExclusive(): void
    {
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/maintenance.php';
        // Conservar una barrera realmente ABIERTA solo para la re-inspección
        // exacta de la misma versión; nunca para upgrade, error o restore.
        $previouslyReady = Maintenance::ready();
        $previousMaintenance = $previouslyReady ? $this->marker(Maintenance::OPTION) : '';
        // Una actualización/re-inspección espera a los TX previos y bloquea
        // cualquier TX nuevo mediante la fila compartida de wp_options.
        $this->writeMarker(Maintenance::OPTION, Maintenance::blockedRecord());
        $installed = $this->marker(self::SCHEMA);
        $installedPlugin = $this->marker(self::PLUGIN);
        $state = $this->marker(self::STATE);
        // Solo se admiten las dos expansiones históricas conocidas de
        // pc_payment_events. Un ZIP antiguo nunca hace rollback del SQL.
        $upgrading = '' !== $installed && PERUCOMMERCE_SCHEMA_VERSION !== $installed;
        if ($upgrading && !\in_array($installed, ['1.0.0', '1.0.1'], true)) {
            throw new \RuntimeException('pc_schema_upgrade_unsupported');
        }

        if (PERUCOMMERCE_SCHEMA_VERSION === $installed
            && PERUCOMMERCE_VERSION === $installedPlugin && 'complete' === $state
            && PERUCOMMERCE_SCHEMA_VERSION === $this->marker(self::VERIFIED)) {
            // Incluso una revisión manual de la misma versión se realiza con
            // barrera activa: si aparece deriva física, nunca queda "complete".
            $this->writeMarker(self::STATE, 'incomplete');
            $this->writeMarker(self::VERIFIED, '');
            $this->inspector->verifyAll();
            $this->writeMarker(self::VERIFIED, PERUCOMMERCE_SCHEMA_VERSION);
            $this->writeMarker(self::STATE, 'complete');
            if ($previouslyReady) {
                // Es la MISMA prueba de estado abierto que ya existía,
                // preservada bajo el lock de instalación. No se certifica
                // un backup nuevo ni se habilita una versión distinta.
                $this->writeMarker(Maintenance::OPTION, $previousMaintenance);
            }
            return;
        }

        $hasPreviousRelease = '' !== $installed || '' !== $installedPlugin;
        if ($hasPreviousRelease) {
            $this->requireBackupEvidence($installed, $installedPlugin);
        }

        // Primera instalación parcial solo puede reentrar con journal propio.
        $definitions = Schema::tableStatements($this->db);
        $journalExists = $this->inspector->tableExists('pc_migrations');
        if ($journalExists) {
            $this->inspector->verifyTable('pc_migrations', $definitions['pc_migrations']);
            $rows = $this->inspector->rows($this->db->prepare(
                'SELECT version FROM %i LIMIT 1', Schema::tableNames($this->db)['pc_migrations']
            ));
            if ([] === $rows) {
                foreach (Schema::suffixes() as $suffix) {
                    if ('pc_migrations' !== $suffix && $this->inspector->tableExists($suffix)) {
                        throw new \RuntimeException('pc_schema_unowned_existing');
                    }
                }
            } elseif (!$this->journalOwned()) {
                throw new \RuntimeException('pc_schema_version_conflict');
            }
        } else {
            foreach (Schema::suffixes() as $suffix) {
                if ('pc_migrations' !== $suffix && $this->inspector->tableExists($suffix)) {
                    throw new \RuntimeException('pc_schema_unowned_existing');
                }
            }
        }

        if ($upgrading) {
            if (!$journalExists || !$this->journalOwned()) {
                throw new \RuntimeException('pc_schema_version_conflict');
            }
            $this->upgradeKnownSchema($installed);
            return;
        }

        $this->writeMarker(self::STATE, 'incomplete');
        $this->writeMarker(self::VERIFIED, '');

        if (!$journalExists) {
            $this->writeSql($definitions['pc_migrations'], 'pc_schema_create_failed');
            $this->inspector->verifyTable('pc_migrations', $definitions['pc_migrations']);
        }

        try {
            $this->recordStage('tables', 'running');
            foreach ($definitions as $suffix => $sql) {
                if (!$this->inspector->tableExists($suffix)) {
                    $this->writeSql($sql, 'pc_schema_create_failed');
                }
                $this->inspector->verifyTable($suffix, $sql);
            }
            $this->recordStage('tables', 'complete');

            $this->recordStage('foreign_keys', 'running');
            foreach (Schema::foreignKeyStatements($this->db) as $sql) {
                if (!$this->inspector->foreignKeyExists($sql)) {
                    $this->writeSql($sql, 'pc_schema_fk_failed');
                }
                if (!$this->inspector->foreignKeyExists($sql)) {
                    throw new \RuntimeException('pc_schema_fk_failed');
                }
            }
            $this->recordStage('foreign_keys', 'complete');

            $this->recordStage('resource', 'running');
            $this->seedResource();
            $this->inspector->verifyAnchor();
            $this->recordStage('resource', 'complete');

            $this->recordStage('verified', 'running');
            $this->inspector->verifyAll();
            $this->recordStage('verified', 'complete');

            // La bandera COMPLETE va última. Un cierre interrumpido permanece bloqueado.
            $this->writeMarker(self::SCHEMA, PERUCOMMERCE_SCHEMA_VERSION);
            $this->writeMarker(self::PLUGIN, PERUCOMMERCE_VERSION);
            $this->writeMarker(self::VERIFIED, PERUCOMMERCE_SCHEMA_VERSION);
            $this->writeMarker(self::STATE, 'complete');
        } catch (\Throwable $error) {
            // El DDL puede confirmar parcialmente: nunca prometer ROLLBACK del esquema.
            try {
                $this->writeMarker(self::STATE, 'incomplete');
                $this->writeMarker(self::VERIFIED, '');
            } catch (\Throwable $ignored) {
                // Un fallo al escribir marcadores requiere recuperación manual.
            }
            throw $error;
        }
    }

    /**
     * El callback de respaldo es un límite de confianza EXTERNO: debe
     * contrastar bytes y época real de DB+privados+config+fuente, restaurabilidad
     * y el corte financiero. Solo verificamos que su atestación esté ligada
     * al sitio/instalación/versiones precisas; no simulamos el backup.
     */
    private function requireBackupEvidence(string $schema, string $plugin): void
    {
        if (null === self::$backupVerifier) {
            throw new \RuntimeException('pc_backup_required');
        }
        try {
            $evidence = (self::$backupVerifier)();
            $identity = \hash('sha256', (string) $this->db->dbname . ':' . (string) $this->db->prefix);
            if (!\is_array($evidence)
                || true !== ($evidence['verified'] ?? false)
                || !\is_string($evidence['manifest_id'] ?? null)
                || 1 !== \preg_match('/\A[a-f0-9]{64}\z/D', $evidence['manifest_id'])
                || ($evidence['site_id'] ?? null) !== \get_current_blog_id()
                || ($evidence['installation_digest'] ?? null) !== $identity
                || ($evidence['schema_version'] ?? null) !== $schema
                || ($evidence['plugin_version'] ?? null) !== $plugin
                || !\is_array($evidence['components'] ?? null)) {
                throw new \RuntimeException('pc_backup_unverified');
            }
            foreach ([
                'database', 'private_files', 'configuration', 'external_cutoff',
                'plugin_assets', 'migration_journal',
            ] as $component) {
                if (true !== ($evidence['components'][$component] ?? false)) {
                    throw new \RuntimeException('pc_backup_unverified');
                }
            }
            $cutoff = $evidence['cutoff_utc'] ?? null;
            if (!\is_string($cutoff) || 1 !== \preg_match(
                '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D',
                $cutoff
            )) {
                throw new \RuntimeException('pc_backup_unverified');
            }
            $parsed = \DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i:s\Z', $cutoff, new \DateTimeZone('UTC')
            );
            if (false === $parsed || $parsed->format('Y-m-d\TH:i:s\Z') !== $cutoff
                || $parsed->getTimestamp() > \time() + 300) {
                throw new \RuntimeException('pc_backup_unverified');
            }
        } catch (\Throwable $ignored) {
            throw new \RuntimeException('pc_backup_unverified');
        }
    }

    /**
     * Admitir journal heredado y etapas de expansión reentrantes solo cuando
     * su digest corresponde a la definición conocida de ESE esquema.
     * Un hash distinto no se "repara" reescribiendo la bitácora.
     */
    private function journalOwned(): bool
    {
        $rows = $this->inspector->rows($this->db->prepare(
            'SELECT version, HEX(checksum) AS digest FROM %i',
            Schema::tableNames($this->db)['pc_migrations']
        ));
        if ([] === $rows) {
            return false;
        }
        foreach ($rows as $row) {
            $entry = (string) $row['version'];
            if (1 !== \preg_match(
                '/\A(1\.0\.[012]):(tables|foreign_keys|resource|verified|upgrade_correction|upgrade_refund)\z/D',
                $entry, $parts
            )) {
                return false;
            }
            $version = $parts[1];
            $stage = $parts[2];
            if (\str_starts_with($stage, 'upgrade_') && '1.0.2' !== $version) {
                return false;
            }
            if (!\hash_equals(
                $this->stageDigest($stage, $version),
                \strtolower((string) $row['digest'])
            )) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,string> */
    private function statementsForVersion(string $version): array
    {
        if (!\in_array($version, ['1.0.0', '1.0.1', '1.0.2'], true)) {
            throw new \RuntimeException('pc_schema_upgrade_unsupported');
        }
        $statements = Schema::tableStatements($this->db);
        if ('1.0.2' !== $version) {
            $statements['pc_payment_events'] = \str_replace(
                "  \x60refund_evidence_snapshot\x60 LONGTEXT NULL,\n", '',
                $statements['pc_payment_events']
            );
        }
        if ('1.0.0' === $version) {
            $statements['pc_payment_events'] = \str_replace(
                "  \x60correction_evidence_snapshot\x60 LONGTEXT NULL,\n", '',
                $statements['pc_payment_events']
            );
        }
        return $statements;
    }

    private function stageDigest(string $stage, string $version = PERUCOMMERCE_SCHEMA_VERSION): string
    {
        $serialized = \implode("\n", \array_values($this->statementsForVersion($version)))
            . "\n" . \implode("\n", Schema::foreignKeyStatements($this->db))
            . "\n" . $version . ':' . $stage;
        return \hash('sha256', $serialized);
    }

    /**
     * Evolución idempotente 1.0.0 → 1.0.1 → 1.0.2. Solo columnas
     * nullable de evidencia; sin backfill inventado, DROP ni reset de datos.
     * El administrador debe contar con backup realmente certificado.
     */
    private function upgradeKnownSchema(string $installed): void
    {
        // Antes de cualquier ALTER, comprobar baseline o uno de los estados
        // aditivos legítimos de reentrada después de DDL implícitamente confirmado.
        $this->verifyUpgradeBaseline($installed);
        $this->writeMarker(self::STATE, 'incomplete');
        $this->writeMarker(self::VERIFIED, '');

        try {
            if ('1.0.0' === $installed) {
                $this->recordStage('upgrade_correction', 'running');
                $this->ensureEvidenceColumn('correction_evidence_snapshot', 'verification_reference');
                $this->recordStage('upgrade_correction', 'complete');
            } elseif (!$this->requireEvidenceColumn('correction_evidence_snapshot')) {
                // Un sitio marcado 1.0.1 sin su columna no es un baseline
                // íntegro: no efectuar más DDL sobre ese estado ambiguo.
                throw new \RuntimeException('pc_schema_columns_mismatch');
            }

            $this->recordStage('upgrade_refund', 'running');
            $this->ensureEvidenceColumn('refund_evidence_snapshot', 'correction_evidence_snapshot');
            $this->recordStage('upgrade_refund', 'complete');

            // Verificación estricta de TODAS las tablas, índices, CHECK y FK.
            // Si el DDL se confirmó parcialmente, los marcadores siguen cerrados.
            $this->inspector->verifyAll();
            $this->recordStage('verified', 'complete');
            $this->writeMarker(self::SCHEMA, PERUCOMMERCE_SCHEMA_VERSION);
            $this->writeMarker(self::PLUGIN, PERUCOMMERCE_VERSION);
            $this->writeMarker(self::VERIFIED, PERUCOMMERCE_SCHEMA_VERSION);
            $this->writeMarker(self::STATE, 'complete');
            // La barrera IMP-68 queda bloqueada hasta verificar recuperación,
            // tokens, fondos externos, versiones de assets y pruebas P-C.
        } catch (\Throwable $error) {
            try {
                $this->writeMarker(self::STATE, 'incomplete');
                $this->writeMarker(self::VERIFIED, '');
            } catch (\Throwable $ignored) {
            }
            throw $error;
        }
    }

    /**
     * Un journal correcto no basta: inspeccionar íntegramente el esquema
     * PREVIO antes de tocar dinero, órdenes o citas. La tabla de eventos
     * financieros puede estar en alguno de los estados aditivos conocidos
     * si un ALTER anterior quedó confirmado sin cerrar la migración.
     */
    private function verifyUpgradeBaseline(string $installed): void
    {
        $statements = $this->statementsForVersion($installed);
        $correction = $this->requireEvidenceColumn('correction_evidence_snapshot');
        $refund = $this->requireEvidenceColumn('refund_evidence_snapshot');
        if (($refund && !$correction) || ('1.0.1' === $installed && !$correction)) {
            throw new \RuntimeException('pc_schema_columns_mismatch');
        }
        $observedVersion = $refund ? '1.0.2' : ($correction ? '1.0.1' : '1.0.0');
        $statements['pc_payment_events'] = $this->statementsForVersion(
            $observedVersion
        )['pc_payment_events'];
        foreach ($statements as $suffix => $sql) {
            $this->inspector->verifyTable($suffix, $sql);
        }
        foreach (Schema::foreignKeyStatements($this->db) as $sql) {
            if (!$this->inspector->foreignKeyExists($sql)) {
                throw new \RuntimeException('pc_schema_fk_missing');
            }
        }
        $this->inspector->verifyAnchor();
    }

    private function requireEvidenceColumn(string $column): bool
    {
        $table = Schema::tableNames($this->db)['pc_payment_events'];
        $rows = $this->inspector->rows($this->db->prepare(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME '
                . 'FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $table, $column
        ));
        if ([] === $rows) {
            return false;
        }
        $row = $rows[0];
        if (1 !== \count($rows)
            || 'longtext' !== \strtolower((string) $row['COLUMN_TYPE'])
            || 'YES' !== $row['IS_NULLABLE']
            || null !== $row['COLUMN_DEFAULT']
            || '' !== (string) $row['EXTRA']
            || 'utf8mb4' !== \strtolower((string) $row['CHARACTER_SET_NAME'])
            || ('' !== $this->db->collate
                && \strtolower((string) $row['COLLATION_NAME']) !== \strtolower($this->db->collate))) {
            throw new \RuntimeException('pc_schema_columns_mismatch');
        }
        return true;
    }

    private function ensureEvidenceColumn(string $column, string $after): void
    {
        if ($this->requireEvidenceColumn($column)) {
            return;
        }
        if (!\in_array($column, ['correction_evidence_snapshot','refund_evidence_snapshot'], true)) {
            throw new \RuntimeException('pc_schema_upgrade_unsupported');
        }
        $this->writeSql($this->db->prepare(
            'ALTER TABLE %i ADD COLUMN %i LONGTEXT NULL AFTER %i',
            Schema::tableNames($this->db)['pc_payment_events'], $column, $after
        ), 'pc_schema_upgrade_failed');
        if (!$this->requireEvidenceColumn($column)) {
            throw new \RuntimeException('pc_schema_upgrade_failed');
        }
    }

    private function recordStage(string $stage, string $result): void
    {
        $version = PERUCOMMERCE_SCHEMA_VERSION . ':' . $stage;
        $digest = $this->stageDigest($stage);
        $table = Schema::tableNames($this->db)['pc_migrations'];

        $rows = $this->inspector->rows($this->db->prepare(
            'SELECT HEX(checksum) AS digest FROM %i WHERE version = %s', $table, $version
        ));
        if ([] !== $rows && (1 !== \count($rows)
            || !\hash_equals($digest, \strtolower((string) $rows[0]['digest'])))) {
            throw new \RuntimeException('pc_schema_version_conflict');
        }

        $sql = $this->db->prepare(
            'INSERT INTO %i (version, checksum, applied_at, plugin_version, migration_result, diagnostic_code) '
                . 'VALUES (%s, UNHEX(%s), UTC_TIMESTAMP(6), %s, %s, NULL) '
                . 'ON DUPLICATE KEY UPDATE migration_result = %s, '
                . 'applied_at = UTC_TIMESTAMP(6), plugin_version = %s, diagnostic_code = NULL',
            $table, $version, $digest, PERUCOMMERCE_VERSION, $result, $result, PERUCOMMERCE_VERSION
        );
        $this->writeSql($sql, 'pc_schema_journal_failed');
    }

    private function seedResource(): void
    {
        $names = Schema::tableNames($this->db);
        $table = $names['pc_schedule_revisions'];
        $rows = $this->inspector->rows($this->db->prepare(
            'SELECT resource_id, active_revision_no FROM %i LIMIT 2', $table
        ));
        if ([] === $rows) {
            // Instalación nueva: el recurso único existe, pero comienza
            // con todas las jornadas CERRADAS (ninguna venta de citas).
            $this->writeSql($this->db->prepare(
                'INSERT INTO %i '
                    . '(resource_id, row_version, active_revision_no, timezone, updated_at, updated_by) '
                    . 'VALUES (1, 1, 0, %s, UTC_TIMESTAMP(6), %d)',
                $table, 'America/Lima', \get_current_user_id()
            ), 'pc_schema_seed_failed');
            $rows = [['resource_id' => 1, 'active_revision_no' => 0]];
        }
        if (1 !== \count($rows) || '1' !== (string) $rows[0]['resource_id']) {
            throw new \RuntimeException('pc_schema_seed_failed');
        }
        if ('0' !== (string) $rows[0]['active_revision_no']) {
            return;
        }
        // El DTO WeeklyScheduleView exige revision_no positivo. Una
        // revisión inicial de siete días cerrados permite leer CAS=1
        // sin inventar una apertura comercial ni alterar historiales.
        // Es idempotente al reanudar una migración interrumpida.
        for ($weekday = 0; $weekday <= 6; ++$weekday) {
            $this->writeSql($this->db->prepare(
                'INSERT INTO %i '
                    . '(resource_id, revision_no, window_index, weekday, opens_local, '
                    . 'closes_local, closes_day_offset, enabled, valid_from, valid_to, '
                    . 'row_version, created_at) '
                    . 'VALUES (1, 1, 0, %d, NULL, NULL, 0, 0, %s, NULL, 1, UTC_TIMESTAMP(6)) '
                    . 'ON DUPLICATE KEY UPDATE id = id',
                $names['pc_schedule_rules'], $weekday, '1000-01-01'
            ), 'pc_schema_seed_failed');
        }
        $this->writeSql($this->db->prepare(
            'UPDATE %i SET active_revision_no = 1, updated_at = UTC_TIMESTAMP(6) '
                . 'WHERE resource_id = 1 AND active_revision_no = 0',
            $table
        ), 'pc_schema_seed_failed');
    }

    /** Lectura autoritativa, sin cache de options. */
    private function marker(string $name): string
    {
        $rows = $this->inspector->rows($this->db->prepare(
            'SELECT option_value FROM %i WHERE option_name = %s LIMIT 1',
            $this->db->options, $name
        ));
        return [] === $rows ? '' : (string) $rows[0]['option_value'];
    }

    /** Escritura de marcador individual; no publica readiness por el frontend. */
    private function writeMarker(string $name, string $value): void
    {
        $sql = $this->db->prepare(
            'INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s) '
                . 'ON DUPLICATE KEY UPDATE option_value = %s, autoload = %s',
            $this->db->options, $name, $value, 'off', $value, 'off'
        );
        $this->writeSql($sql, 'pc_schema_marker_failed');
        if (\function_exists('wp_cache_delete')) {
            \wp_cache_delete($name, 'options');
            \wp_cache_delete('alloptions', 'options');
            \wp_cache_delete('notoptions', 'options');
        }
        if ($this->marker($name) !== $value) {
            throw new \RuntimeException('pc_schema_marker_failed');
        }
    }

    private function writeSql(string $sql, string $code): void
    {
        // Un reconectado silencioso perdería GET_LOCK: nunca continuar sobre
        // una conexión distinta ni confiar solo en un transient u opción WP.
        $ownership = $this->inspector->rows($this->db->prepare(
            'SELECT IS_USED_LOCK(%s) AS owner_id, CONNECTION_ID() AS connection_id',
            $this->lockName
        ));
        if (1 !== \count($ownership)
            || null === $ownership[0]['owner_id']
            || (string) $ownership[0]['owner_id'] !== (string) $ownership[0]['connection_id']) {
            throw new \RuntimeException('pc_install_locked');
        }

        $suppress = $this->db->suppress_errors(true);
        $show = $this->db->hide_errors();
        try {
            if (false === $this->db->query($sql) || '' !== $this->db->last_error) {
                throw new \RuntimeException($code);
            }
        } finally {
            $this->db->suppress_errors($suppress);
            if ($show) {
                $this->db->show_errors(true);
            }
        }
    }
}
