<?php
/**
 * IMP-17 — R8-35: ajuste administrativo por variante física.
 *
 * Un ajuste no es un despacho, devolución ni cancelación. No toca las
 * unidades comprometidas, el dinero o los snapshots históricos del pedido.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Inventory_Service
{
    /** Guard de recurso real: instalación, producto propio y variante física. */
    public static function existsForStaff(string $sellableId): bool
    {
        try {
            $sellableId = Domain_Validation::sqlId($sellableId);
            return Transaction::run(static function (Transaction_Context $tx) use ($sellableId): bool {
                return self::belongsToThisSite($tx, $sellableId);
            });
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    /** @return array<string,mixed> MutationResult */
    public static function adjust(array $input): array
    {
        if (!\in_array(Access_Roles::currentRole(), ['A', 'O'], true)
            || !\current_user_can('perucommerce_stock_manage')) {
            throw new Rest_Failure('pc_access_denied', 403);
        }
        $sellableId = Domain_Validation::sqlId($input['sellable_id']);
        $expected = Domain_Validation::sqlId($input['expected_version']);
        $delta = $input['delta_units'];
        if (!\is_int($delta) || 0 === $delta
            || $delta < -2147483647 || $delta > 2147483647) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'delta_units');
        }
        $reason = $input['reason'];
        if (!\is_string($reason) || '' === \trim($reason)
            || \mb_strlen($reason, 'UTF-8') > 1000) {
            throw new Rest_Failure('pc_field_invalid', 422, false, 'reason');
        }
        // No persistir comentario humano arbitrario (puede contener PII).
        // La petición idempotente y el audit guardan hashes, y el movimiento
        // conserva código de motivo categórico identificable.
        $reasonDigest = \hash('sha256', \trim($reason));
        $payload = $input;
        unset($payload['idempotency_key']);
        $scope = [
            'actor_kind' => 'staff',
            'actor_ref' => (string) \get_current_user_id(),
            'resource_kind' => 'sellable',
            'resource_id' => $sellableId,
            'purpose' => 'stock_adjust',
        ];
        $actor = Event_Actor::staff(['perucommerce_stock_manage']);

        return Transaction::run(static function (Transaction_Context $tx) use (
            $scope, $input, $payload, $actor, $sellableId, $expected, $delta,
            $reasonDigest
        ): array {
            $claim = Idempotency::claimWithin(
                $tx, $scope, 'stock_adjust', $input['idempotency_key'], $payload
            );
            if ('replay' === $claim['status']) {
                if ('sellable' !== $claim['entity_type']
                    || $sellableId !== $claim['entity_id']) {
                    throw new Transaction_Failure('pc_idempotency_result_invalid');
                }
                // Una versión actual podría ser más nueva que la respuesta
                // original. No devolverla como si perteneciera al replay.
                return [
                    'operation_id' => $claim['operation_id'],
                    'entity_id' => $sellableId,
                    'result' => 'already_applied',
                ];
            }
            if ('new' !== $claim['status']) {
                throw new Transaction_Failure('pc_idempotency_conflict');
            }
            $locked = $tx->lockSellables([$sellableId])[$sellableId];
            if (!self::belongsToThisSite($tx, $sellableId)) {
                throw new Transaction_Failure('pc_inventory_not_found');
            }
            if ('physical' !== (string) $locked['kind']
                || '1' !== (string) $locked['track_stock']) {
                throw new Transaction_Failure('pc_inventory_ineligible');
            }
            if ((string) $locked['row_version'] !== $expected) {
                throw new Transaction_Failure('pc_version_conflict');
            }

            $balance = Inventory_Balance::within($tx, $sellableId);
            try {
                $after = Money::addChecked($balance['on_hand_units'], $delta);
            } catch (\Throwable $ignored) {
                throw new Transaction_Failure('pc_inventory_ineligible');
            }
            $pending = Inventory_Balance::safeAdd(
                Inventory_Balance::safeAdd($balance['committed_units'], $balance['held_units']),
                $balance['admitted_units']
            );
            if ($after < 0 || $after < $pending) {
                // El comerciante no puede retirar unidades ya prometidas.
                throw new Transaction_Failure('pc_inventory_unavailable');
            }
            if (1 !== $tx->execute(
                'UPDATE %i SET stock_on_hand = %s WHERE id = %s '
                    . 'AND stock_on_hand = %s AND row_version = %s',
                [Inventory_Balance::table('pc_sellables'), (string) $after,
                    $sellableId, (string) $balance['on_hand_units'], $expected]
            )) {
                throw new Transaction_Failure('pc_version_conflict');
            }

            $rowVersion = $tx->advanceVersion('pc_sellables', $sellableId, $expected);
            Event_Journal::stockWithin(
                $tx, $actor, $claim['operation_id'], $sellableId, $delta,
                $after, 'manual_adjustment', null, 'manual_stock_adjustment'
            );
            Event_Journal::auditWithin(
                $tx, $actor, $claim['operation_id'], 'sellable',
                $sellableId, 'stock_reason_attested', 'manual_stock_adjustment',
                $reasonDigest, \hash('sha256', $sellableId . ':'
                    . (string) $after . ':' . $rowVersion)
            );
            Idempotency::completeLocalWithin(
                $tx, $claim['operation_id'], 'sellable', $sellableId, 200
            );
            return [
                'operation_id' => $claim['operation_id'],
                'entity_id' => $sellableId,
                'row_version' => $rowVersion,
                'result' => 'stock_adjusted',
            ];
        }, true);
    }

    /** Un vendible debe pertenecer a un post propio del CPT, no a WP ajeno. */
    private static function belongsToThisSite(
        Transaction_Context $tx, string $sellableId
    ): bool {
        global $wpdb;
        if (!$wpdb instanceof \wpdb || (int) \get_current_blog_id() <= 0) {
            return false;
        }
        $rows = $tx->rows(
            'SELECT s.id FROM %i s INNER JOIN %i p ON p.ID = s.product_post_id '
                . 'WHERE s.id = %s AND s.kind = %s AND p.post_type = %s '
                . 'AND p.post_status IN (%s, %s, %s) LIMIT 1',
            [Inventory_Balance::table('pc_sellables'), $wpdb->posts, $sellableId,
                'physical', Catalog_Controller::POST_TYPE, 'draft', 'private', 'publish']
        );
        return 1 === \count($rows);
    }
}
