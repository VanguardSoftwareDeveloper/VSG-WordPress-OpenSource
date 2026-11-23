<?php
/**
 * IMP-30 — expedientes MP en pc_cases, sin tabla adicional.
 * Un expediente por checkout_mp original; el estado es técnico y no
 * sustituye el estado del pedido, del intento ni de dinero.
 */
declare(strict_types=1);
namespace PeruCommerce;
if (!\defined('ABSPATH')) { exit; }

final class MP_Reconciliation_Cases
{
    public const KIND = 'mp_reconciliation';
    public const OPEN = 'open_review';
    public const CLOSED_NO_CHARGE = 'closed_no_charge';
    public const CLOSED_REASSIGNED = 'closed_reassigned';

    /** R8-29; A solamente, sin exponer importes ni credenciales MP. */
    public static function listing(array $query): array
    {
        self::administrator();
        $window = Rest_Pagination::window($query);
        $phase = $query['state'] ?? null;
        if (null !== $phase && !\in_array($phase, [
            self::OPEN, self::CLOSED_NO_CHARGE, self::CLOSED_REASSIGNED,
        ], true)) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'state');
        }
        return Transaction::run(static function(Transaction_Context $tx) use(
            $window, $phase
        ): array {
            $args = [Orders_Domain::table('pc_cases'), self::KIND];
            $sql = 'SELECT c.id, c.order_id, c.source_operation_id, '
                . 'c.reason_code, c.row_version, c.opened_at, '
                . '(SELECT a.provider_order_id FROM %i a WHERE '
                . 'a.order_id = c.order_id AND a.provider = %s '
                . 'ORDER BY a.id DESC LIMIT 1) AS order_ref, '
                . '(SELECT COUNT(*) FROM %i p WHERE p.order_id = c.order_id '
                . 'AND p.provider = %s AND p.kind = %s '
                . 'AND p.amount_cents > 0) AS credited '
                . 'FROM %i c WHERE c.case_type = %s';
            // El placeholder %i de los subqueries precede al de pc_cases.
            $args = [
                Orders_Domain::table('pc_payment_attempts'), 'mercado_pago',
                Orders_Domain::table('pc_payment_events'), 'mercado_pago',
                'initial_charge', Orders_Domain::table('pc_cases'), self::KIND,
            ];
            if (null !== $phase) {
                $sql .= ' AND c.case_phase = %s';
                $args[] = $phase;
            }
            $sql .= ' ORDER BY c.opened_at DESC,c.id DESC LIMIT %d OFFSET %d '
                . 'LOCK IN SHARE MODE';
            $args[] = $window['fetch'];
            $args[] = $window['offset'];
            $rows = $tx->rows($sql, $args);
            $items = [];
            foreach ($rows as $row) {
                $id = Domain_Validation::sqlId((string)$row['id']);
                $source = null === $row['source_operation_id'] ? null
                    : Domain_Validation::sqlId((string)$row['source_operation_id']);
                $orderId = null === $row['order_id'] ? null
                    : Domain_Validation::sqlId((string)$row['order_id']);
                if (null === $source && null === $orderId) {
                    throw new Transaction_Failure('pc_mp_case_invalid');
                }
                $item = [
                    'case_id'=>$id,
                    'reason_code'=>(string)$row['reason_code'],
                    'created_at'=>Business_Time::toApi(
                        Business_Time::fromDatabase((string)$row['opened_at'])
                    ),
                    'has_verified_payment'=>(int)$row['credited'] === 1,
                    'row_version'=>Domain_Validation::sqlId(
                        (string)$row['row_version']
                    ),
                ];
                if (null !== $orderId) { $item['order_id'] = $orderId; }
                if (null !== $source && null === $orderId) {
                    $item['source_operation_id'] = $source;
                }
                if (\is_string($row['order_ref']??null)
                    && \preg_match('/\AORD[A-Z0-9]{16,61}\z/D',$row['order_ref'])) {
                    $item['provider_order_ref'] = $row['order_ref'];
                }
                $items[] = $item;
            }
            return Rest_Pagination::envelope($items,$window);
        });
    }

    /**
     * Apertura atómica por un worker que ya posee root crediticio en
     * PREPARED, actor system autenticado, order bloqueado (si existe),
     * y GET/estado de origen ya comprobado fuera de SQL.
     *
     * La UQ(case_type,source_operation_id) serializa también reenvíos.
     */
    public static function openWithin(
        Transaction_Context $tx, Event_Actor $actor,
        string $creditOp, string $sourceOp, ?string $orderId,
        string $reason, ?string $providerOrderId
    ): string {
        Event_Journal::requirePendingOperation($tx,$creditOp);
        $actor->assertCurrent();
        if ('system' !== $actor->kind()
            || !\preg_match('/\Amp_[a-z_]{4,30}\z/D',$reason)
            || (null !== $providerOrderId
                && !\preg_match('/\AORD[A-Z0-9]{16,61}\z/D',$providerOrderId))) {
            throw new Transaction_Failure('pc_mp_case_invalid');
        }
        $sourceOp=Domain_Validation::sqlId($sourceOp);
        if (null !== $orderId) {
            $orderId=Domain_Validation::sqlId($orderId);
            $tx->lockedOrder($orderId);
        }
        $table=Orders_Domain::table('pc_cases');
        $rows=$tx->rows(
            'SELECT id,order_id,case_phase,reason_code FROM %i '
                . 'WHERE case_type = %s AND source_operation_id = %s FOR UPDATE',
            [$table,self::KIND,$sourceOp]
        );
        if ([] !== $rows) {
            if (1 !== \count($rows)
                || !\in_array($rows[0]['case_phase'],[self::OPEN],true)
                || (null === $orderId && null !== $rows[0]['order_id'])
                || (null !== $orderId
                    && null !== $rows[0]['order_id']
                    && (string)$rows[0]['order_id'] !== $orderId)) {
                throw new Transaction_Failure('pc_mp_case_conflict');
            }
            $caseId=Domain_Validation::sqlId((string)$rows[0]['id']);
            if (null!==$orderId
                && (null===$rows[0]['order_id']
                    || $rows[0]['reason_code']!==$reason)) {
                // Un caso de operación ambigua evoluciona a "pago
                // verificado sin recursos" solo tras prueba GET firmada.
                if (1!==$tx->execute(
                    'UPDATE %i SET order_id=%s,reason_code=%s,'
                        . 'row_version=row_version+1 WHERE id=%s '
                        . 'AND case_type=%s AND case_phase=%s',
                    [$table,$orderId,$reason,$caseId,self::KIND,self::OPEN]
                )) {
                    throw new Transaction_Failure('pc_mp_case_conflict');
                }
                Event_Journal::auditWithin(
                    $tx,$actor,$creditOp,'case',$caseId,
                    'mp_reconciliation_credit_found',$reason
                );
            }
            return $caseId;
        }
        $snapshot=\json_encode([
            'source'=>'orders_get',
            'provider_order_ref'=>$providerOrderId,
        ],\JSON_THROW_ON_ERROR);
        if (1 !== $tx->execute(
            'INSERT INTO %i (order_id,order_line_id,case_type,source_operation_id,'
                . 'case_phase,reason_code,created_by,assigned_to,evidence_snapshot,'
                . 'opened_at,resolved_at,row_version) '
                . 'VALUES (NULLIF(%s,%s),NULL,%s,%s,%s,%s,NULL,NULL,%s,'
                . 'UTC_TIMESTAMP(6),NULL,1)',
            [$table,$orderId??'','',self::KIND,$sourceOp,
                self::OPEN,$reason,$snapshot]
        )) {
            throw new Transaction_Failure('pc_mp_case_conflict');
        }
        $new=$tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1!==\count($new)) {
            throw new Transaction_Failure('pc_mp_case_conflict');
        }
        $caseId=Domain_Validation::sqlId((string)$new[0]['id']);
        // El caso queda visible por R8-29; no equivale a pago acreditado.
        if (null === $orderId) {
            // Operación técnica prepedido: el evento se persiste directamente
            // sin crear pedido ficticio ni cambiar fase de root original.
            if (1!==$tx->execute(
                'INSERT INTO %i (case_id,idempotency_id,event_code,actor_kind,'
                    . 'occurred_at) VALUES (%s,%s,%s,%s,UTC_TIMESTAMP(6))',
                [Orders_Domain::table('pc_case_events'),$caseId,
                    $creditOp,'mp_reconciliation_opened','system']
            )) {
                throw new Transaction_Failure('pc_mp_case_conflict');
            }
        } else {
            $tx->lockCaseForOrder($orderId,$caseId);
            Event_Journal::caseWithin(
                $tx,$actor,$creditOp,$caseId,
                'mp_reconciliation_opened',null,$reason
            );
        }
        Event_Journal::auditWithin(
            $tx,$actor,$creditOp,'case',$caseId,
            'mp_reconciliation_detected',$reason
        );
        return $caseId;
    }

    /** Abrir una causa técnica sin pago para una raíz estancada. */
    public static function detectStale(): int
    {
        if (!Module_Gates::allows('reconciliation','write')) {
            return 0;
        }
        $roots=Transaction::run(static fn (Transaction_Context $tx): array =>
            $tx->rows(
                'SELECT i.id FROM %i i WHERE i.operation_type = %s '
                    . 'AND i.technical_phase IN (%s,%s) '
                    . 'AND i.updated_at < DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE) '
                    . 'AND NOT EXISTS (SELECT 1 FROM %i a '
                    . 'WHERE a.provider = %s '
                    . 'AND a.provider_account_key = i.provider_account_key '
                    . 'AND a.environment = i.provider_environment '
                    . 'AND a.mp_idempotency_key = i.provider_idempotency_key) '
                    . 'AND NOT EXISTS (SELECT 1 FROM %i c '
                    . 'WHERE c.case_type = %s AND c.source_operation_id = i.id) '
                    . 'ORDER BY i.updated_at,i.id LIMIT 4',
                [Orders_Domain::table('pc_idempotency'),'checkout_mp',
                    'external_pending','reconcile',
                    Orders_Domain::table('pc_payment_attempts'),'mercado_pago',
                    Orders_Domain::table('pc_cases'),self::KIND]
            )
        );
        $count=0;
        foreach ($roots as $candidate) {
            $root=Domain_Validation::sqlId((string)$candidate['id']);
            try {
                $created=Transaction::run(
                    static function(Transaction_Context $tx) use ($root): bool {
                        $tx->lockOperation($root);
                        $data=$tx->rows(
                            'SELECT entity_type,entity_id,technical_phase,'
                                . 'provider_idempotency_key FROM %i WHERE id = %s '
                                . 'AND operation_type = %s',
                            [Orders_Domain::table('pc_idempotency'),
                                $root,'checkout_mp']
                        );
                        if (1!==\count($data)
                            || !\in_array($data[0]['technical_phase'],
                                ['external_pending','reconcile'],true)
                            || !\is_string($data[0]['provider_idempotency_key'])) {
                            return false;
                        }
                        // Otra petición pudo persistir el intento entre
                        // el listado inicial y este lock exclusivo.
                        $already=$tx->rows(
                            'SELECT a.id FROM %i a INNER JOIN %i i '
                                . 'ON a.provider_account_key=i.provider_account_key '
                                . 'AND a.environment=i.provider_environment '
                                . 'AND a.mp_idempotency_key=i.provider_idempotency_key '
                                . 'WHERE i.id=%s AND a.provider=%s LIMIT 1',
                            [Orders_Domain::table('pc_payment_attempts'),
                                Orders_Domain::table('pc_idempotency'),
                                $root,'mercado_pago']
                        );
                        if ([]!==$already) { return false; }
                        $orderId='order'===$data[0]['entity_type']
                            && null!==$data[0]['entity_id']
                                ? Domain_Validation::sqlId(
                                    (string)$data[0]['entity_id']
                                ) : null;
                        if (null!==$orderId) { $tx->lockOrder($orderId); }
                        return null!==self::detectWithin($tx,$root,$orderId);
                    },true
                );
                if ($created) { ++$count; }
            } catch (\Throwable $ignored) {
                // Incoherencia o COMMIT ambiguo permanece en raíz y será
                // revisada en siguiente ciclo; nunca crear otro cobro.
            }
        }
        return $count;
    }

    /**
     * Detección no financiera con root original (no actor crediticio);
     * escribir audit SYSTEM directamente porque root está reconcile, no
     * prepared. Sin dinero, attempt ni evento comercial.
     */
    private static function detectWithin(
        Transaction_Context $tx,string $root,?string $orderId
    ): ?string {
        $rows=$tx->rows(
            'SELECT id FROM %i WHERE case_type = %s '
                . 'AND source_operation_id = %s LIMIT 1 FOR UPDATE',
            [Orders_Domain::table('pc_cases'),self::KIND,$root]
        );
        if ([]!==$rows) { return null; }
        $caseType=self::KIND;
        $affected=$tx->execute(
            'INSERT INTO %i (order_id,order_line_id,case_type,source_operation_id,'
                . 'case_phase,reason_code,created_by,assigned_to,evidence_snapshot,'
                . 'opened_at,resolved_at,row_version) VALUES '
                . '(NULLIF(%s,%s),NULL,%s,%s,%s,%s,NULL,NULL,NULL,'
                . 'UTC_TIMESTAMP(6),NULL,1) '
                . 'ON DUPLICATE KEY UPDATE id=id',
            [Orders_Domain::table('pc_cases'),$orderId??'','',
                $caseType,$root,self::OPEN,'mp_attempt_missing']
        );
        if (0===$affected) { return null; }
        if (1!==$affected) { throw new Transaction_Failure('pc_mp_case_conflict'); }
        $found=$tx->rows(
            'SELECT id FROM %i WHERE case_type = %s '
                . 'AND source_operation_id = %s',
            [Orders_Domain::table('pc_cases'),$caseType,$root]
        );
        if (1!==\count($found)) { return null; }
        $caseId=Domain_Validation::sqlId((string)$found[0]['id']);
        // Proveniencia técnica auditada; no finanzas.
        if (1!==$tx->execute(
            'INSERT INTO %i (entity_type,entity_id,operation_code,actor_kind,'
                . 'request_id,occurred_at) VALUES (%s,%s,%s,%s,UNHEX(%s),UTC_TIMESTAMP(6))',
            [Orders_Domain::table('pc_audit_log'),'case',$caseId,
                'mp_reconciliation_detected','system',\bin2hex(\random_bytes(16))]
        )) {
            throw new Transaction_Failure('pc_mp_case_conflict');
        }
        return $caseId;
    }

    public static function administrator(): void
    {
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/access-roles.php';
        if ('A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_reconciliation_manage')
            || (\function_exists('is_network_admin')&&\is_network_admin())) {
            throw new Rest_Failure('pc_access_denied',403);
        }
    }
}
