# E-01 — Máquinas de estados y transiciones V1: contrato funcional aprobado

**Estado:** **E-01 APROBADO FUNCIONALMENTE** — 11 opciones A aprobadas expresamente por el propietario (E01-A…G, E01-H1…H4), registradas como **D-67…D-77**. Las máquinas, nombres y transiciones funcionales O/MP/H/F/G/V de este contrato son la referencia aprobada; las anotaciones **PENDIENTE** dentro de las tablas siguen exigiendo contratos E-02…E-08. **No autoriza implementar código, compilar, ejecutar pruebas ni desplegar.**
**Alcance:** E-01 aprobado D-67…D-77 y **E-02…E-07 aprobados en sus políticas D-78…D-148**. Los eventos N6 y las expiraciones de enlaces E-07 no son estados de pedido/pago/línea. **PENDIENTES:** capabilities/REST E-08, matriz legal J-01…J-05/P0-25, SQL/hosting y pruebas. Ejemplos no crean tablas, estados ni permisos.
**Precedencia:** [01-DECISIONES-Y-ESTADO.md](01-DECISIONES-Y-ESTADO.md), [05-DOMINIO-Y-OPERACION.md](05-DOMINIO-Y-OPERACION.md), [12-CONTRATO-ESTADOS-TRANSICIONES-Y-PERMISOS.md](12-CONTRATO-ESTADOS-TRANSICIONES-Y-PERMISOS.md) y [13-MATRIZ-DE-HUECOS-Y-ESCENARIOS.md](13-MATRIZ-DE-HUECOS-Y-ESCENARIOS.md). Ante contradicción prevalecen los D confirmados.

**Nota E-07 APROBADA (D-135…D-148):** [20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md](20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md) fija seguimiento privado 90 días, verificación adicional y descargas digitales separadas de 24 h. **No modifica estados E-01:** caducidad del enlace de seguimiento no vence el pedido, token digital vencido no extingue derecho ni correo fallido cambia pago. E-08 definirá REST y protección real.

**Nota de precedencia vigente tras D-198 (18-11-2026):** las reglas funcionales E-01…E-07 aprobadas en este documento se conservan. Los textos anteriores que presentan E-08, DTO, capabilities, TTL o cobertura de expedientes como *pendientes de decisión* son **históricos**, no una segunda fuente de contratos: prevalecen `docs/01` D-01…D-198, `docs/41/42`, OpenAPI **0.5.0-draft (67 REST/64 rutas/117 DTO)** y `docs/23/24` (39 tablas, 56 PER). Solo quedan pruebas/compatibilidad de motor, proveedor, hosting, normativa por comercio y release en P-C/P-D; no se presentan como verificadas. El usuario autorizó aquí **documentación**, no código ni ejecución.

## E-08 — REST y MP aprobados sin crear nuevos estados (D-149…D-166)

El [contrato E-08](21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md) fija la política de rutas R8, checkout MP/COD, errores `pc_*` y webhooks Orders. Ningún endpoint puede saltar las transiciones **E-01** ni cambiar el dinero por retorno del navegador, estado externo no verificado, correo o expiración de token. El hold comercial local de 15 min no equivale a vigencia externa MP; pago tardío sin recurso conserva incidencia D-74. **No se ejecuta reembolso MP por API**.

La política está aprobada, pero **el esquema REST físico, los estados comprobables del proveedor, SQL/migraciones, identidad y pruebas siguen pendientes** de OpenAPI/DTO, P0-18/P0-19/P0-23/P0-25 y J-01…J-05.

## 1. Principios obligatorios y alcance de los estados

1. **D-55:** pedido administrativo, cada intento/movimiento de pago y cumplimiento de cada línea son ejes separados. No derivar entrega de pago, ni cobro de confirmación contraentrega.
2. **D-59:** datos y snapshots de pedido confirmado originales son inmutables; los cambios se registran como eventos y ajustes.
3. **D-56/D-58/D-61/D-62:** cancelación ordinaria solo de línea pendiente y elegible; digital habilitado sin descargar requiere evaluación administrativa; físico despachado no entregado requiere incidencia logística.
4. **D-60/D-64:** operador solo cancela con capacidad expresa, sin dinero acreditado y sin ajuste/reintegro exigible. Administrador autoriza cancelaciones financieras y registra reembolsos externos comprobados.
5. **D-27/D-36:** retención de disponibilidad 15 minutos desde creación válida de intento al pulsar Pagar. Selección de horario en ficha no retiene. Nunca renovar automáticamente.
6. **D-37/D-33:** contraentrega compromete stock y cupo al confirmar pedido, sin convertir pago pendiente en acreditado; un solo cobro inicial para todo el pedido.
7. **D-32/D-40:** URL de descarga protegida vence 24 horas; el derecho no necesariamente vence con la URL, se puede reemitir de forma segura; sin cuota predeterminada.
8. **D-28:** pago tardío sin recurso produce incidencia, no reserva ficticia; devolución externa o reprogramación administrada.
9. **D-63/D-65/D-66:** revisar delivery al cancelar último físico; cambio de dirección por ajuste sin reescribir original; reembolsos externos parciales comprobados pueden registrarse.

**Nomenclatura:** los identificadores de estado en mayúsculas son **vocabulario lógico FUNCIONAL APROBADO (D-67…D-77)**. No determinan nombres de tablas, rutas REST, interfaces TypeScript ni propiedades físicas. Cada transición no listada se rechaza por defecto en su dominio, salvo decisión posterior expresa. Incidencias y devoluciones se mantienen separadas de pedido, pago y líneas.

**Actores:** C = comprador/flujo público autorizado; O = operador sujeto a la **política E-02 ya aprobada D-78…D-85** y permisos de recurso; A = administrador; S = backend/proveedor verificado. La matriz por operación está en el documento 15. Quedan pendientes los nombres técnicos de capabilities, la identidad/privacidad E-07 y los endpoints E-08.

## 2. Pedido administrativo: 5 estados aprobados (D-67/D-74/D-76)

| Estado | Significado | ¿Terminal? |
| --- | --- | --- |
| PENDIENTE_PAGO | Pedido creado para intento Mercado Pago, sin cobro verificado. El hold asociado puede caducar independientemente. | No |
| CONFIRMADO | Pedido comercial aceptado, recursos comprometidos; puede estar sin cobrar en contraentrega y sin entregar. | No |
| FINALIZADO | Todas las líneas resueltas conforme a contrato, sin incidencias, ajustes ni devoluciones financieras abiertas **al momento de cierre**; reclamaciones posteriores pueden registrarse sin reescribir el estado histórico. | Sí para operación normal |
| CANCELADO | Todas las líneas canceladas elegiblemente, sin prestación ya cumplida; el cobro eventual ha sido conciliado. | Sí después de conciliación |
| VENCIDO | Venció la retención local sin pago verificado; el pago externo puede seguir pendiente. | No en caso de acreditación tardía |

**Derivados de solo lectura (NO estados administrativos):** CANCELACION_PARCIAL, CUMPLIMIENTO_PARCIAL, TIENE_INCIDENCIA, SALDO_PENDIENTE, REINTEGRO_PARCIAL. Se calculan desde líneas, pagos y eventos.

| ID | Origen → destino | Actor y disparador | Precondiciones y efectos |
| --- | --- | --- | --- |
| O-01 | sin pedido → PENDIENTE_PAGO | C pulsa Pagar; S crea intento válido MP | Cotización PEN/datos/disponibilidad válidos; crear pedido e intento coherentes, iniciar hold 15 minutos solo tras éxito válido; reintento ambiguo no duplica |
| O-02 | sin pedido → CONFIRMADO | C confirma contraentrega; S valida | Todas las líneas cobrables en un mismo acto presencial; comprometer stock/cita inmediatamente; registrar obligación de cobro PENDIENTE |
| O-03 | PENDIENTE_PAGO → CONFIRMADO | S verifica pago MP | Retención y recursos vigentes y válidos; confirmar recursos una vez; no declarar líneas entregadas |
| O-04 | PENDIENTE_PAGO → VENCIDO | S comprueba tiempo de hold | Venció sin pago acreditado; liberar disponibilidad lógica; NO cancelar el pago externo por inferencia |
| O-05 | VENCIDO → CONFIRMADO | A resuelve pago tardío acreditado | Solo si disponibilidad real fue recuperada y comprobada mediante nueva asignación; no resucitar hold vencido |
| O-06 | PENDIENTE_PAGO / CONFIRMADO / VENCIDO → CANCELADO | S/O/A conforme autorización | Todas las líneas efectivamente canceladas, sin cumplidas; conciliación de dinero si lo hubo, preservando original |
| O-07 | CONFIRMADO → FINALIZADO | S tras eventos/administración verificados | Todas las líneas resueltas y cero incidencias/devoluciones/ajustes pendientes **en ese momento**; nuevas reclamaciones posteriores se registran aparte sin regresión del pedido |
| O-08 | VENCIDO → CANCELADO | A/S resuelve expediente | Si existe cobro tardío, dinero reintegrado externamente y resultado comprobado; sin cobro no inventar devolución |
| O-09 | CONFIRMADO → CONFIRMADO | O/A/S ejecuta cumplimiento o ajuste | Cancelación parcial, cambio de dirección o entrega afectan registros asociados, no estado global automáticamente |

**Prohibido:** CONFIRMADO → PENDIENTE_PAGO; FINALIZADO/CANCELADO → PENDIENTE_PAGO; VENCIDO → CONFIRMADO sin pago acreditado y recursos comprobados; CANCELADO por cancelar solo una línea; FINALIZADO con incidente o conciliación abierta.

**E01-1 CERRADO como definición funcional (D-67/D-74/D-76):** cinco estados, derivados independientes, recuperación de vencido solo con pago acreditado y disponibilidad nueva comprobada, cierre sin pendientes al momento del cierre. **E-04/E-05 políticas aprobadas**; quedan pendientes plazos normativos, prueba real, SQL/hosting e integración/reconciliación E-08.

## 3. Intento de pago y dinero: estados aprobados (D-68)

### 3.1 Intento Mercado Pago

| Estado | Significado |
| --- | --- |
| CREADO | Intento válido en servidor, con referencia local y resultado externo no determinado |
| PENDIENTE | Transacción pendiente según verificación del proveedor |
| APROBADO | Acreditación verificada por proveedor |
| RECHAZADO | Rechazo verificado por proveedor |
| CANCELADO_PROVEEDOR | Cancelación verificada en proveedor, **no** por expiración del hold |
| REQUIERE_CONCILIACION | Respuesta ambigua, inconsistente o no verificable |

| ID | Origen → destino | Actor / guarda | Efectos |
| --- | --- | --- | --- |
| MP-01 | sin intento → CREADO | S crea intento válido por orden, referencia/importe PEN | Asociar pedido y hold, clave de correlación, no duplicar |
| MP-02 | CREADO → PENDIENTE | S verifica pendiente | Mantener estado financiero sin afirmar cobro |
| MP-03 | CREADO / PENDIENTE / REQUIERE_CONCILIACION → APROBADO | S verifica aprobación autoritativa | Evento único de acreditación; retención/recursos se verifican por separado |
| MP-04 | CREADO / PENDIENTE / REQUIERE_CONCILIACION → RECHAZADO / CANCELADO_PROVEEDOR | S verifica estado proveedor | Mantener evidencia, liberar hold si corresponde y no hay cobro |
| MP-05 | CREADO / PENDIENTE → REQUIERE_CONCILIACION | S detecta ambigüedad o fallo | No crear otro cobro a ciegas ni declarar éxito |
| MP-06 | cualquier estado → mismo estado | S recibe webhook/callback duplicado concordante | Cero efectos comerciales duplicados |
| MP-07 | APROBADO → estado reembolsado | **NO ES TRANSICIÓN** | Reembolso es movimiento externo verificado, sin borrar aprobación original |

**Invariante:** HOLD_VENCIDA no implica MP_CANCELADO_PROVEEDOR. Una aprobación externa tardía se procesa, se concilia y puede producir incidencia D-28. Un evento contradictorio frente a APROBADO no degrada estado por sí solo: requiere revisión.

### 3.2 Efectivo, reintegros y saldo

- Contraentrega: el pedido puede estar CONFIRMADO con **obligación de cobro pendiente**, sin inventar intento de MP. El cobro efectivamente recibido se registra con actor, monto, fecha y control de duplicados (D-51).
- La corrección del efectivo es **asiento de ajuste** autorizado solo por A (D-53), sin mutar el original.
- Reembolso total/parcial: se efectúa **fuera del plugin** y el administrador registra el resultado **verificado** con importe PEN y referencia externa; nunca ejecutar devolución desde API (D-54/D-66).
- Estados agregados para presentar en pantalla, **solo derivados** de movimientos: SIN_COBRO, PENDIENTE_COBRO, ACREDITADO, REINTEGRO_PARCIAL, REINTEGRO_TOTAL, EN_DISPUTA, CONCILIACION_PENDIENTE.
- Para determinar permisos del operador se consulta historial de **cualquier cobro acreditado** y obligación de reintegro/ajuste, no solo saldo neto igual a cero (D-64).

**E01-2 CERRADO funcionalmente (D-68):** el intento mantiene su vocabulario y cobros/reembolsos son movimientos independientes. **E-05 aprobado** define política técnica de idempotencia y conciliación (D-109…D-122); **pendientes E-08/P0-18/P0-19**: referencias concretas, proveedor real, SQL/hosting y verificación de errores.

## 4. Retenciones: estados aprobados (D-69)

| Estado | Interpretación |
| --- | --- |
| VIGENTE | Recurso reservado por 15 minutos desde intento válido en servidor |
| COMPROMETIDA | Recurso asignado a la operación confirmada, sin doble asignación |
| VENCIDA | Tiempo efectivo transcurrido; no se renueva automáticamente |
| LIBERADA | Recurso liberado expresamente por fallo/cancelación válida |

| ID | Antes → después | Condición |
| --- | --- | --- |
| H-01 | sin retención → VIGENTE | Inicio de MP válido, stock/turno disponible, jamás al navegar |
| H-02 | VIGENTE → COMPROMETIDA | Pago verificado antes del vencimiento y recursos válidos |
| H-03 | VIGENTE → VENCIDA | Comprobación de fecha por servidor aunque cron no corra |
| H-04 | VIGENTE → LIBERADA | Rechazo/cancelación coherente sin cobro ni compromiso |
| H-05 | VENCIDA → COMPROMETIDA | **PROHIBIDO automáticamente**; pago tardío obliga nueva verificación de recursos/incidencia |

Contraentrega no utiliza una retención de pago de quince minutos: en una confirmación válida compromete recursos inmediatamente (D-37). La fecha de caducidad del token digital es **otra entidad**.

## 5. Línea física: estados aprobados (D-70/D-75/D-77)

| Estado | Significado |
| --- | --- |
| PENDIENTE | Aún no se inicia preparación |
| PREPARANDO | Comercio procesa la línea |
| LISTO | Lista para recojo o despacho |
| DESPACHADO | Salió a entrega; comprador aún no recibió |
| ENTREGADO | Entrega confirmada |
| CANCELADO | Cancelación ordinaria válida antes de cumplimiento, con auditoría |

| ID | Origen → destino | Actor / guarda y efectos |
| --- | --- | --- |
| F-01 | al crearse línea de pedido → PENDIENTE | S crea la línea/snapshot durante el pedido incluso si aún espera pago; **no** habilitar preparación/despacho hasta confirmar/comprometer recursos |
| F-02 | PENDIENTE → PREPARANDO | O/A con permiso, pedido confirmado |
| F-03 | PENDIENTE/PREPARANDO → LISTO | O/A documenta preparación |
| F-04 | LISTO → DESPACHADO | O/A confirma salida logística |
| F-05 | LISTO → ENTREGADO | O/A registra recojo en local verificado |
| F-06 | DESPACHADO → ENTREGADO | O/A registra entrega efectiva |
| F-07 | PENDIENTE/PREPARANDO/LISTO → CANCELADO | Línea elegible y sin despacho; O solo sin cobro acreditado ni ajuste, A en demás casos; si última física, revisar delivery D-63 |
| F-08 | DESPACHADO → DESPACHADO + incidencia | O/A registra entrega fallida o solicitud de cancelación; revisión logística D-62 |
| F-09 | DESPACHADO → CANCELADO | **CONFIRMADO por D-75 solo tras investigación logística y no entrega comprobada**, elegibilidad legal, autorización administrativa y ajuste correspondiente. **PENDIENTE E-03/E-04/E-05:** prueba de no entrega, reglas del ajuste, transacción y resolución; nunca clic ordinario |
| F-10 | ENTREGADO → CANCELADO | **PROHIBIDO**; usar devolución, reclamación o garantía en registros separados |

No crear estado «devuelto» que borre ENTREGADO; la devolución posterior debe quedar como evento con evidencia. Un cambio de dirección de entrega tras confirmar es ajuste auditable y debe validar cobertura y tarifa (D-65).

## 6. Línea digital: estados aprobados (D-71)

| Estado | Significado |
| --- | --- |
| BLOQUEADO | Sin pago acreditado, con incidencia o derecho aún no habilitado |
| HABILITADO | Cobro acreditado y derecho vigente |
| CANCELADO | Cancelación válida de línea antes de habilitar derecho, cuando sea elegible |
| REVOCADO | Derecho previamente habilitado restringido por decisión válida |

| ID | Origen → destino | Actor / guarda y efectos |
| --- | --- | --- |
| G-01 | sin línea → BLOQUEADO | S crea línea; incluye contraentrega pendiente de cobrar |
| G-02 | BLOQUEADO → HABILITADO | S comprueba dinero acreditado y derecho de acceso |
| G-03 | BLOQUEADO → CANCELADO | O/A únicamente si línea elegible y permisos; O sin cobro |
| G-04 | HABILITADO → HABILITADO + descarga registrada | S comprueba token, límites opcionales y autorización; **no** convierte DESCARGADO en estado terminal |
| G-05 | HABILITADO → HABILITADO + token vencido | S advierte vencimiento a las 24 h; reemisión segura posible |
| G-06 | HABILITADO → REVOCADO | A tras decisión legal/comercial válida auditada; no automáticamente tras solicitud de reembolso |
| G-07 | HABILITADO sin descarga → CANCELADO | **NO automático**: revisión A bajo D-61 y derechos aplicables |
| G-08 | REVOCADO → HABILITADO | **PENDIENTE** de restauración segura; prohibido reusar token vencido |

**Diferencia decisiva:** evento DESCARGA_REALIZADA no es estado final; TOKEN_VENCIDO no extingue por sí mismo el derecho; ausencia de descarga no obliga a reembolsar.

## 7. Línea de servicio y cita efectiva: estados aprobados (D-72)

**Distinción:** la línea de servicio puede existir en un pedido PENDIENTE_PAGO, pero la **cita efectiva** y su cupo comprometido se crean únicamente al confirmar recursos. Antes solo hay selección en ficha y, desde Pagar, una **retención H**; no duplicar una «cita provisional» ocupando otro recurso.

| Estado de línea de servicio | Significado |
| --- | --- |
| PENDIENTE_CONFIRMACION | Línea en pedido MP aún sin pago acreditado; su posible retención H es una entidad separada |
| CONFIRMADA | Cita efectiva con cupo comprometido, incluso si contraentrega sigue sin cobrar |
| EXPIRADA | Línea nunca confirmó cita porque venció/falló su intento de pago; no equivale a cancelación solicitada |
| REALIZADA | Servicio efectivamente prestado |
| CANCELADA | Cancelación elegible ya gestionada con auditoría |
| NO_ASISTIO | Inasistencia registrada, sin multa o devolución automática |

| ID | Origen → destino | Actor / guarda y efectos |
| --- | --- | --- |
| V-01 | sin línea → PENDIENTE_CONFIRMACION | S crea línea al iniciar pedido MP; selección en ficha no crea cita; retención H se gestiona separadamente |
| V-02 | sin línea → CONFIRMADA | S confirma contraentrega válida, crea cita efectiva y compromete cupo atómicamente |
| V-03 | PENDIENTE_CONFIRMACION → CONFIRMADA | S verifica MP y retención vigente, crea cita efectiva con recurso exclusivo |
| V-04 | PENDIENTE_CONFIRMACION → EXPIRADA | S detecta hold vencido o pago no acreditado y libera su disponibilidad; no fingir cancelación voluntaria |
| V-05 | CONFIRMADA → REALIZADA | O/A registra prestación verificada |
| V-06 | CONFIRMADA → NO_ASISTIO | O/A constata no presentación; efectos económicos pendientes |
| V-07 | CONFIRMADA → CANCELADA | O/A con elegibilidad, política D-39 y restricciones D-60/D-64 |
| V-08 | CONFIRMADA → CONFIRMADA + evento de reprogramación | O/A; asegurar intervalo nuevo antes de liberar el anterior y conservar el historial; no duplicar citas activas |
| V-09 | REALIZADA/NO_ASISTIO → CANCELADA | **PROHIBIDO ordinariamente**; abrir incidencia/devolución/reclamación |
| V-10 | EXPIRADA + MP pago tardío | No confirmar automáticamente; S abre incidencia si no hay recurso y A resuelve. EXPIRADA → CONFIRMADA solo si recupera cupo mediante nueva asignación comprobada, con auditoría |
| V-11 | PENDIENTE_CONFIRMACION → CANCELADA | Solo cancelación comercial válida antes de vencimiento; guardar motivo separado de expiración automática |

Servicio presencial: una agenda/recurso, un cliente por franja sin solapamiento y máximo una cita por pedido. Zona horaria, duración, buffers, anticipación, periodos de no-show y reglas comerciales exactas siguen en E-03/E-04.

## 8. Incidencia, dirección y reintegro externo como registros separados (D-73)

| Registro | Estados candidatos | Regla de decisión confirmada |
| --- | --- | --- |
| Incidencia | ABIERTA → EN_REVISION → RESUELTA | Pago tardío sin recursos y físico despachado sin entrega exigen revisión; la incidencia no reescribe pago ni pedido |
| Solicitud de cambio de dirección | SOLICITADA → VALIDADA / RECHAZADA → APLICADA | Guardar snapshot original, nueva dirección vigente por ajuste, revalidar cobertura/tarifa y viabilidad (D-65) |
| Solicitud de devolución | RECIBIDA → EN_REVISION → TRAMITADA_FUERA / RECHAZADA_CON_MOTIVO | Solicitud o derivación no acredita dinero devuelto |
| Resultado de devolución | Evento EXTERNO_VERIFICADO, o discrepancia pendiente | Solo A registra resultado total/parcial realmente comprobado; no ejecutar reintegro API (D-54/D-66) |

No convertir “solicitud tramitada” en dinero devuelto. Si el cambio de dirección afecta delivery en pedido pagado, la aplicación se detiene hasta calcular y autorizar el ajuste financiero pertinente.

## 9. Prohibiciones y fallos con independencia de estados

| Situación | Comportamiento necesario por decisiones aprobadas | Contrato técnico restante |
| --- | --- | --- |
| Webhook duplicado o callback fuera de orden | No duplicar dinero, stock ni reservas ni borrar historia verificada | Llaves idempotentes y reconciliación E-05 |
| Pago después de hold vencido | Incidencia si ya no hay recurso; no inventar disponibilidad | Reconstrucción/compensación E-05 |
| Operador cancela y existe cobro acreditado | Denegar incluso si saldo actual es cero (D-64) | Capacidad E-02 y comprobación atómica E-05 |
| Dos actores cancelan la misma línea | Solo un ajuste válido y no dos reposiciones | Versionado E-05 |
| Servicio se mueve a turno ocupado | Denegar y conservar turno anterior | Exclusión mutua E-05 |
| Físico ya despachado no entregado | Incidencia, sin cancelación inmediata (D-62) | Resultados de investigación E-03 |
| Digital habilitado no descargado | Revisión A, nunca reintegro automático (D-61) | Matriz legal E-03 |
| Reintegro parcial externo se registra dos veces | No acreditar dos veces mismo reintegro ni mutar cobro inicial | Referencia única, acumulados E-04/E-05 |
| Cancelar último físico con delivery | Revisar costo real/derechos; no monto fijo inventado (D-63) | Fórmula de delivery e impuestos E-04 |
| Cambiar dirección confirmada | Ajuste sin sobrescribir original, cobertura/tarifa revalidada | Aprobación monetaria y reintentos E-04/E-08 |

Los errores de estado, autorización, dinero y versión se deben distinguir para UX; nombres de códigos HTTP/JSON dependen de E-08, no quedan aprobados aquí.

## 10. Definition of Ready por transición

Antes de que Codex implemente cualquier transición O/MP/H/F/G/V, el contrato operativo completo debe precisar: **origen, evento, destino, actor/capability, precondición, estado de pagos, impacto en líneas/stock/cita/delivery, movimiento financiero, atomicidad, idempotencia, auditoría, error/recuperación y criterio de aceptación**. El **comportamiento funcional** de estas tablas quedó aprobado (E-01), pero su ejecución segura exige cerrar también E-02…E-08. No confundir autorización funcional con permiso para implementar.

## 11. Registro de aprobaciones E-01 — CERRADO FUNCIONALMENTE

**Confirmación expresa:** el propietario aprobó las **11 opciones A de E-01**. Sus alternativas B quedan descartadas. Las decisiones quedan registradas como D-67…D-77.

| Origen | Decisión registrada | Resultado aprobado |
| --- | --- | --- |
| E01-A | D-67 | Estados administrativos: PENDIENTE_PAGO, CONFIRMADO, FINALIZADO, CANCELADO, VENCIDO; indicadores de pago/entrega/incidencias derivados o entidades separadas |
| E01-B | D-68 | Intentos MP: CREADO, PENDIENTE, APROBADO, RECHAZADO, CANCELADO_PROVEEDOR, REQUIERE_CONCILIACION; movimientos financieros separados |
| E01-C | D-69 | Retención: VIGENTE, COMPROMETIDA, VENCIDA, LIBERADA; vencimiento local NO cancela MP |
| E01-D | D-70 | Físico: PENDIENTE, PREPARANDO, LISTO, DESPACHADO, ENTREGADO, CANCELADO; incidencia logística aparte |
| E01-E | D-71 | Digital: BLOQUEADO, HABILITADO, CANCELADO, REVOCADO; descargas y vencimiento de token son eventos |
| E01-F | D-72 | Servicio: PENDIENTE_CONFIRMACION, CONFIRMADA, EXPIRADA, REALIZADA, CANCELADA, NO_ASISTIO; cita efectiva solo cuando el recurso se compromete |
| E01-G | D-73 | Incidencias, cambio de dirección y reintegros como registros asociados independientes |
| E01-H1 | D-74 | Recuperación de pedido VENCIDO solo con pago tardío validado, nueva disponibilidad comprobada y resolución auditada; sin reactivar hold |
| E01-H2 | D-75 | Línea física DESPACHADA no entregada puede cancelarse tras investigación y no entrega comprobada, elegibilidad legal, autorización administrativa y ajustes |
| E01-H3 | D-76 | FINALIZADO solo cuando todas las líneas estén resueltas y sin incidencias/ajustes/reembolsos abiertos **al cierre**; reclamaciones posteriores generan expediente aparte |
| E01-H4 | D-77 | Sin segunda máquina de envío V1; estados por línea física y eventos logísticos |

### Alcance de la aprobación

- **APROBADO:** vocabularios lógicos y transiciones funcionales O-01…O-09, MP-01…MP-07, H-01…H-05, F-01…F-10, G-01…G-08 y V-01…V-11, sujetos a las precondiciones y prohibiciones documentadas. Las filas rotuladas **NO ES TRANSICIÓN** o **PROHIBIDO** siguen siéndolo.
- **NO APROBADO:** escoger esquemas SQL, atributos de entidades, números de versión, bibliotecas, endpoints, eventos de proveedor no verificados o contratos de autorización/cálculo/concurrencia por inferencia.
- **AÚN BLOQUEANTES:** E-02…E-07 aprobados en políticas D-78…D-148 (docs.15…20). Estados E-01 no cambian por correo ni tokens. Quedan J-01…J-05/P0-17/P0-25, capabilities y API E-08, SQL/hosting, nueve P0 y pruebas. No confundir admisión interna E05-D con hold comercial D-36.
- **Codex:** no puede escribir código del plugin hasta que se cumplan las condiciones del repositorio y exista autorización explícita. El cierre de E-01 no es la aprobación del sistema completo.
