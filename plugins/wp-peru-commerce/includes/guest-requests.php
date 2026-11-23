<?php
/**
 * IMP-33 — R8-12: solicitud invitada, expediente original y primer evento.
 *
 * Una petición solamente abre revisión. Nunca cambia destino, stock,
 * venta, saldo, derechos, correo o enlace por poseer un grant. La
 * idempotencia se reclama ANTES del consumo: replay de la misma sesión
 * y payload recupera el case_id sin reutilizar el OTP.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Guest_Requests
{
    private const OPERATION='guest_case_request';
    private const OPEN='open_review';
    private const REASON='guest_request_received';
    /** @var array{order_id:string,session_id:string,purpose:string}|null */
    private static ?array $active=null;
    private static bool $verifiersRegistered=false;

    public static function registerVerifiers(): void
    {
        if (self::$verifiersRegistered) { return; }
        Event_Actor::registerGuestVerifier([self::class,'verifiesActor']);
        Idempotency::registerGuestScopeVerifier([self::class,'verifiesScope']);
        self::$verifiersRegistered=true;
    }

    /**
     * Guard de R8-12. La sesión debe ser válida aunque la clave ya se
     * haya completado; así se conserva 409 para igual clave/payload
     * diferente aun cuando el grant original esté consumido.
     *
     * @return array<string,mixed> pruebas generadas en el servidor
     */
    public static function guard(array $body,?string $role): array
    {
        if (null!==$role || (int)\get_current_blog_id()<1) {
            return [];
        }
        try {
            $identity=self::preflight($body);
            $scope=self::scope($identity);
            $key=$body['idempotency_key']??null;
            if (!\is_string($key)) {
                self::$active=null;
                return [];
            }
            $proof=[
                'authorized'=>true,'resource_checked'=>true,
                'state_checked'=>true,'field_checked'=>true,
            ];
            if (Idempotency::keyExistsForScope(
                $scope,self::OPERATION,$key,$body
            )) {
                $proof['guest_existing_key_checked']=true;
                return $proof;
            }
            if (Guest_Action_Grants::availableFor(
                $identity['order_id'],$identity['session_id'],
                $identity['purpose']
            )) {
                $proof['guest_grant_verified']=true;
                return $proof;
            }
            self::$active=null;
            return [];
        } catch (\Throwable $ignored) {
            self::$active=null;
            return [];
        }
    }

    /** @return array{case_id:string,accepted:bool,review_required:bool} */
    public static function submit(array $body): array
    {
        Guest_Sessions::sameOrigin('POST');
        $identity=self::preflight($body);
        $input=self::original($body);
        $key=$body['idempotency_key']??null;
        if (!\is_string($key)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'idempotency_key');
        }
        $scope=self::scope($identity);
        $actor=Event_Actor::guest($identity['session_id'],self::OPERATION);
        try {
            $result=Transaction::run(
                static function(Transaction_Context $tx) use(
                    $scope,$key,$body,$actor,$identity,$input
                ): array {
                    $claim=Idempotency::claimWithin(
                        $tx,$scope,self::OPERATION,$key,$body
                    );
                    if (!\in_array($claim['status'],['new','replay'],true)) {
                        throw new Transaction_Failure('pc_guest_request_pending');
                    }
                    // Igual en replay: revocar la sesión o enlace fuente
                    // debe impedir recuperar el ID histórico del caso.
                    $verified=Guest_Sessions::lockedForSensitiveWithin($tx);
                    if ($verified['order_id']!==$identity['order_id']
                        || $verified['session_id']!==$identity['session_id']) {
                        throw new Transaction_Failure('pc_guest_request_denied');
                    }
                    $orderId=$verified['order_id'];
                    if ('replay'===$claim['status']) {
                        return [
                            'response'=>self::replayWithin($tx,$claim,
                                $orderId,$identity['purpose']),
                            'session'=>null,
                        ];
                    }
                    self::eligibleWithin(
                        $tx,$orderId,$identity['purpose']
                    );
                    // Sólo una operación realmente nueva consume grant.
                    // El mismo COMMIT incluye caso, evento, auditoría y
                    // resultado idempotente; rollback conserva el grant.
                    Guest_Action_Grants::consumeWithin(
                        $tx,$orderId,$verified['session_id'],
                        $identity['purpose']
                    );
                    $session=Guest_Sessions::renewSensitiveWithin(
                        $tx,$verified
                    );
                    $caseId=self::createWithin(
                        $tx,$actor,$claim['operation_id'],
                        $orderId,$identity['purpose'],$input
                    );
                    Idempotency::completeLocalWithin(
                        $tx,$claim['operation_id'],'case',$caseId,202
                    );
                    return [
                        'response'=>self::accepted($caseId),
                        'session'=>$session,
                    ];
                },true
            );
            // Cookies de sesión solo después de COMMIT inequívoco.
            // El replay no reconstruye ni modifica un grant consumido.
            if (\is_array($result['session'])) {
                Guest_Sessions::refreshCookie($result['session']);
            }
            return $result['response'];
        } finally {
            self::$active=null;
        }
    }

    /** Comprobar identidad fresca para guard y handler, no confiar body. */
    private static function preflight(array $body): array
    {
        Guest_Sessions::sameOrigin('POST');
        $purpose=Guest_Challenges::purpose($body['purpose']??null);
        $session=Guest_Sessions::currentForPost();
        self::$active=[
            'order_id'=>Domain_Validation::sqlId($session['order_id']),
            'session_id'=>Domain_Validation::sqlId($session['session_id']),
            'purpose'=>$purpose,
        ];
        return self::$active;
    }

    private static function scope(array $identity): array
    {
        return [
            'actor_kind'=>'guest',
            'actor_ref'=>$identity['session_id'],
            'resource_kind'=>'order',
            'resource_id'=>$identity['order_id'],
            'purpose'=>$identity['purpose'],
        ];
    }

    /** Verificador de Idempotency::digests invocado DENTRO del root SQL. */
    public static function verifiesScope(array $scope): bool
    {
        return null!==self::$active
            && ($scope['actor_kind']??null)==='guest'
            && ($scope['actor_ref']??null)===self::$active['session_id']
            && ($scope['resource_kind']??null)==='order'
            && ($scope['resource_id']??null)===self::$active['order_id']
            && ($scope['purpose']??null)===self::$active['purpose'];
    }

    /** Verificador del primer evento guest, distinto de cookie checkout. */
    public static function verifiesActor(string $reference,string $purpose): bool
    {
        return null!==self::$active
            && $purpose===self::OPERATION
            && $reference===self::$active['session_id'];
    }

    /** Snapshot privado ORIGINAL: preservar texto, nunca secretos/OTP. */
    private static function original(array $body): array
    {
        $purpose=Guest_Challenges::purpose($body['purpose']??null);
        $allowed=['purpose','reason','requested_ubigeo',
            'requested_address','case_details','idempotency_key'];
        if ([]!==\array_diff(\array_keys($body),$allowed)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $result=['purpose'=>$purpose];
        foreach (['reason'=>1000,'requested_address'=>500,
            'case_details'=>3000] as $field=>$max) {
            if (!\array_key_exists($field,$body)) { continue; }
            $v=$body[$field];
            if (!\is_string($v)
                || !\mb_check_encoding($v,'UTF-8')
                || ''===\trim($v)
                || \mb_strlen($v,'UTF-8')>$max
                || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$v)) {
                throw new Rest_Failure('pc_field_invalid',422,false,$field);
            }
            $result[$field]=$v;
        }
        if (\array_key_exists('requested_ubigeo',$body)) {
            if (!\is_string($body['requested_ubigeo']??null)
                || !\preg_match('/\A[0-9]{6}\z/D',
                    $body['requested_ubigeo'])) {
                throw new Rest_Failure('pc_field_invalid',422,false,
                    'requested_ubigeo');
            }
            $result['requested_ubigeo']=$body['requested_ubigeo'];
        }
        if ('delivery_change'===$purpose) {
            if (!isset($result['requested_ubigeo'],$result['requested_address'])) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
        } elseif (isset($result['requested_address'])
            || isset($result['requested_ubigeo'])) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        return $result;
    }

    /**
     * Validar solo elegibilidad para ENVIAR solicitud a revisión.
     * No cambiar estados E-01, saldos, tarifa, cita ni descargas.
     */
    private static function eligibleWithin(
        Transaction_Context $tx,string $orderId,string $purpose
    ): void {
        $order=$tx->lockedOrder($orderId);
        $rows=$tx->rows(
            'SELECT status,original_delivery_snapshot FROM %i WHERE id=%s',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($rows)
            || (string)$rows[0]['status']!==(string)$order['status']) {
            throw new Transaction_Failure('pc_guest_request_ineligible');
        }
        if ('delivery_change'===$purpose) {
            if (\in_array((string)$order['status'],
                ['CANCELADO','FINALIZADO'],true)) {
                throw new Transaction_Failure('pc_guest_request_ineligible');
            }
            $delivery=Orders_Domain::deliverySnapshot(
                $rows[0]['original_delivery_snapshot']
            );
            if (null===$delivery
                || ($delivery['mode']??null)!=='delivery') {
                throw new Transaction_Failure('pc_guest_request_ineligible');
            }
            $lines=$tx->rows(
                'SELECT id FROM %i WHERE order_id=%s AND kind=%s '
                    . 'AND fulfillment_state IN (%s,%s,%s) LIMIT 1',
                [Orders_Domain::table('pc_order_lines'),$orderId,
                    'physical','PENDIENTE','PREPARANDO','LISTO']
            );
            if ([]===$lines) {
                throw new Transaction_Failure('pc_guest_request_ineligible');
            }
        }
        if ('request_refund'===$purpose) {
            $events=$tx->rows(
                'SELECT id FROM %i WHERE order_id=%s '
                    . 'AND kind=%s AND amount_cents>0 LIMIT 1',
                [Orders_Domain::table('pc_payment_events'),
                    $orderId,'initial_charge']
            );
            if ([]===$events) {
                throw new Transaction_Failure('pc_guest_request_ineligible');
            }
        }
        if ('reissue_download'===$purpose) {
            $rights=$tx->rows(
                'SELECT d.id FROM %i d INNER JOIN %i l '
                    . 'ON l.id=d.order_line_id '
                    . 'WHERE l.order_id=%s AND l.kind=%s '
                    . 'AND d.revoked_at IS NULL LIMIT 1',
                [Orders_Domain::table('pc_download_rights'),
                    Orders_Domain::table('pc_order_lines'),
                    $orderId,'digital']
            );
            if ([]===$rights) {
                throw new Transaction_Failure('pc_guest_request_ineligible');
            }
        }
        // Reemisión de seguimiento/notificación solo abre revisión;
        // OTP ya probó la casilla ORIGINAL en esta sesión.
        $caseType=self::caseType($purpose);
        $open=$tx->rows(
            'SELECT id FROM %i WHERE order_id=%s '
                . 'AND case_type=%s AND case_phase=%s LIMIT 2',
            [Orders_Domain::table('pc_cases'),
                $orderId,$caseType,self::OPEN]
        );
        if ([]!==$open) {
            throw new Transaction_Failure('pc_guest_request_conflict');
        }
    }

    private static function createWithin(
        Transaction_Context $tx,Event_Actor $actor,
        string $operationId,string $orderId,string $purpose,array $input
    ): string {
        $tx->lockedOrder($orderId);
        $caseType=self::caseType($purpose);
        $snapshot=\json_encode($input,
            \JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES|\JSON_THROW_ON_ERROR);
        if (!\is_string($snapshot) || \strlen($snapshot)>6500) {
            throw new Transaction_Failure('pc_guest_request_invalid');
        }
        if (1!==$tx->execute(
            'INSERT INTO %i (order_id,order_line_id,case_type,'
                . 'source_operation_id,case_phase,reason_code,created_by,'
                . 'assigned_to,evidence_snapshot,opened_at,resolved_at,'
                . 'row_version) VALUES (%s,NULL,%s,NULL,%s,%s,NULL,NULL,%s,'
                . 'UTC_TIMESTAMP(6),NULL,1)',
            [Orders_Domain::table('pc_cases'),$orderId,$caseType,
                self::OPEN,self::REASON,$snapshot]
        )) {
            throw new Transaction_Failure('pc_guest_request_insert_failed');
        }
        $last=$tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1!==\count($last)) {
            throw new Transaction_Failure('pc_guest_request_insert_failed');
        }
        $caseId=Domain_Validation::sqlId((string)$last[0]['id']);
        $locked=$tx->lockCaseForOrder($orderId,$caseId);
        if ($locked['case_type']!==$caseType
            || $locked['case_phase']!==self::OPEN) {
            throw new Transaction_Failure('pc_guest_request_insert_failed');
        }
        // Primer evento histórico del actor guest real. caseWithin
        // también audita en pc_audit_log en este MISMO COMMIT.
        Event_Journal::caseWithin(
            $tx,$actor,$operationId,$caseId,
            'guest_request_opened',null,self::REASON
        );
        return $caseId;
    }

    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $orderId,string $purpose
    ): array {
        if ('case'!==($claim['entity_type']??null)
            || 202!==($claim['response_code']??null)
            || !\is_string($claim['entity_id']??null)) {
            throw new Transaction_Failure('pc_guest_request_conflict');
        }
        $caseId=Domain_Validation::sqlId($claim['entity_id']);
        $case=$tx->rows(
            'SELECT c.id FROM %i c INNER JOIN %i e '
                . 'ON e.case_id=c.id '
                . 'WHERE c.id=%s AND c.order_id=%s '
                . 'AND c.case_type=%s AND c.source_operation_id IS NULL '
                . 'AND e.idempotency_id=%s AND e.event_code=%s '
                . 'AND e.actor_kind=%s LIMIT 2',
            [Orders_Domain::table('pc_cases'),
                Orders_Domain::table('pc_case_events'),
                $caseId,$orderId,self::caseType($purpose),
                $claim['operation_id'],'guest_request_opened','guest']
        );
        if (1!==\count($case)) {
            throw new Transaction_Failure('pc_guest_request_conflict');
        }
        return self::accepted($caseId);
    }

    private static function caseType(string $purpose): string
    {
        Guest_Challenges::purpose($purpose);
        return 'guest_'.$purpose;
    }

    private static function accepted(string $caseId): array
    {
        return [
            'case_id'=>Domain_Validation::sqlId($caseId),
            'accepted'=>true,
            'review_required'=>true,
        ];
    }
}
