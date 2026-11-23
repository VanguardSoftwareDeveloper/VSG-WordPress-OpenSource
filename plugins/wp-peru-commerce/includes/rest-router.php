<?php
/**
 * IMP-12 — enrutador REST condicionado a implementaciones reales.
 *
 * Se registra el hook REST, pero NINGUNA de las 69 operaciones se publica
 * hasta que una fase de dominio registre handler, guard y módulo ready.
 * Todos los callbacks pertenecen al namespace del plugin; no hay stubs.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Rest_Router
{
    private const REST_PREFIX = '/peru-commerce/v1/';
    private const GATES = [
        'R8-01' => 'bootstrap.read',
        'R8-02' => 'catalog.read',
        'R8-03' => 'catalog.read',
        'R8-04' => 'appointments.read',
        'R8-05' => 'checkout.write',
        'R8-06' => 'payments.write',
        'R8-07' => 'checkout.write',
        'R8-08' => 'orders.read',
        'R8-09' => 'orders.read',
        'R8-10' => 'orders.read',
        'R8-11' => 'orders.read',
        'R8-12' => 'guest.requests',
        'R8-13' => 'digital.read',
        'R8-14' => 'digital.read',
        'R8-15' => 'complaints.write',
        'R8-16' => 'delivery.read',
        'R8-17' => 'payments.webhook',
        'R8-19' => 'bootstrap.staff',
        'R8-20' => 'orders.read',
        'R8-21' => 'orders.read',
        'R8-22' => 'physical.fulfill',
        'R8-23' => 'orders.cancel',
        'R8-24' => 'cash.write',
        'R8-25' => 'cash.write',
        'R8-26' => 'orders.adjust',
        'R8-27' => 'refunds.record',
        'R8-28' => 'delivery.change',
        'R8-29' => 'reconciliation.read',
        'R8-30' => 'reconciliation.write',
        'R8-31' => 'catalog.read',
        'R8-32' => 'catalog.write',
        'R8-33' => 'catalog.write',
        'R8-34' => 'catalog.write',
        'R8-35' => 'inventory.write',
        'R8-36' => 'appointments.read',
        'R8-37' => 'appointments.write',
        'R8-38' => 'appointments.write',
        'R8-39' => 'notifications.read',
        'R8-40' => 'notifications.write',
        'R8-41' => 'complaints.read',
        // Responder un expediente existente no depende de nuevas altas.
        'R8-42' => 'complaints.respond',
        'R8-43' => 'privacy.read',
        'R8-44' => 'privacy.resolve',
        'R8-45' => 'settings.read',
        'R8-46' => 'settings.write',
        'R8-47' => 'settings.write',
        'R8-48' => 'digital.write',
        'R8-49' => 'settings.read',
        'R8-50' => 'privacy.write',
        'R8-51' => 'delivery.read',
        'R8-52' => 'delivery.write',
        'R8-53' => 'appointments.write',
        'R8-54' => 'appointments.write',
        'R8-55' => 'orders.logistics_cases',
        'R8-56' => 'orders.logistics_cases',
        'R8-57' => 'orders.logistics_cases',
        'R8-58' => 'tracking.reissue',
        'R8-59' => 'digital.write',
        'R8-60' => 'digital.write',
        'R8-61' => 'digital.cases',
        'R8-62' => 'digital.read',
        'R8-63' => 'digital.write',
        'R8-64' => 'documents.write',
        'R8-65' => 'complaints.read',
        'R8-66' => 'privacy.read',
        'R8-67' => 'complaints.read',
        'R8-68' => 'privacy.read',
        'R8-69' => 'orders.manual_cases',
        'R8-70' => 'orders.manual_cases',
    ];

    /** @var array<string,array{handler:callable,gate:string}> */
    private static array $bindings = [];

    /** @var array<string,bool> Rutas registradas realmente por WP. */
    private static array $published = [];

    /** @var \WeakMap<\WP_REST_Request,array{id:string,context:array}>|null */
    private static ?\WeakMap $verified = null;

    private static bool $hooks = false;
    private static bool $registrationClosed = false;

    public static function registerHooks(): void
    {
        if (self::$hooks) {
            return;
        }
        self::$hooks = true;
        \add_action('rest_api_init', [self::class, 'registerRoutes'], 10);
        \add_filter('rest_post_dispatch', [self::class, 'safeHeaders'], 10, 3);
    }

    /**
     * Se llama SOLO desde un controlador de negocio implementado.
     * No acepta binarios/multipart, ni enlaces de entrega única que exijan
     * transporte especial. Sus rutas se integrarán en su fase específica.
     * Los consumidores deben registrar también su módulo vía Module_Gates.
     */
    public static function bind(
        string $operationId, callable $handler, callable $guard
    ): void {
        Bootstrap::loadRest();
        $rule = Access_Contract::operation($operationId);
        $contract = Rest_Contract::operation($operationId);
        if (null === $rule || null === $contract
            || !isset(self::GATES[$operationId])
            || isset(self::$bindings[$operationId])
            || self::$registrationClosed
            // Los controladores IMP-13+ enlazan durante rest_api_init (prio 5)
            // ANTES de registerRoutes() (prio 10). Fuera de ese hook,
            // después de su ejecución, se rechazan registros tardíos.
            || (\did_action('rest_api_init') > 0
                && !\doing_action('rest_api_init'))
            || \in_array($operationId, [
                'R8-14','R8-48','R8-62','R8-63','R8-67','R8-68',
            ], true)
            || \in_array('multipart/form-data', $contract['bodyMedia'], true)) {
            throw new \LogicException('pc_rest_route_not_bindable');
        }

        // Guard y callback deben existir juntos, antes de registrar la ruta.
        // Una closure vacía es un error del controlador futuro, no un permiso
        // concedido por el enrutador; se exige revisión en su fase.
        Access_Control::registerGuard($operationId, $guard);
        self::$bindings[$operationId] = [
            'handler' => $handler,
            'gate' => self::GATES[$operationId],
        ];
    }

    /**
     * Evidencia local de disponibilidad de una operación REST concreta:
     * binding + register_rest_route() exitoso + gate vigente. No demuestra
     * autorización de ese usuario, que se revalida en permission_callback.
     */
    public static function isOperationReady(string $operationId): bool
    {
        if (!isset(self::$published[$operationId], self::$bindings[$operationId])) {
            return false;
        }
        [$module, $action] = \explode('.', self::$bindings[$operationId]['gate'], 2);
        return Module_Gates::allows($module, $action);
    }

    public static function registerRoutes(): void
    {
        // Después de prioridad 10 no aceptar bindings silenciosamente
        // omitidos hasta la próxima petición.
        self::$registrationClosed = true;
        if ([] === self::$bindings) {
            return;
        }
        Bootstrap::loadRest();
        foreach (self::$bindings as $operationId => $binding) {
            [$module, $action] = \explode('.', $binding['gate'], 2);
            // No registrar rutas si la implementación/gates del módulo
            // no acreditan readiness. La mera matriz REST no habilita nada.
            if (!Module_Gates::allows($module, $action)) {
                continue;
            }
            $rule = Access_Contract::operation($operationId);
            $contract = Rest_Contract::operation($operationId);
            if (null === $rule || null === $contract) {
                continue;
            }
            try {
                Access_Control::requireRoute(
                    $operationId, $rule['method'], $rule['path']
                );
                $route = self::routePattern($rule['path']);
            } catch (\Throwable $ignored) {
                continue;
            }

            $success = \register_rest_route(
                Rest_Contract::NAMESPACE,
                $route,
                [
                    'methods' => $rule['method'],
                    'permission_callback' => static fn (\WP_REST_Request $req) =>
                        self::authorize($operationId, $req),
                    'callback' => static fn (\WP_REST_Request $req) =>
                        self::dispatch($operationId, $req),
                ],
                false
            );
            if (true === $success) {
                self::$published[$operationId] = true;
            }
        }
    }

    /** Se invoca desde permission_callback, nunca desde el navegador. */
    private static function authorize(string $operationId, \WP_REST_Request $request): true|\WP_Error
    {
        try {
            self::ensureGate($operationId);
            Rest_Throttle::check($operationId, $request);
            $context = Rest_Validator::request($request, $operationId);
            Access_Control::require(
                $operationId, $context,
                $request->get_header('X-WP-Nonce')
            );
            self::$verified ??= new \WeakMap();
            self::$verified[$request] = ['id' => $operationId, 'context' => $context];
            return true;
        } catch (\Throwable $error) {
            return Rest_Errors::fromThrowable($operationId, $error);
        }
    }

    /** @return \WP_REST_Response|\WP_Error */
    private static function dispatch(string $operationId, \WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            if (null === self::$verified || !isset(self::$verified[$request])
                || self::$verified[$request]['id'] !== $operationId) {
                throw new Rest_Failure('pc_access_denied', 403);
            }
            self::ensureGate($operationId);
            $context = self::$verified[$request]['context'];
            // Revalidar permisos tras el trabajo efectuado en el callback
            // permission_callback; el dominio revalida bajo locks al mutar.
            Access_Control::require(
                $operationId, $context,
                $request->get_header('X-WP-Nonce')
            );
            $result = (self::$bindings[$operationId]['handler'])($context, $request);
            if (!\is_array($result)
                || !\is_int($result['status'] ?? null)
                || !\is_array($result['body'] ?? null)) {
                throw new Rest_Failure('pc_result_invalid', 500);
            }

            $body = $result['body'];
            $access = Access_Contract::operation($operationId);
            if (\in_array($access['actor'], ['staff', 'admin_only'], true)) {
                $body = Access_Projection::staff(
                    $operationId, $body, $context,
                    $request->get_header('X-WP-Nonce')
                );
            }
            Rest_Validator::response($operationId, $result['status'], $body);
            $response = new \WP_REST_Response($body, $result['status']);

            $safeCache = true === ($result['cache_public'] ?? false)
                && \in_array($operationId, ['R8-02','R8-03','R8-16'], true)
                && 'public' === ($access['actor'] ?? '');
            $response->header(
                'Cache-Control',
                $safeCache ? 'public, max-age=30' : 'private, no-store, max-age=0'
            );
            $response->header('Referrer-Policy', 'no-referrer');
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-Robots-Tag', 'noindex');
            return $response;
        } catch (\Throwable $error) {
            return Rest_Errors::fromThrowable($operationId, $error);
        } finally {
            if (null !== self::$verified && isset(self::$verified[$request])) {
                unset(self::$verified[$request]);
            }
        }
    }

    /**
     * WP core puede crear errores antes del callback; impedir caché de
     * ellos para el namespace privado sin tocar rutas de otros plugins.
     */
    public static function safeHeaders(
        mixed $response, \WP_REST_Server $_server, \WP_REST_Request $request
    ): mixed {
        if (!\str_starts_with($request->get_route(), self::REST_PREFIX)) {
            return $response;
        }
        if ($response instanceof \WP_REST_Response) {
            $headers = $response->get_headers();
            if (!isset($headers['Cache-Control'])) {
                $response->header('Cache-Control', 'private, no-store, max-age=0');
            }
            $response->header('Referrer-Policy', 'no-referrer');
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-Robots-Tag', 'noindex');
        }
        return $response;
    }

    private static function ensureGate(string $operationId): void
    {
        if (!isset(self::$bindings[$operationId])) {
            throw new Rest_Failure('pc_access_denied', 403);
        }
        [$module, $action] = \explode('.', self::$bindings[$operationId]['gate'], 2);
        if (!Module_Gates::allows($module, $action)) {
            throw new Rest_Failure('pc_module_not_ready', 423, true);
        }
    }

    /** De plantilla OpenAPI a patrón WordPress; nunca URL del navegador. */
    private static function routePattern(string $path): string
    {
        if (!\preg_match('/\A\/[a-z0-9_\/{}-]+\z/D', $path)) {
            throw new \LogicException('pc_rest_template_invalid');
        }
        $pattern = \preg_replace_callback(
            '/\{([a-z][a-z0-9_]*)\}/',
            static function (array $m): string {
                return '(?P<' . $m[1] . '>' .
                    ('evidence_ref' === $m[1]
                        ? '[A-Za-z0-9_-]{16,128}' : '[1-9][0-9]*')
                    . ')';
            },
            $path
        );
        if (!\is_string($pattern) || \str_contains($pattern, '{')) {
            throw new \LogicException('pc_rest_template_invalid');
        }
        return $pattern;
    }
}
