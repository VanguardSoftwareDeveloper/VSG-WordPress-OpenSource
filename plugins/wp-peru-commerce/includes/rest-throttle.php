<?php
/**
 * IMP-12 — limitador base por operación y origen, sin tabla nueva ni Redis.
 * La política sensible por sujeto/cuenta/OTP sigue siendo adicional y se
 * comprueba por su módulo, no se confía en X-Forwarded-For del navegador.
 *
 * Se serializa el contador por una named lock del motor SQL. Los límites
 * son operativos iniciales y deben contrastarse con hosting en P-C.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Rest_Throttle
{
    private const POLICIES = [
        'public_read' => [120, 60],
        'public_write' => [15, 60],
        'guest' => [20, 60],
        'otp' => [5, 600],
        'provider' => [120, 60],
        'staff' => [120, 60],
        'sensitive' => [12, 60],
    ];

    public static function check(string $operationId, \WP_REST_Request $request): void
    {
        $rule = Access_Contract::operation($operationId);
        if (null === $rule || !\function_exists('wp_salt')) {
            throw new Rest_Failure('pc_dependency_unavailable', 503, true);
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!\is_string($remote) || false === \filter_var($remote, \FILTER_VALIDATE_IP)) {
            throw new Rest_Failure('pc_dependency_unavailable', 503, true);
        }
        $policy = self::policy($operationId, $rule);
        [$limit, $window] = self::POLICIES[$policy];

        $user = \function_exists('get_current_user_id') ? (string) \get_current_user_id() : '0';
        // Nunca escribir IP, email, usuario, cookie ni secreto en wp_options.
        $key = \hash_hmac('sha256',
            $operationId . "\0" . $remote . "\0" . $user, \wp_salt('auth')
        );
        $transient = 'pc_rl_' . \substr($key, 0, 40);
        $lock = 'pc_rl_' . \substr($key, 0, 38);

        global $wpdb;
        if (!$wpdb instanceof \wpdb || !$wpdb->dbh instanceof \mysqli) {
            throw new Rest_Failure('pc_dependency_unavailable', 503, true);
        }
        $connection = $wpdb->dbh;
        $thread = \mysqli_thread_id($connection);
        try {
            $stmt = $wpdb->prepare('SELECT GET_LOCK(%s, 0) AS acquired', $lock);
            $result = \mysqli_query($connection, $stmt);
            $row = $result instanceof \mysqli_result ? \mysqli_fetch_assoc($result) : null;
            if ($result instanceof \mysqli_result) {
                \mysqli_free_result($result);
            }
            if (!\is_array($row) || '1' !== (string) $row['acquired']) {
                throw new Rest_Failure('pc_rate_limited', 429, true);
            }
        } catch (Rest_Failure $failure) {
            throw $failure;
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_dependency_unavailable', 503, true);
        }

        try {
            // Evitar revalidar un contador en una conexión diferente.
            self::verifyLock($connection, $thread, $lock);
            $stored = \get_transient($transient);
            $now = \time();
            if (!\is_array($stored)
                || !\is_int($stored['until'] ?? null)
                || !\is_int($stored['count'] ?? null)
                || $stored['until'] <= $now) {
                $stored = ['until' => $now + $window, 'count' => 0];
            }

            $stored['count']++;
            $allowed = $stored['count'] <= $limit;
            $ttl = \max(1, $stored['until'] - $now);
            self::verifyLock($connection, $thread, $lock);
            if (!\set_transient($transient, $stored, $ttl)) {
                throw new Rest_Failure('pc_dependency_unavailable', 503, true);
            }
            self::verifyLock($connection, $thread, $lock);
            if (!$allowed) {
                throw new Rest_Failure('pc_rate_limited', 429, true);
            }
        } finally {
            try {
                if ($wpdb->dbh === $connection && \mysqli_thread_id($connection) === $thread) {
                    $unlock = $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock);
                    $result = \mysqli_query($connection, $unlock);
                    if ($result instanceof \mysqli_result) {
                        \mysqli_free_result($result);
                    }
                }
            } catch (\Throwable $ignored) {
                // La conexión finalizará el lock; no afirmar liberación.
            }
        }
    }

    /** @param array<string,mixed> $rule */
    private static function policy(string $operationId, array $rule): string
    {
        if (\in_array($operationId, ['R8-10', 'R8-11'], true)) {
            return 'otp';
        }
        if (\in_array($operationId, ['R8-12', 'R8-15', 'R8-26', 'R8-27', 'R8-28', 'R8-50', 'R8-58',
            'R8-59', 'R8-61', 'R8-67', 'R8-68', 'R8-69', 'R8-70'], true)) {
            return 'sensitive';
        }
        return match ($rule['actor']) {
            'provider' => 'provider',
            'guest', 'digital_token', 'digital_session' => 'guest',
            'staff', 'admin_only' => 'staff',
            default => 'GET' === $rule['method'] ? 'public_read' : 'public_write',
        };
    }

    private static function verifyLock(\mysqli $db, int $thread, string $lock): void
    {
        try {
            if (\mysqli_thread_id($db) !== $thread) {
                throw new \RuntimeException();
            }
            global $wpdb;
            if (!$wpdb instanceof \wpdb || $wpdb->dbh !== $db) {
                throw new \RuntimeException();
            }
            $sql = $wpdb->prepare('SELECT IS_USED_LOCK(%s) AS owner_id', $lock);
            $res = \mysqli_query($db, $sql);
            $row = $res instanceof \mysqli_result ? \mysqli_fetch_assoc($res) : null;
            if ($res instanceof \mysqli_result) {
                \mysqli_free_result($res);
            }
            if (!\is_array($row) || (string) $row['owner_id'] !== (string) $thread) {
                throw new \RuntimeException();
            }
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_dependency_unavailable', 503, true);
        }
    }
}
