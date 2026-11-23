<?php
/**
 * IMP-35 — R8-59, reemisión administrativa legitimada de un enlace digital.
 *
 * No se acepta el ID del derecho, un folio, un nonce o una cadena de evidencia
 * como prueba de identidad. Caso guest real con OTP consumido, o verificador
 * interno registrado que compruebe y consuma evidencia en la misma TX.
 * La primera URL se libera únicamente tras COMMIT inequívoco.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Reissue
{
    private const OPERATION='digital_link_reissue';
    private const MAX_TOKENS=100;
    /** @var callable|null */
    private static $identityVerifier=null;

    /**
     * Verificador alternativo legítimo para recuperación sin seguimiento.
     * (Transaction_Context|null, right, body, userId): bool.
     * Con TX debe consumir la evidencia de un uso, no solo atestiguarla.
     */
    public static function registerIdentityVerifier(callable $verifier): void
    {
        if (null!==self::$identityVerifier) {
            throw new \LogicException('pc_digital_identity_verifier_duplicate');
        }
        self::$identityVerifier=$verifier;
    }

    public static function guard(array $ctx,?string $role): array
    {
        if ('A'!==$role || 'A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_digital_files_manage')
            || (int)\get_current_blog_id()<1
            || (\function_exists('is_network_admin')&&\is_network_admin())) {
            return [];
        }
        try {
            $id=Domain_Validation::sqlId($ctx['path']['id']??'');
            $body=self::validated($ctx['body']??null);
            $scope=self::scope($id);
            if (Idempotency::keyExistsForScope(
                $scope,self::OPERATION,$body['idempotency_key'],$body
            )) {
                return self::proof(false,true);
            }
            Digital_Download_View::pageUrl();
            $right=Transaction::run(
                static fn(Transaction_Context $tx):array=>
                    Digital_Download::rightWithin($tx,$id)
            );
            if ((string)$right['row_version']!==$body['expected_version']
                || !self::identity(null,$right,$body)) {
                return [];
            }
            return self::proof(true,false);
        } catch (\Throwable $ignored) {
            return [];
        }
    }

    public static function reissue(string $rightId,array $body): array
    {
        self::admin();
        $rightId=Domain_Validation::sqlId($rightId);
        $body=self::validated($body);
        $scope=self::scope($rightId);
        $actor=Event_Actor::staff(['perucommerce_digital_files_manage']);
        $existing=Idempotency::keyExistsForScope(
            $scope,self::OPERATION,$body['idempotency_key'],$body
        );
        $url=$existing?null:Digital_Download_View::pageUrl();
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $scope,$actor,$rightId,$body,$url
            ):array {
                $claim=Idempotency::claimWithin(
                    $tx,$scope,self::OPERATION,$body['idempotency_key'],$body
                );
                if ('replay'===$claim['status']) {
                    if ('download_right'!==($claim['entity_type']??null)
                        || $rightId!==($claim['entity_id']??null)
                        || 200!==($claim['response_code']??null)) {
                        throw new Transaction_Failure('pc_digital_reissue_conflict');
                    }
                    return [
                        'operation_id'=>$claim['operation_id'],
                        'already_issued'=>true,
                    ];
                }
                if ('new'!==$claim['status']) {
                    throw new Transaction_Failure('pc_digital_reissue_conflict');
                }
                if (null===$url) {
                    throw new Transaction_Failure('pc_digital_reissue_conflict');
                }
                // Prelectura sin lock solo para identificar el padre. Después
                // hay que revalidar TODO bajo pedido → línea → caso → derecho.
                $pre=Digital_Download::rightWithin($tx,$rightId);
                $orderId=$pre['order_id'];
                $lineId=$pre['order_line_id'];
                $tx->lockOrder($orderId);
                $tx->lockOrderLine($orderId,$lineId);
                if (!self::identity(
                    $tx,$pre,$body,$actor,$claim['operation_id']
                )) {
                    throw new Transaction_Failure('pc_digital_identity_unverified');
                }
                $right=Digital_Download::rightWithin($tx,$rightId,true);
                if ((string)$right['row_version']!==$body['expected_version']
                    || $right['order_id']!==$orderId
                    || $right['order_line_id']!==$lineId) {
                    throw new Transaction_Failure('pc_digital_reissue_conflict');
                }
                $tokens=$tx->rows(
                    'SELECT id FROM %i WHERE right_id=%s '
                        .'AND revoked_at IS NULL ORDER BY id ASC '
                        .'LIMIT 101 FOR UPDATE',
                    [Digital_Rights::table('pc_download_tokens'),$rightId]
                );
                if (\count($tokens)>self::MAX_TOKENS) {
                    throw new Transaction_Failure('pc_digital_reissue_conflict');
                }
                $tx->execute(
                    'UPDATE %i SET revoked_at=UTC_TIMESTAMP(6) '
                        .'WHERE right_id=%s AND revoked_at IS NULL',
                    [Digital_Rights::table('pc_download_tokens'),$rightId]
                );
                // 256 bits; HMAC ligado a la instalación y no reusado
                // como tracking, OTP, nonce WP ni cookie digital.
                $secret='pcd1.'.$rightId.'.'
                    .\rtrim(\strtr(\base64_encode(\random_bytes(32)),
                        '+/','-_'),'=');
                $digest=Digital_Download::tokenHash($secret);
                if (1!==$tx->execute(
                    'INSERT INTO %i (right_id,token_hash,issued_at,'
                        .'expires_at,revoked_at,last_used_at) '
                        .'VALUES (%s,UNHEX(%s),UTC_TIMESTAMP(6),'
                        .'DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 24 HOUR),NULL,NULL)',
                    [Digital_Rights::table('pc_download_tokens'),$rightId,$digest]
                )) {
                    throw new Transaction_Failure('pc_digital_reissue_unavailable');
                }
                $inserted=$tx->rows(
                    'SELECT id,expires_at FROM %i WHERE id=LAST_INSERT_ID() '
                        .'AND right_id=%s AND revoked_at IS NULL',
                    [Digital_Rights::table('pc_download_tokens'),$rightId]
                );
                if (1!==\count($inserted)
                    || 1!==$tx->execute(
                        'UPDATE %i SET row_version=row_version+1 '
                            .'WHERE id=%s AND row_version=%s AND revoked_at IS NULL',
                        [Digital_Rights::table('pc_download_rights'),$rightId,
                            $body['expected_version']]
                    )) {
                    throw new Transaction_Failure('pc_digital_reissue_conflict');
                }
                Event_Journal::auditWithin(
                    $tx,$actor,$claim['operation_id'],
                    'download_right',$rightId,'digital_link_reissued',
                    'buyer_identity_verified',
                    \hash('sha256',$body['expected_version']),
                    \hash('sha256',Settings_Store::nextVersion(
                        $body['expected_version']
                    ))
                );
                Idempotency::completeLocalWithin(
                    $tx,$claim['operation_id'],'download_right',$rightId,200
                );
                return [
                    'operation_id'=>$claim['operation_id'],
                    'url_once'=>$url.'#digital='.$secret,
                    'expires_at_utc'=>Business_Time::toApi(
                        Business_Time::fromDatabase(
                            (string)$inserted[0]['expires_at']
                        )
                    ),
                ];
            },true
        );
    }

    /**
     * Verificación guest E07-D: OTP REAL al correo original + grant consumido
     * + primera solicitud R8-12 idempotente completa de la MISMA sesión.
     * El caso de 24h se cierra por CAS al usarse, en TX de reemisión.
     */
    private static function verifiedCase(
        Transaction_Context $tx,array $right,string $caseId,
        ?Event_Actor $actor,?string $operationId
    ): bool {
        $orderId=$right['order_id'];
        $orders=$tx->rows(
            'SELECT buyer_email FROM %i WHERE id=%s',
            [Digital_Rights::table('pc_orders'),$orderId]
        );
        if (1!==\count($orders)
            || !\is_string($orders[0]['buyer_email'])) {
            return false;
        }
        $cases=$tx->rows(
            'SELECT c.id,c.evidence_snapshot,c.opened_at,c.row_version,'
                .'LOWER(HEX(i.scope_hash)) AS scope_digest '
                .'FROM %i c INNER JOIN %i e '
                .'ON e.case_id=c.id AND e.event_code=%s AND e.actor_kind=%s '
                .'INNER JOIN %i i ON i.id=e.idempotency_id '
                .'AND i.operation_type=%s AND i.technical_phase=%s '
                .'AND i.entity_type=%s AND i.entity_id=c.id AND i.response_code=%d '
                .'WHERE c.id=%s AND c.order_id=%s AND c.case_type=%s '
                .'AND c.case_phase=%s AND c.order_line_id IS NULL '
                .'AND c.source_operation_id IS NULL AND c.created_by IS NULL '
                .'AND c.opened_at>=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 24 HOUR) '
                .'AND NOT EXISTS (SELECT 1 FROM %i earlier '
                .'WHERE earlier.case_id=c.id AND earlier.id<e.id) LIMIT 2',
            [Orders_Domain::table('pc_cases'),
                Orders_Domain::table('pc_case_events'),
                'guest_request_opened','guest',
                Orders_Domain::table('pc_idempotency'),
                'guest_case_request','complete','case',202,
                $caseId,$orderId,'guest_reissue_download','open_review',
                Orders_Domain::table('pc_case_events')]
        );
        if (1!==\count($cases)
            || !\is_string($cases[0]['evidence_snapshot'])) {
            return false;
        }
        $original=Orders_Domain::decode($cases[0]['evidence_snapshot'],10000);
        if (($original['purpose']??null)!=='reissue_download') { return false; }
        $grants=$tx->rows(
            'SELECT g.id,g.guest_session_id,'
                .'LOWER(HEX(ch.recipient_hash)) AS recipient_digest '
                .'FROM %i g INNER JOIN %i ch '
                .'ON ch.id=g.verified_challenge_id AND ch.order_id=g.order_id '
                .'AND ch.guest_session_id=g.guest_session_id '
                .'AND ch.purpose=g.purpose '
                .'WHERE g.order_id=%s AND g.purpose=%s '
                .'AND g.consumed_at IS NOT NULL AND ch.consumed_at IS NOT NULL '
                .'AND ch.superseded_at IS NULL '
                .'AND g.created_at>=DATE_SUB(%s,INTERVAL 6 MINUTE) '
                .'AND g.created_at<=%s '
                .'AND g.consumed_at>=DATE_SUB(%s,INTERVAL 1 MINUTE) '
                .'AND g.consumed_at<=%s '
                .'ORDER BY g.id DESC LIMIT 32',
            [Orders_Domain::table('pc_action_grants'),
                Orders_Domain::table('pc_otp_challenges'),
                $orderId,'reissue_download',
                $cases[0]['opened_at'],$cases[0]['opened_at'],
                $cases[0]['opened_at'],$cases[0]['opened_at']]
        );
        $matches=0;
        foreach ($grants as $grant) {
            if (\hash_equals(
                Idempotency::guestCaseScopeDigest(
                    $orderId,Domain_Validation::sqlId(
                        (string)$grant['guest_session_id']
                    ),'reissue_download'
                ),(string)$cases[0]['scope_digest']
            ) && Guest_Challenges::matchesOriginalRecipient(
                $orders[0]['buyer_email'],
                (string)$grant['recipient_digest']
            )) {
                ++$matches;
            }
        }
        if (1!==$matches) { return false; }
        if (null!==$actor && null!==$operationId) {
            $locked=$tx->lockCaseForOrder($orderId,$caseId);
            if ('guest_reissue_download'!==$locked['case_type']
                || 'open_review'!==$locked['case_phase']
                || (string)$locked['row_version']!==(string)$cases[0]['row_version']) {
                return false;
            }
            $version=$tx->advanceVersion(
                'pc_cases',$caseId,(string)$cases[0]['row_version']
            );
            if (1!==$tx->execute(
                'UPDATE %i SET case_phase=%s,resolved_at=UTC_TIMESTAMP(6) '
                    .'WHERE id=%s AND order_id=%s AND case_type=%s '
                    .'AND case_phase=%s AND row_version=%s',
                [Orders_Domain::table('pc_cases'),'closed_reissued',
                    $caseId,$orderId,'guest_reissue_download',
                    'open_review',$version]
            )) {
                throw new Transaction_Failure('pc_digital_identity_unverified');
            }
            Event_Journal::caseWithin(
                $tx,$actor,$operationId,$caseId,
                'digital_reissue_identity_used','reissue_download'
            );
        }
        return true;
    }

    private static function identity(
        ?Transaction_Context $tx,array $right,array $body,
        ?Event_Actor $actor=null,?string $operationId=null
    ): bool {
        $ref=$body['identity_verification_ref'];
        if (\preg_match('/\A[1-9][0-9]{0,19}\z/D',$ref)) {
            try {
                $caseId=Domain_Validation::sqlId($ref);
                return null===$tx
                    ? Transaction::run(
                        static fn(Transaction_Context $db):bool=>
                            self::verifiedCase($db,$right,$caseId,null,null)
                    )
                    : self::verifiedCase(
                        $tx,$right,$caseId,$actor,$operationId
                    );
            } catch (\Throwable $ignored) {
                return false;
            }
        }
        if (null===self::$identityVerifier) { return false; }
        try {
            return true===(self::$identityVerifier)(
                $tx,$right,$body,(string)\get_current_user_id()
            );
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    private static function validated(mixed $body): array
    {
        if (!\is_array($body) || \array_is_list($body)
            || \count($body)!==4) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $keys=\array_keys($body);
        $expected=['identity_verification_ref','reason',
            'expected_version','idempotency_key'];
        \sort($keys,\SORT_STRING);
        \sort($expected,\SORT_STRING);
        if ($keys!==$expected) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $ref=$body['identity_verification_ref'];
        $reason=$body['reason'];
        $key=$body['idempotency_key'];
        if (!\is_string($ref) || ''===\trim($ref)
            || !\mb_check_encoding($ref,'UTF-8')
            || \mb_strlen($ref,'UTF-8')>500
            || \preg_match('/[\x00-\x1F\x7F]/',$ref)
            || !\is_string($reason) || ''===\trim($reason)
            || !\mb_check_encoding($reason,'UTF-8')
            || \mb_strlen($reason,'UTF-8')>1000
            || \preg_match('/[\x00-\x1F\x7F]/',$reason)
            || !\is_string($key) || \strlen($key)<16 || \strlen($key)>128
            || \preg_match('/[\x00-\x20\x7F]/',$key)
            || !\is_string($body['expected_version'])) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        Domain_Validation::sqlId($body['expected_version']);
        return $body;
    }

    private static function admin(): void
    {
        if ('A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_digital_files_manage')
            || (int)\get_current_blog_id()<1) {
            throw new Transaction_Failure('pc_digital_identity_unverified');
        }
    }

    private static function scope(string $rightId): array
    {
        return [
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>'download_right',
            'resource_id'=>$rightId,
            'purpose'=>self::OPERATION,
        ];
    }

    private static function proof(bool $fresh,bool $replay): array
    {
        return [
            'authorized'=>true,'same_site'=>true,
            'resource_checked'=>true,'state_checked'=>true,
            'field_checked'=>true,
            'digital_buyer_reverified'=>$fresh,
            'digital_reissue_existing_key_checked'=>$replay,
        ];
    }
}
