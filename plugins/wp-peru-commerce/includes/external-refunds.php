<?php
/**
 * IMP-42 — registro administrativo R8-27 de devolución externa comprobada.
 *
 * IMPORTANTE: NO se envía POST de reembolso al proveedor ni se deduce una
 * devolución de un estado "refund requested". El administrador responsable
 * debe haber cotejado REALMENTE una constancia externa y su referencia
 * inequívoca antes de enviar este DTO. El software preserva su atestación,
 * la procedencia del cobro, las cantidades y la unicidad; NO certifica de
 * forma automática la autenticidad de una constancia proporcionada por A.
 *
 * Locks E-05: root idempotente -> pc_orders -> lecturas del libro histórico
 * y resultado -> CAS pc_orders -> audit -> root completo en un solo COMMIT.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class External_Refunds
{
    private const OP='external_refund_record';
    private const KIND='external_refund';
    private const BASIS='administrator_checked_external_confirmation';
    private static bool $registered=false;
    /** @var \WeakMap<Event_Actor,array<string,mixed>>|null */
    private static ?\WeakMap $active=null;

    /**
     * Verificador exclusivo para este tipo de asiento. El de MP conserva
     * exclusivamente la acreditación original; el de caja exclusivamente
     * los cobros/correcciones. Ningún hook autoriza a ejecutar refunds.
     */
    public static function register(): void
    {
        if (self::$registered) { return; }
        Bootstrap::loadEvents();
        self::$active=new \WeakMap();
        Financial_Events::registerRefundEvidenceVerifier(
            [self::class,'verifiesFact']
        );
        self::$registered=true;
    }

    /**
     * Guard REST: objeto y fuente pertenecen al mismo pedido/sitio, actor
     * A legítimo, entrada cerrada. La transacción vuelve a validar TODO.
     */
    public static function guard(array $ctx,?string $role): array
    {
        if (!self::staffReady($role)) { return []; }
        try {
            $orderId=Domain_Validation::sqlId($ctx['path']['id']??'');
            $body=self::input($ctx['body']??null);
            $authorized=Transaction::run(
                static function(Transaction_Context $tx) use($orderId,$body):bool {
                    $rows=$tx->rows(
                        'SELECT o.id FROM %i o INNER JOIN %i e '
                            .'ON e.order_id=o.id '
                            .'WHERE o.id=%s AND e.id=%s AND e.kind=%s '
                            .'AND e.amount_cents>0 AND e.provider=o.payment_method '
                            .'AND o.currency=%s LIMIT 2',
                        [Orders_Domain::table('pc_orders'),
                            Orders_Domain::table('pc_payment_events'),
                            $orderId,$body['source_payment_event_id'],
                            'initial_charge','PEN']
                    );
                    return 1===\count($rows);
                }
            );
            return $authorized?[
                'authorized'=>true,'same_site'=>true,
                'resource_checked'=>true,'state_checked'=>true,
                'field_checked'=>true,
            ]:[];
        } catch (\Throwable $ignored) { return []; }
    }

    public static function record(string $orderId,array $body): array
    {
        self::staff();
        $orderId=Domain_Validation::sqlId($orderId);
        $body=self::input($body);
        $actor=Event_Actor::staff(['perucommerce_refunds_record_external']);
        $scope=[
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'order',
            'resource_id'=>$orderId,
            'purpose'=>self::OP,
        ];
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $orderId,$body,$actor,$scope
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OP,$body['idempotency_key'],$body
                );
                if (!\in_array($claim['status'],['new','replay'],true)) {
                    throw new Transaction_Failure('pc_refund_conflict');
                }
                $locked=$tx->lockOrder($orderId);
                if ('replay'===$claim['status']) {
                    // Nunca reinterpretar el COMMIT histórico en términos
                    // de saldo o estado actuales, ni insertar otro asiento.
                    return self::replayWithin(
                        $tx,$claim,$orderId,$body
                    );
                }
                $order=self::orderWithin($tx,$orderId,$locked);
                if ((string)$locked['row_version']!==$body['expected_version']) {
                    throw new Transaction_Failure('pc_refund_conflict');
                }
                $source=self::sourceWithin(
                    $tx,$orderId,$body['source_payment_event_id'],$order
                );
                $gross=$source['amount'];
                $history=self::historyWithin($tx,$orderId,$source,$gross);
                $refund=Money::fromApi($body['amount_cents']);
                if ($refund<1 || $refund>$history['remaining']) {
                    throw new Transaction_Failure('pc_refund_limit_exceeded');
                }
                $canonicalRef=\strtolower($body['external_reference']);
                if ($canonicalRef===\strtolower($source['external_reference'])) {
                    // El ID del cobro NO puede reciclarse como supuesta
                    // referencia inequívoca de devolución distinta.
                    throw new Transaction_Failure('pc_refund_reference_invalid');
                }
                if ($canonicalRef===\strtolower($body['evidence'])) {
                    // La constancia es otra referencia: repetir el ID de
                    // devolución en "evidence" no la documenta.
                    throw new Transaction_Failure('pc_refund_evidence_invalid');
                }
                $refToken=self::token($body['external_reference']);
                self::requireUniqueReferenceWithin(
                    $tx,$source,$refToken
                );
                $observedAt=Business_Time::toApi(Business_Time::nowUtc());
                $snapshot=self::snapshot([
                    'kind'=>self::KIND,
                    'source_payment_event_id'=>$source['id'],
                    'amount_cents'=>$body['amount_cents'],
                    'external_reference'=>$body['external_reference'],
                    'evidence_ref'=>$body['evidence'],
                    'provider'=>$source['provider'],
                    'verification_basis'=>self::BASIS,
                    'verified_by'=>(string)\get_current_user_id(),
                    // Hora del REGISTRO ADMINISTRATIVO, no fecha externa
                    // inventada (el DTO no tiene occurred_at del proveedor).
                    'verified_at_utc'=>$observedAt,
                ]);
                $operationId=$claim['operation_id'];
                $fact=[
                    'kind'=>self::KIND,
                    'amount_cents'=>Money::toLedger(-$refund),
                    'currency'=>'PEN',
                    'provider'=>$source['provider'],
                    'account_digest'=>$source['account_digest'],
                    'environment'=>$source['environment'],
                    // Huella SHA-256 determinista del identificador
                    // íntegro de hasta 200 caracteres; no truncar ni filtrar
                    // referencias privadas al listado financiero.
                    'external_reference'=>$refToken,
                    'verification_reference'=>'proof:'
                        .\hash('sha256',$body['evidence']),
                    'occurred_at_utc'=>$observedAt,
                    'payment_attempt_id'=>$source['payment_attempt_id'],
                    'source_payment_event_id'=>$source['id'],
                ];
                self::$active ??= new \WeakMap();
                self::$active[$actor]=[
                    'operation_id'=>$operationId,
                    'order_id'=>$orderId,
                    'fact'=>$fact,
                    'snapshot'=>$snapshot,
                ];
                try {
                    $eventId=Financial_Events::appendWithin(
                        $tx,$actor,$operationId,$orderId,
                        $fact,null,$snapshot
                    );
                    $newVersion=$tx->advanceVersion(
                        'pc_orders',$orderId,$body['expected_version']
                    );
                    // No reescribir cobro original, obligaciones
                    // comerciales o estado E-01. Evento negativo es sólo
                    // salida de fondos que A declara ya verificada fuera.
                    Event_Journal::auditWithin(
                        $tx,$actor,$operationId,'payment_event',$eventId,
                        'external_refund_recorded','manual_external_result',
                        \hash('sha256',$source['id'].':'
                            .(string)$history['refunded'].':'
                            .$body['expected_version']),
                        \hash('sha256',$eventId.':'.$newVersion.':'
                            .(string)($history['refunded']+$refund).':'
                            .$snapshot)
                    );
                    Queue_Outbox::essentialWithin(
                        $tx,$actor,$operationId,$orderId,
                        'external_refund_verified',$eventId
                    );
                    $closed=Orders_Finalization::afterWithin(
                        $tx,$actor,$operationId,$orderId
                    );
                    if(null!==$closed){$newVersion=$closed;}
                    Idempotency::completeLocalWithin(
                        $tx,$operationId,'payment_event',$eventId,200
                    );
                    return self::result(
                        $operationId,$eventId,$newVersion
                    );
                } finally {
                    unset(self::$active[$actor]);
                }
            },true
        );
    }

    /**
     * Verificación interna de que el registro manual proviene EXACTAMENTE
     * de este consumidor, actor staff/root/pedido/fact/snapshot y NO del
     * webhook o de otro escritor del ledger. No autentica una devolución
     * ante MP; requiere constatación humana verdadera antes de R8-27.
     */
    public static function verifiesFact(
        array $fact,string $orderId,Event_Actor $actor,
        ?string $snapshot
    ): bool {
        if ('staff'!==$actor->kind()
            || 'A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_refunds_record_external')
            || null===self::$active || !isset(self::$active[$actor])) {
            return false;
        }
        $saved=self::$active[$actor];
        return $saved['order_id']===$orderId
            && $saved['fact']===$fact
            && $saved['snapshot']===$snapshot
            && $fact['kind']===self::KIND
            && \str_starts_with($fact['external_reference'],'refund:')
            && $fact['source_payment_event_id']!==null
            && Money::fromSignedLedger($fact['amount_cents'])<0;
    }

    /** Pago verificado anterior; no una solicitud ni ajuste comercial. */
    private static function sourceWithin(
        Transaction_Context $tx,string $orderId,string $eventId,array $order
    ): array {
        $rows=$tx->rows(
            'SELECT id,kind,provider,amount_cents,actor_user_id,'
                .'external_reference,payment_attempt_id,'
                .'LOWER(HEX(provider_account_key)) AS account_digest,'
                .'environment FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
            [Orders_Domain::table('pc_payment_events'),$eventId,$orderId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_refund_source_invalid');
        }
        $row=$rows[0];
        $provider=(string)$row['provider'];
        $gross=Money::fromSignedLedger((string)$row['amount_cents']);
        if ($row['kind']!=='initial_charge'
            || !\in_array($provider,['cash','mercado_pago'],true)
            || $provider!==$order['payment_method']
            || $gross<=0 || $gross>$order['total_cents']
            || !\is_string($row['external_reference'])) {
            throw new Transaction_Failure('pc_refund_source_invalid');
        }
        $attemptId=null;$digest=null;$environment=null;
        if ('mercado_pago'===$provider) {
            $attemptId=Domain_Validation::sqlId(
                (string)($row['payment_attempt_id']??'')
            );
            $digest=$row['account_digest'];
            $environment=$row['environment'];
            if (!\is_string($digest)
                || !\preg_match('/\A[a-f0-9]{64}\z/D',$digest)
                || !\in_array($environment,['sandbox','production'],true)
                || $gross!==$order['total_cents']) {
                throw new Transaction_Failure('pc_refund_source_invalid');
            }
            $payments=$tx->rows(
                'SELECT state,provider,amount_cents,provider_payment_id,'
                    .'LOWER(HEX(provider_account_key)) AS digest,environment '
                    .'FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
                [Orders_Domain::table('pc_payment_attempts'),
                    $attemptId,$orderId]
            );
            if (1!==\count($payments)
                || 'mercado_pago'!==$payments[0]['provider']
                || 'APROBADO'!==$payments[0]['state']
                || (string)$payments[0]['amount_cents']!==(string)$gross
                || $payments[0]['provider_payment_id']
                    !==$row['external_reference']
                || $payments[0]['digest']!==$digest
                || $payments[0]['environment']!==$environment) {
                throw new Transaction_Failure('pc_refund_source_invalid');
            }
        } else {
            if (null!==$row['payment_attempt_id']
                || null!==$row['account_digest']
                || null!==$row['environment']
                || null===$row['actor_user_id']) {
                throw new Transaction_Failure('pc_refund_source_invalid');
            }
        }
        return [
            'id'=>$eventId,
            'provider'=>$provider,
            'amount'=>$gross,
            'external_reference'=>$row['external_reference'],
            'payment_attempt_id'=>$attemptId,
            'account_digest'=>$digest,
            'environment'=>$environment,
        ];
    }

    /**
     * Un origen único y solo refunds previos del mismo cobro. Cualquier
     * cash_correction, contracargo, reversión, fee u otro movimiento
     * ambiguo BLOQUEA para conciliación aparte: jamás contabilizarlo
     * como refund ni como neto automáticamente.
     */
    private static function historyWithin(
        Transaction_Context $tx,string $orderId,array $source,int $gross
    ): array {
        $rows=$tx->rows(
            'SELECT id,kind,provider,amount_cents,source_payment_event_id,'
                .'payment_attempt_id,external_reference,'
                .'LOWER(HEX(provider_account_key)) AS account_digest,'
                .'environment,refund_evidence_snapshot,actor_user_id,'
                .'verification_reference '
                .'FROM %i WHERE order_id=%s ORDER BY id LIMIT 201',
            [Orders_Domain::table('pc_payment_events'),$orderId]
        );
        if ([]===$rows || \count($rows)>200) {
            throw new Transaction_Failure('pc_refund_money_unresolved');
        }
        $originals=0;$sum=0;
        foreach ($rows as $row) {
            $amount=Money::fromSignedLedger((string)$row['amount_cents']);
            if ($row['kind']==='initial_charge') {
                ++$originals;
                if ((string)$row['id']!==$source['id']
                    || $amount!==$gross
                    || $row['provider']!==$source['provider']
                    || null!==$row['source_payment_event_id']) {
                    throw new Transaction_Failure('pc_refund_money_unresolved');
                }
                continue;
            }
            if ($row['kind']!==self::KIND
                || (string)$row['source_payment_event_id']!==$source['id']
                || $row['provider']!==$source['provider']
                || $row['payment_attempt_id']!==$source['payment_attempt_id']
                || $row['account_digest']!==$source['account_digest']
                || $row['environment']!==$source['environment']
                || $amount>=0
                || !\is_string($row['refund_evidence_snapshot'])) {
                throw new Transaction_Failure('pc_refund_money_unresolved');
            }
            $snapshot=Orders_Domain::decode(
                $row['refund_evidence_snapshot'],4096
            );
            if (($snapshot['kind']??null)!==self::KIND
                || ($snapshot['source_payment_event_id']??null)!==$source['id']
                || ($snapshot['provider']??null)!==$source['provider']
                || ($snapshot['amount_cents']??null)!==(string)(-$amount)
                || ($snapshot['verification_basis']??null)!==self::BASIS
                || !\is_string($snapshot['external_reference']??null)
                || (string)$row['external_reference']!==self::token(
                    $snapshot['external_reference']
                )
                || !\is_string($snapshot['evidence_ref']??null)
                || !self::opaqueRef($snapshot['external_reference'],200)
                || !self::opaqueRef($snapshot['evidence_ref'],500)
                || ($snapshot['verified_by']??null)
                    !==(string)$row['actor_user_id']
                || !\is_string($snapshot['verified_at_utc']??null)
                || (string)$row['verification_reference']
                    !=='proof:'.\hash('sha256',$snapshot['evidence_ref'])) {
                throw new Transaction_Failure('pc_refund_money_unresolved');
            }
            $sum=Money::addChecked($sum,-$amount);
            if ($sum>$gross) {
                throw new Transaction_Failure('pc_refund_money_unresolved');
            }
        }
        if (1!==$originals) {
            throw new Transaction_Failure('pc_refund_money_unresolved');
        }
        return ['refunded'=>$sum,'remaining'=>$gross-$sum];
    }

    /** La referencia no se reutiliza con otra operación, cobro o pedido. */
    private static function requireUniqueReferenceWithin(
        Transaction_Context $tx,array $source,string $token
    ): void {
        if ('mercado_pago'===$source['provider']) {
            $rows=$tx->rows(
                'SELECT id FROM %i WHERE kind=%s AND provider=%s '
                    .'AND provider_account_key=UNHEX(%s) '
                    .'AND environment=%s AND external_reference=%s LIMIT 2',
                [Orders_Domain::table('pc_payment_events'),
                    self::KIND,'mercado_pago',$source['account_digest'],
                    $source['environment'],$token]
            );
        } else {
            $rows=$tx->rows(
                'SELECT id FROM %i WHERE kind=%s AND provider=%s '
                    .'AND provider_account_key IS NULL AND environment IS NULL '
                    .'AND external_reference=%s LIMIT 2',
                [Orders_Domain::table('pc_payment_events'),
                    self::KIND,'cash',$token]
            );
        }
        if ([]!==$rows) {
            throw new Transaction_Failure('pc_refund_duplicate_reference');
        }
        // Una carrera ENTRE pedidos queda cerrada también por la
        // restricción real UQ(event_dedup_hash) del libro.
    }

    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $orderId,array $body
    ): array {
        if ('payment_event'!==($claim['entity_type']??null)
            || !\is_string($claim['entity_id']??null)
            || 200!==($claim['response_code']??null)) {
            throw new Transaction_Failure('pc_refund_conflict');
        }
        $id=Domain_Validation::sqlId($claim['entity_id']);
        $rows=$tx->rows(
            'SELECT id,kind,order_id,source_payment_event_id,amount_cents,'
                .'actor_user_id,external_reference,refund_evidence_snapshot '
                .'FROM %i WHERE id=%s AND order_id=%s LIMIT 2',
            [Orders_Domain::table('pc_payment_events'),$id,$orderId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_refund_conflict');
        }
        $row=$rows[0];
        if ($row['kind']!==self::KIND
            || (string)$row['source_payment_event_id']
                !==$body['source_payment_event_id']
            || (string)$row['amount_cents']!=='-'.$body['amount_cents']
            || (string)$row['actor_user_id']!==(string)\get_current_user_id()
            || (string)$row['external_reference']!==self::token(
                $body['external_reference']
            )
            || !\is_string($row['refund_evidence_snapshot'])) {
            throw new Transaction_Failure('pc_refund_conflict');
        }
        $snapshot=Orders_Domain::decode(
            $row['refund_evidence_snapshot'],4096
        );
        if (($snapshot['source_payment_event_id']??null)
                !==$body['source_payment_event_id']
            || ($snapshot['amount_cents']??null)!==$body['amount_cents']
            || ($snapshot['external_reference']??null)!==$body['external_reference']
            || ($snapshot['evidence_ref']??null)!==$body['evidence']
            || ($snapshot['verification_basis']??null)!==self::BASIS
            || ($snapshot['verified_by']??null)
                !==(string)\get_current_user_id()) {
            throw new Transaction_Failure('pc_refund_conflict');
        }
        return self::result(
            $claim['operation_id'],$id,
            Orders_Finalization::replayVersionWithin(
                $tx,$orderId,$claim['operation_id'],$body['expected_version']
            )
        );
    }

    private static function orderWithin(
        Transaction_Context $tx,string $orderId,array $locked
    ): array {
        $rows=$tx->rows(
            'SELECT payment_method,currency,total_cents,status,row_version '
                .'FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($rows)
            || (string)$rows[0]['payment_method']
                !==(string)$locked['payment_method']
            || (string)$rows[0]['status']!==(string)$locked['status']
            || (string)$rows[0]['row_version']
                !==(string)$locked['row_version']
            || $rows[0]['currency']!=='PEN'
            || !\in_array($rows[0]['status'],Orders_Domain::STATES,true)) {
            throw new Transaction_Failure('pc_refund_invariant_invalid');
        }
        $total=Money::fromApi((string)$rows[0]['total_cents']);
        if ($total<1) {
            throw new Transaction_Failure('pc_refund_invariant_invalid');
        }
        return [
            'payment_method'=>(string)$rows[0]['payment_method'],
            'total_cents'=>$total,
        ];
    }

    private static function input(mixed $body): array
    {
        $expected=['source_payment_event_id','amount_cents',
            'external_reference','evidence','expected_version','idempotency_key'];
        if (!\is_array($body) || \array_is_list($body)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $keys=\array_keys($body);
        \sort($keys,\SORT_STRING);
        \sort($expected,\SORT_STRING);
        if ($keys!==$expected) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        try {
            Domain_Validation::sqlId($body['source_payment_event_id']);
            Domain_Validation::sqlId($body['expected_version']);
            if (!\is_string($body['amount_cents'])
                || Money::fromApi($body['amount_cents'])<1
                || !\is_string($body['idempotency_key'])
                || !\mb_check_encoding($body['idempotency_key'],'UTF-8')
                || \mb_strlen($body['idempotency_key'],'UTF-8')<16
                || \mb_strlen($body['idempotency_key'],'UTF-8')>128
                || \preg_match('/[\x00-\x20\x7F]/',$body['idempotency_key'])
                || !self::opaqueRef($body['external_reference'],200)
                || !self::opaqueRef($body['evidence'],500)) {
                throw new \InvalidArgumentException();
            }
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return $body;
    }

    /** Identificadores opacos sin URL ni texto libre; A custodia sus pruebas. */
    private static function opaqueRef(mixed $text,int $max): bool
    {
        // La regla de negocio exige constancia inequívoca (>= 8), no
        // recibe URL de archivo ni un relato libre. El servidor no puede
        // inferir si un identificador numérico corresponde a PII: el
        // administrador debe custodiar su constancia fuera del plugin.
        return \is_string($text)
            && \strlen($text)>=8 && \strlen($text)<=$max
            && 1===\preg_match('/\A[A-Za-z0-9._:-]+\z/D',$text);
    }

    /** Huella estable del ID COMPLETO; no lo truncamos a VARCHAR(128). */
    private static function token(string $reference): string
    {
        return 'refund:'.\hash('sha256',\strtolower($reference));
    }

    private static function snapshot(array $data): string
    {
        $encoded=\json_encode($data,
            \JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE|\JSON_THROW_ON_ERROR);
        if (!\is_string($encoded)
            || ''===$encoded || \strlen($encoded)>4096
            || !\mb_check_encoding($encoded,'UTF-8')) {
            throw new Transaction_Failure('pc_refund_invariant_invalid');
        }
        return $encoded;
    }

    private static function staffReady(?string $role): bool
    {
        return 'A'===$role
            && $role===Access_Roles::currentRole()
            && \current_user_can('perucommerce_refunds_record_external')
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin')&&\is_network_admin())
            && Module_Gates::allows('refunds','record');
    }

    private static function staff(): void
    {
        if (!self::staffReady(Access_Roles::currentRole())
            || null===self::$active) {
            throw new Transaction_Failure('pc_refund_denied');
        }
    }

    private static function result(
        string $op,string $eventId,string $version
    ): array {
        return [
            'operation_id'=>$op,
            'entity_id'=>$eventId,
            'row_version'=>$version,
            'result'=>'external_refund_recorded',
            'public_status'=>'external_result_documented',
        ];
    }
}
