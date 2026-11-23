<?php
/**
 * IMP-16 — única proyección pública R8-02/R8-03 y HTML de WordPress.
 *
 * Filtra en SQL fichas publicadas y vendibles activos. Nunca retorna SKU,
 * existencia exacta, metadatos privados, rutas de archivos ni permisos.
 * Precio y variante se leen de pc_sellables; PHP y REST consumen la misma vista.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Catalog_Public_Service
{
    private const LABEL_META = '_pc_variant_labels';

    /** @param array<string,mixed> $query */
    public static function listing(array $query): array
    {
        self::requireReady();
        $window = Rest_Pagination::window($query);
        $ordering = self::checkoutReady();
        $physicalReady = $ordering && Module_Gates::allows('inventory', 'write');
        if ($physicalReady) {
            Bootstrap::loadInventory();
        }

        return Transaction::run(static function (Transaction_Context $tx) use (
            $window, $ordering, $physicalReady
        ): array {
            $db = self::database();
            $table = self::sellablesTable($db);
            // EXISTS descarta una ficha sin ninguna variante activa ANTES
            // de paginar; no cuenta borradores, ocultos o variantes retiradas.
            $posts = $tx->rows(
                'SELECT p.ID, p.post_title, p.post_content FROM %i p '
                    . 'WHERE p.post_type = %s AND p.post_status = %s '
                    . 'AND EXISTS (SELECT 1 FROM %i s '
                    . 'WHERE s.product_post_id = p.ID AND s.active = 1) '
                    . 'ORDER BY p.ID DESC LIMIT %d OFFSET %d LOCK IN SHARE MODE',
                [$db->posts, Catalog_Controller::POST_TYPE, 'publish',
                    $table, $window['fetch'], $window['offset']]
            );
            $items = self::projectWithin($tx, $posts, $ordering, $physicalReady);
            return Rest_Pagination::envelope($items, $window);
        });
    }

    /** R8-03. El id corresponde al post WP, no a un sellable. */
    public static function detail(string $id): array
    {
        $id = Domain_Validation::sqlId($id);
        self::requireReady();
        $ordering = self::checkoutReady();
        $physicalReady = $ordering && Module_Gates::allows('inventory', 'write');
        if ($physicalReady) {
            Bootstrap::loadInventory();
        }

        return Transaction::run(static function (Transaction_Context $tx) use (
            $id, $ordering, $physicalReady
        ): array {
            $db = self::database();
            $posts = $tx->rows(
                'SELECT p.ID, p.post_title, p.post_content FROM %i p '
                    . 'WHERE p.ID = %s AND p.post_type = %s AND p.post_status = %s '
                    . 'AND EXISTS (SELECT 1 FROM %i s '
                    . 'WHERE s.product_post_id = p.ID AND s.active = 1) LIMIT 1 LOCK IN SHARE MODE',
                [$db->posts, $id, Catalog_Controller::POST_TYPE, 'publish',
                    self::sellablesTable($db)]
            );
            if ([] === $posts) {
                throw new Transaction_Failure('pc_catalog_not_found');
            }
            $products = self::projectWithin($tx, $posts, $ordering, $physicalReady);
            if (1 !== \count($products)) {
                throw new Transaction_Failure('pc_catalog_public_invalid');
            }
            return $products[0];
        });
    }

    /**
     * Si no existen las rutas de cotización/cobro, policies o módulos listos,
     * can_order es false; una cantidad en stock no equivale a una reserva.
     * No llamar a Settings_Service dentro de una transacción de catálogo.
     */
    private static function checkoutReady(): bool
    {
        if (!Module_Gates::allows('checkout', 'write')
            || !Rest_Router::isOperationReady('R8-05')
            || (!Rest_Router::isOperationReady('R8-06')
                && !Rest_Router::isOperationReady('R8-07'))) {
            return false;
        }
        try {
            // Reusar el gate contractual de IMP-14: además de rutas y textos
            // comprueba payment_enabled sin inferir compra desde un toggle.
            Bootstrap::loadBootstrapApi();
            $bootstrap = Bootstrap_Api_Service::publicResponse();
            return [] !== ($bootstrap['payment_methods'] ?? []);
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    private static function requireReady(): void
    {
        if (!\post_type_exists(Catalog_Controller::POST_TYPE)
            || !Module_Gates::allows('catalog', 'read')) {
            throw new Rest_Failure('pc_module_not_ready', 423, true);
        }
    }

    /**
     * Dos consultas por lote con ids validados de la primera, sin N+1.
     * El post público no hereda precio del padre: el representativo se
     * elige de los vendibles activos y cada variante conserva su importe.
     *
     * @param list<array<string,mixed>> $posts
     * @return list<array<string,mixed>>
     */
    private static function projectWithin(
        Transaction_Context $tx, array $posts, bool $ordering,
        bool $physicalReady
    ): array {
        if ([] === $posts) {
            return [];
        }
        $db = self::database();
        $ids = [];
        foreach ($posts as $post) {
            $ids[] = Domain_Validation::sqlId((string) $post['ID']);
        }
        $placeholders = \implode(',', \array_fill(0, \count($ids), '%s'));

        $rows = $tx->rows(
            'SELECT id, product_post_id, variant_key, kind, price_cents, '
                . 'sale_price_cents, sale_active, active, track_stock, stock_on_hand, '
                . 'service_duration_minutes '
                . 'FROM %i WHERE product_post_id IN (' . $placeholders . ') '
                . 'ORDER BY product_post_id, id LOCK IN SHARE MODE',
            \array_merge([self::sellablesTable($db)], $ids)
        );
        $grouped = [];
        $digitalIds = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['product_post_id']][] = $row;
            if ($ordering && 'digital' === $row['kind']
                && '1' === (string) $row['active']) {
                $digitalIds[] = Domain_Validation::sqlId((string) $row['id']);
            }
        }
        // Mismo orden de locks que un PATCH: post -> vendibles -> meta.
        // Bajo READ COMMITTED, el HTML no debe mezclar una ficha publicada
        // con variantes modificadas en otra transacción.
        $rawLabels = $tx->rows(
            'SELECT post_id, meta_value FROM %i '
                . 'WHERE post_id IN (' . $placeholders . ') AND meta_key = %s '
                . 'LOCK IN SHARE MODE',
            \array_merge([$db->postmeta], $ids, [self::LABEL_META])
        );
        $labels = [];
        foreach ($rawLabels as $entry) {
            $postId = (string) $entry['post_id'];
            if (isset($labels[$postId])) {
                throw new Transaction_Failure('pc_catalog_public_invalid');
            }
            $labels[$postId] = self::labels((string) $entry['meta_value']);
        }

        $assignments = [];
        // Solo las asignaciones activas a archivos privados vigentes son
        // vendibles; una configuración genérica de digital no es suficiente.
        if ([] !== $digitalIds && Module_Gates::allows('digital', 'read')
            && Module_Gates::allows('digital', 'write')) {
            $markers = \implode(',', \array_fill(0, \count($digitalIds), '%s'));
            $names = Schema::tableNames($db);
            $records = $tx->rows(
                'SELECT DISTINCT a.sellable_id FROM %i a INNER JOIN %i f '
                    . 'ON f.id = a.active_file_id AND f.sellable_id = a.sellable_id '
                    . 'WHERE a.sellable_id IN (' . $markers . ') '
                    . 'AND f.archived_at IS NULL LOCK IN SHARE MODE',
                \array_merge([
                    $names['pc_digital_assignments'], $names['pc_digital_files'],
                ], $digitalIds)
            );
            foreach ($records as $record) {
                $assignments[(string) $record['sellable_id']] = true;
            }
        }

        $physicalAvailable = [];
        if ($physicalReady) {
            // IMP-17: las filas vendibles ya están bloqueadas en modo
            // compartido. La elegibilidad se calcula en lote sin consultar
            // opciones/env WordPress durante la transacción.
            $physicalAvailable = Inventory_Balance::publicAvailabilityWithin($tx, $rows);
        }

        $result = [];
        foreach ($posts as $post) {
            $id = (string) $post['ID'];
            $variants = $grouped[$id] ?? [];
            if (!isset($labels[$id]) || [] === $variants || \count($variants) > 100) {
                throw new Transaction_Failure('pc_catalog_public_invalid');
            }
            $kind = (string) $variants[0]['kind'];
            if (!\in_array($kind, ['physical', 'digital', 'service'], true)) {
                throw new Transaction_Failure('pc_catalog_public_invalid');
            }
            $isSimple = 1 === \count($variants)
                && 'base' === (string) $variants[0]['variant_key'];
            if ($isSimple && [] !== $labels[$id]) {
                throw new Transaction_Failure('pc_catalog_public_invalid');
            }

            $publicVariants = [];
            $chosen = null;
            $minimum = null;
            $orderable = false;
            foreach ($variants as $row) {
                $variantId = Domain_Validation::sqlId((string) $row['id']);
                if ($row['kind'] !== $kind
                    || (!$isSimple && 'base' === $row['variant_key'])) {
                    throw new Transaction_Failure('pc_catalog_public_invalid');
                }
                if (!$isSimple && !isset($labels[$id][$variantId])) {
                    throw new Transaction_Failure('pc_catalog_public_invalid');
                }
                if ('1' !== (string) $row['active']) {
                    continue;
                }
                $price = (string) $row['price_cents'];
                $sale = null === $row['sale_price_cents']
                    ? null : (string) $row['sale_price_cents'];
                $regular = Money::fromApi($price);
                $offer = null === $sale ? null : Money::fromApi($sale);
                $saleActive = '1' === (string) $row['sale_active'];
                if ($saleActive && (null === $offer || $offer > $regular || $offer <= 0)) {
                    // No corregir ofertas inválidas como si fueran ofertas válidas.
                    throw new Transaction_Failure('pc_catalog_public_invalid');
                }
                $effective = $saleActive ? $offer : $regular;
                $canOrder = self::variantOrderable(
                    $row, $kind, $effective, $ordering,
                    true === ($assignments[$variantId] ?? false),
                    true === ($physicalAvailable[$variantId] ?? false)
                );
                $entry = [
                    'id' => $variantId,
                    'label' => $isSimple ? 'Presentación única' : $labels[$id][$variantId],
                    'price_cents' => $price,
                    'sale_active' => $saleActive,
                    'can_order' => $canOrder,
                ];
                // Una oferta desactivada es información interna, aunque
                // exista en la variante. El público ve solo ofertas vigentes.
                if ($saleActive && null !== $sale) {
                    $entry['sale_price_cents'] = $sale;
                }
                $publicVariants[] = $entry;
                // El precio representativo pertenece a la variante elegible
                // más económica. Solo si ninguna puede cotizarse se muestra
                // el mínimo informativo sin sugerir compra disponible.
                if (null === $chosen
                    || ($canOrder && !$orderable)
                    || ($canOrder === $orderable
                        && $canOrder === $chosen['can_order']
                        && $effective < $minimum)) {
                    $minimum = $effective;
                    $chosen = $entry;
                }
                $orderable = $orderable || $canOrder;
            }
            if ([] === $publicVariants || null === $chosen) {
                // Una ficha que dejó de tener variantes visibles no es pública.
                throw new Transaction_Failure('pc_catalog_public_invalid');
            }
            if (!$isSimple && \count($labels[$id]) !== \count($variants)) {
                throw new Transaction_Failure('pc_catalog_public_invalid');
            }
            $title = \sanitize_text_field((string) $post['post_title']);
            $description = \wp_kses_post((string) $post['post_content']);
            if ('' === \trim($title) || \mb_strlen($title, 'UTF-8') > 240
                || \mb_strlen($description, 'UTF-8') > 20000) {
                throw new Transaction_Failure('pc_catalog_public_invalid');
            }
            $item = [
                'id' => $id,
                'title' => $title,
                'kind' => $kind,
                'price_cents' => $chosen['price_cents'],
                'sale_active' => $chosen['sale_active'],
                'can_order' => $orderable,
                'variants' => $publicVariants,
            ];
            if ('' !== $description) {
                $item['description'] = $description;
            }
            if (isset($chosen['sale_price_cents'])) {
                $item['sale_price_cents'] = $chosen['sale_price_cents'];
            }
            $result[] = $item;
        }
        return $result;
    }

    private static function variantOrderable(
        array $row, string $kind, int $effective, bool $checkout,
        bool $hasActiveDigitalFile, bool $physicalAvailable
    ): bool {
        if (!$checkout || $effective <= 0) {
            return false;
        }
        return match ($kind) {
            'physical' => $physicalAvailable,
            'digital' => $hasActiveDigitalFile
                && Module_Gates::allows('digital', 'read')
                && Module_Gates::allows('digital', 'write'),
            'service' => Module_Gates::allows('appointments', 'write')
                && Rest_Router::isOperationReady('R8-04')
                && null !== $row['service_duration_minutes']
                && (int) $row['service_duration_minutes'] > 0,
            default => false,
        };
    }

    /** Etiquetas por ID real del vendible, no por índice ni entrada del cliente. */
    private static function labels(string $json): array
    {
        try {
            $decoded = \json_decode($json, false, 18, \JSON_THROW_ON_ERROR);
            if (!$decoded instanceof \stdClass) {
                throw new \UnexpectedValueException();
            }
            $out = [];
            foreach (\get_object_vars($decoded) as $id => $label) {
                $id = Domain_Validation::sqlId((string) $id);
                if (!\is_string($label) || '' === \trim($label)
                    || \mb_strlen($label, 'UTF-8') > 180) {
                    throw new \UnexpectedValueException();
                }
                $out[$id] = \sanitize_text_field($label);
                if ('' === $out[$id]) {
                    throw new \UnexpectedValueException();
                }
            }
            return $out;
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_catalog_public_invalid');
        }
    }

    private static function database(): \wpdb
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        return $wpdb;
    }

    private static function sellablesTable(\wpdb $db): string
    {
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        $names = Schema::tableNames($db);
        return $names['pc_sellables'];
    }
}
