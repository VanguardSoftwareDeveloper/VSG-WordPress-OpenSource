# Contrato de estados, transiciones y permisos — PeruCommerce V1

**Estado:** E-01 aprobado funcionalmente (D-67…D-77, doc. 14) y **E-02 aprobado como política de acceso** (D-78…D-85, doc. 15). **Sigue siendo borrador técnico** para capabilities, datos por campo, autenticación, elegibilidad, cálculos, atomicidad, comunicaciones, privacidad y API (E-03…E-08). No autoriza código, pruebas ni infraestructura.
**Objetivo:** impedir que Codex deduzca estados, operaciones o autorizaciones a partir de pantallas. Complementa [05-DOMINIO-Y-OPERACION.md](05-DOMINIO-Y-OPERACION.md), [09-DECISIONES-PENDIENTES.md](09-DECISIONES-PENDIENTES.md) y [13-MATRIZ-DE-HUECOS-Y-ESCENARIOS.md](13-MATRIZ-DE-HUECOS-Y-ESCENARIOS.md).

## 1. Invariantes que SÍ están confirmados

1. **D-55:** estado administrativo del pedido, historial/estado de cada intento o movimiento de pago y cumplimiento de cada línea son **dimensiones independientes**. Pago aprobado no significa entrega; pedido contraentrega confirmado no significa cobrado.
2. **D-20/D-18/D-19/D-38:** pedido con como máximo **una cita** de servicio presencial en el local, con una agenda/recurso y un cliente por intervalo no superpuesto, incluidos duración y buffers.
3. **D-21/D-33:** Mercado Pago puede cobrar cualquier tipo de carrito; efectivo solo cuando **todo** pueda cobrarse en **un mismo evento presencial**, con **un cobro inicial por pedido**. No dividirlo por línea para evadir la restricción.
4. **D-27/D-36:** retención de stock y cita durante **15 minutos** al pulsar «Pagar» y crear un intento válido en servidor. Sin renovación automática. Elegir turno en ficha **no reserva**.
5. **D-37:** confirmar pedido contraentrega compromete stock/cita disponibles inmediatamente; el dinero continúa pendiente de acreditación manual.
6. **D-28:** si un pago externo se acredita tras vencer la retención y ya no existe recurso, abrir **incidencia**; no confirmar recurso ficticio. Resolución por comerciante: reprogramación o reintegro externo cuando corresponda.
7. **D-09/D-32/D-40:** no entregar archivos sin cobro acreditado; derechos de descarga protegidos; enlaces individuales válidos **24 horas** y reemisión segura al autorizado; sin cuota de descargas predeterminada.
8. **D-56/D-58:** cancelación ordinaria por **línea pendiente de cumplimiento y elegible**, conservando las demás. Líneas ya entregadas, servicios realizados o prestaciones consumadas van a devolución, reclamación o incidencia según derechos aplicables.
9. **D-59:** pedido confirmado, precios/condiciones originales y snapshots **inmutables**. Ajustes posteriores son eventos relacionados, nunca sobrescritura destructiva.
10. **D-47/D-51/D-53/D-54/D-60:** operador gestiona pedidos/productos/stock/agenda y registra efectivo recibido; solo administrador cambia credenciales, corrige cobros, registra reembolsos externos verificados y autoriza cancelaciones de pedidos pagados con impacto financiero.
11. **D-54:** el plugin **no ejecuta reembolsos** en V1. Registrar el resultado verificado de un reintegro externo no equivale a solicitarlo.
12. **D-45/D-49/D-52/D-57:** PEN exclusivo; precio final propio del producto simple y precio explícito por variante, oferta simple manual por entidad vendible, sin herencia automática.
13. **D-24/D-34:** compras sin registro obligatorio con datos de contacto condicionales y seguimiento por enlace privado no adivinable; no convertirlo en acceso universal a otros pedidos.
14. **D-16/D-17:** interfaz pública y administrativa con objetivos de WCAG 2.2 AA y controles táctiles independientes de al menos 44 × 44 px CSS; todavía no se ha verificado cumplimiento.
15. **D-61:** digital con acceso habilitado sin descarga registrada no implica elegibilidad automática; solicitud evaluada por administrador según condiciones y ley, sin devolución/cancelación automática.
16. **D-62:** físico despachado sin entregar genera incidencia logística y revisión; no cancelación ordinaria de un clic.
17. **D-63:** cancelar última línea física antes de despacho exige revisar delivery, servicio realmente prestado y derechos; si hay impacto financiero en pedido pagado autoriza administrador.
18. **D-64:** operador solo cancela sin **dinero acreditado** y sin obligación de ajuste/reintegro, con permiso y auditoría; concreta más restrictivamente D-60.
19. **D-65:** dirección/datos de entrega se modifican tras confirmar por solicitud/ajuste auditable, sin sobrescribir snapshot original; cobertura/costo se revalidan.
20. **D-66:** reembolso externo **parcial** verificable puede registrarse por administrador con monto y referencia, conservando cobro original y sin emitir reembolso por API.

## E-08 — REST y permisos APROBADOS EN POLÍTICA (D-149…D-166)

El contrato [21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md](21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md) ratifica E08-A…R: namespace REST, entradas R8-01…R8-52 (R8-18 es página WP, no endpoint), capabilities `perucommerce_*` por operación/recurso/campo, errores `pc_*`, sesión invitada limitada, checkout MP/COD e inbox. **Es diseño aprobado, aún no un OpenAPI/DTO físico ni un backend operativo**.

Las máquinas de estados E-01 no cambian por eventos de correo, expiración de tokens ni estados `processed` de MP sin acreditación comprobada. D-64 prohíbe cancelación por operador con dinero acreditado; D-54 prohíbe refund API; D-85 limita seguimiento a lectura. P0-18/19/23/25 y J-01…J-05 siguen pendientes, igual que la validación de la pasarela en Perú.

## 2. Límites de consistencia (dominio lógico, no esquema aprobado)

| Unidad conceptual | Fuente de verdad / reglas | No confundir con |
| --- | --- | --- |
| Pedido | Identidad comercial, comprador, línea/snapshot, total y estado administrativo | Intento de pago o entrega |
| Línea de pedido | Producto/variante real, cantidad, precio pactado, estado de cumplimiento y ajustes | Estado de todo el pedido |
| Intento de pago | Referencia local/proveedor, método, importe PEN y resultado validado | Retorno visual del navegador |
| Movimiento financiero | Cobro, ajuste, reintegro externo comprobado, referencia y auditoría | Edición del movimiento original |
| Reserva de disponibilidad | Stock/intervalo y vencimiento efectivo | Cita confirmada o cobro |
| Cita | Servicio, único recurso, inicio, duración, buffers y estado | Reserva temporal al navegar |
| Derecho de descarga | Titular autorizado, archivo y condiciones de acceso | Token de descarga de 24 h |
| Entrega física | Dirección, distrito UBIGEO, modalidad y cumplimiento | Validez del pin de mapa |
| Bitácora de operación | Actor, motivo, referencia, momento y resultado | Estado mutable como única evidencia |

**CONFIRMADO como dirección:** tablas propias para pedidos/pagos/reservas y evaluación de estructuras nativas WordPress para catálogo (D-43). **PENDIENTE:** entidades finales, número y nombre de tablas, claves, índices, bloqueo transaccional, relaciones y migraciones (P0-18). No crear esquema a partir de esta tabla.

## 3. Estados y máquinas: E-01 aprobado en documento 14 (D-67…D-77)

**D-55** confirma separar los ejes y **D-67…D-77 aprueban expresamente el vocabulario y las transiciones funcionales de E-01**. La matriz preliminar antigua fue retirada para evitar enums contradictorios.

**Fuente única y APROBADA del contrato funcional E-01:** [14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md](14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md). Sus estados lógicos, transiciones O/MP/H/F/G/V, prohibiciones y 11 opciones A están aprobados como D-67…D-77. **No implementar todavía:** faltan mecanismos de autorización, importes, estados externos, consistencia, errores y API exigidos por E-02…E-08.

**E-01…E-07 aprobados dentro de sus políticas (docs.14…20, D-67…D-148)**. E-06 añade avisos derivados, y E-07 determina privacidad y verificación sensible sin cambiar estados de negocio. **Pendientes:** capabilities REST E-08, J-01…J-05/P0-17/P0-25, SQL/hosting P0-18/P0-19, evidencia de seguridad y cumplimiento.

## 4. Catálogo de eventos y reglas de transición

**Etiquetas:** C = comportamiento confirmado; P = detalles por aprobar. Esta tabla de eventos T-01…T-26 describe **disparadores funcionales**, no es una segunda máquina de estados ni sustituye los nombres y transiciones candidatos del documento 14. Una fila C no implica que su persistencia/endpoint ya esté elegido.

| ID | Disparador y condición | Resultado obligatorio | Actor / precisión |
| --- | --- | --- | --- |
| T-01 | Comprador selecciona fecha en ficha de servicio | Solo selección provisional; al añadir al carrito no se promete cupo | C: comprador; no reserva |
| T-02 | «Pagar» produce intento válido en servidor con disponibilidad | Retención atómica de recursos por 15 min sin renovación; precio y PEN verificados | C: disparador/TTL; P: transacción, idempotencia y errores |
| T-03 | Intento de pago no se crea / falla red | No afirmar reserva confirmada ni dinero pagado; consulta segura de operación antes de reintentar | C: honestidad/anti duplicación; P: estrategia de reconciliación |
| T-04 | Mercado Pago informa aprobación verificable | Registrar resultado de pago independientemente del pedido/entrega; comprometer recursos disponibles conforme a retención | C: separación y autoridad; P: estados exactos y llegada tardía |
| T-05 | Retención vencida y recurso ya ocupado; llega pago | Incidencia con pago acreditado sin promesa de entrega/cita; comerciante resuelve reprogramar o reintegrar fuera del plugin | C: D-28; P: comunicación/compensación |
| T-06 | Retención vencida sin pago | Disponibilidad puede volver a ofrecerse aunque WP-Cron no haya limpiado; no renovar la retención automáticamente | C: 15 min y expiración efectiva; P: mecanismo técnico |
| T-07 | Pedido contraentrega válido confirmado | Comprometer stock/cita al confirmar y mantener pago pendiente | C: D-37; P: recuperación de fallo atómico |
| T-08 | Operador registra efectivo efectivamente cobrado | Evento de acreditación auditable, sin borrar intento previo; no introducir reembolso | C: D-51; P: verificación de monto/idempotencia |
| T-09 | Administrador corrige registro de efectivo | Asiento adicional de ajuste enlazado al original, conservándolo | C: D-53; P: forma de corregir saldo |
| T-10 | Se marca línea física como entregada | Solo cambia cumplimiento físico y entrega; no implica cambios automáticos de pago | C: separación; P: quién y qué evidencia |
| T-11 | Se habilita acceso digital tras cobro acreditado | Derecho de acceso con enlaces protegidos de 24 h; contador de descargas sin cuota por defecto | C: D-09/32/40; P: firma/reemisión |
| T-12 | Vence enlace digital | Token antiguo no autoriza descarga; el comprador autorizado puede obtener reemisión segura | C: 24 h; P: identidad y protocolo |
| T-13 | Cita se realiza | Marcar solo cumplimiento de servicio, con registro del actor | C: separar cumplimiento; P: evidencia/no-show |
| T-14 | Comerciante reprograma una cita | Auditar cambio y evitar doble ocupación de origen/destino | C: gestión desde panel; P: condiciones y concurrencia |
| T-15 | Se solicita cancelar línea pendiente y elegible, sin impacto financiero | Conservar pedido original; cancelar solo la línea, ajustar recursos y registrar evento | C: D-56/58/59; P: capacidad exacta del operador |
| T-16 | Se solicita cancelar línea pendiente de pedido pagado, con impacto financiero | Autorización exclusiva del administrador; ajuste auditable, sin reembolso automático | C: D-60; P: cálculo del impacto |
| T-17 | Se solicita «cancelar» línea entregada/prestada | No retroceder el cumplimiento; tramitar devolución, reclamación o incidencia apropiada | C: D-58; P: reglas normativas por tipo |
| T-18 | Reembolso ya efectuado por proveedor/medio | Solo administrador registra resultado **verificado**, referencia/monto, conservando historial | C: D-54; P: evidencia/conciliación |
| T-19 | Webhook repetido, fuera de orden o retorno repetido | No duplicar pago, pedido, consumo de stock ni cita; no retroceder estados verificados | C: integridad necesaria; P: llave/eventos concretos |
| T-20 | Módulo deshabilitado | No aceptar nuevas operaciones de ese módulo; preservar pedidos, derechos y registros históricos | C: continuidad; P: operaciones activas afectadas |
| T-21 | Digital habilitado sin descarga; comprador pide cancelación/devolución | Administrador evalúa caso según política y derechos; no inferir no cumplimiento ni ejecutar reintegro automático | C: D-61; P: términos, criterios, evidencia |
| T-22 | Línea física despachada, aún sin entrega; se solicita cancelar | Abrir incidencia logística y evaluar; no cancelar ordinariamente de forma inmediata | C: D-62; P: gestión de incidencias y compensación |
| T-23 | Se cancela última línea física antes de despacho y hay tarifa delivery | Revisar y, si corresponde, ajustar costo por servicio prestado y derechos legales; administración autoriza impacto financiero de pedido pagado | C: D-63; P: fórmula/importes e impuestos |
| T-24 | Operador intenta cancelar con dinero acreditado o ajuste/reintegro exigible | Rechazar acción del operador aunque diga que el impacto es cero; administrador resuelve si procede | C: D-64; P: clasificación y capacidades técnicas |
| T-25 | Cambio solicitado de dirección/datos de entrega tras confirmar | Conservar originales, crear ajuste auditable y comprobar nueva cobertura/tarifa y viabilidad logística antes de aplicar | C: D-65; P: efectos económicos y reglas |
| T-26 | Administrador registra reembolso parcial verificado en medio externo | Añadir movimiento de reintegro parcial con importe PEN/referencia, conservar cobro original y no llamar API de devolución | C: D-66; P: acumulados, conciliación/evidencia |

## 5. Matriz de capacidades: alcance confirmado vs pendiente

**Clave:** S = autorizado por decisión confirmada; N = prohibido expresamente; P = posible según matriz detallada por aprobar; E = operación técnica protegida, no una facultad de usuario. El usuario público sin cuenta puede actuar sobre su pedido solo con el acceso pertinente.

| Operación | Comprador invitado | Operador | Administrador | Sistema/proveedor |
| --- | --- | --- | --- | --- |
| Consultar catálogo público | S | S | S | — |
| Crear su pedido / elegir cita | S | P | P | E valida |
| Consultar su pedido con enlace privado | S con token | P | P | E verifica |
| Editar productos y precios finales/ofertas | N | S con capacidades | S | E valida |
| Ajustar existencias por variante | N | S con capacidades | S | E valida |
| Gestionar agenda y cambios de cita | N autoservicio | S con capacidades | S | E valida |
| Ver pedidos en panel | N sin token | S con alcance P | S | E valida |
| Registrar efectivo realmente recibido | N | S autorizado | S | E valida y audita |
| Corregir cobro efectivo histórico | N | **N** | **S** con ajuste | E audita |
| Cancelar línea pendiente sin impacto financiero | N autoservicio | P **solo si no hay dinero acreditado ni reintegro/ajuste exigible**, con capacidad y elegibilidad (D-64) | S según elegibilidad | E verifica |
| Autorizar cancelación de pedido pagado con impacto financiero | N | **N** | **S** | E verifica |
| Reembolso externo (dinero) | Se solicita por canal aplicable | **N** | Acción fuera del plugin (total o parcial) | Proveedor externo |
| Registrar resultado de reembolso externo verificado | N | **N** | **S** (incluye parcial con importe/referencia D-66) | E valida evidencia |
| Modificar credenciales o configuración sensible | N | **N** | **S** | E protege |
| Leer/exportar datos personales | Solo propios con acceso válido | P: mínimo necesario | P: política legal | E controla |
| Administrar reclamaciones | P: presentar reclamación | P: permisos pendientes | P: conforme ley | E protege |

**E-02 CERRADO EN POLÍTICA FUNCIONAL (D-78…D-85):** consultar [15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md](15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md). Sus ocho opciones A están aprobadas y aplican al inventario de 56 operaciones PER. **PENDIENTE TÉCNICO:** vincular *capabilities* reales por endpoint/acción, controles por recurso/fila/campo y la verificación de identidad E-07, sin revelar secretos. **D-64** impide cancelar por operador si existe dinero acreditado o reintegro/ajuste exigible.

## 6. Condiciones y efectos de cancelación parcial

| Estado de una línea | Cancelación ordinaria | Flujo alternativo | Cuestión pendiente |
| --- | --- | --- | --- |
| Físico pendiente, aún sin despacho | Puede ser elegible (D-58) | Ajuste auditable; si es la última línea física revisar delivery (D-63) | Elegibilidad y cálculo de entrega/tributos |
| Físico despachado, aún sin entregar | **No cancelar de un clic** (D-62) | Abrir incidencia y revisión logística | Resultado de revisión, responsabilidades, importes |
| Físico entregado | **No**, ya cumplida | Devolución/garantía/reclamación según ley | Reingreso de stock, costos de envío y documentos |
| Digital bloqueado sin pago acreditado | Candidato a elegible | Revocación de expectativa no pagada | Criterio legal y estado exacto |
| Digital con acceso habilitado pero sin evento de descarga | **No cancelar ni devolver automáticamente** (D-61) | Evaluación por administrador según política/derechos | Evidencia, estado de prestación, decisión y comunicación |
| Digital descargado | **No tratar automáticamente como pendiente** | Devolución/reclamación según normativa | Derechos de desistimiento/garantía aplicables |
| Servicio reservado, aún no prestado | Candidato a elegible | Liberar/reprogramar cupo cuando proceda | Política de cancelación y pagos ya aprobados |
| Servicio ya prestado | **No**, ya cumplido | Incidencia/reclamación/devolución según caso | Evidencia y tratamiento legal |

**E-03 APROBADO EN POLÍTICA FUNCIONAL (D-86…D-95):** consultar [16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md](16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md). Se aprobaron diez opciones A sobre expedientes, versión histórica de condiciones, elegibilidad física, acceso digital, no-show, reprogramación y cancelación de línea completa. **PENDIENTES:** matriz jurídica sectorial J-01/J-02/P0-25, evidencias, efectos sobre recursos E-05, importes E-04 y API E-08. Ningún importe o derecho se infiere del estado de la línea.

## E-04: política monetaria APROBADA (D-96…D-108)

El contrato `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md` **aprueba E04-A…M** como decisiones D-96…D-108: enteros de céntimos PEN, totales comerciales, único delivery por pedido, cotización congelada, diferencia de tarifa sin cargo automático, bruto versus comisión y reintegros externos verificados con historial inmutable.

Las máquinas E-01 y la política E-02/E-03 ya aprobadas no autorizan: redondear tributación por cuenta propia, modificar precio pactado para cuadrar un webhook, dividir cobro inicial, acreditar efectivo parcial como total o registrar reintegro no comprobado. E-04 y E-05 deben definir sumas, restricciones atómicas y eventos idempotentes, y E-08 la traducción a la API real del proveedor.

## 7. Impacto financiero y autorización

**Confirmado:** las cancelaciones financieras de pedidos pagados requieren administrador (D-60), no modifican registros originales (D-59) y jamás ejecutan reintegros desde el plugin (D-54).

**Propuesta de control preventivo, NO política comercial aprobada:** antes de autorizar una cancelación, el backend debe calcular un impacto esperado y comprobar de forma atómica elegibilidad, autorización, importe y versión del pedido. Si faltan datos para saber si un ajuste financiero es necesario, no permitir que el operador lo declare «sin impacto» por su cuenta.

**E-04 APROBADO EN POLÍTICA COMERCIAL/NUMÉRICA (D-96…D-108)**: importes, delivery, efectivo y reintegros documentados en doc.17. **E-05 APROBADO en política técnica (D-109…D-122)**: atomicidad e idempotencia a materializar en SQL. **PENDIENTES:** J-03 fiscal, P0-18/P0-19 físico, E-07 privacidad y E-08 proveedor.

## E-07 — identidad, datos y expiración de secretos (APROBADA D-135…D-148)

El documento [20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md](20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md) **APRUEBA E07-A…N (D-135…D-148)**: enlace privado solo lectura con vigencia 90 días, OTP sensible 8 dígitos/10 min/5 intentos, recuperación manual para teléfono, descargas digitales 24h, acceso por recurso/campo, protección del Libro, WordPress Privacy y conservación. **No** agregar estados E-01 ni conceder mutación por enlace. Nonce WP no sustituye identidad/capability.

**Pendientes:** campos/salidas exactos y controles REST E-08, modelo SQL P0-18/hosting P0-19, responsabilidades y plazos ANPD J-04/J-05/P0-25. **Los 90 días y OTP sí están aprobados** como D-137/D-138; no son plazos jurídicos ni implementación probada.

## E-06 — avisos derivados, NO estados del pedido

El documento `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` **APRUEBA E06-A…L como D-123…D-134**, con 26 eventos N6. El estado técnico del email no cambia pago, pedido ni línea E-01. Outbox E-05 y política de reintentos E-06 aprobadas; payload, identidad, retención y permisos específicos siguen en E-07/E-08.

## E-05 — política de bloqueo e idempotencia APROBADA (D-109…D-122)

El contrato E-05 en [18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md](18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md) está **APROBADO EN POLÍTICA TÉCNICA D-109…D-122**: 14 opciones E05-A…N sobre transacciones locales InnoDB, locks por variante/recurso-día, fases MP, TTL efectivo, idempotencia, inbox/outbox y conciliación. Sus 48 escenarios C5-X **no están probados**; P0-18/P0-19/E-08 deben cerrar esquema/motor/endpoint. No agregar enums E-01.

Las guardas D-36/D-37/D-64/D-74/D-95/D-107 siguen confirmadas: no reservar comercialmente antes de intento válido, no renovar hold, no duplicar cobro/restock/reintegro ni cancelar por operador con dinero acreditado. **E-05 aprobado las respeta**; E-08 decidirá los detalles reales del proveedor.

## 8. Auditoría y concurrencia — campos candidatos

**De las decisiones confirmadas** se deriva que no se debe destruir evidencia histórica. **Campos propuestos** (no diseño de tabla): identificador de evento; idempotency key/solicitud; actor y rol efectivo; pedido/línea/intento/registro fuente; acción y motivo; fecha UTC y zona local para presentación; estado previo/posterior; monto/moneda PEN; evidencia/referencia externa; origen; resultado; versión esperada de entidad; correlación de proveedor; datos sensibles mínimos.

Escenarios que requieren diseño transaccional: último producto o turno concurrente, doble clic, webhook tardío/duplicado, dos operadores cancelando la misma línea, administrador registrando dos veces un reembolso, edición de producto mientras existe pedido pendiente, expiración con tareas diferidas y reprogramación que libera un turno antes de asegurar el nuevo.

**E-05 APROBADO EN POLÍTICA TÉCNICA (D-109…D-122):** documento 18. **PENDIENTES:** esquema/índices/aislamiento P0-18, hosting/motor P0-19, retención E-07, proveedor E-08 y pruebas con autorización. No extrapolar transacciones desde Angular ni de una consulta SQL simple.

## 9. Ejemplos obligatorios de interpretación

- **Pedido mixto pagado**: físico pendiente, digital entregado, cita futura. El pedido puede estar pagado y solo parcialmente cumplido; cancelar el físico no cancela ni revoca automáticamente el digital o la cita.
- **Pedido contraentrega confirmado**: inventario y cita comprometidos; el dinero sigue pendiente. El registro de efectivo cambia el estado financiero, no el hecho de entrega.
- **Pago tardío tras caducar 15 min**: si se perdió la unidad o turno, incidencia; el sistema no crea stock ni cita. Cualquier devolución se ejecuta externamente.
- **Producto simple versus variable**: un simple usa sus propios precios; una variante sin precio explícito no hereda precio del padre para autorizar venta.
- **Reembolso no confirmado**: el comprador reclama; el administrador no puede registrar dinero como devuelto solo por iniciar una solicitud en el proveedor.
- **Cancelación de producto entregado**: se abre devolución/reclamación aplicable, no se vuelve a marcar «pendiente» para cancelar.

## 10. Decisiones que faltan para aprobar este contrato

| ID | Bloqueo concreto | Responsable de resolución |
| --- | --- | --- |
| E-01 | **CERRADO FUNCIONALMENTE:** 11 opciones A, nombres de estados y transiciones O/MP/H/F/G/V aprobados como D-67…D-77 en documento 14 | Propietario (aprobado) |
| E-02 | **CERRADO EN POLÍTICA FUNCIONAL:** ocho opciones A registradas D-78…D-85 (doc. 15). **PENDIENTES TÉCNICOS:** capabilities por ruta, datos por campo, autenticación, protección de sesiones y acceso E-07/E-08 | Propietario + validación de seguridad |
| E-03 | **CERRADO EN POLÍTICA FUNCIONAL:** diez opciones E03-A…J aprobadas D-86…D-95 (doc.16). **PENDIENTES J-01/J-02/P0-25:** matriz jurídica, pruebas/condiciones por operación y consecuencias técnicas E-04…E-08 | Propietario (aprobado) + revisión jurídica pendiente |
| E-04 | **CERRADO EN POLÍTICA COMERCIAL/NUMÉRICA D-96…D-108**, doc.17. **PENDIENTES POR SEPARADO:** J-03/P0-17/P0-25 fiscal, implementación de asientos/conciliación E-05 y proveedor/API E-08 | Propietario (aprobado) + SUNAT/consumidor pendiente |
| E-05 | **CERRADO EN POLÍTICA TÉCNICA D-109…D-122 (doc.18):** InnoDB/locks, MP en fases, webhooks/inbox, outbox, idempotencia y recuperación aprobados; P0-18/P0-19/E-08 y pruebas pendientes | Propietario (aprobado) + verificación técnica pendiente |
| E-06 | **CERRADO EN POLÍTICA FUNCIONAL/OPERATIVA D-123…D-134**, doc.19: eventos N6, correo WordPress, outbox, bandeja, citas, Libro, plantillas; E-07/E-08 y verificación legal/técnica pendientes | Propietario (aprobado) + seguridad/ley/hosting pendientes |
| E-07 | **CERRADO EN POLÍTICA FUNCIONAL D-135…D-148 (doc.20)**: datos/identidad, tokens, WordPress, ARCO, protección, retención e incidentes. **PENDIENTE E-08/J-04/J-05/P0-25:** capacidades/rest, evidencia y plazos legales | Propietario (aprobado) + revisión jurídica/técnica pendiente |
| E-08 | API pública/administrativa exacta, errores y semántica de reintentos | Diseño técnico aprobado |

**Condición de cierre del contrato completo:** E-01…E-07 están aprobados en sus políticas D-67…D-148. Completar J-01…J-05/P0-17/P0-25, capabilities y contratos REST E-08, SQL/hosting/ZIP P0-18/P0-19/P0-26 y campos/error/retención legal exactos. **Codex sigue sin autorización de implementar.**
