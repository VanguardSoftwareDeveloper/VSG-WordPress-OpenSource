# E-03 — Elegibilidad de cancelaciones, devoluciones y servicios V1

**Estado: E-03 APROBADO EN POLÍTICA FUNCIONAL (D-86…D-95), con MATRIZ NORMATIVA PARCIAL PENDIENTE.** El propietario aprobó expresamente las diez opciones A E03-A…J el 2026-11-17. La política es vinculante; J-01/J-02, P0-25 y E-04…E-08 todavía no se han cerrado. No equivale a conformidad legal ni autoriza código, pruebas o despliegues.
**Corte de consulta oficial: 2026-11-17.** No autoriza código, compilaciones, pruebas, integraciones ni despliegues. No es una certificación legal.
**Objetivo:** que Codex no decida por sí mismo si cancelar una línea, aceptar una devolución, reprogramar una cita, restaurar inventario, revocar descargas o remitir una reclamación. Los motivos de decisión se expresan en contratos auditables; los montos, tributos y mecanismos transaccionales quedan en E-04/E-05.
**Fuentes de producto:** decisiones D-01…D-95 en 01-DECISIONES-Y-ESTADO.md; máquinas de estados aprobadas en 14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md; política de permisos aprobada en 15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md. La política E-03 queda fijada por D-86…D-95; prevalece la ley aplicable ante cualquier política comercial.

**Matriz legal posterior (2026-11-17):** `docs/25-MATRICES-NORMATIVAS-J01-J05-P0-25-V1.md` propone obligaciones y evidencias J-01/J-02, incluidas Ley 32495 y DL 1729, sin fingir reglamento de Libro definitivo ni derecho universal de desistimiento. **La elegibilidad individual por sector y el cumplimiento probado permanecen pendientes**; E-03 no se reabre ni autoriza refund automático.

**Nota de precedencia vigente tras D-198 (18-11-2026):** las reglas funcionales E-01…E-07 aprobadas en este documento se conservan. Los textos anteriores que presentan E-08, DTO, capabilities, TTL o cobertura de expedientes como *pendientes de decisión* son **históricos**, no una segunda fuente de contratos: prevalecen `docs/01` D-01…D-198, `docs/41/42`, OpenAPI **0.5.0-draft (67 REST/64 rutas/117 DTO)** y `docs/23/24` (39 tablas, 56 PER). Solo quedan pruebas/compatibilidad de motor, proveedor, hosting, normativa por comercio y release en P-C/P-D; no se presentan como verificadas. El usuario autorizó aquí **documentación**, no código ni ejecución.

## 1. Límites: cuatro procedimientos jurídicamente y operativamente diferentes

| Proceso | Propósito | Qué NO significa |
| --- | --- | --- |
| Cancelación ordinaria | Resolver una línea **pendiente de cumplimiento** que sea elegible según lo pactado y la ley, sin destruir otras líneas (D-56/D-58) | No borra la venta pactada ni equivale por sí sola a devolver dinero |
| Devolución / cambio / garantía | Atender problema o remedio relativo a un bien entregado o prestación ya ejecutada, según hechos, oferta, ley y resolución | No revierte automáticamente ENTREGADO/REALIZADA a PENDIENTE |
| Reclamación del consumidor | Facilitar reclamo por falta de idoneidad, atención o cumplimiento; usar cauce legal y mecanismo accesible | No depende de que la línea esté cancelable ni de aceptar primero el reclamo |
| Reembolso financiero externo | Registrar solo después de verificación que se devolvió dinero por Mercado Pago u otro canal correspondiente, total o parcialmente (D-54/D-66) | No se ejecuta por API de PeruCommerce, ni toda solicitud equivale a pago devuelto |

**CONFIRMADO:** datos/snapshots del pedido original se conservan (D-59); estados de pago/pedido/línea son independientes (D-55/D-67…D-77); el admin aprueba impacto financiero y registra resultados de devoluciones, el operador solo cancela cuando no existió acreditación monetaria y no hay ajuste/reintegro exigible (D-60/D-64/D-78…D-85).

**POLÍTICA APROBADA (D-86/D-90…D-93):** representar solicitudes y reclamaciones mediante expediente común tipado, vinculado a pedido/línea, con motivo, evidencia, evaluación y resultado separados, sin crear estados globales extra de pedido. Los identificadores físicos, campos, formatos, restricciones de datos y API requieren E-05/E-07/E-08.

## 2. Comprobaciones normativas y límites de la investigación

**Fuentes primarias consultadas y alcance identificado:**

| ID | Norma / fuente oficial | Consecuencia de diseño | Lo que no se puede inferir |
| --- | --- | --- | --- |
| N-01 | Ley 29571, Código de Protección y Defensa del Consumidor; artículos 18 y 19 (idoneidad), 97 (remedios), 24 (reclamos) | Producto/servicio no conforme, defectuoso, tardío o no brindado necesita cauces de atención y remedio según supuesto | No existe un porcentaje fijo o elección universal automática de reintegro aplicable a toda categoría |
| N-02 | D. Leg. 1729, publicado 12-02-2026: añade art. 24.4 (mecanismo accesible, eficaz y permanentemente operativo para reclamos de transacciones digitales) y art. 56.1.h (prohibición de patrones que manipulen la elección y causen perjuicio) | No esconder reclamar detrás de un checkout, login o requisito excesivo; evitar cancelar de forma engañosa y opciones preseleccionadas perjudiciales | No se ha verificado aquí texto definitivo de reglamentación complementaria; consultar vigencia específica al cerrar P0-25 |
| N-03 | Ley 32495, 11-11-2025: modifica arts. 150/151 sobre Libro de Reclamaciones en plataformas digitales | Mantener libro nativo o enlace permanente visible/accesible; mecanismo de reclamación no condicionado a compra pagada | No declarar que cualquier formulario ad hoc cumple el formato reglamentario vigente |
| N-04 | Portal oficial consumidor.gob.pe/Libro de Reclamaciones | Referencia operativa de reclamos/quejas y plazo indicado de 15 días hábiles improrrogables para respuesta | No confundir plazo de respuesta al reclamo con plazo universal para desistir de toda compra |
| N-05 | SUNAT: nota de crédito electrónica sobre comprobante previo para anulación/devolución/descuento según tipo de operación | Si un ajuste requiere documento tributario, permitir registrar referencia del documento externo y su vínculo al pedido/línea | No emitir automáticamente nota de crédito ni ofrecerla a cualquier régimen; la norma y el emisor concreto importan |
| N-06 | Indecopi, junio 2026, advertencia sobre outlets y promociones | Una venta de oferta no elimina por sí sola remedios de idoneidad/cambios y devoluciones reconocidos por ley | No imponer «sin devoluciones por estar en oferta» o exigir siempre empaque impecable |
| N-07 | Indecopi, consulta pública sobre reglamento del D. Leg. 1729, junio 2026 | La consulta era preparación de regulación, no la aprobación automática de un reglamento | Verificar disposición final publicada antes de certificar cumplimiento |

**Advertencia de derecho de arrepentimiento:** la propuesta de protección al consumidor de comercio electrónico que mencionaba plazos de arrepentimiento fue publicada como **propuesta**, y **no debe convertirse en una regla legal vigente por inferencia**. En esta revisión no se ha acreditado un plazo universal de desistimiento para toda compra digital en Perú. Los derechos efectivos dependen de Ley 29571, normas especiales, condiciones válidamente ofrecidas y del caso concreto. No copiar plazos de otros países o sector financiero.

**Referencias oficiales verificadas:**
- Código original: https://busquedas.elperuano.pe/dispositivo/NL/538113-1
- Decreto Legislativo 1729: https://busquedas.elperuano.pe/dispositivo/NL/2486266-3
- Indecopi consulta de reglamento (30-06-2026): https://www.gob.pe/institucion/indecopi/noticias/1412832-indecopi-inicia-consulta-publica-para-mejorar-reglas-del-comercio-electronico-y-frenar-practicas-enganosas
- Ley 32495: https://busquedas.elperuano.pe/dispositivo/NL/2457203-1
- Consumidor — Libro de Reclamaciones: https://consumidor.gob.pe/libro-de-reclamaciones/
- Indecopi — cambios/devoluciones en outlets (16-06-2026): https://consumidor.gob.pe/2026/06/16/outlets-no-pueden-restringir-cambios-nidevoluciones/
- SUNAT — tipos de comprobante: https://www.gob.pe/institucion/sunat/pages/26395-tipos-de-comprobantes-electronicos
- SUNAT — emisión de nota de crédito: https://www.gob.pe/17414-emitir-nota-de-cr-dito-electr-nica
- Indecopi sobre reprogramación/devolución de servicios: https://www.gob.pe/institucion/indecopi/noticias/688916-servicios-turisticos-es-posible-solicitar-una-reprogramacion-si-no-se-brinda-el-servicio-debido-a-las-manifestaciones

**PENDIENTE J-01 (P0-25):** matriz jurídica por clase de producto/servicio, momento de cumplimiento, sujeto, sector especial, política ofertada y texto reglamentario efectivamente publicado, con referencia oficial vigente al publicar. N-01…N-07 identifican obligaciones/risgos, NO equivalen a cumplimiento legal ya demostrado.

## 3. Política de tienda y reglas de elegibilidad

**CONFIRMADO D-39:** cada comercio configura y presenta su política de cancelación, sin plazo universal de 24 h impuesto por PeruCommerce; esta no puede limitar derechos legales obligatorios.

**POLÍTICA APROBADA E03-A (D-86):** clasificar solicitudes de la siguiente manera, preservando siempre los cauces legales:
- CANCELABLE_ORDINARIA: línea no cumplida, sin excepción logística/digital, operación permitida por política/ley y con permisos/impacto monetario resueltos.
- REVISION_ADMINISTRATIVA: se requiere juicio sobre derecho, entrega, prestación, costo o evidencia; registrar expediente sin ejecutar cancelación ni devolución hasta resolución.
- NO_CANCELABLE_POR_VIA_ORDINARIA: hecho ya cumplido o no elegible; **ofrecer devolución, reclamación o garantía cuando corresponda**, nunca denegar sin posibilidad de atención.
- INCIERTA: faltan hechos o contrato normativo; **no ejecutar cancelación**, mostrar revisión y canal de reclamación. No ocultar el caso ni marcarlo como rechazado automáticamente.

**POLÍTICA APROBADA E03-B (D-87):** cada pedido referencia una versión **inmutable y recuperable** de las condiciones informadas al comprador, con evidencia de aceptación; no es necesario duplicar todo el texto por cada pedido. Cambios posteriores no sustituyen condiciones originales válidas; condiciones abusivas no prevalecen sobre la ley. Los campos y la estructura de versionado siguen en E-05/E-07.

**Jerarquía de resultado:** norma obligatoria y derechos irrenunciables → oferta/condiciones informadas y válidas del pedido original → hechos comprobados de entrega, pago y servicio → decisión autorizada del comercio → efectos contables/operativos. No permitir que un operador elija a mano «sin impacto financiero» sin comprobación.

## 4. Matriz física por estado E-01

| Estado físico | Elegibilidad ordinaria por confirmados | Tratamiento previsto / pendiente | Recurso y dinero |
| --- | --- | --- | --- |
| PENDIENTE sin despacho | Candidata a cancelación si es elegible, D-58 | Validar política, motivo, stock, pago, permisos | Reposición solo tras cancelación aprobada; no devolver dinero dentro del plugin |
| PREPARANDO sin despacho | Candidata, no automática si hay costo/servicio incurrido | **APROBADO D-88:** cancelación ordinaria permitida si hay elegibilidad, costo, estado y permiso validados; caso ambiguo a revisión administrativa | Costos/importe E-04; stock E-05 |
| LISTO para recojo, sin entrega | Candidata a elegibilidad mientras no se haya entregado | Recojo pendiente no equivale a entregado; si hay disputa de preparación o costos, revisión | Stock e inventario controlados E-05 |
| LISTO para delivery, sin despacho | Candidata con revisión de tarifa si última línea física (D-63) | Determinar si se incurrieron costos y si hay restantes físicos | Tarifa y documentos E-04 |
| DESPACHADO no entregado | **No** cancelar con un clic: abrir incidencia D-62 | Investigar, evidenciar no entrega y resolver por administrador; excepcionalmente DESPACHADO→CANCELADO conforme D-75 | No reponer stock antes de tener físicamente unidad o determinar su estado; diferencia financiera E-04 |
| ENTREGADO | **Prohibido** ENTREGADO→CANCELADO por vía ordinaria (F-10) | Ruta separada de devolución/garantía/reclamación según el caso | **APROBADO D-89:** no reponer stock vendible al solicitar devolución; exigir recepción/inspección o resolución autorizada antes de reponer |
| Físico defectuoso, incompleto o diferente de lo ofrecido | Derecho a remedio sujeto a norma y hechos aunque estuviera en promoción | Abrir expediente de idoneidad, no denegar por descuento o falta de embalaje original | Reposición/reparación/reintegro conforme resolución y ley, no convertir en cancelación ordinaria |

**CONFIRMADO:** cancelar última línea física obliga a revisar delivery por prestación/costos y ley (D-63); no hay tarifa «no reembolsable» universal. D-77 prohíbe crear segunda máquina de envío V1: conservar eventos logísticos de la línea física.

**PENDIENTE:** prueba mínima de despacho/entrega, actor que verifica recepción/recojo, transportista externo o propio, custodia de bienes devueltos, pérdida logística, quién asume gastos y causales permitidas por legislación sectorial.

## 5. Matriz digital por estado E-01

| Estado/dato | Regla confirmada | Resultado por contrato / hueco |
| --- | --- | --- |
| BLOQUEADO sin dinero acreditado | No descargar. Cancelación ordinaria candidata si hay elegibilidad D-58 | Operador solo si D-64 (sin cobro ni ajuste), demás por admin; no confundir token con derecho |
| HABILITADO, ninguna descarga registrada | **D-61:** evaluación del administrador, no cancelación ni reembolso automáticos | **APROBADO D-90:** expediente común tipado y revisión/decisión explícita del administrador con evidencia y respuesta; sin cancelación automática |
| HABILITADO con una o más descargas | Descarga es evento, no estado terminal (D-71) | **APROBADO D-91:** reclamación por archivo o acceso defectuoso mediante expediente común tipado, incluso si se descargó, sin revocación automática |
| Token caducado a 24 h | No descargar con token vencido; derecho puede seguir vigente y permitir reemisión segura (D-40) | No exigir compra nueva por mero vencimiento de enlace; E-07 define identidad |
| REVOCADO | Debe existir causa válida y actor autorizado según G-06 | **APROBADO D-92:** revocación únicamente tras causa legítima y decisión administrativa auditable en expediente común; nunca por simple solicitud o vencimiento del enlace |
| CANCELADO antes de habilitar | Cancelación válida con evento; no se entregaron archivos | Conservar hechos, derecho y tipo de devolución separados |

**No inventar** «digital descargado = consumidor perdió siempre todo remedio» ni «sin descarga = devolución obligatoria». Determinar las condiciones de no conformidad, licencias de uso, acceso efectivamente brindado, límites de descargas opcionales y derechos legales. Si existe un caso sectorial especial, E-03 lo marca como revisión jurídica. **No aceptar alternativas que eliminen canales legales, permitan cancelación digital automática contra D-61 o revocación sin una decisión válida**.

## 6. Servicios presenciales y agenda por estado E-01

**Una agenda/recurso, un cliente por franja, un servicio/cita máximo por pedido, solo atención en el local (D-18…D-20/D-38).**

| Estado de servicio/cita | Tratamiento | Actor/guardas y efectos |
| --- | --- | --- |
| PENDIENTE_CONFIRMACION | Selección/retención MP provisional, no cita efectiva | Hold 15 min; no prometer cupo definitivo. Vence→EXPIRADA; pago tardío sin cupo genera incidencia |
| CONFIRMADA, antes de fecha/hora | Cancelación o reprogramación manual desde panel según política y ley D-22/D-30/D-39 | Operador con D-81 y D-64 para cancelar; administrador en pedidos con dinero acreditado/ajustes. Reprogramación auditada con cupo nuevo disponible |
| CONFIRMADA, servicio en curso | **PENDIENTE J-02:** definir cuándo deja de estar pendiente de cumplimiento | No fingir que una prestación parcial es «no iniciada» ni permitir anulación retroactiva sin expediente |
| REALIZADA | No REALIZADA→CANCELADA ordinario V-09 | Reclamo/remedio por servicio no conforme, no borrar evidencia de prestación |
| NO_ASISTIO | No auto cancelar, multar o reembolsar por inferencia | **APROBADO D-93:** registrar NO_ASISTIO con hechos/evidencia y resolver por expediente común según política válida y derechos; sin penalidades ni reintegros automáticos |
| CANCELADA | Finaliza cita autorizadamente; reembolso externo por separado si procede | Liberación de intervalo/cupo según condiciones y exactitud E-05 |
| EXPIRADA | Hold vencido antes de cita efectiva | No representa penalidad por no-show ni cancelación solicitada; verificar pago tardío por incidencia D-28 |

**POLÍTICA APROBADA E03-I (D-94), E06-I (D-131) y E-07 (D-135…D-148):** reprogramación exige disponibilidad/cupo, historial y constancia de comunicación; el acceso del invitado a cambios sensibles requiere verificación adicional E-07. **Pendientes E-08/P0-25:** canal REST, prueba de comunicación, derechos/aceptación y datos expuestos. No reprogramar unilateralmente suprimiendo remedios.

**No establecer** tarifa de inasistencia, horas mínimas para anular, multa o devolución automática general. Esos valores dependen de E-04 y de la política particular validada bajo P0-25.

## 7. Pedido mixto: matriz de efectos sin prorrateos inventados

| Escenario | Comportamiento confirmado | Punto que falta |
| --- | --- | --- |
| Cancelar solo físico aún pendiente + digital ya entregado | Conservar otras líneas; estado administrativo no cambia a cancelado total por una línea (D-56/D-59/D-67) | Cálculo de importes y derecho digital E-04 |
| Cancelar última línea física antes despacho | Revisar y ajustar delivery según prestación/costos y derechos (D-63) | Qué parte de tarifa/tributos se devuelve E-04 |
| Cancelar servicio pero mantener físico | No liberar stock de físico ni borrar línea; liberar franja solo al confirmar cancelación/reprogramación | Concurrencia E-05 |
| Digital incluido en contraentrega sin dinero acreditado | Descarga bloqueada pese a pedido confirmado, solo si cobro presencial único es elegible D-21/D-37 | Lugar/momento de cobro, verificación E-05 |
| Pago MP tardío sin disponibilidad | Abrir incidencia; reprogramar o reintegrar externamente bajo administración D-28/D-74 | Contacto, consentimiento, saldo E-04/E-06 |
| Solicitud de devolución de una línea cumplida | Crear expediente separado de cancelación; no revertir entrega/servicio | Derechos y comprobantes externos P0-25 |
| Cambio de dirección con pedido confirmado | Ajuste auditable D-65; aprobación administrativa si afecta costo/cobertura o hubo cobro D-83 | Viabilidad tras despacho, diferencia de tarifa E-04 |
| Reembolso externo parcial | Solo admin registra resultado verificado D-66; no alterar cobro original | Prueba, acumulados y documento SUNAT E-04/P0-25 |

**POLÍTICA APROBADA E03-J (D-95):** cancelación ordinaria V1 por **línea completa**; un pedido con 3 unidades en una línea no puede cancelar silenciosamente una unidad mediante flujo ordinario. Las cantidades parciales se tramitan en expediente y ajuste administrativo, sin modificar snapshot original ni inventar cálculo/prorrateo (E-04/E-05). **Esto no elimina** la posibilidad de registrar reintegros externos parciales ya confirmada en D-66 ni los remedios legales aplicables.

## Protección de identidad en expedientes — E-07 APROBADO (D-135…D-148)

**Contrato vinculante:** [20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md](20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md). La versión histórica del pedido y la política de acceso se preservan; los endpoints/permisos y los plazos jurídicos siguen E-08/J-04/J-05/P0-25.

Para solicitar cambio de dirección, devolución asociada a pedido o reemisión digital, el enlace de seguimiento D-85 es **solo lectura**. **E-07 aprobado (D-135…D-148)** fija OTP de 8 dígitos/10 min/5 intentos para operaciones sensibles de clientes con correo, verificación manual proporcional para físicos solo teléfono, mínimo acceso y privacidad de expedientes. El Libro público permanece accesible sin cuenta. **Pendientes E-08/J-04/J-05/P0-25:** controles y plazos legales exactos.

No bloquear la presentación pública de reclamos por falta de cuenta/email; no usar el vencimiento de token para negar derechos a remedios. J-01/J-02/J-04/J-05/P0-25 y E-08 deben definir verificación y protección de datos en expedientes antes de implementar.

## E-08 — solicitudes y expedientes APROBADOS COMO POLÍTICA (D-149…D-166)

El [contrato E-08](21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md) fija rutas de consulta de pedido por sesión invitada **solo lectura** y de solicitud verificada de cambio, reemisión o devolución, sin ejecutarlas automáticamente. Cancelación ordinaria continúa por **línea completa** D-95; restitución/garantía de artículo ENTREGADO mantiene expediente separado y el Libro no requiere cuenta ni compra. **D-54 prohíbe refund API** y D-64 limita al operador.

Los nombres/errores/rutas se aprobaron como diseño, pero faltan esquemas DTO/OpenAPI, controles implementados, validación de identidad y matriz legal J-01/J-02/J-04/J-05/P0-25. No declarar resuelta una devolución ni dinero acreditado solo por una respuesta 202 o por el correo.

## 8. Procedimiento del motor de elegibilidad — política aprobada, detalles técnicos pendientes

**Entrada mínima candidata:** identificador de pedido/línea; estado E-01; tipo de línea, cantidad/variante; evento de entrega/descarga/servicio; política versionada pactada; motivo/solicitud; actor E-02; historial de pago acreditado; incidencias/retenciones activas; forma de entrega y evidencia; restricciones legales por tipo.

**Salida candidata, sin importes inventados:** clase de resultado (cancelable, requiere revisión, ruta de devolución/reclamación, no elegible por vía ordinaria); estado anterior intacto; motivo trazable; actor/autorización necesaria; recursos a revisar; posible ajuste monetario por evaluar E-04; documento tributario externo a registrar si legalmente corresponde; eventos y notificación por E-06; error/recuperación E-08.

**Guarda de ejecución:** solo tras confirmar elegibilidad y autorización; si hay dinero acreditado **nunca cancela el operador** (D-64). Si hay incertidumbre legal, financiera, stock o identidad, no resolver automática ni silenciosamente: expediente de revisión y canal de reclamación disponible.

**Eventos auditables candidatos para diseño técnico:** solicitud_recibida, evaluacion_iniciada, informacion_requerida, cancelacion_autorizada, cancelacion_denegada_con_motivo, linea_cancelada, devolucion_solicitada, devolucion_aprobada, devolucion_tramitada_externa, reintegro_externo_verificado, garantia_tramitada, incidencia_logistica_abierta, incidencia_resuelta, cita_reprogramada, no_asistencia_registrada. **La necesidad de auditoría ya está aprobada**, pero esos nombres de evento aún son candidatos E-05/E-08 y no nuevos estados E-01.

**Errores y negativas que deben distinguirse:** sin permiso; pedido ajeno; token de invitado insuficiente; línea ya cumplida; despacho iniciado; cupo ya ocupado; doble cancelación; dinero acreditado; falta de prueba/evidencia; política no vigente; entrega no conforme; falta de norma aplicable; monto sin contrato; discrepancia del proveedor; reembolso externo no verificado. E-08 definirá códigos y respuestas seguras.

## 9. Escenarios de aceptación E-03 por familia — NO EJECUTADOS

| ID | Caso | Resultado exigible o pendiente |
| --- | --- | --- |
| E3-X01 | Comprador intenta cancelar directamente su cita desde enlace de seguimiento | No autoservicio D-22/D-85; brindar canal de solicitud y verificación adicional |
| E3-X02 | Operador cancela línea no cobrada pendiente y elegible | Solo si D-64, política, capacidad y auditoría; recursos se gestionan sin duplicidad |
| E3-X03 | Operador cancela línea cuyo dinero estuvo acreditado y luego se reintegró | DENEGADO por D-64, aunque saldo neto sea cero |
| E3-X04 | Físico PREPARANDO sin despacho con cancelación solicitada | Revisar política/costos; si elegible procesar con autorización, sin inferir tarifa |
| E3-X05 | Físico DESPACHADO, no entregado | Incidencia logística D-62; cancelación excepcional solo con condiciones D-75 |
| E3-X06 | Físico ENTREGADO presenta defecto pese a oferta | Atender reclamación/devolución/garantía; no negar por descuento ni cambiar ENTREGADO a CANCELADO |
| E3-X07 | Solicitud de devolución antes de recibir físicamente el producto | No reponer stock vendible solo por solicitar devolución (D-89); requiere recepción/inspección o resolución autorizada |
| E3-X08 | Digital BLOQUEADO por falta de pago | Descarga denegada; cancelación posible solo según elegibilidad y D-64 |
| E3-X09 | Digital HABILITADO sin evento de descarga | Evaluación A obligatoria D-61; no reembolso ni cancelación automática |
| E3-X10 | Digital descargado pero archivo corrupto | Permitir reclamar no conformidad, no denegar por hecho de descarga; remedio según derecho |
| E3-X11 | Token digital venció a 24 h | No desbloquear con token viejo; permitir reemisión segura si derecho vigente D-40 |
| E3-X12 | Reembolso externo solicitado pero no ejecutado | No revocar archivo ni marcar dinero devuelto por el mero pedido, salvo decisión válida independiente |
| E3-X13 | Servicio CONFIRMADA antes de la cita | Solicitud atendida según política válida y ley, sin 24 horas impuestas universalmente |
| E3-X14 | Servicio YA REALIZADA presenta incumplimiento | Canal de reclamación/remedio; no cancelación ordinaria retroactiva |
| E3-X15 | Servicio NO_ASISTIO | Registrar hecho, no multa ni reintegro automático sin contrato/ley |
| E3-X16 | Reprogramar a franja ocupada | No doble reserva; conservar slot original si no se logra uno nuevo |
| E3-X17 | Comercio suspende cita previamente confirmada | Atención de incumplimiento, comunicación y remedio; no cambio unilateral silencioso |
| E3-X18 | Cancela última línea física en mixto con delivery | Revisar entrega/costos y autorización D-63, conservar otras líneas y originales |
| E3-X19 | Solicitud de Libro de Reclamaciones desde web sin login | Canal visible/accesible, no condicionar a compra o estado de pedido, pendiente formato normativo |
| E3-X20 | Se cambia política de tienda después de confirmar | Mantener evidencia de condiciones ofertadas al comprar; no sustituir por política nueva |
| E3-X21 | Reclamo por falta de servicio aunque pedido dice FINALIZADO | Registrar expediente posterior separado D-76, sin reescribir cierre |
| E3-X22 | Se tramita reintegro parcial externo y falta nota de crédito aplicable | Mantener resultado financiero/evidencia; notificar documento externo pendiente según matriz fiscal E-04/P0-25, nunca emitir automáticamente |
| E3-X23 | Pedido con 3 unidades de la misma variante y comprador pide cancelar una | **D-95:** no cancelar una unidad mediante operación ordinaria; tramitar expediente/ajuste administrativo y determinar importes/stock en E-04/E-05 |
| E3-X24 | Usuario deja queja o reclama derecho por producto ya entregado | Reclamo sigue admitido como expediente; denegar cancelación ordinaria no lo bloquea |
| E3-X25 | Falla el servicio de libro nativo o el enlace externo configurado | No declarar cumplimiento; definir contingencia y aviso operativo según reglamento y E-06/P0-25 |

## 10. Registro de decisiones E-03 — CERRADO EN POLÍTICA FUNCIONAL

**Confirmación expresa (2026-11-17):** el propietario aprobó **«Todas A en E-03»**. Se registran las diez políticas como D-86…D-95. Las alternativas B fueron descartadas; esta aprobación no equivale a validar toda legislación peruana ni resolver casos sectoriales.

| Origen | Registro | Política A aprobada |
| --- | --- | --- |
| E03-A | D-86 | Clasificar solicitud por cancelación ordinaria, evaluación, devolución/reclamación y no elegible por vía ordinaria; nunca suprimir canales legales |
| E03-B | D-87 | Referenciar versión inmutable/recuperable de condiciones pactadas con evidencia de aceptación |
| E03-C | D-88 | Cancelación elegible de físico PREPARANDO/LISTO sin despacho después de validar costo, estado y permiso, o revisión si hay dudas |
| E03-D | D-89 | Devolución/garantía tras entrega en expediente; no restock vendible sin recepción/inspección o resolución autorizada |
| E03-E | D-90 | Digital HABILITADO sin descarga usa expediente común tipado con evaluación/evidencia y respuesta; sin automatismos |
| E03-F | D-91 | Digital descargado puede reclamar contenido/acceso defectuoso en expediente común; conserva remedios legales |
| E03-G | D-92 | Revocación digital únicamente tras causa legítima, resolución administrativa y auditoría; no por solicitud/token vencido |
| E03-H | D-93 | NO_ASISTIO se registra y revisa según condiciones/derechos en expediente común, sin multas ni devolución automáticas |
| E03-I | D-94 | Reprogramación con disponibilidad/historial; el canal/constancia transaccional está aprobado posteriormente por E06-I/D-131. Pendientes identidad/datos E-07 y ejecución E-08 |
| E03-J | D-95 | Cancelación ordinaria por línea completa; cantidades parciales mediante expediente/ajuste administrativo, sin fragmentación automática |

**Contratos aún abiertos:** derecho por sector J-01/J-02/J-04/J-05/P0-25, evidencia de entrega, costo fiscal y SQL. **E-03…E-07 aprobados en sus políticas D-86…D-148**; quedan E-08, conformidad real y retención legal pendiente. Los escenarios de avisos no equivalen a pruebas.

## 11. Subcontratos pendientes tras aprobar E-03

- **J-01:** matriz de consumidor por causal/sector, fecha, excepción real, evidencia y autoridad (Ley 29571 y D. Leg. 1729); libro físico/virtual y contingencia, adaptación reglamentaria, no tomar proyectos como ley.
- **J-02:** determinar exactamente cuándo un servicio se considera comenzado/prestado y qué evidencia tiene la cita.
- **E-04:** política comercial/numérica **APROBADA D-96…D-108** en `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md`: céntimos, precio/cantidad, delivery, efectivo, saldo y devoluciones externas. **PENDIENTE J-03/P0-17/P0-25:** tratamiento fiscal, redondeo legal, gastos repercutibles, comprobantes externos y aplicación sectorial. E-05 tiene política transaccional aprobada; integración real E-08 y SQL siguen pendientes.
- **E-05:** **POLÍTICA TÉCNICA APROBADA D-109…D-122** en `18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md` (E05-A…N), con atomicidad de cancelar/restock y cita, reprogramación sin doble ocupación, idempotencia de ajustes/reintegros y conciliación externa. **PENDIENTE:** diseño físico P0-18/P0-19, API E-08 y validación autorizada.
- **E-06 APROBADO (D-123…D-134):** `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` fija avisos de solicitud/revisión/resultado, citas con constancia, incidencias y reintegro externo comprobado, sin confundir solicitudes con dinero devuelto. Restan prueba de identidad, retención, API/correo real E-07/E-08 y evidencias legales P0-25.
- **E-07 APROBADO (D-135…D-148):** identidad del invitado mediante OTP o proceso manual, privacidad de expedientes, mínimo acceso y retención por categoría. Pendientes E-08 para rutas/archivos y J-04/J-05/P0-25 para base/plazos legales.
- **E-08:** endpoints, métodos, entrada/salida, errores, capacidad y seguridad.
- **P0-25:** certificación de matriz legal vigente antes de distribución, sin asumir que un plugin instalado equivale a cumplimiento.

**Estado de salida:** E-03…E-07 aprobados en sus políticas (D-86…D-148). **Pendientes:** J-01…J-05/P0-17/P0-25, E-08, capabilities y datos por endpoint, SQL/hosting, validación jurídica y pruebas. No iniciar código.

## Recuperación del contrato original y prueba de caso (18-11-2026, OpenAPI 0.8.0-draft)

La elegibilidad E-03 usa **`OrderAgreedNotices` del pedido confirmado**, obtenido en R8-21 para personal autorizado (o R8-09 para el invitado de su pedido), desde `pc_orders.policy_version_id/privacy_version_id` y `pc_policy_versions` INMUTABLE. La política actual de R8-45/46 nunca sustituye la informada al comprador. Las condiciones abusivas o contrarias a ley no prevalecen; la lectura de términos originales no concede facultad de registrar ajustes o reintegros.

Para revisar solicitud de cambio de dirección, incidencia logística, devolución o revocación digital, A con permiso del caso recupera mediante **R8-21?case_id=...** el relato/dirección original `AdminCaseOriginalRequest` y los eventos históricos `AdminCaseEvent` paginados. No decidir únicamente a partir de `reason_code`, del último evento o de la dirección vigente. Si falta un original crítico se mantiene expediente pendiente de revisión, sin fingir resolución. El operador O nunca obtiene el detalle por conocer `case_id`. Los efectos monetarios, de stock o de entrega siguen mediante las operaciones separadas y autorizadas E-04/E-05.
