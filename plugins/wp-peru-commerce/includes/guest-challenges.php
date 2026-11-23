<?php
/**
 * IMP-32 — OTP por correo asociado al pedido y grant con finalidad.
 *
 * 8 cifras decimales CSPRNG, 10 min, máximo cinco intentos por desafío.
 * Nunca confiar en correo recibido del cliente; se utiliza únicamente
 * pc_orders.buyer_email del pedido vinculado a la sesión invitada.
 * wp_mail se invoca FUERA del root SQL y no equivale a entrega real.
 * Sin correo válido, rate cap o correo caído: R8-10 responde igual,
 * sin crear identidad ficticia ni filtrar si existe el destinatario.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Guest_Challenges
{
    private const PURPOSES=[
        'reissue_tracking','delivery_change','request_refund',
        'reissue_download','resend_notification',
    ];
    private const MAX_ATTEMPTS=5;
    private const MAX_SESSION_HOUR=3;
    private const MAX_RECIPIENT_HOUR=5;
    private const MAX_RECIPIENT_DAY=12;
    private const MAX_ORDER_DAY=10;
    private const UNIFORM_HINT='Si tu pedido tiene correo registrado, recibirás un código allí.';

    /** Solo finalidades del contrato OpenAPI, nunca strings genéricos. */
    public static function purpose(mixed $purpose): string
    {
        if (!\is_string($purpose)
            || !\in_array($purpose,self::PURPOSES,true)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'purpose');
        }
        return $purpose;
    }

    /**
     * R8-10. Envío efímero solo tras un COMMIT inequívoco. R8-10 no
     * admite correo en body ni exige correo para comprar/ver pedido.
     * @return array{accepted:bool,delivery_hint:string}
     */
    public static function request(array $body): array
    {
        Guest_Sessions::sameOrigin('POST');
        $purpose=self::purpose($body['purpose']??null);
        $code=\str_pad((string)\random_int(0,99999999),8,'0',\STR_PAD_LEFT);
        $result=Transaction::run(
            static function(Transaction_Context $tx) use(
                $purpose,$code
            ): array {
                $session=Guest_Sessions::lockedForSensitiveWithin($tx);
                $orderId=$session['order_id'];
                $sessionId=$session['session_id'];
                $orders=$tx->rows(
                    'SELECT buyer_email FROM %i WHERE id=%s',
                    [Orders_Domain::table('pc_orders'),$orderId]
                );
                if (1!==\count($orders)) {
                    throw new Transaction_Failure('pc_guest_session_invalid');
                }
                $email=self::mailbox($orders[0]['buyer_email']);
                if (null===$email) {
                    // Pedido solo con teléfono: verificación administrativa
                    // posterior, sin OTP por SMS o correo inventado.
                    return [
                        'email'=>null,
                        'session'=>Guest_Sessions::renewSensitiveWithin($tx,$session),
                    ];
                }
                $recipient=self::recipientDigest($email);
                $table=self::table();
                $sessionRate=$tx->rows(
                    'SELECT COUNT(*) AS hour_count,'
                        . 'COALESCE(SUM(CASE WHEN issued_at > '
                        . 'DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 60 SECOND) '
                        . 'THEN 1 ELSE 0 END),0) AS minute_count '
                        . 'FROM %i WHERE guest_session_id=%s AND purpose=%s '
                        . 'AND issued_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 HOUR)',
                    [$table,$sessionId,$purpose]
                );
                $recipientHour=$tx->rows(
                    'SELECT COUNT(*) AS n FROM %i '
                        . 'WHERE recipient_hash=UNHEX(%s) '
                        . 'AND issued_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 HOUR)',
                    [$table,$recipient]
                );
                $recipientDay=$tx->rows(
                    'SELECT COUNT(*) AS n FROM %i '
                        . 'WHERE recipient_hash=UNHEX(%s) '
                        . 'AND issued_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 DAY)',
                    [$table,$recipient]
                );
                $orderDay=$tx->rows(
                    'SELECT COUNT(*) AS n FROM %i WHERE order_id=%s '
                        . 'AND issued_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 DAY)',
                    [$table,$orderId]
                );
                if (1!==\count($sessionRate)
                    || 1!==\count($recipientHour)
                    || 1!==\count($recipientDay)
                    || 1!==\count($orderDay)) {
                    throw new Transaction_Failure('pc_guest_challenge_unavailable');
                }
                if ((int)$sessionRate[0]['hour_count']>=self::MAX_SESSION_HOUR
                    || (int)$sessionRate[0]['minute_count']>0
                    || (int)$recipientHour[0]['n']>=self::MAX_RECIPIENT_HOUR
                    || (int)$recipientDay[0]['n']>=self::MAX_RECIPIENT_DAY
                    || (int)$orderDay[0]['n']>=self::MAX_ORDER_DAY) {
                    // Respuesta igual a una emisión o correo ausente:
                    // no revelar rate caps ni existencia de dirección.
                    return [
                        'email'=>null,
                        'session'=>Guest_Sessions::renewSensitiveWithin($tx,$session),
                    ];
                }

                // Un código viejo ya no sirve tras pedir otro nuevo; el
                // registro persiste para límites y trazabilidad técnica.
                $tx->execute(
                    'UPDATE %i SET superseded_at=UTC_TIMESTAMP(6) '
                        . 'WHERE order_id=%s AND guest_session_id=%s '
                        . 'AND purpose=%s AND consumed_at IS NULL '
                        . 'AND superseded_at IS NULL',
                    [$table,$orderId,$sessionId,$purpose]
                );
                $placeholder=\bin2hex(\random_bytes(32));
                if (1!==$tx->execute(
                    'INSERT INTO %i (order_id,guest_session_id,purpose,'
                        . 'recipient_hash,code_hash,issued_at,expires_at,'
                        . 'attempts,consumed_at,superseded_at) '
                        . 'VALUES (%s,%s,%s,UNHEX(%s),UNHEX(%s),'
                        . 'UTC_TIMESTAMP(6),'
                        . 'DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE),'
                        . '0,NULL,NULL)',
                    [$table,$orderId,$sessionId,$purpose,$recipient,$placeholder]
                )) {
                    throw new Transaction_Failure('pc_guest_challenge_unavailable');
                }
                $last=$tx->rows('SELECT LAST_INSERT_ID() AS id');
                if (1!==\count($last)) {
                    throw new Transaction_Failure('pc_guest_challenge_unavailable');
                }
                $id=Domain_Validation::sqlId((string)$last[0]['id']);
                if (1!==$tx->execute(
                    'UPDATE %i SET code_hash=UNHEX(%s) WHERE id=%s '
                        . 'AND order_id=%s AND guest_session_id=%s '
                        . 'AND code_hash=UNHEX(%s) AND attempts=0 '
                        . 'AND consumed_at IS NULL',
                    [$table,self::codeDigest(
                        $orderId,$sessionId,$purpose,$id,$code
                    ),$id,$orderId,$sessionId,$placeholder]
                )) {
                    throw new Transaction_Failure('pc_guest_challenge_unavailable');
                }
                $renewed=Guest_Sessions::renewSensitiveWithin($tx,$session);
                self::auditWithin($tx,$orderId,'otp_challenge_issued');
                return [
                    'email'=>$email,'code'=>$code,'session'=>$renewed,
                ];
            },true
        );

        // La cabecera Set-Cookie es uniforme incluso sin correo o
        // cuando no se envía por control de abuso: no filtrar identidad
        // mediante presencia/ausencia de renovación. Una petición POST
        // autenticada es actividad válida; un código incorrecto NO lo es.
        Guest_Sessions::refreshCookie($result['session']);
        // El código solo existe en memoria durante este POST. Nunca en
        // pc_outbox, wp_options, Event_Journal, respuesta JSON o URL.
        if (\is_string($result['email']??null)
            && \is_string($result['code']??null)) {
            try {
                \wp_mail(
                    $result['email'],
                    'Código para confirmar tu solicitud — PeruCommerce',
                    'Tu código de verificación es: ' . $result['code']
                        . "\nVálido durante 10 minutos y para una sola acción."
                        . "\nSi no solicitaste este código, ignora este correo."
                );
            } catch (\Throwable $ignored) {
                // wp_mail false/true no asegura recepción; mantener
                // respuesta no enumerable y permitir otro intento acotado.
            }
        }
        return ['accepted'=>true,'delivery_hint'=>self::UNIFORM_HINT];
    }

    /**
     * R8-11: un código usado, vencido o con 5 intentos jamás genera grant.
     * accepted=false se entrega igual para no-código o correo inexistente.
     * Nunca se devuelve el token de grant en el DTO ActionGrantResult.
     */
    public static function verify(array $body): array
    {
        Guest_Sessions::sameOrigin('POST');
        $purpose=self::purpose($body['purpose']??null);
        $code=$body['code']??null;
        if (!\is_string($code)
            || !\preg_match('/\A[0-9]{8}\z/D',$code)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'code');
        }
        $result=Transaction::run(
            static function(Transaction_Context $tx) use(
                $purpose,$code
            ): array {
                $session=Guest_Sessions::lockedForSensitiveWithin($tx);
                $orderId=$session['order_id'];
                $sessionId=$session['session_id'];
                $challenges=$tx->rows(
                    'SELECT id,attempts,LOWER(HEX(code_hash)) AS digest,'
                        . 'LOWER(HEX(recipient_hash)) AS recipient_digest '
                        . 'FROM %i WHERE order_id=%s AND guest_session_id=%s '
                        . 'AND purpose=%s AND consumed_at IS NULL '
                        . 'AND superseded_at IS NULL '
                        . 'AND expires_at>UTC_TIMESTAMP(6) '
                        . 'ORDER BY id DESC LIMIT 1 FOR UPDATE',
                    [self::table(),$orderId,$sessionId,$purpose]
                );
                if (1!==\count($challenges)
                    || (int)$challenges[0]['attempts']>=self::MAX_ATTEMPTS) {
                    return ['accepted'=>false];
                }
                $id=Domain_Validation::sqlId((string)$challenges[0]['id']);
                $attempt=(int)$challenges[0]['attempts'];
                // Un correo registrado modificado/sustituido no hereda el
                // derecho de un OTP enviado al destinatario antiguo.
                $order=$tx->rows(
                    'SELECT buyer_email FROM %i WHERE id=%s',
                    [Orders_Domain::table('pc_orders'),$orderId]
                );
                $email=1===\count($order)
                    ? self::mailbox($order[0]['buyer_email']) : null;
                $allowed=null!==$email
                    && \hash_equals(self::recipientDigest($email),
                        (string)$challenges[0]['recipient_digest']);
                $matching=$allowed
                    && \hash_equals(
                        self::codeDigest($orderId,$sessionId,$purpose,$id,$code),
                        (string)$challenges[0]['digest']
                    );
                if (!$matching) {
                    if (1!==$tx->execute(
                        'UPDATE %i SET attempts=attempts+1 '
                            . 'WHERE id=%s AND order_id=%s '
                            . 'AND guest_session_id=%s AND purpose=%s '
                            . 'AND attempts=%d AND attempts<5 '
                            . 'AND consumed_at IS NULL '
                            . 'AND superseded_at IS NULL '
                            . 'AND expires_at>UTC_TIMESTAMP(6)',
                        [self::table(),$id,$orderId,$sessionId,$purpose,$attempt]
                    )) {
                        throw new Transaction_Failure('pc_guest_challenge_unavailable');
                    }
                    return ['accepted'=>false];
                }
                if (1!==$tx->execute(
                    'UPDATE %i SET attempts=attempts+1,'
                        . 'consumed_at=UTC_TIMESTAMP(6) '
                        . 'WHERE id=%s AND order_id=%s '
                        . 'AND guest_session_id=%s AND purpose=%s '
                        . 'AND attempts=%d AND attempts<5 '
                        . 'AND consumed_at IS NULL '
                        . 'AND superseded_at IS NULL '
                        . 'AND expires_at>UTC_TIMESTAMP(6)',
                    [self::table(),$id,$orderId,$sessionId,$purpose,$attempt]
                )) {
                    throw new Transaction_Failure('pc_guest_challenge_unavailable');
                }
                $renewed=Guest_Sessions::renewSensitiveWithin($tx,$session);
                $grant=Guest_Action_Grants::issueWithin(
                    $tx,$renewed,$purpose,$id
                );
                self::auditWithin($tx,$orderId,'otp_grant_issued');
                return [
                    'accepted'=>true,'session'=>$renewed,'grant'=>$grant,
                ];
            },true
        );
        if (true!==($result['accepted']??false)) {
            return ['accepted'=>false];
        }
        // El grant y la renovación se entregan solamente después del
        // COMMIT; si falla un setcookie, jamás revelar raw tokens por JSON.
        Guest_Action_Grants::deliverCookie($result['grant']);
        Guest_Sessions::refreshCookie($result['session']);
        return [
            'accepted'=>true,
            'scope'=>$purpose,
            'grant_expires_at_utc'=>$result['grant']['expires_at_utc'],
        ];
    }

    /**
     * IMP-34: comprobar que un caso guest de reemisión procede del
     * correo ORIGINAL actual, sin exponer recipient_hash ni recalcular
     * OTP. Solo válido junto con grant/caso/idempotencia históricos.
     */
    public static function matchesOriginalRecipient(
        string $email,string $digestHex
    ): bool {
        if (!\preg_match('/\A[a-f0-9]{64}\z/D',$digestHex)) {
            return false;
        }
        $mail=self::mailbox($email);
        return null!==$mail
            && \hash_equals(self::recipientDigest($mail),$digestHex);
    }

    /** HMAC irreversible del correo ORIGINAL, jamás correo en SQL/audit. */
    private static function recipientDigest(string $email): string
    {
        return \hash_hmac('sha256',
            'pc-otp-recipient-v1:'.\get_current_blog_id().':'
                .\strtolower($email),
            self::secretKey()
        );
    }

    /** HMAC por desafío + finalidad/sesión/pedido/instalación y 8 cifras. */
    private static function codeDigest(
        string $orderId,string $sessionId,string $purpose,
        string $challengeId,string $code
    ): string {
        return \hash_hmac('sha256',
            'pc-otp-code-v1:'.\get_current_blog_id().':'
                .$orderId.':'.$sessionId.':'.$purpose.':'
                .$challengeId.':'.$code,
            self::secretKey()
        );
    }

    private static function secretKey(): string
    {
        $key=\wp_salt('auth');
        if (!\is_string($key) || \strlen($key)<32) {
            throw new Transaction_Failure('pc_guest_challenge_unavailable');
        }
        return $key;
    }

    /** No activar OTP ficticio para compradores que solo tienen teléfono. */
    private static function mailbox(mixed $value): ?string
    {
        if (!\is_string($value) || ''===$value
            || \strlen($value)>254 || \preg_match('/[\x00-\x20\x7f]/',$value)
            || false===\filter_var($value,\FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return $value;
    }

    /** Auditoría mínima, sin OTP, correo, grant, token ni contenido libre. */
    private static function auditWithin(
        Transaction_Context $tx,string $orderId,string $event
    ): void {
        if (!\in_array($event,['otp_challenge_issued','otp_grant_issued'],true)
            || 1!==$tx->execute(
                'INSERT INTO %i (entity_type,entity_id,operation_code,'
                    . 'actor_kind,request_id,occurred_at) '
                    . 'VALUES (%s,%s,%s,%s,UNHEX(%s),UTC_TIMESTAMP(6))',
                [Orders_Domain::table('pc_audit_log'),'order',$orderId,
                    $event,'guest',\bin2hex(\random_bytes(16))]
            )) {
            throw new Transaction_Failure('pc_guest_challenge_unavailable');
        }
    }

    private static function table(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_guest_challenge_unavailable');
        }
        return Schema::tableNames($wpdb)['pc_otp_challenges'];
    }
}
