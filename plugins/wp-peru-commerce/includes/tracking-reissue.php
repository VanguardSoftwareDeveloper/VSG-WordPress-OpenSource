<?php
/**
 * IMP-34 — R8-58, reemisión administrativa de seguimiento.
 *
 * No recupera el antiguo secreto; revoca todas las fuentes tracking y
 * sesiones guest derivadas antes de generar un token nuevo de 256 bits.
 * El primer resultado se entrega SOLO tras COMMIT: un replay devuelve
 * operation_id sin URL. Sin verificador real de identidad -> fail-closed.
 *
 * Requiere un verificador confiable previamente instalado por la fuente
 * propietaria. Su callback (Transaction_Context|null, order, body, userId)
 * debe comprobar control REAL del canal original y un segundo hecho
 * proporcional no público. Con $tx=null solo consulta (sin efectos);
 * con $tx consume ATOMICAMENTE evidencia de identidad de un uso en SQL.
 * No basta con comparar una cadena ref o con declarar "verificado".
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Tracking_Reissue
{
    private const OPERATION='tracking_reissue';
    private const MAX_OLD_LINKS=100;
    private static $identityVerifier=null;

    /** Verificador de evidencias de fuente propia; instalar una sola vez. */
    public static function registerIdentityVerifier(callable $verifier): void
    {
        if (null!==self::$identityVerifier) {
            throw new \LogicException('pc_tracking_identity_verifier_duplicate');
        }
        self::$identityVerifier=$verifier;
    }

    /**
     * No equivale a identidad verificada: solo permite al router evaluar
     * credenciales de personal, scope y replay con los mismos datos.
     */
    public static function guard(array $context,?string $role): array
    {
        if ('A'!==$role
            || !\current_user_can('perucommerce_privacy_manage')
            || !\current_user_can('perucommerce_orders_read')
            || (int)\get_current_blog_id()<1
            || (\function_exists('is_network_admin')&&\is_network_admin())) {
            return [];
        }
        try {
            $orderId=self::orderId($context['path']['id']??null);
            $body=self::validated($context['body']??null);
            $scope=self::scope($orderId);
            // Claim existente: el verificador de un solo uso ya quedó
            // consumido; solo un replay legítimo o 409 puede salir.
            if (Idempotency::keyExistsForScope(
                $scope,self::OPERATION,$body['idempotency_key'],$body
            )) {
                return self::proof(false,true);
            }
            // Primera emisión: comprobar que hay página HTTPS válida y
            // evidencia real del canal ORIGINAL, sin consumirla en guard.
            self::pageUrl();
            $data=self::currentOrder($orderId);
            if ((string)$data['row_version']!==$body['expected_version']
                || !self::identity(null,$data,$body)) {
                return [];
            }
            return self::proof(true,false);
        } catch (\Throwable $ignored) {
            return [];
        }
    }

    /**
     * El procesamiento de un intento nuevo exige actor A, prueba real
     * de identidad y CAS; cualquier fallo hace ROLLBACK de las fuentes,
     * las sesiones derivadas, la versión del pedido y el resultado.
     *
     * @return array{operation_id:string,url_once?:string,expires_at_utc?:string,already_issued?:bool}
     */
    public static function reissue(string $orderId,array $body): array
    {
        self::admin();
        $orderId=self::orderId($orderId);
        $body=self::validated($body);
        $actor=Event_Actor::staff([
            'perucommerce_privacy_manage','perucommerce_orders_read',
        ]);
        $scope=self::scope($orderId);
        // Para replay no hace falta la página; para una nueva emisión
        // sí. Calcular fuera de SQL para no usar WP options dentro.
        $prior=Idempotency::keyExistsForScope(
            $scope,self::OPERATION,$body['idempotency_key'],$body
        );
        $url=$prior?null:self::pageUrl();
        $key=\wp_salt('auth');
        if (!\is_string($key) || \strlen($key)<32) {
            throw new Transaction_Failure('pc_tracking_reissue_unavailable');
        }
        $result=Transaction::run(
            static function(Transaction_Context $tx) use(
                $actor,$scope,$orderId,$body,$url,$key
            ): array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OPERATION,$body['idempotency_key'],$body
                );
                if ('replay'===$claim['status']) {
                    $order=$tx->lockOrder($orderId);
                    if ('order'!==($claim['entity_type']??null)
                        || ($claim['entity_id']??null)!==$orderId
                        || 200!==($claim['response_code']??null)) {
                        throw new Transaction_Failure('pc_tracking_reissue_conflict');
                    }
                    // NO leer/verificar otra vez evidencia consumida; NO
                    // reconstructar URL, hash ni secreto de la BD.
                    return [
                        'operation_id'=>$claim['operation_id'],
                        'already_issued'=>true,
                    ];
                }
                if ('new'!==$claim['status']) {
                    throw new Transaction_Failure('pc_tracking_reissue_pending');
                }
                if (null===$url) {
                    // Otro request pudo reclamar el root entre preflight
                    // y COMMIT; jamás generar enlace con URL sin validar.
                    throw new Transaction_Failure('pc_tracking_reissue_pending');
                }
                $locked=$tx->lockOrder($orderId);
                if ((string)$locked['row_version']!==$body['expected_version']) {
                    throw new Transaction_Failure('pc_version_conflict');
                }
                $order=self::orderWithin($tx,$orderId);
                if (!self::identity(
                    $tx,$order,$body,$actor,$claim['operation_id']
                )) {
                    throw new Transaction_Failure('pc_tracking_identity_unverified');
                }

                // Después de verificar evidencia REAL y bajo pedido
                // bloqueado, serializar todas las fuentes aún activas.
                // Revocar también sus sesiones para eliminar accesos
                // aunque una cookie posterior siga sin caducar.
                $guest=self::table();
                $sources=$tx->rows(
                    'SELECT id FROM %i WHERE order_id=%s AND '
                        . 'credential_kind=%s AND revoked_at IS NULL '
                        . 'ORDER BY id ASC LIMIT 101 FOR UPDATE',
                    [$guest,$orderId,'tracking']
                );
                if ([]===$sources || \count($sources)>self::MAX_OLD_LINKS) {
                    throw new Transaction_Failure('pc_tracking_reissue_conflict');
                }
                foreach ($sources as $source) {
                    Guest_Sessions::revokeTrackingWithin(
                        $tx,$actor,$claim['operation_id'],$orderId,
                        Domain_Validation::sqlId((string)$source['id'])
                    );
                }

                // Se conserva el formato IMP-31: prefijo de pedido NO
                // autenticador + secreto base64url de 32 bytes CSPRNG.
                $secret='pc1.'.$orderId.'.'
                    .\rtrim(\strtr(\base64_encode(\random_bytes(32)),
                        '+/','-_'),'=');
                $digest=\hash_hmac('sha256',
                    'pc-tracking-v1:'.\get_current_blog_id()
                        .':'.$orderId.':'.$secret,$key
                );
                if (1!==$tx->execute(
                    'INSERT INTO %i (order_id,credential_kind,token_hash,'
                        . 'source_credential_id,issued_at,expires_at,'
                        . 'revoked_at,last_used_at) '
                        . 'VALUES (%s,%s,UNHEX(%s),NULL,UTC_TIMESTAMP(6),'
                        . 'DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 90 DAY),NULL,NULL)',
                    [$guest,$orderId,'tracking',$digest]
                )) {
                    throw new Transaction_Failure('pc_tracking_reissue_insert_failed');
                }
                $inserted=$tx->rows(
                    'SELECT id,expires_at FROM %i WHERE '
                        . 'id=LAST_INSERT_ID() AND order_id=%s '
                        . 'AND credential_kind=%s',
                    [$guest,$orderId,'tracking']
                );
                if (1!==\count($inserted)) {
                    throw new Transaction_Failure('pc_tracking_reissue_insert_failed');
                }

                // Reissue cambia credencial vinculada al pedido, por
                // eso hace CAS de la versión del pedido sin variar estado,
                // importe, cumplimiento, cliente ni obligación financiera.
                $tx->advanceVersion(
                    'pc_orders',$orderId,$body['expected_version']
                );
                Event_Journal::auditWithin(
                    $tx,$actor,$claim['operation_id'],
                    'order',$orderId,'tracking_link_reissued',
                    $body['original_verified_channel']
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'order',$orderId,200
                );
                return [
                    'operation_id'=>$claim['operation_id'],
                    'url_once'=>$url.'#tracking='.$secret,
                    'expires_at_utc'=>Business_Time::toApi(
                        Business_Time::fromDatabase(
                            (string)$inserted[0]['expires_at']
                        )
                    ),
                ];
            },true
        );
        // Solo la primera respuesta TLS no-store transporta el secreto.
        // No enviar por mail/outbox, no guardarlo en opciones ni eventos.
        return $result;
    }

    /**
     * Una prueba auténtica puede proceder del caso guest de IMP-33:
     * R8-12 solo se creó después de OTP al correo original y consumo
     * de un grant; el scope_hash vincula la raíz a ESA sesión real.
     *
     * Recuperación cuando el comprador YA perdió todo acceso: se
     * requiere otro verificador confiable (teléfono + dato privado,
     * email verificado por canal externo propio o cotejo presencial).
     * El campo identity_verification_ref no es prueba por sí solo.
     */
    private static function identity(
        ?Transaction_Context $tx,array $order,array $body,
        ?Event_Actor $actor=null,?string $operationId=null
    ): bool {
        $channel=$body['original_verified_channel'];
        if ('registered_email'===$channel
            && (!\is_string($order['buyer_email']??null)
                || ''===$order['buyer_email'])) {
            return false;
        }
        if ('registered_phone'===$channel
            && (!\is_string($order['buyer_phone']??null)
                || ''===$order['buyer_phone'])) {
            return false;
        }
        $ref=$body['identity_verification_ref'];
        if ('registered_email'===$channel
            && \is_string($ref)
            && \preg_match('/\\A[1-9][0-9]{0,19}\\z/D',$ref)) {
            try {
                if (null===$tx) {
                    return Transaction::run(
                        static fn(Transaction_Context $readTx):bool =>
                            self::verifiedGuestCaseWithin(
                                $readTx,$order,$ref,null,null
                            )
                    );
                }
                return self::verifiedGuestCaseWithin(
                    $tx,$order,$ref,$actor,$operationId
                );
            } catch (\Throwable $ignored) {
                // Un case_id ilegítimo nunca reemplaza la verificación
                // telefónica ni concede permiso por sí solo.
                return false;
            }
        }
        if (null===self::$identityVerifier) { return false; }
        try {
            // El callback de fuente propia no debe invocar HTTP/wp_mail
            // ni get_option() cuando $tx no es NULL.
            return true===(self::$identityVerifier)(
                $tx,$order,$body,(string)\get_current_user_id()
            );
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * Prueba interna D-138: case guest legítimo, operación completa,
     * mismo scope de la sesión que consumió grant con OTP al correo
     * ORIGINAL. No basta con conocer el case ID. Se permite verificar
     * como máximo hasta 24 h tras su creación; nada se deduce del canal
     * o de un número de pedido por sí solos.
     *
     * Con $actor/op se consume el caso en este MISMO root SQL mediante
     * CAS + primer evento administrativo de uso; un segundo intento
     * distinto jamás puede usar el mismo expediente.
     */
    private static function verifiedGuestCaseWithin(
        Transaction_Context $tx,array $order,string $caseId,
        ?Event_Actor $actor,?string $operationId
    ): bool {
        $orderId=Domain_Validation::sqlId((string)$order['id']);
        $caseId=Domain_Validation::sqlId($caseId);
        $rows=$tx->rows(
            'SELECT c.id,c.evidence_snapshot,c.opened_at,c.row_version,'
                . 'LOWER(HEX(i.scope_hash)) AS scope_digest '
                . 'FROM %i c INNER JOIN %i e '
                . 'ON e.case_id=c.id AND e.event_code=%s AND e.actor_kind=%s '
                . 'INNER JOIN %i i ON i.id=e.idempotency_id '
                . 'AND i.operation_type=%s AND i.technical_phase=%s '
                . 'AND i.entity_type=%s AND i.entity_id=c.id '
                . 'AND i.response_code=%d '
                . 'WHERE c.id=%s AND c.order_id=%s '
                . 'AND c.case_type=%s AND c.case_phase=%s '
                . 'AND c.order_line_id IS NULL '
                . 'AND c.source_operation_id IS NULL AND c.created_by IS NULL '
                . 'AND c.opened_at>=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 24 HOUR) '
                . 'AND NOT EXISTS (SELECT 1 FROM %i earlier '
                . 'WHERE earlier.case_id=c.id AND earlier.id<e.id) LIMIT 2',
            [Orders_Domain::table('pc_cases'),
                Orders_Domain::table('pc_case_events'),
                'guest_request_opened','guest',
                Orders_Domain::table('pc_idempotency'),
                'guest_case_request','complete','case',202,
                $caseId,$orderId,'guest_reissue_tracking','open_review',
                Orders_Domain::table('pc_case_events')]
        );
        if (1!==\count($rows)
            || !\is_string($rows[0]['evidence_snapshot']??null)
            || !\is_string($order['buyer_email']??null)) {
            return false;
        }
        $original=Orders_Domain::decode(
            $rows[0]['evidence_snapshot'],10000
        );
        if (($original['purpose']??null)!=='reissue_tracking') {
            return false;
        }
        // Buscar únicamente grants OTP realmente consumidos cerca de
        // la apertura original; la firma del scope asegura que fue ESA
        // sesión la que autorizó la operación guest_case_request.
        $grants=$tx->rows(
            'SELECT g.id,g.guest_session_id,'
                . 'LOWER(HEX(ch.recipient_hash)) AS recipient_digest '
                . 'FROM %i g INNER JOIN %i ch '
                . 'ON ch.id=g.verified_challenge_id '
                . 'AND ch.order_id=g.order_id '
                . 'AND ch.guest_session_id=g.guest_session_id '
                . 'AND ch.purpose=g.purpose '
                . 'WHERE g.order_id=%s AND g.purpose=%s '
                . 'AND g.consumed_at IS NOT NULL '
                . 'AND ch.consumed_at IS NOT NULL '
                . 'AND ch.superseded_at IS NULL '
                . 'AND g.created_at>=DATE_SUB(%s,INTERVAL 6 MINUTE) '
                . 'AND g.created_at<=%s '
                . 'AND g.consumed_at>=DATE_SUB(%s,INTERVAL 1 MINUTE) '
                . 'AND g.consumed_at<=%s '
                . 'ORDER BY g.id DESC LIMIT 32',
            [Orders_Domain::table('pc_action_grants'),
                Orders_Domain::table('pc_otp_challenges'),
                $orderId,'reissue_tracking',
                $rows[0]['opened_at'],$rows[0]['opened_at'],
                $rows[0]['opened_at'],$rows[0]['opened_at']]
        );
        $matched=0;
        foreach ($grants as $grant) {
            $sessionId=Domain_Validation::sqlId(
                (string)$grant['guest_session_id']
            );
            if (\hash_equals(
                    Idempotency::guestCaseScopeDigest(
                        $orderId,$sessionId,'reissue_tracking'
                    ),
                    (string)$rows[0]['scope_digest']
                )
                && Guest_Challenges::matchesOriginalRecipient(
                    $order['buyer_email'],(string)$grant['recipient_digest']
                )) {
                ++$matched;
            }
        }
        if (1!==$matched) { return false; }

        if (null!==$tx && null!==$actor && null!==$operationId) {
            $locked=$tx->lockCaseForOrder($orderId,$caseId);
            if (($locked['case_type']??null)!=='guest_reissue_tracking'
                || ($locked['case_phase']??null)!=='open_review') {
                return false;
            }
            $version=$tx->advanceVersion(
                'pc_cases',$caseId,(string)$rows[0]['row_version']
            );
            if (1!==$tx->execute(
                'UPDATE %i SET case_phase=%s,resolved_at=UTC_TIMESTAMP(6) '
                    . 'WHERE id=%s AND order_id=%s AND case_type=%s '
                    . 'AND case_phase=%s AND row_version=%s',
                [Orders_Domain::table('pc_cases'),
                    'closed_reissued',$caseId,$orderId,
                    'guest_reissue_tracking','open_review',$version]
            )) {
                throw new Transaction_Failure('pc_tracking_identity_unverified');
            }
            Event_Journal::caseWithin(
                $tx,$actor,$operationId,$caseId,
                'tracking_reissue_identity_used','reissue_tracking'
            );
        }
        return true;
    }

    private static function currentOrder(string $orderId): array
    {
        return Transaction::run(static function(Transaction_Context $tx)
            use($orderId): array {
            return self::orderWithin($tx,$orderId);
        });
    }

    private static function orderWithin(
        Transaction_Context $tx,string $orderId
    ): array {
        $rows=$tx->rows(
            'SELECT id,row_version,status,buyer_email,buyer_phone,'
                . 'customer_snapshot FROM %i WHERE id=%s',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_tracking_reissue_conflict');
        }
        return $rows[0];
    }

    private static function proof(bool $fresh,bool $existing): array
    {
        return [
            'authorized'=>true,'same_site'=>true,
            'resource_checked'=>true,'state_checked'=>true,
            'field_checked'=>true,'purpose_checked'=>true,
            'buyer_reverified'=>$fresh,
            'tracking_reissue_existing_key_checked'=>$existing,
        ];
    }

    private static function validated(mixed $body): array
    {
        if (!\is_array($body)
            || \array_is_list($body)
            || \count($body)!==5) {
            throw new Transaction_Failure('pc_tracking_reissue_input_invalid');
        }
        $expected=['identity_verification_ref','original_verified_channel',
            'reason','expected_version','idempotency_key'];
        $keys=\array_keys($body);
        \sort($keys,\SORT_STRING);
        \sort($expected,\SORT_STRING);
        if ($keys!==$expected) {
            throw new Transaction_Failure('pc_tracking_reissue_input_invalid');
        }
        $ref=$body['identity_verification_ref'];
        $reason=$body['reason'];
        $channel=$body['original_verified_channel'];
        $key=$body['idempotency_key'];
        $version=$body['expected_version'];
        if (!\is_string($ref) || ''===\trim($ref)
            || !\mb_check_encoding($ref,'UTF-8')
            || \mb_strlen($ref,'UTF-8')>500
            || \preg_match('/[\x00-\x1F\x7F]/',$ref)
            || !\in_array($channel,[
                'registered_phone','registered_email','verified_in_person',
            ],true)
            || !\is_string($reason) || ''===\trim($reason)
            || !\mb_check_encoding($reason,'UTF-8')
            || \mb_strlen($reason,'UTF-8')>1000
            || \preg_match('/[\x00-\x1F\x7F]/',$reason)
            || !\is_string($key)) {
            throw new Transaction_Failure('pc_tracking_reissue_input_invalid');
        }
        Domain_Validation::sqlId((string)$version);
        return $body;
    }

    private static function scope(string $orderId): array
    {
        return [
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'order',
            'resource_id'=>$orderId,
            'purpose'=>self::OPERATION,
        ];
    }

    private static function admin(): void
    {
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/access-roles.php';
        if ('A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_privacy_manage')
            || !\current_user_can('perucommerce_orders_read')
            || (\function_exists('is_network_admin')
                && \is_network_admin())) {
            throw new Transaction_Failure('pc_tracking_reissue_denied');
        }
    }

    private static function orderId(mixed $raw): string
    {
        if (!\is_string($raw)) {
            throw new Transaction_Failure('pc_tracking_reissue_input_invalid');
        }
        return Domain_Validation::sqlId($raw);
    }

    /** Cargar solo la página publicada real fuera del root SQL. */
    private static function pageUrl(): string
    {
        $page=Checkout_COD_Controller::trackingPage();
        Checkout_Tracking::configurePageUrl($page);
        return $page;
    }

    private static function table(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_tracking_reissue_unavailable');
        }
        return Schema::tableNames($wpdb)['pc_guest_sessions'];
    }
}
