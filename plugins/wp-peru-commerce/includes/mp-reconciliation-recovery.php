<?php
/**
 * IMP-30 — recuperación acotada de Orders externas sin intento local.
 *
 * Consulta únicamente la external_reference congelada de una operación
 * MP original y su cuenta histórica. Nunca hace POST, crea otra order,
 * emite checkout_url ni retiene stock/citas. Un GET no acredita dinero.
 */
declare(strict_types=1);
namespace PeruCommerce;
if (!\defined('ABSPATH')) { exit; }

final class MP_Reconciliation_Recovery
{
    private static ?array $active=null;
    private static bool $registered=false;

    public static function registerHooks(): void
    {
        if (self::$registered) { return; }
        self::$registered=true;
        \add_action(Queue_Worker::HOOK,[self::class,'cronTick'],21);
    }

    /** Máximo dos búsquedas oficiales por ejecución, sin asumir cron puntual. */
    public static function cronTick(): void
    {
        try {
            self::scan();
        } catch (\Throwable $ignored) {
            self::$active=null;
        }
    }

    public static function scan(): int
    {
        if (!Module_Gates::allows('reconciliation','write')) {
            return 0;
        }
        Bootstrap::loadReconciliation();
        // Autoridad diferenciada de webhook; un caller REST no puede
        // forjar esta prueba de finalidad, cuenta ni operación.
        if (!self::$registered) {
            // El registro del hook no requiere que haya webhooks activos.
            self::registerHooks();
        }
        static $authorizer=false;
        if (!$authorizer) {
            Idempotency::registerMaintenanceAuthorizer(
                [self::class,'verifiesMaintenance']
            );
            $authorizer=true;
        }
        $cases=Transaction::run(
            static fn(Transaction_Context $tx):array=>$tx->rows(
                'SELECT c.source_operation_id,c.order_id,'
                    . 'LOWER(HEX(i.provider_account_key)) AS account_digest,'
                    . 'i.provider_environment,i.created_at '
                    . 'FROM %i c INNER JOIN %i i '
                    . 'ON i.id=c.source_operation_id '
                    . 'WHERE c.case_type=%s AND c.case_phase=%s '
                    . 'AND c.reason_code=%s AND c.order_id IS NOT NULL '
                    . 'AND i.operation_type=%s AND i.technical_phase IN (%s,%s) '
                    . 'AND i.provider_account_key IS NOT NULL '
                    . 'AND i.provider_environment IN (%s,%s) '
                    . 'AND NOT EXISTS (SELECT 1 FROM %i a '
                    . 'WHERE a.order_id=c.order_id '
                    . 'AND a.mp_idempotency_key=i.provider_idempotency_key '
                    . 'AND a.provider_account_key=i.provider_account_key '
                    . 'AND a.environment=i.provider_environment) '
                    . 'ORDER BY c.opened_at,c.id LIMIT 2',
                [Orders_Domain::table('pc_cases'),
                    Orders_Domain::table('pc_idempotency'),
                    MP_Reconciliation_Cases::KIND,MP_Reconciliation_Cases::OPEN,
                    'mp_attempt_missing','checkout_mp',
                    'external_pending','reconcile','sandbox','production',
                    Orders_Domain::table('pc_payment_attempts')]
            )
        );
        $recovered=0;
        foreach ($cases as $case) {
            try {
                $root=Domain_Validation::sqlId(
                    (string)$case['source_operation_id']
                );
                $orderId=Domain_Validation::sqlId(
                    (string)$case['order_id']
                );
                $digest=(string)$case['account_digest'];
                $environment=(string)$case['provider_environment'];
                if (!\preg_match('/\A[a-f0-9]{64}\z/D',$digest)
                    || !\in_array($environment,
                        ['sandbox','production'],true)) {
                    continue;
                }
                $created=Business_Time::fromDatabase(
                    (string)$case['created_at']
                );
                $now=Business_Time::nowUtc();
                $elapsed=$now->getTimestamp()-$created->getTimestamp();
                // API Orders solo admite ventana <=30 días por consulta.
                // Históricos mayores pasan a revisión manual sin buscar
                // o repetir el POST original.
                if ($elapsed<600 || $elapsed>2591990) { continue; }
                self::$active=[
                    'operation_id'=>$root,'account_digest'=>$digest,
                    'environment'=>$environment,
                ];
                $recovery=Idempotency::recoveryContextForMaintenance(
                    $root,$digest,$environment
                );
                if (($recovery['order_id']??null)!==$orderId) {
                    continue;
                }
                $found=MP_Orders_Client::searchReference(
                    $recovery,$created,$now
                );
                if (1!==\count($found)) { continue; }
                $official=MP_Orders_Client::getOrder(
                    $recovery,$found[0]['provider_order_id']
                );
                Transaction::run(
                    static function(Transaction_Context $tx) use(
                        $root,$orderId,$recovery,$official
                    ): void {
                        $tx->lockOperation($root);
                        $tx->lockOrder($orderId);
                        MP_Payment_Attempts::recoverUnheldWithin(
                            $tx,$recovery,$official
                        );
                        // El caso sigue abierto. No crear hold ni marcar
                        // fondos pagados; el worker firmado o admin deberá
                        // conciliar los efectos del proveedor.
                    },true
                );
                ++$recovered;
            } catch (\Throwable $ignored) {
                // Missing result, ambigüedad, credencial rota o fallo
                // de DB se conservan como caso sin efectos adicionales.
            } finally {
                self::$active=null;
            }
        }
        return $recovered;
    }

    public static function verifiesMaintenance(
        string $operationId,string $accountDigest,string $environment
    ): bool {
        return null!==self::$active
            && self::$active['operation_id']===$operationId
            && self::$active['account_digest']===$accountDigest
            && self::$active['environment']===$environment;
    }
}
