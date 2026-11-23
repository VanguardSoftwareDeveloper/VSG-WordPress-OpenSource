<?php
/**
 * IMP-32 — grant sensible de 256 bits, un solo uso y una sola finalidad.
 *
 * No es una sesión de lectura ni el token de seguimiento. R8-11 lo
 * entrega exclusivamente como cookie __Host-, nunca por JSON.
 * IMP-33 consumirá consumeWithin() en el MISMO COMMIT del caso/evento
 * y DESPUÉS de reclamar idempotencia para permitir replay legítimo.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Guest_Action_Grants
{
    private const COOKIE='__Host-pc_action_grant';
    private const TTL_SECONDS=300;

    /**
     * Se llama dentro del TX de verificación OTP con pedido y sesión
     * ya bloqueados, después de consumir el desafío correcto.
     *
     * @return array{cookie:string,expires_at_utc:string}
     */
    public static function issueWithin(
        Transaction_Context $tx,array $session,string $purpose,string $challengeId
    ): array {
        Guest_Challenges::purpose($purpose);
        $orderId=Domain_Validation::sqlId((string)($session['order_id']??''));
        $sessionId=Domain_Validation::sqlId((string)($session['session_id']??''));
        $challengeId=Domain_Validation::sqlId($challengeId);
        $tx->lockedOrder($orderId);
        $table=self::table();
        $challenges=$tx->rows(
            'SELECT id FROM %i WHERE id=%s AND order_id=%s '
                . 'AND guest_session_id=%s AND purpose=%s '
                . 'AND consumed_at IS NOT NULL AND superseded_at IS NULL '
                . 'AND expires_at>UTC_TIMESTAMP(6)',
            [self::challengesTable(),$challengeId,$orderId,$sessionId,$purpose]
        );
        if (1!==\count($challenges)) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }

        // El navegador usa UNA cookie grant: invalidar también los
        // grants antiguos de otros propósitos para no acumular poder.
        $tx->execute(
            'UPDATE %i SET consumed_at=UTC_TIMESTAMP(6) '
                . 'WHERE guest_session_id=%s AND order_id=%s '
                . 'AND consumed_at IS NULL',
            [$table,$sessionId,$orderId]
        );

        $secret=self::randomToken();
        // La clave se ata al identificador del desafío REAL validado.
        $hash=self::digest($orderId,$sessionId,$purpose,$challengeId,$secret);
        $guest=self::guestTable();
        if (1!==$tx->execute(
            'INSERT INTO %i (order_id,guest_session_id,purpose,token_hash,'
                . 'verified_challenge_id,expires_at,consumed_at,created_at) '
                . 'SELECT s.order_id,s.id,%s,UNHEX(%s),%s,'
                . 'LEAST(s.expires_at,'
                . 'DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE)),'
                . 'NULL,UTC_TIMESTAMP(6) FROM %i s '
                . 'INNER JOIN %i t ON t.id=s.source_credential_id '
                . 'AND t.order_id=s.order_id WHERE s.id=%s AND s.order_id=%s '
                . 'AND s.credential_kind=%s AND t.credential_kind=%s '
                . 'AND s.revoked_at IS NULL AND t.revoked_at IS NULL '
                . 'AND s.expires_at>UTC_TIMESTAMP(6) '
                . 'AND t.expires_at>UTC_TIMESTAMP(6)',
            [$table,$purpose,$hash,$challengeId,$guest,$guest,
                $sessionId,$orderId,'session','tracking']
        )) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        $last=$tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1!==\count($last)) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        $id=Domain_Validation::sqlId((string)$last[0]['id']);
        $expires=$tx->rows(
            'SELECT expires_at FROM %i WHERE id=%s AND order_id=%s '
                . 'AND guest_session_id=%s AND purpose=%s',
            [$table,$id,$orderId,$sessionId,$purpose]
        );
        if (1!==\count($expires)) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        return [
            'cookie'=>'pc1.'.$id.'.'.$secret,
            'expires_at_utc'=>Business_Time::toApi(
                Business_Time::fromDatabase((string)$expires[0]['expires_at'])
            ),
        ];
    }

    /** Entregar exclusivamente tras COMMIT y respuesta HTTPS privada. */
    public static function deliverCookie(array $grant): void
    {
        Transaction::assertOutside();
        $value=$grant['cookie']??null;
        $expiry=$grant['expires_at_utc']??null;
        if (!\is_string($value) || !\is_string($expiry)
            || !\preg_match('/\Apc1\.[1-9][0-9]{0,19}\.[A-Za-z0-9_-]{43}\z/D',$value)
            || !\is_ssl() || \headers_sent()) {
            throw new Transaction_Failure('pc_guest_grant_cookie_unavailable');
        }
        $date=Business_Time::fromApi($expiry)->getTimestamp();
        if ($date<=\time() || $date>\time()+self::TTL_SECONDS+2
            || !\setcookie(self::COOKIE,$value,[
                'expires'=>$date,'path'=>'/','secure'=>true,
                'httponly'=>true,'samesite'=>'Strict'
            ])) {
            throw new Transaction_Failure('pc_guest_grant_cookie_unavailable');
        }
    }

    /**
     * IMP-33: prueba de autorización para permission_callback; NO consume
     * grant y NO renueva sesión. La raíz SQL revalidará y consumirá bajo
     * FOR UPDATE antes de insertar el caso. Un replay no necesita grant.
     */
    public static function availableFor(
        string $orderId,string $sessionId,string $purpose
    ): bool {
        try {
            Guest_Challenges::purpose($purpose);
            $orderId=Domain_Validation::sqlId($orderId);
            $sessionId=Domain_Validation::sqlId($sessionId);
            $cookie=$_COOKIE[self::COOKIE]??null;
            if (!\is_string($cookie)
                || !\preg_match(
                    '/\Apc1\.([1-9][0-9]{0,19})\.([A-Za-z0-9_-]{43})\z/D',
                    $cookie,$matches
                )) {
                return false;
            }
            $grantId=Domain_Validation::sqlId($matches[1]);
            return Transaction::run(
                static function(Transaction_Context $tx) use(
                    $orderId,$sessionId,$purpose,$grantId,$matches
                ): bool {
                    $rows=$tx->rows(
                        'SELECT g.verified_challenge_id,'
                            . 'LOWER(HEX(g.token_hash)) AS digest FROM %i g '
                            . 'INNER JOIN %i c ON c.id=g.verified_challenge_id '
                            . 'AND c.order_id=g.order_id '
                            . 'AND c.guest_session_id=g.guest_session_id '
                            . 'AND c.purpose=g.purpose '
                            . 'INNER JOIN %i s ON s.id=g.guest_session_id '
                            . 'AND s.order_id=g.order_id '
                            . 'INNER JOIN %i t ON t.id=s.source_credential_id '
                            . 'AND t.order_id=s.order_id '
                            . 'WHERE g.id=%s AND g.order_id=%s '
                            . 'AND g.guest_session_id=%s AND g.purpose=%s '
                            . 'AND g.consumed_at IS NULL '
                            . 'AND g.expires_at>UTC_TIMESTAMP(6) '
                            . 'AND c.consumed_at IS NOT NULL '
                            . 'AND c.superseded_at IS NULL '
                            . 'AND s.credential_kind=%s '
                            . 'AND t.credential_kind=%s '
                            . 'AND s.revoked_at IS NULL '
                            . 'AND t.revoked_at IS NULL '
                            . 'AND s.expires_at>UTC_TIMESTAMP(6) '
                            . 'AND t.expires_at>UTC_TIMESTAMP(6) '
                            . 'AND s.last_used_at>DATE_SUB('
                            . 'UTC_TIMESTAMP(6),INTERVAL 30 MINUTE) LIMIT 2',
                        [self::table(),self::challengesTable(),
                            self::guestTable(),self::guestTable(),
                            $grantId,$orderId,$sessionId,$purpose,
                            'session','tracking']
                    );
                    if (1!==\count($rows)) {
                        return false;
                    }
                    $challenge=Domain_Validation::sqlId(
                        (string)$rows[0]['verified_challenge_id']
                    );
                    return \hash_equals(
                        self::digest(
                            $orderId,$sessionId,$purpose,
                            $challenge,$matches[2]
                        ),
                        (string)$rows[0]['digest']
                    );
                }
            );
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /**
     * IMP-33 usa este método después de Idempotency::claimWithin('new'),
     * ANTES de crear pc_cases/pc_case_events y en el mismo root SQL.
     * El caller NO aporta grant_id, token o email; se leen de cookie.
     * Verificación guest_session + source y lockOrder debe preceder caso.
     *
     * @return string ID de grant consumido una sola vez
     */
    public static function consumeWithin(
        Transaction_Context $tx,string $orderId,string $sessionId,
        string $purpose
    ): string {
        Guest_Challenges::purpose($purpose);
        $orderId=Domain_Validation::sqlId($orderId);
        $sessionId=Domain_Validation::sqlId($sessionId);
        $verified=Guest_Sessions::lockedForSensitiveWithin($tx);
        if ($verified['order_id']!==$orderId
            || $verified['session_id']!==$sessionId) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        $cookie=$_COOKIE[self::COOKIE]??null;
        if (!\is_string($cookie)
            || !\preg_match('/\Apc1\.([1-9][0-9]{0,19})\.([A-Za-z0-9_-]{43})\z/D',
                $cookie,$parts)) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        $grantId=Domain_Validation::sqlId($parts[1]);
        $rows=$tx->rows(
            'SELECT g.id,g.verified_challenge_id,'
                . 'LOWER(HEX(g.token_hash)) AS digest '
                . 'FROM %i g INNER JOIN %i c '
                . 'ON c.id=g.verified_challenge_id '
                . 'AND c.order_id=g.order_id '
                . 'AND c.guest_session_id=g.guest_session_id '
                . 'AND c.purpose=g.purpose '
                . 'WHERE g.id=%s AND g.order_id=%s '
                . 'AND g.guest_session_id=%s AND g.purpose=%s '
                . 'AND g.consumed_at IS NULL AND g.expires_at>UTC_TIMESTAMP(6) '
                . 'AND c.consumed_at IS NOT NULL AND c.superseded_at IS NULL '
                . 'LIMIT 1 FOR UPDATE',
            [self::table(),self::challengesTable(),$grantId,
                $orderId,$sessionId,$purpose]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        $challengeId=Domain_Validation::sqlId(
            (string)$rows[0]['verified_challenge_id']
        );
        if (!\hash_equals(
            self::digest($orderId,$sessionId,$purpose,$challengeId,$parts[2]),
            (string)$rows[0]['digest']
        )) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        if (1!==$tx->execute(
            'UPDATE %i SET consumed_at=UTC_TIMESTAMP(6) '
                . 'WHERE id=%s AND order_id=%s AND guest_session_id=%s '
                . 'AND purpose=%s AND consumed_at IS NULL '
                . 'AND expires_at>UTC_TIMESTAMP(6)',
            [self::table(),$grantId,$orderId,$sessionId,$purpose]
        )) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        return $grantId;
    }

    private static function digest(
        string $orderId,string $sessionId,string $purpose,
        string $challengeId,string $secret
    ): string {
        $key=\wp_salt('auth');
        if (!\is_string($key) || \strlen($key)<32) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        return \hash_hmac('sha256',
            'pc-action-grant-v1:'.\get_current_blog_id().':'
                .$orderId.':'.$sessionId.':'.$purpose.':'
                .$challengeId.':'.$secret,$key
        );
    }

    private static function randomToken(): string
    {
        return \rtrim(\strtr(\base64_encode(\random_bytes(32)),'+/','-_'),'=');
    }

    private static function table(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        return Schema::tableNames($wpdb)['pc_action_grants'];
    }

    private static function challengesTable(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        return Schema::tableNames($wpdb)['pc_otp_challenges'];
    }

    private static function guestTable(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_guest_grant_invalid');
        }
        return Schema::tableNames($wpdb)['pc_guest_sessions'];
    }
}
