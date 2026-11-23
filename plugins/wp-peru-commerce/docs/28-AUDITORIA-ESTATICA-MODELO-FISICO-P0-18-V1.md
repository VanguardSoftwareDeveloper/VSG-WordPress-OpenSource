# P0-18 — Auditoría física estática del modelo de 39 tablas (sin DDL ejecutable)

**Corte:** 2026-11-17. **Estado:** AUDITORÍA ESTÁTICA Y PROPUESTAS DE NORMALIZACIÓN. D-168 y D-176…D-187 ratifican funciones, **NO** avalan automáticamente los nombres finales de columnas, constraints o índices. **No se ejecutó SQL**, no se generó script instalable ni se comprobó versión/privilegios del hosting.

## 1. Fuentes y objetivo de cierre

- `docs/23-MODELO-FISICO-DE-DATOS-E-INDICES-V1.md`: 39 tablas y columnas candidatas (36 originales + 3 de soporte D-176…D-187), E-05, snapshot y casos DB-X.
- `docs/14` a `docs/21` (E-01…E-08), `docs/27` (rutas C23 ratificadas), `docs/openapi/peru-commerce-v1.openapi.draft.json` 0.3.0-draft (63 operaciones), `docs/25` y `docs/26` (J-01…J-05, fiscalidad contextual).
- Requisitos oficiales WordPress, MariaDB y MySQL en `docs/29-COMPATIBILIDAD-WORDPRESS-HOSTING-P0-19-V1.md`. La fuente técnica es el **schema del producto**, no la estructura predeterminada de WordPress ni WooCommerce.

El propósito es especificar qué garantiza cada tabla, qué índices/relaciones no pueden omitirse y qué **brechas impiden convertir el catálogo en DDL seguro**.

## 2. Reglas de normalización transversales propuestas

1. Nombre de tabla = `$wpdb->prefix + 'pc_' + nombre`; **nunca `wp_` fijo**. Comprobar longitud real del identificador y nombre de índices en MySQL/MariaDB (límite técnico), además de funcionamiento en subcarpeta y WordPress multisitio por `blog_id`/prefijo específico. No compartir datos de comercios entre sitios.
2. Todas las tablas críticas InnoDB, `utf8mb4` y collation compatible con sitio. Ninguna tabla financiera/agenda con MyISAM, MEMORY o DDL que pierda integridad.
3. `BIGINT UNSIGNED` para PK internos; `BIGINT` **signed** para céntimos PEN; precios finales positivos cuando sean vendibles, deltas pueden ser negativos si procede; cantidades integrales (no unidades fraccionarias V1). No DECIMAL ni FLOAT para cantidades monetarias comerciales. Al exponer 64-bit JSON usar **string decimal**, PHP calcula con enteros verificados y BCMath cuando proceda.
4. Fechas de eventos e intervalos **UTC DATETIME(6)** y zona IANA del comercio para vistas; `local_date` en locks diarios según zona. **No confundir** NOW() de DB con UTC si el servidor tiene otro timezone. Validar saltos DST en cambios horarios.
5. Cada UQ debe distinguir campos **nullable**: MySQL/MariaDB permiten varias filas con NULL en un índice único. Para deduplicación de operaciones críticas, usar clave no nullable de idempotencia/evento; para order ID externo pendiente de asignación, aceptar NULL sin interpretar como orden externa única.
6. El catálogo original guarda `product_post_id` lógico WordPress; no imponer FK con `wp_posts` o `wp_users` del core. Entre tablas propias InnoDB pueden definirse FK explícitas con comportamiento `RESTRICT`/sin `ON DELETE CASCADE` de historia, siempre que el hosting y orden de creación lo admitan. Una referencia polimórfica `entity_type+entity_id` es lógica, nunca FK accidental.
7. Claves de 191 caracteres con `utf8mb4` y claves compuestas deben revisarse por **bytes y page size** del InnoDB real. Para IDs externos extensos, usar `BINARY(32)` de digest estable y campos originales fuera del índice cuando la identidad esté verificada. Nunca truncar identificadores proveedor para “hacer caber” una UQ.
8. Proteger originales: pedidos/líneas/snapshots, asientos financieros, revisiones de dirección, respuestas/hojas de reclamo y auditoría no se eliminan en cascada ni se reemplazan con UPDATE correctivo. Los campos `row_version` se usan sólo en filas mutables; los eventos append-only tienen PK y origen único.
9. `dbDelta()` puede ayudar con tablas/índices iniciales, pero **no** es motor de migraciones generales. FK, renombres, backfills, validaciones y estados transitorios deben seguir plan por versiones, con control de éxito. DDL puede generar COMMIT implícito: no prometer rollback por SQL o ZIP. No escribir SQL ejecutable hasta autorización específica.
10. Una tabla no es un nuevo microservicio: **un único plugin** y una BD local al WordPress; las 39 tablas son propuesta física bajo el alcance funcional aprobado, no obligación de añadir dependencias externas.

## 3. Inventario completo: PK, unicidad, referencias y invariantes

**UQ** = índice/candidato único, **lógico** = referencia sin FK física al WordPress core, **self** = autorreferencia. `—` significa sin vínculo adicional. Cada fila requiere tipos completos, nulabilidad, índice de acceso y revisión de existencia desde doc.23 antes del DDL.

| # | Tabla | Clave primaria | Unicidad candidata | Referencias candidatas | Restricción comercial/seguridad |
| --- | --- | --- | --- | --- | --- |
| 1 | `pc_sellables` | `id` | (product_post_id, variant_key); SKU opcional con vacío normalizado a NULL | wp_posts.product_post_id lógico | Una fila por producto simple o variante; stock_on_hand entero no negativo; precio final explícito por variante |
| 2 | `pc_stock_movements` | `id` | idempotency_id+source_type condicionado a operación | sellable_id→sellables; idempotency_id→idempotency | Apéndice inmutable; cambio stock_on_hand y movimiento en misma transacción |
| 3 | `pc_stock_commitments` | `id` | order_line_id | order_line_id→order_lines; sellable_id→sellables | Nunca committed < fulfilled + released; compromiso no se suma como hold |
| 4 | `pc_orders` | `id` | order_number | policy_version_id→policy_versions; privacy_version_id→policy_versions; current_delivery_revision_id relación circular lógica | PENDIENTE_PAGO/CONFIRMADO/FINALIZADO/CANCELADO/VENCIDO únicamente; precios y snapshots originales inmutables |
| 5 | `pc_order_lines` | `id` | sin UQ de servicio en modelo base; guardar una única línea de servicio mediante lock orden | order_id→orders; sellable_id→sellables | Una cita máxima por pedido, líneas de unidades enteras, estados por tipo E-01 |
| 6 | `pc_payment_attempts` | `id` | (provider,provider_account_key,environment,mp_idempotency_key); ID order externo si informado | order_id→orders | Una obligación de cobro inicial; reintentos seguros, proveedor no puede identificarse por URL navegador |
| 7 | `pc_payment_events` | `id` | event_dedup_hash NOT NULL | order_id→orders; payment_attempt_id→payment_attempts; source_payment_event_id→self | Eventos de cobro/comisión/efectivo/reembolso externo inmutables; no refund API |
| 8 | `pc_order_adjustments` | `id` | idempotency_id NOT NULL en mutación | order_id→orders; order_line_id→order_lines; case_id→cases; idempotency_id→idempotency | Ajuste posterior sin editar total/linea original ni fingir reintegro |
| 9 | `pc_holds` | `id` | (payment_attempt_id,order_line_id,resource_kind) | payment_attempt_id→payment_attempts; order_line_id→order_lines; order_id→orders; sellable_id→sellables | 15 minutos tras intento MP válido, nunca al navegar; expiración lógica sin cron |
| 10 | `pc_resource_admissions` | `id` | idempotency_id+sellable/resource/slot: deduplicación por operación, propuesta | idempotency_id→idempotency; sellable_id→sellables; resource_id→schedule_revisions lógico | Bloqueo de admisión MUY breve alrededor de API externa sin transacción abierta; TTL exacto aún pendiente |
| 11 | `pc_idempotency` | `id` | (scope_hash,key_hash) NOT NULL | entity_type+entity_id polimórfico lógico | La respuesta de replay de reemisión no contiene secreto; no purgar si hay cargo ambiguo |
| 12 | `pc_webhook_inbox` | `id` | (provider,provider_account_key,environment,event_dedup_hash) | resource_id es identificador externo textual, sin FK interna | Firma válida y persistencia ANTES de ACK; GET fuente autoritativa antes de asentar dinero |
| 13 | `pc_outbox` | `id` | deduplication_hash NOT NULL | entity_type+entity_id polimórfico lógico | Intención de aviso no prueba recibido; payload mínimo sin tokens ni PII excesiva |
| 14 | `pc_calendar_days` | `id` | (resource_id,local_date) NOT NULL | resource_id→schedule_revisions ancla estable | Fila estable y lock aunque día sin citas; no confiar en UNIQUE de intervalos |
| 15 | `pc_schedule_rules` | `id` | (resource_id,revision_no,weekday,window_index) candidato | resource_id→schedule_revisions | Faltan revision_no/window_index para múltiples ventanas/pausas y vigencia; validar solapamientos |
| 16 | `pc_schedule_blocks` | `id` | sin único de intervalo, exclusión por lock calendario | resource_id→schedule_revisions | Cierres excepcionales también bajo locks de días, evidencia/auditoría |
| 17 | `pc_appointments` | `id` | order_line_id | order_line_id→order_lines; resource_id→schedule_revisions | Cita efectiva sólo tras confirmación, evento outcome con evidencia, intervalo [inicio,fin) + buffers |
| 18 | `pc_appointment_history` | `id` | sin UQ salvo idempotencia de evento cuando aplique | appointment_id→appointments; communication_outbox_id→outbox | Historial append-only, reprogramación asegura nuevo slot primero |
| 19 | `pc_ubigeo_versions` | `id` | dataset_hash | — | Dataset INEI versionado; una versión activa verificable por instalación |
| 20 | `pc_ubigeo` | `id` | (version_id,code) | version_id→ubigeo_versions | Diferenciar versión activa de códigos históricos; FK sólo por versión+code |
| 21 | `pc_delivery_rates` | `id` | ubigeo_code en comercio único | UBIGEO code validado contra versión activa, relación lógica | Tarifa >=0, snapshot de pedido no cambia al actualizar tarifa |
| 22 | `pc_delivery_revisions` | `id` | (order_id,revision_number) | order_id→orders; previous_revision_id→self; case_id→cases | Cambio de dirección inmutable, solo aplicar revisión aprobada al puntero actual |
| 23 | `pc_guest_sessions` | `id` | token_hash | order_id→orders; source_credential_id→self | Hash/HMAC de 256 bits; 90 días para enlace, cookie separada; no almacenar secreto claro |
| 24 | `pc_otp_challenges` | `id` | sin UQ single-use universal; rate limit por pedido+destino | order_id→orders | 8 dígitos/10 min/5 intentos, registro HMAC, no enumeración |
| 25 | `pc_action_grants` | `id` | token_hash | order_id→orders; verified_challenge_id→otp_challenges | Grant por propósito/compra, TTL y CSRF todavía por ratificar |
| 26 | `pc_digital_files` | `id` | private_storage_key | owner_product_post_id→wp_posts lógico; sellable_id propuesto→sellables; replaces_file_id→self | Binarios inmutables y protegidos, reemplazo crea nuevo; no renombrar archivo vendido |
| 27 | `pc_download_rights` | `id` | (order_line_id,file_id) | order_line_id→order_lines; file_id→digital_files | Derecho histórico anclado al file_id original; expiración token no revoca derecho |
| 28 | `pc_download_tokens` | `id` | token_hash | right_id→download_rights | 256 bits/24h; revocar anterior al reemitir sin devolver secreto en replay |
| 29 | `pc_download_events` | `id` | sin único general, evento append-only | right_id→download_rights; file_id→digital_files | Auditoría mínima de resultado, no token ni IP permanente |
| 30 | `pc_complaints` | `id` | (sequence_scope,sequence_number) | —; referencias pedido opcionales según ley y sin compra obligatoria | Reclamo/queja y acuse accesibles sin cuenta/email; plazo 15 días hábiles calculado normativamente |
| 31 | `pc_cases` | `id` | sin único general; versionado + idempotencia por acto | order_id→orders opcional; order_line_id→order_lines opcional | Caso logístico/digital/financiero separado de estados E-01; eventos inmutables |
| 32 | `pc_privacy_requests` | `id` | sin UQ general, consulta por asunto/fecha | identity evidence lógico en archivo protegido | ARCO con verificación proporcional; retención legal impide purge automático |
| 33 | `pc_external_documents` | `id` | document_dedup_hash condicional a identidad externa completa (nuevo campo propuesto) | order_id→orders; case_id→cases; source_document_id→self; idempotency_id→idempotency | Sólo evidencia de documento emitido fuera; nota relacionada con documento fuente; no CPE SUNAT |
| 34 | `pc_policy_versions` | `id` | (policy_kind,version_code) | — | Texto/hash inmutables y fecha de efecto; pedidos conservan IDs pactados |
| 35 | `pc_audit_log` | `id` | sin único; índice (entity_type,entity_id,occurred_at) | actor_user_id→wp_users lógico; entity_type+id polimórfico | Append-only y sin secretos/PII excesiva; registrar actor+motivo/evento |
| 36 | `pc_migrations` | `version VARCHAR(50)` | version PK | — | Metadatos de esquema; DDL no rollback transaccional; no declarar aplicado si una fase falló |
| 37 | `pc_schedule_revisions` | `resource_id BIGINT UNSIGNED` | resource_id PK | Ancla lógica del único recurso por instalación | Fila estable antes de cualquier cita o edición horaria; row_version atómico y timezone IANA |
| 38 | `pc_case_events` | `id` | idempotency_id NOT NULL en operación | case_id→cases; idempotency_id→idempotency; actor_user_id→wp_users lógico | Append-only con evidencia sin secreto; no puede autorizar efectos colaterales de dinero |
| 39 | `pc_digital_assignments` | `id` | (sellable_id,slot_key) | sellable_id→sellables; active_file_id→digital_files | Puntero al fichero para nuevas ventas; snapshot derechos anteriores no se modifica |

**Resolución documental posterior, no DDL definitivo:** `docs/36-RESOLUCIONES-PROPUESTAS-DB-A01-A16-P0-18-V1.md` asigna una corrección candidata a **cada DB-A01…DB-A16** y 20 escenarios de aceptación. No se consideran implementadas ni probadas; conservar 39 tablas propuestas, sin migraciones, y contrastar con datos legales LEG-01…10 de `docs/35-CONTRATOS-LEGALES-PERMISOS-DTO-P0-23-P0-25-V1.md`. **Atención:** disponibilidad E-05 descuenta holds/compromisos/admisiones sin doble conteo; al fulfillment se reducen stock físico y compromiso a la vez, no dos reservas.

## 4. Defectos y vacíos físicos encontrados en revisión

| Ref | Hallazgo estático | Riesgo real si se implementa literalmente | Propuesta técnica que debe ratificarse |
| --- | --- | --- | --- |
| DB-A01 | `pc_schedule_rules` no tiene `revision_no` ni orden de ventanas aunque C23 editó horario semanal con pausas | Edición borra o mezcla reglas históricas; ventanas superpuestas en mismo día | Añadir `revision_no`, `window_index` y una UQ por recurso/revisión/día/ventana; bloquear `pc_schedule_revisions` antes de modificar |
| DB-A02 | `pc_schedule_revisions` existe, pero falta definir semilla de `resource_id` y uso de la fila estable para días vacíos | Dos reservas concurrentes podrían usar anclas distintas y ocupar mismo intervalo | Recurso único por instalación anclado a esa tabla, fila creada en instalación; `calendar_days` UQ por recurso/día y locks ordenados; no confiar en UQ(intervalo) |
| DB-A03 | `pc_order_lines` no declara UQ que impida >1 servicio por pedido | Carrito mixto podría almacenar dos citas por fallos/race | Dentro de la misma transacción bloquear el pedido y validar número de líneas servicio; imponer constraint adicional sólo si MySQL/MariaDB ambos lo soportan sin incompatibilidad |
| DB-A04 | Uniques de proveedor con columnas nulas, `idempotency_id` opcionales en algunos eventos | Duplicados de pagos/stock/refund al recibir reintentos | Digest único no nullable por evento financiero; claves de operación no nullable para mutaciones; duplicado del proveedor → revisión, no doble cobro |
| DB-A05 | `pc_schedule_blocks`/`pc_appointments` tienen índices de inicio/fin, pero no exclusión de intervalos | Dos citas se solapan con distintas horas de inicio | Lock de ancla recurso/día, comprobar intersección **[inicio, fin)** + buffers; bloquear todos los días implicados en orden |
| DB-A06 | Modelo original guarda un `status` por pedido y varios de cumplimiento; no hay límite SQL por E-01 | Mutación arbitraria de estado crea pedido FINALIZADO con deuda | Backend transición controlada + expected_version + historia financiera, nunca REST `set_status` genérico |
| DB-A07 | `pc_guest_sessions`, `pc_action_grants` y eventos de idempotencia no contienen el secreto original (correcto), pero hay falta de contrato de replay | Si se guarda token en resultado idempotente, una repetición lo filtra; si se rota en cada replay, duplica autorizaciones | Respuesta inicial secreto una sola vez; replay sólo referencia sin token, por lo tanto la reemisión tras pérdida es nueva operación autenticada; comprobación de hashes/cookie/CSRF pendiente |
| DB-A08 | `pc_digital_files` original apunta a producto WP, el anexo C23 a `sellable_id` y `replaces_file_id` | Riesgo de asignar binario incorrecto a una variante/compra previa | Definir autoridad de archivo por `sellable_id`; asignación `pc_digital_assignments(sellable_id,slot_key)`; `pc_download_rights.file_id` inmutable para ventas existentes |
| DB-A09 | `pc_external_documents` permite serie/número nulos y carece relación correctiva en columnas originales | Nota de crédito sin documento fuente, duplicación o falsa validación SUNAT | Añadir `source_document_id`, `idempotency_id`, contexto de emisor y deduplicación **condicional** sólo si hay identidad completa; nunca emisión CPE |
| DB-A10 | `pc_complaints` no especifica `due_at` ni los feriados computables de J-01 | Afirmar atención en plazo incorrectamente por fecha natural o cron | Cálculo jurídicamente verificado de 15 días hábiles y evidencia separada; no deducir que correo aceptado=respuesta recibida |
| DB-A11 | `pc_privacy_requests` guarda verificación y fecha, sin referencia suficiente de método/evidencia | No poder justificar legitimación y decisión ARCO sin email | Referencia privada mínima a constancia de verificación y causa legal de conservación; sin fotografiar DNI por defecto |
| DB-A12 | `pc_policy_versions` no reemplaza versión atómica de opciones WP/credenciales | Dos administradores pisan settings o rotación de secreto MP | Definir fuente de `row_version` de configuración y CAS en servidor, no usar policy_version para lock de secretos |
| DB-A13 | Stock `pc_sellables.stock_on_hand` se combina con holds, admisiones y compromisos | Sobreventa o doble descuento al convertir hold en compromiso y entregar | Contabilidad por recurso: disponible=on_hand−compromisos−holds vigentes−admisiones efectivas; conversión atómica; descontar stock físico al evento operativo pactado una sola vez |
| DB-A14 | FK de tabla core WordPress / orden de migración y límites de índices no verificados | Activación parcial o FK incompatibles en host compartido | FK internas restrictivas, referencias WP lógicas, migración ordenada y preflight de índice/charset/motor; bloquear checkout si incompleta |
| DB-A15 | `pc_idempotency.response_ref` y outbox son LONGTEXT, potencial almacenar JSON secreto/PII y archivos | Filtración, exceso de almacenamiento/paquete SQL y problemas de retención | Respuestas guardan referencia/estado seguro mínimo; archivos en storage privado, payloads acotados y cifrado/acceso por finalidad |
| DB-A16 | Faltan duraciones físicas de admisión/OTP grant/sesión y conservación de eventos | Reintento crea segundo cobro o token permanece indefinidamente | No fijar TTL arbitrario; documento de seguridad y recuperación P0-23/P0-26 debe ratificar por amenaza/obligación legal; respetar hold 15m, OTP 10m, digital 24h, seguimiento 90d |

**Conclusión:** 39 tablas son **descripción física viable en principio**, no esquema final probado. Las brechas DB-A01…A16 se resuelven antes de crear DDL o claims de integridad comercial.

**Integración P0-26 — verificación documental (2026-11-17):** `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md` aporta barrera de escritura para migraciones, backup coherent de BD/privados/config, journal versionado, restauración y conciliación MP. Los riesgos DB-A01…DB-A16 y P0-19 siguen sin corregirse mediante SQL. Nada instalado/ejecutado.

## 5. Orden declarativo de creación/migración y circularidades

| Fase | Grupos de tablas propuestos | Riesgo/guarda |
| --- | --- | --- |
| M0 — instalaciones y bloqueo | `pc_migrations`, diagnóstico engine/charset/DDL, prefijo, versión, archivo privado, extensiones | Activación no debe habilitar pagos con migración incompleta |
| M1 — dominio estable | `pc_policy_versions`, `pc_ubigeo_versions`, `pc_ubigeo`, `pc_sellables`, `pc_schedule_revisions`, `pc_calendar_days`, `pc_schedule_rules`, `pc_schedule_blocks`, `pc_delivery_rates` | Semillas de recurso único/versiones; no crear índices de longitud incompatible |
| M2 — pedidos, snapshots, agenda | `pc_orders`, `pc_order_lines`, `pc_appointments`, `pc_appointment_history`, `pc_delivery_revisions` | `current_delivery_revision_id` circular: crear primero con nullable y enlazar después; no exigir FK deferrable inexistente |
| M3 — dinero y reservas | `pc_idempotency`, `pc_payment_attempts`, `pc_holds`, `pc_resource_admissions`, `pc_stock_commitments`, `pc_stock_movements`, `pc_payment_events`, `pc_order_adjustments` | Referencias a casos todavía no creados: FK de `case_id` se pospone y valida al final |
| M4 — seguridad y descarga | `pc_guest_sessions`, `pc_otp_challenges`, `pc_action_grants`, `pc_digital_files`, `pc_digital_assignments`, `pc_download_rights`, `pc_download_tokens`, `pc_download_events` | No crear binarios en BD, verificación de storage privado antes de permitir venta digital |
| M5 — casos, normativa y observabilidad | `pc_cases`, `pc_case_events`, `pc_complaints`, `pc_privacy_requests`, `pc_external_documents`, `pc_webhook_inbox`, `pc_outbox`, `pc_audit_log` | Orden de FK: crear tablas y después añadir relaciones internas para evitar ciclos |
| M6 — aceptación y habilitación | Revisar PK/UQ/FK, datos/semillas, versiones, rutas REST y dependencias, archivar checksum | Solo marcar migración completada tras verificación; habilitar módulos críticos por garantías, no por cron |

**Advertencia:** las fases son **plan documental**, no pasos SQL. CREATE/ALTER/DROP pueden hacer COMMIT implícito; ningún ZIP anterior revierte por sí mismo el esquema. Para recuperación real, respaldo consistente de BD, archivos privados, opciones/secretos y reconciliación del proveedor tras restaurar.

## 6. Contratos de las operaciones críticas en relación con locks

| Operación E-05 / R8 | Ancla de lock obligatoria | Atomicidad esperada |
| --- | --- | --- |
| Checkout MP R8-06 | `pc_idempotency` + variantes ordenadas + `pc_calendar_days` del intervalo | Admisión breve → HTTP MP fuera SQL → revalidar y crear hold comercial 15m sólo tras intento MP válido |
| COD R8-07 | `pc_idempotency` + variantes + días | Pedido y compromiso de stock/cita en una transacción; dinero PENDIENTE |
| Vencimiento hold | Pedido/linea + sellable/día | Expiración efectiva incluso sin cron; no cancelar pago MP por suposición |
| Webhook R8-17 | Inbox firmado y luego lock intento+pedido+recursos | Cobro único y sin degradar aprobado; GET proveedor autoritativo fuera SQL |
| Cancelación R8-23 | Pedido/línea + variante/días + ledger | Un ajuste y liberación por acción elegible; O no cancela si hubo dinero acreditado |
| Cita R8-38/54 | Pedido/línea/cita + filas de calendario | No solapar ni editar cita efectiva sin evidencia; sólo A R8-54 |
| Agenda R8-53 | `pc_schedule_revisions` + días afectados | Reglas por revisión, no mover citas confirmadas |
| Incidencia R8-55/56/57 | `pc_cases` + idempotencia + evento append-only | Cambia expediente, no dinero, stock, logística por efecto lateral |
| Derecho/archivo R8-58…63 | Pedido o derecho digital + token/assignments | Secreto mostrado una vez; derecho de venta histórica apuntado a fichero anterior |
| Documento externo R8-64 | Pedido + idempotencia + documento fuente propio | Nuevo registro por emisión externa, no inventar CPE ni refund |

## 7. Validaciones futuras autorizables (no ejecutadas)

DB-A01…DB-A16 y DB-X01…X16 de docs/23 se convierten en criterios de aceptación. Antes de habilitar checkout o ventas digitales deberán demostrarse, **sólo tras autorización específica**: engine, locks, dos compradores simultáneos, día sin citas, MP timeout/duplicado/tardío, restore anterior a cobro real, token privado un solo uso, idempotencia, rol O/A, archivo privado y límites del hosting.

**Evidencia al corte:** se revisó estáticamente el inventario de **39 tablas** contra E-01…E-08, D-176…D-187, matrices legales y documentación oficial; no existe SQL DDL ejecutable ni schema desplegado. **Pendientes**: ratificación de las propuestas DB-A, compatibilidad/versionado MySQL/MariaDB/host real, límites legales/TTL, fórmulas de índices/campos definitivos, migraciones y validación. No se autoriza implementación.
