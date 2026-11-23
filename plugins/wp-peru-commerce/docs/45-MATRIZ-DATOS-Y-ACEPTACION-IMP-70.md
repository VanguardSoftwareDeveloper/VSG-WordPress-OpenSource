# IMP-70 — Trazabilidad PER, tablas/DB-A y criterios A-01…A-202

**Corte: 2026-11-21, rama `main`.** Matriz de responsabilidades de **código fuente**; no prueba que WP, MySQL, archivos, MP, cookies o UX funcionen. Las especificaciones históricas de docs/08/23/24/38 conservan sus fechas; prevalecen OpenAPI 0.12, D-200 y el [registro de fases](43-ESTADO-IMPLEMENTACION.md).

Se cotejaron referencias textuales con archivos realmente presentes (sin ejecutar PHP/SQL/Node). **No equivale a una auditoría exhaustiva de cada SQL o permiso interno**: son rutas de inspección delimitadas y escenarios pendientes, no casillas funcionales aprobadas. El mapa REST/consumidores está en [docs/44](44-TRAZABILIDAD-REST-IMP-70.md).

## 1. Las 56 operaciones de permisos PER

| Permiso/alcance funcional | R8 / vía aprobada | Estado y responsable en fuente |
| --- | --- | --- |
| **PER-01** Ver catálogo público/ficha/precio | R8-01/02/03 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-02** Crear carrito y cotizar | R8-05 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-03** Seleccionar fecha/hora de servicio | R8-04/05 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-04** Confirmar contraentrega elegible / iniciar pago | R8-06/07 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-05** Consultar estado del **propio** pedido | R8-08/09/21 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-06** Listar todos los pedidos de la instalación | R8-20 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-07** Consultar detalle con contacto/dirección | R8-09/21 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-08** Ver auditoría de un pedido | R8-21 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-09** Exportar listado de pedidos (EXCLUIDO V1 D-188) | — | EXCLUIDA V1 D-188 (exportación comercial masiva) |
| **PER-10** Consultar historial de movimientos monetarios | R8-09/21/29 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-11** Obtener/reemitir enlace de pedido a tercero | R8-10/11/12, R8-58 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-12** Buscar pedido por nombre/teléfono/correo | R8-20 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-13** Crear/editar producto simple o variable | R8-31/32/33 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-14** Activar/desactivar oferta manual y precios por variante | R8-31/33 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-15** Publicar/ocultar productos | R8-34 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-16** Borrar definitivamente producto con pedidos vinculados | — | EXCLUIDA: borrado destructivo de producto con vínculos |
| **PER-17** Consultar/actualizar stock de variante | R8-31/35 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-18** Cambiar manualmente stock de variante | R8-35 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-19** Editar calendario semanal, buffers y cierres | R8-36/37, R8-53 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-20** Registrar servicio realizado/no-asistencia | —, R8-54 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-21** Reprogramar cita confirmada | R8-38 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-22** Cancelar cita pendiente sin cobro acreditado | R8-23 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-23** Acceder a fichero protegido de producto digital como empleado | R8-48, R8-62 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-24** Modificar URL/almacenamiento de descargas digitales | R8-48, R8-63 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-25** Marcar línea física PREPARANDO/LISTO | R8-22 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-26** Registrar DESPACHADO, ENTREGADO o recojo | R8-22 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-27** Abrir incidencia logística por DESPACHADO no entregado | R8-12, R8-55 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-28** Resolver incidencia logística | —, R8-56, R8-57 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-29** Cancelar línea pendiente sin dinero acreditado ni ajustes | R8-23 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-30** Cancelar línea con dinero alguna vez acreditado | R8-23/26 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-31** Resolver digital HABILITADO sin descarga al pedir cancelación | R8-26, R8-61 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-32** Cancelar DESPACHADO no entregado tras investigación | R8-23/26, R8-57 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-33** Cambiar dirección confirmada | R8-10/11/12/28 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-34** Registrar efectivo realmente recibido | R8-24 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-35** Corregir cobro efectivo histórico | R8-25 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-36** Ejecutar reembolso mediante API del plugin | — | PROHIBIDA V1: ejecutar refund por API del plugin |
| **PER-37** Registrar reembolso externo **total o parcial** comprobado | R8-27 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-38** Solicitar devolución/reclamación | R8-12/15/69/70 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-39** Corregir pedido confirmado | R8-26/28/33/69/70 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-40** Marcar línea digital HABILITADA | R8-17 + E-01 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-41** Revocar/reemitir descarga digital | R8-12/13/14, R8-59, R8-60 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-42** Marcar pedido FINALIZADO | E-01 O-07 | EFECTO INTERNO E-01/O-07: orders-finalization.php; no REST nuevo |
| **PER-43** Leer credenciales Mercado Pago / proveedor mapas | — | PROHIBIDA lectura en claro de secretos por UI |
| **PER-44** Crear/cambiar/retirar credenciales o configuración sensible | R8-46/47 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-45** Configurar UBIGEO/cobertura/tarifas | R8-45/46/51/52 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-46** Configurar régimen/boleta/factura/recibo externo | R8-45/46 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-47** Configurar políticas de cancelación/garantía | R8-45/46 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-48** Leer/procesar Libro de Reclamaciones | R8-15/41/42/65/67 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-49** Cambiar rol/permisos de operador | WordPress nativo | WORDPRESS NATIVO: access-roles.php |
| **PER-50** Exportar datos personales o financieros | R8-43/44/50/66/68 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-51** Instalar/actualizar ZIP del plugin | WordPress nativo | WORDPRESS NATIVO + installer.php/maintenance.php; sin ZIP ejecutado |
| **PER-52** Desinstalar/eliminar datos históricos | WordPress nativo | WORDPRESS NATIVO: uninstall conservador sin purga automática |
| **PER-53** Consultar log de errores de integración | R8-49 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-54** Solicitar reenvío de notificación transaccional | R8-10/11/12/39/40 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-55** Activar/desactivar módulos de pago/mapa/reclamaciones | R8-45/46 | Alcance registrado en docs/24; controlador/es de R8 detallados en docs/44. Validación de rol y recurso pendiente P-C. |
| **PER-56** Gestionar cuenta y sesión propia WordPress | WordPress nativo / R8-19 | WORDPRESS NATIVO + R8-19: access-roles.php |

**Exclusiones que no deben «corregirse» con nuevos endpoints:** PER-09 exportación comercial masiva D-188, PER-16 borrado destructivo vinculado, PER-36 refund API y PER-43 lectura de secretos. PER-42 finalización E-01 y PER-49/51/52/56 usan WordPress o efectos internos explícitos, no rutas inventadas. Para R8-30/64, las responsabilidades y autorizaciones están definidas por E-08 y OpenAPI aunque no haya PER dedicado. La proyección O/A y titular invitado son requisitos en cada ruta, no un permiso global.

## 2. Persistencia: las 39 tablas declarativas

Cada nombre corresponde a `Schema::tableStatements` en `includes/schema.php` y a inspección física en `schema-inspector.php`; la segunda columna apunta al consumidor dominante, **no** a un único escritor exclusivo. `wp_options`, `wp_posts` y `wp_postmeta` son tablas WordPress adicionales del entorno, fuera de las 39 del plugin.

| Tabla | Consumidor/fuente para revisión | Propósito y validación residual |
| --- | --- | --- |
| `pc_sellables` | `includes/catalog-service.php` | catálogo, variantes/precios; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_stock_movements` | `includes/inventory-service.php` | movimientos inmutables de stock; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_stock_commitments` | `includes/inventory-reservations.php` | compromisos/entregas físicos; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_orders` | `includes/orders-persistence.php` | pedido, importe y estado global; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_order_lines` | `includes/orders-persistence.php` | snapshots originales por línea; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_payment_attempts` | `includes/mp-payment-attempts.php` | intentos proveedor correlacionados; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_payment_events` | `includes/financial-events.php` | ledger firmado e inmutable; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_order_adjustments` | `includes/orders-adjustments.php` | ajustes comerciales; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_holds` | `includes/inventory-reservations.php` | holds MP con caducidad; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_resource_admissions` | `includes/checkout-coordinator.php` | admisiones breves pre-HTTP; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_idempotency` | `includes/idempotency.php` | raíces, replay, referencia externa; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_webhook_inbox` | `includes/queue-inbox.php` | avisos recibidos/verificados; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_outbox` | `includes/queue-outbox.php` | intenciones de notificación recuperables; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_calendar_days` | `includes/agenda-repository.php` | anclas de días por recurso; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_schedule_rules` | `includes/agenda-repository.php` | horario semanal por revisión; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_schedule_blocks` | `includes/agenda-mutations.php` | cierres versionados; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_appointments` | `includes/checkout-appointments.php` | cita única por pedido; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_appointment_history` | `includes/appointment-operations.php` | historial de reprogramación; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_ubigeo_versions` | `includes/ubigeo-catalog.php` | versiones de fuente INEI; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_ubigeo` | `includes/ubigeo-catalog.php` | jerarquía/códigos UBIGEO; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_delivery_rates` | `includes/delivery-coverage.php` | cobertura y tarifas versionadas; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_delivery_revisions` | `includes/orders-delivery-changes.php` | propuestas/aprobaciones históricas; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_guest_sessions` | `includes/guest-sessions.php` | sesiones/seguimiento hash, revocación; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_otp_challenges` | `includes/guest-challenges.php` | OTP acotado y consumido; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_action_grants` | `includes/guest-action-grants.php` | grants de un uso; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_digital_files` | `includes/digital-files.php` | binarios/versiones históricas; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_download_rights` | `includes/digital-rights.php` | derechos por file_id original; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_download_tokens` | `includes/digital-download.php` | accesos temporales hash; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_download_events` | `includes/digital-download.php` | bitácora de transferencias; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_complaints` | `includes/complaint-service.php` | Libro, snapshot, fecha, constancia; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_cases` | `includes/orders-cases.php` | expedientes, fase y pruebas originales; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_privacy_requests` | `includes/privacy-admin.php` | solicitudes y resoluciones ARCO; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_external_documents` | `includes/external-documents.php` | referencias de comprobantes externos; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_policy_versions` | `includes/settings-store.php` | condiciones/privacidad versionadas; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_audit_log` | `includes/event-journal.php` | auditoría append-only; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_migrations` | `includes/installer.php` | etapas y checksums de schema; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_schedule_revisions` | `includes/agenda-repository.php` | recurso único y versión activa; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_case_events` | `includes/orders-cases.php` | historial de expediente y actor; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |
| `pc_digital_assignments` | `includes/digital-files.php` | slots/archivo activo por variante; PK/UQ/FK/CHECK, transacciones, retención y reglas de acceso pendientes P-C. |

## 3. Los 16 tratamientos de integridad DB-A

| Riesgo lógico aprobado | Fuente candidata de implementación | Verificación P-C diferenciada |
| --- | --- | --- |
| **DB-A01** pc_schedule_rules inmutables por revisión y ventana; combinación única recurso/revisión/día/índice de ventana; vigencia por fecha. pc_schedule_revisions controla versión activa | `includes/agenda-repository.php`, `includes/agenda-mutations.php` | R8-36/53: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A02** Una fila estable de recurso/instalación en pc_schedule_revisions; pc_calendar_days UQ(recurso,fecha local) insertable idempotentemente y bloqueada antes de validar franjas | `includes/agenda-repository.php`, `includes/checkout-appointments.php` | R8-04/07: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A03** `pc_appointments.order_id` NN con `UQ(order_id)` y `UQ(order_line_id)` además de serialización de líneas bajo lock de `pc_orders`; reprogramar actualiza la misma fila/histórico | `includes/checkout-appointments.php`, `includes/appointment-operations.php` | R8-07/38: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A04** pc_idempotency UQ(scope_hash,key_hash); pc_payment_events UQ(event_dedup_hash) NOT NULL; intento MP UQ(cuenta,ambiente,clave) y eventos externos deduplicados por cuenta+ambiente+recurso comprobado | `includes/idempotency.php`, `includes/financial-events.php`, `includes/mp-webhook-auth.php` | R8-06/07/17: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A05** Para toda reserva, lock de pc_calendar_days de todos los días locales tocados, ordenados; comparar intervalos semiabiertos de citas, holds, cierres y buffers | `includes/checkout-appointments.php`, `includes/agenda-time.php` | R8-04/07/38: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A06** Transiciones exactas E-01 en servidor, expected_version y auditoría atómica; no endpoint genérico set_status | `includes/orders-domain.php`, `includes/orders-finalization.php` | R8-22/23/26: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A07** pc_guest_sessions y pc_download_tokens guardan HMAC/hash, no URL; pc_idempotency solo referencia segura y respuesta sin secreto en replay | `includes/guest-sessions.php`, `includes/digital-download.php` | R8-08/09/13/14: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A08** `pc_digital_files.sellable_id/replaces_file_id` versionan inmutable por variante, y `pc_digital_assignments` con `UQ(sellable_id,slot_key)` avanza activo por CAS; `pc_download_rights` fija `file_id` al pagar | `includes/digital-files.php`, `includes/digital-rights.php` | R8-48/59/60/63: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A09** pc_external_documents append-only con emisor/ambiente/tipo/referencia/fuente, idempotencia, documento correctivo vinculado al original de la misma instalación | `includes/external-documents.php` | R8-64: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A10** pc_complaints preserva snapshot original del reclamante/proveedor/relato, número correlativo, fecha de ingreso local+UTC y vencimiento calculado por calendario hábil; eventos de respuesta y constancia | `includes/complaint-service.php`, `includes/complaint-calendar.php` | R8-15/41/42: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A11** pc_privacy_requests almacena tipo, solicitud original protegida y referencia privada a legitimación verificada, fecha de recepción/plazo, fundamento/excepción y constancia de respuesta | `includes/privacy-intake.php`, `includes/privacy-admin.php` | R8-43/44/50: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A12** Opción dedicada `wp_options` sin autoload, valor versionado y escritura atómica condicional `WHERE` sobre versión/valor previo exacto, con auditoría compatible y invalidación de caches; jamás `update_option()` sin CAS | `includes/settings-store.php`, `includes/transaction-context.php` | R8-45/46/47: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A13** Disponibilidad inventariada = on_hand – compromisos aún sin entregar – holds vigentes – admisiones efectivas; hold→compromiso y salida+reducción compromiso son transacciones indivisibles | `includes/inventory-reservations.php`, `includes/checkout-coordinator.php` | R8-05/06/07/22: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A14** Migración en etapas y journal; FK restrictivas internas compatibles, sin borrado cascada de movimientos; preflight InnoDB/charset/columnas/índices/backups; versión del esquema vinculada a plugin | `includes/installer.php`, `includes/schema-inspector.php`, `includes/maintenance.php` | ciclo WordPress/IMP-68: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A15** Whitelist en inbox/outbox/audit/idempotency; hash y referencia opaca, sin tokens, payload MP raw, dirección completa ni secretos. Adjuntos solo almacenamiento privado | `includes/queue-inbox.php`, `includes/queue-outbox.php`, `includes/event-journal.php` | R8-17/39/40: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |
| **DB-A16** Fechas de expiración de credenciales **separadas** de fechas de conservación legal/financiera. Holds 15m, OTP 10m/5 intentos, descarga 24h, seguimiento 90d según D ya aprobado | `includes/domain/business-time.php`, `includes/guest-challenges.php`, `includes/digital-download.php` | R8-10/11/13/14: verificar SQL/invariantes verdaderas en motor, concurrencia/hosting cuando corresponda; sin prueba ejecutada. |

## 4. Índice de aceptación individual A-01…A-202 (no ejecutado)

El criterio es el texto original de [docs/08](08-PLAN-Y-ACEPTACION.md); la tercera columna asigna una **responsabilidad de revisión por grupo** (no equivalencia funcional automática ni prueba suficiente). `R69-xx` significa que existe un escenario explícito en `tests/risk/scenarios.json`; `C70-A-xx` exige preparar la verificación particular en P-C con entrada/éxito/fallo/concurrencia que corresponda. Para requisitos normativos, de proveedor, hosting, Figma, privacidad y accesibilidad, se debe guardar además evidencia externa, no solo código.

| Aceptación documental | Grupo / fuente principal a revisar | Caso/verificación pendiente |
| --- | --- | --- |
| **A-01** Comprador invitado físico | Catálogo y checkout base: catalog-public-view.php, quote-service.php, frontend/src/app/cart-page.ts | C70-A-01 (P-C, no ejecutado) |
| **A-02** Catálogo incrustado | Catálogo y checkout base: catalog-public-view.php, quote-service.php, frontend/src/app/cart-page.ts | C70-A-02 (P-C, no ejecutado) |
| **A-03** Móvil | Catálogo y checkout base: catalog-public-view.php, quote-service.php, frontend/src/app/cart-page.ts | C70-A-03 (P-C, no ejecutado) |
| **A-04** Escritorio | Catálogo y checkout base: catalog-public-view.php, quote-service.php, frontend/src/app/cart-page.ts | C70-A-04 (P-C, no ejecutado) |
| **A-05** Producto con variante | Catálogo y checkout base: catalog-public-view.php, quote-service.php, frontend/src/app/cart-page.ts | C70-A-05 (P-C, no ejecutado) |
| **A-06** Última unidad simultánea | Concurrencia y expiración: inventory-reservations.php, checkout-appointments.php, domain/business-time.php | C70-A-06 (P-C, no ejecutado) |
| **A-07** Último cupo simultáneo | Concurrencia y expiración: inventory-reservations.php, checkout-appointments.php, domain/business-time.php | C70-A-07 (P-C, no ejecutado) |
| **A-08** Vence retención sin tarea programada | Concurrencia y expiración: inventory-reservations.php, checkout-appointments.php, domain/business-time.php | C70-A-08 (P-C, no ejecutado) |
| **A-09** Carrito mixto | Mixto, pago y derechos: checkout-coordinator.php, mp-checkout-service.php, digital-download.php | C70-A-09 (P-C, no ejecutado) |
| **A-10** Mercado Pago retorna aprobado visualmente | Mixto, pago y derechos: checkout-coordinator.php, mp-checkout-service.php, digital-download.php | C70-A-10 (P-C, no ejecutado) |
| **A-11** Webhook duplicado/fuera de orden | Mixto, pago y derechos: checkout-coordinator.php, mp-checkout-service.php, digital-download.php | C70-A-11 (P-C, no ejecutado) |
| **A-12** Pago aprobado sin stock/cupo | Mixto, pago y derechos: checkout-coordinator.php, mp-checkout-service.php, digital-download.php | C70-A-12 (P-C, no ejecutado) |
| **A-13** Contraentrega | Mixto, pago y derechos: checkout-coordinator.php, mp-checkout-service.php, digital-download.php | C70-A-13 (P-C, no ejecutado) |
| **A-14** Descarga digital no pagada | Mixto, pago y derechos: checkout-coordinator.php, mp-checkout-service.php, digital-download.php | C70-A-14 (P-C, no ejecutado) |
| **A-15** Descarga pagada | Mixto, pago y derechos: checkout-coordinator.php, mp-checkout-service.php, digital-download.php | C70-A-15 (P-C, no ejecutado) |
| **A-16** Dirección sin cobertura | Entrega, cambio de cotización, gating: delivery-coverage.php, quote-service.php, module-gates.php | C70-A-16 (P-C, no ejecutado) |
| **A-17** Proveedor cartográfico falla | Entrega, cambio de cotización, gating: delivery-coverage.php, quote-service.php, module-gates.php | C70-A-17 (P-C, no ejecutado) |
| **A-18** Cambio de precio posterior | Entrega, cambio de cotización, gating: delivery-coverage.php, quote-service.php, module-gates.php | C70-A-18 (P-C, no ejecutado) |
| **A-19** Desactivación de módulo | Entrega, cambio de cotización, gating: delivery-coverage.php, quote-service.php, module-gates.php | C70-A-19 (P-C, no ejecutado) |
| **A-20** Libro nativo | Libro nativo o enlace externo: complaint-form.php, complaint-service.php | C70-A-20 (P-C, no ejecutado) |
| **A-21** Libro externo | Libro nativo o enlace externo: complaint-form.php, complaint-service.php | C70-A-21 (P-C, no ejecutado) |
| **A-22** Falla actualización ZIP | Actualización ZIP y recuperación: installer.php, maintenance.php | C70-A-22 (P-C, no ejecutado) |
| **A-23** Usuario sin permisos | Permisos y doble envío: access-control.php, idempotency.php | C70-A-23 (P-C, no ejecutado) |
| **A-24** Refresco/doble clic en checkout | Permisos y doble envío: access-control.php, idempotency.php | C70-A-24 (P-C, no ejecutado) |
| **A-25** Cancelación/reembolso | Cancelación y servicios: orders-cancellation.php, appointment-operations.php | C70-A-25 (P-C, no ejecutado) |
| **A-26** Servicio presencial V1 | Cancelación y servicios: orders-cancellation.php, appointment-operations.php | C70-A-26 (P-C, no ejecutado) |
| **A-27** Control táctil móvil | UX, accesibilidad y Figma: frontend/src/app/checkout-page.ts, frontend/src/styles.css, docs/10 (criterio) | C70-A-27 (P-C, no ejecutado) |
| **A-28** Accesibilidad | UX, accesibilidad y Figma: frontend/src/app/checkout-page.ts, frontend/src/styles.css, docs/10 (criterio) | C70-A-28 (P-C, no ejecutado) |
| **A-29** Formulario condicional | UX, accesibilidad y Figma: frontend/src/app/checkout-page.ts, frontend/src/styles.css, docs/10 (criterio) | C70-A-29 (P-C, no ejecutado) |
| **A-30** Compra móvil/escritorio | UX, accesibilidad y Figma: frontend/src/app/checkout-page.ts, frontend/src/styles.css, docs/10 (criterio) | C70-A-30 (P-C, no ejecutado) |
| **A-31** Rendimiento | UX, accesibilidad y Figma: frontend/src/app/checkout-page.ts, frontend/src/styles.css, docs/10 (criterio) | C70-A-31 (P-C, no ejecutado) |
| **A-32** Interoperabilidad con tema WordPress | UX, accesibilidad y Figma: frontend/src/app/checkout-page.ts, frontend/src/styles.css, docs/10 (criterio) | C70-A-32 (P-C, no ejecutado) |
| **A-33** Handoff Figma→Codex | UX, accesibilidad y Figma: frontend/src/app/checkout-page.ts, frontend/src/styles.css, docs/10 (criterio) | C70-A-33 (P-C, no ejecutado) |
| **A-34** Agenda y recurso único | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-34 (P-C, no ejecutado) |
| **A-35** Una cita por pedido | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-35 (P-C, no ejecutado) |
| **A-36** Pago mixto | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-36 (P-C, no ejecutado) |
| **A-37** Gestión de cambios | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-37 (P-C, no ejecutado) |
| **A-38** Cobertura por UBIGEO | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-38 (P-C, no ejecutado) |
| **A-39** Datos condicionales | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-39 (P-C, no ejecutado) |
| **A-40** Roles y permisos | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-40 (P-C, no ejecutado) |
| **A-41** Comprobante externo | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-41 (P-C, no ejecutado) |
| **A-42** Pedido con efectivo y digital | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-42 (P-C, no ejecutado) |
| **A-43** Retención temporal de recursos | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-43 (P-C, no ejecutado) |
| **A-44** Pago tardío sin disponibilidad | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-44 (P-C, no ejecutado) |
| **A-45** Agenda del servicio | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-45 (P-C, no ejecutado) |
| **A-46** Cambio de cita y dinero | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-46 (P-C, no ejecutado) |
| **A-47** Inventario de variantes | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-47 (P-C, no ejecutado) |
| **A-48** Descargas de digitales | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-48 (P-C, no ejecutado) |
| **A-49** Cobro único | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-49 (P-C, no ejecutado) |
| **A-50** Pedido de invitado | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-50 (P-C, no ejecutado) |
| **A-51** Selección de cita | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-51 (P-C, no ejecutado) |
| **A-52** Inicio preciso de retención | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-52 (P-C, no ejecutado) |
| **A-53** Contraentrega confirmada | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-53 (P-C, no ejecutado) |
| **A-54** Capacidad de la agenda | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-54 (P-C, no ejecutado) |
| **A-55** Condiciones de cancelación | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-55 (P-C, no ejecutado) |
| **A-56** Enlace digital de 24 h | Agenda, catálogo mixto, pedidos y derechos: checkout-coordinator.php, agenda-service.php, digital-rights.php | C70-A-56 (P-C, no ejecutado) |
| **A-57** Precios de oferta simple | Precios, catálogos, mapa y permisos: catalog-service.php, map-adapter.php, access-control.php | C70-A-57 (P-C, no ejecutado) |
| **A-58** Catálogo indexable e interacción aislada | Precios, catálogos, mapa y permisos: catalog-service.php, map-adapter.php, access-control.php | C70-A-58 (P-C, no ejecutado) |
| **A-59** Diseño de persistencia | Precios, catálogos, mapa y permisos: catalog-service.php, map-adapter.php, access-control.php | C70-A-59 (P-C, no ejecutado) |
| **A-60** Mapa y respaldo manual | Precios, catálogos, mapa y permisos: catalog-service.php, map-adapter.php, access-control.php | C70-A-60 (P-C, no ejecutado) |
| **A-61** Moneda única PEN | Precios, catálogos, mapa y permisos: catalog-service.php, map-adapter.php, access-control.php | C70-A-61 (P-C, no ejecutado) |
| **A-62** Precio final y fiscalidad contextual | Precios, catálogos, mapa y permisos: catalog-service.php, map-adapter.php, access-control.php | C70-A-62 (P-C, no ejecutado) |
| **A-63** Operador limitado | Precios, catálogos, mapa y permisos: catalog-service.php, map-adapter.php, access-control.php | C70-A-63 (P-C, no ejecutado) |
| **A-64** Compatibilidad mantenida | Precios, catálogos, mapa y permisos: catalog-service.php, map-adapter.php, access-control.php | C70-A-64 (P-C, no ejecutado) |
| **A-65** Precio final configurado | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-65 (P-C, no ejecutado) |
| **A-66** Oferta simple manual | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-66 (P-C, no ejecutado) |
| **A-67** Cobro efectivo registrado por operador | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-67 (P-C, no ejecutado) |
| **A-68** Precio y oferta independiente por variante | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-68 (P-C, no ejecutado) |
| **A-69** Corrección exclusiva y no destructiva de efectivo | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-69 (P-C, no ejecutado) |
| **A-70** Reembolso externo registrado | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-70 (P-C, no ejecutado) |
| **A-71** Estados independientes de pedido mixto | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-71 (P-C, no ejecutado) |
| **A-72** Cancelación parcial auditable | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-72 (P-C, no ejecutado) |
| **A-73** Precio simple versus precio de variante | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-73 (P-C, no ejecutado) |
| **A-74** Cancelación ordinaria solo de líneas pendientes | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-74 (P-C, no ejecutado) |
| **A-75** Cambios auditados sin reescritura del pedido | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-75 (P-C, no ejecutado) |
| **A-76** Autoridad de cancelaciones con impacto financiero | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-76 (P-C, no ejecutado) |
| **A-77** Digital habilitado sin descargar | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-77 (P-C, no ejecutado) |
| **A-78** Físico despachado no entregado | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-78 (P-C, no ejecutado) |
| **A-79** Revisión de delivery al cancelar última línea física | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-79 (P-C, no ejecutado) |
| **A-80** Restricción estricta de cancelación por operador | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-80 (P-C, no ejecutado) |
| **A-81** Ajuste de dirección confirmada | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-81 (P-C, no ejecutado) |
| **A-82** Reembolso externo parcial comprobado | Efectivo, ajustes, cancelación y documentos: cash-payments.php, orders-adjustments.php, external-refunds.php | C70-A-82 (P-C, no ejecutado) |
| **A-83** E01-A: pedido administrativo | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | C70-A-83 (P-C, no ejecutado) |
| **A-84** E01-B: intento de Mercado Pago | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | C70-A-84 (P-C, no ejecutado) |
| **A-85** E01-C: retención | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | R69-14, R69-28 (P-C, no ejecutado) |
| **A-86** E01-D: línea física | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | C70-A-86 (P-C, no ejecutado) |
| **A-87** E01-E: digital y tokens | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | C70-A-87 (P-C, no ejecutado) |
| **A-88** E01-F: servicio y cita | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | C70-A-88 (P-C, no ejecutado) |
| **A-89** E01-G: expedientes asociados | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | C70-A-89 (P-C, no ejecutado) |
| **A-90** E01-H1: recuperar vencido con pago tardío | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | R69-14, R69-29 (P-C, no ejecutado) |
| **A-91** E01-H2: excepción tras despacho | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | C70-A-91 (P-C, no ejecutado) |
| **A-92** E01-H3: cierre sin pendientes | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | C70-A-92 (P-C, no ejecutado) |
| **A-93** E01-H4: sin doble máquina de envío | Estados E-01 y excepciones: orders-domain.php, orders-finalization.php, mp-credit-application.php | C70-A-93 (P-C, no ejecutado) |
| **A-94** E02-A: datos mínimos de operador | Permisos E-02: access-control.php, access-projection.php | C70-A-94 (P-C, no ejecutado) |
| **A-95** E02-B: catálogo sin borrado destructivo | Permisos E-02: access-control.php, access-projection.php | C70-A-95 (P-C, no ejecutado) |
| **A-96** E02-C: stock y agenda auditados | Permisos E-02: access-control.php, access-projection.php | C70-A-96 (P-C, no ejecutado) |
| **A-97** E02-D: cumplimiento y citas operativas | Permisos E-02: access-control.php, access-projection.php | C70-A-97 (P-C, no ejecutado) |
| **A-98** E02-E: exportación y archivos protegidos | Permisos E-02: access-control.php, access-projection.php | C70-A-98 (P-C, no ejecutado) |
| **A-99** E02-F: dirección posterior | Permisos E-02: access-control.php, access-projection.php | C70-A-99 (P-C, no ejecutado) |
| **A-100** E02-G: configuración y reclamaciones | Permisos E-02: access-control.php, access-projection.php | C70-A-100 (P-C, no ejecutado) |
| **A-101** E02-H: invitado y verificación adicional | Permisos E-02: access-control.php, access-projection.php | C70-A-101 (P-C, no ejecutado) |
| **A-102** E03-A: clasificación de solicitudes | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-102 (P-C, no ejecutado) |
| **A-103** E03-B: política histórica | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-103 (P-C, no ejecutado) |
| **A-104** E03-C: físico sin despacho | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-104 (P-C, no ejecutado) |
| **A-105** E03-D: devolución y stock | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-105 (P-C, no ejecutado) |
| **A-106** E03-E: digital habilitado sin descarga | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-106 (P-C, no ejecutado) |
| **A-107** E03-F: digital descargado defectuoso | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-107 (P-C, no ejecutado) |
| **A-108** E03-G: revocación legítima digital | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-108 (P-C, no ejecutado) |
| **A-109** E03-H: no-show | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-109 (P-C, no ejecutado) |
| **A-110** E03-I: reprogramación | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-110 (P-C, no ejecutado) |
| **A-111** E03-J: línea completa V1 | Elegibilidad E-03: orders-cancellation.php, digital-cases.php, appointment-operations.php | C70-A-111 (P-C, no ejecutado) |
| **A-112** E04-A: céntimos PEN exactos | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-112 (P-C, no ejecutado) |
| **A-113** E04-B: precios/cantidades válidos | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-113 (P-C, no ejecutado) |
| **A-114** E04-C: total comercial | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-114 (P-C, no ejecutado) |
| **A-115** E04-D: recotizar y preservar snapshot | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-115 (P-C, no ejecutado) |
| **A-116** E04-E: única entrega física | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-116 (P-C, no ejecutado) |
| **A-117** E04-F: delivery previo y ajuste | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-117 (P-C, no ejecutado) |
| **A-118** E04-G: última línea física cancelada | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-118 (P-C, no ejecutado) |
| **A-119** E04-H: precio histórico de cancelación | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-119 (P-C, no ejecutado) |
| **A-120** E04-I: cobro bruto y comisión MP | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | R69-01, R69-02, R69-03, R69-04 (P-C, no ejecutado) |
| **A-121** E04-J: efectivo neto | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-121 (P-C, no ejecutado) |
| **A-122** E04-K: dirección postpago más cara | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-122 (P-C, no ejecutado) |
| **A-123** E04-L: reintegros y asientos inmutables | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-123 (P-C, no ejecutado) |
| **A-124** E04-M: documentación fiscal externa | Moneda y asientos E-04: domain/money.php, financial-events.php, orders-adjustments.php | C70-A-124 (P-C, no ejecutado) |
| **A-125** E05-A: InnoDB requerido y verificable | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-125 (P-C, no ejecutado) |
| **A-126** E05-B: Stock protegido por variante | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-126 (P-C, no ejecutado) |
| **A-127** E05-C: Agenda protegida en día vacío | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | R69-06, R69-07 (P-C, no ejecutado) |
| **A-128** E05-D: Crear order MP en fases | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | R69-06, R69-10, R69-26, R69-27 (P-C, no ejecutado) |
| **A-129** E05-E: HTTP fuera de transacción SQL | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-129 (P-C, no ejecutado) |
| **A-130** E05-F: Expiración efectiva sin cron puntual | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-130 (P-C, no ejecutado) |
| **A-131** E05-G: Idempotencia durable | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-131 (P-C, no ejecutado) |
| **A-132** E05-H: Webhook validado e inbox | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | R69-12, R69-13 (P-C, no ejecutado) |
| **A-133** E05-I: Acreditación por verificación de proveedor | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-133 (P-C, no ejecutado) |
| **A-134** E05-J: Contraentrega atómica | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-134 (P-C, no ejecutado) |
| **A-135** E05-K: Cancelación/reprogramación atómicas | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-135 (P-C, no ejecutado) |
| **A-136** E05-L: Reembolso externo deduplicado | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-136 (P-C, no ejecutado) |
| **A-137** E05-M: Outbox local durable | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-137 (P-C, no ejecutado) |
| **A-138** E05-N: Reconciliación y recuperación | Concurrencia y recuperación E-05: transaction-context.php, idempotency.php, inventory-reservations.php | C70-A-138 (P-C, no ejecutado) |
| **A-139** E06-A: Confirmación web sin correo obligatorio físico | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-139 (P-C, no ejecutado) |
| **A-140** E06-B: Correo WordPress y diagnóstico | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-140 (P-C, no ejecutado) |
| **A-141** E06-C: Sin marketing en V1 | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-141 (P-C, no ejecutado) |
| **A-142** E06-D: Eventos de comunicación basados en hechos | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-142 (P-C, no ejecutado) |
| **A-143** E06-E: Outbox y reenvío auditado | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-143 (P-C, no ejecutado) |
| **A-144** E06-F: Transporte no equivale a recepción | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-144 (P-C, no ejecutado) |
| **A-145** E06-G: Bandeja interna priorizada | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-145 (P-C, no ejecutado) |
| **A-146** E06-H: Mensajes mínimos y enlaces seguros | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-146 (P-C, no ejecutado) |
| **A-147** E06-I: Avisos de citas sin recordatorios extra | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-147 (P-C, no ejecutado) |
| **A-148** E06-J: Libro con constancia y alertas | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-148 (P-C, no ejecutado) |
| **A-149** E06-K: Contacto telefónico manual | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-149 (P-C, no ejecutado) |
| **A-150** E06-L: Plantillas versionadas es-PE | Notificaciones E-06: notification-service.php, queue-outbox.php, complaint-service.php | C70-A-150 (P-C, no ejecutado) |
| **A-151** E07-A: Responsable y ANPD por instalación | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-151 (P-C, no ejecutado) |
| **A-152** E07-B: Datos mínimos por finalidad y rol | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-152 (P-C, no ejecutado) |
| **A-153** E07-C: Seguimiento 256 bits/90 días | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-153 (P-C, no ejecutado) |
| **A-154** E07-D: OTP sensible por correo | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-154 (P-C, no ejecutado) |
| **A-155** E07-E: Recuperación telefónica manual | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-155 (P-C, no ejecutado) |
| **A-156** E07-F: Descarga segura independiente | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-156 (P-C, no ejecutado) |
| **A-157** E07-G: Identidad WordPress de empleados | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-157 (P-C, no ejecutado) |
| **A-158** E07-H: Privacidad del comercio y evidencia | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-158 (P-C, no ejecutado) |
| **A-159** E07-I: Inventario de proveedores y flujos | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-159 (P-C, no ejecutado) |
| **A-160** E07-J: Mapa solo por decisión del comprador | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-160 (P-C, no ejecutado) |
| **A-161** E07-K: Derechos ARCO con y sin email | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-161 (P-C, no ejecutado) |
| **A-162** E07-L: Retención y desinstalación | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-162 (P-C, no ejecutado) |
| **A-163** E07-M: Privacidad de enlaces y antiabuso | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-163 (P-C, no ejecutado) |
| **A-164** E07-N: Incidentes y notificación legal | Privacidad E-07: guest-sessions.php, guest-challenges.php, privacy-admin.php | C70-A-164 (P-C, no ejecutado) |
| **A-165** E08-A: REST versionado y autorización | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-165 (P-C, no ejecutado) |
| **A-166** E08-B: Angular embebido mismo origen | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-166 (P-C, no ejecutado) |
| **A-167** E08-C: Cotización autoridad backend | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-167 (P-C, no ejecutado) |
| **A-168** E08-D: Checkout independiente e idempotente | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | R69-01, R69-04, R69-05, R69-08 (P-C, no ejecutado) |
| **A-169** E08-E: Canje seguro de seguimiento | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-169 (P-C, no ejecutado) |
| **A-170** E08-F: Permiso WordPress por recurso/campo | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-170 (P-C, no ejecutado) |
| **A-171** E08-G: OTP sensible con alcance y teléfono manual | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-171 (P-C, no ejecutado) |
| **A-172** E08-H: Orders API con credencial comercial privada | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-172 (P-C, no ejecutado) |
| **A-173** E08-I: Payer condicional sujeto a verificación | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-173 (P-C, no ejecutado) |
| **A-174** E08-J: Cargo logístico no inventariable | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-174 (P-C, no ejecutado) |
| **A-175** E08-K: Vigencia MP independiente del hold | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | R69-04, R69-14, R69-29 (P-C, no ejecutado) |
| **A-176** E08-L: Webhook firmado e inbox durable | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | R69-11, R69-12, R69-13 (P-C, no ejecutado) |
| **A-177** E08-M: Errores REST coherentes | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-177 (P-C, no ejecutado) |
| **A-178** E08-N: Permisos administrativos y versiones | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-178 (P-C, no ejecutado) |
| **A-179** E08-O: Descarga digital privada o inhabilitada | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-179 (P-C, no ejecutado) |
| **A-180** E08-P: Libro y ARCO segregados | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-180 (P-C, no ejecutado) |
| **A-181** E08-Q: Idempotencia y caché delimitada | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-181 (P-C, no ejecutado) |
| **A-182** E08-R: Formalización OpenAPI antes de código | REST, MP, descargas y contratos E-08: rest-router.php, mp-orders-client.php, digital-private-storage.php | C70-A-182 (P-C, no ejecutado) |
| **A-183** A reabre pedido un día después y consulta case_id de cambio de dirección | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | C70-A-183 (P-C, no ejecutado) |
| **A-184** Política del comercio cambia después de confirmar pedido | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | C70-A-184 (P-C, no ejecutado) |
| **A-185** Comercio no configura cuota de descargas | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | C70-A-185 (P-C, no ejecutado) |
| **A-186** Comercio configura nueva cuota y ya vendió digitales | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | C70-A-186 (P-C, no ejecutado) |
| **A-187** Dos descargas concurrentes del último uso permitido | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | R69-21, R69-22, R69-23, R69-28 (P-C, no ejecutado) |
| **A-188** A desactiva o cambia proveedor de tiles | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | C70-A-188 (P-C, no ejecutado) |
| **A-189** El cliente no abre mapa | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | C70-A-189 (P-C, no ejecutado) |
| **A-190** R8-12 creó expediente y perdió respuesta tras consumir grant | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | R69-08, R69-17, R69-19, R69-20 (P-C, no ejecutado) |
| **A-191** R8-12 reenvía la clave con contenido distinto o sesión ajena | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | R69-17, R69-19, R69-20 (P-C, no ejecutado) |
| **A-192** Solicitud invitada registra primer evento de caso | Resoluciones C23 y sesiones: orders-cases.php, digital-rights.php, guest-requests.php | C70-A-192 (P-C, no ejecutado) |
| **A-193** Se implementa ficha de producto/catálogo móvil y escritorio | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | C70-A-193 (P-C, no ejecutado) |
| **A-194** Comprador físico puro abre checkout, luego se compara con carrito digital/servicio/mixto | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | R69-31 (P-C, no ejecutado) |
| **A-195** Comprador pega teléfono/dirección con espacios/formato habitual; intenta UBIGEO erróneo, importe ambiguo o dato de seguridad inválido | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | C70-A-195 (P-C, no ejecutado) |
| **A-196** Usuario confirma checkout, cambia método o regresa desde Mercado Pago con resultado pendiente | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | R69-11, R69-32, R69-35 (P-C, no ejecutado) |
| **A-197** Se revisa jerarquía de precio, artículo, entrega, cita y CTA en catálogo/checkout y gestión de pedido | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | C70-A-197 (P-C, no ejecutado) |
| **A-198** Recotización 409, turno agotado, OTP fallido, caída de red o enlace protegido caducado | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | R69-32 (P-C, no ejecutado) |
| **A-199** Comprador vuelve, corrige dirección/cantidad o cierra diálogo antes de confirmar | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | C70-A-199 (P-C, no ejecutado) |
| **A-200** Operador y administrador trabajan con pedidos y expedientes repetitivos | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | R69-18, R69-25, R69-33, R69-36 (P-C, no ejecutado) |
| **A-201** Uso móvil con teclado abierto, zoom/texto ampliado y acceso sin ratón/arrastre | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | R69-24, R69-31, R69-32, R69-33 (P-C, no ejecutado) |
| **A-202** Codex entrega una pantalla comparada con Figma y docs/10 | UX móvil/escritorio y WCAG: frontend/src/app/checkout-page.ts, frontend/src/app/admin-page.ts, docs/10 (criterio) | C70-A-202 (P-C, no ejecutado) |

## 5. Brechas no cubiertas por conteos ni evidencia de compilación

1. Los criterios A-01…A-202 están indexados **uno por uno**, pero esa trazabilidad de grupo no equivale a verificar 202 comportamientos: los tests ejecutados y evidencia externa de MP Perú/SQL/hosting/legales/UX no existen. Cerrar IMP-70 estáticamente no anticipa aceptación P-C/P-D.
2. **Actualización IMP-71:** R8-16 tiene ahora consumidor Angular en checkout-page.ts/html, con búsqueda y selección de distrito habilitado desde servidor. La tarifa no se acepta desde el navegador, y la validación real INEI/coverage sigue fail-closed y pendiente P-C (D-16/D-17, A-16/A-38/A-194/A-201).
3. R8-58/59 requieren identidad original/reverificación y canal legítimo de entrega de enlace; no suplirlo con botones que conviertan un case_id en credencial. **Actualización IMP-71:** R8-60/61 cuentan ahora con consumidor Angular A por caso/derecho y versión; la apertura tipada de solicitud invitada para un derecho de pedido multiarchivo se enlazó mediante admin-post WP nativo protegido por nonce/capacidades A y el servicio existente Digital_Cases::openFromGuest, sin ampliar OpenAPI. La referencia opaca de decisión R8-61 se proyecta limitadamente por R8-21 para R8-60. Esta conexión es estática y requiere P-C de permisos/CSRF, replay, revocación y múltiples archivos; no afirmar flujo real probado. PER-20/42 no habilitan `set_status` genérico.
4. P-C debe observar commits ambiguos, carreras de stock/agenda/descarga, holdings vencidos, reenvíos MP, acceso A/O/guest, datos legales, cookies/CSP, archivos/versiones, restauración/epoch y ausencia de secretos en JSON/outbox/logs.
5. La compatibilidad y distribución finales requieren Node/PHP/SQL/WordPress/MP, derechos GPL y fuente correspondiente, comprobación de locks y SQL físico, navegador/WCAG, upgrade/restauración de una instalación de prueba y autorización específica. Nada fue ejecutado en esta fase.
