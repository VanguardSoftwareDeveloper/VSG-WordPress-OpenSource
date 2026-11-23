<?php
/**
 * IMP-23 — derechos digitales por línea y archivo vendido inmutable.
 *
 * Otorgar un derecho NO emite token. Los enlaces 256-bit/24h y streaming
 * del comprador pertenecen a fases posteriores y revalidarán derecho,
 * cobro, estado de línea y cuota bajo lock antes de iniciar transferencia.
 *
 * Revocación: solo tras autorización de expediente administrativo R8-61
 * verificada por servidor; R8-60 no decide expediente ni ejecuta refund.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Digital_Rights
{
    private const TABLES = [
        'pc_download_rights', 'pc_download_tokens', 'pc_download_events',
        'pc_orders', 'pc_order_lines', 'pc_digital_files',
        'pc_cases', 'pc_case_events', 'pc_payment_attempts',
        'pc_payment_events',
    ];

    /**
     * Verificador SQL de un cobro REAL, registrado por el consumidor de
     * checkout/reconciliación. Nunca aceptar un booleano en el navegador.
     * @var callable|null
     */
    private static $creditVerifier = null;

    /**
     * Autoridad R8-61 futura: valida el tipo/estado/finalidad del expediente,
     * su decisión y evidencia histórica; no se inventa una fase de caso.
     * @var callable|null
     */
    private static $caseVerifier = null;

    /** @var callable|null R8-14 futuro: token/sesión/pago/stream preparado. */
    private static $transferVerifier = null;

    public static function registerTransferVerifier(callable $verifier): void
    {
        if (null !== self::$transferVerifier) {
            throw new \LogicException('pc_digital_transfer_verifier_duplicate');
        }
        self::$transferVerifier = $verifier;
    }

    public static function registerCreditVerifier(callable $verifier): void
    {
        if (null !== self::$creditVerifier) {
            throw new \LogicException('pc_digital_credit_verifier_duplicate');
        }
        self::$creditVerifier = $verifier;
    }

    public static function registerCaseVerifier(callable $verifier): void
    {
        if (null !== self::$caseVerifier) {
            throw new \LogicException('pc_digital_case_verifier_duplicate');
        }
        self::$caseVerifier = $verifier;
    }

    public static function table(string $suffix): string
    {
        if (!\in_array($suffix, self::TABLES, true)) {
            throw new Transaction_Failure('pc_digital_right_invariant_invalid');
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        return Schema::tableNames($wpdb)[$suffix];
    }

    /**
     * Únicamente invocable desde una operación financiera ya autenticada
     * y en la MISMA transacción que confirma/acredita el pedido.
     *
     * Precondiciones del caller: pc_idempotency en prepared, pedido y TODAS
     * las líneas digitales prebloqueadas en orden estable antes de adquirir
     * locks de variantes/agenda. No retroceder de stage 50 a stage 30.
     * "CONFIRMADO" y pago verificado
     * no son sinónimos. No modificar la cantidad pactada ni precio/snapshot.
     *
     * @return list<array{right_id:string,line_id:string,file_id:string}>
     */
    public static function grantForOrderWithin(
        Transaction_Context $tx,
        Event_Actor $actor,
        string $operationId,
        string $orderId,
        string $creditEventId
    ): array {
        $operationId = Event_Journal::requirePendingOperation($tx,$operationId);
        $orderId = Domain_Validation::sqlId($orderId);
        $creditEventId = Domain_Validation::sqlId($creditEventId);
        $tx->lockedOrder($orderId);
        $actor->assertCurrent();

        // El pedido puede haber pasado de PENDIENTE_PAGO a CONFIRMADO
        // dentro del MISMO TX después de lockOrder(). Consultar su
        // estado vivo: la fila cacheada del lock refleja el comienzo.
        $currentOrder = $tx->rows(
            'SELECT status FROM %i WHERE id = %s',
            [self::table('pc_orders'),$orderId]
        );
        if (!Module_Gates::allows('digital','write')
            || !Module_Gates::allows('orders','write')
            || null === self::$creditVerifier
            || 1 !== \count($currentOrder)
            || 'CONFIRMADO' !== (string) $currentOrder[0]['status']) {
            throw new Transaction_Failure('pc_digital_right_not_ready');
        }
        $credit = self::creditWithin($tx,$orderId,$creditEventId);
        try {
            if (true !== (self::$creditVerifier)(
                $tx, $actor, $operationId, $orderId, $credit
            )) {
                throw new Transaction_Failure('pc_digital_right_credit_missing');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_digital_right_credit_missing');
        }

        $lines = $tx->rows(
            'SELECT id FROM %i WHERE order_id = %s AND kind = %s '
                . 'ORDER BY id LIMIT 101',
            [self::table('pc_order_lines'),$orderId,'digital']
        );
        if (\count($lines) > 100) {
            throw new Transaction_Failure('pc_digital_right_invariant_invalid');
        }
        if ([] === $lines) {
            return [];
        }
        // La cuota comercial vigente solo se captura AL CREAR el derecho.
        // Ningún PATCH posterior reescribe optional_limit de ventas previas.
        $settings = Settings_Store::readWithin($tx)['settings'];
        $quota = $settings['digital_download_limit'] ?? null;
        if (null !== $quota && (!\is_int($quota) || $quota < 1 || $quota > 100000)) {
            throw new Transaction_Failure('pc_digital_right_invariant_invalid');
        }
        $granted = [];
        foreach ($lines as $row) {
            $lineId = Domain_Validation::sqlId((string) $row['id']);
            // La transición del checkout ya tomó los locks en orden
            // operación -> pedido -> líneas -> recursos. Reutilizarlos
            // evita invertir stage 30/50 al confirmar un cobro.
            $line = $tx->lockedOrderLine($lineId);
            if ((string) $line['order_id'] !== $orderId
                || 'digital' !== (string) $line['kind']
                || !\in_array((string) $line['fulfillment_state'],
                    ['BLOQUEADO','HABILITADO'],true)) {
                throw new Transaction_Failure('pc_digital_right_ineligible');
            }
            $snapshot = $tx->rows(
                'SELECT catalog_snapshot FROM %i WHERE id = %s AND order_id = %s',
                [self::table('pc_order_lines'),$lineId,$orderId]
            );
            if (1 !== \count($snapshot)) {
                throw new Transaction_Failure('pc_digital_right_invariant_invalid');
            }
            $sold = Orders_Domain::catalogSnapshot(
                (string) $snapshot[0]['catalog_snapshot'],'digital'
            );
            // Una variante vendida puede incluir VARIOS slots de archivos.
            // Cada derecho tiene file_id fijo y cuota histórica propia; no
            // se sustituyen por el archivo activo del catálogo al reintentar.
            $soldFiles = $sold['files'] ?? [[
                'slot_key' => $sold['slot_key'],
                'file_id' => $sold['file_id'],
            ]];
            if ([] === $soldFiles || \count($soldFiles) > 100
                || \count($granted) + \count($soldFiles) > 200) {
                throw new Transaction_Failure('pc_digital_right_invariant_invalid');
            }
            $expected = [];
            foreach ($soldFiles as $entry) {
                $fileId = Domain_Validation::sqlId($entry['file_id']);
                if (isset($expected[$fileId])) {
                    throw new Transaction_Failure('pc_digital_right_invariant_invalid');
                }
                $expected[$fileId] = $entry;
            }
            $fileIds = \array_keys($expected);
            $marks = \implode(',',\array_fill(0,\count($fileIds),'%s'));
            $files = $tx->rows(
                'SELECT id, byte_size, LOWER(HEX(content_hash)) AS sha256 '
                    . 'FROM %i WHERE sellable_id = %s AND id IN ('.$marks.')',
                \array_merge([self::table('pc_digital_files'),
                    Domain_Validation::sqlId((string) $line['sellable_id'])],$fileIds)
            );
            if (\count($files) !== \count($expected)) {
                throw new Transaction_Failure('pc_digital_right_invariant_invalid');
            }
            foreach ($files as $file) {
                $fileId = (string) $file['id'];
                $frozen = $expected[$fileId] ?? null;
                if (null === $frozen
                    || !\is_string($file['byte_size'])
                    || !\ctype_digit($file['byte_size'])
                    || '0' === $file['byte_size']
                    || (isset($frozen['sha256'])
                        && $frozen['sha256'] !== $file['sha256'])) {
                    throw new Transaction_Failure('pc_digital_right_invariant_invalid');
                }
            }

            $existing = $tx->rows(
                'SELECT id, file_id, revoked_at FROM %i '
                    . 'WHERE order_line_id = %s ORDER BY file_id FOR UPDATE',
                [self::table('pc_download_rights'),$lineId]
            );
            if ([] !== $existing) {
                if ('HABILITADO' !== (string) $line['fulfillment_state']
                    || \count($existing) !== \count($expected)) {
                    throw new Transaction_Failure('pc_digital_right_conflict');
                }
                $byFile = [];
                foreach ($existing as $right) {
                    $fileId = (string) $right['file_id'];
                    if (!isset($expected[$fileId])
                        || null !== $right['revoked_at']
                        || isset($byFile[$fileId])) {
                        throw new Transaction_Failure('pc_digital_right_conflict');
                    }
                    $byFile[$fileId] = Domain_Validation::sqlId((string) $right['id']);
                }
                // Replays legítimos no duplican derechos ni restauran
                // versiones revocadas, aunque cambien settings o slots.
                foreach ($fileIds as $fileId) {
                    $granted[] = [
                        'right_id'=>$byFile[$fileId],
                        'line_id'=>$lineId,'file_id'=>(string) $fileId,
                    ];
                }
                continue;
            }
            if ('BLOQUEADO' !== (string) $line['fulfillment_state']) {
                throw new Transaction_Failure('pc_digital_right_conflict');
            }
            $limit = null === $quota ? '' : (string) $quota;
            foreach ($fileIds as $fileId) {
                if (1 !== $tx->execute(
                    'INSERT INTO %i (order_line_id,file_id,granted_at,revoked_at,'
                        . 'optional_limit,download_count,row_version) '
                        . 'VALUES (%s,%s,UTC_TIMESTAMP(6),NULL,NULLIF(%s,%s),0,1)',
                    [self::table('pc_download_rights'),$lineId,(string)$fileId,$limit,'']
                )) {
                    throw new Transaction_Failure('pc_digital_right_conflict');
                }
                $last = $tx->rows('SELECT LAST_INSERT_ID() AS id');
                if (1 !== \count($last)) {
                    throw new Transaction_Failure('pc_digital_right_invariant_invalid');
                }
                $rightId = Domain_Validation::sqlId((string) $last[0]['id']);
                Event_Journal::auditWithin(
                    $tx,$actor,$operationId,'download_right',$rightId,
                    'digital_right_granted','verified_credit',
                    null,\hash('sha256',$orderId.':'.$lineId.':'.$fileId.':'.$creditEventId)
                );
                $granted[] = [
                    'right_id'=>$rightId,'line_id'=>$lineId,'file_id'=>(string)$fileId,
                ];
            }
            // G-02 se aplica UNA VEZ a la línea, no una vez por slot.
            $version = $tx->advanceVersion(
                'pc_order_lines',$lineId,(string) $line['row_version']
            );
            if (1 !== $tx->execute(
                'UPDATE %i SET fulfillment_state = %s WHERE id = %s '
                    . 'AND order_id = %s AND kind = %s '
                    . 'AND fulfillment_state = %s AND row_version = %s',
                [self::table('pc_order_lines'),
                    'HABILITADO',$lineId,$orderId,'digital','BLOQUEADO',$version]
            )) {
                throw new Transaction_Failure('pc_digital_right_conflict');
            }
        }
        return $granted;
    }

    /**
     * Lectura segura de elegibilidad de R8-60, también desde guard REST.
     * No es la autorización administrativa por sí sola: la decisión del
     * expediente y sus pruebas son obligatorias y verificadas aparte.
     */
    public static function scopeWithin(
        Transaction_Context $tx,string $rightId,string $caseId,
        string $evidenceRef,bool $lock = false
    ): array {
        $rightId = Domain_Validation::sqlId($rightId);
        $caseId = Domain_Validation::sqlId($caseId);
        if ('' === $evidenceRef || \strlen($evidenceRef) > 500
            || !\mb_check_encoding($evidenceRef,'UTF-8')
            || \preg_match('/[\x00-\x1F\x7F]/',$evidenceRef)
            || \str_contains($evidenceRef,'://')) {
            throw new Transaction_Failure('pc_digital_right_ineligible');
        }
        $rows = $tx->rows(
            'SELECT r.id, r.order_line_id, r.file_id, r.row_version, '
                . 'r.revoked_at, r.download_count, r.optional_limit, '
                . 'l.order_id, l.kind, l.sellable_id, l.fulfillment_state, '
                . 'l.catalog_snapshot, l.row_version AS line_version, '
                . 'o.status AS order_status, o.payment_method, '
                . 'f.id AS owned_file_id '
                . 'FROM %i r INNER JOIN %i l ON l.id = r.order_line_id '
                . 'INNER JOIN %i o ON o.id = l.order_id '
                . 'LEFT JOIN %i f ON f.id = r.file_id AND f.sellable_id = l.sellable_id '
                . 'WHERE r.id = %s' . ($lock ? ' FOR UPDATE' : ''),
            [self::table('pc_download_rights'),self::table('pc_order_lines'),
                self::table('pc_orders'),self::table('pc_digital_files'),$rightId]
        );
        if (1 !== \count($rows)
            || 'digital' !== (string) $rows[0]['kind']
            || null === $rows[0]['owned_file_id']
            || !\in_array((string) $rows[0]['order_status'],
                ['CONFIRMADO','FINALIZADO'],true)) {
            throw new Transaction_Failure('pc_digital_right_not_found');
        }
        $right = $rows[0];
        $sold = Orders_Domain::catalogSnapshot(
            (string) $right['catalog_snapshot'],'digital'
        );
        $soldFiles = $sold['files'] ?? [['file_id'=>$sold['file_id']]];
        $soldIds = \array_column($soldFiles,'file_id');
        if (!\in_array((string) $right['file_id'],$soldIds,true)
            || !\in_array((string) $right['fulfillment_state'],
                ['HABILITADO','REVOCADO'],true)) {
            throw new Transaction_Failure('pc_digital_right_invariant_invalid');
        }
        $cases = $tx->rows(
            'SELECT id, order_id, order_line_id, case_type, case_phase, '
                . 'row_version, resolved_at FROM %i '
                . 'WHERE id = %s AND order_id = %s '
                . 'AND ((order_line_id = %s AND case_type = %s) '
                . 'OR (order_line_id IS NULL AND case_type = %s))'
                . ($lock ? ' FOR UPDATE' : ''),
            [self::table('pc_cases'),$caseId,
                (string) $right['order_id'],(string) $right['order_line_id'],
                'digital_access_dispute','guest_request_refund']
        );
        if (1 !== \count($cases) || null === $cases[0]['resolved_at']) {
            throw new Transaction_Failure('pc_digital_right_case_invalid');
        }
        $decision = $tx->rows(
            'SELECT id, idempotency_id, event_code, decision_code, '
                . 'actor_kind, actor_user_id, evidence_ref, safe_payload_snapshot '
                . 'FROM %i WHERE case_id = %s AND decision_code IS NOT NULL '
                . 'ORDER BY occurred_at DESC,id DESC LIMIT 1'
                . ($lock ? ' FOR UPDATE' : ''),
            [self::table('pc_case_events'),$caseId]
        );
        if (1 !== \count($decision)
            || 'authorize_revocation' !== (string) $decision[0]['decision_code']
            || 'staff' !== (string) $decision[0]['actor_kind']
            || null === $decision[0]['actor_user_id']
            || !\is_string($decision[0]['evidence_ref'])
            || !\hash_equals($decision[0]['evidence_ref'],$evidenceRef)
            || null === self::$caseVerifier) {
            throw new Transaction_Failure('pc_digital_right_case_invalid');
        }
        try {
            if (true !== (self::$caseVerifier)(
                $tx,$cases[0],$decision[0],$right
            )) {
                throw new Transaction_Failure('pc_digital_right_case_invalid');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_digital_right_case_invalid');
        }
        return [
            'right'=>$right,
            'case'=>$cases[0],
            'decision'=>$decision[0],
        ];
    }

    /**
     * Efecto interno de R8-14 FUTURO, no un nuevo endpoint.
     *
     * El consumidor autorizado de streaming debe haber abierto/probado
     * previamente el archivo privado FUERA de esta transacción y acreditado
     * token/sesión/TTL de 24h, pago y derecho del comprador. Solo entonces
     * llama esta operación antes del primer byte, con operación/linea/pedido
     * ya bloqueados en orden E-05 y callback verificador REAL registrado.
     * Si el COMMIT es incierto, jamás transmitir el archivo a ciegas.
     *
     * No contar fallos 401/403/404 ni expiración del token. Una transferencia
     * iniciable puede consumir cuota si el cliente se desconecta después.
     *
     * @param array<string,mixed> $sessionProof Contexto interno sin secreto claro
     * @return array{right_id:string,file_id:string,row_version:string,download_count:int}
     */
    public static function authorizeTransferWithin(
        Transaction_Context $tx,
        Event_Actor $actor,
        string $orderId,
        string $lineId,
        string $rightId,
        string $requestHex,
        array $sessionProof
    ): array {
        $orderId = Domain_Validation::sqlId($orderId);
        $lineId = Domain_Validation::sqlId($lineId);
        $rightId = Domain_Validation::sqlId($rightId);
        if (!\preg_match('/\A[a-f0-9]{32}\z/D',$requestHex)
            || null === self::$transferVerifier
            || !Module_Gates::allows('digital','read')) {
            throw new Transaction_Failure('pc_digital_right_not_ready');
        }
        $actor->assertCurrent();
        $tx->lockedOrder($orderId);
        $lockedLine = $tx->lockedOrderLine($lineId);
        if ((string) $lockedLine['order_id'] !== $orderId
            || 'digital' !== (string) $lockedLine['kind']) {
            throw new Transaction_Failure('pc_digital_right_ineligible');
        }
        $live = $tx->rows(
            'SELECT r.id, r.order_line_id, r.file_id, r.optional_limit, '
                . 'r.download_count, r.row_version, r.revoked_at, '
                . 'l.fulfillment_state, l.catalog_snapshot, '
                . 'o.status AS order_status, '
                . 'f.id AS owned_file_id '
                . 'FROM %i r INNER JOIN %i l ON l.id = r.order_line_id '
                . 'INNER JOIN %i o ON o.id = l.order_id '
                . 'LEFT JOIN %i f ON f.id = r.file_id AND f.sellable_id = l.sellable_id '
                . 'WHERE r.id = %s AND r.order_line_id = %s AND l.order_id = %s FOR UPDATE',
            [self::table('pc_download_rights'),self::table('pc_order_lines'),
                self::table('pc_orders'),self::table('pc_digital_files'),
                $rightId,$lineId,$orderId]
        );
        if (1 !== \count($live)
            || null !== $live[0]['revoked_at']
            || null === $live[0]['owned_file_id']
            || 'HABILITADO' !== (string) $live[0]['fulfillment_state']
            || !\in_array((string) $live[0]['order_status'],['CONFIRMADO','FINALIZADO'],true)) {
            throw new Transaction_Failure('pc_digital_right_ineligible');
        }
        $right = $live[0];
        $snapshot = Orders_Domain::catalogSnapshot(
            (string) $right['catalog_snapshot'],'digital'
        );
        $soldFiles = $snapshot['files'] ?? [['file_id'=>$snapshot['file_id']]];
        if (!\in_array((string) $right['file_id'],
            \array_column($soldFiles,'file_id'),true)) {
            throw new Transaction_Failure('pc_digital_right_invariant_invalid');
        }
        $limit = null === $right['optional_limit'] ? null : self::safeCount(
            (string) $right['optional_limit'],100000,false
        );
        $used = self::safeCount((string) $right['download_count'],4294967295,true);
        if (4294967295 === $used || null !== $limit && $used >= $limit) {
            throw new Transaction_Failure('pc_digital_right_quota_exhausted');
        }

        // Una misma petición opaca nunca puede debitar dos veces. El
        // derecho ya está bloqueado, de modo que dos transferencias con
        // igual identificador se serializan incluso sin índice UNIQUE.
        $duplicate = $tx->rows(
            'SELECT id FROM %i WHERE right_id = %s '
                . 'AND opaque_request_id = UNHEX(%s) LIMIT 1 FOR UPDATE',
            [self::table('pc_download_events'),$rightId,$requestHex]
        );
        if ([] !== $duplicate) {
            throw new Transaction_Failure('pc_digital_right_conflict');
        }

        // No sustituir esta llamada por una bandera de sesión o el número
        // de pedido. El futuro R8-14 debe contrastar HMAC/token/cookie,
        // vigencia absoluta, entidad exacta, pago autoritativo y file handle.
        try {
            if (true !== (self::$transferVerifier)(
                $tx,$actor,$orderId,$lineId,$rightId,$right,$sessionProof
            )) {
                throw new Transaction_Failure('pc_digital_right_ineligible');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_digital_right_ineligible');
        }
        if (1 !== $tx->execute(
            'UPDATE %i SET download_count = download_count + 1, '
                . 'row_version = row_version + 1 '
                . 'WHERE id = %s AND row_version = %s AND revoked_at IS NULL '
                . 'AND download_count < %d '
                . 'AND (optional_limit IS NULL OR download_count < optional_limit)',
            [self::table('pc_download_rights'),$rightId,
                (string) $right['row_version'],4294967295]
        )) {
            throw new Transaction_Failure('pc_digital_right_conflict');
        }
        // No significa "todos los bytes recibidos". El consumidor solo
        // puede iniciar streaming DESPUÉS de COMMIT confirmado.
        if (1 !== $tx->execute(
            'INSERT INTO %i (right_id,file_id,result_code,'
                . 'occurred_at,opaque_request_id) '
                . 'VALUES (%s,%s,%s,UTC_TIMESTAMP(6),UNHEX(%s))',
            [self::table('pc_download_events'),$rightId,
                (string) $right['file_id'],'stream_authorized',$requestHex]
        )) {
            throw new Transaction_Failure('pc_digital_right_invariant_invalid');
        }
        return [
            'right_id'=>$rightId,
            'file_id'=>Domain_Validation::sqlId((string) $right['file_id']),
            'row_version'=>Settings_Store::nextVersion((string) $right['row_version']),
            'download_count'=>$used+1,
        ];
    }

    /** Limites DECIMALES exactos para no usar floats ni overflow. */
    private static function safeCount(string $value,int $max,bool $allowZero): int
    {
        if (!\preg_match('/\A(?:0|[1-9][0-9]*)\z/D',$value)
            || (!$allowZero && '0' === $value)
            || Transaction_Context::compareId($value,(string) $max) > 0) {
            throw new Transaction_Failure('pc_digital_right_invariant_invalid');
        }
        return (int) $value;
    }

    /**
     * Revocar no implica reembolso/cancelación de pedido/estado financiero.
     * El derecho y todos sus tokens se invalidan atómicamente; los eventos,
     * snapshots, binarios e historial no se borran.
     */
    public static function revoke(string $rightId,array $body): array
    {
        Digital_Files::staff();
        if (!Module_Gates::allows('digital','write')) {
            throw new Transaction_Failure('pc_digital_right_not_ready');
        }
        $rightId = Domain_Validation::sqlId($rightId);
        $caseId = Domain_Validation::sqlId($body['case_id']);
        $expected = Domain_Validation::sqlId($body['expected_version']);
        $key = $body['idempotency_key'];
        $reason = $body['legal_reason'];
        $reference = $body['evidence_ref'];
        if (!\is_string($reason) || '' === \trim($reason)
            || !\mb_check_encoding($reason,'UTF-8')
            || \mb_strlen($reason,'UTF-8') > 1000
            || \preg_match('/[\x00-\x1F\x7F]/',$reason)
            || !\is_string($reference)
            || !\is_string($key)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $scope = [
            'actor_kind'=>'staff',
            'actor_ref'=>(string) \get_current_user_id(),
            'resource_kind'=>'digital_right',
            'resource_id'=>$rightId,
            'purpose'=>'digital_revoke',
        ];
        $payload = [
            'right_id'=>$rightId,'case_id'=>$caseId,
            'expected_version'=>$expected,
            'legal_reason_sha256'=>\hash('sha256',$reason),
            'evidence_ref_sha256'=>\hash('sha256',$reference),
        ];
        $actor = Event_Actor::staff(['perucommerce_digital_files_manage']);
        return Transaction::run(static function (Transaction_Context $tx) use (
            $rightId,$caseId,$expected,$reference,$scope,$key,$payload,$actor
        ): array {
            $claim = Idempotency::claimWithin(
                $tx,$scope,'digital_revoke',$key,$payload
            );
            if ('replay' === $claim['status']) {
                if ('download_right' !== $claim['entity_type']
                    || (string) $claim['entity_id'] !== $rightId) {
                    throw new Transaction_Failure('pc_digital_right_conflict');
                }
                $approved = self::scopeWithin($tx,$rightId,$caseId,$reference);
                $right = $approved['right'];
                $after = Settings_Store::nextVersion($expected);
                if (null === $right['revoked_at']
                    || (string) $right['row_version'] !== $after) {
                    throw new Transaction_Failure('pc_digital_right_conflict');
                }
                return [
                    'operation_id'=>$claim['operation_id'],
                    'result'=>'revoked',
                    'row_version'=>$after,
                ];
            }
            if ('new' !== $claim['status']) {
                throw new Transaction_Failure('pc_digital_right_conflict');
            }

            // Prelectura establece parent para el orden de locks;
            // luego revalidamos TODOS los hechos dentro del mismo TX.
            $pre = self::scopeWithin($tx,$rightId,$caseId,$reference);
            $info = $pre['right'];
            $orderId = Domain_Validation::sqlId((string) $info['order_id']);
            $lineId = Domain_Validation::sqlId((string) $info['order_line_id']);

            $tx->lockOrder($orderId);
            $lockedLine = $tx->lockOrderLine($orderId,$lineId);
            if (null === $pre['case']['order_line_id']
                && 'guest_request_refund' === $pre['case']['case_type']) {
                $tx->lockCaseForOrder($orderId,$caseId);
            } else {
                $tx->lockCaseForLine($orderId,$lineId,$caseId);
            }
            $current = $tx->rows(
                'SELECT id, row_version, revoked_at FROM %i '
                    . 'WHERE id = %s AND order_line_id = %s FOR UPDATE',
                [self::table('pc_download_rights'),$rightId,$lineId]
            );
            if (1 !== \count($current)
                || null !== $current[0]['revoked_at']
                || (string) $current[0]['row_version'] !== $expected
                || 'HABILITADO' !== (string) $lockedLine['fulfillment_state']) {
                throw new Transaction_Failure('pc_digital_right_conflict');
            }
            // SELECT FOR UPDATE entrega la versión ACTUAL aun cuando la
            // lectura inicial de pertenencia abrió una vista REPEATABLE READ.
            $approved = self::scopeWithin($tx,$rightId,$caseId,$reference,true);
            if ($approved['right']['revoked_at'] !== null
                || (string) $approved['right']['row_version'] !== $expected) {
                throw new Transaction_Failure('pc_digital_right_conflict');
            }

            if (1 !== $tx->execute(
                'UPDATE %i SET revoked_at = UTC_TIMESTAMP(6), '
                    . 'row_version = row_version + 1 '
                    . 'WHERE id = %s AND order_line_id = %s '
                    . 'AND row_version = %s AND revoked_at IS NULL',
                [self::table('pc_download_rights'),$rightId,$lineId,$expected]
            )) {
                throw new Transaction_Failure('pc_digital_right_conflict');
            }
            // Token vigente, expirado o no usado: marcar revocado sin
            // borrar el hash ni permitir que el futuro R8-13 lo canjee.
            $tx->execute(
                'UPDATE %i SET revoked_at = UTC_TIMESTAMP(6) '
                    . 'WHERE right_id = %s AND revoked_at IS NULL',
                [self::table('pc_download_tokens'),$rightId]
            );

            $others = $tx->rows(
                'SELECT COUNT(*) AS active_count FROM %i '
                    . 'WHERE order_line_id = %s AND revoked_at IS NULL',
                [self::table('pc_download_rights'),$lineId]
            );
            if (1 !== \count($others)
                || !\ctype_digit((string) $others[0]['active_count'])) {
                throw new Transaction_Failure('pc_digital_right_invariant_invalid');
            }
            // Si la variante tiene otros slots vendidos con derechos aún
            // activos, conservar HABILITADO; G-06 solo cuando no queda
            // ningún derecho de descarga de esta línea.
            if ('0' === (string) $others[0]['active_count']) {
                $version = $tx->advanceVersion(
                    'pc_order_lines',$lineId,
                    (string) $lockedLine['row_version']
                );
                if (1 !== $tx->execute(
                    'UPDATE %i SET fulfillment_state = %s '
                        . 'WHERE id = %s AND order_id = %s '
                        . 'AND kind = %s AND fulfillment_state = %s '
                        . 'AND row_version = %s',
                    [self::table('pc_order_lines'),
                        'REVOCADO',$lineId,$orderId,'digital','HABILITADO',$version]
                )) {
                    throw new Transaction_Failure('pc_digital_right_conflict');
                }
            }
            $after = Settings_Store::nextVersion($expected);
            // Evento separado de la resolución R8-61: decisión previa no
            // produce efectos ni reembolso, este evento sí aplica derecho.
            if (1 !== $tx->execute(
                'INSERT INTO %i (case_id,idempotency_id,event_code,'
                    . 'decision_code,actor_kind,actor_user_id,evidence_ref,'
                    . 'safe_payload_snapshot,occurred_at) '
                    . 'VALUES (%s,%s,%s,NULL,%s,%s,%s,%s,UTC_TIMESTAMP(6))',
                [self::table('pc_case_events'),$caseId,
                    $claim['operation_id'],'digital_right_revoked','staff',
                    (string) \get_current_user_id(),$reference,
                    \json_encode(['right_id'=>$rightId],\JSON_THROW_ON_ERROR)]
            )) {
                throw new Transaction_Failure('pc_digital_right_invariant_invalid');
            }
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],
                'download_right',$rightId,
                'digital_right_revoked','case_authorized',
                \hash('sha256',$expected),
                \hash('sha256',$after.':'.$caseId)
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'download_right',$rightId,200
            );
            return [
                'operation_id'=>$claim['operation_id'],
                'result'=>'revoked',
                'row_version'=>$after,
            ];
        },true);
    }

    /**
     * Credencial de pago: el asiento financiero debe pertenecer al pedido,
     * coincidir en proveedor e importe positivo del único cobro inicial.
     * Esta lectura NO sustituye la verificación autoritativa externa real.
     */
    private static function creditWithin(
        Transaction_Context $tx,string $orderId,string $eventId
    ): array {
        $orders = $tx->rows(
            'SELECT id, payment_method, total_cents, status FROM %i WHERE id = %s',
            [self::table('pc_orders'),$orderId]
        );
        $events = $tx->rows(
            'SELECT id, order_id, kind, provider, amount_cents, '
                . 'payment_attempt_id FROM %i WHERE id = %s AND order_id = %s',
            [self::table('pc_payment_events'),$eventId,$orderId]
        );
        if (1 !== \count($orders) || 1 !== \count($events)
            || 'CONFIRMADO' !== (string) $orders[0]['status']
            || $events[0]['provider'] !== $orders[0]['payment_method']) {
            throw new Transaction_Failure('pc_digital_right_credit_missing');
        }
        try {
            $amount = Money::fromSignedLedger((string) $events[0]['amount_cents']);
            $total = Money::fromApi((string) $orders[0]['total_cents']);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_digital_right_credit_missing');
        }
        if ($amount < $total || $total <= 0) {
            throw new Transaction_Failure('pc_digital_right_credit_missing');
        }
        if ('mercado_pago' === $events[0]['provider']) {
            if (null === $events[0]['payment_attempt_id']) {
                throw new Transaction_Failure('pc_digital_right_credit_missing');
            }
            $attempt = $tx->rows(
                'SELECT state FROM %i WHERE id = %s AND order_id = %s '
                    . 'AND provider = %s',
                [self::table('pc_payment_attempts'),
                    (string) $events[0]['payment_attempt_id'],
                    $orderId,'mercado_pago']
            );
            if (1 !== \count($attempt)
                || 'APROBADO' !== (string) $attempt[0]['state']) {
                throw new Transaction_Failure('pc_digital_right_credit_missing');
            }
        } elseif ('cash' !== $events[0]['provider']) {
            throw new Transaction_Failure('pc_digital_right_credit_missing');
        }
        return $events[0];
    }
}
