<?php
/**
 * IMP-13 — servicio R8-45/46: CAS, idempotencia, auditoría y versiones
 * inmutables de condiciones/privacidad.
 *
 * Los métodos operativos se llaman solo tras autorización A real; el cambio
 * de flags no habilita gates comerciales sin evidencia P-C de cada módulo.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Settings_Service
{
    private const RESOURCE_ID = '1';
    private const MAX_PATCH_BYTES = 90000;

    /** Lectura R8-45 sin escribir defaults en la base de datos. */
    public static function read(): array
    {
        self::administrator();
        return Transaction::run(static function (Transaction_Context $tx): array {
            return self::viewWithin($tx, Settings_Store::readWithin($tx)['settings']);
        });
    }

    /**
     * R8-46: primero claim idempotente, luego lock de opción, versiones
     * históricas, CAS exacto, evento de auditoría y complete, UN SOLO COMMIT.
     *
     * @param array<string,mixed> $request
     * @return array<string,mixed> AdminSettings sin secretos
     */
    public static function update(array $request): array
    {
        self::administrator();
        Settings_Validation::patch($request);
        if (!\is_string($request['expected_version'] ?? null)
            || !\is_string($request['idempotency_key'] ?? null)) {
            throw new Rest_Failure('pc_field_invalid', 422);
        }
        $oldVersion = Domain_Validation::sqlId($request['expected_version']);
        $key = $request['idempotency_key'];
        $payload = $request;
        unset($payload['idempotency_key']);
        if (\strlen(\json_encode($payload, \JSON_THROW_ON_ERROR)) > self::MAX_PATCH_BYTES) {
            throw new Rest_Failure('pc_request_too_large', 400);
        }

        $actorId = (string) \get_current_user_id();
        $scope = [
            'actor_kind' => 'staff',
            'actor_ref' => $actorId,
            'resource_kind' => 'settings',
            'resource_id' => '0',
            'purpose' => 'settings_patch',
        ];
        $actor = Event_Actor::staff(['perucommerce_settings_manage']);
        try {
            return Transaction::run(static function (Transaction_Context $tx) use (
                $scope, $key, $payload, $oldVersion, $actor
            ): array {
                $claim = Idempotency::claimWithin(
                    $tx, $scope, 'settings_patch', $key, $payload
                );
                if ('replay' === $claim['status']) {
                    $current = Settings_Store::readWithin($tx)['settings'];
                    // Un replay nunca aplica efectos. Si otra edición
                    // ocurrió después, exigir refresh en vez de fingir
                    // que la respuesta histórica es el estado actual.
                    if ('settings' !== $claim['entity_type']
                        || (string) $claim['entity_id'] !== $current['row_version']) {
                        throw new Transaction_Failure('pc_settings_replay_superseded');
                    }
                    return self::viewWithin($tx, $current);
                }
                if ('new' !== $claim['status']) {
                    throw new Transaction_Failure('pc_settings_operation_pending');
                }

                $record = Settings_Store::ensureWithin($tx);
                $current = $record['settings'];
                $raw = $record['raw'];
                if (!\is_string($raw) || $current['row_version'] !== $oldVersion) {
                    throw new Transaction_Failure('pc_settings_version_conflict');
                }

                $updated = $current;
                foreach (['business', 'fiscal', 'delivery_enabled', 'payment_enabled',
                    'digital_download_limit', 'map_tiles', 'mail_sender',
                    'routine_buyer_emails_enabled'] as $field) {
                    // Omisión conserva el valor anterior. Null solo se
                    // admite para la cuota digital, verificado por schema.
                    if (\array_key_exists($field, $payload)) {
                        // Un objeto mail_sender vacío significa quitar
                        // configuración opcional, no emitir [] como JSON.
                        if ('mail_sender' === $field) {
                            if ([] === $payload[$field]) {
                                // El objeto {} elimina remitente opcional.
                                unset($updated['mail_sender']);
                            } else {
                                // A diferencia de map_tiles (reemplazo
                                // completo), un campo del remitente no debe
                                // borrar otros atributos al omitirse.
                                $updated['mail_sender'] = \array_replace(
                                    $current['mail_sender'] ?? [], $payload[$field]
                                );
                            }
                        } elseif ('fiscal' === $field) {
                            // FiscalSettings admite PATCH parcial y {}.
                            $updated['fiscal'] = \array_replace(
                                $current['fiscal'], $payload[$field]
                            );
                        } elseif ('business' === $field) {
                            // Campos legales opcionales no se pierden al
                            // cambiar solo nombre/complaint_mode. A
                            // diferencia del mapa, no es reemplazo total.
                            $updated['business'] = \array_replace(
                                $current['business'], $payload[$field]
                            );
                        } else {
                            $updated[$field] = $payload[$field];
                        }
                    }
                }

                foreach ([
                    'policy_notice' => ['policy_version_id', 'commercial_terms'],
                    'privacy_notice' => ['privacy_version_id', 'privacy_notice'],
                ] as $field => [$idKey, $kind]) {
                    if (!\array_key_exists($field, $payload)) {
                        continue;
                    }
                    $previousText = null === $current[$idKey] ? null
                        : Settings_Store::policyWithin($tx, $current[$idKey], $kind);
                    // Mismo contenido textual: conservar versión y fecha.
                    if ($payload[$field] !== $previousText) {
                        $updated[$idKey] = Settings_Store::publishPolicyWithin(
                            $tx, $kind, $payload[$field]
                        );
                    }
                }

                $updated['row_version'] = Settings_Store::nextVersion($oldVersion);
                Settings_Store::compareAndSwapWithin($tx, $raw, $updated);
                Event_Journal::auditWithin(
                    $tx, $actor, $claim['operation_id'],
                    'settings', self::RESOURCE_ID, 'settings_updated',
                    null, \hash('sha256', $raw),
                    \hash('sha256', Settings_Store::encode($updated))
                );
                Idempotency::completeLocalWithin(
                    $tx, $claim['operation_id'], 'settings',
                    $updated['row_version'], 200
                );

                return self::viewWithin($tx, $updated);
            }, true);
        } finally {
            // También en COMMIT incierto: los lectores de PeruCommerce
            // consultan SQL directo, pero WP puede haber cacheado ausencia.
            Settings_Store::invalidateCache();
        }
    }

    /**
     * Comprobar que existen textos históricos antes de habilitar checkout.
     * No convierte valores "payment_enabled" en prueba de pasarela lista.
     */
    public static function noticesReady(): bool
    {
        try {
            return Transaction::run(static function (Transaction_Context $tx): bool {
                $s = Settings_Store::readWithin($tx)['settings'];
                if (null === $s['policy_version_id'] || null === $s['privacy_version_id']
                    || 'Comercio sin configurar' === $s['business']['business_name']) {
                    return false;
                }
                Settings_Store::policyWithin($tx, $s['policy_version_id'], 'commercial_terms');
                Settings_Store::policyWithin($tx, $s['privacy_version_id'], 'privacy_notice');
                return true;
            });
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * @return array<string,mixed> El DTO AdminSettings, nunca credenciales MP.
     */
    private static function viewWithin(Transaction_Context $tx, array $s): array
    {
        $result = [
            'business' => $s['business'],
            'fiscal' => $s['fiscal'],
            'delivery_enabled' => $s['delivery_enabled'],
            'payment_enabled' => $s['payment_enabled'],
            // El webhook aún no está conectado (IMP-29). Configurada
            // significa solo credencial cifrada legible, NO MP validado.
            'webhook_connected' => false,
            'provider_configured' => self::storedMpCredentialsWithin($tx),
            'row_version' => $s['row_version'],
            'digital_download_limit' => $s['digital_download_limit'],
            'map_tiles' => $s['map_tiles'],
            'routine_buyer_emails_enabled' => $s['routine_buyer_emails_enabled'],
        ];
        if (isset($s['mail_sender'])) {
            $result['mail_sender'] = $s['mail_sender'];
        }
        foreach ([
            'policy_version_id' => ['policy_version', 'policy_notice', 'commercial_terms'],
            'privacy_version_id' => ['privacy_version', 'privacy_notice', 'privacy_notice'],
        ] as $idKey => [$versionKey, $bodyKey, $kind]) {
            if (null === $s[$idKey]) {
                continue;
            }
            $result[$versionKey] = $s[$idKey];
            $result[$bodyKey] = Settings_Store::policyWithin($tx, $s[$idKey], $kind);
        }
        return $result;
    }

    /**
     * Una bóveda corrupta o clave ausente bloquea MP sin impedir leer
     * condiciones comerciales desde R8-45. No devolver diagnósticos secretos.
     */
    private static function storedMpCredentialsWithin(Transaction_Context $tx): bool
    {
        try {
            require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-credentials.php';
            return MP_Credentials::configuredWithin($tx);
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /** Solo administrador WP del sitio con capability real del plugin. */
    private static function administrator(): void
    {
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-roles.php';
        if ('A' !== Access_Roles::currentRole()
            || !\current_user_can('perucommerce_settings_manage')) {
            throw new Rest_Failure('pc_access_denied', 403);
        }
    }
}
