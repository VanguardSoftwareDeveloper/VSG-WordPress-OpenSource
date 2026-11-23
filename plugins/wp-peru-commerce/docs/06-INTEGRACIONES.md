# Integraciones externas — PeruCommerce

**Estado:** Mercado Pago, contraentrega, mapas y delivery confirmados como necesidades; contrato preciso todavía PENDIENTE.

## Política general

- Cada tienda usa cuentas/credenciales propias de sus proveedores; PeruCommerce no procesa ni custodia fondos.
- Integraciones optativas se habilitan explícitamente, con estado de configuración, pruebas de conexión y fallos comprensibles.
- No cargar SDK/scripts ni enviar datos a servicios externos que no participan en la página o el flujo activo.
- Mantener historial y estados del negocio aunque se retire una credencial o se desactive un módulo.
- Nunca guardar ni exponer tokens privados en frontend, URLs, logs o archivos de exportación.
- Verificar costos, condiciones, disponibilidad regional y cambios de API antes de implementar.

## Importes de Checkout Pro y efectivo — E-04 APROBADO (D-96…D-108)

**Contrato de política comercial/numérica APROBADO:** `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md` (D-96…D-108). Los importes internos se expresan en enteros de céntimos PEN; cotización/total congelados al crear intento válido. El contrato de payload real MP sigue pendiente E-08.

Mercado Pago documenta para **Checkout Pro Orders** que el total enviado debe concordar con la suma de unit_price × quantity de sus ítems y admite importes con dos decimales o ninguno. El propio proveedor distingue importe bruto, comisiones y neto liquidado. **E-08 debe decidir cómo representar el cargo de entrega en el endpoint real**, sin añadir un producto ficticio ni confundir la comisión con deuda del comprador. La creación/cambio de intento requiere idempotencia E-05. La existencia de un proveedor externo no autoriza que PeruCommerce ejecute devoluciones.

**Contraentrega:** un cobro presencial inicial por pedido, con dinero realmente aplicado/auditado D-33/D-51; **E04-J/D-105 aprobado:** registrar solo importe neto aplicado y no modelar billetes/vuelto. Diferencia monetaria no acredita pedido completo y se tramita incidencia conforme a E-05/E-08.

**Reembolso externo:** D-54/D-66/E04-L/D-107 y **E05-L/D-120 aprobados**: solo administrador registra resultado comprobado total/parcial, con referencia única y acumulado limitado bajo una transacción. **Aún pendiente:** SQL real P0-18 y verificación contra proveedor E-08. No existe API de reembolso del plugin.

Fuentes:
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/create-order
- https://www.mercadopago.com.pe/developers/es/reference/online-payments/checkout-pro/create-order/post
- https://www.mercadopago.com.pe/developers/es/docs/checkout-api-payments/additional-content/reports/account-money/report-fields

## Cancelación, reclamación y reintegro externo — E-03 APROBADO FUNCIONALMENTE (D-86…D-95)

Las reglas D-54/D-66 permiten **registrar** resultados externos de devolución total/parcial comprobados, no ejecutarlos por API. **E-03 está aprobado funcionalmente** (D-86…D-95, `16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md`): expediente común tipado separa solicitud, cancelación ordinaria de línea completa, devolución/garantía, reclamación y reintegro externo verificado; el original no se sobrescribe. **No marcar dinero devuelto ni revocar acceso digital por una simple solicitud.** Los importes/conciliación E-04/E-05 y cumplimiento jurídico J-01/J-02/P0-25 siguen pendientes.

Un caso de producto despachado no entregado exige incidencia logística D-62/D-75; reclamar sigue siendo posible aunque no proceda cancelación ordinaria. Cuando un ajuste requiera nota de crédito externa conforme a SUNAT, registrar referencia válida según operación/emisor sin generar documento automáticamente. E-04 definirá montos y E-05 reconciliación; P0-25 verificará obligación fiscal concreta.

## Segregación de funciones y datos en integraciones — E-02 (D-78…D-85)

- **Credenciales de Mercado Pago y servicios cartográficos:** solo administrador autorizado las configura; el operador y visitante no pueden leer secretos ni obtenerlos mediante errores, API, logs o exportaciones.
- **Cobro manual:** operador registra efectivo efectivamente recibido con auditoría (D-51), pero no corrige asientos ni procesa o registra reintegros; esos movimientos corresponden al administrador (D-53/D-54). Ni siquiera el administrador ejecuta reembolso desde la API del plugin.
- **Direcciones y cobertura:** operador puede recibir solicitud de corrección y preparar ajuste; si cambia tarifa/cobertura o hubo cobro acreditado, solo administrador aprueba antes de aplicar. En todo caso conservar el snapshot original y volver a validar UBIGEO.
- **Seguimiento de invitados:** enlace privado permite consultar solo el propio pedido. Reemitir acceso, modificar entrega o solicitar devolución exige verificación adicional E-07, no confiar exclusivamente en poseer el enlace.
- **Datos operativos:** el operador no exporta masivamente ni accede a archivos digitales protegidos, expediente completo de reclamos o información fiscal completa por defecto. Los campos exactos permitidos en APIs continúan pendientes de E-07/E-08.
- Los controles se exigen en **backend**, independientemente de la visibilidad de Angular o WordPress. Ver `15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md`.

## Restricciones monetarias y de credenciales confirmadas (D-45…D-47)

- El plugin V1 opera exclusivamente en **PEN**, sin conversión ni carrito multimoneda; verificar que la orden enviada al proveedor corresponda al importe y moneda de la cotización autoritativa.
- El comprador debe conocer el **precio final** y todos los cargos aplicables antes de confirmar. El plugin no aplicará un motor tributario general: la determinación de impuestos depende del régimen y operación, con reglas y comprobantes externos por especificar.
- Las credenciales de Mercado Pago y del proveedor cartográfico, así como las configuraciones sensibles, solo se administrarán con capacidades de **administrador**, nunca del operador. El operador podrá gestionar pedidos, catálogo, stock y agenda bajo permisos específicos por definir, sin acceso indirecto a secretos mediante API/logs/exportaciones.

## Datos enviados a integraciones — E-07 APROBADO (D-135…D-148)

El documento `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` **APRUEBA E07-A…N (D-135…D-148)**: destinatarios mínimos, token de seguimiento/descarga protegidos, OTP/verificación, inventario por instalación de terceros de hosting, pasarela, correo y mapas. No suponer que Leaflet evita enviar IP a servidores de tiles, ni transferir dirección precisa sin necesidad. D-143 exige inventario por instalación y D-144 conservar alternativa manual para delivery.

E-07 distingue comprador con email y físico solo con teléfono, que requiere recuperación manual validada. El secreto de seguimiento no puede transmitirse a scripts/analytics de terceros y el archivo digital jamás debe exponerse por ruta pública de uploads. **E-08/P0-19/P0-21** definirán endpoints, protección de archivos, provider de mapas y transporte real; **J-04/J-05/P0-25** determinarán inscripción/flujo de datos e incidentes legales. Ninguna de estas medidas está implementada.

## Webhooks, idempotencia y recuperación de proveedor — E-05 APROBADO EN POLÍTICA (D-109…D-122)

**E-05 APROBADO EN POLÍTICA TÉCNICA D-109…D-122:** `18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md` fija MP en fases con correlación durable, clave **X-Idempotency-Key**, verificación de **x-signature**, inbox deduplicada, consulta de estado autoritativo, recuperación de pago tardío y operaciones locales atómicas. **E-08 sigue pendiente** para modalidad concreta Checkout Pro Orders, endpoints, tipos/status de firma y errores, incluida representación de delivery. Ningún callback visual acredita pago.

WordPress documenta que WP-Cron depende de visitas; no garantizar puntualidad para liberar reservas. Los holds vencen por tiempo efectivo de backend aun sin cron. Las operaciones externas no participan del commit SQL local: si se pierde respuesta de MP, conciliar orden antes de intentar crear una nueva. No ejecutar reembolsos API en V1; registrar solo resultados externos ya comprobados por administrador.

Fuentes: https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/create-order · https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/resources/notifications/webhooks · https://developer.wordpress.org/plugins/cron/

## Checkout Pro Orders — política E-08 APROBADA (D-156…D-160)

El documento `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` fija **Orders API por PHP/HTTP WordPress** con credenciales propias del comerciante, idempotencia e inbox de webhook HMAC consultando al proveedor. Para pedido físico sin email se elige **omitir `payer`**; `Entrega` es un ítem de cargo **no inventariable** que suma con las líneas al `total_amount`; `PT15M` solo si el proveedor lo admite. **Son opciones A aprobadas CONDICIONALMENTE D-157/D-158/D-159**, no capacidades verificadas en la cuenta peruana: **bloquear activación MP hasta prueba expresamente autorizada** de esos tres supuestos. Si falla alguno, pedir una nueva decisión, no fabricar correo ni cambiar el contrato unilateralmente.

Backend valida firma/`x-request-id`/`data.id`, persiste inbox y consulta `GET /v1/orders/{id}` antes de acreditar. Retorno de navegador no confirma dinero, hold local de 15 min no cancela order externa y **nunca se ejecutan reembolsos por API**. Quedan pendientes E08-R OpenAPI/DTO y P0-18/P0-19/P0-25, credenciales y webhook probados.

## Mercado Pago (confirmado como método de pago)

**Propuesta técnica:** Checkout Pro mediante **Orders API**, documentado por Mercado Pago para Perú como flujo recomendado, con redirección segura al proveedor.

Secuencia objetivo (PROPUESTA):
1. Backend revalida líneas, cantidades, cupos, tarifa de entrega, comprador y monto.
2. Registra pedido/retenciones y un intento de pago con identificador idempotente.
3. Crea order en Mercado Pago desde servidor con credencial del comercio, monto verificado, **PEN (D-45)** y referencia local; captura URL segura.
4. Redirige comprador al checkout; la salida/retorno no confirma pago.
5. Recibe webhook HTTPS, comprueba autenticidad y consulta estado autoritativo; registra idempotentemente y traduce a pago interno.
6. Confirma o gestiona compromisos de stock/cita por reglas definidas; permite conciliación posterior y reversos.

**Casos obligatorios:** webhook duplicado, retrasado, fuera de orden o inválido; retorno sin webhook; fallo de red; doble clic; diferencia monto/moneda; vencimiento de retención; reembolso externo parcial/total y su conciliación; contracargo; cancelación parcial sin alterar otras líneas; cambio de credenciales; test vs producción.

**CONFIRMADO:** Mercado Pago en cualquier tipo de carrito, un cobro por pedido (D-21/D-33), retención de 15 min sin renovación **desde pulsar «Pagar» y crear intento válido en backend (D-27/D-36)**, incidencia y decisión manual cuando un pago llega tarde sin stock/cupo (D-28). **PENDIENTE:** creación atómica, idempotencia, relación entre expiración local y orden externa, reconciliación, ejecución de reintegro, credenciales/onboarding y campos de pagador.

Fuentes oficiales (consultadas 2026-11-16):
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/overview
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/create-order
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/notifications
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/go-to-production

## Casos límite confirmados y efectos externos (D-61…D-66)

- Con **acceso digital ya habilitado pero sin descarga registrada**, solo el administrador evalúa cancelación/devolución según derechos aplicables: no activar reembolso ni cancelación automática por ausencia de registro de descarga.
- Una línea **física despachada y no entregada** exige incidencia/revisión logística antes de resolver, no cancelar de inmediato.
- Al cancelar última línea física antes del despacho, revisar costo de delivery efectivamente prestado y normas; ajustar cuando corresponda y obtener autorización administrativa si el pedido pagado tiene impacto monetario. Nunca asumir reintegro fijo.
- Un **operador** solo cancela sin dinero ya acreditado ni ajuste o reembolso exigible (D-64); pago verificado bloquea su capacidad de cancelación, aunque crea que la suma a devolver es cero.
- Cambiar **dirección** tras confirmación exige ajuste no destructivo, validación de cobertura UBIGEO/tarifa y viabilidad, preservando condición pactada originalmente. No reconstruir silenciosamente pedido del pasado.
- **Reembolso externo parcial**: registrado por administrador con importe PEN/referencia y evidencia comprobable, sin ordenar al proveedor reintegrar desde el plugin. Conservar pago original y movimientos posteriores por separado.

Pendientes la clasificación contable exacta, conciliación de parciales, validación del soporte del proveedor y la definición normativa por tipo de entrega.

## Restricción de cancelaciones y autorizaciones (D-58…D-60)

- Solo líneas aún **pendientes de cumplimiento** y elegibles pueden seguir el flujo ordinario de cancelación. Un artículo entregado o servicio ya prestado no debe retroceder de estado para cancelar; corresponde una devolución, reclamación o incidencia según el caso.
- Conservar íntegramente el pedido confirmado y los importes originalmente cobrados: ajustes posteriores se relacionan con la línea y el movimiento financiero, sin sobrescribir la operación inicial.
- Si la cancelación de una línea de pedido **pagado** requiere ajuste financiero, solo administrador puede autorizarla. **Regla más estricta D-64:** operador únicamente cancela si **no hay dinero acreditado** ni reintegro/ajuste financiero requerido y posee la capacidad concedida.
- Reembolsos siguen **externos a PeruCommerce**: autorización de cancelación y resultado de devolución de fondos son eventos distintos. El administrador registra solo un resultado verificado, no una intención.
- **Pendientes:** determinación exacta de elegibilidad, clasificación de impacto monetario, prorrateos, conciliación del pago de un carrito mixto, estados tardíos/duplicados y los derechos legales aplicables.

## Cancelación parcial y conciliación de pedidos mixtos (D-55/D-56)

- Los estados del pedido, los intentos/movimientos de pago y el cumplimiento de **cada línea** se conservan independientemente. Mercado Pago informa pagos, pero sus eventos no marcan automáticamente artículos como entregados.
- Se permite cancelar una línea **pendiente de cumplimiento y elegible** sin cancelar las demás; conservar la captura original inalterada y registrar un **ajuste auditable** que explique el importe y los recursos afectados. Si el pedido está pagado y el ajuste es financiero, requiere autorización de administrador.
- Si corresponde devolver una parte del cobro, **no ejecutar el reintegro desde PeruCommerce**. El administrador lo tramita por el medio correspondiente y registra el resultado verificable, manteniendo referencia al pago original y a la línea afectada.
- PENDIENTES: conciliación tras cambios de línea, montos/prorrateos de envío y tributos, reembolso parcial externo, señales de webhook posteriores, contracargos y validaciones por estado.

## Reembolsos exclusivamente externos en V1 (D-54)

- **No** ejecutar reembolsos desde PeruCommerce, tampoco mediante API de Mercado Pago; esto incluye devoluciones totales o parciales originadas por cancelaciones de líneas.
- El administrador tramita la devolución en el panel de Mercado Pago u otro canal apropiado al medio de cobro. Cuando el resultado sea verificable, lo registra manualmente en PeruCommerce junto con importe, medio, referencia/fecha y constancia disponible.
- Mantener separados estado del pago, solicitud de devolución y **resultado comprobado**; registrar una solicitud no autoriza marcar dinero como reembolsado.
- El operador no puede iniciar, confirmar ni registrar reembolsos; el administrador tampoco debe simular una devolución exitosa cuando no exista evidencia.
- **CONFIRMADO (D-66):** se pueden registrar resultados externos de reembolsos **parciales** verificados por administrador con importe y referencia, sin modificar el cobro original ni ejecutar una devolución API. **PENDIENTE:** procedimiento de comprobación, acumulados/conciliación de parciales, contracargos, derechos digitales y notas/documentos externos conforme a norma aplicable.

## Limitación confirmada de ofertas y precios (D-49/D-50)

El comercio informa **precio final por variante vendible** en PEN (D-52), incluyendo una oferta simple propia si la activa manualmente. Los importes y moneda enviados a Mercado Pago deben provenir de la cotización validada por backend y coincidir con el total comunicado antes de pagar. No introducir cálculo genérico de impuestos ni programación automática de promociones.

## Efectivo contraentrega (confirmado)

Pago manual del cliente en acto físicamente cobrable. **CONFIRMADO (D-21/D-33/D-37): únicamente cuando TODAS las líneas del pedido pueden cobrarse en un MISMO evento presencial, con un cobro por pedido. Al confirmar válidamente el pedido contraentrega se compromete inmediatamente stock y cita, pero el cobro permanece pendiente.** Requiere: saldo pendiente, registro de quién cobró, fecha/monto y corrección auditable; ninguna confirmación digital automática. Digitales siguen protegidos hasta que se acredite el pago de acuerdo con la política aprobada.

**CONFIRMADO (D-51/D-53):** operador autorizado registra efectivo recibido con auditoría; **solo administrador** puede efectuar ajustes correctivos vinculados al registro original sin borrarlo. No ejecutar ni registrar reembolsos por operador; los reembolsos se gestionan externamente y su resultado verificado lo registra solo el administrador (D-54). **PENDIENTE:** lugar/momento del cobro, procedimiento de acreditación, idempotencia y semántica de correcciones. No se autoriza fraccionar el cobro inicial del pedido.

**Contrato documental P0-21 posterior (2026-11-17):** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` rastrea 10 fuentes de OSM/Leaflet/INEI, 16 controles MAP y 20 casos M21-X. El mapa OSM Standard es opcional y sin SLA; requiere atribución, Referer y caché, prohíbe precarga; Nominatim público no es motor de autocomplete V1. Hay dos catálogos INEI con metadatos de licencia diferentes y aún **NO** se seleccionó ni incorporó archivo territorial definitivo. El backend sigue validando **distrito UBIGEO** y tarifa; el comprador siempre puede escribir dirección manual. No hay código, tiles o servicios probados.

## Mapas, direcciones y delivery

**CONFIRMADO (D-23):** tarifa por **distrito UBIGEO** configurada por el comercio y mapa para ubicar entrega; el mapa no se utiliza para dibujar polígonos tarifarios de V1.

**CONFIRMADO (D-44):** visor **Leaflet** con proveedor de mapas/teselas sustituible sin reescribir las reglas de entrega. Dirección manual disponible como alternativa si el proveedor falla o no está configurado; el servidor debe validar distrito UBIGEO, cobertura y tarifa antes de aceptar el pedido. La ubicación exacta mediante pin requiere un mapa operativo; la entrada manual no inventa coordenadas ni asegura cobertura sin comprobación.

**PROPUESTO:** cargar Leaflet solo cuando se abre el selector; guardar coordenadas solo cuando el comprador las aporta y son necesarias; no usar rutas o distancia para calcular el delivery en V1.

- Catálogo de departamentos/provincias/distritos vía datos oficiales (UBIGEO), con versión y actualización por decidir.
- Zonas y tarifas configurables **por distrito UBIGEO**, con reglas para distritos no atendidos todavía pendientes.
- Cobertura y tarifa se calculan en backend y se congelan en el pedido confirmado.
- Un pin en mapa NO demuestra que la dirección sea atendible; verificar zona/cobertura con reglas del comercio.
- Ubicación del establecimiento/cliente y enlaces externos a Google Maps/Waze sin forzar dependencia de esas APIs.
- No usar servidores públicos de `tile.openstreetmap.org` como proveedor garantizado de producción: no tienen SLA, exigen atribución y condiciones de uso y pueden bloquear consumo indebido. Elegir proveedor y documentar sus costos y obligaciones antes de publicar.

Referencias:
- https://leafletjs.com/
- https://operations.osmfoundation.org/policies/tiles/
- https://www.inei.gob.pe/ (fuente oficial a concretar para el catálogo territorial)

**PENDIENTE:** elección y configuración real del proveedor cartográfico (o autohospedado), claves/costos/atribución, geocodificación si se requiere, origen/actualización del UBIGEO, tratamiento de distritos sin cobertura, precisión, errores y privacidad de coordenadas. Leaflet y respaldo de dirección manual están aprobados, pero **no** la dependencia en un proveedor concreto ni geocodificación obligatoria. No rediseñar V1 con zonas poligonales sin autorización.

## Seguimiento de invitados y descargas protegidas (D-32/D-34)

CONFIRMADO: enlace de seguimiento privado no adivinable, visible al concluir pedido y enviado por correo cuando exista; el comerciante puede entregarlo manualmente por teléfono **tras identificar al comprador**. Enlace individual de descarga protegido **válido durante 24 horas**, con reemisión segura para comprador autorizado, sin cuota predeterminada y con límites opcionales. **PENDIENTE:** método de reemisión/verificación, revocación, recuperación, validación de contacto, protección de acceso y caducidad independiente del enlace de seguimiento; ningún enlace debe exponer secretos de Mercado Pago.

## Correo y WhatsApp

**E-06 APROBADO EN POLÍTICA (D-123…D-134):** el doc.19 fija 12 reglas de correo transaccional con `wp_mail`, remitente administrado, eventos N6, outbox idempotente, bandeja y reintentos. **`wp_mail()` TRUE no demuestra entrega al destinatario**. El correo es requisito de contacto solo para digital/servicio D-24, no para compra física exclusiva. El transporte real, controles REST y privacidad siguen E-07/E-08/P0-19.

**WhatsApp y SMS automatizados están EXCLUIDOS de V1 por D-133.** Compras físicas sin correo usan seguimiento WEB y contacto telefónico manual tras verificar identidad; tampoco se incorpora el enlace de WhatsApp alternativo en V1. Integraciones futuras requieren aprobación comercial y de datos específica.

**CONFIRMADO (D-24/D-34/D-123…D-148):** correo para digitales/servicios, teléfono para físicos, seguimiento WEB, avisos E-06 y seguridad de datos E-07 (token/OTP/retención/minimización). **PENDIENTES J-04/J-05/P0-25:** finalidad legal, flujos, plazos y respuesta formal; **E-08/P0-19:** transporte/API/comprobación real. Email no acredita pago ni documento SUNAT.

## SUNAT y comprobantes

Confirmado: recolectar y guardar datos fiscales suficientes para facilitar emisión **externa**, no enviar a SUNAT desde V1. No afirmar que un pedido del plugin sea comprobante tributario. El tipo de comprobante aplicable depende del comercio y operación.

Referencia: https://cpe.sunat.gob.pe/informacion_general/obligados_cpe

## Módulos habilitables

Un módulo de pago o mapa solo aparece al comprador después de que un **administrador autorizado** complete una configuración válida. El operador no accede a credenciales ni a parámetros sensibles (D-47). Desactivar un módulo impide nuevas operaciones y conserva historial. Cuando la obligación legal exige un enlace/recurso, el asistente de configuración debe advertirlo en vez de ofrecer desactivación indiferenciada.
