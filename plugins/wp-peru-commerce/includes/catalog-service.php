<?php
/**
 * IMP-15 — catálogo de administración; WordPress almacena las fichas y
 * pc_sellables es la única fuente de precios, variantes y existencias.
 *
 * Todas las escrituras de post, metadatos, vendibles, stock inicial,
 * idempotencia y auditoría comparten una conexión/transacción InnoDB.
 * Se evita wp_insert_post/wp_update_post dentro de una transacción: sus
 * hooks y cambios de caché no serían reversibles. La indexación pública
 * y los hooks de presentación corresponden a IMP-16.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Catalog_Service
{
    private const META_VERSION = '_pc_catalog_version';
    private const META_LABELS = '_pc_variant_labels';
    private const STATUSES = ['draft' => 'draft', 'hidden' => 'private', 'published' => 'publish'];

    /**
     * Las fichas usan las tablas nativas WordPress dentro del mismo COMMIT
     * que pc_sellables. No asumir que posts/postmeta son transaccionales
     * solo porque las 39 tablas PeruCommerce y options sean InnoDB.
     */
    public static function wordpressTablesTransactional(): bool
    {
        $db = self::db();
        $database = null;
        $suppressed = $db->suppress_errors(true);
        $shown = $db->hide_errors();
        try {
            $database = $db->get_var('SELECT DATABASE()');
            if (!\is_string($database) || '' === $database) {
                return false;
            }
            $sql = $db->prepare(
                'SELECT TABLE_NAME AS table_name, ENGINE AS engine '
                    . 'FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s '
                    . 'AND TABLE_NAME IN (%s, %s)',
                $database, $db->posts, $db->postmeta
            );
            if (!\is_string($sql)) {
                return false;
            }
            $rows = $db->get_results($sql);
            if (!\is_array($rows) || 2 !== \count($rows) || '' !== $db->last_error) {
                return false;
            }
            $found = [];
            foreach ($rows as $row) {
                if (!\is_object($row) || !\is_string($row->table_name ?? null)
                    || !\in_array($row->table_name, [$db->posts, $db->postmeta], true)
                    || 'InnoDB' !== ($row->engine ?? null)
                    || isset($found[$row->table_name])) {
                    return false;
                }
                $found[$row->table_name] = true;
            }
            return isset($found[$db->posts], $found[$db->postmeta]);
        } catch (\Throwable $ignored) {
            return false;
        } finally {
            $db->suppress_errors($suppressed);
            if ($shown) {
                $db->show_errors(true);
            }
        }
    }

    public static function exists(string $postId): bool
    {
        $postId = Domain_Validation::sqlId($postId);
        return Transaction::run(static function (Transaction_Context $tx) use ($postId): bool {
            $db = self::db();
            return 1 === \count($tx->rows(
                'SELECT ID FROM %i WHERE ID = %s AND post_type = %s',
                [$db->posts, $postId, Catalog_Controller::POST_TYPE]
            ));
        });
    }

    /** R8-31: la paginación nunca mezcla fichas de otro blog/sitio WP. */
    public static function listing(array $query): array
    {
        self::requireStaff('perucommerce_catalog_edit');
        $window = Rest_Pagination::window($query);
        $state = $query['state'] ?? null;
        if (null !== $state && !\array_key_exists($state, self::STATUSES)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }

        return Transaction::run(static function (Transaction_Context $tx) use ($window, $state): array {
            $db = self::db();
            $sql = 'SELECT ID, post_title, post_content, post_status FROM %i '
                . 'WHERE post_type = %s AND post_status ';
            $args = [$db->posts, Catalog_Controller::POST_TYPE];
            if (null === $state) {
                $sql .= 'IN (%s, %s, %s)';
                \array_push($args, 'draft', 'private', 'publish');
            } else {
                $sql .= '= %s';
                $args[] = self::STATUSES[$state];
            }
            $sql .= ' ORDER BY ID DESC LIMIT %d OFFSET %d LOCK IN SHARE MODE';
            $args[] = $window['fetch'];
            $args[] = $window['offset'];
            $posts = $tx->rows($sql, $args);
            if ([] === $posts) {
                return Rest_Pagination::envelope([], $window);
            }

            $views = self::viewsWithin($tx, $posts);
            return Rest_Pagination::envelope($views, $window);
        });
    }

    /** R8-32: por contrato todas las altas nacen como borrador no público. */
    public static function create(array $body): array
    {
        $actor = self::requireStaff('perucommerce_catalog_edit');
        $kind = self::kind($body['kind'] ?? null);
        $title = self::cleanTitle($body['title'] ?? null, true);
        $description = self::cleanDescription($body['description'] ?? '');
        $specs = [];
        $variable = \count($body['variants'] ?? []) > 0;
        if ($variable) {
            self::forbidParentSellableFields($body);
            foreach ($body['variants'] as $item) {
                $specs[] = self::variantSpec($item, $kind, null, null);
            }
            self::uniqueLabels($specs);
        } else {
            $specs[] = self::variantSpec($body, $kind, null, 'base');
        }
        $key = self::key($body);
        $payload = $body;
        unset($payload['idempotency_key']);
        $scope = self::scope('catalog', '0', 'catalog_create');

        $result = Transaction::run(static function (Transaction_Context $tx) use (
            $actor, $scope, $key, $payload, $title, $description, $kind, $specs, $variable
        ): array {
            $claim = Idempotency::claimWithin($tx, $scope, 'catalog_create', $key, $payload);
            if ('replay' === $claim['status']) {
                if ('catalog' !== $claim['entity_type'] || !\is_string($claim['entity_id'])) {
                    throw new Transaction_Failure('pc_idempotency_result_invalid');
                }
                $view = self::oneWithin($tx, $claim['entity_id']);
                if ('1' !== $view['row_version']) {
                    throw new Transaction_Failure('pc_version_conflict');
                }
                return ['id' => $claim['entity_id'], 'view' => $view];
            }
            self::requireNew($claim);
            $postId = self::insertPost($tx, $title, $description);
            $labels = [];

            foreach ($specs as $spec) {
                $variantId = self::insertVariant($tx, $postId, $kind, $spec, $actor, $claim['operation_id']);
                if ($variable) {
                    $labels[$variantId] = $spec['label'];
                }
            }
            self::putMeta($tx, $postId, self::META_VERSION, '1');
            self::putMeta($tx, $postId, self::META_LABELS, self::encodeLabels($labels));
            $after = self::oneWithin($tx, $postId);
            Event_Journal::auditWithin(
                $tx, $actor, $claim['operation_id'], 'catalog', $postId, 'catalog_created',
                null, null, self::catalogDigest($after)
            );
            Idempotency::completeLocalWithin($tx, $claim['operation_id'], 'catalog', $postId, 201);
            return ['id' => $postId, 'view' => $after];
        }, true);

        self::invalidatePost($result['id']);
        return $result['view'];
    }

    /** R8-33: PATCH de campos presentes, sin reemplazo destructivo del array. */
    public static function patch(string $postId, array $body): array
    {
        $actor = self::requireStaff('perucommerce_catalog_edit');
        $postId = Domain_Validation::sqlId($postId);
        $expected = self::expected($body);
        $key = self::key($body);
        $payload = $body;
        unset($payload['idempotency_key']);
        $scope = self::scope('product', $postId, 'catalog_patch');

        $result = Transaction::run(static function (Transaction_Context $tx) use (
            $postId, $expected, $key, $payload, $scope, $actor
        ): array {
            $claim = Idempotency::claimWithin($tx, $scope, 'catalog_patch', $key, $payload);
            $post = self::lockPost($tx, $postId);
            $view = self::oneWithin($tx, $postId);
            if ('replay' === $claim['status']) {
                self::requireReplay($claim, $postId, $view, $expected);
                return ['id' => $postId, 'view' => $view];
            }
            self::requireNew($claim);
            self::requireExpected($view, $expected);

            $kind = $view['kind'];
            $sellables = self::variantRows($tx, [$postId]);
            if ([] === $sellables) {
                throw new Transaction_Failure('pc_catalog_invariant_invalid');
            }
            $byId = [];
            foreach ($sellables as $row) {
                $byId[(string) $row['id']] = $row;
            }
            $tx->lockSellables(\array_keys($byId));
            $simple = 1 === \count($sellables) && 'base' === $sellables[0]['variant_key'];
            $labels = self::labelsWithin($tx, $postId);

            if ($simple) {
                if ([] !== ($payload['variants'] ?? [])) {
                    throw new Transaction_Failure('pc_catalog_field_invalid');
                }
                $old = $sellables[0];
                $update = self::variantSpec($payload, $kind, $old, 'base');
                self::updateVariant($tx, $old, $update);
            } else {
                self::forbidParentSellableFields($payload);
                if (\count($sellables) + \count(\array_filter(
                    $payload['variants'] ?? [],
                    static fn (array $v): bool => !isset($v['id'])
                )) > 100) {
                    throw new Transaction_Failure('pc_catalog_field_invalid');
                }

                $seenUpdates = [];
                foreach ($payload['variants'] ?? [] as $item) {
                    if (isset($item['id'])) {
                        $variantId = Domain_Validation::sqlId($item['id']);
                        if (!isset($byId[$variantId]) || isset($seenUpdates[$variantId])) {
                            throw new Transaction_Failure('pc_catalog_field_invalid');
                        }
                        $seenUpdates[$variantId] = true;
                        $spec = self::variantSpec($item, $kind, $byId[$variantId],
                            $labels[$variantId] ?? null);
                        self::updateVariant($tx, $byId[$variantId], $spec);
                        $labels[$variantId] = $spec['label'];
                    } else {
                        $spec = self::variantSpec($item, $kind, null, null);
                        $newId = self::insertVariant($tx, $postId, $kind, $spec, $actor, $claim['operation_id']);
                        $labels[$newId] = $spec['label'];
                    }
                }
                // Variantes omitidas conservan identidad y atributos.
                self::uniqueLabels(\array_map(static fn ($l): array => ['label' => $l], \array_values($labels)));
            }

            $title = \array_key_exists('title', $payload)
                ? self::cleanTitle($payload['title'], true) : $post['post_title'];
            $description = \array_key_exists('description', $payload)
                ? self::cleanDescription($payload['description']) : $post['post_content'];
            self::updatePost($tx, $postId, $title, $description, $post['post_status']);
            self::replaceLabels($tx, $postId, $labels);
            self::advanceCatalogVersion($tx, $postId, $expected);
            $after = self::oneWithin($tx, $postId);
            // IMP-16: un PATCH sobre una ficha publicada no puede dejar
            // precios cero, ofertas inválidas ni variantes sin vendibilidad
            // visibles al lector PHP/REST. Abortamos el mismo COMMIT.
            if ('published' === $view['visibility']) {
                self::requirePublishReady($tx, $after, true);
            }
            Event_Journal::auditWithin(
                $tx, $actor, $claim['operation_id'], 'catalog', $postId, 'catalog_edited',
                null, self::catalogDigest($view), self::catalogDigest($after)
            );
            Idempotency::completeLocalWithin($tx, $claim['operation_id'], 'catalog', $postId, 200);
            return ['id' => $postId, 'view' => $after];
        }, true);

        self::invalidatePost($result['id']);
        return $result['view'];
    }

    /** R8-34: el operador oculta; publicar requiere administrador y módulos listos. */
    public static function visibility(string $postId, array $body): array
    {
        $postId = Domain_Validation::sqlId($postId);
        $target = $body['visibility'] ?? null;
        if (!\in_array($target, ['hidden', 'published'], true)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        $cap = 'hidden' === $target ? 'perucommerce_catalog_hide' : 'perucommerce_catalog_publish';
        $actor = self::requireStaff($cap);
        if ('published' === $target && Access_Roles::currentRole() !== 'A') {
            throw new Transaction_Failure('pc_catalog_access_denied');
        }
        $reason = \sanitize_textarea_field((string) ($body['reason'] ?? ''));
        if ('' === \trim($reason) || \mb_strlen($reason, 'UTF-8') > 1000) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        $expected = self::expected($body);
        $key = self::key($body);
        $payload = $body;
        unset($payload['idempotency_key']);
        $scope = self::scope('product', $postId, 'catalog_visibility');

        $result = Transaction::run(static function (Transaction_Context $tx) use (
            $postId, $expected, $key, $payload, $scope, $actor, $reason, $target
        ): array {
            $claim = Idempotency::claimWithin($tx, $scope, 'catalog_visibility', $key, $payload);
            $post = self::lockPost($tx, $postId);
            $view = self::oneWithin($tx, $postId);
            if ('replay' === $claim['status']) {
                self::requireReplay($claim, $postId, $view, $expected);
                return ['id' => $postId, 'view' => $view];
            }
            self::requireNew($claim);
            self::requireExpected($view, $expected);

            if ('published' === $target) {
                self::requirePublishReady($tx, $view);
            }
            $status = self::STATUSES[$target];
            self::updatePost($tx, $postId, $post['post_title'], $post['post_content'], $status);
            $version = self::nextVersion($expected);
            self::advanceCatalogVersion($tx, $postId, $expected);
            // Razón original privada, preservada por revisión, sin exponerla al REST.
            self::putMeta($tx, $postId, '_pc_visibility_' . $version,
                \json_encode([
                    'target' => $target,
                    'reason' => $reason,
                    'actor_user_id' => (string) \get_current_user_id(),
                ], \JSON_THROW_ON_ERROR));
            $after = self::oneWithin($tx, $postId);
            Event_Journal::auditWithin(
                $tx, $actor, $claim['operation_id'], 'catalog', $postId,
                'hidden' === $target ? 'catalog_hidden' : 'catalog_published',
                'visibility_change',
                self::catalogDigest($view), self::catalogDigest($after)
            );
            Idempotency::completeLocalWithin($tx, $claim['operation_id'], 'catalog', $postId, 200);
            return ['id' => $postId, 'view' => $after];
        }, true);

        self::invalidatePost($result['id']);
        return $result['view'];
    }

    /** Solo atributos comerciales seguros; las cadenas nunca se auditan en claro. */
    private static function catalogDigest(array $view): string
    {
        return \hash('sha256', \json_encode(
            $view, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
        ));
    }

    /** @return array<string,mixed> */
    private static function oneWithin(Transaction_Context $tx, string $postId): array
    {
        $db = self::db();
        $posts = $tx->rows(
            'SELECT ID, post_title, post_content, post_status FROM %i '
                . 'WHERE ID = %s AND post_type = %s',
            [$db->posts, $postId, Catalog_Controller::POST_TYPE]
        );
        if (1 !== \count($posts)) {
            throw new Transaction_Failure('pc_catalog_not_found');
        }
        $items = self::viewsWithin($tx, $posts);
        if (1 !== \count($items)) {
            throw new Transaction_Failure('pc_catalog_invariant_invalid');
        }
        return $items[0];
    }

    /**
     * Obtiene metadatos y variantes por lotes: dos consultas para hasta 101
     * productos, evitando el problema N+1 del panel.
     * @param list<array<string,mixed>> $posts
     * @return list<array<string,mixed>>
     */
    private static function viewsWithin(Transaction_Context $tx, array $posts): array
    {
        $db = self::db();
        $ids = \array_map(static fn (array $p): string => (string) $p['ID'], $posts);
        $markers = \implode(',', \array_fill(0, \count($ids), '%s'));
        $meta = $tx->rows(
            'SELECT post_id, meta_key, meta_value FROM %i WHERE post_id IN (' . $markers
                . ') AND meta_key IN (%s, %s)',
            \array_merge([$db->postmeta], $ids, [self::META_VERSION, self::META_LABELS])
        );
        $props = [];
        foreach ($meta as $m) {
            $id = (string) $m['post_id'];
            $name = (string) $m['meta_key'];
            if (isset($props[$id][$name])) {
                throw new Transaction_Failure('pc_catalog_metadata_duplicate');
            }
            $props[$id][$name] = (string) $m['meta_value'];
        }

        $variants = self::variantRows($tx, $ids);
        // Solo A con AMBAS capacidades puede leer asignaciones privadas.
        $digitalSlots = self::digitalAssignmentsWithin($tx, $ids);
        $grouped = [];
        foreach ($variants as $row) {
            $grouped[(string) $row['product_post_id']][] = $row;
        }

        $results = [];
        foreach ($posts as $post) {
            $id = (string) $post['ID'];
            if (!isset($props[$id][self::META_VERSION], $props[$id][self::META_LABELS])
                || !isset($grouped[$id]) || \count($grouped[$id]) > 100) {
                throw new Transaction_Failure('pc_catalog_invariant_invalid');
            }
            $version = Domain_Validation::sqlId($props[$id][self::META_VERSION]);
            $labels = self::decodeLabels($props[$id][self::META_LABELS]);
            $status = \array_search((string) $post['post_status'], self::STATUSES, true);
            if (false === $status) {
                throw new Transaction_Failure('pc_catalog_invariant_invalid');
            }
            $items = $grouped[$id];
            $kind = self::kind($items[0]['kind']);
            $simple = 1 === \count($items) && 'base' === $items[0]['variant_key'];
            $view = [
                'id' => $id,
                'post_id' => $id,
                'title' => (string) $post['post_title'],
                'kind' => $kind,
                'visibility' => $status,
                'row_version' => $version,
                'description' => (string) $post['post_content'],
                'variants' => [],
            ];
            foreach ($items as $item) {
                if ((string) $item['kind'] !== $kind
                    || ($simple && [] !== $labels)
                    || (!$simple && 'base' === $item['variant_key'])) {
                    throw new Transaction_Failure('pc_catalog_invariant_invalid');
                }
                $variantId = (string) $item['id'];
                if ($simple) {
                    $view += self::productFields($item);
                    // El DTO AdminProduct ya permite variantes. Exponer la
                    // base como AdminVariant (ID + versión del sellable) es
                    // necesario para R8-35 y R8-48 incluso en producto simple.
                    // Los campos de edición simple siguen en nivel producto.
                    $base = \array_merge([
                        'id' => $variantId,
                        'label' => 'Presentación única',
                    ], self::variantFields($item));
                    if ('digital' === $kind && null !== $digitalSlots) {
                        $base['digital_assignments'] = $digitalSlots[$variantId] ?? [];
                        $view['digital_assignments'] = $digitalSlots[$variantId] ?? [];
                    }
                    $view['variants'][] = $base;
                } else {
                    if (!isset($labels[$variantId])) {
                        throw new Transaction_Failure('pc_catalog_invariant_invalid');
                    }
                    $variant = \array_merge([
                        'id' => $variantId,
                        'label' => $labels[$variantId],
                    ], self::variantFields($item));
                    if ('digital' === $kind && null !== $digitalSlots) {
                        $variant['digital_assignments'] = $digitalSlots[$variantId] ?? [];
                    }
                    $view['variants'][] = $variant;
                }
            }
            if (!$simple && \count($labels) !== \count($items)) {
                throw new Transaction_Failure('pc_catalog_invariant_invalid');
            }
            $results[] = $view;
        }
        return $results;
    }

    /**
     * IMP-61: asignaciones activas por lote, solo metadatos para A.
     * La consulta nunca trae contenido, storage key, hash privado,
     * enlaces de descarga ni derechos de clientes.
     *
     * @param list<string> $postIds
     * @return array<string,list<array<string,string>>>|null
     */
    private static function digitalAssignmentsWithin(
        Transaction_Context $tx, array $postIds
    ): ?array {
        if ('A' !== Access_Roles::currentRole()
            || !\current_user_can('perucommerce_catalog_edit')
            || !\current_user_can('perucommerce_digital_files_manage')) {
            return null;
        }
        $db = self::db();
        $tables = Schema::tableNames($db);
        $markers = \implode(',', \array_fill(0, \count($postIds), '%s'));
        $rows = $tx->rows(
            'SELECT a.sellable_id, a.slot_key, a.active_file_id, '
                . 'a.row_version, f.original_filename, f.archived_at '
                . 'FROM %i a '
                . 'INNER JOIN %i s ON s.id=a.sellable_id '
                . 'INNER JOIN %i f ON f.id=a.active_file_id '
                . 'AND f.sellable_id=s.id '
                . 'AND f.owner_product_post_id=s.product_post_id '
                . 'INNER JOIN %i p ON p.ID=s.product_post_id '
                . 'WHERE p.post_type=%s AND s.kind=%s '
                . 'AND s.product_post_id IN (' . $markers . ') '
                . 'ORDER BY a.sellable_id, a.slot_key',
            \array_merge([
                $tables['pc_digital_assignments'], $tables['pc_sellables'],
                $tables['pc_digital_files'], $db->posts,
                Catalog_Controller::POST_TYPE, 'digital',
            ], $postIds)
        );
        $bySellable = [];
        foreach ($rows as $row) {
            $id = Domain_Validation::sqlId((string)$row['sellable_id']);
            $slot = (string)$row['slot_key'];
            if (null !== $row['archived_at']
                || !\preg_match('/\A[a-z0-9][a-z0-9_-]{0,99}\z/D', $slot)
                || !\is_string($row['original_filename'])
                || \count($bySellable[$id] ?? []) >= 100) {
                throw new Transaction_Failure('pc_catalog_invariant_invalid');
            }
            $bySellable[$id][] = [
                'sellable_id' => $id,
                'slot_key' => $slot,
                'active_file_id' => Domain_Validation::sqlId((string)$row['active_file_id']),
                'assignment_row_version' => Domain_Validation::sqlId((string)$row['row_version']),
                'filename' => (string)$row['original_filename'],
            ];
        }
        return $bySellable;
    }

    private static function productFields(array $r): array
    {
        $fields = self::variantFields($r);
        unset($fields['active'], $fields['row_version']);
        return $fields;
    }

    private static function variantFields(array $r): array
    {
        $out = [
            'price_cents' => (string) $r['price_cents'],
            'sale_active' => '1' === (string) $r['sale_active'],
            'track_stock' => '1' === (string) $r['track_stock'],
            'stock_on_hand' => (string) $r['stock_on_hand'],
            'active' => '1' === (string) $r['active'],
            'row_version' => (string) $r['row_version'],
        ];
        if (null !== $r['sale_price_cents']) {
            $out['sale_price_cents'] = (string) $r['sale_price_cents'];
        }
        if (null !== $r['sku']) {
            $out['sku'] = (string) $r['sku'];
        }
        foreach (['service_duration_minutes', 'service_buffer_before_minutes',
            'service_buffer_after_minutes'] as $name) {
            if (null !== $r[$name]) {
                $out[$name] = (int) $r[$name];
            }
        }
        return $out;
    }

    /** @param list<string> $postIds */
    private static function variantRows(Transaction_Context $tx, array $postIds): array
    {
        $db = self::db();
        $markers = \implode(',', \array_fill(0, \count($postIds), '%s'));
        return $tx->rows(
            'SELECT id, product_post_id, variant_key, kind, sku, price_cents, '
                . 'sale_price_cents, sale_active, track_stock, stock_on_hand, '
                . 'service_duration_minutes, service_buffer_before_minutes, '
                . 'service_buffer_after_minutes, active, row_version '
                . 'FROM %i WHERE product_post_id IN (' . $markers . ') '
                . 'ORDER BY product_post_id, id',
            \array_merge([$db->prefix . 'pc_sellables'], $postIds)
        );
    }

    private static function lockPost(Transaction_Context $tx, string $postId): array
    {
        $db = self::db();
        $rows = $tx->rows(
            'SELECT ID, post_title, post_content, post_status FROM %i '
                . 'WHERE ID = %s AND post_type = %s FOR UPDATE',
            [$db->posts, $postId, Catalog_Controller::POST_TYPE]
        );
        if (1 !== \count($rows)
            || !\in_array($rows[0]['post_status'], self::STATUSES, true)) {
            throw new Transaction_Failure('pc_catalog_not_found');
        }
        return $rows[0];
    }

    private static function labelsWithin(Transaction_Context $tx, string $postId): array
    {
        $db = self::db();
        $rows = $tx->rows(
            'SELECT meta_value FROM %i WHERE post_id = %s AND meta_key = %s FOR UPDATE',
            [$db->postmeta, $postId, self::META_LABELS]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_catalog_invariant_invalid');
        }
        return self::decodeLabels((string) $rows[0]['meta_value']);
    }

    private static function replaceLabels(Transaction_Context $tx, string $postId, array $labels): void
    {
        $db = self::db();
        $rows = $tx->rows(
            'SELECT meta_id, meta_value FROM %i WHERE post_id = %s AND meta_key = %s FOR UPDATE',
            [$db->postmeta, $postId, self::META_LABELS]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_catalog_invariant_invalid');
        }
        if (1 !== $tx->execute(
            'UPDATE %i SET meta_value = %s WHERE meta_id = %s AND meta_value = %s',
            [$db->postmeta, self::encodeLabels($labels),
                (string) $rows[0]['meta_id'], (string) $rows[0]['meta_value']]
        ) && self::encodeLabels($labels) !== $rows[0]['meta_value']) {
            throw new Transaction_Failure('pc_version_conflict');
        }
    }

    private static function advanceCatalogVersion(Transaction_Context $tx, string $postId, string $expected): void
    {
        $db = self::db();
        $newVersion = self::nextVersion($expected);
        if (1 !== $tx->execute(
            'UPDATE %i SET meta_value = %s WHERE post_id = %s '
                . 'AND meta_key = %s AND meta_value = %s',
            [$db->postmeta, $newVersion, $postId, self::META_VERSION, $expected]
        )) {
            throw new Transaction_Failure('pc_version_conflict');
        }
    }

    private static function insertPost(Transaction_Context $tx, string $title, string $description): string
    {
        $db = self::db();
        $date = \current_time('mysql');
        $author = (string) \get_current_user_id();
        $tx->execute(
            'INSERT INTO %i (post_author, post_date, post_date_gmt, post_content, '
                . 'post_title, post_excerpt, post_status, comment_status, ping_status, '
                . 'post_password, post_name, to_ping, pinged, post_modified, '
                . 'post_modified_gmt, post_content_filtered, post_parent, guid, '
                . 'menu_order, post_type, post_mime_type, comment_count) VALUES '
                . '(%s, %s, UTC_TIMESTAMP(), %s, %s, %s, %s, %s, %s, '
                . '%s, %s, %s, %s, %s, UTC_TIMESTAMP(), %s, %d, %s, %d, %s, %s, %d)',
            [$db->posts, $author, $date, $description, $title, '', 'draft',
                'closed', 'closed', '', '', '', '', $date, '', 0, '', 0,
                Catalog_Controller::POST_TYPE, '', 0]
        );
        $id = self::lastId($tx);
        $tx->execute(
            'UPDATE %i SET post_name = %s, guid = %s WHERE ID = %s',
            [$db->posts, 'pc-item-' . $id,
                \home_url('/?post_type=' . Catalog_Controller::POST_TYPE . '&p=' . $id), $id]
        );
        return $id;
    }

    private static function updatePost(
        Transaction_Context $tx, string $id, string $title, string $content, string $status
    ): void {
        $db = self::db();
        if (!\in_array($status, self::STATUSES, true)) {
            throw new Transaction_Failure('pc_catalog_invariant_invalid');
        }
        if (1 < $tx->execute(
            'UPDATE %i SET post_title = %s, post_content = %s, post_status = %s, '
                . 'post_modified = %s, post_modified_gmt = UTC_TIMESTAMP() '
                . 'WHERE ID = %s AND post_type = %s',
            [$db->posts, $title, $content, $status, \current_time('mysql'),
                $id, Catalog_Controller::POST_TYPE]
        )) {
            throw new Transaction_Failure('pc_version_conflict');
        }
    }

    private static function putMeta(
        Transaction_Context $tx, string $postId, string $key, string $value
    ): void {
        $db = self::db();
        if (1 !== $tx->execute(
            'INSERT INTO %i (post_id, meta_key, meta_value) VALUES (%s, %s, %s)',
            [$db->postmeta, $postId, $key, $value]
        )) {
            throw new Transaction_Failure('pc_catalog_invariant_invalid');
        }
    }

    /** @return string ID SqlId; fuente de identidad y de auditoría, no del navegador. */
    private static function insertVariant(
        Transaction_Context $tx, string $postId, string $kind, array $spec,
        Event_Actor $actor, string $operationId
    ): string {
        $db = self::db();
        $key = 'base' === $spec['key'] ? 'base' : 'v-' . \bin2hex(\random_bytes(12));
        $tx->execute(
            'INSERT INTO %i (product_post_id, variant_key, kind, sku, price_cents, '
                . 'sale_price_cents, sale_active, track_stock, stock_on_hand, '
                . 'service_duration_minutes, service_buffer_before_minutes, '
                . 'service_buffer_after_minutes, active, row_version, created_at, updated_at) '
                . 'VALUES (%s, %s, %s, NULLIF(%s, %s), %s, NULLIF(%s, %s), '
                . '%d, %d, %s, NULLIF(%s, %s), NULLIF(%s, %s), NULLIF(%s, %s), '
                . '%d, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))',
            [$db->prefix . 'pc_sellables', $postId, $key, $kind,
                $spec['sku'], '', $spec['price_cents'],
                $spec['sale_price_cents'], '', (int) $spec['sale_active'],
                (int) $spec['track_stock'], '0',
                $spec['service_duration_minutes'], '',
                $spec['service_buffer_before_minutes'], '',
                $spec['service_buffer_after_minutes'], '',
                (int) $spec['active']]
        );
        $id = self::lastId($tx);
        $initial = $spec['initial_stock_units'];
        if ($initial > 0) {
            // El saldo parte de cero. Ledger y balance se confirman juntos.
            $tx->lockSellables([$id]);
            if (1 !== $tx->execute(
                'UPDATE %i SET stock_on_hand = %s, row_version = row_version + 1, '
                    . 'updated_at = UTC_TIMESTAMP(6) WHERE id = %s '
                    . 'AND stock_on_hand = 0 AND row_version = 1',
                [$db->prefix . 'pc_sellables', (string) $initial, $id]
            )) {
                throw new Transaction_Failure('pc_version_conflict');
            }
            Event_Journal::stockWithin(
                $tx, $actor, $operationId, $id, $initial, $initial,
                'catalog_initial', $postId, 'initial_stock'
            );
        }
        return $id;
    }

    private static function updateVariant(Transaction_Context $tx, array $old, array $spec): void
    {
        $db = self::db();
        $id = (string) $old['id'];
        if ($spec['track_stock'] !== ('1' === (string) $old['track_stock'])) {
            // No convertir un artículo históricamente controlado en
            // ilimitado (ni al revés) con recursos o movimientos previos.
            if ('0' !== (string) $old['stock_on_hand']) {
                throw new Transaction_Failure('pc_catalog_conflict');
            }
            foreach (['pc_stock_movements', 'pc_stock_commitments',
                'pc_holds', 'pc_resource_admissions'] as $suffix) {
                if ([] !== $tx->rows(
                    'SELECT id FROM %i WHERE sellable_id = %s LIMIT 1',
                    [$db->prefix . $suffix, $id]
                )) {
                    throw new Transaction_Failure('pc_catalog_conflict');
                }
            }
        }
        if (1 < $tx->execute(
            'UPDATE %i SET sku = NULLIF(%s, %s), price_cents = %s, '
                . 'sale_price_cents = NULLIF(%s, %s), sale_active = %d, '
                . 'track_stock = %d, service_duration_minutes = NULLIF(%s, %s), '
                . 'service_buffer_before_minutes = NULLIF(%s, %s), '
                . 'service_buffer_after_minutes = NULLIF(%s, %s), active = %d, '
                . 'updated_at = UTC_TIMESTAMP(6) WHERE id = %s AND row_version = %s',
            [$db->prefix . 'pc_sellables', $spec['sku'], '',
                $spec['price_cents'], $spec['sale_price_cents'], '',
                (int) $spec['sale_active'], (int) $spec['track_stock'],
                $spec['service_duration_minutes'], '',
                $spec['service_buffer_before_minutes'], '',
                $spec['service_buffer_after_minutes'], '',
                (int) $spec['active'], $id, (string) $old['row_version']]
        )) {
            throw new Transaction_Failure('pc_version_conflict');
        }
        $tx->advanceVersion('pc_sellables', $id, (string) $old['row_version']);
    }

    /**
     * Validación de reglas condicionales que OpenAPI deja para el dominio.
     * @return array<string,mixed>
     */
    private static function variantSpec(
        array $input, string $kind, ?array $old, ?string $label
    ): array {
        $new = null === $old;
        $source = $old ?? [];
        $name = $label ?? ($input['label'] ?? null);
        if (isset($input['label'])) {
            $name = $input['label'];
        }
        $name = 'base' === $label ? 'base' : self::cleanTitle($name, true);
        // ProductCreate permite crear un borrador simple sin precio;
        // nunca se podrá publicar/cotizar con precio efectivo cero.
        $price = $input['price_cents'] ?? $source['price_cents']
            ?? ($new && 'base' === $name ? '0' : null);
        if (!\is_string($price)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        Money::fromApi($price);
        $sale = $input['sale_price_cents'] ?? $source['sale_price_cents'] ?? null;
        if (null !== $sale) {
            if (!\is_string($sale)) {
                throw new Transaction_Failure('pc_catalog_field_invalid');
            }
            Money::fromApi($sale);
        }
        $activeOffer = $input['sale_active'] ?? ('1' === (string) ($source['sale_active'] ?? '0'));
        if (!\is_bool($activeOffer)
            || (null !== $sale && Money::fromApi($sale) > Money::fromApi($price))
            || ($activeOffer && (null === $sale || '0' === $sale || '0' === $price))) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        $track = $input['track_stock'] ?? ('1' === (string) ($source['track_stock'] ?? '0'));
        if (!\is_bool($track) || ('physical' !== $kind && $track)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        $stock = $input['initial_stock_units'] ?? '0';
        if (!\is_string($stock)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        $initial = Domain_Validation::unitsCount($stock);
        if ($initial > 0 && (!$track || !$new || 'physical' !== $kind)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }

        $durationFields = [
            'service_duration_minutes', 'service_buffer_before_minutes',
            'service_buffer_after_minutes',
        ];
        $timing = [];
        foreach ($durationFields as $f) {
            if ('service' !== $kind && \array_key_exists($f, $input)) {
                throw new Transaction_Failure('pc_catalog_field_invalid');
            }
            $raw = $input[$f] ?? $source[$f] ?? null;
            if ('service' === $kind) {
                // mysqli entrega DATETIME e INT SQL como strings; solo la
                // lectura confiable de BD puede normalizarse, nunca el JSON.
                if (!array_key_exists($f, $input) && null !== $raw) {
                    if (!is_string($raw) || !ctype_digit($raw)) {
                        throw new Transaction_Failure('pc_catalog_invariant_invalid');
                    }
                    $raw = (int) $raw;
                }
                $raw ??= 'service_duration_minutes' === $f ? null : 0;
                if (null !== $raw && (!\is_int($raw)
                    || $raw < ('service_duration_minutes' === $f ? 1 : 0)
                    || $raw > 1440)) {
                    throw new Transaction_Failure('pc_catalog_field_invalid');
                }
                // Los borradores pueden carecer de duración; publicar
                // valida el servicio completo bajo las puertas de agenda.
            } else {
                $raw = null;
            }
            $timing[$f] = null === $raw ? '' : (string) $raw;
        }
        $sku = \array_key_exists('sku', $input)
            ? \sanitize_text_field((string) $input['sku'])
            : (string) ($source['sku'] ?? '');
        if (\mb_strlen($sku, 'UTF-8') > 100) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        $active = $input['active'] ?? ('1' === (string) ($source['active'] ?? '1'));
        if (!\is_bool($active)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }

        return [
            'key' => 'base' === $name ? 'base' : 'variant',
            'label' => $name,
            'sku' => $sku,
            'price_cents' => $price,
            'sale_price_cents' => $sale ?? '',
            'sale_active' => $activeOffer,
            'track_stock' => $track,
            'active' => $active,
            'initial_stock_units' => $initial,
        ] + $timing;
    }

    private static function requirePublishReady(
        Transaction_Context $tx, array $view, bool $sellablesLocked = false
    ): void
    {
        $module = match ($view['kind']) {
            'physical' => 'inventory',
            'digital' => 'digital',
            'service' => 'appointments',
            default => '',
        };
        if ('' === $module || !Module_Gates::allows($module, 'write')) {
            throw new Transaction_Failure('pc_catalog_not_ready');
        }
        $variants = $view['variants'] ?: [$view];
        $ready = false;
        foreach ($variants as $v) {
            if (false === ($v['active'] ?? true)) {
                continue;
            }
            $price = $v['price_cents'] ?? '0';
            if ('0' === $price || (true === $v['sale_active']
                && ('0' === ($v['sale_price_cents'] ?? '0')
                    || Money::fromApi($v['sale_price_cents']) > Money::fromApi($price)))) {
                throw new Transaction_Failure('pc_catalog_field_invalid');
            }
            if ('service' === $view['kind']
                && (!\is_int($v['service_duration_minutes'] ?? null)
                    || $v['service_duration_minutes'] < 1
                    || !\is_int($v['service_buffer_before_minutes'] ?? null)
                    || !\is_int($v['service_buffer_after_minutes'] ?? null))) {
                throw new Transaction_Failure('pc_catalog_field_invalid');
            }
            $ready = true;
        }
        if (!$ready) {
            throw new Transaction_Failure('pc_catalog_not_ready');
        }
        if ('digital' === $view['kind']) {
            // Publicar no concede derechos ni modifica snapshots vendidos.
            // Exige un archivo activo por CADA variante digital activa, en
            // la misma transacción y bajo el orden post -> sellable -> slot.
            $postId = Domain_Validation::sqlId((string)$view['id']);
            $db = self::db();
            $tables = Schema::tableNames($db);
            $sellables = self::variantRows($tx, [$postId]);
            $activeIds = [];
            foreach ($sellables as $sellable) {
                if ('1' === (string)$sellable['active']) {
                    $activeIds[] = Domain_Validation::sqlId((string)$sellable['id']);
                }
            }
            if ([] === $activeIds) {
                throw new Transaction_Failure('pc_catalog_not_ready');
            }
            // PATCH ya bloqueó todos los vendibles existentes en orden
            // numérico; los nuevos nacen en esta misma transacción.
            // Repetir lockSellables desde el ID menor violaría la fase 50.
            if (!$sellablesLocked) {
                $tx->lockSellables($activeIds);
            }
            $markers = \implode(',', \array_fill(0, \count($activeIds), '%s'));
            $rows = $tx->rows(
                'SELECT s.id AS sellable_id, a.active_file_id, '
                    . 'f.id AS verified_file_id, f.archived_at '
                    . 'FROM %i s INNER JOIN %i a ON a.sellable_id=s.id '
                    . 'INNER JOIN %i f ON f.id=a.active_file_id '
                    . 'AND f.sellable_id=s.id '
                    . 'AND f.owner_product_post_id=s.product_post_id '
                    . 'WHERE s.id IN (' . $markers . ') '
                    . 'ORDER BY s.id, a.slot_key FOR UPDATE',
                \array_merge([
                    $tables['pc_sellables'], $tables['pc_digital_assignments'],
                    $tables['pc_digital_files'],
                ], $activeIds)
            );
            // INNER JOIN omite variantes sin slot; la cobertura completa
            // se comprueba más abajo y la lectura actual usa FOR UPDATE.
            $covered = [];
            foreach ($rows as $row) {
                $id = (string)$row['sellable_id'];
                if (null !== $row['active_file_id']) {
                    if (null === $row['verified_file_id']
                        || null !== $row['archived_at']) {
                        throw new Transaction_Failure('pc_catalog_not_ready');
                    }
                    $covered[$id] = true;
                }
            }
            foreach ($activeIds as $id) {
                if (!isset($covered[$id])) {
                    throw new Transaction_Failure('pc_catalog_not_ready');
                }
            }
        }
    }

    private static function requireStaff(string $capability): Event_Actor
    {
        if (!\in_array(Access_Roles::currentRole(), ['A', 'O'], true)
            || !\current_user_can($capability)) {
            throw new Transaction_Failure('pc_catalog_access_denied');
        }
        return Event_Actor::staff([$capability]);
    }

    private static function scope(string $resource, string $id, string $purpose): array
    {
        return [
            'actor_kind' => 'staff',
            'actor_ref' => (string) \get_current_user_id(),
            'resource_kind' => $resource,
            'resource_id' => $id,
            'purpose' => $purpose,
        ];
    }

    private static function expected(array $body): string
    {
        if (!\is_string($body['expected_version'] ?? null)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        return Domain_Validation::sqlId($body['expected_version']);
    }

    private static function key(array $body): string
    {
        $key = $body['idempotency_key'] ?? null;
        if (!\is_string($key) || \mb_strlen($key, 'UTF-8') < 16
            || \mb_strlen($key, 'UTF-8') > 128) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        return $key;
    }

    private static function requireNew(array $claim): void
    {
        if ('new' !== $claim['status']) {
            throw new Transaction_Failure('pc_idempotency_conflict');
        }
    }

    private static function requireReplay(
        array $claim, string $postId, array $view, string $expected
    ): void {
        if ('catalog' !== $claim['entity_type']
            || $claim['entity_id'] !== $postId
            || $view['row_version'] !== self::nextVersion($expected)) {
            throw new Transaction_Failure('pc_version_conflict');
        }
    }

    private static function requireExpected(array $view, string $expected): void
    {
        if ($view['row_version'] !== $expected) {
            throw new Transaction_Failure('pc_version_conflict');
        }
    }

    private static function nextVersion(string $value): string
    {
        $chars = \str_split($value);
        for ($i = \count($chars) - 1; $i >= 0; --$i) {
            if ('9' === $chars[$i]) {
                $chars[$i] = '0';
            } else {
                $chars[$i] = (string) ((int) $chars[$i] + 1);
                return Domain_Validation::sqlId(\implode('', $chars));
            }
        }
        return Domain_Validation::sqlId('1' . \implode('', $chars));
    }

    private static function kind(mixed $kind): string
    {
        if (!\in_array($kind, ['physical', 'digital', 'service'], true)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        return $kind;
    }

    private static function cleanTitle(mixed $value, bool $required): string
    {
        if (!\is_string($value)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        $out = \trim(\sanitize_text_field($value));
        if ($required && '' === $out || \mb_strlen($out, 'UTF-8') > 240) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        return $out;
    }

    private static function cleanDescription(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        $out = \wp_kses_post($value);
        if (\mb_strlen($out, 'UTF-8') > 20000) {
            throw new Transaction_Failure('pc_catalog_field_invalid');
        }
        return $out;
    }

    private static function forbidParentSellableFields(array $body): void
    {
        foreach (['price_cents', 'sale_price_cents', 'sale_active', 'sku',
            'track_stock', 'initial_stock_units', 'service_duration_minutes',
            'service_buffer_before_minutes', 'service_buffer_after_minutes'] as $key) {
            if (\array_key_exists($key, $body)) {
                throw new Transaction_Failure('pc_catalog_field_invalid');
            }
        }
    }

    private static function uniqueLabels(array $items): void
    {
        $labels = [];
        foreach ($items as $item) {
            $label = (string) $item['label'];
            if ('' === $label || 'base' === \mb_strtolower($label, 'UTF-8')) {
                throw new Transaction_Failure('pc_catalog_field_invalid');
            }
            $key = \mb_strtolower($label, 'UTF-8');
            if (isset($labels[$key])) {
                throw new Transaction_Failure('pc_catalog_field_invalid');
            }
            $labels[$key] = true;
        }
    }

    private static function encodeLabels(array $labels): string
    {
        return \json_encode((object) $labels, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    private static function decodeLabels(string $raw): array
    {
        try {
            $object = \json_decode($raw, false, 20, \JSON_THROW_ON_ERROR);
            if (!$object instanceof \stdClass) {
                throw new \UnexpectedValueException();
            }
            $labels = \get_object_vars($object);
            foreach ($labels as $id => $label) {
                if (!\is_string($label) || '' === $label) {
                    throw new \UnexpectedValueException();
                }
                Domain_Validation::sqlId((string) $id);
            }
            return $labels;
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_catalog_metadata_invalid');
        }
    }

    private static function lastId(Transaction_Context $tx): string
    {
        $rows = $tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_catalog_invariant_invalid');
        }
        return Domain_Validation::sqlId((string) $rows[0]['id']);
    }

    private static function invalidatePost(string $id): void
    {
        if (\strlen($id) < \strlen((string) \PHP_INT_MAX)
            || (\strlen($id) === \strlen((string) \PHP_INT_MAX)
                && \strcmp($id, (string) \PHP_INT_MAX) <= 0)) {
            try {
                \clean_post_cache((int) $id);
            } catch (\Throwable $ignored) {
                // La operación ya pudo confirmar COMMIT: no repetirla.
            }
        }
    }

    private static function db(): \wpdb
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        return $wpdb;
    }
}
