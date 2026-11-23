# Contrato de diseño Figma y entrega a Codex — PeruCommerce

**Estado (2026-11-17):** contrato visual **CERRADO EN BASELINE TÉCNICA D-195**, 34 frames editables (17 escritorio/17 móvil), con objetivos WCAG 2.2 AA y 44 × 44 CSS que deberán verificarse en la aplicación. Esta congelación **no afirma aceptación estética personal de cada frame ni conformidad accesible demostrada**. No autoriza código ni pruebas. Fuente vigente docs/41. Complementa [04-UX-Y-FLUJOS.md](04-UX-Y-FLUJOS.md) y [10-ESTANDARES-UX-MOVIL-Y-ACCESIBILIDAD.md](10-ESTANDARES-UX-MOVIL-Y-ACCESIBILIDAD.md).

## 0. Archivo UX V1 y cobertura disponible (PRE-07)

**Archivo Figma:** https://www.figma.com/design/qJaGwnjfaI35gYDjFwzBub — `PeruCommerce — UX V1 (móvil y escritorio) — revisión`. Creado y ampliado el 2026-11-17 a **34 frames (17 pares móvil/escritorio)** mediante capas editables; fuentes y colores son **propuesta**, no identidad comercial aprobada. El archivo se encuentra disponible para revisión del propietario, sin código ni publicación de frontend.

| Par de frames | Escritorio | Móvil | Objetivo funcional |
| --- | --- | --- | --- |
| 01–02 | Catálogo | Catálogo | Catálogo público indexable WordPress/PHP, variantes y precios PEN |
| 03–04 | Checkout | Checkout | Campos mínimos por tipo, distrito UBIGEO, delivery/recojo, pago y total |
| 05–06 | Reserva de servicios | Reserva de servicios | Un recurso, fecha/hora en ficha, revalidación posterior |
| 07–08 | Expedientes legales admin | Expedientes legales admin | Resumen legal, acceso A y referencia a lectura protegida R8-65/66 |
| 09–10 | Cambio de cotización | Cambio de cotización | Error de precio/stock y no duplicación de cobro |
| 11–12 | Seguimiento invitado | Seguimiento invitado | Solo pedido propio, verificación adicional para cambios |
| 13–14 | Libro público | Libro público | Presentar reclamo sin cuenta ni compra, constancia legal por modalidad |
| 15–16 | Inventario operador | Inventario operador | Stock por variante y ajustes auditables |
| 17–18 | Descarga digital | Descarga digital | Derecho y enlace temporales protegidos |
| 19–20 | Confirmación de pedido | Confirmación de pedido | Cobro efectivo pendiente y seguimiento sin afirmaciones falsas |
| 21–22 | Reprogramación de cita | Reprogramación de cita | Revalidación de recurso/franja, historial y evidencia |
| 23–24 | Reembolso externo | Reembolso externo | Solo administrador registra resultado ya verificado, sin API de devolución |
| 25–26 | Incidencia logística | Incidencia logística | Expediente de entrega no comprobada sin cancelación financiera automática |
| 27–28 | Comprobantes externos | Comprobantes externos | Referencia fiscal por pedido bajo régimen/actividad real, no emisión SUNAT |
| 29–30 | Resolución ARCO | Resolución ARCO | Admin exclusivo y legitimación, conservación y plazos por caso |
| 31–32 | Diagnóstico y recuperación | Diagnóstico y recuperación | Condiciones fail-closed de hosting, MP, privados y preflight |
| 33–34 | Cancelación por línea | Cancelación por línea | Estado, dinero histórico y delivery revisados antes de autorizar |

**D-195 — PRE-07 CERRADA COMO BASE DE INGENIERÍA:** se fija estructura de 34 pantallas y colores/Roboto como referencia visual inicial. Estados y mensajes no dibujados derivan de E-01…E-08 y docs/04/10/11; no se inventan derechos, estados ni permisos. Ajustes de marca/componentes durante la implementación serán cambios visuales controlados, no decisiones funcionales abiertas. **WCAG se auditará en P-C sobre software real**, y no se presume que el usuario aprobó personalmente cada detalle de Figma. Los flujos principales y excepciones administrativas enumerados ya tienen **34 marcos editables**, pero aún son propuestas visuales, no una aplicación navegable ni diseño aceptado por el propietario. Las maquetas son referencia editable, no prueba de comportamiento ni contrato legal universal. La aceptación visual final debe asociarse a IDs de frame/versiones, no inferirse del acto de crear el archivo.

**Handoff funcional de Figma tras D-198 (2026-11-18):** los frames de expedientes 07/08 y 29/30 son maquetas; su versión implementada debe distinguir lista R8-41/43, detalle privado R8-65/66 y acción de descarga de evidencia legal R8-67/68, accesible solo al administrador autorizado, con estado de no disponible/denegación y sin URL pública. Los frames de Libro público 13/14 deben validar identidad/contacto conforme al **Anexo I vigente**, aunque la compra física del checkout no exija correo. La confirmación mostrará `ComplaintReceiptCopy` imprimible sin depender de envío email; esto no exime campos legales del formulario. Figma no es una prueba de accesibilidad y no modifica permisos, ruta ni lógica E-01…E-08.

## 1. Principio de fuente de verdad

- **GitHub/documentación confirmada:** comportamiento, campos, negocio, reglas legales, estados y criterios de aceptación.
- **Figma aprobado:** presentación visual, distribución, iconografía, componentes, variantes y estados representados.
- **Implementación de Codex:** debe respetar ambos; si existe conflicto o falta de especificación, documentar y solicitar decisión. Nunca inventar un requisito desde el mockup.
- **No generar múltiples aplicaciones ni duplicar contratos** por tener diseños específicos para móvil y escritorio.

## 2. Propuesta de organización del archivo Figma

| Sección | Contenido |
| --- | --- |
| 00 — Fundamentos | Principios, alcance, persona por tarea, mapa de flujos, referencias |
| 01 — Tokens | Color semántico, tipografía, espaciado, radios, bordes, sombras, densidad, tamaños de control |
| 02 — Componentes | Botones, inputs, selects, radios, tarjetas, carrito, estados, calendarios, mapas, modales |
| 03 — Comprador móvil | Catálogo, ficha, variante, carrito, checkout, calendario, mapa, estados de pedido |
| 04 — Comprador escritorio | Composiciones específicas de las mismas tareas |
| 05 — Administración | Onboarding, catálogo, agenda, pedidos, pagos, zonas, reclamos, ajustes y módulos |
| 06 — Prototipos | Navegación real de recorridos clave y recuperación de errores |
| 07 — Estados excepcionales | Sin stock, sin horario, pago pendiente, falla del mapa, errores y vacíos |
| 08 — Handoff | Inventario de frames, dependencias, activos, anotaciones y referencias de contratos |

Los nombres son recomendados, no un requisito de herramienta; adaptar si Figma permite una organización más clara.

## 3. Contenido requerido de cada frame

Antes de aprobar un frame, documentar:

- Identificador estable (ejemplo UX-CHK-MOB-01); nombre y propósito de la tarea.
- Usuario (comprador invitado / operador / administrador), datos necesarios y origen.
- Ancho, alto, orientación y comportamiento con teclado, safe areas, zoom y scroll.
- Acciones principales, secundarias y opciones condicionales.
- Navegación: entrada, salida, volver, cerrar, reintento, recuperación de datos y contexto.
- Componentes y tokens utilizados; variantes normal/hover/focus/disabled/loading/error/empty/success cuando correspondan.
- Estados de negocio presentados: pago, pedido, stock, servicio, descarga, reclamo.
- Mensajes y reglas de error, sin confundir confirmación visual con estado autoritativo del backend.
- Accesibilidad: etiqueta, orden de foco, contraste, alternativa a gesto, textos y verificación del objetivo CONFIRMADO de ≥ 44 × 44 píxeles CSS en controles táctiles independientes, bajo WCAG 2.2 AA.
- Referencia a los requisitos de GitHub y criterios de aceptación que cubre.

Un frame visual por sí solo **no es una especificación ejecutable**: los datos, flujos y validaciones deben quedar vinculados a documentos de dominio/UX.

### 3.1 Evaluación UX por tarea y frame — guía de handoff para Codex (2026-11-18)

Usar los **18 principios** detallados en [docs/10, sección 2](10-ESTANDARES-UX-MOVIL-Y-ACCESIBILIDAD.md) como herramientas de diagnóstico, **no** como nuevas leyes de producto, nuevas pantallas obligatorias o sustitutos del contrato visual D-195. La referencia sigue siendo el mismo archivo Figma de **34 frames (17 pares móvil/escritorio)**. No se cambian sus capas ni se afirma haberlos auditado visualmente en esta actualización documental.

Para cada pantalla que se implemente, la ficha de handoff debe especificar **solo los principios relevantes** y su efecto concreto: objetivo de la tarea, acción principal, información que debe permanecer reconocible, opciones mostradas por contexto, agrupación/jerarquía visual, estado de espera y resultado, recuperación/error, navegación atrás/cancelación segura, permisos y método accesible sin gestos obligatorios. No convertir esto en un checklist decorativo de 18 casillas; **un principio solo está cubierto si puede observarse el comportamiento correspondiente en el software**.

| Flujo | Principios prioritarios y consecuencia observable | Revisión que deberá hacerse al existir UI |
| --- | --- | --- |
| Catálogo / ficha | Jakob, Fitts, Gestalt, estética y reconocimiento: título, variante, precio y acción agrupados sin competir; contenido PHP indexable | Lectura, selección y agregar al carrito con mouse, teclado y toque; no necesitar cargar Angular para ver el producto público |
| Carrito y checkout físico | Hick-Hyman, Tesler, Postel, divulgación progresiva: teléfono y entrega si aplican; no exigir email, servicio, DNI ni mapa opcional por defecto | Campos pertinentes al tipo de pedido; ingreso razonable tolerado sin eludir validación del servidor; total y entrega visibles |
| Carrito mixto y servicios | Carga cognitiva, reconocimiento, prevención: una cita máxima por pedido y reglas de efectivo/MP según elegibilidad real | Mostrar selección de fecha/hora e información de entrega/digital sin prometer cupo hasta confirmar |
| Pago MP y seguimiento | Doherty, visibilidad del estado, recuperación: verificación, confirmado o incierto diferenciados sin doble cobro por repetir clic | Feedback temprano y estado autoritativo; fallo de red conserva contexto seguro y ofrece reintento/consulta correctos |
| Panel A/O | Gestalt, consistencia, flexibilidad, reconocimiento: filtros, búsqueda y acciones según capability; versiones y expedientes solo a A autorizado | Nada reservado a A aparece en proyección O; errores 403/409 recuperables y sin disclosure de datos |
| Libro, ARCO y evidencia | Accesibilidad, prevención, control: formulario conforme a norma aplicable, constancia, y acceso legal protegido por expediente | Navegación teclado/lector de pantalla y error contextual; no usar privacidad visual como reemplazo de autorización |
| Móvil/escritorio | Fitts, ergonomía, jerarquía, control: disposición adaptada, misma lógica, CTA no tapa foco/teclado/información legal | Revisar anchos intermedios, zoom, cambio de orientación, foco, pulsación involuntaria y retorno de foco |

**Criterios no negociables ya confirmados:** objetivo WCAG **2.2 AA** en público y administración y áreas activables de controles táctiles independientes **≥ 44 × 44 px CSS** (D-16/D-17). La validación efectiva de contraste, foco, teclado, zoom, responsive y lectores de pantalla pertenece a pruebas posteriores autorizadas (P-C); Figma únicamente permite anticipar riesgos.

**No imponer métricas inventadas:** ni «tres clics», ni «todo sin scroll», ni 400 ms de respuesta real garantizada. El principio Doherty exige feedback **honesto**, no mostrar un pago/cita como confirmado antes de la fuente autoritativa. El principio Postel permite normalizaciones **inequívocas** de entrada de usuario; jamás suaviza validación de precio, UBIGEO, identidades, permisos, sesión o claves de idempotencia. La estética no sustituye accesibilidad/legibilidad ni es prueba de conversión.

**Registro de revisión futuro:** para cada flujo, asociar frame/versión de Figma, criterio UX de `docs/10` (UX-01…UX-24), captura del comportamiento implementado cuando exista, hallazgo concreto y decisión de corrección limitada al alcance; distinguir `documentado`, `inspeccionado estáticamente` y `verificado en entorno`. Un desajuste visual no autoriza cambiar E-01…E-08, añadir dependencias o activar funciones no comprobadas.


## 4. Tokens y componentes propuestos

### Tokens por definir en Figma

- Colores semánticos: acción primaria/secundaria, fondo, superficie, texto, borde, foco, error, advertencia, éxito/confirmación.
- Escala tipográfica legible en pantallas pequeñas, tamaños mínimos y altura de línea; preferir familias eficientes y prever aumento de texto.
- Espaciado, dimensiones táctiles, alto de botones, iconos, bordes/radios, elevación y espacio de foco.
- Estados de control, animación reducida, tematización/dark mode **solo si se aprueba**, y adaptación a fondos/estilos de un tema WordPress.
- Formatos visuales PEN/S/, cantidades, fechas, horarios, precios tachados cuando exista descuento válido.

**Trazabilidad de derechos sobre assets Figma (P0-24):** `docs/32-LICENCIA-FUENTES-Y-DISTRIBUCION-P0-24-V1.md` exige acreditar licencia y permiso de redistribución de tipografías, iconos, ilustraciones e imágenes elegidas en Figma antes de incorporarlas a un ZIP GPL; una captura/referencia de diseño no acredita derechos sobre esos activos. El montaje real de Angular dentro de WordPress está propuesto en `docs/31-CONTRATO-ANGULAR-WORDPRESS-ASSETS-P0-20-V1.md`. **Ni Figma final ni assets licenciados están disponibles**.

**La baseline D-195 usa paleta verde/neutra y Roboto como guía visual, no como marca definitiva ni permiso para distribuir fuentes sin licencia. No añadir bibliotecas/materiales adicionales si no son necesarios; aislar estilos del plugin y conservar objetivos de accesibilidad.** **Angular ya está CONFIRMADO** para carrito, checkout y panel (D-42); no asumir Angular Material. Comparar componentes propios/nativos frente a accesibilidad, peso y consistencia al cerrar el sistema visual.

### Componentes mínimos de inventario

Botón, enlace, icon-only button con nombre accesible, campo/textarea, selector, radio/checkbox, campo de teléfono/correo, tarjeta de producto, selector de variante/cantidad, indicador de stock, mini-cart móvil/escritorio, resumen de pedido, método de pago, modalidad de entrega, selector de zona y ubicación, calendario de servicios y franjas, aviso de reserva expirada, feedback de pago, estado de pedido, enlace legal, alerta de reclamo, diálogo accesible y estado vacío/error/carga.

Reutilización por comportamiento, no por identidad visual forzada: una tarjeta móvil y una de escritorio pueden distribuir contenido diferente sin duplicar acceso a datos ni reglas.

## Restricciones visuales confirmadas por arquitectura (D-42…D-44)

- Para el **catálogo indexable**, Figma debe definir cómo se representa con bloques/shortcodes de WordPress y HTML renderizado en PHP, evitando obligar al rastreador o al comprador a esperar una aplicación Angular para ver productos.
- Para **carrito, checkout y panel**, Figma define componentes interactivos realizados en Angular con disposiciones independientes para móvil y escritorio; la API y reglas del servidor siguen siendo únicas.
- Para **mapas de dirección**, diseño con Leaflet y proveedor sustituible más variante de entrada manual si no se carga. No ocultar el distrito y la información de cobertura; dirección sin coordenadas es aceptable solo cuando validación de cobertura lo permite.
- Exigir diseños de carga/error del mapa y protección frente a colisiones de estilos con el tema WordPress; el mecanismo CSS queda pendiente.
- La propuesta de persistencia (D-43) no cambia la presentación visual, pero las fichas deben referenciar contratos de datos aprobados; no asignar automáticamente una tabla a un frame.

## Reglas visuales comerciales y de acceso aprobadas (D-45…D-48)

- Figma utilizará **PEN/S/** como única moneda del comercio y checkout; no diseñar conversiones o selectores de otras divisas en V1.
- Catálogo, ofertas, carrito y checkout muestran **precio final claro**. Los costos de reparto y cargos aplicables aparecen antes de confirmar el pago. No usar etiquetas fiscales universales: matriz tributaria por régimen/operación sigue pendiente.
- Diseñar acciones para **operador** (pedidos, productos, stock y agenda) y **administrador** (incluye configuración sensible/credenciales); nunca presentar credenciales o claves en vistas de operador. La granularidad exacta de permisos y protección de datos espera aprobación.
- Diseñar para versiones estables/mantenidas cuya matriz de navegadores y plataformas todavía debe fijarse y verificarse, sin introducir interfaces alternativas para versiones antiguas por defecto.

## Requisitos visuales confirmados de precio y cobro manual (D-49…D-51)

- El administrador u operador autorizado configura **precio de venta final** en PEN, sin obligar a descomponerlo en precio neto + impuestos para darlo de alta.
- Precio de oferta simple con control explícito **activar/desactivar**; no diseñar calendario de oferta, cuenta atrás o motor promocional en V1.
- La pantalla del operador incluye registro de **cobro efectivo recibido** con confirmación y trazabilidad, diferenciando pedidos «pendientes de cobro» y «cobrados». No debe incluir UI para reembolsos ni configuración de credenciales; el backend impondrá esa prohibición.
- Las pantallas administrativas deben prever errores de permisos, duplicación de registro e importe que no coincide, sin fingir que un clic equivale a dinero recibido.

## Flujos visuales confirmados para excepciones S-01…S-06 (D-61…D-66)

- **Digital con acceso habilitado, sin descarga registrada:** mostrar evaluación del administrador, no botón de cancelación/reembolso automático; estado «solicitud en evaluación» sin prometer resultado.
- **Físico despachado, pendiente de entrega:** mostrar incidencia logística y seguimiento/revisión, sin cancelación ordinaria inmediata.
- **Cancelación de última línea física antes del despacho:** revisar cargo delivery, costos prestados y derechos aplicables; mostrar importe solo después de cálculo/validación autorizados y requerir administrador si el pedido pagado tiene ajuste financiero.
- **Permisos del operador:** ante cualquier dinero acreditado, ajuste o reintegro exigible, no permitir cancelación por operador aun cuando el impacto aparente sea cero. Registrar la denegación sin afirmar éxito.
- **Cambio de dirección de entrega tras confirmar:** representar dirección pactada e historial, nueva propuesta/ajuste, validación de cobertura/tarifa y resolución de incompatibilidad logística; no sobrescribir snapshot original.
- **Reembolso parcial externo:** registrar resultado verificado por administrador con importe PEN, medio/referencia, evidencia y conservación de pago original; no presentar botón que envíe devolución a Mercado Pago.
- Todos los estados de revisión/espera/error de estos flujos requieren frames de móvil y escritorio y texto accesible. **Pendientes**: cálculo tributario y delivery, campos finales, condiciones de elegibilidad, prueba externa y máquina de estados.

## Figma: contratos API y mensajes de error — E-08 APROBADO COMO POLÍTICA

Consultar `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` (**D-149…D-166**) para diseñar estados de recotización 409, stock/cita agotados, pago MP ambiguo, expiración de seguimiento, OTP sensible, archivo digital protegido y recuperación. Las 52 entradas R8 y los códigos `pc_*` se han elegido como dirección de diseño, pero los **60 I8-X no se ejecutaron**, y falta OpenAPI/DTO físico/errores exactos E08-R. No mostrar pago aprobado por retorno del navegador ni correo entregado solo porque `wp_mail()` aceptó la solicitud.

Las vistas específicas móvil/escritorio conservan catálogo indexable PHP y Angular cargado condicionalmente en WordPress. No crear cuentas obligatorias ni exigir correo a físicos puros. **Pendientes:** contratos de campos/seguridad verificables, textos legales P0-25, Figma final y pruebas autorizadas.

## Figma: identidad, privacidad y accesos sensibles — E-07 APROBADO (D-135…D-148)

El contrato `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` **APRUEBA E07-A…N (D-135…D-148)**; 42 P7-X son escenarios no ejecutados. Figma debe diseñar móvil/escritorio sin cuenta de invitado, físico solo teléfono, seguimiento privado solo lectura de 90 días, OTP de 8 dígitos/10 min/5 intentos para operaciones sensibles con email, recuperación manual de teléfono, descargas digitales 24 h y derechos ARCO. **No imponer OTP al checkout normal ni DNI/RUC universal.**

**D-137/D-138/D-144/D-145/D-146 aprobados:** seguimiento 90 días, OTP sensible, aislamiento de caché, geolocalización por elección, exportación/eliminación condicionada por ley y retención por categoría. El Libro admite reclamación sin cuenta y el operador no ve expedientes. **PENDIENTES:** permisos/rutas E-08, campos/errores, textos legales y retención exacta J-04/J-05/P0-25; no demostrar cumplimiento por dibujar componentes.

## Figma: comunicaciones E-06 APROBADAS EN POLÍTICA (D-123…D-134)

El contrato **E-06 aprobado (D-123…D-134)** en `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` fija UX de confirmación/seguimiento, correo disponible o no, pagos, citas, digitales, reclamos, reintegros y errores de envío. Diseñar bandeja por prioridad y permisos de admin/operador, sin exponer expedientes del Libro a operadores. **Faltan** campos, tokens, consentimientos E-07, APIs E-08 y formatos legales P0-25; los 38 N6-X no son pruebas.

No convertir correo procesado en mensaje recibido ni depender de cron puntual. Pedidos solo físicos usan teléfono/WEB sin correo obligatorio; plantillas/eventos E-06 están **aprobados en política**, pero el contenido final, autorizaciones de campos E-07 y APIs E-08 permanecen por especificar.

## Figma: estados excepcionales E-05 — política técnica APROBADA D-109…D-122

E-05 aprobado D-109…D-122 exige estados UX para concurrencia, pagos ambiguos y recuperación; E-06 aprobado D-123…D-134 define canales/avisos de esos hechos. Producir variantes móvil/escritorio con error y recuperación, sin inventar estado pagado a partir de email ni SMS/WhatsApp en V1. Quedan datos E-07, errores E-08 y validación de escenarios no ejecutada.

No presentar nuevo cobro ante timeout sin verificación. **E-01…E-07 fijan políticas** de estados, roles, dinero, consistencia, avisos e identidad/datos; **E-08 sigue pendiente** para respuestas/API y validación de seguridad. Figma no autoriza mecanismos nuevos ni sustituye derechos del consumidor.

## Cotización, cargos y balances visibles — E-04 APROBADO (D-96…D-108)

El documento `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md` está **APROBADO en política comercial/numérica** (D-96…D-108). Figma deberá representar los flujos confirmados, preservando las reglas de permisos de E-02 y estados E-01:

- Precios finales y ofertas manuales independientes por variante; subtotal por cantidad y cargo de delivery una sola vez cuando proceda.
- Re-cotización antes de crear intento de pago si los precios/UBIGEO cambiaron, con confirmación clara del comprador; estados de error de importe/moneda y proveedor incoherente.
- Precio histórico pactado, importe bruto cobrado, comisión del proveedor solo donde corresponda, ajuste comercial, importe a devolver y devolución externa **comprobada** como conceptos separados.
- Cancelaciones de línea completa y expedientes por cantidad parcial, con decisión administrativa si hay dinero acreditado.
- Cambios de dirección con variación de tarifa y resolución, nunca cobro adicional implícito; contraentrega sin doble acreditación.
- Documento externo SUNAT y nota de crédito solo según régimen/supuesto, sin calculadora genérica IGV ni comprobante inventado.

**PENDIENTE:** componentes, textos/errores, fuente de tarifas, límites de almacenamiento, permisos REST, redondeo fiscal J-03, datos E-07, envío real E-08 y texto legal del Libro P0-25. E-06 ya está aprobado en política de comunicaciones; no se ha demostrado entrega.

## Pantallas para elegibilidad y derechos — política E-03 APROBADA (D-86…D-95)

E-03 aprobado D-86…D-95 exige expedientes, elegibilidad y remedios; E-06 aprobado D-123…D-134 fija notificación/constancia cuando corresponde. Figma representará cancelaciones, devoluciones, servicios, reprogramaciones, no-show y avisos sin prometer reembolso ni entrega. Faltan campos/identidad E-07, API E-08 y normas J-01/J-02/P0-25.

**Fuente legal actualizada:** revisar `07-CUMPLIMIENTO-PERU.md` para DL 1729/2026 (reclamos digitales accesibles y prohibición de prácticas coercitivas). Los controles de reclamo no deben depender de completar una compra, usar un descuento o cambiar el estado de una línea, y no pueden manipular la elección del consumidor. Mantener canal del Libro de Reclamaciones como obligación independiente.

## Matriz visual de roles — E-02 cerrada en política (D-78…D-85)

- **Operador:** diseñar pantallas para atención de pedidos con datos mínimos, alta/edición y ocultamiento de productos, precios/ofertas, stock/agenda con auditoría, preparación/entrega y reprogramación con evidencia. No mostrar exportación masiva, lectura de archivos digitales protegidos, fiscalidad completa, credenciales, políticas, roles ni expedientes completos de reclamaciones.
- **Administrador:** acceso autorizado a configuración de integraciones, fiscalidad, Libro de Reclamaciones, políticas y perfiles; exportaciones/ficheros protegidos solo bajo política de datos, corrección de cobros y registro de reintegros externos.
- **Dirección confirmada:** operador recibe y prepara propuesta; si varía tarifa/cobertura o existe dinero acreditado, mostrar aprobación administrativa previa. Preservar condición original y cambio vigente con estados de espera/rechazo.
- **Invitado:** enlace de seguimiento presenta datos propios en modo consulta; reemisión, solicitud de cambio de dirección o devolución exige un **paso adicional de identidad** cuyo mecanismo aún depende de E-07.
- **Los estados de error y permisos deben diseñarse para móvil/escritorio:** sin capacidad, dato protegido, enlace inválido/ajeno, revisión administrativa, falta de identidad y conflicto de versión.
- **PENDIENTE:** campos exactos, controles y nombres de capabilities, UX de identidad, protección de sesiones y límites de los datos según E-07/E-08. Figma no concede capacidades que el contrato no permita.

## Contrato visual aprobado para cancelaciones e historial (D-58…D-60)

- **Cancelar línea:** mostrar acción ordinaria únicamente para línea **pendiente de cumplimiento y elegible**. Para línea entregada o servicio prestado, mostrar acceso correspondiente a devolución/reclamación/incidencia sin prometer cancelación retroactiva ni desconocer derechos legales.
- **Historial:** detalle de pedido confirmado con datos originales conservados; ajustes y correcciones se presentan en secuencia trazable, indicando actor, fecha y motivo, sin «editar original» destructivo.
- **Roles:** si el pedido tiene dinero acreditado o exige cualquier ajuste/reintegro, requerir administrador (D-64); el operador solo puede cancelar sin cobro acreditado ni efectos financieros y con capacidad expresa. Diseñar estado de «se requiere administrador» sin presentar una acción denegada como exitosa.
- **Dinero:** registrar efecto monetario auditable; devolución externa es un paso independiente y solo se muestra como completada cuando el administrador registre resultado verificado.
- **Estados/error:** línea no pendiente, cumplimiento ya realizado, permiso insuficiente, recálculo pendiente, intento duplicado, cambio concurrente y reclamo/devolución gestionados por otra vía. Montos y transiciones exactas sujetos a contrato pendiente.

## Reglas de diseño confirmadas para estados y parcialidad (D-55…D-57)

- **Detalle de pedido y panel:** representar por separado el estado administrativo, los estados de pago y el cumplimiento de cada artículo. Un pedido pagado con una sola línea entregada no aparece como completamente cumplido.
- **Cancelación parcial:** ofrecer selección de línea pendiente de cumplimiento y elegible con resumen de efectos/importe y confirmación; mantener líneas restantes y originales intactos. Si tiene efecto financiero en pedido pagado, autoriza solo administrador; si hay reembolso, se procesa externamente y solo administrador registra el resultado comprobado.
- **Precios:** formulario simple con su propio precio final y oferta manual; formulario variable con precios/ofertas explícitos por variante. No incluir herencia implícita de valores ni permitir una compra con variante sin precio válido.
- **Estados de error:** línea ya cumplida/no elegible, restricciones legales, doble cancelación, ajuste monetario no resuelto, reintegro externo sin evidencia, permisos insuficientes y diferencia entre dinero recibido y artículo entregado.
- Las transiciones y reglas contables concretas siguen pendientes; Figma no debe inventar permisos, derechos de cancelación ni prorrateos.

## Pantallas afectadas por variantes y ajustes financieros (D-52…D-54)

- **Ficha/catalogo/carrito:** seleccionar variante actualiza su precio final PEN, oferta manual y stock propios; los importes mostrados se recalculan desde el backend y se conserva el snapshot de venta.
- **Editor de producto:** campos y acciones de precio y oferta por variante vendible, con controles de activación manual accesibles; no programar fechas ni cupones.
- **Panel del operador:** permite registrar efectivo cobrado; no incluye correcciones, reembolsos ni credenciales.
- **Panel del administrador:** formulario de ajuste de cobro referenciado al registro original (no borrado) con motivo y trazabilidad; sección para registrar resultado **comprobado** de reembolso procesado externamente, sin botón de «reembolsar desde PeruCommerce».
- **Estados de error:** modificación sin permisos, referencia inválida, importe contradictorio, doble acreditación, ausencia de verificación externa, operación parcial sin contrato. No mostrar «reembolsado» solo por registrar intención.
- **Pendientes:** campos definitivos de ajuste, prueba externa, autorización, revocación y estados de pago. Figma no puede imponerlos silenciosamente.

## 5. Contrato móvil / escritorio

| Aspecto | Móvil (propuesta) | Escritorio (propuesta) |
| --- | --- | --- |
| Navegación | Una columna priorizada, contacto táctil | Columnas/paneles si ayudan |
| Carrito | Barra inferior y panel inferior/pantalla completa | Panel lateral |
| Checkout | Una sola ruta, secciones verticales | Una sola ruta, formulario + resumen |
| Acción principal | Visible sin tapar teclado, campos o textos legales | Destacada sin competir con secundarios |
| Servicios | Calendario y horarios táctiles, lista alternativa | Calendario/slots con teclado |
| Dirección | Formulario primero, mapa opcional | Formulario y mapa solo cuando procede |
| Estados | Errores contextuales y recuperación | Mismos estados, composición apropiada |
| Tecnología | Mismos contratos de API/negocio | Mismos contratos de API/negocio |

El criterio es adecuación a tarea/dispositivo, no «dos versiones por obligación estética». Se revisará el comportamiento en anchos intermedios.

## 6. Prioridad de diseño Figma propuesta

1. Checkout móvil de un artículo físico con contraentrega, con errores, zonas y confirmación.
2. Mismo checkout en escritorio.
3. Carrito mixto con físico, digital y servicio; mostrar solo campos aplicables.
4. Reserva de servicio y agotamiento de cupo (móvil/escritorio).
5. Compra digital y autorización de descarga.
6. Mercado Pago con retorno pendiente/confirmado/rechazado.
7. Catálogo/ficha/variantes/bloques WordPress.
8. Panel de comerciante: onboarding, productos, agenda, pedidos, zonas/pagos, reclamaciones.
9. Estado sin proveedor externo, fallo de red, textos largos, accesibilidad/zoom.

**Condiciones confirmadas para los frames (D-18…D-26):** el calendario representa una única agenda/recurso de un servicio presencial; el carrito admite como máximo una cita; las composiciones de pago no deben ofrecer efectivo si alguna línea no es cobrable presencialmente; delivery se calcula por distrito UBIGEO, no por polígonos; el correo es obligatorio para digitales/servicios y el teléfono para físicos; habrá vistas de administrador y operador con capacidades diferentes; el selector fiscal solamente muestra documentos externos aplicables.

**Decisiones confirmadas D-27…D-35 para diseñar:** mostrar fecha y hora en ficha antes de carrito, revalidar en checkout; presentación de retención de 15 min desde inicio de intento de pago sin auto-renovación; errores de pago tardío sin cupo/stock como incidencia; configuración visual de horarios semanales, buffers y fechas bloqueadas; cancelación/reprogramación manual auditada con devolución separada; stock individual por variante; enlaces digitales temporales y límites opcionales sin cuota por defecto; un cobro por pedido y contraentrega en un mismo evento presencial; seguimiento privado de pedido sin cuenta.

**Condiciones confirmadas D-36…D-41 para cada frame correspondiente:**
- El botón «Pagar» inicia la retención solamente cuando el backend acepta y crea un intento válido; contador de 15 minutos y recuperación tras error.
- La confirmación contraentrega compromete stock/cita inmediatamente, diferenciando «pedido confirmado» de «cobro pendiente».
- La agenda permite un cliente por franja, con duraciones y buffers que no se solapan.
- El comercio configura condiciones de cancelación y el comprador las puede consultar; no diseñar un plazo obligatorio universal de 24 horas.
- Enlaces individuales de descarga digital duran 24 horas; diseñar estado de token caducado y reemisión segura.
- Productos presentan precio normal y oferta simple; **no diseñar campo de cupón ni promociones automáticas V1**.

Siguen **PENDIENTES** atomicidad/reintentos al crear el intento, método de reemisión verificada, valores de agenda por defecto, permisos granulares, lugar/momento concreto del cobro, cálculo fiscal y compensación. Figma no debe inventar esos contratos.

No se trata de una orden de implementación hasta que el alcance esté aprobado.

## 7. Handoff a Codex

**Instrucción UX adicional:** Codex debe leer primero `docs/10` y las fichas pertinentes de este documento; al implementar una pantalla, elegir y explicar brevemente los principios UX **aplicables**, preservando contratos, permisos, navegación y datos, y revisar la interacción real cuando se autoricen pruebas. La referencia son los 34 frames existentes, **sin exigir redibujar los frames, añadir otra aplicación ni introducir componentes visuales por moda**. Los objetivos D-16/D-17 se mantienen obligatorios; las heurísticas restantes no son decisiones comerciales ratificadas ni medidas de rendimiento demostradas.

Cada componente/pantalla aprobada debe entregar:
- Link exacto de archivo y frame Figma estable, última versión aprobada y responsable de aprobación.
- Tokens/variables y assets exportables con licencias compatibles con distribución.
- Contrato de entradas/salidas, estados, eventos y permisos referenciados en GitHub.
- Variantes completas, responsive e instrucciones de movimiento/reflow.
- Matriz de casos de aceptación vinculados, incluyendo errores y módulos opcionales.
- Criterios UX pertinentes (UX-01…UX-24) con comportamiento observable: acción principal/alcance táctil, agrupación, entradas, estados y recuperación; indicar cuándo un principio no aplica en vez de añadir elementos innecesarios.
- Pantallas de referencia comparables a una implementación posterior (misma tarea/estado y ancho).
- Lista explícita de asuntos no decididos. No codificar los que bloquean comportamiento.

Acceso mediante Figma/MCP si está conectado y autorizado; **tener diseño en Figma no garantiza que Codex posea contexto, permisos ni que el código resultante sea correcto**. Se requiere revisión humana de fidelidad, funcionalidad, accesibilidad y rendimiento cuando esas etapas se autoricen.

## 8. Reglas de calidad para aprobación visual

- No declarar «aprobada» una pantalla con estados de error o pago pendientes sin especificar.
- No usar librerías visuales distintas para móvil/escritorio si generan incongruencia de marca y comportamiento.
- No colocar información esencial en una interacción que solo aparece por hover.
- No ocultar totales, cobertura, cambios, devoluciones y reclamaciones por minimalismo.
- No diseñar mapas como único método de entregar ubicación.
- No usar imágenes o microanimaciones que impidan visualizar y completar una compra.
- No exigir Dark Mode, PWA, login social, chatbot, tours o efectos visuales como supuesto requisito moderno.

## 9. Pendientes antes de aprobar el contrato

Identidad/branding y paleta; tipografía y licencias; demostración del cumplimiento de WCAG 2.2 AA y 44 × 44 px CSS; inventario exacto de pantallas/variantes; puntos de corte tras prototipos; integración técnica de Angular ya aprobado con temas WordPress; **versiones estables/mantenidas concretas todavía por verificar** y librerías visuales; matriz de soportes móviles/escritorio; diseño de calendario según modalidad y recursos; desglose fiscal según régimen y orden de campos definitivos.

**Regla:** primero se cierra el dominio; después se aprueban los flujos; posteriormente Figma resuelve la presentación. Para pedidos, pagos, cumplimiento, citas y permisos, consultar `12-CONTRATO-ESTADOS-TRANSICIONES-Y-PERMISOS.md`, `13-MATRIZ-DE-HUECOS-Y-ESCENARIOS.md`, el **contrato E-01 aprobado** `14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md` y la **política E-02 aprobada** `15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md`. La política de acceso D-78…D-85 es vinculante; **E-03 está aprobado en política funcional (D-86…D-95)** en `16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md` y **E-07 aprobado D-135…D-148** en doc.20 fija minimización/identidad, mientras capacidades/rutas/errores técnicos E-08 siguen pendientes. Figma no aprueba contratos de negocio ni técnicos aún abiertos.
