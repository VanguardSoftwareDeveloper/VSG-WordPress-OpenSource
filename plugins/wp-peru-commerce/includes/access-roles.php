<?php
/**
 * IMP-11 — perfiles WordPress A y O por sitio.
 *
 * Sólo se escribe en activation hook o admin-post con nonce; no muta roles
 * durante páginas públicas, cron, plugins_loaded o sustitución de ZIP.
 * Nunca se asigna automáticamente un usuario al rol operador.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Access_Roles
{
    public const ADMIN = 'administrator';
    public const OPERATOR = 'perucommerce_operator';

    private const ACTION = 'perucommerce_sync_access_roles';
    private const NONCE = 'perucommerce_sync_access_roles_nonce';

    /** 24 primitivas del x-authz 0.13.0-draft; no introducir caps inventadas. */
    private const ADMIN_CAPS = [
        'perucommerce_appointments_manage',
        'perucommerce_cash_correct',
        'perucommerce_cash_movements_record',
        'perucommerce_cash_record',
        'perucommerce_cash_session_operate',
        'perucommerce_cash_session_review',
        'perucommerce_catalog_edit',
        'perucommerce_catalog_hide',
        'perucommerce_catalog_publish',
        'perucommerce_complaints_manage',
        'perucommerce_digital_files_manage',
        'perucommerce_external_documents_record',
        'perucommerce_integrations_manage',
        'perucommerce_notifications_operate',
        'perucommerce_orders_cancel_unpaid',
        'perucommerce_orders_financial_adjust',
        'perucommerce_orders_fulfill',
        'perucommerce_orders_read',
        'perucommerce_pos_sell',
        'perucommerce_privacy_manage',
        'perucommerce_reconciliation_manage',
        'perucommerce_refunds_record_external',
        'perucommerce_settings_manage',
        'perucommerce_stock_manage',
    ];

    /**
     * Mínimo de tareas del operador; jamás configuración ni ajustes monetarios.
     * D-214/D-215: vende, opera su propia sesión y registra comprobantes
     * externos; no revisa sesiones ajenas, no mueve efectivo ni devuelve.
     */
    private const OPERATOR_CAPS = [
        'read',
        'perucommerce_appointments_manage',
        'perucommerce_cash_record',
        'perucommerce_cash_session_operate',
        'perucommerce_catalog_edit',
        'perucommerce_catalog_hide',
        'perucommerce_external_documents_record',
        'perucommerce_notifications_operate',
        'perucommerce_orders_cancel_unpaid',
        'perucommerce_orders_fulfill',
        'perucommerce_orders_read',
        'perucommerce_pos_sell',
        'perucommerce_stock_manage',
    ];

    public static function registerAdminHooks(): void
    {
        \add_action('admin_notices', [self::class, 'notice']);
        \add_action('admin_post_' . self::ACTION, [self::class, 'handleSync']);
    }

    /** Sólo activation hook / POST administrador verificado. */
    public static function install(): void
    {
        if (!self::mayProvision()) {
            throw new \RuntimeException('pc_roles_provision_denied');
        }
        $admin = \get_role(self::ADMIN);
        if (!$admin instanceof \WP_Role) {
            throw new \RuntimeException('pc_roles_admin_missing');
        }

        $operator = \get_role(self::OPERATOR);
        if (!$operator instanceof \WP_Role) {
            \add_role(
                self::OPERATOR,
                'Operador PeruCommerce',
                \array_fill_keys(self::OPERATOR_CAPS, true)
            );
            $operator = \get_role(self::OPERATOR);
        }
        if (!$operator instanceof \WP_Role) {
            throw new \RuntimeException('pc_roles_operator_missing');
        }

        // Este rol es propio del plugin: no aceptar privilegios heredados o
        // agregados manualmente que permitan editar WordPress o datos A.
        foreach (\array_keys($operator->capabilities) as $cap) {
            if (!\in_array($cap, self::OPERATOR_CAPS, true)) {
                $operator->remove_cap($cap);
            }
        }
        foreach (self::OPERATOR_CAPS as $cap) {
            if (true !== ($operator->capabilities[$cap] ?? null)) {
                $operator->add_cap($cap, true);
            }
        }
        foreach (self::ADMIN_CAPS as $cap) {
            if (true !== ($admin->capabilities[$cap] ?? null)) {
                $admin->add_cap($cap, true);
            }
        }

        if (!self::ready()) {
            throw new \RuntimeException('pc_roles_provision_failed');
        }
    }

    /**
     * No confiar en una opción "roles_installed": comparar estado efectivo
     * de roles. Las actualizaciones ZIP no disparan activation hook.
     */
    public static function ready(): bool
    {
        $admin = \get_role(self::ADMIN);
        $operator = \get_role(self::OPERATOR);
        if (!$admin instanceof \WP_Role || !$operator instanceof \WP_Role) {
            return false;
        }
        foreach (self::ADMIN_CAPS as $cap) {
            if (true !== ($admin->capabilities[$cap] ?? null)) {
                return false;
            }
        }
        if (\count($operator->capabilities) !== \count(self::OPERATOR_CAPS)) {
            return false;
        }
        foreach (self::OPERATOR_CAPS as $cap) {
            if (true !== ($operator->capabilities[$cap] ?? null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * La comprobación del rol es un límite adicional al capability y jamás
     * lo reemplaza. Rechaza usuarios A+O ambiguos y perfiles WordPress ajenos.
     */
    public static function currentRole(): ?string
    {
        if (!self::ready() || !\is_user_logged_in()) {
            return null;
        }
        $user = \wp_get_current_user();
        if (!$user instanceof \WP_User || (int) $user->ID <= 0) {
            return null;
        }
        $roles = $user->roles;
        if (\in_array(self::ADMIN, $roles, true)
            && !\in_array(self::OPERATOR, $roles, true)
            && \current_user_can('manage_options')) {
            return 'A';
        }
        if (\in_array(self::OPERATOR, $roles, true)
            && !\in_array(self::ADMIN, $roles, true)
            && \current_user_can('perucommerce_orders_read')
            && !\current_user_can('manage_options')) {
            return 'O';
        }
        return null;
    }

    public static function operatorCapabilities(): array
    {
        return \array_values(\array_filter(
            self::OPERATOR_CAPS,
            static fn (string $cap): bool => 'read' !== $cap
        ));
    }

    public static function adminCapabilities(): array
    {
        return self::ADMIN_CAPS;
    }

    public static function notice(): void
    {
        if (!self::mayProvision() || self::ready()) {
            return;
        }
        echo '<div class="notice notice-warning"><p>'
            . \esc_html('PeruCommerce: las capacidades A/O todavía no están instaladas o son incompatibles. El panel comercial permanece bloqueado.')
            . '</p><form action="' . \esc_url(\admin_url('admin-post.php')) . '" method="post">';
        echo '<input type="hidden" name="action" value="' . \esc_attr(self::ACTION) . '">';
        \wp_nonce_field(self::NONCE);
        \submit_button('Sincronizar capacidades de PeruCommerce', 'secondary', 'submit', false);
        echo '</form></div>';
    }

    public static function handleSync(): void
    {
        if (!self::mayProvision()) {
            \wp_die('No autorizado.', '', ['response' => 403]);
        }
        \check_admin_referer(self::NONCE);
        $result = 'failed';
        try {
            self::install();
            $result = 'complete';
        } catch (\Throwable $ignored) {
            // No mostrar excepciones sobre usuarios, opciones o hosting.
        }
        \wp_safe_redirect(\add_query_arg(
            'pc_roles_result', $result, \admin_url('plugins.php')
        ));
        exit;
    }

    private static function mayProvision(): bool
    {
        if ((\function_exists('is_network_admin') && \is_network_admin())
            || !\current_user_can('activate_plugins')
            || !\current_user_can('manage_options')) {
            return false;
        }
        $user = \wp_get_current_user();
        return $user instanceof \WP_User
            && \in_array(self::ADMIN, $user->roles, true)
            && !\in_array(self::OPERATOR, $user->roles, true);
    }
}
