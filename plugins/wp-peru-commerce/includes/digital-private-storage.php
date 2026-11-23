<?php
/**
 * IMP-21: almacén físico independiente de wp_upload_dir y Media Library.
 * No hay atomicidad FS+SQL: ante rollback/commit incierto, conservar un
 * archivo huérfano privado para conciliación posterior; no borrarlo a ciegas.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Private_Storage
{
    // Límite defensivo inicial, sujeto a la verificación del hosting P-C.
    public const MAX_BYTES = 20 * 1024 * 1024;
    private const FORMATS = [
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip', 'application/x-zip'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'mp3' => ['audio/mpeg'],
        'mp4' => ['video/mp4'],
    ];

    private static $verifier = null;

    /** Evidencia real de denegación HTTP, streaming, backup y restauración. */
    public static function registerInstallationVerifier(callable $verifier): void
    {
        if (null !== self::$verifier) {
            throw new \LogicException('pc_storage_verifier_duplicate');
        }
        self::$verifier = $verifier;
        Module_Gates::registerEvidence('private_storage', static function () use ($verifier): bool {
            try {
                $root = self::root();
                return true === $verifier([
                    'root_sha256' => \hash('sha256', $root),
                    'site_id' => (string) \get_current_blog_id(),
                    'schema_version' => PERUCOMMERCE_SCHEMA_VERSION,
                ]);
            } catch (\Throwable $ignored) {
                return false;
            }
        });
    }

    public static function root(): string
    {
        if (!\defined('PERUCOMMERCE_PRIVATE_DIR')
            || !\is_string(PERUCOMMERCE_PRIVATE_DIR)
            || \is_link(PERUCOMMERCE_PRIVATE_DIR)
            || !\is_dir(PERUCOMMERCE_PRIVATE_DIR)) {
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        $root = \realpath(PERUCOMMERCE_PRIVATE_DIR);
        $wp = \realpath(ABSPATH);
        $doc = \realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $mode = false === $root ? false : \fileperms($root);
        if (false === $root || false === $wp || false === $doc
            || !self::outside($root, $wp) || !self::outside($root, $doc)
            || !\is_readable($root) || !\is_writable($root) || !\is_executable($root)
            || !\is_int($mode) || (0 !== ($mode & 0077))) {
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        return $root;
    }

    /** Origen exclusivamente de WP_REST_Request::get_file_params(). */
    public static function inspectUpload(array $upload, string $filename): array
    {
        if (!isset($upload['error'], $upload['tmp_name'], $upload['size'])
            || \UPLOAD_ERR_OK !== $upload['error']
            || !\is_string($upload['tmp_name'])
            || !\is_uploaded_file($upload['tmp_name'])
            || !\is_int($upload['size'])
            || $upload['size'] < 1 || $upload['size'] > self::MAX_BYTES
            || !\is_file($upload['tmp_name'])
            || \is_link($upload['tmp_name'])
            || \filesize($upload['tmp_name']) !== $upload['size']
            || !\function_exists('finfo_open')) {
            throw new Transaction_Failure('pc_digital_upload_invalid');
        }
        $safeName = self::filename($filename);
        $ext = \strtolower(\pathinfo($safeName, \PATHINFO_EXTENSION));
        if (!isset(self::FORMATS[$ext])) {
            throw new Transaction_Failure('pc_digital_upload_invalid');
        }
        $finfo = \finfo_open(\FILEINFO_MIME_TYPE);
        if (false === $finfo) {
            throw new Transaction_Failure('pc_digital_upload_invalid');
        }
        try {
            $mime = \finfo_file($finfo, $upload['tmp_name']);
        } finally {
            \finfo_close($finfo);
        }
        if (!\is_string($mime) || !\in_array($mime, self::FORMATS[$ext], true)) {
            throw new Transaction_Failure('pc_digital_upload_invalid');
        }
        $digest = \hash_file('sha256', $upload['tmp_name']);
        if (!\is_string($digest) || !\preg_match('/\A[a-f0-9]{64}\z/D', $digest)) {
            throw new Transaction_Failure('pc_digital_upload_invalid');
        }
        return [
            'tmp_name' => $upload['tmp_name'],
            'filename' => $safeName, 'mime' => $mime,
            'size' => $upload['size'], 'sha256' => $digest,
        ];
    }

    public static function filename(string $name): string
    {
        if ('' === $name || !\mb_check_encoding($name, 'UTF-8')
            || \mb_strlen($name, 'UTF-8') > 255
            || \str_contains($name, '/') || \str_contains($name, '\\')
            || \str_contains($name, '..')
            || \preg_match('/[\x00-\x1F\x7F<>:"|?*]/', $name)) {
            throw new Transaction_Failure('pc_digital_upload_invalid');
        }
        $safe = \sanitize_file_name($name);
        if (!\is_string($safe) || '' === $safe || \strlen($safe) > 255
            || '.' === $safe || \str_starts_with($safe, '.')) {
            throw new Transaction_Failure('pc_digital_upload_invalid');
        }
        return $safe;
    }

    /**
     * FUERA del callback SQL: copia a archivo nuevo exclusivo, comprueba
     * digest/tamaño, y lo promueve mediante rename. El binario resultante
     * jamás se sustituye aunque llegue otra venta o versión de asignación.
     */
    public static function store(array $upload): array
    {
        Transaction::assertOutside();
        // El filesystem no participa del COMMIT SQL: denegar nuevas copias
        // durante actualización o recuperación, también fuera del REST.
        Maintenance::assertWritable();
        $root = self::root();
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        $site = \substr(\hash('sha256',
            (string) \get_current_blog_id() . ':' . $wpdb->prefix), 0, 16);
        $key = 'pc-' . $site . '-' . \bin2hex(\random_bytes(24)) . '.blob';
        $final = $root . \DIRECTORY_SEPARATOR . $key;
        $part = $final . '.part';
        $input = @\fopen($upload['tmp_name'], 'rb');
        if (false === $input) {
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        $output = @\fopen($part, 'xb');
        if (false === $output) {
            \fclose($input);
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        try {
            if (!@\chmod($part, 0600)) {
                throw new Transaction_Failure('pc_private_storage_unavailable');
            }
            $copied = 0;
            while (!\feof($input)) {
                $chunk = \fread($input, 65536);
                if (!\is_string($chunk) || ('' === $chunk && !\feof($input))) {
                    throw new Transaction_Failure('pc_private_storage_unavailable');
                }
                if ('' === $chunk) {
                    break;
                }
                $offset = 0;
                while ($offset < \strlen($chunk)) {
                    $wrote = \fwrite($output, \substr($chunk, $offset));
                    if (!\is_int($wrote) || $wrote < 1) {
                        throw new Transaction_Failure('pc_private_storage_unavailable');
                    }
                    $offset += $wrote;
                }
                $copied += \strlen($chunk);
                if ($copied > self::MAX_BYTES) {
                    throw new Transaction_Failure('pc_digital_upload_invalid');
                }
            }
            if (!\fflush($output) || $copied !== $upload['size']) {
                throw new Transaction_Failure('pc_private_storage_unavailable');
            }
        } catch (\Throwable $error) {
            \fclose($input);
            \fclose($output);
            @\unlink($part);
            throw $error;
        }
        \fclose($input);
        \fclose($output);
        // La barrera puede haber cambiado durante el streaming. El operador
        // debe esperar las peticiones FS en vuelo antes de capturar el backup;
        // esta segunda comprobación impide promover archivos ya bloqueados.
        if (!Maintenance::ready()) {
            @\unlink($part);
            throw new Transaction_Failure('pc_maintenance_blocked');
        }
        if (\hash_file('sha256', $part) !== $upload['sha256']
            || \file_exists($final) || \is_link($final)
            || !@\rename($part, $final)) {
            @\unlink($part);
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        return [
            'key' => $key, 'sha256' => $upload['sha256'],
            'size' => $upload['size'], 'mime' => $upload['mime'],
            'filename' => $upload['filename'],
        ];
    }

    /** Solo claves generadas localmente, jamás rutas/URLs de la petición. */
    public static function resolve(string $key): string
    {
        Transaction::assertOutside();
        if (!\preg_match('/\Apc-[a-f0-9]{16}-[a-f0-9]{48}\.blob\z/D', $key)) {
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        $root = self::root();
        $path = $root . \DIRECTORY_SEPARATOR . $key;
        if (\is_link($path) || !\is_file($path)
            || \realpath($path) !== $path || !\is_readable($path)) {
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        return $path;
    }

    public static function verify(string $key, string $hash, string $size): string
    {
        $path = self::resolve($key);
        $actual = \filesize($path);
        if (!\is_int($actual) || $actual < 1 || (string) $actual !== $size
            || !\preg_match('/\A[a-f0-9]{64}\z/D', $hash)
            || !\hash_equals($hash, (string) \hash_file('sha256', $path))) {
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        return $path;
    }

    private static function outside(string $path, string $base): bool
    {
        $path = \strtolower(\str_replace('\\', '/', $path));
        $base = \strtolower(\rtrim(\str_replace('\\', '/', $base), '/'));
        return $path !== $base && !\str_starts_with($path, $base . '/');
    }
}
