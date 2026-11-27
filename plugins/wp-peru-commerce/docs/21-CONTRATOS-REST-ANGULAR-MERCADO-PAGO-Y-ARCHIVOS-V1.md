# E-08 — Contratos REST, Angular, Mercado Pago, archivos y errores V1

**PRECEDENCIA VIGENTE D-210 (27-11-2026):** el contrato vigente es OpenAPI **0.13.0-draft (82 REST/78 rutas/162 esquemas)**, con 83 entradas R8, 62 PER, 41 tablas declarativas y 210 decisiones; el POS V1 consta en [docs/48](48-CONTRATO-POS-V1-P2.md) como **DOCUMENTADO/NO IMPLEMENTADO**. Las cifras D-200/0.12.0-draft o anteriores de este archivo quedan como corte histórico; el código y las pruebas siguen alineados con 0.12.0-draft hasta P3.

**CORTE HISTÓRICO anterior a D-199:** OpenAPI principal **0.10.0-draft, 67 REST/64 rutas/134 esquemas**. Los conteos y tablas de 0.3/0.5/0.6 que describen cortes anteriores son antecedentes; el contrato vigente y las correcciones de este documento prevalecen. Ningún endpoint está implementado.

**Investigación legal J-01…J-05/P0-25 y fiscal P0-17 (2026-11-17):** `docs/25-MATRICES-NORMATIVAS-J01-J05-P0-25-V1.md` y `docs/26-CONTRATO-TRIBUTARIO-P0-17-J03-V1.md` proporcionan fuentes normativas y supuestos; **NO ratifican DTO de Libro/ARCO/fiscalidad ni plazos por comercio/sector**. Mantener pendientes de OpenAPI y permisos por campo; no generar campos tributarios universales.

**Revisión técnica posterior P0-18/P0-19 (2026-11-17):** `docs/28-AUDITORIA-ESTATICA-MODELO-FISICO-P0-18-V1.md` identifica 16 riesgos de DDL/concurrencia/identidad y `docs/29-COMPATIBILIDAD-WORDPRESS-HOSTING-P0-19-V1.md` documenta versiones y requisitos Hostinger con 12 checks futuros, sin probar instalación ni MP. El negocio permanece bloqueado donde falten garantías críticas.

**Extensión de cobertura C23 APROBADA EN POLÍTICA (2026-11-17):** el propietario respondió «OK adelante» al paso explícito de ratificar las 12 operaciones y sincronizar contratos. **D-176…D-187** confirman **R8-53…R8-64** añadidas al diseño vigente, sobre la base histórica R8-01…R8-52 (51 REST + R8-18 página WP). Total **64 entradas, 63 operaciones REST y una página WP**, sin introducir máquinas E-01 ni capabilities nuevas; el operador queda **denegado** en PER-20/27/28 donde no existía decisión de concesión, mientras las rutas siguen disponibles solo para A. El OpenAPI principal contiene la ampliación con DTO propuestos. **No** significa endpoints implementados, permisos concedidos en WordPress, pruebas ejecutadas ni aprobación física del SQL/hosting.

**Desarrollo documental P0-23 (2026-11-17):** matriz R8↔PER y DTO en `docs/24-MATRIZ-REST-PERMISOS-DTO-P0-23-V1.md`; borrador OpenAPI 3.1 **AMPLIADO, CON COBERTURA RATIFICADA Y DTO FÍSICOS EN REVISIÓN** en `docs/openapi/peru-commerce-v1.openapi.draft.json` (**63 REST / 60 rutas URL / 113 esquemas DTO, R8-18 página WP no REST**). Los anexos 11/12 de docs/23 contrastan los DTO con **39 tablas propuestas**. Aún falta ratificar condiciones legales J-01…J-05, grants/CSRF, permisos efectivos por campo/estado, operaciones funcionales sin ruta, SQL y validación de proveedor/hosting. No se autoriza implementar.

**Estado: E-08 APROBADO EN POLÍTICA DE INTEGRACIÓN (D-149…D-166).** El propietario aprobó expresamente «Todas A en E-08» el 2026-11-17. Corte de documentación oficial: 2026-11-17. **Pendientes** P0-17/P0-18/P0-19/P0-20/P0-21/P0-23/P0-24/P0-25/P0-26, J-01…J-05, contratos DTO/OpenAPI y SQL físicos, validación real de Mercado Pago/hosting y autorización específica. **No autoriza código, compilaciones, pruebas, cuentas, migraciones ni despliegues.**

**Objetivo:** evitar que Codex invente endpoints, permisos, payloads, esquemas de errores, callbacks, dependencias de frontend o comportamientos de proveedor. Aquí se proponen una superficie REST acotada, reglas de autenticación y DTO de intercambio, traducción a Orders API, comunicación WordPress–Angular, acceso a archivos, seguridad e inventario de excepciones. Las 18 políticas **E08-A…R están APROBADAS D-149…D-166**. El inventario R8, los códigos de error y nombres de capabilities constituyen el diseño seleccionado, que todavía debe formalizarse como contrato físico OpenAPI/DTO E08-R, esquema de datos y permisos por ruta. No están implementados ni validados contra el proveedor.

**Precedencia:** decisiones confirmadas D-01…D-166 en [01](01-DECISIONES-Y-ESTADO.md); E-01 estados [14](14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md), E-02 permisos [15](15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md), E-03 elegibilidad [16](16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md), E-04 dinero [17](17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md), E-05 consistencia [18](18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md), E-06 comunicaciones [19](19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md), E-07 privacidad [20](20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md). La matriz de obligaciones J-01…J-05 y los nueve P0 siguen separados. **E-08 ratifica las opciones A sin modificar ni sustituir decisiones previas.**

## Contrato vigente D-200 / DOC-QA31 — cierre de expediente físico parcial (18-11-2026)

**Fuente contractual actual:** OpenAPI **`0.12.0-draft`**, **69 REST/66 rutas/138 esquemas**, 70 entradas R8 incluyendo página WordPress R8-18, **56 PER y 39 tablas**. D-200 rectifica el ciclo de vida de D-199 sin añadir estados comerciales E-01 ni facultades de operador.

| Ruta | Actor | Solicitud, transición y efecto |
| --- | --- | --- |
| **R8-69 POST `/admin/orders/{id}/cases`** | Solo A, `orders_read` Y `orders_financial_adjust` | Abre `open_review`, valida identidad telefónica proporcional, pedido/línea física, versiones e idempotencia; **no modifica stock/dinero**. |
| **R8-70 POST `/admin/orders/{id}/cases/{case_id}/close`** | Solo A, mismas DOS capacidades | `AdminManualCaseCloseRequest`: `decision=reject|buyer_withdrawn`, `reason`, `expected_case_version`, `idempotency_key`; retiro exige nueva `withdrawal_verification` por teléfono registrado y dato privado. Solo si caso `open_review` y tipo físico parcial del mismo pedido/línea: `closed_rejected` o `closed_withdrawn`, `resolved_at`, nueva `row_version`, `pc_case_events` y auditoría en transacción. `AdminManualCaseClosed` devuelve solo acuse sin PII. Nunca altera importe, stock, cumplimiento, refund, cita ni pedido. |
| **R8-26 POST `/admin/orders/{id}/adjustments`** | Solo A con `orders_financial_adjust`; al ajustar cantidad se verifica además caso legitimado | Si contiene `affected_units`, requiere `case_id`, `line_id`, `expected_case_version` y compara petición original, fase `open_review`, misma instalación/pedido/línea. Solo el ajuste financiero/inventario exitoso termina ese caso como **`closed_adjusted`** de modo **atómico** con movimiento, compromiso, evento, `resolved_at` y versión. Cualquier fallo revierte TODO; duplicado/replay no libera dos veces. `closed_adjusted` NO significa que haya reembolso externo verificado (R8-27). |

El lock se adquiere **`pc_orders → pc_order_lines → pc_cases`**, y en R8-26 continúa por recurso/compromiso y libro monetario según E-05. R8-70 nunca debe tomar primero `pc_cases` y después `pc_orders` invirtiendo el orden. Un caso cerrado no puede volver a `open_review`; otra solicitud legítima futura requiere identificación, versiones y clave nuevas. R8-21 (solo A con capacidad del tipo de caso) devuelve `case_phase`/versión y eventos, sin exponer a O. La API de invitados R8-12 NO representa reducción física parcial: sigue con sus propósitos y OTP/grant, sin inferir una apertura R8-69 por ese origen.

**Solo contrato documental:** la privacidad, carrera R8-26 vs R8-70, CAS, idempotencia y estados terminales se verificarán durante implementación y pruebas autorizadas, no aquí.

## Antecedente de apertura manual — D-199/DOC-QA30 (18-11-2026)

**Fuente actual:** OpenAPI 3.1 **`0.11.0-draft`, 68 REST/65 rutas/136 DTO, 56 PER**; `R8-18` es una página WordPress y no cuenta como REST. Nuevo `R8-69 POST /admin/orders/{id}/cases` únicamente para abrir expedientes de **solicitud física de reducción estrictamente parcial** de unidades aún no despachadas. No habilita un CRUD de expedientes, una cancelación ordinaria parcial, un cambio de estado comercial, un reembolso API ni un correo obligatorio para el comprador.

| Operación | Actor y capacidades | Contrato |
| --- | --- | --- |
| **R8-69 POST `/admin/orders/{id}/cases`** | **Solo administrador A**, `perucommerce_orders_read` **Y** `perucommerce_orders_financial_adjust` (PER-38/39 existentes), misma instalación, pedido y línea física | `AdminManualCaseCreate → AdminManualCaseOpened`. Incluye `line_id`, `purpose=physical_partial_quantity_adjustment`, `requested_affected_units`, `reason`, `verification` manual, `expected_order_version`, `expected_line_version` e `idempotency_key`. `201` caso nuevo, `200` replay idéntico, `409` conflicto/versiones/diferente payload/caso duplicado. |

**Autorización de la identidad:** canal telefónico ORIGINAL registrado, comprobación real de control y segundo dato de pedido no público. `verification` contiene atestaciones/auditoría minimizadas; **un booleano no verifica identidad automáticamente** y se prohíbe almacenar texto literal del segundo factor, DNI, OTP o teléfono íntegro. Ante disputa o insuficiencia no abrir expediente legítimo para ajuste. El servidor obtiene actor WordPress y hora real, valida `X-WP-Nonce` solo como protección CSRF, revalida estados/recursos bajo transacción e impide duplicar casos abiertos del mismo pedido/línea/finalidad.

**Persistencia y efecto:** R8-69 escribe únicamente `pc_cases`, evento inicial inmutable `pc_case_events`, `pc_idempotency` y auditoría en transacción coherente. `case_type=physical_partial_quantity_adjustment`, `case_phase=open_review`, `source_operation_id=NULL`; el caso se consulta vía **R8-21** a A con `case_id` y `AdminCaseOriginalRequest.source_kind=staff`. Crear el caso **no** libera stock, realiza ajuste, cancela, cobra, devuelve ni envía enlace. **R8-26** requiere caso vigente de ese pedido/línea, verifica legitimación y versiones actuales y aplica, cuando proceda, el efecto financiero/inventario bajo sus propias guardas. **R8-12 invitado** mantiene su OTP/grant; jamás fingir solicitud invitada a partir de staff. Las rutas existentes R8-21/26 no se sustituyen ni dan acceso al operador.

**Cierre P-A exclusivamente documental:** esta rectificación resuelve una entrada faltante demostrada por auditoría sin agregar funcionalidad comercial nueva. Comprobaciones de identidad/caché/locks/replay, MySQL, MP y conformidad por comercio siguen P-C/P-D, no ejecutadas.

## Antecedente histórico posterior a D-198 (2026-11-17)

**Fuente REST del corte histórico D-198 (superada por D-200):** `docs/openapi/peru-commerce-v1.openapi.draft.json` **0.10.0-draft**, **67 operaciones REST, 64 rutas y 134 esquemas**, más R8-18 página WordPress. D-198 completa la lectura privada de evidencias del Libro y ARCO sin conceder privilegios O, sin página WP excepcional y sin añadir un servicio externo. El esquema uniforme `x-authz` usa actores canónicos `public/guest/digital_token/digital_session/provider/staff/admin_only`, capacidades `perucommerce_*` completas, `capabilitiesAllOf` como AND, `capabilitiesAnyOf` como OR y condiciones de actor/estado/campo/instalación que siempre prevalecen. Los `staff` no acceden automáticamente a expedientes sensibles: **R8-41/42/43/44/50/65…68 solo A**.

| Ruta | Contrato y seguridad | Estado |
| --- | --- | --- |
| **R8-67 GET `/admin/complaints/{id}/evidence/{evidence_ref}`** | Solo A con `perucommerce_complaints_manage`, instalación y expediente correspondientes, finalidad auditada, evidencia opaca privada vinculada al caso, TLS/no-store y descarga `attachment`; sin upload público adicional | D-198 DISEÑO, no implementado |
| **R8-68 GET `/admin/privacy-requests/{id}/evidence/{evidence_ref}`** | Solo A con `perucommerce_privacy_manage`, caso/instalación/finalidad legítimos, misma denegación de acceso ajeno, no exponer documento de identidad por defecto | D-198 DISEÑO, no implementado |
| **R8-15 POST `/public/reclamaciones`** | `ComplaintCreate.idempotency_key` de 16…128 + contexto de formulario same-origin, folio único por intento lógico y `ComplaintReceiptCopy` imprimible en el éxito original; sin cuenta/compra, pero **con identidad y contacto según Anexo I en el formulario**; la copia se imprime sin depender del envío por email; sin búsqueda pública por folio | D-198 DISEÑO, no implementado |

Se cerraron `additionalProperties=false` en seis respuestas C23 sensibles y se normalizaron las claves `idempotency_key` de C23 a **16…128 caracteres**. La seguridad de evidencia en `R8-67/68` no reutiliza la ruta digital R8-62 ni sus facultades; los binarios quedan en almacenamiento privado, nunca en medios WP públicos. `docs/23` describe el manifest protegido del caso sin tabla 40. La vigencia fiscal/Libro/ARCO por comerciante y las comprobaciones P-C/P-D permanecen externas a la documentación.

### Antecedente histórico de D-189…D-194

Las tablas R8-01…R8-64 de este documento conservan su historia. **OpenAPI 3.1 `0.4.0-draft` fue el corte anterior a D-198: 65 REST/62 rutas/115 DTO; el corte D-198 fue `0.5.0-draft`: 67 REST/64 rutas/117 DTO, y la rectificación intermedia fue `0.6.0-draft`: 67 REST/64 rutas/125 DTO**. **El corte posterior fue `0.7.0-draft`: 67 REST/64 rutas/129 DTO y la versión VIGENTE es `0.10.0-draft`: 67 REST/64 rutas/134 DTO**, más R8-18, página WordPress no REST. D-189 agrega dos consultas individuales de expediente, **sin nuevos roles ni permisos globales**:

| ID | Método y ruta | Actor y permiso | Proyección |
| --- | --- | --- | --- |
| R8-65 | GET `/admin/complaints/{id}` | Solo A con `perucommerce_complaints_manage`, pertenencia a instalación y finalidad | `ComplaintDetail`; contenido según J-01, sin URL pública de adjuntos |
| R8-66 | GET `/admin/privacy-requests/{id}` | Solo A con `perucommerce_privacy_manage`, pertenencia/legitimación/finalidad | `PrivacyRequestDetail`; evidencia por referencia privada y datos mínimos |

Los **R8-41/43 continúan como listados resumidos** y R8-42/44 siguen siendo las mutaciones autorizadas por expediente, sin sustituir WordPress Privacy. Se descarta como contrato vigente la pantalla WordPress excepcional antes propuesta en docs/35 §3. No exponer expedientes completos a operador O, público o invitado, incluso con ID conocido, nonce, enlace de pedido o acceso a la lista. Respuestas no-cache, consulta auditada sin PII en logs y denegación de recurso de otra instalación.

**D-190** fija admisión previa a MP de 60 segundos sin renovación, grant de acción sensible de 5 minutos/un uso y cookie de invitado 30 minutos de inactividad limitada por el enlace fuente de 90 días; 10 minutos OTP, 24 horas de descarga y 15 minutos de retención comercial permanecen como fueron confirmados. El tiempo de sesión no autoriza borrar la idempotencia financiera. **D-191…D-194** fijan contrato de integridad lógica, obligaciones legales condicionadas al comercio, fuente INEI versionada y distribución ZIP+fuentes GPL. Ninguna de estas decisiones equivale a implementación, pruebas o habilitación comercial.

**Precedencia vigente:** docs/01 decisiones D-189…D-198, docs/42 para rectificaciones y OpenAPI **0.10.0-draft** para operaciones/DTO; docs/37/38 para reglas específicas. Las cifras históricas de 63 REST/60 rutas/113 DTO presentes en secciones anteriores describen el estado previo a D-189.

## 1. Evidencia oficial contrastada

| Referencia | Hecho comprobado | Restricción aplicable |
| --- | --- | --- |
| WordPress REST: custom endpoints | Registrar rutas con `register_rest_route` en `rest_api_init`, namespace/version, `args` y `permission_callback`; responder usando REST/`WP_Error` | No registrar mutaciones sin control de acceso ni usar `wp_send_json` como callback REST |
| WordPress REST cookie authentication | La sesión WordPress usa cookies y `X-WP-Nonce` para CSRF; el nonce no reemplaza autenticación ni la capability | Solo panel de empleados WordPress; invitados usan secretos propios, sin cuenta |
| WordPress enqueue y script modules | `wp_enqueue_script`/`wp_register_script_module` soportan assets con dependencias/versiones y carga condicional | Angular se incluye en el ZIP precompilado, no requiere un segundo servidor o Node en hosting |
| Angular CLI deployment y HttpClient | Compilación produce archivos de navegador; `HttpClient` realiza solicitudes por suscripción y admite configuración de interceptores | Evitar duplicar mutaciones por varias suscripciones; configurar base URL en runtime, sin asumir raíz de dominio |
| MP Checkout Pro **Orders API** | `POST https://api.mercadopago.com/v1/orders` con Access Token backend, `X-Idempotency-Key`, `type=online`, `processing_mode=manual`; responde `id` y `checkout_url` | No usar Preferences API ni Checkout Bricks indistintamente: modos distintos no comparten contratos |
| MP Orders API: importes | `total_amount` es string decimal y, si se envía `items`, debe igualar la suma `unit_price × quantity`; cero no permitido | E-04 exige monto PEN exacto, un cobro inicial y delivery explícito; no confiar en total del navegador |
| MP Orders API: `payer` | `payer` es condicional y `payer.email` es obligatorio **cuando se envía `payer`** | No imponer email al comprador exclusivamente físico por el uso de MP; confirmar aceptación real sin `payer` en pruebas |
| MP Orders API: vigencia | `expiration_time` es duración ISO 8601 desde creación; es distinta del reloj del hold local | No confundir vigencia de Checkout Pro con retención local de 15 min, ni confiar en que expirar hold canceló la order |
| MP Orders: consulta | `GET /v1/orders/{order_id}` devuelve estado/importe, con detalles y transacciones | `created` / `processed` por sí solos no autorizan mapear todo a pago APROBADO sin examinar pagos acreditados |
| MP Orders: Webhooks | Tópico **order**, `data.id`, headers `x-signature` y `x-request-id`; se valida HMAC y se obtiene recurso del proveedor | Firma/autenticación primero, inbox durable y GET del recurso antes de actualizar cobro; duplicados esperables |
| MP Orders: errores | 409, 423, 429, 5xx y fallos de red pueden representar intento ambiguo; `Retry-After` en cuota | Conciliar antes de intentar otro pago; nunca rotar idempotency key por timeout a ciegas |

**Fuentes oficiales:**
- https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- https://developer.wordpress.org/reference/functions/register_rest_route/
- https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- https://developer.wordpress.org/reference/functions/wp_enqueue_script/
- https://developer.wordpress.org/reference/functions/wp_register_script_module/
- https://developer.wordpress.org/reference/functions/wp_safe_remote_post/
- https://angular.dev/tools/cli/deployment
- https://angular.dev/guide/http/making-requests
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/create-order
- https://www.mercadopago.com.pe/developers/es/reference/online-payments/checkout-pro/create-order/post
- https://www.mercadopago.com.pe/developers/es/reference/online-payments/checkout-pro/get-order/get
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/notifications
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/resources/notifications/webhooks
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/additional-settings/define-order-validity
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/payment-management/integration-errors

**Estado de verificación:** documentación consultada, **NO** se crearon órdenes, usuarios, webhooks ni pagos reales o de prueba. La aceptación efectiva de ítems de entrega, país/cuenta/credenciales y vencimiento PT15M debe comprobarse con un entorno autorizado antes de habilitar pagos. Las páginas de documentación de Orders y Preferences **son productos distintos**.

## 2. Fronteras de arquitectura y reglas ya aprobadas

- **D-42:** WordPress/PHP renderiza **catálogo público indexable** mediante bloques/shortcodes, Angular interactúa con carrito, checkout, agenda y panel. WordPress/PHP es autoridad única de precio, stock, citas, pedidos, permisos y pasarela. Una sola tienda/local por instalación.
- **D-24/D-123:** pedido exclusivamente físico requiere teléfono, **no email**; digital/servicio requieren correo y un mixto acumula requisitos. No exigir cuenta WordPress ni registro al comprador.
- **D-33/D-36/D-37:** un cobro inicial por pedido; MP genera intento válido al pulsar Pagar y hold comercial de 15 min **solo tras éxito**; contraentrega compromete recursos al confirmar, cobro pendiente.
- **D-55…D-77:** ejes separados de pedido, intento/MP, hold, líneas físicas, digitales y citas; no introducir estados arbitrarios al simplificar REST.
- **D-78…D-85:** operador limitado, administrador controla secretos/fiscalidad/reintegros externos/Libro, invitado con enlace solo lectura y verificación adicional para cambios.
- **D-96…D-108:** montos de dominio en enteros de céntimos PEN, subtotal exacto, un delivery por pedido, total histórico inmutable, bruto/comisiones separados, **no ejecutor API de reembolsos**.
- **D-109…D-122:** InnoDB transaccional, lock estable de stock/agenda, fases de creación MP sin HTTP dentro de SQL, idempotencia durable, webhook validado y outbox/recuperación.
- **D-123…D-134:** `wp_mail` con seguimiento WEB, sin marketing ni WhatsApp/SMS automático; email aceptado no significa recibido; constancias/reclamos con obligaciones normativas.
- **D-135…D-148:** token de seguimiento de 256 bits y **90 días**, solo lectura; OTP **8 dígitos/10 min/5 intentos** para acciones sensibles con correo; recuperación manual teléfono; descargas 256 bits y **24 h**, WordPress para empleados, mínimos datos, no caché compartida ni exposición de secretos.

## 3. E08-A APROBADO: namespace, versionado y superficie REST

**E08-A APROBADO (D-149):** un único namespace REST versionado `peru-commerce/v1`, registrado desde `rest_api_init`; rutas de negocio explícitas por recurso y método, sin `POST /action` genérico ni introducir RPC sin validación. URLs generadas con `rest_url()` para admitir WordPress en subdirectorios/permalinks distintos. Las rutas descritas en esta sección son **contrato propuesto**; nombres definitivos requieren aprobación. Todas declaran schema, validación, sanitización, `permission_callback` que además verifica recurso/estado y datos de salida filtrados.

**Sin endpoints públicos de configuración secreta**, dumps SQL, logs con tokens, SDK MP administrativo, ejecución de reembolso externo ni acceso a archivos sin autorización. Para operaciones que tocan dinero/stock/agenda, idempotencia local durable D-115.

**Anexo P0-20/P0-24 posterior (2026-11-17):** `docs/31-CONTRATO-ANGULAR-WORDPRESS-ASSETS-P0-20-V1.md` documenta 8 superficies de carga, 10 campos propuestos de manifest, módulos ESM WordPress y 20 casos A20-X; `docs/32-LICENCIA-FUENTES-Y-DISTRIBUCION-P0-24-V1.md` detalla GPL-2.0-or-later, fuentes correspondientes por release, 18 LIC y 20 LIC-X. **D-150/D-170/D-173 estaban aprobadas en política; no existe build, ZIP, archivo LICENSE físico ni auditoría de dependencias instaladas**. No crear entradas REST adicionales ni interpretar manifest propuesto como generado.

## 4. E08-B APROBADO: Angular embebido en WordPress

**E08-B APROBADO (D-150):** empaquetar en el ZIP la distribución compilada de **una** aplicación Angular con composiciones móvil/escritorio y entrypoints cargados **condicionalmente** para carrito, checkout y panel; páginas públicas indexables siguen siendo salida PHP, no Angular SSR obligatorio. Sin Node, npm, daemon, Angular dev server, Docker o segundo dominio de aplicación en hosting de producción. Enqueue vía API de assets WordPress y manifest/archivos versionados; resolver base de assets según URL del plugin (incluyendo WordPress en subdirectorio) y evitar duplicación de scripts al mostrar varios shortcodes.

**Contrato WordPress ↔ Angular propuesto:** PHP renderiza un root de montaje solo cuando hay superficie interactiva con configuración mínima **no secreta**: URL REST generada, identificación de idioma `es-PE`, moneda `PEN`, capacidades UI del panel como orientación (no permisos reales), versión de API/asset y nonce **solo para empleados autenticados**. No inyectar secretos MP, saldos de otros clientes, enlace privado ni tokens de descarga en variables globales/HTML indexable. CSS y recursos Angular aislados del tema; no emitir import maps/scripts duplicados.

**No decidir todavía:** versión exacta WP/Angular/PHP/BD y compatibilidad de la build en P0-19, manifest real, estrategia CSS, budgets o cumplimiento WCAG 2.2 AA. Figma es diseño, no contrato de backend.

**Contrato P0-21 complementario (2026-11-17):** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` detalla OSM Standard best-effort (no SLA, atribución, caché y Referer), Leaflet solo a petición, no autocomplete Nominatim público, catálogo INEI versionado y delivery por distrito. Los R8-05/06/07/16/28/45/46/51/52 **se mantienen sin rutas adicionales** y sujetos a backend PHP/seguridad E-02/E-07. El archivo UBIGEO exacto de V1 y condiciones reales del servidor tiles no están comprobados.

## 5. E08-C/E08-D APROBADOS: cotización, carrito y pedido

**E08-C APROBADO (D-151):** estado temporal de carrito en frontend **sin reservas y sin guardar precios autorizativos en sesión**, revalidado mediante `POST /public/quotes` con artículos/variantes/cantidades, cita seleccionada, modalidad/distrito y contacto exigible. Backend responde con cotización **comercial** y reglas aplicables, sin comprometer stock/cupo. Al pulsar Pagar, **revalidar otra vez**: si cambió el total o disponibilidad, devolver conflicto de recotización para nueva aceptación y **no crear order MP**. No aceptar descuentos, tarifa o totales enviados por el comprador.

**E08-D APROBADO (D-152):** separar inicio MP y confirmación de contraentrega en dos rutas tipadas, con **idempotencia de operación** y semántica diferente:
- `POST /public/checkout/mercado-pago` → backend valida todo, crea admisión breve y order externa con clave idempotente E-05, confirma hold local válido de **15 minutos** y devuelve únicamente entonces un `checkout_url` autorizado, referencia pública de seguimiento y fecha efectiva. Si el proveedor responde ambiguamente, devolver operación `en_revision` sin URL de un segundo cobro.
- `POST /public/checkout/contraentrega` → transacción local confirma pedido, compromete stock/cita, deja dinero pendiente y devuelve seguimiento; no crea order MP ficticia ni hold de 15 minutos.
- Reintentar la **misma operación/payload** devuelve resultado existente; la misma clave con otros artículos/monto/slot es conflicto y no mutación adicional. El navegador no puede generar dos pedidos por doble clic, refresh o reconexión.

### Superficie pública propuesta (P = público con validación estricta; G = sesión de invitado de lectura; V = verificación adicional)

| ID | Método y ruta relativa `peru-commerce/v1` | Actor | Entrada mínima / efecto, guardas |
| --- | --- | --- | --- |
| R8-01 | GET `/public/bootstrap` | P | Nombre público comercio, moneda, modalidad/áreas y configuración inocua; no claves ni PII |
| R8-02 | GET `/public/products` | P | Resumen de catálogo publicable con paginación/índice; el HTML SEO sigue en PHP |
| R8-03 | GET `/public/products/{id}` | P | Detalle, variantes y precio actual; archivos protegidos y stock exacto interno nunca públicos |
| R8-04 | GET `/public/services/{id}/slots` | P | Fecha local y franjas estimadas, sin hold ni promesa de disponibilidad |
| R8-05 | POST `/public/quotes` | P | Carrito, entrega/UBIGEO, cita, contacto aplicable; total PEN exacto/errores; **sin reserva** |
| R8-06 | POST `/public/checkout/mercado-pago` | P | Identidad contactable, quote/fingerprint, idempotency; intento MP en fases/hold de 15 min |
| R8-07 | POST `/public/checkout/contraentrega` | P | Idempotency y reglas de cobro único presencial; confirmación local, pago pendiente |
| R8-08 | POST `/guest/sessions` | Token de URL fragment | Intercambia secreto de seguimiento por cookie de sesión privada **solo lectura** |
| R8-09 | GET `/guest/order` | G | Un solo pedido propio, campos filtrados por E-07; estados administrativos/pagos/líneas separados |
| R8-10 | POST `/guest/challenges` | G/V | Solicitud OTP para `purpose` de acción sensible, limitación de abusos y respuesta uniforme |
| R8-11 | POST `/guest/challenges/verify` | G/V | Comprueba OTP de 8 dígitos/10 min/5 intentos y emite capacidad **específica de acción** |
| R8-12 | POST `/guest/requests` | G + V | Encola **solicitud** de cambio dirección, reemisión o devolución; no la aprueba ni ejecuta automáticamente |
| R8-13 | POST `/guest/digital/sessions` | Token digital | Comprueba token 256 bits, derecho/pago/24 h; sesión de descarga acotada y distinta de seguimiento |
| R8-14 | GET `/guest/digital/{right_id}/file` | Sesión digital | Entrega archivo protegido solo con derecho vigente, sin ruta pública/FTP/direct URL |
| R8-15 | POST `/public/reclamaciones` | Público | Libro nativo con entradas/constancia bajo matriz legal P0-25, sin cuenta ni compra |
| R8-16 | GET `/public/ubigeo` | Público | Distritos/cobertura/tarifa pública filtrada; fuente/actualización P0-21/ P0-25 |
| R8-17 | POST `/webhooks/mercado-pago` | Proveedor HMAC | Verifica firma, cuenta, ambiente e inbox E-05; **nunca** usa nonce de WordPress |
| R8-18 | GET `/public/return` (página WP, NO REST) | Navegador | Muestra seguimiento/estado en revisión; **no acredita pago por querystring** |

**Contratos físicos pendientes después de aprobar rutas en política:** todos los campos formales de Libro J-01/P0-25, format URI de privacidad/descarga, token/cookie exactos, restricciones de sesión/browser, payloads y permisos REST E-07/E-08 y esquema físico P0-18. La tabla queda **APROBADA como inventario de diseño** R8-01…R8-52, no como rutas publicadas ni esquema OpenAPI físico. E08-R exige completar propiedades/métodos/permisos antes de implementar.

## 6. DTO comercial e invariantes de validación (modelos conceptuales por formalizar E08-R)

**Petición de cotización**, conceptual:
```json
{
  "items": [
    {"product_id": 123, "variant_id": 456, "quantity": 2},
    {"product_id": 789, "quantity": 1, "service_start": "2026-12-03T15:00:00-05:00"}
  ],
  "delivery": {"mode": "delivery", "ubigeo": "150101", "address": "Domicilio suministrado por comprador"},
  "contact": {"phone": "999888777", "email": "comprador@ejemplo.com"}
}
```
Los ejemplos son **valores ficticios** y no representan datos reales ni campos normativos ya aprobados. Esquema final usará un ID público de producto seguro y tipado; las relaciones producto/variante/cita se verifican en servidor, sin enviar IDs internos de pago, stock o agenda como autoridad. `service_start` se acompaña de zona/slot válido y no se acepta que el cliente reserve más de una cita por pedido.

**Respuesta conceptual de cotización**:
```json
{
  "quote_id": "opaco-no-enumerativo",
  "currency": "PEN",
  "lines": [{"item_ref": "a", "quantity": 2, "unit_price_minor": 1250, "subtotal_minor": 2500}],
  "delivery_minor": 600,
  "total_minor": 3100,
  "stock_and_slot_checked_at": "2026-11-17T12:00:00Z",
  "availability_reserved": false
}
```
El ID de quote **no garantiza** precio/stock para siempre. `quote_id` y sus datos autorizados se revalidan al Pagar; el snapshot de precio y tarifa se congela al crear intento válido. **No hay cálculo fiscal general** ni cuotas/fracciones, cupones o ofertas programadas. Moneda interna de intercambio en **enteros de céntimos**; serializar `"31.00"` al proveedor, no float binario.

**Checkout**, campos propuestos: `operation_id` UUID opaco de intento estable, `quote_id`, `quote_version`, `contact`, `delivery`, `consent_evidence_ref` de políticas presentadas, `payment_method` implícito en ruta. El backend no confía en `total_minor` solicitado por frontend ni en identidad/información de stock/cita no verificada. Controles de abuso por IP/sesión/instalación sin impedir el derecho público de reclamación.

## 7. E08-E/E08-F/E08-G APROBADOS: sesión de invitado, WordPress y acciones sensibles

**E08-E APROBADO (D-153):** enlace de seguimiento enviado como URL de página WordPress con **token en fragmento del navegador** (no enviado en GET HTTP inicial), extraído una sola vez por frontend privado y canjeado por POST HTTPS `/guest/sessions` en una sesión **HttpOnly, Secure, SameSite** restringida a un pedido y modo lectura. Limpiar el fragmento del historial después de canjearlo; aislar scripts externos, caché, analytics y logs. Un usuario que copie un enlace no obtiene facultad de devolución ni de otra orden; caduca/revoca según D-137 (90 días inicialmente). La sesión técnica no amplía la vigencia del token subyacente.

**E08-F APROBADO (D-154):** panel admin/operador con login y cookies WordPress + `X-WP-Nonce` para CSRF; autorización efectiva en cada request mediante **capability específica, recurso, comercio/instalación y campos de salida**, no solo rol string ni comprobación de UI. Sin JWT propio, API key de empleados ni exponer `manage_options` como sustituto genérico de permisos. Solicitudes same-origin; no habilitar CORS `*` con cookies ni depender de un frontend publicado en otro dominio.

**E08-G APROBADO (D-155):** una acción sensible de invitado con email usa **OTP 8 dígitos, 10 minutos, 5 intentos** D-138 y devuelve comprobación de **propósito específico y uso único**; solo habilita **solicitar** ajuste/reenlace/reintegro/archivo, no aprobarlo en backend automáticamente. Comprador solo teléfono se tramita **manualmente** desde panel, con identidad comprobada, capacidad de empleado y auditoría, no OTP SMS. El enlace de seguimiento original **no** basta para cambiar email, destino o titular. Para Libro no imponer cuenta/OTP a presentación pública permitida.

**No inventar** sesión WordPress para invitados, verificación por DNI obligatorio, token admin dentro de correo, nonce que representa identidad, o endpoint de búsqueda pública por teléfono/número de pedido.

## 8. Superficie administrativa — política de rutas APROBADA E08-F/E08-N

**A = administrador**, **O = operador con capabilities/guardas aprobadas**. Los nombres de capacidades están **APROBADOS como diseño E08-N (D-162)**, pero aún no se asignan a cuentas reales y requieren mapeo estático completo PER↔R8 y prueba de controles. El backend aplica D-78…D-85, D-64, estados E-01 y mínimo campo E-07 en cada método. Las lecturas son filtradas/paginadas y nunca permiten introducir un `user_id`/tenant distinto para escalar.

| ID | Método y ruta relativa | Actor permitido propuesto | Guarda y efecto permitido |
| --- | --- | --- | --- |
| R8-19 | GET `/admin/bootstrap` | A/O | Perfil, capacidades UI no autoritativas, nombre comercio y diagnóstico inocuo |
| R8-20 | GET `/admin/orders` | A/O | Pedidos paginados, contacto y campos mínimos por tarea; sin exportación implícita |
| R8-21 | GET `/admin/orders/{id}` | A/O | Detalle con secciones filtradas por capability/campo; no IDOR |
| R8-22 | POST `/admin/orders/{id}/lines/{line_id}/fulfillment` | A/O | Transiciones físicas E-01 permitidas (PREPARANDO/LISTO/DESPACHADO/ENTREGADO); evidencia y versión |
| R8-23 | POST `/admin/orders/{id}/lines/{line_id}/cancel` | A/O | Línea completa D-95, elegibilidad E-03; operador solo si **nunca hubo dinero acreditado** D-64 |
| R8-24 | POST `/admin/orders/{id}/cash-receipts` | A/O | Monto neto realmente recibido D-105 y idempotencia; no corrección destructiva |
| R8-25 | POST `/admin/orders/{id}/cash-corrections` | A | Evento correctivo inmutable, evidencia y motivo, sin editar recepción original |
| R8-26 | POST `/admin/orders/{id}/adjustments` | A | Ajuste financiero/admisible con documento y cantidades, no pago/reembolso ejecutado |
| R8-27 | POST `/admin/orders/{id}/external-refunds` | A | Registrar **resultado** externo total/parcial comprobado y conciliado, sin API proveedor refund |
| R8-28 | POST `/admin/orders/{id}/delivery-change` | A/O según D-83 | Operador propone; aprobación **solo A** ante costo/cobertura/dinero acreditado |
| R8-29 | GET `/admin/reconciliation` | A | Órdenes externas y excepciones; filtrar secretos/cuentas ajenas |
| R8-30 | POST `/admin/reconciliation/{id}/resolve` | A | Resolución auditable de pago tardío/discrepancia, nunca segunda order por reflejo |
| R8-31 | GET `/admin/products` | A/O | Metadatos de catálogo y variantes con paginación |
| R8-32 | POST `/admin/products` | A/O | Alta producto validada; sin archivos privados públicos |
| R8-33 | PATCH `/admin/products/{id}` | A/O | Editar precio/oferta/catálogo, versión/auditoría, sin alterar pedidos históricos |
| R8-34 | POST `/admin/products/{id}/visibility` | A/O limitado | O puede **ocultar** D-79; publicar/volver a publicar queda bajo capability **solo A por defecto**; no borrar histórico |
| R8-35 | POST `/admin/stock/adjustments` | A/O | Ajustar variante con motivo bajo lock/versión y auditoría |
| R8-36 | GET `/admin/agenda` | A/O | Horarios, citas y bloques filtrados por finalidad |
| R8-37 | POST `/admin/agenda/closures` | A/O | Horarios/cierres con control de disponibilidad y versión |
| R8-38 | POST `/admin/appointments/{id}/reschedule` | A/O | Nueva franja validada, conservar anterior/historial/constancia E-06 |
| R8-39 | GET `/admin/notifications` | A/O | Bandeja N6 por prioridad/rol, sin expedientes completos del Libro para O |
| R8-40 | POST `/admin/notifications/{id}/resend` | A/O autorizado | Reenvío de intención original, solo destinatario/medio verificado y auditoría |
| R8-41 | GET `/admin/complaints` | A | **Listado resumido** de Libro (`ComplaintPage`); no expone expediente íntegro en REST |
| R8-42 | POST `/admin/complaints/{id}/respond` | A | Respuesta/constancia legal, no dar por recibido solo por `wp_mail` TRUE |
| R8-43 | GET `/admin/privacy-requests` | A | **Listado resumido** ARCO (`PrivacyPage`); detalle no incluido en este REST |
| R8-44 | POST `/admin/privacy-requests/{id}/resolve` | A | Retener/anonimizar/exportar datos según ley; no borrar comprobantes requeridos |
| R8-45 | GET `/admin/settings` | A | Política/cobertura/fiscalidad/terceros, con secretos siempre enmascarados |
| R8-46 | PATCH `/admin/settings` | A | Configuración sensible auditada; no desactivar obligaciones legales |
| R8-47 | PUT `/admin/payment-credentials` | A | Cambiar credencial MP de manera protegida, no devolverla ni guardarla en JS |
| R8-48 | POST `/admin/digital-files` | A | Upload/administración privada con controles de tipo/tamaño; sin Media Library pública |
| R8-49 | GET `/admin/diagnostics` | A | Comprobar configuración, engine/hosting/cron/email sin revelar tokens/archivos/logs brutos |
| R8-50 | POST `/admin/privacy-exports` | A | Exportación segura con finalidad/identidad, archivo temporal no público |
| R8-51 | GET `/admin/ubigeo-coverage` | A | Configuración de cobertura/distritos y tarifas |
| R8-52 | PATCH `/admin/ubigeo-coverage` | A | Ajustes tarifarios/ubicaciones sin mutar pedidos existentes |

**No se añade una segunda API de envío, una segunda de cobro o endpoints de cancelación MP/reembolso externo.** PER-01…PER-56 del doc.15 se asignarán explícitamente a R8-01…R8-52 o declararán ausencia de endpoint (p. ej., documento externo/operación no permitida); **no** inferir que todas las 56 operaciones implican un endpoint propio. Contrato físico de rutas y capabilities aún requiere ratificación.


### Extensión C23 ratificada: R8-53…R8-64 (D-176…D-187)

El **corte histórico de la ampliación C23** tenía **63 operaciones REST en 60 rutas URL diferentes** y una página WordPress R8-18 no REST. Se preservan las 51 operaciones previas y sus reglas; las 12 añadidas proceden del anexo 27. Ningún endpoint adicional habilita reembolso por proveedor ni emisión fiscal, y ninguna capability de lectura autoriza una mutación. El `operationId` es R8-53…R8-64, sin duplicar ID histórico. Todas usan sesión nativa WP + nonce anti-CSRF + verificación por capability, objeto, estado, actor, campo y petición idempotente cuando mutan recursos.

| ID | Método y ruta relativa | Actor/capability existentes autorizadas en contrato | Efecto limitado |
| --- | --- | --- | --- |
| R8-53 | PATCH `/admin/agenda/schedule` | O/A con perucommerce_appointments_manage; sólo operación PER-19 | PER-19: Horario semanal y buffers, sin mover citas confirmadas |
| R8-54 | POST `/admin/appointments/{id}/outcome` | Solo A con perucommerce_appointments_manage; O no habilitado PER-20 | PER-20: CONFIRMADA→REALIZADA/NO_ASISTIO; actor O pendiente de ratificación |
| R8-55 | POST `/admin/orders/{id}/lines/{line_id}/logistics-incidents` | Solo A con perucommerce_orders_fulfill; O no habilitado PER-27 | PER-27: Abrir incidencia de entrega no comprobada; no cancelar línea |
| R8-56 | POST `/admin/logistics-incidents/{id}/proposals` | Solo A con perucommerce_orders_fulfill; O no habilitado PER-28 | PER-28: Proponer resolución sin efectos financieros/logísticos |
| R8-57 | POST `/admin/logistics-incidents/{id}/resolve` | Solo A con perucommerce_orders_financial_adjust Y perucommerce_orders_fulfill | PER-28/32: Resolver expediente; R8-22/23/26/27 ejecutan efectos separados |
| R8-58 | POST `/admin/orders/{id}/tracking-links/reissue` | Solo A con perucommerce_privacy_manage Y perucommerce_orders_read | PER-11: Verificar identidad, revocar enlace anterior y emitir nuevo enlace una sola vez |
| R8-59 | POST `/admin/digital-rights/{id}/links/reissue` | Solo A con perucommerce_digital_files_manage | PER-41: Reemitir token 24h, sin modificar derecho/compra |
| R8-60 | POST `/admin/digital-rights/{id}/revoke` | Solo A con perucommerce_digital_files_manage | PER-41: Revocar derecho por causa legítima y decisión previa, nunca refund |
| R8-61 | POST `/admin/digital-cases/{id}/decide` | Solo A con perucommerce_digital_files_manage | PER-31: Decidir expediente sin revocar ni devolver dinero automáticamente |
| R8-62 | GET `/admin/digital-files/{id}/download` | Solo A con perucommerce_digital_files_manage | PER-23: Descarga protegida administrativa, sin exponer URL webroot |
| R8-63 | POST `/admin/digital-files/{id}/replacements` | Solo A con perucommerce_digital_files_manage | PER-24: Nuevo archivo para ventas futuras; pedidos anteriores conservan original |
| R8-64 | POST `/admin/orders/{id}/external-documents` | Solo A con perucommerce_settings_manage Y perucommerce_orders_read | D-26/J-03: Guardar referencia externa de boleta/factura/RHE/nota/guía, sin emisión SUNAT |

**Condiciones innegociables de cobertura:**

- R8-53: operador/admin únicamente para gestionar horario semanal/buffers, con validación de conflicto con citas futuras; R8-37 conserva gestión de cierres puntuales.
- R8-54: solo A registra V-05/V-06, desde CONFIRMADA y con evidencia; no multa ni reembolso automático por NO_ASISTIO. Actor O de PER-20 sigue sin autorización.
- R8-55/56/57: solo A abre, propone o resuelve expediente de DESPACHADO sin entrega. Si corresponde alterar cumplimiento o ajustar dinero, R8-22/23/26/27 siguen siendo rutas y guardas separadas. La petición de cliente continúa vía R8-12; O de PER-27/28 sigue denegado.
- R8-58: solo A tras verificación proporcional de identidad, nuevo enlace 256 bits/90 días, anterior revocado; R8-59 token digital independiente 256 bits/24 h, preserva derecho. La reemisión no almacena secreto claro ni lo envía en outbox/logs; respuesta HTTPS no cacheable visible una sola vez. Un reintento con la misma clave idempotente **no devuelve el secreto**, solo referencia/historial: no se permite doble emisión ni se garantiza reenvío recuperable del secreto.
- R8-60: revocación con expediente/causa válida D-92, no auto-refund; R8-61 decisión de expediente digital sin revocación ni dinero automática; nunca habilitar REVOCADO→HABILITADO sin contrato E-01.
- R8-62: stream admin de binario privado bajo finalidad comprobable, sin URL pública; R8-63: nuevo binario con versión activa para **ventas futuras**, sin sobrescribir derechos ni archivos históricamente vendidos.
- R8-64: A registra **referencia de comprobante, nota o guía emitidos externamente** según régimen/operación J-03, jamás CPE/nota de crédito emitida por el plugin, sin RUC/DNI universal obligatorio. R8-46 mantiene configuración fiscal, no creación documental.
- PER-40 habilitación digital y PER-42 cierre de pedido permanecen internos por hechos E-01. PER-49/51/52 se gestionan mediante WordPress; PER-36 refund API está prohibido; PER-09 exportación comercial general quedó **EXCLUIDA de V1 por D-188**, sin ruta; R8-50 conserva su finalidad de privacidad.

**Estado físico:** Los DTO y controles de estas 12 operaciones se especifican por la ampliación del borrador OpenAPI; SQL/DDL, plazos técnicos aún sin fijar, legalidad por emisor/comerciante, entrega segura/hosting y verificación de seguridad siguen sujetos a P0-18/P0-19/P0-23/P0-25/P0-26. La aprobación de cobertura **no** autoriza implementación ni pruebas.

### Propuesta de capabilities WordPress por grupos (E08-N)

| Capability candidata | Alcance principal | Rol inicial propuesto |
| --- | --- | --- |
| `perucommerce_orders_read` | Lectura mínima pedidos/operación | O/A |
| `perucommerce_orders_fulfill` | Preparar/entregar evidencia | O/A |
| `perucommerce_orders_cancel_unpaid` | Cancelar solo si D-64, sin dinero acreditado ni ajustes | O/A |
| `perucommerce_orders_financial_adjust` | Ajustar/cancelar con impacto financiero | A |
| `perucommerce_cash_record` | Registrar efectivo neto | O/A |
| `perucommerce_cash_correct` | Corregir cobros registrados | A |
| `perucommerce_refunds_record_external` | Registrar devolución externa **comprobada** | A |
| `perucommerce_reconciliation_manage` | Recuperar incidencias MP/importe/cupo tardío | A |
| `perucommerce_catalog_edit` | Crear/editar borrador, productos, variantes, precios y ofertas sin publicación autónoma adicional | O/A |
| `perucommerce_catalog_hide` | Ocultar producto sin borrarlo destructivamente, D-79 | O/A |
| `perucommerce_catalog_publish` | Publicar nuevos productos o volver a publicar con revisión de condiciones y permisos específicos | A; operador NO autorizado por defecto hasta decisión explícita |
| `perucommerce_stock_manage` | Stock y auditoría | O/A |
| `perucommerce_appointments_manage` | Agenda y citas auditables | O/A |
| `perucommerce_notifications_operate` | Bandeja operativa y reenvío autorizado | O/A, filtrado |
| `perucommerce_settings_manage` | Configuración comercial/sensible | A |
| `perucommerce_integrations_manage` | MP, emails y proveedor cartográfico/credenciales | A |
| `perucommerce_digital_files_manage` | Archivos protegidos/rights | A |
| `perucommerce_complaints_manage` | Expedientes del Libro y respuesta formal | A |
| `perucommerce_privacy_manage` | Solicitudes ARCO y exportaciones de PII | A |
| `perucommerce_permissions_manage` | Roles y asignaciones de capabilities del plugin | A |

**Importante:** esta tabla es propuesta, no mapeo WordPress definitivo. La asignación de rol no basta para autorizar: una acción requiere capacidad + alcance de registro + estado E-01 + guarda financiera E-04 + protección de campo E-07. Solo administrador modifica roles y credenciales D-84, y jamás debe poder reescribir el histórico financiero.

## 9. E08-H/E08-I/E08-J/E08-K APROBADOS condicionalmente: Mercado Pago Orders

**E08-H APROBADO (D-156):** un comerciante configura **sus propias credenciales de Mercado Pago por instalación**, mediante panel administrador. WordPress/PHP llama `POST /v1/orders` y `GET /v1/orders/{id}` usando Access Token **solo servidor**; no marketplace/OAuth multi-comercio obligatorio, Checkout Bricks, Preferences API ni SDK JS MP en el navegador V1. Configurar credenciales/ambiente y clave secreta de webhooks; mantener test y producción aislados y sin desplegar token de prueba en producción. `checkout_url` se entrega al browser solo después de confirmar hold local válido. El comerciante sí necesitará su cuenta/credenciales del proveedor para cobrar: documentar ese requisito externo.

**E08-I APROBADO CON CONDICIÓN DE COMPATIBILIDAD (D-157):** enviar `payer` **solo cuando exista correo válido del comprador**; en físico sin email omitir el objeto `payer` como diseño elegido. **Bloquear habilitación de MP hasta verificar en el entorno peruano** que la cuenta/medio realmente acepta esa solicitud. No fabricar email ni modificar D-24. **No fabricar un correo** ni usar correo del comerciante como comprador. Si MP exige algún dato adicional para Perú, detener habilitación y comprobar compatibilidad en entorno de prueba con el proveedor; no cambiar el contrato D-24 unilateralmente.

**E08-J APROBADO CON VERIFICACIÓN OBLIGATORIA (D-158):** serializar **líneas comerciales reales** como ítems del proveedor con `quantity`, `unit_price` y títulos mínimos sin datos sensibles; el **cargo de entrega** se representa, cuando aplica, como un **ítem explícito de servicio logístico de pago, NO un producto del catálogo, SIN stock** (`title = "Entrega"`, cantidad 1, importe en PEN). La suma de ítems debe ser igual a `total_amount` serializado desde céntimos; no añadir IGV universal, comisiones ni redondeos fantasma. **Condición bloqueante:** validar en contrato real que Orders API acepte ese ítem de cargo de entrega y sus campos; si no, **bloquear MP y solicitar decisión explícita** entre representación documentada o el camino sin ítems que el proveedor acepte. No inventar producto WooCommerce ni un ítem cobrable sin mostrárselo al comprador.

**E08-K APROBADO CON VERIFICACIÓN OBLIGATORIA (D-159):** al crear order MP enviar `expiration_time = "PT15M"` **solo si el proveedor lo acepta en el país/medio y entorno de prueba**; guardar `created_date`/vencimiento real externo si disponible y el vencimiento **local** del hold calculado desde intento válido. Son relojes independientes; una order externa podría expirar antes de la retención local o admitir pagos tardíos. **No** reanimar hold, afirmar que se canceló el pago ni prometer disponibilidad porque venció el cron. Ante falta de compatibilidad de `PT15M`, no extender retención local: dejar pendiente aprobación de estrategia explícita de expiración externa y atención de pago tardío. No asumir que crear order y acreditar pago son equivalentes.

**Contrato de MP propuesto de creación:**
- `Authorization: Bearer <credencial privada>` solo PHP, `X-Idempotency-Key: <UUID persistido>`.
- `type = "online"`, `processing_mode = "manual"`, `total_amount` decimal string exacto `"73.50"`, `external_reference` opaca y correlacionable sin PII (máximo fuente oficial), `items[]` conciliables con total.
- `payer` solo cuando email presente, nunca enviar DNI/RUC sin necesidad y base legal.
- `config.online.success_url/failure_url/pending_url` apuntan a página WP de **resultado no autoritativo**; jamás mutan pago. URL se genera desde dominio HTTPS actual y se contrasta con la allowlist de la propia instalación.
- `checkout_url` **es una URL externa del proveedor**; validar que host/esquema corresponda al destino permitido oficial por país/ambiente antes de redirigir, sin aceptar una URL arbitraria proporcionada por el comprador.
- El comercio no opera como marketplace de reparto; `marketplace_fee` no representa comisión del proveedor y no debe configurarse por error.
- Ante respuesta ambigua y `X-Idempotency-Key` reutilizada, consultar order por ID/external_reference y conciliar primero; **no duplicar flujo**.

**PENDIENTE crítico:** pruebas autorizadas sobre cuenta peruana, restricciones de medios de pago de Checkout Pro Orders, duración `PT15M`, envío `payer` ausente, cargo `Entrega`, estado de transactions y webhook. No atribuir al proveedor resultados que la documentación no prueba.

## 10. E08-L APROBADO: webhook seguro, respuesta e idempotencia

**E08-L APROBADO (D-160):** solo Webhooks Orders `type=order` habilitados en la integración seleccionada, con verificación `x-signature` (timestamp y HMAC según manifest oficial con `data.id` y `x-request-id`), comparación de tiempo constante, validación de timestamp/replay y asociación cuenta/ambiente. Aceptar POST sin cookies/nonce WordPress **pero con autenticación HMAC** y límite de payload/abuso. **No** incorporar IPN legacy como fallback, pues no ofrece firma equivalente.

**Secuencia confirmada por política D-116/D-117 y propuesta REST:**
1. Comprobar firma/método/identidad del recurso, validar forma y ámbito; una firma inválida no muta pedido ni genera comunicación.
2. Guardar notificación en **inbox durable** deduplicada y confirmada localmente; solo entonces responder 200/201 según contrato de MP. Si falla persistencia, devolver error para que MP reintente; no responder falso éxito.
3. Worker o procesamiento seguro obtiene order por `GET /v1/orders/{id}` con credencial de instalación. Contrastar vendedor, modo, importe PEN, order/attempt y transacciones. **No equiparar `status=processed` sin verificación de acreditación financiera**, ni `created` con pago confirmado.
4. Bajo transacción D-109…D-122, aplicar una vez evento financiero, hold/recurso o incidencia de pago tardío. Duplicados/antiguos no degradan saldo/historial.
5. Outbox E-06 envía avisos basados en hecho confirmado; la respuesta al navegador no recibe secreto ni callback detallado.

**No emitir refund ni cancelación financiera por callback.** La cancelación de una order externa que no llegó a cobrarse requiere contrato específico del proveedor y análisis de efecto; el plugin V1 no utiliza automáticamente endpoints de cancelación/reembolso para compensar errores sin verificar pago y condiciones. Mantener guía administrativa externa de reintegro (D-54/D-66).

## 11. E08-M APROBADO: semántica HTTP, respuesta y errores

**E08-M APROBADO (D-161):** DTO versionados y respuesta de error común para frontend; `WP_REST_Response` y `WP_Error` con HTTP status correcto. **No retornar `200 OK` por errores de autorización/negocio ni usar HTML o mensaje del proveedor en respuesta pública**. Todo error tiene código estable `pc_*`, texto claro es-PE, `request_id` de correlación no sensible, `retryable` y `details` con campos seguros **solo cuando corresponde**. Formularios/operaciones idempotentes pueden recibir **202 Accepted** para ejecución pendiente en lugar de inventar éxito.

**Ejemplo de error conceptual propuesto:**
```json
{
  "code": "pc_quote_changed",
  "message": "El total cambió. Revisa la cotización antes de continuar.",
  "data": {"status": 409, "retryable": false, "request_id": "opaco", "details": {"current_quote_required": true}}
}
```

| HTTP | Código estable propuesto | Significado / conducta del cliente |
| --- | --- | --- |
| 400 | `pc_invalid_input` | JSON/forma/importe/tipo inválidos, no ocultar error como éxito |
| 401 | `pc_auth_required` | Sesión no válida; nunca revelar existencia de otro pedido |
| 403 | `pc_forbidden` | Usuario autenticado sin capability/recurso/campo o acción prohibida |
| 404 | `pc_not_found` | Entidad ausente o **no visible para actor** sin revelar otra tienda |
| 409 | `pc_quote_changed` | Precio/entrega/slot cambiaron; comprador debe aceptar nuevo total |
| 409 | `pc_stock_unavailable` / `pc_slot_unavailable` | Recurso agotado u ocupado tras validación/lock |
| 409 | `pc_state_conflict` | Transición/versión ya cambió, no sobrescribir ni repetir efecto |
| 409 | `pc_idempotency_conflict` | Reuso de clave con payload diferente, nunca duplicar cobro |
| 410 | `pc_link_expired` | Enlace de seguimiento/descarga caducado; recuperar sin perder derecho |
| 422 | `pc_business_ineligible` | Política legal/comercial impide acción ordinaria; presentar trámite correcto |
| 423 | `pc_operation_in_progress` | Operación ya se ejecuta y no hay resultado; reconsultar misma referencia |
| 429 | `pc_rate_limited` | Antiabuso/OTP/descargas; `Retry-After` si aplicable, sin enumeración |
| 500 | `pc_internal_error` | Error interno sin stack trace, SQL, URL privada ni datos sensibles |
| 503 | `pc_provider_unavailable` | MP/correo/servicio no disponible; no iniciar segundo cobro sin conciliar |
| 202 | `pc_pending_reconciliation` (respuesta informativa) | No confirma pago ni retención activa; frontend muestra seguimiento/recuperación |

**Los códigos `pc_*` están aprobados como catálogo conceptual E08-M (D-161)**; faltan schema final y verificación de comportamiento. No son los errores nativos de MP; el adaptador traduce estados y errores oficiales sin copiar mensajes peligrosos. `operation_id`, versiones y `request_id` deben permanecer vinculados a intentos persistentes D-115.

## 12. E08-N APROBADO: mutación administrativa y versionado

**E08-N APROBADO (D-162):** los endpoints admin R8-19…52 deben verificar capacidades propuestas de la sección 8 y hacer **compare-and-swap/versionado/locks** según E-05: `expected_version` de pedido/línea/agenda cuando corresponda, operación UUID idempotente, motivo/evidencia, backend valida transición E-01 y actor E-02. Nunca exponer un método `set_status` libre que acepte pasar `FINALIZADO` sin validar líneas y pendientes D-76. La respuesta devuelta expresa estado actualizado, número/versión/evento y código de resultado, sin revelar PII extra.

**Casos no delegables al operador:** refund registrado, corrección de cash, aprobación de dirección si cambió costo/cobertura o dinero acreditado, configuraciones fiscales/secretos, Libro íntegro, exportación masiva y gestión de roles. El administrador tampoco puede ejecutar API de reintegro ni borrar originales.

**Controles de concurrencia:** cancelar línea requiere ajuste/restock/cupo atómicos; reprogramar asegura nuevo slot antes de soltar el anterior; pedido VENCIDO con pago tardío recupera solo con nueva disponibilidad y auditoría D-74. Ninguna ruta omite estas guardas por ser admin.

## 13. E08-O APROBADO: acceso a archivos digitales protegidos

**E08-O APROBADO (D-163):** archivo cargado/guardado en **almacenamiento no accesible directamente por HTTP** (idealmente fuera de webroot; si hosting compartido no lo permite, prohibir servir archivos hasta disponer de un contenedor privado equivalente **verificado**). El endpoint de descarga aplica derecho/pago/token 24 h D-40/D-140 y cabeceras `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff` y restricciones de referrer; el backend **transmite** el contenido, sin adjuntar archivos ni revelar rutas físicas/archivos en Media Library pública.

- Enlace digital independiente del seguimiento y de login WordPress; verificador criptográfico protegido/rotación y cuota de descargas **solo si el comercio la configuró**.
- Proteger contra path traversal, symlinks, URL de `uploads` pública, listados de carpetas, CDN, referer y caches LiteSpeed; sin URL con token en logs/analytics.
- Definir límite máximo por archivo, tipo MIME permitido, streaming eficiente/chunks y comportamiento ante Range/reanudación **solo tras verificar hosting P0-19**. PHP compartido puede tener límites de memoria/ejecución; no declarar soporte de archivos grandes sin pruebas autorizadas.
- Si el hosting no garantiza protección y streaming sin saltarse autorización, **deshabilitar venta/descarga digital en esa instalación e informar al administrador**, no degradar silenciosamente a URL pública.

## 14. E08-P APROBADO: reclamaciones, ARCO y comunicaciones

**E08-P APROBADO (D-164):**
- Libro nativo: la ruta pública R8-15 acepta reclamo válido sin compra/cuenta, genera constancia conforme al contrato legal J-01/P0-25, sin exponer expedientes de terceros ni usar un checkout como requisito. Administrador R8-41/42 gestiona y conserva prueba de respuesta; operador solo alerta mínima D-84.
- Derechos ARCO: reutilizar **exporter/eraser de WordPress** para compradores con email y flujo admin verificado para físicos solo teléfono D-145; no ofrecer un endpoint público de borrado por teléfono o pedido. Retención legal D-146, anonimizaciones/archivo, backups y respuestas quedan en J-04/J-05/P0-26.
- Notificaciones: outbox E-05/E-06 y `wp_mail()` con estados transporte; aceptar técnicamente no equivale a recibido; no pedir SMTP ni mensajería de terceros para permitir checkout.
- Geolocalización: Leaflet/proveedor de tiles solo por elección informada del comprador, dirección manual alternativa, cobertura por distrito UBIGEO definida por servidor; campos/atribuciones/terceros P0-21 pendientes.

**ANTECEDENTE HISTÓRICO LEG-04/05 ANTERIOR A D-189/D-198 (no vigente):** R8-41 y R8-43 se mantienen como listados de proyecciones mínimas; R8-42 y R8-44 siguen siendo las operaciones de **respuesta/resolución de expediente** en E-08, sin sustituir los flujos WordPress Privacy aprobados en D-145 para exportar/suprimir datos de forma legítima. `docs/35-CONTRATOS-LEGALES-PERMISOS-DTO-P0-23-P0-25-V1.md` §3 conserva la alternativa original de **pantalla WordPress**. `docs/37-CONTRATO-PRECODIGO-SEGURIDAD-API-Y-EXPEDIENTES-V1.md` propone **dos GET individuales y privados R8-65/66** para el panel Angular D-13/D-42, con A + `perucommerce_complaints_manage` / `perucommerce_privacy_manage`, aislamiento de instalación, finalidad y no-store. La segunda opción es la **recomendada, NO ratificada**; no crear ruta, cambiar OpenAPI, consumir DTO de detalle o construir página por inferencia. La adopción de REST requeriría aprobación y actualización de E-08/OpenAPI; entretanto se conservan **63 REST/60 rutas/113 DTO**, y R8-41/43 siguen resumidos.

La **forma y campos jurídicos exactos** de Libro/ARCO no quedan aprobados al ratificar rutas; deben someterse a la matriz vigente P0-25, y si legalmente resulta incompatible con una propuesta, prima la norma.

**Puertas de fase (2026-11-17):** `docs/39-MATRIZ-DE-PUERTAS-ANTES-DE-CODIGO-Y-RELEASE-V1.md` distingue contrato previo al código, autorización de implementación y pruebas/release que no pueden existir sin fuente. No confundir aprobación de E-08 en política con endpoints funcionales ni habilitación MP.

**Integración de ciclo de vida P0-26 (2026-11-17):** `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md` fija preflight, actualización, barrera de escritura, restore y reconciliación de pagos con E-05/MP, sin endpoints REST nuevos. Un ZIP anterior no revierte DDL; R8-06/07 y mutaciones económicas permanecen bloqueadas mientras esquema/recursos externos estén sin conciliar. No hay versión operativa probada.

## 15. E08-Q/E08-R APROBADOS: resiliencia y documentación de contratos

**E08-Q APROBADO (D-165):** API de operaciones persistentes con `operation_id`, reintentos seguros y respuesta recuperable, inbox/outbox locales y conciliación de MP por worker/consulta/WP-Cron sin integridad dependiente de puntualidad cron. Diagnóstico administrador **no** ejecuta pagos ni tests de correo por defecto; únicamente informa configuración/alertas y recuperación autorizada. Limitar tamaño de payload, paginar listados, filtrar campos, cachear **solo catálogo público**, `no-store` para sesión/seguimiento/descargas/dinero/Libro. Nunca enviar credenciales, OTP, PII o tokens de invitado a registros, navegador tercero o plugins ajenos.

**E08-R APROBADO (D-166):** para pasar de «política E-08 aprobada» a contrato **físicamente implementable**, generar posteriormente una matriz de **cada** R8-01…R8-52 y PER-01…PER-56 con: método/ruta; esquema JSON `required/type/enum/limits`; actor/capability; propósito/identidad; estados/guardas E-01; idempotencia; versión/lock; campos de salida filtrados E-07; errores `pc_*`; auditoría; documento/evento de outbox; prueba de aceptación. Exigir catálogo de DTO OpenAPI o equivalente **estático, versionado y fuente de verdad**, sin montar servidor ni exponer documentación privada en endpoints públicos. Cambios incompatibles requieren namespace `v2` y migración explícita de consumidores.

**No confundir:** aprobar E08-R no autoriza crear código del plugin, compilar Angular, ejecutar MariaDB, consumir MP, generar cuentas o desplegar. La siguiente fase de esquema físico/contratos de datos requiere autorización específica y Códex sigue detenido hasta resolver los nueve P0 bloqueantes y marco jurídico.

## 16. Escenarios de aceptación documental E-08 — SIN EJECUCIÓN

| ID | Escenario | Resultado contractual propuesto |
| --- | --- | --- |
| I8-X01 | WordPress está instalado en subdirectorio | Angular recibe `rest_url()`, scripts desde URL plugin, sin rutas `/wp-json` hardcodeadas |
| I8-X02 | Página de catálogo sin carrito | PHP devuelve HTML indexable; no carga Angular globalmente |
| I8-X03 | Dos bloques/shortcodes en la misma página | No doble import/mapa/event listeners ni componentes globales duplicados |
| I8-X04 | Se abre checkout con producto/variante | Backend ofrece precio vigente PEN, no confía en precio JS |
| I8-X05 | Cita elegida con turno que se acaba de ocupar | Quote/checkout rechaza slot, sin reservarlo al verlo |
| I8-X06 | Precio cambia entre cotizar y pulsar pagar | 409 `pc_quote_changed`, muestra nuevo total y exige aceptación |
| I8-X07 | Doble clic MP mismo `operation_id` | Una orden/intent/hold persistidos o recuperación de resultado, no dos cobros |
| I8-X08 | Reutilización de idempotencia con payload diferente | 409 sin mutaciones adicionales |
| I8-X09 | Contraentrega válida con cita y físico | Stock/cita comprometen atómicamente, pago pendiente y seguimiento |
| I8-X10 | Contraentrega incluye digital imposible de cobrar en único evento presencial | Backend deniega medio conforme D-21/D-33 |
| I8-X11 | Físico puro con teléfono sin email y MP | Se omite `payer`; **validar aceptación en MP test antes de habilitar** |
| I8-X12 | Pedido de servicio/digital sin email | Backend solicita email aplicable, sin imponer teléfono ajeno a físicos |
| I8-X13 | MP recibe ítems + entrega | Total de ítems concuerda exactamente con total PEN; entrega no producto del catálogo |
| I8-X14 | MP rechaza ítem de entrega en Orders API | Checkout MP bloqueado/diagnosticado, no cargo ficticio ni diferencia silenciosa |
| I8-X15 | MP rechaza `PT15M` en ambiente/medio | No extender hold; no prometer sincronía; bloqueo de integración pendiente de decisión |
| I8-X16 | MP crea order y PHP pierde respuesta | Recuperar por correlación/idempotencia sin segundo POST a ciegas |
| I8-X17 | Orden externa creada, admisión local venció | No entregar URL checkout sin hold; conciliar orden externa, no sobreventa |
| I8-X18 | Página return indica `approved` en query | No acreditar pago hasta GET servidor y verificación |
| I8-X19 | Webhook MP sin firma válida | No registrar ni confirmar dinero, respuesta HTTP de rechazo |
| I8-X20 | Webhook con firma válida de otra cuenta/ambiente | Cuarentena/rechazo, no aplicar a otro comerciante |
| I8-X21 | MP reenvía misma notificación tres veces | Una inbox/una aplicación de efectos y una intención de aviso |
| I8-X22 | Webhook supera tiempo o falla BD inbox | No responder éxito ficticio; proveedor puede reintentar |
| I8-X23 | Order `processed` con pago no acreditado comprobablemente | No convertir automáticamente E-01 a APROBADO sin analizar transacción/fondos |
| I8-X24 | MP acredita luego de vencido hold y cupo ya tomado | Dinero verificado + incidencia manual D-74, nunca prometer cupo ni refund realizado |
| I8-X25 | Error MP 409/423/429/5xx | Mantener intento incierto y `Retry-After`/backoff, consultar antes de nuevo cargo |
| I8-X26 | URL `checkout_url` con dominio extraño | No redirigir a URL no permitida; incidente |
| I8-X27 | Admin intenta editar monto original de pedido | Denegado; solo ajustes inmutables D-59 |
| I8-X28 | Operador cancela con dinero históricamente acreditado | Denegado D-64 aunque saldo neto posterior sea cero |
| I8-X29 | Operador intenta registrar refund externo | Denegado; solo administrador y resultado comprobado |
| I8-X30 | Admin registra refund externo sin prueba | Denegado, no llamar API MP para devolver |
| I8-X31 | Doble refund externo mismo ID | Un movimiento contabilizado D-107/E-05 |
| I8-X32 | Operador intenta consultar expediente Libro por REST | 403/404 sin detalles privados |
| I8-X33 | Invitado cambia ID pedido usando su cookie | No obtiene otro pedido ni enumera IDs |
| I8-X34 | Enlace privado se comparte o vence a 90 días | Solo lectura mientras vigente; expirado exige recuperación, sin borrar derechos |
| I8-X35 | Token de seguimiento está en fragment URL | Primer GET no incluye token; canje HTTPS y limpieza de historial, sin analytics |
| I8-X36 | Invitado solicita cambio con solo seguimiento | Requiere OTP o revisión manual; no muta dirección automáticamente |
| I8-X37 | OTP correcto tras 11 minutos o sexto intento | No habilita acción; control de abuso sin enumeración |
| I8-X38 | Solo físico con teléfono necesita reemisión | Canal manual con verificación/admin/operador autorizado, sin SMS |
| I8-X39 | Token digital vigente pero pago no acreditado | Descarga denegada, no confiar en token solo |
| I8-X40 | Token digital vence tras 24 h | Enlace viejo denegado, derecho persiste/reemisión verificada |
| I8-X41 | Archivo digital se encuentra bajo uploads públicos | Bloquear operación; no permitir URL directa |
| I8-X42 | LiteSpeed cachea seguimiento | Respuesta privada no se sirve de caché compartida |
| I8-X43 | Angular envía nonce WP válido pero usuario no tiene capability | Operación denegada; nonce no autoriza |
| I8-X44 | Se usa sitio WordPress con login de operador en otro dominio | Same-origin propuesto; CORS externo no habilitado por defecto |
| I8-X45 | Cliente reclama sin cuenta ni pedido | Libro accesible y constancia legal tras validar requisitos P0-25 |
| I8-X46 | Cliente ARCO solo teléfono | Expediente administrativo verificado, sin exigir cuenta WordPress |
| I8-X47 | Se solicita borrar pedido con factura, reclamo o disputa | Evaluar retención legal; no borrar movimientos/snapshots automáticamente |
| I8-X48 | wp_mail responde TRUE | No afirmar entrega recibida ni mutar pago |
| I8-X49 | Se carga mapa sin elección y en vista con token | No cargar tercero ni filtrarle URL; geolocalización opt-in y UBIGEO manual |
| I8-X50 | Correo falla con pedido digital pagado | Derecho no se revierte; seguimiento y bandeja de incidencias disponibles |
| I8-X51 | Hosting no soporta streaming privado eficiente | Bloquear venta digital insegura, informar limitación técnica sin publicar archivo |
| I8-X52 | REST recibe campo extra `total_minor` o `is_paid` controlado por comprador | Validar/ignorar como no autoritativo; nunca aceptar estado de pago del cliente |
| I8-X53 | Componente Angular dispara dos `HttpClient` subscriptions mutantes | Idempotencia backend evita segunda operación, UI no provoca reenvío injustificado |
| I8-X54 | Página de API/diagnóstico revela tokens o stacktrace | Filtrar respuesta, registrar solo error seguro/ID de correlación |
| I8-X55 | Extensión de plugin cambia esquema de API a formato incompatible | No romper `v1` en producción; nueva versión/migración cuando proceda |
| I8-X56 | Instalación nueva no tiene credenciales MP | Métodos MP deshabilitados con diagnóstico admin; no ocultar causa ni pedir credenciales al comprador |
| I8-X57 | No hay permisos InnoDB/índices o faltó migración | No habilitar checkout/citas/cobros críticos con falsa atomicidad |
| I8-X58 | Se intenta guardar credencial MP en bundle/frontend o email | Prohibido, solo servidor y administración segura |
| I8-X59 | Recibo SUNAT o nota de crédito no emitidos externamente | REST no fabrica documento ni lo declara emitido |
| I8-X60 | Usuario intenta descargar comprobante fiscal de otro cliente | Denegado por recurso/campo; no exponer información ajena |

**Ningún I8-X representa ejecución de pruebas, integración real con MP, instalación WordPress, build Angular, seguridad demostrada ni análisis jurídico certificado.** La matriz cubre negativos y rutas; **todavía falta OpenAPI/DTO físico validado** si se aprueban las opciones.

## 17. Registro de aprobación E-08 — POLÍTICA DE INTEGRACIÓN CERRADA

**Aprobación expresa (2026-11-17):** el propietario confirmó **«Todas A en E-08»**. Las 18 opciones E08-A…R están registradas como **D-149…D-166**, con criterios de aceptación **A-165…A-182**. Las opciones B quedan descartadas. No se autorizó implementar, probar ni instalar.

| Opción | Decisión | Política A aprobada |
| --- | --- | --- |
| E08-A | D-149 | Namespace REST `peru-commerce/v1`, inventario R8-01…R8-52, métodos, schema y autorización por operación |
| E08-B | D-150 | Angular precompilado y cargado condicionalmente dentro del ZIP WordPress, same-origin, sin runtime adicional en hosting |
| E08-C | D-151 | Carrito temporal sin hold; cotización server-side `/public/quotes` y nueva validación al pagar |
| E08-D | D-152 | Rutas independientes MP/contraentrega, idempotencia duradera y resultados recuperables |
| E08-E | D-153 | Enlace privado con secreto en fragmento URL, intercambio HTTPS a cookie segura de sesión solo lectura |
| E08-F | D-154 | Empleados con login/cookie WordPress + `X-WP-Nonce` y capabilities específicas por operación/recurso/campo |
| E08-G | D-155 | OTP numérico D-138 para acciones sensibles por correo, verificación manual para solo teléfono y autorización por propósito |
| E08-H | D-156 | Checkout Pro mediante Orders API con cliente HTTP WordPress y credenciales propias del comerciante, sin SDK ni Preferences API |
| E08-I | D-157 | Omitir `payer` si físico no tiene email; **condicionado a compatibilidad verificada con cuenta MP peruana**, sin forzar correo |
| E08-J | D-158 | Ítems comerciales más línea no inventariable de entrega `Entrega` conciliada con total; **condicionado a aceptación real MP** |
| E08-K | D-159 | `expiration_time="PT15M"` solo si MP lo acepta; reloj externo y hold local independientes, sin prolongación implícita |
| E08-L | D-160 | Webhook Orders con HMAC/inbox durable, deduplicación y GET a fuente autoritativa antes de acreditar |
| E08-M | D-161 | Códigos REST estables `pc_*`, HTTP correcto, error seguro y `202` para reconciliación pendiente; DTO versionados |
| E08-N | D-162 | Inventario administrativo R8-19…R8-52 y capabilities `perucommerce_*` por recurso/campo/versión y auditoría |
| E08-O | D-163 | Archivos fuera de acceso HTTP directo y streaming PHP autorizado; deshabilitar descarga insegura si host no lo garantiza |
| E08-P | D-164 | Libro/ARCO en rutas separadas, WordPress Privacy y canal manual verificado por teléfono, con constancias y permisos |
| E08-Q | D-165 | API idempotente e inbox/outbox local, diagnóstico admin, caché solo pública, paginación y antiabuso sin infraestructura extra |
| E08-R | D-166 | Matriz R8↔PER con contratos OpenAPI/DTO estáticos versionados, estados/permisos/errores y criterios antes de escribir código |

**Condiciones que forman parte de la aprobación:** E08-I/J/K son elecciones **condicionadas** a que la cuenta, medio, país y entorno de Mercado Pago realmente acepten omisión de `payer`, ítem de entrega y duración `PT15M`. Si falla una condición, **no habilitar MP y no pasar silenciosamente a la alternativa B**; se necesita nueva decisión específica sin alterar D-24, D-36 o D-104. E08-O obliga a bloquear descargas inseguras si el alojamiento no asegura almacenamiento privado. E08-P no certifica el Libro ni ARCO. E08-R exige completar después la matriz física R8/PER/DTO/OpenAPI, pero **no autoriza código**.

**Inventario histórico base E08-A…R aprobado en diseño (antes de D-176…D-187), todavía no implementado:** 52 entradas R8 (R8-18 es página WP, no REST), 18 familias de capacidades candidatas, 60 escenarios I8-X **no ejecutados**. Los nombres están elegidos como dirección de diseño, pero su contrato definitivo exige métodos, tipos/limits, permisos exactos por operación, recursos, mensajes, auditoría y errores antes de producción.

## 18. Bloqueos que seguirán abiertos aunque se aprueben E08-A…R

| ID | Entregable faltante específico | Etapa dependiente |
| --- | --- | --- |
| P0-17/J-03/P0-25 | Impuestos, redondeo fiscal, boleta/factura/recibo externo, nota de crédito y delivery por régimen/actividad | Validación normativa vigente antes de publicación |
| P0-18 | Esquema ER/SQL de WordPress/tablas InnoDB, índices, locks, migraciones, backup, rollback y pruebas de restauración | Diseño físico autorizado |
| P0-19 | Versiones estables exactas WP/PHP/Angular/MySQL/MariaDB, hosting/SSL, correo, streaming de archivos, límites y políticas de compatibilidad | Investigación oficial y verificación de instalación |
| P0-20 | Manifest/build/asset strategy Angular en ZIP, inyección en bloques/panel, aislamiento CSS, versión estable, SEO/rendimiento | Diseño técnico frontend autorizado |
| P0-21 | Proveedor Leaflet tiles/geocoding/costos/atribuciones/privacidad reales, dataset UBIGEO validado y fallback | Selección de proveedor/condiciones |
| P0-23 | Capabilities/DTO aprobados en E-08, pero todavía requiere prueba real de IDOR, CSRF, filtrado de campos, webhooks, rate limits y roles | Seguridad autorizada |
| P0-24 | Licencia y distribución de plugin gratuito/privado con dependencias, avisos y derechos | Decisión comercial y legal independiente |
| P0-25/J-01…J-05 | Ley 29571, DL 1729 y reglamentación, Libro, garantías, SUNAT, ANPD, plazos de ARCO/retención e incidentes, transferencias | Matriz jurídica por comerciante y tipo de operación |
| P0-26 | Actualización ZIP, activación, desinstalación sin borrar historia, migraciones, retención/backups, rollback y restauración | Contrato de ciclo de vida autorizado |
| MP-VALIDAR | Cuenta peruana/credenciales, payload `payer` ausente, ítem entrega, `PT15M`, `checkout_url`, webhook real y discrepancias sin reembolso | Pruebas MP **solo con permiso explícito** |
| SEG-VALIDAR | URL fragment/session/cookie, envío de email/OTP, token digital 24h, caché LiteSpeed y streaming privado en host soportado | Pruebas seguridad **solo con permiso explícito** |
| UX-VALIDAR | Diseño Figma por tipo de flujo, mensajes/errores, acceso móvil/escritorio y accesibilidad WCAG 2.2 AA | Diseño y comprobación independiente |

**Estado de salida original E-08 APROBADO EN POLÍTICA (D-149…D-166), EXTENDIDO con C23 D-176…D-187.** Quedan abiertos nueve P0, J-01…J-05, el OpenAPI/DTO y esquema físico, versiones y compatibilidad de MP/hosting, diseño UX y autorización de código. Los 60 I8-X son casos documentales **no ejecutados**. No declarar listo para Codex ni habilitar pagos/descargas sin los contratos y pruebas autorizados. **Fase 0: documentación sin implementación.**


## Rectificación de siete recorridos API/consumidor (2026-11-18; fuente física OpenAPI)

Los cambios **solo corrigen DTO y persistencia dentro de los R8 existentes**, sin nuevos `operationId`, rutas, permisos, estados E-01, campos públicos de stock, servicios ni nuevas tablas:

| Recorrido | Contrato corregido | Regla de implementación |
| --- | --- | --- |
| MP pre-HTTP | R8-06 ↔ `pc_idempotency` (docs/18/23) | Guardar solicitud exacta y clave/cuenta/ambiente/correlación antes del HTTP. No crear pedido/intento E-01 ni iniciar hold antes de order válida, no repetir cobro ambiguo. |
| Webhook Orders | R8-17 `WebhookNotice.data` con `id` obligatorio y campos adicionales de proveedor tolerados | HMAC y límites de payload/inbox antes de ACK; consultar orden oficial. `data.status` o `processed` no acredita fondos. Referencia oficial: https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/notifications |
| Seguimiento inicial | R8-06/07 `tracking_url_once` + `tracking_link_issued` | Primera entrega en respuesta HTTPS no-store con fragmento, nunca en `checkout_url` externo o replay. Pérdida exige verificación R8-58, no extraer secreto persistido. |
| Condiciones del checkout | R8-05 `QuoteResponse.policy_version/privacy_version/policy_notice/privacy_notice` | Mostrar textos/versiones antes de confirmar; R8-06/07 validan `CheckoutRequest.policy_version/privacy_version` y fingerprint. Cambios → nueva confirmación, sin datos inventados. |
| Control de versión | R8-36 `AgendaItem.row_version` y `AgendaView.weekly_schedule`; R8-39 `NotificationEntry.row_version`; R8-29 `ReconciliationSummary.row_version` | Las rutas R8-38/53, R8-40 y R8-30 consumen `expected_version` emitido en su lectura y devuelven 409 al modificarse concurrentemente. R8-53 usa `WeeklyWindow`: ISO lunes 1…domingo 7 → SQL domingo 0…sábado 6; ventanas/buffers/cruce de medianoche validados por backend. |
| Catálogo, servicios y finanzas | R8-32/33 `ProductVariantCreate/Update`, `AdminVariant/AdminProduct`, duración/buffers/stock opcional; R8-21 `AdminOrder.financial_movements/external_documents` | El panel puede crear, recuperar y editar borradores/variantes sin ID inventado. El stock detallado y los asientos/documentos son solo proyección por permiso; operador jamás recibe historial financiero reservado ni documentos fiscales completos. R8-35 lleva ajustes de stock. |
| Digital inicial | R8-48 `sellable_id` y `slot_key`, `DigitalFileMetadata` tipado | Comprobar pertenencia a producto digital, asignar archivo privado inicial a ese slot de variante; si existe, R8-63 hace reemplazo versionado. Derechos vendidos no cambian de `file_id`. |

Los contratos antiguos que describen solicitudes sin estos campos son una **fotografía previa de la misma superficie**, no otra autorización. Se conserva R8-18 como página WordPress, PER-09 excluida, R8-27 solo registro de reintegro externo verificado y las condiciones de compatibilidad MP Perú pendientes para habilitación. Ninguna ruta está implementada ni probada.

## Cierre histórico de consumidores y permisos en 0.7.0-draft (18-11-2026)
1. Las listas `x-field-exposure` de R8-01/05/06/07/21/32/33/34/36/45/46/48 y respuestas C23 R8-55/56/57/61/63 están alineadas con los DTO: `denyUnknownInput` no puede rechazar campos válidos. La lista es un máximo de representación, nunca autorización efectiva para O.
2. R8-45 lee `policy_notice/privacy_notice` y sus versiones; R8-46 guarda nuevos textos mediante CAS de settings e inserción de versiones inmutables `pc_policy_versions`. R8-01 publica ambos antes de recopilar email/teléfono, R8-05 cotiza con ellos y R8-06/07 valida versiones/fingerprint; un cambio exige nueva confirmación. `privacy_url` es enlace opcional, no la fuente del texto informado. Si faltan textos/versiones, no ofrecer checkout.
3. R8-21 devuelve `AdminOrderLine.row_version` para mutaciones de línea (R8-22/23/55), `digital_rights` para R8-59/60 y `cases` para R8-56/57/61 bajo **permisos extra SOLO A**. Las respuestas C23 de caso devuelven su `row_version`. R8-31/32/33/34 permite a A con `catalog_edit` Y `digital_files_manage` recuperar `digital_assignments` de artículo simple o variante, incluyendo archivo activo/versión de asignación, aun después de reabrir el editor. R8-48/63 devuelven `assignment_row_version`; R8-63 requiere que el ID de archivo de la ruta sea el activo y CAS del slot, sin mutar derechos históricos.
4. R8-39 devuelve `resource_type/resource_id` solo para recursos visibles al actor, evitando que O navegue desde una alerta a expediente jurídico o financiero. No usar URL secreta de archivo o token en navegación.
5. R8-29 representa conciliaciones MP previas al pedido con `source_operation_id` de `pc_idempotency.id`, sin `order_id` ficticio. R8-30 opera sobre `case_id/row_version`, verifica estado real/proveedor y no repite cobro ni revive hold.

**Límites invariantes:** 67 REST/64 rutas, 56 PER, 39 tablas lógicas, sin refund API, sin permiso O adicional. Todo permanece documentado, no implementado ni probado.

## Cierre preciso de recorridos — OpenAPI 0.8.0-draft (18-11-2026)

**Casos comerciales recuperables (R8-21, sin ruta nueva):** la lectura ordinaria sigue devolviendo el pedido y resúmenes `AdminCaseSummary`, pero no puede bastar para que A evalúe una devolución o cambio de dirección. Con `GET /admin/orders/{id}?case_id=...` el servidor devuelve `AdminOrder.case_detail` solo para **A** con `perucommerce_orders_read` Y capability específica del `case_type`, perteneciente al mismo pedido y sitio. `case_events_page` requiere `case_id`; eventos en páginas de 50, orden por hora+ID, desde `pc_case_events`. `AdminCaseOriginalRequest` contiene motivo, destino solicitado y relato originales recuperados del snapshot protegido de `pc_cases` o del primer evento, NO de valores actuales ni del último evento. Si faltan datos indispensables para resolver, no completar la operación sin reconstrucción verificable; se mantiene el expediente para revisión. Todo acceso A requiere finalidad, no-store y auditoría; O nunca recibe case_detail/cases.

**Condiciones históricas (R8-09/21):** `OrderAgreedNotices` contiene `policy_version/privacy_version` y textos correspondientes a `pc_orders.policy_version_id/privacy_version_id`, leídos de `pc_policy_versions` inmutable. No devolver condiciones de settings actuales en lugar de las originales. El invitado solo recibe los textos públicos de SU pedido con sesión de lectura válida; empleados A/O solo del pedido que puedan leer, sin privilegio para expedientes. No crear GET público por número de pedido.

**Cuota digital opcional D-32:** `AdminSettings/SettingsPatch.digital_download_limit` es `null` (sin límite) o un entero positivo (1…100000) por COMERCIO. Campo omitido en PATCH = conservar, null = eliminar cuota SOLO para derechos creados después. Al otorgar derecho nuevo, `pc_download_rights.optional_limit` conserva snapshot; cambios administrativos no retocan `download_count` ni derechos vendidos. `AdminDigitalRight` lee el límite histórico y contador solo para A. R8-14 verifica bajo lock el derecho, pago, token y cuota y contabiliza **transferencia autorizada que puede iniciar** antes de soltar SQL y abrir streaming, sin contar solicitudes rechazadas; una transferencia iniciada puede consumir uso incluso si el cliente desconecta después. El token expira a 24h sin extinguir el derecho. No admitir venta de digital sin protección real de binarios.

**Mapa configurable P0-21:** R8-45/46 transporta `map_tiles` con `enabled`, plantilla de tiles HTTPS, atribución visible y enlace de licencia. Es una modificación versionada CAS dentro de settings; solo A, sin API adicional, datos secretos, rutas CDN del plugin ni geocoding. Se valida origen permitido, placeholders Leaflet, referencia, licencia, cabeceras/CSP y condiciones de proveedor. R8-01 publica solo la configuración inocua **cuando esté habilitada y segura**; recibir JSON no dispara carga de tiles ni geolocalización: Angular carga Leaflet/teselas tras petición explícita de mapa en superficie sin secreto. `enabled:false` desactiva SOLO mapa, no cobertura UBIGEO ni dirección manual.

**Solicitud invitada R8-12:** `GuestActionRequest.idempotency_key` de 16…128 obligatoria, aleatoria por operación lógica y persistida en `pc_idempotency` con scope por instalación/pedido/sesión/propósito y hash canónico de payload. En única transacción SQL registrar caso y primer evento/snapshot, idempotencia y consumo de grant ligado a sesión (5m/un uso). Recuperar respuesta por misma clave/payload **antes** de rechazar grant ya consumido, pero solo tras verificar sesión y misma identidad de operación; devuelve 202 y el MISMO `case_id`, sin duplicación. Clave igual/payload distinto: 409. Clave nueva con grant consumido: denegar. Si se revocó la sesión, NO devolver el caso. `CaseAccepted.case_id` es obligatorio en 202; no ejecuta la solicitud automáticamente ni devuelve el token/OTP/clave. Actor invitado escribe `pc_case_events.actor_kind='guest'` y `actor_user_id=NULL`, no un WP user ficticio.

**Relación R8-63:** el campo de entrada es `C2311Request.expected_version` alimentado por `AdminDigitalSlot.assignment_row_version`, no hay un campo `assignment_row_version` adicional en el body de reemplazo. La respuesta `C2311Response.assignment_row_version` ofrece la versión nueva. Solo A, sin reemplazar archivos vendidos.

## Antecedente contractual DOC-QA22…26 — 18-11-2026

**Corte histórico de DOC-QA22…26: OpenAPI 0.9.0-draft (67 REST, 64 rutas y 134 esquemas).** Sus reglas se conservan con las precisiones DOC-QA27…29 del contrato vigente 0.10.0-draft; ningún corte anterior es una fuente alternativa. D-01…D-198, 56 PER, 39 tablas lógicas, límites de rol y exclusiones V1 permanecen intactos. Sin código ni comprobación en entorno real.

- **R8-26 / ajuste parcial por expediente:** si se disminuyen unidades de una línea, `FinancialAdjustment` requiere `affected_units` (positivo), `unit_effect=release_unfulfilled_units`, `line_id` y `case_id`. No modificar snapshot de venta; `pc_order_adjustments` conserva `delta_cents`/motivo/caso/idempotencia y la cantidad afectada en evento protegido, asociado con `pc_case_events`; dentro de la misma transacción se incrementa `pc_stock_commitments.released_units` y se revalida `committed_units - fulfilled_units - released_units` bajo locks. No liberar dos veces, no restock de físico ya entregado, no reembolso automático; saldo separado del ajuste.
- **R8-30 / pago tardío y primer turno:** conciliación de un pago efectivamente acreditado con `resolution=reassign_resources` exige `order_id` existente/vinculado. Si perdió franja y se requiere servicio, exige `new_service_start_at_utc`, `new_service_end_at_utc` y `buyer_agreement_evidence`; el backend comprueba acuerdo, importe, estado del proveedor, unicidad por pedido y recurso/día, y crea la PRIMERA cita `pc_appointments` junto con nueva asignación. No usar R8-38 sin appointment existente, revivir hold ni cobrar por segunda vez. Sin franja validada, incidencia abierta y ningún pedido confirmado ficticio.
- **R8-45/46 / proveedor del comercio:** `PublicBusinessSettings` puede transportar razón social `legal_name`, `tax_id_ruc` condicionado y dirección de local `premises_address`; `AdminSettings/SettingsPatch.mail_sender` configura nombre, correo y firma transaccionales, no credenciales SMTP. Persisten en el CAS/versionado de opciones WP; el Libro R8-15 usa identidad legal y dirección **congeladas** al ingresar reclamación y solo exige campos propios del formulario jurídico, jamás DNI/correo de forma universal al checkout. Agenda y avisos de servicio muestran dirección real del único local si está configurada; envío técnico no demuestra recepción.
- **R8-50/66/68 / exportación ARCO:** `privacy_request_id` y `expected_version` vinculan cada exportación a `pc_privacy_requests` e identidad verificada; `scope=order_legal_copy` obliga `order_id` del mismo titular/instalación. `PrivacyExportAck` da operación y expediente; el estado se consulta desde `PrivacyRequestDetail.export_operations` por R8-66. El artefacto terminado se custodia mediante `evidence_manifest/result_reference` protegido del mismo expediente (sin nueva tabla); solo R8-68 A puede transmitir el archivo por evidencia con finalidad/no-store y expiración. No API pública de exportación ni PER-09 comercial.
- **R8-36/37 / excepción de calendario:** cada cierre expone `closure_id`, `row_version`, `active`. La misma R8-37 recibe `action=create|update|retire`; update y retire requieren ID+versión y create/update requieren instantes. Con locks de todos los días afectados, comprobar solapamientos, pertenencia de cierre al recurso, disponibilidad y citas; retirar se audita sin borrar el cierre de `pc_schedule_blocks` ni cambiar citas automáticamente. R8-53 sigue gestionando solo el horario semanal versionado.

**Precedencia histórica del corte DOC-QA22…29:** el OpenAPI 0.10.0-draft define propiedades y reglas condicionales, `docs/42` su motivación, y este anexo su consumo por la capa pertinente. P-A permanece cerrado en diseño, P-B necesita autorización específica para implementar y P-C/P-D siguen sin pruebas, compilación, migraciones, MP, hosting, conformidad jurídica, ZIP o release.

## Contratos integrados DOC-QA27…29 — OpenAPI 0.10.0-draft

- **R8-09/21 → R8-22/23/26:** `quantity` conserva el original. `physical_quantities` es obligatorio solo en líneas físicas: `committed_units`, `released_units`, `stock_out_units`, `pending_stock_out_units`, enteros 0..100000; pendiente = comprometido − liberado − salido, sin confundir salida con entrega al domicilio. Los contadores son operativos de esa línea, no inventario global ni facultades financieras. Ceros sin compromiso solo antes de primera confirmación; ausencia inesperada tras confirmación es inconsistencia. Se mantiene la proyección con stock opcional y se leen estado/contadores/versión coherentes. Toda mutación de contadores incrementa también `pc_order_lines.row_version`; una acción con versión anterior recibe 409 y recarga. R8-26 admite reducción estrictamente parcial (menor que el remanente); liberar todo el remanente usa R8-23 con sus guardas existentes. R8-22 usa el compromiso actual para la primera salida; ENTREGADO después de DESPACHADO no vuelve a descontar.
- **R8-45/46 y R8-40:** R8-45/46 admite/devuelve `mail_sender` y `routine_buyer_emails_enabled`, solo A con settings_manage. La preferencia siempre se lee (true inicial); en PATCH, omitir conserva y booleano sustituye. Afecta solo nuevos emails rutinarios físicos PREPARANDO/LISTO/DESPACHADO/ENTREGADO; la decisión por evento y versión de configuración persiste incluso al omitir EMAIL, sin recalcular en replay. Cola/reenvíos existentes conservan decisión; reactivar no reconstruye avisos históricos. WEB/PANEL, historial, pagos, incidencias, ajustes, servicios, digitales y avisos legales/esenciales permanecen. Cambiar remitente/preferencia no crea una versión de textos legales. R8-40 solo reenvía intención EMAIL existente y autorizada; una omisión por preferencia no crea derecho de reenvío.
- **R8-66 → R8-68:** `export_operations` figura tanto en PrivacyRequestDetail como en `x-field-exposure.response`. Operación, estado y referencia privada del mismo expediente permiten recuperar el resultado R8-50, siempre con A, capability, instalación, caso y finalidad.
- **Coherencia obligatoria:** los campos superiores declarados en solicitudes/respuestas deben coincidir con las listas de exposición; objetos anidados siguen su schema cerrado. La lista no concede autorización ni elimina guardas por rol/campo. Sin nuevas rutas, roles, tablas o estados E-01.
