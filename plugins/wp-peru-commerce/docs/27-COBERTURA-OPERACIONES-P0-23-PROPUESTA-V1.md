# P0-23 — Complemento de cobertura REST V1 (C23)

**Fecha:** 2026-11-17. **Estado actualizado:** LAS 12 OPERACIONES C23-01…C23-12 ESTÁN RATIFICADAS EN COBERTURA como D-176…D-187/R8-53…R8-64 por aprobación expresa contextual «OK adelante». **La ratificación es documental**, no licencia para implementar, generar SQL, compilar, probar, migrar ni publicar. El detalle físico, legal y de hosting se completa antes de implementar. Los actores O en PER-20/27/28/31 permanecen **NO AUTORIZADOS** hasta ratificación específica; A atiende esas rutas.


**Actualización posterior D-188 (2026-11-17):** PER-09 exportación comercial general/masiva queda excluida de V1. No se modifica la ratificación C23 D-176…D-187, el OpenAPI principal ni los permisos.

## 0. Ratificación y transición de propuesta a contrato (2026-11-17)

La propuesta original C23 ha sido aceptada para **cobertura V1**, con la siguiente correspondencia estable. La fuente vigente de rutas/DTO será `docs/21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` y `docs/openapi/peru-commerce-v1.openapi.draft.json`. El OpenAPI `docs/openapi/peru-commerce-v1-coverage-C23.PROPOSAL.openapi.json` se conserva únicamente como **antecedente de propuesta**, NO como contrato actual ni segundo API a implementar.

| Candidato original | Decisión confirmada | Operación REST nueva | Actor vigente en V1 |
| --- | --- | --- | --- |
| C23-01 | D-176 | R8-53 | O/A, solo agenda |
| C23-02 | D-177 | R8-54 | Solo A; O no concedido hasta decisión PER |
| C23-03 | D-178 | R8-55 | Solo A; O no concedido hasta decisión PER |
| C23-04 | D-179 | R8-56 | Solo A; O no concedido hasta decisión PER |
| C23-05 | D-180 | R8-57 | Solo A |
| C23-06 | D-181 | R8-58 | Solo A |
| C23-07 | D-182 | R8-59 | Solo A |
| C23-08 | D-183 | R8-60 | Solo A |
| C23-09 | D-184 | R8-61 | Solo A |
| C23-10 | D-185 | R8-62 | Solo A |
| C23-11 | D-186 | R8-63 | Solo A |
| C23-12 | D-187 | R8-64 | Solo A |

**No volver a preguntar si se incorporan estas 12 rutas**: la decisión de cobertura está confirmada. Siguen pendientes los subcontratos no ratificados por los documentos previos: TTL/entrega segura del secreto, acceso del operador para las PER marcadas P, asociación de versión digital, formato legal por emisor y comprobación del entorno. La negativa a ampliar O sin permiso explícito es una guarda conservadora, no una nueva facultad.

**Reemisión segura e idempotencia:** al emitir enlace nuevo, el servidor guarda solo verificador hash/HMAC y muestra el secreto una sola vez en la respuesta HTTPS autorizada `no-store`. Si se repite exactamente el mismo `idempotency_key`, se devuelve la referencia persistida **sin volver a revelar el secreto**; el cliente puede solicitar otra reemisión sólo mediante nueva operación legitimada y auditada. El resultado de reintento no es duplicación de secreto. No registrar valor completo de token en `pc_idempotency`, bitácora, outbox o trazas; no prometer entrega por correo con `wp_mail()` sin mecanismo seguro aprobado.

## 1. Precedencia

**Inventario HISTÓRICO de ratificación C23:** 64 entradas R8: 63 REST y R8-18 página WP. **Inventario contractual actual D-198:** 68 entradas R8, **67 REST/64 rutas/117 DTO** en OpenAPI 0.5.0-draft y una página WP no REST. La base histórica de 52 entradas/51 REST E-08 D-149…D-166 se mantiene sin reescritura de significado; D-176…D-187 ratifican 12 operaciones añadidas. El OpenAPI principal se amplía documentalmente; este archivo sigue reflejando las opciones originales C23 como trazabilidad, y las rutas R8-53…64 son contratos aprobados en cobertura, **no endpoints instalados o probados**.

Reutilizar las capacidades WordPress específicas ya previstas por E08-N, conservando exactamente la asignación de roles de E-02. Las operaciones PER-20/27/28/31 aún tienen aspectos de actor **PENDIENTES**: proponer su implementación no concede acceso al operador. Toda operación exige autenticación WP, nonce anti-CSRF, permission_callback, autorización recurso/campo/estado, versionado, auditoría y transacción/idempotencia cuando sea mutadora. Nunca usar permisos de lectura como autoridad de mutación ni inventar estado nuevo.

Referencia técnica: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/ y https://spec.openapis.org/oas/v3.1 .

## 2. Rutas ratificadas (trazabilidad C23, detalles físicos pendientes)

Las rutas son relativas al namespace peru-commerce/v1. **Ratificadas como R8-53…R8-64 e incorporadas al OpenAPI principal DOCUMENTAL**. El inventario C23 sigue describiendo la propuesta previa; prevalece la restricción de actor vigente en la tabla de ratificación (solo A donde la PER original tenía actor P).

| Candidato | PER / norma | Método y ruta | Perfil y capacidad EXISTENTE | DTO entrada → salida | Efecto autorizado propuesto | Persistencia P0-18 |
| --- | --- | --- | --- | --- | --- | --- |
| C23-01 | PER-19 | PATCH /admin/agenda/schedule | O/A · perucommerce_appointments_manage | ScheduleChange → ScheduleRevision | Horario semanal y buffers, sin mover citas confirmadas | pc_schedule_rules, pc_sellables, pc_calendar_days |
| C23-02 | PER-20 | POST /admin/appointments/{id}/outcome | Solo A (D-177) · perucommerce_appointments_manage | AppointmentOutcome → MutationResult | CONFIRMADA→REALIZADA/NO_ASISTIO; actor O pendiente de ratificación | pc_appointments, pc_order_lines, pc_audit_log |
| C23-03 | PER-27 | POST /admin/orders/{id}/lines/{line_id}/logistics-incidents | Solo A (D-178) · perucommerce_orders_fulfill | LogisticsIncidentOpen → CaseResult | Abrir incidencia de entrega no comprobada; no cancelar línea | pc_cases, pc_audit_log |
| C23-04 | PER-28 | POST /admin/logistics-incidents/{id}/proposals | Solo A (D-179) · perucommerce_orders_fulfill | LogisticsProposal → CaseResult | Proponer resolución sin efectos financieros/logísticos | pc_cases, pc_audit_log |
| C23-05 | PER-28/32 | POST /admin/logistics-incidents/{id}/resolve | Solo A · perucommerce_orders_financial_adjust + perucommerce_orders_fulfill | LogisticsResolution → CaseResult | Resolver expediente; R8-22/23/26/27 ejecutan efectos separados | pc_cases, pc_audit_log |
| C23-06 | PER-11 | POST /admin/orders/{id}/tracking-links/reissue | Solo A · perucommerce_privacy_manage + perucommerce_orders_read | TrackingReissue → OneTimeLinkResult | Verificar identidad, revocar enlace anterior y emitir nuevo enlace una sola vez | pc_guest_sessions, pc_audit_log |
| C23-07 | PER-41 | POST /admin/digital-rights/{id}/links/reissue | Solo A · perucommerce_digital_files_manage | DigitalLinkReissue → OneTimeLinkResult | Reemitir token 24h, sin modificar derecho/compra | pc_download_tokens, pc_download_rights, pc_audit_log |
| C23-08 | PER-41 | POST /admin/digital-rights/{id}/revoke | Solo A · perucommerce_digital_files_manage | DigitalRightRevoke → MutationResult | Revocar derecho por causa legítima y decisión previa, nunca refund | pc_download_rights, pc_download_tokens, pc_cases |
| C23-09 | PER-31 | POST /admin/digital-cases/{id}/decide | Solo A · perucommerce_digital_files_manage | DigitalCaseDecision → CaseResult | Decidir expediente sin revocar ni devolver dinero automáticamente | pc_cases, pc_audit_log |
| C23-10 | PER-23 | GET /admin/digital-files/{id}/download | Solo A · perucommerce_digital_files_manage | — → PrivateBinary | Descarga protegida administrativa, sin exponer URL webroot | pc_digital_files, pc_audit_log |
| C23-11 | PER-24 | POST /admin/digital-files/{id}/replacements | Solo A · perucommerce_digital_files_manage | DigitalFileReplacement → FileRevisionResult | Nuevo archivo para ventas futuras; pedidos anteriores conservan original | pc_digital_files, vínculo de versión activa P0-18 pendiente |
| C23-12 | D-26/J-03 | POST /admin/orders/{id}/external-documents | Solo A · perucommerce_settings_manage + perucommerce_orders_read | ExternalDocumentRegister → ExternalDocumentResult | Guardar referencia externa de boleta/factura/RHE/nota/guía, sin emisión SUNAT | pc_external_documents, pc_audit_log |

## 3. Contratos y guardas por operación

### C23-01: editar horario semanal

Entrada propuesta: resource_id (recurso único V1), expected_version, effective_from (fecha local), weekly_rules con weekday, opens_local, closes_local, enabled, reason e idempotency_key. Permisos O/A existentes de agenda, sin ampliar roles. Rechazar edición retroactiva que contradiga citas comprometidas, no reprogramar automáticamente. Las reglas semanales podrán tener varias ventanas no solapadas por día (para pausas); horario nocturno y días cruzados deben validarse bajo E-05. Los buffers y duración por servicio deben corresponder al sellable/configuración versionada de servicio; P0-18 debe definir materialización. R8-37 continúa exclusivo para cierres excepcionales.

### C23-02: registrar prestación o no asistencia

Entrada: outcome exactamente REALIZADA o NO_ASISTIO, observed_at_utc, evidence_ref, expected_version e idempotency_key. E-01 V-05/06 sólo desde CONFIRMADA, con hecho comprobado. NO_ASISTIO no impone multa, cancelación ni reintegro. **Solo A de forma conservadora** hasta ratificar actor O de PER-20, marcado P en E-02. R8-22 sirve sólo para línea física y no se reutiliza.

### C23-03/04/05: incidencia logística

C23-03 abre caso con reason, evidence_ref, expected_version, idempotency_key sobre línea física DESPACHADO. C23-04 propone resolution_proposal entre entrega_verificada, no_entrega_acreditada y continuar_investigacion, con evidencia y versión; no aplica efectos. C23-05 reserva a A resolver el expediente con decision, evidence_ref, expected_version, idempotency_key y dos capabilities existentes cuando se exijan. Resolución **no** marca automáticamente ENTREGADO/CANCELADO, no restock ni ajuste/reintegro. Acciones reales siguen R8-22/23/26/27, sujetas E-03/E-04. R8-30 corresponde a conciliación MP, no logística. Elegibilidad de O para proponer sigue pendiente PER-27/28.

### C23-06: reemisión de enlace de pedido

Entrada: identity_verification_ref, original_verified_channel, reason, expected_version, idempotency_key. Sólo A con privacidad + lectura de pedido, identidad comprobada incluso para compradores sólo teléfono; nombre+número de pedido no bastan. Generar secreto nuevo de 256 bits, revocar anterior, nueva vigencia de 90 días; guardar sólo verificador criptográfico. Devolver enlace una sola vez por canal administrativo seguro, con cabeceras private/no-store; nunca guardar texto del token en BD, outbox ni logs. **Pendiente** ratificar la entrega segura por correo o teléfono sin conservar token en claro.

### C23-07/08/09: derechos digitales

C23-07 exige identidad legitimada, pago verificado, derecho vigente, reason, expected_version e idempotency_key. Reemitir token distinto de 256 bits por 24 h; revocar anterior sin perder derecho. C23-08 revoca derecho y tokens sólo con case_id, legal_reason, evidence_ref y decisión autorizada de E-03/D-92. C23-09 decide expediente de digital habilitado sin descarga con decision entre maintain_access, authorize_revocation y financial_review, más reason/evidence/version/idempotency. Decidir no ejecuta refund o revocación; usar ruta específica C23-08 o R8 financiera separada. No habilitar REVOCADO→HABILITADO, aún pendiente de E-01 G-08.

### C23-10/11: ficheros privados

C23-10 GET sólo A bajo finalidad justificada, archivo en hosting privado y stream sin URL directa, con no-store/nosniff y acceso auditado. C23-11 multipart con file, reason, expected_version e idempotency_key: nuevo binario privado y referencia inmutable para **ventas futuras**. No sobrescribir archivo vinculado a derechos históricos, ni conceder binario a operador. P0-18 debe definir asociación de versión activa y comprobar storage real P0-19; R8-48 sólo hace upload y no cierra reemplazo.

### C23-12: referencia tributaria externa del pedido

Entrada: document_kind condicionado por régimen (boleta/factura/rhe/ticket/nota_credito/guia cuando proceda), series?, number?, issued_at, external_reference?, source_document_id? para corrección, amount_cents? como cadena PEN, evidence_ref?, expected_version, idempotency_key. **Solo A con las dos capacidades existentes indicadas**; verificación de régimen y operación por J-03. Añade registro inmutable de comprobante YA EMITIDO fuera del plugin y documento correctivo relacionado; nunca emisión/validación CPE, pago ni refund. R8-46 configura régimen y no cumple esta operación. El registro resultante será visible sólo para A mediante una proyección protegida de R8-21, sin crear otro GET ni revelar documentos completos al operador. P0-18 necesita relación con documento fuente sin editar original.

## 4. Funciones que NO requieren ruta REST nueva

| PER | Decisión técnica sin ampliación |
| --- | --- |
| PER-40 | Habilitar digital tras pago verificado es efecto interno E-01 G-02; no toggle manual |
| PER-42 | FINALIZADO es transición interna E-01 O-07 sólo si líneas y finanzas están conciliadas |
| PER-49 | Administración de roles mediante WordPress nativo, sólo A, con auditoría; sin endpoint comercial nuevo |
| PER-51 | Actualizar ZIP en WordPress plugins y contrato P0-26; nunca REST de despliegue |
| PER-52 | Desinstalación conservadora y purga legal excepcional fuera de API comercial |
| PER-36 | Reembolso por API prohibido, R8-27 sólo registra refund externo comprobado |
| PER-09 | **EXCLUIDA expresamente de V1 por D-188**; no es una brecha de cobertura. Solo una nueva decisión permitiría evaluarla en versión posterior; R8-50 sigue siendo privacidad |
| PER-11/41 solicitudes de comprador | R8-12 ya acepta solicitud tras verificación; C23 sólo ejecuta reemisión autorizada |
| Cierres puntuales | R8-37 ya cubre cierres; C23-01 modifica únicamente calendario semanal |

No deducir que funciones no implementadas estén cumplidas por usar la interfaz WP. Su forma precisa debe especificarse al implementar WordPress, con autorización de fase.

## 5. Garantías comunes de seguridad y consistencia

1. No inventar nuevas capabilities. El permiso existente no implica autorizar a O donde PER todavía está PENDIENTE: confirmar guardas/actor antes de implementación. Solicitar dos capabilities A cuando correspondan. No usar manage_options universal como reemplazo.
2. Todos los ID/expected_version/céntimos 64 bits se transmiten como strings decimales; los importes siguen enteros PEN y no se recalculan fiscalmente. Los mutadores requieren idempotency_key durable, bloqueo/versionado E-05 y auditoría.
3. 401 sesión inválida; 403 capability; 404 recurso no visible; 409 conflicto de estado/versión; 410 token vencido; 422 no elegible legalmente; 429 abuso. Responder error WP_Error pc_* sin secretos ni respuesta 200 ficticia.
4. No entregar tokens en logs, headers de referrer, outbox persistido en claro ni caché compartida. Reemisión segura de enlaces requiere mecanismo físico ratificado, no se considera resuelta por el DTO.
5. No efectuar operaciones HTTP externas mientras SQL está abierta. Una resolución de expediente no cambia dinero, línea, inventario ni derecho de archivo salvo operación posterior específica y elegible.
6. J-01…J-05, en especial reclamaciones/ARCO/fiscalidad, y P0-19 hosting siguen sin verificación operativa. No imponer documentos de identidad universales al comprador.

## 6. Desajustes físicos P0-18 pendientes

| Candidatos | Brecha ER/DDL concreta |
| --- | --- |
| C23-01 | Versionar horario semanal, buffers por servicio, fecha efectiva, detectar conflictos con citas |
| C23-02 | Constancia de prestación/no-show, actor y evidencia; distinguir estados línea/cita |
| C23-03/04/05 | pc_cases no explicita referencia de propuesta, autor de decisión y evidencia; completar sin máquina E-01 nueva |
| C23-06 | pc_guest_sessions verifica hash pero falta contrato físico de entrega única y legitimación telefónica |
| C23-07/08/09 | Relación decisión-expediente-derecho y rotación atómica de token sin expirar derecho |
| C23-10/11 | Versionado de archivo y puntero activo futuro; proteger derechos históricos y streaming |
| C23-12 | pc_external_documents carece vínculo explícito al documento externo corregido, además de matriz fiscal por emisor |

## 7. Aceptación documental; no pruebas ejecutadas

| Caso | Condición exigible |
| --- | --- |
| C23-X01 | Operador sin ratificación PER-20 no registra REALIZADA ni NO_ASISTIO |
| C23-X02 | NO_ASISTIO no multa, reembolsa ni cancela automáticamente |
| C23-X03 | Edición de agenda concurrente con citas comprometidas se rechaza sin moverlas |
| C23-X04 | Abrir incidencia por DESPACHADO conserva estado y pagos |
| C23-X05 | Propuesta O no ejecuta cancelación, ajuste ni restock |
| C23-X06 | Resolución A requiere acción de cumplimiento/finanzas separada si procede |
| C23-X07 | Sólo nombre o número de pedido no permite reemitir enlace |
| C23-X08 | Reemisión de enlace de pedido revoca previo y nuevo dura 90 días |
| C23-X09 | Reemisión digital sólo gira token 24 h; derecho sigue vigente |
| C23-X10 | Sustituir archivo no altera binario correspondiente a compra anterior |
| C23-X11 | Operador no puede descargar binario ni ver storage privado |
| C23-X12 | Registrar documento SUNAT externo no lo emite ni modifica cobro |
| C23-X13 | Nuevo RUS no acepta factura como comprobante propio por elegirlo en pantalla |
| C23-X14 | Reintento duplicado no crea dos eventos ni dos reemisiones |
| C23-X15 | La ratificación C23 es contractual: ninguna ruta está activa sin implementación y despliegue autorizados |

## 8. Estado vigente y puerta técnica restante

**Ratificación de cobertura completada:** doce operaciones aprobadas en política D-176…D-187 y numeradas R8-53…R8-64. No se requiere repetir esa decisión. El contrato actual está en E-08 y OpenAPI `0.3.0-draft` principal; el archivo `coverage-C23.PROPOSAL.openapi.json` queda archivado como antecedente. **Este mismo documento conserva algunos criterios formulados antes de la aprobación; prevalecen la sección 0 y los D confirmados.**

**Sigue pendiente, sin autorización de código:** (1) validación física SQL y hosting del modelo P0-18 de 39 tablas propuestas; (2) permisos por campo, evidencia y mecanismos seguros de una sola entrega de enlace; (3) formato, elegibilidad y retención J-01…J-05 por actividad/comerciante; (4) pruebas autorizadas de almacenamiento digital, seguridad, concurrencia y MP peruano; (5) UX/Figma y publicación del plugin. El operador permanece denegado en PER-20/27/28/31 hasta una decisión explícita posterior. **No hay código ni pruebas funcionales.**
