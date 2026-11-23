<?php
/**
 * IMP-24 — R8-05: cotización completa, pero NO reserva, NO autorización
 * de cobro y NO escritura comercial. R8-06/R8-07 volverán a calcular el
 * fingerprint bajo sus propias precondiciones, locks, versión y estado.
 *
 * Ningún precio, descuento, tarifa, stock, cita, archivo privado o política
 * se acepta como verdad desde el JSON del comprador. Los únicos inputs son
 * CartLine, Delivery y Contact de OpenAPI 0.12.0-draft.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Quote_Service
{
    /**
     * Evidencias de módulos tomadas antes de abrir el TX SQL.
     * Los gates no son selección del comprador ni se obtienen del body.
     *
     * @return array{physical:bool,digital:bool,service:bool,delivery:bool,payment_methods:list<string>}
     */
    public static function capabilities(): array
    {
        return [
            'physical' => Module_Gates::allows('inventory', 'write'),
            'digital' => Module_Gates::allows('digital', 'read')
                && Module_Gates::allows('digital', 'write'),
            'service' => Module_Gates::allows('appointments', 'read')
                && Module_Gates::allows('appointments', 'write')
                && Rest_Router::isOperationReady('R8-04'),
            'delivery' => Module_Gates::allows('delivery', 'read')
                && Module_Gates::allows('delivery', 'write')
                && Rest_Router::isOperationReady('R8-16'),
            'payment_methods' => \array_values(\array_filter(
                ['mercado_pago','cash'],
                static fn (string $method): bool =>
                    Rest_Router::isOperationReady(
                        'cash' === $method ? 'R8-07' : 'R8-06'
                    )
            )),
        ];
    }

    /** R8-05: acceso público, sin sesión comprador, carrito todavía no persistido. */
    public static function quote(array $input): array
    {
        if (!Module_Gates::allows('checkout','write')
            || !Module_Gates::allows('catalog','read')) {
            throw new Rest_Failure('pc_module_not_ready',423,true);
        }
        $request = self::normalize($input);
        $capabilities = self::capabilities();
        return Transaction::run(static fn (Transaction_Context $tx): array =>
            self::quoteWithin($tx,$request,$capabilities)
        );
    }

    /**
     * Constructor interno reutilizable por checkout (antes de hold/cobro).
     * El checkout real debe revalidar además el recurso bajo FOR UPDATE:
     * esta lectura con locks compartidos no es una reserva y el fingerprint
     * NO es prueba de fondos, stock garantizado ni cita confirmada.
     *
     * @param array<string,mixed> $request Solo entrada normalizada por ::normalize
     * @param array<string,mixed> $capabilities Verificados por ::capabilities en PHP
     * @return array<string,mixed> QuoteResponse público por defecto; con
     *   includeCheckoutSnapshot retorna quote + order + service SOLO en PHP.
     */
    public static function quoteWithin(
        Transaction_Context $tx,array $request,array $capabilities,
        bool $includeCheckoutSnapshot = false
    ): array {
        $state = Settings_Store::readWithin($tx);
        $settings = $state['settings'];
        if (null === $state['raw']
            || true !== ($settings['payment_enabled'] ?? false)
            || !\is_string($settings['business']['business_name'] ?? null)
            || '' === \trim($settings['business']['business_name'])
            || 'Comercio sin configurar' === $settings['business']['business_name']
            || !\is_string($settings['policy_version_id'] ?? null)
            || !\is_string($settings['privacy_version_id'] ?? null)) {
            throw new Transaction_Failure('pc_quote_not_ready');
        }
        $policyId = Domain_Validation::sqlId($settings['policy_version_id']);
        $privacyId = Domain_Validation::sqlId($settings['privacy_version_id']);
        $terms = Settings_Store::policyWithin($tx,$policyId,'commercial_terms');
        $privacy = Settings_Store::policyWithin($tx,$privacyId,'privacy_notice');
        if ('' === \trim($terms) || '' === \trim($privacy)
            || \mb_strlen($terms,'UTF-8') > 30000
            || \mb_strlen($privacy,'UTF-8') > 30000) {
            throw new Transaction_Failure('pc_quote_not_ready');
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        $ids = \array_column($request['lines'],'sellable_id');
        $marks = \implode(',', \array_fill(0,\count($ids),'%s'));
        $tables = Schema::tableNames($wpdb);
        $variants = $tx->rows(
            'SELECT s.id, s.product_post_id, s.variant_key, s.kind, '
                . 's.price_cents, s.sale_price_cents, s.sale_active, '
                . 's.track_stock, s.stock_on_hand, s.active, s.row_version, '
                . 's.service_duration_minutes, s.service_buffer_before_minutes, '
                . 's.service_buffer_after_minutes, p.post_title '
                . 'FROM %i s INNER JOIN %i p ON p.ID = s.product_post_id '
                . 'WHERE s.id IN ('.$marks.') AND s.active = 1 '
                . 'AND p.post_type = %s AND p.post_status = %s '
                . 'ORDER BY s.id ASC LOCK IN SHARE MODE',
            \array_merge(
                [$tables['pc_sellables'],$wpdb->posts],$ids,
                [Catalog_Controller::POST_TYPE,'publish']
            )
        );
        if (\count($variants) !== \count($ids)) {
            throw new Transaction_Failure('pc_quote_unavailable');
        }
        $labels = self::labelsWithin($tx,$variants);

        $byId = [];
        $physical = [];
        $digital = [];
        $service = null;
        $kinds = [];
        $normalized = [];
        $prices = [];
        foreach ($variants as $row) {
            $id = Domain_Validation::sqlId((string) $row['id']);
            $kind = Domain_Validation::sellableKind((string) $row['kind']);
            if (isset($byId[$id])
                || '1' !== (string) $row['active']
                || !\is_string($row['post_title'])
                || '' === \trim($row['post_title'])
                || !\mb_check_encoding($row['post_title'],'UTF-8')
                || \mb_strlen($row['post_title'],'UTF-8') > 200
                || \preg_match('/[\x00-\x1F\x7F<>]/',$row['post_title'])
                || !\in_array((string) $row['sale_active'],['0','1'],true)) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            $pid = Domain_Validation::sqlId((string) $row['product_post_id']);
            $title = (string) $row['post_title'];
            if ('base' !== (string) $row['variant_key']) {
                $label = $labels[$pid][$id] ?? null;
                if (!\is_string($label) || '' === \trim($label)
                    || !\mb_check_encoding($label,'UTF-8')
                    || \mb_strlen($label,'UTF-8') > 180) {
                    throw new Transaction_Failure('pc_quote_invariant_invalid');
                }
                $separator = ' · ';
                $availableLength = 200 - \mb_strlen($label,'UTF-8')
                    - \mb_strlen($separator,'UTF-8');
                if ($availableLength < 1) {
                    throw new Transaction_Failure('pc_quote_invariant_invalid');
                }
                $title = \mb_substr($title,0,$availableLength,'UTF-8')
                    . $separator . $label;
            }
            $row['quote_title'] = $title;
            $byId[$id] = $row;
            try {
                $unit = Money::effectiveUnitPrice(
                    Money::fromApi((string) $row['price_cents']),
                    null === $row['sale_price_cents'] ? null
                        : Money::fromApi((string) $row['sale_price_cents']),
                    '1' === (string) $row['sale_active']
                );
            } catch (\Throwable $ignored) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            $prices[$id] = $unit;
            $kinds[$id] = $kind;
            if ('physical' === $kind) {
                $physical[$id] = $row;
            } elseif ('digital' === $kind) {
                $digital[] = $id;
            } elseif ('service' === $kind) {
                if (null !== $service) {
                    throw new Rest_Failure('pc_field_invalid',422,false,'lines');
                }
                $service = $id;
            }
        }
        $requested = [];
        foreach ($request['lines'] as $line) {
            $id = $line['sellable_id'];
            if (!isset($byId[$id])) {
                throw new Transaction_Failure('pc_quote_unavailable');
            }
            if ('service' === $kinds[$id] && 1 !== $line['quantity']
                || 'service' !== $kinds[$id] && isset($line['slot'])) {
                throw new Rest_Failure('pc_field_invalid',422,false,'lines.slot');
            }
            if ('service' === $kinds[$id] && !isset($line['slot'])) {
                throw new Rest_Failure('pc_field_invalid',422,false,'lines.slot');
            }
            $requested[$id] = $line['quantity'];
            $normalized[$id] = $line;
        }
        // R8-05 nunca acepta una variante duplicada: en lugar de sumar
        // 2 líneas de la misma variante por conveniencia, el frontend
        // debe enviar una sola cantidad efectiva ya normalizada.
        if (\count($requested) !== \count($request['lines'])) {
            throw new Rest_Failure('pc_field_invalid',422,false,'lines');
        }

        foreach (['physical'=>[] !== $physical,
            'digital'=>[] !== $digital,'service'=>null !== $service] as $kind=>$used) {
            if ($used && true !== $capabilities[$kind]) {
                throw new Transaction_Failure('pc_quote_not_ready');
            }
        }
        try {
            Domain_Validation::contactForKinds(
                \array_values($kinds),
                $request['contact']['email'] ?? null,
                $request['contact']['phone'] ?? null
            );
        } catch (\Throwable $ignored) {
            throw new Rest_Failure('pc_field_invalid',422,false,'contact');
        }
        $delivery = $request['delivery'];
        if ([] === $physical && 'none' !== $delivery['mode']
            || [] !== $physical && !\in_array($delivery['mode'],
                ['pickup','delivery'],true)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'delivery.mode');
        }

        $deliveryFee = 0;
        $coverage = null;
        if ('delivery' === $delivery['mode']) {
            if (true !== $capabilities['delivery']
                || true !== ($settings['delivery_enabled'] ?? false)) {
                throw new Transaction_Failure('pc_quote_not_ready');
            }
            $coverage = Delivery_Coverage::feeWithin($tx,$delivery['ubigeo_code']);
            $deliveryFee = Money::fromApi($coverage['fee_cents']);
        }

        if ([] !== $physical) {
            Quote_Availability::physicalWithin($tx,$byId,
                \array_intersect_key($requested,$physical));
        }
        $digitalFiles = [] === $digital ? []
            : Quote_Availability::digitalWithin($tx,$digital);
        $slot = null;
        if (null !== $service) {
            $slot = Quote_Availability::serviceWithin(
                $tx,$byId[$service],$normalized[$service]['slot']
            );
        }

        $subtotals = [];
        $quoteLines = [];
        $fingerprintLines = [];
        foreach ($request['lines'] as $line) {
            $id = $line['sellable_id'];
            $row = $byId[$id];
            $kind = $kinds[$id];
            $price = $prices[$id];
            $subtotal = Money::lineSubtotal($price,$line['quantity']);
            $subtotals[] = $subtotal;
            $quoteLines[] = [
                'sellable_id'=>$id,
                'quantity'=>$line['quantity'],
                'title'=>(string) $row['quote_title'],
                'unit_price_cents'=>Money::toApi($price),
                'subtotal_cents'=>Money::toApi($subtotal),
            ];
            $fingerprintLines[] = [
                'sellable_id'=>$id,
                'product_id'=>Domain_Validation::sqlId((string)$row['product_post_id']),
                'variant_key'=>(string) $row['variant_key'],
                'title'=>(string) $row['quote_title'],
                'source_title'=>(string) $row['post_title'],
                'kind'=>$kind,
                'row_version'=>Domain_Validation::sqlId((string)$row['row_version']),
                'quantity'=>$line['quantity'],
                'price_cents'=>Money::toApi($price),
                'subtotal_cents'=>Money::toApi($subtotal),
                'digital_files'=>$digitalFiles[$id] ?? [],
                'slot'=>'service' === $kind ? $slot : null,
            ];
        }
        $subtotal = Money::orderTotal($subtotals,0);
        $total = Money::orderTotal($subtotals,$deliveryFee);
        $readyToPay = $capabilities['payment_methods'];
        $warnings = ['La cotización no reserva productos ni citas.'];
        if ([] === $readyToPay) {
            $warnings[] = 'No hay medios de pago disponibles para confirmar la compra.';
        }
        if ([] !== $digital && \in_array('cash',$readyToPay,true)) {
            $warnings[] = 'El pago en efectivo no está permitido con productos digitales.';
        }
        if (null !== $service && 'delivery' === $delivery['mode']
            && \in_array('cash',$readyToPay,true)) {
            $warnings[] = 'La entrega física y el servicio en local requieren un pago único verificable.';
        }
        $stamp = [
            'version'=>'pc-quote-v1',
            'site'=>(string) \get_current_blog_id(),
            'currency'=>'PEN',
            'catalog'=>$fingerprintLines,
            'contact'=>$request['contact'],
            'delivery'=>[
                'mode'=>$delivery['mode'],
                'ubigeo_code'=>$coverage['ubigeo_code'] ?? null,
                'address'=>$delivery['address'] ?? null,
                'reference'=>$delivery['reference'] ?? null,
                // La coordenada NO es autoridad del importe; no se firma
                // como cobertura y puede omitirse sin alterar el precio.
                'fee_cents'=>Money::toApi($deliveryFee),
                'coverage_version'=>$coverage['coverage_version'] ?? null,
                'dataset_version'=>$coverage['dataset_version'] ?? null,
            ],
            'subtotal_cents'=>Money::toApi($subtotal),
            'total_cents'=>Money::toApi($total),
            'policy_version'=>$policyId,
            'privacy_version'=>$privacyId,
            'policy_sha256'=>\hash('sha256',$terms),
            'privacy_sha256'=>\hash('sha256',$privacy),
        ];
        try {
            $encoded = \json_encode($stamp,
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE
                | \JSON_UNESCAPED_SLASHES);
            $secret = \wp_salt('auth');
            if (!\is_string($encoded) || !\is_string($secret)
                || \strlen($secret) < 32) {
                throw new \UnexpectedValueException();
            }
            $fingerprint = \hash_hmac('sha256',$encoded,$secret);
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_quote_invariant_invalid');
        }

        $response = [
            'quote_fingerprint'=>$fingerprint,
            'currency'=>'PEN',
            'lines'=>$quoteLines,
            'subtotal_cents'=>Money::toApi($subtotal),
            'delivery_cents'=>Money::toApi($deliveryFee),
            'total_cents'=>Money::toApi($total),
            'requires_confirmation'=>true,
            'warnings'=>$warnings,
            'policy_version'=>$policyId,
            'privacy_version'=>$privacyId,
            'policy_notice'=>$terms,
            'privacy_notice'=>$privacy,
        ];
        if (!$includeCheckoutSnapshot) {
            return $response;
        }

        // Únicamente para el coordinador PHP, jamás para R8-05. Los valores
        // de esta estructura proceden de la misma cotización HMAC y DB.
        $preparedLines = [];
        foreach ($fingerprintLines as $entry) {
            $snap = [
                'title'=>$entry['source_title'],
                'kind'=>$entry['kind'],
                'product_id'=>$entry['product_id'],
                'variant_key'=>$entry['variant_key'],
            ];
            if ('digital' === $entry['kind']) {
                $snap['files'] = $entry['digital_files'];
            } elseif ('service' === $entry['kind']) {
                $source = $byId[$entry['sellable_id']];
                $snap['service_duration_minutes'] = (int) $source['service_duration_minutes'];
                $snap['service_buffer_before_minutes'] = (int) $source['service_buffer_before_minutes'];
                $snap['service_buffer_after_minutes'] = (int) $source['service_buffer_after_minutes'];
                $snap['service_start_utc'] = $entry['slot']['starts_at_utc'];
                $snap['service_end_utc'] = $entry['slot']['ends_at_utc'];
                $snap['service_timezone'] = $entry['slot']['timezone'];
            }
            $preparedLines[] = [
                'sellable_id'=>$entry['sellable_id'],
                'quantity'=>$entry['quantity'],
                'unit_price_cents'=>$entry['price_cents'],
                'catalog_snapshot'=>$snap,
            ];
        }
        $deliverySnapshot = [
            'mode'=>$delivery['mode'],
            'fee_cents'=>Money::toApi($deliveryFee),
        ];
        if (null !== $coverage) {
            $deliverySnapshot += [
                'ubigeo_code'=>$coverage['ubigeo_code'],
                'address'=>$delivery['address'],
                'coverage_version'=>$coverage['coverage_version'],
                'dataset_version'=>$coverage['dataset_version'],
            ];
        }
        $serviceSnapshot = null;
        if (null !== $service) {
            $serviceSnapshot = [
                'sellable_id'=>$service,
                'slot'=>$slot,
                'before_minutes'=>(int) $byId[$service]['service_buffer_before_minutes'],
                'after_minutes'=>(int) $byId[$service]['service_buffer_after_minutes'],
            ];
        }
        return [
            'quote'=>$response,
            'order'=>[
                'currency'=>'PEN',
                'buyer_email'=>$request['contact']['email'] ?? null,
                'buyer_phone'=>$request['contact']['phone'] ?? null,
                'recipient'=>$request['contact']['display_name'] ?? null,
                'policy_version_id'=>$policyId,
                'privacy_version_id'=>$privacyId,
                'quote_fingerprint'=>$fingerprint,
                'delivery'=>$deliverySnapshot,
                'lines'=>$preparedLines,
            ],
            'service'=>$serviceSnapshot,
        ];
    }

    /**
     * Etiquetas públicas por variante; las mismas se usan en catálogo.
     * Una sola lectura compartida de postmeta, sin N+1 ni precio inferido
     * del producto padre. Las etiquetas NO son claves de un archivo.
     *
     * @param list<array<string,mixed>> $variants
     * @return array<string,array<string,string>>
     */
    private static function labelsWithin(
        Transaction_Context $tx,array $variants
    ): array {
        global $wpdb;
        $productIds = [];
        foreach ($variants as $v) {
            $id = Domain_Validation::sqlId((string) $v['product_post_id']);
            $productIds[$id] = $id;
        }
        $ids = \array_values($productIds);
        $marks = \implode(',',\array_fill(0,\count($ids),'%s'));
        $rows = $tx->rows(
            'SELECT post_id, meta_value FROM %i WHERE post_id IN ('
                . $marks . ') AND meta_key = %s LOCK IN SHARE MODE',
            \array_merge([$wpdb->postmeta],$ids,['_pc_variant_labels'])
        );
        $result = [];
        foreach ($rows as $row) {
            $id = Domain_Validation::sqlId((string) $row['post_id']);
            if (isset($result[$id])) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            try {
                $labels = \json_decode((string) $row['meta_value'],
                    true,16,\JSON_THROW_ON_ERROR);
            } catch (\Throwable $ignored) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            if (!\is_array($labels) || \array_is_list($labels) && [] !== $labels
                || \count($labels) > 100) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
            foreach ($labels as $key=>$value) {
                if (!\is_string($value) || '' === \trim($value)
                    || !\mb_check_encoding($value,'UTF-8')
                    || \mb_strlen($value,'UTF-8') > 180
                    || \preg_match('/[\x00-\x1F\x7F<>]/',$value)) {
                    throw new Transaction_Failure('pc_quote_invariant_invalid');
                }
            }
            $result[$id] = $labels;
        }
        // IMP-15 crea _pc_variant_labels también para producto simple.
        foreach ($ids as $id) {
            if (!\array_key_exists($id,$result)) {
                throw new Transaction_Failure('pc_quote_invariant_invalid');
            }
        }
        return $result;
    }

    /**
     * Normalización determinista para huella idéntica sin depender del
     * orden del carrito. No confundir firma con autorización del checkout.
     */
    public static function normalize(array $input): array
    {
        $lines = $input['lines'] ?? null;
        if (!\is_array($lines) || !\array_is_list($lines)
            || [] === $lines || \count($lines) > 100) {
            throw new Rest_Failure('pc_field_invalid',422,false,'lines');
        }
        $output = [];
        foreach ($lines as $line) {
            if (!\is_array($line) || !\is_string($line['sellable_id'] ?? null)
                || !\is_int($line['quantity'] ?? null)
                || [] !== \array_diff(\array_keys($line),
                    ['sellable_id','quantity','slot'])) {
                throw new Rest_Failure('pc_field_invalid',422,false,'lines');
            }
            try {
                $id = Domain_Validation::sqlId($line['sellable_id']);
                Money::requireCartQuantity($line['quantity']);
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_field_invalid',422,false,'lines');
            }
            if (isset($output[$id])) {
                throw new Rest_Failure('pc_field_invalid',422,false,'lines');
            }
            $entry = ['sellable_id'=>$id,'quantity'=>$line['quantity']];
            if (isset($line['slot'])) {
                if (!\is_array($line['slot'])) {
                    throw new Rest_Failure('pc_field_invalid',422,false,'lines.slot');
                }
                $entry['slot']=$line['slot'];
            }
            $output[$id] = $entry;
        }
        \uksort($output,static fn (int|string $a,int|string $b): int =>
            Transaction_Context::compareId((string) $a,(string) $b)
        );

        $contact = $input['contact'] ?? [];
        if (!\is_array($contact)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'contact');
        }
        $safeContact = [];
        foreach (['email','phone','display_name'] as $key) {
            if (!\array_key_exists($key,$contact)) {
                continue;
            }
            if (!\is_string($contact[$key])) {
                throw new Rest_Failure('pc_field_invalid',422,false,'contact');
            }
            try {
                $safeContact[$key] = match ($key) {
                    'email'=>Domain_Validation::email($contact[$key]),
                    'phone'=>Domain_Validation::phone($contact[$key]),
                    'display_name'=>Domain_Validation::displayName($contact[$key]),
                };
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_field_invalid',422,false,'contact');
            }
        }
        if (\count($safeContact) !== \count($contact)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'contact');
        }

        $delivery = $input['delivery'] ?? ['mode'=>'none'];
        if (!\is_array($delivery)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'delivery');
        }
        Bootstrap::loadMapAdapter();
        $delivery = Delivery_Location::normalize($delivery);

        return [
            'lines'=>\array_values($output),
            'contact'=>$safeContact,
            'delivery'=>$delivery,
        ];
    }
}
