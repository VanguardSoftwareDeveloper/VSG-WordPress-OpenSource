**PRECEDENCIA VIGENTE D-200 (18-11-2026):** OpenAPI `0.12.0-draft` con **69 REST/66 rutas/138 esquemas**. Este contrato conserva requisitos legales J-01…J-05 sin certificación automática; R8-69/70 son expedientes **comerciales físicos parciales**, no reclamaciones del Libro ni ARCO y no suprimen derechos legales del comprador. El cierre R8-70 solo registra rechazo/retiro de esa solicitud comercial sin refund ni ajuste. Las referencias antiguas 0.3…0.11 permanecen históricas; ver docs/42. Sin código/pruebas.

# P0-23/P0-25 — Contrato de datos legales, proyecciones y autorización por finalidad (J-01/J-03/J-04/J-05)

**Fecha de corte:** 2026-11-17, Perú. **Estado:** especificación documental **PROPUESTA PARA RATIFICAR CAMPOS Y GUARDAS**, sin ampliar E-08, E-02 ni el alcance de V1. No certificación Indecopi, ANPD o SUNAT; no se modificaron negocios ni datos. Cualquier obligación legal concreta se evalúa por instalación/emisor/actividad. **No se autoriza código, DDL, ejecución, pruebas, llamadas a proveedor o despliegue.**

**Corte HISTÓRICO D-189/D-192 antes de D-198:** LEG-04/05 ya tenían lectura de detalle mediante R8-65/66 (OpenAPI 0.4.0-draft); el contrato **actual 0.5.0-draft agrega R8-67/68 de evidencias privadas**. La pantalla WP descrita en §3 es antecedente **descartado**; no construirla. Para LEG-01/02/03/06/07/08/10, D-192 ratifica la política de **campos y obligaciones condicionales** por modalidad/actividad/regímenes, pero ningún plugin puede certificar automáticamente cada comercio. Los flujos legales requieren comprobación del texto vigente y configuración del responsable antes del release; no hay código ni auditoría real. Los 10 hallazgos conservan trazabilidad histórica; no confundir contrato de acceso cerrado con conformidad Indecopi/ANPD/SUNAT.

**Actualización técnica D-198 tras el corte histórico 0.3.0 (2026-11-17):** el OpenAPI 0.5.0-draft contiene **R8-65/66** (detalle de expediente) y **R8-67/68** (lectura de evidencia binaria privada de un caso), además de `R8-15` con **`ComplaintReceiptCopy` imprimible sin depender del envío de correo**, aunque los datos de contacto de la hoja deben cumplir el Anexo I aplicable; no exige cuenta ni compra. Solo el administrador A accede a pruebas del expediente mediante capacidad legal, instalación, caso, referencia privada, finalidad, auditoría y HTTPS/no-store; el folio no concede acceso y no hay URL pública del binario. No se crea un endpoint de subida de archivos del visitante ni una pantalla WordPress paralela. **Mecanismo cerrado en diseño; seguridad runtime y legalidad por comercio no verificadas.** Detalle en `docs/42-AUDITORIA-Y-CORRECCIONES-CONTRATOS-PRECODIGO-V1.md`.

**Precisación normativa verificada (2026-11-18):** la [orientación oficial de Indecopi](https://consumidor.gob.pe/libro-de-reclamaciones/) identifica entre los campos del Anexo I nombre, documento de identidad, dirección y datos de contacto (teléfono/correo), además de representante en caso de menor, bien/servicio, monto, hechos y pedido. **El Libro NO es el checkout:** un artículo físico puede comprarse con teléfono conforme al alcance, pero el formulario jurídico requiere sus datos mínimos propios. Que la copia se pueda imprimir sin **envío** de email no exime de recabar un campo legal de contacto cuando la norma lo exija. Si faltan datos jurídicamente imprescindibles, no registrar falsamente un reclamo completo: informar qué falta o dar un canal alternativo legalmente válido. `ComplaintParty` y `ComplaintCreate` documentan validación condicional; verificar reglamento consolidado antes de habilitar Libro nativo.

## 1. Hechos y límites

- D-174 aprobó **método de matrices**, no identidad fiscal obligatoria de todo comprador. El Libro **no es checkout**: jamás imponer DNI/RUC/compra previa al visitante de un catálogo sólo porque existan en formulario legal de reclamos.
- Plataforma ecommerce en Perú debe mantener Libro visible/accesible según Ley 32495. Indecopi documenta hoja con numeración, proveedor, consumidor, producto/servicio, descripción y petición, copia e **intervalo máximo de respuesta de 15 días hábiles improrrogables**. El reglamento base DS 011-2011-PCM tiene cambios; RM 244-2026-PCM de julio de 2026 **publica un proyecto**, no modifica automáticamente la norma vigente. Validar composición definitiva de la hoja/campos condicionales conforme texto consolidado y modalidad.
- ANPD: acceso 20 días hábiles y rectificación/cancelación/oposición 10 según normativa y procedimiento. No confundir expiración de cookie/token con obligación de supresión de datos; notificación de incidente dentro de 48 horas **solo cuando se configuran los supuestos jurídicos** del DS 016-2024-JUS, nunca todo error HTTP.
- SUNAT: configuración fiscal la declara comerciante y documento se emite **externamente** a PeruCommerce; el registro R8-64 prueba únicamente que el admin registró una referencia bajo su responsabilidad, **NO** que SUNAT la validó. NRUS no se convierte en emisor de factura por escoger un combo. Ni la tasa IGV ni un DNI se infieren en checkout.
- **D-188 (2026-11-17):** la exportación general de pedidos PER-09 está excluida de V1; no interpretar R8-50 como atajo comercial. La decisión no resuelve LEG-01…LEG-10 ni altera permisos de privacidad.
- Las fuentes [N35-F01…F08] se verificaron en portales oficiales. **No hay decisión de aplicar una versión legal definitiva del proyecto de julio 2026 ni certificación de cumplimiento por negocio.**

## 2. Matriz campo-ruta-finalidad: no mezclar checkout, Libro, ARCO y fiscalidad

| ID | Fuente | R8 | Actor autorizado | Datos de entrada/salida y finalidad | Precondición/validación por caso | Exposición de campos | Persistencia propuesta | Pendiente jurídico o físico |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| L35-01 | J-01 | R8-15 | Público sin compra/cuenta | Tipo reclamo/queja, identidad aplicable, bien/servicio, detalle, petición | Formato oficial Anexo I, menor con representante, campos/contacto según norma/modalidad y medio disponible; **no obligar correo en checkout** | Solicitante solo su constancia; admin caso completo | pc_complaints snapshot protegido; correlativo atómico por ámbito | Formato jurídico definitivo de hoja, adjuntos, identificación/contacto, reglamento posterior al proyecto 2026 |
| L35-02 | J-01 | R8-15 respuesta | Solicitante en el mismo formulario | **ComplaintReceiptCopy** con folio, proveedor y copia imprimible sin necesidad de **entregarla por email**; los campos legales de contacto se solicitan según Anexo I | Reintento idempotente con contexto original same-origin, no búsqueda pública por folio | Copia solo al formulario emisor, HTTPS/no-store; sin PII en logs | Snapshot inmutable `pc_complaints`, clave `pc_idempotency`, evidencia de constancia | Cerrado en diseño D-198; verificar Anexo I y entrega efectiva por instalación |
| L35-03 | J-01 | R8-41/65/67 | Solo A / complaints_manage | Lista mínima R8-41; detalle R8-65 y evidencia privada R8-67 por caso/finalidad | Sin PII íntegra en lista ni permiso a O; evidencia vinculada al mismo caso | Solo A con permiso, auditoría/no-store y pertenencia | `pc_complaints` manifest privado + audit | **CERRADO EN DISEÑO D-189/D-198**; IDOR/cache/legalidad en pruebas/activación |
| L35-04 | J-01 | R8-42 | Solo A / complaints_manage | Texto de respuesta escrita, versión, evidencia | Verificar tipo, fecha de ingreso, 15 días hábiles y canal; trámite no prorrogable salvo norma posterior; salida idempotente | Admin responde; copia/titular por canal válido, sin exponer expediente ajeno | pc_complaints + audit/outbox y constancia protegida | Calendario de feriados y evidencia de comunicación efectiva, no wp_mail TRUE |
| L35-05 | J-03 | R8-45/46 | Solo A / settings_manage | Régimen declarado, tipos documentales permitidos | No inferir régimen a partir de comprador; no crear comprobante desde config ni calcular IGV automáticamente | A configuración fiscal; O prohibido | Opciones versionadas/CAS; audit | Datos del emisor/actividad y aplicabilidad por comercio; CAS versión config |
| L35-06 | J-03 | R8-64 | Solo A / settings_manage + orders_read | Documento externo ya emitido: clase, fecha, serie/número o referencia según caso; monto opcional; documento fuente si correctivo | Elegibilidad según régimen/actividad; referencia no equivale a CPE validado ni a pago; corrección añade nuevo registro | Admin solo por pedido de instalación; no operador | pc_external_documents append-only; source_document_id, dedup idempotente | Validación de clases y datos por tipo, serie/número y documento correctivo, sin obligatoriedad universal de DNI |
| L35-07 | J-04 | Captura pública y R8-06/07 | Público | Datos compradores según tipo de artículo; versión de aviso | Digital/servicio requiere email según D-24; físico teléfono según D-24; legal fiscal/Libro se captura en su contexto | Comprador propios datos; personal solo tarea legitimada | pc_orders snapshot, policy version, contact mínimo | Texto privacidad/fundamento real, terceros/términos por negocio |
| L35-08 | J-04 | R8-43/66/68 | Solo A / privacy_manage | Lista resumida R8-43; detalle R8-66 y evidencia R8-68 privada por caso | Canal original WordPress/manual legítimo sin requerir correo; identidad proporcional | Solo A, no pruebas de identidad en listados | `pc_privacy_requests` manifest privado y auditoría | **CERRADO EN DISEÑO D-189/D-198**; legitimación real y acceso se verifican por comercio |
| L35-09 | J-04 | R8-44 | Solo A / privacy_manage | Resolución, fundamento y evidencia, expected_version, clave idempotente | Acceso 20 días hábiles; rectificación/cancelación/oposición 10, según régimen/procedimiento; no purgar historia con deber de retención | Admin; notificación al titular por canal legitimado | pc_privacy_requests + auditoría y export privado | Vigencia/competencia de excepciones y forma de entrega/comunicación |
| L35-10 | J-04 | R8-50 | Solo A / privacy_manage | Exportación por titular verificado/finalidad legal, razón, identidad legitimada | No descarga masiva comercial PER-09; archivo temporal privado; secreto no aparece en respuesta | Admin solo sujeto/finalidad; titulares por canal seguro | pc_privacy_requests, storage temporal privado | Política de expiración y entrega, custodias/retención |
| L35-11 | J-05 | Caso administrativo/incidente | Solo A / privacy_manage | Descubrimiento UTC, categorías/datos/volumen, daños, decisión y notificación | Reglamento DS 016-2024-JUS art 34: 48h ANPD cuando concurren supuestos; documentar incidentes art 35; no notificar todo error automáticamente | Administración; canal regulatorio fuera del plugin | pc_cases/audit privado y backups protegidos | Precisar aplicabilidad del caso, encargado/responsable y reporte |
| L35-12 | E-07 | R8-08/09 | Invitado autenticado por su propio secreto | Pedido solo lectura, sesión cookie independiente | Tracking 90 días política acceso; no equivale retención legal; no uso de nonce de WP como auth | Solo pedido enlazado, campos necesarios | pc_guest_sessions hash/HMAC | TTL cookie canjeada y protección cache/fragmento |
| L35-13 | E-07 | R8-10/11/12 | Invitado con sesión + grant purpose | Challenge 8 dígitos/10 min, 5 intentos y grant de propósito | No inferir derecho sobre otro pedido ni ejecutar refund al solicitarlo | Grant por propósito, mismo pedido, sin acceso económico fuera de caso | pc_otp_challenges, pc_action_grants, pc_cases | TTL grant y protección CSRF para cookie en acción sensible |
| L35-14 | E-07 | R8-58/59/60/61 | Solo A, capabilities por cada operación | Verificación de identidad, decisión/caso, rotación de token digital/seguimiento | Token 256 bits una sola vez; replay devuelve resultado sin secreto; digital 24 h, seguimiento 90 días; revocación no refund | A solamente; O no habilitado por tener orders_read | pc_guest_sessions, download_rights/tokens, pc_cases | Canal seguro de entrega y evidencia de legitimación, no secreto en bitácora |

### R8-15: diseño mínimo de Libro nativo versus exigencia legal

- **Entrada funcional**: `kind` reclamo/queja, `claimant` con nombre y datos de identificación/contacto según Anexo I y modalidad; representantes de menores cuando corresponda; `subject`, descripción del bien/servicio y monto si aplicable, hechos, pretensión concreta, fecha de ingreso desde servidor. No necesitar `order_id` para poder reclamar.
- **Datos del proveedor**: razón social/RUC y dirección vinculadas a la **versión real de configuración del negocio al presentar la queja**; no confiar en campos escritos por visitante. Correlativo atómico por ámbito legal (instalación/establecimiento según norma) con constancia protegida y copia imprimible en navegador sin depender del transporte email; el propio formulario conserva los requisitos de contacto de la norma aplicable.
- **Campos obligatorios finales**: Indecopi informa en su [orientación oficial](https://consumidor.gob.pe/libro-de-reclamaciones/) que el Anexo I contiene correlativo, identificación del proveedor, fecha, identificación/contacto del consumidor, padre/representante si es menor, bien/servicio y monto, hechos, pedido y acciones del proveedor. **No trasladar esos campos al checkout ni omitirlos arbitrariamente del formulario del Libro.** Antes de ratificar el DTO, cotejar la modalidad exacta y texto vigente del Anexo I por campo/obligatoriedad y canal de copia/constancia. Si la norma exige identificación o una forma determinada de contacto, debe validarse **en este formulario**, no en compra física general. No interpretar campo presente en un formulario publicado como prueba automática de que todas las personas deben proporcionar todos los canales a la vez. Revisar Anexo I actualizado y situaciones especiales de menor/representación.
- **Acceso y respuesta**: administrador puede responder con evidencia y fecha de notificación; `wp_mail()` TRUE solo acredita intento de envío en la infraestructura, no recepción. El plazo legal se calcula con calendario de días hábiles/feriados y sustento vigente, no `addDays(15)` ni WP-Cron.
- **LEG-04, CERRADO EN DISEÑO D-189/D-198:** R8-41 lista resúmenes; R8-65 da detalle individual a A y R8-67 transmite evidencia privada del mismo caso bajo finalidad y capability; R8-42 responde con versión. La pantalla WordPress de §3 es un antecedente descartado. Pruebas de acceso, caché y legalidad pendientes de ejecución.

### J-04: ARCO, protección de titular y conservación

- El titular puede iniciar solicitud fuera de un flujo basado en cuenta/email (teléfono legítimo, canal escrito u otro conforme), con constancia de recepción y comprobación proporcional de identidad; no copiar DNI/imagen por defecto, almacenar **referencia** a verificación protegida. Guardar tipo, `received_at`, `due_at` con calendario legal, motivo, decisión, base de excepción de conservación y `responded_at`.
- R8-43 lista resúmenes administrativamente; R8-44 decide bajo `perucommerce_privacy_manage`; R8-50 exporta **únicamente** datos de sujeto legitimado conforme finalidad. No usar R8-50 para PER-09 exportación comercial masiva.
- **LEG-05, CERRADO EN DISEÑO D-189/D-198:** R8-43 lista resúmenes; R8-66 da detalle y R8-68 evidencia privada del mismo caso solo a A con finalidad acreditada; R8-44 resuelve. WordPress Privacy/manual sigue recibiendo solicitudes sin nuevo endpoint público de alta. La pantalla WordPress de §3 fue descartada.
- La supresión no borra automáticamente documentos de ventas, Libro o registros con obligación legal; anonimizar lo prescindible y retener lo exigible con causa registrada. La ejecución de ARCO no debería sustituir decisiones legales del responsable de instalación.

### J-03: documento externo por pedido

- R8-45/46 gestionan **configuración declarada** del comercio. R8-64 es sólo **alta append-only de referencia documental previamente emitida**; `document_kind`, emisor, fecha, serie/número/referencia (obligatoriedad condicionada a tipo), `source_document_id` para nota/correctivo, importe coherente en céntimos si se aporta, `expected_version` e `idempotency_key`. El admin certifica procedencia externa, **el plugin no verifica CPE** con SUNAT.
- Clasificación `boleta/factura/rhe/ticket/nota_credito/guia` es **vocabulario técnico candidato**, no una lista de documentos jurídicamente admisibles para todos los emisores. Discriminar emisor NRUS/RER/RMT/General/cuarta categoría, facturación a consumidor, documento correctivo/traslado, referencias y forma válida de emisión según J-03 y docs/26.
- **Nunca** habilitar tipo documental o registrar una nota correctiva sin documento fuente del mismo comercio y evidencia; el registro no acredita dinero, reembolso ni impuesto. R8-64 solo A con `perucommerce_settings_manage` **Y** `perucommerce_orders_read`.

## 3. Alternativa histórica descartada D-189 — pantalla WordPress para LEG-04/05

**Estado (2026-11-17): antecedente documental NO VIGENTE tras D-189.** Esta sección conserva la **alternativa WordPress original**. El análisis posterior **docs/37** propone como recomendación preferida dos GET REST individuales y protegidos (R8-65/66), porque D-13/D-42 asignan el panel comercial interactivo a Angular. **Ninguna alternativa está ratificada ni autoriza una ruta nueva.** D-13/D-42 establecen panel comercial interactivo Angular; esta alternativa utiliza una **pantalla nativa WordPress excepcional y de solo lectura** para los expedientes jurídicos sensibles. Es una elección de integración por ratificar, no una modificación automática del panel aprobado. Alternativa si no se acepta esta excepción: ratificar formalmente rutas REST individuales de detalle y su modelo de permisos, cambiando el inventario OpenAPI mediante una decisión separada; **ninguna ruta nueva queda autorizada aquí**.

### 3.1 Interfaz, conexión y navegación propuesta

1. R8-41 `GET /admin/complaints` y R8-43 `GET /admin/privacy-requests` **siguen paginados y devuelven solamente resúmenes** (`ComplaintPage` / `PrivacyPage`). Los DTO actuales no contienen texto íntegro, datos del reclamante, medios de legitimación ni documentos.
2. Desde el listado correspondiente en el panel Angular, solo un administrador autorizado obtiene la navegación a **una pantalla de detalle del plugin en el área `wp-admin` del mismo sitio**, resuelta mediante las herramientas nativas de URL de WordPress. La navegación puede usar identificador interno de caso y categoría `complaint` o `privacy`; **nunca** token de invitado, OTP, número documental, nombres ni información personal en la URL. El identificador no se considera secreto.
3. Una única superficie de **lectura** puede reutilizar el renderizado administrativo de ambos expedientes, pero la autorización y la proyección de campos se deciden por **categoría e ID**. No convierte R8-41/43 en un listado con detalle ni añade un REST o `admin-ajax` de expediente. El `GET` no cambia estados, no confirma lectura al consumidor ni decide reclamación/ARCO.
4. La **respuesta formal del Libro** y la **resolución administrativa del expediente ARCO** continúan por R8-42 y R8-44 con su `expected_version`, `idempotency_key`, guardas y capacidades; esto **no excluye** los flujos nativos WordPress Privacy (exporter/eraser) ya aprobados en E08-P/D-145 para tratar datos conforme a su fundamento legal. Si el administrador vuelve al panel Angular para resolver, la mutación debe revalidar versión, plazo y recurso; la vista previa no otorga permiso ni bloquea un caso. **No crear un segundo POST ad hoc de respuesta/resolución en WordPress admin**, ni reutilizar el exporter/eraser como acceso público a expedientes.
5. Si el comercio usa **Libro externo** (D-05), no fabricar expedientes nativos ni duplicar el libro del tercero; la superficie de Libro nativo solo existe cuando la modalidad y sus datos fueron habilitados conforme a J-01. La tramitación ARCO no depende de que exista un pedido ni de que el interesado tenga cuenta/correo.

### 3.2 Autorización, campos y defensa contra exposición

| Control | Libro `complaint` | ARCO `privacy` |
| --- | --- | --- |
| Actor | Solo administrador del comercio `A` | Solo administrador del comercio `A` |
| Capability efectiva | `perucommerce_complaints_manage` | `perucommerce_privacy_manage` |
| Ámbito | Caso existente perteneciente a la instalación/establecimiento correcto | Solicitud vinculada al responsable y la instalación correctos |
| Proyección permitida | Correlativo; fecha y tipo; proveedor/snapshot legal; reclamante y representante **solo según J-01**; bien/servicio, hechos, petición; plazo, respuesta, historial y evidencia referenciada | Tipo de derecho, ingreso, titular/representante **según legitimación**; referencia de verificación, petición y fundamento; plazo, estado, decisión, evidencias y restricción legal de conservación |
| Prohibido | Panel del operador, listados con detalle, otros comercios, adjuntos por URL pública, credenciales de pago | Panel del operador, expedientes ajenos, difusión de DNI/imagen por defecto, exportaciones masivas comerciales, enlaces de archivos sin control |
| Mutación | Respuesta formal por R8-42 y condiciones J-01/E-07 | Resolución del expediente por R8-44 y condiciones J-04/E-07; WordPress Privacy nativo para exportación/supresión legítima sin atajos |

**Invariantes del diseño:** sesión WordPress autenticada **y** comprobación de capacidad efectiva al renderizar; validar además actor `A`, pertenencia del caso y finalidad. No basta con ocultar enlaces del menú, un nonce, conocer el ID, tener `manage_options` ni pertenecer a otro sitio de una instalación multisitio. Respuestas de acceso denegado/recurso ajeno sin confirmar existencia, y contenido escapado al renderizar. Separar personal administrativo de compradores y portadores de enlaces de seguimiento.

**Protección de transporte y auditoría propuesta:** HTTPS; respuesta privada `no-store` y exclusión verificable de caché LiteSpeed/CDN; evitar filtración de datos por referentes, trazas, vista previa y logs; no incrustar scripts de terceros en esta superficie. Registrar **acceso administrativo al expediente** (actor, instalación, clase/ID, momento, propósito y resultado) sin reproducir relato, DNI, contacto completo, secreto o binario. La existencia de una fila de auditoría se revisará contra el diseño de `pc_audit_log` ya propuesto; no se crea tabla nueva por defecto. Adjuntos/evidencias únicamente mediante mecanismo privado autorizado **aún pendiente**; no construir enlaces directos a `uploads` ni asumir que una ruta de stream digital sirve para expedientes.

### 3.3 Datos disponibles versus vacíos físicos reales

| Caso | Ya contemplado en P0-18 (sin implementación) | Sin definir antes de la pantalla/DDL |
| --- | --- | --- |
| Libro | `pc_complaints`: `sequence_scope`, `sequence_number`, `submitted_at`, `complainant_snapshot`, `complaint_snapshot`, `response_snapshot`, `response_evidence`, `row_version` | Campos y validaciones del Anexo I por modalidad/menor, `due_at` normativo y feriados, identificación/propiedad del establecimiento, evidencias de emisión **y recepción**, acceso seguro a constancia/adjuntos |
| ARCO | `pc_privacy_requests`: `applicant_reference`, `request_type`, `received_at`, `verified_at`, `due_at`, `resolved_at`, `legal_basis_code`, `result_reference`, `row_version` | Conservación privada de la **solicitud y fundamento original**, vínculo de sujeto/instalación, referencia a legitimación verificada, decisión/entrega, excepción de conservación y canal sin email, sin recopilar DNI innecesario |
| Ambas | `pc_audit_log` y eventos/outbox previstos por E-05/E-07 | Proyección exacta por propósito, tratamiento de adjuntos, cobertura histórica de evidencia, plazo de conservación y comportamiento cuando falta campo antiguo |

No rellenar vacíos del esquema con columnas ficticias en OpenAPI o tablas adicionales. Una pantalla no debe deducir una prueba de recepción de correo, identidad ni cumplimiento solo porque el registro contiene una marca de fecha.

### 3.4 Criterios de aceptación documentales (NO ejecutados)

| Caso | Comprobación exigida en etapa de implementación autorizada |
| --- | --- |
| LEG35-X01 | Actor operador o visitante conoce URL/ID: no recibe el expediente ni confirmación útil de su existencia |
| LEG35-X02 | Administrador con una capability de Libro pero no de ARCO: ve exclusivamente el expediente de Libro permitido |
| LEG35-X03 | Administrador con ID de otra instalación: denegación por pertenencia aunque conozca el número |
| LEG35-X04 | R8-41/43 conservan solo resúmenes y la cantidad de operaciones OpenAPI continúa en 63 |
| LEG35-X05 | La vista administrativa de detalle tiene `no-store`, no se cachea, no expone identidad en URL, referer, HTML de terceros o registros |
| LEG35-X06 | Expediente con múltiples versiones/evidencias: se muestra el histórico autorizado sin sobrescribir el original |
| LEG35-X07 | El detalle se consultó con versión antigua y otro administrador respondió: R8-42/44 rechaza conflicto o revalida sin sobrescribir |
| LEG35-X08 | Petición ARCO sin email/compra: puede registrarse por canal legítimo y revisarse tras verificación proporcional |
| LEG35-X09 | Evidencia privada inaccesible: no ofrecer URL pública alternativa; mostrar indisponibilidad segura |
| LEG35-X10 | Caso con plazo legal vencido: visible y procesable conforme ley, sin cambiar `due_at` ni darlo por respondido automáticamente |

**Conclusión histórica de §3:** la pantalla WordPress no fue seleccionada. **R8-65…68 de Angular** son la solución documental D-189/D-198; el acceso real todavía debe probarse y la normativa depende del comercio. No se ha creado ni probado ningún controlador.

## 4. Autorizaciones que deben verificarse en backend (no solo UI)

| Ref | Capa | Restricción necesaria | Aplicación |
| --- | --- | --- | --- |
| AUTH-01 | Autenticación | Sesión WP staff / sesión guest propia / webhook firmado; no confiar en ID/nonce como auth | R8-08…14, 17, 19…64 |
| AUTH-02 | Capability + rol | AND de permisos efectivos y prohibición actor O cuando PER no aprobado | R8-41…50, R8-54…64 |
| AUTH-03 | Propiedad del recurso | Id pertenece al sitio, pedido/línea/caso/derecho correspondiente; cross-order=denegación | Todos los R8 con {id} |
| AUTH-04 | Estado E-01 y dinero E-04 | Guardas por fase actual y **historial financiero**, no solo status string o UI | R8-06/07/22…28/54…61 |
| AUTH-05 | Datos por campo | Permitir únicamente atributos requeridos por tarea/finalidad, nunca secretos MP, Libro/ARCO íntegros al operador | R8-19…52, 64 |
| AUTH-06 | CSRF/Origin | X-WP-Nonce staff para cookies WP, CSRF adicional en cookies guest/grant; nonce != permiso | R8-10…12, admin, R8-58/59 |
| AUTH-07 | Idempotencia y versión | expected_version en mutaciones y evento único; respuestas replay sin secretos | R8-12/22…28/42/44/46/50/54…64 |
| AUTH-08 | Salida / mensaje error | WP_Error pc_* 401/403/404/409/422/429 apropiado, sin indicar pedido ajeno/secretos | Todas rutas según contexto |
| AUTH-09 | Plazos legales vs técnicos | 10m OTP, 24h download, 90d seguimiento, 15m hold no borran historias legales | R8-06/08…14/15/42/44/58/59 |
| AUTH-10 | Auditoría | Evento mínimo con actor+motivo+resource+outcome; no token, dirección/PII completa, payload proveedor | Mutaciones sensibles |

**Secuencia para cada request sensible:** autenticar → comprobar actor/capability → comprobar propiedad del recurso e instalación → validar versión/estado y guardas financieras → aplicar propósito y lista permitida de campos → comprobar CSRF/rate limit → transacción/idempotencia → serializar proyección mínima → auditar evento sin secretos. **Ningún orden textual exime de CSRF/rate limit antes de efectuar una mutación;** una capa física deberá fijar el punto exacto. Para recurso no visible, uniformar error sin confirmar existencia; no filtrar `complainant_snapshot`, `private_storage_key`, tokens o credenciales.

### Trazabilidad de roles para rutas con riesgo alto

- **Público:** R8-15 para presentar reclamo, sin cuenta ni compra; antiabuso proporcional sin ocultar el canal legal. R8-16 UBIGEO y catálogo no exponen historiales de comprador.
- **Invitado:** R8-09 solo lectura de pedido canjeado; R8-10/11/12 verificación por propósito, ninguna facultad de reembolso ni cambio directo sin revisión. Las cookies privadas no son la sesión WP de personal.
- **Operador O:** jamás Libro/ARCO completos, settings fiscales/MP, exportación de datos personales, ficheros protegidos ni R8-54…64; **excepción aprobada R8-53 O/A para horario**, con controles de estado. `orders_read` no equivale a permiso de mutar.
- **Administrador A:** R8-41/42 `complaints_manage`; R8-43/44/50 `privacy_manage`; R8-45/46 `settings_manage`; R8-64 requiere **ambos** permisos indicados. También necesita propósito/instalación/versión para todas las mutaciones: `manage_options` genérico no autoriza por sí solo todos los expedientes.
- **Webhook MP R8-17:** únicamente autenticación de proveedor firmado y validación GET autoritativa; no exigir cookie ni nonce de staff, no aceptar `payload` del proveedor como prueba de acreditación.

## 5. Brechas de DTO y almacenamiento antes de ratificación física

| Brecha | Evidencia actual | Cambio documental propuesto | Condición de aceptación |
| --- | --- | --- | --- |
| LEG-01 | `ComplaintParty` actual solo exige `full_name`, no representante ni condiciones J-01 | Formalizar campos condicionales en el **DTO del Libro**, no en checkout | Verificación Anexo I consolidado y decisión sobre identificación/canales/minores |
| LEG-02 | `ComplaintCreate` contiene representante/monto condicional e idempotencia; `ComplaintReceiptCopy` devuelve copia imprimible con proveedor, folio y formulario original | Guardar snapshot y devolverlo solo al contexto emisor, sin búsquedas por folio | **CERRADO EN DISEÑO D-198**, sujeto a validación del Anexo I/constancia en entorno real |
| LEG-03 | `ComplaintRespond` guarda texto, sin canal/fecha/evidencia de entrega | Añadir contrato de canal y evidencia protegida; plazo no derivado solo de WP-Cron | Demostrar constancia legal y cómputo de días hábiles |
| LEG-04 | R8-41 summary sin detalle, R8-42 escribe respuesta | **D-189:** R8-65 GET individual privado `ComplaintDetail` en Angular, A + complaints_manage | **CERRADA EN DISEÑO**; pruebas IDOR/PII/cache y obligación legal por caso pendientes |
| LEG-05 | R8-43 summary sin detalle, R8-44 resuelve | **D-189:** R8-66 GET individual privado `PrivacyRequestDetail` en Angular, A + privacy_manage | **CERRADA EN DISEÑO**; verificación legal de titular/canal y pruebas de acceso pendientes |
| LEG-06 | `PrivacyResolution` no vincula evidencia de verificación del titular | Referencia privada de identidad y resolución; no pedir imágenes por defecto | Solo admin, datos mínimos y deberes de conservación |
| LEG-07 | `FiscalSettings` no prueba compatibilidad del régimen/actividad | Config declarativa y advertencia de documento externo, no certificación | Regímenes aplicables por emisor/caso J-03 |
| LEG-08 | `C2312Request` exige idempotencia 16…128, fuente correctiva tipada y reglas jurídicas condicionales | Registrar referencia de documento externo legítimo según emisor y misma instalación; sin emisión SUNAT | Diseño tipado cerrado; validar reglas por comercio y deduplicación real |
| LEG-09 | R8-58/59 usan `oneOf` primera entrega/replay sin secreto; **todas las claves C23 tienen 16…128 caracteres** en OpenAPI 0.5.0-draft | Entregar secreto una vez TLS/no-store, nunca persistir en idempotencia/outbox/logs | Diseño cerrado; CSRF, identidad y replay real se verificarán en ejecución autorizada |
| LEG-10 | Retenciones por categoría y plazos normativos mezclados con TTL | Distinguir `due_at` legal, `expires_at` credencial y periodos de conservación | Sin purga automática de pedidos/Libro al vencer token |

### Reconciliación HISTÓRICA contra OpenAPI 3.1 `0.3.0-draft` (antes de D-189/D-198)

Este cuadro registra el corte **histórico** con 63 REST. El OpenAPI vigente **0.5.0-draft tiene 67 REST/64 rutas/117 DTO**; D-198 corrigió campos/copia de Libro, evidencia privada, claves idempotentes y seis DTO C23. No generar esquemas actuales desde el cuadro previo.

| LEG | Estado observado en el borrador | Ajuste/ratificación pendiente (documental) |
| --- | --- | --- |
| LEG-01 | `ComplaintParty` requiere `full_name`; identificación, teléfono, email y dirección son opcionales; **no tiene representación de menor** | Campos/requisitos condicionales solo en Libro según texto vigente; nada de DNI/correo universal en checkout |
| LEG-02 | `ComplaintCreate` requiere `kind`, `claimant`, `subject`, `description`, `request`; **no** incluye monto reclamado ni representante; `ComplaintReceipt` requiere únicamente `receipt_number` y `submitted_at` | Evidencia del formato oficial por caso, snapshot del proveedor, numeración y constancia/copia en canal accesible |
| LEG-03 | `ComplaintRespond` requiere `response_text`, `expected_version`, `idempotency_key`; `evidence` es opcional | Contrato por canal y constancia de comunicación efectiva; fecha de envío ≠ recepción |
| LEG-04 | `ComplaintSummary` solo exige `id`, `receipt_number`, `kind`, `submitted_at`; contiene `customer_name` opcional, pero **no** contiene `due_at` ni estado legal. La descripción de R8-41 en OpenAPI aún dice «Expedientes ... íntegros» pese al DTO resumido | Corregir texto descriptivo del OpenAPI **cuando se autorice editar el contrato**, minimizar `customer_name` si no es imprescindible y ratificar vista privada de detalle § 3. No aumentar la respuesta de listado |
| LEG-05 | `PrivacyCaseSummary` contiene `id`, `request_type`, `received_at` y opcionales `due_at`, `has_verified_identity`; R8-43 sigue siendo un listado `PrivacyPage` | Ratificar lectura de solicitud original y verificación por finalidad en pantalla admin; la marca booleana no equivale a evidencia de identidad |
| LEG-06 | `PrivacyResolution` exige resolución, base legal, versión y clave; `evidence` opcional. `PrivacyExportRequest.verified_identity` es texto, **no prueba de legitimación** | Exigir comprobación real server-side y referencia privada a evidencia/forma de entrega, sin exponer dato de identidad en logs |
| LEG-07 | `FiscalSettings` admite `regime` y `external_document_types` declarativos | La combinación elegible por emisor/régimen/actividad debe validarse para el comercio, nunca inferirse desde un enum |
| LEG-08 | `C2312Request.idempotency_key` ya tiene `minLength: 16` y `maxLength: 128`; `source_document_id`, serie/número y referencia siguen opcionales sin condición de tipo | **Corregida la observación histórica minLength 1**; falta validar correctivo contra documento fuente y datos condicionales J-03 |
| LEG-09 | `R8-58/59` **sí** especifican respuesta `oneOf`: primera con `url_once` y replay con `already_issued=true` sin secreto. Sus requests `C2306Request/C2307Request` aceptan `idempotency_key` mínimo 1 | Conservar el `oneOf` correcto; ratificar control de claves, almacenamiento, replay real, identidad, CSRF y entrega una sola vez |
| LEG-10 | Los TTL técnicos figuran en políticas separadas; no hay contrato físico validado que demuestre retención legal por categoría | No asociar purga de expedientes al vencimiento de credenciales; conservar requisitos de J-04/J-05/P0-26 |

**Alcance de la inspección:** estructura y requisitos declarados en el JSON; no validación automática de esquema, no lint, no pruebas de consumidor de API, ningún servicio iniciado. La lista identifica **qué está realmente definido** para evitar corregir lo que ya se documentó bien (especialmente el `oneOf` de replay).

**Fuentes nuevamente contrastadas (2026-11-17):** la guía Indecopi sobre Libro confirma 15 días hábiles improrrogables, el formato de hoja y acceso virtual; la [RM 244-2026-PCM](https://www.gob.pe/institucion/pcm/normas-legales/8406634-244-2026-pcm) continúa identificada como **publicación de proyecto**, no se asume una reforma definitiva a partir de esa ficha. La [ANPD](https://www.gob.pe/9269-iniciar-procedimiento-para-el-ejercicio-de-derechos-de-acceso-rectificacion-cancelacion-y-oposicion) informa 20 días hábiles para acceso y 10 para R/C/O en el procedimiento correspondiente. Sin verificación de un caso concreto ni certificación general.

**Estado actual:** LEG-01…10 conservan trazabilidad histórica; LEG-02/04/05/09 tienen correcciones documentales D-189/D-198. Contrato vigente: **67 REST/64 rutas/117 DTO**. El derecho aplicable por comercio y la seguridad real requieren evidencia futura, no se certifican en el diseño.

## 6. Fuentes y evidencia

| ID | Fuente institucional | URL | Aplicación |
| --- | --- | --- | --- |
| N35-F01 | Indecopi, Libro de Reclamaciones | https://consumidor.gob.pe/libro-de-reclamaciones/ | Formato Anexo I, plataforma virtual, respuesta 15 días hábiles, constancia |
| N35-F02 | Ley 32495 (El Peruano) | https://busquedas.elperuano.pe/dispositivo/NL/2457203-1 | Libro en ecommerce visible y accesible |
| N35-F03 | PCM RM 244-2026-PCM | https://www.gob.pe/institucion/pcm/normas-legales/8406634-244-2026-pcm | PROYECTO de modificación de reglamento, NO texto aprobado |
| N35-F04 | DS 011-2011-PCM | https://www.gob.pe/institucion/presidencia/normas-legales/541080-011-2011-pcm | Reglamento base; cotejar texto consolidado y reformas vigentes |
| N35-F05 | DS 016-2024-JUS / ANPD | https://www.gob.pe/institucion/anpd/normas-legales/6554453-16-2024-jus | Protección datos y notificación de incidentes condicionada |
| N35-F06 | ANPD trámites ARCO | https://www.gob.pe/9269-iniciar-procedimiento-para-el-ejercicio-de-derechos-de-acceso-rectificacion-cancelacion-y-oposicion | Acceso 20 días; R/C/O 10 días hábiles, según trámite |
| N35-F07 | SUNAT Regímenes | https://emprender.sunat.gob.pe/ruc/regimenes-tributarios-mype/regimenes-tributarios | Clases de contribuyentes y comprobantes por emisor, no universales |
| N35-F08 | WordPress REST permisos | https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/ | permission_callback y current_user_can no sustituido por nonce |
| N35-F09 | WordPress, páginas administrativas | https://developer.wordpress.org/reference/functions/add_submenu_page/ | La capability del menú no sustituye la comprobación en el renderizado |
| N35-F10 | WordPress, autorización efectiva | https://developer.wordpress.org/reference/functions/current_user_can/ | Capacidad efectiva con comprobación adicional de recurso/finalidad; no confiar solo en rol |
| N35-F11 | WordPress, impedir cache HTTP | https://developer.wordpress.org/reference/functions/nocache_headers/ | Referencia de encabezados; comprobar caché real del hosting y CDN |
| N35-F12 | WordPress, herramientas de privacidad | https://developer.wordpress.org/plugins/privacy/privacy-related-options-hooks-and-capabilities/ | Exporter/eraser nativos para canal pertinente; no sustituyen manejo manual verificado sin email |

**Advertencia sobre el reglamento de Libro:** al corte 2026-11-17 se encuentra publicada la RM 244-2026-PCM **como proyecto**. La búsqueda de fuentes usada en esta fase no localizó un decreto de modificación final verificable posterior. **No declarar que jamás se publicó**, sino volver a verificar el diario oficial y el texto consolidado antes del release. La normativa aplicable puede exigir matices de formatos/canales según caso.

## 7. Siguiente tarea específica autorizable

**PRE-01 ya ratificada:** D-189 adoptó dos GET privados en Angular R8-65/66 y actualizó E-08/OpenAPI/DTO. Lo pendiente no es decidir entre UI alternativas, sino verificar los controles y aplicar la normativa por instalación antes de habilitar el módulo. Docs/39 separa esta evidencia P-C/P-D. Después precisar `docs/openapi/peru-commerce-v1.openapi.draft.json` bajo las brechas documentales una vez se ratifiquen los campos legales por modalidad; ajustar tablas `pc_complaints`, `pc_privacy_requests`, `pc_external_documents`, índices/auditoría en P0-18. **No inventar endpoints nuevos ni certificar legalidad**.

**Revisión estática del corte histórico D-198:** contraste de E-02/E-07/E-08 con OpenAPI 0.5.0-draft y protección legal. Sin controladores PHP, SQL ejecutado, cuentas reales, pruebas o migraciones.
