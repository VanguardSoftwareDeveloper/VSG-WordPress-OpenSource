<?php
/**
 * IMP-08 — idempotencia local, replay seguro y correlación pre-HTTP.
 *
 * Fuente: E05-G/D, E07, docs/18/20/21/23 y OpenAPI 0.12.0-draft.
 * Esta clase NO implementa una pasarela ni concede autorización por sí misma.
 * Cada controlador revalida actor, cookie/sesión y pertenencia del recurso.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Idempotency
{
    private const PHASE_PREPARED = 'prepared';
    private const PHASE_EXTERNAL_PENDING = 'external_pending';
    private const PHASE_RECONCILE = 'reconcile';
    private const PHASE_COMPLETE = 'complete';
    private const MAX_REQUEST_BYTES = 65536;

    /** @var callable|null Verifica de forma efectiva guest/form/system en cada petición. */
    private static $scopeVerifier = null;
    /** R8-15: cookie/CSRF independientes de checkout, verificador propio. */
    private static $complaintScopeVerifier = null;
    /** @var callable|null Identidad guest: distinta de la cookie checkout. */
    private static $guestScopeVerifier = null;
    /** @var callable|null Verificación específica de raíz system de webhook. */
    private static $systemScopeVerifier = null;
    /** La confirmación core WP Privacy NO comparte raíz con MP/webhook. */
    private static $privacySystemScopeVerifier = null;
    private static $privacyExportSystemScopeVerifier = null;

    /** @var callable|null Autorización de reconciliación server-side sin sesión guest. */
    private static $recoveryAuthorizer = null;
    /** @var callable|null Autoridad exclusiva de recuperación cron IMP-30. */
    private static $maintenanceAuthorizer = null;

    /**
     * Solo el adaptador MP registrado podrá autorizar conciliaciones por
     * cuenta/ambiente y operación. No aceptar un booleano del navegador.
     */
    public static function registerRecoveryAuthorizer(callable $authorizer): void
    {
        if (null !== self::$recoveryAuthorizer) {
            throw new \LogicException('pc_idempotency_recovery_authorizer_duplicate');
        }
        self::$recoveryAuthorizer = $authorizer;
    }

    /** Recuperación por tarea de conciliación con contexto temporal propio. */
    public static function registerMaintenanceAuthorizer(callable $authorizer): void
    {
        if (null!==self::$maintenanceAuthorizer) {
            throw new \LogicException('pc_mp_maintenance_duplicate');
        }
        self::$maintenanceAuthorizer=$authorizer;
    }

    /**
     * Los futuros controladores del plugin registran aquí su comprobación
     * server-side de sesión guest, cookie same-origin o firma de evento.
     * Hasta contar con esa implementación, esos actores DENIEGAN por defecto.
     */
    public static function registerComplaintScopeVerifier(callable $verifier): void
    {
        if (null!==self::$complaintScopeVerifier) {
            throw new \LogicException('pc_complaint_idempotency_verifier_duplicate');
        }
        self::$complaintScopeVerifier=$verifier;
    }

    public static function registerScopeVerifier(callable $verifier): void
    {
        if (null !== self::$scopeVerifier) {
            throw new \LogicException('pc_idempotency_verifier_duplicate');
        }
        self::$scopeVerifier = $verifier;
    }

    /** IMP-33: R8-12 comparte sesión autenticada con IMP-31/32, no form. */
    public static function registerGuestScopeVerifier(callable $verifier): void
    {
        if (null !== self::$guestScopeVerifier) {
            throw new \LogicException('pc_idempotency_guest_verifier_duplicate');
        }
        self::$guestScopeVerifier = $verifier;
    }

    public static function registerPrivacySystemScopeVerifier(callable $verifier):void
    {
        if(null!==self::$privacySystemScopeVerifier){
            throw new \LogicException('pc_privacy_idempotency_duplicate');
        }
        self::$privacySystemScopeVerifier=$verifier;
    }

    public static function registerPrivacyExportScopeVerifier(callable $check):void
    {
        if(null!==self::$privacyExportSystemScopeVerifier){
            throw new \LogicException('pc_privacy_export_scope_duplicate');
        }
        self::$privacyExportSystemScopeVerifier=$check;
    }

    /** Evitar reutilizar la cookie de comprador como identidad system. */
    public static function registerSystemScopeVerifier(callable $verifier): void
    {
        if (null!==self::$systemScopeVerifier) {
            throw new \LogicException('pc_idempotency_system_verifier_duplicate');
        }
        self::$systemScopeVerifier=$verifier;
    }

    /**
     * Un scope procede únicamente de identidad VERIFICADA por el servidor:
     * staff=usuario WP autenticado; guest=sesión legitimada; form=cookie
     * same-origin verificada (digest opaco); system=fuente interna.
     * No pasar aquí email, teléfono, nombre, OTP, token ni datos del navegador.
     *
     * @param array{actor_kind:string,actor_ref:string,resource_kind:string,resource_id:string,purpose:string} $scope
     * @param array<string,mixed> $payload JSON decodificado por el controlador
     * @return array{operation_id:string,status:string,phase:string,entity_type:?string,entity_id:?string,response_code:?int}
     */
    public static function claim(array $scope, string $operationType, string $key, array $payload): array
    {
        return Transaction::run(
            static fn (Transaction_Context $tx): array =>
                self::claimWithin($tx, $scope, $operationType, $key, $payload),
            true
        );
    }

    /**
     * Para mutaciones locales, llamar al comienzo del MISMO callback de
     * Transaction::run() que crea pedido/caso/evento y completeLocalWithin().
     * Respetar "status": sólo "new" autoriza iniciar efectos.
     *
     * @param array{actor_kind:string,actor_ref:string,resource_kind:string,resource_id:string,purpose:string} $scope
     * @param array<string,mixed> $payload
     * @return array{operation_id:string,status:string,phase:string,entity_type:?string,entity_id:?string,response_code:?int}
     */
    public static function claimWithin(
        Transaction_Context $tx,
        array $scope,
        string $operationType,
        string $key,
        array $payload
    ): array {
        return self::loadWithin($tx, $scope, $operationType, $key, $payload, true);
    }

    /**
     * La recuperación solo lee; el alta en una transacción local es explícita.
     * Así no se valida dos veces una identidad sensible ni se crea una
     * operación nueva por un simple intento de reconciliación.
     *
     * @param array<string,string> $scope
     * @param array<string,mixed> $payload
     * @return array{operation_id:string,status:string,phase:string,entity_type:?string,entity_id:?string,response_code:?int}
     */
    private static function loadWithin(
        Transaction_Context $tx,
        array $scope,
        string $operationType,
        string $key,
        array $payload,
        bool $create
    ): array {
        [$scopeHex, $keyHex, $requestHex] = self::digests($scope, $operationType, $key, $payload);
        $table = self::table();
        $rows = self::find($tx, $table, $scopeHex, $keyHex);
        $created = false;

        if ([] === $rows) {
            if (!$create) {
                throw new Transaction_Failure('pc_idempotency_missing');
            }
            try {
                $tx->execute(
                    'INSERT INTO %i (scope_hash, key_hash, request_hash, operation_type, '
                        . 'technical_phase, created_at, updated_at) '
                        . 'VALUES (UNHEX(%s), UNHEX(%s), UNHEX(%s), %s, %s, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))',
                    [$table, $scopeHex, $keyHex, $requestHex, $operationType, self::PHASE_PREPARED]
                );
            } catch (Transaction_Failure $error) {
                if ('pc_unique_conflict' === $error->getMessage()) {
                    // Otra petición insertó (scope,key) primero: reintentar
                    // únicamente tras ROLLBACK confirmado y volver a leer.
                    throw new Transaction_Failure('pc_idempotency_claim_race', true);
                }
                throw $error;
            }
            $rows = self::find($tx, $table, $scopeHex, $keyHex);
            $created = true;
        }

        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_idempotency_record_invalid');
        }
        $record = $rows[0];
        $id = Domain_Validation::sqlId((string) $record['id']);
        $tx->lockOperation($id);

        if (!\hash_equals($requestHex, \strtolower((string) $record['request_digest']))
            || !\hash_equals($scopeHex, \strtolower((string) $record['scope_digest']))
            || !\hash_equals($keyHex, \strtolower((string) $record['key_digest']))
            || (string) $record['operation_type'] !== $operationType) {
            throw new Transaction_Failure('pc_idempotency_conflict');
        }

        $phase = (string) $record['technical_phase'];
        if (!\in_array($phase, [
            self::PHASE_PREPARED,
            self::PHASE_EXTERNAL_PENDING,
            self::PHASE_RECONCILE,
            self::PHASE_COMPLETE,
        ], true)) {
            throw new Transaction_Failure('pc_idempotency_phase_invalid');
        }

        if ($created && self::PHASE_PREPARED !== $phase) {
            throw new Transaction_Failure('pc_idempotency_record_invalid');
        }
        $status = $created ? 'new' : match ($phase) {
            self::PHASE_COMPLETE => 'replay',
            self::PHASE_PREPARED => 'pending',
            default => 'reconcile',
        };

        $entityType = $record['entity_type'];
        $entityId = $record['entity_id'];
        $code = $record['response_code'];
        if (self::PHASE_COMPLETE === $phase
            && (!\is_string($entityType) || '' === $entityType
                || (!\is_int($entityId) && !\is_string($entityId))
                || !\preg_match('/\A[1-9][0-9]*\z/D', (string) $entityId)
                || null === $code || (int) $code < 200 || (int) $code > 299
                || null !== $record['response_ref'])) {
            // Nunca reconstruir resultados desde un token persistido.
            throw new Transaction_Failure('pc_idempotency_result_invalid');
        }

        return [
            'operation_id' => $id,
            'status' => $status,
            'phase' => $phase,
            'entity_type' => self::PHASE_COMPLETE === $phase ? $entityType : null,
            'entity_id' => self::PHASE_COMPLETE === $phase ? (string) $entityId : null,
            'response_code' => self::PHASE_COMPLETE === $phase ? (int) $code : null,
        ];
    }

    /**
     * IMP-34: probar una raíz guest HISTÓRICA contra un grant OTP
     * consumido para el mismo pedido/sesión/finalidad, sin recuperar
     * clave idempotente ni dar permisos por conocer el digest.
     * Solo debe usarse junto con pc_case_events + challenge + grant.
     */
    public static function guestCaseScopeDigest(
        string $orderId,string $sessionId,string $purpose
    ): string {
        $orderId=Domain_Validation::sqlId($orderId);
        $sessionId=Domain_Validation::sqlId($sessionId);
        if (!\in_array($purpose,['reissue_tracking','reissue_download'],true)) {
            throw new Transaction_Failure('pc_idempotency_scope_invalid');
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_idempotency_scope_invalid');
        }
        return \hash('sha256','pc-scope-v1:'.self::canonicalJson([
            'site_prefix'=>(string)$wpdb->prefix,
            'blog_id'=>(string)\get_current_blog_id(),
            'operation'=>'guest_case_request',
            'actor_kind'=>'guest',
            'actor_ref'=>$sessionId,
            'resource_kind'=>'order',
            'resource_id'=>$orderId,
            'purpose'=>$purpose,
        ]));
    }

    /**
     * IMP-33: preflight read-only para distinguir una clave ya reclamada
     * en el MISMO scope legitimado de un grant nuevo. No devuelve IDs ni
     * cuerpo de otros pedidos y no permite nuevos efectos. Solo ayuda
     * al guard a dejar que claimWithin decida replay o 409 de payload.
     */
    public static function keyExistsForScope(
        array $scope,string $operationType,string $key,array $payload
    ): bool {
        [$scopeHex,$keyHex] = self::digests(
            $scope,$operationType,$key,$payload
        );
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $scopeHex,$keyHex,$operationType
            ): bool {
                $rows=$tx->rows(
                    'SELECT id FROM %i WHERE scope_hash=UNHEX(%s) '
                        . 'AND key_hash=UNHEX(%s) AND operation_type=%s LIMIT 2',
                    [self::table(),$scopeHex,$keyHex,$operationType]
                );
                if (\count($rows)>1) {
                    throw new Transaction_Failure('pc_idempotency_record_invalid');
                }
                return 1===\count($rows);
            }
        );
    }

    /**
     * Recuperación explícita DESPUÉS de timeout/COMMIT incierto. No crea fila,
     * no reejecuta negocio ni exige reconstruir un secreto previamente emitido.
     *
     * @param array<string,string> $scope
     * @param array<string,mixed> $payload
     * @return array{operation_id:string,status:string,phase:string,entity_type:?string,entity_id:?string,response_code:?int}
     */
    public static function lookup(array $scope, string $operationType, string $key, array $payload): array
    {
        return Transaction::run(
            static fn (Transaction_Context $tx): array =>
                self::loadWithin($tx, $scope, $operationType, $key, $payload, false)
        );
    }

    /**
     * Finalización local ATÓMICA al crear el recurso/evento/auditoría del
     * consumidor dentro de la misma transacción. No admite token, URL, cuerpo,
     * OTP o proveedor como "response_ref"; el replay solo obtiene IDs seguros.
     *
     * El consumidor valida permisos y estado E-01 antes de escribir efectos.
     */
    public static function completeLocalWithin(
        Transaction_Context $tx,
        string $operationId,
        string $entityType,
        string $entityId,
        int $httpCode
    ): void {
        $id = Domain_Validation::sqlId($operationId);
        $entityId = Domain_Validation::sqlId($entityId);
        if (!\preg_match('/\A[a-z][a-z0-9_]{0,23}\z/D', $entityType)
            || !\in_array($httpCode, [200, 201, 202, 204], true)) {
            throw new Transaction_Failure('pc_idempotency_result_invalid');
        }
        $row = $tx->lockedOperation($id);
        // Un checkout MP preparado nunca equivale a un cobro comprobado:
        // el vínculo/prepago exige la reconciliación autoritativa de IMP-27+.
        $detail = self::recordById($tx, $id);
        if ((string) $row['technical_phase'] !== self::PHASE_PREPARED
            || 'checkout_mp' === (string) $detail['operation_type']) {
            throw new Transaction_Failure('pc_idempotency_phase_conflict');
        }
        if (1 !== $tx->execute(
            'UPDATE %i SET technical_phase = %s, entity_type = %s, entity_id = %s, '
                . 'response_code = %d, response_ref = NULL, updated_at = UTC_TIMESTAMP(6) '
                . 'WHERE id = %s AND technical_phase = %s AND provider_idempotency_key IS NULL',
            [self::table(), self::PHASE_COMPLETE, $entityType, $entityId,
                $httpCode, $id, self::PHASE_PREPARED]
        )) {
            throw new Transaction_Failure('pc_idempotency_phase_conflict');
        }
    }

    /**
     * Crear vínculo MP DURABLE antes de HTTP y en la misma transacción que la
     * admisión de 60 s. Nunca usar un segundo POST al recuperar una operación.
     *
     * $frozenContext es generado por el servidor (cotización, líneas, políticas
     * y solicitud exacta al proveedor SIN credenciales/secretos). Se cifra con
     * una clave persistente de wp-config y se guarda en la tabla existente.
     * La falta de clave o cualquier inconsistencia BLOQUEA la integración.
     *
     * @param array<string,mixed> $frozenContext
     * @return array{operation_id:string,provider_idempotency_key:string,external_reference:string}
     */
    public static function bindProviderWithin(
        Transaction_Context $tx,
        string $operationId,
        string $accountDigestHex,
        string $environment,
        array $frozenContext,
        string $orderId
    ): array {
        $id = Domain_Validation::sqlId($operationId);
        $orderId = Domain_Validation::sqlId($orderId);
        $order = $tx->lockedOrder($orderId);
        if ('mercado_pago' !== (string) $order['payment_method']
            || 'PENDIENTE_PAGO' !== (string) $order['status']) {
            throw new Transaction_Failure('pc_idempotency_provider_invalid');
        }
        if (!\preg_match('/\A[a-f0-9]{64}\z/D', $accountDigestHex)
            || !\in_array($environment, ['sandbox', 'production'], true)) {
            throw new Transaction_Failure('pc_idempotency_provider_invalid');
        }
        $row = $tx->lockedOperation($id);
        $detail = self::recordById($tx, $id);
        if ((string) $row['technical_phase'] !== self::PHASE_PREPARED
            || (string) $detail['operation_type'] !== 'checkout_mp'
            || null !== $detail['provider_idempotency_key']) {
            throw new Transaction_Failure('pc_idempotency_phase_conflict');
        }
        // El cliente no suministra external_reference: se genera de manera
        // estable en esta misma transacción, antes de congelar el body exacto.
        if (\is_array($frozenContext['provider_request'] ?? null)
            && \array_key_exists('external_reference', $frozenContext['provider_request'])) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
        // Mercado Pago documenta UUID para X-Idempotency-Key (máximo 128).
        $providerKey = self::uuidV4();
        // Una referencia basada solo en id local colisionaría entre comercios
        // con la misma cuenta MP. Esta correlación opaca tiene 128 bits y se
        // crea una única vez antes de HTTP, sin rotarse en reintentos.
        $externalReference = 'pc-' . \bin2hex(\random_bytes(16));
        if (!\is_array($frozenContext['provider_request'] ?? null)) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
        $frozenContext['provider_request']['external_reference'] = $externalReference;
        self::requireCheckoutContext($frozenContext);
        $encrypted = self::sealContext($frozenContext);
        if (1 !== $tx->execute(
            'UPDATE %i SET provider_account_key = UNHEX(%s), provider_environment = %s, '
                . 'provider_idempotency_key = %s, provider_external_reference = %s, '
                . 'checkout_context_snapshot = %s, technical_phase = %s, '
                . 'entity_type = %s, entity_id = %s, updated_at = UTC_TIMESTAMP(6) '
                . 'WHERE id = %s AND technical_phase = %s '
                . 'AND provider_idempotency_key IS NULL AND entity_id IS NULL',
            [self::table(), $accountDigestHex, $environment, $providerKey,
                $externalReference, $encrypted, self::PHASE_EXTERNAL_PENDING,
                'order',$orderId,$id, self::PHASE_PREPARED]
        )) {
            throw new Transaction_Failure('pc_idempotency_phase_conflict');
        }
        return [
            'operation_id' => $id,
            'provider_idempotency_key' => $providerKey,
            'external_reference' => $externalReference,
        ];
    }

    /**
     * Timeout/red/409/423/429/5xx: marcar conciliación sin rotar la clave ni
     * declarar pedido o pago acreditado. El caller confirma COMMIT local.
     */
    public static function requireReconciliationWithin(Transaction_Context $tx, string $operationId): void
    {
        $id = Domain_Validation::sqlId($operationId);
        $row = $tx->lockedOperation($id);
        if (self::PHASE_RECONCILE === (string) $row['technical_phase']) {
            return;
        }
        if (self::PHASE_EXTERNAL_PENDING !== (string) $row['technical_phase']) {
            throw new Transaction_Failure('pc_idempotency_phase_conflict');
        }
        if (1 !== $tx->execute(
            'UPDATE %i SET technical_phase = %s, updated_at = UTC_TIMESTAMP(6) '
                . 'WHERE id = %s AND technical_phase = %s AND provider_idempotency_key IS NOT NULL',
            [self::table(), self::PHASE_RECONCILE, $id, self::PHASE_EXTERNAL_PENDING]
        )) {
            throw new Transaction_Failure('pc_idempotency_phase_conflict');
        }
    }

    /**
     * Solo para conciliador servidor que YA volvió a comprobar legitimación
     * y scope/payload. No hay POST al proveedor ni cambio de importe/estado.
     * Un proveedor puede NO permitir buscar orders por clave idempotente.
     *
     * @param array<string,string> $scope
     * @param array<string,mixed> $payload
     * @return array{operation_id:string,order_id:string,provider_account_digest:string,environment:string,provider_idempotency_key:string,external_reference:string,checkout_context:array<string,mixed>}
     */
    public static function recoveryContext(
        array $scope,
        string $key,
        array $payload
    ): array {
        return Transaction::run(static function (Transaction_Context $tx) use ($scope, $key, $payload): array {
            $claim = self::loadWithin($tx, $scope, 'checkout_mp', $key, $payload, false);
            if (!\in_array($claim['phase'], [self::PHASE_EXTERNAL_PENDING, self::PHASE_RECONCILE], true)) {
                throw new Transaction_Failure('pc_idempotency_phase_conflict');
            }
            return self::externalContextFromLocked($tx, $claim['operation_id']);
        });
    }

    /**
     * Recuperación cron autenticada por el conciliador, sin credencial de
     * usuario ni permiso por simple posesión del operation_id.
     * La raíz SIN pedido local se rechaza: el caso conserva source_op.
     */
    public static function recoveryContextForMaintenance(
        string $operationId,string $accountDigest,string $environment
    ): array {
        $id=Domain_Validation::sqlId($operationId);
        if (null===self::$maintenanceAuthorizer
            || !\preg_match('/\A[a-f0-9]{64}\z/D',$accountDigest)
            || !\in_array($environment,['sandbox','production'],true)) {
            throw new Transaction_Failure('pc_idempotency_identity_unverified');
        }
        try {
            if (true!==(self::$maintenanceAuthorizer)(
                $id,$accountDigest,$environment
            )) {
                throw new \UnexpectedValueException();
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_idempotency_identity_unverified');
        }
        return Transaction::run(
            static function(Transaction_Context $tx) use(
                $id,$accountDigest,$environment
            ): array {
                $tx->lockOperation($id);
                return self::externalContextFromLocked(
                    $tx,$id,$accountDigest,$environment
                );
            }
        );
    }

    /**
     * IMP-30: recuperación explícita por administrador A, después de
     * autorización WordPress/nonce/capability del endpoint R8-30.
     * No crea un nuevo POST, ni reemite checkout_url o token. La orden
     * debe existir y permanecer asociada a la raíz original.
     */
    public static function recoveryContextForAdmin(string $operationId): array
    {
        require_once PERUCOMMERCE_PLUGIN_DIR.'includes/access-roles.php';
        if ('A'!==Access_Roles::currentRole()
            || !\current_user_can('perucommerce_reconciliation_manage')) {
            throw new Transaction_Failure('pc_idempotency_identity_unverified');
        }
        $id=Domain_Validation::sqlId($operationId);
        return Transaction::run(static function(Transaction_Context $tx) use($id): array {
            $tx->lockOperation($id);
            return self::externalContextFromLocked($tx,$id);
        });
    }

    /**
     * La conciliación de pagos tardíos requiere funcionar aunque la sesión
     * del comprador ya haya vencido. Acceso interno por operación/cuenta
     * SOLO si un proveedor autenticó al actor mediante un autorizador real.
     * Nunca se expone como REST público; no hace POST ni acredita fondos.
     *
     * @return array{operation_id:string,provider_account_digest:string,environment:string,provider_idempotency_key:string,external_reference:string,checkout_context:array<string,mixed>}
     */
    public static function recoveryContextForSystem(
        string $operationId,
        string $accountDigestHex,
        string $environment
    ): array {
        $id = Domain_Validation::sqlId($operationId);
        if (!\preg_match('/\A[a-f0-9]{64}\z/D', $accountDigestHex)
            || !\in_array($environment, ['sandbox', 'production'], true)
            || null === self::$recoveryAuthorizer) {
            throw new Transaction_Failure('pc_idempotency_identity_unverified');
        }
        try {
            if (true !== (self::$recoveryAuthorizer)($id, $accountDigestHex, $environment)) {
                throw new Transaction_Failure('pc_idempotency_identity_unverified');
            }
        } catch (\Throwable $ignored) {
            throw new Transaction_Failure('pc_idempotency_identity_unverified');
        }

        return Transaction::run(static function (Transaction_Context $tx) use ($id, $accountDigestHex, $environment): array {
            $tx->lockOperation($id);
            return self::externalContextFromLocked($tx, $id, $accountDigestHex, $environment);
        });
    }

    /**
     * @return array{operation_id:string,provider_account_digest:string,environment:string,provider_idempotency_key:string,external_reference:string,checkout_context:array<string,mixed>}
     */
    private static function externalContextFromLocked(
        Transaction_Context $tx,
        string $id,
        ?string $accountDigestHex = null,
        ?string $expectedEnvironment = null
    ): array {
        $row = self::recordById($tx, $id);
        if ('checkout_mp' !== (string) $row['operation_type']
            || !\in_array((string) $row['technical_phase'],
                [self::PHASE_EXTERNAL_PENDING, self::PHASE_RECONCILE], true)
            || !\is_string($row['provider_idempotency_key'])
            || !\is_string($row['checkout_context_snapshot'])
            || !\is_string($row['provider_account_digest'])
            || !\is_string($row['provider_environment'])
            || !\is_string($row['provider_external_reference'])) {
            throw new Transaction_Failure('pc_idempotency_provider_invalid');
        }
        $digest = \strtolower($row['provider_account_digest']);
        if (null !== $accountDigestHex && !\hash_equals($accountDigestHex, $digest)
            || null !== $expectedEnvironment && $expectedEnvironment !== $row['provider_environment']) {
            throw new Transaction_Failure('pc_idempotency_provider_invalid');
        }
        if ('order' !== ($row['entity_type'] ?? null)
            || (!\is_string($row['entity_id'] ?? null)
                && !\is_int($row['entity_id'] ?? null))) {
            throw new Transaction_Failure('pc_idempotency_provider_invalid');
        }
        $orderId = Domain_Validation::sqlId((string) $row['entity_id']);
        $context = self::openContext($row['checkout_context_snapshot']);
        if (!\hash_equals(
            $row['provider_external_reference'],
            (string) ($context['provider_request']['external_reference'] ?? '')
        )) {
            throw new Transaction_Failure('pc_operation_context_protection_failed');
        }
        return [
            'operation_id' => $id,
            'order_id' => $orderId,
            'provider_account_digest' => $digest,
            'environment' => $row['provider_environment'],
            'provider_idempotency_key' => $row['provider_idempotency_key'],
            'external_reference' => $row['provider_external_reference'],
            'checkout_context' => $context,
        ];
    }

    /**
     * El resto de la reconciliación MP (GET autoritativo, pedido/intento,
     * stock/agenda/ledger) corresponde a IMP-27+; NUNCA inferir pago aquí.
     */

    /** @return array{string,string,string} hashes SHA-256 de ámbito, clave y solicitud */
    private static function digests(array $scope, string $operation, string $key, array $payload): array
    {
        // OpenAPI admite claves string de 16..128 caracteres, no solo UUID
        // o base64url. No modificarlas ni truncarlas al calcular su hash.
        if (!\preg_match('/\A[a-z][a-z0-9_]{0,47}\z/D', $operation)
            || !\mb_check_encoding($key, 'UTF-8')
            || \mb_strlen($key, 'UTF-8') < 16 || \mb_strlen($key, 'UTF-8') > 128
            || 0 !== \preg_match('/[\x00-\x20\x7F]/', $key)) {
            throw new Transaction_Failure('pc_idempotency_input_invalid');
        }

        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_idempotency_input_invalid');
        }

        $keys = ['actor_kind', 'actor_ref', 'resource_kind', 'resource_id', 'purpose'];
        $provided = \array_keys($scope);
        \sort($provided, \SORT_STRING);
        \sort($keys, \SORT_STRING);
        if ($provided !== $keys) {
            throw new Transaction_Failure('pc_idempotency_scope_invalid');
        }
        foreach ($scope as $value) {
            if (!\is_string($value) || '' === $value) {
                throw new Transaction_Failure('pc_idempotency_scope_invalid');
            }
        }
        if (!\in_array($scope['actor_kind'], ['staff', 'guest', 'form', 'system'], true)
            || !\preg_match('/\A[a-z][a-z0-9_]{0,23}\z/D', $scope['resource_kind'])
            || !\preg_match('/\A[a-z][a-z0-9_]{0,47}\z/D', $scope['purpose'])) {
            throw new Transaction_Failure('pc_idempotency_scope_invalid');
        }

        if ('staff' === $scope['actor_kind']) {
            if (!\function_exists('get_current_user_id')
                || (int) \get_current_user_id() <= 0
                || $scope['actor_ref'] !== (string) \get_current_user_id()) {
                throw new Transaction_Failure('pc_idempotency_scope_invalid');
            }
        } elseif ('form' === $scope['actor_kind']) {
            // Solo digest HMAC de cookie aleatoria validada por el controlador,
            // nunca cookie cruda, nombre, teléfono, IP o número de folio.
            if (!\preg_match('/\A[a-f0-9]{64}\z/D', $scope['actor_ref'])) {
                throw new Transaction_Failure('pc_idempotency_scope_invalid');
            }
        } elseif ('guest' === $scope['actor_kind']) {
            Domain_Validation::sqlId($scope['actor_ref']);
        } elseif ('system' === $scope['actor_kind']) {
            if (!\preg_match('/\A[a-f0-9]{64}\z/D',$scope['actor_ref'])) {
                throw new Transaction_Failure('pc_idempotency_scope_invalid');
            }
        } else {
            throw new Transaction_Failure('pc_idempotency_scope_invalid');
        }

        // Una cadena guest_session_id o un digest de cookie enviados desde
        // el navegador no prueban identidad ni legitimación por sí mismos.
        if ('staff' !== $scope['actor_kind']) {
            $verifier=match($scope['actor_kind']) {
                'guest' => self::$guestScopeVerifier,
                'system' => 'privacy_export'===$scope['resource_kind']
                    && 'privacy_export_worker'===$scope['purpose']
                    ?self::$privacyExportSystemScopeVerifier:
                    ('privacy_wp'===$scope['resource_kind']
                        && 'privacy_intake_wp'===$scope['purpose']
                        ?self::$privacySystemScopeVerifier:
                            self::$systemScopeVerifier),
                'form' => 'complaint_form'===$scope['resource_kind']
                    && 'complaint_submit'===$scope['purpose']
                    ? self::$complaintScopeVerifier : self::$scopeVerifier,
                default => self::$scopeVerifier,
            };
            if (null === $verifier) {
                throw new Transaction_Failure('pc_idempotency_identity_unverified');
            }
            try {
                if (true !== $verifier($scope)) {
                    throw new Transaction_Failure('pc_idempotency_identity_unverified');
                }
            } catch (\Throwable $ignored) {
                throw new Transaction_Failure('pc_idempotency_identity_unverified');
            }
        }

        if ('0' !== $scope['resource_id']) {
            Domain_Validation::sqlId($scope['resource_id']);
        } elseif (!\in_array($scope['resource_kind'], [
            'checkout', 'complaint_form', 'system', 'settings', 'catalog',
        ], true)) {
            throw new Transaction_Failure('pc_idempotency_scope_invalid');
        }

        $scopeData = [
            'site_prefix' => (string) $wpdb->prefix,
            'blog_id' => (string) \get_current_blog_id(),
            'operation' => $operation,
            'actor_kind' => $scope['actor_kind'],
            'actor_ref' => $scope['actor_ref'],
            'resource_kind' => $scope['resource_kind'],
            'resource_id' => $scope['resource_id'],
            'purpose' => $scope['purpose'],
        ];
        $scopeJson = self::canonicalJson($scopeData);
        $requestJson = self::canonicalJson($payload);
        if (\strlen($requestJson) > self::MAX_REQUEST_BYTES) {
            throw new Transaction_Failure('pc_idempotency_input_invalid');
        }
        return [
            \hash('sha256', 'pc-scope-v1:' . $scopeJson),
            \hash('sha256', 'pc-key-v1:' . $key),
            \hash('sha256', 'pc-request-v1:' . $requestJson),
        ];
    }

    /**
     * Orden canónico de mapas, listas ordenadas, cadenas intactas. Prohíbe
     * floats, objetos/arbitrarios, INF, NaN y JSON UTF-8 inválido.
     *
     * @param array<string,mixed> $payload
     */
    private static function canonicalJson(array $payload): string
    {
        try {
            $canonical = self::canonicalValue($payload, 0);
            return \json_encode(
                $canonical, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
                24
            );
        } catch (\JsonException|\InvalidArgumentException $ignored) {
            throw new Transaction_Failure('pc_idempotency_input_invalid');
        }
    }

    private static function canonicalValue(mixed $value, int $depth): mixed
    {
        if ($depth > 20) {
            throw new \InvalidArgumentException();
        }
        if (\is_array($value)) {
            if (\array_is_list($value)) {
                return \array_map(
                    static fn (mixed $item): mixed => self::canonicalValue($item, $depth + 1),
                    $value
                );
            }
            \ksort($value, \SORT_STRING);
            $result = [];
            foreach ($value as $name => $item) {
                if (!\is_string($name) || !\preg_match('/\A[a-z][a-zA-Z0-9_]{0,127}\z/D', $name)) {
                    throw new \InvalidArgumentException();
                }
                $result[$name] = self::canonicalValue($item, $depth + 1);
            }
            return (object) $result;
        }
        if (\is_string($value) || \is_int($value) || \is_bool($value) || null === $value) {
            return $value;
        }
        throw new \InvalidArgumentException();
    }

    private static function table(): string
    {
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            throw new Transaction_Failure('pc_idempotency_input_invalid');
        }
        return Schema::tableNames($wpdb)['pc_idempotency'];
    }

    /** @return list<array<string,mixed>> */
    private static function find(Transaction_Context $tx, string $table, string $scopeHex, string $keyHex): array
    {
        return $tx->rows(
            'SELECT id, HEX(scope_hash) AS scope_digest, HEX(key_hash) AS key_digest, '
                . 'HEX(request_hash) AS request_digest, operation_type, technical_phase, '
                . 'entity_type, entity_id, response_code, response_ref '
                . 'FROM %i WHERE scope_hash = UNHEX(%s) AND key_hash = UNHEX(%s) FOR UPDATE',
            [$table, $scopeHex, $keyHex]
        );
    }

    /** @return array<string,mixed> */
    private static function recordById(Transaction_Context $tx, string $operationId): array
    {
        $rows = $tx->rows(
            'SELECT id, operation_type, technical_phase, entity_type, entity_id, '
                . 'provider_idempotency_key, provider_environment, '
                . 'HEX(provider_account_key) AS provider_account_digest, '
                . 'provider_external_reference, checkout_context_snapshot '
                . 'FROM %i WHERE id = %s FOR UPDATE',
            [self::table(), $operationId]
        );
        if (1 !== \count($rows)) {
            throw new Transaction_Failure('pc_idempotency_missing');
        }
        return $rows[0];
    }

    /** UUIDv4 aleatorio usando CSPRNG de PHP, sin dependencia de librería. */
    private static function uuidV4(): string
    {
        $bytes = \random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3f) | 0x80);
        $hex = \bin2hex($bytes);
        return \substr($hex, 0, 8) . '-' . \substr($hex, 8, 4)
            . '-' . \substr($hex, 12, 4) . '-' . \substr($hex, 16, 4)
            . '-' . \substr($hex, 20, 12);
    }

    /** El contexto MP comercial se protege y autentica, nunca texto plano. */
    private static function snapshotKey(): string
    {
        if (!\defined('PERUCOMMERCE_OPERATION_SNAPSHOT_KEY')
            || !\is_string(PERUCOMMERCE_OPERATION_SNAPSHOT_KEY)
            || !\preg_match('/\A[a-f0-9]{64}\z/D', PERUCOMMERCE_OPERATION_SNAPSHOT_KEY)) {
            throw new Transaction_Failure('pc_operation_snapshot_key_missing');
        }
        $raw = \hex2bin(PERUCOMMERCE_OPERATION_SNAPSHOT_KEY);
        if (false === $raw || 32 !== \strlen($raw)) {
            throw new Transaction_Failure('pc_operation_snapshot_key_missing');
        }
        return $raw;
    }

    /** @param array<string,mixed> $context */
    private static function requireCheckoutContext(array $context): void
    {
        foreach (['currency', 'total_cents', 'delivery_cents', 'quote_fingerprint', 'lines',
                     'policy_version', 'privacy_version', 'provider_request'] as $key) {
            if (!\array_key_exists($key, $context)) {
                throw new Transaction_Failure('pc_operation_context_invalid');
            }
        }
        if ('PEN' !== $context['currency']
            || !\is_string($context['total_cents'])
            || Money::fromApi($context['total_cents']) <= 0
            || !\is_string($context['quote_fingerprint'])
            || \strlen($context['quote_fingerprint']) < 16
            || \strlen($context['quote_fingerprint']) > 256
            || !\is_array($context['lines']) || [] === $context['lines']
            || !\array_is_list($context['lines']) || \count($context['lines']) > 100
            || !\is_array($context['provider_request'])) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
        if (!\is_string($context['policy_version'])
            || !\is_string($context['privacy_version'])
            || !\is_string($context['total_cents'])) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
        Domain_Validation::sqlId($context['policy_version']);
        Domain_Validation::sqlId($context['privacy_version']);
        if (!\is_string($context['delivery_cents'])) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
        $subtotals = [];
        foreach ($context['lines'] as $line) {
            if (!\is_array($line) || !\is_string($line['sellable_id'] ?? null)
                || !\is_int($line['quantity'] ?? null)
                || !\is_string($line['unit_price_cents'] ?? null)) {
                throw new Transaction_Failure('pc_operation_context_invalid');
            }
            Domain_Validation::sqlId($line['sellable_id']);
            $unitPrice = Money::fromApi($line['unit_price_cents']);
            $subtotals[] = Money::lineSubtotal($unitPrice, $line['quantity']);
        }
        $totalCents = Money::fromApi($context['total_cents']);
        if (Money::orderTotal($subtotals, Money::fromApi($context['delivery_cents']))
            !== $totalCents) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }

        // Verificar también la representación del pago congelado para evitar
        // que un importe MP distinto pase bajo una cotización local correcta.
        $body = $context['provider_request'];
        if ('online' !== ($body['type'] ?? null)
            || 'manual' !== ($body['processing_mode'] ?? null)
            || Money::toProviderDecimal($totalCents) !== ($body['total_amount'] ?? null)
            || !\is_string($body['external_reference'] ?? null)
            || !\preg_match('/\Apc-[a-f0-9]{32}\z/D', $body['external_reference'])
            || !\is_array($body['items'] ?? null) || !\array_is_list($body['items'])
            || [] === $body['items'] || \count($body['items']) > 101) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
        // 100 líneas comerciales + un ítem Entrega permitido. La rutina
        // Money::orderTotal limita a 100 líneas del carrito (no a ítems MP).
        $providerTotal = 0;
        foreach ($body['items'] as $item) {
            if (!\is_array($item) || !\is_string($item['unit_price'] ?? null)
                || !\is_int($item['quantity'] ?? null)) {
                throw new Transaction_Failure('pc_operation_context_invalid');
            }
            $unit = Money::fromDisplayDecimal($item['unit_price']);
            $providerTotal = Money::addChecked(
                $providerTotal, Money::lineSubtotal($unit, $item['quantity'])
            );
        }
        if ($providerTotal !== $totalCents) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
        $shipping = Money::fromApi($context['delivery_cents']);
        if ($shipping > 0) {
            // E08-J: sin producto ficticio; un cargo logístico separado
            // titulado "Entrega", una unidad y céntimos exactos.
            $deliveryItems = 0;
            foreach ($body['items'] as $item) {
                if (($item['title'] ?? null) === 'Entrega'
                    && $item['quantity'] === 1
                    && $item['unit_price'] === Money::toProviderDecimal($shipping)) {
                    ++$deliveryItems;
                }
            }
            if (1 !== $deliveryItems) {
                throw new Transaction_Failure('pc_operation_context_invalid');
            }
        }
        if (\array_key_exists('payer', $body)) {
            if (!\is_array($body['payer']) || !\is_string($body['payer']['email'] ?? null)) {
                throw new Transaction_Failure('pc_operation_context_invalid');
            }
            Domain_Validation::email($body['payer']['email']);
        }
        if (\array_key_exists('expiration_time', $body) && 'PT15M' !== $body['expiration_time']) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
        self::forbidSecretKeys($context, 0);
        if (\strlen(self::canonicalJson($context)) > self::MAX_REQUEST_BYTES) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
    }

    private static function forbidSecretKeys(mixed $value, int $depth): void
    {
        if ($depth > 20) {
            throw new Transaction_Failure('pc_operation_context_invalid');
        }
        if (!\is_array($value)) {
            return;
        }
        foreach ($value as $name => $item) {
            if (\is_string($name)
                && \preg_match('/(?:secret|token|api_key|credential|cookie|authorization|'
                    . 'password|bearer|otp|grant|hmac_key|private_key)/i', $name)) {
                throw new Transaction_Failure('pc_operation_context_invalid');
            }
            self::forbidSecretKeys($item, $depth + 1);
        }
    }

    /** @param array<string,mixed> $context */
    private static function sealContext(array $context): string
    {
        $plain = self::canonicalJson($context);
        $nonce = \random_bytes(12);
        $tag = '';
        $cipher = \openssl_encrypt(
            $plain, 'aes-256-gcm', self::snapshotKey(), \OPENSSL_RAW_DATA, $nonce,
            $tag, 'pc-idempotency-context-v1', 16
        );
        if (!\is_string($cipher) || 16 !== \strlen($tag)) {
            throw new Transaction_Failure('pc_operation_context_protection_failed');
        }
        return 'gcm1:' . \base64_encode($nonce . $tag . $cipher);
    }

    /** @return array<string,mixed> */
    private static function openContext(string $sealed): array
    {
        if (!\str_starts_with($sealed, 'gcm1:')) {
            throw new Transaction_Failure('pc_operation_context_protection_failed');
        }
        $raw = \base64_decode(\substr($sealed, 5), true);
        if (!\is_string($raw) || \strlen($raw) < 29 || \strlen($raw) > self::MAX_REQUEST_BYTES + 256) {
            throw new Transaction_Failure('pc_operation_context_protection_failed');
        }
        $plain = \openssl_decrypt(
            \substr($raw, 28), 'aes-256-gcm', self::snapshotKey(), \OPENSSL_RAW_DATA,
            \substr($raw, 0, 12), \substr($raw, 12, 16), 'pc-idempotency-context-v1'
        );
        if (!\is_string($plain)) {
            throw new Transaction_Failure('pc_operation_context_protection_failed');
        }
        try {
            $value = \json_decode($plain, true, 24, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $ignored) {
            throw new Transaction_Failure('pc_operation_context_protection_failed');
        }
        if (!\is_array($value)) {
            throw new Transaction_Failure('pc_operation_context_protection_failed');
        }
        self::requireCheckoutContext($value);
        return $value;
    }
}
