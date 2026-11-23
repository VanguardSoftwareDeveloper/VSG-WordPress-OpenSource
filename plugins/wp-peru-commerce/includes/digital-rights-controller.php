<?php
/**
 * IMP-23 — único consumidor REST de derechos digitales en esta fase:
 * R8-60 POST /admin/digital-rights/{id}/revoke.
 *
 * Las operaciones R8-13/14, R8-59 y R8-61 siguen cerradas hasta que sus
 * implementaciones y evidencias se incorporen; registrar un derecho no
 * significa que exista token o streaming habilitado.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Rights_Controller
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadDigitalRights();

        Rest_Router::bind(
            'R8-60',
            static function (array $ctx, \WP_REST_Request $_request): array {
                return [
                    'status' => 200,
                    'body' => Digital_Rights::revoke(
                        (string) $ctx['path']['id'], $ctx['body']
                    ),
                ];
            },
            static fn (array $ctx, ?string $role): array =>
                self::guard($ctx, $role)
        );
    }

    /** Autorización A + capability, instalación/recurso/decisión del caso. */
    private static function guard(array $ctx, ?string $role): array
    {
        if ('A' !== $role
            || 'A' !== Access_Roles::currentRole()
            || !\current_user_can('perucommerce_digital_files_manage')
            || !Module_Gates::allows('digital','write')
            || (int) \get_current_blog_id() < 1) {
            return [];
        }
        $id = $ctx['path']['id'] ?? null;
        $body = $ctx['body'] ?? null;
        if (!\is_string($id) || !\is_array($body)
            || !\is_string($body['case_id'] ?? null)
            || !\is_string($body['evidence_ref'] ?? null)
            || !\is_string($body['expected_version'] ?? null)) {
            return [];
        }
        try {
            $id = Domain_Validation::sqlId($id);
            $caseId = Domain_Validation::sqlId($body['case_id']);
            Domain_Validation::sqlId($body['expected_version']);
            $scope = Transaction::run(static function (Transaction_Context $tx) use (
                $id, $caseId, $body
            ): array {
                return Digital_Rights::scopeWithin(
                    $tx,$id,$caseId,$body['evidence_ref']
                );
            });
            if ((string) $scope['right']['id'] !== $id
                || (string) $scope['case']['id'] !== $caseId) {
                return [];
            }
        } catch (\Throwable $ignored) {
            return [];
        }
        // Estos flags provienen de SELECTs y del verificador legítimo del
        // caso, nunca de campos bool enviados por el cliente o de nonce.
        return [
            'authorized'=>true,
            'same_site'=>true,
            'resource_checked'=>true,
            'state_checked'=>true,
            'field_checked'=>true,
            'digital_right_scope_verified'=>true,
            'case_revocation_authorized'=>true,
        ];
    }
}
