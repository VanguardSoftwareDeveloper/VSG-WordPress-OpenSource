<?php
/**
 * IMP-22 — R8-20 y R8-21, consultas de pedidos A/O.
 *
 * Ningún GET permite mutate, pago, refund, nueva cita o archivo protegido.
 * Los valores de prueba del guard se leen del servidor y se repiten al
 * construir la respuesta dentro de una transacción local.
 *
 * @package PeruCommerce
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Orders_Controller
{
    private static bool $gates = false;
    private static bool $registered = false;

    public static function registerGates(): void
    {
        if (self::$gates) {
            return;
        }
        self::$gates = true;
        Module_Gates::registerOperation('orders','read', static fn (): bool => true);
        // orders.write corresponde a checkout/cobros/recursos, todavía no
        // implementados. No anunciar disponibilidad por el lector.
    }

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadOrders();
        Rest_Router::bind(
            'R8-20',
            static fn (array $ctx, \WP_REST_Request $_request): array => [
                'status'=>200,'body'=>Orders_Read::listing($ctx['query']),
            ],
            static fn (array $ctx, ?string $role): array =>
                self::guard('R8-20',$ctx,$role)
        );
        Rest_Router::bind(
            'R8-21',
            static fn (array $ctx, \WP_REST_Request $_request): array => [
                'status'=>200,
                'body'=>Orders_Read::detail($ctx['path']['id'],$ctx['query']),
            ],
            static fn (array $ctx, ?string $role): array =>
                self::guard('R8-21',$ctx,$role)
        );
    }

    /** @return array<string,mixed> */
    private static function guard(string $operation, array $ctx, ?string $role): array
    {
        if (!\in_array($role,['A','O'],true)
            || Access_Roles::currentRole() !== $role
            || !\current_user_can('perucommerce_orders_read')
            || (int) \get_current_blog_id() < 1) {
            return [];
        }
        $proof = [
            'authorized'=>true,'same_site'=>true,
            'resource_checked'=>true,'state_checked'=>true,
            'field_checked'=>true,
        ];
        if ('R8-20' === $operation) {
            return $proof;
        }
        $id = $ctx['path']['id'] ?? null;
        if (!\is_string($id)) {
            return [];
        }
        try {
            $id = Domain_Validation::sqlId($id);
            // La dependencia case_events_page -> case_id es de forma
            // de petición; el handler devuelve 422. No disfrazarla como
            // falta de permisos (403) en el permission_callback.
            $requestedCase = $ctx['query']['case_id'] ?? null;
            if (null !== $requestedCase && !\is_string($requestedCase)) {
                return [];
            }
            if (null !== $requestedCase) {
                $requestedCase = Domain_Validation::sqlId($requestedCase);
            }
            $valid = Transaction::run(static function (Transaction_Context $tx) use (
                $id,$requestedCase
            ): bool {
                $rows = $tx->rows(
                    'SELECT id FROM %i WHERE id = %s',
                    [Orders_Domain::table('pc_orders'),$id]
                );
                if (1 !== \count($rows)) {
                    return false;
                }
                if (null !== $requestedCase) {
                    $cases = $tx->rows(
                        'SELECT case_type, order_line_id FROM %i '
                            . 'WHERE id = %s AND order_id = %s',
                        [Orders_Domain::table('pc_cases'),$requestedCase,$id]
                    );
                    if (1!==\count($cases)
                        || !Orders_Cases::allowed((string)$cases[0]['case_type'])) {
                        return false;
                    }
                    if (Orders_Cases::isGuestType((string)$cases[0]['case_type'])) {
                        // Un caso R8-12 pertenece al pedido entero, no a
                        // una línea apta para ajuste parcial R8-26.
                        if (null!==$cases[0]['order_line_id']) {
                            return false;
                        }
                    } else {
                        if (null===$cases[0]['order_line_id']) {
                            return false;
                        }
                        $related=$tx->rows(
                            'SELECT id FROM %i WHERE id=%s AND order_id=%s '
                                . 'AND kind=%s',
                            [Orders_Domain::table('pc_order_lines'),
                                (string)$cases[0]['order_line_id'],$id,
                                'digital_access_dispute'===(string)$cases[0]['case_type']
                                    ? 'digital' : 'physical']
                        );
                        if (1!==\count($related)) {
                            return false;
                        }
                    }
                }
                return true;
            });
            if (!$valid) {
                return [];
            }
            $task = Orders_Read::allowedTask($id);
        } catch (\Throwable $ignored) {
            return [];
        }
        if (null !== $task) {
            $proof['task'] = $task;
            $proof['contact_scope_verified'] = true;
        }
        if ('A' === $role) {
            $proof['financial_purpose_verified'] = Orders_Read::canFinancial();
            $proof['fiscal_purpose_verified'] = Orders_Read::canFiscal();
            $proof['digital_purpose_verified'] = Orders_Read::canDigital();
            $proof['case_scope_verified'] = Orders_Read::canCases();
            if (null !== $requestedCase) {
                $proof['requested_case_detail'] = true;
                $proof['case_id_verified'] = true;
            }
        }
        return $proof;
    }
}
