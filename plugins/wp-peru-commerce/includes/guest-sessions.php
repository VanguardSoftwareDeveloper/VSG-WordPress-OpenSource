<?php
/**
 * IMP-31 — canje del enlace de seguimiento por cookie de UN pedido.
 *
 * No crea cuentas WP, no admite order_id como identidad, no devuelve
 * secretos de tracking ni permite acciones sensibles. Caducidad efectiva
 * por inactividad de 30 minutos, acotada por enlace fuente de 90 días.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Guest_Sessions
{
    private const COOKIE = '__Host-pc_guest_session';
    private const IDLE_SECONDS = 1800;
    private const KIND_LINK = 'tracking';
    private const KIND_SESSION = 'session';

    /**
     * POST intercambia una sola referencia secreta por sesión aleatoria.
     * La referencia pc1.<order_id>.<secret> permite indexar sin escanear
     * todos los clientes, pero NUNCA autoriza por el order_id del prefijo.
     * El HMAC existente de Checkout_Tracking es la autoridad.
     */
    public static function exchange(array $input): array
    {
        self::sameOrigin('POST');
        [$orderId,$token] = self::parseTracking($input['tracking_secret'] ?? null);
        $sessionSecret = self::randomToken();
        $created = Transaction::run(static function(Transaction_Context $tx) use (
            $orderId,$token,$sessionSecret
        ): array {
            $tx->lockOrder($orderId);
            $source=self::sourceWithin($tx,$orderId,$token,true);
            $hmac=self::sessionHash(
                $orderId,$source['id'],$sessionSecret
            );
            $table=self::table();
            if (1!==$tx->execute(
                'INSERT INTO %i (order_id,credential_kind,token_hash,'
                    . 'source_credential_id,issued_at,expires_at,revoked_at,last_used_at) '
                    . 'SELECT %s,%s,UNHEX(%s),id,UTC_TIMESTAMP(6),'
                    . 'LEAST(expires_at,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)),'
                    . 'NULL,UTC_TIMESTAMP(6) FROM %i WHERE id=%s '
                    . 'AND order_id=%s AND credential_kind=%s '
                    . 'AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP(6)',
                [$table,$orderId,self::KIND_SESSION,$hmac,$table,
                    $source['id'],$orderId,self::KIND_LINK]
            )) {
                throw new Transaction_Failure('pc_guest_exchange_invalid');
            }
            $row=$tx->rows('SELECT LAST_INSERT_ID() AS id');
            if (1!==\count($row)) {
                throw new Transaction_Failure('pc_guest_exchange_invalid');
            }
            $sessionId=Domain_Validation::sqlId((string)$row[0]['id']);
            $expires=$tx->rows(
                'SELECT expires_at FROM %i WHERE id=%s AND order_id=%s '
                    . 'AND credential_kind=%s',
                [$table,$sessionId,$orderId,self::KIND_SESSION]
            );
            if (1!==\count($expires)) {
                throw new Transaction_Failure('pc_guest_exchange_invalid');
            }
            return [
                'id'=>$sessionId,
                'expires_at_utc'=>Business_Time::toApi(
                    Business_Time::fromDatabase((string)$expires[0]['expires_at'])
                ),
            ];
        });
        $cookie='pc1.'.$created['id'].'.'.$sessionSecret;
        self::setCookie($cookie,$created['expires_at_utc']);
        return [
            'scope'=>'order_read_only',
            'expires_at_utc'=>$created['expires_at_utc'],
        ];
    }

    /** Solo credencial válida, origen apropiado y fila fuente no revocada. */
    public static function authorize(string $operationId,array $context): bool
    {
        try {
            if ('R8-08'===$operationId) {
                self::sameOrigin('POST');
                [$orderId,$token]=self::parseTracking(
                    $context['body']['tracking_secret']??null
                );
                return Transaction::run(
                    static function(Transaction_Context $tx) use(
                        $orderId,$token
                    ): bool {
                        self::sourceWithin($tx,$orderId,$token,false);
                        return true;
                    }
                );
            }
            if ('R8-09'===$operationId) {
                self::sameOrigin('GET');
                self::current();
                return true;
            }
            if (\in_array($operationId,['R8-10','R8-11','R8-12'],true)) {
                self::currentForPost();
                return true;
            }
            // Otros endpoints guest requieren autoridad específica y
            // no heredan facultades por poseer la cookie de seguimiento.
            return false;
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * Comprobación de pertenencia por cookie para permission_callback.
     * No renueva plazo ni inserta nada en una lectura de permiso.
     * @return array{order_id:string,session_id:string}
     */
    public static function current(): array
    {
        self::sameOrigin('GET');
        [$id,$token]=self::parseCookie();
        return Transaction::run(
            static function(Transaction_Context $tx) use ($id,$token): array {
                return self::sessionWithin($tx,$id,$token,false);
            }
        );
    }

    /**
     * R8-10/R8-11: probar sesión HttpOnly propia para POST sensitive.
     * Solo prueba pertenencia, no prorroga sesión ni valida OTP/grant.
     */
    public static function currentForPost(): array
    {
        self::sameOrigin('POST');
        [$id,$token]=self::parseCookie();
        return Transaction::run(
            static fn(Transaction_Context $tx): array =>
                self::sessionWithin($tx,$id,$token,false)
        );
    }

    /**
     * Prueba fuerte en el MISMO root de OTP/grant: lectura inicial sin
     * lock, luego bloqueo de pedido (stage20) y recién entonces
     * FOR UPDATE de sesión y enlace fuente. No ampliar la sesión aquí.
     * El caller ya comprobó el Origin ANTES de iniciar SQL.
     *
     * @return array{order_id:string,session_id:string,cookie:string}
     */
    public static function lockedForSensitiveWithin(Transaction_Context $tx): array
    {
        [$id,$token]=self::parseCookie();
        $candidate=self::sessionWithin($tx,$id,$token,false);
        $orderId=$candidate['order_id'];
        $tx->lockOrder($orderId);
        $verified=self::sessionWithin($tx,$id,$token,true);
        if ($verified['order_id']!==$orderId
            || $verified['session_id']!==$id) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        return [
            'order_id'=>$orderId,'session_id'=>$id,
            'cookie'=>'pc1.'.$id.'.'.$token,
        ];
    }

    /**
     * Sólo una actividad legítima que acabó con resultado durable.
     * No renovar sesión por intentos OTP incorrectos o desafíos bloqueados.
     * Ya están bloqueados pedido + sesión + enlace fuente.
     */
    public static function renewSensitiveWithin(
        Transaction_Context $tx,array $session
    ): array {
        $id=Domain_Validation::sqlId((string)($session['session_id']??''));
        $orderId=Domain_Validation::sqlId((string)($session['order_id']??''));
        $tx->lockedOrder($orderId);
        if (!\is_string($session['cookie']??null)) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        $table=self::table();
        if (1!==$tx->execute(
            'UPDATE %i s INNER JOIN %i t '
                . 'ON t.id=s.source_credential_id AND t.order_id=s.order_id '
                . 'SET s.last_used_at=UTC_TIMESTAMP(6), '
                . 's.expires_at=LEAST(t.expires_at,'
                . 'DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)) '
                . 'WHERE s.id=%s AND s.order_id=%s '
                . 'AND s.credential_kind=%s AND t.credential_kind=%s '
                . 'AND s.revoked_at IS NULL AND t.revoked_at IS NULL '
                . 'AND s.expires_at>UTC_TIMESTAMP(6) '
                . 'AND t.expires_at>UTC_TIMESTAMP(6) '
                . 'AND s.last_used_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)',
            [$table,$table,$id,$orderId,self::KIND_SESSION,self::KIND_LINK]
        )) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        $fresh=$tx->rows(
            'SELECT s.expires_at FROM %i s INNER JOIN %i t '
                . 'ON t.id=s.source_credential_id AND t.order_id=s.order_id '
                . 'WHERE s.id=%s AND s.order_id=%s '
                . 'AND s.credential_kind=%s AND t.credential_kind=%s '
                . 'AND s.revoked_at IS NULL AND t.revoked_at IS NULL '
                . 'AND s.expires_at>UTC_TIMESTAMP(6) '
                . 'AND t.expires_at>UTC_TIMESTAMP(6)',
            [$table,$table,$id,$orderId,self::KIND_SESSION,self::KIND_LINK]
        );
        if (1!==\count($fresh)) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        return [
            'order_id'=>$orderId,'session_id'=>$id,
            'cookie'=>$session['cookie'],
            'expires_at_utc'=>Business_Time::toApi(
                Business_Time::fromDatabase((string)$fresh[0]['expires_at'])
            ),
        ];
    }

    /**
     * GET R8-09: revalidación bajo lock de pedido, fila de sesión y
     * enlace fuente en el MISMO TX de lectura. Solo si éxito renovar
     * inactividad; expiración nunca supera la fuente.
     *
     * @return array{order_id:string,session_id:string,expires_at_utc:string,cookie:string}
     */
    public static function authorizeReadWithin(Transaction_Context $tx): array
    {
        // El caller validó Origin/Fetch-Site ANTES del callback SQL;
        // no invocar home_url()/get_option() dentro de la transacción.
        [$id,$token]=self::parseCookie();
        $candidate=self::sessionWithin($tx,$id,$token,false);
        $orderId=$candidate['order_id'];
        $tx->lockOrder($orderId);
        $verified=self::sessionWithin($tx,$id,$token,true);
        if ($verified['order_id']!==$orderId) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        $table=self::table();
        $affected=$tx->execute(
            'UPDATE %i s INNER JOIN %i t '
                . 'ON t.id=s.source_credential_id AND t.order_id=s.order_id '
                . 'SET s.last_used_at=UTC_TIMESTAMP(6),'
                . 's.expires_at=LEAST(t.expires_at,'
                . 'DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)) '
                . 'WHERE s.id=%s AND s.order_id=%s '
                . 'AND s.credential_kind=%s AND t.credential_kind=%s '
                . 'AND s.revoked_at IS NULL AND t.revoked_at IS NULL '
                . 'AND s.expires_at>UTC_TIMESTAMP(6) '
                . 'AND t.expires_at>UTC_TIMESTAMP(6) '
                . 'AND s.last_used_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)',
            [$table,$table,$id,$orderId,self::KIND_SESSION,self::KIND_LINK]
        );
        if (1!==$affected) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        $fresh=$tx->rows(
            'SELECT expires_at FROM %i WHERE id=%s AND order_id=%s',
            [$table,$id,$orderId]
        );
        if (1!==\count($fresh)) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        return [
            'order_id'=>$orderId,
            'session_id'=>$id,
            'expires_at_utc'=>Business_Time::toApi(
                Business_Time::fromDatabase((string)$fresh[0]['expires_at'])
            ),
            'cookie'=>'pc1.'.$id.'.'.$token,
        ];
    }

    /** Solo después del COMMIT de una lectura legítima, no desde guard. */
    public static function refreshCookie(array $session): void
    {
        Transaction::assertOutside();
        self::setCookie($session['cookie'],$session['expires_at_utc']);
    }

    /**
     * Primitiva IMP-34: A con autorización/operación preparada y lock
     * de pedido revoca enlace y TODAS sus sesiones descendientes.
     * Un token revocado no puede reactivarse por URL o replay.
     */
    public static function revokeTrackingWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $orderId,string $sourceId
    ): void {
        Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        if ('staff'!==$actor->kind()
            || !\current_user_can('perucommerce_privacy_manage')
            || !\current_user_can('perucommerce_orders_read')) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        $orderId=Domain_Validation::sqlId($orderId);
        $sourceId=Domain_Validation::sqlId($sourceId);
        $tx->lockedOrder($orderId);
        $table=self::table();
        $rows=$tx->rows(
            'SELECT id,revoked_at FROM %i WHERE id=%s AND order_id=%s '
                . 'AND credential_kind=%s FOR UPDATE',
            [$table,$sourceId,$orderId,self::KIND_LINK]
        );
        if (1!==\count($rows) || null!==$rows[0]['revoked_at']) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        if (1!==$tx->execute(
            'UPDATE %i SET revoked_at=UTC_TIMESTAMP(6) '
                . 'WHERE id=%s AND order_id=%s AND credential_kind=%s '
                . 'AND revoked_at IS NULL',
            [$table,$sourceId,$orderId,self::KIND_LINK]
        )) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        $tx->execute(
            'UPDATE %i SET revoked_at=UTC_TIMESTAMP(6) '
                . 'WHERE source_credential_id=%s AND order_id=%s '
                . 'AND credential_kind=%s AND revoked_at IS NULL',
            [$table,$sourceId,$orderId,self::KIND_SESSION]
        );
        Event_Journal::auditWithin(
            $tx,$actor,$operationId,'order',$orderId,'tracking_link_revoked'
        );
    }

    /** Validación anónima de índice más HMAC ligado a sitio/pedido. */
    private static function sourceWithin(
        Transaction_Context $tx,string $orderId,string $secret,bool $lock
    ): array {
        $rows=$tx->rows(
            'SELECT id,LOWER(HEX(token_hash)) AS digest '
                . 'FROM %i WHERE order_id=%s AND credential_kind=%s '
                . 'AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP(6) '
                . 'ORDER BY id DESC LIMIT 101 '
                . ($lock?'FOR UPDATE':'LOCK IN SHARE MODE'),
            [self::table(),$orderId,self::KIND_LINK]
        );
        if ([]===$rows || \count($rows)>100) {
            throw new Transaction_Failure('pc_guest_tracking_invalid');
        }
        $expected=self::trackingHash($orderId,$secret);
        $matched=null;
        foreach ($rows as $row) {
            if (\hash_equals($expected,(string)$row['digest'])) {
                if (null!==$matched) {
                    throw new Transaction_Failure('pc_guest_tracking_invalid');
                }
                $matched=Domain_Validation::sqlId((string)$row['id']);
            }
        }
        if (null===$matched) {
            throw new Transaction_Failure('pc_guest_tracking_invalid');
        }
        return ['id'=>$matched,'order_id'=>$orderId];
    }

    /** Lee sesión y fuente, revalidando ambas revocaciones cada vez. */
    private static function sessionWithin(
        Transaction_Context $tx,string $id,string $secret,bool $lock
    ): array {
        $rows=$tx->rows(
            'SELECT s.id,s.order_id,s.source_credential_id,'
                . 'LOWER(HEX(s.token_hash)) AS digest FROM %i s '
                . 'INNER JOIN %i t ON t.id=s.source_credential_id '
                . 'AND t.order_id=s.order_id '
                . 'WHERE s.id=%s AND s.credential_kind=%s '
                . 'AND t.credential_kind=%s '
                . 'AND s.revoked_at IS NULL AND t.revoked_at IS NULL '
                . 'AND s.expires_at>UTC_TIMESTAMP(6) '
                . 'AND t.expires_at>UTC_TIMESTAMP(6) '
                . 'AND s.last_used_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE) '
                . 'LIMIT 1 '.($lock?'FOR UPDATE':''),
            [self::table(),self::table(),$id,
                self::KIND_SESSION,self::KIND_LINK]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        $row=$rows[0];
        $orderId=Domain_Validation::sqlId((string)$row['order_id']);
        $sourceId=Domain_Validation::sqlId((string)$row['source_credential_id']);
        if (!\hash_equals(
            self::sessionHash($orderId,$sourceId,$secret),
            (string)$row['digest']
        )) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        return ['order_id'=>$orderId,'session_id'=>$id];
    }

    private static function parseTracking(mixed $secret): array
    {
        if (!\is_string($secret)
            || !\preg_match('/\Apc1\.([1-9][0-9]{0,19})\.([A-Za-z0-9_-]{43})\z/D',
                $secret,$match)) {
            // Enlaces heredados sin order_id verificable no se escanean:
            // exigir reemisión tras identidad en IMP-34, no búsqueda
            // ilimitada por todos los pedidos del comercio.
            throw new Transaction_Failure('pc_guest_tracking_invalid');
        }
        return [Domain_Validation::sqlId($match[1]),$secret];
    }

    private static function parseCookie(): array
    {
        $raw=$_COOKIE[self::COOKIE]??null;
        if (!\is_string($raw)
            || !\preg_match('/\Apc1\.([1-9][0-9]{0,19})\.([A-Za-z0-9_-]{43})\z/D',
                $raw,$match)) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        return [Domain_Validation::sqlId($match[1]),$match[2]];
    }

    private static function randomToken(): string
    {
        return \rtrim(\strtr(\base64_encode(\random_bytes(32)),'+/','-_'),'=');
    }

    private static function trackingHash(string $orderId,string $secret): string
    {
        return \hash_hmac('sha256',
            'pc-tracking-v1:'.\get_current_blog_id().':'.$orderId.':'.$secret,
            self::secretKey()
        );
    }

    private static function sessionHash(
        string $orderId,string $sourceId,string $secret
    ): string {
        return \hash_hmac('sha256',
            'pc-guest-session-v1:'.\get_current_blog_id().':'
                .$orderId.':'.$sourceId.':'.$secret,
            self::secretKey()
        );
    }

    private static function secretKey(): string
    {
        $key=\wp_salt('auth');
        if (!\is_string($key) || \strlen($key)<32) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        return $key;
    }

    private static function table(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_guest_session_invalid');
        }
        return Schema::tableNames($wpdb)['pc_guest_sessions'];
    }

    /** R8-08 solo POST JSON Origin exacto; R8-09 GET same-origin. */
    public static function sameOrigin(string $method): void
    {
        if (!\is_ssl()
            || ($_SERVER['REQUEST_METHOD']??null)!==$method
            || (isset($_SERVER['HTTP_SEC_FETCH_SITE'])
                && 'same-origin'!==$_SERVER['HTTP_SEC_FETCH_SITE'])) {
            throw new Transaction_Failure('pc_guest_origin_invalid');
        }
        $origin=$_SERVER['HTTP_ORIGIN']??null;
        if ('POST'===$method && !\is_string($origin)) {
            throw new Transaction_Failure('pc_guest_origin_invalid');
        }
        if (null!==$origin) {
            if (!\is_string($origin)) {
                throw new Transaction_Failure('pc_guest_origin_invalid');
            }
            $actual=\wp_parse_url($origin);
            $site=\wp_parse_url(\home_url('/'));
            if (!\is_array($actual) || !\is_array($site)
                || 'https'!==($actual['scheme']??null)
                || 'https'!==($site['scheme']??null)
                || \strtolower((string)($actual['host']??''))!==
                    \strtolower((string)($site['host']??''))
                || ($actual['port']??443)!==($site['port']??443)
                || isset($actual['path']) || isset($actual['query'])
                || isset($actual['fragment']) || isset($actual['user'])
                || isset($actual['pass'])) {
                throw new Transaction_Failure('pc_guest_origin_invalid');
            }
        }
    }

    private static function setCookie(string $cookie,string $expiry): void
    {
        Transaction::assertOutside();
        $deadline=Business_Time::fromApi($expiry)->getTimestamp();
        if ($deadline<=\time() || $deadline>\time()+self::IDLE_SECONDS+2
            || !\setcookie(self::COOKIE,$cookie,[
                'expires'=>$deadline,'path'=>'/','secure'=>true,
                'httponly'=>true,'samesite'=>'Strict',
            ])) {
            throw new Transaction_Failure('pc_guest_cookie_unavailable');
        }
    }
}
