<?php
/**
 * IMP-35 — autoridad independiente de acceso digital R8-13/R8-14.
 *
 * El token de 24h NO es una sesión de seguimiento ni una capacidad de staff.
 * Un GET autorizado consume una unidad ANTES del primer byte, tras verificar
 * un handle privado real. Las desconexiones posteriores pueden consumir uso.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Download
{
    private const COOKIE='__Host-pc_digital_session';
    private const SESSION_SECONDS=600;
    /** Contexto efímero exclusivo del GET ya autenticado, nunca global entre requests. */
    private static ?array $active=null;

    public static function tokenHash(string $token): string
    {
        $key=\wp_salt('auth');
        if (!\is_string($key) || \strlen($key)<32) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        return \hash_hmac('sha256',
            'pc-digital-link-v1:'.\get_current_blog_id().':'.$token,$key
        );
    }

    /** El prefijo solo permite indexar: la autoridad es el HMAC de 256 bits. */
    public static function parseToken(mixed $raw): array
    {
        if (!\is_string($raw)
            || !\preg_match('/\Apcd1\.([1-9][0-9]{0,19})\.([A-Za-z0-9_-]{43})\z/D',
                $raw,$m)) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        return [Domain_Validation::sqlId($m[1]),self::tokenHash($raw)];
    }

    /** Solo pruebas de servidor para permission_callback; nunca emite cookie. */
    public static function authorize(string $op,array $context): bool
    {
        try {
            if ('R8-13'===$op) {
                Guest_Sessions::sameOrigin('POST');
                [$rightId,$hash]=self::parseToken(
                    $context['body']['digital_token']??null
                );
                return Transaction::run(
                    static function(Transaction_Context $tx) use($rightId,$hash):bool {
                        $right=self::rightWithin($tx,$rightId);
                        self::liveTokenWithin($tx,$rightId,$hash,null);
                        self::quota($right);
                        return true;
                    }
                );
            }
            if ('R8-14'===$op) {
                Guest_Sessions::sameOrigin('GET');
                $proof=self::cookie();
                if (($context['path']['right_id']??null)!==$proof['right_id']) {
                    return false;
                }
                return Transaction::run(
                    static function(Transaction_Context $tx) use($proof):bool {
                        $right=self::rightWithin($tx,$proof['right_id']);
                        self::liveTokenWithin(
                            $tx,$proof['right_id'],null,$proof['token_id']
                        );
                        self::quota($right);
                        return true;
                    }
                );
            }
        } catch (\Throwable $ignored) {
        }
        return false;
    }

    /** Intercambio POST JSON → cookie HttpOnly limitada a 10 min y token fuente. */
    public static function exchange(array $body): array
    {
        Guest_Sessions::sameOrigin('POST');
        [$rightId,$digest]=self::parseToken($body['digital_token']??null);
        $session=\rtrim(\strtr(\base64_encode(\random_bytes(32)),'+/','-_'),'=');
        $issued=Transaction::run(
            static function(Transaction_Context $tx) use($rightId,$digest):array {
                $pre=self::rightWithin($tx,$rightId);
                $tx->lockOrder($pre['order_id']);
                $tx->lockOrderLine($pre['order_id'],$pre['order_line_id']);
                $right=self::rightWithin($tx,$rightId,true);
                self::quota($right);
                $token=self::liveTokenWithin($tx,$rightId,$digest,null,true);
                if (1!==$tx->execute(
                    'UPDATE %i SET last_used_at=UTC_TIMESTAMP(6) '
                        .'WHERE id=%s AND right_id=%s AND revoked_at IS NULL '
                        .'AND expires_at>UTC_TIMESTAMP(6)',
                    [Digital_Rights::table('pc_download_tokens'),
                        $token['id'],$rightId]
                )) {
                    throw new Transaction_Failure('pc_digital_access_denied');
                }
                $time=$tx->rows(
                    'SELECT LEAST(expires_at,DATE_ADD(UTC_TIMESTAMP(6),'
                        .'INTERVAL 10 MINUTE)) AS session_exp '
                        .'FROM %i WHERE id=%s AND right_id=%s '
                        .'AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP(6)',
                    [Digital_Rights::table('pc_download_tokens'),
                        $token['id'],$rightId]
                );
                if (1!==\count($time)) {
                    throw new Transaction_Failure('pc_digital_access_denied');
                }
                return [
                    'right_id'=>$rightId,'token_id'=>$token['id'],
                    'expires_at_utc'=>Business_Time::toApi(
                        Business_Time::fromDatabase((string)$time[0]['session_exp'])
                    ),
                ];
            },true
        );
        self::setCookie($issued,$session);
        return [
            'right_id'=>$issued['right_id'],
            'expires_at_utc'=>$issued['expires_at_utc'],
        ];
    }

    /**
     * Obtiene metadatos reales del derecho y constata cobro acreditado.
     * Se usa antes y DESPUÉS de los locks; no acepta un archivo reemplazado.
     */
    public static function rightWithin(
        Transaction_Context $tx,string $rightId,bool $lock=false
    ): array {
        $rightId=Domain_Validation::sqlId($rightId);
        $rows=$tx->rows(
            'SELECT r.id,r.order_line_id,r.file_id,r.revoked_at,'
                .'r.download_count,r.optional_limit,r.row_version,'
                .'l.order_id,l.kind,l.sellable_id,l.fulfillment_state,'
                .'l.catalog_snapshot,o.status AS order_status,'
                .'o.payment_method,o.total_cents,'
                .'f.private_storage_key,f.original_filename,f.byte_size,'
                .'LOWER(HEX(f.content_hash)) AS sha256 '
                .'FROM %i r INNER JOIN %i l ON l.id=r.order_line_id '
                .'INNER JOIN %i o ON o.id=l.order_id '
                .'INNER JOIN %i f ON f.id=r.file_id '
                .'AND f.sellable_id=l.sellable_id '
                .'WHERE r.id=%s'.($lock?' FOR UPDATE':''),
            [Digital_Rights::table('pc_download_rights'),
                Digital_Rights::table('pc_order_lines'),
                Digital_Rights::table('pc_orders'),
                Digital_Rights::table('pc_digital_files'),$rightId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        $r=$rows[0];
        if (null!==$r['revoked_at'] || 'digital'!==$r['kind']
            || 'HABILITADO'!==$r['fulfillment_state']
            || !\in_array($r['order_status'],['CONFIRMADO','FINALIZADO'],true)
            || 'mercado_pago'!==$r['payment_method']
            || !\is_string($r['private_storage_key'])
            || !\is_string($r['original_filename'])
            || !\is_string($r['sha256'])
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$r['sha256'])
            || !\is_string($r['byte_size'])
            || !\ctype_digit($r['byte_size']) || '0'===$r['byte_size']) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        $snapshot=Orders_Domain::catalogSnapshot(
            (string)$r['catalog_snapshot'],'digital'
        );
        $sold=$snapshot['files']??(
            isset($snapshot['file_id']) ? [['file_id'=>$snapshot['file_id']]] : []
        );
        if (!\is_array($sold) || []===$sold
            || !\in_array((string)$r['file_id'],\array_column($sold,'file_id'),true)) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        // Un derecho concedido antes no basta: exigir el asiento original
        // confirmado y el intento MP aprobado del MISMO pedido.
        $payments=$tx->rows(
            'SELECT e.id,e.amount_cents,a.state '
                .'FROM %i e INNER JOIN %i a ON a.id=e.payment_attempt_id '
                .'AND a.order_id=e.order_id AND a.provider=e.provider '
                .'WHERE e.order_id=%s AND e.kind=%s AND e.provider=%s '
                .'ORDER BY e.id LIMIT 2',
            [Digital_Rights::table('pc_payment_events'),
                Digital_Rights::table('pc_payment_attempts'),
                (string)$r['order_id'],'initial_charge','mercado_pago']
        );
        if (1!==\count($payments)
            || 'APROBADO'!==(string)$payments[0]['state']) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        try {
            if (Money::fromSignedLedger((string)$payments[0]['amount_cents'])
                < Money::fromApi((string)$r['total_cents'])) {
                throw new \RuntimeException('insufficient');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        $r['order_id']=Domain_Validation::sqlId((string)$r['order_id']);
        $r['order_line_id']=Domain_Validation::sqlId((string)$r['order_line_id']);
        $r['file_id']=Domain_Validation::sqlId((string)$r['file_id']);
        return $r;
    }

    /** Cuota congelada al otorgar el derecho; no aplicar settings actuales. */
    private static function quota(array $right): void
    {
        $used=(string)$right['download_count'];
        $limit=$right['optional_limit'];
        if (!\ctype_digit($used) || \strlen($used)>10
            || Transaction_Context::compareId($used,'4294967295')>=0
            || (null!==$limit &&
                (!\ctype_digit((string)$limit)
                    || Transaction_Context::compareId($used,(string)$limit)>=0))) {
            throw new Transaction_Failure('pc_digital_quota_exhausted');
        }
    }

    private static function liveTokenWithin(
        Transaction_Context $tx,string $rightId,?string $hash,
        ?string $tokenId,bool $lock=false
    ): array {
        if (null===$hash && null===$tokenId) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        $where=null!==$hash?'t.token_hash=UNHEX(%s)':'t.id=%s';
        $rows=$tx->rows(
            'SELECT t.id,t.right_id,t.expires_at '
                .'FROM %i t WHERE t.right_id=%s AND '.$where.' '
                .'AND t.revoked_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6) '
                .'LIMIT 2'.($lock?' FOR UPDATE':''),
            [Digital_Rights::table('pc_download_tokens'),$rightId,
                $hash??$tokenId]
        );
        if (1!==\count($rows)) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        $rows[0]['id']=Domain_Validation::sqlId((string)$rows[0]['id']);
        return $rows[0];
    }

    /** Cookie firmada independiente: token_id + right_id + TTL + nonce. */
    private static function cookie(): array
    {
        $raw=$_COOKIE[self::COOKIE]??null;
        if (!\is_string($raw)
            || !\preg_match(
                '/\Apcd1\.([1-9][0-9]{0,19})\.([1-9][0-9]{0,19})\.([1-9][0-9]{0,12})\.([A-Za-z0-9_-]{43})\.([a-f0-9]{64})\z/D',
                $raw,$m
            )) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        $tokenId=Domain_Validation::sqlId($m[1]);
        $rightId=Domain_Validation::sqlId($m[2]);
        $expiry=$m[3];
        if (!\ctype_digit($expiry) || (int)$expiry<=\time()
            || (int)$expiry>\time()+self::SESSION_SECONDS+2) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        // Construir usando los componentes exactos, sin capturar cookie cruda.
        $base='pcd1.'.$tokenId.'.'.$rightId.'.'.$expiry.'.'.$m[4];
        if (!\hash_equals(self::sessionMac($base),$m[5])) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        return ['token_id'=>$tokenId,'right_id'=>$rightId];
    }

    private static function sessionMac(string $base): string
    {
        $key=\wp_salt('auth');
        if (!\is_string($key) || \strlen($key)<32) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        return \hash_hmac('sha256',
            'pc-digital-session-v1:'.\get_current_blog_id().':'.$base,$key
        );
    }

    private static function setCookie(array $data,string $random): void
    {
        Transaction::assertOutside();
        $deadline=Business_Time::fromApi($data['expires_at_utc'])->getTimestamp();
        if ($deadline<=\time() || $deadline>\time()+self::SESSION_SECONDS+2) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        $base='pcd1.'.$data['token_id'].'.'.$data['right_id'].'.'
            .$deadline.'.'.$random;
        if (\headers_sent() || !\setcookie(
            self::COOKIE,$base.'.'.self::sessionMac($base),[
                'expires'=>$deadline,'path'=>'/','secure'=>true,
                'httponly'=>true,'samesite'=>'Strict',
            ]
        )) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
    }

    /**
     * Verificar checksum/size de un handle fuera de SQL, antes de debitarlos.
     * Nunca transmitir bytes de otro archivo por reemplazo de la asignación.
     */
    public static function prepare(string $rightId): array
    {
        Guest_Sessions::sameOrigin('GET');
        $rightId=Domain_Validation::sqlId($rightId);
        $proof=self::cookie();
        if ($proof['right_id']!==$rightId) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
        $pre=Transaction::run(
            static function(Transaction_Context $tx) use($rightId,$proof):array {
                $right=self::rightWithin($tx,$rightId);
                self::liveTokenWithin($tx,$rightId,null,$proof['token_id']);
                self::quota($right);
                return $right;
            }
        );
        $path=Digital_Private_Storage::verify(
            $pre['private_storage_key'],$pre['sha256'],$pre['byte_size']
        );
        $handle=@\fopen($path,'rb');
        if (false===$handle) {
            throw new Transaction_Failure('pc_private_storage_unavailable');
        }
        try {
            $stat=\fstat($handle);
            $hash=\hash_init('sha256');
            $read=\hash_update_stream($hash,$handle);
            if (!\is_array($stat) || (string)($stat['size']??'')!==$pre['byte_size']
                || $read!==(int)$stat['size']
                || !\hash_equals($pre['sha256'],\hash_final($hash))
                || !\rewind($handle)) {
                throw new Transaction_Failure('pc_private_storage_unavailable');
            }
            self::$active=$proof;
            $actor=Event_Actor::digital($proof['token_id']);
            $requestHex=\bin2hex(\random_bytes(16));
            // El contador y evento quedan en un COMMIT seguro ANTES del stream.
            Transaction::run(
                static function(Transaction_Context $tx) use(
                    $pre,$proof,$rightId,$actor,$requestHex
                ):void {
                    $tx->lockOrder($pre['order_id']);
                    $tx->lockOrderLine($pre['order_id'],$pre['order_line_id']);
                    Digital_Rights::authorizeTransferWithin(
                        $tx,$actor,$pre['order_id'],$pre['order_line_id'],
                        $rightId,$requestHex,[
                            'token_id'=>$proof['token_id'],
                            'right_id'=>$rightId,'file_id'=>$pre['file_id'],
                            'verified_sha256'=>$pre['sha256'],
                        ]
                    );
                },true
            );
            return [
                'handle'=>$handle,'size'=>$pre['byte_size'],
                'filename'=>$pre['original_filename']
            ];
        } catch (\Throwable $error) {
            \fclose($handle);
            throw $error;
        } finally {
            self::$active=null;
        }
    }

    /** Callback de Event_Actor: no concede autoridad a la cookie guest. */
    public static function verifiesActor(string $reference,string $purpose): bool
    {
        return 'digital_download'===$purpose
            && null!==self::$active
            && $reference===self::$active['token_id'];
    }

    /** TransferVerifier invocado bajo lock del derecho por IMP-23. */
    public static function verifiesTransferWithin(
        Transaction_Context $tx,Event_Actor $actor,
        string $orderId,string $lineId,string $rightId,
        array $right,array $proof
    ): bool {
        try {
            $cookie=self::cookie();
            if ('digital'!==$actor->kind() || null===self::$active
                || $cookie!==self::$active
                || $cookie['token_id']!==($proof['token_id']??null)
                || $cookie['right_id']!==$rightId
                || $rightId!==($proof['right_id']??null)
                || (string)$right['file_id']!==($proof['file_id']??null)) {
                return false;
            }
            $live=self::rightWithin($tx,$rightId,true);
            self::liveTokenWithin($tx,$rightId,null,$cookie['token_id'],true);
            return $live['order_id']===$orderId
                && $live['order_line_id']===$lineId
                && $live['file_id']===$proof['file_id']
                && \hash_equals($live['sha256'],(string)($proof['verified_sha256']??''));
        } catch (\Throwable $ignored) {
            return false;
        }
    }
}
