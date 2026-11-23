<?php
/**
 * IMP-26 — R8-07 POST /public/checkout/contraentrega.
 *
 * Una sola obligación de cobro presencial. Confirma pedido, stock y una cita
 * por COMMIT local; nunca crea pago/intent MP ni acredita efectivo.
 * El HTTP del comprador no puede aportar precios/stock/estado de pago.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Checkout_COD_Controller
{
    private static bool $gates = false;
    private static bool $registered = false;

    /** Registrado después de checkout.write. No habilita MP ni R8-06. */
    public static function registerGates(): void
    {
        if (self::$gates) {
            return;
        }
        self::$gates = true;
        Module_Gates::registerOperation('orders','write', static function (): bool {
            if (!\post_type_exists(Catalog_Controller::POST_TYPE)
                || !Module_Gates::allows('checkout','write')) {
                return false;
            }
            Bootstrap::loadCatalog();
            return Catalog_Service::wordpressTablesTransactional();
        });
    }

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadCheckout();
        // R8-07 no se anuncia a la cotización si aún falta evidencia de
        // locks/schema o página HTTPS de seguimiento realmente publicada.
        // Una ruta inexistente es preferible a ofrecer un medio imposible.
        if (!Module_Gates::allows('orders','write')) {
            return;
        }
        try {
            Checkout_Tracking::configurePageUrl(self::trackingPage());
        } catch (\Throwable $ignored) {
            return;
        }

        // La idempotencia form se vincula a la cookie firmada, NO al email,
        // teléfono, IP, id de producto o identidad WordPress ficticia.
        Checkout_Form::registerVerifiers();
        Rest_Router::bind(
            'R8-07',
            [self::class,'confirm'],
            [self::class,'publicGuard']
        );
    }

    /** Guard R8-07: se verifica dos veces por el router, sin SQL. */
    public static function publicGuard(array $_ctx, ?string $role): array
    {
        if (null !== $role || !\function_exists('get_current_blog_id')
            || (int) \get_current_blog_id() < 1
            || !\post_type_exists(Catalog_Controller::POST_TYPE)) {
            return [];
        }
        try {
            Checkout_Form::verifiedDigest();
        } catch (\Throwable $ignored) {
            return [];
        }
        return ['authorized'=>true];
    }

    /** @return array{status:int,body:array<string,mixed>} */
    public static function confirm(array $ctx, \WP_REST_Request $_request): array
    {
        $body = $ctx['body'] ?? null;
        if (!\is_array($body)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'checkout');
        }
        if (!Module_Gates::allows('checkout','write')
            || !Module_Gates::allows('orders','write')) {
            throw new Rest_Failure('pc_module_not_ready',423,true);
        }
        $digest = Checkout_Form::verifiedDigest();
        $actor = Event_Actor::checkout($digest,'checkout_cash');
        $scope = [
            'actor_kind'=>'form',
            'actor_ref'=>$digest,
            'resource_kind'=>'checkout',
            'resource_id'=>'0',
            'purpose'=>'checkout_cash',
        ];

        // Una URL de seguimiento debe corresponder a una página WordPress
        // pública EXISTENTE. No inventar /seguimiento, ni crear nueva ruta
        // REST o exponer token en querystring/log.
        Checkout_Tracking::configurePageUrl(self::trackingPage());
        $capabilities = Quote_Service::capabilities();
        $result = Transaction::run(
            static function (Transaction_Context $tx) use (
                $actor,$scope,$body,$capabilities
            ): array {
                $prepared = Checkout_Coordinator::prepareWithin(
                    $tx,$actor,$scope,$body,'cash',$capabilities
                );
                if ('replay' === $prepared['status']) {
                    return self::replayWithin($tx,$prepared['claim']);
                }
                if ('new' !== $prepared['status']) {
                    // prepared externo COD nunca se asume cancelado ni se
                    // recrea pedido con una idempotency_key nueva.
                    throw new Transaction_Failure('pc_checkout_operation_pending');
                }
                return $prepared;
            },
            true
        );

        $first = 'new' === $result['status'];
        $payload = [
            'operation_id'=>$result['operation_id'],
            'order_id'=>$result['order_id'],
            'order_state'=>'CONFIRMADO',
            'payment_due_cents'=>$result['amount_due_cents'],
            'payment_state'=>'PENDIENTE',
            'tracking_link_issued'=>true ===
                ($result['tracking']['tracking_link_issued'] ?? false),
        ];
        // En replay no existe secreto recuperable. Nunca enviar null en el
        // DTO opcional y nunca reconstruir desde hash/BD.
        if ($first && \is_string(
                $result['tracking']['tracking_url_once'] ?? null
            )) {
            $payload['tracking_url_once'] = $result['tracking']['tracking_url_once'];
        }
        if ($first) {
            // Solo tras COMMIT inequívoco, sin reintento duradero de token.
            Checkout_Tracking::sendInitialEmailAfterCommit(
                $result['tracking'],
                $result['buyer_email'] ?? null
            );
        }
        return ['status'=>201,'body'=>$payload];
    }

    /**
     * El replay lee resultado histórico de una orden confirmada con misma
     * cookie, clave y digest exacto. Si el dinero/estado cambió después,
     * no presenta un saldo antiguo como pendiente: requiere recuperación
     * verificada sin reexponer el secreto ni repetir recursos.
     */
    private static function replayWithin(
        Transaction_Context $tx, array $claim
    ): array {
        if (($claim['entity_type'] ?? null) !== 'order'
            || ($claim['response_code'] ?? null) !== 201
            || !\is_string($claim['entity_id'] ?? null)) {
            throw new Transaction_Failure('pc_checkout_operation_pending');
        }
        $id = Domain_Validation::sqlId($claim['entity_id']);
        $locked = $tx->lockOrder($id);
        if ('CONFIRMADO' !== (string) $locked['status']
            || 'cash' !== (string) $locked['payment_method']) {
            throw new Transaction_Failure('pc_checkout_operation_pending');
        }
        $order = $tx->rows(
            'SELECT currency,total_cents FROM %i WHERE id = %s',
            [Orders_Domain::table('pc_orders'),$id]
        );
        if (1 !== \count($order) || 'PEN' !== $order[0]['currency']
            || (string) $order[0]['total_cents'] !==
                Money::toApi(Money::fromApi((string) $order[0]['total_cents']))) {
            throw new Transaction_Failure('pc_checkout_order_invalid');
        }
        $movements = $tx->rows(
            'SELECT id FROM %i WHERE order_id = %s LIMIT 1',
            [Orders_Domain::table('pc_payment_events'),$id]
        );
        if ([] !== $movements) {
            throw new Transaction_Failure('pc_checkout_operation_pending');
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_transaction_unavailable');
        }
        $table = Schema::tableNames($wpdb)['pc_guest_sessions'];
        $tracking = $tx->rows(
            'SELECT id FROM %i WHERE order_id = %s '
                . 'AND credential_kind = %s LIMIT 1',
            [$table,$id,'tracking']
        );
        if (1 !== \count($tracking)) {
            throw new Transaction_Failure('pc_tracking_order_invalid');
        }
        return [
            'status'=>'replay',
            'operation_id'=>$claim['operation_id'],
            'order_id'=>$id,
            'amount_due_cents'=>(string) $order[0]['total_cents'],
            'tracking'=>['tracking_link_issued'=>true,'tracking_url_once'=>null],
        ];
    }

    /** Solo una página pública existente, no un enlace reconstruido. */
    public static function trackingPage(): string
    {
        if (!\is_ssl()) {
            throw new Transaction_Failure('pc_tracking_url_not_ready');
        }
        $page = \get_page_by_path('seguimiento', OBJECT, 'page');
        if (!$page instanceof \WP_Post
            || 'publish' !== $page->post_status) {
            throw new Transaction_Failure('pc_tracking_url_not_ready');
        }
        $url = \get_permalink($page->ID);
        if (!\is_string($url) || '' === $url) {
            throw new Transaction_Failure('pc_tracking_url_not_ready');
        }
        return $url;
    }
}
