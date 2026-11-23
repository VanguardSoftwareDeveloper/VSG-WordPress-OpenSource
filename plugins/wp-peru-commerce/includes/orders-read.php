<?php
/**
 * IMP-22 — R8-20/R8-21: lectura coherente de pedidos y líneas.
 *
 * El pedido es ancla de serialización; los escritores futuros deben tomar
 * pc_orders antes de pc_order_lines, compromisos, casos y ledger. Nunca se
 * infieren importes/condiciones de catálogos u opciones presentes.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Read
{
    public static function listing(array $query): array
    {
        self::staff();
        $window = Rest_Pagination::window($query);
        $state = $query['state'] ?? null;
        if (null !== $state && (!\is_string($state)
            || !\in_array($state, Orders_Domain::STATES, true))) {
            throw new Rest_Failure('pc_field_invalid',422,false,'state');
        }
        return Transaction::run(static function (Transaction_Context $tx) use (
            $window, $state
        ): array {
            $args = [Orders_Domain::table('pc_orders')];
            $sql = 'SELECT id, order_number, status, payment_method, currency, '
                . 'total_cents, created_at FROM %i';
            if (null !== $state) {
                $sql .= ' WHERE status = %s';
                $args[] = $state;
            }
            $sql .= ' ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d LOCK IN SHARE MODE';
            $args[] = $window['fetch'];
            $args[] = $window['offset'];
            $rows = $tx->rows($sql,$args);
            $visible = \array_slice($rows, 0, $window['per_page']);
            $indicators = self::paymentIndicatorsWithin($tx,$visible);
            $items = [];
            foreach ($rows as $row) {
                if (\count($items) === $window['per_page']) {
                    break;
                }
                Orders_Domain::state((string) $row['status']);
                self::validateMoneyOrder($row);
                $id = Orders_Domain::id((string) $row['id']);
                $items[] = [
                    'id'=>$id,
                    'order_number'=>self::number((string) $row['order_number']),
                    'order_state'=>(string) $row['status'],
                    'created_at'=>Orders_Domain::utc((string) $row['created_at']),
                    'total_cents'=>Orders_Domain::money((string) $row['total_cents']),
                    'payment_indicator'=>$indicators[$id] ?? 'CONCILIACION_PENDIENTE',
                ];
                // No mostrar contactos en listados masivos al operador.
            }
            return Rest_Pagination::envelope(
                \array_merge($items,
                    \count($rows) > $window['per_page'] ? [[]] : []),
                $window
            );
        });
    }

    public static function detail(string $id, array $query): array
    {
        self::staff();
        $id = Domain_Validation::sqlId($id);
        $caseId = $query['case_id'] ?? null;
        $eventsPage = $query['case_events_page'] ?? 1;
        if (null === $caseId && \array_key_exists('case_events_page',$query)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'case_events_page');
        }
        if (!\is_int($eventsPage) || $eventsPage < 1 || $eventsPage > 100000) {
            throw new Rest_Failure('pc_field_invalid',422,false,'case_events_page');
        }
        if (null !== $caseId) {
            $caseId = Domain_Validation::sqlId($caseId);
        }
        return Transaction::run(static function (Transaction_Context $tx) use (
            $id,$caseId,$eventsPage
        ): array {
            try {
                $locked = $tx->lockOrder($id);
            } catch (Transaction_Failure $error) {
                if ('pc_record_missing_or_conflict' === $error->getMessage()) {
                    throw new Transaction_Failure('pc_orders_not_found');
                }
                throw $error;
            }
            $orders = $tx->rows(
                'SELECT id, order_number, status, payment_method, currency, '
                    . 'subtotal_cents, delivery_cents, total_cents, buyer_email, '
                    . 'buyer_phone, customer_snapshot, original_delivery_snapshot, '
                    . 'current_delivery_revision_id, policy_version_id, privacy_version_id, '
                    . 'confirmed_at, closed_at, created_at, row_version '
                    . 'FROM %i WHERE id = %s',
                [Orders_Domain::table('pc_orders'),$id]
            );
            if (1 !== \count($orders)) {
                throw new Transaction_Failure('pc_orders_not_found');
            }
            $order = $orders[0];
            if ((string) $order['row_version'] !== (string) $locked['row_version']
                || (string) $order['status'] !== (string) $locked['status']) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            self::validateMoneyOrder($order);
            $lines = $tx->rows(
                'SELECT id, order_id, sellable_id, kind, quantity, '
                    . 'unit_price_cents, subtotal_cents, fulfillment_state, '
                    . 'catalog_snapshot, row_version, service_slot_start_utc, '
                    . 'service_slot_end_utc, service_timezone FROM %i '
                    . 'WHERE order_id = %s ORDER BY id LIMIT 101 LOCK IN SHARE MODE',
                [Orders_Domain::table('pc_order_lines'),$id]
            );
            if ([] === $lines || \count($lines) > 100) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            $physical = $tx->rows(
                'SELECT c.order_line_id, c.sellable_id, c.committed_units, '
                    . 'c.fulfilled_units, c.released_units FROM %i c '
                    . 'INNER JOIN %i l ON l.id = c.order_line_id '
                    . 'WHERE l.order_id = %s',
                [Orders_Domain::table('pc_stock_commitments'),
                    Orders_Domain::table('pc_order_lines'),$id]
            );
            $counts = [];
            foreach ($physical as $row) {
                $lineId = (string) $row['order_line_id'];
                if (isset($counts[$lineId])) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
                $counts[$lineId] = $row;
            }
            $mapped = [];
            $expectedSum = 0;
            $serviceCount = 0;
            foreach ($lines as $line) {
                $kind = (string) $line['kind'];
                $state = Orders_Domain::lineState($kind,(string) $line['fulfillment_state']);
                $lineId = Orders_Domain::id((string) $line['id']);
                $quantity = Orders_Domain::quantity((string) $line['quantity']);
                if ($quantity < 1) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
                $unitPrice = Money::fromApi((string) $line['unit_price_cents']);
                if ($unitPrice < 1
                    || (string) Money::lineSubtotal($unitPrice,$quantity)
                        !== (string) $line['subtotal_cents']) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
                $expectedSum = Money::addChecked($expectedSum,
                    Money::fromApi((string) $line['subtotal_cents']));
                $snapshot = Orders_Domain::catalogSnapshot((string) $line['catalog_snapshot'],$kind);
                $entry = [
                    'line_id'=>$lineId,'title'=>$snapshot['title'],
                    'kind'=>$kind,'quantity'=>$quantity,
                    'unit_price_cents'=>(string) $unitPrice,
                    'fulfillment_state'=>$state,
                    'row_version'=>Orders_Domain::id((string) $line['row_version']),
                ];
                if ('physical' === $kind) {
                    $entry['physical_quantities'] = Orders_Domain::physicalQuantities(
                        $order,$line,$counts[$lineId] ?? null
                    );
                    unset($counts[$lineId]);
                } elseif (isset($counts[$lineId])) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
                if ('service' === $kind) {
                    ++$serviceCount;
                    if ('1' !== (string) $line['quantity']
                        || null === $line['service_timezone']
                            && null !== $line['service_slot_start_utc']) {
                        throw new Transaction_Failure('pc_orders_invariant_invalid');
                    }
                }
                $mapped[] = $entry;
            }
            if ([] !== $counts || $serviceCount > 1
                || (string) $expectedSum !== (string) $order['subtotal_cents']
                || (string) Money::orderTotal(
                    \array_map(static fn (array $l): int =>
                        Money::lineSubtotal(
                            Money::fromApi((string) $l['unit_price_cents']),
                            Orders_Domain::quantity((string) $l['quantity'])
                        ),$lines),
                    Money::fromApi((string) $order['delivery_cents'])
                ) !== (string) $order['total_cents']) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            // Un recurso de agenda es único por pedido, incluso cuando
            // la reserva MP aún no se confirmó. La cita efectiva NO existe
            // durante PENDIENTE_CONFIRMACION; si existe debe apuntar a la
            // línea de servicio del mismo pedido.
            $appointments = $tx->rows(
                'SELECT order_line_id FROM %i WHERE order_id = %s LIMIT 2',
                [Orders_Domain::table('pc_appointments'),$id]
            );
            $serviceLine = null;
            $serviceConfirmed = false;
            foreach ($lines as $line) {
                if ('service' === $line['kind']) {
                    $serviceLine = (string) $line['id'];
                    $serviceConfirmed = \in_array(
                        (string) $line['fulfillment_state'],
                        ['CONFIRMADA','REALIZADA','NO_ASISTIO'],true
                    );
                    break;
                }
            }
            if (\count($appointments) > 1
                || null === $serviceLine && [] !== $appointments
                || null !== $serviceLine && 1 === \count($appointments)
                    && (string) $appointments[0]['order_line_id'] !== $serviceLine
                || $serviceConfirmed && 1 !== \count($appointments)) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }

            $notice = [
                'policy_version'=>Orders_Domain::id((string) $order['policy_version_id']),
                'privacy_version'=>Orders_Domain::id((string) $order['privacy_version_id']),
                'policy_notice'=>Settings_Store::policyWithin($tx,
                    (string) $order['policy_version_id'],'commercial_terms'),
                'privacy_notice'=>Settings_Store::policyWithin($tx,
                    (string) $order['privacy_version_id'],'privacy_notice'),
            ];
            $indicators = self::paymentIndicatorsWithin($tx,[$order]);
            $data = [
                'id'=>$id,
                'order_number'=>self::number((string) $order['order_number']),
                'order_state'=>Orders_Domain::state((string) $order['status']),
                'row_version'=>Orders_Domain::id((string) $order['row_version']),
                'lines'=>$mapped,
                'total_cents'=>Orders_Domain::money((string) $order['total_cents']),
                'payment_indicator'=>$indicators[$id] ?? 'CONCILIACION_PENDIENTE',
                'agreed_notices'=>$notice,
            ];
            $operational = self::operationalWithin($tx,$id);
            if ([] !== $operational) {
                $data['operational_events'] = $operational;
            }

            $delivery = self::deliveryWithin($tx,$order);
            if (null !== $delivery) {
                $data['delivery_snapshot'] = $delivery['summary'];
            }
            $contact = self::contactForTask($order,$delivery,$lines);
            if ([] !== $contact) {
                $data['contact_for_task'] = $contact;
            }
            if (self::canFinancial()) {
                $data['financial_movements'] = self::financialWithin($tx,$id);
            }
            if (self::canFiscal()) {
                $data['external_documents'] = self::externalDocumentsWithin($tx,$id);
            }
            if (self::canDigital()) {
                $data['digital_rights'] = self::digitalRightsWithin($tx,$id,$lines);
            }
            if (self::canCases()) {
                $data['cases'] = Orders_Cases::summariesWithin($tx,$id);
                if (null !== $caseId) {
                    $data['case_detail'] = Orders_Cases::detailWithin(
                        $tx,$id,$caseId,$eventsPage
                    );
                }
            } elseif (null !== $caseId) {
                throw new Transaction_Failure('pc_orders_not_found');
            }
            return $data;
        });
    }

    public static function allowedTask(string $orderId): ?string
    {
        $orderId = Domain_Validation::sqlId($orderId);
        return Transaction::run(static function (Transaction_Context $tx) use ($orderId): ?string {
            $orders = $tx->rows(
                'SELECT status, original_delivery_snapshot FROM %i WHERE id = %s',
                [Orders_Domain::table('pc_orders'),$orderId]
            );
            if (1 !== \count($orders)
                || \in_array((string) $orders[0]['status'],
                    ['CANCELADO','FINALIZADO','VENCIDO'],true)) {
                // No exponer contacto operativo de un encargo terminado.
                return null;
            }
            $rows = $tx->rows(
                'SELECT kind, fulfillment_state FROM %i WHERE order_id = %s LIMIT 101',
                [Orders_Domain::table('pc_order_lines'),$orderId]
            );
            if ([] === $rows || \count($rows) > 100) {
                return null;
            }
            $activePhysical = false;
            $activeService = false;
            foreach ($rows as $line) {
                if ('physical' === (string) $line['kind']
                    && !\in_array((string) $line['fulfillment_state'],
                        ['ENTREGADO','CANCELADO'],true)) {
                    $activePhysical = true;
                }
                if ('service' === (string) $line['kind']
                    && \in_array((string) $line['fulfillment_state'],
                        ['PENDIENTE_CONFIRMACION','CONFIRMADA'],true)) {
                    $activeService = true;
                }
            }
            if ($activePhysical && \current_user_can('perucommerce_orders_fulfill')) {
                $delivery = Orders_Domain::deliverySnapshot(
                    $orders[0]['original_delivery_snapshot']
                );
                return 'delivery' === ($delivery['mode'] ?? null)
                    ? 'delivery' : 'contact';
            }
            return $activeService && \current_user_can('perucommerce_appointments_manage')
                ? 'service' : null;
        });
    }

    private static function contactForTask(array $order, ?array $delivery, array $lines): array
    {
        $role = Access_Roles::currentRole();
        $customer = Orders_Domain::customerSnapshot((string) $order['customer_snapshot']);
        foreach (['buyer_phone'=>'phone','buyer_email'=>'email'] as $column=>$name) {
            $persisted = $order[$column] ?? null;
            if (($persisted ?? null) !== ($customer[$name] ?? null)) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
        }
        $contact = [];
        foreach (['buyer_phone'=>'phone','buyer_email'=>'email'] as $source=>$field) {
            if (null !== $order[$source] && '' !== $order[$source]) {
                $contact[$field] = (string) $order[$source];
            }
        }
        if (isset($customer['recipient'])) {
            $contact['recipient'] = $customer['recipient'];
        }
        if (null !== $delivery && 'delivery' === $delivery['mode']
            && isset($delivery['address'])) {
            $contact['address'] = $delivery['address'];
        }
        if ('O' === $role) {
            // La capa Access_Projection aplica el task verificado
            // por guard del servidor; aun así evitar datos innecesarios.
            if ([] === $contact) {
                return [];
            }
        }
        return $contact;
    }

    private static function deliveryWithin(Transaction_Context $tx, array $order): ?array
    {
        $current = Orders_Domain::deliverySnapshot(
            $order['original_delivery_snapshot']
        );
        if (null === $current) {
            if (Money::fromApi((string) $order['delivery_cents']) !== 0) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            return null;
        }
        if (null !== $order['current_delivery_revision_id']) {
            $rows = $tx->rows(
                'SELECT id, ubigeo_code, address_snapshot, fee_cents, '
                    . 'approved_by,approved_at FROM %i WHERE id = %s AND order_id = %s',
                [Orders_Domain::table('pc_delivery_revisions'),
                    (string) $order['current_delivery_revision_id'],
                    (string) $order['id']]
            );
            if (1 !== \count($rows)
                || null === $rows[0]['approved_at']
                || null === $rows[0]['approved_by']) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            $revision = Orders_Domain::decode((string) $rows[0]['address_snapshot'],3072);
            if (($revision['mode']??null)!=='delivery'
                || !\is_string($revision['address'] ?? null)
                || '' === \trim($revision['address'])
                || \mb_strlen($revision['address'],'UTF-8') > 500
                || !\is_string($rows[0]['ubigeo_code'])
                || ($revision['ubigeo_code']??null)!==$rows[0]['ubigeo_code']
                || !\is_string($revision['fee_cents']??null)
                || Money::fromApi($revision['fee_cents'])
                    !==Money::fromApi((string)$rows[0]['fee_cents'])) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            $current = [
                'mode'=>'delivery', 'address'=>$revision['address'],
                'ubigeo_code'=>$rows[0]['ubigeo_code'],
            ];
        }
        $summary = (string) $current['mode'];
        if ('delivery' === $summary) {
            $summary .= ':' . $current['ubigeo_code'];
        }
        $current['summary'] = $summary;
        return $current;
    }

    /** Solo códigos operacionales realmente auditados, sin PII, finanzas ni casos. */
    private static function operationalWithin(Transaction_Context $tx, string $orderId): array
    {
        $approved = ['order_snapshot_created','order_confirmed',
            'order_cancelled','order_expired','order_finalized',
            'delivery_change_proposed','delivery_change_approved',
            'order_finalized'];
        $markers = \implode(',',\array_fill(0,\count($approved),'%s'));
        $events = $tx->rows(
            'SELECT operation_code, occurred_at FROM %i '
                . 'WHERE entity_type = %s AND entity_id = %s '
                . 'AND operation_code IN (' . $markers . ') '
                . 'ORDER BY occurred_at DESC, id DESC LIMIT 50',
            \array_merge([Orders_Domain::table('pc_audit_log'),
                'order',$orderId],$approved)
        );
        $out = [];
        foreach (\array_reverse($events) as $event) {
            $out[] = [
                'event' => (string) $event['operation_code'],
                'at' => Orders_Domain::utc((string) $event['occurred_at']),
            ];
        }
        return $out;
    }

    /**
     * Indicador administrativo derivado, NO acreditación de negocio,
     * saldo materializado ni permiso de cancelar/reembolsar.
     *
     * Agregar por pedido evita cargar todo el ledger de 100 pedidos en
     * memoria y no confunde respuesta MP con dinero comprobado.
     */
    private static function paymentIndicatorsWithin(Transaction_Context $tx, array $orders): array
    {
        if ([] === $orders) {
            return [];
        }
        $ids = \array_map(static fn (array $o): string =>
            Orders_Domain::id((string) $o['id']), $orders);
        $marks = \implode(',',\array_fill(0,\count($ids),'%s'));
        $attempts = $tx->rows(
            'SELECT order_id, '
                . 'MAX(CASE WHEN state = %s THEN 1 ELSE 0 END) AS approved, '
                . 'MAX(CASE WHEN state = %s THEN 1 ELSE 0 END) AS uncertain '
                . 'FROM %i WHERE order_id IN ('.$marks.') GROUP BY order_id',
            \array_merge(['APROBADO','REQUIERE_CONCILIACION',
                Orders_Domain::table('pc_payment_attempts')],$ids)
        );
        $events = $tx->rows(
            'SELECT order_id, COUNT(*) AS event_count, '
                . 'SUM(CASE WHEN amount_cents > 0 AND provider = %s THEN 1 ELSE 0 END) AS mp_credit, '
                . 'SUM(CASE WHEN amount_cents > 0 AND provider = %s THEN 1 ELSE 0 END) AS cash_credit, '
                . 'SUM(CASE WHEN amount_cents < 0 THEN 1 ELSE 0 END) AS negatives '
                . 'FROM %i WHERE order_id IN ('.$marks.') GROUP BY order_id',
            \array_merge(['mercado_pago','cash',
                Orders_Domain::table('pc_payment_events')],$ids)
        );
        $signals = [];
        foreach ($attempts as $record) {
            $signals[(string) $record['order_id']]['attempt'] = $record;
        }
        foreach ($events as $record) {
            $signals[(string) $record['order_id']]['event'] = $record;
        }
        $result = [];
        foreach ($orders as $order) {
            $id = Orders_Domain::id((string) $order['id']);
            $a = $signals[$id]['attempt'] ?? [];
            $e = $signals[$id]['event'] ?? [];
            $approved = (int) ($a['approved'] ?? 0) > 0;
            $uncertain = (int) ($a['uncertain'] ?? 0) > 0;
            $positiveMp = (int) ($e['mp_credit'] ?? 0) > 0;
            $positiveCash = (int) ($e['cash_credit'] ?? 0) > 0;
            $negative = (int) ($e['negatives'] ?? 0) > 0;
            $hasFacts = (int) ($e['event_count'] ?? 0) > 0;
            if ($uncertain || $negative) {
                $result[$id] = 'CONCILIACION_PENDIENTE';
            } elseif (('cash' === (string) $order['payment_method'] && $positiveCash)
                || ('mercado_pago' === (string) $order['payment_method']
                    && $approved && $positiveMp)) {
                $result[$id] = 'ACREDITADO';
            } elseif ($hasFacts || $approved) {
                // Un intento APROBADO sin asiento verificable o un asiento
                // con clasificación no reconciliada NO prueba fondos.
                $result[$id] = 'CONCILIACION_PENDIENTE';
            } elseif (\in_array((string) $order['status'],['CANCELADO','VENCIDO'],true)) {
                $result[$id] = 'SIN_COBRO';
            } else {
                $result[$id] = 'PENDIENTE_COBRO';
            }
        }
        return $result;
    }

    private static function financialWithin(Transaction_Context $tx,string $id): array
    {
        $rows = $tx->rows(
            'SELECT id, kind, amount_cents, source_payment_event_id, '
                . 'external_reference, occurred_at, refund_evidence_snapshot FROM %i '
                . 'WHERE order_id = %s ORDER BY recorded_at DESC,id DESC LIMIT 201',
            [Orders_Domain::table('pc_payment_events'),$id]
        );
        if (\count($rows) > 200) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        $out = [];
        foreach ($rows as $row) {
            $signed = Money::fromSignedLedger((string) $row['amount_cents']);
            $part = [
                'event_id'=>Orders_Domain::id((string) $row['id']),
                'kind'=>(string) $row['kind'],
                'amount_cents'=>Money::toLedger($signed),
                'occurred_at'=>Orders_Domain::utc((string) $row['occurred_at']),
            ];
            if (null !== $row['source_payment_event_id']) {
                $part['source_payment_event_id'] =
                    Orders_Domain::id((string) $row['source_payment_event_id']);
            }
            if (null !== $row['external_reference']) {
                $reference=(string)$row['external_reference'];
                // R8-27 guarda el ID literal 1..200 solo en snapshot
                // protegido, no trunca a VARCHAR(128) ni lo publica a O.
                // AdminFinancialEvent limita esta proyección a 128; si
                // el original es más largo se conserva el digest opaco.
                if ('external_refund'===$row['kind']
                    && \is_string($row['refund_evidence_snapshot'])) {
                    $proof=Orders_Domain::decode(
                        $row['refund_evidence_snapshot'],4096
                    );
                    $full=$proof['external_reference']??null;
                    if (\is_string($full)
                        && \strlen($full)<=128
                        && 1===\preg_match('/\A[A-Za-z0-9._:-]+\z/D',$full)) {
                        $reference=$full;
                    }
                }
                $part['external_reference']=$reference;
            }
            $out[] = $part;
        }
        return $out;
    }

    private static function externalDocumentsWithin(Transaction_Context $tx,string $id): array
    {
        $rows = $tx->rows(
            'SELECT id, document_kind, series, number, issued_at, '
                . 'recorded_at, source_document_id, amount_cents FROM %i '
                . 'WHERE order_id = %s ORDER BY recorded_at DESC,id DESC LIMIT 101',
            [Orders_Domain::table('pc_external_documents'),$id]
        );
        if (\count($rows) > 100) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        $out = [];
        foreach ($rows as $row) {
            $v = [
                'id'=>Orders_Domain::id((string) $row['id']),
                'document_kind'=>(string) $row['document_kind'],
                'recorded_at'=>Orders_Domain::utc((string) $row['recorded_at']),
            ];
            foreach (['series','number'] as $key) {
                if (null !== $row[$key]) {
                    $v[$key] = (string) $row[$key];
                }
            }
            if (null !== $row['source_document_id']) {
                $v['source_document_id'] = Orders_Domain::id((string) $row['source_document_id']);
            }
            if (null !== $row['amount_cents']) {
                $v['amount_cents'] = Orders_Domain::money((string) $row['amount_cents']);
            }
            if (null !== $row['issued_at']) {
                $v['issued_at'] = Orders_Domain::utc((string) $row['issued_at']);
            }
            $out[] = $v;
        }
        return $out;
    }

    /**
     * Los derechos se comparan con TODOS los archivos del snapshot original
     * de la línea, nunca con pc_digital_assignments vigente actualmente.
     *
     * @param list<array<string,mixed>> $lines
     */
    private static function digitalRightsWithin(
        Transaction_Context $tx,string $id,array $lines
    ): array {
        $frozen = [];
        foreach ($lines as $line) {
            if ('digital' !== (string) $line['kind']) {
                continue;
            }
            $lineId = Orders_Domain::id((string) $line['id']);
            $snap = Orders_Domain::catalogSnapshot(
                (string) $line['catalog_snapshot'],'digital'
            );
            $files = $snap['files'] ?? [[
                'slot_key'=>$snap['slot_key'],'file_id'=>$snap['file_id'],
            ]];
            $ids = [];
            foreach ($files as $file) {
                $fileId = Orders_Domain::id((string) $file['file_id']);
                if (isset($ids[$fileId])) {
                    throw new Transaction_Failure('pc_orders_invariant_invalid');
                }
                $ids[$fileId] = true;
            }
            $frozen[$lineId] = [
                'ids'=>$ids,
                'state'=>(string) $line['fulfillment_state'],
                'seen'=>[],
            ];
        }
        $rows = $tx->rows(
            'SELECT r.id, r.order_line_id, r.file_id, r.row_version, '
                . 'r.revoked_at, r.optional_limit, r.download_count, '
                . 'f.id AS owned_file_id '
                . 'FROM %i r INNER JOIN %i l ON l.id = r.order_line_id '
                . 'LEFT JOIN %i f ON f.id = r.file_id AND f.sellable_id = l.sellable_id '
                . 'WHERE l.order_id = %s AND l.kind = %s '
                . 'ORDER BY r.id LIMIT 201',
            [Orders_Domain::table('pc_download_rights'),
                Orders_Domain::table('pc_order_lines'),
                Orders_Domain::table('pc_digital_files'),$id,'digital']
        );
        if (\count($rows) > 200) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        $out = [];
        foreach ($rows as $row) {
            $lineId = Orders_Domain::id((string) $row['order_line_id']);
            $fileId = Orders_Domain::id((string) $row['file_id']);
            if (null === $row['owned_file_id']
                || (string) $row['owned_file_id'] !== $fileId
                || !isset($frozen[$lineId]['ids'][$fileId])
                || isset($frozen[$lineId]['seen'][$fileId])) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            $frozen[$lineId]['seen'][$fileId] = true;
            $limit = null === $row['optional_limit'] ? null :
                Orders_Domain::quantity((string) $row['optional_limit']);
            $count = (string) $row['download_count'];
            if (!\preg_match('/\A(?:0|[1-9][0-9]*)\z/D',$count)
                || \strlen($count) > 10
                || (10 === \strlen($count) && \strcmp($count,'4294967295') > 0)) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            $out[] = [
                'right_id'=>Orders_Domain::id((string) $row['id']),
                'order_line_id'=>$lineId,
                'file_id'=>$fileId,
                'row_version'=>Orders_Domain::id((string) $row['row_version']),
                'revoked'=>null !== $row['revoked_at'],
                'download_limit'=>$limit,
                'download_count'=>(int) $count,
            ];
        }
        foreach ($frozen as $line) {
            // Una línea digital habilitada/revocada sin TODOS los derechos
            // originalmente vendidos es corrupción, no lista parcial.
            if (\in_array($line['state'],['HABILITADO','REVOCADO'],true)
                && \count($line['ids']) !== \count($line['seen'])) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            if ('BLOQUEADO' === $line['state'] && [] !== $line['seen']) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
        }
        return $out;
    }

    private static function number(string $value): string
    {
        if ('' === $value || \strlen($value) > 40
            || !\preg_match('/\A[A-Za-z0-9._-]+\z/D',$value)) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        return $value;
    }

    private static function validateMoneyOrder(array $order): void
    {
        Orders_Domain::state((string) $order['status']);
        if ('PEN' !== (string) $order['currency']
            || !\in_array((string) $order['payment_method'], ['cash','mercado_pago'],true)) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        Orders_Domain::money((string) $order['total_cents']);
    }

    public static function staff(): void
    {
        if (!\in_array(Access_Roles::currentRole(),['A','O'],true)
            || !\current_user_can('perucommerce_orders_read')) {
            throw new Rest_Failure('pc_access_denied',403);
        }
    }

    public static function canFinancial(): bool
    {
        return 'A' === Access_Roles::currentRole()
            && (\current_user_can('perucommerce_orders_financial_adjust')
                || \current_user_can('perucommerce_refunds_record_external'));
    }

    public static function canFiscal(): bool
    {
        return 'A' === Access_Roles::currentRole()
            && \current_user_can('perucommerce_settings_manage');
    }

    public static function canDigital(): bool
    {
        return 'A' === Access_Roles::currentRole()
            && \current_user_can('perucommerce_digital_files_manage');
    }

    public static function canCases(): bool
    {
        // IMP-33: lectura A de tipos guest únicamente si la capability
        // específica del tipo se comprueba de nuevo en Orders_Cases.
        return Orders_Cases::canReadAny();
    }
}
