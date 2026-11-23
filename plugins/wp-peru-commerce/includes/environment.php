<?php
/**
 * Comprobaciones de solo lectura y denegación ante falta de evidencia.
 * La inspección no habilita módulos por sí misma.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Environment
{
    /** @var array<string, bool> Evidencia temporal; nunca persistir resultados de una petición en caché global. */
    private array $probes = [];

    public function has(string $requirement): bool
    {
        if (\array_key_exists($requirement, $this->probes)) {
            return $this->probes[$requirement];
        }

        try {
            switch ($requirement) {
                case 'platform':
                    $ok = $this->platform();
                    break;
                case 'commerce_php':
                    $ok = $this->commercePhp();
                    break;
                case 'database':
                    $ok = $this->database();
                    break;
                case 'schema':
                    $ok = $this->schema();
                    break;
                case 'https':
                    $ok = $this->https();
                    break;
                case 'private_storage':
                    $ok = $this->privateStorage();
                    break;
                default:
                    $ok = false;
            }
        } catch (\Throwable $ignored) {
            // Un diagnóstico fallido no habilita operaciones ni revela errores internos.
            $ok = false;
        }

        $this->probes[$requirement] = $ok;
        return $ok;
    }

    private function platform(): bool
    {
        if (\PHP_VERSION_ID < 80300 || \PHP_INT_SIZE < 8 || !\function_exists('get_bloginfo')) {
            return false;
        }

        $wpVersion = \get_bloginfo('version');
        if (!\is_string($wpVersion) || \preg_match('/(?:alpha|beta|rc)/i', $wpVersion)
            || \version_compare($wpVersion, '7.1.4', '<')) {
            return false;
        }

        // Módulos de lectura no comercial pueden seguir disponibles sin BCMath/cURL.
        foreach (['json', 'openssl'] as $extension) {
            if (!\extension_loaded($extension)) {
                return false;
            }
        }
        foreach (['random_bytes', 'hash_hmac', 'get_option', 'wp_parse_url', 'home_url', 'site_url'] as $function) {
            if (!\function_exists($function)) {
                return false;
            }
        }

        return true;
    }

    private function commercePhp(): bool
    {
        if (!$this->has('platform')) {
            return false;
        }
        foreach (['mysqli', 'curl', 'mbstring', 'fileinfo', 'bcmath'] as $extension) {
            if (!\extension_loaded($extension)) {
                return false;
            }
        }
        return \function_exists('bcadd');
    }

    private function https(): bool
    {
        return \function_exists('is_ssl') && \is_ssl()
            && \function_exists('home_url') && \function_exists('site_url')
            && \function_exists('wp_parse_url')
            && 'https' === \wp_parse_url(\home_url('/'), \PHP_URL_SCHEME)
            && 'https' === \wp_parse_url(\site_url('/'), \PHP_URL_SCHEME);
    }

    private function database(): bool
    {
        if (!$this->has('platform') || !\extension_loaded('mysqli')) {
            return false;
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return false;
        }

        $version = $this->readValue('SELECT VERSION()');
        if (!\is_string($version) || '' === $version) {
            return false;
        }

        $maria = false !== \stripos($version, 'mariadb');
        if ($maria) {
            // El prefijo de compatibilidad 5.5.5- no representa la versión del servidor MariaDB.
            $normal = \preg_replace('/^5\.5\.5-/', '', $version);
            $minimum = '10.11';
        } else {
            $normal = $version;
            $minimum = '8.4';
        }
        if (!\preg_match('/^(\d+\.\d+(?:\.\d+)?)/', $normal, $match)
            || \version_compare($match[1], $minimum, '<')) {
            return false;
        }

        // No ejecutar SET SESSION; la lectura no cambia el aislamiento de la conexión.
        $isolation = $this->readValue('SELECT @@session.transaction_isolation');
        if ((!\is_string($isolation) || '' === $isolation) && $maria) {
            // MariaDB anterior conserva el nombre tx_isolation.
            $isolation = $this->readValue('SELECT @@session.tx_isolation');
        }
        if (!\is_string($isolation)
            || !\in_array(\strtoupper(\str_replace('_', '-', $isolation)), ['READ-COMMITTED', 'REPEATABLE-READ'], true)) {
            return false;
        }

        // Un cliente con las FK desactivadas no puede garantizar integridad.
        if ('1' !== (string) $this->readValue('SELECT @@session.foreign_key_checks')) {
            return false;
        }

        // MariaDB permite desactivar CHECK por sesión. Una definición DDL
        // presente no equivale a una restricción aplicada si este valor es 0.
        if ($maria && '1' !== (string) $this->readValue('SELECT @@session.check_constraint_checks')) {
            return false;
        }

        return true;
    }

    private function schema(): bool
    {
        if (!$this->has('database') || !\defined('PERUCOMMERCE_SCHEMA_VERSION')) {
            return false;
        }

        // IMP-05 establecerá la versión objetivo, migraciones y su evidencia real.
        $expected = PERUCOMMERCE_SCHEMA_VERSION;
        if (!\is_string($expected) || '' === $expected
            || !\defined('PERUCOMMERCE_VERSION')
            || PERUCOMMERCE_VERSION !== $this->readOption('perucommerce_plugin_version')
            || $expected !== $this->readOption('perucommerce_schema_version')
            || $expected !== $this->readOption('perucommerce_schema_verified_version')
            || 'complete' !== $this->readOption('perucommerce_migration_state')) {
            return false;
        }

        global $wpdb;
        // Carga diferida: la definición DDL no se evalúa en páginas normales.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        $names = \array_values(Schema::tableNames($wpdb));

        $databaseName = $this->readValue('SELECT DATABASE()');
        if (!\is_string($databaseName) || '' === $databaseName) {
            return false;
        }

        // Los cuatro marcadores que protegen cada escritura viven en options.
        // Una options MyISAM no proporciona las garantías mínimas esperadas.
        $optionsQuery = $wpdb->prepare(
            'SELECT ENGINE AS engine FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
            $databaseName, $wpdb->options
        );
        if (!\is_string($optionsQuery)) {
            return false;
        }
        $optionTables = $this->readRows($optionsQuery);
        if (null === $optionTables || 1 !== \count($optionTables)
            || 'InnoDB' !== ($optionTables[0]->engine ?? null)) {
            return false;
        }

        $holders = \implode(', ', \array_fill(0, \count($names), '%s'));
        $query = $wpdb->prepare(
            'SELECT TABLE_NAME AS table_name, ENGINE AS engine FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN (' . $holders . ')',
            \array_merge([$databaseName], $names)
        );
        if (!\is_string($query)) {
            return false;
        }

        $rows = $this->readRows($query);
        if (null === $rows || \count($rows) !== \count($names)) {
            return false;
        }

        $found = [];
        foreach ($rows as $row) {
            if (!\is_object($row) || !isset($row->table_name, $row->engine)
                || 'InnoDB' !== $row->engine || !\in_array($row->table_name, $names, true)
                || isset($found[$row->table_name])) {
                return false;
            }
            $found[$row->table_name] = true;
        }
        return \count($found) === \count($names);
    }

    private function privateStorage(): bool
    {
        // Una ruta configurada sin verificación HTTP/proveedor no autoriza archivos privados.
        if (!\defined('PERUCOMMERCE_PRIVATE_DIR') || !\is_string(PERUCOMMERCE_PRIVATE_DIR)
            || !\is_dir(PERUCOMMERCE_PRIVATE_DIR) || \is_link(PERUCOMMERCE_PRIVATE_DIR)) {
            return false;
        }
        $path = \realpath(PERUCOMMERCE_PRIVATE_DIR);
        $docroot = isset($_SERVER['DOCUMENT_ROOT']) && \is_string($_SERVER['DOCUMENT_ROOT'])
            ? \realpath($_SERVER['DOCUMENT_ROOT']) : false;
        $wpRoot = \realpath(ABSPATH);
        if (false === $path || false === $docroot || false === $wpRoot
            || !$this->outside($path, $docroot) || !$this->outside($path, $wpRoot)
            || !\is_readable($path) || !\is_writable($path)) {
            return false;
        }

        // IMP-21/P-C deberá registrar prueba real de denegación HTTP y de streaming.
        // El flag de configuración comercial jamás constituye esa prueba.
        $evidence = $this->readOption('perucommerce_storage_verification');
        return \is_array($evidence)
            && isset($evidence['path_hash'], $evidence['schema_version'], $evidence['verified'])
            && true === $evidence['verified']
            && \is_string($evidence['path_hash'])
            && \hash_equals(\hash('sha256', $path), $evidence['path_hash'])
            && \defined('PERUCOMMERCE_SCHEMA_VERSION')
            && $evidence['schema_version'] === PERUCOMMERCE_SCHEMA_VERSION
            && $this->has('schema');
    }

    private function outside(string $child, string $root): bool
    {
        $root = \rtrim($root, '/\\');
        return $child !== $root && 0 !== \strpos($child, $root . \DIRECTORY_SEPARATOR);
    }

    /**
     * Lectura autoritativa de marcadores técnicos: evita un `get_option()` cacheado
     * durante upgrades o recuperación concurrente. Nunca escribe en wp_options.
     *
     * @return mixed
     */
    private function readOption(string $name)
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return null;
        }
        $sql = $wpdb->prepare('SELECT option_value FROM %i WHERE option_name = %s LIMIT 1', $wpdb->options, $name);
        if (!\is_string($sql)) {
            return null;
        }
        $value = $this->readValue($sql);
        return \is_string($value) && \function_exists('maybe_unserialize')
            ? \maybe_unserialize($value) : null;
    }

    /**
     * Evita que una consulta de diagnóstico vuelque consultas/errores en la salida o log.
     * Nunca incluye consultas de escritura ni de prueba de locks.
     *
     * @return mixed
     */
    private function readValue(string $sql)
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return null;
        }
        $suppressed = $wpdb->suppress_errors(true);
        $shown = $wpdb->hide_errors();
        try {
            $result = $wpdb->get_var($sql);
            return '' === $wpdb->last_error ? $result : null;
        } finally {
            $wpdb->suppress_errors($suppressed);
            if ($shown) {
                $wpdb->show_errors(true);
            }
        }
    }

    private function readRows(string $sql): ?array
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return null;
        }
        $suppressed = $wpdb->suppress_errors(true);
        $shown = $wpdb->hide_errors();
        try {
            $rows = $wpdb->get_results($sql);
            return '' === $wpdb->last_error && \is_array($rows) ? $rows : null;
        } finally {
            $wpdb->suppress_errors($suppressed);
            if ($shown) {
                $wpdb->show_errors(true);
            }
        }
    }
}
