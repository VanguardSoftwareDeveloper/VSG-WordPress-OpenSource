# UX y flujos — PeruCommerce

**Estado:** principios confirmados; composición y escenarios específicos propuestos y pendientes de validar en Figma.

## Principios confirmados

1. Mínimos pasos y campos, sin exigir creación de cuenta al comprador.
2. Interfaz diferenciada para **móvil** y **escritorio**; misma lógica de negocio.
3. Catálogo insertable en WordPress mediante bloques y shortcodes; checkout propio dedicado.
4. Carrito capaz de mezclar físicos, digitales y servicios; pedir datos solo cuando apliquen.
5. Interfaz administrativa propia, con diseño en Figma, integrada en WordPress.
8. Servicios presenciales en local, agenda y recurso únicos, una cita por pedido; cambios de cita por comerciante desde panel.
9. Mercado Pago disponible para todo tipo de artículo; efectivo solo si todas las líneas pueden cobrarse presencialmente.
10. Delivery con tarifas por distritos UBIGEO; correo obligatorio para digitales/servicios y teléfono para físicos.
11. Dos perfiles de administración (administrador y operador de permisos limitados); matriz granular pendiente.
6. Objetivo obligatorio WCAG 2.2 nivel AA en interfaces públicas y de administración.
7. Objetivo táctil mínimo de 44 × 44 píxeles CSS para controles interactivos independientes, con la distinción entre objetivo activable y tamaño gráfico.

## Responsabilidades visuales aprobadas (D-42…D-44)

- El catálogo visible al público, fichas y componentes de bloques/shortcodes se generan desde WordPress/PHP con HTML indexable; no dependen de la ejecución de Angular para presentar contenido esencial.
- Angular gestiona la experiencia interactiva del carrito, checkout y panel del comerciante; las composiciones móvil/escritorio comparten estado y contratos de API, sin duplicar lógica de negocio.
- Si el mapa Leaflet está disponible, se puede fijar un punto de entrega; si falta proveedor o carga, permitir dirección escrita y distrito, con verificación de cobertura y tarifa en backend. No mostrar como válida una ubicación que no se pudo comprobar.
- No cargar scripts pesados del checkout o mapas en páginas que no los necesiten. Los detalles de integración, tokens y rendimiento quedan por diseñar.

## UX de rutas y errores — E-08 APROBADO EN POLÍTICA (D-149…D-166)

El contrato `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` define respuestas de recotización, stock/cita agotados, idempotencia en proceso, pago MP incierto, token privado vencido, OTP fallido y descarga protegida. Los códigos HTTP/`pc_*` son dirección de diseño E08-M, **todavía no implementados ni verificados**. Figma debe reflejar dos vistas móvil/escritorio, consulta de pedido solo lectura y estados reales de pago/entrega, sin inventar estados E-01.

La aprobación E-08 **no** confirma pago por retorno del navegador ni autoriza refund API. Un pedido solo físico no requiere correo; OTP solo en acciones sensibles. Pendientes OpenAPI/DTO, conexión MP real, textos jurídicos P0-25, componentes finales y pruebas autorizadas.

## UX de privacidad/identidad de invitados — E-07 APROBADO (D-135…D-148)

**E-07 APROBADO (D-135…D-148):** `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` fija seguimiento privado solo lectura de 90 días, acciones sensibles con OTP 8 dígitos/10 min/5 intentos para correo, asistencia manual verificada para físicos sin email, descargas independientes de 24 h, acceso mínimo por perfil y derechos del consumidor. **No exigir OTP al checkout normal**, no hacer caducar derechos al vencer el enlace. E-08 establecerá controles y errores REST definitivos.

Figma debe respetar **mínimo de campos D-136, geolocalización voluntaria D-144**, sin DNI/RUC universal ni casillas de marketing; Libro accesible sin cuenta y derechos conservados tras caducidad de enlace. Las vistas privadas no exponen token a scripts/terceros/caché. **Pendientes:** textos legales por negocio J-04/J-05/P0-25, detalle de UI y autorización REST E-08, seguridad verificada.

## UX de mensajes, reclamaciones y avisos — E-06 APROBADO (D-123…D-134)

La política **E-06 aprobada D-123…D-134** en `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` fija eventos N6 de pedido pendiente/cobro verificado, efectivo, citas, digital, reclamos, reintegros e incidencias. Figma debe cubrir confirmación WEB, bandeja y errores de transporte. **E06-A…L aprobadas**; datos/plantillas REST y evidencia legal exacta siguen pendientes E-07/E-08/P0-25.

Reglas vigentes: el pedido **solo físico no necesita email**; siempre se muestra confirmación/seguimiento WEB. No comunicar «pagado» por retorno del navegador ni «reintegrado» por mera solicitud. El Libro debe seguir accesible al consumidor y una falla de email no puede ocultar el reclamo. Diseñar móvil/escritorio con estados de aviso fallido y contacto manual; **E-07 ya fija identidad como política D-135…D-148**, mientras los controles REST/errores y pruebas E-08 siguen pendientes.

## Interfaz frente a carreras, pagos inciertos y recuperación — E-05 APROBADO (D-109…D-122)

E-05 (D-109…D-122) fija reintentos idempotentes y recuperación; **E-06 (D-123…D-134) fija política de avisos**. Figma debe distinguir stock/turno agotado, MP pendiente/ambiguo, hold vencido, pago tardío e incidencias; mensaje/plantilla concreto, campos privados y errores HTTP se cerrarán en E-07/E-08.

El frontend **no confirma pago/reserva por regreso del navegador** ni anima a cobrar otra vez ante un timeout sin conciliación. La bandeja presenta incidencias y reintegros con estado real; **E-06 aprobado** diferencia transporte aceptado y recepción probada. Pendientes respuestas E-08, datos E-07 y comprobaciones de concurrencia/correo no ejecutadas.

## Estados de cotización y cobro — E-04 política APROBADA (D-96…D-108)

El contrato comercial aprobado `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md` (D-96…D-108) obliga a diseñar precios/variantes y ofertas válidas, subtotales y un delivery, recotización con aceptación si cambió antes de Pagar, snapshots originales, diferencia de total MP, efectivo neto, cancelación por línea completa, revisión de cargo de delivery, cambio de dirección sin cobro automático adicional y reembolso externo parcial con estado verificado independiente.

**No confundir** cobro acreditado, saldo pendiente, obligación de devolver, devolución comprobada ni comisión del proveedor. E-04 monetario y E-06 de comunicaciones aprobados; pendientes desglose fiscal J-03, datos E-07, API E-08, componentes Figma y pruebas. No añadir cargos/multas automáticos.

## UX de cancelaciones, garantías y reclamaciones — política E-03 APROBADA (D-86…D-95)

E-03 (doc.16) aprueba expedientes tipados, elegibilidad de líneas, reclamaciones, digitales y no-show; E-06 (doc.19) define avisos de esos hechos. **Pendientes:** textos legales J-01/J-02/P0-25, identidad/datos E-07, respuestas REST E-08 y diseño final de componentes. Figma no crea nuevas reglas de arrepentimiento ni niega remedios.

**Restricción normativa revisada (DL 1729/2026):** canal de reclamos digitales accesible/eficaz y sin cargas innecesarias; evitar prácticas coercitivas de diseño que manipulen la elección del comprador. El Libro de Reclamaciones visible sigue su normativa propia. Nunca presentar «producto en oferta = sin reclamos», «descargado = no puede reclamar» o «servicio realizado = sin remedios». No confundir reclamo con cancelación ordinaria.

## UX administrativa y del invitado — E-02 aprobado (D-78…D-85)

- **Panel de operador:** pedidos y contacto mínimo necesario, sin datos fiscales completos por defecto; crear/editar y ocultar productos, actualizar precios/ofertas y modificar stock/agenda con motivo, registrar preparación/entrega y reprogramar cita con evidencia. Nunca presentar acceso a exportaciones masivas, ficheros digitales protegidos ni configuración sensible.
- **Panel de administrador:** gestión de credenciales, políticas, tributación, Libro de Reclamaciones y roles; exportaciones/archivos digitales protegidos sujetos a política de datos, corrección de cobros y registro de reintegros externos verificados.
- **Dirección:** operador recibe/propondrá cambios; si varía cobertura/costo o existe cobro acreditado, mostrar revisión/aprobación del administrador. No actualizar silenciosamente dirección original.
- **Seguimiento de invitado:** enlace privado solo da lectura a su pedido; controles de reemisión, modificación de entrega o devolución remiten a un proceso separado de verificación de identidad E-07.
- **Estados de error obligatorios para diseño:** permiso denegado en backend, acceso ajeno, error de identidad, estado de línea incompatible, datos ocultos por rol, intento de exportación y falta de evidencia. La política funcional está aprobada; el mecanismo visual, componentes y validaciones detalladas siguen sujetos a Figma y E-07/E-08.

## UX para excepciones y recuperación (D-61…D-66)

- **Digital con enlace habilitado y sin descarga:** presentar solicitud «Revisar cancelación/devolución» para evaluación del administrador; no sugerir reintegro automático o prestación no cumplida por ausencia de descarga.
- **Físico despachado no entregado:** mostrar incidente/seguimiento logístico, sin botón de cancelación ordinaria de un clic; revisar antes de resolver.
- **Última línea física antes del despacho:** indicar que los costos de delivery están sujetos a evaluación, no fijar devolución ni retención total predeterminada; mostrar autorización del administrador para ajuste financiero de pedido pagado.
- **Operador:** ocultar/deshabilitar acciones de cancelación si hubo cualquier cobro acreditado o existe reintegro/ajuste exigible, además de validar elegibilidad y capacidad; el backend debe denegar una llamada directa incompatible.
- **Cambio de dirección:** formulario de solicitud posterior a confirmación con dirección original en historial y propuesta vigente en ajuste, validación de distrito/cobertura/costo, resolución de error/logística y nueva confirmación cuando corresponda.
- **Reembolso parcial externo:** pantalla del administrador para registrar importe PEN, referencia y resultado **verificado**; separar solicitud/pendiente/comprobado y preservar original. No mostrar «reembolsar ahora» desde el plugin.
- Diseñar variantes de éxito, rechazo, espera de evaluación, falta de cobertura, error de concurrencia, validación normativa y cambio que altera tarifa. La decisión sobre cálculo y permisos precisos sigue abierta.

## UX confirmada para cancelación pendiente y pedido inmutable (D-58…D-60)

- Mostrar acción **«Cancelar artículo» solo cuando la línea esté pendiente de cumplimiento y sea elegible**; si fue entregada o el servicio ya se realizó, dirigir al proceso adecuado de devolución, reclamación o incidencia, sin ocultar derechos exigibles.
- El detalle de un **pedido confirmado** muestra sus datos originales y un historial de ajustes rectificativos; no permitir reescritura silenciosa del precio, cantidad, cobro o estado anterior.
- Para cancelar con dinero acreditado o efecto financiero, dirigir al administrador. **El operador solo puede cancelar si nunca se acreditó dinero y no existe ajuste/reintegro requerido** (D-64), además de tener capacidad expresa; no puede registrar un reembolso como ejecutado.
- En la confirmación de cancelación, distinguir la línea afectada, restantes sin cambios, posibles ajustes y, si procede, **reembolso pendiente de tramitación externa**. Nunca mostrar «dinero devuelto» sin resultado verificable.
- Figma debe definir variantes de estado y permisos, incluido intento no autorizado, artículo ya entregado, devolución solicitada e historial de ajustes; los importes/prorrateos exactos requieren contrato aprobado.

## UX confirmada para estados y cancelaciones parciales (D-55…D-57)

- **Detalle de pedido:** presentar estado del pedido, estado del pago y estado de cumplimiento **por cada línea** (físico, digital o servicio). No etiquetar «entregado» por estar cobrado, ni «pagado» por confirmar una contraentrega.
- **Cancelación de artículo:** en el panel autorizado, permitir seleccionar línea **pendiente de cumplimiento y elegible** y explicar qué se cancela, el ajuste, lo que queda intacto y si requerirá devolución externa. Si hay efecto financiero en pedido pagado, aprobación exclusiva del administrador. No cancelar líneas restantes ni afirmar reintegro sin confirmación.
- **Producto simple:** campo de precio final y oferta manual propios. **Producto con variantes:** mostrar y solicitar precio final/oferta explícitos por variante; ante precio faltante, impedir completar compra inválida hasta corregir, sin herencia automática.
- **Estados extraordinarios:** cancelación parcial con pago aprobado, reembolso externo pendiente/confirmado, parte del pedido entregada, cita cancelada pero físico todavía en preparación, archivo digital revocado cuando corresponda.
- La forma exacta de las acciones, autorización y recuperación se definirá en el contrato de estados antes de aprobar Figma; no inventar reglas de devolución o prorrateo.

## UX aprobada: variantes, correcciones y reembolsos externos (D-52…D-54)

- Al cambiar variante, presentar su **precio final explícito propio** y, si está habilitada, su **oferta manual independiente**; el producto simple tiene precio/oferta propios. No heredar automáticamente importes cuando falta el valor de una variante.
- El formulario de variantes del comerciante permite editar precio y oferta por variante; no obliga a usar el mismo precio para todas.
- **Operador:** puede registrar efectivo cobrado, pero no ve controles para corregir registros, registrar reembolsos o realizar reembolsos. El backend también los deniega.
- **Administrador:** puede ingresar un **ajuste de corrección** con motivo y referencia al cobro original inalterable; nunca se presenta una acción «borrar cobro histórico».
- **Reembolso externo:** administrador dispone de registro del **resultado confirmado** por Mercado Pago o medio correspondiente; mostrar método/importe/referencia y estados de evidencia, sin botón de reembolso automático. Una solicitud todavía no confirmada no debe presentarse como «reembolsada».
- Diseñar estados de error para referencia inexistente, importe incoherente, cobro ya corregido o reembolso no comprobado. La política de reconciliación precisa sigue pendiente.

## UX confirmada de precios, ofertas y cobros (D-49…D-51)

- El formulario de producto/variante del comerciante solicita **precio final** en PEN, sin exigir que descomponga primero precio neto + impuestos; la información tributaria complementaria se configurará según régimen/operación.
- El precio de oferta simple se habilita/deshabilita **manualmente** desde el panel, sin controles de programación por fecha; la ficha debe comunicar el precio vigente de manera veraz.
- El operador podrá registrar **efectivo efectivamente recibido** con una acción explícita, confirmación comprensible y constancia de usuario/fecha/monto. No mostrarle controles de reembolso ni credenciales; las API igualmente deben negar esas operaciones.
- Mostrar siempre si el pedido está **confirmado pero pendiente de cobro** o **cobrado**; nunca equiparar una venta contraentrega confirmada con dinero recibido.

## Reglas de presentación confirmadas (D-45…D-48)

- Presentar importes exclusivamente en **soles peruanos (PEN, S/)**; no ofrecer selector de moneda V1.
- El comprador ve el **precio final** y el total de la operación con costos de entrega/cargos aplicables antes de confirmar. Un cambio de distrito o medio de pago que altere el total requiere recálculo visible y nueva aceptación cuando corresponda.
- La explicación fiscal no debe suponer que todos los comercios trasladan los mismos impuestos. El desglose/etiquetado se derivará de reglas aprobadas por régimen/operación; evitar textos legales inventados o engañosos.
- El operador del panel dispone de gestión de pedidos, productos, inventario y agenda, sin pantallas o acciones que permitan modificar **credenciales, tokens o parámetros sensibles**. Las operaciones privilegiadas se rechazan en el backend aunque se invoquen directamente.
- No adaptar el diseño por obligación de admitir navegadores/versiones antiguas aún no verificadas; la matriz compatible se definirá con evidencia oficial y controles de accesibilidad aprobados.

## Recorrido base propuesto

**Catálogo o ficha → carrito instantáneo → checkout único → confirmación/estado del pedido.**

- Compra de un artículo: botón «Comprar ahora» puede saltar la revisión de carrito.
- Móvil: barra inferior persistente con cantidad/total; carrito como panel inferior; checkout vertical a pantalla completa.
- Escritorio: carrito lateral sin abandonar catálogo; checkout de columnas con resumen persistente.
- Nunca duplicar pedido porque el cliente pulse dos veces o reintente una solicitud.
- Mercado Pago redirige a su sitio y vuelve; página de retorno NO afirma pago aprobado hasta conciliarlo con backend/proveedor.
- Cuando el pedido tiene servicio presencial en el local: máximo una cita; **fecha/hora se eligen en la ficha antes de añadir al carrito** y se revalida en checkout. Seleccionar fecha no bloquea cupo.
- Cuando contiene físicos: entrega a domicilio o recojo; solo mostrar mapa si es relevante.
- Cuando contiene digitales: correo de entrega y explicación de acceso protegido, sin solicitar dirección física salvo otros artículos.

## Decisiones UX/operativas adicionales confirmadas (D-27…D-35)

- El stock/cupo se retiene 15 minutos **cuando el comprador pulsa «Pagar» y se crea un intento válido en el servidor**, sin renovación automática. En checkout debe informarse vencimiento y recuperación (presentación pendiente).
- El pago tardío sin capacidad genera incidencia; no se promete entrega o cita y el comerciante resuelve.
- Agenda con horas semanales configurables, duración por servicio, buffers y fechas bloqueadas; cancelación/reprogramación desde panel de manera auditable, con reintegro separado.
- Stock por variante; la selección de una variante agotada debe gestionarse sin borrar el resto del carrito.
- Enlaces de descarga de **24 horas**, protegidos y reemitibles de forma segura, con límites configurables y sin número máximo de descargas predeterminado.
- Un cobro por pedido; efectivo únicamente si todo puede cobrarse en un solo evento presencial.
- Seguimiento de invitado con enlace secreto visible tras confirmar pedido y enviado por correo cuando exista, o comunicado manualmente con verificación del comprador.

## Seis decisiones complementarias confirmadas (D-36…D-41)

- El botón **«Pagar»** inicia la retención **solo cuando el servidor crea el intento válido**, por 15 minutos sin renovación. Evitar mostrar contador activo antes de obtener la respuesta del servidor.
- Con **efectivo contraentrega**, «Confirmar pedido» compromete stock/cita al confirmarlo válidamente; informar «pedido confirmado, pago pendiente», no «pagado».
- Un cliente por franja, con duración de servicio y buffers sin solapamientos. Mostrar horarios realmente disponibles.
- El comerciante configura sus **condiciones de cancelación**; presentar la política aplicable antes de completar la contratación y nunca ignorar derechos legales.
- Al vencer el enlace de descarga a las **24 horas**, ofrecer un flujo de **reemisión segura** al comprador autorizado. No confundir límite temporal del enlace con cuota de descargas.
- Mostrar **precio regular y precio de oferta simple** donde aplique, con total real calculado por backend; no dibujar entrada de cupones ni promociones automáticas en V1.

## Comportamiento condicional del checkout (propuesta)

| Carrito | Datos específicos | Sin mostrar |
| --- | --- | --- |
| Físico solamente | Dirección/distrito/zona o recojo; cobro | Calendario y descarga |
| Digital solamente | Contacto/correo de entrega; cobro | Delivery y mapa |
| Servicio solamente | Calendario, horario, contacto y cobro | Dirección salvo servicio a domicilio |
| Mixto | Una sola cita como máximo; bloques específicos por línea, correo si hay digitales/servicios y teléfono si hay físicos; elegir pago elegible | Campos irrelevantes |

**CONFIRMADO:** correo si hay digitales/servicios y teléfono si hay físicos. Resto de campos, validación, autenticación/recuperación de pedidos invitados: **PENDIENTES**. Datos fiscales: condicionar a régimen/importe/tipo y comprobante requerido, no imponer DNI/RUC universalmente.

## Estados visuales a diseñar en Figma

**Catálogo:** sin productos, cargando, error, filtros sin resultados, producto agotado, variante inválida, sin precio válido.

**Carrito:** vacío, cambio de cantidad, precio recalculado, artículo despublicado, stock insuficiente, vencimiento de reserva, bloqueo de horario.

**Checkout:** validación por campo, pago no disponible, zona fuera de cobertura, mapa inoperativo, sesión expirada, intento duplicado, navegador sin conectividad, formulario incompleto, timeout y recuperación sin doble pedido.

**Mercado Pago:** salida al proveedor, retorno pendiente, aprobado verificado, rechazado, cancelado, expirado, notificación tardía, reembolso.

**Reservas:** calendario sin cupo, recurso no disponible, horario cambiado, cita confirmada, cancelada, reprogramación, pago aprobado sin cupo.

**Digitales:** archivo disponible, descarga sin autorización, enlace vencido, límite agotado, archivo eliminado, revocación después de reembolso.

**Pedidos:** visualizar por separado estado administrativo del pedido, estado del pago y cumplimiento individual de cada línea (D-55); estados exactos/transiciones aún pendientes de aprobar.

**Reclamaciones:** formulario, errores, envío duplicado, número de constancia y estado de atención.

## Pautas de diseño

**Documentos vinculados:** [10-ESTANDARES-UX-MOVIL-Y-ACCESIBILIDAD.md](10-ESTANDARES-UX-MOVIL-Y-ACCESIBILIDAD.md) desarrolla las reglas operativas (Fitts, reducción de decisiones, accesibilidad, formularios y rendimiento); [11-CONTRATO-FIGMA-Y-DISENO-DE-COMPONENTES.md](11-CONTRATO-FIGMA-Y-DISENO-DE-COMPONENTES.md) define tokens, variantes, frames y traspaso Figma→Codex. **Los objetivos WCAG 2.2 AA y 44 × 44 px CSS están CONFIRMADOS**; el resto de recomendaciones de implementación y rendimiento sigue propuesto.


- Figma: un lenguaje visual común, dos composiciones por contexto y variantes para todos los estados.
- Preferir controles reconocibles, etiquetas visibles, mensajes accionables y resúmenes totales transparentes.
- Controles táctiles independientes con área de interacción mínima 44 × 44 px CSS, teclado, lectores de pantalla, foco visible, contraste y mensajes de error; validar conformidad con WCAG 2.2 AA. Para enlaces dentro de texto corrido rigen los criterios/excepciones aplicables de WCAG AA.
- Mapas Leaflet con proveedor intercambiable y carga bajo demanda; permitir dirección manual sin mapa, pero no confirmar delivery fuera de un distrito con cobertura verificada. Calendario con carga contextual.
- Precio final, costo de delivery y restricciones conocidos antes de confirmar el pedido.
- Consentimientos opcionales de marketing separados de aceptación de condiciones necesarias.
- Ubicar enlace visible al Libro de Reclamaciones exigible sin obligar al comprador a iniciar sesión.

## Inventario de pantallas a especificar

**Público:** catálogo (bloque/shortcode), ficha, variantes, carrito móvil, carrito escritorio, checkout móvil, checkout escritorio, selector de zona/mapa, calendario/horarios, retorno de pago, resultado/seguimiento de pedido, entrega de descargas, Libro de Reclamaciones, condiciones/privacidad.

**Comerciante:** onboarding de tienda, resumen, catálogo y variantes, pedidos y detalles, calendario/agenda, inventario, tarifas/cobertura, pagos, descargas, reclamos, datos tributarios, diseño/integraciones y permisos.

Para cada pantalla debe existir ficha con: propósito, usuario, disparadores, datos, validaciones, carga/vacío/error, responsive, permisos, accesibilidad, navegación y criterios de aceptación.

## Decisiones pendientes de UX

- **CERRADO (D-20/D-35):** fecha y horario se eligen en ficha, máximo una cita por pedido, y el checkout revalida. Pendientes: diseño de pérdida de disponibilidad, mensajes y mecanismo de recuperación de selección.
- ¿Qué ocurre al cambiar variante, borrar producto o dejar vencer un cupo reservado?
- El comprador invitado consulta por enlace privado (D-34), pero **no** reprograma desde autoservicio (D-22); faltan caducidad, revocación y asistencia manual segura.
- **CERRADO (D-33/D-37):** un único cobro en un evento presencial y compromiso inmediato al confirmar contraentrega; siguen por definir el lugar/momento exactos del cobro y los mensajes para pedidos incompatibles.
- ¿Se permite editar el pedido tras recibir pago y cómo se calcula la diferencia?
- Fecha/hora en ficha, agenda de un recurso, cambios manuales desde panel confirmados. Quedan pendientes mensajes de expiración/revalidación, contenido, condiciones de cambio y ausencia del cliente.
- ¿Figma se diseñará primero para checkout móvil y luego escritorio? Propuesto, no aprobado.
