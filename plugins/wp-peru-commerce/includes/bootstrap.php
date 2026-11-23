<?php
/**
 * Arranque sin efectos comerciales, migraciones ni llamadas externas.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Bootstrap
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        // Deben registrarse ANTES de plugins_loaded para funcionar en activación.
        \register_activation_hook(PERUCOMMERCE_PLUGIN_FILE, [self::class, 'activate']);
        \register_deactivation_hook(PERUCOMMERCE_PLUGIN_FILE, [self::class, 'deactivate']);
        \add_action('plugins_loaded', [self::class, 'boot'], 10);
    }

    public static function boot(): void
    {
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/environment.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/module-gates.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/maintenance.php';

        // El hook WP-Cron se registra incluso sin handlers conectados, para
        // permitir recuperación tras actualización/caída. No ejecuta SQL
        // ni programa tareas por sí mismo al cargar.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/queue-worker.php';
        Queue_Worker::registerHooks();

        // IMP-47: handler de correo diferido, sin wp_mail al arrancar ni
        // nuevos proveedores. Gate de envío falla cerrado hasta P-C.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/notification-delivery.php';
        \add_action('init',[Notification_Delivery::class,'register'],18);
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/notification-controller.php';
        \add_action('init',[Notification_Controller::class,'registerGates'],17);
        \add_action('rest_api_init',[Notification_Controller::class,'register'],5);

        // IMP-12: registrar únicamente los hooks del enrutador, no rutas
        // vacías. El contrato/validador se cargan solo cuando un consumidor
        // real registra una operación antes de rest_api_init.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-router.php';
        Rest_Router::registerHooks();

        // IMP-13: el controlador de settings se registra solo en REST,
        // antes del enrutador (prioridad 10), sin cargar DTO en páginas WP.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/settings-controller.php';
        \add_action('rest_api_init', [Settings_Controller::class, 'register'], 5);

        // IMP-14: dos respuestas bootstrap reales; prioridad 5 antes del
        // registro condicional de rutas (10). No se cargan DTO en boot().
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/bootstrap-api-controller.php';
        \add_action('rest_api_init', [Bootstrap_Api_Controller::class, 'register'], 5);

        // IMP-54: montajes Angular por shortcode/pantalla WP, sin runtime
        // hasta existir manifest generado desde una build autorizada.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/angular-surfaces.php';
        Angular_Surfaces::registerHooks();

        // IMP-48: Libro independiente del checkout, público solo cuando
        // exista norma/campos y configuración verificados por instalación.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-form.php';
        Complaint_Form::registerHooks();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-controller.php';
        \add_action('init',[Complaint_Controller::class,'registerGates'],28);
        \add_action('rest_api_init',[Complaint_Controller::class,'register'],5);

        // IMP-49: solo administrador A; binarios con ruta especial no-JSON.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-admin-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-evidence-controller.php';
        \add_action('init',[Complaint_Admin_Controller::class,'registerGates'],29);
        \add_action('rest_api_init',[Complaint_Admin_Controller::class,'register'],5);
        \add_action('rest_api_init',[Complaint_Evidence_Controller::class,'register'],5);

        // IMP-50/51: ingreso WordPress ARCO y exportación temporal verificada.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-intake.php';
        Privacy_Intake::registerHooks();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-export.php';
        Privacy_Export::registerHooks();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-admin-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-evidence-controller.php';
        \add_action('init',[Privacy_Admin_Controller::class,'registerGates'],30);
        \add_action('rest_api_init',[Privacy_Admin_Controller::class,'register'],5);
        \add_action('rest_api_init',[Privacy_Evidence_Controller::class,'register'],5);

        // IMP-53: diagnóstico admin de lectura, no ejecuta pruebas ni habilita módulos.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/diagnostics-controller.php';
        \add_action('rest_api_init',[Diagnostics_Controller::class,'register'],5);

        // IMP-15: el CPT administra fichas internamente; la API publica
        // permanece cerrada hasta IMP-16 (indexacion PHP y vistas SEO).
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/catalog-controller.php';
        \add_action('init', [Catalog_Controller::class, 'registerPostType'], 5);
        \add_action('rest_api_init', [Catalog_Controller::class, 'register'], 5);

        // IMP-16: HTML PHP/bloques/shortcodes solo donde hay superficie
        // pública; el controlador REST conserva la misma proyección.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/catalog-public-view.php';
        Catalog_Public_View::registerHooks();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/catalog-public-controller.php';
        \add_action('rest_api_init', [Catalog_Public_Controller::class, 'register'], 5);

        // IMP-17: gate común a REST/workers en init, controlador de stock
        // solo en REST. Sin evidencia de locks el endpoint no se publica.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/inventory-controller.php';
        \add_action('init', [Inventory_Controller::class, 'registerGates'], 6);
        \add_action('rest_api_init', [Inventory_Controller::class, 'register'], 5);

        // IMP-18: ancla de agenda del recurso único y cuatro rutas REST.
        // Gate en init sin registrar endpoints falsos; handlers solo REST.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/agenda-controller.php';
        \add_action('init', [Agenda_Controller::class, 'registerGates'], 7);
        \add_action('rest_api_init', [Agenda_Controller::class, 'register'], 5);

        // IMP-45: misma cita, reprogramación A/O y resultado solo A.
        // El gate appointments.write proviene del módulo de agenda.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/appointment-operations-controller.php';
        \add_action('rest_api_init',
            [Appointment_Operations_Controller::class, 'register'], 5);

        // IMP-19: catálogo INEI local, tarifas versionadas y lectura pública.
        // El gate "ubigeo" NO se autoacredita desde archivos/documentos.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/delivery-controller.php';
        \add_action('init', [Delivery_Controller::class, 'registerGates'], 8);
        \add_action('rest_api_init', [Delivery_Controller::class, 'register'], 5);

        // IMP-20: registro de proveedor cartográfico condicionado a
        // evidencia real y del módulo ESM, sin cargar Leaflet ni tiles.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/map-adapter.php';
        Map_Adapter::registerHooks();

        // IMP-21: formatos binarios/multipart por rutas dedicadas. El
        // gate privado exige verificación HTTP/streaming en P-C antes
        // de registrar R8-48/62/63 o permitir ventas digitales.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-controller.php';
        \add_action('init', [Digital_Controller::class, 'registerGates'], 10);
        \add_action('rest_api_init', [Digital_Controller::class, 'register'], 5);

        // IMP-22: lecturas de pedidos y líneas bajo locks/rol A/O.
        // No registrar orders.write hasta implementar checkout real.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-controller.php';
        \add_action('init', [Orders_Controller::class, 'registerGates'], 11);
        \add_action('rest_api_init', [Orders_Controller::class, 'register'], 5);

        // IMP-23: R8-60 se vincula al gate digital.write existente;
        // R8-61 debe aportar una decisión legítima antes de revocar.
        // No publicar R8-13/14/59 ni generar tokens por registrar clases.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-rights-controller.php';
        \add_action('rest_api_init', [Digital_Rights_Controller::class, 'register'], 5);

        // IMP-24: R8-05 recotiza en PHP; el gate checkout.write no
        // registra cobro MP/COD, reservas ni el alta de pedidos.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/quote-controller.php';
        \add_action('init', [Quote_Controller::class, 'registerGates'], 12);
        \add_action('rest_api_init', [Quote_Controller::class, 'register'], 5);

        // IMP-26: gate orders.write condicionado al checkout real; R8-07
        // sólo confirma COD, no registra pagos ni R8-06 de Mercado Pago.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/checkout-cod-controller.php';
        \add_action('init', [Checkout_COD_Controller::class, 'registerGates'], 13);
        \add_action('rest_api_init', [Checkout_COD_Controller::class, 'register'], 5);

        // IMP-27: R8-47 permite guardar/rotar credenciales sin requerir
        // mp_orders (evidencia futura P-C). No registra R8-06 ni pagos.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-credential-controller.php';
        \add_action('rest_api_init', [MP_Credential_Controller::class, 'register'], 5);

        // IMP-28: R8-06 condicionado a evidencia P-C real de MP Orders.
        // R8-07 COD permanece independiente, R8-47 solo configura tokens.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-checkout-controller.php';
        \add_action('init', [MP_Checkout_Controller::class,'registerGates'], 14);
        \add_action('rest_api_init', [MP_Checkout_Controller::class,'register'], 5);

        // IMP-29: R8-17 recibe solo HMAC Orders y COMMIT de inbox; el
        // worker GET autoritativo procesa después, sin REST de cobro.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-webhook-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-webhook-worker.php';
        \add_action('init', [MP_Webhook_Controller::class,'registerGates'], 15);
        \add_action('init', [MP_Webhook_Worker::class,'registerHooks'], 16);
        \add_action('rest_api_init', [MP_Webhook_Controller::class,'register'], 5);

        // IMP-30: R8-29/30 siempre reservados al administrador A y caso
        // real. La detección acotada de raíces MP no hace POST ni renueva hold.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-reconciliation-controller.php';
        \add_action('init', [MP_Reconciliation_Controller::class,'registerGates'], 17);
        \add_action('init', [MP_Reconciliation_Controller::class,'registerHooks'], 18);
        \add_action('rest_api_init', [MP_Reconciliation_Controller::class,'register'], 5);

        // IMP-31: el seguimiento de un pedido se renderiza fuera de
        // theme/scripts ajenos; R8-08 y R8-09 validan secret o cookie guest.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/guest-tracking-view.php';
        Guest_Tracking_View::registerHooks();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/guest-controller.php';
        \add_action('init', [Guest_Controller::class,'registerGates'], 19);
        \add_action('rest_api_init', [Guest_Controller::class,'register'], 5);

        // IMP-34: R8-58 reemite tracking solo A con verificación real
        // de identidad, CAS y revocación de todas las sesiones derivadas.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/tracking-reissue-controller.php';
        \add_action('init', [Tracking_Reissue_Controller::class,'registerGates'], 20);
        \add_action('rest_api_init', [Tracking_Reissue_Controller::class,'register'], 5);

        // IMP-35: página aislada /descarga (sin terceros), sesiones digitales
        // R8-13/14 y reemisión A R8-59; solo REST/archivo cuando gates P-C.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-download-view.php';
        Digital_Download_View::registerHooks();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-download-controller.php';
        \add_action('rest_api_init',[Digital_Download_Controller::class,'register'],5);
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-reissue-controller.php';
        \add_action('rest_api_init',[Digital_Reissue_Controller::class,'register'],5);

        // IMP-36: expediente digital y decisión R8-61 con gate propio.
        // No ejecutar R8-60 ni refunds desde la decisión.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-cases-controller.php';
        \add_action('init',[Digital_Cases_Controller::class,'registerGates'],21);
        \add_action('rest_api_init',[Digital_Cases_Controller::class,'register'],5);

        // IMP-37: transición de línea física con salida de stock sólo
        // al primer despacho/recojo, guardas A/O y avisos idempotentes.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/physical-fulfillment-controller.php';
        \add_action('init',[Physical_Fulfillment_Controller::class,'registerGates'],22);
        \add_action('rest_api_init',[Physical_Fulfillment_Controller::class,'register'],5);

        // IMP-38: registrar caja neta y correcciones inmutables; MP conserva
        // su verificador propio, sin activar refunds ni nuevas ventas.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/cash-payments-controller.php';
        \add_action('init',[Cash_Payments_Controller::class,'registerGates'],23);
        \add_action('rest_api_init',[Cash_Payments_Controller::class,'register'],5);

        // IMP-39: R8-23 cancela línea completa con D-64, liberación de
        // compromiso/cita, ajuste comercial separado de refund externo.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-cancellation-controller.php';
        \add_action('init',[Orders_Cancellation_Controller::class,'registerGates'],24);
        \add_action('rest_api_init',[Orders_Cancellation_Controller::class,'register'],5);

        // IMP-40: expedientes físicos parciales legitimados R8-69/70.
        // Nunca ejecutar liberación, ajuste R8-26 ni refund desde estas rutas.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/manual-physical-cases-controller.php';
        \add_action('init',[Manual_Physical_Cases_Controller::class,'registerGates'],25);
        \add_action('rest_api_init',[Manual_Physical_Cases_Controller::class,'register'],5);

        // IMP-41: R8-26 registra solo ajustes comerciales y, si procede,
        // libera unidades con R8-69+closed_adjusted en UN COMMIT.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-adjustments-controller.php';
        \add_action('init',[Orders_Adjustments_Controller::class,'registerGates'],26);
        \add_action('rest_api_init',[Orders_Adjustments_Controller::class,'register'],5);

        // IMP-42: solo constancia de devolución externa ya revisada
        // por A. NUNCA solicitud de refund a Mercado Pago o caja.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/external-refunds-controller.php';
        \add_action('init',[External_Refunds_Controller::class,'registerGates'],27);
        \add_action('rest_api_init',[External_Refunds_Controller::class,'register'],5);

        // IMP-52: referencia de documento emitido FUERA del plugin;
        // no emitir SUNAT ni modificar el ledger del pedido.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/external-documents-controller.php';
        \add_action('init',[External_Documents_Controller::class,'registerGates'],31);
        \add_action('rest_api_init',[External_Documents_Controller::class,'register'],5);

        // IMP-43: R8-28 separa propuesta O, aprobación A, tarifa y
        // versión aprobada, sin mutar dirección/snapshot vendido.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-delivery-changes-controller.php';
        \add_action('init',[Orders_Delivery_Changes_Controller::class,'registerGates'],28);
        \add_action('rest_api_init',[Orders_Delivery_Changes_Controller::class,'register'],5);

        // IMP-44: casos de entrega no comprobada, sin fulfillment/refund automático.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/logistics-incidents-controller.php';
        \add_action('init',[Logistics_Incidents_Controller::class,'registerGates'],29);
        \add_action('rest_api_init',[Logistics_Incidents_Controller::class,'register'],5);

        // Detección de upgrade en admin, también tras sustitución de ZIP.
        // No ejecuta DDL al cargar; requiere POST + nonce + capability.
        if (\is_admin()) {
            require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/installer.php';
            Installer::registerAdminHooks();
            Maintenance::registerAdminHooks();
            require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-roles.php';
            Access_Roles::registerAdminHooks();
            // Caso digital multiarchivo: acción WordPress interna de apertura
            // con nonce/capacidades A, sin añadir REST ni efectuar revocación.
            Digital_Cases_Controller::registerAdminHooks();
        }

        // Las funciones de negocio y las 69 rutas REST llegan en sus fases.
    }

    /**
     * Carga a demanda las invariantes de IMP-06. No registrar acciones,
     * consultas SQL, rutas ni lógica comercial durante plugins_loaded.
     */
    public static function loadDomain(): void
    {
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/domain/money.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/domain/business-time.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/domain/validation.php';
    }

    /**
     * IMP-07: acceso transaccional cargado solamente por módulos de negocio.
     * No registrar "locks" como garantía verificada hasta P-C del hosting.
     */
    public static function loadTransactions(): void
    {
        self::loadDomain();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/transaction.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/transaction-context.php';
    }

    /**
     * IMP-08: idempotencia y correlación externa, solo al requerirse.
     * No hay endpoints/cron/pagos registrados por cargar el archivo.
     */
    public static function loadIdempotency(): void
    {
        self::loadTransactions();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/idempotency.php';
    }

    /**
     * IMP-09: eventos/auditoría append-only y verificador financiero.
     * Sin nuevos endpoints, cron o eventos registrados al cargar WordPress.
     */
    public static function loadEvents(): void
    {
        self::loadIdempotency();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/event-actor.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/event-journal.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/financial-events.php';
    }

    /**
     * IMP-10 — carga diferida de persistencia y worker. Ningún proveedor
     * de correo/webhook se conecta sin un módulo implementado y verificado.
     */
    public static function loadQueue(): void
    {
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/queue-outbox.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/queue-inbox.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/queue-worker.php';
        // Política DOC-QA29: el primer evento lee settings en la misma
        // transacción y congela su decisión aunque el email se omita.
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/settings-store.php';
        Settings_Store::connectQueueReader();
    }

    /**
     * IMP-11: cargar al requerir una ruta real. La mera presencia de esta
     * capa no registra endpoints ni habilita módulos comerciales.
     */
    public static function loadAuthorization(): void
    {
        self::loadTransactions();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-roles.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-contract.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-control.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-output-contract.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-projection.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-resources.php';
    }

    /**
     * IMP-12: contrato y validadores de la API a demanda. Las 69 rutas
     * documentales no se publican automáticamente por cargar esta capa.
     */
    public static function loadRest(): void
    {
        self::loadAuthorization();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-contract.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-errors.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-validator.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-pagination.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-throttle.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-router.php';
    }

    /**
     * IMP-13: estado comercial, avisos y versiones legales inmutables.
     * No se ejecuta SQL al cargar ni se modifica ningún setting en boot().
     */
    public static function loadSettings(): void
    {
        self::loadRest();
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/settings-store.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/settings-validation.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/settings-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/settings-controller.php';
    }

    /**
     * IMP-14: lectura pública y del personal solo de las propiedades
     * explícitas de PublicBootstrap/AdminBootstrap, sin URLs inventadas.
     */
    public static function loadBootstrapApi(): void
    {
        self::loadSettings();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/bootstrap-api-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/bootstrap-api-controller.php';
    }

    /**
     * IMP-15: cargar modelo de catalogo, variantes y auditoria solo en REST.
     * Los cambios SQL no se ejecutan al cargar archivos ni WordPress.
     */
    public static function loadCatalog(): void
    {
        self::loadRest();
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/catalog-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/catalog-controller.php';
    }

    /**
     * IMP-16: solo invariantes/lecturas públicas a demanda en PHP. No cargar
     * las 69 rutas REST ni Angular en páginas que no muestren catálogo.
     */
    public static function loadPublicCatalog(): void
    {
        self::loadTransactions();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-errors.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-pagination.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/catalog-public-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/catalog-public-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/catalog-public-view.php';
    }

    /**
     * IMP-17: operaciones de inventario y reservas internas de checkout.
     * Sin crear rutas de pagos, cron de limpieza, nuevos roles ni HTTP.
     */
    public static function loadInventory(): void
    {
        self::loadCatalog();
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/inventory-balance.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/inventory-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/inventory-reservations.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/inventory-controller.php';
    }

    /**
     * IMP-18: reglas semanales, cierres y calendario. No crea citas al
     * cargar ni ejecuta migraciones/consultas en páginas sin agenda.
     */
    public static function loadAgenda(): void
    {
        self::loadRest();
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/agenda-time.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/agenda-repository.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/agenda-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/agenda-mutations.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/agenda-controller.php';
    }

    /**
     * IMP-19: adaptador territorial y rutas de cobertura a demanda.
     * No importa, descarga ni publica un XLSX/ZIP durante boot/activación.
     */
    public static function loadDelivery(): void
    {
        self::loadRest();
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/ubigeo-catalog.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/coverage-version.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/delivery-coverage.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/ubigeo-importer.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/delivery-controller.php';
    }

    /**
     * IMP-20: contrato opcional de mapa/dirección. No registrar rutas REST
     * ni pedir geolocalización/teselas en PHP, bootstrap o catálogo.
     */
    public static function loadMapAdapter(): void
    {
        // La normalización manual puede llamarse sin REST/checkout: cargar
        // las validaciones y fallos tipados sin publicar rutas ni ejecutar SQL.
        self::loadTransactions();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/rest-errors.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/ubigeo-catalog.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/map-adapter.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/delivery-location.php';
    }

    /**
     * IMP-21: custodia fuera de webroot, hashes, slots inmutables y rutas
     * binarias. No toca el disco al cargar ni publica nuevos medios WP.
     */
    public static function loadDigital(): void
    {
        self::loadCatalog();
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-private-storage.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-files.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-controller.php';
    }

    /**
     * IMP-22: snapshots comerciales históricos, casos acotados y DTO
     * administrativos. Sin registrar escritura o crear pedidos en boot.
     */
    public static function loadOrders(): void
    {
        self::loadSettings();
        self::loadCatalog();
        self::loadEvents();
        self::loadQueue();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-domain.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-persistence.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-cases.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-read.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-finalization.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-controller.php';
    }

    /**
     * IMP-23: concesión de derechos por cobro verificado y revocación
     * administrativa. Sin acceso a archivos, HTTP ni creación de enlaces
     * en bootstrap, y sin nuevas operaciones de gate por inferencia.
     */
    public static function loadDigitalRights(): void
    {
        self::loadDigital();
        self::loadOrders();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-rights.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-rights-controller.php';
    }

    /**
     * IMP-24: cotización de carrito mixto, inventario, agenda, archivos
     * digitales, UBIGEO y políticas, sin efectos sobre pedidos/cobros.
     */
    public static function loadQuote(): void
    {
        self::loadSettings();
        self::loadInventory();
        self::loadAgenda();
        self::loadDelivery();
        self::loadDigital();
        self::loadMapAdapter();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/quote-availability.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/quote-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/checkout-form.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/quote-controller.php';
    }

    /**
     * IMP-25: núcleo transaccional compartido por R8-06/07 futuros.
     * No habilita rutas, medios de pago, credenciales, HTTP ni SMTP.
     * La página del enlace se configura fuera del TX por el consumidor.
     */
    public static function loadCheckout(): void
    {
        self::loadQuote();
        self::loadOrders();
        self::loadIdempotency();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/checkout-appointments.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/checkout-tracking.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/checkout-coordinator.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/checkout-cod-controller.php';
        Checkout_Coordinator::register();
    }

    /**
     * IMP-27: solo SQL cifrado de credenciales y cliente Orders a demanda.
     * Ningún HTTP al cargar clases; credentials no hacen backend Node ni SaaS.
     */
    public static function loadMp(): void
    {
        self::loadOrders();
        self::loadIdempotency();
        self::loadAuthorization();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-credentials.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-credential-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-orders-client.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-payment-attempts.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-checkout-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-credit-application.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-credential-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-checkout-controller.php';
    }

    /** Solo clases y autorizadores; NO ejecutar HTTP ni SQL al cargar. */
    public static function loadWebhook(): void
    {
        self::loadMp();
        self::loadQueue();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-webhook-auth.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-webhook-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-webhook-worker.php';
    }

    /** IMP-30 — casos y recuperaciones MP, sin ejecutar SQL/HTTP aquí. */
    public static function loadReconciliation(): void
    {
        self::loadCheckout();
        self::loadMp();
        self::loadDigitalRights();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-reconciliation-cases.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-reconciliation-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-reconciliation-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/mp-reconciliation-recovery.php';
    }

    /**
     * IMP-31: lectura privada y sesión cookie sin iniciar SQL, emitir
     * cookies ni cargar secretos al registrar clases en PHP.
     */
    public static function loadGuest(): void
    {
        self::loadCheckout();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/guest-sessions.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/guest-order.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/guest-action-grants.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/guest-challenges.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/guest-requests.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/guest-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/guest-tracking-view.php';
    }

    /**
     * IMP-34: no auto-registra prueba de identidad ni ejecuta operaciones
     * de reemisión al cargar los servicios.
     */
    public static function loadTrackingReissue(): void
    {
        self::loadGuest();
        self::loadAuthorization();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/tracking-reissue.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/tracking-reissue-controller.php';
    }

    /**
     * IMP-35: código a demanda. Nunca comprueba cookies ni sirve bytes
     * durante plugins_loaded; los guards reales viven en REST.
     */
    public static function loadDigitalAccess(): void
    {
        self::loadDigitalRights();
        self::loadGuest();
        self::loadAuthorization();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-download.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-download-view.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-download-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-reissue.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-reissue-controller.php';
    }

    /** IMP-36: conectar procedencia del caso y verificación de R8-60. */
    public static function loadDigitalCases(): void
    {
        self::loadDigitalAccess();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-cases.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/digital-cases-controller.php';
    }

    /** IMP-37: leer negocio/inventario + outbox versionada a demanda. */
    public static function loadPhysicalFulfillment(): void
    {
        self::loadOrders();
        self::loadInventory();
        self::loadQueue();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/physical-fulfillment.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/physical-fulfillment-controller.php';
    }

    /** IMP-38: libro de efectivo y verificador staff a demanda. */
    public static function loadCashPayments(): void
    {
        self::loadOrders();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/cash-payments.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/cash-payments-controller.php';
    }

    /** IMP-39: transiciones físicas/servicios y ledger de obligación. */
    public static function loadOrdersCancellation(): void
    {
        self::loadOrders();
        self::loadInventory();
        self::loadAgenda();
        self::loadCheckout();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-cancellation.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-cancellation-controller.php';
    }

    /** IMP-40: casos manuales D-199/200, no ejecuta mutaciones al cargar. */
    public static function loadManualPhysicalCases(): void
    {
        self::loadOrders();
        self::loadInventory();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/manual-physical-cases.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/manual-physical-cases-controller.php';
    }

    /** IMP-41: dependencia interna de casos + compromisos, no HTTP/DDL. */
    public static function loadOrdersAdjustments(): void
    {
        self::loadOrders();
        self::loadInventory();
        self::loadManualPhysicalCases();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-adjustments.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-adjustments-controller.php';
    }

    /** IMP-42: registro de hechos de devolución ajenos al plugin. */
    public static function loadExternalRefunds(): void
    {
        self::loadOrders();
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/external-refunds.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/external-refunds-controller.php';
    }

    /** IMP-53: carga de diagnóstico seguro, conteo local y estado conservador. */
    public static function loadDiagnostics(): void
    {
        self::loadOrders();
        self::loadQueue();
        self::loadSettings();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/diagnostics-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/diagnostics-controller.php';
    }

    /** IMP-52: R8-64 append-only, lectura fiscal desde settings versionados. */
    public static function loadExternalDocuments(): void
    {
        self::loadOrders();
        self::loadSettings();
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/external-documents.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/external-documents-controller.php';
    }

    /** IMP-43: pedidos previos, tarifa INEI y revisiones logísticas. */
    public static function loadOrdersDeliveryChanges(): void
    {
        self::loadOrders();
        self::loadDelivery();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-delivery-changes.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/orders-delivery-changes-controller.php';
    }

    /** IMP-44: expediente logístico separado de entrega, stock y finanzas. */
    public static function loadLogisticsIncidents(): void
    {
        self::loadOrders();
        self::loadEvents();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/logistics-incidents.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/logistics-incidents-controller.php';
    }

    /** IMP-48: R8-15, idempotencia de hoja y constancia protegida. */
    public static function loadComplaints(): void
    {
        self::loadSettings();
        self::loadQueue();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-form.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-legal.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-calendar.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-evidence.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-controller.php';
        Complaint_Form::registerVerifiers();
    }

    /** IMP-49: administrar la hoja original y el acceso legal reservado. */
    public static function loadComplaintAdmin():void
    {
        self::loadComplaints();
        self::loadRest();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-admin.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-admin-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/complaint-evidence-controller.php';
    }

    /** IMP-50: modelo ARCO separado del pedido/Libro y de exportación. */
    public static function loadPrivacy():void
    {
        self::loadSettings();
        self::loadQueue();
        self::loadEvents();
        self::loadRest();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-calendar.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-legal.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-retention.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-incident.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-intake.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-evidence.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-admin.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-admin-controller.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-export.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/privacy-evidence-controller.php';
        Privacy_Intake::registerVerifiers();
        Privacy_Export::registerVerifiers();
    }

    /** IMP-47: notificaciones administradas + plantillas v1 es-PE. */
    public static function loadNotifications(): void
    {
        self::loadOrders();
        self::loadQueue();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/notification-templates.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/notification-delivery.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/notification-service.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/notification-controller.php';
    }

    /** IMP-45: operaciones sobre cita efectiva sin crear otro recurso. */
    public static function loadAppointmentOperations(): void
    {
        self::loadOrders();
        self::loadAgenda();
        self::loadQueue();
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/checkout-appointments.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/appointment-operations.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/appointment-operations-controller.php';
    }

    public static function activate(bool $networkWide = false): void
    {
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/installer.php';
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/maintenance.php';
        Installer::activate($networkWide);
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/access-roles.php';
        // Únicamente en activación WP: nunca en peticiones normales ni
        // durante una sustitución ZIP (que no ejecuta activation hook).
        Access_Roles::install();
    }

    public static function deactivate(): void
    {
        require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/queue-worker.php';
        Queue_Worker::unschedule();
        // No borrar estructura, estados, pedidos, intentos ni derechos.
    }
}
