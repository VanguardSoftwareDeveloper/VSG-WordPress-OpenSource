<?php
/**
 * IMP-11 — autorización por operación del OpenAPI 0.12.0-draft.
 *
 * Rol + capability son solo dos puertas: toda ruta requiere además guard
 * de servidor por instalación, recurso, estado, finalidad y campos. Un
 * nonce X-WP-Nonce protege CSRF, nunca concede acceso por sí mismo.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Access_Control
{
    /** @var array<string, callable> */
    private static array $guards = [];

    /** @var array<string, callable> */
    private static array $identityVerifiers = [];

    /**
     * Registrar en el archivo REAL del controlador de la operación.
     * El guard revalida contra servidor/BD, no confía en ruta, UI o DTO.
     */
    public static function registerGuard(string $operationId, callable $guard): void
    {
        if (null === Access_Contract::operation($operationId)
            || isset(self::$guards[$operationId])) {
            throw new \LogicException('pc_access_guard_invalid_or_duplicate');
        }
        self::$guards[$operationId] = $guard;
    }

    /**
     * Identidades no-WP: sesión guest/OTP, token digital o webhook firmado.
     * Sin verificador real registrado ninguna de esas operaciones se autoriza.
     */
    public static function registerIdentityVerifier(string $actor, callable $verifier): void
    {
        if (!\in_array($actor, ['guest', 'digital_token', 'digital_session', 'provider'], true)
            || isset(self::$identityVerifiers[$actor])) {
            throw new \LogicException('pc_access_identity_verifier_invalid_or_duplicate');
        }
        self::$identityVerifiers[$actor] = $verifier;
    }

    /**
     * @param array<string,mixed> $requestContext Datos de entrada del controlador.
     */
    public static function allows(
        string $operationId,
        array $requestContext = [],
        ?string $wpRestNonce = null
    ): bool {
        try {
            self::require($operationId, $requestContext, $wpRestNonce);
            return true;
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * Devuelve SOLAMENTE pruebas categóricas producidas por el guard real.
     * El resultado nunca contiene datos privados ni se envía al navegador.
     *
     * @param array<string,mixed> $requestContext
     * @return array<string,mixed>
     */
    public static function require(
        string $operationId,
        array $requestContext = [],
        ?string $wpRestNonce = null
    ): array {
        $rule = Access_Contract::operation($operationId);
        if (null === $rule || true !== $rule['deny']
            || !isset(self::$guards[$operationId])) {
            throw new \RuntimeException('pc_access_denied');
        }

        $role = null;
        if (\in_array($rule['actor'], ['staff', 'admin_only'], true)) {
            $role = Access_Roles::currentRole();
            if (null === $role || !\in_array($role, $rule['roles'], true)
                || !self::staffCookieAndNonce($wpRestNonce)
                || !self::hasCapabilities($rule['all'], $rule['any'])) {
                throw new \RuntimeException('pc_access_denied');
            }
        } elseif ('public' !== $rule['actor']) {
            if (!isset(self::$identityVerifiers[$rule['actor']])) {
                throw new \RuntimeException('pc_access_denied');
            }
            try {
                if (true !== (self::$identityVerifiers[$rule['actor']])(
                    $operationId, $requestContext
                )) {
                    throw new \RuntimeException('pc_access_denied');
                }
            } catch (\Throwable $ignored) {
                throw new \RuntimeException('pc_access_denied');
            }
        }

        try {
            $proof = (self::$guards[$operationId])($requestContext, $role);
        } catch (\Throwable $ignored) {
            throw new \RuntimeException('pc_access_denied');
        }
        if (!\is_array($proof) || true !== ($proof['authorized'] ?? false)
            || (true === $rule['scope'] && true !== ($proof['same_site'] ?? false))
            || (true === $rule['resource']
                && (true !== ($proof['resource_checked'] ?? false)
                    || true !== ($proof['state_checked'] ?? false)
                    || true !== ($proof['field_checked'] ?? false)))
            || (true === $rule['purpose'] && true !== ($proof['purpose_checked'] ?? false))
            || !self::conditional($operationId, $role, $proof)) {
            throw new \RuntimeException('pc_access_denied');
        }
        return $proof;
    }

    /**
     * El registro de rutas en IMP-12 comprobará que una operación no se
     * asoció por error al método/plantilla de otra. No aceptar un ID R8
     * conocido como justificación de una URL o método diferente.
     */
    public static function requireRoute(string $operationId, string $method, string $template): void
    {
        $rule = Access_Contract::operation($operationId);
        if (null === $rule || $rule['method'] !== \strtoupper($method)
            || $rule['path'] !== $template) {
            throw new \RuntimeException('pc_access_route_mismatch');
        }
    }

    /**
     * Rechaza propiedades adicionales a nivel superior del cuerpo REST,
     * sin modificar valores ni justificar un mass assignment a SQL.
     * La validación recursiva de DTO completos corresponde a IMP-12.
     *
     * @param array<string,mixed> $body
     */
    public static function requireKnownBodyFields(string $operationId, array $body): void
    {
        $rule = Access_Contract::operation($operationId);
        if (null === $rule) {
            throw new \InvalidArgumentException('pc_access_invalid_body');
        }
        // Solo R8-17 permite extensiones del proveedor autenticado. Nunca
        // son autoritativas y el DTO/firma se verifican en IMP-12/27.
        if (false === $rule['strict']) {
            return;
        }
        if ([] !== $body && \array_is_list($body)) {
            throw new \InvalidArgumentException('pc_access_invalid_body');
        }
        foreach (\array_keys($body) as $field) {
            if (!\is_string($field) || !\in_array($field, $rule['inputs'], true)) {
                throw new \InvalidArgumentException('pc_access_invalid_body');
            }
        }
    }

    /**
     * Si el endpoint se invoca desde REST, el controlador pasa explícitamente
     * $request->get_header('X-WP-Nonce'). No aceptar application passwords
     * u otras credenciales en rutas staff que exigen sesión de WordPress.
     */
    private static function staffCookieAndNonce(?string $nonce): bool
    {
        if (null === $nonce || '' === $nonce || \strlen($nonce) > 128
            || !\function_exists('wp_validate_auth_cookie')
            || !\function_exists('wp_verify_nonce')) {
            return false;
        }
        $cookieUser = \wp_validate_auth_cookie('', 'logged_in');
        return \is_int($cookieUser) && $cookieUser > 0
            && $cookieUser === (int) \get_current_user_id()
            && false !== \wp_verify_nonce($nonce, 'wp_rest');
    }

    /** @param list<string> $all
     * @param list<string> $any
     */
    private static function hasCapabilities(array $all, array $any): bool
    {
        foreach ($all as $cap) {
            if (!\current_user_can($cap)) {
                return false;
            }
        }
        if ([] !== $any) {
            foreach ($any as $cap) {
                if (\current_user_can($cap)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Los flags provienen de un guard PHP respaldado por lectura/locks del
     * recurso real, NO de una propiedad booleana enviada por cliente.
     *
     * @param array<string,mixed> $proof
     */
    private static function conditional(string $id, ?string $role, array $proof): bool
    {
        switch ($id) {
            case 'R8-09': // Invitado: solo su pedido y sesión no revocada.
                return true === ($proof['guest_order_ownership_verified'] ?? false);

            case 'R8-12': // Grant, replay o misma clave ya reclamada.
                // guest_existing_key_checked NO autoriza una nueva
                // mutación: solo permite que claimWithin devuelva replay
                // original o el conflicto 409 por payload diferente.
                return true===($proof['guest_grant_verified']??false)
                    || true===($proof['original_replay_authorized']??false)
                    || true===($proof['guest_existing_key_checked']??false);

            case 'R8-14': // Binario digital: derecho, sesión y storage privado.
                return true === ($proof['digital_right_verified'] ?? false)
                    && true === ($proof['private_storage_verified'] ?? false);

            case 'R8-21': // No degradar la consulta de case_id a vista O.
                if (true === ($proof['requested_case_detail'] ?? false)) {
                    // El guard R8-21 comprueba tipo, pedido/línea y la
                    // capability específica; no exigir siempre finanzas
                    // para leer un expediente guest de privacidad/entrega.
                    return 'A' === $role
                        && true === ($proof['case_scope_verified'] ?? false)
                        && \current_user_can('perucommerce_orders_read');
                }
                return true;

            case 'R8-23': // D-64: O jamás cancela si alguna vez hubo dinero.
                if ('O' === $role) {
                    return true === ($proof['pending_eligible_line'] ?? false)
                        && false === ($proof['ever_credited'] ?? null)
                        && false === ($proof['financial_adjustment_required'] ?? null)
                        && \current_user_can('perucommerce_orders_cancel_unpaid');
                }
                return 'A' === $role
                    && true === ($proof['pending_eligible_line'] ?? false)
                    && (false === ($proof['financial_adjustment_required'] ?? null)
                        || \current_user_can('perucommerce_orders_financial_adjust'))
                    && (false === ($proof['ever_credited'] ?? null)
                        || \current_user_can('perucommerce_orders_financial_adjust'));

            case 'R8-28': // O propone; A aprueba si cambia costo/cobertura/dinero.
                if ('O' === $role) {
                    return 'propose' === ($proof['decision'] ?? null)
                        && \current_user_can('perucommerce_orders_fulfill');
                }
                if ('A' !== $role) {
                    return false;
                }
                if ('propose' === ($proof['decision'] ?? null)) {
                    return \current_user_can('perucommerce_orders_fulfill');
                }
                return 'approve' === ($proof['decision'] ?? null)
                    && \current_user_can('perucommerce_orders_financial_adjust');

            case 'R8-34': // O solo ocultar. Publicar/republicar requiere A.
                return 'O' === $role
                    ? ('hide' === ($proof['visibility_action'] ?? null)
                        && \current_user_can('perucommerce_catalog_hide'))
                    : ('A' === $role
                        && \in_array($proof['visibility_action'] ?? null,
                            ['hide', 'publish'], true)
                        && \current_user_can(
                            'hide' === ($proof['visibility_action'] ?? null)
                                ? 'perucommerce_catalog_hide' : 'perucommerce_catalog_publish'
                        ));

            case 'R8-40': // Reenvío operador solo operacional.
                return 'A' === $role
                    || ('O' === $role
                        && 'operational' === ($proof['notification_category'] ?? null)
                        && true === ($proof['recipient_authorized'] ?? false));

            case 'R8-53': // O y A: solo horario/ventanas/buffers.
                return true === ($proof['schedule_only'] ?? false);

            case 'R8-26': // Expediente parcial D-200 exige ambas capacidades.
                return 'A' === $role
                    && (true !== ($proof['physical_partial_case'] ?? false)
                        || (\current_user_can('perucommerce_orders_read')
                            && true === ($proof['case_open_verified'] ?? false)));

            case 'R8-50': // Exportación de privacidad, no PER-09 masivo.
                return 'A' === $role
                    && true === ($proof['subject_legitimacy_verified'] ?? false);

            case 'R8-58': // Reemisión NUEVA exige identidad; replay solo mismo scope.
                return 'A' === $role
                    && (true===($proof['buyer_reverified']??false)
                        || true===($proof['tracking_reissue_existing_key_checked']??false));

            case 'R8-59': // Reemisión digital: solo identidad real o replay scoped.
                return 'A' === $role
                    && (true === ($proof['digital_buyer_reverified'] ?? false)
                        || true === ($proof['digital_reissue_existing_key_checked'] ?? false));

            case 'R8-61': // R8-61 decide solo un caso digital de fuente validada.
                return 'A' === $role
                    && true === ($proof['digital_case_scope_verified'] ?? false);

            case 'R8-60': // La existencia de un derecho NO autoriza revocar.
                return 'A' === $role
                    && true === ($proof['digital_right_scope_verified'] ?? false)
                    && true === ($proof['case_revocation_authorized'] ?? false);

            case 'R8-62':
                return 'A' === $role
                    && true === ($proof['digital_file_scope_verified'] ?? false)
                    && true === ($proof['private_storage_verified'] ?? false);

            case 'R8-65':
            case 'R8-66':
                return 'A' === $role
                    && true === ($proof['legal_case_verified'] ?? false);

            case 'R8-67':
            case 'R8-68':
                return 'A' === $role
                    && true === ($proof['evidence_manifest_verified'] ?? false)
                    && true === ($proof['private_storage_verified'] ?? false);

            case 'R8-69':
                return 'A' === $role
                    && true === ($proof['physical_partial_case'] ?? false)
                    && true === ($proof['buyer_identity_verified'] ?? false)
                    && true === ($proof['case_scope_verified'] ?? false);

            case 'R8-70':
                if ('A' !== $role
                    || true !== ($proof['physical_partial_case'] ?? false)
                    || true !== ($proof['case_scope_verified'] ?? false)) {
                    return false;
                }
                // Rechazo motivado no requiere otra verificación del comprador;
                // retiro sí exige nueva legitimación proporcional D-200.
                return match ($proof['decision'] ?? null) {
                    'reject' => true === ($proof['reason_verified'] ?? false),
                    'buyer_withdrawn' => true === ($proof['reason_verified'] ?? false)
                        && true === ($proof['buyer_identity_verified'] ?? false)
                        && true === ($proof['withdrawal_reverified'] ?? false),
                    default => false,
                };
        }
        return true;
    }
}
