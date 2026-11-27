# PeruCommerce — contrato documental de integridad: BD, concurrencia y recuperación V1

**PRECEDENCIA VIGENTE D-210 (27-11-2026):** el contrato vigente es OpenAPI **0.13.0-draft (82 REST/78 rutas/162 esquemas)**, con 83 entradas R8, 62 PER, 41 tablas declarativas y 210 decisiones; el POS V1 consta en [docs/48](48-CONTRATO-POS-V1-P2.md) como **DOCUMENTADO/NO IMPLEMENTADO**. Las cifras D-200/0.12.0-draft o anteriores de este archivo quedan como corte histórico; el código y las pruebas siguen alineados con 0.12.0-draft hasta P3.

**Corte:** 2026-11-17. **Estado:** invariantes lógicas y 16 tratamientos **RATIFICADOS EN DISEÑO D-191**, no DDL ejecutable, migraciones, instalación, prueba ni compatibilidad de motor comprobada. Documento subordinado a D-168/E-01/E-04/E-05/E-07/E-08, docs/23 (39 tablas) y docs/28/36 (16 observaciones). No declara 16 defectos corregidos en una base de datos inexistente ni incorpora nuevas tablas por inferencia.

## 1. Convenciones y límites

- Un WordPress y un comercio/local principal por instalación. Prefijo real de WordPress determinado por instalación; relaciones a wp_posts/wp_users son lógicas salvo revisión explícita de migración/hosting.
- IDs internos propuestos como BIGINT UNSIGNED; fechas de hechos en UTC DATETIME(6) y zona IANA del local para agenda; importes PEN como BIGINT de céntimos enteros firmados únicamente para deltas. Prohibidos float monetarios, totales de frontend como fuente y reescritura de snapshots.
- PK/UQ/FK/collation/rango de índices se validan frente a MySQL/MariaDB real y charset del hosting. Los índices escritos aquí son contratos lógicos que se trasladarán al DDL tras autorización, no SQL verificado.
- Las 39 tablas documentadas en docs/23 siguen siendo el límite de diseño actual. Se preservan los tres soportes C23: pc_schedule_revisions, pc_case_events y pc_digital_assignments.
- Nunca declarar «listo para cobrar/reservar» si falta motor transaccional, bloqueo de recursos, integridad de eventos, secreto de streaming privado o conciliación con Mercado Pago.

## 2. Tratamientos de integridad ratificados en diseño lógico (DB-A01…DB-A16)

| ID | Tratamiento lógico obligatorio D-191 | Condición que se verifica durante DDL/ejecución autorizada |
| --- | --- | --- |
| DB-A01 | pc_schedule_rules inmutables por revisión y ventana; combinación única recurso/revisión/día/índice de ventana; vigencia por fecha. pc_schedule_revisions controla versión activa | Definir rango de ventanas, cruce medianoche y cambio de horario legal; ninguna modificación mueve citas existentes |
| DB-A02 | Una fila estable de recurso/instalación en pc_schedule_revisions; pc_calendar_days UQ(recurso,fecha local) insertable idempotentemente y bloqueada antes de validar franjas | Día sin citas también debe tener una fila real que permita bloqueo; motores/aislamiento/índices a probar |
| DB-A03 | `pc_appointments.order_id` NN con `UQ(order_id)` y `UQ(order_line_id)` además de serialización de líneas bajo lock de `pc_orders`; reprogramar actualiza la misma fila/histórico | Una sola cita por pedido en todo el ciclo, incluso simultáneamente, validando que order_line pertenece al order_id |
| DB-A04 | pc_idempotency UQ(scope_hash,key_hash); pc_payment_events UQ(event_dedup_hash) NOT NULL; intento MP UQ(cuenta,ambiente,clave) y eventos externos deduplicados por cuenta+ambiente+recurso comprobado | Reintentos sin segunda order, firma webhook antes de inbox y consulta fuente antes de acreditar |
| DB-A05 | Para toda reserva, lock de pc_calendar_days de todos los días locales tocados, ordenados; comparar intervalos semiabiertos de citas, holds, cierres y buffers | Probar franjas nocturnas y días inicialmente vacíos, sin confiar en índices contra solapamientos |
| DB-A06 | Transiciones exactas E-01 en servidor, expected_version y auditoría atómica; no endpoint genérico set_status | Historia financiera/estado por línea y pedido no pueden saltarse reglas en concurrencia |
| DB-A07 | pc_guest_sessions y pc_download_tokens guardan HMAC/hash, no URL; pc_idempotency solo referencia segura y respuesta sin secreto en replay | Primera entrega por TLS/no-store, verificación de identidad y rotación, bloqueo por CSRF/Origin/IDOR |
| DB-A08 | `pc_digital_files.sellable_id/replaces_file_id` versionan inmutable por variante, y `pc_digital_assignments` con `UQ(sellable_id,slot_key)` avanza activo por CAS; `pc_download_rights` fija `file_id` al pagar | Reemplazo no altera versiones anteriores vendidas; almacenamiento privado y controles de lectura deben verificarse realmente |
| DB-A09 | pc_external_documents append-only con emisor/ambiente/tipo/referencia/fuente, idempotencia, documento correctivo vinculado al original de la misma instalación | No es emisor SUNAT. Campos condicionales por régimen y documento fiscal real |
| DB-A10 | pc_complaints preserva snapshot original del reclamante/proveedor/relato, número correlativo, fecha de ingreso local+UTC y vencimiento calculado por calendario hábil; eventos de respuesta y constancia | Comprobar Anexo I y 15 días hábiles/feriados; «enviado» no equivale a «entregado» |
| DB-A11 | pc_privacy_requests almacena tipo, solicitud original protegida y referencia privada a legitimación verificada, fecha de recepción/plazo, fundamento/excepción y constancia de respuesta | Canal sin email, derecho según ley y conservación que no destruye registros tributarios |
| DB-A12 | Opción dedicada `wp_options` sin autoload, valor versionado y escritura atómica condicional `WHERE` sobre versión/valor previo exacto, con auditoría compatible y invalidación de caches; jamás `update_option()` sin CAS | Comprobar semántica transaccional/colación/cache e impacto de rotaciones; si no garantiza CAS entre procesos, bloquear mutaciones sensibles y MP, sin sumar tabla 40 en silencio |
| DB-A13 | Disponibilidad inventariada = on_hand – compromisos aún sin entregar – holds vigentes – admisiones efectivas; hold→compromiso y salida+reducción compromiso son transacciones indivisibles | Nada de descontar dos veces ni ventas con stock negativo, excepto inventario expresamente desactivado |
| DB-A14 | Migración en etapas y journal; FK restrictivas internas compatibles, sin borrado cascada de movimientos; preflight InnoDB/charset/columnas/índices/backups; versión del esquema vinculada a plugin | No depender de dbDelta como migrador reversible universal ni tratar ZIP viejo como rollback de BD |
| DB-A15 | Whitelist en inbox/outbox/audit/idempotency; hash y referencia opaca, sin tokens, payload MP raw, dirección completa ni secretos. Adjuntos solo almacenamiento privado | Inspección de caché, logs, copias, CDN y permisos por entorno |
| DB-A16 | Fechas de expiración de credenciales **separadas** de fechas de conservación legal/financiera. Holds 15m, OTP 10m/5 intentos, descarga 24h, seguimiento 90d según D ya aprobado | Definir y ratificar TTL de admisión/grant/cookie y mínimo de retención de idempotencia/inbox antes de DDL. Restore no reabre una autorización vencida |

**Reconciliación física incorporada a docs/23:** `pc_schedule_rules` tiene ahora clave `(resource_id,revision_no,weekday,window_index)` y offset nocturno, con `pc_schedule_revisions.active_revision_no`; `pc_appointments.order_id` es único y ligado a su `order_line_id` (reprogramación sobre la misma fila, histórico en `pc_appointment_history`); `pc_digital_files.sellable_id/replaces_file_id` preservan versiones; y la configuración mutable usa CAS condicional sobre opción dedicada no autoload más auditoría atómica y control de caché, o se deshabilita la función. D-190 define las duraciones técnicas. **No se construyó SQL ni se probó el hosting**; nada de lo anterior autoriza ampliar las 39 tablas.

**Vinculación de identidad temporal (D-190):** el esquema lógico docs/23 fija ahora `pc_otp_challenges.guest_session_id` y `pc_action_grants.guest_session_id` (NN, con índices por sesión/propósito). La identidad verificada, la sesión canjeada y el pedido deben coincidir; al rotar/revocar la sesión, los desafíos y grants derivados dejan de ser utilizables, independientemente del TTL restante. Un grant se consume atómicamente una única vez. No se añaden tablas ni endpoints; la comprobación transaccional y de concurrencia se efectúa cuando se autorice implementar y probar.

## 3. Orden de bloqueo y consistencia propuesto

1. El intento de confirmar pedido toma el lock de la orden (o de su raíz de creación duradera); con ello valida un máximo de una cita/pedido y versiona carrito/cotización.
2. Al reservar inventario, lock de variantes vendibles por ID ascendente; al reservar servicio, lock de filas ancla de recurso/día por fecha creciente, **insertadas antes incluso para días vacíos**. Un pedido mixto requiere orden global determinista para no provocar inversión de locks entre operaciones.
3. Verificar a tiempo real cantidad disponible y ausencia de intersección de intervalos; calcular todo en backend con servidor temporal UTC y política local IANA. No conservar locks durante llamada HTTP a Mercado Pago.
4. La admisión pre-HTTP limita simultaneidad; tras obtener order válida asociada a un intento, el hold comercial inicia por 15 minutos **sin extensión**. Timeout proveedor conserva intento ambiguo y exige reconciliación; no crear un segundo cobro por reflejo.
5. Evento firmado y conciliado acredita un solo movimiento financiero por digest deduplicable; la compra de una cita convierte el hold en compromiso válido. Si pago llega tarde sin recurso, abrir incidencia sin sobreventa, sin devolución ejecutada por API.
6. Contraentrega, cuando todo el pedido es cobrable presencialmente, compromete stock/cita en transacción sin acreditar dinero hasta el efectivo realmente recibido. No admitir cobro dividido por línea.
7. Cancelación/reprogramación usa locks y versiones, transiciones E-01, snapshot inmutable y asiento de ajuste. La corrección de efectivo/reembolso externo solo por administrador con referencia y motivo comprobables.
8. Caducidad lógica se evalúa en backend dentro de la operación transaccional, sin depender de que WP-Cron se ejecute puntualmente.

**Pruebas de aceptación futuras obligatorias:** dos compradores última unidad; dos citas día vacío y franja nocturna; dos webhooks idénticos; timeout MP y webhook tardío; replay de secretos; dos administradores compitiendo por configuración; migración interrumpida y restauración con pago externo ocurrido fuera del backup. Todos los casos en docs/36 están **NO EJECUTADOS**.

## 4. Contrato de integridad y actualizaciones

| Familia | Invariante exigido | Restricción de implementación futura |
| --- | --- | --- |
| Pedidos/líneas | Snapshot y versión inmutables; FK lógica entre orden y líneas; ninguna línea huérfana | No eliminar pedido con movimientos, reclamaciones, descarga o obligaciones de conservación |
| Pagos | UQ digest de evento acreditable, un hecho financiero por referencia y fuente | No acreditar por webhook sin consulta autoritativa; no refund API |
| Idempotencia | UQ(scope,key), request_hash coherente; replay con misma intención y payload; discrepancia 409 | No almacenar URL/token reemitido en respuesta persistida |
| Citas/horarios | UQ anclas de día + búsqueda bajo lock; ventanas versionadas y buffers locales | Sin confirmación por seleccionar horario en ficha |
| Archivos digitales | Binario privado inmutable, derechos congelados por file_id; asignación futura con CAS | Prohibido servir binario desde URL pública |
| Registro de reclamos | Folio correlativo por ámbito legal y constancia; respuestas sin reescribir entrada | No condicionar Libro a cuenta o compra |
| Expedientes ARCO | Verificación de legitimación y fundamento, proceso por canal alternativo | No borrar ventas/Libro por vencimiento de token |
| Configuración | CAS/versión de ajuste sensible y control de acceso A | No meter credencial MP en browser, REST ni logs |
| Migraciones | Versionar plugin/schema, journal con fase, backup coherente BD+archivos+config | No marcar migración parcial «completada» ni inventar restauración |
| Restauración | Reconcilia MP y revocaciones tras restablecer copia, protege adjuntos | No prometer RTO/RPO ni restaurar seguridad solo por cambiar ZIP |

## 5. Incertidumbres que no se pueden «resolver» sin ejecución real

- **Motor/hosting:** 12 HOST del docs/29; InnoDB, aislamiento, locks, cache, almacenamiento y webhooks en una instalación real.
- **Mercado Pago Perú:** payer omitido en compra física sin correo; línea de Entrega en items con suma exacta; expiration_time PT15M y pagos tardíos. La documentación oficial sugiere soporte de las propiedades, pero **no es prueba de aceptación de la cuenta peruana**.
- **Legal:** identificación y obligaciones de Libro, protección ANPD, SUNAT y conservación por actividad/régimen específico. Se debe aplicar la norma vigente a cada instalación; no inventar certificación universal.
- **Ubigeo:** archivo oficial de distritos con licencia verificable y hash por release, validación de jerarquía/importe.
- **Distribución:** build Angular, ZIP con fuentes GPL correspondientes, instalación/upgrade/restore y comprobación de seguridad. Estas puertas son posteriores a artefactos reales; no pueden marcarse aprobadas antes de programar.

## 6. Criterio de cierre documental

Una observación DB-A se puede marcar **«tratamiento documental especificado»** cuando tiene tabla(s), invariante, condición de fallo y caso de aceptación, pero no **«defecto corregido»** ni **«DDL certificado»**. La matriz docs/34 debe conservar dos columnas/etiquetas separadas: diseño revisado y evidencia de entorno. Nunca reducir el inventario de 16 riesgos a 0 fallos probados porque existe esta propuesta.

**Cierre de PRE-03:** D-191 ratifica los 16 tratamientos como invariantes de diseño lógico con 39 tablas; los detalles físicos exactos que solo pueden derivarse de código/hosting real se revisarán al elaborar DDL en implementación autorizada. Ninguna prueba SQL, MP o privacidad ha sido ejecutada y ninguna base de datos real contiene estos cambios.
