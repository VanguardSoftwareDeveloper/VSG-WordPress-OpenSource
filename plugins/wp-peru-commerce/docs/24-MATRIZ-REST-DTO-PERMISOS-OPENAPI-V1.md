# P0-23 — Matriz REST/DTO/OpenAPI y autorización V1

> **Nota vigente D-188 (2026-11-17):** PER-09 está EXCLUIDA de V1. Sus referencias de falta de cobertura en esta matriz histórica no son bloqueos actuales. R8-50 conserva su finalidad de privacidad por titular legitimado.

> **Documento complementario HISTÓRICO de trazabilidad (NO fuente de rutas actual).** La matriz vigente de **68 R8 (67 REST y una página WP R8-18) y 56 PER** está en `docs/24-MATRIZ-REST-PERMISOS-DTO-P0-23-V1.md`; el OpenAPI actual `docs/openapi/peru-commerce-v1.openapi.draft.json` es **0.8.0-draft con 67 REST/64 rutas y 134 esquemas**, con extensión ratificada D-176…D-187. Las tablas de **51 REST** que siguen en este documento son el inventario **E-08 original** antes de C23; no deben usarse para eliminar R8-53…64. Los anexos 11/12 del modelo de **39 tablas propuestas** cruzan persistencia. No constituye una segunda fuente ejecutable.

**Fecha:** 2026-11-17. **Estado:** CONTRATO FÍSICO **PROPUESTO** para revisión; la opción P0-23 está aprobada como política D-172, **no** está aprobada todavía cada propiedad nueva de transporte. **No autoriza código, pruebas, instalaciones, llamadas a proveedores ni despliegues.**

**Precedencia:** `docs/14` a `docs/21` (E-01…E-08), `docs/22` (D-167…D-175) y `docs/23` (almacenamiento propuesto). Este documento no puede ampliar las máquinas de estados, permisos ni los 15 minutos de hold aprobados.

## 1. Convenciones y seguridad

- El inventario aprobado consta de **52 entradas R8**: **51 operaciones REST** y **R8-18 = página WordPress GET /public/return, NO REST**. El namespace es `peru-commerce/v1`. La URL real proviene de `rest_url()`; no suponer instalación en raíz ni introducir servidores PHP/Node nuevos.
- Mapa de **56 PER**: una PER puede ser automática, nativa WordPress, parcialmente implementable, prohibida o carecer de endpoint. **Ningún endpoint adicional** queda autorizado por este mapeo.
- Los importes `*_cents` y los IDs de 64 bits se serializan como **cadenas decimales**, backend autoridad comercial, moneda PEN. `operation_id` (UUID) identifica reintentos idempotentes; `expected_version` exige detección de conflicto de registro. Snapshots de pedido y movimientos financieros son inmutables.
- **Rutas admin:** cookie y sesión WordPress, `X-WP-Nonce` para CSRF; `permission_callback` y capability específica por operación, alcance de registro, estado, historial financiero y campos. El nonce **no** sustituye autenticación ni permisos. `manage_options` genérico no concede acceso comercial.
- **Invitados:** secreto de seguimiento de 256 bits (90 días) sólo lectura, canjeado desde fragmento de URL a cookie HttpOnly/Secure/SameSite; OTP 8 dígitos/10 minutos/5 intentos exclusivamente para solicitudes sensibles con email. Solo teléfono implica verificación administrativa manual; **no SMS OTP V1**. Token digital separado, 256 bits/24h; expiración de enlace ≠ pérdida de derechos.
- **Webhook R8-17:** validar `x-signature`/`x-request-id`, marca temporal, recurso/cuenta/ambiente, guardar inbox antes de ACK; GET autoritativo antes de acreditar. Never asumir estado pago del callback ni usar WordPress nonce.
- **Errores:** estructura nativa `WP_Error`: `{code:"pc_*",message:"…",data:{status,retryable,request_id,details?}}`, HTTP real. 202 significa proceso pendiente y **nunca** cobro confirmado. No usar wrapper `{error:{...}}`.
- **Reembolsos:** ningún endpoint para ejecutar refund MP; R8-27 sólo registra operación externa comprobada. HTTPS, no-store para dinero/PII/Libro/tokens; antiabuso/rate-limit y prevención IDOR.
- Las condiciones de compatibilidad MP peruano `payer` omitido cuando no hay email, ítem `Entrega` y `PT15M` continúan **bloqueadas hasta prueba autorizada**. No inventar correo del comprador.

## 2. Las 51 operaciones REST

| R8 | Método y ruta | Actor | Capability o condición | DTO de entrada → salida | PER | Guarda relevante |
| --- | --- | --- | --- | --- | --- | --- |
| R8-01 | `GET /public/bootstrap` | P | — | `—` → `PublicBootstrap` | PER-01 | Sin secretos ni PII |
| R8-02 | `GET /public/products` | P | — | `—` → `ProductPage` | PER-01 | Paginación; catálogo SEO en PHP |
| R8-03 | `GET /public/products/{id}` | P | — | `—` → `PublicProduct` | PER-01 | Solo precios/variantes públicas |
| R8-04 | `GET /public/services/{id}/slots` | P | — | `—` → `SlotPage` | PER-03 | Consulta de turno NO reserva |
| R8-05 | `POST /public/quotes` | P | — | `QuoteRequest` → `QuoteResponse` | PER-02, PER-03 | Cotización sin hold |
| R8-06 | `POST /public/checkout/mercado-pago` | P | — | `CheckoutRequest` → `CheckoutMPResponse` | PER-04 | MP bloqueado hasta verificar payer/Entrega/PT15M |
| R8-07 | `POST /public/checkout/contraentrega` | P | — | `CheckoutRequest` → `CheckoutCODResponse` | PER-04 | Un acto presencial; compromiso atómico |
| R8-08 | `POST /guest/sessions` | Secreto enlace | — | `GuestSessionExchange` → `GuestSessionResult` | PER-05, PER-11 | Token fragmento canjeado por sesión solo lectura |
| R8-09 | `GET /guest/order` | G | — | `—` → `GuestOrder` | PER-05, PER-07, PER-10 | Solo pedido propio; campos filtrados |
| R8-10 | `POST /guest/challenges` | G | — | `ChallengeRequest` → `ChallengeAccepted` | PER-11, PER-33, PER-38, PER-41, PER-54 | Solicita OTP con respuesta uniforme |
| R8-11 | `POST /guest/challenges/verify` | G | — | `ChallengeVerify` → `ActionGrantResult` | PER-11, PER-33, PER-38, PER-41, PER-54 | Código 8 dígitos, 10 min, 5 intentos |
| R8-12 | `POST /guest/requests` | G + V | — | `GuestActionRequest` → `CaseAccepted` | PER-11, PER-33, PER-38, PER-41, PER-54 | Solo solicitud; no ejecuta reintegro ni modifica pedido |
| R8-13 | `POST /guest/digital/sessions` | Token digital | — | `DigitalSessionExchange` → `DigitalSessionResult` | PER-41 | Token 256 bits distinto al seguimiento |
| R8-14 | `GET /guest/digital/{right_id}/file` | Sesión digital | — | `—` → `PrivateBinaryFile` | PER-41 | Derecho+abono+24h; archivo no público |
| R8-15 | `POST /public/reclamaciones` | P | — | `ComplaintCreate` → `ComplaintReceipt` | PER-38, PER-48 | Sin cuenta; campos legales bajo J-01 |
| R8-16 | `GET /public/ubigeo` | P | — | `—` → `UbigeoPage` | PER-01 | UBIGEO y cobertura pública |
| R8-17 | `POST /webhooks/mercado-pago` | HMAC MP | — | `MPWebhookNotice` → `WebhookReceipt` | PER-04, PER-40 | Firma, inbox, GET autoritativo; nunca pago por body |
| R8-19 | `GET /admin/bootstrap` | A/O | Sesión WP | `—` → `AdminBootstrap` | PER-56 | Capacidades UI orientativas |
| R8-20 | `GET /admin/orders` | A/O | perucommerce_orders_read | `—` → `AdminOrdersPage` | PER-06, PER-12 | Listado paginado, mínimo contacto |
| R8-21 | `GET /admin/orders/{id}` | A/O | perucommerce_orders_read | `—` → `AdminOrder` | PER-05, PER-07, PER-08, PER-10 | Filtro de PII, financiero y auditoría por actor |
| R8-22 | `POST /admin/orders/{id}/lines/{line_id}/fulfillment` | A/O | perucommerce_orders_fulfill | `FulfillmentChange` → `MutationResult` | PER-25, PER-26 | Solo transiciones físicas |
| R8-23 | `POST /admin/orders/{id}/lines/{line_id}/cancel` | A/O condicionado | O: perucommerce_orders_cancel_unpaid sin dinero; A: perucommerce_orders_financial_adjust | `CancelLine` → `MutationResult` | PER-22, PER-29, PER-30, PER-32 | O solo sin dinero acreditado; A si dinero/ajuste |
| R8-24 | `POST /admin/orders/{id}/cash-receipts` | A/O | perucommerce_cash_record | `CashReceipt` → `MutationResult` | PER-34 | Efectivo neto aplicado; sin edición destructiva |
| R8-25 | `POST /admin/orders/{id}/cash-corrections` | A | perucommerce_cash_correct | `CashCorrection` → `MutationResult` | PER-35 | Solo A; evento correctivo auditado |
| R8-26 | `POST /admin/orders/{id}/adjustments` | A | perucommerce_orders_financial_adjust | `FinancialAdjustment` → `MutationResult` | PER-39 | Ajuste sin reemplazar snapshot |
| R8-27 | `POST /admin/orders/{id}/external-refunds` | A | perucommerce_refunds_record_external | `ExternalRefund` → `MutationResult` | PER-37 | Registrar reembolso EXTERNO verificado, no emitir |
| R8-28 | `POST /admin/orders/{id}/delivery-change` | A/O condicionado | O con orders_fulfill sólo propone; A con orders_financial_adjust aprueba si tarifa, cobertura o dinero lo requieren | `DeliveryChange` → `MutationResult` | PER-33 | O propone; A aprueba si costo/cobertura/dinero |
| R8-29 | `GET /admin/reconciliation` | A | perucommerce_reconciliation_manage | `—` → `ReconciliationPage` | PER-10 | Solo admin; no credenciales MP |
| R8-30 | `POST /admin/reconciliation/{id}/resolve` | A | perucommerce_reconciliation_manage | `ReconciliationResolve` → `MutationResult` | — | Conciliar tardío sin inventar nueva order |
| R8-31 | `GET /admin/products` | A/O | perucommerce_catalog_edit | `—` → `AdminProductPage` | PER-13, PER-14, PER-17 | Catálogo editable sin binarios privados |
| R8-32 | `POST /admin/products` | A/O | perucommerce_catalog_edit | `ProductCreate` → `MutationResult` | PER-13 | Alta en borrador, sin autopublicar |
| R8-33 | `PATCH /admin/products/{id}` | A/O | perucommerce_catalog_edit | `ProductPatch` → `MutationResult` | PER-13, PER-14 | Versionado, precio por variante |
| R8-34 | `POST /admin/products/{id}/visibility` | A/O condicionado | O hide: catalog_hide; A publish: catalog_publish | `VisibilityChange` → `MutationResult` | PER-15 | O oculta; solo A publica por defecto |
| R8-35 | `POST /admin/stock/adjustments` | A/O | perucommerce_stock_manage | `StockAdjustment` → `MutationResult` | PER-17, PER-18 | Lock de sellable y motivo |
| R8-36 | `GET /admin/agenda` | A/O | perucommerce_appointments_manage | `—` → `AgendaResult` | PER-19 | Vista operativa sin PII de otras citas |
| R8-37 | `POST /admin/agenda/closures` | A/O | perucommerce_appointments_manage | `AgendaClosure` → `MutationResult` | PER-19 | Cierres; no administra reglas semanales |
| R8-38 | `POST /admin/appointments/{id}/reschedule` | A/O | perucommerce_appointments_manage | `RescheduleAppointment` → `MutationResult` | PER-21 | Tomar nueva franja antes de liberar original |
| R8-39 | `GET /admin/notifications` | A/O | perucommerce_notifications_operate | `—` → `NotificationPage` | PER-54 | Sin expediente íntegro del Libro para O |
| R8-40 | `POST /admin/notifications/{id}/resend` | A/O | perucommerce_notifications_operate | `NotificationResend` → `MutationResult` | PER-54 | Auditar; wp_mail no prueba entrega |
| R8-41 | `GET /admin/complaints` | A | perucommerce_complaints_manage | `—` → `ComplaintPage` | PER-48 | Solo A; Libro íntegro |
| R8-42 | `POST /admin/complaints/{id}/respond` | A | perucommerce_complaints_manage | `ComplaintRespond` → `MutationResult` | PER-48 | Respuesta con constancia legal |
| R8-43 | `GET /admin/privacy-requests` | A | perucommerce_privacy_manage | `—` → `PrivacyPage` | PER-50 | Solo A; sujeto a finalidad |
| R8-44 | `POST /admin/privacy-requests/{id}/resolve` | A | perucommerce_privacy_manage | `PrivacyResolve` → `MutationResult` | PER-50 | No borrar conservación legal |
| R8-45 | `GET /admin/settings` | A | perucommerce_settings_manage | `—` → `AdminSettings` | PER-45, PER-46, PER-47, PER-55 | Secretos siempre enmascarados |
| R8-46 | `PATCH /admin/settings` | A | perucommerce_settings_manage | `SettingsPatch` → `MutationResult` | PER-44, PER-45, PER-46, PER-47, PER-55 | No desactivar derechos legales |
| R8-47 | `PUT /admin/payment-credentials` | A | perucommerce_integrations_manage | `CredentialsPut` → `MutationResult` | PER-44 | Secreto writeOnly; sin GET token |
| R8-48 | `POST /admin/digital-files` | A | perucommerce_digital_files_manage | `DigitalFileUpload` → `MutationResult` | PER-24 | Multipart almacenamiento privado |
| R8-49 | `GET /admin/diagnostics` | A | perucommerce_integrations_manage | `—` → `DiagnosticResult` | PER-53 | No pagos ni envío de email en diagnóstico |
| R8-50 | `POST /admin/privacy-exports` | A | perucommerce_privacy_manage | `PrivacyExportRequest` → `PrivacyExportResult` | PER-50 | Exportación privada solo derechos propios |
| R8-51 | `GET /admin/ubigeo-coverage` | A | perucommerce_settings_manage | `—` → `CoveragePage` | PER-45 | Cobertura tarifa actual, no snapshot previo |
| R8-52 | `PATCH /admin/ubigeo-coverage` | A | perucommerce_settings_manage | `CoveragePatch` → `MutationResult` | PER-45 | Cambio tarifario no afecta históricos |

**R8-18:** página WordPress de retorno del proveedor (no REST), nunca acredita dinero por querystring y no se registra como OpenAPI.

## 3. Las 56 operaciones de autorización PER

| PER | Ruta/mecanismo | Cobertura | Observación |
| --- | --- | --- | --- |
| PER-01 | R8-01/02/03 | **Cubierto** | Catálogo público, PHP indexable |
| PER-02 | R8-05 | **Cubierto** | Cotizar no reserva |
| PER-03 | R8-04/05 | **Cubierto** | Franja sin hold |
| PER-04 | R8-06/07 | **Condicionado** | MP requiere validación peruana |
| PER-05 | R8-08/09/21 | **Cubierto** | Sólo propio y datos filtrados |
| PER-06 | R8-20 | **Cubierto** | Paginación de instalación |
| PER-07 | R8-09/21 | **Filtrado** | Contacto según actor y tarea |
| PER-08 | R8-21 | **Parcial** | Auditoría resumida, sin logs brutos |
| PER-09 | — | **Sin endpoint** | Exportación masiva no cubierta por R8-50 |
| PER-10 | R8-09/21/29 | **Parcial** | Cliente sólo resumen de pago |
| PER-11 | R8-10/11/12 | **Parcial** | Solicitud sí, emisión administrativa de nuevo enlace no |
| PER-12 | R8-20 | **Parcial** | Búsqueda limitada, nunca pública |
| PER-13 | R8-31/32/33 | **Cubierto** | Catálogo borrador y precios |
| PER-14 | R8-31/33 | **Cubierto** | Oferta manual, precio variante |
| PER-15 | R8-34 | **Condicionado** | Operador oculta |
| PER-16 | — | **Prohibido** | Sin DELETE para productos con historial |
| PER-17 | R8-31/35 | **Cubierto** | Stock bajo permisos |
| PER-18 | R8-35 | **Cubierto** | Ajuste con motivo y lock |
| PER-19 | R8-36/37 | **Parcial** | Falta edición de horario semanal/buffers |
| PER-20 | — | **Brecha** | Falta registrar servicio REALIZADA/NO_ASISTIO |
| PER-21 | R8-38 | **Cubierto** | Reprogramación atómica |
| PER-22 | R8-23 | **Parcial** | Cancelación línea servicio sujeta a elegibilidad |
| PER-23 | R8-48 | **Parcial** | Upload admin, no GET de binario protegido |
| PER-24 | R8-48 | **Parcial** | No modificación/reemplazo completo |
| PER-25 | R8-22 | **Cubierto** | Físico PREPARANDO/LISTO |
| PER-26 | R8-22 | **Cubierto** | DESPACHADO/ENTREGADO |
| PER-27 | R8-12 | **Brecha** | Cliente puede solicitar, falta apertura staff de incidencia |
| PER-28 | — | **Brecha** | No hay resolver incidencia logística |
| PER-29 | R8-23 | **Condicionado** | Operador sin ningún dinero acreditado/ajuste |
| PER-30 | R8-23/26 | **Condicionado** | Sólo A y elegibilidad financiera |
| PER-31 | R8-26 | **Parcial** | Falta resolución derecho digital específica |
| PER-32 | R8-23/26 | **Condicionado** | Sólo tras no entrega comprobada e investigación |
| PER-33 | R8-10/11/12/28 | **Condicionado** | O propone, A aprueba cuando procede |
| PER-34 | R8-24 | **Cubierto** | Cobro neto idempotente |
| PER-35 | R8-25 | **Cubierto** | Corrección auditable sólo A |
| PER-36 | — | **Prohibido** | No refund API V1 |
| PER-37 | R8-27 | **Cubierto** | Reintegro externo comprobado por A |
| PER-38 | R8-12/15 | **Cubierto** | Pedido con verificación |
| PER-39 | R8-26/28/33 | **Parcial** | Ajustes, nunca reescritura destructiva |
| PER-40 | R8-17 + E-01 | **Automático** | Habilitar digital tras pago verificado |
| PER-41 | R8-12/13/14 | **Parcial** | No ruta admin para revocar o reemitir derecho |
| PER-42 | E-01 O-07 | **Automático** | Cierre al resolver todas las líneas/finanzas |
| PER-43 | — | **No exponer** | R8-45 sólo muestra MP conectado/enmascarado |
| PER-44 | R8-46/47 | **Cubierto** | Solo A con secretos writeOnly |
| PER-45 | R8-45/46/51/52 | **Cubierto** | Administrar tarifas y cobertura |
| PER-46 | R8-45/46 | **Parcial legal** | Régimen/documento por verificar J-03 |
| PER-47 | R8-45/46 | **Parcial legal** | Políticas sin renuncia de derechos |
| PER-48 | R8-15/41/42 | **Parcial legal** | Libro requiere J-01 definitivo |
| PER-49 | WordPress nativo | **Parcial** | Roles desde WP, no endpoint propio |
| PER-50 | R8-43/44/50 | **Parcial** | ARCO WordPress, no exportación financiera general |
| PER-51 | WordPress nativo | **Fuera REST** | ZIP y actualizaciones P0-26 |
| PER-52 | WordPress nativo | **Fuera REST** | Desinstalación sin purga automática |
| PER-53 | R8-49 | **Cubierto filtrado** | Diagnóstico sin datos sensibles |
| PER-54 | R8-10/11/12/39/40 | **Condicionado** | Cliente pide reenvío tras verificar |
| PER-55 | R8-45/46 | **Condicionado** | Solo A, respeta obligatoriedad legal |
| PER-56 | WordPress nativo / R8-19 | **Fuera REST propio** | Login WordPress y bootstrap UI |

## 4. Capabilities específicas E08-N

| Capability | Rol inicial | R8 o mecanismo | Límite adicional |
| --- | --- | --- | --- |
| `perucommerce_orders_read` | O/A | R8-20,21 | Pedido, filtros mínimos |
| `perucommerce_orders_fulfill` | O/A | R8-22 | Sólo línea física E-01 |
| `perucommerce_orders_cancel_unpaid` | O/A | R8-23 | O sólo sin dinero acreditado |
| `perucommerce_orders_financial_adjust` | A | R8-23,26,28 | Elegibilidad y ajustes |
| `perucommerce_cash_record` | O/A | R8-24 | Cobro neto |
| `perucommerce_cash_correct` | A | R8-25 | Corregir con asiento |
| `perucommerce_refunds_record_external` | A | R8-27 | Registro de reintegro, no API de cobro |
| `perucommerce_reconciliation_manage` | A | R8-29,30 | MP/inconsistencias |
| `perucommerce_catalog_edit` | O/A | R8-31,32,33 | No autopublicar |
| `perucommerce_catalog_hide` | O/A | R8-34 hide | No DELETE |
| `perucommerce_catalog_publish` | A | R8-34 publish | O sin autorización por defecto |
| `perucommerce_stock_manage` | O/A | R8-35 | Stock bajo lock |
| `perucommerce_appointments_manage` | O/A | R8-36,37,38 | Agenda sin acceso a datos ajenos |
| `perucommerce_notifications_operate` | O/A | R8-39,40 | Mínimo aviso |
| `perucommerce_settings_manage` | A | R8-45,46,51,52 | Config. comercial/legal |
| `perucommerce_integrations_manage` | A | R8-47,49 | Credenciales sólo escritura |
| `perucommerce_digital_files_manage` | A | R8-48 | Solo subir archivo privado |
| `perucommerce_complaints_manage` | A | R8-41,42 | Libro íntegro |
| `perucommerce_privacy_manage` | A | R8-43,44,50 | ARCO y exportación |
| `perucommerce_permissions_manage` | A | WP nativo | No endpoint propio, O prohibido |

## 5. Matriz de campos por actor

| Superficie / actor | Campos devueltos y finalidad | Campos prohibidos |
| --- | --- | --- |
| Público catálogo | ID público, título, precio final, oferta, variantes y disponibilidad orientativa | Archivos digitales, stock exacto, PII, credenciales |
| Público cotización | Ítems cotizados, cantidad, valor en céntimos, tarifa, total, expiración **de cotización si se define**, aviso sin reserva | Reservas ficticias, comisión MP como cargo, datos de otros |
| Invitado con enlace | Pedido propio, estados separados, cobro resumido, líneas, entrega pertinente | Documentos fiscales completos, referencias MP sensibles, terceros, bitácora y tokens |
| Operador | Pedido necesario, contacto mínimo, dirección si hay entrega, estado de cobro resumido, stock y agenda por tarea | Token MP, archivos binarios, exportaciones masivas, expediente íntegro del Libro, ARCO, refunds |
| Administrador financiero | Pedido, movimientos auditados, referencia de reintegro verificado y ajustes por finalidad | Secreto MP en claro, OTP, contraseñas, datos ajenos |
| Operador notificaciones | Prioridad, evento, resultado técnico mínimo y reenvío limitado | Contenido de reclamación, enlaces privados y datos sensibles |
| Admin Libro/ARCO | Expediente completo bajo necesidad, constancia y evidencias legalmente retenidas | Credenciales de integraciones y accesos de otra instalación |
| Diagnóstico admin | Estado InnoDB/HTTPS/streaming/cola y errores filtrados | Logs completos, archivos y datos personales |

## 6. Familias de DTO y sus precondiciones

| Familia | Petición y salida requerida | Persistencia/guardas |
| --- | --- | --- |
| Catálogo | Ficha, variantes, ID público y céntimos; paginación | Sin secretos; PHP renderiza SEO |
| Cotización | Ítems/cantidad/slot/UBIGEO/contacto condicional → quote fingerprint, líneas, PEN, disponibilidad **no reservada** | Revalidar antes de Pagar; no fijar IGV universal |
| Checkout MP | operation_id, quote_id/version, contacto y condiciones versionadas → intento/checkout **solo si** retención local válida | Clave MP durable, admisión corta, HTTP fuera SQL, 15 min desde intento válido |
| Contraentrega | Igual cotización, modalidad válida → pedido CONFIRMADO y cobro pendiente | Un evento presencial con recursos atómicos |
| Invitado | Canje de secreto → sesión read-only; challenge/verify → grant específico; solicitud → expediente PENDIENTE de revisión | No acción financiera por OTP, no cuenta comprador |
| Administrador | expected_version, operation_id, motivo/evidencia → nueva versión/evento | Estado E-01, E-03 y E-05, permisos E-02, bitácora, idempotencia |
| Archivos | Upload multipart admin, descarga vía sesión digital separada → stream privado | Sin medios públicos, MIME y límites requieren P0-19 |
| Libro/ARCO | Formulario de reclamación, expedientes, respuesta/exportación → constancias con J-01…J-05 | Campos y plazos jurídicos todavía por confirmar |
| Integraciones | Secreto MP sólo escritura, enmascarado al leer, diagnósticos no destructivos | No enviar secreto a Angular ni exponer al operador |

**Estado de DTO:** los nombres y las restricciones de esta matriz son propuestos y necesitan representación JSON Schema completa en `docs/openapi/peru-commerce-v1.openapi.draft.json` y revisión de contratos. Los casos de Libro, fiscalidad y conservación siguen condicionados a J-01…J-05.

**Propuesta documental posterior:** `docs/27-COBERTURA-OPERACIONES-P0-23-PROPUESTA-V1.md` y `docs/openapi/peru-commerce-v1-coverage-C23.PROPOSAL.openapi.json` describen 12 rutas C23 **NO RATIFICADAS**, sin modificar el inventario original E-08. Esta matriz continúa señalando las brechas de la API aprobada; no declararlas cubiertas hasta autorización.

## 7. Brechas de cobertura que Codex NO debe suplir por inferencia

1. **PER-19:** R8-37 gestiona cierres, pero falta método para editar horario semanal, duración y buffers.
2. **PER-20:** faltan REALIZADA y NO_ASISTIO de servicios; R8-22 sólo gestiona línea física.
3. **PER-27/28:** no hay endpoint de personal para abrir/resolver incidencia logística; R8-30 es conciliación **financiera**, no logística.
4. **PER-11/41:** el comprador solicita reemisión, pero no hay ruta administrativa para aprobar/revocar/reemitir el enlace o el derecho digital.
5. **PER-23/24:** R8-48 permite cargar archivo privado, no acceder binario administrativo ni modificar/sustituir archivo.
6. **PER-09:** exportación general de pedidos no existe; R8-50 sólo es exportación de privacidad.
7. **PER-49:** permisos/roles operan sobre WordPress; si habrá UI Angular independiente, su contrato específico sigue pendiente y no se autoriza crear endpoint por reflejo.
8. **P0-25/J-01…J-05:** faltan campos jurídicos completos, validación de plazos, comprobantes por régimen y tratamiento de incidencias/retención.
9. No se ha fijado TTL físico de sesión canjeada, grant de acción ni admisión técnica MP (distintos de 90d/10min/24h/15min aprobados).
10. El contrato MP, el nombre real de cookies dinámicas WordPress, MIME/streaming y el almacenamiento privado requieren verificación efectiva cuando esté autorizado.

Una brecha funcional **no** se convierte en un nuevo endpoint ni se declara resuelta por describir una tabla/DTO.

## 8. Criterios de revisión estática y estado

El inventario se obtiene del contrato E-08 (51 operaciones REST + una página WP) y E-02 (56 PER). En OpenAPI cada operación debe tener `operationId`, actor, security, `permission_callback` exigible al implementarse, DTO entrada/salida o binario, error `pc_*`, estados/guardas, idempotencia, campos y escenarios documentales. Los modelos son propuestos, no pruebas. Falta ruta aprobada para el registro de **comprobantes externos por pedido** aunque existe `pc_external_documents`, además de los demás huecos PER; **no** atribuírsele a R8-46 que solo administra configuración. Integridad real, seguridad, cumplimiento y pagos **no verificados**.

**Fuentes oficiales:** https://spec.openapis.org/oas/v3.1.1.html y https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/ .

**Estado posterior (D-176…D-187):** la cobertura de las brechas 1…5 y el registro de comprobantes externos por pedido tiene rutas de diseño R8-53…64; actor O no concedido en PER-20/27/28/31. **PER-09 exportación comercial general sigue sin ruta**. Continúan pendientes el DDL, permisos efectivos por campo/estado, pruebas MP/hosting/seguridad, validación jurídica J-01…J-05 y UX. Nada de esto autoriza implementar.
