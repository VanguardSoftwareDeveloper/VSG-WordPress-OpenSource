# Producto y alcance de V1 — PeruCommerce

**Adenda posterior de 2026-11-22:** se aprobó incorporar un POS integrado a PeruCommerce y destinar su código a un repositorio público independiente antes de la distribución. Alcance POS técnico y pendientes en [docs/47](47-POS-INTEGRADO-Y-APERTURA-PUBLICA.md). La V1 previa no incluía POS; las 71 fases cerradas no acreditan esa funcionalidad. Esta adenda no autoriza por sí sola compilar, ejecutar pruebas, instalar, generar ZIP ni hacer público el monorepositorio.

**Estado:** borrador sujeto al cierre de decisiones. Los requisitos marcados «confirmado» sí han sido aprobados.

## Problema y destinatarios

Plugin WordPress gratuito para pequeños comercios de Perú que desean vender desde su web sin la complejidad de una solución de comercio electrónico generalista. El comerciante configura su tienda en WordPress; el comprador puede completar la compra sin crear una cuenta.

**Principios:** pocos pasos, móvil prioritario, configuración comprensible, soberanía de datos en el alojamiento WordPress, sin dependencia de WooCommerce ni SaaS propio obligatorio, operación adaptada a Perú.

## Alcance confirmado V1

| Área | Alcance |
| --- | --- |
| Instalación | Plugin instalable/actualizable mediante ZIP de WordPress |
| Tienda | Un comercio, un local principal |
| Catálogo | Productos físicos, digitales descargables y servicios presenciales con reservas (una agenda/recurso, una cita por pedido) |
| Variaciones | Variantes básicas de productos |
| Inventario | Opcional y por variante vendible; retenciones 15 minutos desde inicio del intento de pago sin renovación automática |
| Carrito | Mixto, con un cobro por pedido; efectivo solo si todo se cobra en un mismo evento presencial |
| Compra | Checkout dedicado, pocas pantallas, sin cuenta obligatoria |
| Pagos | Mercado Pago para todos los tipos; efectivo solo si todos los artículos pueden cobrarse presencialmente |
| Entrega | Delivery por distritos UBIGEO y tarifa por distrito, o recojo en tienda; mapa de ubicación |
| Integración WP | Bloques y shortcodes para catálogo |
| Administración | Panel Figma integrado en WordPress; administrador y operador limitado; gestión manual auditada de citas |
| Normativa | Datos de boleta/factura/recibo por honorarios solo según régimen y operación, con emisión externa; Libro de Reclamaciones nativo o enlace conforme |
| Diseño | Composiciones separadas móvil/escritorio, Figma antes de codificar |
| Distribución | Gratuita, código GPL-2.0-or-later; futura fuente pública en repositorio independiente; actualización integrada futura, no V1 |

## Integración E-08 — APROBADA EN POLÍTICA (D-149…D-166)

El documento `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` **aprueba E08-A…R**: namespace REST versionado `peru-commerce/v1`, Angular precompilado/condicional dentro de WordPress, cotización y checkout MP/COD con idempotencia, sesión de invitado solo lectura, OTP para cambios sensibles, capacidades administrativas por recurso/campo, Orders API de MP, webhooks firmados, errores seguros y archivos digitales protegidos. Comprende **52 entradas R8 (una página WP no REST)** y 60 I8-X documentales, **no pruebas**.

**PENDIENTES:** formalizar OpenAPI/DTO y PER↔R8, SQL/hosting, matriz legal, versiones y pruebas autorizadas. En particular MP físico sin `payer`, ítem `Entrega` y `PT15M` dependen de compatibilidad real; no imponer correo a físicos ni sustituir la opción aprobada a ciegas. El catálogo HTML indexable continúa bajo PHP y Angular no determina permisos ni precios.

## Privacidad e identidad del comprador — E-07 APROBADO (D-135…D-148)

El contrato `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` **APRUEBA E07-A…N (D-135…D-148)**: mínimo dato por carrito/rol, seguimiento privado de 256 bits y 90 días, verificación OTP 8 dígitos/10 min/5 intentos para cambios sensibles con email, recuperación manual con teléfono, descargas 24 h independientes, WordPress/capabilities, WordPress Privacy, mapas voluntarios, retención por categoría y respuesta a incidentes. Los 42 casos P7-X **no son pruebas ejecutadas**. J-04/J-05/P0-25 y E-08 siguen abiertos.

**Reglas aprobadas que no se alteran:** comprar sin cuenta, físico puro con teléfono sin email, digital/servicio con correo, seguimiento de pedido propio en lectura solamente, operaciones sensibles tras verificación adicional D-85, exportaciones/Libro reservados al administrador y evidencia comercial inmutable. **No implementar plazos de token ni OTP propuestos sin aprobación**. El trámite de inscripción de banco de datos ante ANPD se evalúa para el comerciante responsable, no es prerequisito para escribir el plugin.

## Comunicaciones transaccionales — E-06 APROBADO EN POLÍTICA (D-123…D-134)

El contrato `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` **APRUEBA E06-A…L (D-123…D-134)**: 26 eventos N6, confirmación/seguimiento WEB, correo WordPress cuando existe, bandeja interna, reintentos por outbox, avisos de cita y reclamos, mínimos datos, plantillas es-PE y contacto telefónico manual. Los 38 casos N6-X son documentales, no ejecutados. Solo físicos: teléfono sin correo obligatorio; digitales/servicios: correo exigido como contacto.

**Ya aprobado:** outbox E-05, seguimiento privado y política de comunicaciones E-06, y **privacidad E-07 D-135…D-148**. **Pendientes E-08/P0-19/J-04/J-05/P0-25:** controles REST/identidad efectivos, transporte/recepción, campos de respuesta, calendarios legales y conservación. Fallo de correo no revierte cobro o pedido.

## Disponibilidad real y recuperaciones — E-05 APROBADO COMO POLÍTICA TÉCNICA (D-109…D-122)

El contrato `18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md` está **APROBADO en política técnica D-109…D-122**: InnoDB en datos transaccionales críticos, lock por variante/recurso-día, intento MP coordinado con admisión interna breve y hold válido de 15 min, idempotencia, webhook firmado/inbox, outbox y conciliación de fallos. Los 48 escenarios C5-X siguen **no ejecutados**; P0-18/P0-19/P0-26 y E-08 aún deben definir esquema, host, migración y proveedor real.

En V1, la selección en catálogo/carrito no debe prometer stock/horario reservado; un regreso visual desde MP no acredita dinero; un vencimiento de 15 minutos no cancela la transacción externa; errores ambiguos exigen conciliación y no otro cobro a ciegas. Estas invariantes preceden al mecanismo técnico elegido.

## Cotización, delivery y conciliación monetaria — E-04 APROBADO (D-96…D-108)

**Política comercial y numérica aprobada:** `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md`, D-96…D-108. Montos internos en céntimos PEN; precios positivos de hasta 2 decimales y cantidades enteras; subtotales exactos; recotización aceptada antes de crear intento; una modalidad física/delivery por pedido; corrección mediante ajustes históricos; cobro bruto MP separado de comisión; efectivo neto aplicado; tarifa más cara tras pago sin segundo cargo automático; movimientos financieros inmutables y documentos externos registrados cuando corresponda. Los 32 escenarios M4-X son documentales, **no pruebas**.

**Ya CONFIRMADO:** el vendedor introduce precio final PEN por producto simple o variante; oferta manual sin cupones; el comprador conoce total/cargos antes de pagar; un cobro inicial por pedido, original inmutable y reintegros solo externos comprobados registrados por administrador. **Pendientes J-03/P0-17/P0-25:** normas fiscales por régimen, redondeos tributarios y tratamiento documentario del delivery, notas de crédito y servicios. **E-05 aprobado como política técnica D-109…D-122; pendientes P0-18/P0-19/P0-26 y E-08:** esquema real de asientos, locks/índices, instalación, proveedor MP y validación de errores; no equivale a implementación operativa.

## Elegibilidad de operaciones E-03 — POLÍTICA FUNCIONAL APROBADA (D-86…D-95)

**E-03 aprobado D-86…D-95:** clasificación de solicitudes, condiciones históricas, físico elegible antes de despacho, devolución sin restock prematuro, derechos digitales, no-show, reprogramación y cancelación por línea completa. **E-04/E-05/E-06 también aprobados en sus políticas (D-96…D-134)**; pendientes legalidad específica J-01/J-02/J-03/P0-17/P0-25, privacidad E-07, API E-08, SQL/hosting y evidencia real. No imponer arrepentimiento universal ni negar reclamaciones.

## Operadores, datos y funciones administrativas — E-02 aprobado (D-78…D-85)

- **Operador:** accede a pedidos y contacto mínimo para atender o entregar; no consulta datos fiscales completos ni credenciales por defecto (D-78). Puede crear/editar productos, precios y ofertas y ocultar productos, sin borrar definitivamente artículos asociados a pedidos (D-79).
- **Inventario y agenda:** ajustables por operador con motivo y auditoría; puede marcar preparación/entrega y reprogramar citas con evidencia, capacidades y condiciones E-01, sin reembolsar ni cancelar con dinero acreditado (D-80/D-81).
- **Exportación y archivos:** datos masivos y archivos digitales protegidos reservados a administrador bajo política de privacidad; operador solo metadatos imprescindibles (D-82).
- **Dirección confirmada:** operador recibe/propondrá cambios; aprobación exclusivamente administrativa si cambian tarifas, cobertura o ya hay dinero acreditado; conservar original y validar nuevamente (D-83).
- **Configuración:** fiscalidad, credenciales, Libro de Reclamaciones, políticas y perfiles son responsabilidad exclusiva de administrador. Operador ve avisos operativos pertinentes, sin acceso a expediente completo. El comprador siempre conserva el canal para presentar reclamaciones (D-84).
- **Invitado:** enlace privado solo consulta su pedido; reemisión, cambio de dirección o devolución exigen verificación adicional de identidad (D-85).
- **PENDIENTE TÉCNICO:** capabilities de WordPress, campos exactos por perfil, protección de sesiones y endpoints, criterios legales E-07. Consultar `15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md`.

## Políticas confirmadas para casos límite (D-61…D-66)

- **Digital habilitado, sin descarga registrada:** no asumir que aún está pendiente de cumplimiento ni conceder cancelación/reintegro automáticos. El **administrador evalúa** la solicitud según condiciones comerciales y derechos legales vigentes (D-61).
- **Producto físico despachado, no entregado:** registrar **incidencia logística** y revisar antes de cancelar; no mostrar cancelación ordinaria inmediata (D-62).
- **Última línea física cancelada antes de despacho:** revisar el **delivery** cobrado, costos reales de prestación y derechos del consumidor; ajustar cuando corresponda. Si es pedido pagado con efecto financiero, autorización exclusiva de administrador (D-63).
- **Operador y cancelaciones:** únicamente sin dinero acreditado, sin obligación de reintegro/ajuste financiero, con línea elegible, capacidad expresa y auditoría. D-64 restringe las operaciones previamente permitidas por D-60.
- **Cambio de dirección tras confirmar:** guardar solicitud y modificación vigente mediante ajuste auditable, sin alterar el snapshot; validar de nuevo distrito/cobertura/tarifa y factibilidad logística antes de aplicar (D-65).
- **Devolución parcial:** se gestiona fuera del plugin y el administrador registra su **resultado comprobado** con importe/referencia, preservando pago original; no se ejecuta por API (D-66).
- **Aún pendientes:** evidencia y condiciones del digital, tramitación de logística, fórmula de delivery, estados de dirección, mecanismos de autorización y conciliación, derechos legales y prorrateo.

## Cancelaciones permitidas y preservación del pedido (D-58…D-60)

- **Cancelación ordinaria:** disponible para líneas cuyo cumplimiento sigue pendiente y cuya cancelación sea jurídicamente y operativamente aplicable. Artículos ya entregados, servicios prestados u otras prestaciones consumadas se tramitan por **devolución, reclamación o incidencia** según corresponda; nunca presentar una cancelación ordinaria como sustituto de esos procedimientos.
- **Pedido confirmado:** conservar de forma inmutable sus datos y snapshots originales. Cambios posteriores se reflejan en eventos/ajustes ligados al pedido, con actor, momento y motivo, sin sobrescribir historial.
- **Autorización:** cancelación de pedido pagado con ajuste financiero requiere **administrador** (D-60). D-64 restringe al operador a cancelaciones elegibles **sin dinero acreditado ni reintegro o ajuste**, con permiso y auditoría; no tiene capacidad de reembolsar.
- **Subcontratos pendientes:** definición exacta de línea «pendiente», permisos por operación, cómo se clasifica el impacto financiero, devoluciones/reclamaciones, ajuste de envío/tributos, reposición de stock, agenda y conciliación.

## Estados independientes, cancelación parcial y precio simple (D-55…D-57)

- **Estados:** conservar por separado el estado administrativo del pedido, los intentos y movimientos de pago, y el cumplimiento de cada línea física, digital o de servicio. Pago aprobado no equivale a entrega ni a servicio prestado.
- **Cancelación parcial:** permitir cancelar individualmente líneas **pendientes de cumplimiento** que sean elegibles de un pedido mixto sin cancelar las demás, con ajustes y auditoría (D-56/D-58). Si la operación requiere ajuste financiero en pedido pagado, solo administrador autoriza (D-60); eventual reembolso se realiza externamente y su resultado lo registra únicamente el administrador (D-54).
- **Precio de catálogo:** el artículo simple sin variantes usa un precio final y una oferta manual propios; los artículos con variantes requieren precios finales y ofertas explícitos por cada variante vendible, sin heredar automáticamente el importe principal.
- **E-01…E-07 aprobados en política (docs.14…20, D-67…D-148).** Pendientes detalles jurídicos J-01…J-05/P0-17/P0-25, capabilities/REST E-08, SQL/hosting/mapas/ZIP, y comprobación de correo/proveedor/seguridad. El cierre documental no equivale a funcionamiento.

## Nuevas reglas aprobadas por variante y correcciones financieras (D-52…D-54)

- **Variantes:** cada variante vendible puede definir su propio precio final y precio de oferta simple en PEN; la oferta se activa/desactiva manualmente para esa variante. El stock, selección y precio de variantes diferentes no se mezclan.
- **Correcciones de efectivo:** solo el administrador puede corregir cobros registrados; la corrección constituye un movimiento auditable, con preservación del asiento original. El operador puede registrar el cobro original autorizado, pero no corregirlo.
- **Reembolsos externos:** se tramitan en Mercado Pago u otro medio de origen que corresponda; el administrador registra después en PeruCommerce el resultado comprobado. No implementar API para ejecutar reembolsos desde V1 ni permitir al operador registrar su resultado.
- **PENDIENTE:** efectos de movimientos correctivos sobre saldo, reembolso parcial/total, campos de comprobación del proveedor, conciliación de montos, flujo de confirmación de auditoría y decisiones legales por caso.

## Precio final, oferta manual y registro de cobro (D-49…D-51)

- **Precio final:** el comerciante ingresa el importe final en PEN para cada producto o variante aplicable, sin formulario obligatorio de precio neto más impuestos. El sistema no infiere exenciones tributarias ni elimina datos/documentos exigibles al negocio.
- **Ofertas:** el precio de oferta simple puede activarse y desactivarse manualmente desde la administración autorizada; V1 no incluye fechas de inicio/fin programadas ni descuentos automáticos.
- **Efectivo:** el operador autorizado puede registrar cobros efectivamente recibidos mediante acción auditada en el panel. No puede realizar reembolsos ni cambiar credenciales. Los permisos exactos, identidad del actor, conciliación y corrección de registros siguen por especificar.

## Configuración comercial y compatibilidad confirmadas (D-45…D-48)

- **Moneda V1:** únicamente **PEN (sol peruano)**; importes en interfaz como **S/**, sin conversor ni opciones multimoneda.
- **Transparencia de precio:** mostrar al comprador el precio final y el total que corresponde a la operación, incluyendo el delivery y demás cargos aplicables antes de confirmar. El tratamiento tributario depende del régimen y tipo de operación del comercio; **no crear motor fiscal universal** ni asumir IGV idéntico para todos.
- **Permisos:** el operador limitado administra pedidos, productos, inventario y agenda bajo capacidades específicas; credenciales de pagos, claves y configuración sensible quedan exclusivamente bajo administrador.
- **Compatibilidad:** priorizar WordPress, PHP y dependencias **estables y mantenidas**, sin comprometer soportes antiguos. Fijar versiones mínimas concretas tras contrastar documentación oficial y requisitos de implementación; no hay versiones ni matriz de pruebas aprobadas por ahora.

## Arquitectura y mapas confirmados (D-42…D-44)

- Catálogo de bloques/shortcodes indexable renderizado por WordPress/PHP; carrito, checkout y panel interactivo implementados en Angular. La lógica crítica de negocio y su persistencia permanecen en PHP del plugin.
- Evaluar entidades y metadatos nativos de WordPress para el catálogo; diseñar tablas propias para pedidos, pagos y reservas. Aún no se aprueban modelos físicos, claves, índices, migraciones ni otras entidades.
- Mapas con Leaflet y proveedor cartográfico intercambiable. Cuando no haya mapa disponible, permitir dirección manual; el backend debe seguir validando distrito, cobertura y tarifa UBIGEO sin inventar una entrega atendible.
- Pendientes: versiones estables compatibles, diseño del puente/API, empaquetado de Angular, proveedor y costo de mapas, protección de datos geográficos y pruebas de rendimiento.

## Decisiones de operación confirmadas (D-36…D-41)

- **Pago Mercado Pago:** al pulsar «Pagar», el backend crea un intento válido y comienza la reserva de stock/cupo de 15 minutos, sin renovación automática. No reservar antes en la ficha o carrito.
- **Contraentrega:** al confirmar pedido válido, el stock y la cita se comprometen inmediatamente, sin esperar que el comerciante acepte; el estado de cobro permanece pendiente.
- **Servicios:** un solo cliente/cita por franja, con solapamientos de duración y buffers impedidos.
- **Cancelaciones:** cada comercio configura condiciones sujetas a derechos legales; no imponer 24 horas uniformes ni reembolsos automáticos.
- **Digitales:** enlaces de acceso a descargas válidos por 24 horas, con reemisión segura; el derecho de acceso y la cuota de descargas son conceptos independientes.
- **Precios:** precio regular y precio de oferta simple en V1. **Cupones y promociones automáticas quedan excluidos de V1**, sin afectar obligaciones fiscales ni reglas de precio final.

## Capacidades cuya forma exacta está PENDIENTE

- Catálogo: **precio final y oferta propios del producto simple o explícitos por variante (D-49/D-50/D-52/D-57), sin herencia automática**, PEN y total visible (D-45/D-46). Pendientes precisión/redondeo, régimen fiscal, validación de valores, visibilidad/borrado/categorías; sin cupones ni promociones automáticas V1.
- Variantes: **stock individual por variante CONFIRMADO (D-31)**; pendientes SKU, combinaciones, tarifas y casos sin control de stock.
- Digitales: **enlace temporal protegido de 24 horas, reemisión segura, sin cuota predeterminada y límites opcionales CONFIRMADOS (D-32/D-40)**; pendientes formatos/tamaño, mecanismo de reemisión, revocación y abuso.
- Servicios: **agenda semanal, duración, pausas/buffers, fechas bloqueadas, un cliente por franja y condiciones de cancelación configurables por comercio CONFIRMADOS (D-29/D-38/D-39)**; pendientes anticipación, huso horario, condiciones legales y no-show.
- Mixtos: **un cobro inicial por pedido y efectivo en un mismo evento presencial (D-33); cancelación parcial de líneas elegibles confirmada (D-56)**. Aprobada revisión del delivery ante cancelación de la última línea física (D-63) y reembolso externo parcial comprobado (D-66); pendientes lugar/momento del cobro, fórmulas, ajustes y conciliación.
- Inventario: **15 minutos desde pulsar «Pagar» y crear intento válido en servidor (D-27/D-36); contraentrega compromete stock/cita al confirmar pedido (D-37)**. Pendientes atomicidad, idempotencia, reintentos y caducidad efectiva.
- Delivery: **distritos UBIGEO con tarifa configurable confirmados**; pendientes zonas excluidas, dirección manual y validación de cobertura.
- Pagos: **estado de pagos independiente del estado administrativo y del cumplimiento de cada artículo (D-55)**; incidencia ante pago tardío y devolución independiente (D-28/D-30). Pendientes reglas por estados, plazos de devoluciones externas, contracargos, callbacks y reintentos.
- Comprobantes y libro: formatos, avisos, evidencia, conservación y datos del comercio.
- **Seguimiento por enlace privado de invitado CONFIRMADO (D-34)**; pendientes caducidad, revocación y asistencia telefónica segura.
- **Selección de cita en ficha con revalidación en checkout CONFIRMADA (D-35)**; pendientes mensajes y recuperación de cupos.
- **Operador gestiona pedidos, catálogo, stock, agenda y cobros efectivos con límites D-47/D-51/D-78…D-85;** solo administrador corrige cobros y registra reembolsos externos (D-53/D-54). Aprobada política de mínimo acceso, operación auditada y restricciones de exportación/archivos protegidos. Pendientes capacidades WordPress, campos exactos E-07, conciliación, notificaciones y retención.

No debe iniciarse implementación completa hasta definir lo anterior.

## Exclusiones propuestas V1 (aún no ratificadas)

- Marketplace/múltiples vendedores, múltiples sucursales y multisite como escenario comercial.
- Multicaja avanzada, facturación SUNAT automática, contabilidad y sincronización ERP. **POS integrado pasa a alcance futuro de V1 por decisión 2026-11-22**; sus contratos y pruebas aún están pendientes (ver docs/47).
- Seguimiento GPS en tiempo real, flotas y optimización automática de rutas.
- Suscripciones/pagos recurrentes, multimoneda y logística internacional.
- Creador de sitios/maquetador visual propio, IA integrada y dependencias SaaS del fabricante.
- Actualizador remoto del plugin; V1 usa ZIP manual.

## Dependencias de módulos: propuesta

| Módulo | Depende de | Efecto al desactivarlo |
| --- | --- | --- |
| Catálogo y pedidos | Núcleo | No se desactiva si se desea vender |
| Inventario | Productos físicos con stock | La venta deja de controlar existencias; historial intacto |
| Reservas | Servicios configurados | Debe impedir nuevas reservas sin borrar las antiguas |
| Digitales | Productos digitales configurados | Mantener derechos ya otorgados hasta resolver política |
| Delivery | Productos físicos elegibles | Ocultar envío y validar pedidos abiertos |
| Mapas | Delivery físico y ubicación de entrega, con Leaflet/proveedor intercambiable confirmado (D-44) | Dirección manual como alternativa; validación de cobertura del distrito indispensable |
| Mercado Pago | Cuenta y credenciales del comercio | No ofrecer pagos nuevos por dicho medio |
| Contraentrega | Modalidades físicamente cobrables | Ocultar para artículos incompatibles |
| Reclamaciones | Cumplimiento del comercio | No ocultar acceso exigible por normativa |

## Requisitos no funcionales propuestos

- **Criterio CONFIRMADO (D-48):** soportar versiones estables y mantenidas de WordPress/PHP/dependencias, sin compatibilidad antigua innecesaria. Versiones mínimas exactas, requisitos de base de datos y matriz de validación **pendientes de verificación oficial**. Arquitectura Angular + WordPress/PHP confirmada (D-42).
- Seguridad: CSRF según contexto WP, autorización por operación, validación de entradas, salida escapada, secretos solo servidor.
- Accesibilidad: navegación por teclado, etiquetas, mensajes de error y objetivos de accesibilidad por fijar.
- Rendimiento: interfaz pública indexable, assets bajo demanda, sin mapa/calendario en páginas que no los usan.
- Confiabilidad: reintentos idempotentes, recuperación de fallos externos, instalación y actualización sin pérdida de pedidos.
- **Moneda PEN única V1 CONFIRMADA (D-45), con precios finales transparentes (D-46)**; localización en español de Perú y S/. Pendiente especificar régimen fiscal aplicable, desgloses, redondeo y zona horaria.
