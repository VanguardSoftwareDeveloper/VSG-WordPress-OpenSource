# E-02 — Matriz de permisos y autorizaciones V1

**Estado: E-02 CERRADO EN POLÍTICA FUNCIONAL D-78…D-85; E-07 APROBADO EN POLÍTICA DE DATOS D-135…D-148; implementación técnica pendiente.** Doc.20 fija mínimos de datos, identidad de invitados, enlace de 90 días, OTP sensible, WordPress Privacy y conservación por fundamento. Las 56 operaciones PER aún requieren capabilities/REST/campos concretos E-08. No autoriza código, pruebas ni despliegue.
**Propósito:** especificar exactamente qué puede ver, solicitar y modificar cada actor, separando el rol de la capacidad efectiva y la elegibilidad del recurso. **No autoriza código, pruebas, configuración de usuarios, migraciones, credenciales ni despliegues.**
**Precedencia:** decisiones confirmadas **D-01…D-85** en [01](01-DECISIONES-Y-ESTADO.md); E-01 aprobado en [14](14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md); disparadores generales en [12](12-CONTRATO-ESTADOS-TRANSICIONES-Y-PERMISOS.md) y escenarios en [13](13-MATRIZ-DE-HUECOS-Y-ESCENARIOS.md). Ante discrepancia prevalecen las decisiones D y la restricción de capacidades/mínimo acceso aquí confirmada.

**Extensión documental D-198:** se conservan las **56 PER**. R8-65/67 corresponden a PER-48 (Libro) y R8-66/68 a PER-50 (ARCO), **solo administrador A** y capabilities `perucommerce_complaints_manage`/`perucommerce_privacy_manage`, más comprobación de instalación, expediente, manifest de evidencia y finalidad por cada GET. O nunca recibe expediente íntegro, secreto ni binario legal. R8-15 permite constancia imprimible para quien presenta una reclamación sin compra/cuenta/correo, protegida por contexto de formulario y origen. OpenAPI 0.5.0-draft unifica `x-authz` (actor, roles, capabilities completas y guardas por campo/estado); UI o ID no conceden permiso. PER-09 comercial sigue excluido D-188.

**Nota de precedencia vigente (18-11-2026):** las reglas funcionales E-01…E-07 aprobadas en este documento se conservan. Los textos anteriores que presentan E-08, DTO, capabilities, TTL o cobertura de expedientes como *pendientes de decisión* son **históricos**, no una segunda fuente de contratos: prevalecen `docs/01` D-01…D-198, `docs/41/42`, OpenAPI **0.10.0-draft (67 REST/64 rutas/134 DTO)** y `docs/23/24` (39 tablas, 56 PER). Las comprobaciones de motor, proveedor, hosting, normativa por comercio y release pertenecen a P-C/P-D; no se presentan como verificadas. El usuario autorizó aquí **documentación**, no código ni ejecución.

**Precisión de permisos D-199 / DOC-QA30 (18-11-2026):** la operación nueva `R8-69 POST /admin/orders/{id}/cases` materializa la parte administrativa de **PER-38/39** para abrir un caso de solicitud física parcial **sin ejecutar ajustes**. **Solo A** con `perucommerce_orders_read` **Y** `perucommerce_orders_financial_adjust`, validando sesión WordPress, nonce CSRF, mismo pedido/línea/instalación, versiones e identidad manual del comprador por teléfono original y segundo dato privado. Ningún permiso adicional para O y **no** usar `perucommerce_orders_read` aislado para abrir casos. A recupera únicamente el caso legítimo con R8-21 bajo capability del caso; O no recibe `cases`/`case_detail`. R8-26 aplica su propia autorización/decisión y no hereda la mera apertura como prueba de aprobación. Inventario vigente **56 PER, 69 entradas R8 (68 REST + R8-18 página WP), OpenAPI 0.11.0-draft**; no se habilitó ningún endpoint real.

**Precisión D-200/DOC-QA31 (18-11-2026):** R8-70 rechaza o registra retiro legítimo de un expediente físico parcial abierto por R8-69 **sin ajustar dinero/stock**. Solo A con `perucommerce_orders_read` **Y** `perucommerce_orders_financial_adjust`, capacidad de administrar **PER-38/39 existentes**; ni O ni invitados obtienen cierre de expediente. Exige mismo comercio/pedido/línea/tipo, fase `open_review`, `expected_case_version`, idempotencia, `reason` y verificación nueva para retiro del comprador. R8-26, con cantidad parcial, exige también `expected_case_version` y en ajuste válido cambia caso a `closed_adjusted` atómicamente. Lista/caso privado en R8-21 para A; ningún texto o archivo privado se expone a O. **56 PER intactas; 70 R8 contando R8-18 no REST, 69 REST, OpenAPI 0.12.0-draft, 200 decisiones**. No implementación ni prueba de permisos ejecutada.

## 1. Reglas confirmadas que E-02 NO puede cambiar

- **D-25/D-47:** dos perfiles administrativos funcionales: administrador y operador limitado. El operador gestiona pedidos, productos, inventario y agenda, no la configuración sensible o credenciales.
- **D-51:** el operador autorizado puede registrar dinero en efectivo **realmente recibido**, con auditoría.
- **D-53:** el operador no corrige cobros históricos; solo el administrador agrega ajustes conservando original.
- **D-54/D-66:** el plugin nunca ejecuta reembolsos por API; únicamente el administrador registra resultados **externos comprobados**, también parciales.
- **D-60/D-64:** cualquier cancelación por operador exige línea pendiente y elegible, capacidad concedida, **ausencia histórica de dinero acreditado** y **ausencia de ajuste o reintegro exigible**, con auditoría. Si la línea tiene dinero acreditado, aunque el saldo neto sea cero, denegar.
- **D-22/D-30:** citas se cancelan/reprograman desde panel de comerciante, auditando cambios; no autoservicio para el comprador en V1.
- **D-34:** seguimiento del pedido como invitado mediante enlace privado no adivinable. El enlace de un pedido nunca autoriza ver otro ni concede facultades administrativas.
- **D-59/D-65:** pedido confirmado y snapshot de dirección originales inmutables; cambios posteriores mediante ajuste/evento relacionado.
- **D-55/D-67…D-77:** roles no cambian máquinas de estados ni habilitan transiciones prohibidas. Cumplimiento, pago y pedido son ejes separados.

**E02-A…H aprobadas (D-78…D-85):** mínimo acceso a pedidos/contactos; productos/ofertas editables y ocultables por operador sin borrado histórico; stock/agenda auditables; preparación/entrega/reprogramación con evidencia; exportaciones masivas/archivos digitales protegidos solo administrador bajo ley; dirección propuesta por operador y aprobación administrativa ante costo/cobertura/cobro; parámetros fiscales/credenciales/reclamaciones/políticas/roles solo administrador; invitado consulta por enlace y acciones sensibles requieren verificación adicional. **La autorización debe validarse en backend por operación, recurso y estado**, no por UI. No inventar detalles técnicos faltantes.

**D-188, cierre posterior de alcance:** PER-09 permanece en el inventario de 56 operaciones como **excluida de V1**, no como capacidad implementable. D-82 establece quién podría administrar exportaciones masivas si se aprobasen en una fase futura, sin obligar a incluirlas ahora. R8-50 es exportación de datos de un titular legitimado para privacidad, no reporte comercial masivo.

## 2. Actores y límites de sesión

| Actor | Identidad funcional | Capacidad básica confirmada | Aspecto pendiente |
| --- | --- | --- | --- |
| VISITANTE | Sesión anónima/no autenticada | Ver catálogo indexable, preparar compra como invitado, seleccionar horario sin retener | Tokens de carrito, abuso/rate limiting, consentimiento |
| INVITADO_PEDIDO | Portador del enlace privado de **un** pedido | **D-85:** solo consulta del pedido propio; reemisión/cambio de dirección/devolución requieren verificación adicional | E-07: campos expuestos, autenticación adicional, caducidad/recuperación de token |
| OPERADOR | Persona autorizada en WordPress bajo perfil limitado | **D-78…D-83:** solo contacto necesario, catálogo y ofertas, inventario/agenda, preparación/entrega/reprogramación con evidencia; solo propone dirección en casos sensibles | E-02 técnico/E-07: capabilities, filtros concretos, auditoría y campos exactos |
| ADMINISTRADOR | Responsable autorizado de la tienda | Facultades de administración comercial, finanzas y parámetros sensibles | Separación entre administrador WordPress y capacidad de plugin, datos personales y retención |
| SISTEMA | Lógica de backend protegida, tareas programadas | Revalidación, holds, conciliación, transiciones internas que un usuario no ejecuta directamente | Identidad del proceso, job de recuperación y mínimos privilegios |
| PROVEEDOR_PAGO | Notificación validada de pasarela | Informar hechos verificables de pago; no cambiar cumplimiento por sí misma | Firma, idempotencia, endpoint e interpretación del proveedor |
| SOLICITANTE_RECLAMO | Consumidor por canal de reclamación | Presentar reclamación bajo requisitos legales | Verificación de identidad y contenido permitido en consulta |

**Propuesta P:** reutilizar usuarios/capacidades de WordPress para el panel sin introducir un registro de usuarios paralelo, y comprobar de forma individual el acceso en cada endpoint. **No fijar aún nombres de roles/capabilities de WordPress ni el método de crear/retirar el perfil operador.**

## 3. Matriz preliminar de acciones — lectura de catálogo y pedidos

**Clave:** C = alcance funcional confirmado por D; R = prohibición/condición confirmada; P = detalle técnico o alcance todavía PENDIENTE; — = no aplica. **Las opciones A E02-A…H están confirmadas, no todas las celdas P.** Una celda P no autoriza acceso por inferencia.

| ID | Operación | Visitante / invitado | Operador | Administrador | Estado |
| --- | --- | --- | --- | --- | --- |
| PER-01 | Ver catálogo público/ficha/precio | C (público) | C | C | C |
| PER-02 | Crear carrito y cotizar | C, con validación de servidor | — | — | C, detalles E-08 |
| PER-03 | Seleccionar fecha/hora de servicio | C, no retiene recurso | — | — | C D-35 |
| PER-04 | Confirmar contraentrega elegible / iniciar pago | C, con revalidación y método válido | — | — | C, política transaccional E-05 D-112/D-118; SQL/API pendientes |
| PER-05 | Consultar estado del **propio** pedido | C con enlace privado solo consulta D-85 | C con acceso mínimo de atención D-78 | C con control de finalidad | C política; campos E-07 pendientes |
| PER-06 | Listar todos los pedidos de la instalación | No | C: pedidos para atención D-78, sin exposición masiva innecesaria | C según finalidad | C política D-136; paginación, filtros y campos REST exactos E-08 pendientes |
| PER-07 | Consultar detalle con contacto/dirección | Solo pedido propio y datos pertinentes D-85 | C: contacto mínimo para atención/entrega; no fiscal completo por defecto D-78 | C con finalidad legítima | C política; lista de campos E-07 |
| PER-08 | Ver auditoría de un pedido | No | P: solo eventos operativos no sensibles | P: todo evento que corresponda | P E-07 |
| PER-09 | Exportar listado de pedidos | No | R: PROHIBIDA exportación masiva D-82 | **EXCLUIDO de V1 por D-188**; solo A sería elegible si se aprueba en otra versión | **EXCLUIDO V1**, sin R8; R8-50 es solo privacidad |
| PER-10 | Consultar historial de movimientos monetarios | No salvo información propia de pago | P: saldo/resultado de cobro **sin secretos** | P: detalle/auditoría | P E-07 |
| PER-11 | Obtener/reemitir enlace de pedido a tercero | R: enlace actual no basta; verificación adicional D-85 | P: canal de asistencia sin saltar verificación | C: administración con verificación adicional | C política; protocolo E-07 |
| PER-12 | Buscar pedido por nombre/teléfono/correo | No | P: solo resultado mínimo de atención | P: conforme política | P E-07 |

## 4. Matriz preliminar — catálogo, variantes, stock y agenda

| ID | Operación | Visitante | Operador | Administrador | Estado |
| --- | --- | --- | --- | --- | --- |
| PER-13 | Crear/editar producto simple o variable | No | C: crear/editar catálogo D-79 | C: administración catálogo | C política; validación/bitácora E-05/E-08 |
| PER-14 | Activar/desactivar oferta manual y precios por variante | No | C: precios/ofertas del catálogo D-79 | C | C política; versionado/auditoría E-05 |
| PER-15 | Publicar/ocultar productos | No | C: ocultar D-79; P: publicar nuevos productos | C: según capacidad | C ocultar; publicar y flujo E-02 técnico pendientes |
| PER-16 | Borrar definitivamente producto con pedidos vinculados | No | R: PROHIBIDO D-79 | P: sujeto a conservación legal y snapshots, no aprobado por D-79 | C para operador; para admin pendiente E-07/P0-26 |
| PER-17 | Consultar/actualizar stock de variante | No | C dentro de gestión autorizada | C | C para función, P para límites |
| PER-18 | Cambiar manualmente stock de variante | No | C: ajuste con motivo y auditoría D-80 | C: auditado | C política; atomicidad E-05 |
| PER-19 | Editar calendario semanal, buffers y cierres | No | C: gestión de agenda con auditoría D-80, sin parámetros sensibles | C: gestión | C política; reglas horario E-03 y capacidades técnicas pendientes |
| PER-20 | Registrar servicio realizado/no-asistencia | No | **R: no concedido en V1** | **C: solo A, con evidencia y guarda E-01 V-05/V-06 (D-177/R8-54)** | C en cobertura con regla de actor conservadora; detalles probatorios/DDL pendientes |
| PER-21 | Reprogramar cita confirmada | No autoservicio | C: con evidencia, capacidad y política D-81 | C: con política | C política; E-03/E-05 pendientes |
| PER-22 | Cancelar cita pendiente sin cobro acreditado | No autoservicio | P: solo con D-64 y elegibilidad | P: según política | C condición; P permiso |
| PER-23 | Acceder a fichero protegido de producto digital como empleado | No por catálogo público | R: PROHIBIDO; solo metadatos necesarios D-82 | C: acceso administrativo sujeto a finalidad y seguridad | C política; E-07 pendiente |
| PER-24 | Modificar URL/almacenamiento de descargas digitales | No | R: no acceder al archivo protegido; P: metadatos operativos no sensibles | C: administración protegida D-82 | C política; E-07/E-08 detalles |

## 5. Matriz preliminar — pedido, cumplimiento y dinero

| ID | Operación | Visitante/invitado | Operador | Administrador | Estado |
| --- | --- | --- | --- | --- | --- |
| PER-25 | Marcar línea física PREPARANDO/LISTO | No | C: con capacidad/estado/evidencia D-81 | C | C política; evidencia exacta E-03 |
| PER-26 | Registrar DESPACHADO, ENTREGADO o recojo | No | C: con evidencia y permiso D-81 | C | C política; prueba de entrega E-03 |
| PER-27 | Abrir incidencia logística por DESPACHADO no entregado | P: presentar reporte/solicitud, no alterar estado | **R: no concedido para apertura REST V1** | **C: A puede abrir expediente R8-55 (D-178), sin cambiar estado/dinero** | C en cobertura; evidencia y seguridad pendientes |
| PER-28 | Resolver incidencia logística | No | **R: propuesta/resolución REST no concedidas al operador V1** | **C: A propone R8-56 y resuelve R8-57 (D-179/D-180) con las capabilities y guardas vigentes** | C cobertura; sin efecto financiero/cumplimiento automático |
| PER-29 | Cancelar línea pendiente sin dinero acreditado ni ajustes | No autoservicio | **R: solo con D-64, elegibilidad/capacidad/auditoría** | P: según política | C guardas, P permiso |
| PER-30 | Cancelar línea con dinero alguna vez acreditado | No | **R: PROHIBIDO aunque saldo sea cero** | P: con elegibilidad y eventual impacto financiero | C D-64 |
| PER-31 | Resolver digital HABILITADO sin descarga al pedir cancelación | No ejecuta directo | **R: no decide reintegro** | **C: evalúa** conforme política/derecho | C D-61, reglas E-03 |
| PER-32 | Cancelar DESPACHADO no entregado tras investigación | No | **R: no cancela directamente** | **C: solo tras no entrega comprobada, ley, autorización, ajuste** | C D-75, detalles E-03 |
| PER-33 | Cambiar dirección confirmada | P: solicitar tras verificación adicional D-85 | C: recibir y proponer ajuste D-83; no aprobar costo/cobertura/cobro | C: aprobación obligatoria ante costo, cobertura o dinero acreditado D-83 | C política D-83/D-138/D-139; aplicación REST E-08 y límites legales J-04/J-05 pendientes |
| PER-34 | Registrar efectivo realmente recibido | No | **C con autorización y auditoría** | C | C D-51, validación E-05 |
| PER-35 | Corregir cobro efectivo histórico | No | **R: PROHIBIDO** | **C: ajuste preservando original** | C D-53 |
| PER-36 | Ejecutar reembolso mediante API del plugin | **R: PROHIBIDO** | **R: PROHIBIDO** | **R: PROHIBIDO V1** | C D-54 |
| PER-37 | Registrar reembolso externo **total o parcial** comprobado | No | **R: PROHIBIDO** | **C: con importe/referencia verificados** | C D-54/D-66 |
| PER-38 | Solicitar devolución/reclamación | C: consumidor conserva derecho a reclamar; verificación adicional si acción sobre pedido D-85 | C: solo avisos operativos, NO expediente completo D-84 | C: gestión administrativa D-84 | C política; canal legal/flujo E-03/E-07 |
| PER-39 | Corregir pedido confirmado | No | P: solicitudes no financieras sin borrar originales | P: ajustes autorizados y auditados | C D-59, permisos P |
| PER-40 | Marcar línea digital HABILITADA | No | **R: no por simple clic** | **R: no sin pago acreditado** | C D-09, sistema automatiza |
| PER-41 | Revocar/reemitir descarga digital | P: solicitar tras identificar | P: sin facultad por defecto | P: autorizado con derechos válidos | C protección, E-03/E-07 P |
| PER-42 | Marcar pedido FINALIZADO | No | P: confirmar hechos de cumplimiento | P: resolución administrativa | C D-76, actor final P |

## 6. Matriz preliminar — configuración, cumplimiento, datos y distribución

| ID | Operación | Visitante / invitado | Operador | Administrador | Estado |
| --- | --- | --- | --- | --- | --- |
| PER-43 | Leer credenciales Mercado Pago / proveedor mapas | **R: NO** | **R: NO** | P: acceso estrictamente necesario, nunca exposición de secretos en cliente | C D-47 |
| PER-44 | Crear/cambiar/retirar credenciales o configuración sensible | **R: NO** | **R: NO** | **C: administrador exclusivamente** | C D-47 |
| PER-45 | Configurar UBIGEO/cobertura/tarifas | No | R: configuración no concedida por D-80 | C: administración autorizada | P: responsabilidades exactas y auditoría E-05/E-08 |
| PER-46 | Configurar régimen/boleta/factura/recibo externo | No | R: PROHIBIDO D-84 | C: administrador exclusivamente D-84 | C política; normas E-03/P0-25 pendientes |
| PER-47 | Configurar políticas de cancelación/garantía | No | R: PROHIBIDO D-84 | C: administrador exclusivamente D-84 | C política; límites legales E-03 |
| PER-48 | Leer/procesar Libro de Reclamaciones | C: consumidor puede presentarla conforme vía legal | R: no acceder al expediente; solo avisos operativos pertinentes D-84 | C: gestión administrativa D-84, sujeta a ley | C política; confidencialidad/plazos E-07/P0-25 |
| PER-49 | Cambiar rol/permisos de operador | No | R: PROHIBIDO D-84 | C: administrador exclusivamente D-84 | C política; capabilities WordPress/auditoría pendientes |
| PER-50 | Exportar datos personales o financieros | No ajenos | R: PROHIBIDA exportación masiva D-82 | C: solo administrador bajo política de datos | C política; E-07 formato/retención pendientes |
| PER-51 | Instalar/actualizar ZIP del plugin | No | P: no por defecto | P: administración autorizada | P P0-26 |
| PER-52 | Desinstalar/eliminar datos históricos | **R: NO** | P: no por defecto | P: supeditado a retención/ley, advertencias y autorización expresa | P P0-26 |
| PER-53 | Consultar log de errores de integración | No | P: solo incidencia sin secretos | P: logs filtrados de secretos | P E-07 |
| PER-54 | Solicitar reenvío de notificación transaccional | P: comprador solo tras verificación adicional E-07; token privado de seguimiento no basta D-85 | P: operador autorizado a reenvío operativo según capability, sin leer datos protegidos | P: administrador con auditoría | C: reenvío manual auditable D-127, **P** actor/capability/campos E-07/E-08 |
| PER-55 | Activar/desactivar módulos de pago/mapa/reclamaciones | No | R: no concede configuración sensible D-84 | C: solo administrador y sujeto a obligación legal | C política; E-03/P0-25 reglas de desactivación pendientes |
| PER-56 | Gestionar cuenta y sesión propia WordPress | No aplica invitado | P: solo su cuenta bajo controles WP | P: con permisos WordPress | P compatibilidad |

## E-06: política de avisos por destinatario APROBADA (D-123…D-134)

La matriz `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` **APRUEBA E06-A…L (D-123…D-134)** sobre mensajes mínimos, bandeja y reenvío auditado. **No concede por sí sola capabilities REST**: el operador ve solo avisos necesarios, no expedientes del Libro ni secretos (D-78/D-82/D-84). El invitado requiere verificación adicional para cambios (D-85). **Pendientes E-07/E-08:** campos, identidad, control de sesión, permisos de reenvío y retención.

## E-08 — capacidades y rutas APROBADAS COMO DISEÑO (D-149…D-166)

El contrato `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` aprueba 52 entradas R8 (R8-18 es página WordPress) y familias `perucommerce_*` para relacionarlas con **56 operaciones PER** de esta matriz. **E08-N/D-162** establece dirección de autorización por operación/recurso/campo/estado/versión, pero falta el inventario físico R8↔PER↔DTO/OpenAPI **por cada método y salida**, definido como entregable E08-R/D-166.

No conceder permisos por el simple rol/nonce; el operador no cancela con dinero acreditado (D-64), ni ajusta cobros o registra reintegros externos, ni consulta Libro/PII completa. El enlace privado solo permite lectura; la solicitud sensible requiere D-138. Siguen pendientes SQL/hosting, seguridad verificada y J-04/J-05/P0-25. Ninguna capability está instalada o probada por la ratificación.

## 7. Autorización de backend: comprobaciones para cada mutación

**Orden propuesto de comprobación:** identidad del actor → capability específica → alcance del recurso → estado/versión vigente (E-01) → elegibilidad de la operación (E-03) → condición financiera y D-64 → validación de importe (E-04) → operación atómica/idempotente (E-05) → auditoría y respuesta segura (E-08). No exponer existencia de pedidos ajenos ni secretos en respuestas de error.

Para acciones ejecutadas por integración:
- Validar autenticidad/origen de webhook y consulta al proveedor **antes** de traducir a APROBADO; no conceder facultades humanas a un callback.
- Jobs de vencimiento de hold operan sobre la hora efectiva del servidor, no «como si fueran operador» ni por sesiones del navegador.
- Las solicitudes de invitado se limitan a su pedido; el token no concede lectura masiva, cancelación o edición financiera.
- Una operación que pierde autorización, estado o versión mientras se procesa debe abortar de forma segura; no conceder por UI lo que el backend deniega.
- El registro de auditoría debe explicar quién ejecutó qué y por qué, sin persistir secretos ni datos personales innecesarios.

**PENDIENTE:** autenticación WordPress REST, CSRF, enlaces privados, permisos por recurso, limitación de frecuencia y mensajes HTTP, a fijar E-07/E-08. E-05 aprobado fija la política de idempotencia y atomicidad, **no** los endpoints ni capacidades. No inventar nombres de roles/capabilities.

## E-07 — política de datos por perfil, tokens y privacidad APROBADA (D-135…D-148)

Para concretar las 56 operaciones PER, consultar `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` (**E07-A…N APROBADAS D-135…D-148**, 42 P7-X no ejecutados). Seguimiento privado solo lectura durante 90 días; acciones sensibles con OTP 8 dígitos/10 min/5 intentos o recuperación manual telefónica. No se crean cuentas de invitados ni se conceden capacidades por nonce/enlace. **E-08** debe precisar campos y endpoints.

El operador solo puede acceder a contacto/operaciones necesarios, nunca exportar masivamente ni consultar datos fiscales completos, archivos digitales o Libro por su cuenta. WP REST nonce no autoriza una ruta; acceso por capability + recurso + campo se concreta E-08. Los datos ARCO/eliminación y retención requieren J-04/J-05/P0-25/P0-26. **No inferir que las propuestas son controles ya implementados**.

## 8. Datos personales y operaciones con riesgo elevado

**Mínimos de datos por tarea, sujetos a las proyecciones vigentes y autorización de recurso/campo:**

- Preparación física: nombre/identificación de pedido, artículos, cantidad original `quantity` y contadores `physical_quantities` de la propia línea según DOC-QA27 (`docs/17/23`); dirección únicamente para delivery, contacto cuando sea necesario. O lee `pending_stock_out_units` para la cantidad pendiente de preparar/sacar, con estados y versión de línea vigentes; no obtiene el expediente ni movimientos financieros para reconstruirla. `stock_out_units` no acredita recepción del comprador ni permite saltar las guardas E-01.
- Citas: hora, tipo de servicio y contacto estrictamente necesario; no exponer datos fiscales cuando la tarea no los requiere.
- Registro de cobro: pedido y monto/moneda; no mostrar credenciales del proveedor.
- Administración de productos: metadatos y stock; no combinar con acceso a exportaciones de compradores por comodidad.
- Gestión de reclamación: identidad, relato y estado protegidos; acceso restringido según legislación.
- Enlace de seguimiento: solo datos pertinentes del pedido propio, incluida cantidad original y `physical_quantities` en líneas físicas; sin campos internos/auditoría, credenciales ni datos de otras compras. Estos contadores no revelan existencias generales de la variante ni notas del expediente. La propiedad se omite en líneas digitales y de servicio.

**E-07 APROBADO EN POLÍTICA:** minimización de datos, WordPress Privacy y conservación por categoría. **PENDIENTES E-08/J-04/J-05/P0-25:** mapeo exacto de campos/endpoints, capacidades REST, plazos legales de retención, derechos ARCO y controles verificables. Gestionar pedidos no habilita a leer todos los datos.

## 9. Casos de denegación/aceptación requeridos

| ID | Caso | Resultado exigido o propuesta |
| --- | --- | --- |
| E2-X01 | Operador intenta ver o cambiar token MP | **DENEGADO** en backend, sin filtrar secreto (D-47) |
| E2-X02 | Operador cancela línea con dinero acreditado y saldo actual cero | **DENEGADO** (D-64), independientemente del saldo |
| E2-X03 | Operador registra efectivo recibido válido | Permitido si está autorizado, valida pedido/importe y audita; no duplica (D-51) |
| E2-X04 | Operador intenta ajustar efectivo original | **DENEGADO**; solo administrador (D-53) |
| E2-X05 | Operador intenta registrar reintegro externo | **DENEGADO**; solo administrador (D-54/D-66) |
| E2-X06 | Cualquier actor intenta reintegro API del plugin | **NO EXISTE** en V1 (D-54) |
| E2-X07 | Invitado cambia ID/token para consultar pedido ajeno | **DENEGADO**; solo su token válido (D-34) |
| E2-X08 | Operador intenta exportar direcciones/comprobantes masivamente | **DENEGADO por D-82** en backend; E-07 definirá política de exportación para administrador |
| E2-X09 | Operador con cita ocupada intenta confirmar segunda cita | **DENEGADO**, sin reserva doble (D-38) |
| E2-X10 | Administrador solicita corrección de cobro | Añadir ajuste auditado, conservar original (D-53) |
| E2-X11 | Compra de digital contraentrega sin dinero acreditado | **DENEGAR** descarga aunque pedido esté confirmado (D-09/D-37) |
| E2-X12 | Digital con derecho habilitado sin descarga pide cancelación | Revisión de administrador; no automatizar devolución (D-61) |
| E2-X13 | Operador marca envío entregado sin evidencia aprobada | **Propuesta:** denegar por validación E-03 de entrega |
| E2-X14 | Administrador intenta cancelar línea ya entregada por vía ordinaria | **DENEGADO**; seguir devolución/reclamación (D-58) |
| E2-X15 | Reembolso externo parcial real se registra dos veces | D-120/E-05 aprobado: una sola referencia/cobro y acumulado atómico; algoritmo SQL y prueba real P0-18/E-08 pendientes |
| E2-X16 | Operador intenta cambiar política fiscal o rol de otro operador | **DENEGADO por D-84** en backend; solo administrador gestiona estos parámetros |

**Ningún E2-X está ejecutado.** Cada caso necesitará datos de entrada, estado previo, actor/permiso, resultado, error e impacto en bitácora; la etapa de pruebas debe autorizarse aparte.

## 10. Registro de aprobaciones E-02 — POLÍTICA FUNCIONAL CERRADA

**Confirmación expresa:** el propietario aprobó **«Todas A en E-02»** el 2026-11-17. Se registran como decisiones **D-78…D-85**. Las ocho alternativas B quedan descartadas.

| Origen | Registro | Política funcional aprobada |
| --- | --- | --- |
| E02-A | D-78 | Operador lee pedidos y contacto mínimo necesario; no accede por defecto a datos fiscales completos ni credenciales |
| E02-B | D-79 | Operador crea/edita productos, precios y ofertas y puede ocultarlos; sin borrado definitivo de productos con historial de pedidos |
| E02-C | D-80 | Operador ajusta stock y agenda con motivo y auditoría; no configura tributos, credenciales ni permisos de otros |
| E02-D | D-81 | Operador registra preparación/entrega y reprograma citas con evidencia y capacidad; sin reintegros ni cancelación con dinero acreditado |
| E02-E | D-82 | Solo administrador gestiona exportación masiva y archivos digitales protegidos bajo política legal; operador solo metadatos mínimos |
| E02-F | D-83 | Operador recibe solicitud/propuesta de cambio de dirección; administrador aprueba ante cambio de tarifa/cobertura o dinero acreditado |
| E02-G | D-84 | Configuración fiscal, credenciales, Libro de Reclamaciones, políticas y roles exclusiva del administrador; operador solo avisos operativos |
| E02-H | D-85 | Enlace privado permite consulta limitada; reemisión, cambio de dirección y devolución requieren verificación adicional E-07 |

**No inferir:** que el administrador puede ignorar límites de datos personales, que el operador puede publicar/borrar cualquier contenido, acceder a expedientes del Libro de Reclamaciones o aplicar por su cuenta cambios de dirección con impacto. **La presentación de reclamaciones por consumidores permanece disponible** conforme a su cauce legal.

## Extensión de cobertura C23 ratificada D-176…D-187 — restricciones de actor (2026-11-17)

El propietario ratificó 12 operaciones R8-53…R8-64 como cobertura documental de V1. Los permisos **NO** se amplían por inferencia: PER-19/R8-53 permanece O/A con capability de agenda; PER-20/R8-54, PER-27/R8-55 y PER-28/R8-56/57 quedan **solo A** hasta un cambio expreso; PER-31/R8-61 ya reservaba decisión al administrador. El comprador mantiene solicitudes verificadas R8-12, no acciones administrativas.

Para todas las rutas nuevas se aplica la capability **ya existente** de E08-N más comprobaciones por recurso, versión, estado, evidencia y minimización. En particular R8-57 exige ambas capacidades `perucommerce_orders_financial_adjust` y `perucommerce_orders_fulfill`; R8-58 requiere `perucommerce_privacy_manage` y `perucommerce_orders_read`; R8-64 requiere `perucommerce_settings_manage` y `perucommerce_orders_read`, todas exclusivamente del rol A. El nuevo permiso administrativo de **registrar** referencia fiscal **no** implica facultad de emitir un CPE SUNAT ni acceso fiscal total del operador.

No se crean roles nuevos, capacidades globales, permisos de `manage_options` como atajo ni un endpoint `set_status` libre. El **DDL, el mecanismo seguro de tokens, la documentación legal específica y las pruebas** continúan pendientes. Consultar `docs/27-COBERTURA-OPERACIONES-P0-23-PROPUESTA-V1.md` como historial de ratificación, y el contrato E-08 actualizado en `docs/21`.

## 11. Alcance del cierre funcional de E-02 y contratos todavía abiertos

**E-02 ya está CERRADO EN POLÍTICA FUNCIONAL** por aprobación expresa de las ocho A y registro D-78…D-85. El contrato técnico todavía debe:
- Respetar las celdas C/R modificadas; resolver los detalles P de publicación, acceso exacto a datos fiscales, permisos de atención, gestión de adjuntos e identidad adicional. **E-03 tiene política funcional APROBADA (D-86…D-95)** en `16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md`: expediente común tipado, cancelación de línea completa elegible, devolución/restock controlado, digital con remedios, no-show y comunicación de reprogramación. **No conceder al operador capacidad para denegar derechos del consumidor ni resolver expedientes completos del Libro.** E-07 define identidad y datos, J-01/J-02 las obligaciones jurídicas por supuesto.
- Construir tabla acción → capability → entidad/alcance → precondición E-01 → posible denegación → auditoría → respuesta REST E-08, sin nombres inventados.
- Completar los contratos de datos personales conforme a la matriz E-07 y fuentes legales peruanas, y confirmar diseño técnico/versiones WordPress antes de implementar.

**Implementación sigue prohibida** hasta completar capacidades/endpoints E-08, J-01…J-05/P0-17/P0-25, P0-18/P0-19/P0-26, diseños y autorización expresa. **E-07 está aprobado D-135…D-148**, pero no probado en hosting ni ante ANPD; el operador sigue sin facultad de cancelar con dinero acreditado o registrar reintegros.
