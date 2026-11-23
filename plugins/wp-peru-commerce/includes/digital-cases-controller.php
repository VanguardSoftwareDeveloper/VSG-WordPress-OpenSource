<?php
/**
 * IMP-36 — R8-61, decisión administrativa de expediente digital.
 * No se confunde con R8-60 ni con efectos de cancelación/reintegro.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Cases_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;
    private static bool $adminRegistered=false;

    /**
     * IMP-71: el único puente WordPress nativo para abrir un caso digital
     * tipado desde R8-12 cuando existen varios derechos/archivos. No crea
     * operación REST, no emite enlaces, no revoca ni registra reembolsos.
     */
    public static function registerAdminHooks(): void
    {
        if (self::$adminRegistered) { return; }
        self::$adminRegistered=true;
        \add_action('admin_post_perucommerce_digital_case_open',
            [self::class, 'handleOpenFromGuest']);
        \add_action('admin_notices', [self::class, 'adminNotice']);
    }

    public static function handleOpenFromGuest(): void
    {
        if (!\is_ssl() || 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')
            || !\function_exists('get_current_blog_id')
            || (int)\get_current_blog_id()<1
            || (\function_exists('is_network_admin') && \is_network_admin())
            || 'A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_orders_read')
            || !\current_user_can('perucommerce_digital_files_manage')) {
            \wp_die('No autorizado.', '', ['response'=>403]);
        }
        \check_admin_referer('perucommerce_digital_case_open','_pc_digital_nonce');
        $result='pc_digital_case_open_denied';
        try {
            $expected=[
                '_pc_digital_nonce','action','guest_case_id','right_id',
                'review_reason','idempotency_key',
            ];
            $keys=\array_keys($_POST);
            \sort($keys,\SORT_STRING);
            \sort($expected,\SORT_STRING);
            if ($keys!==$expected) {
                throw new \InvalidArgumentException('pc_digital_case_input_invalid');
            }
            $inputs=[];
            foreach (['guest_case_id','right_id','review_reason','idempotency_key'] as $name) {
                $value=$_POST[$name]??null;
                if (!\is_string($value)) {
                    throw new \InvalidArgumentException('pc_digital_case_input_invalid');
                }
                $inputs[$name]=\wp_unslash($value);
            }
            Bootstrap::loadDigitalCases();
            $case=Digital_Cases::openFromGuest(
                $inputs['guest_case_id'], $inputs['right_id'],
                $inputs['review_reason'], $inputs['idempotency_key']
            );
            if (!\is_array($case)
                || !\is_string($case['case_id']??null)
                || !\is_string($case['operation_id']??null)) {
                throw new \RuntimeException('pc_digital_case_open_denied');
            }
            $result='pc_digital_case_open_recorded';
        } catch (\Throwable $ignored) {
            // Nunca filtrar existencia del pedido, derecho o caso en la URL.
        }
        \wp_safe_redirect(\add_query_arg('pc_digital_case_result',$result,
            \admin_url('admin.php?page=perucommerce')),303);
        exit;
    }

    public static function adminNotice(): void
    {
        if (!\current_user_can('perucommerce_digital_files_manage')
            || !\current_user_can('perucommerce_orders_read')
            || 'A'!==Access_Roles::currentRole()
            || !isset($_GET['pc_digital_case_result'])
            || !\is_string($_GET['pc_digital_case_result'])) {
            return;
        }
        $result=\sanitize_key(\wp_unslash($_GET['pc_digital_case_result']));
        if (!\in_array($result,[
            'pc_digital_case_open_recorded','pc_digital_case_open_denied',
        ],true)) { return; }
        $message='pc_digital_case_open_recorded'===$result
            ? 'PeruCommerce: expediente digital tipado registrado. Consulta nuevamente el pedido y verifica el caso y su versión antes de decidir.'
            : 'PeruCommerce: no se pudo registrar el expediente digital. Comprueba permisos, legitimación original, recurso y estado; no repitas con otra clave antes de revisar el historial.';
        echo '<div class="notice notice-info"><p>'.\esc_html($message).'</p></div>';
    }

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        // Un expediente puede decidirse aunque el almacenamiento privado
        // esté fuera de servicio; no concede streaming ni ventas digitales.
        Module_Gates::registerOperation(
            'digital','cases',static fn():bool=>true
        );
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadDigitalCases();
        // IMP-23 revalida esta decisión para revocar en R8-60.
        // No instalar callback de autorización por defecto permisiva.
        Digital_Rights::registerCaseVerifier(
            [Digital_Cases::class,'verifiesRevocationWithin']
        );
        Rest_Router::bind(
            'R8-61',
            static fn(array $ctx,\WP_REST_Request $_request):array => [
                'status'=>200,
                'body'=>Digital_Cases::decide(
                    $ctx['path']['id'],$ctx['body']
                ),
            ],
            [Digital_Cases::class,'guard']
        );
    }
}
