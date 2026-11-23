<?php
/**
 * IMP-21 — metadatos de archivo digital y versiones activas por slot.
 *
 * Una inserción de pc_digital_files representa un binario inmutable.
 * pc_digital_assignments apunta al archivo activo SOLO para nuevas ventas.
 * pc_download_rights.file_id de pedidos previos jamás se modifica aquí.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Files
{
    private const TABLES = ['pc_digital_files', 'pc_digital_assignments', 'pc_sellables'];

    public static function table(string $suffix): string
    {
        if (!\in_array($suffix, self::TABLES, true)) {
            throw new Transaction_Failure('pc_digital_table_denied');
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/schema.php';
        return Schema::tableNames($wpdb)[$suffix];
    }

    public static function slot(string $value): string
    {
        if (!\preg_match('/\A[a-z0-9][a-z0-9_-]{0,99}\z/D', $value)) {
            throw new Transaction_Failure('pc_digital_field_invalid');
        }
        return $value;
    }

    public static function staff(): void
    {
        if ('A' !== Access_Roles::currentRole()
            || !\current_user_can('perucommerce_digital_files_manage')
            || (int) \get_current_blog_id() <= 0) {
            throw new Transaction_Failure('pc_digital_access_denied');
        }
    }

    /**
     * Verifica pertenencia REAL al post de este sitio WP y a variante digital.
     * El consumidor que muta debe bloquear primero post y luego sellable.
     */
    public static function productWithin(
        Transaction_Context $tx, string $productId, string $sellableId,
        bool $lock
    ): array {
        global $wpdb;
        $productId = Domain_Validation::sqlId($productId);
        $sellableId = Domain_Validation::sqlId($sellableId);
        $post = $tx->rows(
            'SELECT ID, post_status FROM %i WHERE ID = %s AND post_type = %s '
                . 'AND post_status IN (%s,%s,%s) '
                . ($lock ? 'FOR UPDATE' : ''),
            [$wpdb->posts, $productId, Catalog_Controller::POST_TYPE,
                'draft','private','publish']
        );
        if (1 !== \count($post)) {
            throw new Transaction_Failure('pc_digital_not_found');
        }
        if ($lock) {
            $v = $tx->lockSellables([$sellableId])[$sellableId];
        } else {
            $rows = $tx->rows(
                'SELECT id, product_post_id, kind, active FROM %i WHERE id = %s',
                [self::table('pc_sellables'), $sellableId]
            );
            if (1 !== \count($rows)) {
                throw new Transaction_Failure('pc_digital_not_found');
            }
            $v = $rows[0];
        }
        if ((string) ($v['product_post_id'] ?? $productId) !== $productId
            || (string) $v['kind'] !== 'digital') {
            throw new Transaction_Failure('pc_digital_not_found');
        }
        // lockSellables() no incluye product_post_id: revalidar pertenencia
        // mediante SELECT simple tras bloquear la variante y el post.
        if ($lock) {
            $rows = $tx->rows(
                'SELECT product_post_id FROM %i WHERE id = %s',
                [self::table('pc_sellables'), $sellableId]
            );
            if (1 !== \count($rows) || (string) $rows[0]['product_post_id'] !== $productId) {
                throw new Transaction_Failure('pc_digital_not_found');
            }
        }
        return ['post' => $post[0], 'sellable' => $v];
    }

    /** Servidor: recuperar fichero y su ruta OPACA, nunca proyectar tal campo. */
    public static function fileWithin(Transaction_Context $tx, string $fileId): array
    {
        global $wpdb;
        $fileId = Domain_Validation::sqlId($fileId);
        $rows = $tx->rows(
            'SELECT f.id, f.owner_product_post_id, f.sellable_id, '
                . 'f.original_filename, f.mime_type, f.byte_size, '
                . 'f.private_storage_key, f.replaces_file_id, HEX(f.content_hash) AS content_sha256, '
                . 'f.archived_at '
                . 'FROM %i f INNER JOIN %i s ON s.id = f.sellable_id '
                . 'INNER JOIN %i p ON p.ID = f.owner_product_post_id '
                . 'WHERE f.id = %s AND s.kind = %s '
                . 'AND s.product_post_id = f.owner_product_post_id '
                . 'AND p.post_type = %s',
            [self::table('pc_digital_files'), self::table('pc_sellables'),
                $wpdb->posts, $fileId, 'digital', Catalog_Controller::POST_TYPE]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_digital_not_found');
        }
        $row = $rows[0];
        if (!\is_string($row['content_sha256'])
            || !\preg_match('/\A[A-F0-9]{64}\z/D', $row['content_sha256'])
            || !\is_string($row['private_storage_key'])
            || !\is_string($row['original_filename'])
            || !\is_string($row['mime_type'])) {
            throw new Transaction_Failure('pc_digital_invariant_invalid');
        }
        return $row;
    }

    public static function byId(string $fileId): array
    {
        self::staff();
        return Transaction::run(static fn (Transaction_Context $tx): array =>
            self::fileWithin($tx, $fileId)
        );
    }

    /** Primer upload: crear slot y archivo, sin UPSERT que pise versión anterior. */
    public static function create(array $fields, array $staged): array
    {
        self::staff();
        if (!Module_Gates::allows('digital', 'write')) {
            throw new Transaction_Failure('pc_digital_not_ready');
        }
        $product = Domain_Validation::sqlId($fields['product_id']);
        $sellable = Domain_Validation::sqlId($fields['sellable_id']);
        $slot = self::slot($fields['slot_key']);
        $key = $fields['idempotency_key'];
        $scope = self::scope($sellable, 'digital_initial');
        $payload = [
            'product_id' => $product, 'sellable_id' => $sellable,
            'slot_key' => $slot, 'filename' => $staged['filename'],
            'mime' => $staged['mime'], 'size' => (string) $staged['size'],
            'sha256' => $staged['sha256'],
        ];
        $actor = Event_Actor::staff(['perucommerce_digital_files_manage']);
        return Transaction::run(static function (Transaction_Context $tx) use (
            $product,$sellable,$slot,$key,$payload,$scope,$staged,$actor
        ): array {
            $claim = Idempotency::claimWithin(
                $tx, $scope, 'digital_initial', $key, $payload
            );
            if ('replay' === $claim['status']) {
                return self::replayInitial($tx, $claim, $product, $sellable, $slot);
            }
            self::pending($claim);
            self::productWithin($tx, $product, $sellable, true);
            $existing = $tx->rows(
                'SELECT id FROM %i WHERE sellable_id = %s AND slot_key = %s FOR UPDATE',
                [self::table('pc_digital_assignments'), $sellable, $slot]
            );
            if ([] !== $existing) {
                throw new Transaction_Failure('pc_digital_conflict');
            }
            // AdminDigitalSlot permite como máximo 100 asignaciones por
            // variante. La fila sellable estable ya protege la cuenta.
            $slotCount = $tx->rows(
                'SELECT COUNT(*) AS n FROM %i WHERE sellable_id = %s',
                [self::table('pc_digital_assignments'), $sellable]
            );
            if (1 !== \count($slotCount)
                || !\ctype_digit((string) $slotCount[0]['n'])
                || (int) $slotCount[0]['n'] >= 100) {
                throw new Transaction_Failure('pc_digital_conflict');
            }
            $fileId = self::insertFileWithin(
                $tx, $product, $sellable, $staged, null
            );
            if (1 !== $tx->execute(
                'INSERT INTO %i (sellable_id, slot_key, active_file_id, '
                    . 'row_version, updated_at, updated_by) '
                    . 'VALUES (%s,%s,%s,1,UTC_TIMESTAMP(6),%s)',
                [self::table('pc_digital_assignments'), $sellable,
                    $slot, $fileId, (string) \get_current_user_id()]
            )) {
                throw new Transaction_Failure('pc_digital_conflict');
            }
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],'digital_file',$fileId,
                'digital_file_registered','initial_upload',null,$staged['sha256']
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'digital_file',$fileId,201
            );
            return self::metadata($fileId,$product,$sellable,$slot,'1',$staged);
        }, true);
    }

    /** Reemplazo: CAS del slot activo, nunca tocar derechos de venta previa. */
    public static function replace(string $oldFileId, array $fields, array $staged): array
    {
        self::staff();
        if (!Module_Gates::allows('digital','write')) {
            throw new Transaction_Failure('pc_digital_not_ready');
        }
        $oldFileId = Domain_Validation::sqlId($oldFileId);
        $expected = Domain_Validation::sqlId($fields['expected_version']);
        $reason = $fields['reason'];
        if (!\is_string($reason) || '' === \trim($reason)
            || \mb_strlen($reason,'UTF-8') > 1000) {
            throw new Transaction_Failure('pc_digital_field_invalid');
        }
        $reasonHash = \hash('sha256', \trim($reason));
        $old = self::byId($oldFileId);
        $product = Domain_Validation::sqlId((string) $old['owner_product_post_id']);
        $sellable = Domain_Validation::sqlId((string) $old['sellable_id']);
        $scope = self::scope($sellable, 'digital_replace');
        $payload = [
            'old_file_id' => $oldFileId,'expected_version' => $expected,
            'reason_hash' => $reasonHash,'filename' => $staged['filename'],
            'mime' => $staged['mime'],'size' => (string) $staged['size'],
            'sha256' => $staged['sha256'],
        ];
        $actor = Event_Actor::staff(['perucommerce_digital_files_manage']);
        return Transaction::run(static function (Transaction_Context $tx) use (
            $oldFileId,$expected,$reasonHash,$product,$sellable,
            $scope,$payload,$actor,$staged,$fields
        ): array {
            $claim = Idempotency::claimWithin(
                $tx,$scope,'digital_replace',$fields['idempotency_key'],$payload
            );
            if ('replay' === $claim['status']) {
                return self::replayReplacement($tx,$claim,$oldFileId);
            }
            self::pending($claim);
            self::productWithin($tx,$product,$sellable,true);
            $file = self::fileWithin($tx,$oldFileId);
            if ((string) $file['sellable_id'] !== $sellable
                || (string) $file['owner_product_post_id'] !== $product
                || null !== $file['archived_at']) {
                throw new Transaction_Failure('pc_digital_not_found');
            }
            $rows = $tx->rows(
                'SELECT id, slot_key, active_file_id, row_version '
                    . 'FROM %i WHERE sellable_id = %s AND active_file_id = %s FOR UPDATE',
                [self::table('pc_digital_assignments'),$sellable,$oldFileId]
            );
            if (1 !== \count($rows) || (string) $rows[0]['row_version'] !== $expected) {
                throw new Transaction_Failure('pc_version_conflict');
            }
            $newFile = self::insertFileWithin(
                $tx,$product,$sellable,$staged,$oldFileId
            );
            $assignId = Domain_Validation::sqlId((string) $rows[0]['id']);
            if (1 !== $tx->execute(
                'UPDATE %i SET active_file_id = %s, row_version = row_version + 1, '
                    . 'updated_at = UTC_TIMESTAMP(6), updated_by = %s '
                    . 'WHERE id = %s AND sellable_id = %s '
                    . 'AND active_file_id = %s AND row_version = %s',
                [self::table('pc_digital_assignments'),$newFile,
                    (string) \get_current_user_id(),$assignId,$sellable,
                    $oldFileId,$expected]
            )) {
                throw new Transaction_Failure('pc_version_conflict');
            }
            // Un archivo sustituido sigue siendo resoluble para derechos
            // de pedidos anteriores. archived_at solo etiqueta el estado.
            if (1 !== $tx->execute(
                'UPDATE %i SET archived_at = UTC_TIMESTAMP(6) '
                    . 'WHERE id = %s AND archived_at IS NULL',
                [self::table('pc_digital_files'),$oldFileId]
            )) {
                throw new Transaction_Failure('pc_digital_conflict');
            }
            Event_Journal::auditWithin(
                $tx,$actor,$claim['operation_id'],'digital_file',$newFile,
                'digital_file_replaced','new_sales_only',
                \strtolower($file['content_sha256']),$staged['sha256']
            );
            Idempotency::completeLocalWithin(
                $tx,$claim['operation_id'],'digital_file',$newFile,201
            );
            return [
                'operation_id' => $claim['operation_id'],
                'new_file_id' => $newFile,
                'old_file_id' => $oldFileId,
                'assignment_row_version' => self::increment($expected),
            ];
        }, true);
    }

    private static function insertFileWithin(
        Transaction_Context $tx,string $product,string $sellable,
        array $stored,?string $replaces
    ): string {
        $columns = [
            'owner_product_post_id','sellable_id','private_storage_key',
            'original_filename','mime_type','byte_size','content_hash','created_at',
        ];
        $holders = ['%s','%s','%s','%s','%s','%s','UNHEX(%s)','UTC_TIMESTAMP(6)'];
        $args = [$product,$sellable,$stored['key'],$stored['filename'],
            $stored['mime'],(string) $stored['size'],$stored['sha256']];
        if (null !== $replaces) {
            $columns[] = 'replaces_file_id';
            $holders[] = '%s';
            $args[] = $replaces;
        }
        if (1 !== $tx->execute(
            'INSERT INTO %i (' . \implode(',', $columns) . ') VALUES ('
                . \implode(',', $holders) . ')',
            \array_merge([self::table('pc_digital_files')],$args)
        )) {
            throw new Transaction_Failure('pc_digital_conflict');
        }
        $rows = $tx->rows('SELECT LAST_INSERT_ID() AS id');
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_digital_invariant_invalid');
        }
        return Domain_Validation::sqlId((string) $rows[0]['id']);
    }

    private static function metadata(
        string $id,string $product,string $sellable,string $slot,
        string $version,array $stored
    ): array {
        return [
            'file_id' => $id,'product_id' => $product,
            'filename' => $stored['filename'],
            'mime_type' => $stored['mime'],'size_bytes' => (string) $stored['size'],
            'protected' => true,'sellable_id' => $sellable,
            'slot_key' => $slot,'assignment_row_version' => $version,
        ];
    }

    private static function replayInitial(
        Transaction_Context $tx,array $claim,string $product,string $sellable,string $slot
    ): array {
        if ('digital_file' !== $claim['entity_type']
            || !\is_string($claim['entity_id'])) {
            throw new Transaction_Failure('pc_digital_conflict');
        }
        $f = self::fileWithin($tx,$claim['entity_id']);
        $rows = $tx->rows(
            'SELECT row_version FROM %i WHERE sellable_id = %s '
                . 'AND slot_key = %s AND active_file_id = %s',
            [self::table('pc_digital_assignments'),$sellable,$slot,$claim['entity_id']]
        );
        if ((string) $f['owner_product_post_id'] !== $product
            || (string) $f['sellable_id'] !== $sellable
            || 1 !== \count($rows)) {
            throw new Transaction_Failure('pc_digital_conflict');
        }
        return self::metadata(
            $claim['entity_id'],$product,$sellable,$slot,
            (string) $rows[0]['row_version'],
            [
                'filename'=>$f['original_filename'],'mime'=>$f['mime_type'],
                'size'=>$f['byte_size'],
            ]
        );
    }

    private static function replayReplacement(
        Transaction_Context $tx,array $claim,string $oldId
    ): array {
        if ('digital_file' !== $claim['entity_type']
            || !\is_string($claim['entity_id'])) {
            throw new Transaction_Failure('pc_digital_conflict');
        }
        $f = self::fileWithin($tx,$claim['entity_id']);
        if ((string) $f['replaces_file_id'] !== $oldId) {
            throw new Transaction_Failure('pc_digital_conflict');
        }
        $rows = $tx->rows(
            'SELECT row_version FROM %i WHERE active_file_id = %s AND sellable_id = %s',
            [self::table('pc_digital_assignments'),$claim['entity_id'],$f['sellable_id']]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_digital_conflict');
        }
        return [
            'operation_id'=>$claim['operation_id'],
            'new_file_id'=>$claim['entity_id'],'old_file_id'=>$oldId,
            'assignment_row_version'=>(string) $rows[0]['row_version'],
        ];
    }

    private static function scope(string $sellable,string $purpose): array
    {
        return [
            'actor_kind'=>'staff','actor_ref'=>(string) \get_current_user_id(),
            'resource_kind'=>'sellable','resource_id'=>$sellable,
            'purpose'=>$purpose,
        ];
    }

    private static function pending(array $claim): void
    {
        if ('new' !== $claim['status']) {
            throw new Transaction_Failure('pc_idempotency_conflict');
        }
    }

    private static function increment(string $version): string
    {
        $v = Domain_Validation::sqlId($version);
        if (Transaction_Context::compareId($v,(string) \PHP_INT_MAX) >= 0) {
            throw new Transaction_Failure('pc_version_conflict');
        }
        return (string) ((int) $v + 1);
    }
}
