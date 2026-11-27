# P0-23 — Matriz detallada R8, PER, DTO y permisos

**CORTE HISTÓRICO anterior al ajuste D-199:** OpenAPI principal **0.10.0-draft, 67 REST/64 rutas/134 esquemas**. Los conteos y tablas de 0.3/0.5/0.6 que describen cortes anteriores son antecedentes; el contrato vigente y las correcciones de este documento prevalecen. Ningún endpoint está implementado.

**Fecha:** 2026-11-17. **Estado:** PROPUESTA DE CONTRATO FÍSICO DOCUMENTAL derivado de D-172 / E-08 aprobados en política; no autorización para implementarlo. **Fuente de verdad:** docs/21 para 52 R8, docs/15 para 56 PER, docs/14 E-01, docs/17 E-04, docs/18 E-05, docs/19 E-06, docs/20 E-07, docs/23 modelo físico propuesto. El registro docs/22 mantiene nueve decisiones P0 aprobadas.

**Revisión legal y técnica posterior (2026-11-17):** `docs/35-CONTRATOS-LEGALES-PERMISOS-DTO-P0-23-P0-25-V1.md` asigna autorizaciones y brechas de DTO para Libro/ARCO/fiscalidad, incluyendo el antecedente de dos alternativas de detalle para LEG-04/05; **D-189 ratificó los GET privados R8-65/66 en Angular** y descartó la pantalla WordPress como diseño vigente; R8-41/43 siguen siendo listados resumidos. `docs/36-RESOLUCIONES-PROPUESTAS-DB-A01-A16-P0-18-V1.md` trata 16 problemas de estructura sin ejecutar DDL. El histórico OpenAPI 0.3.0-draft tenía 63 REST/60 rutas/113 DTO y corrigió 12 `paths` C23 con prefijo duplicado; el corte posterior 0.4.0-draft tenía 65/62/115; no cambia el namespace WordPress ni las operaciones ratificadas. No inventar rutas adicionales o salida de PII de listados.

**Actualización de fuente de verdad (D-189, 2026-11-17):** el inventario de diseño actual contiene **66 entradas R8 (65 REST + R8-18 página WordPress), 62 rutas y 115 DTO** en OpenAPI 3.1 `0.4.0-draft`. R8-65/66 consultan expedientes individuales privados exclusivamente desde Angular, con las capacidades legales existentes, sin nuevos PER ni roles. Las referencias a 64 entradas/63 REST/60 rutas/113 DTO en secciones anteriores son un **corte histórico previo**, no los conteos actuales. D-190…D-194 ratifican políticas pre-código, sin generar código ni habilitar integraciones.

**D-198 — CORTE DOCUMENTAL HISTÓRICO:** OpenAPI 3.1 `0.5.0-draft`: **68 entradas R8, 67 REST, 64 rutas, 117 DTO y 56 PER**. Tras rectificación 18-11-2026, el contrato posterior fue `0.7.0-draft` con 129 DTO, y el corte entonces posterior fue `0.10.0-draft` con 134 DTO, mismas 67 REST/64 rutas/56 PER (R8-18 sigue siendo página WordPress no REST). R8-67/68 descargan evidencia legal privada exclusivamente como administrador A con la capacidad del expediente; R8-15 devuelve `ComplaintReceiptCopy` imprimible sin exigir cuenta, **no sin los datos legales de contacto** que exija la hoja del Libro; el acceso a adjuntos se vincula por caso, instalación y finalidad, no por folio. En `x-authz` todos los actores y capabilities usan el mismo esquema canónico, sin acceso O a Libro/ARCO, y los seis DTO C23 que admitían campos desconocidos fueron cerrados. Cifras anteriores 65/62/115 son el **corte previo** a D-198. Ver docs/42.

**CORTE HISTÓRICO tras D-199/DOC-QA30 (18-11-2026):** OpenAPI `0.11.0-draft` contiene **69 entradas R8 (68 REST + R8-18 página WP), 65 rutas y 136 esquemas**, con **56 PER** y 39 tablas. La apertura manual de caso R8-69 usa capacidades existentes de A; R8-21 mantiene casos privados y R8-26 exige caso vinculado y validación propia. Las cifras 67 REST/64 rutas/134 DTO son corte histórico de 0.10.0-draft. `docs/42` documenta el cierre y los casos de aceptación futuros.

**CORTE HISTÓRICO DOC-QA31/D-200 (18-11-2026):** la fuente de ese corte fue OpenAPI **`0.12.0-draft`**: **70 entradas R8 (69 operaciones REST + R8-18 página WP), 66 rutas, 138 DTO y 56 PER**, con 39 tablas y 200 decisiones. Los títulos de «matriz vigente» de cortes previos son HISTORIA: nunca usarlos para generar controladores. **R8-70** permite finalizar una solicitud administrativa física parcial sin ajuste (reject o buyer_withdrawn con nueva legitimación) usando permiso A `orders_read` Y `orders_financial_adjust`, caso/instalación/línea/finalidad y expected_case_version. **R8-26** exige `expected_case_version` para `affected_units` y termina caso como `closed_adjusted` dentro de la misma transacción que libera compromiso y registra ajuste. No hay refund externo, cambio comercial E-01, permiso adicional O ni tabla nueva. El resultado de R8-26 usa su contrato `MutationResult`; para conocer la versión/estado actualizado del caso, A consulta R8-21 con `case_id`. **Solo documentación; no pruebas ni código.**

**PRECEDENCIA ÚNICA ACTUAL DOC-QA32/D-210 (27-11-2026):** la fuente vigente es OpenAPI **`0.13.0-draft`**: **83 entradas R8 (82 operaciones REST + R8-18 página WP), 78 rutas, 162 DTO y 62 PER**, con 41 tablas y 210 decisiones. Añade el POS V1 ratificado: **R8-71…R8-83** (13 operaciones en 12 rutas nuevas, 24 esquemas nuevos), **PER-57…PER-62** y cinco capacidades nuevas (D-204), más enmiendas aditivas de R8-20/21/24/25/27/45/46 y `ErrorResponse.data.details.reason`. El contrato detallado está en [docs/48](48-CONTRATO-POS-V1-P2.md). Las operaciones nuevas están **DOCUMENTADAS/NO IMPLEMENTADAS**, con revisión del propietario pendiente antes de P3; el código y las pruebas offline siguen en 0.12.0-draft (docs/48 §15). **Solo documentación; no pruebas ni código.**

## Invariantes de la API

Namespace `/wp-json/peru-commerce/v1`. Hay **82 operaciones REST en 78 rutas URL**, más **R8-18 página WordPress**, no REST, conforme D-189/D-198/D-210 y OpenAPI 0.13.0-draft. El contrato histórico R8-01…R8-52 mantiene sus 51 REST originales; D-176…D-187 incorporan 12 adicionales R8-53…R8-64, D-189 suma las dos consultas privadas R8-65/66 y D-210 añade las 13 operaciones POS R8-71…R8-83. Prohibido inventar endpoint de reembolso ejecutor, cobro por línea, segunda API logística o endpoint público de borrado.

Autenticación y autorización: PUBLIC sin cuenta, limitación de abuso; invitado cookie privada HttpOnly/Secure/SameSite y enlace secreto 256 bits 90 días para SOLO lectura; OTP 8 dígitos/10 min/5 intentos para acción sensible y correo registrado, comprador solo teléfono con verificación manual; token digital independiente 256 bits/24 h y derecho de archivo; empleados por WordPress cookie + `X-WP-Nonce` contra CSRF, `current_user_can` y acceso por **recurso, operación y campo**; MP webhook con HMAC, no nonce. WordPress nonce NO autentica al invitado ni concede permisos. Los roles O/A son límites iniciales: no autorizan por sí solos.

**Convención DTO propuesta:** `SqlId` JSON string decimal `^[1-9][0-9]*$` hasta BIGINT UNSIGNED; `MoneyCents` string entero de céntimos PEN (no float); deltas firmados solo cuando la operación lo exige; fechas UTC RFC3339 y zona IANA del local; UBIGEO seis cifras; cantidades enteras > 0; `expected_version` para comparación optimista, `idempotency_key` para mutación crítica durable. `contact.email` requerido cuando hay digital/servicio, `contact.phone` cuando hay físico, mixto acumula, físico puro NO exige correo. `delivery.mode` delivery/pickup/none, una tarifa de entrega por pedido, revalidada en backend.

Errores E08-M: `{code,message,data:{status,retryable,request_id,details?}}` vía WP_Error; 400 pc_invalid_input; 401 pc_auth_required; 403 pc_forbidden; 404 pc_not_found, también recurso ajeno; 409 pc_quote_changed/pc_stock_unavailable/pc_slot_unavailable/pc_state_conflict/pc_idempotency_conflict; 410 pc_link_expired; 422 pc_business_ineligible; 423 pc_operation_in_progress; 429 pc_rate_limited; 500 pc_internal_error; 503 pc_provider_unavailable. 202 pc_pending_reconciliation NO es pago acreditado.

## Matriz acumulada R8-01…R8-83 actualizada D-210 (82 REST + R8-18 página WP)

Abreviaturas de capabilities a partir de E08-N: `orders_read` significa `perucommerce_orders_read`. Todas las capacidades son propuestas sujetas a autorización por fila/campo/estado. R8 con capacidades compuestas tienen guardas distintas para operador y administrador, nunca privilegio sumado.

| Identificador | Método/ruta | PER | Identidad/capability | DTO y efecto |
| --- | --- | --- | --- | --- |
| R8-01 | GET `/public/bootstrap` | PER-01 | `PUBLIC` | `none`: sin cuerpo de entrada; proyección de campos por actor |
| R8-02 | GET `/public/products` | PER-01 | `PUBLIC` | `pagination`: page,per_page,filters; respuesta items,page,per_page,has_more; límites servidor |
| R8-03 | GET `/public/products/{id}` | PER-01 | `PUBLIC` | `id`: ID ruta como SqlId; entidad filtrada por actor y pertenencia |
| R8-04 | GET `/public/services/{id}/slots` | PER-03 | `PUBLIC` | `id,date`: Fecha local y franjas estimadas, sin hold ni promesa de disponibilidad |
| R8-05 | POST `/public/quotes` | PER-02, PER-03 | `PUBLIC` | `quote`: lines:[{sellable_id:SqlId,quantity:int}],delivery:{mode,ubigeo_code?,address?},slot?:{start_utc,end_utc},contact:{email?,phone?}; respuesta: currency=PEN, lines, subtotal_cents,delivery_cents,total_cents,quote_fingerprint,requires_confirmation. No hold |
| R8-06 | POST `/public/checkout/mercado-pago` | PER-04 | `PUBLIC_IDEMP` | `mp_checkout`: quote_fingerprint,lines,contact,idempotency_key; retorno operation_id/order_id/checkout_url solo tras intento válido y hold local 15 min; fallo proveedor se concilia, no repetir cargo |
| R8-07 | POST `/public/checkout/contraentrega` | PER-04 | `PUBLIC_IDEMP` | `cod_checkout`: quote_fingerprint,lines,contact,idempotency_key; confirmar recursos atómicamente; pago pendiente, digital sin descarga hasta cobro |
| R8-08 | POST `/guest/sessions` | PER-05, PER-11 | `GUEST_SECRET` | `exchange_secret`: tracking_secret secreto fragmento URL -> cookie HttpOnly Secure SameSite solo lectura, pedido propio, sin retornar secreto |
| R8-09 | GET `/guest/order` | PER-05, PER-07, PER-10 | `GUEST_READ` | `none`: sin cuerpo de entrada; proyección de campos por actor |
| R8-10 | POST `/guest/challenges` | PER-11, PER-33, PER-38, PER-41, PER-54 | `GUEST_READ` | `otp_request`: purpose acotado, respuesta uniforme sin enumerar cuenta; OTP 8 dígitos, 10 min, 5 intentos |
| R8-11 | POST `/guest/challenges/verify` | PER-11, PER-33, PER-38, PER-41, PER-54 | `GUEST_READ` | `otp_verify`: purpose,code; grant acotado, no sesión administrativa |
| R8-12 | POST `/guest/requests` | PER-11, PER-33, PER-38, PER-41, PER-54 | `GUEST_GRANT` | `guest_request`: purpose,reason,payload,action_grant; crea solicitud sin ejecutar ajuste/cancelación/reembolso |
| R8-13 | POST `/guest/digital/sessions` | PER-41 | `DIGITAL_SECRET` | `exchange_digital`: digital_token distinto del seguimiento; verificar derecho, pago, token 256 bits/24h |
| R8-14 | GET `/guest/digital/{right_id}/file` | PER-41 | `DIGITAL_SESSION` | `right_id`: right_id en URL; sesión digital válida; stream binario protegido Cache-Control:no-store |
| R8-15 | POST `/public/reclamaciones` | PER-38, PER-48 | `PUBLIC` | `ComplaintCreate` con idempotencia 16…128 y campos Anexo I condicionales → `ComplaintReceiptCopy` imprimible al mismo formulario, sin cuenta/compra, con campos de contacto **condicionados al Anexo I**; entrega de copia sin depender de email |
| R8-16 | GET `/public/ubigeo` | PER-01 | `PUBLIC` | `pagination`: page,per_page,filters; respuesta items,page,per_page,has_more; límites servidor |
| R8-17 | POST `/webhooks/mercado-pago` | PER-04, PER-40 | `HMAC_MP` | `webhook`: x-signature+x-request-id, antirreplay, cuenta y ambiente, inbox antes 2xx; acreditar solo con GET autoritativo |
| R8-18 | GET `/public/return` (PÁGINA WP, NO REST) | — | `WORDPRESS_PAGE` | `return`: página WP, NO REST; retorno navegador nunca acredita pago |
| R8-19 | GET `/admin/bootstrap` | PER-56 | `WP_STAFF` | `none`: sin cuerpo de entrada; proyección de campos por actor |
| R8-20 | GET `/admin/orders` | PER-06, PER-12 | `orders_read` | `pagination`: page,per_page,filters; respuesta items,page,per_page,has_more; límites servidor |
| R8-21 | GET `/admin/orders/{id}` | PER-05, PER-07, PER-08, PER-10 | `orders_read` | `id`: ID ruta como SqlId; entidad filtrada por actor y pertenencia |
| R8-22 | POST `/admin/orders/{id}/lines/{line_id}/fulfillment` | PER-25, PER-26 | `orders_fulfill` | `fulfillment`: expected_version,target,evidence,idempotency_key; solo transición E-01 permitida |
| R8-23 | POST `/admin/orders/{id}/lines/{line_id}/cancel` | PER-22, PER-29, PER-30, PER-32 | `orders_cancel_unpaid or orders_financial_adjust` | `cancel`: expected_version,reason,idempotency_key; línea completa; operador solo si jamás hubo dinero acreditado y sin efecto financiero; excepciones A |
| R8-24 | POST `/admin/orders/{id}/cash-receipts` | PER-34 | `cash_record` | `cash`: amount_cents,expected_version,idempotency_key; neto real, operación única auditada |
| R8-25 | POST `/admin/orders/{id}/cash-corrections` | PER-35 | `cash_correct` | `cash_correction`: source_event_id,delta_cents,reason,evidence,expected_version,idempotency_key; A, evento inmutable |
| R8-26 | POST `/admin/orders/{id}/adjustments` | PER-39 | `orders_financial_adjust` | `adjustment`: line_id?,delta_cents,case_id,reason,expected_version,idempotency_key; con affected_units exige expected_case_version, caso open_review de mismo pedido/línea; aplica ajuste y closed_adjusted atómicos, no refund automático |
| R8-27 | POST `/admin/orders/{id}/external-refunds` | PER-37 | `refunds_record_external` | `external_refund`: source_payment_event_id,amount_cents,external_reference,evidence,expected_version,idempotency_key; solo registro EXTERNO comprobado |
| R8-28 | POST `/admin/orders/{id}/delivery-change` | PER-33 | `orders_fulfill or orders_financial_adjust` | `delivery_change`: new_ubigeo,new_address,reason,expected_version,idempotency_key; O propone, A aprueba cambio sensible D-83 |
| R8-29 | GET `/admin/reconciliation` | PER-10 | `reconciliation_manage` | `pagination`: page,per_page,filters; respuesta items,page,per_page,has_more; límites servidor |
| R8-30 | POST `/admin/reconciliation/{id}/resolve` | — | `reconciliation_manage` | `reconciliation`: resolution,evidence,expected_version,idempotency_key; A, pago tardío exige nueva disponibilidad, sin revivir hold |
| R8-31 | GET `/admin/products` | PER-13, PER-14, PER-17 | `catalog_edit` | `pagination`: page,per_page,filters; respuesta items,page,per_page,has_more; límites servidor |
| R8-32 | POST `/admin/products` | PER-13 | `catalog_edit` | `product_create`: product_fields,idempotency_key; O crea/edita catálogo, no publica por defecto |
| R8-33 | PATCH `/admin/products/{id}` | PER-13, PER-14 | `catalog_edit` | `product_patch`: product_patch,expected_version,idempotency_key; no mutar snapshots históricos |
| R8-34 | POST `/admin/products/{id}/visibility` | PER-15 | `catalog_hide or catalog_publish` | `visibility`: visibility,reason,expected_version,idempotency_key; O puede ocultar; A publicar/republicar |
| R8-35 | POST `/admin/stock/adjustments` | PER-17, PER-18 | `stock_manage` | `stock_adjustment`: sellable_id,delta_units,reason,expected_version,idempotency_key; lock variante y auditoría |
| R8-36 | GET `/admin/agenda` | PER-19 | `appointments_manage` | `date`: fecha local e ID recurso/servicio; validar franjas y buffers, sin reserva |
| R8-37 | POST `/admin/agenda/closures` | PER-19 | `appointments_manage` | `closure`: resource_id,starts_at,ends_at,reason,idempotency_key; lock por recurso/día |
| R8-38 | POST `/admin/appointments/{id}/reschedule` | PER-21 | `appointments_manage` | `reschedule`: new_start,new_end,reason,expected_version,idempotency_key; asegurar nuevo cupo antes de liberar anterior |
| R8-39 | GET `/admin/notifications` | PER-54 | `notifications_operate` | `pagination`: page,per_page,filters; respuesta items,page,per_page,has_more; límites servidor |
| R8-40 | POST `/admin/notifications/{id}/resend` | PER-54 | `notifications_operate` | `resend`: reason,expected_version,idempotency_key; reenvío de intención, no prueba recepción |
| R8-41 | GET `/admin/complaints` | PER-48 | `complaints_manage` | Solo `ComplaintPage` resumido: `pagination` page,per_page,filters; items,page,per_page,has_more; **sin PII íntegra** |
| R8-42 | POST `/admin/complaints/{id}/respond` | PER-48 | `complaints_manage` | `complaint_response`: response_text,evidence,expected_version,idempotency_key; A, constancia jurídica J-01 |
| R8-43 | GET `/admin/privacy-requests` | PER-50 | `privacy_manage` | Solo `PrivacyPage` resumido: `pagination` page,per_page,filters; items,page,per_page,has_more; **sin evidencia íntegra** |
| R8-44 | POST `/admin/privacy-requests/{id}/resolve` | PER-50 | `privacy_manage` | `privacy_resolution`: resolution,legal_basis,evidence,expected_version,idempotency_key; A, retener datos legalmente exigidos |
| R8-45 | GET `/admin/settings` | PER-45, PER-46, PER-47, PER-55 | `settings_manage` | `none`: sin cuerpo de entrada; proyección de campos por actor |
| R8-46 | PATCH `/admin/settings` | PER-44, PER-45, PER-46, PER-47, PER-55 | `settings_manage` | `settings_patch`: settings_patch,expected_version,idempotency_key; A, auditar, no desactivar obligación legal |
| R8-47 | PUT `/admin/payment-credentials` | PER-44 | `integrations_manage` | `credential_update`: credential_update,expected_version,idempotency_key; solo servidor; jamás devolver secreto |
| R8-48 | POST `/admin/digital-files` | PER-24 | `digital_files_manage` | `file_upload`: file_upload,metadata,idempotency_key; binario privado; bloquear si hosting lo expone |
| R8-49 | GET `/admin/diagnostics` | PER-53 | `settings_manage` | `none`: sin cuerpo de entrada; proyección de campos por actor |
| R8-50 | POST `/admin/privacy-exports` | PER-50 | `privacy_manage` | `privacy_export`: scope,reason,verified_identity,idempotency_key; A, exportación privada temporal |
| R8-51 | GET `/admin/ubigeo-coverage` | PER-45 | `settings_manage` | `none`: sin cuerpo de entrada; proyección de campos por actor |
| R8-52 | PATCH `/admin/ubigeo-coverage` | PER-45 | `settings_manage` | `coverage_patch`: coverage_patch,expected_version,idempotency_key; A, tarifas futuras sin alterar pedidos |
| R8-53 | PATCH `/admin/agenda/schedule` | PER-19 | O/A: perucommerce_appointments_manage | `C2301Request → C2301Response`: Horario semanal y buffers; ninguna cita se desplaza por inferencia |
| R8-54 | POST `/admin/appointments/{id}/outcome` | PER-20 | A: perucommerce_appointments_manage | `C2302Request → C2302Response`: Sólo CONFIRMADA→REALIZADA/NO_ASISTIO, evidencia; NO multa automática |
| R8-55 | POST `/admin/orders/{id}/lines/{line_id}/logistics-incidents` | PER-27 | A: perucommerce_orders_fulfill | `C2303Request → C2303Response`: Abre incidencia sin cambiar entrega, pedido, stock o dinero |
| R8-56 | POST `/admin/logistics-incidents/{id}/proposals` | PER-28 | A: perucommerce_orders_fulfill | `C2304Request → C2304Response`: Propuesta sin efectos logísticos/financieros |
| R8-57 | POST `/admin/logistics-incidents/{id}/resolve` | PER-28, PER-32 | A: perucommerce_orders_financial_adjust Y perucommerce_orders_fulfill | `C2305Request → C2305Response`: Cierra expediente; ejecutar otros efectos por operaciones E-08 separadas |
| R8-58 | POST `/admin/orders/{id}/tracking-links/reissue` | PER-11 | A: perucommerce_privacy_manage Y perucommerce_orders_read | `C2306Request → C2306Response`: Identidad verificada, enlace 90 días una vez, previo revocado |
| R8-59 | POST `/admin/digital-rights/{id}/links/reissue` | PER-41 | A: perucommerce_digital_files_manage | `C2307Request → C2307Response`: Token 24h rotado, derecho y pago siguen |
| R8-60 | POST `/admin/digital-rights/{id}/revoke` | PER-41 | A: perucommerce_digital_files_manage | `C2308Request → C2308Response`: Sólo causa legítima con expediente, sin refund |
| R8-61 | POST `/admin/digital-cases/{id}/decide` | PER-31 | A: perucommerce_digital_files_manage | `C2309Request → C2309Response`: Decide caso sin revocar ni pagar automáticamente |
| R8-62 | GET `/admin/digital-files/{id}/download` | PER-23 | A: perucommerce_digital_files_manage | `— → binario privado`: Sólo A y finalidad verificada, streaming no-cache |
| R8-63 | POST `/admin/digital-files/{id}/replacements` | PER-24 | A: perucommerce_digital_files_manage | `C2311Request → C2311Response`: Nuevo binario futuro, versión anterior histórica intacta |
| R8-64 | POST `/admin/orders/{id}/external-documents` | D-26/J-03 (sin PER de alta todavía) | A: perucommerce_settings_manage Y perucommerce_orders_read | `C2312Request → C2312Response`: Sólo referencia externa ya emitida, sin emisión SUNAT |
| R8-65 | GET `/admin/complaints/{id}` | PER-48 | A: perucommerce_complaints_manage | `— → ComplaintDetail`: expediente individual, finalidad/instalación/permisos, no-store; sin acceso O |
| R8-66 | GET `/admin/privacy-requests/{id}` | PER-50 | A: perucommerce_privacy_manage | `— → PrivacyRequestDetail`: legitimación/instalación/finalidad, no-store; sin acceso O |
| R8-67 | GET `/admin/complaints/{id}/evidence/{evidence_ref}` | PER-48 | A: perucommerce_complaints_manage | `— → PrivateBinary`: evidencia de ese Libro/instalación/finalidad, manifest privado, auditoría, no-store/nosniff/attachment |
| R8-68 | GET `/admin/privacy-requests/{id}/evidence/{evidence_ref}` | PER-50 | A: perucommerce_privacy_manage | `— → PrivateBinary`: evidencia ARCO del caso/instalación, finalidad y legitimación; nunca URL pública/PII a O |
| **R8-69** | **POST `/admin/orders/{id}/cases`** | **PER-38/39 sin nuevas operaciones** | **Solo A: perucommerce_orders_read Y perucommerce_orders_financial_adjust** | `AdminManualCaseCreate → AdminManualCaseOpened`: apertura manual verificada de caso de reducción física parcial, sin ajuste, refund, cancelación ni permiso O. 201 nuevo/200 replay; relectura R8-21 y decisión por R8-26 |
| **R8-70** | **POST `/admin/orders/{id}/cases/{case_id}/close`** | **PER-38/39 sin nuevos permisos** | **Solo A: perucommerce_orders_read Y perucommerce_orders_financial_adjust** | `AdminManualCaseCloseRequest → AdminManualCaseClosed`: rechazar o registrar retiro verificado de expediente físico parcial `open_review`, CAS `expected_case_version`, idempotencia/evento/auditoría; `closed_rejected`/`closed_withdrawn`. **No dinero/stock/estados E-01, no acceso O** |
| R8-71 | GET `/admin/pos/catalog` | PER-57 | A/O: pos_sell | `q,page,per_page → PosCatalogPage`: vendibles físicos publicados con precio y stock del servidor; sin costo ni datos de proveedor. Puerta `pos.read` (D-210, DOCUMENTADO/NO IMPLEMENTADO) |
| R8-72 | POST `/admin/pos/sales` | PER-57 | A/O: pos_sell y sesión propia abierta | `PosSaleCommit → 201 PosSaleResult`: O-10 atómico (pedido `pos` FINALIZADO, líneas ENTREGADO, salida de stock, `initial_charge` por R, `cash_rounding` 0–9, `sale_cash`); idempotencia durable; 409 `pc_quote_changed` si los totales mostrados difieren. Puerta `pos.write` |
| R8-73 | POST `/admin/pos/sessions` | PER-58 | A/O: cash_session_operate | `CashSessionOpen → 201 CashSession`: abre sesión con fondo inicial; una abierta por caja y por persona (409 `pc_conflict`, `reason = pos_register_busy` o `pos_session_already_open`). Puerta `pos.sessions` |
| R8-74 | GET `/admin/pos/sessions/current` | PER-58 | A/O: cash_session_operate | `→ CashSession`: sesión abierta propia, **sin esperado ni diferencia** (arqueo ciego). Puerta `pos.read` |
| R8-75 | POST `/admin/pos/sessions/{id}/movements` | PER-60 | Solo A: cash_movements_record | `CashMovementCreate → MutationResult`: `pay_in`/`pay_out` con motivo, CAS `expected_version`; sesión cerrada 409 `pc_conflict` (`reason = pos_session_closed`). **Sin acceso O en V1**. Puerta `pos.sessions` |
| R8-76 | POST `/admin/pos/sessions/{id}/close` | PER-58 | A/O: cash_session_operate y sesión propia | `CashSessionClose → CashSessionCloseResult`: arqueo ciego; el servidor fija esperado/contado/diferencia inmutables; esperado y diferencia solo con `cash_session_review`. Puerta `pos.sessions` |
| R8-77 | GET `/admin/pos/sessions` | PER-59 | Solo A: cash_session_review | `page,per_page,state,register_code,business_date → CashSessionPage`: resumen con esperado/contado/diferencia. Puerta `pos.read` |
| R8-78 | GET `/admin/pos/sessions/{id}` | PER-59 | Solo A: cash_session_review | `→ CashSessionDetail`: sesión y movimientos inmutables. Puerta `pos.read` |
| R8-79 | POST `/admin/pos/sessions/{id}/force-close` | PER-59 | Solo A: cash_session_review | `CashSessionForceClose → CashSessionCloseResult`: cierre de sesión ajena con motivo; sin conteo, diferencia pendiente de revisión. Puerta `pos.sessions` |
| R8-80 | GET `/admin/pos/documents/pending` | PER-61 | A/O: external_documents_record | `page,per_page → PosDocumentPendingPage`: pedidos `pos` sin comprobante de venta o con nota de crédito pendiente; **sin número de documento del adquirente**. Puerta `pos.read` |
| R8-81 | POST `/admin/pos/orders/{id}/external-documents` | PER-61 | A/O: external_documents_record; `nota_credito` además refunds_record_external (solo A en V1) | `PosExternalDocumentRecord → 201 C2312Response`: referencia del comprobante **emitido externamente** (D-206), importe igual a T o al reembolso del expediente; solo `buyer_id_type`. No emite ni valida SUNAT. Puerta `pos.write` |
| R8-82 | GET `/admin/pos/orders/{id}/receipt` | PER-57 | A/O: pos_sell Y orders_read; O solo su propia sesión | `→ PosOperationReceipt`: constancia «no es comprobante de pago», solo pedidos `pos`. Puerta `pos.read` |
| R8-83 | POST `/admin/pos/orders/{id}/returns` | PER-62 | Solo A: orders_read Y refunds_record_external Y stock_manage | `PosReturnRecord → 201 PosReturnResult`: devolución o anulación atómica (expediente por línea, reingreso solo `fit_for_sale`, reembolso en efectivo ≤ R − previos, `refund_cash` solo con `cash_session_id`); línea sigue ENTREGADO y pedido FINALIZADO. Revisión del propietario antes de P3. Puerta `pos.write` |


**Extensión ratificada D-176…D-187:** estas 12 entradas fueron incorporadas al contrato E-08 como D-176…D-187; requisitos de implementación y validación pendientes.

## Matriz inversa de las 62 operaciones PER

Una correspondencia expresa relación funcional, **no autorización**. PER-36 ejecutar reembolso por API sigue PROHIBIDO. Operaciones sin R8 (roles/ZIP/desinstalación/cuenta WP o efectos solo automáticos) no justifican endpoint nuevo. PER-20 marcar servicio realizado y PER-42 cerrar pedido son huecos de mutación explícita, no se implementarán a través de un `set_status` genérico no aprobado.

| PER | Nombre funcional | R8 relacionado |
| --- | --- | --- |
| PER-01 | Ver catálogo público/ficha/precio | R8-01/02/03 |
| PER-02 | Crear carrito y cotizar | R8-05 |
| PER-03 | Seleccionar fecha/hora de servicio | R8-04/05 |
| PER-04 | Confirmar contraentrega elegible / iniciar pago | R8-06/07 |
| PER-05 | Consultar estado del **propio** pedido | R8-08/09/21 |
| PER-06 | Listar todos los pedidos de la instalación | R8-20 |
| PER-07 | Consultar detalle con contacto/dirección | R8-09/21 |
| PER-08 | Ver auditoría de un pedido | R8-21 |
| PER-09 | Exportar listado de pedidos (EXCLUIDO V1 D-188) | — |
| PER-10 | Consultar historial de movimientos monetarios | R8-09/21/29 |
| PER-11 | Obtener/reemitir enlace de pedido a tercero | R8-10/11/12, R8-58 |
| PER-12 | Buscar pedido por nombre/teléfono/correo | R8-20 |
| PER-13 | Crear/editar producto simple o variable | R8-31/32/33 |
| PER-14 | Activar/desactivar oferta manual y precios por variante | R8-31/33 |
| PER-15 | Publicar/ocultar productos | R8-34 |
| PER-16 | Borrar definitivamente producto con pedidos vinculados | — |
| PER-17 | Consultar/actualizar stock de variante | R8-31/35 |
| PER-18 | Cambiar manualmente stock de variante | R8-35 |
| PER-19 | Editar calendario semanal, buffers y cierres | R8-36/37, R8-53 |
| PER-20 | Registrar servicio realizado/no-asistencia | —, R8-54 |
| PER-21 | Reprogramar cita confirmada | R8-38 |
| PER-22 | Cancelar cita pendiente sin cobro acreditado | R8-23 |
| PER-23 | Acceder a fichero protegido de producto digital como empleado | R8-48, R8-62 |
| PER-24 | Modificar URL/almacenamiento de descargas digitales | R8-48, R8-63 |
| PER-25 | Marcar línea física PREPARANDO/LISTO | R8-22 |
| PER-26 | Registrar DESPACHADO, ENTREGADO o recojo | R8-22 |
| PER-27 | Abrir incidencia logística por DESPACHADO no entregado | R8-12, R8-55 |
| PER-28 | Resolver incidencia logística | —, R8-56, R8-57 |
| PER-29 | Cancelar línea pendiente sin dinero acreditado ni ajustes | R8-23 |
| PER-30 | Cancelar línea con dinero alguna vez acreditado | R8-23/26 |
| PER-31 | Resolver digital HABILITADO sin descarga al pedir cancelación | R8-26, R8-61 |
| PER-32 | Cancelar DESPACHADO no entregado tras investigación | R8-23/26, R8-57 |
| PER-33 | Cambiar dirección confirmada | R8-10/11/12/28 |
| PER-34 | Registrar efectivo realmente recibido | R8-24 |
| PER-35 | Corregir cobro efectivo histórico | R8-25 |
| PER-36 | Ejecutar reembolso mediante API del plugin | — |
| PER-37 | Registrar reembolso externo **total o parcial** comprobado | R8-27 |
| PER-38 | Solicitar devolución/reclamación | R8-12/15/69/70 |
| PER-39 | Corregir pedido confirmado | R8-26/28/33/69/70 |
| PER-40 | Marcar línea digital HABILITADA | R8-17 + E-01 |
| PER-41 | Revocar/reemitir descarga digital | R8-12/13/14, R8-59, R8-60 |
| PER-42 | Marcar pedido FINALIZADO | E-01 O-07 |
| PER-43 | Leer credenciales Mercado Pago / proveedor mapas | — |
| PER-44 | Crear/cambiar/retirar credenciales o configuración sensible | R8-46/47 |
| PER-45 | Configurar UBIGEO/cobertura/tarifas | R8-45/46/51/52 |
| PER-46 | Configurar régimen/boleta/factura/recibo externo | R8-45/46 |
| PER-47 | Configurar políticas de cancelación/garantía | R8-45/46 |
| PER-48 | Leer/procesar Libro de Reclamaciones | R8-15/41/42/65/67 |
| PER-49 | Cambiar rol/permisos de operador | WordPress nativo |
| PER-50 | Exportar datos personales o financieros | R8-43/44/50/66/68 |
| PER-51 | Instalar/actualizar ZIP del plugin | WordPress nativo |
| PER-52 | Desinstalar/eliminar datos históricos | WordPress nativo |
| PER-53 | Consultar log de errores de integración | R8-49 |
| PER-54 | Solicitar reenvío de notificación transaccional | R8-10/11/12/39/40 |
| PER-55 | Activar/desactivar módulos de pago/mapa/reclamaciones | R8-45/46 |
| PER-56 | Gestionar cuenta y sesión propia WordPress | WordPress nativo / R8-19 |
| PER-57 | Vender en caja presencial y reimprimir la constancia (D-210, DOCUMENTADO/NO IMPLEMENTADO) | R8-71 / R8-72 / R8-82 |
| PER-58 | Operar la propia sesión de caja: abrir, consultar y cerrar con arqueo ciego | R8-73 / R8-74 / R8-76 |
| PER-59 | Revisar sesiones de caja y forzar su cierre (solo A) | R8-77 / R8-78 / R8-79 |
| PER-60 | Registrar ingresos y retiros justificados de caja (solo A en V1) | R8-75 |
| PER-61 | Registrar la referencia del comprobante emitido externamente para pedidos POS | R8-80 / R8-81 |
| PER-62 | Registrar devolución o anulación presencial POS (solo A) | R8-83 |

## Proyección de campos por actor

| Familia DTO | Público | Invitado mismo pedido | Operador | Administrador |
| --- | --- | --- | --- | --- |
| Producto | id, nombre, tipo, precios finales y disponibilidad aproximada | igual | SKU, stock, campos catálogo según tarea | catálogo completo permitido sin archivo protegido por GET |
| Pedido | no existe GET por ID público | order_number, líneas, total_cents, estados públicos de pago/cumplimiento, entrega resumida | ID, línea, contacto mínimo para atención y entrega | detalle administrativo autorizado, sin secretos |
| Pagos | ninguno | resumen del dinero propio sin comisiones/identificadores secretos | indicador mínimo de cobro, no movimientos privados de reembolsos | movimientos históricos y reintegros comprobados con finalidad |
| Libro/ARCO | formulario público y constancia bajo verificación legal | solo presentación/estado propio verificado | alerta operacional sin expediente/relato | expediente íntegro bajo finalidad y norma |
| Digital | descripción comercial sin URL de archivo | derecho propio y descarga tras validación | sin acceso a fichero protegido | gestión y stream autorizado según finalidad |
| Credenciales/configuración | nombre comercial y datos inocuos | ninguno | banderas UI autorizadas | configuración sensible sin devolver credencial MP en claro |

**Denegar por defecto** cualquier campo no enumerado para el actor. Nunca enviar todos los campos al navegador y ocultarlos en Angular. Rechazar propiedades adicionales en mutaciones y no hacer mass assignment de JSON a SQL.

## Guardas obligatorias E-01…E-08

- R8-06: admisión breve, HTTP Mercado Pago Orders fuera de SQL, hold de 15 minutos solo desde intento válido; sin correo en físico puro omitir payer; item Entrega y `PT15M` requieren verificación real de cuenta peruana. Si falla, bloquear MP sin cambiar de modalidad o forzar correo.
- R8-07: contraentrega solo si todo el pedido se cobra presencialmente en un evento, compromiso atómico de stock/cita; pago aún pendiente, digitales BLOQUEADO.
- R8-17: firmar/verificar cuenta/ambiente y deduplicar inbox antes de responder 2xx; GET al proveedor para verificar acreditación, no interpretar retorno del navegador como pago.
- R8-23: operador nunca cancela línea si se acreditó **alguna vez** dinero, aunque saldo neto sea cero. Cancelaciones excepcionales, ajustes financieros y reembolsos externos comprobados requieren autorización administrativa. No reescribir snapshots.
- R8-28: operador propone dirección, solo administrador aprueba cuando cambió costo/cobertura o hay dinero acreditado, sin segundo cargo automático.
- R8-34: operador puede ocultar; publicar/republicar por administrador. R8-38: asegurar nueva franja bajo lock antes de liberar anterior.
- R8-15: Libro no exige cuenta ni compra, datos y plazos sujetos a J-01. R8-14: archivos privados en stream, Cache-Control:no-store, sin ruta HTTP directa; bloquear funcionalidad digital en hosting inseguro.
- R8-49: diagnóstico es lectura; no pruebas reales de email/MP sin autorización. R8-27 **registra**, jamás ejecuta reembolso.
- Los DTO de fiscalidad, campos de Libro, conservación legal, formatos y exportaciones se concretan según J-01…J-05; el esquema propuesto no demuestra cumplimiento legal.

**Investigación legal posterior (2026-11-17):** `docs/25-MATRICES-NORMATIVAS-J01-J05-P0-25-V1.md` y `docs/26-CONTRATO-TRIBUTARIO-P0-17-J03-V1.md` describen obligaciones de Libro, privacidad y referencias fiscales. Los DTO legales de R8-15/41/42/43/44/45/46/50 **no se completan automáticamente** por esos análisis; requieren esquema por campo ratificado, normativa vigente y permisos. No crear impuestos calculados en R8-05/06/07.

## Compatibilidad con P0-18 y huecos físicos

Persistencia: catálogo WP/pc_sellables; checkout pc_orders,pc_order_lines,pc_idempotency,pc_resource_admissions,pc_payment_attempts,pc_holds; COD pc_stock_commitments,pc_appointments; Webhook pc_webhook_inbox/pc_payment_events/outbox; agenda pc_calendar_days/pc_appointment_history; tokens pc_guest_sessions,pc_otp_challenges,pc_action_grants,pc_download_rights; reclamaciones pc_complaints; privacidad pc_privacy_requests; auditoría pc_audit_log. P0-18 es aún propuesta, NO DDL definitivo.

**OpenAPI del corte histórico D-198/DOC-QA27…29:** `docs/openapi/peru-commerce-v1.openapi.draft.json` **0.10.0-draft**, con **67 REST, 64 rutas y 134 DTO**; los 63/60/113 corresponden al corte histórico C23. Los 12 C23 se aprobaron en cobertura D-176…D-187; los campos legales y la seguridad por entorno siguen en revisión. No existe API ejecutable.

Era **pendiente en el corte histórico completar OpenAPI 3.1 por operación** con `requestBody`, `required`, tipos, límites, respuestas y errores, además del permiso por campo/estado en rutas duales, DDl y la matriz J-01…J-05. Ningún controlador podrá generarse como si esta matriz sustituyera esos contratos.

**Estado histórico D-200 (18-11-2026):** 70/70 entradas R8 documentadas, 69 REST (R8-18 no REST) y 56/56 PER rastreados; los 12 C23 incorporados por D-176…D-187.

**Estado vigente D-210 (27-11-2026):** 83/83 entradas R8 documentadas, 82 REST (R8-18 no REST) y 62/62 PER rastreados; R8-71…R8-83 y PER-57…PER-62 del POS V1 incorporados por D-210 como **DOCUMENTADO/NO IMPLEMENTADO**. Los campos legales, DDL y seguridad siguen pendientes de validación, y NO existe API implementada.


## Matriz acumulada de DTO y permisos R8 (actualizada D-210; filas C23 originales históricas)

**Estado vigente D-210:** Contrato DOCUMENTAL, no implementación. Fuente estructurada: `docs/openapi/peru-commerce-v1.openapi.draft.json` `0.13.0-draft`. Esta tabla acumulada incluye las **82 operaciones REST** (R8-18 sigue fuera del OpenAPI), con los 12 R8-53…64 ratificados en cobertura y las 13 R8-71…83 del POS V1 pendientes de revisión del propietario antes de P3 ([docs/48](48-CONTRATO-POS-V1-P2.md)). Es una vista humana de **propiedades superiores**; para tipos, requeridos, objetos anidados, proyección y errores consultar el esquema correspondiente. Los DTO legales están identificados como sujetos a ratificación J-01…J-05.

| R8 | PER vigente | Esquema request → response | Campos entrada (nivel superior) | Campos salida (nivel superior) | Capability/actor |
| --- | --- | --- | --- | --- | --- |
| R8-01 | PER-01 | `—` → `PublicBootstrap` | sin body | business_name, locale, currency, supports_pickup, supports_delivery, complaints_url, payment_methods | none |
| R8-02 | PER-01 | `—` → `ProductPage` | sin body | items, page, per_page, has_more | none |
| R8-03 | PER-01 | `—` → `ProductPublic` | sin body | id, title, kind, description, price_cents, sale_price_cents, sale_active, can_order, variants | none |
| R8-04 | PER-03 | `—` → `SlotPage` | sin body | service_id, date, slots | none |
| R8-05 | PER-02, PER-03 | `QuoteRequest` → `QuoteResponse` | lines, delivery, contact | quote_fingerprint, currency, lines, subtotal_cents, delivery_cents, total_cents, requires_confirmation, warnings | none |
| R8-06 | PER-04 | `CheckoutRequest` → `CheckoutMPResponse` | quote_fingerprint, lines, delivery, contact, idempotency_key, policy_version, privacy_version | operation_id, order_id, order_state, payment_state, checkout_url, hold_expires_at_utc, pending_reconciliation | none |
| R8-07 | PER-04 | `CheckoutRequest` → `CheckoutCODResponse` | quote_fingerprint, lines, delivery, contact, idempotency_key, policy_version, privacy_version | operation_id, order_id, order_state, payment_due_cents, payment_state | none |
| R8-08 | PER-05, PER-11 | `GuestSessionExchange` → `SessionAck` | tracking_secret | scope, expires_at_utc | none |
| R8-09 | PER-05, PER-07, PER-10 | `—` → `GuestOrder` | sin body | order_number, created_at, order_state, currency, lines, total_cents, payment_summary, delivery_summary, appointment_summary | none |
| R8-10 | PER-11, PER-33, PER-38, PER-41, PER-54 | `ChallengeRequest` → `ChallengeAccepted` | purpose | accepted, delivery_hint | none |
| R8-11 | PER-11, PER-33, PER-38, PER-41, PER-54 | `ChallengeVerify` → `ActionGrantResult` | purpose, code | accepted, scope, grant_expires_at_utc | none |
| R8-12 | PER-11, PER-33, PER-38, PER-41, PER-54 | `GuestActionRequest` → `CaseAccepted` | purpose, reason, requested_ubigeo, requested_address, case_details | case_id, accepted, review_required | none |
| R8-13 | PER-41 | `DigitalSessionExchange` → `DigitalSessionAck` | digital_token | right_id, expires_at_utc | none |
| R8-14 | PER-41 | `—` → `PrivateBinary` | sin body | binario/— | none |
| R8-15 | PER-38, PER-48 | `ComplaintCreate` → `ComplaintReceipt` + `ComplaintReceiptCopy` | kind, claimant, representative?, subject, claimed_amount_cents?, description, request, idempotency_key, datos condicionales J-01 | receipt_number, submitted_at, merchant_name?, receipt_reference?, receipt_copy (hoja íntegra imprimible) | none; mismo formulario y contexto, no GET por folio |
| R8-16 | PER-01 | `—` → `UbigeoPage` | sin body | items, dataset_version, page, per_page, has_more | none |
| R8-17 | PER-04, PER-40 | `WebhookNotice` → `WebhookAck` | type, data, action | received | none |
| R8-19 | PER-56 | `—` → `AdminBootstrap` | sin body | user_id, role_label, ui_capabilities, business_name | staff |
| R8-20 | PER-06, PER-12 | `—` → `AdminOrdersPage` | sin body | items, page, per_page, has_more | orders_read |
| R8-21 | PER-05, PER-07, PER-08, PER-10 | `—` → `AdminOrder` | sin body | id, order_number, order_state, row_version, lines, total_cents, contact_for_task, payment_indicator, delivery_snapshot, operational_events | orders_read |
| R8-22 | PER-25, PER-26 | `FulfillmentChange` → `MutationResult` | target, expected_version, evidence, idempotency_key | operation_id, entity_id, row_version, result, public_status | orders_fulfill |
| R8-23 | PER-22, PER-29, PER-30, PER-32 | `CancelLine` → `MutationResult` | expected_version, reason, idempotency_key, case_id | operation_id, entity_id, row_version, result, public_status | orders_cancel_unpaid|orders_financial_adjust |
| R8-24 | PER-34 | `CashReceipt` → `MutationResult` | amount_cents, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | cash_record |
| R8-25 | PER-35 | `CashCorrection` → `MutationResult` | source_event_id, delta_cents, reason, evidence, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | cash_correct |
| R8-26 | PER-39 | `FinancialAdjustment` → `MutationResult` | line_id, delta_cents, case_id, reason, expected_version, idempotency_key, affected_units?, unit_effect?, expected_case_version? condicional | operation_id, entity_id, row_version, result, public_status | orders_financial_adjust |
| R8-27 | PER-37 | `ExternalRefundRecord` → `MutationResult` | source_payment_event_id, amount_cents, external_reference, evidence, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | refunds_record_external |
| R8-28 | PER-33 | `DeliveryChange` → `MutationResult` | new_ubigeo, new_address, reason, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | orders_read|orders_financial_adjust |
| R8-29 | PER-10 | `—` → `ReconciliationPage` | sin body | items, page, per_page, has_more | reconciliation_manage |
| R8-30 | — | `ReconciliationResolve` → `MutationResult` | resolution, evidence, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | reconciliation_manage |
| R8-31 | PER-13, PER-14, PER-17 | `—` → `AdminCatalogPage` | sin body | items, page, per_page, has_more | catalog_edit |
| R8-32 | PER-13 | `ProductCreate` → `AdminProduct` | title, kind, description, price_cents, variants, idempotency_key | id, post_id, title, kind, visibility, variants, row_version | catalog_edit |
| R8-33 | PER-13, PER-14 | `ProductPatch` → `AdminProduct` | title, description, price_cents, sale_price_cents, sale_active, variants, expected_version, idempotency_key | id, post_id, title, kind, visibility, variants, row_version | catalog_edit |
| R8-34 | PER-15 | `VisibilityChange` → `AdminProduct` | visibility, reason, expected_version, idempotency_key | id, post_id, title, kind, visibility, variants, row_version | catalog_hide|catalog_publish |
| R8-35 | PER-17, PER-18 | `StockAdjustment` → `MutationResult` | sellable_id, delta_units, reason, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | stock_manage |
| R8-36 | PER-19 | `—` → `AgendaView` | sin body | resource_id, date, timezone, appointments, closures | appointments_manage |
| R8-37 | PER-19 | `ClosureCreate` → `MutationResult` | resource_id, starts_at, ends_at, reason, idempotency_key | operation_id, entity_id, row_version, result, public_status | appointments_manage |
| R8-38 | PER-21 | `AppointmentReschedule` → `MutationResult` | new_start, new_end, reason, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | appointments_manage |
| R8-39 | PER-54 | `—` → `NotificationPage` | sin body | items, page, per_page, has_more | notifications_operate |
| R8-40 | PER-54 | `NotificationResend` → `MutationResult` | reason, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | notifications_operate |
| R8-41 | PER-48 | `—` → `ComplaintPage` | sin body | items, page, per_page, has_more | complaints_manage |
| R8-42 | PER-48 | `ComplaintRespond` → `MutationResult` | response_text, evidence, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | complaints_manage |
| R8-43 | PER-50 | `—` → `PrivacyPage` | sin body | items, page, per_page, has_more | privacy_manage |
| R8-44 | PER-50 | `PrivacyResolution` → `MutationResult` | resolution, legal_basis, evidence, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | privacy_manage |
| R8-45 | PER-45, PER-46, PER-47, PER-55 | `—` → `AdminSettings` | sin body | business, fiscal, delivery_enabled, payment_enabled, webhook_connected, provider_configured, row_version | settings_manage |
| R8-46 | PER-44, PER-45, PER-46, PER-47, PER-55 | `SettingsPatch` → `AdminSettings` | business, fiscal, delivery_enabled, payment_enabled, expected_version, idempotency_key | business, fiscal, delivery_enabled, payment_enabled, webhook_connected, provider_configured, row_version | settings_manage |
| R8-47 | PER-44 | `PaymentCredentialUpdate` → `MutationResult` | environment, access_token, webhook_secret, expected_version, idempotency_key | operation_id, entity_id, row_version, result, public_status | integrations_manage |
| R8-48 | PER-24 | `—` → `DigitalFileMetadata` | file, product_id, filename, idempotency_key | file_id, product_id, filename, mime_type, size_bytes, protected | digital_files_manage |
| R8-49 | PER-53 | `—` → `DiagnosticsSafe` | sin body | https_available, db_engine, db_transactions_supported, private_storage_status, email_transport_status, pending_jobs | settings_manage |
| R8-50 | PER-50 | `PrivacyExportRequest` → `PrivacyExportAck` | scope, reason, verified_identity, idempotency_key | operation_id, accepted, expires_at_utc | privacy_manage |
| R8-51 | PER-45 | `—` → `CoverageView` | sin body | items, row_version, dataset_version | settings_manage |
| R8-52 | PER-45 | `CoveragePatch` → `CoverageView` | entries, expected_version, idempotency_key | items, row_version, dataset_version | settings_manage |
| R8-53 | PER-19 | `C2301Request` → `C2301Response` | resource_id, effective_from, weekly_rules, reason, expected_version, idempotency_key | resource_id, row_version, effective_from, operation_id | perucommerce_appointments_manage (O/A) |
| R8-54 | PER-20 | `C2302Request` → `C2302Response` | outcome, observed_at_utc, evidence_ref, expected_version, idempotency_key | operation_id, result, row_version | perucommerce_appointments_manage (sólo A) |
| R8-55 | PER-27 | `C2303Request` → `C2303Response` | reason, evidence_ref, expected_version, idempotency_key | case_id, operation_id, case_phase | perucommerce_orders_fulfill (sólo A) |
| R8-56 | PER-28 | `C2304Request` → `C2304Response` | resolution_proposal, evidence_ref, reason, expected_version, idempotency_key | case_id, operation_id, case_phase | perucommerce_orders_fulfill (sólo A) |
| R8-57 | PER-28, PER-32 | `C2305Request` → `C2305Response` | decision, evidence_ref, reason, expected_version, idempotency_key | case_id, operation_id, case_phase | perucommerce_orders_financial_adjust Y perucommerce_orders_fulfill (sólo A) |
| R8-58 | PER-11 | `C2306Request` → `C2306Response` | identity_verification_ref, original_verified_channel, reason, expected_version, idempotency_key | operation_id, url_once, expires_at_utc | perucommerce_privacy_manage Y perucommerce_orders_read (sólo A) |
| R8-59 | PER-41 | `C2307Request` → `C2307Response` | identity_verification_ref, reason, expected_version, idempotency_key | operation_id, url_once, expires_at_utc | perucommerce_digital_files_manage (sólo A) |
| R8-60 | PER-41 | `C2308Request` → `C2308Response` | case_id, legal_reason, evidence_ref, expected_version, idempotency_key | operation_id, result, row_version | perucommerce_digital_files_manage (sólo A) |
| R8-61 | PER-31 | `C2309Request` → `C2309Response` | decision, evidence_ref, reason, expected_version, idempotency_key | case_id, operation_id, case_phase | perucommerce_digital_files_manage (sólo A) |
| R8-62 | PER-23 | `—` → `PrivateBinary` | sin body | binario/no JSON | perucommerce_digital_files_manage (sólo A) |
| R8-63 | PER-24 | `C2311Request` → `C2311Response` | file, reason, expected_version, idempotency_key | operation_id, new_file_id, old_file_id | perucommerce_digital_files_manage (sólo A) |
| R8-64 | D-26/J-03 | `C2312Request` → `C2312Response` | document_kind, issued_at, series, number, external_reference, source_document_id, amount_cents, evidence_ref, expected_version, idempotency_key | operation_id, record_id, external_only | perucommerce_settings_manage Y perucommerce_orders_read (sólo A) |
| R8-65 | PER-48 | `— → ComplaintDetail` | sin body, `id` en path | detalle del Libro protegido, no PII en lista | perucommerce_complaints_manage (solo A) |
| R8-66 | PER-50 | `— → PrivacyRequestDetail` | sin body, `id` en path | detalle ARCO protegido, no identidad por booleano | perucommerce_privacy_manage (solo A) |
| R8-67 | PER-48 | `— → PrivateBinary` | `id` y `evidence_ref` en path, `purpose` en query | binario privado `attachment`; no-store, no URI de almacenamiento | perucommerce_complaints_manage (solo A) |
| R8-68 | PER-50 | `— → PrivateBinary` | `id` y `evidence_ref` en path, `purpose` en query | binario privado de ARCO solo del caso y finalidad | perucommerce_privacy_manage (solo A) |
| **R8-69** | **PER-38/39** | **`AdminManualCaseCreate → AdminManualCaseOpened`** | line_id, purpose físico parcial, requested_affected_units, reason, verification, expected_order_version, expected_line_version, idempotency_key | case_id, order_id/line_id, row_version, case_phase=open_review, operation_id y fecha, sin PII | **A con orders_read Y orders_financial_adjust** (sin O), mismo comercio/pedido, CSRF y auditoría |
| **R8-70** | **PER-38/39** | **`AdminManualCaseCloseRequest → AdminManualCaseClosed`** | `case_id` en path, decision reject/buyer_withdrawn, reason, expected_case_version, idempotency_key; withdrawal_verification obligatoria para retiro | case_id, order_id/line_id, case_phase terminal, nueva row_version, operation_id y fecha, sin PII | Solo A, orders_read Y orders_financial_adjust, pertenencia/estado/caso/campo verificados |
| R8-71 | PER-57 | `— → PosCatalogPage` | query q, page, per_page | items[sellable_id, name, price_cents, available_units, sku?, variant_label?], page, per_page, has_more; sin costo ni proveedor | A/O, pos_sell; puerta `pos.read` |
| R8-72 | PER-57 | `PosSaleCommit → PosSaleResult` | cash_session_id, lines[sellable_id, quantity], expected_total_cents, expected_rounding_cents, expected_cash_due_cents, tendered_cents, idempotency_key | operation_id, order_id, order_number, total_cents, rounding_cents, cash_due_cents, tendered_cents, change_cents, row_version | A/O, pos_sell y sesión propia abierta; puerta `pos.write` |
| R8-73 | PER-58 | `CashSessionOpen → CashSession` | register_code, opening_float_cents, idempotency_key | id, register_code, status, opening_float_cents, business_date, opened_at, row_version, opened_by_self?, pending_documents_count? | A/O, cash_session_operate; puerta `pos.sessions` |
| R8-74 | PER-58 | `— → CashSession` | — | igual que R8-73; **sin esperado ni diferencia** | A/O, cash_session_operate, solo sesión propia; puerta `pos.read` |
| R8-75 | PER-60 | `CashMovementCreate → MutationResult` | `id` en path, kind pay_in/pay_out, amount_cents, reason, expected_version, idempotency_key, order_id? | MutationResult existente (operation_id, result, entity_id?, row_version?) | Solo A, cash_movements_record; puerta `pos.sessions` |
| R8-76 | PER-58 | `CashSessionClose → CashSessionCloseResult` | `id` en path, counted_cents, expected_version, idempotency_key | session_id, status, counted_cents, pending_documents_count; expected_cents y difference_cents solo con cash_session_review | A/O, cash_session_operate y sesión propia; puerta `pos.sessions` |
| R8-77 | PER-59 | `— → CashSessionPage` | query page, per_page, state, register_code, business_date | items[id, register_code, status, opened_by_user_id, business_date, opened_at, closed_at?, expected/counted/difference_cents?], page, per_page, has_more | Solo A, cash_session_review; puerta `pos.read` |
| R8-78 | PER-59 | `— → CashSessionDetail` | `id` en path | id, register_code, status, opened_by_user_id, opening_float_cents, business_date, opened_at, row_version, expected_cents, movements[], counted/difference_cents?, close_reason?, closed_at?, closed_by_user_id?, pending_documents_count? | Solo A, cash_session_review; puerta `pos.read` |
| R8-79 | PER-59 | `CashSessionForceClose → CashSessionCloseResult` | `id` en path, reason, expected_version, idempotency_key, counted_cents? | igual que R8-76 con esperado y diferencia | Solo A, cash_session_review; puerta `pos.sessions` |
| R8-80 | PER-61 | `— → PosDocumentPendingPage` | query page, per_page | items[order_id, order_number, created_at, total_cents, pending_kind, case_id?], page, per_page, has_more; sin documento del adquirente | A/O, external_documents_record; puerta `pos.read` |
| R8-81 | PER-61 | `PosExternalDocumentRecord → C2312Response` | `id` en path, document_kind, series, number, issued_at, amount_cents, buyer_id_type, expected_version, idempotency_key, case_id?, external_reference? | C2312Response existente (operation_id, record_id, external_only) | A/O, external_documents_record; `nota_credito` además refunds_record_external (solo A); puerta `pos.write` |
| R8-82 | PER-57 | `— → PosOperationReceipt` | `id` en path | order_id, order_number, issued_at, register_code, lines[name, quantity, unit_price_cents, line_total_cents], total_cents, rounding_cents, cash_due_cents, tendered_cents, change_cents, legend, reminder | A/O, pos_sell Y orders_read; O solo pedidos de su propia sesión; puerta `pos.read` |
| R8-83 | PER-62 | `PosReturnRecord → PosReturnResult` | `id` en path, lines[line_id, returned_units, restock_units, inspection], refund_cents, reason, evidence, expected_version, idempotency_key, cash_session_id? | operation_id, order_id, case_ids, refund_cents, restocked_units, row_version | Solo A, orders_read Y refunds_record_external Y stock_manage; puerta `pos.write`; revisión del propietario antes de P3 |

### Guardas especiales por campo

- **R8-15/41/42/65/67:** `ComplaintCreate` incluye idempotencia y campos legales condicionales J-01 (sin contaminar checkout); `ComplaintReceiptCopy` devuelve hoja imprimible solo a la sesión del formulario emisor; `R8-41` resume, `R8-65` detalla y `R8-67` sirve evidencia privada exclusivamente a A. Reglamento y constancia por comercio deben comprobarse antes de habilitar, no hay DNI universal.
- **R8-43/44/50/66/68:** la lista ARCO solo contiene resúmenes; `R8-66` detalla y `R8-68` transmite evidencia protegida exclusivamente A por instalación/caso/finalidad. R8-50 únicamente exporta datos del titular legitimado, nunca pedidos masivos PER-09. WordPress Privacy sigue los flujos nativos legítimos.
- **R8-45/46:** `FiscalSettings` es configuración declarativa del comerciante para documento **externo**. Prohibido inferir régimen por cliente, calcular `igv_cents` o emitir CPE. Configuración no reemplaza la emisión/referencia de comprobante por pedido.
- **R8-06/07/17:** dinero comercial en céntimos PEN exactos; una obligación de cobro inicial; retorno navegador/webhook **no acredita** sin consulta autoritativa. MP no habilitado hasta compatibilidad peruana.
- **R8-08/09 y R8-10/11/12:** seguimiento solo lectura; cookie invitado canjeada HttpOnly/Secure/SameSite expira tras **30 min inactividad** sin superar enlace 90d, grant de propósito **5 min/un uso** y OTP 10min/5 intentos (D-190). CSRF/Origin se impone a operaciones sensibles aunque exista cookie; no expone secreto/OTP.
- **R8-13/14:** sesión digital distinta del enlace de pedido, archivo en streaming privado sin ruta web ni cabeceras cacheables.
- **Operador WordPress:** nunca `access_token`, `webhook_secret`, datos fiscales completos, Libro/ARCO íntegros, archivos protegidos ni refund externo; además filtrar por tarea y versión del recurso. Administrador tampoco recibe secretos MP en respuestas.
- **R8-71…R8-83 (POS V1, D-210, DOCUMENTADO/NO IMPLEMENTADO):** el operador cierra con **arqueo ciego**: R8-74/76 nunca le devuelven `expected_cents` ni `difference_cents`, que solo ve quien tenga `perucommerce_cash_session_review`. El comprobante externo guarda solo `buyer_id_type`, **nunca el número de documento del adquirente**, y R8-80 no lo expone. R8-82 entrega a O solo pedidos `pos` de su propia sesión. Ingresos y retiros (R8-75), revisión y cierre forzado (R8-77…79) y devoluciones (R8-83) son exclusivos de A en V1. Los pedidos `pos` se rechazan en R8-25/27 con 422 `pc_business_ineligible` (`reason = web_channel_required`), y R8-81/83 rechazan pedidos web con `reason = pos_channel_required`. Detalle en [docs/48](48-CONTRATO-POS-V1-P2.md).

### Anexo C23 — extensión RATIFICADA EN COBERTURA (D-176…D-187)

`docs/27-COBERTURA-OPERACIONES-P0-23-PROPUESTA-V1.md` conserva el origen de las 12 operaciones, **ya incorporadas como R8-53…R8-64 al E-08 y al OpenAPI principal 0.3.0-draft**. `docs/openapi/peru-commerce-v1-coverage-C23.PROPOSAL.openapi.json` es **archivo histórico, no fuente de implementación**. Los 51 REST originales conservan sus contratos; no se crean nuevas capabilities ni se conceden permisos de operador en PER-20/27/28/31. R8-64 es registro externo de comprobante por pedido; R8-46 continúa sólo configuración fiscal. Falta contrato físico/hosting y autorización de código.

### Detalle y evidencia privados ratificados en diseño (D-189/D-198, LEG-04/05)

**Selección vigente:** R8-65 y R8-66 son GET específicos de expedientes en Angular, solo A con `perucommerce_complaints_manage` y `perucommerce_privacy_manage`, control de instalación/recurso/finalidad y respuesta no-store. R8-41/43 siguen listados resumidos sin PII íntegra; R8-42/44 siguen siendo las únicas operaciones específicas de respuesta/resolución del expediente; WordPress Privacy conserva exportaciones/erasers legales. La antigua pantalla de detalle WordPress es antecedente descartado. No se crean capabilities globales ni privilegios O.

| R8 | DTO individual | Requeridos principales | Campos condicionales | Denegación |
| --- | --- | --- | --- | --- |
| 65 | `ComplaintDetail` | id, receipt_number, kind, submitted_at, row_version, claimant, subject, description, request | merchant/representative, monto, plazos, evidencias y entrega según J-01 | O y caso ajeno 403/404 uniforme; nunca listado íntegro |
| 66 | `PrivacyRequestDetail` | id, request_type, received_at, row_version, verification_status | solicitud, identidad proporcional, fundamento, plazos, evidencia, respuesta según J-04/J-05 | O, visitante, cuenta/caso ajeno denegados |

**Fuente contractual del corte histórico DOC-QA27…29:** `docs/openapi/peru-commerce-v1.openapi.draft.json` 0.10.0-draft, docs/37/42 y D-189/D-198. Se requiere implementación autorizada y pruebas IDOR/PII/cache posteriores; el diseño no demuestra cumplimiento.

### Alternativa histórica de lectura por expediente (descartada D-189)

- **Listados vigentes:** `R8-41 → ComplaintPage/ComplaintSummary` y `R8-43 → PrivacyPage/PrivacyCaseSummary`, sin relato, contacto completo, adjunto ni evidencia íntegra. Estos DTO no se amplían ni se convierten en un endpoint por ID.
- **Opción antecedente docs/35 §3:** página administrativa WordPress de solo lectura desde el panel, que el servidor renderizaría exclusivamente para administrador A con `perucommerce_complaints_manage` o `perucommerce_privacy_manage` según clase y pertenencia del caso; sin nuevos REST. No ratificada.
- **Opción seleccionada D-189:** dos consultas individuales GET R8-65/66, exclusivas A, sin mutación, con proyección por finalidad e instalación; ya ratificadas en diseño y descritas en OpenAPI 0.4.0-draft. Sin implementación ni pruebas.
- **Escrituras vigentes:** `R8-42` y `R8-44` siguen siendo las operaciones aprobadas para responder/resolver esos expedientes; los exporter/eraser nativos de WordPress Privacy (D-145/E08-P) siguen su propio flujo legítimo y no se convierten en una API de acceso general. Revalidar estado/versión al escribir, sin confiar en la pantalla de lectura. El diseño excepcional frente al panel Angular D-13/D-42 **requiere ratificación explícita** y prueba de permisos/cache; no declarar LEG-04/05 resueltas.
- **Resultado HISTÓRICO al corte D-198:** OpenAPI 0.5.0-draft **67 REST/64 rutas/117 DTO**; los controles E-07 y proyección `x-authz` canónica son obligatorios, ningún listado expone expedientes íntegros.

### Huecos reconocidos que el OpenAPI no soluciona

PER-19/20/27/28/11/41/23/24 y el registro de comprobante externo **ya tienen operaciones de diseño R8-53…R8-64 aprobadas en cobertura**, no implementadas ni probadas. **PER-09 exportación comercial masiva general quedó EXCLUIDA de V1 por D-188**, sin ruta; no es una brecha de cobertura que requiera inventar endpoint. Permisos del operador sobre PER-20/27/28/31 siguen denegados; faltan contratos de acceso seguro, datos y entorno. Los demás puntos no cubiertos son **brechas funcionales** sujetas a contrato/autorización si pertenecen a V1; no justifican endpoints improvisados. Los DTO de Libro/ANPD y campos de grabación de comprobantes/retención siguen sujetos a J-01…J-05 y P0-18/P0-26.

**Ajuste de autorización R8-28 (revisión estática):** una capability de **lectura** `perucommerce_orders_read` no debe autorizar una mutación. La propuesta ahora exige `perucommerce_orders_fulfill` para que el operador **proponga** un cambio logístico y `perucommerce_orders_financial_adjust` para aprobación administrativa cuando D-83 exige revisar tarifa/cobertura/dinero. Conserva límites E-02; requiere ratificación de permisos físicos antes de implementar.


## Rectificación de trazabilidad REST/DTO (2026-11-18; misma cobertura R8/PER)

El OpenAPI principal HISTÓRICO `0.7.0-draft` conservaba exactamente **67 operaciones REST, 64 rutas URL y 68 entradas R8 (R8-18 es página WordPress)**; 129 esquemas DTO. Se amplían solo esquemas/proyecciones de los contratos ya elegidos, sin permitir al operador nuevos datos/acciones.

| R8 / PER | Cambio dentro del contrato vigente | Fuente/consumidor |
| --- | --- | --- |
| R8-05 / PER-02 | QuoteResponse incluye versiones y textos públicos de políticas/privacidad | R8-06/07 envía versiones informadas y el servidor compara fingerprint |
| R8-06/07 / PER-04,05 | CheckoutMPResponse/CheckoutCODResponse incluyen emisión inicial única de tracking, sin secretos en replay | R8-08/09 canjea fragmento solo lectura; reemisión verificada R8-58 |
| R8-17 / PER-04 | WebhookNotice permite extensión oficial de `data` con ID obligatorio, no prueba fondos | HMAC → inbox durable → GET order acreditada |
| R8-29/30 / PER-10 | ReconciliationSummary.row_version de pc_cases alimenta ReconciliationResolve.expected_version | Solo A y case propio |
| R8-36/38/53 / PER-19/21 | AgendaItem.row_version; AgendaView.weekly_schedule.row_version/weekly_rules | C2301Request y AppointmentReschedule; domingo JSON7 → SQL0 |
| R8-39/40 / PER-54 | NotificationEntry.row_version proveniente de pc_outbox | NotificationResend.expected_version; O recibe solo aviso filtrado |
| R8-31/32/33 / PER-13/14/17 | AdminProduct/AdminVariant para edición, ProductVariantCreate/Update para mutación; stock inicial auditado, duración y buffers | ProductPublic/ProductVariant no exponen stock exacto; R8-35 muta unidades existentes |
| R8-21/25/27/64 / PER-07/35/37 + D-187 | AdminOrder.financial_movements[].event_id y external_documents para A con permisos/campo | Corrección cash/reintegro usa ID original recuperable, documento externo no se emite |
| R8-48/63 / PER-24 | upload inicial requiere product_id, sellable_id, slot_key; metadatos asociados | pc_digital_assignments; reemplazo con versión y derechos históricos |

La matriz histórica previa a esta rectificación no debe usarse para generar formularios sin datos de lectura, especialmente un `expected_version` que no se entrega. La proyección financiera/documentaria es opcional **solo por autorización servidor A**, nunca por ocultación en Angular. No hay nueva API ni nuevas capacidades.

## Matriz actualizada de lecturas que habilitan operaciones — OpenAPI 0.7.0
| Recorrido | Campo de origen y mutación consumidora | Actor/guarda |
| --- | --- | --- |
| R8-01/05/06/07, R8-45/46 | Política/privacidad de `pc_policy_versions` con texto/IDs versionados, edición por CAS y verificación en checkout | Textos públicos antes de contacto; edición solo A |
| R8-21 → R8-22/23/55 | `AdminOrderLine.row_version` de `pc_order_lines` | Cambios según guardas; R8-55 solo A |
| R8-21 → R8-56/57/61 | `AdminOrder.cases[].case_id/row_version/case_phase`; respuestas de C23 actualizan versión | Solo A con orders_read y capability del caso |
| R8-21 → R8-59/60 | `AdminOrder.digital_rights[].right_id/row_version` | Solo A con orders_read Y digital_files_manage |
| R8-31/32/33/34/48 → R8-63 | `AdminProduct/AdminVariant.digital_assignments` y `assignment_row_version` de primera carga/reemplazo | Solo A con catalog_edit Y digital_files_manage |
| R8-29 → R8-30 | `ReconciliationSummary.order_id` cuando existe o `source_operation_id` MP prepedido; `case_id/row_version` | Solo A con reconciliation_manage; sin cobro por inferencia |
| R8-39 → R8-40 | `NotificationEntry.row_version/resource_type/resource_id` | O solo recursos/tareas propios; sin IDs jurídicos/financieros |
 
`x-field-exposure` expresa los campos posibles del esquema, pero toda proyección condicional permanece sujeta a `x-authz`, pertenencia, estado, finalidad y capability adicional; el operador no recibe `cases`, `digital_rights`, `digital_assignments`, `financial_movements` ni `external_documents`. El documento histórico anterior no debe generar controladores con versiones o referencias ficticias.

## Cierre de correspondencias de consumidores — OpenAPI 0.8.0-draft (18-11-2026)

La superficie se conserva: **68 R8 (67 REST + una página WP), 64 rutas, 134 esquemas y 56 PER**, 39 tablas lógicas. Se añaden campos y consultas de detalle **dentro de R8 ya existentes**, sin cambiar permisos.

| Recorrido | Origen autoritativo y campos de salida/entrada | Regla de seguridad |
| --- | --- | --- |
| R8-21 | Sin `case_id`: AdminOrder con `AdminCaseSummary` mínimos. Con query `case_id` y `case_events_page`: `AdminCaseDetail` con `original_request` y eventos históricos paginados 50 | SOLO A con orders_read Y capability específica del caso, mismo pedido/instalación. O no recibe `cases/case_detail` |
| R8-09 y R8-21 | `OrderAgreedNotices` desde `pc_orders.policy_version_id/privacy_version_id` → `pc_policy_versions` originales | Invitado solo pedido propio, A/O con permiso de lectura del pedido; no exponer expedientes por ello |
| R8-45/46 y R8-21 | `digital_download_limit` nulo/entero positivo (configuración del COMERCIO) y `AdminDigitalRight.download_limit/download_count` | Cambios solo A, derechos ya emitidos inmutables; O no ve derechos ni binarios |
| R8-14 | Contador bajo lock antes de streaming autenticado, respeta cuota por derecho y archivo | Nunca usar token 24h para modificar derecho; sin cuota por defecto, fallo sin stream no consume |
| R8-45/46 → R8-01 | `MapTilesConfig`: enabled/URL HTTPS plantilla/atribución con origen validado | A configura; público solo origen activo sin secretos, Leaflet diferido; delivery manual no depende de tiles |
| R8-12 | `GuestActionRequest.idempotency_key` obligatorio, R8-12 devuelve `CaseAccepted.case_id`; caso/grant/idempotencia atómicos | Misma sesión+pedido+propósito+payload+clave recupera ID, otra solicitud no revive grant |
| R8-31/48 → R8-63 | `C2311Request.expected_version = AdminDigitalSlot.assignment_row_version` y respuesta con nueva `assignment_row_version` | Solo A con permiso doble; no mutar archivos vendidos |

La lista `x-field-exposure` no es autorización: aplicar siempre `x-authz`, permisos adicionales por campo, estado y finalidad. R8-21 rechaza cross-case/cross-order, O no obtiene detalles por modificar query. Los eventos de invitado pueden tener `actor_user_id=NULL` con `actor_kind=guest`, sin falsificar cuenta WP.

## Antecedente contractual DOC-QA22…26 — 18-11-2026

**Corte histórico de DOC-QA22…26: OpenAPI 0.9.0-draft (67 REST, 64 rutas y 134 esquemas).** Sus reglas se conservan con las precisiones DOC-QA27…29 del contrato vigente 0.10.0-draft; ningún corte anterior es una fuente alternativa. D-01…D-198, 56 PER, 39 tablas lógicas, límites de rol y exclusiones V1 permanecen intactos. Sin código ni comprobación en entorno real.

- **R8-26 / ajuste parcial por expediente:** si se disminuyen unidades de una línea, `FinancialAdjustment` requiere `affected_units` (positivo), `unit_effect=release_unfulfilled_units`, `line_id` y `case_id`. No modificar snapshot de venta; `pc_order_adjustments` conserva `delta_cents`/motivo/caso/idempotencia y la cantidad afectada en evento protegido, asociado con `pc_case_events`; dentro de la misma transacción se incrementa `pc_stock_commitments.released_units` y se revalida `committed_units - fulfilled_units - released_units` bajo locks. No liberar dos veces, no restock de físico ya entregado, no reembolso automático; saldo separado del ajuste.
- **R8-30 / pago tardío y primer turno:** conciliación de un pago efectivamente acreditado con `resolution=reassign_resources` exige `order_id` existente/vinculado. Si perdió franja y se requiere servicio, exige `new_service_start_at_utc`, `new_service_end_at_utc` y `buyer_agreement_evidence`; el backend comprueba acuerdo, importe, estado del proveedor, unicidad por pedido y recurso/día, y crea la PRIMERA cita `pc_appointments` junto con nueva asignación. No usar R8-38 sin appointment existente, revivir hold ni cobrar por segunda vez. Sin franja validada, incidencia abierta y ningún pedido confirmado ficticio.
- **R8-45/46 / proveedor del comercio:** `PublicBusinessSettings` puede transportar razón social `legal_name`, `tax_id_ruc` condicionado y dirección de local `premises_address`; `AdminSettings/SettingsPatch.mail_sender` configura nombre, correo y firma transaccionales, no credenciales SMTP. Persisten en el CAS/versionado de opciones WP; el Libro R8-15 usa identidad legal y dirección **congeladas** al ingresar reclamación y solo exige campos propios del formulario jurídico, jamás DNI/correo de forma universal al checkout. Agenda y avisos de servicio muestran dirección real del único local si está configurada; envío técnico no demuestra recepción.
- **R8-50/66/68 / exportación ARCO:** `privacy_request_id` y `expected_version` vinculan cada exportación a `pc_privacy_requests` e identidad verificada; `scope=order_legal_copy` obliga `order_id` del mismo titular/instalación. `PrivacyExportAck` da operación y expediente; el estado se consulta desde `PrivacyRequestDetail.export_operations` por R8-66. El artefacto terminado se custodia mediante `evidence_manifest/result_reference` protegido del mismo expediente (sin nueva tabla); solo R8-68 A puede transmitir el archivo por evidencia con finalidad/no-store y expiración. No API pública de exportación ni PER-09 comercial.
- **R8-36/37 / excepción de calendario:** cada cierre expone `closure_id`, `row_version`, `active`. La misma R8-37 recibe `action=create|update|retire`; update y retire requieren ID+versión y create/update requieren instantes. Con locks de todos los días afectados, comprobar solapamientos, pertenencia de cierre al recurso, disponibilidad y citas; retirar se audita sin borrar el cierre de `pc_schedule_blocks` ni cambiar citas automáticamente. R8-53 sigue gestionando solo el horario semanal versionado.

**Precedencia histórica del corte DOC-QA22…29:** el OpenAPI 0.10.0-draft define propiedades y reglas condicionales, `docs/42` su motivación, y este anexo su consumo por la capa pertinente. P-A permanece cerrado en diseño, P-B necesita autorización específica para implementar y P-C/P-D siguen sin pruebas, compilación, migraciones, MP, hosting, conformidad jurídica, ZIP o release.

## Matriz HISTÓRICA DOC-QA27…29 — 0.10.0-draft

| Recorrido | Contrato de campos | Actor y guarda |
| --- | --- | --- |
| R8-09/21 | `lines[].quantity` original; `lines[].physical_quantities` solo físico con comprometido/liberado/salido/pendiente de salida | Invitado del propio pedido o A/O autorizado; no stock global, finanzas ni expediente |
| R8-26 → R8-21 → R8-22/23 | Reducción estrictamente parcial actualiza cantidades y versión de línea atómicamente; cancelación de todo remanente por R8-23 | R8-26 solo A; O recarga ante 409 y prepara el saldo vigente, sin obtener facultad de ajuste |
| R8-45 | `mail_sender` y `routine_buyer_emails_enabled` en schema y exposición de respuesta | Solo A settings_manage; preferencia siempre presente |
| R8-46 | Ambos campos en schema y exposición de entrada/respuesta; preferencia omitida conserva | Solo A, idempotencia/CAS del agregado; cambiar preferencia/remitente no versiona textos legales |
| R8-39/40 | Omitido por preferencia no equivale a fallo; si aparece como aviso, `can_resend=false`; solo intención EMAIL existente puede reenviarse | Permisos existentes; ninguna conversión PANEL→EMAIL o reconstrucción de históricos omitidos |
| R8-66 → R8-68 | `export_operations` permitido en respuesta, correlacionado al mismo expediente | Exclusivamente A/privacy_manage, instalación/caso/finalidad y evidencia privada |

`quantity` conserva el original. `physical_quantities` es obligatorio solo en líneas físicas: `committed_units`, `released_units`, `stock_out_units`, `pending_stock_out_units`, enteros 0..100000; pendiente = comprometido − liberado − salido, sin confundir salida con entrega al domicilio. Los contadores son operativos de esa línea, no inventario global ni facultades financieras. Ceros sin compromiso solo antes de primera confirmación; ausencia inesperada tras confirmación es inconsistencia. Se mantiene la proyección con stock opcional y se leen estado/contadores/versión coherentes.

R8-45/46 admite/devuelve `mail_sender` y `routine_buyer_emails_enabled`, solo A con settings_manage. La preferencia siempre se lee (true inicial); en PATCH, omitir conserva y booleano sustituye. Afecta solo nuevos emails rutinarios físicos PREPARANDO/LISTO/DESPACHADO/ENTREGADO; la decisión por evento y versión de configuración persiste incluso al omitir EMAIL, sin recalcular en replay. Cola/reenvíos existentes conservan decisión; reactivar no reconstruye avisos históricos. WEB/PANEL, historial, pagos, incidencias, ajustes, servicios, digitales y avisos legales/esenciales permanecen. Cambiar remitente/preferencia no crea una versión de textos legales.
