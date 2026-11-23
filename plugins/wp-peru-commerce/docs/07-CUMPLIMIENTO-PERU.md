# Cumplimiento peruano — referencia normativa para diseño

**Revisión de fuentes:** 2026-11-17 (actualización de comercio electrónico, reclamos y remedios). **Estado:** análisis de requisitos; NO implica validación legal de una implementación inexistente. Verificar nuevamente normas, reglamentos y supuestos antes de publicar V1.

## Matrices normativas y fiscalidad contextual — documentación nueva

`docs/25-MATRICES-NORMATIVAS-J01-J05-P0-25-V1.md` recoge **59 controles** J-01…J-05 (Libro, consumidor, SUNAT, datos personales, incidentes/conservación), con condición de aplicación, sujeto responsable, evidencia y fuente oficial; `docs/26-CONTRATO-TRIBUTARIO-P0-17-J03-V1.md` concreta **15 supuestos SUNAT** y el alcance sin motor fiscal V1. Son **INVESTIGACIÓN DOCUMENTAL**, no validación legal por tienda ni código. La RM 244-2026-PCM es proyecto de modificación del Reglamento del Libro; no se verificó decreto definitivo posterior al corte y debe revalidarse. Se mantienen fuera de V1 el desglose fiscal vinculante, emisión CPE, notas de crédito automáticas y reportes ANPD/Indecopi/SUNAT.

## REST, privacidad y documentos externos — E-08 APROBADO COMO POLÍTICA

El contrato `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` **aprueba E08-A…R (D-149…D-166)** con acceso REST de empleados por capabilities, consultas privadas de invitados y segregación del Libro/ARCO. E08-P exige constancias, control de expedientes y exportación/borrado sujetos a ley. **NO certifica** los formularios, la respuesta legal, plazos, bases jurídicas ni recepción de correo: **J-01…J-05/P0-25 siguen pendientes**. Los comprobantes SUNAT continúan siendo **externos** y ninguna ruta emite factura/nota de crédito automática.

La adaptación MP peruana también requiere pruebas autorizadas de `payer` ausente, ítem de entrega y `PT15M`. La elección de endpoints y permisos no equivale a pruebas de seguridad, hospedaje ni cumplimiento ANPD/SUNAT/Indecopi.

## Enfoque

PeruCommerce ofrece herramientas para que los comercios cumplan deberes aplicables; no sustituye al comerciante, a SUNAT, a Indecopi ni asesoría legal. Los ajustes comerciales son opcionales; las obligaciones normativas dependen de la actividad, régimen y circunstancias y no se suprimen con un interruptor.

## Comercio electrónico y derecho del consumidor: fuentes oficiales verificadas para E-03

**Decreto Legislativo 1729**, publicado el **12-02-2026**, modifica la Ley 29571 e incorpora:

- **Artículo 24.4:** mecanismos de atención de reclamos y resolución de controversias de transacciones digitales accesibles, eficaces, permanentemente operativos y sin costos ni cargas innecesarias para el consumidor. Esta obligación es relevante junto con el Libro de Reclamaciones, no lo sustituye.
- **Artículo 24.5:** mecanismo mínimo de correo electrónico para proveedores **no domiciliados** orientados al mercado peruano que no cuenten con mecanismos de atención. No extrapolar esta condición especial como sustituto general de las obligaciones del comercio domiciliado.
- **Artículo 56.1.h:** prohíbe prácticas digitales que manipulen la libertad de elección y conduzcan a decisiones de consumo no deseadas con perjuicio; la UX de checkout, cancelación y reclamación no debe usar patrones coercitivos.
- **Reglamentación:** Indecopi informó el 30-06-2026 una consulta pública para elaborar el reglamento del DL 1729. **No se ha verificado en esta revisión un texto definitivo posterior**; comprobar norma, publicación, vigencia y reglas transitorias antes de cerrar P0-25.

**Ley 29571, arts. 18, 19 y 97:** idoneidad y remedios por deficiencias/incumplimiento de productos y servicios, según supuesto, aunque el pedido esté ENTREGADO/REALIZADA. Una cancelación ordinaria del sistema es distinta del procedimiento de cambio, garantía, devolución o reclamación del consumidor. La publicación de un proyecto sobre «derecho de arrepentimiento» **no acredita su vigencia**. No programar plazo universal de devolución por cambiar de opinión sin identificar norma aplicable o una oferta contractual válida.

**Indecopi 16-06-2026:** advirtió que la condición de oferta u outlet no suprime remedios por falla o incumplimiento; evitar condiciones generales como «no aceptamos cambios/devoluciones de productos en oferta» que restrinjan derechos.

**SUNAT:** una nota de crédito puede acreditar anulaciones, devoluciones y ajustes de comprobantes previos según supuesto; la V1 solo **registra el documento emitido fuera del plugin**. No generar comprobantes ni definir plazos de nota de crédito genéricos, porque varían por régimen/modalidad/supuesto.

**E-03 APROBADO EN POLÍTICA FUNCIONAL (D-86…D-95):** `16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md` contiene las diez opciones A confirmadas, matrices física/digital/servicio y 25 escenarios. **No está aprobada ni demostrada la matriz jurídica por sector y supuesto**: J-01/J-02 y P0-25 siguen abiertos, así como la verificación de reglamentos y los mecanismos E-04…E-08. No extrapolar plazos o derechos universales de proyectos de ley.

Fuentes oficiales adicionales:
- https://busquedas.elperuano.pe/dispositivo/NL/2486266-3
- https://www.gob.pe/institucion/indecopi/noticias/1412832-indecopi-inicia-consulta-publica-para-mejorar-reglas-del-comercio-electronico-y-frenar-practicas-enganosas
- https://busquedas.elperuano.pe/dispositivo/NL/538113-1
- https://consumidor.gob.pe/2026/06/16/outlets-no-pueden-restringir-cambios-nidevoluciones/
- https://www.gob.pe/17414-emitir-nota-de-cr-dito-electr-nica
- https://www.gob.pe/institucion/sunat/pages/26395-tipos-de-comprobantes-electronicos

## Libro de Reclamaciones (confirmada opción nativa o externa)

- Ley 29571 (Código de Protección y Defensa del Consumidor), artículos 150 y 151 modificados por **Ley 32495** (11-11-2025), incorporan plataformas digitales de comercio electrónico y enlace permanente, visible y fácilmente accesible al Libro de Reclamaciones.
- Consultar el Reglamento del Libro de Reclamaciones, DS 011-2011-PCM y modificaciones vigentes antes de fijar plantilla, constancias y tratamiento de datos.
- Indecopi informa plazo de **15 días hábiles improrrogables** para atender/responder. La guía oficial exige respuesta escrita y conservar prueba de respuesta/remisión según canal. La aceptación técnica de un email no prueba su recepción; el plazo legal deberá calcularse con calendario válido y no desde WP-Cron.
- Formato oficial: hoja/número correlativo, datos de proveedor/consumidor, fecha, bien o servicio, descripción, pretensión, respuesta y constancia según modalidad aplicable.
- Si es externo, su enlace y disponibilidad siguen siendo responsabilidad del comerciante; debe ser apropiado y accesible. Nativo: diseñar emisión de constancia, registro inalterable de ingreso y seguimiento, exportación y trazabilidad.
- El 17-07-2026 se publicó **un proyecto**, no necesariamente una norma definitiva, para modificar el reglamento. Verificar si se aprobó un texto posterior antes de cerrar V1; no convertir el proyecto en regla vigente.

**PENDIENTE:** reglamento definitivo del Libro, formato/constancia, numeración, copia, conservación, evidencia de respuesta y permiso técnico. **E-06 D-132 y E-07 D-135…D-148 aprobados en política** fijan constancia/alertas, accesos mínimos y retención por categoría; **J-01/J-04/J-05/P0-25 y E-08** siguen exigiendo validación legal/REST y prueba de evidencias reales antes de afirmar conformidad.

Fuentes oficiales:
- https://busquedas.elperuano.pe/dispositivo/NL/2457203-1
- https://consumidor.gob.pe/libro-de-reclamaciones/
- https://www.gob.pe/institucion/indecopi/noticias/1286430-comercios-electronicos-tambien-estan-obligados-a-contar-con-libro-de-reclamaciones-activo-y-visible
- https://www.gob.pe/institucion/pcm/normas-legales/8406634-244-2026-pcm

## E-02: autoridad administrativa y acceso mínimo (D-78…D-85)

**Política funcional aprobada:**
- El operador ve **solo** pedidos y datos de contacto indispensables para atención/entrega; no accede por defecto a información fiscal completa o secretos de proveedores. Se excluyen exportaciones masivas y acceso a archivos digitales protegidos.
- Solo el administrador gestiona credenciales, fiscalidad, políticas de la tienda, perfiles/permisos y expedientes del Libro de Reclamaciones; el operador recibe exclusivamente avisos operativos pertinentes. **El consumidor puede seguir presentando reclamaciones** por el mecanismo legal correspondiente; esta restricción solo afecta a la administración.
- Cambios de dirección posteriores a confirmación conservan evidencia original, y requieren aprobación administrativa si hay cambio de costo, cobertura o dinero acreditado. El token de seguimiento no permite cambios sensibles sin verificación adicional.
- Toda autorización se valida en servidor, bajo restricciones E-01 y D-64; la documentación no equivale a comprobar seguridad o cumplimiento de la Ley de Protección de Datos.
- **E-07 APROBADO EN POLÍTICA D-135…D-148** en `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md`: mínimo acceso por campo, enlace privado 90 días, OTP sensible, canal sin correo, WordPress Privacy y protección de archivos/caché. **PENDIENTES:** J-04/J-05/P0-25 para bases legales, ANPD, transferencias, ARCO, retención e incidentes; E-08 para controles REST efectivos.

## E-07: privacidad y seguridad de enlaces — APROBADO EN POLÍTICA D-135…D-148

**Fuentes oficiales revisadas el 17-11-2026:** DS 016-2024-JUS (Reglamento Ley 29733), vigente desde el **31-03-2025**; orientaciones de la **ANPD** actualizadas el **22-05-2026** sobre inscripción de bancos de datos personales y flujos transfronterizos; derechos ARCO; documentación técnica WordPress de exportador/borrador personal y advertencia de nonce como CSRF, no autenticación. La ANPD informa que la **inscripción de bancos de datos** del titular es un trámite gratuito y de aprobación automática, sujeto a cuenta y representación. **No condicionar desarrollo del plugin a un registro de cada negocio**, pero documentar el deber administrativo del comerciante cuando aplique.

**Política E-07 APROBADA (D-135…D-148):** `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` confirma 14 opciones E07-A…N sobre responsables, mínimos datos, seguimiento privado con vigencia inicial **90 días**, OTP **8 dígitos/10 min/5 intentos** para operaciones sensibles, descarga 24 h, WordPress Privacy, mapa a elección, conservación legal e incidentes. Incluye **42 escenarios P7-X no ejecutados**. Esos plazos son decisiones de seguridad de producto, **no plazos legales** ni certificación ante ANPD.

**Incidentes de datos:** DS 016-2024-JUS art. 34 contempla **notificación de determinados incidentes** a ANPD/titulares bajo condiciones y plazo de hasta **48 horas**, además de responsabilidades para incidentes digitales. No programar alertas o reportes de cada error como si todos fueran incidentes notificables; verificar supuestos J-05/P0-25 y registrar evidencia de evaluación y contención. El plazo no equivale a prueba de cumplimiento.

**Derechos ARCO:** la ANPD informa acceso (20 días hábiles) y rectificación/cancelación/oposición (10 días hábiles), sujetos a requisitos y verificación normativa vigente; el flujo nativo WordPress por correo **no cubre automáticamente compras sin correo**. No borrar registros de compra/tributos/Libro mientras una norma deba conservarlos. Pendientes J-04/P0-25 y retención/desinstalación P0-26.

**Responsabilidades y transferencias:** inventariar hospedaje real, Mercado Pago, correo y mapas/tiles; revisar quién es responsable/encargado y qué flujos requieren comunicación/registro. El desarrollador no procesa necesariamente datos por distribuir ZIP, pero el soporte con acceso a datos requiere análisis. El plugin no realiza trámites ante la ANPD.

Referencias oficiales:
- https://www.gob.pe/institucion/anpd/campa%C3%B1as/128319-nuevo-reglamento-de-proteccion-de-datos-personales
- https://busquedas.elperuano.pe/dispositivo/SE/2349653-1
- https://www.gob.pe/institucion/anpd/pages/8060-inscribir-banco-de-datos-en-el-registro-nacional-de-proteccion-de-datos-personales
- https://www.gob.pe/9253-inscribir-flujo-transfronterizo-de-datos-personales
- https://www.gob.pe/9270-que-son-los-derechos-arco
- https://www.gob.pe/9269-iniciar-procedimiento-para-el-ejercicio-de-derechos-de-acceso-rectificacion-cancelacion-y-oposicion
- https://developer.wordpress.org/plugins/privacy/
- https://developer.wordpress.org/plugins/privacy/privacy-related-options-hooks-and-capabilities/
- https://developer.wordpress.org/apis/security/nonces/

**Estado legal:** **E-07 cerrado en política**, pero J-04/J-05/P0-25 y P0-26 **NO CERRADOS**; faltan matriz por comercio/instalación, bases jurídicas, transferencias, plazos, protección real, incidentes notificables y validación del alojamiento.

**Mapas/tiles e información a terceros P0-21 (2026-11-17):** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` mantiene Leaflet opcional por iniciativa del comprador; si se abren tiles externos, el proveedor recibe la **IP del navegador** y `Referer` de una pantalla de selección sin secretos. No transmitir dirección completa, teléfono, OTP o tokens a terceros y no descargar mapas en páginas privadas. Pantallas de seguimiento, OTP y documentos privados NO cargan terceros; el fallback manual conserva delivery por distrito sin geolocalización. Revisar divulgación de prestadores y base por instalación J-04, sin asegurar cumplimiento ANPD comprobado.

## Protección de datos personales

Norma base: Ley 29733 y Reglamento aprobado por **DS 016-2024-JUS**, vigente desde 31-03-2025.

- Datos tratados: identificación/contacto de comprador, direcciones, coordenadas, historial de compras y reservas, destinatarios de descargas y reclamaciones.
- **PROPUESTA:** limitar campos por finalidad; avisos claros; marketing opcional separado; acceso/rectificación/supresión donde corresponda; protección de credenciales, registros de acceso y controles de exportación/retención.
- Identificar titular del banco de datos, encargado, finalidad, transferencias a proveedores, inscripción y requerimientos de seguridad conforme al supuesto normativo aplicable.
- PENDIENTE: política de retención por categoría, respuesta a solicitudes de derechos, transferencia a Mercado Pago/mapas/correo, incidentes y plazos legales. No eliminar información fiscal o de reclamaciones si debe conservarse.

Fuentes:
- https://www.gob.pe/institucion/anpd/normas-legales/6554453-16-2024-jus
- https://www.gob.pe/institucion/anpd/campa%C3%B1as/128319-nuevo-reglamento-de-proteccion-de-datos-personales

## E-05 — integridad y trazabilidad sin conformidad legal demostrada (D-109…D-122)

Se aprobó la política técnica de E-05 en `18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md`: operaciones transaccionales, idempotencia, webhooks verificados e inbox/outbox con recuperación. **Esto no constituye prueba de cumplimiento normativo ni seguridad del hosting.** El registro de accesos, referencias financieras, expedientes, auditoría, inbox/outbox y reintentos debe respetar minimización, permisos, conservación y eliminación legalmente exigible; sus campos, protección y plazos siguen abiertos en E-07/P0-25/P0-26. Tampoco permite tratar una solicitud de reembolso como dinero devuelto ni una notificación no verificada como pago acreditado.

## E-04: totales comerciales y documentos tributarios — POLÍTICA COMERCIAL APROBADA (D-96…D-108)

**Comprobación documental adicional (2026-11-17):** Mercado Pago Checkout Pro Orders requiere que el total enviado corresponda a la suma del precio unitario por cantidad de los ítems incluidos y admite valores monetarios con hasta dos decimales. Su reporte distingue **monto bruto**, **comisión** y **neto abonado al comerciante**; no son campos intercambiables. Esta regla de integración no define base imponible ni impuesto del comercio peruano.

**SUNAT:** los regímenes y comprobantes permitidos varían; en **Nuevo RUS** se emiten boletas/tickets, no facturas con derecho a crédito fiscal. La **nota de crédito electrónica** documenta determinadas anulaciones/devoluciones/descuentos vinculados a comprobantes previos conforme al emisor/modalidad. PeruCommerce V1 solo registra referencias de documentos emitidos externamente, sin generar notas de crédito ni una tasa IGV general.

**E-04 APROBADO en política comercial/numérica:** `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md` (D-96…D-108): céntimos PEN, totales sin redondeo intermedio, delivery por pedido, ajustes históricos y reembolsos externos verificables. **NO equivale a fiscalidad aprobada**: matriz por emisor/régimen/operación, base/afectación, redondeo fiscal, delivery, notas de crédito y plazos continúa **J-03/P0-17/P0-25 PENDIENTE**. No declarar integración SUNAT ni conformidad fiscal por la política comercial.

Fuentes oficiales:
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/create-order
- https://www.mercadopago.com.pe/developers/es/reference/online-payments/checkout-pro/create-order/post
- https://www.mercadopago.com.pe/developers/es/docs/checkout-api-payments/additional-content/reports/account-money/report-fields
- https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/nuevo-regimen-unico-simplificado-nuevo-rus
- https://cpe.sunat.gob.pe/tipos_de_comprobantes/nota_de_credito

## Precio final y tratamiento tributario contextual (D-45/D-46)

**CONFIRMADO como requisito de producto:** todas las operaciones comerciales V1 se expresan en **PEN (S/)**, sin multimoneda. El comprador debe conocer el **precio final** y los cargos de entrega u otros conceptos aplicables antes de confirmar el pedido. Esto no significa imponer a todos los regímenes el mismo impuesto, tasa o desglose.

**NO decidido aún (J-03):** cálculo de impuestos y conceptos según régimen/operación, qué importes incluyen tributos, reglas de redondeo fiscal, cuándo se presenta el desglose, tratamientos de servicios de cuarta categoría y validación de documentación externa. Investigar y establecer matriz normativa oficial actualizada antes de definir reglas fiscales y casos de aceptación. V1 no incorpora motor fiscal general ni emite comprobantes SUNAT.

## Políticas adicionales de revisión y conservación (D-61…D-66)

**Decisiones de funcionamiento CONFIRMADAS, sin declaración de conformidad jurídica:**
- **Digital habilitado sin descarga:** evaluación individual por administrador de cancelación/devolución, conforme derechos legales y condiciones informadas; la ausencia de descarga no demuestra por sí sola incumplimiento.
- **Despachado no entregado:** abrir incidencia logística para investigar destino/entrega y resolver según deberes del comercio; no negar derechos por la ausencia de cancelación ordinaria instantánea.
- **Cancelar última línea física antes del despacho:** evaluar costos/servicio de entrega efectivamente realizado, información pactada y derechos aplicables; no aplicar regla universal «delivery no reembolsable» ni «reembolso total».
- **Operador:** solo puede cancelar sin dinero acreditado ni ajuste/reintegro exigible y con autorización; no sustituye al administrador ante operaciones con cobro.
- **Dirección confirmada:** mantener condición original como evidencia y registrar nueva solicitud/ajuste sujeto a cobertura y posibles diferencias monetarias; cumplir protección de datos y no alterar hechos anteriores.
- **Reembolso parcial externo:** el administrador registra resultado verificado y sus referencias/importe; la posibilidad de registrar un reintegro no reemplaza derechos, plazos ni documentos fiscales de ajuste.

**Pendiente de matriz legal:** derechos digitales/servicios según supuesto, responsabilidad logística, tarifa de entrega, documentos externos, conservación de evidencias y tratamiento de datos de dirección.

## Elegibilidad, pedidos confirmados y protección del consumidor (D-58…D-60)

**CONFIRMADO como política funcional V1:** solo se tramita una cancelación ordinaria sobre líneas pendientes de cumplimiento y elegibles. Bienes ya entregados o servicios ya prestados se derivan, según corresponda, a procesos de devolución, reclamación o incidencia. Esta clasificación **no niega garantías ni derechos irrenunciables** y requiere reglas de negocio específicas por tipo de prestación y régimen aplicable.

Los datos originales de un pedido confirmado permanecen inalterados; las rectificaciones son eventos/ajustes auditables. Si la cancelación afecta financieramente un pedido pagado, solo el administrador la autoriza. Los reintegros se realizan por el medio externo correspondiente, nunca automáticamente por el plugin; su resultado verificado se registra después por el administrador.

**PENDIENTE de revisión normativa:** causales y plazos por tipo de operación, derechos ante bienes defectuosos y servicios no conformes, devoluciones parciales y comprobantes de ajuste, retención de evidencia, consentimientos/información de políticas al comprador y cálculo de importes. No declarar conformidad sin esa matriz validada.

## Cancelaciones parciales de artículos y obligaciones comerciales (D-56)

**CONFIRMADO como decisión de producto (D-56/D-58…D-60):** V1 permite cancelar individualmente líneas **pendientes de cumplimiento y elegibles** de un pedido mixto sin cancelar las restantes; las originales permanecen inmutables y los ajustes se registran aparte. Si el pedido pagado requiere ajuste financiero, solo administrador autoriza. La devolución de fondos, si corresponde, se tramita externamente y se registra su resultado comprobado según D-54.

**E-03 política funcional APROBADA:** el documento 16 clasifica bienes, servicios, descargas digitales y excepciones como reglas de producto D-86…D-95. **PENDIENTE de matriz jurídica:** leyes especiales, reclamaciones, causales, remedios, prueba de entrega, notas de crédito, costos/tributos E-04 y plazos por actividad/supuesto. La aprobación de una clasificación funcional no presume derecho de cancelación ni devolución en cualquier circunstancia.

## Reembolsos tramitados fuera del plugin (D-54)

**CONFIRMADO como decisión de producto:** en V1 la devolución de dinero se efectúa por el canal correspondiente fuera de PeruCommerce; el administrador registra en el plugin únicamente el resultado comprobado. Esto **no elimina** derechos de devolución, plazos, obligaciones de atención de reclamos o documentos de ajuste que correspondan al caso. La normativa aplicable y los comprobantes externos relacionados deben analizarse en la matriz legal antes de publicar.

**CONFIRMADO (D-66):** V1 registra reembolsos externos **parciales** comprobados por administrador, con importe/referencia y cobro original conservado. **PENDIENTE:** condiciones y plazos legales, comprobación externa, conciliación/acumulación, contracargos, notas de crédito u otros documentos y conservación de la trazabilidad.

## Precio final suministrado por el comerciante (D-49/D-50)

**CONFIRMADO:** la interfaz administrativa solicitará el precio final de venta en PEN y permitirá activar/desactivar manualmente una oferta simple. Esto no determina por sí solo la base imponible, régimen, tributos aplicables ni comprobantes exigibles. El responsable del comercio deberá configurar o registrar la información requerida por el supuesto correspondiente. El plugin no calculará automáticamente impuestos a partir de un precio neto genérico ni incorporará un motor fiscal general.

**PENDIENTE:** normas y reglas por régimen/operación, presentación del desglose cuando corresponda, precio unitario vs total, tributos incluidos, redondeo y obligaciones de información al consumidor; respaldar con fuentes SUNAT vigentes antes de implementar.

## SUNAT, comprobantes y régimen tributario

**CONFIRMADO (D-04/D-26):** V1 registra datos de **boleta, factura y recibo por honorarios** únicamente según el sujeto emisor, régimen y operación aplicables, con emisión en sistemas externos. No es emisor electrónico SUNAT ni supone que cualquiera puede emitir indistintamente esos documentos.

- El régimen afecta tipos de comprobantes habilitados: el **Nuevo RUS** permite boletas/tickets, no facturas con crédito fiscal; otros regímenes tienen condiciones diferentes.
- SUNAT indica amplia obligatoriedad de emisión electrónica, con excepciones y condiciones particulares; no presumir que un ticket interno cumple función de comprobante.
- Activar tipo de documento, identificación y campos del comprador solo cuando sea pertinente; guardar referencia, número, serie, fecha y estado de comprobante externo cuando el comerciante lo informe.
- **PENDIENTE:** matriz por régimen (Nuevo RUS/RER/RMT/General y rentas por servicios independientes de cuarta categoría), comprobantes legalmente disponibles, identificación e importes, notas de crédito, ventas parciales, devoluciones y validaciones. Confirmar con fuente SUNAT antes de construir el selector.

Fuentes:
- https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/nuevo-regimen-unico-simplificado-nuevo-rus
- https://cpe.sunat.gob.pe/informacion_general/obligados_cpe
- https://www.sunat.gob.pe/

## Reglas de UX de datos fiscales y personales

**CONFIRMADO (D-24):** pedir correo electrónico al incluir digitales o servicios, teléfono al incluir físicos; en mixtos, campos aplicables acumulados. Estos son mínimos funcionales, no sustituyen los datos que puedan exigir SUNAT, el proveedor de pago, la entrega o el Libro de Reclamaciones; no inferir números de documento obligatorios en todas las ventas.

## Políticas de cancelación configurables — decisión de producto

**CONFIRMADO (D-39):** el comercio configura sus condiciones de cancelación, sin una regla universal impuesta por PeruCommerce. Esta configuración **no puede excluir derechos irrenunciables ni requisitos legales aplicables**; el plugin debe permitir mostrar la política relevante antes de contratar, conservar evidencia de la versión aceptada y facilitar atención de reclamos/devoluciones. **PENDIENTE:** matriz jurídica por tipo de bien/servicio, información obligatoria, límites de penalidades, cambios de política sobre contratos anteriores y su aplicación a servicios ya reservados. No afirmar conformidad jurídica sin verificar la norma vigente y el caso concreto.

## Información al consumidor

**PROPUESTA:** permitir configurar datos del comerciante, precio total y cargos, cobertura/entrega, condiciones de pago, políticas de cambios/devoluciones, términos, privacidad, medios de contacto y vínculo reclamaciones; mostrarlos donde corresponda, especialmente antes de finalizar compra. Revisar requisitos sectoriales y comerciales específicos.

**PENDIENTE:** contenido mínimo por tipo de negocio, información de garantía, retractación/devolución según supuestos aplicables, contratos de servicio, tratamiento de menores, accesibilidad y normativa sectorial (alimentación, salud, productos regulados, etc.).

## Criterio de cierre

Ya existe **matriz documental** en `25-MATRICES-NORMATIVAS-J01-J05-P0-25-V1.md` con obligación, norma, sujeto, ámbito, pantalla/campo/evidencia y estado; el contrato tributario se amplía en `26-CONTRATO-TRIBUTARIO-P0-17-J03-V1.md`. Sigue faltando ratificación aplicable por comerciante/actividad y verificaciones de implementación. E-03 ya fijó **políticas funcionales D-86…D-95**, pero eso no demuestra conformidad jurídica. Verificar si hubo reglamento definitivo del DL 1729 posterior a la consulta de junio de 2026, modificaciones del Libro y obligaciones SUNAT por supuesto. Sin matriz y verificación actualizada, NO declarar «cumple toda la legislación peruana».
