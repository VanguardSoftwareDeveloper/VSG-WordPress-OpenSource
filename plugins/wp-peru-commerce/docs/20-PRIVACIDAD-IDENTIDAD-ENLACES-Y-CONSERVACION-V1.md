**PRECEDENCIA VIGENTE D-210 (27-11-2026):** el contrato vigente es OpenAPI **0.13.0-draft (82 REST/78 rutas/162 esquemas)**, con 83 entradas R8, 62 PER, 41 tablas declarativas y 210 decisiones; el POS V1 consta en [docs/48](48-CONTRATO-POS-V1-P2.md) como **DOCUMENTADO/NO IMPLEMENTADO**. Las cifras D-200/0.12.0-draft o anteriores de este archivo quedan como corte histórico; el código y las pruebas siguen alineados con 0.12.0-draft hasta P3.

**FUENTE CONTRACTUAL VIGENTE D-200 / DOC-QA31 (18-11-2026):** OpenAPI **`0.12.0-draft`**, 69 REST/66 rutas/138 esquemas. El caso comercial físico parcial se inicia R8-69 con identidad manual verificada, se rechaza o registra su retiro por **R8-70** (nueva comprobación de control de teléfono original y segundo factor privado para retiro) y solo se termina `closed_adjusted` por R8-26 con ajuste aplicado; R8-70 no altera dinero/stock ni elimina evidencia. No registrar valores literales de verificación en auditoría, enviar correo a teléfono-only por inferencia ni emplear estas rutas para Libro/ARCO. Las referencias históricas a 0.8/0.10/0.11 no son fuentes de implementación. Sin datos reales ni tests.

# E-07 — Privacidad, identidad, enlaces privados y conservación V1

**Estado: E-07 APROBADO EN POLÍTICA FUNCIONAL DE PRIVACIDAD Y SEGURIDAD (D-135…D-148).** El propietario aprobó expresamente las 14 opciones A E07-A…N el 2026-11-17. **Sin cierre jurídico/operativo:** J-04/J-05/P0-25 (ANPD, plazos y obligaciones reales), E-08 (API y controles efectivos), P0-18/P0-19/P0-21/P0-26 (SQL, hosting, mapas y retención/ZIP). Corte de fuentes públicas: **2026-11-17**. Fase 0: documentación, sin autorización de código, datos reales, correos, pruebas, migraciones o despliegue.

**Propósito:** cerrar decisiones de quién procesa información, qué datos se piden para cada compra, cómo se autoriza a un invitado, cómo se protegen seguimiento y descargas, cómo se tramitan derechos de titulares, incidentes, conservación y desinstalación. Las políticas **E07-A…N fueron aprobadas expresamente como D-135…D-148**. Sus opciones A son vinculantes dentro del alcance funcional aprobado; campos, interfaces, demostración de seguridad y cumplimiento legal siguen pendientes.

**Fuentes internas vinculantes:** decisiones D-01…D-148 en [01](01-DECISIONES-Y-ESTADO.md); E-01 [14](14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md); E-02 [15](15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md); E-03 [16](16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md); E-04 [17](17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md); E-05 [18](18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md); E-06 [19](19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md). La conformidad legal P0-25 y contratos REST E-08 siguen abiertos.

**Matriz legal posterior (2026-11-17):** `docs/25-MATRICES-NORMATIVAS-J01-J05-P0-25-V1.md` contiene J-04/J-05 (DS 016-2024-JUS, registro de bancos de datos, derechos ARCO, notificación condicional de incidentes y conservación). Es **investigación documental**, no conformidad ANPD del comerciante, ni autorización de purga, ni incidentes comunicados.

**Extensión técnica D-189/D-190 (2026-11-17):** los expedientes de Libro y ARCO se consultan individualmente mediante R8-65/66 con actor A, capability legal y control por recurso/instalación/finalidad; nunca por usar listado R8-41/43 o conocer el ID. El seguimiento de 90 días, OTP de 10 minutos/5 intentos, descarga digital de 24 h y hold de 15 minutos permanecen invariables. Se especifican **60 s de admisión pre-HTTP**, **grant sensible de 5 minutos y un uso**, **cookie canjeada de invitado de 30 min de inactividad sin superar vigencia absoluta del enlace fuente**; validar revocación/replay y CSRF/Origin en fase autorizada de pruebas. Se prohíbe limpiar idempotencia financiera, auditoría o expedientes por expirar la cookie. D-190 es contrato de diseño, no comprobación de seguridad ni permiso para ejecutar código. Aplicabilidad ANPD por negocio según D-192.

**Corrección de privacidad D-198:** R8-65/66 consultan expedientes de Libro/ARCO y R8-67/68 transmiten evidencia binaria **solo A**, con capability específica, pertenencia del caso/instalación, finalidad y auditoría. `PrivateEvidenceRef` no es token: nunca descarga pública por folio, URL de almacenamiento ni acceso de operador. R8-15 devuelve `ComplaintReceiptCopy` solo a quien envía el formulario, protegido por cookie y CSRF same-origin con idempotencia, sin exigir cuenta ni que la copia se entregue por email; el formulario sí exige los datos de identificación/contacto pertinentes del Anexo I; no filtrar PII en caché/logs/analytics. Conservación legal independiente de tokens y cookies. Contrato vigente OpenAPI 0.8.0-draft 67/64/134; seguridad runtime sin verificar.

**Nota de precedencia HISTÓRICA tras D-198 (18-11-2026):** las reglas funcionales E-01…E-07 aprobadas en este documento se conservan. Los textos anteriores que presentan E-08, DTO, capabilities, TTL o cobertura de expedientes como *pendientes de decisión* son **históricos**, no una segunda fuente de contratos: prevalecen `docs/01` D-01…D-198, `docs/41/42`, OpenAPI **0.8.0-draft (67 REST/64 rutas/134 DTO)** y `docs/23/24` (39 tablas, 56 PER). Solo quedan pruebas/compatibilidad de motor, proveedor, hosting, normativa por comercio y release en P-C/P-D; no se presentan como verificadas. El usuario autorizó aquí **documentación**, no código ni ejecución.

## 1. Normas y documentación oficial comprobadas: no confundir política con cumplimiento

| Fuente | Hallazgo verificado | Consecuencia y límite |
| --- | --- | --- |
| Ley **29733** y Reglamento **DS 016-2024-JUS**, vigente desde el **31-03-2025** | Regulan el tratamiento de datos personales en Perú; el Reglamento reemplazó al antiguo DS 003-2013-JUS | Comercio y proveedores deben revisar responsabilidades, finalidades, seguridad, derechos y transferencias; no se declara cumplimiento por instalar ZIP |
| **ANPD** — inscripción de bancos de datos personales, página actualizada **22-05-2026** | Quien sea titular de banco de datos personales debe inscribirlo; trámite gratuito y de aprobación automática, con cuenta y representación documentada | **No exigir inscripción para desarrollar código**; documentar trámite/responsabilidad del comerciante que trata datos de compradores, si le corresponde. No prometer «cero registros legales» |
| **ANPD** — derechos ARCO, información pública | Se reconocen acceso, rectificación, cancelación y oposición. La orientación oficial publica para acceso **20 días hábiles** y para rectificación/cancelación/oposición **10 días hábiles**, sujeto a requisitos procedimentales y norma vigente | No hacer borrado automático indiscriminado ni usar estos plazos como caducidad de la compra; verificar reglas/transitorias y excepciones antes de fijar cómputos en producción |
| **DS 016-2024-JUS**, incidentes de seguridad (art. 34), y explicación oficial Minjus | Determinados incidentes deben notificarse a ANPD dentro de **48 h desde conocimiento**, y a titulares cuando afectan derechos; pueden existir notificaciones adicionales del entorno digital y documentación obligatoria | Las condiciones dependen de gravedad, tipo/volumen y derechos afectados. **No declarar que todo fallo simple obliga a 48 h ni automatizar reportes a ANPD** |
| **ANPD** — inscripción de flujos transfronterizos, página actualizada 22-05-2026 | Existe tratamiento administrativo para comunicar flujos de datos al exterior en supuestos aplicables | Hosting, pasarela, proveedor de email y mapas requieren inventario de destinos/roles; no inferir que una ubicación de CDN o un proveedor extranjero determina por sí sola todas las obligaciones |
| **WordPress Privacy** — exporter/eraser | WordPress ofrece filtros `wp_privacy_personal_data_exporters` y `wp_privacy_personal_data_erasers`; sus flujos están orientados a solicitudes **por correo**, incluso de usuarios no registrados | Reutilizar infraestructura nativa donde corresponda, **complementando compras solo con teléfono** que no tienen correo. Un eraser puede retener registros y justificar por qué |
| **WordPress REST / Nonces** | El nonce WordPress ayuda frente a CSRF pero **no prueba identidad ni concede autorización**; endpoints deben usar `permission_callback` y comprobar capacidad/recurso | Iniciar sesión como operador no permite acceder a datos de cualquier pedido por cambiar un ID; un enlace de seguimiento tampoco equivale a sesión WordPress |
| **OWASP** — tokens de recuperación y sesiones | Recomienda aleatoriedad segura, uso limitado, expiración, limitación de intentos, almacenamiento protegido, HTTPS y política no-referrer para URL con token | No usar RUC, número de pedido, teléfono, timestamp ni token de descarga como acceso administrativo |
| **Ley 29571, Libro de Reclamaciones**, y políticas E-03/E-06 | Reclamar es independiente de cuenta de comprador, y el expediente requiere confidencialidad/evidencia | No borrar o filtrar reclamos a usuarios no autorizados; plazos/formatos reales J-01/P0-25 pendientes |

**Fuentes oficiales de referencia:**
- https://www.gob.pe/institucion/anpd/campa%C3%B1as/128319-nuevo-reglamento-de-proteccion-de-datos-personales
- https://busquedas.elperuano.pe/dispositivo/SE/2349653-1
- https://www.gob.pe/institucion/congreso-de-la-republica/normas-legales/243470
- https://www.gob.pe/institucion/anpd/pages/8060-inscribir-banco-de-datos-en-el-registro-nacional-de-proteccion-de-datos-personales
- https://www.gob.pe/9253-inscribir-flujo-transfronterizo-de-datos-personales
- https://www.gob.pe/9270-que-son-los-derechos-arco
- https://www.gob.pe/9269-iniciar-procedimiento-para-el-ejercicio-de-derechos-de-acceso-rectificacion-cancelacion-y-oposicion
- https://www.gob.pe/institucion/minjus/noticias/1067368-ejecutivo-aprueba-nuevo-reglamento-de-la-ley-de-proteccion-de-datos-personales
- https://developer.wordpress.org/plugins/privacy/
- https://developer.wordpress.org/plugins/privacy/privacy-related-options-hooks-and-capabilities/
- https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-eraser-to-your-plugin/
- https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- https://developer.wordpress.org/apis/security/nonces/
- https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html
- https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet

**Verificación jurídica PENDIENTE:** lista de bancos sujetos a inscripción por cada comerciante, responsable y encargado de tratamiento, acuerdos con proveedores, flujos transfronterizos efectivos, consentimiento/base jurídica por finalidad, plazos ARCO/portabilidad aplicables, criterios de incidente notificable, evidencia y conservación tributaria. La solución no ejecutará trámites ante ANPD.

## 2. Invariantes ya aprobados: NO son opciones nuevas

1. D-24: **correo obligatorio si hay digital o servicio** y teléfono si hay físico; ambos si es mixto. Un físico puro puede comprar con solo teléfono.
2. D-34/D-85: pedido invitado consultable con **enlace privado no adivinable**; se muestra tras compra y se envía por email cuando existe; comerciante puede comunicarlo manualmente por teléfono **previa verificación de identidad**. El enlace permite **consulta solamente**, no autoriza por sí solo reemisión, cambio de dirección ni devolución.
3. D-40/D-71: token individual para descarga protegido **24 horas**; el derecho a descargar es distinto de la caducidad del token y admite reemisión segura.
4. D-47/D-78…D-85: administrador y operador con datos/capacidades distintos; operador ve **solo contacto necesario** para atención/entrega, no fiscales completos por defecto, secretos, exportación masiva, archivos digitales protegidos ni expedientes del Libro.
5. D-59/D-87/D-107: pedido confirmado, condiciones versionadas, cobros, movimientos y auditoría **no se reescriben destructivamente**; ajustes y correcciones son aditivos.
6. D-82/D-84: exportaciones masivas y expedientes del Libro son **administrativos y sujetos a ley**; no implican exposición ilimitada ni eliminación indiscriminada.
7. D-92: revocar digital previamente habilitado requiere causa válida y resolución administrativa con auditoría, no mero vencimiento de token.
8. D-121/D-127/D-130: outbox, notificaciones y registros no deben exponer secretos ni adjuntos digitales; no hay campañas ni marketing en V1.
9. D-132: el Libro nativo debe ofrecer constancia/alertas, sin equiparar correo enviado a respuesta legal acreditada.
10. D-114/D-115: solicitudes, eventos y tokens requieren idempotencia/recuperación; expiración por reloj efectivo, no depender de WP-Cron.

## 3. Responsable del tratamiento y distribución privada del plugin — E07-A APROBADO

**E07-A APROBADO (D-135):** una instalación = un comercio que controla las finalidades y comunica sus datos de contacto, política y receptores. PeruCommerce es **herramienta local** instalada por ese comercio, sin telemetría hacia el desarrollador ni base de compradores central obligatoria. El plugin muestra al administrador una **lista de obligaciones externas** (evaluación/inscripción de banco de datos ANPD, encargados, hosting/transferencias, privacidad, derechos, incidentes) y permite registrar que fueron revisadas; **no hace registros ni declara cumplimiento automáticamente**.

Si el desarrollador presta soporte con acceso real a datos del comercio o servicios gestionados, sus obligaciones como encargado y el contrato respectivo deben evaluarse por separado: no deducir «el desarrollador nunca es encargado» solo por distribuir un ZIP. No se exige cuenta del desarrollador al comprador ni al comerciante para comprar/usar el plugin.

## 4. Inventario de datos y autorización mínima — E07-B APROBADO

**E07-B APROBADO (D-136):** documentar **campos por finalidad y actor**, aplicar minimización por operación y salida REST, no por esconder columnas en Angular. El comercio configura sus datos de privacidad; el plugin registra evidencia mínima de la política informada al comprar (D-87), sin recopilar datos innecesarios para su clasificación fiscal.

| Clase | Datos necesarios según operación | Visita/seguimiento invitado | Operador | Administrador | Restricción |
| --- | --- | --- | --- | --- | --- |
| Identificación pública de orden | ID público no enumerativo, fechas, líneas/estados e importes pertinentes | **Solo pedido propio** y datos apropiados | Datos de atención | Atención/comercio | No exponer ID internos como autorización |
| Contacto | Nombre comercial del destinatario cuando requerido, teléfono para físico, email para digital/servicio | Enmascarado donde sea posible | Solo teléfono/email necesario para atender o entregar | Acceso justificado por finalidad | No buscar masivamente por contacto público |
| Domicilio/delivery | Distrito UBIGEO, domicilio textual, indicaciones mínimas y punto geográfico **solo si elegido por comprador** | Área/distrito y estado, no dirección completa por defecto | Dirección necesaria para entrega autorizada | Ver/autorizar conforme rol | No mostrar coordenadas precisas fuera de atención |
| Datos fiscales | RUC/DNI/razón social cuando aplique al comprobante externo, tipo de documento y referencia | Solo resumen del documento propio, sin datos fiscales completos por defecto | Sin fiscal completo por defecto D-78 | Con finalidad documentaria | No imponer DNI/RUC a toda compra |
| Pedido/pagos | Precio histórico PEN, método, estado, ajustes y reintegros realmente comprobados | Su propio resumen sin IDs/secretos del proveedor | Solo estado de cobro necesario para operar | Movimientos completos autorizados | No exponer token MP/credenciales |
| Digital protegido | Referencia interna del archivo, derecho, fecha de token y límites opcionales | Información de acceso de su pedido, token de descarga separado | Metadatos no sensibles, **nunca archivo** | Gestión protegida con finalidad | No adjuntar archivo por email ni publicar en medios WP |
| Servicio/cita | Servicio, franja, cambios, contacto mínimo | Su cita y estado, sin datos de otras personas | Agenda y contacto necesario | Administración | Sin campos de salud/intimidad predeterminados |
| Libro y reclamos | Relato, identidad, constancia, respuesta y evidencia | Formulario público y su constancia bajo verificación adecuada | Aviso operativo mínimo, **sin expediente** | Solo admin autorizado | No indexar reclamos ni enviarlos por email interno completo |
| Tokens/logs/auditoría | Hash de secreto, evento, recurso, actor, fecha, resultado/razón mínima | Nunca token interno, clave ni auditoría | Solo eventos operativos filtrados | Diagnóstico según necesidad | No registrar texto de contraseña, OTP, link privado o secretos |

Campos SQL concretos, niveles de acceso REST, mascarado y perfiles por endpoint permanecen **E-08/P0-18**. Si el comerciante vende servicios que requieren datos sensibles, V1 **no abre campos sensibles genéricos por defecto**; el tratamiento excepcional requiere revisión jurídica y decisión de producto específica.

## 5. Enlace privado de seguimiento por pedido — E07-C APROBADO

**E07-C APROBADO (D-137):** un **secreto opaco generado criptográficamente** con al menos **256 bits de entropía**, distinto del número de pedido, identificador de sesión, token de descarga y clave de recuperación. Guardar en base de datos **solo verificador criptográfico** (hash/HMAC con clave del servidor), ligado a un pedido/comercio y alcance **solo lectura**. No autenticar nunca por ID de pedido, nombre, teléfono o WordPress nonce.

- **Vigencia APROBADA D-137: 90 días** desde emisión inicial, con revocación/rotación anticipada. Tras vencer, el derecho de compra/reclamación/digital no caduca; solicitar nuevo enlace tras verificar identidad. La vigencia del enlace es **seguridad de acceso**, no plazo legal de conservación.
- Mostrar el enlace en confirmación real del pedido y enviarlo por correo cuando exista (D-34/D-123). Un token anterior revocado no vuelve a habilitarse por un reintento de correo.
- Ruta privada por HTTPS, `noindex`, cabeceras sin caché compartida y `Referrer-Policy: no-referrer`; no cargar analytics, píxeles ni recursos de terceros sobre la vista que recibe el secreto. Evitar que URL/token se guarde en logs, etiquetas de métricas o cabeceras del proveedor.
- **Protección contra filtración del token URL:** E-08 debe aprobar mecanismo de intercambio breve por sesión/cookie segura o equivalente y eliminación del secreto de URL de navegación visible, sin introducir cuentas de comprador. No afirmar que la cabecera no-referrer elimina historial/registro HTTP de por sí.
- La respuesta pública oculta campos internos y datos personales no indispensables; la capacidad de consulta no incluye exportación completa, modificación ni reemisión.
- Un pedido con varias líneas mantiene **un solo enlace de seguimiento**, sin conceder acceso a pedidos ajenos ni documentos fiscales completos.

**PENDIENTE E-08:** estructura de URL, cookies/cache, rol invitado, invalidación, rate limiting, cabeceras y formato de error. **90 días es decisión D-137 de seguridad de acceso**, no plazo legal ni duración de los derechos del comprador.

## 6. Verificación adicional para invitados con correo — E07-D APROBADO

**E07-D APROBADO (D-138):** para **solicitar** reemisión de seguimiento, cambio de dirección/dato sensible, devolución o reemisión de token digital, verificar **en el momento de la acción sensible** el control del correo registrado mediante un **código de un solo uso**, además de revalidar pedido, alcance y derechos. **No añadir un paso de OTP obligatorio al checkout normal**. Política APROBADA D-138: código **de 8 dígitos**, válido **10 minutos**, **máximo 5 intentos**, hash/HMAC y control de reenvíos por destinatario/pedido/IP con minimización, invalidado tras uso o sustitución.

- Un correo enviado no prueba que se recibió; solo el código correcto constituye la prueba adicional **de control de esa casilla**, no identidad civil ni autorización para aprobar una devolución financiera.
- Respuesta uniforme tanto si el correo/pedido existe como si no; no permitir enumeración de compradores ni ataques de envío masivo.
- No usar un OTP para consultar pedidos ajenos ni aceptar nuevo email sin comprobación suficiente del comprador original. Si la casilla registrada ya no es accesible, usar revisión administrativa verificada, sin recuperación automática al correo solicitado.
- En un pedido mixto, cambios de tarifa/cobertura o cualquier dinero acreditado siguen requiriendo aprobación de administrador según D-83.
- El formulario de reclamo público **no queda bloqueado** si no hay correo o si el comprador no posee un enlace; la verificación adicional se aplica a acciones sobre un pedido concreto, no al derecho general a reclamar.

**PENDIENTE E-08/J-04:** formato y verificación REST del OTP aprobado, límites de reenvío/abuso, alternativa si el correo falla, proporcionalidad de identificación y recuperación. No asumir que un OTP reemplaza el consentimiento ni la autorización E-02.

**DOC-QA30/D-199, apertura administrativa del expediente de solicitud parcial (2026-11-18):** cuando un comprador físico sin correo pide por teléfono reducir **parte** de las unidades todavía no despachadas, el administrador A puede abrir el caso mediante **R8-69** bajo `perucommerce_orders_read` y `perucommerce_orders_financial_adjust`. Es obligatorio confirmar control del **teléfono previamente registrado**, sin confiar en Caller ID, y un **segundo hecho del pedido no público**. `AdminManualCaseCreate.verification` registra las categorías y las atestaciones del actor WP real, una nota segura y la fecha del servidor: **NO** guardar teléfono completo de la llamada, respuestas del segundo factor, DNI, códigos OTP ni secretos en auditoría. Las atestaciones por sí solas no acreditan identidad: el administrador debe efectuar la verificación; ante disputa o insuficiencia no se abre caso válido para R8-26, sino que se eleva el control conforme E07-E. `pc_cases.evidence_snapshot` y primer `pc_case_events` conservan la solicitud inicial con minimización; la lista de operador no recibe casos ni pruebas. R8-69 solo abre `open_review` y no libera stock ni aprueba ajustes. El expediente se recupera por R8-21 con `case_id` solo a A y se decide por R8-26 en otra solicitud autorizada e idempotente. Se mantienen canales de teléfono/WordPress Privacy previstos, sin nueva cuenta de comprador, servicio externo, tabla o permiso O. **No implementación ni verificación real.**

## 7. Invitados solo con teléfono y atención manual — E07-E APROBADO

**E07-E APROBADO (D-139):** sin email, la compra física es plenamente válida; consulta por enlace ya recibido sigue siendo lectura. **Reemisión de enlace/operación sensible se tramita manualmente desde panel tras comprobar legitimación**, registrar quién revisó, qué factores no públicos fueron verificados y el canal original usado.

- La mera coincidencia entre **número de pedido, nombre o teléfono escritos** no es prueba suficiente. Verificar control del teléfono previamente informado y un segundo dato del expediente que no aparezca en el enlace/consulta pública; **no pedir fotografía de DNI por defecto** ni almacenar copias de identidad innecesarias.
- Si hay disputa de titularidad, número cambiado, pérdida de teléfono o solicitud de datos altamente sensibles, **elevar al administrador** con evidencia proporcional; operador solo gestiona contacto que su capacidad permita y no abre expedientes fiscales o del Libro.
- Nunca enviar nuevo enlace/token a un teléfono/email distinto únicamente porque alguien lo solicite. Si se aprueba cambio de contacto, crear ajuste y mantener original histórico (D-59/D-65) con identidad comprobada.
- La ausencia de email no bloquea recepción de reclamos legales; canal del Libro nativo/externo permanece accesible conforme P0-25.

**PENDIENTE E-08/J-04:** cómo documentar y verificar de forma proporcional la legitimación telefónica, datos mínimos del acta, permisos y respuestas seguras; no simular pruebas de identidad por registrar una llamada.

## 8. Descarga digital y reemisión — E07-F APROBADO

**E07-F APROBADO (D-140):** cada URL de descarga **token independiente** del seguimiento, 256 bits aleatorios y verificador solo hash/HMAC, ligado a **derecho de la línea, comprador autorizado, comercio, archivo, emisión y vencimiento a 24 h** (D-40). No vence el derecho al expirar el enlace. Los límites opcionales de descargas son configurables; no imponer cuota por defecto (D-32).

- Entrega de archivo mediante backend autorizado, no enlace público directo a ruta de uploads, media library o almacenamiento indexable. Ningún archivo protegido se manda adjunto por correo.
- **No** usar nonce de WordPress como token de invitado ni como autorización de descarga. HTTPS, cabeceras anti-caché/anti-indexación, control de referrer/tokens y antiabuso.
- Reemisión solo después de verificar derecho vigente, pago acreditado y legitimación (E07-D/E); invalidar token anterior aún válido por rotación segura, sin revocar el derecho ni modificar cobro.
- Registrar evento de descarga mínimo (linea/recurso/fecha/resultado, y datos de diagnóstico estrictamente necesarios); no grabar direcciones IP completas de forma indefinida ni token en claro.
- Tras revocación legítima D-92, todos los tokens derivados dejan de autorizar acceso aunque su TTL no haya transcurrido; mantener historia.

**PENDIENTE E-08/P0-19:** protección de archivos WordPress reales, integración de rutas, cabeceras en LiteSpeed/Hostinger y verificaciones de descargas concurrentes. El contrato E-07 no autoriza elegir SaaS de almacenamiento externo.

## E-08 — sesión privada, archivos y autorización REST APROBADOS EN POLÍTICA (D-149…D-166)

El contrato `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` ratifica misma-origin WordPress, cookies de empleados con nonce CSRF, secreto de seguimiento en **fragmento URL** canjeado por HTTPS por una cookie HttpOnly/Secure/SameSite de **solo lectura**; OTP D-138 para solicitudes sensibles y revisión manual de teléfono. Descargas digitales usan endpoint PHP que verifica derecho y token distinto de 24 h, nunca archivos HTTP públicos; si el alojamiento no ofrece almacenamiento privado seguro, se bloquea esa modalidad de venta.

D-137/D-138/D-140/D-141/D-147 mantienen límites de 90 días de seguimiento, OTP 8 dígitos/10 min/5 intentos, archivos protegidos y antiabuso. **Pendientes:** OpenAPI/DTO y permisos por campo implementados, controles de cookies/caché/CSRF en LiteSpeed/hosting, pruebas de IDOR y normativa J-04/J-05/P0-25. La aprobación no demuestra seguridad operativa ni certificación ANPD.

## 9. Acceso administrativo y autorizaciones servidor — E07-G APROBADO

**E07-G APROBADO (D-141):** panel de administrador/operador usa **identidad y sesión nativas de WordPress**, con capacidades específicas del plugin y autorización **por operación + recurso + campo**, comprobadas por servidor. Nonce WordPress REST es **protección CSRF**, NO autenticación ni permiso por sí solo. Las rutas de invitados usan sus tokens propios y nunca heredan capacidad de admin/operador.

- Operador: contacto mínimo para atender pedidos, dirección solo cuando hace entrega, inventario, agenda y eventos necesarios; sin datos fiscales completos por defecto, exportaciones masivas, credenciales, archivos digitales protegidos ni expediente del Libro (D-78/D-82/D-84).
- Administrador: control comercial/financiero según D, lectura justificada, nunca bypass general sobre información de cualquier instalación distinta ni exportación sin finalidad/registro.
- Sistema/MP: autenticación de callbacks y consultas a proveedor con correlación, sin impersonar administrador. Permisos de servidor no se infieren de enviar un nonce en Angular.
- Gestión de roles: asignación y retiro auditados, y acceso revocado cuando cambie el rol. Los datos de invitados no requieren registrar cuentas WordPress.

**E-08 pendiente:** nombres exactos de capabilities y endpoints, `permission_callback`, cookies/nonces, CSRF, cambio de permisos y pruebas de acceso. No se autorizan herramientas JWT ni autenticación propia para empleados sin una decisión posterior.

## 10. Información/consentimiento/finalidades y terceros — E07-H/E07-I APROBADOS

**E07-H APROBADO (D-142):** política de privacidad del **comerciante** y aviso accesible antes de finalizar compra, con identidad del responsable, categorías/finalidades, prestadores que reciben datos, mecanismo de derechos, conservación y contactos. Registrar la **versión del aviso efectivamente informado** por pedido (similar a D-87) con evidencia mínima y sin insertar una casilla genérica obligatoria de «marketing».

- No bloquear compras por rechazar publicidad ni usar casilla preseleccionada de promociones: **marketing excluido por D-125**. La base jurídica del tratamiento necesario para ejecutar compra/reserva/pago **debe documentarse por finalidad**, no fingir consentimiento irrestricto ni asumir que el tratamiento siempre requiere la misma casilla.
- Tratar de forma proporcional los datos de clientes invitados y la eventual presentación del Libro sin compra; informar cuando corresponda sin impedir ejercicio del derecho.
- Una petición ARCO no se resuelve alterando silenciosamente el snapshot de una compra confirmada ni publicando PII en email.

**E07-I APROBADO (D-143):** inventario de procesadores y transferencias **por instalación**: hosting real y ubicación, Mercado Pago, proveedor de correo/SMTP si existe, mapas/tiles/geolocalización y servicios que efectivamente vean datos. Activar solo integraciones que el comercio configure, con aviso de transferencia/encargo y evaluación de registro/flujos ANPD **cuando correspondan**; no activar trackers, telemetría del desarrollador ni fuentes remotas innecesarias por defecto.

- Si la tienda es local pero su hosting/procesador está fuera, el responsable revisa flujos transfronterizos efectivos y contratos; no derivar que todas las comunicaciones sean transferencias obligatoriamente inscribibles por simple marca comercial.
- No seleccionar/proponer automáticamente proveedor de tiles como si el mapa fuera neutral frente a privacidad; P0-21/E-08 determinan proveedor, costos y atribuciones.

## 11. Mapa y geolocalización — E07-J APROBADO

**E07-J APROBADO (D-144):** **no** solicitar geolocalización al abrir catálogo/carrito por defecto. Mostrar el mapa/ubicación solo cuando el comprador elija proporcionar una ubicación para su delivery; informar si recursos cartográficos remotos contactarán a terceros. Mantener siempre dirección manual y tarifa basada en distrito UBIGEO, **no** en coordenadas como fuente de verdad (D-23/D-44).

- Geolocalización precisa del navegador requiere acción del usuario y permiso correspondiente; no persistir lat/lon si la ubicación manual basta. No guardar historiales de posición/rutas ni compartir direcciones exactas con proveedor ajeno por defecto.
- En checkout y seguimiento privado, excluir recursos de terceros que puedan recibir tokens o URLs sensibles, incluso imágenes de tracking y scripts no necesarios.
- La modalidad Leaflet y el proveedor tile exacto permanecen P0-21/E-08; no afirmar que usar Leaflet elimina la transferencia de IP al servidor de mapas.

## 12. Derechos personales, exportación y eliminación — E07-K APROBADO

**E07-K APROBADO (D-145):** usar **exportadores y borradores de datos personales nativos de WordPress** para pedidos con email, con adaptadores del plugin que alcancen sus tablas propias y datos correspondientes. Para pedidos **solo con teléfono** y solicitudes por canales permitidos, ofrecer un **expediente administrativo de privacidad con legitimación comprobada**, exportación segura y aplicación de medidas proporcionadas, sin exigir crear cuenta o agregar correo obligatorio.

- Informar al titular el derecho pertinente (acceso, rectificación, cancelación, oposición y portabilidad cuando resulte exigible); verificar identidad en proporción al riesgo antes de revelar datos.
- La exportación del interesado incluye **solo sus datos** y metadatos pertinentes, nunca información de otros pedidos/personas ni claves de proveedores. Las exportaciones masivas de comerciantes siguen exclusivas de administrador D-82 y requieren finalidad/registro.
- La solicitud de eliminación se **evalúa**: borrar/anonimizar campos cuya conservación ya no tenga fundamento y **retener** los exigidos para venta, obligaciones fiscales, garantía, Libro de Reclamaciones, disputa, seguridad o defensa conforme a norma. No borrar indiscriminadamente movimientos/snapshots históricos D-59/D-107.
- WordPress eraser acepta que un registro permanezca si hay causa; documentar de forma comprensible lo retenido y motivo, con acceso restringido.
- Conservar evidencia de solicitud, verificación, resolución, fecha y respuesta, sin incorporar copias de documentos de identidad por defecto.

**Pendiente matriz legal J-04/P0-25:** plazos de respuesta actuales por derecho, excepciones, portabilidad, bases jurídicas de retención, prueba de remisión y representación de compradores sin email. El flujo nativo WP basado en email no resuelve automáticamente teléfonos sin cuenta.

**Ciclo de vida P0-26 — diseño posterior (2026-11-17):** `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md` distingue backup consistente de BD, binarios privados y configuración protegida, retención legal, reinstalación sin perder derecho, y **uninstall de PeruCommerce no destructivo por defecto**. El restablecimiento puede invalidar cookies/verificadores si faltan claves HMAC; reemitir tras comprobación de identidad, sin inventar accesos. Nada ejecutado o legalmente certificado.

## 13. Conservación, copias, desinstalación y secretos — E07-L APROBADO

**E07-L APROBADO (D-146):** política de conservación por **categoría y fundamento**, no por borrar todos los pedidos después de un número arbitrario de meses/años:

| Categoría | Criterio de política E-07 aprobado o invariante previo | Plazo/implementación por cerrar |
| --- | --- | --- |
| Pedido confirmado, sus líneas, movimientos/cobros y ajustes | Historial inmutable, acceso según capacidad, posibilidad de anonimización solo de campos legalmente prescindibles | SUNAT/consumidor/defensa J-03/P0-25 |
| Libro de Reclamaciones y constancias de respuesta | Conservación conforme formato/plazo legal y restricciones de confidencialidad | J-01/P0-25 |
| Tokens activos de seguimiento | Almacenar verificador hasta caducidad/revocación; retirar tokens ya inutilizables en limpieza segura | **90 días de vigencia inicial APROBADOS D-137**; limpieza/retención técnica y rotación E-08/P0-26 |
| Tokens digitales | Expiran a **24 h** conforme D-40; conservar solo evidencia mínima de emisión/descarga mientras proceda | Datos técnicos y documentación E-07/E-08 |
| OTP de confirmación | **10 minutos, 8 dígitos y 5 intentos APROBADOS D-138**; invalidar tras uso | Control de abuso/reintentos y diseño de almacenamiento E-08 |
| Carritos no convertidos, admisiones y holds vencidos | Purga limitada al riesgo operativo y necesidad de defensa, sin romper idempotencia E-05 | E-05/P0-18/P0-26 |
| Inbox/outbox y auditoría técnica | Conservar referencias para deduplicar/reconciliar, sin tokens/PII innecesarios | Matriz de plazos E-07/E-08/P0-26 |
| Backups y exportaciones | Protección/rotación y eliminación conforme política de copias; no dejar archivos descargables públicamente | P0-26/hosting/ley |

- Desinstalar ZIP **no elimina por defecto** pedidos, pagos, Libro ni obligaciones legales. Requerir una función **administrativa separada** y advertencias para purga al finalizar periodos aplicables, compatible con P0-26.
- No almacenar credenciales, OTP, tokens, PIN, archivos digitales protegidos o exportaciones en logs/medios públicos. Secretos de pasarela se administran solo por administrador y mecanismo seguro sin imprimir en JS.
- Evitar inventar cifrado casero o suponer que WordPress salts por sí solos cifran automáticamente datos en BD; estrategia real de protección de secretos y backups en E-08/P0-19.
- Responder a solicitudes de supresión de forma coherente también en copias/respaldos según ciclo de retención, evitando prometer eliminación física inmediata de backups fuera de control.

**NO RESUELTOS POR E-07:** períodos legales exactos de conservación, formato y medios de exportación, copias/rollback y motor físico. D-146 fija la política por categoría/fundamento y desinstalación no destructiva, sin establecer por sí sola plazos fiscales o del Libro.

## 14. Abuso, exposición por caché/logs e incidentes de seguridad — E07-M/E07-N APROBADOS

**E07-M APROBADO (D-147):** controles de acceso y antiabuso transversales:
- HTTPS en vistas de pago, seguimiento, datos personales, descargas y panel. Enlaces con secretos nunca se incluyen en scripts/consultas externas ni analytics.
- `Cache-Control: private, no-store` o política equivalente en páginas/respuestas privadas; **no caché de página compartida** en LiteSpeed/WordPress, CDN ni navegador de tercero que exponga pedidos. Un `noindex` no sustituye autorización.
- Rate limiting server-side para acceso con enlace, OTP, recuperación, login, descargas, Libro y exportaciones; respuestas indistinguibles para pedidos/contactos existentes y no existentes, sin revelar datos de otros.
- Mitigar enumeración, fuerza bruta, CSRF, replay, IDOR, divulgación en `Referer`, logs, snapshots de diagnóstico, backups públicos y búsquedas de pedidos por texto público.
- Fallos de verificación no crean pedidos, no rotan enlaces, no liberan archivos, no conceden capabilities, no envían claves y no modifican montos.

**E07-N APROBADO (D-148):** protocolo administrativo **de incidentes de datos personales**, distinto de incidencia de pago/stock: registrar descubrimiento, alcance, categorías, personas afectadas, evidencia protegida, contención, responsables, medidas y comunicaciones. Si se cumplen supuestos del **art. 34 DS 016-2024-JUS**, mostrar alerta con evaluación de **48 horas** desde conocimiento para notificar ANPD/titulares cuando corresponda, y otras autoridades cuando aplique. **No** enviar automáticamente notificaciones regulatorias ni declarar «no notificable» a todo caso sin revisión. E-06 provee bandeja/avisos, pero exige acceso solo administrativo; E-07/E-08 determinan datos/plantillas/evidencia.

**Pendiente J-05/P0-25:** umbrales exactos de notificación, eventual responsable/encargado y flujo de atención ante la autoridad, cumplimiento documental, plazos efectivos y eventuales condiciones especiales.

## 15. Matriz de amenazas y negativos para E-08/P0-18

| Riesgo / intento | Decisión de política necesaria | Efecto esperado |
| --- | --- | --- |
| Adivinar un número de pedido secuencial | D-34/D-85 y E07-C | No revela datos ni habilita acceso |
| Compartir enlace con un tercero | E07-C, E07-M | Consulta limitada mientras sea válido; rotación para pérdida/filtración |
| Usar enlace de seguimiento como autorización de devolución | D-85, E07-D/E | Bloqueado; verificación adicional |
| Usar nonce WP de visitante para pedido ajeno | E07-G | No es prueba de identidad ni capacidad |
| Operador consulta expediente completo del Libro | D-84, E07-B/G | Denegado incluso por REST directo |
| Exportar todos los compradores mediante operador | D-82 | Denegado, sin diferencia entre UI y endpoint |
| Descargar archivo protegido mediante URL de uploads | D-40, E07-F/M | Acceso denegado sin derecho/token válido |
| Reemitir descarga a correo nuevo no verificado | E07-D/E/F | Negado o revisión administrativa proporcional |
| Enviar OTP repetidamente para descubrir clientes | E07-D/M | Límite y respuesta no enumerativa |
| Cachear seguimiento en LiteSpeed público | E07-C/M | No se cachea ni expone a terceros |
| Mapas/analytics cargan en vista con token privado | E07-I/J/M | Evitar recurso de terceros que lea URL/token |
| Dos pedidos comparten teléfono | E07-E/K | Verificación por pedido, no entrega de ambos por teléfono coincidente |
| Solicitud ARCO sin email | E07-K | Canal administrativo permitido y respuesta tras verificación |
| Solicitud de eliminación con cobro SUNAT/libro activo | E07-K/L | Evaluar excepciones legales, no borrar original irrecuperable |
| Desinstalación ZIP con historial fiscal | E07-L | No eliminar automáticamente evidencias |
| Incidente serio de datos | E07-N | Detección, contención, evaluación normativa, aviso admin y evidencia |
| Token de descarga vencido | D-40/E07-F | No descargar por token viejo; derecho no vence automáticamente |
| Exportación insegura en uploads compartidos | E07-K/L/M | No exponer contenido exportado públicamente |

## 16. Escenarios documentales E-07, NO EJECUTADOS

| ID | Entrada / escenario | Resultado de referencia propuesto |
| --- | --- | --- |
| P7-X01 | Cliente físico completa pedido con teléfono sin email | Compra y seguimiento WEB válidos; no exigir crear usuario/correo |
| P7-X02 | Cliente de servicio/digital compra sin correo | Solicitar correo obligatorio aplicable según D-24, sin teléfono innecesario |
| P7-X03 | Cliente mixto | Solo campos aplicables de ambas familias; no DNI/RUC universal |
| P7-X04 | Atacante cambia ID pedido sin token válido | No revela datos ni existencia del pedido |
| P7-X05 | Token válido de pedido A se usa para pedido B | Acceso denegado, no se puede consultar B |
| P7-X06 | Token de seguimiento venció a 90 días | No exponer datos; solicitar recuperación verificada sin eliminar pedido |
| P7-X07 | Token de seguimiento se filtra por referrer/analítica | Página privada no comparte URL/secreto con terceros ni se sirve por caché |
| P7-X08 | Comprador solicita cambio dirección con solo enlace | Exigir verificación adicional D-85, aunque vea seguimiento |
| P7-X09 | OTP a correo vinculado, correcto y vigente | Habilita solicitud sensible del pedido, no aprueba devolución automáticamente |
| P7-X10 | OTP vencido/usado/repetido o con intentos excesivos | Denegar sin revelar otros pedidos ni conservar OTP plano |
| P7-X11 | Comprador dice haber cambiado de email | No reemitir al nuevo correo sin verificación proporcional |
| P7-X12 | Invitado solo teléfono pide nuevo enlace por llamada | Tramitar manualmente tras verificación y auditoría, no por número escrito |
| P7-X13 | Persona presenta teléfono de otro comprador y número de pedido | No acceso ni reemisión por mera coincidencia |
| P7-X14 | Se intenta registrar WordPress como invitado obligatoriamente | Denegar requisito de cuenta en V1 |
| P7-X15 | Operador intenta exportar emails/direcciones masivamente | Denegado en backend D-82 |
| P7-X16 | Operador llama REST para leer fiscal completo | Denegado D-78 |
| P7-X17 | Operador lee reclamo íntegro aunque tenga aviso operativo | Denegado D-84 |
| P7-X18 | Admin descarga exportación del comprador de otra cuenta/tienda | Denegado; ámbito de comercio y autorización |
| P7-X19 | Digital no pagado y token construido manualmente | Acceso denegado aunque token tenga formato correcto |
| P7-X20 | Token digital 24 h vencido con derecho vigente | Token viejo no descarga; reemisión segura tras verificación |
| P7-X21 | Derecho digital revocado con token aún vigente | Acceso denegado por estado del derecho |
| P7-X22 | Archivo digital alojado bajo ruta pública WP | No entrega directa; exigir endpoint protegido E-08 |
| P7-X23 | wp_mail acepta email pero este no llega | No afirmar recepción; seguimiento WEB y aviso de incidencia E-06 |
| P7-X24 | Se carga Leaflet con proveedor externo sin intervención comprador | D-144: no activar geolocalización automáticamente; informar tercero y mantener dirección manual |
| P7-X25 | Cliente rechaza publicidad opcional | Checkout sigue funcionando, sin campañas D-125 |
| P7-X26 | Comerciante activa proveedor de email o mapas externo | Inventario de encargado/transferencia e información al titular; no afirmar ausencia de obligaciones |
| P7-X27 | Cliente pide acceso ARCO por email | Flujo WP exportador con identidad y permisos, solo sus registros |
| P7-X28 | Cliente físico con solo teléfono pide ARCO | Canal manual verificado, sin exigencia de nueva cuenta/correo |
| P7-X29 | Cliente pide borrar pedido facturado con disputa abierta | Evaluar retención legal y anonimización permitida, no destruir evidencia |
| P7-X30 | Plugin se desinstala con pedidos y Libro | Datos obligatorios permanecen hasta proceso administrativo legal |
| P7-X31 | Archivo exportado queda en carpeta pública por error | Bloqueo de exposición y alerta; no dejar URL permanente |
| P7-X32 | Se consulta pedido tras caducidad de sesión privada | Pedir recuperación; no volver a usar token antiguo |
| P7-X33 | Llega incidente de filtración en hosting | Registro y evaluación de reportabilidad condicional art.34, alerta y evidencia |
| P7-X34 | Admin quiere mandar newsletter a compradores anteriores | Funcionalidad excluida V1 D-125; no usar contacto contractual para marketing |
| P7-X35 | Petición de datos de comprador usando email ajeno | No enviar exportación sin verificación de titular |
| P7-X36 | Se verifica identidad telefónica con un dato público del seguimiento | No aceptarlo como segundo factor suficiente |
| P7-X37 | WP nonce válido pero usuario sin capability | Endpoint denegado |
| P7-X38 | Compra física con indicaciones y geolocalización optativa | Solo persistir datos necesarios para delivery, permitir dirección manual |
| P7-X39 | Política privacidad cambió tras pedido confirmado | Conservar versión histórica informada y nueva solo para compras posteriores |
| P7-X40 | Contrato de hosting o servicio usa procesamiento extranjero | Revisar responsable/encargado, transferencias y aviso según hechos; no inferir registro automático |
| P7-X41 | Se pierde correo original, reemisión digital solicitada | Administración verifica proporcionalmente; no perder derecho por inaccesibilidad del email |
| P7-X42 | Usuario sin email registra reclamo público | Permitido conforme datos/formato legal sin registro de cuenta y con constancia |

**Ningún P7-X equivale a prueba ejecutada, auditoría legal completada ni protección demostrada en hosting.** Las políticas E07-A…N quedaron aprobadas como D-135…D-148; validar requiere E-08/P0-18, matriz jurídica J-04/J-05/P0-25 y pruebas expresamente autorizadas.

## 17. Registro de aprobación E-07 — CERRADO EN POLÍTICA DE PRIVACIDAD/SEGURIDAD

**Aprobación expresa (2026-11-17):** el propietario confirmó **«Todas A en E-07»**. Se registran como **D-135…D-148**. Las opciones B quedan descartadas. La aprobación es funcional y técnica de política; no certifica conformidad ante ANPD, seguridad del hosting ni derechos de tratamiento realmente comprobados.

| Origen | Decisión | Política A aprobada |
| --- | --- | --- |
| E07-A | D-135 | Un comercio responsable por instalación; sin base central ni telemetría del desarrollador; checklist ANPD/encargados y trámites externos cuando correspondan |
| E07-B | D-136 | Minimización de datos por finalidad, rol, operación y campo; sin formularios sensibles genéricos V1 |
| E07-C | D-137 | Seguimiento privado de un pedido, token opaco con 256 bits, verificador protegido, solo lectura, **90 días de vigencia inicial**, rotación y recuperación verificada |
| E07-D | D-138 | Acciones sensibles de cliente con correo: OTP de **8 dígitos**, **10 minutos**, **máximo 5 intentos**, validación y antiabuso; no al checkout normal |
| E07-E | D-139 | Compradores solo teléfono: procedimiento de identidad manual, proporcional y auditado; sin confiar solo en número/nombre/pedido |
| E07-F | D-140 | Token digital independiente 256 bits, 24 h según D-40, hash y archivo protegido; reemisión tras verificar identidad/derecho |
| E07-G | D-141 | Empleados autenticados por WordPress y capacidades del plugin por operación/recurso/campo; nonce no equivale a autorización |
| E07-H | D-142 | Aviso de privacidad propio del comercio con finalidades, responsables, versión y evidencia de presentación, sin marketing por defecto |
| E07-I | D-143 | Inventario de prestadores/transferencias por instalación, sin trackers ni telemetría central por defecto |
| E07-J | D-144 | Geolocalización/mapa únicamente por elección del comprador; dirección manual y tarifa UBIGEO siempre disponibles |
| E07-K | D-145 | Exportadores/borradores WordPress para pedidos con correo y expediente administrativo verificado para clientes solo teléfono; retención legal justificada |
| E07-L | D-146 | Conservación por tipo/fundamento legal y desinstalación sin purga automática; eliminación administrativa separada cuando proceda |
| E07-M | D-147 | HTTPS, rate limiting, antifiltraciones, errores no enumerativos y caché privada para enlaces, descargas, datos y panel |
| E07-N | D-148 | Registro y alerta de incidentes de datos, evaluación jurídica condicional del plazo de 48 h y evidencias; sin envío regulatorio automático |

**No extender por inferencia:** D-137 no fija plazo de conservación comercial; D-138 no sustituye autorizaciones del comerciante; D-146 no declara plazos legales; D-148 no significa que cualquier incidencia de stock/email exija notificación a la ANPD. El mecanismo REST y las interfaces concretas siguen E-08.

## 18. Contratos bloqueantes tras decidir E-07

- **J-04 / P0-25:** matriz por sujeto/instalación de responsable/encargado, banco de datos e inscripción ANPD, transferencias, avisos, finalidades, derechos ARCO/portabilidad, conservación y forma legal de verificar identidad.
- **J-05 / P0-25:** condiciones de incidentes notificables bajo DS 016-2024-JUS, plazos y destinatarios (ANPD/titulares/CNSD según caso), prueba y responsabilidades.
- **E-08:** capacidades/rutas REST, sesión/nonce/cookies, token y OTP exactos, cabeceras anti-caché, almacenamiento de archivo protegido, rate limiting, verificación de proveedor y errores seguros.
- **P0-18/P0-19:** esquema e índices de tokens, expiración, privacidad, roles, protección de secretos, PHP/BD/host, WordPress/LiteSpeed, email/HTTPS y versión exacta.
- **P0-21:** proveedor de mapas/tiles, ubicación/transferencias/atribuciones y fallback real.
- **P0-24:** licencia/complementos y distribución privada/gratuita, compatibilidad legal de dependencias.
- **P0-26:** retención por categoría, backups, exportaciones, revocación, migraciones y desinstalación preservando registros obligatorios.
- **E-06:** política de comunicación ya aprobada D-123…D-134, pero faltan campos/redacción final y evidencia legal según E-07/E-08/P0-25.
- **Seguridad/validación futura autorizada:** pruebas de exposición de URLs/cache, IDOR, roles, abuso, revocación, datos ARCO, accesos digitales e incidentes; documentar sin ejecutar.

**Estado de salida: E-07 CERRADO EN POLÍTICA FUNCIONAL DE PRIVACIDAD Y SEGURIDAD (D-135…D-148).** E-08 sigue pendiente para interfaces/autorización/archivos y P0-18/P0-19/P0-21/P0-26 y J-04/J-05/P0-25 para esquema, hosting, mapas, conservación y legislación. Los 42 escenarios P7-X no se ejecutaron. **No se autoriza Codex a escribir código todavía.**


## Rectificación del primer enlace de seguimiento (2026-11-18)

El contrato de checkout R8-06/07 devuelve `tracking_url_once` **solo en la primera respuesta segura** después de existir pedido válido; para Mercado Pago se exige además intento/hold local válido. El secreto de 256 bits aparece en el **fragmento URL** y se persiste únicamente como hash/HMAC; `tracking_link_issued` permite distinguir emisión de recuperación sin revelar el secreto. Ante replay idéntico o pérdida de respuesta, **no se recupera la URL secreta** de BD/response_ref, no se rota automáticamente el token y se usa reemisión R8-58 tras verificación de identidad. En respuesta MP `202` todavía sin pedido materializado no se emite enlace ni se afirma pago. Evitar que el enlace se envíe a Mercado Pago o quede en caché, Referer, analítica o outbox. Cuando existe email, el envío inicial del enlace solo puede usar el secreto de forma efímera después del commit y fuera de transacción; `wp_mail` puede fallar y la outbox reintentable conserva exclusivamente aviso **sin secreto**. La recuperación posterior exige nueva reemisión legítima, no fabricar un correo ni guardar token reversible. No alterar derechos por pérdida de enlace.

## Versionado de avisos y control de exposición — 18-11-2026
R8-01 publica texto completo de condiciones/privacidad y referencias a las versiones efectivas **antes de que Angular solicite datos de contacto**. R8-05 vuelve a presentar ambos avisos/versiones y R8-06/07 compara IDs y fingerprint; si hay cambio, el comprador debe revisar y aceptar de nuevo, sin fingir aceptación por un campo arbitrario. R8-45/46 restringe su edición a A: nuevo texto → fila inmutable `pc_policy_versions`, CAS de opción WordPress que apunta a la versión actual y evidencia de información del pedido. Un `privacy_url` no sustituye contenido documentado/mostrado. Si la instalación carece de condiciones/avisos válidos, checkout permanece deshabilitado; ninguna política genérica ficticia es admisible.

R8-21 puede incluir `digital_rights` y `cases` y R8-31 `digital_assignments` solo a A con permisos adicionales según contrato, totalmente **omitidos** en O. R8-39 omite referencias a recursos ocultos para el operador. No se expone ningún token, almacenamiento privado, documento de identidad, secreto MP o enlace de descarga. El contenido normativo aplicable sigue sujeto a cada comercio y J-01…J-05, no probado por esta documentación.

## Tratamiento de casos originales y cuotas/mapas — corte 0.8.0-draft

`AdminOrder.case_detail` de R8-21 solo aparece para A con orders_read Y capacidad específica de expediente, al solicitar `case_id` perteneciente a su pedido/instalación. `original_request` puede contener nueva dirección, motivo y relato aportados; son **datos personales** y no se devuelven a O, no se envían por correo/analytics, no se cachean ni se usan en enlaces públicos. Los eventos paginados también tienen control de finalidad; referencias `evidence_ref` no autorizan descarga privada. No reconstruir el pedido desde datos actuales sustituyendo la solicitud original.

`OrderAgreedNotices` se obtiene de las versiones originales guardadas por pedido; esos textos son públicos, pero solo se entregan al invitado con acceso legítimo a SU pedido o al personal con `orders_read` del pedido correspondiente, no por `order_id` público. Cambiar políticas después de la compra no afecta el texto histórico ni resuelve automáticamente un expediente.

`digital_download_limit` de R8-45/46 es `null` ilimitado o entero positivo, por comercio y para derechos FUTUROS; `pc_download_rights.optional_limit` es foto fija histórica. El número de transferencias se incrementa de manera atómica solo después de autorizar e iniciar stream; no equivale a prueba de que llegaron todos los bytes. No exponer el contador de otros clientes al operador o visitante. Una desconexión iniciada puede gastar uso y necesita atención según derechos aplicables. El token 24 horas y derecho no cambian por la configuración global.

El `map_tiles` público de R8-01 es metadato sin credenciales; solo aparece si origen/atribución están validados y habilitados. No cargar tiles ni revelar Referer/IP al tercero hasta decisión explícita del comprador de abrir mapa en una escena sin secretos. En páginas de seguimiento, expedientes, OTP/descarga se mantiene `no-store` y sin tiles. La capa opcional no sustituye UBIGEO ni dirección manual.


### IMP-50 (2026-11-20) — Fuente ARCO bajo verificación real, sin borrado automático

`Privacy_Intake` captura el WordPress `user_request_action_confirmed` solo cuando la solicitud core se confirma para `export_personal_data`/`remove_personal_data`, y **nunca presume identidad civil por el mero enlace de correo**. Los canales manuales verificados P-C (presencial, carta, teléfono o correo válido) ingresan mediante API PHP A interna `receiveManual`, sin nueva alta pública REST ni exigir cuenta. La legitimación requiere evidencia privada del mismo expediente, origen y verificadores P-C antes de fijar `verified_at`. `Privacy_Admin` R8-43/66 minimiza lista y protege detalle; R8-44 prepara decisión, pero solo la marca como comunicada tras constancia de entrega autenticada y examen de retención por finalidad. No se borran datos de ventas, pagos, Libro ni archivos por interpretar `resolution=fulfilled` o `cancellation`. Se preserva el estado legal y la evidencia; el exportador WP se limita a aviso transparente hasta implementar el export ARCO individual R8-50 en IMP-51 y el eraser reporta revisión pendiente sin simular supresión. El plazo **base** 20 días hábiles acceso, 10 días R/C/O se deriva de calendario oficial P-C, sin calendarizar suspensiones/subsanaciones/extensiones normativas por inferencia. `Privacy_Incident` registra solo categoría técnica y hash de origen verificado, no es un registro completo de investigación ni avisa a autoridad sin supuesto legal. Sin código ejecutado, tests, correo, migraciones o despliegue; las pruebas P-C/P-D de identidad, datos, host, transporte y normativa por instalación están pendientes.
