**PRECEDENCIA VIGENTE D-210 (27-11-2026):** el contrato vigente es OpenAPI **0.13.0-draft (82 REST/78 rutas/162 esquemas)**, con 83 entradas R8, 62 PER, 41 tablas declarativas y 210 decisiones; el POS V1 consta en [docs/48](48-CONTRATO-POS-V1-P2.md) como **DOCUMENTADO/NO IMPLEMENTADO**. Las cifras D-200/0.12.0-draft o anteriores de este archivo quedan como corte histórico; el código y las pruebas siguen alineados con 0.12.0-draft hasta P3.

**PRECEDENCIA VIGENTE DOC-QA31 / D-200 (18-11-2026):** contrato único OpenAPI `0.12.0-draft`, **69 REST/66 rutas/138 DTO**. R8-70 cierra `physical_partial_quantity_adjustment` solo A y mismas dos capacidades `orders_read` Y `orders_financial_adjust`; exige propiedad del pedido/línea/caso, versión y idempotencia. Retiro del comprador exige verificación nueva proporcional (teléfono original más dato privado) sin guardar respuestas de factores; rechazo solo motivo real. R8-26 termina caso `closed_adjusted` atómicamente con ajuste y evento. Los cuatro GET legales Libro/ARCO R8-65…68 mantienen sus permisos exclusivamente A y no se pueden reutilizar para casos comerciales. Ningún endpoint probado.

# PeruCommerce — contrato documental previo al código: seguridad, REST y expedientes V1

**Corte:** 2026-11-17. **Estado:** contratos de diseño **RATIFICADOS D-189/D-190**; no es implementación, prueba, certificación jurídica ni autorización de código. Prioridad de fuentes: D-01…D-194, E-01…E-08, documentos 14…36. La continuidad de esta tarea habilita documentación únicamente.

**Corrección de contrato D-198 (2026-11-17):** la seguridad jurídica tiene ahora cuatro GET privados separados: R8-65/66 para detalle y **R8-67/68 para evidencia binaria**, todos A con `perucommerce_complaints_manage` o `perucommerce_privacy_manage`, instalación/caso/referencia/finalidad auditada y denegación uniforme de ajenos. Se añadió `PrivateEvidenceRef` URL-safe no autorizante y `ComplaintReceiptCopy` imprimible al mismo solicitante en R8-15; sin nueva ruta de upload público ni consultas por folio. **OpenAPI 0.5.0-draft: 67 REST/64 rutas/117 DTO**. Las 67 operaciones usan esquema `x-authz` homogéneo; los secretos nunca se almacenan en logs, y `R8-58/59` conservan entrega única. Sin código ni pruebas. La guía física de manifest está en docs/23, y la auditoría de correcciones en docs/42.

## 1. Decisión de diseño para LEG-04 y LEG-05

**Solución técnica aprobada como diseño D-189:** mantener toda la interfaz administrativa comercial en **Angular**, con dos consultas individuales y protegidas incorporadas al OpenAPI principal 0.4.0-draft:
- **R8-65 — GET /admin/complaints/{id}**: lectura del expediente individual de Libro, solo administrador con capacidad perucommerce_complaints_manage.
- **R8-66 — GET /admin/privacy-requests/{id}**: lectura del expediente ARCO individual, solo administrador con capacidad perucommerce_privacy_manage.

No se agregan roles, capacidades globales, máquina E-01 ni acciones de modificación. R8-41 y R8-43 permanecen listados paginados de resúmenes mínimos; R8-42 y R8-44 siguen siendo las operaciones que responden/resuelven. Los mecanismos de WordPress Privacy para exportación y supresión no se sustituyen. Se descarta como recomendación la pantalla separada de expediente en wp-admin documentada en docs/35 §3, porque duplicaría la UI que D-13/D-42 asignaron a Angular. **R8-65/66 están RATIFICADOS EN DISEÑO, pero NO implementados ni probados; su uso requiere autorización explícita de código.**

### 1.1 Contrato de consulta individual D-189 y evidencia D-198

| Aspecto | R8-65 Libro | R8-66 ARCO |
| --- | --- | --- |
| Sesión | WordPress del personal, misma instalación | WordPress del personal, misma instalación |
| Actor | Administrador A (operador O denegado) | Administrador A (operador O denegado) |
| Capacidad | perucommerce_complaints_manage | perucommerce_privacy_manage |
| Recurso | Expediente de Libro de esa instalación/establecimiento | Solicitud legal de esa instalación/responsable |
| Id | SqlId de recurso en ruta, no contraseña | SqlId de recurso en ruta, no contraseña |
| Respuesta | Resumen legal, reclamante/representante **según norma**, petición, plazos, respuesta, versión y referencias privadas autorizadas | Derecho solicitado, titular legitimado, petición/fundamento, plazos, versión, decisión y referencias privadas autorizadas |
| Prohibido | Token seguimiento, secretos MP, enlaces directos a evidencia, lista completa de expedientes o datos ajenos | Documento de identidad por defecto, prueba raw de identidad, secretos, exportación general de pedidos, información de terceros |
| Cabeceras | no-store; noindex y protección contra referer de terceros | Igual |
| Auditoría | Acceso por actor, caso, finalidad, hora y resultado sin contenido sensible | Igual |
| Errores | 401 si no autenticado; 403 si sin capability; 404 uniforme por ID inexistente/ajeno; 429 ante abuso | Igual |
| Mutación | Ninguna. R8-42 valida expected_version y estado de nuevo | Ninguna. R8-44 valida expected_version, legitimación y estado de nuevo |

**Controles obligatorios:** autenticar sesión WordPress; verificar capability en permission_callback; reforzar actor A, pertenencia a instalación, tipo/finalidad y campos permitidos en backend; un nonce por sí solo no autentica ni autoriza. La vista Angular no debe guardar datos sensibles en storage de navegador, telemetría ni logs. Nunca aceptar una solicitud de un operador solo porque puede leer pedidos. Referencias de evidencia requieren canal privado autenticado y protección de archivos, que se especificará antes de habilitar adjuntos; **no usar URL pública de uploads**.

**Compatibilidad del corte histórico D-198:** D-189 añadió R8-65/66 y D-198 suma R8-67/68, con total **67 REST, 64 rutas y 117 DTO** en OpenAPI 0.5.0-draft, más R8-18 página WordPress no REST. Las cuatro consultas son de lectura solo A, sin permisos O ni código.

### 1.2 DTO de detalle y transmisión de evidencias

**Evidencia privada:** R8-67/68 son GET de `application/octet-stream` con `Content-Disposition: attachment`, `private,no-store`, `nosniff`, `no-referrer`; el `evidence_ref` (16…128 chars URL-safe) es solo índice y no un secreto/permiso. El servidor comprueba simultáneamente actor A, capability concreta, caso e instalación, que el manifiesto del caso contenga esa referencia, propósito auditado y que el binario procede de almacenamiento privado. O/visitante/otro caso reciben denegación sin existencia enumerable. Nunca reutilizar R8-62 digital para documentos legales; no generar URL pública ni upload de archivos por visitante. El manifest se vincula a `pc_complaints/pc_privacy_requests`, sin agregar una tabla a las 39. No habilitar lectura si falta almacenamiento protegido real.

**Constancia legal:** R8-15 devuelve en el POST público inicial `ComplaintReceiptCopy` con datos del proveedor/folio/formulario original, imprimible **sin cuenta ni depender del envío de email**, aunque el formulario debe exigir los datos de contacto legalmente necesarios conforme al Anexo I aplicable; y `idempotency_key` de 16…128 caracteres protege reintentos del mismo formulario atado a contexto same-origin; nunca GET anónimo por folio ni secretos en URL/logs. La constancia/Anexo I se verificará por modalidad y comercio en P-C/P-D.

### 1.3 DTO de detalle candidato

**ComplaintDetail:** id, receipt_number, kind, submitted_at, due_at cuando jurídicamente calculable, provider_snapshot de la hoja original, claimant_snapshot permitido según ley, complaint_snapshot, response_snapshot cuando exista, response_delivery_status basado en evidencia verificable, evidence_refs privados sin URL, row_version. Todos los campos de identidad y monto se validan por modalidad y reglamento, nunca por asumir que están presentes en cualquier checkout.

**PrivacyRequestDetail:** id, request_type, received_at, due_at cuando aplicable, verification_status y referencia privada de legitimación (no copia de DNI ni secreto), request_basis_snapshot permitido, delivery_channel verificado, legal_retention_basis cuando procede, resolution_summary, resolved_at, evidence_refs privados sin URL y row_version. No convertir un campo booleano de identidad en verificación demostrada. El expediente puede llegar por el canal nativo/manual de WordPress.

Ambos DTO deben declarar con exactitud campos requeridos/opcionales, longitudes, formato UTC, ausencia de PII en errores, y una proyección única por actor/finalidad. La respuesta a R8-65/66 es de lectura; las mutaciones R8-42/44 conservan su contrato de idempotencia y versionado.

## 2. Política de sesiones, CSRF, tokens y reintentos (propuesta física)

**Ya confirmados:** enlace seguimiento 256 bits, vigencia 90 días; URL descarga digital 24 horas; OTP 8 dígitos y 10 minutos con máximo 5 intentos; retención de stock/cupo de 15 minutos desde intento de pago válido; no hay renovación implícita. **Nunca** cambiar esas cuatro duraciones por una decisión de implementación.

| Familia | Decisión técnica a cerrar antes de construir | Invariante |
| --- | --- | --- |
| Sesión staff | Cookie WordPress válida + X-WP-Nonce contra CSRF + capability efectiva + pertenencia de recurso; verificar Origin cuando corresponde | El nonce no concede permisos |
| Canje enlace invitado | Token secreto solo en fragmento, canje same-origin; cookie propia HttpOnly, Secure, SameSite; respuesta no-store; invalidación y rotación verificables | Sesión solo lectura del mismo pedido, no acceso a otros |
| Acción sensible invitado | OTP/verificación proporcional por finalidad, grant ligado a pedido, acción, canal y contexto de sesión; CSRF en cookie propia y Origin | Un enlace de pedido no habilita mutación por sí solo |
| Token digital | Separado del seguimiento; validar derecho inmutable, archivo específico y caducidad; streaming en servidor autorizado | No subir archivo protegido a Media Library pública |
| R8-58/59 reemisión | Primer resultado secreto una sola vez por TLS/no-store; replay idéntico da reference/estado sin secreto, sin nueva rotación | Nunca persistir secreto en pc_idempotency, outbox, audit o URLs de registros |
| MP webhook | Firma HMAC comprobada, idempotencia inbox, consulta de order fuente y correlación de comercio/ambiente/importe antes de acreditar | Nunca confiar en retorno visual o cuerpo webhook como prueba de pago |
| Admin/rest | Errores 401/403/404 por autenticación/permisos/recurso, 409 versión, 429 abuso; sin PII en mensajes | Negación del servidor aunque UI o ID sean conocidos |

**TTL todavía no elegidos por el propietario:** ventana de admisión previa al HTTP de MP, cookie de sesión canjeada, grant para acción sensible, conservación operativa de inbox/outbox e idempotencia. Propuesta de diseño conservador: admisión breve con vencimiento explícito que no renueva el hold; grant de una sola acción consumido atómicamente y con vencimiento corto; sesión canjeada nunca posterior al enlace fuente de 90 días; la conservación de idempotencia debe sobrevivir a pagos tardíos, reintentos y restauraciones y no obedecer al TTL de cookie. **No fijar cifras arbitrarias ni limpiar histórico financiero**. Su valor físico y política de retención requieren ratificación y prueba; esta tabla describe invariantes que se pueden cerrar hoy.

### 2.1 Duraciones técnicas ratificadas D-190 (a verificar al implementar)

Para evitar dejar cifras sin propuesta verificable, se elige **un conjunto candidato coherente y revocable**, sujeto a ratificación del propietario y a revisión del proveedor/hosting, sin cambiar D previas:

| Credencial/proceso | Valor técnico candidato | Motivo/efecto |
| --- | --- | --- |
| pc_resource_admissions, admisión interna MP | **60 segundos desde creación del registro**; sin renovación implícita | Retener recursos solo durante comunicación inicial breve fuera de transacción; timeout deja intento ambiguo para reconciliar, nunca segundo cobro automático |
| pc_action_grants, autorización OTP | **5 minutos, una única mutación y un propósito/pedido**; consumo atómico | Ventana menor que OTP de 10 minutos; evita reutilización por replay o cambio de finalidad |
| Cookie de sesión invitado canjeada | **30 minutos de inactividad**, renovables por actividad válida hasta caducidad absoluta del enlace fuente de 90 días | Sesión corta sin quitar derecho de reingresar por enlace vigente; invalidación obligatoria si enlace revocado |
| Idempotencia para operaciones financieras | **No purgar mientras existan intentos/provider orders, reconciliaciones o deber de conservación vinculados** | Un TTL de UI o sesión nunca permite repetir cobro/corrección/derecho financiero |
| pc_webhook_inbox/pc_outbox | **Conservar eventos contables/jurídicos y huellas de deduplicación conforme categoría legal; no borrar mientras estén en disputa/reintento** | Transportes pueden llegar tarde; evidencia y retención separadas de acceso por token |

**Estas duraciones son decisiones técnicas D-190, no hechos de seguridad o compatibilidad verificados.** Los contratos de fechas/expiraciones deben aplicarse en docs/20/21/23 y validarse en pruebas autorizadas de sesión, replay, token y recuperación. No extender el hold comercial de 15 minutos ni la vigencia MP por la ventana de admisión.

## 3. Matriz de autorización efectiva

Para cada operación REST del inventario se exige actor → capability → recurso/instalación → finalidad → versión/estado E-01 → guardas monetarias E-04/recursos E-05 → proyección E-07 → auditoría. La API de WordPress es la única autoridad del servidor: no conceder permiso por esconder un botón Angular.

- **Administrador A:** Libro/ARCO íntegros solo con capacidad legal y finalidad; credenciales/configuración fiscal/archivo digital bajo sus respectivas capacidades; la administración no exime de privacidad.
- **Operador O:** pedidos/contacto mínimos para atención, catálogo, stock, agenda, preparación/entrega autorizada; nunca LEG-04/05, PER-09, secretos MP, copias de identidad, Libro/ARCO completos, R8-54…64 salvo R8-53 agenda O/A.
- **Visitante:** catálogo/checkout; Libro público accesible sin cuenta/compra. **Invitado de pedido:** seguimiento exclusivamente del pedido canjeado y grant adicional para acciones sensibles. **Proveedor MP:** webhook firmado, separado de sesiones humanas.
- **D-188:** PER-09 exportación comercial masiva excluida de V1; R8-50 exclusivamente acceso/exportación por titular legitimado y finalidad legal.
- **No hay reembolso ejecutado por API**; solo registro externo verificado por administrador. Cobro manual por operador únicamente con capacidad y evidencia, sin corrección del asiento original.

## 4. Precisión documental a partir de OpenAPI 0.3.0 histórico y 0.5.0-draft vigente

- ComplaintParty exige full_name, pero no modela representante de menor; ComplaintCreate no representa monto reclamado; ComplaintReceipt no demuestra entrega/recepción efectiva.
- ComplaintPage/PrivacyPage solo contienen ítems resumidos y paginación, por lo que el detalle protegido necesita consulta específica o interfaz distinta.
- PrivacyResolution no aporta por sí sola prueba de legitimación; la aplicación debe comprobar referencia privada en backend antes de resolver.
- C2312Request.idempotency_key **ya** tiene longitud 16–128; documento correctivo sigue necesitando relación válida con documento externo fuente.
- R8-58/59 ya usan oneOf entre entrega inicial con url_once y replay without secret; **NO duplicar este comportamiento**. C2306Request y C2307Request aún requieren endurecer idempotency_key (mínimo actual 1) antes de considerarlos contrato final.
- El texto «Expedientes Libro íntegros» del summary de R8-41 era obsoleto; OpenAPI 0.5.0-draft ya lo corrige y añade los DTO R8-65/66. Las pruebas de acceso siguen pendientes.

## 5. Criterios de cierre pre-código por materia

| Materia | Diseño disponible | Qué lo bloquea documentalmente |
| --- | --- | --- |
| LEG-04/05 y UX Angular | Dos GET candidatos, roles y proyección por registro | Ratificación de ampliación API y especificación exacta de DTO; sin aprobación no generar endpoints |
| LEG-01/02/03 y DB-A10 | Hoja de reclamo, folio, plazo y respuesta con evidencia | Revalidar Anexo I y textos aplicables por modalidad; campos jurídicos condicionales y copia/recepción |
| LEG-06/10 y DB-A11/16 | ARCO por finalidad sin correo obligatorio de comprador físico | Identidad/evidencia, retención por categoría y canal de entrega; no validación legal universal |
| LEG-07/08 y DB-A09/12 | Registro externo por tipo/emisor; configuración comercial versionada | Decisiones fiscales por régimen/comercio, campos condicionales y CAS real de config |
| E-05/DB-A01…16 | Reglas de locks, idempotencia, snapshots, eventos y relación de tablas | Contrato físico por tabla/index/constraint y pruebas en motor real |
| E-08 MP | Orders API elegida, idempotencia y checksum monetario exacto | Validación por cuenta Perú de payer ausente, ítem Entrega y PT15M; no habilitar MP sin evidencia |
| P0-21 mapas | UBIGEO distrital autoritativo en servidor, entrada manual, tiles opcionales | Archivo oficial concreto/licencia/hash y verificación real de importación |
| P0-20/24/26 | Manifiesto Angular, ZIP GPL y runbook de actualización/restauración | Artefactos, fuentes y restores reales solo tras código y pruebas autorizadas |

## 6. Fuentes oficiales revisitadas (17-11-2026)

- WordPress REST y permission_callback: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- WordPress Releases: https://wordpress.org/download/releases/ (7.1.4 de 2026-10-28).
- Angular versioning: https://angular.dev/reference/releases (v22 en soporte activo).
- Angular compatibilidad Node/TypeScript/RxJS: https://angular.dev/reference/versions
- Mercado Pago Perú, Checkout Pro Orders: https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/create-order
- Mercado Pago, vigencia: https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/additional-settings/define-order-validity
- Indecopi, Libro: https://consumidor.gob.pe/libro-de-reclamaciones/
- PCM RM 244-2026-PCM: https://www.gob.pe/institucion/pcm/normas-legales/8406634-244-2026-pcm (publicación de proyecto, no decreto final).
- ANPD procedimiento ARCO: https://www.gob.pe/9269-iniciar-procedimiento-para-el-ejercicio-de-derechos-de-acceso-rectificacion-cancelacion-y-oposicion

**Esta consulta de documentación pública no es verificación de una cuenta MP, de un sitio WordPress, de una instalación ni un dictamen legal.** Todo lo descrito es documental, sin ejecutar código, SQL, pruebas, migraciones ni despliegues.
