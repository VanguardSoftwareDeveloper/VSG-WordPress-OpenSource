<?php
/**
 * IMP-21 — rutas especiales R8-48/62/63.
 *
 * Rest_Router prohíbe explícitamente multipart/binario. Estas rutas usan
 * register_rest_route() y el MISMO Access_Control para sesión WP+nonce,
 * actor A, capability, recurso/instalación, campo/estado/finalidad.
 *
 * R8-62 transmite desde PHP exclusivamente tras validación y auditoría;
 * jamás devuelve private_storage_key, ruta ni URL permanente.
 */
declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) { exit; }

final class Digital_Controller
{
    private static bool $gates = false;
    private static bool $registered = false;

    /** @var \WeakMap<\WP_REST_Request,array<string,mixed>>|null */
    private static ?\WeakMap $prepared = null;

    public static function registerGates(): void
    {
        if (self::$gates) {
            return;
        }
        self::$gates = true;
        // Mantener init liviano: cargar catálogo/REST/DTO y el filesystem
        // solo al registrar las rutas especiales en rest_api_init.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-private-storage.php';
        Module_Gates::registerOperation('digital','read', [self::class,'storageConfigured']);
        Module_Gates::registerOperation('digital','write', [self::class,'storageConfigured']);
    }

    public static function storageConfigured(): bool
    {
        try {
            Digital_Private_Storage::root();
            // Los locks de wp_posts y wp_postmeta forman parte de la
            // misma transacción que una asignación digital.
            return \class_exists(Catalog_Service::class, false)
                && Catalog_Service::wordpressTablesTransactional();
        } catch (\Throwable $ignored) {
            return false;
        }
    }

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        Bootstrap::loadDigital();

        Access_Control::registerGuard('R8-48',
            static fn (array $ctx, ?string $role): array => self::guard($ctx, $role, 'R8-48'));
        Access_Control::registerGuard('R8-62',
            static fn (array $ctx, ?string $role): array => self::guard($ctx, $role, 'R8-62'));
        Access_Control::registerGuard('R8-63',
            static fn (array $ctx, ?string $role): array => self::guard($ctx, $role, 'R8-63'));

        if (Module_Gates::allows('digital','write')) {
            self::add('R8-48','/admin/digital-files','POST');
            self::add('R8-63','/admin/digital-files/(?P<id>[1-9][0-9]*)/replacements','POST');
        }
        if (Module_Gates::allows('digital','read')) {
            self::add('R8-62','/admin/digital-files/(?P<id>[1-9][0-9]*)/download','GET');
            \add_filter('rest_pre_serve_request', [self::class,'streamPrepared'], 10, 4);
        }
    }

    private static function add(string $op, string $route, string $method): void
    {
        $template = match ($op) {
            'R8-48' => '/admin/digital-files',
            'R8-62' => '/admin/digital-files/{id}/download',
            'R8-63' => '/admin/digital-files/{id}/replacements',
            default => throw new \LogicException('pc_digital_route_invalid'),
        };
        Access_Control::requireRoute($op, $method, $template);
        if (true !== \register_rest_route(
            Rest_Contract::NAMESPACE,
            $route,
            [
                'methods' => $method,
                'permission_callback' => static fn (\WP_REST_Request $req) =>
                    self::authorize($op,$req),
                'callback' => static fn (\WP_REST_Request $req) =>
                    self::handle($op,$req),
            ],
            false
        )) {
            throw new \LogicException('pc_digital_route_failed');
        }
    }

    private static function authorize(string $op, \WP_REST_Request $req): true|\WP_Error
    {
        try {
            if (!Module_Gates::allows('digital',
                'R8-62' === $op ? 'read' : 'write')) {
                throw new Transaction_Failure('pc_digital_not_ready');
            }
            Access_Control::require($op,self::context($op,$req),
                $req->get_header('X-WP-Nonce'));
            return true;
        } catch (\Throwable $error) {
            return self::safeError($op,$error);
        }
    }

    private static function handle(string $op, \WP_REST_Request $req): \WP_REST_Response|\WP_Error
    {
        try {
            $context = self::context($op,$req);
            Access_Control::require($op,$context,$req->get_header('X-WP-Nonce'));
            if ('R8-62' === $op) {
                if (null !== $req->get_header('range')) {
                    throw new Rest_Failure('pc_field_invalid',422);
                }
                $file = Digital_Files::byId($context['path']['id']);
                $path = Digital_Private_Storage::verify(
                    (string) $file['private_storage_key'],
                    \strtolower((string) $file['content_sha256']),
                    (string) $file['byte_size']
                );
                // GET administrativo auditable: lectura autorizada con
                // finalidad categórica, no es descarga del comprador.
                self::auditRead($context['path']['id'], $context['query']['purpose']);
                self::$prepared ??= new \WeakMap();
                self::$prepared[$req] = [
                    'path' => $path,
                    'size' => (string) $file['byte_size'],
                    'filename' => (string) $file['original_filename'],
                ];
                $response = new \WP_REST_Response(['stream_pending'=>true],200);
                $response->header('Cache-Control','private, no-store');
                return $response;
            }

            $params = $context['body'];
            $upload = Digital_Private_Storage::inspectUpload(
                $context['file'], 'R8-48' === $op
                    ? $params['filename'] : $context['file']['name']
            );
            // La operación de bytes se confirma antes de SQL; si hay
            // rollback/COMMIT incierto, conservar binario privado huérfano.
            $stored = Digital_Private_Storage::store($upload);
            $body = 'R8-48' === $op
                ? Digital_Files::create($params,$stored)
                : Digital_Files::replace($context['path']['id'],$params,$stored);
            // No responder protected:true si el COMMIT fue efectivo
            // pero el archivo físico quedó ausente/inconsistente.
            $fileId = 'R8-48' === $op ? $body['file_id'] : $body['new_file_id'];
            $persisted = Digital_Files::byId($fileId);
            Digital_Private_Storage::verify(
                (string) $persisted['private_storage_key'],
                \strtolower((string) $persisted['content_sha256']),
                (string) $persisted['byte_size']
            );

            $body = Access_Projection::staff(
                $op,$body,$context,$req->get_header('X-WP-Nonce')
            );
            Rest_Validator::response($op,201,$body);
            $response = new \WP_REST_Response($body,201);
            $response->header('Cache-Control','private, no-store');
            $response->header('X-Content-Type-Options','nosniff');
            return $response;
        } catch (\Throwable $error) {
            return self::safeError($op,$error);
        }
    }

    /**
     * Contexto server-side; no aceptar propiedades extra ni paths ajenos.
     * El cuerpo multipart se valida explícitamente sin pasar por el
     * validador JSON, el cual rechaza deliberadamente multipart.
     */
    private static function context(string $op, \WP_REST_Request $req): array
    {
        if ($req->get_method() !== ('R8-62' === $op ? 'GET' : 'POST')) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $ctx = ['body'=>[],'path'=>[],'query'=>[]];
        $url = $req->get_url_params();
        $query = $req->get_query_params();
        if ([] !== \array_diff(\array_keys($url),
                'R8-48' === $op ? [] : ['id'])) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        if ('R8-48' !== $op) {
            try {
                $ctx['path']['id'] = Domain_Validation::sqlId(
                    (string) ($url['id'] ?? '')
                );
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_field_invalid',422,false,'id');
            }
        }
        $queryKeys = \array_keys($query);
        \sort($queryKeys);
        if ('R8-62' === $op) {
            // El modo sin permalinks puede incluir rest_route.
            if ([] !== \array_diff($queryKeys,['purpose','rest_route'])
                || !\is_string($query['purpose'] ?? null)
                || !\preg_match('/\A[a-z][a-z0-9_]{0,99}\z/D', $query['purpose'])) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
            $ctx['query']['purpose'] = $query['purpose'];
            return $ctx;
        }
        if ([] !== \array_diff($queryKeys,['rest_route'])
            || !\str_starts_with(\strtolower((string) $req->get_header('content-type')),
                'multipart/form-data;')) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        $fields = $req->get_body_params();
        $files = $req->get_file_params();
        $expected = 'R8-48' === $op
            ? ['filename','idempotency_key','product_id','sellable_id','slot_key']
            : ['expected_version','idempotency_key','reason'];
        $keys = \array_keys($fields);
        \sort($keys);
        $required = $expected;
        \sort($required);
        if ($keys !== $required || \array_keys($files) !== ['file']
            || !\is_array($files['file'])
            || !\is_string($files['file']['name'] ?? null)) {
            throw new Rest_Failure('pc_field_invalid',422);
        }
        foreach ($fields as $key=>$value) {
            if (!\is_string($value)
                || !\mb_check_encoding($value,'UTF-8')) {
                throw new Rest_Failure('pc_field_invalid',422);
            }
        }
        $key = $fields['idempotency_key'];
        if (\mb_strlen($key,'UTF-8') < 16 || \mb_strlen($key,'UTF-8') > 128
            || \preg_match('/[\x00-\x20\x7F]/',$key)) {
            throw new Rest_Failure('pc_field_invalid',422,false,'idempotency_key');
        }
        if ('R8-48' === $op) {
            try {
                $fields['product_id'] = Domain_Validation::sqlId($fields['product_id']);
                $fields['sellable_id'] = Domain_Validation::sqlId($fields['sellable_id']);
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_field_invalid',422,false,'product_id');
            }
            $fields['slot_key'] = Digital_Files::slot($fields['slot_key']);
            Digital_Private_Storage::filename($fields['filename']);
        } else {
            try {
                $fields['expected_version'] = Domain_Validation::sqlId($fields['expected_version']);
            } catch (\Throwable $ignored) {
                throw new Rest_Failure('pc_field_invalid',422,false,'expected_version');
            }
            if ('' === \trim($fields['reason'])
                || \mb_strlen($fields['reason'],'UTF-8') > 1000) {
                throw new Rest_Failure('pc_field_invalid',422,false,'reason');
            }
        }
        $ctx['body'] = $fields;
        $ctx['file'] = $files['file'];
        return $ctx;
    }

    /** Los errores de autorización de IMP-11 son excepciones genéricas. */
    private static function safeError(string $op, \Throwable $error): \WP_Error
    {
        if ('pc_access_denied' === $error->getMessage()) {
            return Rest_Errors::make($op,'pc_access_denied');
        }
        return Rest_Errors::fromThrowable($op,$error);
    }

    /** Guard por recurso: nunca confiar en la palabra 'verified' del body. */
    public static function guard(array $ctx, ?string $role, string $op): array
    {
        if ('A' !== $role
            || 'A' !== Access_Roles::currentRole()
            || !\current_user_can('perucommerce_digital_files_manage')
            || (int) \get_current_blog_id() < 1) {
            return [];
        }
        try {
            if ('R8-48' === $op) {
                $body = $ctx['body'];
                $ok = Transaction::run(static function (Transaction_Context $tx) use ($body): bool {
                    Digital_Files::productWithin($tx, $body['product_id'],
                        $body['sellable_id'], false);
                    return true;
                });
                if (!$ok) {
                    return [];
                }
            } else {
                $f = Digital_Files::byId($ctx['path']['id']);
                if ('R8-62' === $op) {
                    Digital_Private_Storage::verify(
                        (string) $f['private_storage_key'],
                        \strtolower((string) $f['content_sha256']),
                        (string) $f['byte_size']
                    );
                }
            }
        } catch (\Throwable $ignored) {
            return [];
        }
        $proof = [
            'authorized'=>true,'same_site'=>true,
            'resource_checked'=>true,'state_checked'=>true,
            'field_checked'=>true,
        ];
        if ('R8-62' === $op) {
            $proof['purpose_checked'] = true;
            $proof['digital_file_scope_verified'] = true;
            $proof['private_storage_verified'] = true;
        }
        return $proof;
    }

    /** Una lectura GET administrativamente justificada crea auditoría mínima. */
    private static function auditRead(string $id, string $purpose): void
    {
        Digital_Files::staff();
        $digest = \hash('sha256', $purpose);
        Transaction::run(static function (Transaction_Context $tx) use ($id, $digest): void {
            $tx->execute(
                'INSERT INTO %i (entity_type, entity_id, operation_code, '
                    . 'actor_kind, actor_user_id, reason_code, safe_after_hash, occurred_at) '
                    . 'VALUES (%s,%s,%s,%s,%s,%s,UNHEX(%s),UTC_TIMESTAMP(6))',
                [Schema::tableNames($GLOBALS['wpdb'])['pc_audit_log'],
                    'digital_file',$id,'digital_file_access_prepared',
                    'staff',(string) \get_current_user_id(),'admin_file_read',$digest]
            );
        });
    }

    /**
     * Emite binario en el hook REST específico, no como payload JSON.
     * Nunca servir un recurso si ya lo sirvió otro filtro.
     */
    public static function streamPrepared(
        bool $served, \WP_HTTP_Response $response,
        \WP_REST_Request $request, \WP_REST_Server $_server
    ): bool {
        if ($served || null === self::$prepared
            || !isset(self::$prepared[$request])
            || 200 !== $response->get_status()) {
            return $served;
        }
        $prepared = self::$prepared[$request];
        unset(self::$prepared[$request]);

        try {
            if (!Module_Gates::allows('digital','read')
                || null === $request->get_header('X-WP-Nonce')) {
                throw new Transaction_Failure('pc_digital_access_denied');
            }
            $handle = @\fopen($prepared['path'],'rb');
            if (false === $handle) {
                throw new Transaction_Failure('pc_private_storage_unavailable');
            }
            $stat = \fstat($handle);
            if (!\is_array($stat)
                || (string) ($stat['size'] ?? '') !== $prepared['size']) {
                \fclose($handle);
                throw new Transaction_Failure('pc_private_storage_unavailable');
            }
        } catch (\Throwable $ignored) {
            \status_header(503);
            \header('Cache-Control: private, no-store');
            \header('Content-Type: application/json; charset=utf-8');
            echo '{"code":"pc_dependency_unavailable"}';
            return true;
        }

        $filename = \preg_replace(
            '/[^A-Za-z0-9._-]/','_', $prepared['filename']
        );
        if (!\is_string($filename) || '' === $filename) {
            $filename = 'archivo.bin';
        }
        \status_header(200);
        \header('Content-Type: application/octet-stream');
        \header('Content-Disposition: attachment; filename="' . $filename . '"');
        \header('Content-Length: ' . $prepared['size']);
        \header('Cache-Control: private, no-store');
        \header('X-Content-Type-Options: nosniff');
        \header('Referrer-Policy: no-referrer');
        \header('X-Robots-Tag: noindex, nofollow');
        \header('Accept-Ranges: none');
        while (!\feof($handle)) {
            $chunk = \fread($handle, 65536);
            if (!\is_string($chunk) || '' === $chunk) {
                break;
            }
            echo $chunk;
            \flush();
        }
        \fclose($handle);
        return true;
    }
}
