<?php
/**
 * Guardas internas de operación por módulo. Cada handler debe comprobar allows() antes de mutar.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Module_Gates
{
    /** Requisitos por operación; los diagnósticos no son nuevos estados de negocio. */
    private const REQUIREMENTS = [
        // Lectura de arranque configurada por IMP-14: nunca incluye
        // permiso de venta ni implica capacidad de registrar fondos.
        'bootstrap.read' => ['platform', 'https', 'database', 'schema'],
        'bootstrap.staff' => ['platform', 'https', 'database', 'schema'],
        'catalog.read' => ['platform', 'https', 'database', 'schema'],
        'catalog.write' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks'],
        // Puertas REST de módulos sin equivalente en la matriz original.
        // Su mera declaración no habilita nada: registerOperation sigue
        // exigiendo implementación y evidencia real de cada módulo.
        'orders.read' => ['platform', 'https', 'database', 'schema', 'locks'],
        'orders.write' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks'],
        // IMP-52: referencias externas de pedidos antiguos, sin checkout habilitado.
        'documents.write' => ['platform','commerce_php','https','database','schema','locks'],
        // Cancelar una orden ya admitida no exige habilitar nuevos checkout.
        // Cada tipo volverá a comprobar su disponibilidad específica.
        'orders.cancel' => ['platform','commerce_php','https','database','schema','locks'],
        // D-199/D-200: registrar/cerrar expedientes existentes sin crear
        // pagos, nuevas ventas ni liberar recursos. Persistencia InnoDB.
        'orders.manual_cases' => ['platform','commerce_php','https','database','schema','locks'],
        // R8-55/56/57: expediente posterior al despacho, sin ventas ni efectos físicos.
        'orders.logistics_cases' => ['platform','commerce_php','https','database','schema','locks'],
        // R8-26 modifica obligaciones de pedidos existentes. Reducir
        // unidades exige ademas inventory.write y R8-69 valido en el dominio.
        'orders.adjust' => ['platform','commerce_php','https','database','schema','locks'],
        // R8-27: registrar dinero YA devuelto fuera del plugin no
        // depende de nuevas ventas, checkout ni credencial MP actual.
        'refunds.record' => ['platform','commerce_php','https','database','schema','locks'],
        // Cobrar pedidos contraentrega existentes NO depende de iniciar
        // nuevos checkouts, pero exige InnoDB/schema/locks demostrados.
        'cash.write' => ['platform','commerce_php','https','database','schema','locks'],
        // R8-22 procesa pedidos ya confirmados; no depende de poder abrir
        // nuevos checkouts, pero requiere stock y locks verificados.
        'physical.fulfill' => ['platform','commerce_php','https','database','schema','locks'],
        // R8-12 recibe una solicitud bajo sesión/OTP, aun cuando
        // checkout comercial esté apagado. No concede mutación E-01.
        'guest.requests' => ['platform','commerce_php','https','database','schema','locks'],
        // R8-58 recupera credenciales de acceso, NO una venta nueva.
        'tracking.reissue' => ['platform','commerce_php','https','database','schema','locks'],
        'settings.read' => ['platform', 'database', 'schema'],
        'settings.write' => ['platform', 'commerce_php', 'database', 'schema', 'locks'],
        'payments.read' => ['platform', 'https', 'database', 'schema', 'locks', 'mp_orders'],
        'notifications.read' => ['platform', 'https', 'database', 'schema'],
        // IMP-47: reenvíos CAS sobre outbox existente y transporte diferido.
        'notifications.write' => ['platform','commerce_php','https','database','schema','locks'],
        'checkout.write' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks'],
        'inventory.write' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks'],
        'appointments.read' => ['platform', 'https', 'database', 'schema'],
        'appointments.write' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks'],
        'payments.write' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks', 'mp_orders'],
        // Recibir/reconciliar un evento firmado de una order anterior no
        // depende del flag de nuevas ventas. Nunca basta para acreditar.
        'payments.webhook' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks'],
        'reconciliation.read' => ['platform', 'https', 'database', 'schema', 'locks'],
        'reconciliation.write' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks'],
        'digital.read' => ['platform', 'https', 'database', 'schema', 'private_storage', 'locks'],
        'digital.write' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'private_storage', 'locks'],
        // Resolver un expediente digital NO requiere archivos accesibles ni
        // medio de pago disponible: solo motor transaccional verificado.
        'digital.cases' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks'],
        'delivery.read' => ['platform', 'https', 'database', 'schema', 'ubigeo'],
        'delivery.write' => ['platform', 'commerce_php', 'https', 'database', 'schema', 'locks', 'ubigeo'],
        // Cambios posteriores de dirección solo con cobertura INEI
        // verificada y transacciones; no habilitar checkout nuevo.
        'delivery.change' => ['platform','commerce_php','https','database','schema','locks','ubigeo'],
        'complaints.read' => ['platform','https','database','schema','locks'],
        // Conserva la atención de casos existentes aunque el formulario
        // R8-15 esté cerrado por configuración o norma no verificada.
        'complaints.respond' => [
            'platform','commerce_php','https','database','schema','locks'
        ],
        'complaints.evidence' => [
            'platform','https','database','schema','locks','private_storage'
        ],
        // R8-15 requiere motor estable y verificación jurídica por comercio,
        // aportada por Complaint_Legal; sin evidencia el handler no se publica.
        'complaints.write' => ['platform','commerce_php','https','database','schema','locks'],
        'privacy.read' => ['platform','https','database','schema','locks'],
        // WordPress Privacy y canales manuales pueden registrar solicitudes
        // sin que las resoluciones/exportaciones o borrado estén habilitados.
        'privacy.intake' => ['platform','commerce_php','https','database','schema','locks'],
        'privacy.verify' => ['platform','commerce_php','https','database','schema','locks'],
        // El registro de incidentes del sitio NO depende de que un titular
        // tenga solicitud pendiente de verificación de identidad.
        'privacy.incidents' => ['platform','commerce_php','https','database','schema','locks'],
        'privacy.resolve' => ['platform','commerce_php','https','database','schema','locks'],
        'privacy.evidence' => [
            'platform','commerce_php','https','database','schema','locks',
            'private_storage'
        ],
        // Exportación legal R8-50: escritura y almacenamiento privado P-C.
        'privacy.write' => [
            'platform','commerce_php','https','database','schema','locks',
            'private_storage'
        ],
        'maps.read' => ['platform', 'https'],
    ];

    /** Las pruebas no sustituyen los controles por actor/objeto de IMP-11. */
    private const VERIFIED_BY_INTEGRATION = ['locks', 'private_storage', 'mp_orders', 'ubigeo'];

    /** @var array<string, callable(): bool> */
    private static array $operations = [];

    /** @var array<string, callable(): bool> */
    private static array $evidence = [];

    /** @var Environment|null */
    private static ?Environment $environment = null;

    /**
     * Registrar únicamente el componente realmente implementado en su archivo de carga.
     * No conectar directamente esta API a un ajuste booleano del panel.
     */
    public static function registerOperation(string $module, string $action, callable $ready): void
    {
        $operation = $module . '.' . $action;
        if (!isset(self::REQUIREMENTS[$operation]) || isset(self::$operations[$operation])) {
            throw new \LogicException('PeruCommerce: operación inválida o duplicada.');
        }
        self::$operations[$operation] = $ready;
    }

    /**
     * Los proveedores reales de IMP-07/19/27/72 verifican las garantías que no
     * pueden deducirse leyendo la versión SQL o la preferencia del comercio.
     */
    public static function registerEvidence(string $requirement, callable $verify): void
    {
        if (!\in_array($requirement, self::VERIFIED_BY_INTEGRATION, true)
            || isset(self::$evidence[$requirement])) {
            throw new \LogicException('PeruCommerce: evidencia inválida o duplicada.');
        }
        self::$evidence[$requirement] = $verify;
    }

    /**
     * Fail-closed para cada operación. No sustituye autorización por actor,
     * estado ni objeto, que corresponde a IMP-11.
     */
    public static function allows(string $module, string $action): bool
    {
        return [] === self::blockers($module, $action);
    }

    /**
     * Diagnóstico interno de identificadores técnicos, sin datos de hosting,
     * SQL, rutas o secretos. No exponerlo en endpoints públicos.
     *
     * @return list<string>
     */
    public static function blockers(string $module, string $action): array
    {
        $operation = $module . '.' . $action;
        if (!isset(self::REQUIREMENTS[$operation], self::$operations[$operation])
            || !self::safeProbe(self::$operations[$operation])) {
            return ['module_not_ready'];
        }

        self::$environment ??= new Environment();
        $missing = [];
        // Se permite lectura compatible y consulta de evidencia legal, pero
        // no nuevas mutaciones mientras se actualiza o restaura un sitio.
        if (!\str_ends_with($operation, '.read')
            && !\in_array($operation, ['bootstrap.staff', 'complaints.evidence', 'privacy.evidence'], true)
            && !Maintenance::ready()) {
            $missing[] = 'maintenance';
        }
        foreach (self::REQUIREMENTS[$operation] as $requirement) {
            if (\in_array($requirement, self::VERIFIED_BY_INTEGRATION, true)) {
                if (!isset(self::$evidence[$requirement]) || !self::safeProbe(self::$evidence[$requirement])
                    || ('private_storage' === $requirement && !self::$environment->has($requirement))) {
                    $missing[] = $requirement;
                }
            } elseif (!self::$environment->has($requirement)) {
                $missing[] = $requirement;
            }
        }

        return $missing;
    }

    private static function safeProbe(callable $probe): bool
    {
        try {
            return true === $probe();
        } catch (\Throwable $ignored) {
            return false;
        }
    }
}
