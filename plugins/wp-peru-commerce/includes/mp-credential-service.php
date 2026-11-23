<?php
/**
 * IMP-27 — R8-47: rotación admin-only de credenciales cifradas en SQL.
 * Nunca verifica una order de compra ni habilita la pasarela por escritura.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Credential_Service
{
    /**
     * @param array<string,mixed> $body PaymentCredentialUpdate OpenAPI 0.12
     * @return array<string,mixed> MutationResult sin material secreto
     */
    public static function rotate(array $body): array
    {
        self::administrator();
        $environment = $body['environment'] ?? null;
        $token = $body['access_token'] ?? null;
        $webhook = $body['webhook_secret'] ?? null;
        if (!\is_string($environment) || !\is_string($token)
            || (null !== $webhook && !\is_string($webhook))
            || !\is_string($body['expected_version'] ?? null)
            || !\is_string($body['idempotency_key'] ?? null)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'payment_credentials');
        }
        $oldVersion = Domain_Validation::sqlId($body['expected_version']);
        $actor = Event_Actor::staff(['perucommerce_integrations_manage']);
        $scope = [
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'settings',
            'resource_id'=>'0',
            'purpose'=>'mp_credential_rotation',
        ];
        $idempotencyKey = $body['idempotency_key'];
        // Idempotency persiste únicamente el SHA-256 de este payload, no el
        // token. La autorización de la sesión de administrador es obligatoria.
        try {
            return Transaction::run(
                static function (Transaction_Context $tx) use (
                    $scope,$idempotencyKey,$body,$oldVersion,
                    $environment,$token,$webhook,$actor
                ): array {
                    $claim = Idempotency::claimWithin(
                        $tx,$scope,'mp_credential_rotation',
                        $idempotencyKey,$body
                    );
                    if ('replay' === $claim['status']) {
                        $state = Settings_Store::readWithin($tx)['settings'];
                        MP_Credentials::readWithin($tx); // exige bóveda íntegra.
                        if ('payment_credentials' !== ($claim['entity_type'] ?? null)
                            || (string)($claim['entity_id'] ?? '') !== $state['row_version']) {
                            throw new Transaction_Failure('pc_mp_rotation_superseded');
                        }
                        return self::response($claim['operation_id'],
                            $state['row_version'],'replayed');
                    }
                    if ('new' !== $claim['status']) {
                        throw new Transaction_Failure('pc_mp_operation_pending');
                    }
                    // La versión pública de R8-47 es el mismo
                    // AdminSettings.row_version de R8-45: no hay GET
                    // de versiones secretas ni nueva propiedad OpenAPI.
                    $settingsState = Settings_Store::ensureWithin($tx);
                    $settings = $settingsState['settings'];
                    if ($settings['row_version'] !== $oldVersion) {
                        throw new Transaction_Failure('pc_mp_version_conflict');
                    }
                    $state = MP_Credentials::readWithin($tx,true);
                    $updated = MP_Credentials::rotateWithin(
                        $tx,$state,$environment,$token,$webhook
                    );
                    $settings['row_version'] = Settings_Store::nextVersion($oldVersion);
                    Settings_Store::compareAndSwapWithin(
                        $tx,$settingsState['raw'],$settings
                    );
                    Event_Journal::auditWithin(
                        $tx,$actor,$claim['operation_id'],'settings','1',
                        'mp_credentials_rotated','admin_rotation',
                        null,
                        \hash('sha256',
                            $updated['account_digest_hex'] . ':' . $settings['row_version'])
                    );
                    Idempotency::completeLocalWithin(
                        $tx,$claim['operation_id'],'payment_credentials',
                        $settings['row_version'],200
                    );
                    return self::response($claim['operation_id'],
                        $settings['row_version'],'updated');
                },true
            );
        } finally {
            // También tras COMMIT incierto. Otros plugins podrían usar options
            // cache, aunque la bóveda del dominio siempre lee SQL coherente.
            MP_Credentials::invalidateCache();
            Settings_Store::invalidateCache();
        }
    }

    /** Estado guardado != cuenta/MP Orders comprobados en Perú. */
    private static function response(
        string $operationId,string $version,string $result
    ): array {
        return [
            'operation_id'=>$operationId,
            'entity_id'=>'1',
            'row_version'=>$version,
            'result'=>$result,
            'public_status'=>'stored_unverified',
        ];
    }

    private static function administrator(): void
    {
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-roles.php';
        if ('A' !== Access_Roles::currentRole()
            || !\current_user_can('perucommerce_integrations_manage')) {
            throw new Rest_Failure('pc_access_denied',403);
        }
    }
}
