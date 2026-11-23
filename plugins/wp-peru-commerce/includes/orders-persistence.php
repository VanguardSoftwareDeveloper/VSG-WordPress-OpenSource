<?php
/**
 * IMP-22 — inserción transaccional de un contrato comercial YA verificado.
 *
 * No se expone como REST. Los futuros R8-06/R8-07 aportan la cotización
 * autoritativa y el verificador de recursos/condiciones/estado, dentro de
 * su operación idempotente, usando el orden de locks de IMP-07/18.
 *
 * Nunca crea pagos, citas, derechos digitales, holds ni stock por inferencia.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Persistence
{
    /** @var callable|null Verificador confiable de checkout/cash/MP del consumidor futuro. */
    private static $preparedVerifier = null;

    public static function registerPreparedVerifier(callable $verifier): void
    {
        if (null !== self::$preparedVerifier) {
            throw new \LogicException('pc_order_verifier_duplicate');
        }
        self::$preparedVerifier = $verifier;
    }

    /**
     * Inserta pedido y snapshots antes de los efectos de checkout, en el
     * MISMO Transaction::run que decide si confirma/compromete recursos.
     * El caller mantiene pc_idempotency bloqueado en fase prepared;
     * devuelve ID seguro y NO completa la operación, para permitir que
     * los consumidores posteriores terminen sus compromisos y auditoría.
     *
     * @param array<string,mixed> $verified Quote construida SOLO por servidor
     * @return array{id:string,order_number:string,row_version:string}
     */
    public static function insertWithin(
        Transaction_Context $tx, Event_Actor $actor,
        string $operationId, array $verified
    ): array {
        $operationId = Event_Journal::requirePendingOperation($tx,$operationId);
        $actor->assertCurrent();
        if (null === self::$preparedVerifier
            || !Module_Gates::allows('orders','write')) {
            throw new Transaction_Failure('pc_orders_not_ready');
        }
        try {
            if (true !== (self::$preparedVerifier)($tx,$actor,$operationId,$verified)) {
                throw new Transaction_Failure('pc_orders_not_ready');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_orders_not_ready');
        }
        $expected = [
            'payment_method','status','currency','buyer_email','buyer_phone',
            'recipient','policy_version_id','privacy_version_id','quote_fingerprint',
            'delivery','lines',
        ];
        $keys = \array_keys($verified);
        \sort($keys);
        \sort($expected);
        if ($keys !== $expected
            || !\in_array($verified['payment_method'], ['cash','mercado_pago'],true)
            || !\in_array($verified['status'], ['PENDIENTE_PAGO','CONFIRMADO'],true)
            || ('cash' === $verified['payment_method']
                && 'CONFIRMADO' !== $verified['status'])
            || ('mercado_pago' === $verified['payment_method']
                && 'PENDIENTE_PAGO' !== $verified['status'])
            || 'PEN' !== $verified['currency']
            || !\is_string($verified['quote_fingerprint'])
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$verified['quote_fingerprint'])
            || !\is_array($verified['lines'])
            || !\array_is_list($verified['lines'])
            || [] === $verified['lines']
            || \count($verified['lines']) > 100
            || !\is_array($verified['delivery'])) {
            throw new Transaction_Failure('pc_orders_field_invalid');
        }
        $email = $verified['buyer_email'];
        $phone = $verified['buyer_phone'];
        $recipient = $verified['recipient'];
        if (null !== $email && (!\is_string($email) || false === \is_email($email))
            || null !== $phone && (!\is_string($phone)
                || !\preg_match('/\A\+?[0-9() -]{6,32}\z/D',$phone))
            || null !== $recipient && (!\is_string($recipient)
                || '' === \trim($recipient)
                || \mb_strlen($recipient,'UTF-8') > 160)) {
            throw new Transaction_Failure('pc_orders_field_invalid');
        }

        $lines = [];
        $totals = [];
        $serviceCount = 0;
        $physicalCount = 0;
        $digitalCount = 0;
        $requiresEmail = false;
        foreach ($verified['lines'] as $original) {
            if (!\is_array($original)
                || \count($original) !== 4
                || \array_diff(\array_keys($original),
                    ['sellable_id','quantity','unit_price_cents','catalog_snapshot']) !== []
                || !\is_string($original['sellable_id'])
                || !\is_int($original['quantity'])
                || !\is_string($original['unit_price_cents'])
                || !\is_array($original['catalog_snapshot'])) {
                throw new Transaction_Failure('pc_orders_field_invalid');
            }
            $sellableId = Domain_Validation::sqlId($original['sellable_id']);
            $quantity = $original['quantity'];
            $money = Money::fromApi($original['unit_price_cents']);
            if ($quantity < 1 || $quantity > 100000 || $money < 1) {
                throw new Transaction_Failure('pc_orders_field_invalid');
            }
            $snapshot = $original['catalog_snapshot'];
            // Solo campos de catálogo congelados, no secretos de storage,
            // rutas internas o datos de pago suministrados por comprador.
            $permitted = [
                'title','kind','product_id','variant_key','file_id','slot_key',
                'files', 'service_duration_minutes','service_buffer_before_minutes',
                'service_buffer_after_minutes','service_start_utc',
                'service_end_utc','service_timezone',
            ];
            foreach (\array_keys($snapshot) as $field) {
                if (!\in_array($field,$permitted,true)) {
                    throw new Transaction_Failure('pc_orders_field_invalid');
                }
            }
            $kind = $snapshot['kind'] ?? null;
            if (!\is_string($kind) || !isset(Orders_Domain::LINE_STATES[$kind])) {
                throw new Transaction_Failure('pc_orders_field_invalid');
            }
            Orders_Domain::catalogSnapshot(
                self::json($snapshot,'digital' === $kind ? 40000 : 12000),$kind
            );
            if ('physical' === $kind) {
                ++$physicalCount;
            } else {
                $requiresEmail = true;
            }
            if ('digital' === $kind) {
                ++$digitalCount;
            }
            if ('service' === $kind) {
                ++$serviceCount;
                if (1 !== $quantity || !\is_string($snapshot['service_start_utc'] ?? null)
                    || !\is_string($snapshot['service_end_utc'] ?? null)
                    || !\is_string($snapshot['service_timezone'] ?? null)) {
                    throw new Transaction_Failure('pc_orders_field_invalid');
                }
                $start = Business_Time::fromApi($snapshot['service_start_utc']);
                $end = Business_Time::fromApi($snapshot['service_end_utc']);
                Business_Time::requireInterval($start,$end);
                Business_Time::businessZone($snapshot['service_timezone']);
            }
            if ('digital' === $kind && !Module_Gates::allows('digital','read')) {
                throw new Transaction_Failure('pc_orders_not_ready');
            }
            $totals[] = Money::lineSubtotal($money,$quantity);
            $lines[] = [
                'sellable_id'=>$sellableId, 'quantity'=>$quantity,
                'unit_price_cents'=>$money, 'kind'=>$kind,
                'snapshot'=>$snapshot,
            ];
        }
        if ($serviceCount > 1 || $requiresEmail && null === $email
            || $physicalCount > 0 && null === $phone
            || 'cash' === $verified['payment_method'] && $digitalCount > 0) {
            // Un pedido con descarga digital no se puede cobrar en el
            // único evento presencial aprobado para contraentrega V1.
            throw new Transaction_Failure('pc_orders_field_invalid');
        }
        $delivery = $verified['delivery'];
        if (!\is_string($delivery['mode'] ?? null)
            || !\in_array($delivery['mode'],['delivery','pickup','none'],true)
            || !\is_string($delivery['fee_cents'] ?? null)) {
            throw new Transaction_Failure('pc_orders_field_invalid');
        }
        $deliveryFee = Money::fromApi($delivery['fee_cents']);
        if ($physicalCount > 0 && 'none' === $delivery['mode']
            || $physicalCount === 0 && 'none' !== $delivery['mode']) {
            throw new Transaction_Failure('pc_orders_field_invalid');
        }
        if ('delivery' === $delivery['mode']) {
            if ($physicalCount === 0
                || !Module_Gates::allows('delivery','write')
                || !\is_string($delivery['ubigeo_code'] ?? null)
                || !\is_string($delivery['address'] ?? null)
                || '' === \trim($delivery['address'])
                || \mb_strlen($delivery['address'],'UTF-8') > 500) {
                throw new Transaction_Failure('pc_orders_not_ready');
            }
            Bootstrap::loadDelivery();
            $authoritative = Delivery_Coverage::feeWithin(
                $tx,$delivery['ubigeo_code']
            );
            if ($authoritative['fee_cents'] !== (string) $deliveryFee
                || ($delivery['coverage_version'] ?? null) !== $authoritative['coverage_version']
                || ($delivery['dataset_version'] ?? null) !== $authoritative['dataset_version']) {
                throw new Transaction_Failure('pc_orders_quote_changed');
            }
            $delivery = [
                'mode'=>'delivery','ubigeo_code'=>$authoritative['ubigeo_code'],
                'address'=>$delivery['address'],
                'dataset_version'=>$authoritative['dataset_version'],
                'coverage_version'=>$authoritative['coverage_version'],
                'fee_cents'=>(string) $deliveryFee,
            ];
        } elseif ($deliveryFee !== 0
            || \count($delivery) !== 2) {
            throw new Transaction_Failure('pc_orders_field_invalid');
        }
        $subtotal = Money::orderTotal($totals,0);
        $total = Money::orderTotal($totals,$deliveryFee);

        $policyId = Domain_Validation::sqlId($verified['policy_version_id']);
        $privacyId = Domain_Validation::sqlId($verified['privacy_version_id']);
        Settings_Store::policyWithin($tx,$policyId,'commercial_terms');
        Settings_Store::policyWithin($tx,$privacyId,'privacy_notice');
        $customer = [];
        if (null !== $recipient) { $customer['recipient'] = $recipient; }
        if (null !== $email) { $customer['email'] = $email; }
        if (null !== $phone) { $customer['phone'] = $phone; }
        $customerRaw = self::json($customer);
        $deliveryRaw = $physicalCount > 0 ? self::json($delivery) : null;

        // El número comercial no es un secreto de seguimiento ni una
        // autorización para descargar un archivo o consultar un pedido.
        $orderNumber = 'PC-' . \bin2hex(\random_bytes(12));
        if (1 !== $tx->execute(
            'INSERT INTO %i (order_number,status,payment_method,currency,'
                . 'subtotal_cents,delivery_cents,total_cents,buyer_email,buyer_phone,'
                . 'customer_snapshot,quote_fingerprint,policy_version_id,'
                . 'privacy_version_id,original_delivery_snapshot,'
                . 'current_delivery_revision_id,confirmed_at,closed_at,'
                . 'created_at,updated_at,row_version) VALUES '
                . '(%s,%s,%s,%s,%s,%s,%s,NULLIF(%s,%s),NULLIF(%s,%s),'
                . '%s,UNHEX(%s),%s,%s,NULLIF(%s,%s),NULL,'
                . 'CASE WHEN %s = %s THEN UTC_TIMESTAMP(6) ELSE NULL END,'
                . 'NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),1)',
            [Orders_Domain::table('pc_orders'),$orderNumber,$verified['status'],
                $verified['payment_method'],'PEN',(string) $subtotal,
                (string) $deliveryFee,(string) $total,$email ?? '','',
                $phone ?? '','', $customerRaw,$verified['quote_fingerprint'],
                $policyId,$privacyId, $deliveryRaw ?? '','',
                $verified['status'],'CONFIRMADO']
        )) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        $idRow = $tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1 !== \count($idRow)) {
            throw new Transaction_Failure('pc_orders_invariant_invalid');
        }
        $orderId = Domain_Validation::sqlId((string) $idRow[0]['id']);
        $lineIds = [];
        foreach ($lines as $line) {
            $snapshot = $line['snapshot'];
            $service = 'service' === $line['kind'];
            if (1 !== $tx->execute(
                'INSERT INTO %i (order_id,sellable_id,kind,quantity,'
                    . 'unit_price_cents,subtotal_cents,fulfillment_state,catalog_snapshot,'
                    . 'service_slot_start_utc,service_slot_end_utc,service_timezone,'
                    . 'row_version,created_at,updated_at) VALUES '
                    . '(%s,%s,%s,%d,%s,%s,%s,%s,NULLIF(%s,%s),NULLIF(%s,%s),'
                    . 'NULLIF(%s,%s),1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))',
                [Orders_Domain::table('pc_order_lines'),$orderId,
                    $line['sellable_id'],$line['kind'],$line['quantity'],
                    (string) $line['unit_price_cents'],
                    (string) Money::lineSubtotal($line['unit_price_cents'],$line['quantity']),
                    match ($line['kind']) {
                        'physical'=>'PENDIENTE','digital'=>'BLOQUEADO',
                        'service'=> 'CONFIRMADO' === $verified['status']
                            ? 'CONFIRMADA' : 'PENDIENTE_CONFIRMACION',
                    },
                    self::json($snapshot,'digital' === $line['kind'] ? 40000 : 12000),
                    $service ? Business_Time::toDatabase(
                        Business_Time::fromApi($snapshot['service_start_utc'])) : '',
                    '',
                    $service ? Business_Time::toDatabase(
                        Business_Time::fromApi($snapshot['service_end_utc'])) : '',
                    '',
                    $service ? $snapshot['service_timezone'] : '',
                    ''
                ]
            )) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            $created = $tx->rows('SELECT LAST_INSERT_ID() AS id');
            if (1 !== \count($created)) {
                throw new Transaction_Failure('pc_orders_invariant_invalid');
            }
            $lineIds[] = Domain_Validation::sqlId((string) $created[0]['id']);
        }

        // Orden E-05 de anclas: pedido -> líneas -> variantes ordenadas.
        // La raíz es nueva, pero la bloqueamos ANTES de tomar variante o
        // agenda para que Inventory_Reservations y los consumidores MP/COD
        // compartan exactamente los mismos pre-requisitos de locking.
        $tx->lockOrder($orderId);
        foreach ($lineIds as $lineId) {
            $tx->lockOrderLine($orderId,$lineId);
        }
        $sellables = \array_values(\array_unique(
            \array_column($lines,'sellable_id')
        ));
        $tx->lockSellables($sellables);
        foreach ($lines as $line) {
            self::compareWithCatalogWithin(
                $tx,$line['sellable_id'],$line['unit_price_cents'],$line['snapshot']
            );
        }

        Event_Journal::auditWithin(
            $tx,$actor,$operationId,'order',$orderId,'order_snapshot_created',
            'checkout_snapshot',null,
            \hash('sha256', $customerRaw . ':' . $orderNumber
                . ':' . $verified['quote_fingerprint'])
        );
        return ['id'=>$orderId,'order_number'=>$orderNumber,'row_version'=>'1'];
    }

    /**
     * Después de insertar pedido/líneas, el propio insertWithin bloquea
     * pedido -> líneas -> variantes; y SOLO entonces revalida la cotización
     * con filas InnoDB estables, ficha CPT y asignación digital activa.
     * Todo ocurre en la misma transacción y un fallo revierte el alta.
     */
    private static function compareWithCatalogWithin(
        Transaction_Context $tx, string $id, int $price, array $snapshot
    ): void {
        $locked = $tx->lockedSellable($id);
        if ('1' !== (string) $locked['active']
            || (string) $locked['kind'] !== (string) $snapshot['kind']) {
            throw new Transaction_Failure('pc_orders_quote_changed');
        }
        global $wpdb;
        $rows = $tx->rows(
            'SELECT s.product_post_id, s.variant_key, s.kind, s.price_cents, '
                . 's.sale_price_cents, s.sale_active, s.service_duration_minutes, '
                . 's.service_buffer_before_minutes, s.service_buffer_after_minutes, '
                . 'p.post_title FROM %i s INNER JOIN %i p ON p.ID = s.product_post_id '
                . 'WHERE s.id = %s AND p.post_type = %s AND p.post_status = %s',
            [Orders_Domain::table('pc_sellables'), $wpdb->posts,
                $id, Catalog_Controller::POST_TYPE,'publish']
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_orders_quote_changed');
        }
        $row = $rows[0];
        $effective = Money::effectiveUnitPrice(
            Money::fromApi((string) $row['price_cents']),
            null === $row['sale_price_cents'] ? null
                : Money::fromApi((string) $row['sale_price_cents']),
            '1' === (string) $row['sale_active']
        );
        if ($price !== $effective
            || (string) $row['product_post_id'] !== ($snapshot['product_id'] ?? null)
            || (string) $row['variant_key'] !== ($snapshot['variant_key'] ?? null)
            || (string) $row['post_title'] !== $snapshot['title']) {
            throw new Transaction_Failure('pc_orders_quote_changed');
        }
        if ('service' === $snapshot['kind']) {
            foreach (['service_duration_minutes','service_buffer_before_minutes',
                'service_buffer_after_minutes'] as $field) {
                if ((string) $row[$field] !== (string) $snapshot[$field]) {
                    throw new Transaction_Failure('pc_orders_quote_changed');
                }
            }
        }
        if ('digital' === $snapshot['kind']) {
            // La cotización IMP-24 firma la lista COMPLETA de slots.
            // Congelar la misma lista/versión/hash en el pedido impide
            // sustituir binarios para una venta ya pactada.
            $expected = $snapshot['files'] ?? [
                [
                    'slot_key' => $snapshot['slot_key'],
                    'file_id' => $snapshot['file_id'],
                ],
            ];
            $assigned = $tx->rows(
                'SELECT a.slot_key, a.active_file_id, a.row_version, '
                    . 'LOWER(HEX(f.content_hash)) AS sha256, f.archived_at, '
                    . 'f.owner_product_post_id '
                    . 'FROM %i a LEFT JOIN %i f '
                    . 'ON f.id = a.active_file_id AND f.sellable_id = a.sellable_id '
                    . 'WHERE a.sellable_id = %s ORDER BY a.slot_key LOCK IN SHARE MODE',
                [Orders_Domain::table('pc_digital_assignments'),
                    Orders_Domain::table('pc_digital_files'), $id]
            );
            if ([] === $expected || \count($assigned) !== \count($expected)) {
                throw new Transaction_Failure('pc_orders_quote_changed');
            }
            $bySlot = [];
            foreach ($assigned as $entry) {
                $bySlot[(string) $entry['slot_key']] = $entry;
            }
            foreach ($expected as $file) {
                $key = $file['slot_key'];
                $actual = $bySlot[$key] ?? null;
                if (!\is_array($actual)
                    || (string) $actual['active_file_id'] !== $file['file_id']
                    || null !== $actual['archived_at']
                    || (string) $actual['owner_product_post_id']
                        !== (string) $snapshot['product_id']
                    || (isset($file['row_version'])
                        && (string) $actual['row_version'] !== $file['row_version'])
                    || (isset($file['sha256'])
                        && (string) $actual['sha256'] !== $file['sha256'])) {
                    throw new Transaction_Failure('pc_orders_quote_changed');
                }
            }
        }
    }

    private static function json(array $value,int $limit=12000): string
    {
        try {
            $json = \json_encode($value,
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            if (!\is_string($json) || \strlen($json) > $limit) {
                throw new \UnexpectedValueException();
            }
            return $json;
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_orders_field_invalid');
        }
    }
}
