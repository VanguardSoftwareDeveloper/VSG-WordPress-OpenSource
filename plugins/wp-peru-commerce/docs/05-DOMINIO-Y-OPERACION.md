# Dominio y operación — PeruCommerce

**PRECEDENCIA VIGENTE (18-11-2026):** este archivo conserva las decisiones de dominio y ejemplos históricos; el contrato técnico fuente de implementación es **OpenAPI 0.8.0-draft (67 REST/64 rutas/134 esquemas)**, con 68 entradas R8, 56 PER, 39 tablas lógicas y cierre docs/41/42. Los textos que llaman «pendiente» a E-02…E-08 o al OpenAPI reflejan fases antiguas. Quedan por comprobar software/hosting/MP/ley/ZIP al desarrollar, no decisiones comerciales nuevas. R8-21 permite leer casos A-only y condiciones pactadas; R8-45/46 configura cuota digital y mapa; R8-12 usa idempotencia durable. No se han creado módulos.

**Estado:** contratos e invariantes propuestos para revisión; sin entidad/esquema definitivo.

## Contrato de API del dominio — E-08 APROBADO EN POLÍTICA (D-149…D-166)

El documento `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` **aprueba E08-A…R** para 52 entradas R8 (incluida una página WordPress de retorno, no REST), cotización sin reservas, checkout MP/COD, invitados de lectura y solicitudes verificadas, panel con capabilities/estado/versión, descargas protegidas y webhooks. Los **60 escenarios I8-X** no fueron ejecutados.

Los estados E-01, permisos PER E-02, dinero E-04, idempotencia E-05, avisos E-06 y privacidad E-07 siguen siendo obligatorios. **Pendientes:** formalización OpenAPI/DTO y matriz PER↔R8, SQL físico P0-18, compatibilidad MP/hosting y legislación J-01…J-05. Ningún controlador ni endpoint existe por esta aprobación.

## Objetos de negocio propuestos

- **Comercio:** identidad, RUC cuando corresponda, ubicación principal, contacto, horario y políticas.
- **Producto:** tipo (físico, digital, servicio), estado de publicación, precio y atributos.
- **Variante:** combinación válida de atributos, precio/SKU/stock cuando aplica.
- **Disponibilidad:** unidad vendible de inventario o cupo de agenda, con recurso y período.
- **Carrito/cotización:** líneas y total provisional; no es precio final autoritativo.
- **Pedido:** referencia única, comprador invitado, moneda, snapshots y totales verificados.
- **Línea de pedido:** tipo, cantidad, variante, importe y su cumplimiento independiente.
- **Retención:** reserva temporal de stock y/o horario con expiración efectiva y referencia.
- **Intento de pago:** método, solicitud idempotente, ID del proveedor, monto/moneda y estado.
- **Cobro/devolución:** movimientos verificables; ningún pago aprobado se infiere del navegador.
- **Entrega física:** modalidad, dirección/ubicación, zona y estado.
- **Derecho de descarga:** archivo autorizado, titular/identificador de acceso, límites y caducidad.
- **Cita:** servicio presencial en único local, recurso de atención único, inicio/fin, huso horario, capacidad y estado; **máximo una cita por pedido** (D-18…D-20).
- **Comprobante externo:** tipo, datos solicitados, identificación, estado y referencia de emisión externa.
- **Reclamación:** hoja y constancia, hechos, petición, respuesta, sellos temporales y evidencias.

**Estrategia de persistencia CONFIRMADA (D-43):** evaluar estructuras de WordPress para catálogo público; diseñar tablas específicas para pedidos, intentos/cobros de pago y reservas que requieran consistencia transaccional. Es una decisión de estrategia, **no** de estructura física ni un esquema autorizado. Contenido público/renderizado WordPress y operaciones interactivas Angular no justifican duplicar datos entre almacenes.

**PENDIENTE:** cardinalidades, claves, índices, esquema exacto, concurrencia, transacciones, migraciones, backups, formatos, límites, transiciones y conservación de datos.

## Decisiones de dominio confirmadas (D-18…D-26)

- Servicios **presenciales en el local**, una agenda y un recurso de atención; cada pedido contiene **a lo sumo una cita**, combinable con otros productos.
- Mercado Pago permitido en todos los tipos de carrito; **contraentrega solo si la totalidad es cobrable en un único evento presencial (D-33)**. Lugar y momento de ese cobro siguen PENDIENTES.
- Cancelaciones y reprogramaciones de citas solo operadas desde el panel del comerciante; solicitud del comprador por contacto sin autoservicio aún por detallar.
- Delivery por distrito UBIGEO con tarifa definida por el comercio; el punto del mapa no establece por sí solo la tarifa ni la cobertura.
- Correo obligatorio si se incluye digital o servicio; teléfono obligatorio si hay físico. La combinación acumula ambos requisitos.
- Perfiles administrador y operador (D-47); **operador con gestión de pedidos, productos, inventario y agenda**, pero sin acceso a credenciales ni configuración sensible. Definir matriz exacta de permisos sobre operaciones, pagos/reembolsos, datos personales y reclamaciones.
- Documento fiscal externo: boleta, factura o recibo por honorarios únicamente en los supuestos aplicables; V1 no emite mediante SUNAT.

## Contratos operativos confirmados adicionales (D-27…D-35)

- Retener stock/cupo **15 minutos desde pulsar «Pagar» y crear el intento válido en el servidor**, sin renovación automática. Seleccionar horario en ficha no crea reserva. Para contraentrega el recurso se compromete al confirmar pedido, sin retención de pago pendiente indefinida.
- Pago acreditado después de caducar retención, sin unidad o cupo disponible: registrar **incidencia** y permitir que el comerciante elija reprogramación o reintegro; no afirmar entrega/turno confirmado.
- Agenda semanal configurable, duración por servicio, pausas/buffers y fechas bloqueadas en el recurso único del local.
- Cancelar/reprogramar una cita solo por comerciante con auditoría; **reembolso es operación separada**, no reacción automática a cancelar.
- Control de stock **por variante** cuando el inventario está habilitado.
- Digitales: enlace protegido temporal **válido por 24 horas**, reemitible de forma segura al autorizado, sin cuota de descargas predeterminada; el comercio puede configurar límites.
- Un **solo cobro por pedido**; efectivo únicamente si todas las líneas admiten cobro en el **mismo evento presencial**.
- Pedidos de invitado: seguimiento por enlace privado difícil de adivinar mostrado al finalizar; correo si está disponible y asistencia telefónica manual previa identificación.
- Selección de fecha/hora de la cita en la ficha antes de carrito; verificar nuevamente la disponibilidad en checkout.

**Aún PENDIENTE:** atomicidad y secuencia técnica del intento válido al pulsar «Pagar», reintentos y fallos; acreditación del cobro contraentrega; reembolso/aprobación tardía; protocolo de reemisión y revocación de tokens; valores de agenda, política legal y compensación de cancelaciones parciales.

## Decisiones operativas complementarias confirmadas (D-36…D-41)

- **Retención Mercado Pago:** comprador pulsa «Pagar» y el backend crea intento válido → empieza retención por 15 minutos, sin renovación; el frontend no puede iniciar o renovar contadores con autoridad propia. Solicitud duplicada no debe reservar dos veces (idempotencia a diseñar).
- **Contraentrega:** al confirmar pedido válido, comprometer inmediatamente inventario y cita disponible, **independientemente** del cobro todavía pendiente; no esperar aceptación manual.
- **Agenda:** un cliente por franja del recurso único; respetar duración completa del servicio y pausas/buffers para impedir intervalos solapados.
- **Política de cancelación:** configurable por comercio, sin periodo fijo universal. Su aplicación debe respetar derechos legales y documentar en pedido la política informada al comprador; devolver dinero sigue siendo acción separada y auditada.
- **Digital:** el enlace individual de descarga expira a las **24 horas**; el derecho del comprador autorizado no expira automáticamente por ello. Reemisión segura y revocación requieren contrato; no cuota de descargas por defecto.
- **Precio:** precio regular más precio de oferta simple; cupones, combinaciones promocionales y descuentos automáticos fuera de alcance V1.

**No definidos aún:** límites de transacción al crear el intento, concurrencia/reintentos, plazos exactos de políticas comerciales, cómo acreditar evento presencial, protección para reemisión, impuestos, redondeo y representación de ofertas.

## Invariantes de dinero — E-04 APROBADO EN POLÍTICA (D-96…D-108)

**Contrato APROBADO:** `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md` con D-96…D-108. Montos internos en **enteros de céntimos PEN**, precios positivos de hasta dos decimales, cantidades enteras, total comercial por suma exacta de líneas y un delivery, recotización al pagar con snapshot inmutable, MP bruto/comisiones separados, efectivo neto aplicado, no cargo adicional automático tras aumentar tarifa, movimientos financieros inmutables y reintegros externos verificados.

**Separaciones CONFIRMADAS:** pedido y línea pactados, intento de cobro MP, dinero efectivamente acreditado, registro de efectivo y corrección, ajuste comercial, devolución externa total/parcial y documento tributario externo son hechos **diferentes**. Un evento correctivo no sobrescribe el snapshot original, y solo administrador registra devoluciones externas verificadas (D-33/D-54/D-59/D-66).

**PENDIENTES de ejecución:** cálculo fiscal y redondeo tributario J-03/P0-17/P0-25, detalles físicos de almacenamiento e índices P0-18, evidencia legal para decidir entrega no cobrable, monto debido por casos de devolución sectorial, asientos/reintegros concurrentes E-05, identidad E-07 y contrato MP E-08. La diferencia de tarifa más cara se resuelve administrativamente según D-106, sin crear otro cobro automático. Dos actores no deben aplicar dos veces el mismo ajuste.

## Evaluación por tipo de línea — E-03 aprobado en política funcional (D-86…D-95)

Consultar `16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md` para matrices física, digital y de servicio. **E03-A…J APROBADAS (D-86…D-95):** expediente común tipado, condiciones pactadas versionadas, cancelación elegible de PREPARANDO/LISTO antes de despacho, devoluciones con stock no vendible hasta revisión, remedios digitales incluso tras descarga, revocación solo legítima, no-show sin pena automática, reprogramación con constancia y cancelación ordinaria de línea completa. La cancelación excepcional de DESPACHADO D-75 exige investigación. **No cerrado:** evidencia y legalidad sectorial J-01/J-02, importes E-04, atomicidad E-05 y API E-08.

Una devolución/garantía/reclamación no equivale a cancelar línea ni a reintegro ya realizado; conservar trazabilidad. E-04/E-05/E-06 están aprobados como políticas; faltan implementación SQL/hosting, derechos por supuesto J-01/J-02/J-03, protección E-07 y REST E-08.

## Operaciones administrativas y límites de datos — E-02 aprobado (D-78…D-85)

- **Operador limitado:** lectura de pedido/contactos necesaria para atención, creación/edición de catálogo, precios/ofertas y ocultamiento no destructivo; cambios de inventario y agenda auditados, y registro de preparación, entrega y reprogramación con evidencia. Acceso a datos personales y campos fiscales exactos requiere E-07.
- **Administrador:** custodia de configuración fiscal, credenciales, políticas, Libro de Reclamaciones y roles, exportaciones masivas/archivos digitales protegidos bajo ley; exclusivamente corrige cobros y registra resultados externos de reintegro. El operador puede recibir avisos operativos, no leer expedientes completos.
- **Pedido y cambio de dirección:** operador recibe y propone; administrador aprueba si modifica tarifa/cobertura o existe dinero acreditado. Conservar snapshot inicial D-59, evento de ajuste y revalidación D-65.
- **Invitado:** seguimiento privado de lectura para un solo pedido; para reemisión/cambio/devolución necesita verificación extra. No usar el token del seguimiento como autorización irrestricta.
- **Pendientes:** tabla actor→capability→recurso/campo, E-07 identidad y privacidad, E-08 respuestas REST, E-05 control concurrente. Consultar `15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md`.

## Operación confirmada en casos límite (D-61…D-66)

- **Digital habilitado y no descargado:** no inferir «incumplido» por ausencia de evento; una solicitud de cancelación o devolución requiere evaluación de administrador según términos y derechos aplicables. Sin cancelación/reembolso automáticos (D-61).
- **Físico despachado, aún no entregado:** abrir incidencia logística con historia de envío; no cancelar ordinariamente de un clic hasta revisar la prestación y sus consecuencias (D-62).
- **Última línea física antes de despacho:** tras cancelarla cuando sea elegible, evaluar tarifa de delivery según costos/servicio efectivamente prestado y ley; si el pedido pagado requiere efecto financiero, aprobación del administrador (D-63). Recalcular importe por contrato futuro, sin elegir un valor por inferencia.
- **Operador:** la posibilidad D-60 queda restringida por D-64: solo cancelaciones elegibles con **cero dinero acreditado** y sin reintegro ni ajuste exigible, con permiso y registro de auditoría. Si alguna condición no puede verificarse, el operador no cancela.
- **Cambio de dirección tras confirmación:** nuevo dato vigente se registra en ajuste separado con referencia al original, actor y motivo; revalidar distrito UBIGEO, cobertura, tarifa y viabilidad logística, sin garantizar aceptación de cambios imposibles (D-65).
- **Reembolso externo parcial:** registrar el **resultado comprobado** por administrador con importe PEN y referencia del medio externo, conservando el pago original; no ejecutar reintegro API. Contrato de múltiples parciales/acumulados, conciliación y documentos fiscales pendiente (D-66).

Estas son políticas aprobadas, no enums, fórmulas de reintegro ni estructuras físicas autorizadas.

## Cancelación ordinaria y autorización financiera confirmadas (D-58…D-60)

- **Elegibilidad de línea:** la cancelación ordinaria se restringe a una línea con cumplimiento pendiente y jurídicamente elegible. Si la línea ya fue entregada, el servicio prestado o la prestación consumada, derivar a **devolución, reclamación o incidencia**, no alterar retroactivamente su cumplimiento para fingir una cancelación pendiente.
- **Inmutabilidad del pedido confirmado:** sus datos originales, cantidades, importes, productos/variantes y condiciones pactadas no se sobrescriben. Un cambio requiere **evento/ajuste relacionado** con actor, momento, motivo, referencia original y efectos. Mantener historia íntegra y distinguir ajuste de reemplazo de snapshot.
- **Autorización y dinero:** si se cancela una línea de pedido **pagado** y hay impacto financiero, solo administrador autoriza el ajuste. La regla posterior D-64 limita al operador a cancelaciones elegibles **sin dinero acreditado, ni reintegro ni ajuste**, siempre con permiso y auditoría. Reembolsos, si proceden, son externos (D-54/D-66); no se generan automáticamente.
- **Aspectos pendientes:** definición exacta de «cumplimiento pendiente» por tipo, cálculo de impacto y asignación de delivery/tributos, derechos legales, liberación/reposición de stock/cupo, movimientos y conciliación, eventos duplicados, catálogo de estados y permisos finos.

## Decisiones confirmadas de estados y cancelaciones por línea (D-55…D-57)

- El **estado administrativo del pedido**, el estado de cada **intento/movimiento de pago** y el **cumplimiento de cada línea** son independientes. Un cobro aprobado no confirma entrega ni consumo de servicio.
- Se permite **cancelar líneas individuales pendientes de cumplimiento y elegibles** de pedidos mixtos (D-56/D-58), manteniendo las restantes y su historial inmutable (D-59). Se registra la decisión y su ajuste auditable, con aprobación de administrador si un pedido pagado requiere efecto financiero (D-60); el estado global no confunde cancelación parcial con total.
- El **cobro original sigue siendo uno por pedido** (D-33). Cuando una cancelación parcial obliga a devolver dinero, el reintegro se tramita fuera del plugin y solo el administrador registra su resultado comprobado (D-54).
- El artículo **sin variantes** tiene precio final y oferta manual propios. Si tiene variantes, cada variante vendible exige precio/condición de oferta explícitos propios: no se hereda automáticamente el precio del artículo base.
- **PENDIENTES:** estados/transiciones exactos y definición de pendiente por tipo de artículo, permisos específicos, cálculo del efecto financiero, prorrateo de entrega/cargos/tributos, liberación/reposición de recursos, ajustes monetarios y consecuencias de devolución parcial.

## Decisiones confirmadas para variantes, correcciones y devoluciones (D-52…D-54)

- **Producto simple y variante vendible (D-57):** el producto simple tiene precio final/oferta propios; cada variante vendible tiene precio final y oferta independiente y explícitos, sin herencia automática. Cada línea referencia exactamente el tipo y variante concreta (si corresponde), preservando snapshot del precio aplicado; cambios posteriores no modifican un pedido.
- **Cobro efectivo original:** registro por operador o administrador con permiso; el **original es inmutable** para fines de auditoría.
- **Corrección:** exclusivamente administrador, mediante evento de ajuste/reversión enlazado al cobro fuente, motivo, actor, fecha y montos; **nunca borrar ni sobrescribir el original**. Sus efectos contables y de saldo siguen pendientes de contrato.
- **Reembolso V1:** la devolución del dinero se realiza **fuera** de PeruCommerce, en Mercado Pago u otro medio correspondiente. Solo el administrador registra el resultado que pudo verificar, indicando medio, referencia, importe, fecha y evidencia apropiada. Una solicitud o intención no equivale a un resultado confirmado. No crear llamada API de ejecución de reembolso desde el plugin en V1.
- **Estados y excepciones:** definir todavía pagos con devolución parcial, reintentos, doble registro, contracargos, diferencias de monto, efecto sobre stock y acceso digital, conciliación con proveedor y controles de permisos.

## Operaciones confirmadas: precio, oferta y efectivo (D-49…D-51)

- **Precio final de entrada**: almacenar por cada variante vendible su precio final y, cuando aplique, su oferta manual independiente en PEN (D-52); conservar snapshot del precio efectivamente aplicado al confirmar pedido. Cálculo autoritativo de backend y desglose tributario contextual pendientes.
- **Oferta manual**: solo activa/inactiva por acción de personal autorizado; sin reglas automáticas de inicio/fin ni acumulación promocional.
- **Acreditación de efectivo**: operador autorizado registra una acción auditable por cobro recibido, con identidad, importe, momento, método y referencia de pedido; validar estado y prevenir duplicación. Separa el evento de acreditación del estado administrativo del pedido. **Reembolso/corrección financiera** es una acción distinta, no permitida al operador.
- **Pendiente**: diseño de idempotencia, diferencias de importe, recuperación de errores, bitácora, autorizaciones finas, correcciones y su tratamiento contable/tributario.

## Regla monetaria y de autorización confirmada (D-45…D-48)

- **Moneda única V1:** PEN en precios, ofertas, gastos de entrega, totales, órdenes y movimientos de pago. Cada registro monetario debe preservar su moneda; reglas de precisión/redondeo son pendientes de especificación, no se presuponen operaciones de conversión.
- **Precios finales:** la cotización autoritativa del servidor produce el total que se comunica antes de confirmar. Distintos regímenes/operaciones exigen una matriz fiscal aprobada; no deducir IGV ni documentos por generalización.
- **Rol operador:** gestionar pedidos, catálogo, existencias y agenda según matriz de capacidades todavía pendiente; no acceso a credenciales, integraciones ni configuración sensible. Validar en backend, no solo mediante ocultamiento de controles.
- Versiones objetivo: estables y mantenidas de WordPress/PHP/Angular y demás dependencias; requisitos técnicos mínimos y arquitectura física deberán verificarse antes de implementar.

## Invariantes mínimos recomendados

1. Todos los importes del pedido se recalculan en backend con moneda y precisión controladas.
2. No se sobrevende unidad física ni cupo de servicio por solicitudes concurrentes.
3. Un intento de checkout repetido no crea pedidos/cobros duplicados.
4. Todo cambio de precio se conserva como snapshot en pedidos históricos.
5. Ninguna descarga protegida se concede por pago no verificado, salvo regla manual expresa aprobada.
6. Cada pago se reconcilia con importe, moneda, referencia e identidad del comercio.
7. Cancelación/reembolso conserva la bitácora; no borra transacciones anteriores.
8. Módulo desactivado no elimina ventas, reclamos ni reservas históricas.
9. Datos no públicos solo se muestran con permiso o enlace de acceso limitado.
10. Una cita y la retención de stock pueden vencer independientemente del proceso automático programado.

## Estados y transiciones — E-01 APROBADO (D-67…D-77)

**Fuente de verdad funcional:** [14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md](14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md). Se aprobaron los vocabularios y transiciones de pedido, intento de Mercado Pago, retenciones, físico, digital, línea de servicio y entidades relacionadas; **no** usar ejemplos viejos ni derivar un estado global mixto.

- **Pedido:** PENDIENTE_PAGO, CONFIRMADO, FINALIZADO, CANCELADO, VENCIDO. Pago/acreditación/entrega/cancelación parcial/incidencias son ejes o derivados independientes.
- **Intento MP:** CREADO, PENDIENTE, APROBADO, RECHAZADO, CANCELADO_PROVEEDOR, REQUIERE_CONCILIACION. Retención local vencida no cancela MP ni altera acreditación verificada.
- **Hold:** VIGENTE, COMPROMETIDA, VENCIDA, LIBERADA; reloj del servidor con 15 minutos, sin renovación automática.
- **Físico:** PENDIENTE, PREPARANDO, LISTO, DESPACHADO, ENTREGADO, CANCELADO; incidencia logística separada y sin máquina de envío adicional V1.
- **Digital:** BLOQUEADO, HABILITADO, CANCELADO, REVOCADO; descarga y expiración de token son eventos, no estados terminales del derecho.
- **Servicio:** PENDIENTE_CONFIRMACION, CONFIRMADA, EXPIRADA, REALIZADA, CANCELADA, NO_ASISTIO; no ocupar recurso como cita efectiva antes de confirmar. Reprogramación añade evento sin duplicar cita activa.
- **Excepciones aprobadas:** pedido VENCIDO recuperable solo tras pago tardío, nueva disponibilidad real y resolución auditada; físico despachado puede cancelarse excepcionalmente tras investigación y autorización conforme a ley; FINALIZADO exige líneas resueltas y sin operaciones abiertas al cierre, con reclamaciones posteriores separadas.

**Aún PENDIENTES:** capabilities y endpoints E-08, matriz jurídica J-01…J-05/P0-17/P0-25, SQL/hosting/índices/migraciones, correo real/constancias y ZIP/retención. **E-01…E-07 tienen políticas aprobadas**, pero no se autorizaron código ni pruebas.

## Datos personales, tokens y conservación — E-07 APROBADO (D-135…D-148)

El contrato `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` **APRUEBA E07-A…N (D-135…D-148)**, con matriz de datos por finalidad/actor y 42 escenarios documentales no ejecutados. Seguimiento secreto de 256 bits y 90 días consulta **solo su pedido**; cambios sensibles por OTP o proceso manual, sin crear cuentas ni correo obligatorio en físicos puros. Digital usa token independiente 24 h sin destruir derecho. Retención y acceso administrativo se sujetan a normativa y permisos.

Derecho digital no vence con token de 24 h. **E-07 fija** TTL seguimiento 90 días y OTP 8 dígitos/10 min/5 intentos, minimización, ARCO mediante WP más canal telefónico, protección de secretos, retención por categoría y evaluación de incidentes. **Pendientes:** mecanismos reales E-08/P0-18, matriz ANPD J-04/J-05/P0-25 y plazos de conservación legal/ZIP P0-26.

## Eventos de comunicación y seguimiento — E-06 APROBADO (D-123…D-134)

El contrato `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` **aprueba 12 decisiones E06-A…L D-123…D-134**, 26 eventos N6 y 38 escenarios no ejecutados. Outbox idempotente E-05 y política E-06 separan evento de negocio de envío aceptado; correo fallido o reenviado no modifica estados E-01 ni cobros. Comprador físico puede contar solo con teléfono; el panel persiste alertas aunque falle email.

Distinguir **evento de negocio**, intención de envío, transporte aceptado y recepción real, además de evidencia jurídica de reclamación. **E-06 está aprobado en política de canales/avisos/plantillas**; siguen pendientes permisos por campo y retención E-07, API/correo E-08, y constancia/reglamentación P0-25.

## Concurrencia, disponibilidad y recuperación E-05 — POLÍTICA TÉCNICA APROBADA (D-109…D-122)

El contrato `18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md` aprueba como **política técnica D-109…D-122** las 14 opciones E05-A…N: InnoDB, locks de variante/recurso-día, creación MP en fases, caducidad efectiva, idempotencia durable, webhook verificado/inbox, contraentrega atómica, cancelación/restock, registro único de efectivo/reembolso externo y recuperación local. Los 48 escenarios C5-X **no están ejecutados**.

**Invariantes ya CONFIRMADOS:** 15 minutos desde intento válido (no de selección en ficha); stock controlado por variante y una cita sin solapamiento; pago tardío sin recursos genera incidencia; contraentrega compromete recursos al confirmar; dinero pagado, entrega y pedido son estados separados; no duplicar reintegros ni borrar movimientos. **Pendientes:** tablas, índices y orden de locks físico P0-18; motor/versión/hosting P0-19, retención y privacidad E-07, proveedor E-08. La **admisión interna E05-D aprobada** antes de MP no es una reserva comercial ni extiende los 15 minutos del hold.

## Stock y horarios: casos que hay que resolver

| Caso | Resultado exigible | Diseño pendiente |
| --- | --- | --- |
| Última unidad solicitada en dos navegadores | Solo uno obtiene retención efectiva | Mecanismo transaccional |
| Último turno seleccionado por dos compradores | Solo una cita por franja y recurso, sin solapamiento de duración/buffers | Mecanismo transaccional |
| Cliente pulsa «Pagar», se crea intento válido y luego abandona | Retención de 15 min sin renovación desde creación válida; caduca efectivamente | Idempotencia, limpieza y reintentos |
| Mercado Pago aprueba después de vencer retención | Incidencia si stock/cupo indisponible; comerciante resuelve reintegro o reprogramación | Ejecución de devolución, comunicación y conciliación |
| Pedido contraentrega confirmado | Stock y cita comprometidos inmediatamente, con pago aún pendiente | Operación atómica de confirmación y posterior acreditación del cobro |
| Pedido mixto cancela solo una línea | No cancelar otras líneas; ante digital habilitado sin descarga requiere evaluación, despachado físico requiere incidencia y última línea física exige revisión de delivery | Elegibilidad, ajuste de costos y reglas de derecho aplicables |
| Servicio reprogramado | Evitar doble ocupación de origen/destino | Ventana/reglas |
| Disputa o reembolso | Revocar/ajustar derechos y stock cuando corresponda | Reposición y resolución |
| Descarga tras 24 horas del enlace | El token ha expirado; no entregar con token vencido y permitir reemisión segura al comprador autorizado | Identificación, revocación y derechos tras reembolso |
| Cambio de tarifa de delivery | Preservar coste pactado de pedido confirmado | Snapshot de zona/precio |

## Matriz de pago/cumplimiento por tipo — PENDIENTE

- Físico con Mercado Pago: confirmar por proveedor; liberar cumplimiento después de aprobación o regla de negocio.
- Físico con efectivo: al confirmar pedido válido comprometer inmediatamente stock/cita elegibles; crear obligación de cobro pendiente y registrar manualmente su acreditación posterior.
- Digital: descarga protegida después de pago confirmado; en pedido único con cobro manual presencial elegible, liberar descarga solamente cuando el comerciante registre y acredite el cobro, con trazabilidad. Punto de cobro y protocolo de acreditación pendientes.
- Servicio: reserva de agenda temporal, luego confirmación según política de pago/cobro.
- Mixto: Mercado Pago para cualquier combinación; **un solo cobro por pedido**, y contraentrega solo si todas las líneas pueden cobrarse **en el mismo evento presencial**. Pendientes lugar/momento del evento, conciliación y compensaciones por cancelación parcial.

**No dividir el cobro inicial del pedido** sin nueva autorización. **Cancelaciones de líneas individuales sí aprobadas (D-56)**; antes de codificar, definir elegibilidad, prorrateos, ajustes, derechos de devolución, montos de reintegro externos y auditoría.

## Operación administrativa

El comerciante debe poder conocer qué acción tomar ante pedidos con pago pendiente, pago confirmado sin stock/cupo, descargas bloqueadas, fallos de notificación y reclamaciones con plazo de respuesta. Registro de acciones de operador, motivo, fecha, antes/después, y acceso proporcional.

**E-02 APROBADO EN POLÍTICA:** documento 15, 56 operaciones, D-78…D-85. **Pendiente técnico:** capacidades/campos por rol, autenticación y E-03…E-08 para auditoría, datos personales, reintegros, notificaciones, conservación y exportación. **Corregir cobros y registrar resultados comprobados de reembolso son facultades exclusivas del administrador (D-53/D-54).**

## Riesgo operativo principal

El alcance V1 incluye **tres tipos de cumplimiento, stock y agenda**, con estados separados. **E-01 aprobado** en `14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md`; el documento 12 mantiene disparadores generales y el 13 escenarios de error. Antes del código faltan **E-02…E-08**, el cierre de P0 y autorización específica; no tomar las propuestas de esos subcontratos como aprobación.
