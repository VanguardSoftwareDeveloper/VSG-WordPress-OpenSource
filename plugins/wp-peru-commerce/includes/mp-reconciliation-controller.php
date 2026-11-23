<?php
/**
 * IMP-30 — R8-29 / R8-30, conciliación MP administrativa.
 *
 * Autorización A exclusivamente, sin llaves del proveedor ni fondos
 * confiados desde input; REST no ejecuta POST externo ni refunds.
 */
declare(strict_types=1);
namespace PeruCommerce;
if (!\defined('ABSPATH')) { exit; }

final class MP_Reconciliation_Controller
{
    private static bool $gates=false;
    private static bool $registered=false;
    private static bool $hooks=false;

    public static function registerGates(): void
    {
        if (self::$gates) { return; }
        self::$gates=true;
        Module_Gates::registerOperation('reconciliation','read',
            static fn():bool=>true
        );
        Module_Gates::registerOperation('reconciliation','write',
            static fn():bool=>true
        );
        // No mp_orders obligatorio para LEER expedientes o cerrar un
        // intento terminal tras GET; para reassign el servicio EXIGE
        // payments.write/P-C y prueba oficial de acreditación.
    }

    /** Detección oportunista acotada, no un polling de cobros continuo. */
    public static function registerHooks(): void
    {
        if (self::$hooks) { return; }
        self::$hooks=true;
        \add_action(Queue_Worker::HOOK,[self::class,'scan'],20);
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-reconciliation-recovery.php';
        MP_Reconciliation_Recovery::registerHooks();
    }

    public static function scan(): void
    {
        try {
            Bootstrap::loadReconciliation();
            MP_Reconciliation_Cases::detectStale();
        } catch (\Throwable $ignored) {
            // Las raíces no cambian por un diagnóstico fallido.
        }
    }

    public static function register(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        Bootstrap::loadReconciliation();
        Rest_Router::bind(
            'R8-29',
            static fn(array $context,\WP_REST_Request $_request): array=>[
                'status'=>200,
                'body'=>MP_Reconciliation_Cases::listing($context['query']),
            ],
            static fn(array $context,?string $role): array=>
                self::guard('R8-29',$context,$role)
        );
        Rest_Router::bind(
            'R8-30',
            static fn(array $context,\WP_REST_Request $_request): array=>[
                'status'=>200,
                'body'=>MP_Reconciliation_Service::resolve(
                    $context['path']['id'],$context['body']
                ),
            ],
            static fn(array $context,?string $role): array=>
                self::guard('R8-30',$context,$role)
        );
    }

    private static function guard(
        string $operationId,array $context,?string $role
    ): array {
        if ('A'!==$role
            || !\current_user_can('perucommerce_reconciliation_manage')
            || (int)\get_current_blog_id()<1
            || (\function_exists('is_network_admin')&&\is_network_admin())) {
            return [];
        }
        $proof=[
            'authorized'=>true,'same_site'=>true,
            'resource_checked'=>true,'state_checked'=>true,'field_checked'=>true,
        ];
        if ('R8-29'===$operationId) {
            return $proof;
        }
        $caseId=$context['path']['id']??null;
        if (!\is_string($caseId)) { return []; }
        try {
            $caseId=Domain_Validation::sqlId($caseId);
            $valid=Transaction::run(
                static function(Transaction_Context $tx) use ($caseId): bool {
                    $rows=$tx->rows(
                        'SELECT id,case_phase,order_id,source_operation_id '
                            . 'FROM %i WHERE id=%s AND case_type=%s',
                        [Orders_Domain::table('pc_cases'),$caseId,
                            MP_Reconciliation_Cases::KIND]
                    );
                    return 1===\count($rows)
                        && null!==$rows[0]['source_operation_id']
                        && \in_array($rows[0]['case_phase'],[
                            MP_Reconciliation_Cases::OPEN,
                            MP_Reconciliation_Cases::CLOSED_REASSIGNED,
                            MP_Reconciliation_Cases::CLOSED_NO_CHARGE,
                        ],true);
                }
            );
            return $valid ? $proof : [];
        } catch (\Throwable $ignored) {
            return [];
        }
    }
}
