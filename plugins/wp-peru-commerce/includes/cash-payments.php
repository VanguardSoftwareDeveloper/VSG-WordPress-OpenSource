<?php
/**
 * IMP-38 — caja contraentrega R8-24/R8-25.
 *
 * La declaración de efectivo es un acto autorizado de personal WP, no
 * una confirmación del navegador, webhook ni un nuevo intento de MP.
 * Un único cobro inicial íntegro; correcciones append-only vinculadas
 * al original. El saldo y las versiones se validan bajo lock de pedido.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Cash_Payments
{
    private const RECEIPT_OP = 'cash_receipt';
    private const CORRECTION_OP = 'cash_correction';
    private static bool $registered = false;
    /** @var \WeakMap<Event_Actor,array<string,mixed>>|null */
    private static ?\WeakMap $active = null;

    public static function register(): void
    {
        if (self::$registered) { return; }
        Bootstrap::loadEvents();
        self::$active = new \WeakMap();
        Financial_Events::registerCashEvidenceVerifier(
            [self::class,'verifyCashFact']
        );
        self::$registered = true;
    }

    /** Guarda estable, sin reclamar idempotencia ni afectar fondos. */
    public static function guard(
        string $operationId,array $ctx,?string $role
    ): array {
        if (!self::authorized($operationId,$role)) { return []; }
        try {
            $orderId=Domain_Validation::sqlId($ctx['path']['id']??'');
            self::validate($operationId,$ctx['body']??null);
            $valid=Transaction::run(
                static function(Transaction_Context $tx) use($orderId):bool {
                    $rows=$tx->rows(
                        'SELECT id,payment_method,currency FROM %i '
                            .'WHERE id=%s LIMIT 2',
                        [Orders_Domain::table('pc_orders'),$orderId]
                    );
                    return 1===\count($rows)
                        && 'cash'===$rows[0]['payment_method']
                        && 'PEN'===$rows[0]['currency'];
                }
            );
            return $valid ? [
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,
            ] : [];
        } catch (\Throwable $ignored) { return []; }
    }

    public static function receipt(string $orderId,array $body): array
    {
        return self::mutate(self::RECEIPT_OP,$orderId,$body);
    }

    public static function correction(string $orderId,array $body): array
    {
        return self::mutate(self::CORRECTION_OP,$orderId,$body);
    }

    private static function mutate(
        string $operation,string $orderId,array $body
    ): array {
        self::staff($operation);
        $orderId=Domain_Validation::sqlId($orderId);
        $body=self::validate($operation,$body);
        $actor=Event_Actor::staff([
            self::RECEIPT_OP===$operation
                ? 'perucommerce_cash_record' : 'perucommerce_cash_correct'
        ]);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'order',
            'resource_id'=>$orderId,
            'purpose'=>$operation,
        ];
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $operation,$orderId,$body,$actor,$scope
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,$operation,$body['idempotency_key'],$body
                );
                if (!\in_array($claim['status'],['new','replay'],true)) {
                    throw new Transaction_Failure('pc_cash_conflict');
                }
                $order=$tx->lockOrder($orderId);
                $root=self::orderWithin($tx,$orderId,$order);
                if ('cash'!==$root['payment_method']
                    || 'PEN'!==$root['currency']) {
                    throw new Transaction_Failure('pc_cash_denied');
                }
                if ('replay'===$claim['status']) {
                    return self::replayWithin(
                        $tx,$claim,$operation,$orderId,$body
                    );
                }
                if ((string)$order['row_version']!==$body['expected_version']
                    || null===$root['confirmed_at']
                    || (self::RECEIPT_OP===$operation
                        && 'CONFIRMADO'!==$root['status'])
                    || (self::CORRECTION_OP===$operation
                        && !\in_array($root['status'],[
                            'CONFIRMADO','FINALIZADO','CANCELADO'
                        ],true))) {
                    throw new Transaction_Failure('pc_cash_conflict');
                }

                $events=self::eventsWithin($tx,$orderId);
                $snapshot=null;
                $originalId=null;
                if (self::RECEIPT_OP===$operation) {
                    // No se infiere efectivo de status CONFIRMADO: la
                    // persona WP declara la recepción REAL y exacta.
                    if ([]!==$events) {
                        throw new Transaction_Failure('pc_cash_conflict');
                    }
                    $due=self::owedWithin($tx,$orderId,$root['total']);
                    if ($body['amount_cents']!==(string)$due) {
                        throw new Transaction_Failure('pc_cash_amount_mismatch');
                    }
                    $kind='initial_charge';
                    $amount=$due;
                } else {
                    $balance=self::cashBalanceWithin($events);
                    $originalId=$balance['source_id'];
                    if ($balance['original']>$root['total']
                        || $originalId!==$body['source_event_id']) {
                        throw new Transaction_Failure('pc_cash_conflict');
                    }
                    $amount=Money::fromSignedLedger($body['delta_cents']);
                    if (0===$amount) {
                        throw new Transaction_Failure('pc_cash_input_invalid');
                    }
                    $newNet=Money::addChecked($balance['net'],$amount);
                    if ($newNet<0 || $newNet>$balance['original']) {
                        throw new Transaction_Failure('pc_cash_balance_conflict');
                    }
                    $kind='cash_correction';
                    // E-04: motivo íntegro y referencia probatoria
                    // únicamente en el asiento protegido, nunca en
                    // idempotencia, logs ni salida REST.
                    $snapshot=\json_encode([
                        'reason'=>$body['reason'],
                        'evidence_ref'=>$body['evidence']??null,
                        'source_event_id'=>$originalId,
                        'delta_cents'=>$body['delta_cents'],
                    ],\JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES|\JSON_THROW_ON_ERROR);
                    if (!\is_string($snapshot)
                        || \strlen($snapshot)>4096) {
                        throw new Transaction_Failure('pc_cash_input_invalid');
                    }
                }
                $operationId=$claim['operation_id'];
                $fact=[
                    'kind'=>$kind,
                    'amount_cents'=>Money::toLedger($amount),
                    'currency'=>'PEN',
                    'provider'=>'cash',
                    'account_digest'=>null,
                    'environment'=>null,
                    'external_reference'=>'cash:'.$operationId,
                    'verification_reference'=>'cash-proof:'.$orderId.':'.$operationId,
                    'occurred_at_utc'=>\gmdate('Y-m-d\TH:i:s\Z'),
                    'payment_attempt_id'=>null,
                    'source_payment_event_id'=>$originalId,
                ];
                self::$active ??= new \WeakMap();
                self::$active[$actor]=[
                    'order_id'=>$orderId,
                    'operation_id'=>$operationId,
                    'fact'=>$fact,
                ];
                try {
                    $eventId=Financial_Events::appendWithin(
                        $tx,$actor,$operationId,$orderId,$fact,$snapshot
                    );
                    // El pago NO cambia status comercial ni confirma
                    // citas/stock ya comprometidos por R8-07.
                    $version=$tx->advanceVersion(
                        'pc_orders',$orderId,$body['expected_version']
                    );
                    Event_Journal::auditWithin(
                        $tx,$actor,$operationId,'order',$orderId,
                        self::RECEIPT_OP===$operation
                            ? 'cash_receipt_recorded'
                            : 'cash_correction_recorded',
                        self::RECEIPT_OP===$operation
                            ? 'cash_received' : 'cash_corrected',
                        \hash('sha256',$orderId.':'.$body['expected_version']),
                        \hash('sha256',$eventId.':'.(string)$amount
                            .':'.($snapshot??'none'))
                    );
                    Queue_Outbox::essentialWithin(
                        $tx,$actor,$operationId,$orderId,
                        self::RECEIPT_OP===$operation
                            ? 'cash_received':'cash_corrected',$orderId
                    );
                    $closed=Orders_Finalization::afterWithin(
                        $tx,$actor,$operationId,$orderId
                    );
                    if(null!==$closed){$version=$closed;}
                    Idempotency::completeLocalWithin(
                        $tx,$operationId,'payment_event',$eventId,200
                    );
                    return self::result(
                        $operation,$operationId,$eventId,$version
                    );
                } finally {
                    unset(self::$active[$actor]);
                }
            },true
        );
    }

    /** Verificador distinto al de MP, solo dentro del root SQL propio. */
    public static function verifyCashFact(
        array $fact,string $orderId,Event_Actor $actor
    ): bool {
        if ('staff'!==$actor->kind()
            || null===self::$active || !isset(self::$active[$actor])) {
            return false;
        }
        $expected=self::$active[$actor];
        return ($expected['order_id']??null)===$orderId
            && ($expected['fact']??null)===$fact
            && \in_array($fact['kind'],['initial_charge','cash_correction'],true)
            && $fact['provider']==='cash'
            && $fact['external_reference']==='cash:'.$expected['operation_id'];
    }

    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $operation,
        string $orderId,array $body
    ): array {
        $eventId=Domain_Validation::sqlId(
            (string)($claim['entity_id']??'')
        );
        if ('payment_event'!==($claim['entity_type']??null)
            || 200!==($claim['response_code']??null)) {
            throw new Transaction_Failure('pc_cash_conflict');
        }
        $rows=$tx->rows(
            'SELECT id,kind,provider,amount_cents,source_payment_event_id,'
                .'actor_user_id,external_reference,correction_evidence_snapshot '
                .'FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
            [Orders_Domain::table('pc_payment_events'),$eventId,$orderId]
        );
        $kind=self::RECEIPT_OP===$operation
            ? 'initial_charge' : 'cash_correction';
        if (1!==\count($rows) || $rows[0]['kind']!==$kind
            || $rows[0]['provider']!=='cash'
            || (string)$rows[0]['actor_user_id']!==(string)\get_current_user_id()
            || $rows[0]['external_reference']!=='cash:'.$claim['operation_id']
            || (self::RECEIPT_OP===$operation && (
                (string)$rows[0]['amount_cents']!==$body['amount_cents']
                || null!==$rows[0]['source_payment_event_id']
                || null!==$rows[0]['correction_evidence_snapshot']
            ))
            || (self::CORRECTION_OP===$operation && (
                (string)$rows[0]['amount_cents']!==$body['delta_cents']
                || (string)$rows[0]['source_payment_event_id']
                    !==$body['source_event_id']
                || !\is_string($rows[0]['correction_evidence_snapshot'])
            ))) {
            throw new Transaction_Failure('pc_cash_conflict');
        }
        return self::result(
            $operation,$claim['operation_id'],$eventId,
            Orders_Finalization::replayVersionWithin(
                $tx,$orderId,$claim['operation_id'],$body['expected_version']
            )
        );
    }

    /** Fuente única bajo lock de pedido; un cargo MP nunca es efectivo. */
    private static function orderWithin(
        Transaction_Context $tx,string $orderId,array $locked
    ): array {
        $rows=$tx->rows(
            'SELECT payment_method,currency,status,total_cents,confirmed_at,'
                .'row_version FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($rows)
            || (string)$rows[0]['row_version']
                !==(string)$locked['row_version']
            || (string)$rows[0]['status']!==(string)$locked['status']
            || (string)$rows[0]['payment_method']
                !==(string)$locked['payment_method']) {
            throw new Transaction_Failure('pc_cash_invariant_invalid');
        }
        return [
            'payment_method'=>(string)$rows[0]['payment_method'],
            'currency'=>(string)$rows[0]['currency'],
            'status'=>(string)$rows[0]['status'],
            'total'=>Money::fromApi((string)$rows[0]['total_cents']),
            'confirmed_at'=>$rows[0]['confirmed_at'],
        ];
    }

    /** Caja no acredita diferencias parciales como un cobro total. */
    private static function owedWithin(
        Transaction_Context $tx,string $orderId,int $original
    ): int {
        if ($original<=0) {
            throw new Transaction_Failure('pc_cash_invariant_invalid');
        }
        $changes=$tx->rows(
            'SELECT delta_cents FROM %i WHERE order_id=%s '
                .'ORDER BY id LIMIT 201',
            [Orders_Domain::table('pc_order_adjustments'),$orderId]
        );
        if (\count($changes)>200) {
            throw new Transaction_Failure('pc_cash_invariant_invalid');
        }
        $owed=$original;
        foreach ($changes as $change) {
            $owed=Money::addChecked(
                $owed,Money::fromSignedLedger((string)$change['delta_cents'])
            );
        }
        // D-106: no generar deuda adicional y un segundo cobro implícito
        // por delivery/tarifa más alta que el importe pactado.
        if ($owed<1 || $owed>$original) {
            throw new Transaction_Failure('pc_cash_balance_conflict');
        }
        return $owed;
    }

    /** Solo facts anteriores de la misma orden, no del proveedor. */
    private static function eventsWithin(
        Transaction_Context $tx,string $orderId
    ): array {
        $rows=$tx->rows(
            'SELECT id,kind,amount_cents,provider,source_payment_event_id,'
                .'payment_attempt_id,correction_evidence_snapshot '
                .'FROM %i WHERE order_id=%s ORDER BY id LIMIT 201',
            [Orders_Domain::table('pc_payment_events'),$orderId]
        );
        if (\count($rows)>200) {
            throw new Transaction_Failure('pc_cash_invariant_invalid');
        }
        return $rows;
    }

    /**
     * Limitar todas las correcciones al cobro inicial legítimo.
     * Si existe refund/contracargo/cualquier otro asiento pendiente de
     * conciliación, no inventar saldo ni registrar corrección de caja.
     *
     * @return array{source_id:string,net:int,original:int}
     */
    private static function cashBalanceWithin(array $events): array
    {
        if ([]===$events) {
            throw new Transaction_Failure('pc_cash_conflict');
        }
        $source=null;$original=0;$net=0;
        foreach ($events as $event) {
            if ($event['provider']!=='cash'
                || null!==$event['payment_attempt_id']) {
                throw new Transaction_Failure('pc_cash_balance_conflict');
            }
            $amount=Money::fromSignedLedger((string)$event['amount_cents']);
            if ('initial_charge'===$event['kind']) {
                if (null!==$source || $amount<=0
                    || null!==$event['source_payment_event_id']
                    || null!==$event['correction_evidence_snapshot']) {
                    throw new Transaction_Failure('pc_cash_balance_conflict');
                }
                $source=Domain_Validation::sqlId((string)$event['id']);
                $original=$amount;
            } elseif ('cash_correction'===$event['kind']) {
                if (null===$source
                    || (string)$event['source_payment_event_id']!==$source
                    || !\is_string($event['correction_evidence_snapshot'])
                    || 0===$amount) {
                    throw new Transaction_Failure('pc_cash_balance_conflict');
                }
                $proof=Orders_Domain::decode(
                    $event['correction_evidence_snapshot'],4096
                );
                if (($proof['source_event_id']??null)!==$source
                    || ($proof['delta_cents']??null)!==(string)$event['amount_cents']
                    || !\is_string($proof['reason']??null)
                    || ''===\trim($proof['reason'])) {
                    throw new Transaction_Failure('pc_cash_balance_conflict');
                }
            } else {
                throw new Transaction_Failure('pc_cash_balance_conflict');
            }
            $net=Money::addChecked($net,$amount);
        }
        if (null===$source || $net<0 || $net>$original) {
            throw new Transaction_Failure('pc_cash_balance_conflict');
        }
        return ['source_id'=>$source,'net'=>$net,'original'=>$original];
    }

    private static function validate(string $op,mixed $body): array
    {
        if (!\is_array($body) || \array_is_list($body)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $correct=self::CORRECTION_OP===$op;
        $required=$correct
            ? ['source_event_id','delta_cents','reason','expected_version','idempotency_key']
            : ['amount_cents','expected_version','idempotency_key'];
        $optional=$correct ? ['evidence'] : [];
        $keys=\array_keys($body);
        if ([]!==\array_diff($required,$keys)
            || []!==\array_diff($keys,\array_merge($required,$optional))
            || !\is_string($body['expected_version']??null)
            || !\is_string($body['idempotency_key']??null)
            || \strlen($body['idempotency_key'])<16
            || \strlen($body['idempotency_key'])>128
            || !\mb_check_encoding($body['idempotency_key'],'UTF-8')
            || \preg_match('/[\x00-\x20\x7F]/',$body['idempotency_key'])) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try {
            Domain_Validation::sqlId($body['expected_version']);
            if ($correct) {
                Domain_Validation::sqlId($body['source_event_id']);
                if (!\is_string($body['delta_cents'])
                    || 0===Money::fromSignedLedger($body['delta_cents'])) {
                    throw new \InvalidArgumentException();
                }
                self::safeText($body['reason'],1000);
                if (\array_key_exists('evidence',$body)) {
                    self::safeText($body['evidence'],500);
                }
            } else {
                if (!\is_string($body['amount_cents'])
                    || Money::fromApi($body['amount_cents'])<1) {
                    throw new \InvalidArgumentException();
                }
            }
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return $body;
    }

    private static function safeText(mixed $value,int $max): void
    {
        if (!\is_string($value) || ''===\trim($value)
            || \mb_strlen($value,'UTF-8')>$max
            || !\mb_check_encoding($value,'UTF-8')
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)) {
            throw new \InvalidArgumentException();
        }
    }

    private static function authorized(
        string $op,?string $role
    ): bool {
        if (!Module_Gates::allows('cash','write')
            || Access_Roles::currentRole()!==$role
            || (int)\get_current_blog_id()<1) {
            return false;
        }
        if (self::RECEIPT_OP===$op) {
            return \in_array($role,['A','O'],true)
                && \current_user_can('perucommerce_cash_record');
        }
        return self::CORRECTION_OP===$op
            && 'A'===$role
            && \current_user_can('perucommerce_cash_correct');
    }

    private static function staff(string $op): void
    {
        if (!self::authorized($op,Access_Roles::currentRole())) {
            throw new Transaction_Failure('pc_cash_denied');
        }
        if (null===self::$active) {
            throw new Transaction_Failure('pc_cash_not_ready');
        }
    }

    private static function result(
        string $op,string $operationId,string $eventId,string $version
    ): array {
        return [
            'operation_id'=>$operationId,
            'entity_id'=>$eventId,
            'row_version'=>$version,
            'result'=>self::RECEIPT_OP===$op
                ? 'cash_received' : 'cash_corrected',
        ];
    }
}
