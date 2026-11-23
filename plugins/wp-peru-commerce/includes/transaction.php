<?php
/**
 * IMP-07 — límite transaccional InnoDB.
 *
 * Solo SQL local durante el callback. Nada de HTTP, wp_mail, DDL,
 * update_option/get_option u otros accesos mediante $wpdb. Excepción
 * explícita de IMP-13: CAS de la opción propia wp_options con mysqli mediante
 * Transaction_Context, seguido de invalidación de caché DESPUÉS del COMMIT.
 * El callback debe revalidar datos/versiones/recursos en cada intento.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

/** Códigos internos estables; no incluyen SQL, PII ni mensajes del motor. */
final class Transaction_Failure extends \RuntimeException
{
    private bool $retryable;
    private bool $outcomeUncertain;

    public function __construct(string $code, bool $retryable = false, bool $outcomeUncertain = false)
    {
        parent::__construct($code);
        $this->retryable = $retryable;
        $this->outcomeUncertain = $outcomeUncertain;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function isOutcomeUncertain(): bool
    {
        return $this->outcomeUncertain;
    }
}

final class Transaction
{
    /** @var \WeakMap<\wpdb, bool>|null */
    private static ?\WeakMap $active = null;

    /**
     * Obligatorio para los futuros adaptadores HTTP/email: prohíbe E/S externa
     * mientras exista una transacción administrada en la conexión WordPress.
     */
    public static function assertOutside(): void
    {
        global $wpdb;
        if ($wpdb instanceof \wpdb && null !== self::$active && isset(self::$active[$wpdb])) {
            throw new Transaction_Failure('pc_external_io_inside_transaction');
        }
    }

    /**
     * Corre un callback SQL corto. Solo se reintenta tras rollback confirmado
     * cuando el llamante declara retrySafe y NO hubo efectos externos.
     *
     * @template T
     * @param callable(Transaction_Context):T $operation
     * @return T
     */
    public static function run(callable $operation, bool $retrySafe = false): mixed
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb || !$wpdb->dbh instanceof \mysqli) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }

        self::$active ??= new \WeakMap();
        if (isset(self::$active[$wpdb])) {
            throw new Transaction_Failure('pc_nested_transaction_denied');
        }

        // No basta una bandera comercial: verificar instalación, motor y schema
        // antes de intentar START TRANSACTION. Los locks deben probarse en P-C.
        $env = new Environment();
        if (!$env->has('database') || !$env->has('schema')) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }

        self::$active[$wpdb] = true;
        try {
            $maxAttempts = $retrySafe ? 3 : 1;
            for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
                $context = new Transaction_Context($wpdb);
                try {
                    $context->begin();
                    $result = $operation($context);
                    $context->commit();
                    return $result;
                } catch (\Throwable $error) {
                    $failure = $context->rollbackAfter($error);
                    if (!$retrySafe || !$failure->isRetryable() || $attempt === $maxAttempts) {
                        throw $failure;
                    }
                    // Nunca reintentar después de COMMIT ambiguo, HTTP, o
                    // si el ROLLBACK de una transacción abortada falló.
                }
            }
        } finally {
            unset(self::$active[$wpdb]);
        }

        throw new Transaction_Failure('pc_transaction_failed');
    }
}
