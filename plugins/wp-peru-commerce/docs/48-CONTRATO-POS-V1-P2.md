# 48 — Contrato POS V1 (P2): ventas presenciales en efectivo

**Corte: 2026-11-27, rama `main`, partida b1e3552 (ajustes de contrato previos a P3).**

**Estado:**

- P1 está **RATIFICADO por el propietario** (2026-11-27), con las precisiones D-202…D-207.
- P1b está **RESUELTO CONTRACTUALMENTE** (D-207).
- P2 está **APROBADO por el propietario** (2026-11-27): D-208, D-209 y D-210, incluida la operación atómica R8-83. Antes de P3 se resolvieron seis ajustes puntuales (D-211…D-216, §1).
- P3 (backend PHP) está **AUTORIZADO** (P-B). El estado real de cada pieza consta en [docs/43](43-ESTADO-IMPLEMENTACION.md). Ninguna migración, compilación ni prueba se ha ejecutado.

Este documento convierte la propuesta de [docs/47 §2](47-POS-INTEGRADO-Y-APERTURA-PUBLICA.md) en contrato. Añade las decisiones D-201…D-216 a D-01…D-200 ([docs/01](01-DECISIONES-Y-ESTADO.md)). Para el POS prevalece sobre la propuesta de docs/47 y, en rutas y DTO, manda el OpenAPI `0.13.0-draft`. Nada de lo aquí descrito existe en código.

## 0. Precedencia, independencia y etiquetas

**Independencia.** PeruCommerce no depende de los productos comerciales privados de VSG: no usa su POS de escritorio, sus bibliotecas, servicios, conectores MCP ni datos. Este contrato parte del código del propio plugin y de fuentes oficiales públicas. Ninguna fase puede introducir esa dependencia.

**Lo que existe (CONFIRMADO en código, no PROBADO EN ENTORNO):**

- R8-24 cobra en efectivo un pedido de contraentrega por el importe exacto (`includes/cash-payments.php`).
- R8-25 corrige ese cobro con un delta firmado; R8-27 registra reembolsos externos (`includes/external-refunds.php`).
- Hay 19 capacidades primitivas (`includes/access-roles.php`).
- La entrega física descuenta stock con `stockOutWithin` (`includes/physical-fulfillment.php`) y `includes/orders-finalization.php` deriva FINALIZADO.
- Las puertas de módulo fallan cerradas (`includes/module-gates.php`).

**Lo que este documento propone:** todo lo demás, con la etiqueta DOCUMENTADO/NO IMPLEMENTADO salvo que se indique otra cosa.

## 1. Decisiones D-201…D-216

| ID | Decisión | Estado |
| --- | --- | --- |
| D-201 | Ratificación del alcance POS V1 (M1–M6) y de D1–D14 de la propuesta P1, con las precisiones D-202…D-207 | CONFIRMADO por el propietario (2026-11-27) |
| D-202 | D2: sin aceptaciones ficticias; el canal web conserva íntegros sus contratos (§3) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-203 | D4: solo se vinculan a una sesión los movimientos de efectivo que entran o salen realmente de ese cajón; nunca de forma automática (§5) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-204 | D5: cinco capacidades específicas nuevas (19 → 24), sin ampliar las existentes (§4) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-205 | D6: errores, anulaciones, reembolsos y devoluciones, con invariantes y procedimientos seguros para las exclusiones (§7) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-206 | D13: flujo operativo de emisión externa del comprobante, identificación y registro de su referencia (§8) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-207 | D14/P1b: redondeo de efectivo a favor del consumidor, separando total original, ajuste y efectivo recibido (§6) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO; validación contable antes de P-D |
| D-208 | Transición O-10 «sin pedido → CONFIRMADO» del canal POS, sin estados nuevos (§9) | CONFIRMADO por el propietario (2026-11-27); implementación P3 |
| D-209 | Persistencia: 3 tablas nuevas (39 → 42), columnas aditivas y migración 1.0.2 → 1.1.0 (§10) | CONFIRMADO por el propietario (2026-11-27), con el ajuste D-212; implementación P3 sin ejecutar |
| D-210 | REST R8-71…R8-83, incluida la devolución atómica R8-83, enmiendas aditivas de R8-20/21/24/25/27/45/46 y OpenAPI `0.13.0-draft` (§11) | CONFIRMADO por el propietario (2026-11-27); implementación P3 |
| D-211 | Comprobante externo: total original, redondeo e importe pagadero separados en R8-81 (§8) | Ajuste previo a P3 ordenado por el propietario; DOCUMENTADO |
| D-212 | Reembolso por línea: un expediente por operación R8-83, líneas en `pc_pos_return_lines`, devoluciones parciales y sucesivas y una nota de crédito por expediente (§7, §10) | Ajuste previo a P3 ordenado por el propietario; DOCUMENTADO |
| D-213 | Importes cero: venta POS con efectivo debido 0 rechazada; reembolso 0 admitido sin movimiento de cajón (§6, §7) | Ajuste previo a P3 ordenado por el propietario; DOCUMENTADO |
| D-214 | Reintentos idempotentes recuperables aunque la sesión de caja se cierre después (§12) | Ajuste previo a P3 ordenado por el propietario; DOCUMENTADO |
| D-215 | Se conservan todas las puertas de seguridad; el módulo no preparado responde 423 `pc_module_not_ready` (§4) | Ajuste previo a P3 ordenado por el propietario; DOCUMENTADO |
| D-216 | Desactivar el POS no deja sesiones ni obligaciones sin vía de resolución (§4) | Ajuste previo a P3 ordenado por el propietario; DOCUMENTADO |

**Ratificación de D1–D14 (D-201).** Quedan ratificadas tal como se propusieron, salvo las precisiones del propietario:

| # | Decisión ratificada | Precisión del propietario |
| --- | --- | --- |
| D1 | Contacto opcional en una venta presencial entregada en el acto | — |
| D2 | Versiones de política y privacidad NULL solo en el canal `pos` | Sin aceptaciones ficticias; el canal web íntegro → D-202 |
| D3 | Tablas `pc_cash_sessions` y `pc_cash_session_movements` | Nombres fijados en D-209 |
| D4 | Vínculo de sesión en el movimiento financiero | Solo efectivo real del cajón; nunca automático → D-203 |
| D5 | R8-64 sigue solo para A | Capacidades específicas, sin ampliar las existentes → D-204 |
| D6 | Correcciones solo por A | Errores, anulaciones, reembolsos y devoluciones consistentes → D-205 |
| D7 | Importe entregado y vuelto como evidencia fuera del libro | — |
| D8 | Digitales y servicios excluidos de V1 | Procedimiento seguro → D-205 |
| D9 | Entrega atómica con las transiciones existentes | Verificada en D-208 |
| D10 | Módulo desactivado por defecto | — |
| D11 | Bloqueos ordenados existentes | Orden global fijado en §12 |
| D12 | «Constancia de operación (no es comprobante de pago)» | — |
| D13 | Sin capturar el número de documento del adquirente | Flujo operativo completo → D-206 |
| D14 | Ajuste `cash_rounding` de 0 a 9 céntimos | Verificación tributaria y financiera → D-207 |

**Alcance V1 ratificado (M1–M6):**

- **M1 Caja presencial.** Pantalla wp-admin para A y O, módulo desactivado por defecto, búsqueda por nombre o SKU exacto, lector de códigos como teclado, solo en línea.
- **M2 Carrito.** Productos físicos y variantes publicadas, con precio y stock del servidor; máximo 100 líneas.
- **M3 Efectivo.** Solo efectivo, con redondeo D-207.
- **M4 Sesión de caja.** Apertura con fondo, ingresos y retiros con motivo, arqueo ciego y cierre inmutable; una sesión abierta por caja y por persona.
- **M5 Constancia de operación.** No es comprobante de pago.
- **M6 Historial y correcciones.** Pedidos por canal, ventas pendientes de comprobante, y correcciones y devoluciones por A (D-205).

**Cambio respecto de la propuesta P1:** la devolución física con reingreso pasa de EXCLUIDO a la operación atómica R8-83, solo para A. Es la única forma de cumplir la precisión D6 («no permitir inconsistencias entre inventario, dinero y pedidos»): con las operaciones existentes, stock (R8-35) y dinero (R8-27) se registrarían en transacciones separadas. El propietario lo aprobó el 2026-11-27 (D-210).

## 2. Requisitos peruanos (fuentes oficiales; no es certificación)

- **Oportunidad de emisión.** El comprobante se emite en la entrega del bien o en el pago, lo que ocurra primero; en mostrador, en el acto. PeruCommerce **no emite CPE**: el POS recuerda la obligación y registra la referencia del comprobante emitido fuera.
- **Más de S/700.** La boleta exige identificar al adquirente.
- **Infracción.** No emitir u otorgar el comprobante es infracción (Código Tributario, art. 174, num. 1). La responsabilidad es del comerciante; ninguna constancia del POS la sustituye.
- **«Ticket».** Es un tipo de comprobante de pago; el documento interno no puede llamarse así ni imitar su formato.
- **Redondeo.** En pagos en efectivo es a favor del consumidor, a múltiplos de S/0.10, porque las monedas de 1 y 5 céntimos salieron de circulación. No aplica a tarjetas ni transferencias.

Fuentes al final (§17). Ninguna afirmación de este documento acredita cumplimiento tributario ni de protección al consumidor.

## 3. Canal y obligaciones del canal web (D-202)

- **Canal derivado.** Un pedido es `pos` si y solo si `pc_orders.pos_session_id` no es NULL; en otro caso es `web`. No hay columna `channel` ni relleno: todos los pedidos existentes tienen `pos_session_id` NULL y siguen siendo `web`. REST expone `channel` como valor derivado (§11).
- **Canal web sin cambios.** Checkout, aceptación de política y privacidad, contacto D-24, seguimiento D-34, notificaciones, Libro de Reclamaciones virtual y R8-64 siguen exactamente igual. Ninguna regla de este documento relaja un contrato web.
- **Sin aceptaciones ficticias.** En `pos`, `policy_version_id` y `privacy_version_id` son NULL, porque nadie aceptó nada en un formulario. Las columnas admiten NULL, pero una restricción CHECK de la base exige ambas versiones cuando `pos_session_id` es NULL y exige `payment_method = 'cash'` cuando no lo es (§10). Una fila web sin versiones no puede escribirse.
- `customer_snapshot` contiene solo los datos realmente entregados; es `{}` en una venta anónima. El POS no pide nombre, correo ni teléfono.
- **D-24 y D-34 no aplican a `pos`.** La venta se entrega en el acto: no hay contacto obligatorio ni enlace de seguimiento.
- **Recojo en tienda:** `delivery_cents = 0`, `payment_method = cash`.
- **Libro de Reclamaciones.** En el establecimiento es obligación del comerciante. El plugin no lo certifica ni lo sustituye.

## 4. Capacidades y permisos (D-204)

Las 19 capacidades existentes no cambian de significado ni de rol. Se añaden cinco, otorgadas por `includes/access-roles.php` en P3:

| Capacidad | A | O | Alcance |
| --- | --- | --- | --- |
| `perucommerce_pos_sell` | Sí | Sí | Buscar en el catálogo POS, cobrar en su propia sesión abierta y reimprimir la constancia |
| `perucommerce_cash_session_operate` | Sí | Sí | Abrir, consultar y cerrar **su propia** sesión con arqueo ciego |
| `perucommerce_cash_session_review` | Sí | No | Listar y ver sesiones con esperado y diferencia; cerrar a la fuerza |
| `perucommerce_cash_movements_record` | Sí | No (V1) | Ingresos (`pay_in`) y retiros (`pay_out`) con motivo |
| `perucommerce_external_documents_record` | Sí | Sí | Registrar la referencia del comprobante externo de un pedido `pos` |

**Reglas:**

- Una nota de crédito con R8-81 exige además `perucommerce_refunds_record_external`, así que en V1 solo la registra A.
- Las devoluciones y anulaciones POS (R8-83) son solo de A, con `orders_read`, `refunds_record_external` y `stock_manage` a la vez.
- `cash_record`, `cash_correct`, `reconciliation_manage`, `settings_manage` y `orders_read` no se amplían.
- R8-64 (canal web) sigue solo para A.
- O no ve el esperado ni la diferencia de ninguna sesión, ni siquiera de la suya: el arqueo es ciego.
- O no accede a la configuración fiscal ni a correcciones financieras (plugin `AGENTS.md`).

**Permisos funcionales PER-57…PER-62:**

| PER | Permiso funcional | R8 | A | O |
| --- | --- | --- | --- | --- |
| PER-57 | Vender en caja presencial y reimprimir la constancia | R8-71, R8-72, R8-82 | Sí | Sí |
| PER-58 | Operar la propia sesión de caja | R8-73, R8-74, R8-76 | Sí | Sí |
| PER-59 | Revisar sesiones y forzar su cierre | R8-77, R8-78, R8-79 | Sí | No |
| PER-60 | Registrar ingresos y retiros de caja | R8-75 | Sí | No |
| PER-61 | Registrar la referencia del comprobante externo POS | R8-80, R8-81 | Sí | Sí (sin nota de crédito) |
| PER-62 | Registrar devolución o anulación presencial POS | R8-83 | Sí | No |

**Puertas de seguridad** (`module-gates.php`, D-215), cerradas por defecto: `pos.read` (R8-71, R8-74, R8-77, R8-78, R8-80, R8-82), `pos.write` (R8-72, R8-81, R8-83) y `pos.sessions` (R8-73, R8-75, R8-76, R8-79). Se conservan todas las puertas existentes:

- `pos.read` exige plataforma, HTTPS, base de datos InnoDB, esquema 1.1.0 verificado y bloqueos verificados por integración;
- `pos.write` y `pos.sessions` exigen además PHP del comercio y que no haya mantenimiento en curso, como cualquier escritura;
- cada operación conserva su `permission_callback`, sus capacidades y las comprobaciones por recurso (sesión propia, pedido `pos`, expediente del mismo pedido).

Si una puerta está cerrada, la operación responde **423** `pc_module_not_ready` sin efectos, igual que el resto del plugin.

**Activación y desactivación del POS (D-216).** `pos.enabled` y `pos.registers` no son puertas de seguridad: son una regla de negocio que comprueba el servicio dentro de su transacción.

- **Solo abren nuevas obligaciones** R8-71 (catálogo), R8-72 (venta), R8-73 (apertura) y R8-75 (ingreso o retiro). Con el POS desactivado, o sin la caja de la operación entre las configuradas, responden 423 `pc_module_not_ready` con `reason = pos_disabled`.
- **Siempre resuelven lo pendiente**, aunque el POS esté desactivado: R8-74 (consultar la propia sesión), R8-76 (cerrarla), R8-77/78 (revisar), R8-79 (cierre forzado), R8-80/81 (comprobantes pendientes y notas de crédito), R8-82 (constancia) y R8-83 (devolución). Sus puertas de seguridad siguen vigentes.
- **Sin sesiones huérfanas.** R8-46 rechaza con 409 `pc_conflict` (`reason = pos_sessions_open`) desactivar el POS o retirar una caja mientras haya una sesión abierta afectada; A la cierra antes con R8-76 o R8-79.
- **Serialización.** R8-46 y R8-73 bloquean la fila de ajustes (`FOR UPDATE`) antes que cualquier sesión. R8-46 consulta las sesiones abiertas con una lectura bloqueante; R8-72 y R8-75 bloquean la fila de la sesión. Así ninguna sesión se abre ni se usa con una configuración que ya la excluye.
- **Desactivar el plugin de WordPress** no es desactivar el POS: los datos se conservan, y al reactivarlo las sesiones se resuelven con R8-76 o R8-79. La desinstalación es conservadora. Si el esquema deja de estar verificado, la puerta de seguridad cierra todo hasta completar la migración.

## 5. Sesión de caja y vínculo del efectivo (D-203)

- **Sesión por caja y por persona.** Como máximo una sesión abierta por `register_code` y una por usuario. La caja es un código configurado por A (`pos.registers`), no una identidad de terminal confiable.
- **Apertura:** fondo inicial en céntimos y fecha comercial en America/Lima.
- **Movimientos.** Cada entrada o salida real de efectivo del cajón es un movimiento inmutable:

| `kind` | Origen | Signo |
| --- | --- | --- |
| `sale_cash` | Venta POS (R8-72): el `initial_charge` en efectivo | + |
| `web_cash_in` | Cobro en mostrador de un pedido web de contraentrega (R8-24), **solo** si se indica `cash_session_id` | + |
| `pay_in` | Ingreso con motivo (R8-75, A) | + |
| `pay_out` | Retiro con motivo (R8-75, A), incluido el vuelto omitido a un cliente | − |
| `refund_cash` | Reembolso en efectivo desde el cajón: devolución POS (R8-83) o reembolso de un pedido web de efectivo (R8-27), **solo** si se indica `cash_session_id` | − |

- **Nunca automático.** El servidor no asigna un cobro a la sesión abierta del usuario si el cliente no envía `cash_session_id`. El repartidor que cobra una contraentrega fuera de la tienda no lo envía.
- **Nunca se vinculan** los cobros de Mercado Pago, los ajustes `cash_rounding` (no es efectivo), las correcciones R8-25 (son contables, no movimientos de cajón) ni ningún evento web sin efectivo físico en ese cajón.
- **Quién puede vincular.** O, solo su propia sesión abierta; A, cualquier sesión abierta de la instalación. Una sesión cerrada nunca admite movimientos (409 `pc_conflict`, `reason = pos_session_closed`).
- **Esperado** = fondo + Σ movimientos. Se calcula en el servidor desde los movimientos, no desde contadores.
- **Cierre.** O declara el efectivo contado sin ver el esperado (arqueo ciego). El servidor fija `expected_cents` y `counted_cents`, que son **inmutables** tras el cierre; la diferencia `counted − expected` se deriva de ellos al leer y no se almacena. Una diferencia posterior se explica con un expediente o un movimiento de otra sesión, nunca reabriendo la cerrada.
- **Cierre forzado.** A cierra una sesión ajena con motivo y, si lo tiene, el conteo; sin conteo, `counted_cents` queda NULL y la diferencia queda pendiente de revisión.
- **Sesiones largas.** Pasadas 24 horas abierta, la pantalla avisa y A puede forzar el cierre. No hay cierre automático.

## 6. Redondeo de efectivo (D-207, P1b)

**Verificación realizada (2026-11-27, fuentes de §17):**

- Ley 29571, art. 44, e Indecopi: el redondeo es a favor del consumidor y solo en efectivo.
- BCRP: las monedas de 1 y 5 céntimos dejaron de circular, así que el efectivo se maneja en múltiplos de S/0.10.
- Reglas de validación SUNAT (UBL 2.1, actualizadas al 26/08/2026): el comprobante admite `PayableRoundingAmount`. Es condicional y genera la observación 4314 si su valor absoluto supera 1. `PayableAmount` incluye el redondeo (observación 4312). El redondeo queda **fuera de la base imponible y del IGV**: un redondeo a favor del consumidor es negativo.

No se encontró pronunciamiento específico de SUNAT sobre su registro contable. Esta verificación documental permite cerrar el contrato, pero **no es certificación tributaria**: antes de P-D, el contador del comercio valida el tratamiento y se comprueba la configuración del emisor externo.

**Regla:**

- `T` es el total original de la cotización; no cambia la base ni el IGV de los precios.
- `R = floor(T / 10) × 10` es el efectivo debido, y `ajuste = T − R`, de 0 a 9 céntimos.
- Solo en efectivo y nunca hacia arriba.

**Asientos:**

- `initial_charge` en efectivo por `R`.
- Si el ajuste es mayor que 0, un evento `cash_rounding` por el ajuste. Salda el pedido sin contar como efectivo del cajón y nunca es reembolsable.
- Se cumple que `initial_charge + cash_rounding = T`.

**Enmienda acotada de D-105.** «Neto realmente recibido» sigue vigente. El evento `cash_rounding` es la única diferencia admitida entre el total y el efectivo, limitada a 0–9 céntimos y calculada por el servidor.

**R8-24, canal web (P1b).** `CashReceipt` gana `rounding_cents`, opcional y 0 por defecto. Se aceptan exactamente dos combinaciones; cualquier otra devuelve 409 `pc_conflict` (causa interna `pc_cash_amount_mismatch`, como hoy):

- `amount_cents` igual al saldo debido y `rounding_cents` 0;
- `amount_cents = floor10(saldo)` y `rounding_cents = saldo mod 10`, mayor que 0, solo si `floor10(saldo) ≥ 10`.

**R8-72, canal POS.** Siempre redondea. El servidor calcula `T`, el ajuste y `R`; el cliente envía los importes que mostró, y si no coinciden responde 409 `pc_quote_changed` sin escribir nada.

**Importes cero (D-213).** Un cobro en efectivo nunca es de 0 céntimos:

- Una venta POS con `R = 0` (es decir, `T < 10`) se rechaza con 422 `pc_business_ineligible` (`reason = cash_total_below_minimum`), sin escribir nada. Una línea de precio 0 es admisible si el total de la venta da `R ≥ 10`.
- En R8-24 el redondeo solo se admite si deja un efectivo de al menos 10 céntimos; un saldo menor se cobra exacto.
- Un reembolso de 0 es admisible en R8-83 (devolución sin dinero): crea el expediente y el reingreso, pero ningún evento `external_refund`, ningún `refund_cash` y ninguna nota de crédito pendiente (§7).
- `pay_in` y `pay_out` son de al menos 1 céntimo. El fondo inicial y el efectivo contado sí pueden ser 0.

**Entregado y vuelto (D7).** El importe entregado y el vuelto se guardan en el movimiento `sale_cash` (`tendered_cents` y `change_cents`), con `tendered − change = R`. No son ingresos ni asientos del libro.

**Reportes.** Muestran por separado el total original, el ajuste por redondeo y el efectivo recibido.

**Comprobante externo.** La boleta emitida fuera puede llevar `PayableRoundingAmount = −(T − R)` si el emisor lo admite. PeruCommerce no lo genera ni lo valida; R8-81 registra por separado el total, el redondeo y el importe pagadero del documento (§8).

## 7. Errores, anulaciones, reembolsos y devoluciones (D-205)

| Situación | Procedimiento | Efecto en pedido, stock y dinero |
| --- | --- | --- |
| Error antes del commit (validación, stock, versión) | Se revierte la transacción; el carrito sigue en la memoria de la pestaña | Ninguno |
| Resultado incierto (red, tiempo de espera) | Reintentar con **la misma** `idempotency_key`: el reintento espera el bloqueo de la operación y devuelve el resultado guardado, aunque la sesión ya se haya cerrado (D-214). Si el bloqueo vence, 409 `pc_conflict`: consultar R8-82, R8-77 o R8-78, o reintentar con la misma clave. Nunca una clave nueva para la misma intención | Ninguno adicional |
| Venta errónea ya confirmada (anulación) | O no anula: avisa a A. A usa R8-83 como devolución total con reingreso y reembolso en la misma sesión abierta | `sale_cash` +R y `refund_cash` −R suman 0; stock repuesto; pedido FINALIZADO con expediente |
| Anulación con la sesión original ya cerrada | A usa R8-83 indicando la sesión abierta de la que sale el efectivo | La diferencia de la sesión cerrada sigue inmutable; el expediente explica el movimiento |
| Error al dar vuelto | Aparece como diferencia en el arqueo. Si se debía vuelto al cliente, A registra un `pay_out` con motivo que cita el pedido | Solo el cajón |
| Devolución tras la entrega (D-89) | R8-83 (abajo) | Expediente, reingreso solo de lo apto, reembolso acotado |
| Corrección R8-25 o reembolso R8-27 sobre un pedido `pos` | Rechazado con 422 `pc_business_ineligible` (`reason = web_channel_required`); se usa R8-83 | Ninguno |

**R8-83: devolución o anulación presencial, en una sola transacción (D-212).**

1. **Un expediente por operación.** Cada R8-83 confirmada crea exactamente un expediente `pc_cases` (`case_type = pos_return_after_delivery`, `case_phase = closed_returned`, `order_line_id` NULL, `source_operation_id` = la operación), con motivo y evidencia.
2. **Líneas del expediente.** Cada línea devuelta es una fila de `pc_pos_return_lines` con la línea del pedido, el vendible, las unidades devueltas, las repuestas, la inspección y su `refund_cents`. Una línea del pedido aparece como máximo una vez por expediente.
3. **Unidades.** En cada línea, `restock_units ≤ returned_units ≤ unidades entregadas − Σ unidades devueltas en expedientes anteriores`. Solo la inspección `fit_for_sale` admite `restock_units > 0`; las unidades `not_fit` o `warranty_external` no vuelven al stock vendible. Superar el límite devuelve 422 `pc_business_ineligible` (`reason = return_units_exceeded`).
4. **Reingreso.** Se hace en `pc_stock_movements` (`source_type = case`, el expediente como origen), solo por las unidades repuestas.
5. **Distribución del reembolso.** El reembolso se asigna línea a línea:
   - `refund_cents` de cada línea ≤ precio unitario × unidades devueltas en esa operación;
   - `refund_cents` del cuerpo = Σ `refund_cents` de las líneas; si no, 422;
   - acumulado del pedido: Σ reembolsos de todos sus expedientes ≤ `R`, el efectivo recibido; si no, 422 `pc_business_ineligible` (`reason = refund_exceeds_cash`). El ajuste `cash_rounding` nunca se reembolsa.
6. **Dinero.** Si el reembolso `F` es mayor que 0, un evento `external_refund` (proveedor `cash`, sobre el `initial_charge`) por `F`, vinculado al expediente. Si `F = 0`, no hay evento.
7. **Cajón.** El movimiento `refund_cash` (−F) se registra solo si se indica `cash_session_id` de una sesión abierta y `F > 0`. Indicar `cash_session_id` con `F = 0` devuelve 422 `pc_business_ineligible` (`reason = refund_cash_not_applicable`).
8. **Estados.** La línea sigue ENTREGADO (F-10 sigue PROHIBIDO) y el pedido sigue FINALIZADO. La devolución vive en el expediente (O-07, D-89).
9. **Nota de crédito.** Un expediente con `F > 0` exige exactamente una nota de crédito (R8-81 con su `case_id`) y entra en la cola R8-80 hasta registrarla. Un expediente con `F = 0` no la exige.

**Devoluciones parciales y sucesivas.** Cada nueva devolución del mismo pedido es una nueva operación R8-83 con su propio expediente, sus líneas y, si reembolsa, su nota de crédito. Los límites de unidades y de dinero son acumulados sobre todos los expedientes anteriores del pedido, y se comprueban bajo el bloqueo del pedido y de sus líneas.

**Ejemplo.** Venta de `T = 1234` (dos líneas: 2 × 500 y 1 × 234), `R = 1230`, ajuste 4. Primera devolución: 1 unidad de la línea 1 con reembolso 500 (expediente 1, nota de crédito de pagadero 500). Segunda: la otra unidad de la línea 1 y la línea 2; el máximo por líneas es 734, pero el acumulado admite solo `1230 − 500 = 730` (expediente 2, nota de crédito de pagadero 730 y total 734, con redondeo −4).

**Invariantes:**

- **I1** — Cada operación de dinero, stock o pedido es atómica: todo o nada.
- **I2** — Cada `sale_cash` corresponde 1:1 al `initial_charge` en efectivo de su venta (UQ `payment_event_id`).
- **I3** — `initial_charge + cash_rounding = T`; los reembolsos nunca superan el efectivo recibido, y el reembolso de cada expediente es la suma del de sus líneas.
- **I4** — Salidas de stock = unidades entregadas; los reingresos solo nacen de una inspección `fit_for_sale` y nunca superan las unidades devueltas.
- **I5** — Esperado = fondo + Σ movimientos; la diferencia es inmutable tras el cierre.
- **I6** — Ningún evento web se vincula a una sesión sin `cash_session_id` explícito.

**Control de consistencia.** Una comprobación periódica alimenta la conciliación existente (R8-29) con cualquier violación de I2–I6, y A la resuelve con R8-30. No corrige nada automáticamente.

**Procedimientos seguros para las exclusiones de V1:**

| Exclusión | Procedimiento |
| --- | --- |
| Otros medios de pago (tarjeta, Yape, Plin, transferencia, QR) | No se venden por el POS ni se registran como efectivo ficticio. Se usa el canal web o se vende fuera del plugin y A regulariza el stock con R8-35 y motivo |
| Descuentos y cambio manual de precio | No existen. Solo A cambia el precio en el catálogo, y afecta a todos los canales |
| Productos digitales, servicios y citas | Solo por el canal web |
| Sin conexión | Las ventas POS se suspenden. Las ventas hechas fuera del plugin llevan su efectivo aparte de la sesión, el stock se regulariza con R8-35 y el comprobante se emite en el emisor externo |
| Regularización tributaria | Decisión del comerciante y su contador; el plugin nunca sugiere omitir la emisión |

## 8. Comprobante externo (D-206)

**Flujo:**

1. El POS muestra siempre el recordatorio «Emita el comprobante de pago en su sistema de emisión».
2. Al confirmar la venta, el pedido entra en la cola de pendientes (R8-80).
3. Si `T > 70000` céntimos (S/700.00), la pantalla avisa antes de cobrar que la boleta exige identificar al adquirente. Esa identificación se ingresa **solo en el emisor externo**.
4. Quien tenga `external_documents_record` registra en R8-81 el tipo, serie, número, fecha de emisión e importes del comprobante emitido.

**Identificación.** Localmente solo se guarda `buyer_id_type` (`dni`, `ce`, `ruc`, `pasaporte`, `otro`, `none`), nunca el número. Guardar el número queda EXCLUIDO en V1, porque añadiría datos personales y obligaría a revisar la política de privacidad.

**Reglas de R8-81:**

- Tipos `boleta`, `factura`, `ticket` (el comprobante de máquina registradora emitido fuera) y `nota_credito`.
- Un solo comprobante de venta por pedido y una sola nota de crédito por expediente; un segundo registro devuelve 409 `pc_conflict` (`reason = document_already_recorded`).
- Una nota de crédito exige `case_id` de un expediente `pos_return_after_delivery` del mismo pedido.

**Importes del comprobante (D-211).** R8-81 recibe tres cifras separadas, en céntimos, que reflejan el documento tal como lo emitió el emisor externo:

| Campo | Significado | En UBL 2.1 |
| --- | --- | --- |
| `total_cents` | Importe total del documento, sin redondeo | importe total antes de `PayableRoundingAmount` |
| `rounding_cents` | Redondeo del documento, de −9 a 0; 0 por defecto | `PayableRoundingAmount` |
| `payable_cents` | Importe pagadero | `PayableAmount` |

- **Coherencia interna.** Siempre `payable_cents = total_cents + rounding_cents`; si no, 422 `pc_field_invalid` (`field = payable_cents`). Esta es la única comprobación aritmética interna y no depende del pedido.
- **Comprobante de venta.** `total_cents = T` y `rounding_cents` es 0 o `−(T − R)`. Así se aceptan los dos documentos válidos: el de un emisor sin redondeo (pagadero `T`) y el de uno con redondeo (pagadero `R`). Cualquier otra combinación devuelve 422 `pc_business_ineligible` (`reason = document_amount_mismatch`).
- **Nota de crédito.** El pagadero es el reembolso `F` del expediente. El total no es menor que `F`, no lo supera en más de 9 céntimos y no supera `V = Σ precio unitario × unidades devueltas` de ese expediente. Si no, 422 `document_amount_mismatch`. En el ejemplo de §7, la segunda nota de crédito tiene total 734, redondeo −4 y pagadero 730.
- **Expediente sin reembolso.** Un expediente con `F = 0` no exige nota de crédito; registrarla devuelve 422 `pc_business_ineligible` (`reason = credit_note_not_required`).
- **Cola R8-80.** Cada pendiente muestra `total_cents` (`T`, o `V` en una nota de crédito) y `payable_cents` (el efectivo `R`, o el reembolso `F`), para que quien emite vea ambas cifras.

**Cierre de sesión.** No se bloquea por comprobantes pendientes; la respuesta del cierre incluye `pending_documents_count`.

**Contingencia.** Si el emisor externo no está disponible, se siguen los procedimientos oficiales de SUNAT. Este documento no afirma su cumplimiento.

**Almacenamiento.** `pc_external_documents` guarda `amount_cents = total_cents` y las columnas nuevas `rounding_cents` y `buyer_id_type` (§10); el pagadero se deriva como `amount_cents + rounding_cents`. Las filas del canal web conservan `rounding_cents` NULL. La referencia registrada no es un CPE emitido ni validado por PeruCommerce.

## 9. Estados: transición O-10 (D-208)

Sin estados nuevos. Se añade una transición del pedido para el canal `pos`:

| ID | Transición | Actor y condición | Efectos en la misma transacción |
| --- | --- | --- | --- |
| O-10 | sin pedido → CONFIRMADO, con `pos_session_id` = la sesión de la venta | O o A con `pos_sell` y su propia sesión abierta; puerta `pos.write` abierta y POS activado | Ver la secuencia abajo |

**Secuencia de efectos:**

1. Cotización del servidor.
2. Pedido y líneas: F-01 (PENDIENTE), F-03 (LISTO) y F-05 (ENTREGADO, recojo en tienda con `stockOutWithin`).
3. `initial_charge` por `R`, más `cash_rounding` si procede.
4. Movimiento `sale_cash`.
5. O-07 (FINALIZADO).

**Reglas:**

- La transición la ejecuta un coordinador hermano para el actor de personal; no amplía `prepareWithin` del checkout.
- Si cualquier paso falla, no queda nada escrito.
- No se reutiliza ningún estado intermedio visible: la venta nace FINALIZADA o no existe.

## 10. Persistencia y migración (D-209, D-212)

**Tablas nuevas (39 → 42).** Las tres siguen las reglas existentes: InnoDB, `utf8mb4`, céntimos en BIGINT, fechas en DATETIME(6) UTC, FK `RESTRICT` y ninguna FK a `wp_users` ni `wp_posts`.

`pc_cash_sessions`:

- **Columnas:**
  - id BIGINT UNSIGNED PK;
  - register_code VARCHAR(32) NN;
  - status VARCHAR(16) NN (`open`, `closed`, `force_closed`);
  - open_register_code VARCHAR(32) N y open_user_id BIGINT UNSIGNED N: copian la caja y la persona mientras la sesión está abierta y se vacían al cerrar;
  - opened_by BIGINT UNSIGNED NN;
  - opening_float_cents BIGINT NN;
  - business_date DATE NN (America/Lima);
  - opened_at DATETIME(6) NN;
  - closed_at DATETIME(6) N y closed_by BIGINT UNSIGNED N;
  - counted_cents y expected_cents BIGINT N;
  - close_reason VARCHAR(500) N;
  - created_at y updated_at DATETIME(6) NN;
  - row_version BIGINT UNSIGNED NN.
- **La diferencia no se almacena:** es `counted_cents − expected_cents` y se calcula al leer, así que no puede contradecir a sus términos.
- **Índices:** UQ(open_register_code); UQ(open_user_id); IX(business_date, register_code); IX(opened_by, opened_at); IX(status, opened_at, id).
- **Restricciones:**
  - Los UQ con NULL permiten varias sesiones cerradas y una sola abierta por caja y por persona.
  - CHECK: `opening_float_cents ≥ 0`; `counted_cents` NULL o ≥ 0; `status` dentro de su lista; abierta si y solo si `closed_at` es NULL y las dos columnas `open_*` no lo son; `row_version ≥ 1`.
  - Los campos de cierre son inmutables cuando `status ≠ open` (regla de servicio con control de versión).

`pc_cash_session_movements`, inmutable:

- **Columnas:**
  - id BIGINT UNSIGNED PK;
  - session_id BIGINT UNSIGNED NN;
  - kind VARCHAR(16) NN (`sale_cash`, `web_cash_in`, `pay_in`, `pay_out`, `refund_cash`);
  - amount_cents BIGINT NN, distinto de 0 y con el signo de su tipo;
  - order_id, case_id y payment_event_id BIGINT UNSIGNED N;
  - reason VARCHAR(500) N;
  - tendered_cents y change_cents BIGINT N, solo en `sale_cash`;
  - idempotency_id BIGINT UNSIGNED NN, la operación que lo creó;
  - actor_user_id BIGINT UNSIGNED NN;
  - occurred_at DATETIME(6) NN.
- **Índices:** IX(session_id, id); UQ(payment_event_id); IX(order_id, id); IX(case_id); IX(idempotency_id).
- **FK:** sesión; pedido; expediente; (payment_event_id, order_id) → `pc_payment_events`(id, order_id), que impide vincular el evento de otro pedido; raíz de idempotencia.
- **Restricciones:**
  - Nunca se actualiza ni se borra.
  - CHECK: `kind` dentro de su lista y signo según el tipo; `pay_in` y `pay_out` exigen motivo y no llevan evento; `sale_cash`, `web_cash_in` y `refund_cash` exigen evento y pedido; entregado y vuelto solo en `sale_cash`, con `tendered − change = amount`.

`pc_pos_return_lines`, inmutable (D-212):

- **Columnas:**
  - id BIGINT UNSIGNED PK;
  - case_id, order_id, order_line_id y sellable_id BIGINT UNSIGNED NN;
  - returned_units y restocked_units INT UNSIGNED NN;
  - inspection VARCHAR(24) NN (`fit_for_sale`, `not_fit`, `warranty_external`);
  - refund_cents BIGINT NN;
  - created_at DATETIME(6) NN.
- **Índices:** UQ(case_id, order_line_id); IX(order_line_id, id); IX(order_id, id).
- **FK compuestas:** (case_id, order_id) → `pc_cases`(id, order_id); (order_id, order_line_id) → `pc_order_lines`(order_id, id); (order_line_id, sellable_id) → `pc_order_lines`(id, sellable_id). Impiden mezclar líneas, expedientes y vendibles de pedidos distintos.
- **CHECK:** `returned_units ≥ 1`; `restocked_units ≤ returned_units`; `refund_cents ≥ 0`; `inspection` dentro de su lista; `restocked_units = 0` salvo con `fit_for_sale`.

El vínculo entre evento y sesión vive en `pc_cash_session_movements`. Así `pc_payment_events` sigue inmutable y no se recalcula ninguna huella de deduplicación existente.

**Columnas y valores aditivos:**

- `pc_orders`:
  - pos_session_id BIGINT UNSIGNED N, con FK a `pc_cash_sessions` e índice IX(pos_session_id, created_at, id);
  - `policy_version_id` y `privacy_version_id` pasan a NULL a nivel de columna;
  - CHECK: o el pedido es web (`pos_session_id` NULL) con sus dos aceptaciones, o es POS y su medio de pago es `cash`. Así la excepción D-202 la impone también el motor, no solo el servicio.
- `pc_external_documents`:
  - rounding_cents BIGINT N, con CHECK de NULL o de −9 a 0;
  - buyer_id_type VARCHAR(16) N.
- `pc_payment_events`: nuevo valor de `kind`, `cash_rounding`.
- `pc_cases`: nuevo `case_type`, `pos_return_after_delivery`. No gana columnas: las unidades y el reembolso por línea viven en `pc_pos_return_lines`, y la UQ existente (case_type, source_operation_id) garantiza un expediente por operación.
- `pc_stock_movements`: nuevo `source_type`, `case`.

**Canal derivado.** No hay columna `channel`: un pedido es `pos` si y solo si `pos_session_id` no es NULL. No hay relleno ni una segunda fuente de verdad.

**Relaciones.** Las 56 FK existentes no cambian. La 1.1.0 añade 9: una en `pc_orders`, cinco en los movimientos y tres en las líneas de devolución (65 en total).

**Migración 1.0.2 → 1.1.0.** P3 escribe su código; no se ejecuta sin autorización expresa.

1. **Expandir:** crear las tres tablas; añadir en `pc_orders` y `pc_external_documents` las columnas nulas, el índice, las FK y los CHECK; relajar a NULL las dos columnas de versión. No hay relleno: los pedidos existentes tienen `pos_session_id` NULL y sus dos aceptaciones, así que cumplen el CHECK nuevo.
2. **Verificar:** inspección física del esquema 1.1.0 completo (42 tablas, 65 FK, índices y CHECK) con `schema-inspector.php`.
3. **Registrar:** `schema_version = 1.1.0` en `pc_migrations`, bajo el bloqueo de mantenimiento y solo tras la verificación. Cada etapa queda en el diario de la migración con su huella.

La puerta `schema` mantiene cerrado el POS hasta completar el paso 3. La migración es solo hacia adelante e idempotente: una etapa ya registrada no se repite, y una migración parcial no se marca como exitosa. Las sentencias de 1.0.x se conservan byte a byte, para que las huellas de las instalaciones existentes sigan verificándose. La desinstalación conservadora conserva las tablas nuevas igual que las demás.

## 11. REST R8-71…R8-83 y enmiendas (D-210)

Todas exigen sesión WordPress, `X-WP-Nonce` (solo CSRF), deny-by-default y `Cache-Control: private, no-store`. Las mutaciones exigen `idempotency_key` de 16 a 128 caracteres y una versión esperada cuando modifican un recurso existente.

El OpenAPI `0.13.0-draft` suma 13 operaciones en 12 rutas nuevas y 24 esquemas nuevos: en total, **82 operaciones REST, 78 rutas y 162 esquemas**. Cada operación nueva lleva `x-ratification: OWNER_APPROVED_2026-11-27`, su puerta `x-module-gate` (§4) y un `x-contract-status` que dice hasta dónde llegó: `APPROVED_NOT_IMPLEMENTED` mientras no hay código, e `IMPLEMENTED_STATIC_REVIEW_NOT_EXECUTED` cuando P3 lo escribe y lo revisa sin ejecutarlo.

| R8 | Método y ruta | Request → response | Capacidades (todas) | PER |
| --- | --- | --- | --- | --- |
| R8-71 | GET `/admin/pos/catalog` | `q`, `page`, `per_page` → `PosCatalogPage` | `pos_sell` | PER-57 |
| R8-72 | POST `/admin/pos/sales` | `PosSaleCommit` → 201 `PosSaleResult` | `pos_sell` + sesión propia abierta | PER-57 |
| R8-73 | POST `/admin/pos/sessions` | `CashSessionOpen` → 201 `CashSession` | `cash_session_operate` | PER-58 |
| R8-74 | GET `/admin/pos/sessions/current` | → `CashSession` (sin esperado) | `cash_session_operate` | PER-58 |
| R8-75 | POST `/admin/pos/sessions/{id}/movements` | `CashMovementCreate` → `MutationResult` | `cash_movements_record` (A) | PER-60 |
| R8-76 | POST `/admin/pos/sessions/{id}/close` | `CashSessionClose` → `CashSessionCloseResult` | `cash_session_operate` + sesión propia | PER-58 |
| R8-77 | GET `/admin/pos/sessions` | filtros → `CashSessionPage` | `cash_session_review` (A) | PER-59 |
| R8-78 | GET `/admin/pos/sessions/{id}` | → `CashSessionDetail` | `cash_session_review` (A) | PER-59 |
| R8-79 | POST `/admin/pos/sessions/{id}/force-close` | `CashSessionForceClose` → `CashSessionCloseResult` | `cash_session_review` (A) | PER-59 |
| R8-80 | GET `/admin/pos/documents/pending` | → `PosDocumentPendingPage` | `external_documents_record` | PER-61 |
| R8-81 | POST `/admin/pos/orders/{id}/external-documents` | `PosExternalDocumentRecord` (total, redondeo y pagadero, §8) → 201 `C2312Response` | `external_documents_record` (+ `refunds_record_external` para nota de crédito) | PER-61 |
| R8-82 | GET `/admin/pos/orders/{id}/receipt` | → `PosOperationReceipt` | `pos_sell` + `orders_read` | PER-57 |
| R8-83 | POST `/admin/pos/orders/{id}/returns` | `PosReturnRecord` (reembolso por línea, §7) → 201 `PosReturnResult` (un `case_id`) | `orders_read` + `refunds_record_external` + `stock_manage` (A) | PER-62 |

**Respuestas por actor:**

- `CashSessionCloseResult` incluye esperado y diferencia solo si quien llama tiene `cash_session_review`. A O le devuelve solo el conteo declarado, el estado y `pending_documents_count`.
- R8-82 solo devuelve constancias de pedidos `pos`. O solo ve las de su propia sesión, y A las de todas.

**Enmiendas aditivas** (no cambian respuestas existentes del canal web):

- **R8-24** `CashReceipt`: + `rounding_cents` (D-207) y + `cash_session_id` opcional (D-203).
- **R8-27** `ExternalRefundRecord`: + `cash_session_id` opcional, solo si el proveedor del cobro original es `cash`.
- **R8-25 y R8-27:** rechazan pedidos POS (`pos_session_id` no nulo) con 422 `pc_business_ineligible` (`reason = web_channel_required`); la devolución presencial usa R8-83.
- **R8-20:** + filtro `channel` (`web` o `pos`), que se traduce a `pos_session_id IS NULL` o `IS NOT NULL`.
- **R8-21** `AdminOrder`: + `channel`, derivado de `pos_session_id` y nunca almacenado.
- **R8-45 y R8-46:** `AdminSettings` y `SettingsPatch` ganan `pos` (`PosSettings`: `enabled`, falso por defecto, y `registers`, hasta 20 con `code` y `label`), solo para A con `settings_manage`. R8-46 rechaza con 409 `pc_conflict` (`reason = pos_sessions_open`) desactivar el POS o retirar una caja con sesión abierta (D-216).
- **`ErrorResponse.data.details`:** + `reason` opcional (`^[a-z_]+$`, hasta 60 caracteres) para distinguir causas sin nuevos códigos de primer nivel.

**Errores.** Solo códigos públicos que ya existen en `includes/rest-errors.php`; las causas internas se traducen a ellos y `reason` distingue el caso:

- **409 `pc_conflict`:**
  - sesiones: `pos_session_not_open`, `pos_session_closed`, `pos_session_already_open`, `pos_register_busy` y, en R8-46, `pos_sessions_open`;
  - comprobantes: `document_already_recorded`;
  - versión esperada distinta, clave de idempotencia reutilizada con otro cuerpo, efectivo que no cuadra en R8-24, bloqueo vencido o interbloqueo (las causas internas `pc_version_conflict`, `pc_idempotency_conflict`, `pc_cash_amount_mismatch`, `pc_lock_timeout` y `pc_deadlock` salen como `pc_conflict`, igual que hoy).
- **409 `pc_operation_uncertain`:** el resultado del commit es incierto; consultar R8-82, R8-77 o R8-78 antes de reintentar con la misma clave.
- **409 `pc_quote_changed`:** los totales que mostró la pantalla no coinciden con los del servidor.
- **422 `pc_field_invalid`:** campo mal formado o incoherente, con `field` (por ejemplo `payable_cents`).
- **422 `pc_business_ineligible`**, con `reason`:
  - `pos_channel_required` (R8-80…R8-83 sobre un pedido web);
  - `web_channel_required` (R8-25 y R8-27 sobre un pedido POS);
  - `refund_exceeds_cash`, `refund_cash_not_applicable` y `return_units_exceeded` (R8-83);
  - `cash_total_below_minimum` (R8-72, D-213);
  - `document_amount_mismatch` y `credit_note_not_required` (R8-81, D-211).
- **423 `pc_module_not_ready`:** puerta de seguridad cerrada; con `reason = pos_disabled`, POS desactivado o caja no configurada en R8-71/72/73/75 (D-216).
- **503 `pc_dependency_unavailable`:** dependencia interna no disponible; reintentable con la misma clave.

## 12. Idempotencia y concurrencia

**Idempotencia:**

- Cada mutación usa la raíz de idempotencia existente (`pc_idempotency`) con ámbito por actor e instalación.
- La misma clave con otro cuerpo devuelve 409 `pc_conflict` (causa interna `pc_idempotency_conflict`); la misma clave con el mismo cuerpo devuelve el resultado guardado.
- **Reintento tras el cierre (D-214).** La reclamación y la reproducción de la clave preceden a las comprobaciones de sesión, estado y `pos.enabled`. Un reintento de R8-72 cuya venta ya se confirmó devuelve el mismo pedido aunque la sesión se haya cerrado o el POS se haya desactivado después; la respuesta se reconstruye desde el pedido y su movimiento `sale_cash`. Las puertas de seguridad (§4) sí se comprueban antes. Si el cliente perdió la clave, recupera la operación con R8-82, R8-77 o R8-78.
- **Misma clave en paralelo.** La segunda solicitud espera el bloqueo de la raíz de idempotencia y, al liberarse, recibe la reproducción del resultado. Si el bloqueo vence, 409 `pc_conflict`; nunca se ejecuta dos veces.
- La clave de venta se genera al pulsar «Cobrar», y editar el carrito genera una nueva.

**Orden global de bloqueos** (extiende el orden existente sin invertirlo):

1. raíz de idempotencia;
2. fila de ajustes `FOR UPDATE` (solo R8-46 y R8-73);
3. sesión de caja `FOR UPDATE`;
4. `pc_orders`;
5. líneas;
6. expedientes;
7. vendibles en orden ascendente de id;
8. compromisos de stock;
9. inserción de eventos, movimientos y auditoría;
10. resultado de idempotencia.

**Reglas:**

- **Activación frente a apertura.** R8-46 bloquea la fila de ajustes y después lee con lectura bloqueante las sesiones abiertas: si hay alguna en una caja que se retira, o en cualquiera si se desactiva el POS, responde 409 `pos_sessions_open`. R8-73 bloquea la misma fila, comprueba `pos.enabled` y la caja, e inserta la sesión; las UQ `open_register_code` y `open_user_id` dan `pos_register_busy` o `pos_session_already_open`. Así una apertura nunca se cuela durante una desactivación.
- La venta y el cierre de la misma sesión se serializan por el bloqueo de la sesión; una venta posterior al cierre recibe 409 `pc_conflict` (`reason = pos_session_closed`).
- La última unidad la decide el bloqueo de los vendibles: dos cajas no venden la misma unidad.
- Ninguna llamada HTTP ni de impresión ocurre dentro de una transacción.
- **Nonce en turnos largos.** Si el nonce caduca (403 `rest_cookie_invalid_nonce`), la pantalla lo renueva con la acción `rest-nonce` del núcleo de WordPress y reintenta **una vez** con la misma clave, sin perder el carrito.

## 13. UX y accesibilidad

- **Tecnología.** Angular y Angular Material, en el workspace existente del plugin y en las versiones de [ESTANDAR-TECNICO-131.md](../../../docs/ESTANDAR-TECNICO-131.md). No hay workspace nuevo.
- **Teclado primero.** El foco vuelve al buscador tras cada escaneo; el lector de códigos actúa como teclado; hay atajos visibles para cobrar y cancelar el carrito.
- **Panel de totales:** total original, ajuste por redondeo, efectivo a cobrar, entregado y vuelto.
- **Botón «Cobrar».** Mide al menos 44 × 44 CSS px (D-17) y se desactiva mientras hay una solicitud en curso.
- **Accesibilidad.** Los resultados se anuncian con `aria-live`; criterio WCAG 2.2 AA (D-16); formato es-PE de moneda y fecha.
- **Avisos persistentes:**
  - sin sesión abierta;
  - comprobantes pendientes;
  - más de S/700, antes de cobrar;
  - sesión abierta más de 24 horas.
- **Constancia de 80 mm** con CSS de impresión del navegador:
  - leyenda «Este documento no es un comprobante de pago»;
  - sin desglose de IGV;
  - nunca las palabras boleta, factura ni ticket como título;
  - recordatorio de emitir el comprobante.
- **Fallo de impresión.** No repite la venta; se reimprime con R8-82.
- **Sin conexión.** El POS se bloquea con un mensaje claro; no hay modo fuera de línea.

## 14. Criterios de aceptación A-203…A-241 (no ejecutados)

| ID | Criterio |
| --- | --- |
| A-203 | Con una puerta de seguridad cerrada o el esquema anterior a 1.1.0, R8-71…R8-83 responden 423 `pc_module_not_ready` sin efectos |
| A-204 | O sin `pos_sell` recibe 403 en R8-71/72/82; A y O con la capacidad acceden |
| A-205 | O no ve esperado ni diferencia en R8-74/76 ni accede a R8-75/77/78/79/83 |
| A-206 | Una venta en efectivo crea pedido `pos` FINALIZADO, líneas ENTREGADO, salida de stock, `initial_charge` y `sale_cash` en una transacción |
| A-207 | Un fallo inyectado en cualquier paso de R8-72 no deja pedido, stock, evento ni movimiento |
| A-208 | El reintento de R8-72 con la misma clave devuelve el mismo pedido; con otro cuerpo, 409 |
| A-209 | Dos cajas que compiten por la última unidad: una vende y la otra recibe 409 sin sobreventa |
| A-210 | Totales del cliente distintos de los del servidor devuelven 409 `pc_quote_changed` sin escribir |
| A-211 | Redondeo POS: T=1234 da R=1230 y ajuste 4; T=1230 da ajuste 0; nunca hay ajuste hacia arriba |
| A-212 | `initial_charge + cash_rounding = T` y `tendered − change = R` en toda venta |
| A-213 | R8-24 web acepta importe exacto o `floor10` con su ajuste; cualquier otra combinación da 409 `pc_conflict` |
| A-214 | R8-24 sin `cash_session_id` no crea movimiento de cajón aunque el usuario tenga sesión abierta |
| A-215 | R8-24 o R8-27 con la sesión de otra persona (O) o una sesión cerrada se rechazan |
| A-216 | Los cobros de Mercado Pago y `cash_rounding` nunca generan movimientos de sesión |
| A-217 | No pueden abrirse dos sesiones para la misma caja ni dos para la misma persona |
| A-218 | Esperado = fondo + Σ movimientos; tras el cierre, la diferencia no cambia |
| A-219 | Una venta posterior al cierre de su sesión recibe 409 `pc_conflict` (`reason = pos_session_closed`) |
| A-220 | El cierre forzado de A registra motivo y actor; sin conteo deja la diferencia pendiente |
| A-221 | `pay_in` y `pay_out` exigen motivo y solo los registra A |
| A-222 | R8-83 total en sesión abierta: stock repuesto de lo apto, `refund_cash` −F y neto 0 en la sesión cuando F = R |
| A-223 | R8-83 no repone unidades `not_fit` ni `warranty_external` y no supera las unidades entregadas menos las ya devueltas |
| A-224 | R8-83 no reembolsa más que el efectivo recibido menos reembolsos previos ni el ajuste `cash_rounding`; el reembolso del cuerpo es la suma de las líneas |
| A-225 | Tras R8-83 la línea sigue ENTREGADO, el pedido sigue FINALIZADO y, si F > 0, el expediente aparece en la cola de notas de crédito |
| A-226 | R8-25 y R8-27 rechazan pedidos POS (`pos_session_id` no nulo) con 422 (`reason = web_channel_required`) |
| A-227 | Un pedido POS aparece en R8-80 hasta registrar su boleta; total ≠ T, redondeo fuera de {0, −(T−R)} o pagadero ≠ total + redondeo se rechazan |
| A-228 | O no registra nota de crédito; A sí, solo con un expediente de devolución del mismo pedido con F > 0 y pagadero = F |
| A-229 | Ninguna respuesta, auditoría ni registro guarda el número de documento del adquirente |
| A-230 | Los pedidos `web` conservan aceptación de política y privacidad, contacto D-24 y seguimiento D-34 sin cambios |
| A-231 | La constancia muestra la leyenda, no usa boleta, factura ni ticket como título y no desglosa IGV |
| A-232 | El POS se opera completo con teclado, anuncia resultados y cumple 44 × 44 y WCAG 2.2 AA en las pantallas POS |
| A-233 | Una venta POS con T < 10 se rechaza con 422 (`reason = cash_total_below_minimum`) sin escribir nada |
| A-234 | Un R8-83 con reembolso 0 crea expediente y reingreso, sin evento `external_refund`, sin `refund_cash` y sin nota de crédito pendiente |
| A-235 | El reintento de R8-72 con la misma clave tras cerrar la sesión o desactivar el POS devuelve el mismo pedido sin crear otro |
| A-236 | Con `pos.enabled` falso, R8-71/72/73/75 responden 423 (`reason = pos_disabled`); R8-74, R8-76…R8-83 siguen disponibles para cerrar y regularizar |
| A-237 | R8-46 no desactiva el POS ni retira una caja con sesión abierta: 409 (`reason = pos_sessions_open`) |
| A-238 | Dos devoluciones sucesivas del ejemplo de §7 (T = 1234, R = 1230) admiten 500 y luego 730; un tercer reembolso mayor que 0 se rechaza |
| A-239 | R8-46 y R8-73 concurrentes se serializan por la fila de ajustes: nunca queda una sesión abierta con el POS desactivado |
| A-240 | Toda respuesta 409/422/423 de una causa POS incluye `data.details.reason` del catálogo de §11 |
| A-241 | La migración 1.1.0 deja 42 tablas y 65 FK verificadas antes de registrar `schema_version = 1.1.0`; los pedidos web previos cumplen el CHECK nuevo sin relleno |

## 15. Consumidores de P3 (código que cambiará)

| Archivo | Cambio previsto |
| --- | --- |
| `wp-peru-commerce.php` | `PERUCOMMERCE_SCHEMA_VERSION` de 1.0.2 a 1.1.0 |
| `includes/rest-contract.php` | `VERSION` 0.13.0-draft y las 13 operaciones nuevas en su espejo `OPS` |
| `includes/access-contract.php` | `VERSION` 0.13.0-draft y el `x-authz` de R8-71…R8-83 |
| `includes/access-output-contract.php` | Raíces y DTO alcanzables de las respuestas POS |
| `includes/access-roles.php` | Cinco capacidades nuevas y su asignación A/O |
| `includes/module-gates.php` | `pos.read`, `pos.write` y `pos.sessions`, cerrados por defecto |
| `includes/rest-errors.php` | `data.details.reason` en `make()` y `Rest_Failure`; causas internas `pc_pos_*` hacia los códigos públicos de §11 |
| `includes/settings-validation.php`, `settings-store.php` y `settings-service.php` | Bloque `pos` (`enabled`, cajas), fila de ajustes `FOR UPDATE` y 409 `pos_sessions_open` en R8-46 (D-216) |
| `includes/transaction-context.php` | Bloqueo de la sesión de caja entre la idempotencia y el pedido |
| `includes/inventory-reservations.php` | Reingreso de unidades aptas con `source_type = case` |
| `includes/financial-events.php` y `orders-persistence.php` | Verificación del evento `cash_rounding` y del reembolso POS |
| `includes/external-documents.php` | Boleta POS con total, redondeo y pagadero (D-211) y nota de crédito ligada al expediente (D-212) |
| `includes/orders-read.php` | Canal derivado de `pos_session_id` y filtro de R8-20 |
| Lectores del dinero cobrado (`orders-finalization`, `orders-cancellation`, `orders-delivery-changes`, `orders-adjustments`, `guest-order`, `notification-*`, `queue-outbox`) | Reconocen `cash_rounding` al sumar lo cobrado |
| `includes/bootstrap.php` | Registro de servicios y controladores POS |
| `includes/schema.php`, `schema-inspector.php` e `installer.php` | Tablas, columnas y migración 1.1.0 |
| `includes/cash-payments.php` | `rounding_cents` y `cash_session_id` en R8-24; `cashBalanceWithin` y `owedWithin` reconocen `cash_rounding`; rechazo de pedidos POS en R8-25 |
| `includes/external-refunds.php` | Rechazo de pedidos POS y `cash_session_id` opcional en R8-27 |
| Nuevo coordinador de venta POS | O-10, sesión y constancia |
| Nuevo coordinador de devolución POS | R8-83 atómico |
| Controladores REST nuevos | R8-71…R8-83 con `permission_callback` y validación de esquema |
| Frontend Angular (P4) | Pantallas POS en el workspace existente; `dto.ts` y los contratos `admin-*` |
| `tests/risk/contract.test.mjs`, `coverage.test.mjs` y `scenarios.json` | Versión y recuentos 0.13.0-draft (82/78) y escenarios POS |

Otros archivos PHP y TypeScript citan 0.12.0-draft solo en comentarios; se actualizan junto con su módulo.

**Alineación en P3.** El OpenAPI principal es 0.13.0-draft. P3 alinea el código PHP y los contratos de las pruebas offline IMP-69 con esa versión (82 REST, 78 rutas, 162 DTO) en sus cortes; hasta el último de ellos, el código y esas pruebas pueden seguir fijados en 0.12.0-draft. Ninguna prueba se ejecutó y el repositorio no tiene CI que las lance. El estado de cada corte consta en [docs/43](43-ESTADO-IMPLEMENTACION.md).

## 16. Bloqueos y pendientes

1. **Autorización P-B** de implementación de código: concedida el 2026-11-27 para P1b y P3, sin ejecutar migraciones, instalar dependencias, compilar ni probar.
2. **Revisión del propietario** de D-208…D-210: aprobadas el 2026-11-27, incluida R8-83. Los seis ajustes previos a P3 se resolvieron en D-211…D-216.
3. **Entorno local** de WordPress y base de datos para verificar la migración y la concurrencia: necesita autorización expresa.
4. **Antes de P-D** (no bloquea P3): validación del contador sobre el redondeo (D-207) y comprobación de la configuración del emisor externo del comercio.

## 17. Fuentes oficiales

- SUNAT, reglas de validación UBL 2.1 (actualizadas al 26/08/2026): https://cpe.sunat.gob.pe/sites/default/files/2026-08/Reglas%20de%20validaci%C3%B3n%20-%20actualizado%20al%2026.08.2026.xlsx
- SUNAT, guía XML de la boleta 2.1: https://cpe.sunat.gob.pe/sites/default/files/inline-files/guia%2Bxml%2Bboleta%2Bversion%202-1%2B1%2B0_0_0%20%282%29.pdf
- SUNAT, anexo de la R.S. 123-2022: https://www.sunat.gob.pe/legislacion/superin/2022/anexo-123-2022.pdf
- SUNAT, boleta de venta: https://orientacion.sunat.gob.pe/03-boleta-de-venta
- SUNAT, tipos de comprobante, boleta: https://cpe.sunat.gob.pe/tipos_de_comprobantes/boleta
- SUNAT, anexo II de la R.S. 114-2019: https://www.sunat.gob.pe/legislacion/superin/2019/anexoII-114-2019.pdf
- SUNAT, Reglamento de Comprobantes de Pago, capítulo I: https://www.sunat.gob.pe/legislacion/comprob/regla/capituloI.pdf
- Código Tributario, libro IV (art. 174, num. 1): https://www.sunat.gob.pe/legislacion/codigo/libro4/titulo1.htm
- El Peruano: https://busquedas.elperuano.pe/dispositivo/NL/538113-1
- Indecopi, redondeo a favor del consumidor: https://indecopi.gob.pe/inicio/-/asset_publisher/ZxXrtRdgbv1r/content/el-indecopi-recuerda-que-el-redondeo-de-precios-es-a-favor-del-consumidor-para-pagos-en-efectivo
- gob.pe, redondeo de precios: https://cdn.www.gob.pe/uploads/document/file/1694248/4_Redondeo%20de%20precios.pdf.pdf
- BCRP, nota informativa del 31/10/2018: https://www.bcrp.gob.pe/docs/Transparencia/Notas-Informativas/2018/nota-informativa-2018-10-31.pdf
- BCRP, nota informativa del 28/04/2011: https://www.bcrp.gob.pe/docs/Transparencia/Notas-Informativas/2011/Nota-Informativa-BCRP-2011-04-28.pdf
