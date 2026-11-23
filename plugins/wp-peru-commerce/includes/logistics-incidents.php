<?php
/**
 * IMP-44 — R8-55/56/57: expediente logístico posterior al despacho.
 *
 * Apertura, propuestas e investigación/decisión son exclusivamente hechos
 * del expediente. No se cambia el estado E-01, no se crea movimiento de
 * inventario, ajuste comercial, reembolso ni prueba ficticia de entrega.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Logistics_Incidents
{
    private const TYPE = 'logistics_non_delivery';
    private const OPEN = 'open_review';
    private const REVIEW = 'in_review';
    private const CLOSED = 'resolved';
    private const OPEN_OP = 'logistics_case_open';
    private const PROPOSE_OP = 'logistics_case_propose';
    private const RESOLVE_OP = 'logistics_case_resolve';
    private const PROPOSALS = [
        'delivery_verified', 'non_delivery_verified', 'continue_investigation',
    ];
    private const DECISIONS = ['delivery_verified', 'non_delivery_verified'];

    public static function guardOpen(array $ctx, ?string $role): array
    {
        if (!self::ready($role, false)) { return []; }
        try {
            $orderId = Domain_Validation::sqlId($ctx['path']['id'] ?? '');
            $lineId = Domain_Validation::sqlId($ctx['path']['line_id'] ?? '');
            self::input($ctx['body'] ?? null, self::OPEN_OP);
            $valid = Transaction::run(static function (Transaction_Context $tx)
                use ($orderId, $lineId): bool {
                $rows = $tx->rows(
                    'SELECT l.kind,l.fulfillment_state,o.status FROM %i l '
                        . 'INNER JOIN %i o ON o.id=l.order_id '
                        . 'WHERE l.id=%s AND l.order_id=%s LIMIT 2',
                    [Orders_Domain::table('pc_order_lines'),
                        Orders_Domain::table('pc_orders'), $lineId, $orderId]
                );
                return 1 === \count($rows)
                    && 'physical' === $rows[0]['kind']
                    && \in_array((string)$rows[0]['fulfillment_state'],
                        Orders_Domain::LINE_STATES['physical'], true)
                    && \in_array((string)$rows[0]['status'],
                        Orders_Domain::STATES, true);
            });
            return $valid ? self::proof() : [];
        } catch (\Throwable $ignored) { return []; }
    }

    public static function guardPropose(array $ctx, ?string $role): array
    {
        return self::guardCase($ctx, $role, self::PROPOSE_OP);
    }

    public static function guardResolve(array $ctx, ?string $role): array
    {
        return self::guardCase($ctx, $role, self::RESOLVE_OP);
    }

    private static function guardCase(array $ctx, ?string $role, string $action): array
    {
        if (!self::ready($role, self::RESOLVE_OP === $action)) { return []; }
        try {
            $caseId = Domain_Validation::sqlId($ctx['path']['id'] ?? '');
            self::input($ctx['body'] ?? null, $action);
            $valid = Transaction::run(static function (Transaction_Context $tx)
                use ($caseId): bool {
                $rows = $tx->rows(
                    'SELECT c.id,l.kind,o.status FROM %i c '
                        . 'INNER JOIN %i l ON l.id=c.order_line_id '
                        . 'AND l.order_id=c.order_id '
                        . 'INNER JOIN %i o ON o.id=c.order_id '
                        . 'WHERE c.id=%s AND c.case_type=%s '
                        . 'AND c.source_operation_id IS NULL LIMIT 2',
                    [Orders_Domain::table('pc_cases'),
                        Orders_Domain::table('pc_order_lines'),
                        Orders_Domain::table('pc_orders'), $caseId, self::TYPE]
                );
                return 1 === \count($rows)
                    && 'physical' === $rows[0]['kind']
                    && \in_array((string)$rows[0]['status'],
                        Orders_Domain::STATES, true);
            });
            return $valid ? self::proof() : [];
        } catch (\Throwable $ignored) { return []; }
    }

    /** @return array{status:int,body:array<string,string>} */
    public static function open(string $orderId, string $lineId, array $body): array
    {
        self::requireStaff(false);
        $orderId = Domain_Validation::sqlId($orderId);
        $lineId = Domain_Validation::sqlId($lineId);
        $body = self::input($body, self::OPEN_OP);
        $scope = self::scope('order_line', $lineId, self::OPEN_OP);
        $payload = \array_merge(['order_id'=>$orderId,'line_id'=>$lineId], $body);
        $actor = Event_Actor::staff(['perucommerce_orders_fulfill']);

        return Transaction::run(static function (Transaction_Context $tx) use (
            $orderId,$lineId,$body,$scope,$payload,$actor
        ): array {
            $claim = Idempotency::claimWithin(
                $tx,$scope,self::OPEN_OP,$body['idempotency_key'],$payload
            );
            self::claimed($claim);
            $order = $tx->lockOrder($orderId);
            $line = $tx->lockOrderLine($orderId,$lineId);
            if ('physical' !== (string)$line['kind']) {
                throw new Transaction_Failure('pc_logistics_denied');
            }
            if ('replay' === $claim['status']) {
                return ['status'=>201,'body'=>self::replayWithin(
                    $tx,$claim,self::OPEN_OP,201
                )];
            }
            if ('CONFIRMADO' !== (string)$order['status']
                || 'DESPACHADO' !== (string)$line['fulfillment_state']
                || $body['expected_version'] !== (string)$line['row_version']
                || !self::deliveryWithin($tx,$orderId)) {
                throw new Transaction_Failure('pc_logistics_conflict');
            }
            $existing = $tx->rows(
                'SELECT id FROM %i WHERE order_id=%s AND order_line_id=%s '
                    . 'AND case_type=%s AND case_phase IN (%s,%s) LIMIT 2',
                [Orders_Domain::table('pc_cases'),$orderId,$lineId,
                    self::TYPE,self::OPEN,self::REVIEW]
            );
            if ([] !== $existing) {
                throw new Transaction_Failure('pc_logistics_already_open');
            }
            // Original de apertura solo en expediente A; nunca en auditoría.
            $snapshot = self::json([
                'purpose'=>self::TYPE,'reason'=>$body['reason'],
                'evidence_ref'=>$body['evidence_ref'],
                'order_id'=>$orderId,'line_id'=>$lineId,
                'fulfillment_state_at_open'=>'DESPACHADO',
            ]);
            if (1 !== $tx->execute(
                'INSERT INTO %i (order_id,order_line_id,case_type,'
                    . 'source_operation_id,case_phase,reason_code,created_by,'
                    . 'assigned_to,evidence_snapshot,opened_at,resolved_at,'
                    . 'row_version) VALUES (%s,%s,%s,NULL,%s,%s,%s,NULL,%s,'
                    . 'UTC_TIMESTAMP(6),NULL,1)',
                [Orders_Domain::table('pc_cases'),$orderId,$lineId,self::TYPE,
                    self::OPEN,self::TYPE,(string)\get_current_user_id(),$snapshot]
            )) {
                throw new Transaction_Failure('pc_logistics_invalid');
            }
            $caseId = self::lastId($tx);
            $tx->lockCaseForLine($orderId,$lineId,$caseId);
            self::eventWithin($tx,$actor,$claim['operation_id'],$caseId,
                'logistics_case_opened',null,$body['evidence_ref'],[
                    'reason'=>$body['reason'],'case_phase'=>self::OPEN,
                    'row_version'=>'1',
                ]);
            Queue_Outbox::essentialWithin(
                $tx,$actor,$claim['operation_id'],$orderId,
                'logistics_incident_opened',$caseId
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'case',$caseId,201
            );
            return ['status'=>201,'body'=>self::result(
                $caseId,$claim['operation_id'],self::OPEN,'1'
            )];
        }, true);
    }

    /** @return array{status:int,body:array<string,string>} */
    public static function propose(string $caseId, array $body): array
    {
        return self::caseAction($caseId,$body,self::PROPOSE_OP);
    }

    /** @return array{status:int,body:array<string,string>} */
    public static function resolve(string $caseId, array $body): array
    {
        return self::caseAction($caseId,$body,self::RESOLVE_OP);
    }

    private static function caseAction(
        string $caseId,array $body,string $action
    ): array {
        $resolving = self::RESOLVE_OP === $action;
        self::requireStaff($resolving);
        $caseId = Domain_Validation::sqlId($caseId);
        $body = self::input($body,$action);
        $scope = self::scope('case',$caseId,$action);
        $caps = ['perucommerce_orders_fulfill'];
        if ($resolving) { $caps[] = 'perucommerce_orders_financial_adjust'; }
        $actor = Event_Actor::staff($caps);
        return Transaction::run(static function (Transaction_Context $tx) use (
            $caseId,$body,$action,$scope,$actor,$resolving
        ): array {
            $claim = Idempotency::claimWithin(
                $tx,$scope,$action,$body['idempotency_key'],$body
            );
            self::claimed($claim);
            // Localizar la raíz y bloquear SIEMPRE pedido -> línea -> caso.
            // La primera consulta no da derecho a operar sobre ese recurso.
            $source = self::caseScopeWithin($tx,$caseId);
            $orderId = $source['order_id'];
            $lineId = $source['order_line_id'];
            $order = $tx->lockOrder($orderId);
            $line = $tx->lockOrderLine($orderId,$lineId);
            $case = $tx->lockCaseForLine($orderId,$lineId,$caseId);
            if (self::TYPE !== (string)$case['case_type']
                || 'physical' !== (string)$line['kind']
                || null !== $source['source_operation_id']) {
                throw new Transaction_Failure('pc_logistics_denied');
            }
            if ('replay' === $claim['status']) {
                return ['status'=>200,'body'=>self::replayWithin(
                    $tx,$claim,$action,200
                )];
            }
            // Una entrega real registrada por R8-22 no debe dejar
            // bloqueada una incidencia anterior: R8-57 sólo cierra el
            // expediente y nunca marca ENTREGADO por su cuenta.
            if (!\in_array((string)$order['status'],['CONFIRMADO','FINALIZADO'],true)
                || !\in_array((string)$line['fulfillment_state'],
                    ['DESPACHADO','ENTREGADO'],true)
                || !self::deliveryWithin($tx,$orderId)
                || $body['expected_version'] !== (string)$case['row_version']
                || !\in_array((string)$case['case_phase'],
                    [self::OPEN,self::REVIEW],true)) {
                throw new Transaction_Failure('pc_logistics_conflict');
            }
            self::requireOriginWithin($tx,$caseId);
            $value = $resolving ? $body['decision'] : $body['resolution_proposal'];
            // Una línea YA recibida no puede recibir una nueva conclusión
            // documental de «no entrega». La evidencia se reevalúa sin
            // tocar su estado comercial histórico.
            if ('ENTREGADO' === (string)$line['fulfillment_state']
                && 'non_delivery_verified' === $value) {
                throw new Transaction_Failure('pc_logistics_conflict');
            }
            if ($resolving) {
                if (self::REVIEW !== (string)$case['case_phase']) {
                    throw new Transaction_Failure('pc_logistics_conflict');
                }
                // Una decisión terminal debe coincidir con la ÚLTIMA
                // propuesta documentada, no con cualquier propuesta antigua.
                $last = $tx->rows(
                    'SELECT decision_code,safe_payload_snapshot FROM %i WHERE case_id=%s '
                        . 'AND event_code=%s ORDER BY occurred_at DESC,id DESC LIMIT 1',
                    [Orders_Domain::table('pc_case_events'),$caseId,
                        'logistics_case_proposed']
                );
                if (1 !== \count($last)
                    || $last[0]['decision_code'] !== $value
                    || !\is_string($last[0]['safe_payload_snapshot'])) {
                    throw new Transaction_Failure('pc_logistics_conflict');
                }
                $originalProposal=Orders_Domain::decode(
                    (string)$last[0]['safe_payload_snapshot'],10000
                );
                // Si R8-22 cambió la versión de la línea entre la
                // propuesta y esta decisión, exigir nueva investigación.
                if (($originalProposal['line_row_version'] ?? null)
                    !== (string)$line['row_version']) {
                    throw new Transaction_Failure('pc_logistics_conflict');
                }
            }
            $phase = $resolving ? self::CLOSED : self::REVIEW;
            $next = $tx->advanceVersion(
                'pc_cases',$caseId,$body['expected_version']
            );
            $changed = $resolving
                ? $tx->execute(
                    'UPDATE %i SET case_phase=%s,resolved_at=UTC_TIMESTAMP(6) '
                        . 'WHERE id=%s AND order_id=%s AND case_phase=%s '
                        . 'AND row_version=%s AND resolved_at IS NULL',
                    [Orders_Domain::table('pc_cases'),$phase,$caseId,
                        $orderId,$case['case_phase'],$next]
                )
                : $tx->execute(
                    'UPDATE %i SET case_phase=%s '
                        . 'WHERE id=%s AND order_id=%s AND case_phase=%s '
                        . 'AND row_version=%s AND resolved_at IS NULL',
                    [Orders_Domain::table('pc_cases'),$phase,$caseId,
                        $orderId,$case['case_phase'],$next]
                );
            if (1 !== $changed) {
                throw new Transaction_Failure('pc_logistics_conflict');
            }
            $event = $resolving
                ? 'logistics_case_resolved' : 'logistics_case_proposed';
            self::eventWithin($tx,$actor,$claim['operation_id'],$caseId,
                $event,$value,$body['evidence_ref'],[
                    'reason'=>$body['reason'],'case_phase'=>$phase,
                    'row_version'=>$next,
                    'line_row_version'=>(string)$line['row_version'],
                ]);
            if($resolving){
                Orders_Finalization::afterWithin(
                    $tx,$actor,$claim['operation_id'],$orderId
                );
            }
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'case',$caseId,200
            );
            return ['status'=>200,'body'=>self::result(
                $caseId,$claim['operation_id'],$phase,$next
            )];
        },true);
    }

    private static function caseScopeWithin(
        Transaction_Context $tx,string $caseId
    ): array {
        $rows = $tx->rows(
            'SELECT c.order_id,c.order_line_id,c.source_operation_id '
                . 'FROM %i c INNER JOIN %i l '
                . 'ON l.id=c.order_line_id AND l.order_id=c.order_id '
                . 'WHERE c.id=%s AND c.case_type=%s LIMIT 2',
            [Orders_Domain::table('pc_cases'),
                Orders_Domain::table('pc_order_lines'),$caseId,self::TYPE]
        );
        if (1 !== \count($rows)
            || null === $rows[0]['order_id']
            || null === $rows[0]['order_line_id']) {
            throw new Transaction_Failure('pc_logistics_denied');
        }
        return [
            'order_id'=>Domain_Validation::sqlId((string)$rows[0]['order_id']),
            'order_line_id'=>Domain_Validation::sqlId((string)$rows[0]['order_line_id']),
            'source_operation_id'=>$rows[0]['source_operation_id'],
        ];
    }

    private static function requireOriginWithin(
        Transaction_Context $tx,string $caseId
    ): void {
        $rows = $tx->rows(
            'SELECT e.event_code,e.actor_kind,e.actor_user_id,i.operation_type,
                . 'i.technical_phase,i.entity_type,i.entity_id '
                . 'FROM %i e INNER JOIN %i i ON i.id=e.idempotency_id '
                . 'WHERE e.case_id=%s ORDER BY e.occurred_at,e.id LIMIT 1',
            [Orders_Domain::table('pc_case_events'),
                Orders_Domain::table('pc_idempotency'),$caseId]
        );
        if (1 !== \count($rows)
            || 'logistics_case_opened' !== $rows[0]['event_code']
            || 'staff' !== $rows[0]['actor_kind']
            || null === $rows[0]['actor_user_id']
            || self::OPEN_OP !== $rows[0]['operation_type']
            || 'complete' !== $rows[0]['technical_phase']
            || 'case' !== $rows[0]['entity_type']
            || $caseId !== (string)$rows[0]['entity_id']) {
            throw new Transaction_Failure('pc_logistics_conflict');
        }
    }

    private static function deliveryWithin(
        Transaction_Context $tx,string $orderId
    ): bool {
        $rows = $tx->rows(
            'SELECT original_delivery_snapshot FROM %i WHERE id=%s LIMIT 2',
            [Orders_Domain::table('pc_orders'),$orderId]
        );
        if (1 !== \count($rows)) { return false; }
        $data = Orders_Domain::deliverySnapshot(
            $rows[0]['original_delivery_snapshot']
        );
        return 'delivery' === ($data['mode'] ?? null);
    }

    private static function eventWithin(
        Transaction_Context $tx,Event_Actor $actor,string $operationId,
        string $caseId,string $event,?string $decision,
        string $evidenceRef,array $detail
    ): void {
        // pc_case_events permite solo un evento por raíz de idempotencia.
        // El relato/evidencia es privado; auditoría no lo copia.
        $snapshot = self::json($detail);
        $sql = 'INSERT INTO %i (case_id,idempotency_id,event_code,'
            . ($decision === null ? '' : 'decision_code,')
            . 'actor_kind,actor_user_id,evidence_ref,safe_payload_snapshot,'
            . 'occurred_at) VALUES (%s,%s,%s,'
            . ($decision === null ? '' : '%s,')
            . '%s,%s,%s,%s,UTC_TIMESTAMP(6))';
        $args = [Orders_Domain::table('pc_case_events'),$caseId,
            $operationId,$event];
        if (null !== $decision) { $args[] = $decision; }
        \array_push($args,'staff',(string)\get_current_user_id(),
            $evidenceRef,$snapshot);
        if (1 !== $tx->execute($sql,$args)) {
            throw new Transaction_Failure('pc_logistics_invalid');
        }
        Event_Journal::auditWithin(
            $tx,$actor,$operationId,'case',$caseId,$event,self::TYPE,
            null,\hash('sha256',$caseId.':'.$event.':'
                .(string)$detail['row_version'].':'
                .\hash('sha256',$evidenceRef))
        );
    }

    private static function replayWithin(
        Transaction_Context $tx,array $claim,string $action,int $http
    ): array {
        $caseId = (string)($claim['entity_id'] ?? '');
        if (($claim['entity_type'] ?? null) !== 'case'
            || $claim['response_code'] !== $http) {
            throw new Transaction_Failure('pc_logistics_conflict');
        }
        Domain_Validation::sqlId($caseId);
        $event = match ($action) {
            self::OPEN_OP => 'logistics_case_opened',
            self::PROPOSE_OP => 'logistics_case_proposed',
            default => 'logistics_case_resolved',
        };
        $rows = $tx->rows(
            'SELECT actor_user_id,safe_payload_snapshot FROM %i '
                . 'WHERE case_id=%s AND idempotency_id=%s '
                . 'AND event_code=%s AND actor_kind=%s LIMIT 2',
            [Orders_Domain::table('pc_case_events'),$caseId,
                $claim['operation_id'],$event,'staff']
        );
        if (1 !== \count($rows)
            || (string)$rows[0]['actor_user_id']
                !== (string)\get_current_user_id()
            || !\is_string($rows[0]['safe_payload_snapshot'])) {
            throw new Transaction_Failure('pc_logistics_conflict');
        }
        $snapshot = Orders_Domain::decode(
            (string)$rows[0]['safe_payload_snapshot'],10000
        );
        $phase = $snapshot['case_phase'] ?? null;
        $version = $snapshot['row_version'] ?? null;
        if (!\is_string($phase) || !\is_string($version)
            || $phase !== match ($action) {
                self::OPEN_OP => self::OPEN,
                self::PROPOSE_OP => self::REVIEW,
                default => self::CLOSED,
            }) {
            throw new Transaction_Failure('pc_logistics_conflict');
        }
        Domain_Validation::sqlId($version);
        return self::result($caseId,$claim['operation_id'],$phase,$version);
    }

    private static function claimed(array $claim): void
    {
        if (!\in_array($claim['status'] ?? null,['new','replay'],true)) {
            throw new Transaction_Failure('pc_logistics_conflict');
        }
    }

    private static function input(mixed $body,string $action): array
    {
        $fields = match ($action) {
            self::OPEN_OP => ['reason','evidence_ref','expected_version','idempotency_key'],
            self::PROPOSE_OP => ['resolution_proposal','evidence_ref',
                'reason','expected_version','idempotency_key'],
            self::RESOLVE_OP => ['decision','evidence_ref',
                'reason','expected_version','idempotency_key'],
            default => throw new \LogicException('pc_logistics_action_invalid'),
        };
        if (!\is_array($body) || \array_is_list($body)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $actual = \array_keys($body);
        \sort($actual,\SORT_STRING);
        \sort($fields,\SORT_STRING);
        if ($actual !== $fields) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        self::text($body['reason'],1000,'reason');
        self::text($body['evidence_ref'],500,'evidence_ref');
        if (\str_contains($body['evidence_ref'],'://')
            || \preg_match('/[\x00-\x1F\x7F]/u',$body['evidence_ref'])) {
            throw new Rest_Failure('pc_field_invalid',422,false,'evidence_ref');
        }
        if (!\is_string($body['expected_version'])) {
            throw new Rest_Failure('pc_field_invalid',422,false,'expected_version');
        }
        try { Domain_Validation::sqlId($body['expected_version']); }
        catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422,false,'expected_version');
        }
        if (!\is_string($body['idempotency_key'])
            || !\mb_check_encoding($body['idempotency_key'],'UTF-8')
            || \mb_strlen($body['idempotency_key'],'UTF-8')<16
            || \mb_strlen($body['idempotency_key'],'UTF-8')>128
            || \preg_match('/[\x00-\x20\x7F]/u',$body['idempotency_key'])) {
            throw new Rest_Failure('pc_field_invalid',422,false,'idempotency_key');
        }
        if ($action === self::PROPOSE_OP
            && !\in_array($body['resolution_proposal'],self::PROPOSALS,true)
            || $action === self::RESOLVE_OP
            && !\in_array($body['decision'],self::DECISIONS,true)) {
            throw new Rest_Failure('pc_field_invalid',422,false,
                self::PROPOSE_OP===$action?'resolution_proposal':'decision');
        }
        return $body;
    }

    private static function text(mixed $value,int $max,string $field): void
    {
        if (!\is_string($value) || ''===\trim($value)
            || !\mb_check_encoding($value,'UTF-8')
            || \mb_strlen($value,'UTF-8')>$max
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)) {
            throw new Rest_Failure('pc_field_invalid',422,false,$field);
        }
    }

    private static function json(array $data): string
    {
        try {
            $raw = \json_encode($data,\JSON_THROW_ON_ERROR
                |\JSON_UNESCAPED_UNICODE|\JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $ignored) {
            throw new Transaction_Failure('pc_logistics_invalid');
        }
        if (!\is_string($raw) || \strlen($raw)>10000) {
            throw new Transaction_Failure('pc_logistics_invalid');
        }
        return $raw;
    }

    private static function lastId(Transaction_Context $tx): string
    {
        $rows = $tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_logistics_invalid');
        }
        return Domain_Validation::sqlId((string)$rows[0]['id']);
    }

    private static function ready(?string $role,bool $financial): bool
    {
        return 'A' === $role
            && 'A' === Access_Roles::currentRole()
            && \current_user_can('perucommerce_orders_fulfill')
            && (!$financial
                || \current_user_can('perucommerce_orders_financial_adjust'))
            && (int)\get_current_blog_id()>0
            && !(\function_exists('is_network_admin')&&\is_network_admin())
            && Module_Gates::allows('orders','logistics_cases');
    }

    private static function requireStaff(bool $financial): void
    {
        if (!self::ready(Access_Roles::currentRole(),$financial)) {
            throw new Transaction_Failure('pc_logistics_denied');
        }
    }

    private static function proof(): array
    {
        return [
            'authorized'=>true,'same_site'=>true,
            'resource_checked'=>true,'state_checked'=>true,
            'field_checked'=>true,
        ];
    }

    private static function scope(
        string $resource,string $id,string $action
    ): array {
        return [
            'actor_kind'=>'staff',
            'actor_ref'=>(string)\get_current_user_id(),
            'resource_kind'=>$resource,
            'resource_id'=>$id,
            'purpose'=>$action,
        ];
    }

    private static function result(
        string $caseId,string $operationId,string $phase,string $version
    ): array {
        return [
            'case_id'=>$caseId,'operation_id'=>$operationId,
            'case_phase'=>$phase,'row_version'=>$version,
        ];
    }
}
