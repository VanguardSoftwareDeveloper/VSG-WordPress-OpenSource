# P0-18 — Modelo físico declarativo, integridad, índices y migraciones V1

**FUENTE VIGENTE D-200 (18-11-2026):** OpenAPI **0.12.0-draft (69 REST/66 rutas/138 esquemas)**, 200 decisiones, 56 PER, **39 tablas**. D-199 abre caso físico parcial por teléfono y D-200 completa su cierre R8-70 y finalización atómica por R8-26. El párrafo sobre el corte 0.10.0 a continuación es HISTÓRICO, no contrato actual.

**CORTE HISTÓRICO anterior a D-199:** el OpenAPI principal actual es **0.10.0-draft (67 REST/64 rutas/134 esquemas)**; versiones anteriores en anexos son cortes históricos. DOC-QA27 precisa la lectura de cantidades y DOC-QA29 la preferencia de avisos sobre persistencia existente. El modelo continúa con **39 tablas lógicas**, no SQL ejecutado.

**Fecha de elaboración:** 2026-11-17.  
**Estado:** DISEÑO FÍSICO DOCUMENTAL PROPUESTO, derivado de la **elección P0-18 aprobada D-168**. **No es un esquema SQL ejecutado, aprobado por separado ni validado contra alojamiento real.**  
**Permisos:** documentación estática solamente. No instalar tablas, escribir código del plugin, ejecutar SQL, pruebas, compilación, despliegue ni publicar release.

## 1. Fuentes vinculantes y precedencia

- `docs/01-DECISIONES-Y-ESTADO.md`: D-01…D-200 (incluido D-168 y las decisiones de monetización/licencia).
- `docs/14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md`: E-01, **único vocabulario comercial** de estados pedido/intento MP/hold/líneas.
- `docs/15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md`: E-02, matriz PER-01…PER-56.
- `docs/16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md`: E-03.
- `docs/17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md`: E-04, dinero entero de céntimos y libro financiero inmutable.
- `docs/18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md`: E-05, locks/holds/conciliación.
- `docs/19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md`: E-06, outbox e intención de comunicación.
- `docs/20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md`: E-07, credenciales separadas, privacidad y retención.
- `docs/21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md`: E-08, **69 operaciones REST/66 rutas y una página WordPress R8-18 no REST** (fuente OpenAPI 0.12.0-draft).
- `docs/22-DECISIONES-P0-17-A-26-APROBADAS-V1.md`: D-167…D-175.
- Referencias técnicas oficiales: WordPress https://developer.wordpress.org/plugins/creating-tables-with-plugins/ ; MySQL 8.4 https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html y https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html .

Este documento precisa almacenamiento y condiciones verificables. **Donde introduce un campo, índice o tabla no literalmente aprobados, se trata de una propuesta física para revisión**; no modifica políticas E-01…E-08 ni concede permisos.

## 2. Convenciones comunes de almacenamiento

1. Una instalación WordPress representa **un comercio/local**. Todas las tablas utilizan **`$wpdb->prefix . 'pc_'`**, nunca el prefijo literal `wp_`. Instalación multisitio: alcance por sitio, sin tabla global entre comercios.
2. Motor obligatorio **InnoDB** y conexión SQL con transacciones ACID, colación `utf8mb4` según WordPress y versión de host. Las tablas críticas nunca degradan a MyISAM. Verificar privilegios, tamaño y engine con diagnóstico antes de checkout.
3. Tipos usados: `BIGINT UNSIGNED` para ID internos; `BIGINT` con escala cero para importes monetarios comerciales **en céntimos PEN**; `INT UNSIGNED` para unidades; `DATETIME(6)` UTC para eventos; `DATE` para fecha local de bloqueo; `BINARY(32)` para hashes SHA-256/HMAC. **NN = NOT NULL, N = NULL permitido**. Diferenciar cero válido de valor ausente.
4. Serialización JSON desde PHP como **cadena decimal** para ID y céntimos de 64 bits; Angular no calculará dinero vinculante con números en coma flotante. Base de datos no calcula impuestos universales; cálculos fiscales precisos separados (BCMath cuando corresponda).
5. JSON de snapshot en `LONGTEXT` con esquema/versión validados por PHP y hash cuando sea crítico; no depender de funciones JSON exclusivas de una variante MySQL para soportar MariaDB. Caracteres, límites de tamaño y sanitización se fijan con DTO P0-23.
6. Columnas `row_version` (desde 1) y `updated_at` únicamente en registros mutables. Se excluyen `UPDATE`/`DELETE` destructivos en snapshots, dinero, auditoría, documentos y eventos; correcciones como nuevas filas. No usar borrado en cascada de historia comercial.
7. Integridad entre tablas internas: claves foráneas con comportamiento restrictivo o control transaccional equivalente, definidas en DDL final; hacia tablas WordPress (`posts`, `users`) referencias lógicas validadas, sin imponer FK a tablas core. En actualización se deben comprobar huérfanos y tipos/collations antes de crear FK. No asumir que `dbDelta()` administra cambios complejos o rollback.
8. Secretos de cliente y tokens **nunca en claro**; guardar únicamente hashes/HMAC. Secreto MP en configuración protegida no autoload y sin exposición REST; su cifrado/almacenamiento requiere host/extensiones P0-19. Archivos digitales privados quedan fuera de descarga HTTP directa.
9. Estados de proceso en columas `technical_phase` o `case_phase` son diagnósticos internos, **no nuevos estados de E-01**. Su alfabeto definitivo queda subordinado a reglas E-05/E-06 y DTO P0-23; no interpretar como transición autorizada.
10. Esquema **declarativo, sin CREATE TABLE ni migraciones ejecutables**. A partir de este catálogo se preparará DDL compatible con las dos familias de motores aprobadas, después de revisión de tamaño de índices, FK y versiones.

## 3. Propiedad de datos y cardinalidad

- `wp_posts` + metadatos/taxonomías WP: ficha de catálogo SEO, categoría, descripción, imágenes publicables. `pc_sellables`: **exactamente una fila por variante vendible o producto simple** con su precio y configuración comercial; nunca duplicar precio de línea histórica desde el catálogo.
- `pc_orders` 1:N `pc_order_lines`; pedido con **como máximo una línea servicio**; `pc_orders` 1:N intentos MP solo en recuperación/reintentos seguros; una **obligación de cobro inicial**, no un cobro por línea. `pc_order_lines` 0..1 `pc_appointments` efectiva por servicio.
- `pc_orders` 1:N `pc_payment_events`, `pc_order_adjustments`, `pc_cases`, `pc_external_documents` sin alterar original; dinero se reconstruye desde asientos **inmutables**, no desde un estado global de pago.
- Variante 1:N holds, admisiones, compromisos y movimientos; recurso único 1:N filas calendario por día, citas, bloqueos; no hay dos agendas por sucursal V1.
- `pc_guest_sessions` 1:N verificadores por pedido (enlace y sesión canjeada) y `pc_otp_challenges`/grants para acciones sensibles. `pc_download_rights` ligado a línea + archivo y token digital distinto al seguimiento.
- Libro/ARCO/casos son expedientes protegidos, no datos de catálogo público ni incluidos en exportaciones de operador.

## 4. Inventario físico de tablas

Los campos listados constituyen **columnas mínimas descriptivas**, incluyendo tipos/nulabilidad; cada tabla incluye índice propuesto y regla de negocio. Todo campo adicional deberá justificarse por consumidor REST/ER o caso de aceptación y revisarse antes del DDL definitivo.

### 4.1 pc_sellables

**Función:** Variante vendible (incluye producto simple/digital/servicio).

**Columnas mínimas:** id BIGINT UNSIGNED PK; product_post_id BIGINT UNSIGNED NN; variant_key VARCHAR(191) NN; kind VARCHAR(16) NN; sku VARCHAR(100) N; price_cents BIGINT NN; sale_price_cents BIGINT N; sale_active TINYINT(1) NN; track_stock TINYINT(1) NN; stock_on_hand BIGINT NN; service_duration_minutes INT UNSIGNED N; service_buffer_before_minutes INT UNSIGNED N; service_buffer_after_minutes INT UNSIGNED N; active TINYINT(1) NN; row_version BIGINT UNSIGNED NN; created_at DATETIME(6) NN; updated_at DATETIME(6) NN.

**Índices:** UQ(product_post_id,variant_key); UQ(sku) solo si no vacío, normalizando vacío a NULL; IX(kind,active); IX(product_post_id).

**Restricciones:** variant_key usa identificador canónico no vacío: 'base' para simple. Precio efectivo > 0 para vender; stock_on_hand >= 0. Cada variante exige precio explícito. WP post puede archivarse, no borrarse si hay historial.


### 4.2 pc_stock_movements

**Función:** Libro inmutable de movimientos físicos de inventario.

**Columnas mínimas:** id BIGINT UNSIGNED PK; sellable_id BIGINT UNSIGNED NN; delta_units BIGINT NN; balance_after BIGINT NN; source_type VARCHAR(32) NN; source_id BIGINT UNSIGNED N; actor_user_id BIGINT UNSIGNED N; idempotency_id BIGINT UNSIGNED N; reason_code VARCHAR(40) NN; occurred_at DATETIME(6) NN.

**Índices:** IX(sellable_id,occurred_at,id); UQ(idempotency_id,source_type) solo para movimientos de una fuente por operación, si aplica.

**Restricciones:** Ajuste y saldo materializado de pc_sellables cambian en la misma transacción. delta != 0; nunca borrar movimientos; rectificar con movimiento inverso, autorizado.


### 4.3 pc_stock_commitments

**Función:** Compromiso pendiente de salida por línea física (no equivale a stock ya entregado).

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_line_id BIGINT UNSIGNED NN; sellable_id BIGINT UNSIGNED NN; committed_units BIGINT UNSIGNED NN; fulfilled_units BIGINT UNSIGNED NN; released_units BIGINT UNSIGNED NN; created_at DATETIME(6) NN; updated_at DATETIME(6) NN; row_version BIGINT UNSIGNED NN.

**Índices:** UQ(order_line_id); IX(sellable_id).

**Restricciones:** Compromiso restante = committed-fulfilled-released >= 0. La primera salida física ocurre en F-04 (`LISTO → DESPACHADO`) o F-05 (`LISTO → ENTREGADO` por recojo): aumentar `fulfilled_units` y, solo con inventario controlado, descontar existencias reales en la misma transacción. F-06 (`DESPACHADO → ENTREGADO`) acredita recepción, sin otra salida ni doble descuento. No contar compromiso simultáneamente como hold vigente.

**Proyección de cantidades DOC-QA27 (0.10.0-draft):** `AdminOrderLine.quantity` y `GuestOrderLine.quantity` conservan siempre `pc_order_lines.quantity`, original e inmutable. Cada línea física devuelve además el objeto cerrado `physical_quantities` con sus cuatro campos obligatorios; el objeto está ausente en líneas digitales/de servicio.

| Campo de lectura | Fuente y significado |
| --- | --- |
| `committed_units` | Unidades asignadas a la línea confirmada, desde el compromiso existente; no suma holds o reintentos de pago |
| `released_units` | Unidades del compromiso liberadas **antes de salir físicamente**, por cancelación elegible o ajuste administrativo; no equivale a todas las unidades comercialmente canceladas |
| `stock_out_units` | `fulfilled_units`: unidades que ya salieron por despacho o recojo; conserva el hecho histórico y no acredita por sí solo recepción del comprador |
| `pending_stock_out_units` | `committed_units - fulfilled_units - released_units`; saldo comprometido que todavía no salió, calculado por el servidor |

Los cuatro valores REST son enteros `0..100000`, acotados por la cantidad original; `released_units + stock_out_units <= committed_units <= quantity`. No exponen stock global, expediente ni dinero. R8-09 los limita al pedido propio y R8-21 al pedido autorizado; las guardas E-01 y de actor continúan siendo necesarias aunque el saldo sea positivo.

- **Antes de la primera confirmación:** si aún no existe compromiso, los cuatro contadores son cero. Un hold de un pedido `PENDIENTE_PAGO` o `VENCIDO` no se presenta como unidades listas para preparar. La cantidad pactada y los estados se mantienen visibles. Cuando O-05 recupera válidamente el pedido, la asignación crea el compromiso una sola vez; no acumular unidades originales por cada intento/replay. La ausencia inesperada del compromiso de una línea física ya confirmada es inconsistencia: no sustituirla por ceros ni habilitar cumplimiento.
- **Stock opcional:** mantener estos compromisos/contadores operativos también si `track_stock=false`; no aplicarles una disponibilidad cero artificial, ni bloquear por existencias ni descontar un saldo inventariable desactivado. Desactivar control de inventario no borra compromisos/historia ni la cantidad que corresponde preparar.
- **Reducciones y cancelaciones:** `unit_effect=release_unfulfilled_units` exige línea física y compromiso vigente; no se aplica a digital/servicio (422 `pc_business_ineligible`). R8-26 solo libera unidades aún no salidas y de forma estrictamente parcial: bajo lock, `0 < affected_units < pending_stock_out_units` vigente, conservando el snapshot. Liberar todo el remanente exige R8-23, cancelación completa de la línea con sus guardas, expediente y efectos existentes; libera el compromiso restante una vez y no deja una línea en preparación/lista sin unidades. F-09 y una devolución posterior no reclasifican unidades ya salidas como `released_units` ni reducen `stock_out_units`; cualquier reposición física comprobada se registra separadamente. Un saldo cero impide una nueva salida, pero permite F-06 (`DESPACHADO → ENTREGADO`) con sus guardas porque la mercancía ya salió; no crea una nueva transición de negocio.
- **Concurrencia y lectura:** toda variación de estos contadores actualiza `pc_stock_commitments.row_version` y `pc_order_lines.row_version` en la misma transacción de la mutación, con sus locks y el versionado del pedido que corresponda. R8-09/21 leen línea, estado y contadores desde una instantánea coherente; no mezclar versión anterior con cantidades nuevas. R8-22/23 rechazan con conflicto la versión de línea obsoleta y obligan a recargar. Un reintento idempotente no vuelve a liberar ni sacar unidades.

Ejemplo: `quantity=3`, compromiso de tres y reducción aprobada de una → `physical_quantities={"committed_units":3,"released_units":1,"stock_out_units":0,"pending_stock_out_units":2}`. Tras F-04 → `{"committed_units":3,"released_units":1,"stock_out_units":2,"pending_stock_out_units":0}`; F-06 conserva esos contadores. Son ejemplos contractuales, no resultados de pruebas.


### 4.4 pc_orders

**Función:** Pedido administrativo y snapshot original del contrato.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_number VARCHAR(40) NN; status VARCHAR(24) NN; payment_method VARCHAR(16) NN; currency CHAR(3) NN; subtotal_cents BIGINT NN; delivery_cents BIGINT NN; total_cents BIGINT NN; buyer_email VARCHAR(254) N; buyer_phone VARCHAR(32) N; customer_snapshot LONGTEXT NN; quote_fingerprint BINARY(32) NN; policy_version_id BIGINT UNSIGNED NN; privacy_version_id BIGINT UNSIGNED NN; original_delivery_snapshot LONGTEXT N; current_delivery_revision_id BIGINT UNSIGNED N; confirmed_at DATETIME(6) N; closed_at DATETIME(6) N; created_at DATETIME(6) NN; updated_at DATETIME(6) NN; row_version BIGINT UNSIGNED NN.

**Índices:** UQ(order_number); IX(status,created_at,id); IX(created_at,id).

**Restricciones:** E-01 solo permite PENDIENTE_PAGO, CONFIRMADO, FINALIZADO, CANCELADO, VENCIDO. Un solo pedido por operación de checkout idempotente. Snapshots originales nunca reescritos; current_delivery_revision_id es puntero operativo mutable, no el snapshot.


### 4.5 pc_order_lines

**Función:** Líneas mixtas con precio, cantidad, estado de cumplimiento y datos pactados.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; sellable_id BIGINT UNSIGNED NN; kind VARCHAR(16) NN; quantity INT UNSIGNED NN; unit_price_cents BIGINT NN; subtotal_cents BIGINT NN; fulfillment_state VARCHAR(32) NN; catalog_snapshot LONGTEXT NN; service_slot_start_utc DATETIME(6) N; service_slot_end_utc DATETIME(6) N; service_timezone VARCHAR(64) N; row_version BIGINT UNSIGNED NN; created_at DATETIME(6) NN; updated_at DATETIME(6) NN.

**Índices:** IX(order_id,id); IX(sellable_id,fulfillment_state); IX(order_id,kind).

**Restricciones:** Solo estados E-01 propios de físico/digital/servicio; no estado global derivado extra. Cantidad entera > 0. Como máximo 1 línea de servicio por pedido: comprobar bajo lock y rechazo del backend; si se exige garantía SQL, índice/generación derivada sujeto a DDL compatible.


### 4.6 pc_payment_attempts

**Función:** Intento MP con estado de proveedor y vínculo a pedido.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; provider VARCHAR(20) NN; provider_account_key BINARY(32) NN; environment VARCHAR(12) NN; provider_order_id VARCHAR(128) N; provider_payment_id VARCHAR(128) N; mp_idempotency_key VARCHAR(128) NN; amount_cents BIGINT NN; currency CHAR(3) NN; state VARCHAR(32) NN; checkout_url_reference LONGTEXT N; created_at DATETIME(6) NN; last_verified_at DATETIME(6) N; row_version BIGINT UNSIGNED NN.

**Índices:** UQ(provider,provider_account_key,environment,provider_order_id) cuando no NULL; UQ(provider,provider_account_key,environment,mp_idempotency_key); IX(order_id,created_at).

**Restricciones:** Estados del intento solo E-01: CREADO, PENDIENTE, APROBADO, RECHAZADO, CANCELADO_PROVEEDOR, REQUIERE_CONCILIACION. Ningún segundo cargo ciego ante intento ambiguo. Credenciales no almacenadas aquí.


### 4.7 pc_payment_events

**Función:** Libro financiero inmutable de cobros, comisiones, ajustes y reintegros externos verificados.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; payment_attempt_id BIGINT UNSIGNED N; kind VARCHAR(32) NN; amount_cents BIGINT NN; source_payment_event_id BIGINT UNSIGNED N; provider VARCHAR(20) N; provider_account_key BINARY(32) N; environment VARCHAR(12) N; external_reference VARCHAR(128) N; event_dedup_hash BINARY(32) NN; verification_reference VARCHAR(200) N; correction_evidence_snapshot LONGTEXT N; refund_evidence_snapshot LONGTEXT N; actor_user_id BIGINT UNSIGNED N; occurred_at DATETIME(6) NN; recorded_at DATETIME(6) NN.

**Índices:** UQ(event_dedup_hash); IX(order_id,recorded_at,id); IX(source_payment_event_id); IX(provider,provider_account_key,environment,external_reference).

**Restricciones:** Tipos de movimiento técnicos no reemplazan estados de E-01; importe con signo/semántica por clase, sin float. Verificar cobro y origen; reembolso externo registrado únicamente por admin con referencia/evidencia; no ejecutar refund por API. Comisiones separadas del bruto.

**IMP-42, dato imprescindible R8-27 y esquema `1.0.2` (2026-11-20):** `refund_evidence_snapshot LONGTEXT NULL` se incorpora **solo** para `pc_payment_events.kind=external_refund`, sin tabla adicional. Su JSON privado contiene la referencia original completa (OpenAPI hasta 200 caracteres), referencia opaca de la constancia (hasta 500), cobro fuente, importe positivo declarado, proveedor, modalidad de comprobación manual, identidad A WP y hora UTC en la que A registró su verificación. La referencia pública del asiento en `external_reference VARCHAR(128)` es una huella determinista `refund:SHA-256(normalized_reference)`, **nunca una truncación**; `verification_reference` es un digest de la constancia, no el documento. `event_dedup_hash` para `external_refund` se construye por proveedor/cuenta/ambiente y referencia externa completa canónica **sin** `source_payment_event_id` ni idempotency_id, para impedir registrar el mismo resultado con otro cobro, operación o actor. La comparación del resultado referenciado usa `source_payment_event_id` con FK al pago original del mismo pedido; se conserva `UQ(event_dedup_hash)` para cerrar carreras entre pedidos, sin agregar índice ni exponer pruebas al operador/guest. R8-21 autorizado A muestra los eventos históricos y, cuando el ID literal cabe en `AdminFinancialEvent.external_reference` (máximo 128), ese ID; si no cabe, muestra el token opaco y conserva el completo en el snapshot privado. No se amplía el DTO ni se comunica el documento a una sesión guest.

**Alcance de comprobación:** R8-27 registra resultados **ya comprobados fuera** del plugin por A, no consulta/ejecuta automáticamente refunds de Mercado Pago ni certifica que el comprobante que A ingresó sea auténtico. El sistema valida actor, fuente, cuenta/ambiente, intento MP aprobado, importe, saldo y referencia/constancia inequívocas y guarda la atestación con auditoría; la constatación material y custodia de documentos siguen P-C/P-D. El campo `occurred_at` representa aquí el momento UTC de **registro/atestación** por A, pues `ExternalRefundRecord` no incluye fecha de ejecución original del proveedor; nunca presentarlo como fecha bancaria confirmada. Ante contracargos/reversiones/correcciones/ambigüedad se bloquea registro hasta conciliación separada. La nueva columna requiere una migración autorizada si la tienda ya instaló `1.0.1`; `Installer` bloquea cambios de versión no soportados, no se ejecutó DDL/migración.

**IMP-38, dato mínimo justificado por R8-25:** `correction_evidence_snapshot` es **NULL** salvo corrección `cash_correction` de efectivo. Conserva motivo íntegro (máximo 1000 caracteres), referencia de evidencia opcional y vínculo al evento original en JSON privado, protegido por privilegios financieros A; no se proyecta al operador ni al invitado y no debe propagarse a auditoría general o mensajes de error. El campo se escribe exclusivamente al insertar el evento inmutable, sin actualizar recibos anteriores ni crear otra tabla. El esquema declarativo de instalación nueva incorpora la columna; instalaciones antiguas requieren migración P-C autorizada antes de habilitar R8-25. El SHA-256 del motivo/referencia en `pc_audit_log` permite detectar cambios de evidencia; la cadena suministrada por A no constituye verificación física independiente. El cumplimiento de retención, cifrado de backup y calidad de evidencia queda en P-C/P-D.


### 4.8 pc_order_adjustments

**Función:** Créditos/diferencias pactadas sin editar línea, entrega ni cobro original.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; order_line_id BIGINT UNSIGNED N; case_id BIGINT UNSIGNED N; reason_code VARCHAR(40) NN; delta_cents BIGINT NN; approved_by BIGINT UNSIGNED NN; original_basis_snapshot LONGTEXT NN; created_at DATETIME(6) NN; idempotency_id BIGINT UNSIGNED NN.

**Índices:** UQ(idempotency_id); IX(order_id,created_at,id); IX(case_id).

**Restricciones:** No interpretar ajuste económico como reembolso realizado; importes máximos/legales se validan por E-03/E-04 y J-01…J-05.


### 4.9 pc_holds

**Función:** Retenciones E-01 del intento MP; su expiración no depende del cron.

**Columnas mínimas:** id BIGINT UNSIGNED PK; payment_attempt_id BIGINT UNSIGNED NN; order_id BIGINT UNSIGNED NN; order_line_id BIGINT UNSIGNED NN; resource_kind VARCHAR(12) NN; sellable_id BIGINT UNSIGNED N; resource_id BIGINT UNSIGNED N; quantity INT UNSIGNED N; slot_start_utc DATETIME(6) N; slot_end_utc DATETIME(6) N; state VARCHAR(16) NN; started_at DATETIME(6) NN; expires_at DATETIME(6) NN; committed_at DATETIME(6) N; released_at DATETIME(6) N.

**Índices:** UQ(payment_attempt_id,order_line_id,resource_kind); IX(order_line_id,state); IX(sellable_id,state,expires_at); IX(resource_id,slot_start_utc,slot_end_utc); IX(state,expires_at).

**Restricciones:** Solo VIGENTE/COMPROMETIDA/VENCIDA/LIBERADA (E-01). Vigente consume capacidad si expires_at > hora UTC efectiva; compromiso MP genera entidad pc_stock_commitments o cita efectiva sin doble contabilización. COD compromete directamente sin hold MP.


### 4.10 pc_resource_admissions

**Función:** Admisión técnica breve previa a HTTP externo; NO es estado E-01 ni hold comercial.

**Columnas mínimas:** id BIGINT UNSIGNED PK; idempotency_id BIGINT UNSIGNED NN; sellable_id BIGINT UNSIGNED N; resource_id BIGINT UNSIGNED N; quantity INT UNSIGNED N; slot_start_utc DATETIME(6) N; slot_end_utc DATETIME(6) N; created_at DATETIME(6) NN; expires_at DATETIME(6) NN; consumed_at DATETIME(6) N; released_at DATETIME(6) N.

**Índices:** IX(sellable_id,expires_at); IX(resource_id,slot_start_utc,slot_end_utc); IX(idempotency_id).

**Restricciones D-190:** admisión técnica pre-HTTP **60 segundos desde `created_at` hasta `expires_at`**, sin renovación implícita; excluir consumed/released y expiradas en tiempo UTC efectivo. El intento HTTP ambiguo pasa a conciliación sin lanzar segundo cobro ciego; el hold comercial de 15 min arranca únicamente tras intento proveedor válido, sin reutilizar la admisión como retención comercial.


### 4.11 pc_idempotency

**Función:** Resultado de operación crítica durable, no estado de sesión.

**Columnas mínimas:** id BIGINT UNSIGNED PK; scope_hash BINARY(32) NN; key_hash BINARY(32) NN; request_hash BINARY(32) NN; operation_type VARCHAR(48) NN; technical_phase VARCHAR(24) NN; entity_type VARCHAR(24) N; entity_id BIGINT UNSIGNED N; response_code SMALLINT UNSIGNED N; response_ref LONGTEXT N; provider_account_key BINARY(32) N; provider_environment VARCHAR(12) N; provider_idempotency_key VARCHAR(128) N; provider_external_reference VARCHAR(128) N; checkout_context_snapshot LONGTEXT N; expires_at DATETIME(6) N; created_at DATETIME(6) NN; updated_at DATETIME(6) NN.

**Índices:** UQ(scope_hash,key_hash); UQ(provider_account_key,provider_environment,provider_idempotency_key) cuando sus tres valores MP estén presentes; IX(technical_phase,updated_at); IX(expires_at). La cuenta/ambiente se verifican y claves ausentes no se interpretan como órdenes únicas.

**Restricciones:** Mismo key+payload → resultado persistido; key+payload distinto → conflicto. La fase técnica no se confunde con estados de pedido/pago. Retención de keys según riesgo de doble cargo/obligaciones, no purgar mientras haya operación incierta.


### 4.12 pc_webhook_inbox

**Función:** Recepción autenticada, duradera y deduplicada de notificaciones del proveedor.

**Columnas mínimas:** id BIGINT UNSIGNED PK; provider VARCHAR(20) NN; provider_account_key BINARY(32) NN; environment VARCHAR(12) NN; event_dedup_hash BINARY(32) NN; resource_id VARCHAR(128) NN; action_code VARCHAR(64) NN; signature_verified_at DATETIME(6) NN; payload_digest BINARY(32) NN; technical_phase VARCHAR(24) NN; received_at DATETIME(6) NN; processed_at DATETIME(6) N; next_retry_at DATETIME(6) N; retry_count INT UNSIGNED NN; last_error_code VARCHAR(40) N.

**Índices:** UQ(provider,provider_account_key,environment,event_dedup_hash); IX(technical_phase,next_retry_at,id); IX(resource_id).

**Restricciones:** No confirmar recepción si no se persiste seguro. Nunca acreditar desde cuerpo del webhook sin GET autoritativo; evento repetido no duplica cobros.


### 4.13 pc_outbox

**Función:** Intenciones duraderas de avisos/acciones diferidas, separadas de hechos de negocio.

**Columnas mínimas:** id BIGINT UNSIGNED PK; deduplication_hash BINARY(32) NN; event_code VARCHAR(40) NN; entity_type VARCHAR(30) NN; entity_id BIGINT UNSIGNED NN; audience_code VARCHAR(24) NN; channel VARCHAR(20) NN; template_version VARCHAR(32) N; destination_ref LONGTEXT N; safe_payload LONGTEXT N; technical_phase VARCHAR(24) NN; attempt_count INT UNSIGNED NN; next_attempt_at DATETIME(6) N; accepted_at DATETIME(6) N; completed_at DATETIME(6) N; last_error_code VARCHAR(40) N; row_version BIGINT UNSIGNED NN; created_at DATETIME(6) NN.

**Índices:** UQ(deduplication_hash); IX(technical_phase,next_attempt_at,id); IX(entity_type,entity_id).

**Restricciones:** E-06: wp_mail() aceptado no demuestra entrega. Reintentos idempotentes, auditables; no almacenar correo completo ni enlaces secretos en logs. No convertir avisos en confirmaciones financieras.

**Preferencia de avisos DOC-QA29:** al procesar por primera vez un evento rutinario de comprador definido en `docs/19`, congelar en `safe_payload.routine_email_decision` los campos `enabled` (booleano efectivo) y `settings_row_version` (versión de configuración). Si está activado y procede el envío, la intención EMAIL conserva esa decisión; si está desactivado, no se crea intención EMAIL rutinaria, pero la misma transacción conserva la decisión correlacionada al evento en el `safe_payload` de su intención PANEL existente o en un registro durable correlacionado y recuperable. La deduplicación/replay reutiliza esa decisión, sin recalcularla con preferencias futuras. Reactivar no crea correos retrospectivos; cola/reintentos/reenvíos existentes conservan su decisión original. No crear otro estado técnico, tabla o credencial; WEB, PANEL, historia y comunicaciones excluidas de la preferencia permanecen.


### 4.14 pc_calendar_days

**Función:** Fila estable de lock para un único recurso/día, incluso sin citas.

**Columnas mínimas:** id BIGINT UNSIGNED PK; resource_id BIGINT UNSIGNED NN; local_date DATE NN; created_at DATETIME(6) NN; row_version BIGINT UNSIGNED NN.

**Índices:** UQ(resource_id,local_date).

**Restricciones:** Crear/obtener bajo transacción y bloquear días implicados en orden ascendente antes de comprobar reservas. Zona horaria del comercio se documenta por configuración versionada.


### 4.15 pc_schedule_rules

**Función:** Horario semanal y buffers auditables del recurso único.

**Columnas mínimas (DB-A01, diseño normalizado):** id BIGINT UNSIGNED PK; resource_id BIGINT UNSIGNED NN; revision_no BIGINT UNSIGNED NN; window_index SMALLINT UNSIGNED NN; weekday TINYINT UNSIGNED NN (0…6); opens_local TIME N; closes_local TIME N; closes_day_offset TINYINT UNSIGNED NN (0 mismo día, 1 día siguiente); enabled TINYINT(1) NN; valid_from DATE NN; valid_to DATE N; row_version BIGINT UNSIGNED NN; created_at DATETIME(6) NN.

**Índices:** UQ(resource_id,revision_no,weekday,window_index); IX(resource_id,weekday,valid_from,valid_to); IX(resource_id,revision_no).

**Restricciones DB-A01:** todas las ventanas de una revisión forman un conjunto inmutable: actualizar horarios crea un `revision_no` nuevo bajo CAS de `pc_schedule_revisions`, jamás `UPDATE` sobre revisión activa o histórica. Intervalo local semiabierto [opens_local, closes_local + closes_day_offset]; validar horario nocturno (offset 1), día 0…6, ventana válida y no superposición de ventanas/buffers bajo lock de recurso/día; duración, anticipación y zona IANA provienen del contrato de servicio. No mover citas confirmadas; convertir a UTC al comprobar intersecciones y cambios de zona horaria/DST.


### 4.16 pc_schedule_blocks

**Función:** Excepciones y cierres de agenda, sin alterar citas históricas.

**Columnas mínimas:** id BIGINT UNSIGNED PK; resource_id BIGINT UNSIGNED NN; starts_at_utc DATETIME(6) NN; ends_at_utc DATETIME(6) NN; reason_code VARCHAR(40) NN; actor_user_id BIGINT UNSIGNED N; created_at DATETIME(6) NN; row_version BIGINT UNSIGNED NN.

**Índices:** IX(resource_id,starts_at_utc,ends_at_utc).

**Restricciones:** Insertar/cambiar bajo locks pc_calendar_days; duración y buffer deben evaluarse en consulta de solapamiento.


### 4.17 pc_appointments

**Función:** Cita efectiva de servicio; NO existe durante selección ni retención MP.

**Columnas mínimas (DB-A03):** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; order_line_id BIGINT UNSIGNED NN; resource_id BIGINT UNSIGNED NN; starts_at_utc DATETIME(6) NN; ends_at_utc DATETIME(6) NN; local_timezone VARCHAR(64) NN; cancelled_at DATETIME(6) N; completed_at DATETIME(6) N; no_show_at DATETIME(6) N; row_version BIGINT UNSIGNED NN; created_at DATETIME(6) NN; updated_at DATETIME(6) NN.

**Índices:** UQ(order_id); UQ(order_line_id); IX(resource_id,starts_at_utc,ends_at_utc). El par `(order_id, order_line_id)` debe referirse a una línea del **mismo** pedido; usar FK compuesta con índice compatible cuando el motor lo permita, además de validación transaccional.

**Restricciones DB-A03:** **una sola fila de cita por pedido durante todo su ciclo de vida** (`UQ(order_id)`), regla conservadora que satisface el máximo de una cita efectiva y evita duplicación aun ante reintentos concurrentes. La línea de cita pertenece al pedido y se valida con `pc_orders` bloqueada al añadir/quitar líneas de servicio. Reprogramar modifica horario/versionado de esa misma cita bajo locks de ambos días y escribe `pc_appointment_history` inmutable; no crea una segunda fila. Una cita cancelada no se reemplaza con otra en el mismo pedido: nueva contratación requiere otro pedido. Sin reserva efectiva durante selección o hold MP; estados CONFIRMADA/REALIZADA/CANCELADA/NO_ASISTIO según E-01.


### 4.18 pc_appointment_history

**Función:** Historial inmutable de cambios de cita, sin nueva máquina de estados.

**Columnas mínimas:** id BIGINT UNSIGNED PK; appointment_id BIGINT UNSIGNED NN; previous_start_utc DATETIME(6) NN; previous_end_utc DATETIME(6) NN; new_start_utc DATETIME(6) NN; new_end_utc DATETIME(6) NN; actor_user_id BIGINT UNSIGNED NN; reason_code VARCHAR(40) NN; communication_outbox_id BIGINT UNSIGNED N; occurred_at DATETIME(6) NN.

**Índices:** IX(appointment_id,occurred_at,id).

**Restricciones:** Versionar hechos y constancia de comunicación, sin asumir que intento de envío significa notificación recibida.


**Procedencia y versión P0-21 (investigación posterior):** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` compara publicaciones oficiales INEI de 2023 (licencia catalogada ODbL, datos completos de referencia 2022) y 2025-12 (Open Data Commons Attribution, sin garantía de catálogo distrital vigente completo). **No hay dataset descargado ni versión 2026 ratificada**. Las columnas `source_url`, `dataset_hash`, `published_on`, `imported_at` y `is_current` deben registrar procedencia del artefacto real; ZIP y licencia se validarán por release antes de importar.

### 4.19 pc_ubigeo_versions

**Función:** Procedencia e integridad de cada catálogo distrital.

**Columnas mínimas:** id BIGINT UNSIGNED PK; source_name VARCHAR(100) NN; source_url VARCHAR(500) NN; dataset_version VARCHAR(80) NN; dataset_hash BINARY(32) NN; published_on DATE N; imported_at DATETIME(6) NN; is_current TINYINT(1) NN.

**Índices:** UQ(dataset_hash); IX(is_current,imported_at).

**Restricciones:** Ninguna actualización altera el UBIGEO histórico de pedido confirmado.


### 4.20 pc_ubigeo

**Función:** Distrito provincial/departamental versionado y normalizado.

**Columnas mínimas:** id BIGINT UNSIGNED PK; version_id BIGINT UNSIGNED NN; code CHAR(6) NN; department_name VARCHAR(120) NN; province_name VARCHAR(120) NN; district_name VARCHAR(120) NN; valid TINYINT(1) NN.

**Índices:** UQ(version_id,code); IX(code).

**Restricciones:** Solo identificador UBIGEO en backend determina cobertura. Confirmar versión/altas territoriales antes de distribuir catálogo.


### 4.21 pc_delivery_rates

**Función:** Cobertura y tarifa vigente por distrito; no representa mapas.

**Columnas mínimas:** id BIGINT UNSIGNED PK; ubigeo_code CHAR(6) NN; enabled TINYINT(1) NN; fee_cents BIGINT NN; row_version BIGINT UNSIGNED NN; updated_by BIGINT UNSIGNED NN; updated_at DATETIME(6) NN.

**Índices:** UQ(ubigeo_code); IX(enabled,ubigeo_code).

**Restricciones:** Un comercio y local; fee>=0; conservar tarifa original en pedido/entrega. Cambios posteriores no afectan pedidos históricos.


### 4.22 pc_delivery_revisions

**Función:** Original de dirección de entrega y revisiones posteriores auditadas.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; previous_revision_id BIGINT UNSIGNED N; revision_number INT UNSIGNED NN; ubigeo_code CHAR(6) NN; address_snapshot LONGTEXT NN; fee_cents BIGINT NN; approved_by BIGINT UNSIGNED N; approved_at DATETIME(6) N; created_at DATETIME(6) NN; case_id BIGINT UNSIGNED N.

**Índices:** UQ(order_id,revision_number); IX(order_id,created_at,id).

**Restricciones:** Originales y revisiones inmutables; pc_orders.current_delivery_revision_id apunta solo a revisión aprobada; tarifa más cara después de cobro NO ocasiona cargo MP adicional.


### 4.23 pc_guest_sessions

**Función:** Verificador de enlace privado y sesiones limitadas a un pedido.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; credential_kind VARCHAR(12) NN; token_hash BINARY(32) NN; source_credential_id BIGINT UNSIGNED N; issued_at DATETIME(6) NN; expires_at DATETIME(6) NN; revoked_at DATETIME(6) N; last_used_at DATETIME(6) N.

**Índices:** UQ(token_hash); IX(order_id,credential_kind,expires_at).

**Restricciones D-190:** enlace fuente secreto de 256 bits válido 90 días y revocable. Canje same-origin a cookie HttpOnly/Secure/SameSite de **30 minutos de inactividad**, renovable por uso legítimo pero nunca más allá de los 90 días absolutos ni tras revocación del enlace. Persistir solo verificador HMAC/hash del secreto; invalidar cookie y grants al revocar la fuente, no extender derechos del comprador.


### 4.24 pc_otp_challenges

**Función:** OTP para acciones sensibles con correo, no para checkout.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; guest_session_id BIGINT UNSIGNED NN; purpose VARCHAR(40) NN; recipient_hash BINARY(32) NN; code_hash BINARY(32) NN; issued_at DATETIME(6) NN; expires_at DATETIME(6) NN; attempts TINYINT UNSIGNED NN; consumed_at DATETIME(6) N; superseded_at DATETIME(6) N.

**Índices:** IX(guest_session_id,purpose,issued_at); IX(order_id,purpose,issued_at); IX(recipient_hash,expires_at).

**Restricciones D-138/D-190:** OTP 8 dígitos, TTL 10 min y máximo 5 intentos, vinculado a la sesión invitada canjeada y al pedido/propósito (verificar que guest_session_id.order_id coincide). No usar OTP tras revocar/sustituir la sesión; reenvíos limitados y respuesta no enumerable. Sin email se requiere verificación manual documentada; no emitir grant automático sin identidad suficiente.


### 4.25 pc_action_grants

**Función:** Autorización temporal acotada posterior a OTP/verificación.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; guest_session_id BIGINT UNSIGNED NN; purpose VARCHAR(40) NN; token_hash BINARY(32) NN; verified_challenge_id BIGINT UNSIGNED N; expires_at DATETIME(6) NN; consumed_at DATETIME(6) N; created_at DATETIME(6) NN.

**Índices:** UQ(token_hash); IX(guest_session_id,purpose,expires_at); IX(order_id,purpose,expires_at).

**Restricciones D-190:** grant de **cinco minutos** desde emisión, vinculado físicamente por guest_session_id al pedido, propósito y sesión canjeada; validar que la sesión y desafío pertenecen al mismo pedido, son vigentes y fueron legitimados. Consumo **una sola vez atómicamente** con versión/cambio condicional que rechace replay; el OTP previo mantiene 10 minutos/5 intentos. Revocar grant por cambio de sujeto/acción, cookie invalidada o enlace fuente revocado. No conceder lectura masiva ni fondos por la posesión del grant.


### 4.26 pc_digital_files

**Función:** Referencia al binario privado sin URL pública.

**Columnas mínimas (DB-A08):** id BIGINT UNSIGNED PK; owner_product_post_id BIGINT UNSIGNED NN; sellable_id BIGINT UNSIGNED NN; replaces_file_id BIGINT UNSIGNED N (referencia a versión anterior del mismo vendible); private_storage_key VARCHAR(255) NN; original_filename VARCHAR(255) NN; mime_type VARCHAR(100) NN; byte_size BIGINT UNSIGNED NN; content_hash BINARY(32) NN; created_at DATETIME(6) NN; archived_at DATETIME(6) N.

**Índices:** UQ(private_storage_key); IX(sellable_id,created_at,id); IX(replaces_file_id); IX(owner_product_post_id,created_at). `sellable_id` es el origen de propiedad por variante, `owner_product_post_id` solo referencia de catálogo WordPress.

**Restricciones DB-A08:** cada fila es una versión inmutable y pertenece a un vendible/variante real; `replaces_file_id` solo puede apuntar a versión anterior del mismo `sellable_id` (sin ciclos). Cambiar archivo activo se hace en `pc_digital_assignments` con CAS de `row_version`; `pc_download_rights.file_id` queda congelado por compra y nunca se actualiza al reemplazar el archivo. Binarios cifrados/protegidos fuera de acceso HTTP público; no usar Media Library pública, bloquear venta/descarga digital si el hosting no protege.


### 4.27 pc_download_rights

**Función:** Derecho de descarga derivado de línea digital habilitada.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_line_id BIGINT UNSIGNED NN; file_id BIGINT UNSIGNED NN; granted_at DATETIME(6) NN; revoked_at DATETIME(6) N; optional_limit INT UNSIGNED N; download_count INT UNSIGNED NN; row_version BIGINT UNSIGNED NN.

**Índices:** UQ(order_line_id,file_id); IX(file_id).

**Restricciones:** Token de 24 h no es el derecho; cantidad de descargas ilimitada por defecto. Verificar pago efectivo/derecho al descargar, revocar solo por causa válida.


### 4.28 pc_download_tokens

**Función:** Tokens temporales de 256 bits independientes del seguimiento.

**Columnas mínimas:** id BIGINT UNSIGNED PK; right_id BIGINT UNSIGNED NN; token_hash BINARY(32) NN; issued_at DATETIME(6) NN; expires_at DATETIME(6) NN; revoked_at DATETIME(6) N; last_used_at DATETIME(6) N.

**Índices:** UQ(token_hash); IX(right_id,expires_at).

**Restricciones:** TTL 24h; reemisión tras autorización revoca token anterior, no modifica el derecho ni historial.


### 4.29 pc_download_events

**Función:** Auditoría mínima de descargas de archivo privado.

**Columnas mínimas:** id BIGINT UNSIGNED PK; right_id BIGINT UNSIGNED NN; file_id BIGINT UNSIGNED NN; result_code VARCHAR(32) NN; occurred_at DATETIME(6) NN; opaque_request_id BINARY(16) N.

**Índices:** IX(right_id,occurred_at,id).

**Restricciones:** No guardar token, URL secreta ni IP completa de forma permanente.


**Decisiones posteriores D-189/D-190/D-191/D-192 (2026-11-17):** el contrato lógico de 39 tablas está ratificado sin DDL ni ejecución; el acceso íntegro individual de expedientes usa **R8-65/66 privados dentro del panel Angular**, no la pantalla WordPress candidata histórica. Admisión MP de 60 s, grant de un uso por 5 min y sesión canjeada con inactividad 30 min no cambian los límites ya aprobados de 15 min/10 min/24 h/90 d. Campos normativos y retenciones por comerciante siguen condicionados. OpenAPI vigente: 65 REST/62 rutas/115 DTO (0.4.0-draft).

### 4.30 pc_complaints

**Función:** Expediente de Libro nativo cuando comercio habilita opción correspondiente.

**Columnas mínimas de diseño D-191/D-192:** id BIGINT UNSIGNED PK; sequence_scope VARCHAR(40) NN; sequence_number BIGINT UNSIGNED NN; submitted_at DATETIME(6) NN; due_at DATETIME(6) N; merchant_snapshot LONGTEXT NN; complainant_snapshot LONGTEXT NN; complaint_snapshot LONGTEXT NN; evidence_reference LONGTEXT N; evidence_manifest LONGTEXT N; responded_at DATETIME(6) N; response_channel VARCHAR(32) N; response_delivered_at DATETIME(6) N; response_snapshot LONGTEXT N; response_evidence LONGTEXT N; row_version BIGINT UNSIGNED NN.

**Índices:** UQ(sequence_scope,sequence_number); IX(submitted_at,id); IX(responded_at).

**Restricciones D-198:** no exigir cuenta, compra ni correo para presentar reclamación legal. Correlativo/folio y `merchant_snapshot` original inmutables; al crear devolver `ComplaintReceiptCopy` imprimible con el contenido registrado sin añadir GET público por folio; deduplicar por `idempotency_key` de formulario ligada a sesión same-origin. `evidence_manifest` contiene únicamente referencias opacas `PrivateEvidenceRef`, procedencia, hash y metadatos de binarios privados vinculados al caso; no exponer clave de almacenamiento. R8-67 transmite binario exclusivamente a administrador A autorizado del mismo caso/instalación; nunca a visitante u operador, ni mediante URL pública. Plazos hábiles sujetos a J-01; enviar wp_mail no acredita recepción.


### Apertura administrativa de expediente comercial D-199 / R8-69

**No se crea tabla nueva.** Para `R8-69` se usa `pc_cases` con `case_type=physical_partial_quantity_adjustment`, `order_id`/ `order_line_id` NN en este tipo de caso, `source_operation_id=NULL` (reservado a casos MP prepedido), `case_phase=open_review` y `row_version` inicial. El servidor fija `created_by` al ID WordPress autenticado A (no valor enviado por navegador), `opened_at` UTC y `reason_code`. `evidence_snapshot` conserva la solicitud original minimizada: unidades solicitadas, motivo, categoría de verificación manual y nota sin datos secretos; no clonar el número de teléfono, valor del dato privado, identidad completa ni OTP en logs. `pc_case_events` registra `manual_case_opened` con `actor_kind=staff`, `actor_user_id` real y `idempotency_id` único; cada creación/auditoría se confirma atómicamente.

**Restricciones transaccionales:** `pc_idempotency` scope (instalación, administrador, pedido, línea, finalidad, clave), comparar hash de payload. Reintento idéntico recupera el mismo `case_id` sin otro evento; mismo key con distinta solicitud devuelve 409. Bloquear `pc_orders` y `pc_order_lines` y comparar `expected_order_version/expected_line_version`, asegurar pertenencia y `kind=physical`, `0 < requested_affected_units < pending_stock_out_units` y que no exista otro caso `open_review` de esa finalidad/línea. La ausencia de correo del comprador NO invalida la solicitud; sí debe haberse verificado control del teléfono previamente registrado y segundo dato del pedido no público conforme E07-E. **No** liberar compromiso ni alterar dinero al abrir el caso. `R8-26` bajo transacción propia volverá a comprobar caso de mismo pedido/línea/tipo/estado, autorización A y límites de unidades/precio con idempotencia distinta; la apertura del caso por sí sola no constituye una decisión aprobatoria. Si se rechaza/archiva, conservar eventos y auditoría.

### Cierre completo del expediente físico parcial — D-200 / DOC-QA31

**Estados internos exclusivos del `case_type=physical_partial_quantity_adjustment`:** `open_review` tras R8-69 → **`closed_rejected`** o **`closed_withdrawn`** por R8-70; o **`closed_adjusted`** únicamente si R8-26 ejecuta efectivamente ajuste parcial válido. Son valores de `pc_cases.case_phase VARCHAR(32)`, **NO** estados comerciales E-01 ni reembolsos verificados. Un caso terminal nunca retorna a `open_review`; nuevo pedido de ajuste genuino requiere **nuevo expediente**, verificación, versiones e idempotencia. Ninguna transición borra el caso ni sus eventos.

**R8-70 rechazo/retiro sin efecto:** solo A `perucommerce_orders_read` Y `perucommerce_orders_financial_adjust`. Se bloquean **`pc_orders → pc_order_lines → pc_cases`** en ese orden, se comprueba instalación/pedido/línea/tipo, `case_phase=open_review`, `expected_case_version=pc_cases.row_version` y que no exista movimiento de ajuste aplicado para el caso. `decision=reject` exige motivo; `buyer_withdrawn` exige `withdrawal_verification` de **nueva** comprobación de teléfono registrado más segundo dato privado, sin registrar literalmente factores/DNI/OTP. La transacción escribe `pc_case_events` inmutable `manual_case_rejected`/`manual_case_withdrawn`, nueva fase, `resolved_at` UTC, `row_version+1`, `pc_idempotency` y `pc_audit_log` (sin PII). El **mismo intento lógico** retorna respuesta idéntica sin segundo evento; clave igual con payload diferente, versión obsoleta o caso ya terminal: 409, salvo replay original autorizado recuperado antes de volver a validar estado. **No** modificar `pc_orders` financiero, `pc_order_lines`, `pc_stock_commitments`, `pc_order_adjustments`, dinero, pago, estado E-01, cumplimiento, stock, cita ni reembolso externo por cerrar.

**R8-26 ajuste aprobado:** al enviar `affected_units` se exige además `expected_case_version`, caso físico parcial del **mismo** pedido/línea/instalación y todavía `open_review` creado legítimamente por R8-69, versión y cantidad original solicitada. No aceptar cambio unilateral de cantidad del expediente original; nuevas condiciones requieren nuevo acuerdo/expediente. En **una sola transacción** con locks `pc_orders → pc_order_lines → pc_cases → stock/ledger` y deduplicación, aplicar disminución a compromiso pendiente y registro financiero histórico, escribir `manual_case_adjusted` y finalizar caso como `closed_adjusted` con `resolved_at` UTC y `row_version+1`. Si cualquier operación falla, rollback integral: ni ajuste sin cierre ni cierre sin ajuste. **No** interpretar `closed_adjusted` como devolución acreditada; R8-27 registra resultado externo por separado. Se conserva el número de **39 tablas** y no se introduce índice nuevo en este corte; la serialización por fila de pedido garantiza no duplicar casos abiertos cuando se materialicen las transacciones.

### 4.31 pc_cases

**Función:** Expedientes de incidencias, cancelación excepcional, devolución y gestiones manuales.

**Columnas mínimas:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED N; order_line_id BIGINT UNSIGNED N; case_type VARCHAR(40) NN; source_operation_id BIGINT UNSIGNED N; case_phase VARCHAR(32) NN; reason_code VARCHAR(40) NN; created_by BIGINT UNSIGNED N; assigned_to BIGINT UNSIGNED N; evidence_snapshot LONGTEXT N; opened_at DATETIME(6) NN; resolved_at DATETIME(6) N; row_version BIGINT UNSIGNED NN.

**Índices:** IX(order_id,opened_at,id); IX(case_type,case_phase,opened_at); IX(order_line_id); IX(source_operation_id,opened_at); UQ(case_type,source_operation_id) para caso técnico con operación fuente no NULL (múltiples NULL permitidos).

**Restricciones:** Expediente separado no agrega estados E-01; no ejecutar cancelación, reintegro o restock desde un cambio informal de fase.


### 4.32 pc_privacy_requests

**Función:** Solicitud ARCO/privacidad con verificación y retención legal.

**Columnas mínimas de diseño D-191/D-192:** id BIGINT UNSIGNED PK; applicant_reference LONGTEXT NN; request_snapshot LONGTEXT NN; request_type VARCHAR(24) NN; received_channel VARCHAR(32) NN; received_at DATETIME(6) NN; identity_evidence_ref VARCHAR(500) N; evidence_manifest LONGTEXT N; verified_at DATETIME(6) N; due_at DATETIME(6) N; resolved_at DATETIME(6) N; response_channel VARCHAR(32) N; response_evidence_ref VARCHAR(500) N; result_reference LONGTEXT N; legal_basis_code VARCHAR(60) N; row_version BIGINT UNSIGNED NN.

**Índices:** IX(request_type,received_at,id); IX(due_at,resolved_at).

**Restricciones D-198:** `evidence_manifest` privado vincula referencias de identidad/representación/resolución al expediente ARCO, sin copiar DNI/imagen por defecto ni exponer archivos con una URL o folio. R8-68 solo transmite evidencia del mismo caso a A bajo `perucommerce_privacy_manage`, finalidad y auditoría; no atribuir legitimación por conocer referencia. Plazos, bases y conservación según J-04; no purgar ventas/Libro por solicitud; admitir comprador solo teléfono sin crear cuenta.


### 4.33 pc_external_documents

**Función:** Referencias de comprobantes SUNAT/ajustes emitidos externamente.

**Columnas mínimas de diseño D-191/D-192:** id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NN; case_id BIGINT UNSIGNED N; source_document_id BIGINT UNSIGNED N; idempotency_id BIGINT UNSIGNED NN; issuer_tax_regime VARCHAR(32) N; document_kind VARCHAR(32) NN; series VARCHAR(30) N; number VARCHAR(64) N; issued_at DATETIME(6) N; external_reference VARCHAR(255) N; event_dedup_hash BINARY(32) NN; status_note VARCHAR(32) N; amount_cents BIGINT N; recorded_by BIGINT UNSIGNED NN; recorded_at DATETIME(6) NN.

**Índices lógicos:** UQ(idempotency_id); UQ(event_dedup_hash); IX(order_id,recorded_at,id); IX(document_kind,series,number). Una referencia incompleta/NULL no se acredita como documento verificado ni se deduplica mediante un UQ de campos nullable.

**Restricciones:** No emisora SUNAT, no firma ni envío CPE. Tipos y datos fiscales sujetos a J-03; no forzar RUC/DNI universalmente. `source_document_id` correctivo y `idempotency_id` deben corresponder al mismo comercio/pedido dentro de su transacción autorizada; referencias ambiguas quedan como expediente pendiente y no se registran como verificadas.


### 4.34 pc_policy_versions

**Función:** Versiones inmutables de condiciones informadas en checkout.

**Columnas mínimas:** id BIGINT UNSIGNED PK; policy_kind VARCHAR(24) NN; version_code VARCHAR(40) NN; body_snapshot LONGTEXT NN; content_hash BINARY(32) NN; effective_from DATETIME(6) NN; created_at DATETIME(6) NN.

**Índices:** UQ(policy_kind,version_code); IX(policy_kind,effective_from).

**Restricciones:** Pedido conserva ID de política/privacidad vigentes y evidencia de información; la tabla es **solo histórico inmutable** y NO mecanismo CAS de configuración mutable. Cambio de política no modifica acuerdos anteriores.


### 4.35 pc_audit_log

**Función:** Registro inmutable de autor, operación, resultado y evidencias filtradas.

**Columnas mínimas:** id BIGINT UNSIGNED PK; entity_type VARCHAR(32) NN; entity_id BIGINT UNSIGNED NN; operation_code VARCHAR(48) NN; actor_kind VARCHAR(20) NN; actor_user_id BIGINT UNSIGNED N; request_id BINARY(16) N; reason_code VARCHAR(40) N; safe_before_hash BINARY(32) N; safe_after_hash BINARY(32) N; occurred_at DATETIME(6) NN.

**Índices:** IX(entity_type,entity_id,occurred_at,id); IX(actor_user_id,occurred_at).

**Restricciones:** Nunca incluir OTP, tokens, credenciales, datos sensibles completos ni payloads privados de proveedor; cambios como eventos, no UPDATE/DELETE.


### 4.36 pc_migrations

**Función:** Registro versionado de estructura y estado de instalación.

**Columnas mínimas:** version VARCHAR(50) PK; checksum BINARY(32) NN; applied_at DATETIME(6) NN; plugin_version VARCHAR(40) NN; migration_result VARCHAR(24) NN; diagnostic_code VARCHAR(50) N.

**Índices:** IX(applied_at).

**Restricciones:** No marcar exitosa una migración parcial; escrituras de cambios estructurales en instalación/actualización solo tras autorización, respaldo y bloqueo de operaciones incompatibles.

### 4.37 pc_schedule_revisions

**Función:** fila estable de **control de versión del horario semanal** del recurso único, distinta de días de bloqueo por cita. No crea otro recurso ni otra agenda.

**Columnas mínimas (DB-A01/02):** resource_id BIGINT UNSIGNED PK (ancla estable del recurso único); row_version BIGINT UNSIGNED NN; active_revision_no BIGINT UNSIGNED NN; timezone VARCHAR(64) NN; updated_at DATETIME(6) NN; updated_by BIGINT UNSIGNED NN.

**Índices:** PK(resource_id). Una fila ancla persistente por recurso (inicializada desde migración idempotente); `active_revision_no` apunta a revisión de reglas válidas sin borrar las previas. Las actualizaciones usan `row_version` CAS bajo transacción, guardan todas las ventanas de la nueva revisión y promueven versión de forma atómica. Las citas existentes se protegen con locks de `pc_calendar_days` sin moverlas automáticamente. Para una vigencia futura se conserva `valid_from` por regla y se elige revisión efectiva por fecha local; no activar prematuramente una revisión futura.

**Restricciones:** O/A cuando PER-19 y D-176 lo permiten; se almacena efectividad desde una fecha. Las pausas pueden representarse como varias ventanas semanales no solapadas y el servicio conserva sus propios buffers. La política horaria no edita el calendario histórico.

### 4.38 pc_case_events

**Función:** historial **inmutable** de apertura, propuestas y resoluciones de casos de logística, digitales y expedientes comerciales parciales D-199/D-200; `pc_cases` conserva su resumen y versión, pero no reemplaza la evidencia cronológica.

**Columnas mínimas:** id BIGINT UNSIGNED PK; case_id BIGINT UNSIGNED NN; idempotency_id BIGINT UNSIGNED NN; event_code VARCHAR(48) NN; decision_code VARCHAR(64) N; actor_kind VARCHAR(20) NN; actor_user_id BIGINT UNSIGNED N; evidence_ref VARCHAR(500) N; safe_payload_snapshot LONGTEXT N; occurred_at DATETIME(6) NN.

**Índices:** UQ(idempotency_id) por evento mutador; IX(case_id,occurred_at,id); IX(actor_user_id,occurred_at). `actor_kind` es guest/staff/system, con actor_user_id obligatorio solo para staff y NULL legítimo para invitado/sistema; nunca inventar ID de usuario WP. Cada cambio de fase y decisión escribe evento + resumen de `pc_cases` atómicamente, con PII mínima. Nunca almacena secreto de cliente o texto íntegro del Libro en una notificación operativa.

**Restricciones:** la resolución logística R8-57 solo decide **expediente**; confirmar entrega, cancelar línea, restock o registrar dinero sigue una operación E-08 distinta con autorizaciones y locks propios. Casos digitales no disparan refund ni revocación automática.

### 4.39 pc_digital_assignments

**Función:** vincular cada variante digital vendible y cada **slot de archivo** con su **versión activa para nuevas ventas**, sin modificar archivos previamente vendidos.

**Columnas mínimas:** id BIGINT UNSIGNED PK; sellable_id BIGINT UNSIGNED NN; slot_key VARCHAR(100) NN; active_file_id BIGINT UNSIGNED NN; row_version BIGINT UNSIGNED NN; updated_at DATETIME(6) NN; updated_by BIGINT UNSIGNED NN.

**Índices:** UQ(sellable_id,slot_key); IX(active_file_id). Los `pc_digital_files` identifican binarios privados inmutables; `pc_download_rights` retiene `file_id` fijo por venta y se mantiene vigente aunque cambie `active_file_id`.

**Restricciones:** cambiar archivo activo requiere nueva fila de binario privado y CAS en esta asignación; nunca `UPDATE` destructivo del archivo histórico ni redirigir tokens previos al nuevo fichero. Sin almacenamiento privado verificado, no habilitar venta digital.

**Subcontratos físicos propuestos P0-18 + legal (2026-11-17):** `docs/36-RESOLUCIONES-PROPUESTAS-DB-A01-A16-P0-18-V1.md` propone 16 tratamientos específicos de DB-A y 20 escenarios futuros, **no cambios SQL ejecutados ni aprobados**. `docs/35-CONTRATOS-LEGALES-PERMISOS-DTO-P0-23-P0-25-V1.md` vincula plazos/canales del Libro, ARCO y registros externos a LEG-01…10. Mantener las **39 tablas propuestas**, sin aumentar su número para resolver CAS/settings o expedientes por inferencia. No asumir que versionado de políticas equivale a CAS de WordPress options; revisar engine/cache antes de implementación.

### Evidencia legal binaria y constancia imprimible (D-198; sin tabla nueva)

**Persistencia del contrato R8-67/68:** `pc_complaints.evidence_manifest` y `pc_privacy_requests.evidence_manifest` son manifiestos **protegidos y versionados** con `evidence_ref` opaco (URL-safe, 16…128 caracteres), propósito, hash íntegro, instante, tipo MIME validado, nombre seguro, identificador interno del expediente/instalación y clave privada de almacenamiento **nunca expuesta al navegador**. No forman otra tabla ni permiten referencia a otro caso; cada modificación del manifest se serializa con `row_version`/auditoría y mantiene historial de evidencias. El binario está fuera de webroot o con denegación HTTP comprobada; `pc_audit_log` registra solo digest de referencia/actor/instalación/finalidad/resultado sin PII ni binario. No se añade una API pública de subida de adjuntos: evidencia del consumidor en V1 es información textual estructurada o procedente de un canal legítimo revisado por el administrador; se podrán leer solo binarios cuya procedencia privada esté verificada. Si almacenamiento no es privado, **no activar anexos/expedientes binarios ni presentar evidencia como accesible**, sin enlaces directos alternativos. Se requiere `private,no-store`, `nosniff`, `Content-Disposition: attachment`, rechazo de `path traversal`, MIME controlado y comprobación de referencia contra el caso **en cada GET**, no una vez al listar.

**Constancia R8-15:** el servidor guarda el snapshot original `merchant_snapshot/complainant_snapshot/complaint_snapshot` y responde con `ComplaintReceiptCopy` imprimible en el mismo POST autenticado por contexto de formulario público sin cuenta. Los reintentos con `idempotency_key` y cookie antiabuso same-origin no generan otro folio; ninguna búsqueda por folio o GET anónimo de PII se añade. Se exige mecanismo de atención del comerciante para recuperar una constancia cuando el navegador ya no conserva contexto, verificado según legalidad del canal; no inventar correo obligatorio. No usar logs/CDN/analítica de terceros para almacenar la copia.

### Proyección de expedientes protegidos LEG-04/05 — diseño lógico D-189/191 sin DDL

**D-189/D-198 ratifican GET privados R8-65/66 para detalle y R8-67/68 para evidencia binaria**, con capability legal, instalación y finalidad. La pantalla WordPress del docs/35 §3 es antecedente **descartado**. R8-41/43 conservan resúmenes y R8-42/44 las mutaciones. OpenAPI 0.4.0-draft incluye las dos consultas sin agregar rol ni facultad del operador.

- **Libro `pc_complaints` (DB-A10/LEG-01…04):** los snapshots `complainant_snapshot`, `complaint_snapshot` y `response_snapshot` ya están **propuestos** como origen privado; faltan plazos hábiles `due_at`, evidencia de canal y recepción, snapshot del proveedor y alcance/propiedad del establecimiento. Debe separarse el folio visible de las claves internas y del acceso al expediente; no entregar snapshots íntegros a R8-41.
- **ARCO `pc_privacy_requests` (DB-A11/LEG-05/06/10):** `applicant_reference`, `request_type`, `received_at`, `verified_at`, `due_at`, `resolved_at` y `result_reference` **no bastan** para reconstruir siempre una solicitud original, legitimación y fundamento. Propuesta física pendiente: referencia privada a solicitud/evidencia original, alcance de instalación/sujeto, canal, decisiones y restricciones de conservación; evitar guardar copia de documento de identidad por defecto. No asumir que `applicant_reference` prueba verificación.
- **Auditoría `pc_audit_log` (E-07):** considerar evento de consulta de expediente con `entity_type`/`entity_id`, actor, motivo/finalidad, hora y resultado filtrado en estructura ya propuesta. **No** guardar texto, identidad completa ni binario en el log; ratificar catálogo de `operation_code` y duración de conservación antes de materializarlo.
- **Seguridad:** autorización por actor A, capability concreta, instalación y expediente; salida sin caché, sin URL pública a evidencias; el sistema de WordPress/hosting/objeto debe verificarse posteriormente. R8-41/43 no conceden acceso al detalle porque un ID sea conocido.

**Estado:** manifest privado y constancia ya especificados como diseño D-198. Continúan **39 tablas**; nuevas columnas son declaraciones para el DDL futuro, sin SQL creado ni pruebas de almacenamiento privado, accesibilidad, legalidad por comercio o habilitación de Libro/ARCO.

### Configuración mutable con CAS obligatorio (DB-A12, sin tabla nueva)

**Contrato técnico seleccionado dentro de las 39 tablas:** usar una opción dedicada `wp_options` de plugin con `autoload` desactivado, un único valor serializado que contiene **`row_version` entero** y configuración no secreta o referencia segregada a secretos; cambio sensible solo mediante escritura condicional de base de datos que compara **la versión y representación anterior exacta**, dentro de una transacción compatible con la auditoría `pc_audit_log`. **No utilizar `update_option()` como sustituto de CAS**, ni confiar en lecturas cacheadas para decidir conflictos. Comparación debe ser binaria/determinista para no equiparar valores distintos por collation; evaluar 1 fila afectada como éxito, cero como conflicto 409, y más de 1 como error crítico. Invalidar correctamente caché de opción, `alloptions` y cachés persistentes tras commit; comparar versión de lectura autoritativa en cada nueva escritura crítica. **Nunca** registrar secret/old/new option value en auditoría ni exponerlo en GET. Si `wp_options`, cache, auditoría o motor no pueden dar garantías de CAS atómico y coherencia entre procesos, **bloquear configuración/rotación de credenciales y activación de MP** y exigir revisión explícita de diseño; no improvisar una tabla 40 ni degradar a last-write-wins. El DDL y el comportamiento de caché deben verificarse con permisos de ejecución posteriores.

## 5. Índices y restricciones transversales

1. Definir cada `UQ` como índice único/PK física del DDL tras comprobar tipos, collation, claves nullable y longitud máxima. Cuando un campo opcional externo sea NULL, **no deducir unicidad para filas incompletas**; el `event_dedup_hash` no nullable es la deduplicación universal.
2. Relaciones financieras: un evento externo solo se acredita una vez por **cuenta + ambiente + evento/recurso**, y un reintegro comprobado una sola vez por **cuenta + cobro fuente + referencia externa inequívoca**. Si la referencia no es inequívoca → expediente manual sin contabilizar como refund probado. El `event_dedup_hash` incluye esas dimensiones.
3. No confiar en `UNIQUE(resource_id,starts_at)` para evitar solapamiento: **bloquear `pc_calendar_days` y buscar intervalos [inicio, fin)** (buffers incluidos). Para día vacío crear primero fila estable por `UQ(resource_id,local_date)`.
4. Al vencer `pc_holds`, excluir de disponibilidad al evaluar `expires_at <= UTC_now` dentro de la operación crítica, **aunque WP-Cron aún no los haya marcado VENCIDA**. No renovar ni reanimar.
5. Disponibilidad con inventario controlado: `stock_on_hand - compromisos_restantes - holds_vigentes - admisiones_efectivas`, siempre después de lock de variante. La transición de hold→compromiso y las salidas de stock evitan el doble conteo. Si el negocio desactiva inventario, no imponer cero artificial; si tiene cita, sí bloquear recurso.
6. Pueden existir índices adicionales por queries reales y límites de consultas; no agregar índices redundantes por costumbre. Nombre de índice limitado a compatibilidad MySQL/MariaDB; `dbDelta()` tiene formato y limitaciones que deben considerarse.
7. Referencias a WordPress users/posts validadas por ID en la misma instalación; idempotencia de peticiones externas basada en hashes canónicos y claves persistidas, jamás solo en cookies PHP.

## 6. Límite de transacciones por operación

| Operación | Locks persistentes en orden estable | Escrituras atómicas | I/O externo |
| --- | --- | --- | --- |
| Cotizar | Ninguno (lectura no vinculante) | Sin hold ni pedido | Sin llamadas críticas obligatorias |
| Checkout MP fase A | Clave `pc_idempotency`, variantes ordenadas, días recurso ordenados | Guardar en `pc_idempotency` la admisión, la cuenta/ambiente, clave MP y referencia externa estables, además de contexto comercial mínimo reconstruible (cotización congelada, líneas, importe PEN y versiones informadas). No crear pedido E-01 ni intento MP E-01 antes de éxito válido | **Ninguna mientras SQL esté abierta** |
| Checkout MP fase B | Después de POST/consulta externa: operación + pedido + variantes + días | Solo cuando se comprueba orden externa válida y recursos admisibles: pedido E-01 PENDIENTE_PAGO, intento CREADO/estado verificado, snapshot pactado y holds VIGENTE durante 15 min desde intento válido. Si falla, conservar fase técnica y conciliar; jamás publicar checkout_url sin hold | POST/GET MP **fuera** de SQL |
| Contraentrega | Idempotencia, pedido, variantes, días | Pedido CONFIRMADO, líneas, compromiso de stock/cita, obligación de cobro pendiente, outbox/audit | Ninguna para confirmar recursos |
| Webhook MP | Persistir inbox tras validar firma; luego pedido + intento + recursos | Estado MP acreditado verificado por GET, movimientos únicos, conversión de recursos, outbox | GET autoritativo **fuera** de SQL |
| Vencimiento | Pedido, variantes, días según recurso | Marcar vencida/liberada si procede; no modificar order MP del proveedor | Ninguna para liberar; conciliación posterior |
| Efectivo real | Idempotencia + pedido + movimiento | Asiento neto aplicado, historial/avisos sin repetir | Ninguna |
| Cancelación / ajuste | Pedido/línea + variantes/días afectados + operación financiera | Estado elegible, liberaciones únicas, ajuste histórico, caso y auditoría | Reembolso **siempre externo al plugin** |
| Reprogramar servicio | Pedido/línea + `pc_calendar_days` de **ambos** intervalos ordenados | Verificar nueva franja, cambiar cita, guardar historia y outbox | Ninguna mientras SQL abierta |
| Registrar reintegro | Idempotencia + pedido + cobro original + evento | Comprobar origen/importe/acumulado y escribir evento externo verificable único | Comprobación documental/proveedor fuera de SQL |

**Orden general de locks propuesto:** primero fila de operación idempotente; luego pedido existente si lo hay; luego filas de variantes en orden numérico; luego filas de recurso/día ordenadas; finalmente los objetos auxiliares que esas operaciones controlan. Cualquier operación administrativa de stock o agenda usará el mismo orden relativo. **PENDIENTE en DDL/implementación:** consulta de bloqueos exacta, aislamiento, deadlocks/reintentos acotados y tolerancia de host.

**Aislamiento:** requisito contractual es exclusión mutua observable, no un nivel SQL adivinado. Se propone `READ COMMITTED` con bloqueos explícitos de filas estables y control de versión, **sujeto a compatibilidad verificada**. MySQL InnoDB usa habitualmente `REPEATABLE READ` por defecto; no asumir que una lectura sin lock funciona igual ni que SQL de rango evita phantoms. Nunca realizar HTTP con transacción abierta.

**Contrato documental P0-26 (2026-11-17):** `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md` concreta 9 fases L, 12 checks LC, 14 incidentes F26 y 20 escenarios ZIP-X. Los índices, FK y migraciones de las 39 tablas siguen propuestas tras DB-A01…A16; no DDL ejecutable ni restore verificado. WordPress no invoca hook de activación durante cada update; usar verificador separado de versión DB al implementar cuando se autorice.

## 7. Migraciones versionadas, instalación, respaldo y reversión (sin ejecución)

- La primera instalación creará el esquema por fases verificables: infraestructura/versiones → catálogo/ventas → inventario/agenda → integración/idempotencia → derechos/privacidad → auditoría. Registrar resultado `pc_migrations` **solo cuando la fase se haya aplicado y comprobado**.
- **No asumir DDL transaccional:** cambios estructurales pueden realizar commit implícito; mantener un journal de migración, plan de reintento y bloqueo de operaciones críticas hasta consistencia. No usar un rollback SQL como promesa general de revertir DDL.
- Antes de upgrade: copia consistente de base, almacenamiento digital privado y configuración protegida, versiones de plugin y esquema; procedimiento de restore debe mantener integridad referencial y permisos.
- Backward compatibility: si migración cambia representación o constraints, definir preflight/expansión/migración de datos/verificación/activación y estrategia de corte. No alterar snapshots históricos ni aceptar escrituras con esquema incompleto.
- Revertir solo ZIP **no revierte BD**. Reversión segura = restaurar copia compatible, revisar órdenes/cobros externos ocurridos luego del backup y conciliar antes de reabrir operaciones.
- WordPress desinstala plugin de forma no destructiva por defecto según D-146/D-175; borrar datos retenidos legalmente solo tras revisión, autorización y procedimiento independiente.

## 8. Retención y datos personales: no equiparar TTL técnico a plazo legal

- Enlace invitado inicial: **90 días**; código OTP: **10 minutos / 5 intentos**; token de descarga digital: **24 horas**; hold MP: **15 minutos desde intento válido**. Esos plazos no sustituyen conservación tributaria, de Libro, privacidad ni evidencia de pago.
- Expiración de token no borra derecho comprado; expiración de hold no cancela la orden del proveedor; inactividad de usuario no justifica purga de pedido. Secretos en BD siempre hashes verificables.
- **D-190 ratifica** admisión de 60 s, sesión canjeada de 30 min de inactividad bajo enlace de 90 días y grant de 5 min/un uso. Retenciones de idempotencia, inbox/outbox, logs y expedientes dependen de operación financiera y obligaciones legales J-04/J-05 por comercio; **no se fijan como TTL de sesión ni se purgan mientras haya reconciliaciones, disputas o deber de conservación**. No usar cron puntual para expiración efectiva ni restaurar credenciales revocadas al recuperar backup.
- Datos personales en snapshots, archivos, avisos y evidencias: cifrado/acceso/expurgo por política de instalación y obligaciones aplicables, sin telemetría central del desarrollador.

## 9. Matriz de escenarios de aceptación futura — NO EJECUTADOS

| ID | Caso | Invariante que debe poder comprobarse |
| --- | --- | --- |
| DB-X01 | Dos compradores consumen última variante simultáneamente | Solo una asignación autorizada si stock controlado |
| DB-X02 | Dos solicitudes seleccionan mismo intervalo de un día sin citas | Fila recurso/día evita doble cita |
| DB-X03 | Servicio cruza medianoche y solapa con bloqueo en segundo día | No confirma cita inconsistente |
| DB-X04 | MP timeout tras crear order externa | Mismo idempotency key, conciliación; sin segundo cargo |
| DB-X05 | Webhook duplicado y fuera de orden | Una acreditación y sin degradar APROBADO |
| DB-X06 | Hold caduca sin WP-Cron | Nueva operación ve capacidad disponible correctamente |
| DB-X07 | Pago tardío tras hold vencido y stock agotado | Incidencia y reembolso externo/gestión manual; no sobreventa |
| DB-X08 | Contraentrega mixta con digital aún impago | Digital permanece BLOQUEADO; cita y stock comprometidos una vez |
| DB-X09 | Dos empleados registran mismo cobro efectivo | Una sola operación neta acreditada |
| DB-X10 | Cancelación y entrega simultáneas sobre misma línea | Solo una transición legal con recursos consistentes |
| DB-X11 | Dos reprogramaciones de una cita en paralelo | Una cita efectiva; historial intacto |
| DB-X12 | Reembolso externo parcial duplicado | Evento único y acumulado reconciliable |
| DB-X13 | ZIP sustituido y migración parcial | Checkout bloqueado, plan de recuperación, sin falsear versión |
| DB-X14 | Comprador solicita enlace y cambio sensible por token filtrado | Enlace solo lectura; exige OTP o revisión manual |
| DB-X15 | Backup restaura BD anterior a pago efectivo externo | No crea nuevo cobro antes de consultar/conciliar proveedor |
| DB-X16 | Desinstalación | No purga historial legal automáticamente |

Los escenarios son **criterios documentales**, NO resultados de pruebas ni autorizaciones para ejecutar SQL/servicios.

**Auditoría posterior P0-18:** `docs/28-AUDITORIA-ESTATICA-MODELO-FISICO-P0-18-V1.md` examina las 39 tablas y propone correcciones DB-A01…DB-A16 para una futura ratificación de DDL. `docs/29-COMPATIBILIDAD-WORDPRESS-HOSTING-P0-19-V1.md` verifica requisitos oficiales y soporte genérico Hostinger, sin acceso a instalación real. No se generó SQL ejecutable ni se corrieron migraciones.

## 10. Incertidumbres y puerta técnica restante

**Cubierto en diseño:** inventario de entidades/columnas/nulabilidad/índices candidatos, relaciones, estados, locks, concurrencia, migraciones, recuperación, privacidad y casos de aceptación. El esquema aquí todavía es una **propuesta de estructura**, no un DDL definitivo aprobado.

**Cierre de decisiones del modelo:** DB-A01 (revisión/ventanas), DB-A03 (una cita por pedido), DB-A08 (archivo digital inmutable por vendible), DB-A12 (CAS de opción dedicada), DB-A16 (TTL D-190) quedan especificados en este documento y docs/36/38. **Trabajo que solo puede ocurrir con código y autorización:** materializar DDL/migraciones, probar PK/FK/colisiones/MySQL-MariaDB/InnoDB y aislamiento, verificar caché de opciones y transacciones, comprobar hosting, seguridad de evidencias, retención legal por comerciante y backup/restore. Si falla, bloquear el módulo, no convertir la limitación de ejecución en decisión de producto abierta ni fingirla validada.

**Contrato relacionado P0-23 documentado posteriormente:** `docs/24-MATRIZ-REST-PERMISOS-DTO-P0-23-V1.md` y `docs/openapi/peru-commerce-v1.openapi.draft.json` (borrador OpenAPI 3.1 parcial). Sus requisitos definitivos de DTO/campos pueden exigir ajustes al ER/DDL antes de aprobación; no se autoriza implementar, compilar ni probar. La especificación de REST podrá exigir ajustes mínimos a estas columnas, siempre documentados; no se autoriza implementar, compilar o probar por aprobar esta propuesta.

**Extensión ratificada D-176…D-187:** `docs/27-COBERTURA-OPERACIONES-P0-23-PROPUESTA-V1.md` registra 12 operaciones incorporadas como R8-53…R8-64 al OpenAPI principal; el archivo `coverage-C23.PROPOSAL` es histórico. El anexo 12 de este documento amplía el diseño desde **36 a 39 tablas propuestas** para horarios, expedientes y asignaciones de archivos. **No ejecutar SQL ni tratar el modelo como verificado en hosting** hasta cerrar P0-18/P0-19/P0-26.

## 11. Conciliación declarativa P0-23 ↔ P0-18 ↔ J-01…J-05 (2026-11-17)

**Estado:** revisión estática documental de interfaces y persistencia. OpenAPI `docs/openapi/peru-commerce-v1.openapi.draft.json` enumera **67 operaciones REST/64 rutas/134 DTO** en versión 0.8.0-draft; el inventario 63/60/113 es corte histórico C23, campos legales sujetos a J-01…J-05 y seguridad específica por actor; no se ejecutó SQL ni se hizo análisis de compatibilidad del hosting. D-168 aprueba **solo la política**: 36 tablas iniciales ampliadas a 39 tablas documentales propuestas tras C23, no DDL definitivo.

| R8 afectadas | DTO funcionales | Fuente de datos propuesta | Columnas relevantes / materialización | Conclusión de compatibilidad |
| --- | --- | --- | --- | --- |
| R8-01/02/03/31/32/33/34 | PublicProduct/AdminProduct/ProductCreate/ProductPatch | wp_posts + pc_sellables | product_post_id, variant_key, kind, price_cents, sale_price_cents, row_version | Compatible en política, verificar sincronía post+tablas y ausencia de DELETE en historial |
| R8-04/36/37/38 | SlotPage/AgendaView/ClosureCreate/AppointmentReschedule | pc_calendar_days, pc_schedule_rules, pc_schedule_blocks, pc_appointments, pc_appointment_history | resource_id, starts_at_utc, ends_at_utc, row_version | Compatible parcialmente; R8-53/54 ya tienen cobertura D-176/177 para reglas y constancia de servicio, sin DDL probado |
| R8-05/06/07 | QuoteRequest/Response, CheckoutRequest/MP/COD | pc_orders, pc_order_lines, pc_holds, pc_resource_admissions, pc_stock_commitments, pc_idempotency | quote_fingerprint, policy_version_id, privacy_version_id, total_cents | Compatible conceptualmente; política/privacidad presentadas se verifican en servidor, no se aceptan sin comprobar |
| R8-17/29/30 | WebhookNotice/ReconciliationPage/Resolve | pc_webhook_inbox, pc_payment_attempts, pc_payment_events, pc_cases | provider_order_id, environment, payload_digest, event_dedup_hash | Compatible conceptualmente; respuesta del webhook no acredita; vendor payload podría tener campos adicionales |
| R8-22/23/24/25/26/27 | FulfillmentChange/CancelLine/CashReceipt/CashCorrection/FinancialAdjustment/ExternalRefundRecord | pc_order_lines, pc_stock_commitments, pc_payment_events, pc_order_adjustments, pc_audit_log | fulfillment_state, delta_cents, source_payment_event_id, event_dedup_hash | Compatible con restricciones E-01/E-04; necesita transacciones y autorización por historia financiera |
| R8-28 | DeliveryChange | pc_delivery_revisions, pc_orders, pc_cases | previous_revision_id, revision_number, ubigeo_code, fee_cents, approved_at | Compatible; confirmar tarifa/cobertura y aprobaciones sin reescribir original |
| R8-08/09 | GuestSessionExchange/SessionAck/GuestOrder | pc_guest_sessions, pc_orders, pc_order_lines | token_hash, credential_kind, issued_at, expires_at, revoked_at | Compatible en estructura; cookie/secret 90d y canje necesitan transporte físico validado |
| R8-10/11/12 | ChallengeRequest/Verify, ActionGrantResult, GuestActionRequest | pc_otp_challenges, pc_action_grants, pc_cases | code_hash, attempts, purpose, verified_challenge_id, expires_at | Ajuste pendiente: vigencia/CSRF/transporte del grant y auditoría de verificación manual por teléfono |
| R8-13/14/48 | DigitalSessionExchange/Ack, DigitalFileMetadata, stream | pc_digital_files, pc_download_rights, pc_download_tokens, pc_download_events | private_storage_key, token_hash, right_id, mime_type, byte_size | Compatible si almacenamiento real protegido y derecho+token validables; sin soporte streaming comprobado |
| R8-15/41/42 | ComplaintCreate/Receipt/Page/Respond | pc_complaints, pc_outbox, pc_audit_log | sequence_scope, sequence_number, complainant_snapshot, complaint_snapshot, response_snapshot, response_evidence | Ajuste jurídico: formato/correlativo, cálculo de 15 días hábiles y evidencia entrega por J-01 |
| R8-43/44/50 | PrivacyPage/Resolution/ExportAck | pc_privacy_requests, pc_audit_log, pc_cases | applicant_reference, request_type, verified_at, due_at, legal_basis_code, result_reference | Ajuste: evidencia de verificación del titular, decisión y almacenamiento temporal exportación privado |
| R8-45/46/47/49 | AdminSettings/FiscalSettings/SettingsPatch/PaymentCredentialUpdate/Diagnostics | opciones WP protegidas + pc_policy_versions, pc_audit_log | policy_kind, version_code, content_hash, effective_from, row_version en configuración | Ajuste físico: versión/CAS de settings/secretos y conservación; WordPress options no implica por sí misma fila versionada |
| R8-16/51/52 | UbigeoPage/CoverageView/CoveragePatch | pc_ubigeo_versions, pc_ubigeo, pc_delivery_rates | dataset_version, code, fee_cents, row_version | Compatible; tarifa final por distrito backend, instantánea comercial no cambia |
| R8-39/40 | NotificationPage/Resend | pc_outbox, pc_audit_log | event_code, audience_code, channel, attempt_count, next_attempt_at | Compatible; wp_mail TRUE no acredita entrega ni estado económico |
| R8-50 (exportación) | PrivacyExportRequest/PrivacyExportAck | pc_privacy_requests, almacenamiento temporal privado | scope, identity verified, reference, expiry | Ajuste: especificar artefacto de exportación temporal protegido/TTL, sin añadir endpoint público |
| R8-64 ratificada en cobertura D-187 | Registro de documento externo por pedido y correctivo condicionado | pc_external_documents | issuer_tax_regime, document_kind, series, number, issued_at, amount_cents | Cobertura documental aprobada; validación legal/DDL pendiente. R8-46 es solo configuración fiscal |
| R8-53…R8-63 ratificadas en cobertura D-176…D-186 | PER-19/20/27/28/11/41: horario semanal, prestación/no asistencia, incidencia staff y reemisión | pc_schedule_rules, pc_appointments, pc_cases, pc_guest_sessions, pc_download_rights | varios | Rutas documentales aprobadas con límites de actor; DDL/guardas por campo sin ratificar. No inventar set_status genérico |

### Ajustes antes de DDL y aceptación física

1. **No cambiar 36 tablas a ciegas:** la mayoría de campos JSON responde a columnas ya propuestas y algunas respuestas son proyecciones derivadas. Evitar guardar versiones duplicadas del dinero o datos sensibles al añadir DTO.
2. **`pc_orders`:** vincular versión efectivamente informada de política/privacidad y `quote_fingerprint`. `policy_version_id` y `privacy_version_id` existen, pero el backend debe comprobar que corresponden a la versión mostrada al comprador; nunca fiarse del ID aportado por navegador.
3. **`pc_complaints`:** reclamante/detalle/pretensión en snapshots protegidos; el número de constancia deriva de alcance + correlativo. El esquema físico necesita procedimiento de cómputo de días hábiles y evidencia de respuesta verificable; **no** inferir obligación de email/DNI universal hasta ratificar J-01.
4. **`pc_privacy_requests`:** `verified_at` no demuestra cómo se comprobó legitimación; proponer referencia privada de evidencia y protección por finalidad, diferenciando exportación temporal de datos retenidos legalmente, sujeto a J-04/J-05.
5. **`pc_external_documents`:** datos existentes para referencia documental, pero **R8-46 configura régimen y NO graba comprobante por pedido**. R8-64 (D-187) cubre documentalmente el registro externo, solo A y sin emitir CPE; faltan campos legales por tipo y DDL/guardas físicas, sin improvisar nuevas rutas.
6. **`pc_action_grants`:** esquema actual soporta propósito/token hash/expiración, pero no fija duración, transporte HTTP/cookie, anti-CSRF ni manejo de reintento. Se ha propuesto cookie HttpOnly/Secure/SameSite en OpenAPI; requiere decisión y controles antes de hacerla operativa.
7. **`pc_policy_versions` y opciones WP:** para `expected_version` de R8-46/47, definir almacenamiento de versión atómica de configuración y compare-and-swap; no dar por hecho que la tabla de políticas versiona credenciales o settings.
8. **Integridad/vigencia:** precisiones exactas de `BIGINT` y longitud de referencias, FK/índices, charset/collation/longitud de índices, captura de concurrencia y backup/restore siguen subordinadas a P0-19 y P0-26.
9. **Cobertura operacional actual:** PER-20 (servicio), PER-27/28 (logística), PER-11/41 (reemisión/revocación), PER-19 (horario) y registro externo por pedido están cubiertos **en diseño** por R8-53…R8-64 (D-176…D-187), no implementados. PER-09 exportación comercial queda **EXCLUIDA de V1 por D-188**, sin REST. Quedan pendientes DDL, campos, permisos efectivos y verificación; no crear endpoints por inferencia.

**Conclusión:** hay correspondencia declarativa para las operaciones existentes, **no equivalencia física/funcional completa**. Se preservan las decisiones E-01…E-08 y D-167…D-175; los cambios de contrato requieren aprobación antes de cualquier DDL o código.


## 12. Cobertura ratificada C23: ampliación del modelo físico (D-176…D-187)

**Corte 2026-11-17:** la decisión de cobertura autoriza la especificación documental de **R8-53…R8-64**, pero no instala SQL ni convierte en probada la compatibilidad InnoDB/hosting. Se mantienen las 36 tablas originalmente diseñadas y se proponen **3 tablas de soporte imprescindibles** (`pc_schedule_revisions`, `pc_case_events`, `pc_digital_assignments`) para un total **39 tablas documentales propuestas**. No significa que se aprobó la migración ni se ampliaron estados E-01.

| Nueva operación R8 | Persistencia / índice / guarda física | Invariante y riesgo que cubre |
| --- | --- | --- |
| 53 | `pc_schedule_revisions` UQ(resource_id), `pc_schedule_rules` con `revision_no` y `effective_from`, `pc_calendar_days` bloqueados para fechas afectadas | Horarios versionados O/A, múltiples ventanas/pausas sin mover citas existentes |
| 54 | `pc_appointments` añade `outcome_evidence_ref` (referencia privada), `outcome_recorded_by`, `outcome_recorded_at`; `pc_order_lines` estado V-05/06 | Sólo A; prestación/no asistencia verificada, sin dinero automático |
| 55…57 | `pc_cases` resumen + `pc_case_events` historial append-only UQ(idempotency_id), IX(case_id,occurred_at) | Sólo A: apertura/propuesta/resolución de incidencia sin alterar cumplimiento/finanzas por efecto lateral |
| 58 | `pc_guest_sessions` token_hash/issued_at/expires_at/revoked_at; `pc_idempotency` guarda resultado sin secreto | Sólo A, token nuevo 256 bits/90 días y revocación previa atómica; replay idempotente no devuelve enlace en claro |
| 59 | `pc_download_tokens` con hash, right_id, issued_at/expires_at/revoked_at y `pc_download_rights` | Nuevo enlace 24 h rotado sin invalidar derecho, y nunca sin pago |
| 60…61 | `pc_cases`, `pc_case_events`, `pc_download_rights`, `pc_download_tokens` | Sólo A; decisión legítima y revocación con expediente; no ejecutar reembolso |
| 62 | `pc_digital_files` binario privado/archivo por `id` + `pc_audit_log` mínima | Streaming admin solo con finalidad y sin caché/URL pública; sin exponer archivo al operador |
| 63 | `pc_digital_assignments` UQ(sellable_id,slot_key), `pc_digital_files` añade `sellable_id` y `replaces_file_id` para cadena de versiones | Nuevo archivo para futuras ventas; derechos históricos apuntan a file_id previo, sin sobrescribirlo |
| 64 | `pc_external_documents` añade `source_document_id` para correctivos, `idempotency_id`, `issuer_identity_hash` y referencia normalizada opcional deduplicable | Sólo administrador registra documento externo. No genera CPE ni refund; estado de comprobante externo no sustituye verificación SUNAT |

### Contrato de concurrencia propuesto

- **Agenda:** adquirir lock de `pc_schedule_revisions(resource_id)`, determinar fechas locales afectadas, bloquear `pc_calendar_days` ordenadas, comprobar citas confirmadas y crear nueva revisión horaria. No realizar migración de turnos implícita ni bloquear durante una llamada externa. Revisar rangos de fechas, DST y límites de días afectados antes del DDL.
- **Cita:** SELECT FOR UPDATE de `pc_appointments` + línea; comprobar `CONFIRMADA` y evidencia antes de pasar a `REALIZADA` o `NO_ASISTIO`, escribir auditoría y outbox tras persistir hecho.
- **Caso:** idempotencia por operación; lock de `pc_cases` con row_version; insertar `pc_case_events` inmutable y mover solo fase interna del expediente. La decisión **no implica** cobro, restock, revocación ni transición logística colateral.
- **Link/archivo:** para reemisión bloquear pedido o derecho, revocar hashes anteriores, insertar verificador nuevo, escribir auditoría **sin secreto**. El secreto se entrega al admin por HTTPS una vez, nunca en `pc_idempotency.response_ref` ni outbox; retry con misma clave responde resultado sin secreto. Para sustitución digital, bloqueo de asignación activa, insertar binario, CAS en `pc_digital_assignments`; conservar `pc_download_rights.file_id` de ventas previas.
- **Documento externo:** bloquear pedido e idempotencia; registrar una referencia con vínculo `source_document_id` si es correctiva, validar que pertenece a misma instalación, nunca actualizar el original. Cuando el proveedor/serie/número no dé una identidad externa unívoca, guardar expediente de revisión antes de afirmar unicidad. Índice físico definitivo se elegirá con collation/longitudes del hosting comprobado.

### Puertas no resueltas por C23

Las columnas detalladas siguen **pendientes de DDL ejecutable y revisión técnica**: convenciones FK y `dbDelta` WordPress, motores MySQL/MariaDB, índices de expresiones/nullable, semántica de timezone y día de agenda, TTL de admisión/grant, streaming privado, respaldo de archivos/versiones, registro legal por tipo de documento/individuo, y pruebas de seguridad/MP autorizadas. **No declarar P0-18 físicamente cerrado** por haber ampliado el catálogo de columnas.


## Rectificación de correspondencia API ↔ datos (2026-11-18; sin DDL ni tablas nuevas)

**Pre-HTTP MP, E05-D/D-112.** Los campos `pc_idempotency.provider_account_key`, `provider_environment`, `provider_idempotency_key`, `provider_external_reference` y `checkout_context_snapshot` aplican solo a operaciones MP. Se confirman en fase A bajo su clave única, antes de solicitar la order. El snapshot acotado contiene líneas originales, total en céntimos, políticas/privacidad aplicables, modo de entrega, referencias necesarias y la representación exacta de la solicitud al proveedor; no contiene access token, HMAC de webhook, OTP ni URL de seguimiento. Debe cifrarse o protegerse con acceso restringido y política de datos/backup E-07. Se utiliza el identificador técnico `pc_idempotency.id` como raíz de correlación hasta que exista un intento E-01. Si no se puede consultar un recurso externo tras pérdida de respuesta, solo se permite recuperación segura con idempotencia/verificación demostrada por el proveedor o conciliación administrativa; sin nuevo POST a ciegas. Los registros técnicos previos no son un pedido, no confirman cobro y no inician el hold de 15 min. El esquema físico, índice de correlación por proveedor y limpieza legal se revisarán al crear DDL.

**Lecturas y configuración del panel.** `pc_appointments.row_version` alimenta `AgendaItem.row_version`; `pc_schedule_revisions.row_version` alimenta `AgendaView.weekly_schedule.row_version`; `pc_outbox.row_version` aumenta ante cambio técnico y alimenta `NotificationEntry.row_version`; `pc_cases.row_version` alimenta `ReconciliationSummary.row_version`. Toda mutación conserva el control de versión de su recurso. `pc_schedule_rules.weekday` conserva 0=domingo…6=sábado; el DTO REST usa ISO 1=lunes…7=domingo, transformando 7→0. `pc_sellables.service_buffer_before_minutes` y `service_buffer_after_minutes` son enteros de minutos no negativos; validarlos al actualizar el servicio y congelar los valores efectivamente pactados en el snapshot de línea/cita. No mover citas existentes al editar producto.

**Catálogo y archivos privados.** `ProductVariantCreate` no recibe ID; `ProductVariantUpdate` identifica una variante previa y R8-35 sigue siendo la única mutación de cantidad en existencias ya creadas. El stock inicial de una variante nueva genera movimiento auditable en la misma operación de alta. `AdminProduct/AdminVariant` exponen metadatos completos únicamente al empleado autorizado, y `ProductPublic/ProductVariant` no incluyen existencias exactas. R8-48 exige `product_id + sellable_id + slot_key`; bajo lock y comprobación de pertenencia crea `pc_digital_files` y primera `pc_digital_assignments` del slot. Si el slot ya existe usar R8-63, nunca reemplazar de forma implícita. `pc_download_rights.file_id` de ventas previas permanece inmutable.

**Finanzas.** `AdminOrder.financial_movements[].event_id` procede de `pc_payment_events.id` y permite `source_event_id` de R8-25 o `source_payment_event_id` de R8-27 bajo autorización financiera del administrador. `AdminOrder.external_documents` procede de `pc_external_documents`, solo administrador; no es emisión SUNAT. Estos dos campos se OMITEN en la proyección del operador, sin simular permisos desde Angular.

Las 39 tablas siguen siendo **diseño declarativo**. Nuevas propiedades y columnas aquí descritas son corrección del contrato E-05/E-08 y deben revisarse contra SQL/MySQL/MariaDB, WordPress/caché y hosting real al implementar. No se ejecutó DDL ni se verificó concurrencia.

## Proyecciones y vínculo de conciliación incorporados al diseño lógico (18-11-2026)

Las rectificaciones históricas del OpenAPI **0.7.0-draft** no crean tablas nuevas. Su correspondencia física es:

1. **Textos legales y checkout:** `pc_policy_versions` registra filas inmutables de `policy_kind='commercial_terms'` o `'privacy_notice'`, con cuerpo/hash/vigencia. Una opción versionada/no-autoload de `wp_options` guarda **referencias activas**, no reemplaza el histórico; al guardar R8-46, insertar versión si cambió el texto y mover puntero con CAS/auditoría atómicos. R8-45 devuelve los textos/IDs vigentes, R8-01 los publica antes de contacto, R8-05 los vuelve a leer y R8-06/07 los contrasta con el pedido, cuyo `policy_version_id/privacy_version_id` permanece original. Sin CAS seguro o textos vigentes, bloquear checkout/configuración afectada.
2. **Líneas:** `AdminOrderLine.row_version` deriva de `pc_order_lines.row_version` y alimenta R8-22/23/55, nunca de la versión global del pedido. El control de versión de los demás recursos permanece separado.
3. **Archivos y derechos:** `AdminDigitalSlot` proyecta `pc_digital_assignments.sellable_id/slot_key/active_file_id/row_version`; R8-31/32/33/34 lo incluye solo para A con catalog_edit Y digital_files_manage. R8-48 crea archivo y primera asignación de manera coherente y devuelve esa versión; R8-63 exige archivo activo y CAS del slot y retorna nueva versión. `AdminDigitalRight` lee `pc_download_rights` y el pedido propio para R8-59/60, solo A con orders_read Y digital_files_manage. Un cambio de versión futura no altera `pc_download_rights.file_id` histórico ni devuelve tokens.
4. **Casos de pedido:** `AdminCaseSummary` combina `pc_cases` y el último `pc_case_events` para A con capability del caso/lectura de pedido. R8-55/56/57/61 devuelve nueva versión del caso. Resolver el caso no realiza por sí solo ajustes, refund ni cumplimiento físico.
5. **Caso MP anterior al pedido:** `pc_cases.source_operation_id BIGINT UNSIGNED NULL` vincula al `pc_idempotency.id` previo a HTTP. Para un `case_type` técnico MP con `order_id=NULL`, debe existir `source_operation_id` que permita reconstruir cuenta/ambiente/correlación **sin exponer credenciales**. `UQ(case_type,source_operation_id)` evita duplicación para la misma operación/tipo. Un proceso de recuperación identifica operaciones MP ambiguas desde `pc_idempotency.technical_phase` y crea o recupera idempotentemente el caso bajo transacción/lock si PHP cayó antes de registrarlo. R8-29 puede listar caso con `source_operation_id` y **sin `order_id`**; R8-30 usa `pc_cases.id/row_version`, nunca inventa pedido, segundo POST ni pago acreditado por señal no autoritativa. FK y comportamiento SQL se revisarán al crear DDL.
6. **Avisos:** `NotificationEntry.resource_type/resource_id` deriva de `pc_outbox.entity_type/entity_id` solo cuando el actor realmente pueda ver el recurso; O no recibe referencias para expedientes reservados.

**Verificaciones que faltan por naturaleza:** DDL/índices/FK, transacciones CAS y caches WP, host real, concurrencia e integración MP; no se ejecutaron pruebas, SQL ni servicios. El inventario sigue en **39 tablas**.

## Persistencia de recorridos cerrados para OpenAPI 0.8.0-draft (18-11-2026)

Sin tabla nueva (39 lógicas), ni SQL materializado. Las proyecciones son lecturas de datos ya previstos, con una corrección de nulabilidad/actor de eventos de caso:

- **Casos:** `pc_cases.evidence_snapshot` captura de forma inmutable la solicitud inicial tipada o se obtiene del primer `pc_case_events.safe_payload_snapshot`; en R8-12 contiene propósito, motivo, UBIGEO, nueva dirección y detalle mínimos, con protección de PII. El campo original nunca se sustituye al cambiar `case_phase`. R8-21 con `case_id` filtra por `pc_cases.order_id`, `pc_order_lines` del mismo pedido e instalación y finalidad de A. `AdminCaseDetail.original_request` es la solicitud original y `events` se pagina desde `pc_case_events` por (occurred_at,id), hasta 50 por respuesta (`case_events_page`). En R8-21 sin `case_id` solo se muestran resúmenes. No exponer a O, ni al invitado, contenido del expediente. No confundir caso comercial de pedido con Libro/ARCO que tienen GET propios. Si el caso heredado carece de historia requerida, nunca fabricar solicitud original ni aprobar ciegamente.
- **Origen del evento:** `pc_case_events.actor_kind` identifica `guest|staff|system`; `actor_user_id` es **NULL para invitado/sistema** y obligatorio para personal WP. No atribuir usuarios falsos a una solicitud pública R8-12. `UQ(idempotency_id)` por evento permanece; primer evento y caso deben entrar en la transacción de R8-12.
- **Condiciones pactadas:** R8-09/21 une `pc_orders.policy_version_id/privacy_version_id` con `pc_policy_versions.id` para devolver `OrderAgreedNotices` (texto/versiones de la compra ORIGINAL). Nunca reemplazar por textos actualmente activos en `wp_options`. Solo pedido propio invitado o pedido autorizado para A/O; proteger contra IDOR.
- **Cuota digital D-32:** la opción comercial versionada/CAS en `wp_options` incluye `digital_download_limit` nulo o entero positivo para derechos FUTUROS. En la creación del derecho, almacenar valor efectivo en `pc_download_rights.optional_limit` (`NULL` = ilimitado); `download_count` se incrementa bajo lock y autorización al iniciar transferencias válidas, antes del I/O externo; no se cobra una solicitud denegada ni se cambian límites históricos por editar settings. Paralelismo se serializa sobre derecho para no superar cuota; 0 inválido en configuración. Mantener token 24h independiente.
- **Teselas:** `map_tiles` se conserva en opción WordPress protegida bajo versión CAS; campos `enabled/tile_url_template/attribution_text/attribution_url` con validación HTTPS, host permitido, atribución y licencia antes de activación. La proyección pública R8-01 carece de claves y **no solicita tiles por sí sola**. La configuración se registra sin tabla ni token de proveedor en frontend; si falta seguridad/legalidad solo el mapa queda inhabilitado, no el selector UBIGEO/dirección manual.
- **R8-12 idempotente:** `pc_idempotency` registra `operation_type='guest_case_request'`, `scope_hash` por sitio/pedido/guest_session/purpose, `key_hash` de clave recibida y `request_hash` del JSON canónico; `entity_type='case'`, `entity_id=pc_cases.id`. Con lock y transacción breve, consumir `pc_action_grants.consumed_at`, insertar caso + evento y resultado recuperable. Un replay auténtico desde la **misma sesión válida** verifica scope/hash y devuelve case_id previamente guardado sin requerir un segundo consumo; nunca devuelve grant/token/cookie en respuesta persistida ni reanima permiso, y deniega reintentos ajenos. No borrar idempotencia mientras la operación necesite recuperación; política de retención conforme E-05/E-07.
- **Digital R8-63:** `expected_version` del body = `pc_digital_assignments.row_version` que el panel conoce como `AdminDigitalSlot.assignment_row_version`. No es la versión del binario `pc_digital_files`, que sigue inmutable.

Estas precisiones NO implican DDL probado, motor disponible, configuración real o acceso a datos de un comerciante. Se conserva CAS de `wp_options` fail-closed y transacciones InnoDB como requisitos antes de habilitación.

## Enmienda DOC-QA22…26 — corte histórico 0.9.0, 18-11-2026

**Fuente de este corte histórico: OpenAPI 0.9.0-draft (67 REST, 64 rutas y 134 esquemas), `docs/42` DOC-QA22…26.** Sus reglas se conservan con las precisiones DOC-QA27…29 de 0.10.0-draft. Los textos que refieren versiones anteriores no son contratos alternativos. D-01…D-198, 56 PER, 39 tablas lógicas, límites de rol y exclusiones V1 permanecen intactos. Sin código ni comprobación en entorno real.

- **R8-26 / ajuste parcial por expediente:** si se disminuyen unidades de una línea, `FinancialAdjustment` requiere `affected_units` (positivo), `unit_effect=release_unfulfilled_units`, `line_id` y `case_id`. No modificar snapshot de venta; `pc_order_adjustments` conserva `delta_cents`/motivo/caso/idempotencia y la cantidad afectada en evento protegido, asociado con `pc_case_events`; dentro de la misma transacción se incrementa `pc_stock_commitments.released_units` y se revalida `committed_units - fulfilled_units - released_units` bajo locks. DOC-QA27 precisa que `affected_units` debe ser estrictamente menor que ese remanente; cancelarlo íntegro corresponde a R8-23 con sus guardas y efectos existentes. No liberar dos veces, no restock de físico ya entregado, no reembolso automático; saldo separado del ajuste.
- **R8-30 / pago tardío y primer turno:** conciliación de un pago efectivamente acreditado con `resolution=reassign_resources` exige `order_id` existente/vinculado. Si perdió franja y se requiere servicio, exige `new_service_start_at_utc`, `new_service_end_at_utc` y `buyer_agreement_evidence`; el backend comprueba acuerdo, importe, estado del proveedor, unicidad por pedido y recurso/día, y crea la PRIMERA cita `pc_appointments` junto con nueva asignación. No usar R8-38 sin appointment existente, revivir hold ni cobrar por segunda vez. Sin franja validada, incidencia abierta y ningún pedido confirmado ficticio.
- **R8-45/46 / proveedor del comercio:** `PublicBusinessSettings` puede transportar razón social `legal_name`, `tax_id_ruc` condicionado y dirección de local `premises_address`; `AdminSettings/SettingsPatch.mail_sender` configura nombre, correo y firma transaccionales, no credenciales SMTP. Persisten en el CAS/versionado de opciones WP; el Libro R8-15 usa identidad legal y dirección **congeladas** al ingresar reclamación y solo exige campos propios del formulario jurídico, jamás DNI/correo de forma universal al checkout. Agenda y avisos de servicio muestran dirección real del único local si está configurada; envío técnico no demuestra recepción.
- **R8-50/66/68 / exportación ARCO:** `privacy_request_id` y `expected_version` vinculan cada exportación a `pc_privacy_requests` e identidad verificada; `scope=order_legal_copy` obliga `order_id` del mismo titular/instalación. `PrivacyExportAck` da operación y expediente; el estado se consulta desde `PrivacyRequestDetail.export_operations` por R8-66. El artefacto terminado se custodia mediante `evidence_manifest/result_reference` protegido del mismo expediente (sin nueva tabla); solo R8-68 A puede transmitir el archivo por evidencia con finalidad/no-store y expiración. No API pública de exportación ni PER-09 comercial.
- **R8-36/37 / excepción de calendario:** cada cierre expone `closure_id`, `row_version`, `active`. La misma R8-37 recibe `action=create|update|retire`; update y retire requieren ID+versión y create/update requieren instantes. Con locks de todos los días afectados, comprobar solapamientos, pertenencia de cierre al recurso, disponibilidad y citas; retirar se audita sin borrar el cierre de `pc_schedule_blocks` ni cambiar citas automáticamente. R8-53 sigue gestionando solo el horario semanal versionado.

**Preferencia de configuración DOC-QA29, 0.10.0-draft:** la opción WordPress versionada/no-autoload existente incorpora `routine_buyer_emails_enabled` booleano, `true` por defecto si aún no fue configurado. R8-45 lo devuelve siempre a A autorizado; R8-46 omite para conservar o acepta un booleano para sustituir bajo el mismo CAS, con auditoría y nueva `row_version` del agregado. Cambiar únicamente esa preferencia no crea versiones de `pc_policy_versions`, `policy_version` o `privacy_version`; solo un cambio real del texto correspondiente las crea. La política prospectiva y los eventos rutinarios concretos están en `docs/19`; la decisión efectiva se conserva en outbox como especifica §4.13. No se crea tabla ni se reescribe una intención histórica.

**Precedencia y limitaciones del corte histórico DOC-QA27…29:** OpenAPI 0.10.0-draft define propiedades y reglas condicionales, `docs/42` registra las correcciones y este documento define su consumo físico. P-A permanece cerrado en diseño, P-B necesita autorización específica para implementar y P-C/P-D siguen sin pruebas, compilación, migraciones, MP, hosting, conformidad jurídica, ZIP o release.


**IMP-49 (2026-11-20, implementación estática R8-67):** el `evidence_manifest` de `pc_complaints` usa JSON `{"version":1,"items":[...]}` (máximo 20) con cada elemento en forma fija: `ref`, `complaint_id`, `site_id`, `site_digest`, `purpose`, `source`, `storage_key`, `sha256`, `byte_size`, `mime`, `filename`, `added_at_utc`, `channel`, `delivered_at_utc`, `response_sha256`. `site_digest` es SHA-256 de prefijo WordPress + ID de sitio; `storage_key` no aparece en JSON de respuestas REST y apunta solo a `PERUCOMMERCE_PRIVATE_DIR`, fuera de webroot. `purpose` diferencia identidad, representación, constancia de entrega, documento y hoja original; la constancia de entrega exige texto hash SHA-256 original, canal email/carta, instante UTC y verificador legal P-C adicional. El manifest se actualiza exclusivamente por el punto **interno** `Complaint_Evidence::attachPrivate` con actor A, archivo previamente comprobado y origen autorizado, idempotencia + CAS, sin crear subida REST. Sin importer acreditado no existe evidencia disponible por mera referencia o ID. R8-67 revalida cada entrada, SHA-256 binario/tamaño y MIME por finfo ANTES de servir, con sesión/nonce/propósito y auditoría privada. La respuesta R8-42 y su prueba son distintas de la recepción/constancia R8-15: preparar texto no marca `responded_at` ni `response_delivered_at`; sólo constancia verificada completa esos campos. `due_at` se calcula desde el ingreso mediante `Complaint_Calendar` con versión de feriados ratificada P-C, sin suponer días calendario ni crear tabla 40. No hay SQL ejecutado ni P-C/P-D validada.


**IMP-50 (2026-11-20, fuente estática ARCO):** `pc_privacy_requests` persiste `applicant_reference` privado con `site_id/site_digest/source_key`, contacto y origen WordPress-confirmado o manual validado; `request_snapshot` conserva tipo, petición y estado de ingreso, **sin equiparar email confirmado con identidad verificada**. `identity_evidence_ref` y `verified_at` solo se fijan desde verificación P-C con manifest vinculado, actor A, CAS y auditoría. `evidence_manifest` es JSON versión 1 `items` (máximo 20) con: `ref`, `privacy_request_id`, `site_id`, `site_digest`, `purpose`, `source`, `storage_key`, `sha256`, `byte_size`, `mime`, `filename`, `added_at_utc`, `delivery_channel`, `delivered_at_utc`, `resolution_hash`; `storage_key` nunca sale en DTO, no hay endpoint público de carga. `result_reference` reserva JSON `version=1`, `resolution` (nula, preparada o evidenciada) y `export_operations=[]` para el contrato posterior R8-50/68, sin sobrescribir solicitud original. El R8-44 registra `legal_basis_code` de un verificador P-C de retención por categoría, no ejecuta borrado de filas financieras/Libro. `due_at` es valor contractual base 20/10 días hábiles con proveedor de feriados ratificado o NULL; ampliaciones/suspensiones legales NO se autoimplementan. `pc_audit_log` contiene eventos `privacy_request_received`, `privacy_identity_verified`, `privacy_evidence_linked`, `privacy_resolution_prepared/evidenced` asociados al caso y un índice categórico `privacy_incident_recorded` de sitio (hash únicamente); ningún detalle de incidente o contacto va a auditoría. Continuamos con **39 tablas**, sin DDL ni P-C ejecutada.
