# 48 — Contrato POS V1 (P2): ventas presenciales en efectivo

**Corte: 2026-11-27, rama `main`, partida 53c89db.**

**Estado:**

- P1 está **RATIFICADO por el propietario** (2026-11-27), con las precisiones D-202…D-207.
- P1b está **RESUELTO CONTRACTUALMENTE** (D-207).
- P2 está **DOCUMENTADO/NO IMPLEMENTADO**: OpenAPI `0.13.0-draft`, sin PHP, Angular, migraciones ejecutables, compilación ni pruebas.

Este documento convierte la propuesta de [docs/47 §2](47-POS-INTEGRADO-Y-APERTURA-PUBLICA.md) en contrato. Añade las decisiones D-201…D-210 a D-01…D-200 ([docs/01](01-DECISIONES-Y-ESTADO.md)). Para el POS prevalece sobre la propuesta de docs/47 y, en rutas y DTO, manda el OpenAPI `0.13.0-draft`. Nada de lo aquí descrito existe en código.

## 0. Precedencia, independencia y etiquetas

**Independencia.** PeruCommerce no depende de los productos comerciales privados de VSG: no usa su POS de escritorio, sus bibliotecas, servicios, conectores MCP ni datos. Este contrato parte del código del propio plugin y de fuentes oficiales públicas. Ninguna fase puede introducir esa dependencia.

**Lo que existe (CONFIRMADO en código, no PROBADO EN ENTORNO):**

- R8-24 cobra en efectivo un pedido de contraentrega por el importe exacto (`includes/cash-payments.php`).
- R8-25 corrige ese cobro con un delta firmado; R8-27 registra reembolsos externos (`includes/external-refunds.php`).
- Hay 19 capacidades primitivas (`includes/access-roles.php`).
- La entrega física descuenta stock con `stockOutWithin` (`includes/physical-fulfillment.php`) y `includes/orders-finalization.php` deriva FINALIZADO.
- Las puertas de módulo fallan cerradas (`includes/module-gates.php`).

**Lo que este documento propone:** todo lo demás, con la etiqueta DOCUMENTADO/NO IMPLEMENTADO salvo que se indique otra cosa.

## 1. Decisiones D-201…D-210

| ID | Decisión | Estado |
| --- | --- | --- |
| D-201 | Ratificación del alcance POS V1 (M1–M6) y de D1–D14 de la propuesta P1, con las precisiones D-202…D-207 | CONFIRMADO por el propietario (2026-11-27) |
| D-202 | D2: sin aceptaciones ficticias; el canal web conserva íntegros sus contratos (§3) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-203 | D4: solo se vinculan a una sesión los movimientos de efectivo que entran o salen realmente de ese cajón; nunca de forma automática (§5) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-204 | D5: cinco capacidades específicas nuevas (19 → 24), sin ampliar las existentes (§4) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-205 | D6: errores, anulaciones, reembolsos y devoluciones, con invariantes y procedimientos seguros para las exclusiones (§7) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-206 | D13: flujo operativo de emisión externa del comprobante, identificación y registro de su referencia (§8) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO |
| D-207 | D14/P1b: redondeo de efectivo a favor del consumidor, separando total original, ajuste y efectivo recibido (§6) | CONFIRMADO en política; contrato DOCUMENTADO/NO IMPLEMENTADO; validación contable antes de P-D |
| D-208 | Transición O-10 «sin pedido → CONFIRMADO» del canal POS, sin estados nuevos (§9) | DOCUMENTADO/NO IMPLEMENTADO; revisión del propietario antes de P3 |
| D-209 | Persistencia: 2 tablas nuevas (39 → 41), columnas aditivas y migración 1.0.2 → 1.1.0 solo documental (§10) | DOCUMENTADO/NO IMPLEMENTADO; revisión del propietario antes de P3 |
| D-210 | REST R8-71…R8-83, enmiendas aditivas de R8-20/21/24/25/27/45/46 y OpenAPI `0.13.0-draft` (§11) | DOCUMENTADO/NO IMPLEMENTADO; revisión del propietario antes de P3 |

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

**Cambio respecto de la propuesta P1:** la devolución física con reingreso pasa de EXCLUIDO a la operación atómica R8-83, solo para A. Es la única forma de cumplir la precisión D6 («no permitir inconsistencias entre inventario, dinero y pedidos»): con las operaciones existentes, stock (R8-35) y dinero (R8-27) se registrarían en transacciones separadas. Requiere la revisión del propietario antes de P3.

## 2. Requisitos peruanos (fuentes oficiales; no es certificación)

- **Oportunidad de emisión.** El comprobante se emite en la entrega del bien o en el pago, lo que ocurra primero; en mostrador, en el acto. PeruCommerce **no emite CPE**: el POS recuerda la obligación y registra la referencia del comprobante emitido fuera.
- **Más de S/700.** La boleta exige identificar al adquirente.
- **Infracción.** No emitir u otorgar el comprobante es infracción (Código Tributario, art. 174, num. 1). La responsabilidad es del comerciante; ninguna constancia del POS la sustituye.
- **«Ticket».** Es un tipo de comprobante de pago; el documento interno no puede llamarse así ni imitar su formato.
- **Redondeo.** En pagos en efectivo es a favor del consumidor, a múltiplos de S/0.10, porque las monedas de 1 y 5 céntimos salieron de circulación. No aplica a tarjetas ni transferencias.

Fuentes al final (§17). Ninguna afirmación de este documento acredita cumplimiento tributario ni de protección al consumidor.

## 3. Canal y obligaciones del canal web (D-202)

- `pc_orders.channel` toma `web` o `pos`. Todos los pedidos existentes quedan en `web` por relleno de migración.
- **Canal web sin cambios.** Checkout, aceptación de política y privacidad, contacto D-24, seguimiento D-34, notificaciones, Libro de Reclamaciones virtual y R8-64 siguen exactamente igual. Ninguna regla de este documento relaja un contrato web.
- **Sin aceptaciones ficticias.** En `pos`, `policy_version_id` y `privacy_version_id` son NULL, porque nadie aceptó nada en un formulario. La columna admite NULL, pero el servicio exige NOT NULL cuando `channel = web`; una fila web con NULL es un error de invariante.
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

**Puertas de módulo** (`module-gates.php`, P3), todas cerradas por defecto: `pos.read` (R8-71, R8-74, R8-77, R8-78, R8-80, R8-82), `pos.write` (R8-72, R8-81, R8-83) y `pos.sessions` (R8-73, R8-75, R8-76, R8-79). Se abren solo si:

- `pos.enabled = true` en ajustes;
- el esquema es 1.1.0 verificado;
- hay al menos una caja configurada.

Si no, responden 503 `pc_module_not_ready`.

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
- **Cierre.** O declara el efectivo contado sin ver el esperado (arqueo ciego). El servidor fija `expected_cents`, `counted_cents` y `difference_cents = counted − expected`, que son **inmutables** tras el cierre. Una diferencia posterior se explica con un expediente o un movimiento de otra sesión, nunca reabriendo la cerrada.
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

**R8-24, canal web (P1b).** `CashReceipt` gana `rounding_cents`, opcional y 0 por defecto. Se aceptan exactamente dos combinaciones; cualquier otra devuelve `pc_cash_amount_mismatch`:

- `amount_cents` igual al saldo debido y `rounding_cents` 0;
- `amount_cents = floor10(saldo)` y `rounding_cents = saldo mod 10`, mayor que 0.

**R8-72, canal POS.** Siempre redondea. El servidor calcula `T`, el ajuste y `R`; el cliente envía los importes que mostró, y si no coinciden responde 409 `pc_quote_changed` sin escribir nada.

**Entregado y vuelto (D7).** El importe entregado y el vuelto se guardan en el movimiento `sale_cash` (`tendered_cents` y `change_cents`), con `tendered − change = R`. No son ingresos ni asientos del libro.

**Reportes.** Muestran por separado el total original, el ajuste por redondeo y el efectivo recibido.

**Comprobante externo.** La boleta emitida fuera puede llevar `PayableRoundingAmount = −(T − R)` si el emisor lo admite. PeruCommerce no lo genera ni lo valida.

## 7. Errores, anulaciones, reembolsos y devoluciones (D-205)

| Situación | Procedimiento | Efecto en pedido, stock y dinero |
| --- | --- | --- |
| Error antes del commit (validación, stock, versión) | Se revierte la transacción; el carrito sigue en la memoria de la pestaña | Ninguno |
| Resultado incierto (red, tiempo de espera) | Reintentar con **la misma** `idempotency_key`: devuelve el resultado guardado o 423 si sigue en curso. Nunca una clave nueva para la misma intención | Ninguno adicional |
| Venta errónea ya confirmada (anulación) | O no anula: avisa a A. A usa R8-83 como devolución total con reingreso y reembolso en la misma sesión abierta | `sale_cash` +R y `refund_cash` −R suman 0; stock repuesto; pedido FINALIZADO con expediente |
| Anulación con la sesión original ya cerrada | A usa R8-83 indicando la sesión abierta de la que sale el efectivo | La diferencia de la sesión cerrada sigue inmutable; el expediente explica el movimiento |
| Error al dar vuelto | Aparece como diferencia en el arqueo. Si se debía vuelto al cliente, A registra un `pay_out` con motivo que cita el pedido | Solo el cajón |
| Devolución tras la entrega (D-89) | R8-83 (abajo) | Expediente, reingreso solo de lo apto, reembolso acotado |
| Corrección R8-25 o reembolso R8-27 sobre un pedido `pos` | Rechazado con 422 `pc_business_ineligible`; se usa R8-83 | Ninguno |

**R8-83: devolución o anulación presencial, en una sola transacción.**

1. Un expediente `pc_cases` por cada línea devuelta (`case_type = pos_return_after_delivery`, `case_phase = closed_returned`), con unidades devueltas, unidades repuestas, inspección y evidencia.
2. Cada línea cumple `restock_units ≤ returned_units ≤ unidades entregadas − unidades ya devueltas`.
3. El reingreso se hace en `pc_stock_movements` (`source_type = case`), solo por las unidades con inspección `fit_for_sale`.
4. Las unidades `not_fit` o `warranty_external` no vuelven al stock vendible.
5. Reembolso: evento `external_refund`, proveedor `cash`, sobre el `initial_charge`.
   - `refund_cents ≤ R − reembolsos previos`.
   - `refund_cents ≤ Σ(precio unitario × unidades devueltas)`.
   - El ajuste `cash_rounding` nunca se reembolsa.
6. El movimiento `refund_cash` se registra solo si se indica `cash_session_id`.
7. La línea sigue ENTREGADO (F-10 sigue PROHIBIDO) y el pedido sigue FINALIZADO. La devolución vive en expedientes separados (O-07, D-89).
8. El pedido entra en la cola de notas de crédito pendientes (R8-80).

**Invariantes:**

- **I1** — Cada operación de dinero, stock o pedido es atómica: todo o nada.
- **I2** — Cada `sale_cash` corresponde 1:1 al `initial_charge` en efectivo de su venta (UQ `payment_event_id`).
- **I3** — `initial_charge + cash_rounding = T`; los reembolsos nunca superan el efectivo recibido.
- **I4** — Salidas de stock = unidades entregadas; los reingresos solo nacen de una inspección `fit_for_sale`.
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
4. Quien tenga `external_documents_record` registra en R8-81 el tipo, serie, número, fecha de emisión e importe del comprobante emitido.

**Identificación.** Localmente solo se guarda `buyer_id_type` (`dni`, `ce`, `ruc`, `pasaporte`, `otro`, `none`), nunca el número. Guardar el número queda EXCLUIDO en V1, porque añadiría datos personales y obligaría a revisar la política de privacidad.

**Reglas de R8-81:**

- Tipos `boleta`, `factura`, `ticket` (el comprobante de máquina registradora emitido fuera) y `nota_credito`.
- El importe debe coincidir con `T` en el comprobante de venta y con el reembolso de un expediente en la nota de crédito; si no, 422.
- Un solo comprobante de venta por pedido.
- Una nota de crédito exige `case_id` de un expediente `pos_return_after_delivery` del mismo pedido.

**Cierre de sesión.** No se bloquea por comprobantes pendientes; la respuesta del cierre incluye `pending_documents_count`.

**Contingencia.** Si el emisor externo no está disponible, se siguen los procedimientos oficiales de SUNAT. Este documento no afirma su cumplimiento.

**Almacenamiento.** `pc_external_documents` con `buyer_id_type VARCHAR(16) NULL`. La referencia registrada no es un CPE emitido ni validado por PeruCommerce.

## 9. Estados: transición O-10 (D-208)

Sin estados nuevos. Se añade una transición del pedido para el canal `pos`:

| ID | Transición | Actor y condición | Efectos en la misma transacción |
| --- | --- | --- | --- |
| O-10 | sin pedido → CONFIRMADO, con `channel = pos` | O o A con `pos_sell` y su propia sesión abierta; módulo `pos.write` abierto | Ver la secuencia abajo |

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

## 10. Persistencia y migración (D-209)

**Tablas nuevas (39 → 41).**

`pc_cash_sessions`:

- **Columnas:**
  - id BIGINT UNSIGNED PK;
  - register_code VARCHAR(32) NN;
  - opened_by BIGINT UNSIGNED NN;
  - status VARCHAR(16) NN (`open`, `closed`, `force_closed`);
  - open_register_code VARCHAR(32) N y open_user_id BIGINT UNSIGNED N, que se vacían al cerrar;
  - opening_float_cents BIGINT NN;
  - business_date DATE NN (America/Lima);
  - opened_at DATETIME(6) NN;
  - closed_by BIGINT UNSIGNED N y closed_at DATETIME(6) N;
  - counted_cents, expected_cents y difference_cents BIGINT N;
  - close_reason VARCHAR(500) N;
  - row_version BIGINT UNSIGNED NN;
  - created_at y updated_at DATETIME(6) NN.
- **Índices:** UQ(open_register_code); UQ(open_user_id); IX(business_date, register_code); IX(opened_by, opened_at).
- **Restricciones:**
  - Los UQ con NULL permiten varias sesiones cerradas y una sola abierta por caja y por usuario.
  - `opening_float_cents ≥ 0`.
  - Los campos de cierre son inmutables cuando `status ≠ open`.

`pc_cash_session_movements`, inmutable:

- **Columnas:**
  - id BIGINT UNSIGNED PK;
  - session_id BIGINT UNSIGNED NN;
  - kind VARCHAR(16) NN (`sale_cash`, `web_cash_in`, `pay_in`, `pay_out`, `refund_cash`);
  - amount_cents BIGINT NN, con signo según el tipo y distinto de 0;
  - payment_event_id BIGINT UNSIGNED N;
  - order_id BIGINT UNSIGNED N;
  - case_id BIGINT UNSIGNED N;
  - reason VARCHAR(500) N;
  - tendered_cents BIGINT N y change_cents BIGINT N, solo en `sale_cash`;
  - actor_user_id BIGINT UNSIGNED NN;
  - occurred_at y recorded_at DATETIME(6) NN.
- **Índices:** IX(session_id, id); UQ(payment_event_id); IX(order_id).
- **Restricciones:**
  - Nunca se actualiza ni se borra.
  - `pay_in` y `pay_out` exigen motivo.
  - `sale_cash`, `web_cash_in` y `refund_cash` exigen `payment_event_id`.

El vínculo entre evento y sesión vive en esta tabla. Así `pc_payment_events` sigue inmutable y no se recalcula ninguna huella de deduplicación existente.

**Columnas y valores aditivos:**

- `pc_orders`:
  - channel VARCHAR(8) NN DEFAULT 'web';
  - pos_session_id BIGINT UNSIGNED N;
  - índice IX(channel, created_at, id);
  - `policy_version_id` y `privacy_version_id` pasan a NULL a nivel de columna, con la regla de servicio de D-202.
- `pc_payment_events`: nuevo valor de `kind`, `cash_rounding`.
- `pc_external_documents`: buyer_id_type VARCHAR(16) N.
- `pc_cases`:
  - nuevo `case_type`, `pos_return_after_delivery`;
  - returned_units y restocked_units INT UNSIGNED N, solo para ese tipo, con `restocked_units ≤ returned_units`.
- `pc_stock_movements`: nuevo `source_type`, `case`.

**Migración 1.0.2 → 1.1.0** (solo documental; no se escribe ni se ejecuta en P2):

1. **Expandir:** crear las dos tablas y añadir columnas con valores por defecto, sin bloquear el canal web.
2. **Rellenar:** `channel = 'web'` en los pedidos existentes.
3. **Verificar:** inspección física de columnas, índices y restricciones con `schema-inspector.php`.
4. **Registrar:** `schema_version = 1.1.0` en `pc_migrations`, bajo bloqueo y solo tras la verificación.

El módulo POS queda cerrado hasta completar el paso 4. La migración es solo hacia adelante e idempotente; una migración parcial no se marca como exitosa. La desinstalación conservadora conserva las tablas nuevas igual que las demás.

## 11. REST R8-71…R8-83 y enmiendas (D-210)

Todas exigen sesión WordPress, `X-WP-Nonce` (solo CSRF), deny-by-default y `Cache-Control: private, no-store`. Las mutaciones exigen `idempotency_key` de 16 a 128 caracteres y una versión esperada cuando modifican un recurso existente.

El OpenAPI `0.13.0-draft` suma 13 operaciones en 12 rutas nuevas y 24 esquemas nuevos: en total, **82 operaciones REST, 78 rutas y 162 esquemas**. Cada operación nueva lleva `x-contract-status: PROPOSED_NOT_IMPLEMENTED`, `x-ratification: OWNER_REVIEW_PENDING_BEFORE_P3` y su puerta `x-module-gate` (§4).

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
| R8-81 | POST `/admin/pos/orders/{id}/external-documents` | `PosExternalDocumentRecord` → 201 `C2312Response` | `external_documents_record` (+ `refunds_record_external` para nota de crédito) | PER-61 |
| R8-82 | GET `/admin/pos/orders/{id}/receipt` | → `PosOperationReceipt` | `pos_sell` + `orders_read` | PER-57 |
| R8-83 | POST `/admin/pos/orders/{id}/returns` | `PosReturnRecord` → 201 `PosReturnResult` | `orders_read` + `refunds_record_external` + `stock_manage` (A) | PER-62 |

**Respuestas por actor:**

- `CashSessionCloseResult` incluye esperado y diferencia solo si quien llama tiene `cash_session_review`. A O le devuelve solo el conteo declarado, el estado y `pending_documents_count`.
- R8-82 solo devuelve constancias de pedidos `pos`. O solo ve las de su propia sesión, y A las de todas.

**Enmiendas aditivas** (no cambian respuestas existentes del canal web):

- **R8-24** `CashReceipt`: + `rounding_cents` (D-207) y + `cash_session_id` opcional (D-203).
- **R8-27** `ExternalRefundRecord`: + `cash_session_id` opcional, solo si el proveedor del cobro original es `cash`.
- **R8-25 y R8-27:** rechazan pedidos `channel = pos` con 422 `pc_business_ineligible`.
- **R8-20:** + filtro `channel` (`web` o `pos`).
- **R8-21** `AdminOrder`: + `channel`.
- **R8-45 y R8-46:** `AdminSettings` y `SettingsPatch` ganan `pos` (`PosSettings`: `enabled`, falso por defecto, y `registers`, hasta 20 con `code` y `label`), solo para A con `settings_manage`.
- **`ErrorResponse.data.details`:** + `reason` opcional (`^[a-z_]+$`, hasta 60 caracteres) para distinguir causas sin nuevos códigos de primer nivel.

**Errores** (códigos ya presentes en el código):

- 409:
  - `pc_quote_changed` (totales cambiados);
  - `pc_version_conflict`;
  - `pc_conflict` con `reason`: `pos_session_not_open`, `pos_session_closed`, `pos_session_already_open` o `pos_register_busy`.
- 422:
  - `pc_cash_amount_mismatch`;
  - `pc_business_ineligible`, con `reason`: `pos_channel_required`, `web_channel_required` o `refund_exceeds_cash`.
- 423: idempotencia en curso.
- 503: `pc_module_not_ready`.

## 12. Idempotencia y concurrencia

**Idempotencia:**

- Cada mutación usa la raíz de idempotencia existente (`pc_idempotency`) con ámbito por actor e instalación.
- La misma clave con otro cuerpo devuelve 409 `pc_idempotency_conflict`; la misma clave con el mismo cuerpo devuelve el resultado guardado.
- La clave de venta se genera al pulsar «Cobrar», y editar el carrito genera una nueva.

**Orden global de bloqueos** (extiende el orden existente sin invertirlo):

1. raíz de idempotencia;
2. sesión de caja `FOR UPDATE`;
3. `pc_orders`;
4. líneas;
5. expedientes;
6. vendibles en orden ascendente de id;
7. compromisos de stock;
8. inserción de eventos, movimientos y auditoría;
9. resultado de idempotencia.

**Reglas:**

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

## 14. Criterios de aceptación A-203…A-232 (no ejecutados)

| ID | Criterio |
| --- | --- |
| A-203 | Con el módulo desactivado o el esquema anterior a 1.1.0, R8-71…R8-83 responden 503 sin efectos |
| A-204 | O sin `pos_sell` recibe 403 en R8-71/72/82; A y O con la capacidad acceden |
| A-205 | O no ve esperado ni diferencia en R8-74/76 ni accede a R8-75/77/78/79/83 |
| A-206 | Una venta en efectivo crea pedido `pos` FINALIZADO, líneas ENTREGADO, salida de stock, `initial_charge` y `sale_cash` en una transacción |
| A-207 | Un fallo inyectado en cualquier paso de R8-72 no deja pedido, stock, evento ni movimiento |
| A-208 | El reintento de R8-72 con la misma clave devuelve el mismo pedido; con otro cuerpo, 409 |
| A-209 | Dos cajas que compiten por la última unidad: una vende y la otra recibe 409 sin sobreventa |
| A-210 | Totales del cliente distintos de los del servidor devuelven 409 `pc_quote_changed` sin escribir |
| A-211 | Redondeo POS: T=1234 da R=1230 y ajuste 4; T=1230 da ajuste 0; nunca hay ajuste hacia arriba |
| A-212 | `initial_charge + cash_rounding = T` y `tendered − change = R` en toda venta |
| A-213 | R8-24 web acepta importe exacto o `floor10` con su ajuste; cualquier otra combinación da `pc_cash_amount_mismatch` |
| A-214 | R8-24 sin `cash_session_id` no crea movimiento de cajón aunque el usuario tenga sesión abierta |
| A-215 | R8-24 o R8-27 con la sesión de otra persona (O) o una sesión cerrada se rechazan |
| A-216 | Los cobros de Mercado Pago y `cash_rounding` nunca generan movimientos de sesión |
| A-217 | No pueden abrirse dos sesiones para la misma caja ni dos para la misma persona |
| A-218 | Esperado = fondo + Σ movimientos; tras el cierre, la diferencia no cambia |
| A-219 | Una venta posterior al cierre de su sesión recibe 409 `pc_conflict` (`reason = pos_session_closed`) |
| A-220 | El cierre forzado de A registra motivo y actor; sin conteo deja la diferencia pendiente |
| A-221 | `pay_in` y `pay_out` exigen motivo y solo los registra A |
| A-222 | R8-83 total en sesión abierta: stock repuesto, `refund_cash` −R y neto 0 en la sesión |
| A-223 | R8-83 no repone unidades `not_fit` ni `warranty_external` y no supera las unidades entregadas menos las ya devueltas |
| A-224 | R8-83 no reembolsa más que el efectivo recibido menos reembolsos previos ni el ajuste `cash_rounding` |
| A-225 | Tras R8-83 la línea sigue ENTREGADO, el pedido sigue FINALIZADO y aparece en la cola de notas de crédito |
| A-226 | R8-25 y R8-27 rechazan pedidos `pos` con 422 |
| A-227 | Un pedido `pos` aparece en R8-80 hasta registrar su comprobante; el importe distinto de T se rechaza |
| A-228 | O no registra nota de crédito; A sí, solo con un expediente de devolución del mismo pedido |
| A-229 | Ninguna respuesta, auditoría ni registro guarda el número de documento del adquirente |
| A-230 | Los pedidos `web` conservan aceptación de política y privacidad, contacto D-24 y seguimiento D-34 sin cambios |
| A-231 | La constancia muestra la leyenda, no usa boleta, factura ni ticket como título y no desglosa IGV |
| A-232 | El POS se opera completo con teclado, anuncia resultados y cumple 44 × 44 y WCAG 2.2 AA en las pantallas POS |

## 15. Consumidores de P3 (código que cambiará)

| Archivo | Cambio previsto |
| --- | --- |
| `wp-peru-commerce.php` | `PERUCOMMERCE_SCHEMA_VERSION` de 1.0.2 a 1.1.0 |
| `includes/rest-contract.php` | `VERSION` 0.13.0-draft y las 13 operaciones nuevas en su espejo `OPS` |
| `includes/access-contract.php` | `VERSION` 0.13.0-draft y el `x-authz` de R8-71…R8-83 |
| `includes/access-roles.php` | Cinco capacidades nuevas y su asignación A/O |
| `includes/module-gates.php` | `pos.read`, `pos.write` y `pos.sessions`, cerrados por defecto |
| `includes/schema.php`, `schema-inspector.php` e `installer.php` | Tablas, columnas y migración 1.1.0 |
| `includes/cash-payments.php` | `rounding_cents` y `cash_session_id` en R8-24; `cashBalanceWithin` y `owedWithin` reconocen `cash_rounding`; rechazo `pos` en R8-25 |
| `includes/external-refunds.php` | Rechazo `pos` y `cash_session_id` opcional en R8-27 |
| Nuevo coordinador de venta POS | O-10, sesión y constancia |
| Nuevo coordinador de devolución POS | R8-83 atómico |
| Controladores REST nuevos | R8-71…R8-83 con `permission_callback` y validación de esquema |
| Frontend Angular (P4) | Pantallas POS en el workspace existente; `dto.ts` y los contratos `admin-*` |
| `tests/risk/contract.test.mjs`, `coverage.test.mjs` y `scenarios.json` | Versión y recuentos 0.13.0-draft (82/78) y escenarios POS |

Otros archivos PHP y TypeScript citan 0.12.0-draft solo en comentarios; se actualizan junto con su módulo.

**Desalineación conocida.** Desde este corte el OpenAPI principal es 0.13.0-draft, mientras el código y las pruebas offline IMP-69 siguen fijados en 0.12.0-draft (69 REST, 66 rutas, 138 DTO). Es la consecuencia prevista de definir el contrato antes del código: esas pruebas fallarán por versión y recuentos hasta que P3 actualice sus consumidores. No se ejecutaron y el repositorio no tiene CI que las lance.

## 16. Bloqueos reales para iniciar P3

1. **Autorización P-B** de implementación de código: no concedida.
2. **Revisión del propietario** de D-208…D-210, en especial R8-83, que cambia la exclusión M6 de la propuesta.
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
