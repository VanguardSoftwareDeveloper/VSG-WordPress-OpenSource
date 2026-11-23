**FUENTE VIGENTE D-200/DOC-QA31 (18-11-2026):** no quedan decisiones de producto abiertas para P-A tras la rectificación del ciclo de vida de expediente físico parcial. R8-69 abre, R8-70 rechaza o registra retiro verificado **sin efectos de dinero/stock**, R8-26 ejecuta ajuste parcial y cierra `closed_adjusted` atómicamente. OpenAPI **0.12.0-draft: 69 REST/66 rutas/138 esquemas**, 70 entradas R8 contando R8-18 página WP, 200 decisiones, 56 PER y 39 tablas lógicas. Las referencias a contratos 0.5…0.11 y D-189…D-199 más abajo pertenecen al historial, no autorizan controles incompatibles ni reabren PRE-07. P-B/P-C/P-D conservan permiso y verificaciones separados. No existe código ni pruebas ejecutadas.

# Registro HISTÓRICO de decisiones pendientes — PeruCommerce

**RECTIFICACIÓN HISTÓRICA D-199/DOC-QA30 (18-11-2026; superada por D-200):** quedó documentada la apertura administrativa del caso físico de ajuste parcial para comprador atendido por teléfono: `R8-69 POST /admin/orders/{id}/cases` solo A, `perucommerce_orders_read` Y `perucommerce_orders_financial_adjust`, verificación manual proporcional e idempotencia, sin cambio de stock/dinero. El resultado `case_id` permite consulta R8-21 y decisión separada R8-26. **OpenAPI 0.11.0-draft: 68 REST/65 rutas/136 DTO, 69 entradas R8 (incluida R8-18), 56 PER, 39 tablas y 199 D.** P-A cerrada documentalmente, P-B no autorizada y P-C/P-D sin ejecución. Los párrafos de revisiones anteriores más abajo son históricos, no huecos reabiertos.

**CIERRE HISTÓRICO (2026-11-17, D-198):** **NO QUEDAN DECISIONES DE PRODUCTO PRE-CÓDIGO PENDIENTES tras la auditoría rectificativa**. D-195 congela Figma 34 frames como baseline de ingeniería, D-196 elige recurso INEI completo 2023/edición 2022 como referencia de desarrollo (sin validación para producción), D-197 declara **P-A cerrada**. Las fuentes autoritativas son [docs/41 — cierre de Fase 0](41-CIERRE-FASE-0-BASELINE-PRECODIGO-V1.md) y [docs/42 — auditoría de contratos](42-AUDITORIA-Y-CORRECCIONES-CONTRATOS-PRECODIGO-V1.md), con **198 D, 67 REST/64 rutas/134 DTO y 39 tablas**. Las celdas y párrafos posteriores que llaman «pendiente» a una elección describen cortes anteriores, **no** autorizan reabrirla. Siguen abiertos únicamente resultados empíricos de integración/comercio/release y el permiso explícito de código P-B; esos asuntos no pueden certificarse antes de desarrollar.

**Corte:** 2026-11-17 · **Regla:** propuestas aún no aprobadas no son decisiones. Las nueve opciones A P0-17…P0-26 fueron **aprobadas como elecciones** por el propietario el 2026-11-17 (D-167…D-175); sus contratos físicos y verificaciones siguen pendientes. Priorizar esos entregables antes de Figma definitivo o código. **D-188 (2026-11-17): se aprobó excluir PER-09 de V1; no es bloqueo de alcance ni requiere ruta nueva.**

**Índice operativo del corte (2026-11-17):** `docs/34-MATRIZ-CIERRE-FASE-0-Y-VALIDACIONES-PENDIENTES-V1.md` consolida **19 puertas F0**. Este registro conserva la trazabilidad histórica; ante dudas sobre estado vigente consultar docs/34 y la decisión D específica, sin tratar documentación como validación real. No es otro plan de implementación ni requiere repetir elecciones aprobadas.

**CORTE HISTÓRICO antes de D-198 — cierre PRE-01…06 del 17-11-2026:** D-189…D-194 ratificaron dos GET privados R8-65/66, caducidades técnicas, las 16 reglas lógicas DB-A, aplicabilidad legal por comercio, fuente INEI y canal GPL. **OpenAPI 0.4.0-draft: 65 REST, 62 rutas y 115 DTO**; los antiguos 63/60/113 son historia previa a D-189. PRE-07 dispone de **34 frames Figma editables** en https://www.figma.com/design/qJaGwnjfaI35gYDjFwzBub pero aún no aprobados visualmente ni exhaustivos para las excepciones. **La matriz de estado consolidado está en docs/40**. El código sigue no autorizado; integración MP, dataset INEI, pruebas de seguridad, ZIP y legalidad por instalación no se encuentran verificados.

## Ronda P0-01…P0-09: CERRADA (2026-11-16)

Las nueve opciones A fueron **confirmadas expresamente por el propietario**. No volver a solicitarlas salvo cambio de alcance.

| ID | Decisión confirmada | Registro |
| --- | --- | --- |
| P0-01 | Solo servicios presenciales en el local | D-18 |
| P0-02 | Una agenda y un recurso de atención | D-19 |
| P0-03 | Máximo una cita por pedido junto con otros productos | D-20 |
| P0-04 | Mercado Pago para todos; efectivo solamente cuando todo pueda cobrarse presencialmente | D-21 |
| P0-05 | Cancelaciones/reprogramaciones solo desde el panel del comerciante | D-22 |
| P0-06 | Zonas de reparto por distritos UBIGEO con tarifa del comerciante | D-23 |
| P0-07 | Correo para digitales/servicios; teléfono para físicos | D-24 |
| P0-08 | Administrador y operador de permisos limitados | D-25 |
| P0-09 | Registro externo de boleta, factura y recibo por honorarios según aplicación tributaria | D-26 |

La decisión de **recibo por honorarios** no implica ofrecerlo a comerciantes que no pueden emitirlo. La matriz fiscal exacta sigue pendiente.

## Ronda P0-10…P0-16, P0-22 y UX-P01: CERRADA (2026-11-16)

El propietario confirmó expresamente **todas las opciones A** de la ronda siguiente. No volver a consultarlas salvo solicitud de cambio de alcance.

| Origen | Decisión confirmada | Registro |
| --- | --- | --- |
| P0-10 | Retenciones de stock y cita 15 minutos desde inicio de intento de pago; no renovación automática | D-27 |
| P0-11 | Pago tardío sin stock o turno disponible: incidencia y resolución por comerciante (reprogramación o reintegro), sin sustitución automática | D-28 |
| P0-12 | Horario semanal configurable, duración por servicio, buffers y fechas bloqueadas para el recurso único | D-29 |
| P0-13 | Cancelación/reprogramación manual auditada; reembolso es una operación separada | D-30 |
| P0-14 | Stock por variante vendible cuando el comerciante habilite inventario | D-31 |
| P0-15 | Descargas protegidas con enlace temporal; sin cuota por defecto y límites opcionales configurables | D-32 |
| P0-16 | Un único cobro por pedido, y efectivo solo si el pedido completo puede cobrarse en un mismo evento presencial | D-33 |
| P0-22 | Seguimiento del pedido sin cuenta por enlace privado no adivinable; mostrar al completar y enviar por correo cuando exista, o comunicar manualmente al comprador identificado por teléfono | D-34 |
| UX-P01 | Fecha/hora en la ficha del servicio antes de agregar al carrito; revalidar en checkout | D-35 |

**Distinciones indispensables:**
- Elegir un turno en la ficha **no lo reserva**. La retención temporal comienza al iniciar el intento de pago. La ficha debe advertir que la disponibilidad puede cambiar.
- «15 minutos» es duración de la retención iniciada desde un evento que debe definirse exactamente. No garantiza que el proveedor externo cobre dentro de ese plazo ni cancela una transacción en Mercado Pago. El destino del pago tardío sin recursos es una incidencia.
- La devolución no ocurre automáticamente al cancelar o reprogramar: existe operación separada y auditable.
- No imponer número fijo de descargas por defecto **no significa** que un enlace temporal no venza, ni que cualquier persona pueda descargar.
- Un pago por pedido implica un evento único de cobro presencial cuando se elige efectivo; las liquidaciones y reembolsos posteriores no equivalen a dividir el cobro original.

## Ronda de seis ajustes funcionales: CERRADA (2026-11-16)

El propietario respondió **«Todas A»**; quedan aprobadas las seis opciones recomendadas de la ronda de retenciones, contraentrega, agenda, cancelación, enlaces y ofertas. No volver a pedir esas elecciones salvo cambio explícito.

| Ajuste aprobado | Decisión confirmada |
| --- | --- |
| Disparador de reserva de 15 min: pulsar «Pagar» y crear intento de pago válido en servidor | D-36 |
| En contraentrega, compromiso inmediato de stock/cita al confirmar pedido (cobro aún pendiente) | D-37 |
| Agenda de recurso único: un cliente por franja, sin solapamientos de duración/buffers | D-38 |
| Condiciones de cancelación configuradas por comercio, respetando derechos legales; sin política fija universal de 24 h | D-39 |
| Enlace individual de descarga protegido válido durante 24 h, reemisión segura al autorizado | D-40 |
| Precio regular y precio de oferta simple; cupones y promociones automáticas fuera de V1 | D-41 |

**No resuelven** atomicidad/idempotencia al crear intento, reembolso, periodos mínimos legales, verificación de identidad para reemisión ni reglas fiscales de redondeo.

## Ronda de tres decisiones de arquitectura: CERRADA (2026-11-16)

El propietario confirmó expresamente **todas las opciones A**:

| Decisión | Registro | Estado |
| --- | --- | --- |
| WordPress/PHP para catálogo público indexable; Angular para carrito, checkout y panel | D-42 | CONFIRMADO |
| Evaluar estructuras nativas WordPress para catálogo y diseñar tablas propias para pedidos, pagos y reservas | D-43 | CONFIRMADO como estrategia, NO como esquema |
| Leaflet con proveedor cartográfico intercambiable y entrada manual de dirección como respaldo | D-44 | CONFIRMADO como estrategia, NO proveedor concreto |

**Aclaración:** esta ronda no cierra el modelo físico de datos, las versiones mínimas ni la selección real de proveedor cartográfico. Los P0-18/P0-20/P0-21 siguen abiertos en esos detalles, aunque sus alternativas arquitectónicas ya no deben preguntarse de nuevo.

Referencias oficiales de los conceptos: https://developer.wordpress.org/block-editor/getting-started/fundamentals/static-dynamic-rendering/ ; https://angular.dev/guide/elements ; https://operations.osmfoundation.org/policies/tiles/

## Ronda comercial y de compatibilidad: CERRADA (2026-11-16)

El propietario confirmó expresamente **todas las opciones A** de moneda, precio/tributación, permisos del operador y compatibilidad:

| Decisión | Registro | Alcance aprobado |
| --- | --- | --- |
| Solo soles peruanos (PEN) en V1 | D-45 | Moneda única; no conversión ni multimoneda |
| Precio final visible y tratamiento fiscal por régimen/operación | D-46 | Transparencia de total; sin motor fiscal general; reglas legales específicas pendientes |
| Operador gestiona pedidos, catálogo, stock y agenda; sin credenciales ni configuración sensible | D-47 | Responsabilidades principales confirmadas; matriz granular de permisos y datos personales pendiente |
| Priorizar versiones estables/mantenidas de WordPress/PHP/dependencias | D-48 | Política de compatibilidad aprobada; versiones mínimas, requisitos y matriz deben verificarse antes de fijarlos |

No volver a preguntar estas cuatro elecciones salvo cambio explícito. La aprobación de una **política** no verifica implementaciones, versiones ni cumplimiento fiscal.

## Ronda de tres decisiones de precios y cobros: CERRADA (2026-11-16)

El propietario respondió **«Todas A»** a las tres propuestas. Se cierran las elecciones sin fijar por inferencia sus subcontratos de implementación:

| Decisión | Referencia | Confirmación |
| --- | --- | --- |
| Ingreso de **precio final de venta** en PEN por el comerciante; información fiscal contextual, sin motor de cálculo neto + impuesto general | D-49 | CONFIRMADO |
| Precio de oferta activado y desactivado **manualmente**; sin fechas programadas en V1 | D-50 | CONFIRMADO |
| Operador autorizado **registra efectivo recibido con auditoría**, no efectúa reembolsos ni modifica credenciales | D-51 | CONFIRMADO |

**No inferir** que el precio final carece de tributos, que una oferta puede cambiar retroactivamente un pedido o que registrar un cobro manual autoriza una devolución. Requisitos abiertos: moneda/redondeo e impuestos por régimen, idempotencia y ajustes del cobro, permisos detallados, auditoría y conservación de evidencias.

## Ronda de variantes y correcciones financieras: CERRADA (2026-11-16)

El propietario confirmó expresamente **todas las opciones A**:

| Decisión | Referencia | Estado |
| --- | --- | --- |
| Precio final y oferta manual independientes para cada variante vendible | D-52 | CONFIRMADO |
| Corrección de cobros en efectivo exclusivamente por administrador, con asiento original preservado y auditoría | D-53 | CONFIRMADO |
| Reembolso ejecutado fuera del plugin en Mercado Pago/medio correspondiente; administrador registra solo resultado comprobado | D-54 | CONFIRMADO |

La aprobación **no** resuelve la estructura de movimientos financieros, reembolsos parciales, conciliación, evidencia externa ni notas de crédito. No reabrir las tres elecciones salvo cambio de alcance.

## Ronda de estados y pedidos mixtos: CERRADA (2026-11-16)

El propietario respondió **«Todo A»**, confirmando estas tres opciones:

| Decisión | Referencia | Estado |
| --- | --- | --- |
| Estado administrativo del pedido, estado de pago y cumplimiento de cada línea separados | D-55 | CONFIRMADO |
| Cancelación de artículos individuales elegibles, ajustes auditados y reembolsos externos si corresponden, preservando las demás líneas | D-56 | CONFIRMADO |
| Producto simple con precio final/oferta propios; variante con precio final/oferta explícitos e independientes, sin herencia automática | D-57 | CONFIRMADO |

Estas aprobaciones no definen por sí solas las enumeraciones de estados, su máquina de transiciones, la cancelación de líneas ya cumplidas, el prorrateo de delivery, ni los movimientos financieros derivados. El reembolso sigue siendo **externo (D-54)**; no se aprueba un motor de devoluciones automáticas.

## Ronda de reglas de cancelación: CERRADA (2026-11-16)

El propietario respondió **«Todo A»** a las tres alternativas propuestas. Quedan expresamente aprobadas:

| Decisión | Registro | Estado |
| --- | --- | --- |
| Cancelación ordinaria únicamente de líneas pendientes de cumplimiento y elegibles; las cumplidas se atienden por devolución, reclamación o incidencia según corresponda | D-58 | CONFIRMADO |
| Pedido confirmado conserva datos/snapshots originales inmutables; cambios posteriores mediante ajustes/eventos auditables, no ediciones destructivas | D-59 | CONFIRMADO |
| En pedidos pagados, cancelaciones con efecto financiero autorizadas solo por administrador; operador gestiona cancelaciones no financieras dentro de sus permisos | D-60 | CONFIRMADO |

**No reabrir estas elecciones.** Siguen sin decidirse los nombres/transiciones exactos de estados, cómo se detecta y calcula el impacto financiero, permisos por evento, distribución de delivery/tributos, restauración de stock/cupos y criterios legales por tipo de prestación. Ninguna regla de interfaz puede sustituir esos contratos.

## Ronda de excepciones S-01…S-06: CERRADA (2026-11-16)

El propietario confirmó expresamente **«Todo A»**. Las seis propuestas S-01…S-06 de `13-MATRIZ-DE-HUECOS-Y-ESCENARIOS.md` quedaron registradas como reglas **D-61…D-66**:

| Origen | Política aprobada | Decisión |
| --- | --- | --- |
| S-01 | Digital habilitado sin descarga: revisión de administrador según derechos, sin cancelación/reintegro automáticos | D-61 |
| S-02 | Físico despachado, no entregado: incidencia y revisión logística en lugar de cancelación inmediata | D-62 |
| S-03 | Cancelar última línea física antes de despacho: revisar delivery por prestación real y derechos; aprobar ajuste financiero si corresponde | D-63 |
| S-04 | Operador cancela únicamente sin dinero acreditado ni reintegro/ajuste financiero, con permiso y auditoría | D-64 |
| S-05 | Datos de dirección tras confirmar: cambio mediante ajuste auditable con validación de cobertura/costo | D-65 |
| S-06 | Resultado comprobado de reembolso externo parcial registrado por administrador, preservando cobro original y sin API de devolución | D-66 |

**Alcance no resuelto:** importes concretos, fórmula del delivery, aplicación de derechos a digitales, logística, seguridad y costo de la dirección cambiada, identidad/evidencia del reembolso externo, acumulados y conciliación. No inferirlos de estas seis aprobaciones.

## E-01 — estados y transiciones: CERRADO FUNCIONALMENTE

El propietario **aprobó expresamente las once opciones A** (E01-A…E01-G y E01-H1…E01-H4) del documento `14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md`. Se registraron en `01-DECISIONES-Y-ESTADO.md` como **D-67…D-77**. Quedan aprobados el vocabulario de estados, las transiciones funcionales O/MP/H/F/G/V, las prohibiciones y la separación de ejes y eventos.

**No implica** aprobación de permisos exactos, reglas legales por operación, fórmulas de monto/devolución, atomicidad/reintentos, notificaciones, privacidad ni contratos de API: **E-02…E-08 permanecen abiertos**, al igual que los nueve P0 en sus partes restantes. No se ha autorizado implementación.

## E-02 — política de permisos: CERRADA FUNCIONALMENTE (2026-11-17)

El propietario confirmó expresamente **«Todas A en E-02»**. Las ocho alternativas A del documento `15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md` se registraron como **D-78…D-85**:

| Origen | Decisión | Política aprobada |
| --- | --- | --- |
| E02-A | D-78 | Operador solo ve pedidos/contacto necesarios; datos fiscales completos y secretos no disponibles por defecto |
| E02-B | D-79 | Operador crea/edita productos, precios/ofertas y oculta, sin borrar definitivamente artículos con pedidos |
| E02-C | D-80 | Ajustes de inventario y agenda con motivo y auditoría; sin parámetros fiscales, credenciales o gestión de roles |
| E02-D | D-81 | Preparación/entrega y reprogramación de citas con evidencia y permisos, sin reintegros ni cancelación con cobro |
| E02-E | D-82 | Exportación masiva y acceso a archivos digitales protegidos solo administrador bajo política de datos |
| E02-F | D-83 | Operador recibe y propone cambio de dirección; administrador aprueba si afecta costo, cobertura o existe cobro |
| E02-G | D-84 | Fiscalidad, credenciales, Libro de Reclamaciones, políticas y roles solo administrador; avisos operativos al operador |
| E02-H | D-85 | Token privado del invitado limitado a consultar su pedido; reemisión/cambio/devolución exigen verificación adicional |

**Cierre funcional ≠ autorización de implementación:** queda pendiente determinar nombres efectivos de *capabilities* WordPress, acceso por fila/campo, autenticación, CSRF, recuperación de tokens, privacidad E-07 y REST E-08. E-03 está aprobado funcionalmente (D-86…D-95), pero su elegibilidad legal por sector J-01/J-02/P0-25 y los efectos técnicos E-04…E-06 siguen abiertos. No conceder acceso general del operador a datos personales, exportaciones o reclamaciones.

## E-03 — elegibilidad y remedios: CERRADA EN POLÍTICA FUNCIONAL (2026-11-17)

El propietario confirmó expresamente **«Todas A en E-03»**. Las diez opciones E03-A…J del documento `16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md` quedaron registradas como **D-86…D-95**. Se mantienen matrices físicas, digitales y de servicios, 25 escenarios E3-X y referencias oficiales (Ley 29571, Ley 32495, DL 1729, SUNAT).

| Opción aprobada | Decisión | Política |
| --- | --- | --- |
| E03-A | D-86 | Clasificación de solicitud y canal de remedio |
| E03-B | D-87 | Condiciones históricas inmutables y evidencia |
| E03-C | D-88 | Cancelación elegible PREPARANDO/LISTO sin despacho |
| E03-D | D-89 | Devolución de ENTREGADO y stock no vendible hasta revisión |
| E03-E | D-90 | Revisión de digital HABILITADO sin descarga en expediente común tipado |
| E03-F | D-91 | Reclamos de digital descargado por defecto/acceso |
| E03-G | D-92 | Revocación digital solo con causa y decisión auditable |
| E03-H | D-93 | Inasistencia sin cargos automáticos; revisión en expediente |
| E03-I | D-94 | Reprogramación con cupo/historial/constancia; canal E-06 pendiente |
| E03-J | D-95 | Cancelación ordinaria de línea completa; cantidad parcial por expediente/ajuste |

**Cierre de políticas funcionales, no cierre jurídico:** D-86…D-95 no autorizan a denegar derechos legales, efectuar reintegros automáticamente, crear un módulo separado por caso ni cancelar por cantidad sin contrato. J-01/J-02, E-04…E-08 y P0-25 continúan pendientes.

**PENDIENTE de verificación legal P0-25:** obligaciones por sector/producto, reglamentación definitiva posterior a la consulta de 2026 sobre DL 1729, Libro de Reclamaciones, notas de crédito/documentos externos y supuestos reales de desistimiento. No programar plazo universal desde proyectos de ley. **La aprobación de E-03 no certifica cumplimiento legal.**

## E-04 — política monetaria comercial: CERRADA FUNCIONALMENTE (2026-11-17)

El propietario aprobó expresamente **«Todas A en E-04»**. Las 13 opciones E04-A…M del documento `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md` se registraron como **D-96…D-108**, con criterios A-112…A-124. Los 32 escenarios M4-X son documentales, no pruebas ejecutadas.

**Bloqueos y restricciones:**
- **CERRADO FUNCIONALMENTE (D-96…D-108):** enteros de céntimos PEN, precio efectivo positivo/cantidad entera, subtotal exacto, recotización y snapshot, una entrega y una tarifa por pedido, delivery histórico y evaluación legal cuando se cancela última línea, ajustes por precio pactado, MP bruto/comisión separada, efectivo neto, dirección más cara sin cargo automático, libro financiero inmutable y referencias de comprobante externo.
- **J-03/P0-17/P0-25 NO CERRADOS:** matriz fiscal y redondeos tributarios por régimen/operación, delivery y notas de crédito externas. La fórmula comercial aprobada no es un motor fiscal; no aplicar IGV universal ni declarar emisión SUNAT integrada.
- **E-05 CERRADO en política técnica D-109…D-122, pero NO probado.** Modalidad real Checkout Pro Orders, firma/consulta y errores siguen E-08. **E-06 fue aprobado posteriormente en política D-123…D-134; E-07 sigue pendiente** de identidad, privacidad y evidencia.
- **No se autoriza código, actualización de datos, mensajes ni pruebas.** D-96…D-108 cierran política comercial, D-109…D-122 la política E-05 y **D-123…D-134 la de E-06**; J-03/J-04/J-05, esquema físico, E-08 y verificación de seguridad siguen pendientes; E-07 está aprobado posteriormente D-135…D-148.

## E-05 — concurrencia, atomicidad y recuperación: CERRADO EN POLÍTICA TÉCNICA (2026-11-17)

El propietario aprobó expresamente **«Todas A en E-05»**. Las catorce opciones E05-A…N de `18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md` están registradas como **D-109…D-122**, con criterios **A-125…A-138**. Los 48 escenarios C5-X de variante/agenda, MP, webhooks, holds, efectivo, reintegros, fallos y recuperación continúan siendo documentales y **no ejecutados**.

**APROBADO D-109…D-122:** motor InnoDB en tablas críticas, locks de variante y recurso/día, creación MP en dos fases con admisión corta interna y hold comercial de 15 minutos solo tras intento válido, llamadas HTTP fuera de transacción SQL, expiración por reloj backend/BD, registro idempotente durable, webhook firmado/inbox, consulta proveedor, contraentrega atómica, cancelación/reprogramación atómicas, registro único de reintegro externo comprobado, outbox local y reconciliación oportunista. **La política técnica no prueba el hosting ni las integraciones externas.**

**Siguen bloqueados P0-18/P0-19/P0-23/P0-26**: diseño ER/SQL/índices/aislamiento, versiones y motor/correo del alojamiento, migraciones/rollback y ZIP. **E-06 ya está aprobado en política, pero E-07/E-08 y J-01/J-02/J-03/P0-17/P0-25 permanecen abiertos.** Ningún SQL, correo, conexión MP ni prueba ha sido autorizado o ejecutado.

## E-06 — comunicaciones y avisos: CERRADO EN POLÍTICA FUNCIONAL/OPERATIVA (2026-11-17)

El propietario aprobó expresamente **«Todas A en E-06»**. Las 12 opciones E06-A…L del documento `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` se registraron como **D-123…D-134**, y sus criterios de aceptación como **A-139…A-150**. Se conservan **26 eventos N6** y **38 escenarios N6-X NO EJECUTADOS**.

| Opción | Decisión | Política aprobada |
| --- | --- | --- |
| E06-A | D-123 | Seguimiento/confirmación WEB para todos; email cuando existe, no obligatorio para físicos puros |
| E06-B | D-124 | Correo con `wp_mail()` y remitente administrado, sin obligar SMTP de terceros |
| E06-C | D-125 | Solo comunicaciones transaccionales, sin marketing ni carrito abandonado |
| E06-D | D-126 | Eventos N6-01…N6-26 a partir de hechos reales; sin correo por cada cambio irrelevante |
| E06-E | D-127 | Outbox durable idempotente con reintentos y reenvío manual auditado |
| E06-F | D-128 | Correo aceptado técnicamente no equivale a recibido; fallos visibles sin revertir el negocio |
| E06-G | D-129 | Bandeja persistente priorizada por permisos de administrador/operador |
| E06-H | D-130 | Mensajes de mínimo dato con enlaces protegidos y sin documentos/secretos adjuntos |
| E06-I | D-131 | Avisos de cita y reprogramación con constancia; no recordatorios automáticos adicionales |
| E06-J | D-132 | Constancia y alertas del Libro nativo, evidencia de respuesta y plazos legales |
| E06-K | D-133 | Compras físicas con teléfono: contacto manual verificado; sin SMS/WhatsApp ni atajo automático |
| E06-L | D-134 | Plantillas es-PE versionadas y personalización administrativa limitada |

**Cierre funcional ≠ cumplimiento técnico o jurídico:** `wp_mail()` exitoso no demuestra recepción real. **PENDIENTES:** E-07 para identidad, acceso, minimización, consentimiento cuando corresponda y retención; E-08 para API/transporte; P0-19 para correo real del alojamiento; J-01/J-02/P0-25 para la constancia, respuesta legal y cálculo de días hábiles; P0-18/P0-26 para tablas, persistencia, respaldos/recuperación. **No se autorizó implementar ni enviar correos reales.**

## E-07 — privacidad, identidad y conservación: CERRADA EN POLÍTICA FUNCIONAL (2026-11-17)

El propietario aprobó expresamente **«Todas A en E-07»**. Las **14 opciones E07-A…N** del documento `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` se registraron como **D-135…D-148** y los criterios correspondientes como **A-151…A-164**. Sus 42 escenarios P7-X son **documentales, no ejecutados**.

| Opción | Decisión | Política A aprobada |
| --- | --- | --- |
| E07-A | D-135 | Comercio responsable por instalación; checklist de obligaciones ANPD, sin telemetría ni registro automático |
| E07-B | D-136 | Minimización por finalidad, rol, operación y campo, sin campos sensibles genéricos |
| E07-C | D-137 | Seguimiento privado solo lectura, secreto 256 bits, verificador protegido, vigencia inicial de **90 días** y rotación |
| E07-D | D-138 | Para operaciones sensibles por correo: OTP **8 dígitos**, **10 minutos**, **5 intentos**; sin OTP en checkout normal |
| E07-E | D-139 | Cliente solo teléfono: verificación manual proporcional/auditable antes de reemisión o cambio sensible |
| E07-F | D-140 | Descargas protegidas con token independiente 256 bits y **24 horas**, reemisión autorizada |
| E07-G | D-141 | Autenticación nativa WordPress de empleados y capabilities por operación, recurso y campo |
| E07-H | D-142 | Aviso de privacidad propio del comercio con finalidades, responsable y evidencia/versionado |
| E07-I | D-143 | Inventario de prestadores y flujos por instalación, sin trackers ni telemetría del desarrollador |
| E07-J | D-144 | Mapa/geolocalización por elección del comprador y dirección manual basada en UBIGEO |
| E07-K | D-145 | Exportadores/borradores WP para pedidos con email y expediente verificado para compras solo teléfono |
| E07-L | D-146 | Conservación por categoría/base legal y desinstalación sin borrado automático; purga separada cuando proceda |
| E07-M | D-147 | HTTPS, rate limiting, no enumeración, aislamiento de caché y prevención de filtración de tokens |
| E07-N | D-148 | Registro/evaluación administrativa de incidentes y notificación regulatoria condicional, **no automática** |

**La aprobación no demuestra cumplimiento con la ANPD ni seguridad implementada.** Vigencia de 90 días afecta únicamente el enlace privado, no derechos del comprador ni conservación legal. Los 48 h se evalúan únicamente ante incidentes que cumplan supuestos reglamentarios.

**PENDIENTES J-04/J-05/P0-25:** bases legales de tratamiento, registro del banco cuando corresponda, roles/encargados, flujos internacionales, derechos ARCO/portabilidad, evidencias/plazos, privacidad de reclamos e incidentes notificables. **PENDIENTES técnicos E-08/P0-18/P0-19/P0-21/P0-26:** capacidades y endpoints definitivos, tokens/OTP/archivos y antifiltración reales, mapas, hosting, SQL/índices, copias y purga. No se autorizaron código ni pruebas.

## E-08 — REST, Angular, Checkout Pro Orders y archivos: CERRADO EN POLÍTICA DE INTEGRACIÓN (2026-11-17)

El propietario aprobó expresamente **«Todas A en E-08»**. Las **18 opciones E08-A…R** del documento `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` están registradas como **D-149…D-166**, y sus criterios como **A-165…A-182**. Hay **52 entradas R8** (incluida una página WordPress de retorno que no es REST) y **60 escenarios I8-X** documentales **no ejecutados**.

| Opción aprobada | D | Política principal |
| --- | --- | --- |
| E08-A | D-149 | Namespace REST `peru-commerce/v1` y rutas R8 versionadas |
| E08-B | D-150 | Angular precompilado/condicional en ZIP WordPress, same-origin |
| E08-C | D-151 | Carrito sin hold y cotización validada por backend al pagar |
| E08-D | D-152 | Checkout MP/COD separado e idempotente |
| E08-E | D-153 | Token de seguimiento en fragmento URL y canje por cookie segura de solo lectura |
| E08-F | D-154 | Sesión WordPress + nonce CSRF para empleados, capability/recurso/campo |
| E08-G | D-155 | OTP D-138 y recuperación telefónica manual con propósito restringido |
| E08-H | D-156 | MP Checkout Pro **Orders API** vía HTTP WordPress con credenciales propias del comercio |
| E08-I | D-157 | `payer` omitido si compra física sin email, **condicionado a verificación MP** |
| E08-J | D-158 | Ítems reales + línea `Entrega` no inventariable, **condicionado a aceptación MP** |
| E08-K | D-159 | `expiration_time=PT15M` únicamente si MP lo admite; reloj local independiente |
| E08-L | D-160 | Webhook Orders con HMAC, inbox durable y consulta fuente antes de acreditar |
| E08-M | D-161 | Errores HTTP/`pc_*` coherentes, `202` para incertidumbre y DTO versionados |
| E08-N | D-162 | Rutas administrativas y capabilities `perucommerce_*` por recurso/campo |
| E08-O | D-163 | Archivos digitales no públicos y streaming PHP verificado; no venta insegura |
| E08-P | D-164 | Libro/ARCO segregados con WordPress Privacy y atención telefónica manual |
| E08-Q | D-165 | Idempotencia/inbox/outbox, diagnóstico, paginación y caché solo pública |
| E08-R | D-166 | Formalización R8↔PER en OpenAPI/DTO estático antes de código |

**Aprobación de políticas ≠ compatibilidad ni contratos físicos listos.** Antes de habilitar pago MP, verificar por medios expresamente autorizados en una cuenta peruana: físico sin `payer`, línea de Entrega en ítems, vigencia `PT15M`, estados efectivos, callback y conciliación. Si falla, **bloquear integración y pedir nueva decisión**, sin cambiar silenciosamente a la alternativa B ni imponer correo a físicos. No hay pagos, órdenes ni pruebas realizadas.

**PENDIENTES:** nueve P0 (17/18/19/20/21/23/24/25/26), J-01…J-05, matriz completa R8↔PER↔DTO/OpenAPI con campos/capabilities/errores, SQL/migraciones/rollback, versiones/hosting, mapa, licencia, legislación peruana, compatibilidad y UX. E-01…E-08 están cerrados en política, pero Codex sigue sin autorización para implementar, compilar, probar, publicar o desplegar.

## Contratos preparados y bloqueos técnicos todavía abiertos

- `12-CONTRATO-ESTADOS-TRANSICIONES-Y-PERMISOS.md`: invariantes de negocio de E-01…E-08 aprobadas en sus políticas; **PENDIENTES** contrato técnico físico de capabilities/rutas/campos/error/OpenAPI, ejecución y verificación real. No usar una celda PER marcada P como autorización por defecto.
- `13-MATRIZ-DE-HUECOS-Y-ESCENARIOS.md`: entregable exigido por cada P0, **escenarios X** no ejecutados, plantilla de contratos y condiciones de preparación; **S-01…S-06 CERRADAS con D-61…D-66**, sin aprobar los cálculos, permisos y contratos técnicos que todavía faltan.
- `14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md`: **CONTRATO FUNCIONAL E-01 APROBADO (D-67…D-77)** con vocabulario y transiciones funcionales por eje. **No extrapolar** permisos, importes, concurrencia o endpoints de esas tablas.
- `15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md`: **POLÍTICAS E-02 APROBADAS (D-78…D-85)**, con inventario de 56 operaciones PER-01…PER-56. Las celdas marcadas P todavía requieren campos, alcance y capacidades técnicas; no conceder más permisos de los confirmados.
- `16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md`: **E-03 APROBADO D-86…D-95**; matriz legal J-01/J-02/P0-25 y aplicación/validación de E-04…E-08 siguen abiertas técnicamente. No inferir plazos legales.
- `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md`: **E-04 APROBADO D-96…D-108**; 32 casos M4-X no ejecutados. Siguen pendientes SQL/DTO físico, fiscalidad J-03 y validación de Orders API E-08 en una cuenta autorizada.
- `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md`: **E-08 base D-149…D-166 y cobertura ampliada D-176…D-187**; inventario de **66 entradas R8 (65 REST + R8-18 página WP)**, **56 PER** y OpenAPI **0.4.0-draft/115 DTO** actualizado por D-189. Los 60 I8-X de la base original no se ejecutaron. **PENDIENTE:** ratificación física/campos legales, controles de acceso, MP/hosting/seguridad y pruebas autorizadas.
- `18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md`: **E-05 APROBADO EN POLÍTICA TÉCNICA D-109…D-122**, con 14 opciones A y 48 escenarios C5-X NO ejecutados. Faltan contrato físico, versiones/hosting y pruebas autorizadas; no declarar funcionamiento verificado.
- Los nueve P0 siguientes permanecen abiertos en sus aspectos listados. Las **194 decisiones confirmadas D-01…D-194** incluyen la base E-01…E-08 (D-67…D-166) y la ampliación C23 (D-176…D-187). **Las políticas están aprobadas; ejecución, DDL, seguridad, compatibilidad y cumplimiento real siguen sin probarse.** Véase docs/34 para cierre por tipo de evidencia.

**Precisión adicional (2026-11-17):** `docs/35-CONTRATOS-LEGALES-PERMISOS-DTO-P0-23-P0-25-V1.md` enumera 10 brechas LEG y 14 relaciones entre rutas legales y campos; `docs/36-RESOLUCIONES-PROPUESTAS-DB-A01-A16-P0-18-V1.md` da tratamientos documentales a los 16 DB-A/20 criterios, **sin declarar DDL aprobado o ejecutado**. OpenAPI principal corrigió originalmente paths C23 relativos a la URL base; **D-189 añadió dos GET protegidos** para R8-41/R8-43 como consultas por ID (65 operaciones/62 rutas en total). La versión histórica 0.3.0-draft anterior a D-189 tenía 63/60. PER-09 exportación comercial general quedó **EXCLUIDA de V1 por D-188**, sin endpoint ni ampliación de permisos.

**Cierre de LEG-04/05 (D-189, 2026-11-17):** la propuesta de pantalla WordPress en docs/35 §3 fue descartada; docs/37 y OpenAPI 0.4.0-draft RATIFICAN dos GET de detalle privados R8-65/66 para Angular. R8-41/43 mantienen listados mínimos y R8-42/44 las acciones de respuesta/resolución. Docs/38 recoge 16 tratamientos lógicos aprobados D-191; docs/39 y docs/40 separan diseño de verificaciones de código y release.

**Matriz histórica tras corrección D-198:** docs/39 separa P-A (diseño), P-B (autorización de código), P-C (pruebas) y P-D (release). Los 16 DB-A de docs/38 y dos GET de docs/37 ya están RATIFICADOS COMO DISEÑO D-189/D-191, sin DDL implementado ni permisos runtime. PRE-07 tiene 34 pantallas Figma como baseline D-195, no prueba WCAG ni aprobación estética individual; no bloquea iniciar el primer módulo cuando se autorice código. No exigir build antes de código, ni afirmar compatibilidad por documentación.

## P0 todavía abiertos — nueve áreas principales

| ID | Alcance pendiente |
| --- | --- |
| P0-17 | **DECISIÓN APROBADA D-167 (política):** céntimos PEN y fiscalidad contextual. **Investigación documental:** `docs/26-CONTRATO-TRIBUTARIO-P0-17-J03-V1.md` (15 supuestos); no hay cálculo ni redondeo fiscal automático en V1. **Pendiente:** aplicabilidad del comercio, DTO/campos legales y comprobación MP. |
| P0-18 | **D-168 aprobada como política:** 39 tablas documentales en `docs/23-MODELO-FISICO-DE-DATOS-E-INDICES-V1.md`, con **auditoría estática `docs/28-AUDITORIA-ESTATICA-MODELO-FISICO-P0-18-V1.md` (16 hallazgos DB-A01…A16)** y plan no ejecutable de normalización, FK/índices y migración por fases. **Pendiente:** resolver observaciones, ratificar DDL físico, compatibilidad MariaDB/MySQL del hosting y recuperación, sin ejecutar SQL ni código. |
| P0-19 | **D-169 aprobada como objetivo:** WordPress 7.1.4+ estable, PHP 8.3+ y BCMath, MariaDB 10.11+ o MySQL 8.4+ InnoDB, Angular 22 compilado fuera del servidor. **Investigación oficial `docs/29-COMPATIBILIDAD-WORDPRESS-HOSTING-P0-19-V1.md`:** Hostinger Web/Cloud declara MariaDB/InnoDB y configuración PHP; **12 checks HOST propuestos**. **Pendiente:** versión/permisos/motor/aislamiento/correo/HTTPS/almacenamiento real, sin acceso al sitio. |
| P0-20 | **DECISIÓN APROBADA D-170:** Angular 22 compilado fuera de hosting, montaje condicional WordPress y SEO PHP. **Contrato documental `docs/31-CONTRATO-ANGULAR-WORDPRESS-ASSETS-P0-20-V1.md`**: 8 superficies, 10 campos de manifest propuesto y 20 A20-X. **Pendiente:** build real, manifest emitido, compatibilidad de chunks/rutas/CSP, rendimiento, UX/hosting y aprobación física. |
| P0-21 | **DECISIÓN APROBADA D-171:** Leaflet/OSM Standard inicial solo mapa opcional y best-effort, siempre alternativa de dirección manual y tarifa distrital UBIGEO en backend. **Investigación documental `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md`**: 10 fuentes, 16 controles MAP y 20 M21-X; OSM sin SLA, Nominatim no autocomplete, dos publicaciones INEI con licencia/fecha distintas. **Pendiente:** elegir y verificar archivo INEI completo/vigente, licencia de recurso a distribuir, condiciones del proveedor instalado, privacidad, cache, atribución y pruebas, sin importación ni código. |
| P0-23 | **APROBADO EN DISEÑO D-172/D-176…D-187/D-189/D-190:** 66 entradas R8/56 PER, OpenAPI 0.4.0-draft con 65 REST, 62 rutas y 115 DTO; los dos GET de expedientes son solo A y no dan permisos O. **Pendiente operativo:** implementación/seguridad real/hosting/MP/ZIP, aplicación jurídica por comercio y aprobación visual final. PER-09 sigue excluida por D-188. No hay API ejecutable. |
| P0-24 | **DECISIÓN APROBADA D-173:** ZIP gratuito GPL-2.0-or-later, repositorio desarrollo privado. **Contrato documental `docs/32-LICENCIA-FUENTES-Y-DISTRIBUCION-P0-24-V1.md`**: entrega de fuente correspondiente, inventario tercero por versión, 18 controles LIC y 20 LIC-X. **Pendiente:** LICENSE físico, avisos y lockfile reales, método conforme de suministro de fuente, hash/build/ZIP y validación legal distributiva. |
| P0-25 | **MÉTODO APROBADO D-174:** matrices J-01…J-05 por norma/sujeto/caso/evidencia. **Investigación documental:** `docs/25-MATRICES-NORMATIVAS-J01-J05-P0-25-V1.md` (59 controles, fuentes oficiales). **Pendiente legal:** reglamento definitivo, supuestos por actividad/comerciante, campos y validación de implementación. |
| P0-26 | **DECISIÓN APROBADA D-175 (política):** ZIP manual, migraciones versionadas, backups consistentes, restauración y uninstall no destructivo. **Runbook documental en `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md`**: 9 fases L, 12 LC, 14 F26, 20 ZIP-X, sin ejecutar. **Pendiente físico/real:** ZIP/manifiesto Angular y licencia/avisos, SQL/migrador, respaldo y restore de Hostinger, reconciliación MP y RPO/RTO autorizados/comprobados. |

## Ronda P0-17…P0-26: OPCIONES A APROBADAS (2026-11-17)

El propietario confirmó expresamente las nueve opciones propuestas para **P0-17, P0-18, P0-19, P0-20, P0-21, P0-23, P0-24, P0-25 y P0-26**. Quedan registradas como **D-167…D-175** y detalladas en `docs/22-DECISIONES-P0-17-A-26-APROBADAS-V1.md`. No volver a pedir esas elecciones; sí completar DDL, DTO/OpenAPI, J-01…J-05 y las verificaciones de entorno/integración antes de habilitar código.

**Extensión C23 APROBADA EN COBERTURA (D-176…D-187):** se incorporaron documentalmente R8-53…R8-64 a E-08/OpenAPI principal; fuente `docs/27-COBERTURA-OPERACIONES-P0-23-PROPUESTA-V1.md`, y propuesta OpenAPI separada queda **histórica**. Sin nuevas capabilities ni roles: O no puede ejecutar PER-20/27/28/31 hasta cambio expreso. **PER-09 está EXCLUIDA de V1 por D-188**; no tiene ruta, y una exportación posterior requiere nueva autorización de alcance. No declarar implementación o pruebas.

**Progreso P0-23:** matriz R8/PER en `docs/24-MATRIZ-REST-PERMISOS-DTO-P0-23-V1.md`, OpenAPI 3.1 0.4.0-draft (65 operaciones/115 DTO) en `docs/openapi/peru-commerce-v1.openapi.draft.json` y esquema ampliado a 39 tablas en anexos docs/23. **Cobertura aprobada ≠ implementación, DDL definitivo, validación jurídica o pruebas.** Se debe completar y ratificar por operación/campo, junto a J-01…J-05 y el esquema físico P0-18.

**Progreso P0-21:** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` define proveedor OSM inicial opcional sin SLA, reglas de tiles, no Nominatim autocomplete, tarifario autoritativo PHP y dataset territorial INEI versionado/pendiente de licencia. **Solo revisión documental; sin archivo UBIGEO descargado/importado, sin integración o pruebas.**

**Progreso P0-20/P0-24:** `docs/31-CONTRATO-ANGULAR-WORDPRESS-ASSETS-P0-20-V1.md` fija diseño de manifest/módulos y escenas condicionales; `docs/32-LICENCIA-FUENTES-Y-DISTRIBUCION-P0-24-V1.md` define fuentes GPL y auditoría de terceros por versión. **Son documentos, no archivos compilados ni una distribución legalmente verificada**. No crear LICENSE de contenido incompleto, manifest ficticio ni ZIP hasta tener código, dependencias y autorización.

**Progreso P0-26:** `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md` define ciclo de ZIP, gates de módulos, copia consistente, rollback no automático, conciliación posterior, privacidad/desinstalación. **No se ha hecho instalación, backup ni restore**, y un respaldo nominal de Hostinger no equivale a copia coherente o restauración demostrada.

**Progreso P0-18/P0-19:** docs/23 mantiene las 39 tablas propuestas; `docs/28-AUDITORIA-ESTATICA-MODELO-FISICO-P0-18-V1.md` revisa relaciones/constraints con 16 hallazgos sin SQL; `docs/29-COMPATIBILIDAD-WORDPRESS-HOSTING-P0-19-V1.md` contrasta fuentes oficiales WordPress/Hostinger/Angular y define 12 condiciones de preflight NO comprobadas en una instalación. **No equivale a DDL aprobado ni soporte efectivo probado**. La cobertura R8 se ratificó antes con D-176…D-187 y el OpenAPI 0.4.0-draft es contractual/documental, no API ejecutable.

**Progreso P0-25/J:** matrices y contrato fiscal disponibles en docs/25 y docs/26. Las fuentes legales están investigadas, **no significa validación jurídica sectorial ni compatibilidad real**; mantener bloqueos de implementación.


**Prioridad del corte histórico D-189…D-194:** no reabrir alcance ya ratificado ni inventar exportación comercial. La revisión/aprobación visual PRE-07 (Figma 34 frames) se cerró posteriormente como baseline de ingeniería D-195, preparar ejecución autorizada de DDL con las 39 tablas y probar MP/hosting/archivos privados, validar bytes/licencia INEI y completar ZIP/fuentes GPL antes de habilitar release. La política legal queda condicionada a la actividad/comercio y norma vigente.

**Matriz de ejecución documental vigente:** `docs/34-MATRIZ-CIERRE-FASE-0-Y-VALIDACIONES-PENDIENTES-V1.md` diferencia trabajo de lectura (contrato/norma/artefacto), necesidades de pruebas reales (Hostinger/MP/SQL/ZIP/seguridad) y una decisión de alcance ya ratificada (**PER-09, excluida de V1 por D-188**). **Orden recomendado:** contratos legales y DTO → DB-A/locks → INEI/manifest/licencias → pruebas específicas autorizadas → UX/restore. Ningún resultado real se declara comprobado por un documento.

## Subcontratos todavía necesarios de las decisiones D-27…D-35

Estos **no reabren** la elección A ya aprobada, pero deben cerrarse antes de implementar las operaciones afectadas:

| Ref. | Detalle requerido |
| --- | --- |
| D-27/D-36/D-37 | **CERRADO:** evento funcional «Pagar» y creación válida en servidor, 15 min sin renovación; contraentrega compromete al confirmar. **PENDIENTE:** atomicidad, reintentos/errores de creación y limpieza diferida |
| D-28 | Notificación y conciliación de pago tardío, gestión de devolución según proveedor, referencia, plazos y evidencia |
| D-29/D-38 | **CERRADO:** un cliente por franja y recurso sin solapamientos. **PENDIENTE:** granularidad de horario, duraciones, buffers, anticipación, vacaciones, zona horaria y fecha máxima |
| D-30/D-39/D-86…D-95 | **CERRADO FUNCIONALMENTE:** condiciones históricas, elegibilidad por etapa, expedientes y cancelación por línea; E-04…E-07 aprobados en sus políticas. **PENDIENTES:** J-01…J-05 por sector/comercio, SQL/hosting y API E-08 para ejecución real |
| D-31/D-52/D-57 | **CERRADO:** producto simple con precio/oferta propios y variante con valores explícitos independientes, sin herencia automática. **PENDIENTES:** SKU, valores faltantes, variantes sin inventario, restock/reintegro y efectos de cancelación parcial sobre stock |
| D-32/D-40 | **CERRADO:** enlaces de descarga válidos 24 h, reemisión segura autorizada y sin cuota por defecto. **PENDIENTE:** identificación, reemisión concreta, almacenamiento, abuso, revocación y devoluciones |
| D-33/D-51/D-53/D-54/D-56/D-58…D-66 | Cobro inicial único, elegibilidad por línea con revisión digital/logística, operador sin cobro acreditado, ajuste de dirección y reembolso externo parcial comprobado. **PENDIENTE:** lugar/momento de cobro, idempotencia, impacto financiero, fórmula del delivery, prorrateos, conciliación, evidencia y derechos legales aplicables |
| D-34 | Caducidad/revocación y alcance del enlace, identificación para asistencia telefónica, acceso a datos personales y recuperación |
| D-35 | Confirmación visual de disponibilidad, cambios al revalidar en checkout y cómo se actualiza el carrito |

**Prioridad:** preservar todas las decisiones aprobadas, incluidas las estrategias D-42 a D-44, documentar los detalles y no introducir supuestos de Codex.

## UX e integraciones (P1 una vez cerrado modelo)

- Inventario exacto de bloques/shortcodes, configuración y previsualización.
- **CERRADO UX-P01:** fecha/hora en ficha antes de carrito; pendientes mensajes de validación/expiración. **Cupones/promociones automáticas fuera de V1 (D-41); precio final ingresado por comercio y visible en PEN (D-45/D-46/D-49); ofertas manuales (D-50)**. Pendientes desglose fiscal contextual y otros errores.
- **CERRADO D-44:** Leaflet con proveedor intercambiable y dirección manual de respaldo. Pendientes detalles de pin, zona, cambio de tarifa, estados de proveedor indisponible y validación de cobertura.
- Pantallas Figma y variantes móvil/escritorio; accesibilidad y estados.
- **CERRADO (D-16/D-17):** WCAG 2.2 AA y objetivos táctiles de 44 × 44 px CSS para controles independientes aprobados. Falta definir procedimientos de validación, matriz de dispositivos y responsables, pero no redefinir estos mínimos.
- Elegir identidad visual, tipografía, tokens, componentes, estados, matriz de dispositivos y contrato de entrega Figma→Codex (`11-CONTRATO-FIGMA-Y-DISENO-DE-COMPONENTES.md`).
- Fijar cómo y cuándo se medirán Core Web Vitals y éxito de tareas, sin habilitar telemetría externa por defecto.
- Onboarding del comerciante y acciones de recuperación si falta conexión.
- Notificaciones transaccionales y WhatsApp (manual/automatizado).
- Conciliación Mercado Pago, observabilidad e incidencias.
- Contenido de ayuda, instalación y soporte para comercios.

## Registro y cierre

Para cada decisión, registrar:
- ID, opción escogida o regla exacta, motivos, fecha y confirmación expresa.
- Contratos/módulos/pantallas que cambian.
- Casos de aceptación adicionales.
- Fuente oficial que sustente obligaciones y versión consultada cuando corresponda.

**Puerta de salida:** no iniciar código ni declarar «sin vacíos» si existe algún P0 sin respuesta. Una aprobación global por inferencia no cierra pendientes individuales.

## Corte posterior de contrato — 18-11-2026
La fuente del corte histórico DOC-QA16…21 fue OpenAPI **0.8.0-draft: 67 REST, 64 rutas, 134 esquemas**. Los conteos 117 y 125 en apartados históricos son cortes anteriores. Se repararon cuatro grupos técnicos sin reabrir decisiones D-01…D-198: exposición DTO, políticas versionadas, datos de lectura necesarios para mutaciones administrativas y conciliación MP antes de existir un pedido. Detalle en docs/42, JSON, docs/21/23/24/31. P-A permanece cerrada en decisiones/contratos para solicitar P-B, pero ninguna compatibilidad, pago, SQL o release queda probado.

## Actualización de recorridos pre-código (18-11-2026)

Las últimas omisiones contractuales detectadas se corrigieron **en OpenAPI 0.8.0-draft**, sin reabrir alcance D-01…D-198: lectura de caso completo para A, condiciones históricas del pedido, cuota de descargas digital por comercio, proveedor de mapa configurable y reintento idempotente de R8-12. Los 134 esquemas eran la referencia del corte 0.8.0-draft; el contrato vigente posterior D-200 es 0.12.0-draft con 138 esquemas. No se incorporaron endpoints, roles, tablas ni operaciones de refund ejecutor. P-A cerrada como contrato documental; P-B requiere permiso expreso para código y P-C/P-D la verificación real de módulos/instalaciones. Se conservan las políticas previamente aprobadas aunque textos antiguos empleen «pendiente» como fotografía de una ronda anterior.
