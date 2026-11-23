<?php
/**
 * IMP-28 — orquestación R8-06 de Checkout Pro Orders.
 *
 * SQL → HTTP → SQL. Un POST externo se intenta como máximo una vez desde
 * esta operación técnica; los replays solo hacen GET/búsqueda. Nunca se
 * acredita dinero por un callback visual ni se crean intentos sin order MP.
 * Un recurso sin hold confirmado jamás recibe checkout_url.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class MP_Checkout_Service
{
    /** @return array{status:int,body:array<string,mixed>} */
    public static function confirm(array $body): array
    {
        if (!Module_Gates::allows('payments','write')
            || !Module_Gates::allows('checkout','write')
            || !Module_Gates::allows('orders','write')) {
            throw new Rest_Failure('pc_module_not_ready',423,true);
        }
        $digest=Checkout_Form::verifiedDigest();
        $actor=Event_Actor::checkout($digest,'checkout_mp');
        $scope=[
            'actor_kind'=>'form','actor_ref'=>$digest,
            'resource_kind'=>'checkout','resource_id'=>'0',
            'purpose'=>'checkout_mp',
        ];
        $credential=MP_Credentials::current('production');
        $capabilities=Quote_Service::capabilities();
        $prepared=Transaction::run(
            static fn (Transaction_Context $tx): array =>
                Checkout_Coordinator::prepareWithin(
                    $tx,$actor,$scope,$body,'mercado_pago',$capabilities,
                    [
                        'account_digest_hex'=>$credential['account_digest_hex'],
                        'environment'=>$credential['environment'],
                    ]
                ),true
        );
        $operationId=$prepared['operation_id']
            ?? $prepared['claim']['operation_id'] ?? null;
        if (!\is_string($operationId)) {
            throw new Transaction_Failure('pc_mp_checkout_unresolved');
        }
        if ('new'!==$prepared['status']) {
            if ('reconcile'===$prepared['status']) {
                return self::resume($scope,$body,$actor,$operationId);
            }
            // El root prepared/complete de checkout_mp nunca da permiso
            // para repetir un POST ni entregar un enlace de seguimiento.
            return self::pending($operationId);
        }
        try {
            $recovery=Idempotency::recoveryContext(
                $scope,$body['idempotency_key'],$body
            );
            $verified=MP_Orders_Client::createPrepared($recovery);
            // Si se pierde GET o expira un hold, conservar primero el ID
            // de order oficial en el TX local, sin crear hold ficticio.
            self::persistAttempt($recovery,$verified);
            $authoritative=MP_Orders_Client::getOrder(
                $recovery,$verified['provider_order_id']
            );
            return self::finish($recovery,$authoritative,$actor,
                $body['contact']['email']??null);
        } catch (\Throwable $failure) {
            // El COMMIT de pre-HTTP existe. Su posible order MP solo
            // se resolverá mediante GET/identidad externa; nunca POST ciego.
            return self::pending($operationId);
        }
    }

    /**
     * R8-06 repetido con la MISMA cookie, clave y payload. Nunca crea
     * nueva order, reemplaza cuenta/ambiente o renueva los 15 min.
     */
    private static function resume(
        array $scope,array $body,Event_Actor $actor,string $operationId
    ): array {
        try {
            $recovery=Idempotency::recoveryContext(
                $scope,$body['idempotency_key'],$body
            );
            $snapshot=self::snapshot($recovery);
            $id=$snapshot['provider_order_id'];
            if (\is_string($id)) {
                $verified=MP_Orders_Client::getOrder($recovery,$id);
                if ($snapshot['ready'] && self::payable($verified)) {
                    return self::ready($recovery,$verified,$snapshot,null);
                }
                // No hubo hold, pero tal vez la admisión pre-HTTP aún
                // esté viva. Solo se reintenta el COMMIT local, no POST.
                if (self::payable($verified)
                    && !$snapshot['tracking_link_issued']) {
                    return self::finish($recovery,$verified,$actor,null);
                }
                return self::pending($operationId,
                    $snapshot['tracking_link_issued']);
            }
            // El POST pudo haber llegado pese a TCP timeout. Buscar
            // únicamente por external_reference congelada y cuenta original.
            $start=self::rootCreated($recovery);
            $now=Business_Time::nowUtc();
            if ($start>$now || $now->getTimestamp()-$start->getTimestamp()>2591990) {
                return self::pending($operationId);
            }
            $found=MP_Orders_Client::searchReference(
                $recovery,$start,$now
            );
            if (1!==\count($found)) {
                return self::pending($operationId);
            }
            $verified=MP_Orders_Client::getOrder(
                $recovery,$found[0]['provider_order_id']
            );
            if (!self::payable($verified)) {
                return self::pending($operationId);
            }
            self::persistAttempt($recovery,$verified);
            return self::finish($recovery,$verified,$actor,null);
        } catch (\Throwable $failure) {
            // NO reinterpretar 404/search vacío como permiso para
            // crear un segundo pedido con el mismo clic.
            return self::pending($operationId);
        }
    }

    /**
     * Persistir localmente el ID de proveedor bajo root y orden antes
     * de cualquier promoción de recursos. No requiere el actor del
     * navegador para redescubrir una order, pero el caller viene de
     * Idempotency::recoveryContext con la cookie intacta.
     */
    private static function persistAttempt(array $recovery,array $verified): void
    {
        if (!self::payable($verified)) {
            throw new Transaction_Failure('pc_mp_attempt_unverified');
        }
        Transaction::run(
            static function (Transaction_Context $tx) use ($recovery,$verified): void {
                $opId=Domain_Validation::sqlId($recovery['operation_id']);
                $orderId=Domain_Validation::sqlId($recovery['order_id']);
                $tx->lockOperation($opId);
                $tx->lockOrder($orderId);
                MP_Payment_Attempts::recordCreatedWithin(
                    $tx,$opId,$orderId,$recovery,$verified
                );
            },true
        );
    }

    /**
     * Solo cuando el GET oficial sigue en created/created, aún impago y
     * todos los recursos (si existen) admitidos o bajo hold inmutable.
     */
    private static function finish(
        array $recovery,array $verified,Event_Actor $actor,?string $email
    ): array {
        $opId=$recovery['operation_id'];
        if (!self::payable($verified)) {
            return self::pending($opId);
        }
        $snapshot=self::snapshot($recovery);
        if ($snapshot['ready']) {
            // Replay: no reconstruir fragmento, no enviar correo otra vez.
            return self::ready($recovery,$verified,$snapshot,null);
        }
        if ($snapshot['tracking_link_issued']) {
            // Un token emitido para una hold vencido NO autoriza un
            // nuevo hold ni una segunda URL para la misma operación.
            return self::pending($opId,true);
        }
        try {
            $result=Transaction::run(
                static function (Transaction_Context $tx) use (
                    $recovery,$verified,$actor
                ): array {
                    $opId=Domain_Validation::sqlId($recovery['operation_id']);
                    $tx->lockOperation($opId);
                    return Checkout_Coordinator::promoteMpWithin(
                        $tx,$actor,$opId,$recovery['order_id'],$recovery,$verified
                    );
                },true
            );
            // Segunda lectura SQL después de COMMIT, no basta un
            // resultado PHP de retenciones que pudo haber expirado.
            $snapshot=self::snapshot($recovery);
            if (!$snapshot['ready']) {
                return self::pending($opId,
                    $snapshot['tracking_link_issued']);
            }
            $tracking=$result['tracking'];
            if (true===($tracking['created']??false)) {
                Checkout_Tracking::sendInitialEmailAfterCommit(
                    $tracking,\is_string($email)?$email:null
                );
            }
            return self::ready($recovery,$verified,$snapshot,$tracking);
        } catch (\Throwable $ignored) {
            // ROLLBACK o COMMIT ambiguo, admisión vencida, inventario
            // cambió o recurso ocupado: sin checkout_url por seguridad.
            return self::pending($opId);
        }
    }

    private static function snapshot(array $recovery): array
    {
        return Transaction::run(
            static function (Transaction_Context $tx) use ($recovery): array {
                $opId=Domain_Validation::sqlId($recovery['operation_id']);
                $tx->lockOperation($opId);
                return MP_Payment_Attempts::snapshotWithin(
                    $tx,$opId,$recovery['order_id']
                );
            }
        );
    }

    private static function rootCreated(array $recovery): \DateTimeImmutable
    {
        return Transaction::run(
            static function (Transaction_Context $tx) use ($recovery): \DateTimeImmutable {
                $opId=Domain_Validation::sqlId($recovery['operation_id']);
                $tx->lockOperation($opId);
                return MP_Payment_Attempts::operationCreatedAtWithin(
                    $tx,$opId,$recovery['order_id']
                );
            }
        );
    }

    private static function payable(array $verified): bool
    {
        return 'created'===($verified['status']??null)
            && 'created'===($verified['status_detail']??null)
            && '0'===($verified['paid_cents']??null)
            && \is_string($verified['checkout_url']??null);
    }

    /**
     * Una URL de mercado se libera solo tras GET actual y hold local
     * totalmente válido. El fragmento del seguimiento se entrega una vez.
     */
    private static function ready(
        array $recovery,array $verified,array $snapshot,?array $tracking
    ): array {
        if (true!==$snapshot['ready'] || !self::payable($verified)
            || !\is_string($snapshot['expires_at']??null)) {
            return self::pending($recovery['operation_id'],
                $snapshot['tracking_link_issued']??false);
        }
        $body=[
            'operation_id'=>$recovery['operation_id'],
            'order_id'=>$recovery['order_id'],
            'order_state'=>'PENDIENTE_PAGO',
            'payment_state'=>'PENDIENTE',
            'checkout_url'=>$verified['checkout_url'],
            'hold_expires_at_utc'=>$snapshot['expires_at'],
            'pending_reconciliation'=>false,
            'tracking_link_issued'=>true,
        ];
        if (\is_string($tracking['tracking_url_once']??null)
            && true===($tracking['created']??false)) {
            $body['tracking_url_once']=$tracking['tracking_url_once'];
        }
        return ['status'=>200,'body'=>$body];
    }

    /**
     * No hay URL, no se declara fondos ni se devuelve token. La raíz
     * existente siempre conserva su operación durable para soporte.
     */
    private static function pending(
        string $operationId,bool $issued=false
    ): array {
        return [
            'status'=>202,
            'body'=>[
                'operation_id'=>Domain_Validation::sqlId($operationId),
                'pending_reconciliation'=>true,
                'tracking_link_issued'=>$issued,
            ],
        ];
    }
}
