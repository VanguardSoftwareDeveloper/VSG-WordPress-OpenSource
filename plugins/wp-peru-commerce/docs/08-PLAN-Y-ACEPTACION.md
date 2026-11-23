**PRECEDENCIA DE IMPLEMENTACIÓN D-200/DOC-QA31 (2026-11-18):** OpenAPI **0.12.0-draft (69 REST/66 rutas/138 esquemas)**, 70 entradas R8 incluyendo R8-18, 200 D, 56 PER y 39 tablas. R8-70 permite A cerrar caso físico parcial `open_review` mediante rechazo motivado o retiro nuevamente verificado sin modificar pedido/dinero/stock; R8-26 exige `expected_case_version` para `affected_units`, y cierra `closed_adjusted` **atómicamente** con los efectos del ajuste. Los criterios originales de aceptación son documentos, no pruebas ejecutadas; los conteos históricos no prevalecen sobre OpenAPI actual. P-A cerrada documentalmente, P-B aún no autorizado, P-C/P-D sin pruebas/despliegue.

# Plan de ejecución y criterios de aceptación — PeruCommerce

**Estado:** **DISEÑO FASE 0 / P-A CERRADO D-197 CON RECTIFICACIÓN D-200**, sin calendario de entregas ni autorización de código. Fuente vigente docs/41; las comprobaciones reales de P-C/P-D siguen pendientes de ejecución autorizada.

**Definition of Ready actualizado (2026-11-17):** docs/35/36 mantienen el origen de 10 LEG y 16 DB-A; D-189…D-194 cerraron sus decisiones técnicas y políticas sin ejecutar DDL ni certificar legalidad. **OpenAPI del corte histórico DOC-QA16…21: 0.8.0-draft tenía 67 REST/64 rutas/134 DTO**, incluyendo R8-65/66 de detalle protegido. Docs/41 cierra P-A y clasifica P-B/P-C/P-D. **Figma 34 frames D-195 es baseline técnica para ingeniería** (no aceptación estética personal de cada frame ni prueba WCAG). La autorización de código y pruebas es independiente.

## Seguimiento operativo de la implementación

El desglose aprobado se mantiene en [docs/43-ESTADO-IMPLEMENTACION.md](43-ESTADO-IMPLEMENTACION.md): **72 fases con IDs estables IMP-01…IMP-72**, dependencias, entregables, cierre, casillas y evidencia para retomar entre chats. Este documento conserva las macrofases 0…4 y los criterios A; docs/43 registra el avance real. El plan incorporado no inicia implementación ni acredita pruebas.

## Fase 0 — ESPECIFICACIÓN RECTIFICADA EN DISEÑO D-197/D-198 (SIN CÓDIGO)

**Índice de cierre vigente (2026-11-17):** `docs/34-MATRIZ-CIERRE-FASE-0-Y-VALIDACIONES-PENDIENTES-V1.md` contiene **19 puertas F0** (F0-19 cerrada en alcance D-188). `docs/37` y `docs/38` especifican contratos candidatos de seguridad/expedientes y 16 DB-A; `docs/39-MATRIZ-DE-PUERTAS-ANTES-DE-CODIGO-Y-RELEASE-V1.md` clasifica qué **sí** requiere ratificación antes de escribir código (P-A), qué depende de código y pruebas posteriores (P-C) y qué es habilitación comercial (P-D). Ninguno de esos documentos autoriza implementar. **Está cerrada P-A y lista para SOLICITAR autorización de implementar**, no para implementar sin permiso. No hay autorización de código, compilación, pruebas o despliegues. Fuente vigente D-01…D-197; 66 entradas R8 (65 REST, R8-18 página WP), 56 PER y 39 tablas lógicas con invariantes D-191.


1. Congelar decisiones confirmadas y registrar contradicciones y supuestos.
2. Resolver todos los P0 en `09-DECISIONES-PENDIENTES.md`.
3. **E-01…E-08 APROBADOS EN SUS POLÍTICAS (D-67…D-166)**, docs.14…21. Las 18 opciones E08-A…R se registraron como D-149…D-166, con criterios **A-165…A-182**. El inventario histórico E-08 tuvo 52 entradas R8 y 60 I8-X documentales **NO EJECUTADOS**; la ampliación C23 D-176…D-187 aportó 12 entradas; **D-189/D-198 agregaron cuatro GET privados** para **68 R8 (67 REST + 1 página WP)**, sin ampliar permisos de operador. Quedan nueve áreas P0 abiertas en sus aspectos físicos, J-01…J-05, revisión de 39 tablas, hosting/Angular/mapas/licencia/ZIP y validación MP Perú, resumidas en docs/34. No autoriza código ni pruebas.
4. Fijar compatibilidad WordPress/PHP/base de datos tras verificación oficial de **versiones estables y mantenidas (D-48)**; con Angular ya elegido definir montaje, dependencias, APIs y empaquetado; con Leaflet definir proveedor cartográfico real y fallback; cerrar esquema, matriz fiscal y migraciones.
5. Precisar matriz de cumplimiento peruano con verificación de fuentes. Incorporar Ley 29571, Ley 32495 y DL 1729 de 2026; comprobar reglamentos definitivos y tipos de comprobante SUNAT por operación. **No confundir normativa publicada con propuestas de derecho de arrepentimiento.**
6. Aprobar diseño de flujos de compra, calendario, administración y navegación.
7. Aprobar alcance/exclusiones V1, licencia y acuerdos de distribución.

**Salida documental pretendida:** matriz de puertas F0 con contratos aceptados, pendientes externos identificados y alcance sin contradicciones. La existencia de pruebas P-C/P-D pendientes por no existir software **no** debe interpretarse como necesidad de probar el producto antes de escribir su primer módulo; se exige tener definidos sus criterios de aceptación y autorización por fase, sin habilitar operaciones inseguras. PRE-01…PRE-07 siguen cerrados en decisiones de diseño D-189…D-197 con rectificación contractual D-198, incluida línea base Figma de 34 marcos. La marca correcta es **`P-A_CERRADA_PB_NO_AUTORIZADA`**. El dueño aún debe autorizar explícitamente el módulo de código en P-B; no exigir pruebas de P-C/P-D antes de escribir la primera línea. Las pruebas de MP/hosting/seguridad solo se ejecutan si se autoriza su entorno y alcance; una lista de escenarios no es prueba de comportamiento.

## Fase 1 — Figma base de ingeniería D-195, pruebas y refinamiento posteriores

- Sistema visual, tokens y componentes; accesibilidad.
- Composición móvil y escritorio independiente para cada pantalla.
- Estados de carga, vacíos, errores, pagos, reservas, entregas y reclamaciones.
- Archivo de referencia Figma: https://www.figma.com/design/qJaGwnjfaI35gYDjFwzBub, con diecisiete pares móvil/escritorio de catálogo, checkout, servicios, expedientes, error de cotización, seguimiento, Libro público, inventario, digital, confirmación, reprogramación, reembolso externo, incidencia logística, comprobantes externos, privacidad, diagnóstico y cancelación. Se congeló baseline para ingeniería D-195 por instrucción de cerrar la fase; **no** confundir esos marcos con aplicación navegable, aceptación estética personal ni medición WCAG.
- Entrega de enlaces de frames/variantes, mapeo a componentes e inventario de assets.
- Aplicar los objetivos CONFIRMADOS WCAG 2.2 AA y áreas táctiles 44 × 44 px CSS de `10-ESTANDARES-UX-MOVIL-Y-ACCESIBILIDAD.md`; las demás metas continúan propuestas. Seguir el contrato de frames/tokens de `11-CONTRATO-FIGMA-Y-DISENO-DE-COMPONENTES.md` congelado como baseline D-195.
- Revisar que no existan decisiones de negocio «escondidas» en Figma.

## Fase 2 — Diseño técnico lógico ratificado; detalles físicos y pruebas futuros

- Contratos REST de entrada/salida/error/permiso; modelos, índices, migraciones, roles y auditoría.
- **E-01…E-08 APROBADOS EN SUS POLÍTICAS** (D-67…D-166): doc.21 confirma REST versionado, Angular embebido, cotización/checkout, sesión invitada/OTP, MP Orders condicional, firma de webhooks, capabilities, errores y streaming privado. **Pendientes:** formalización física R8/PER/OpenAPI/DTO, matriz legal J-01…J-05/P0-17/P0-25, SQL/hosting/ZIP, versiones y comprobaciones reales de seguridad/MP bajo autorización. Elegir arquitectura no demuestra funcionamiento ni cumplimiento.
- Diagramas de concurrencia para stock/agenda; expiración y compensación. El contrato técnico doc.18 está **APROBADO D-109…D-122**, con 48 escenarios C5-X no ejecutados; todavía faltan diseño físico/índices/tiempos/hosting y pruebas que solo se ejecutarán si se autorizan.
- Contratos de integraciones con proveedor y manejo de fallos.
- Versiones mínimas y objetivo exactas **verificadas frente a documentación oficial vigente**, estables/mantenidas y compatibles; requisitos de base de datos, dependencias y licencias. No elegir números por suposición.
- Plan de compatibilidad, rendimiento, seguridad, mantenimiento y distribución de ZIP.

## Fase 3 — Implementación por módulos (futura; requiere autorización)

Ejecutar el desglose **IMP-01…IMP-71** de [docs/43](43-ESTADO-IMPLEMENTACION.md) dentro del alcance autorizado. Comienza por arranque WordPress, compatibilidad, DDL/migraciones, transacciones, idempotencia, eventos/outbox, permisos y configuración; después integra catálogo, inventario, agenda, delivery, archivos privados y pedidos antes de cerrar cotización/checkout. Continúa con pagos/recuperación, identidad, operación/casos/dinero, obligaciones aplicables e interfaces; cierra la fuente con distribución preparada, recuperación, escenarios y revisión estática integrada.

La instalación/migración básica y la persistencia de avisos son fundamentos tempranos; el transporte/plantillas y el empaquetado se completan en sus fases. Cada módulo tiene su criterio de cierre estático y consumidores identificados. Una fase puede abarcar varios chats; una autorización para varias fases permite avanzar sin pedir confirmación por cada casilla. No añadir complementos ajenos al alcance ni marcar probado lo que solo se revisó en lectura.

## Fase 4 — Verificación y entrega (futura; permisos propios)

Corresponde a **IMP-72 — Codex** en [docs/43](43-ESTADO-IMPLEMENTACION.md): recibir la fuente ya construida, auditarla adversarialmente, corregir defectos, ejecutar las verificaciones autorizadas y generar/revisar el ZIP instalable junto con la fuente correspondiente, licencias y evidencia del mismo release. Incluye integración de pagos en el ambiente permitido, seguridad, accesibilidad, funcionalidad, compatibilidad y ciclo de instalación/actualización/respaldo/restauración. Si cambia código después de comprobarlo, reconstruir y repetir las verificaciones afectadas sobre el artefacto final. **No afirmar funcionalidad real sin ejecutar las verificaciones pertinentes**; compilación, pruebas, paquete, publicación y producción conservan estados separados. Los gates por comercio siguen docs/41.

## Matriz inicial de escenarios de aceptación (PROPUESTA)

| ID | Condición | Resultado observable |
| --- | --- | --- |
| A-01 | Comprador invitado físico | Completa compra sin crear cuenta |
| A-02 | Catálogo incrustado | Bloque/shortcode muestra productos y enlaza checkout |
| A-03 | Móvil | Carrito inferior y checkout adaptado, sin desbordamiento |
| A-04 | Escritorio | Carrito lateral y resumen de checkout legible |
| A-05 | Producto con variante | Precio/stock de variante correcta, no variante inválida |
| A-06 | Última unidad simultánea | No se acepta sobreventa |
| A-07 | Último cupo simultáneo | No se confirma doble reserva |
| A-08 | Vence retención sin tarea programada | Nueva compra no ve capacidad falsamente ocupada |
| A-09 | Carrito mixto | Pide solo datos aplicables, registra cumplimiento por línea separado del pedido/pago y permite cancelación parcial conforme a elegibilidad y auditoría aprobadas |
| A-10 | Mercado Pago retorna aprobado visualmente | Sistema espera verificación autoritativa |
| A-11 | Webhook duplicado/fuera de orden | Sin duplicación de cobro ni estado regresivo |
| A-12 | Pago aprobado sin stock/cupo | Se crea incidencia; no se confirma disponibilidad inexistente |
| A-13 | Contraentrega | Al confirmar pedido válido compromete stock/cita y mantiene cobro pendiente hasta que se acredite manualmente |
| A-14 | Descarga digital no pagada | Acceso denegado |
| A-15 | Descarga pagada | Acceso autorizado según límites aprobados |
| A-16 | Dirección sin cobertura | No permite finalizar envío incompatible |
| A-17 | Proveedor cartográfico falla | Ofrece alternativa manual sin mentir sobre cobertura |
| A-18 | Cambio de precio posterior | Pedido anterior conserva importes pactados |
| A-19 | Desactivación de módulo | Sin pérdida de pedidos ni registros históricos |
| A-20 | Libro nativo | Genera hoja/constancia y acceso público conforme |
| A-21 | Libro externo | Enlace permanentemente accesible y configurable |
| A-22 | Falla actualización ZIP | Datos persistidos no se destruyen |
| A-23 | Usuario sin permisos | No puede modificar pedidos/datos ajenos |
| A-24 | Refresco/doble clic en checkout | No duplica pedido |
| A-25 | Cancelación/reembolso | Conserva evidencia y ajusta derechos según reglas |
| A-26 | Servicio presencial V1 | Solo se puede reservar en el local principal; no se ofrecen modalidades remota ni a domicilio |
| A-27 | Control táctil móvil | Controles táctiles independientes con objetivo activable de al menos 44 × 44 píxeles CSS; ninguna acción principal queda tapada por teclado/barra fija |
| A-28 | Accesibilidad | Verificar WCAG 2.2 AA como requisito obligatorio antes de declarar conformidad: foco, contraste, teclado, reflow, mensajes y alternativa al arrastre |
| A-29 | Formulario condicional | Solicita solo datos aplicables, admite autocompletado y conserva datos válidos al corregir errores |
| A-30 | Compra móvil/escritorio | Dos composiciones aprobadas sobre una sola lógica; sin pérdida de función a 320 px CSS ni en zoom |
| A-31 | Rendimiento | Se miden y evalúan LCP, INP y CLS contra umbrales aprobados en dispositivos/condiciones documentados, sin afirmar cumplimiento por diseño |
| A-32 | Interoperabilidad con tema WordPress | Catálogo y checkout mantienen legibilidad, foco y disposición sin colisiones de estilos o scripts en matriz de temas aprobada |
| A-33 | Handoff Figma→Codex | Cada pantalla tiene frame aprobado, tokens/variantes, estados, referencias de contratos y criterios de aceptación |
| A-34 | Agenda y recurso único | No se publican agendas/profesionales adicionales en V1; disponibilidad de un recurso se valida al reservar |
| A-35 | Una cita por pedido | Un segundo servicio con cita se rechaza o requiere un nuevo pedido; el resto del carrito físico/digital sigue siendo posible |
| A-36 | Pago mixto | Mercado Pago disponible para todos los tipos; efectivo no se ofrece si alguna línea no es cobrable presencialmente |
| A-37 | Gestión de cambios | Sin autoservicio de cambio/cancelación de citas; operador autorizado gestiona desde panel según política futura |
| A-38 | Cobertura por UBIGEO | Solo ofrece delivery en distritos configurados, con tarifa correspondiente y sin cálculos por polígono |
| A-39 | Datos condicionales | Solo físicos: teléfono; digital/servicio: correo; mixto aplicable: ambos, más requisitos normativos/operativos definidos |
| A-40 | Roles y permisos | Administrador y operador limitado; acciones no autorizadas rechazadas en backend, conforme matriz futura |
| A-41 | Comprobante externo | Opciones boleta/factura/recibo por honorarios condicionadas a sujeto/régimen/operación, sin emisión automática |
| A-42 | Pedido con efectivo y digital | Si todo el pedido es cobrable en un único evento presencial, el método puede habilitarse; descarga digital bloqueada hasta acreditar el cobro manual |
| A-43 | Retención temporal de recursos | Desde pulsar «Pagar» y crear intento válido en el backend retiene stock/cupo 15 min sin renovación; selección de horario en ficha no bloquea recurso |
| A-44 | Pago tardío sin disponibilidad | Pedido entra en incidencia; comerciante decide reprogramar/reintegrar y no se confirma stock/cupo imposible |
| A-45 | Agenda del servicio | Respeta horario semanal, duración por servicio, buffers y fechas bloqueadas, sobre un solo recurso |
| A-46 | Cambio de cita y dinero | Solo comerciante autorizado cancela/reprograma con auditoría; cambio de cita no activa devolución automática |
| A-47 | Inventario de variantes | Bloqueo y descuento de stock por variante vendible cuando está habilitado |
| A-48 | Descargas de digitales | Enlace temporal protegido, sin cuota de descarga predeterminada; respeta límites configurados |
| A-49 | Cobro único | Una sola operación de cobro por pedido; efectivo solo si todas las líneas pueden cobrarse en el mismo evento presencial |
| A-50 | Pedido de invitado | Consulta con enlace privado no enumerable tras compra y enviado por correo cuando existe; soporte telefónico requiere identificar al cliente |
| A-51 | Selección de cita | Fecha/hora se elige en ficha antes de carrito; checkout revalida disponibilidad y comunica pérdida del cupo |
| A-52 | Inicio preciso de retención | Pulsar «Pagar» y crear intento válido en servidor origina retención de 15 min; si la creación falla no se anuncia recurso retenido; reintentos no duplican bloqueos |
| A-53 | Contraentrega confirmada | Al confirmar pedido contraentrega válido se comprometen stock/cita y el cobro permanece pendiente; no esperar aceptación manual del comerciante |
| A-54 | Capacidad de la agenda | Un cliente por franja y recurso, sin solapamiento al considerar duración y pausas/buffers |
| A-55 | Condiciones de cancelación | Cada comercio configura y muestra sus condiciones; no se impone plazo universal de 24 h ni se eliminan derechos legales; devolución separada |
| A-56 | Enlace digital de 24 h | Token individual expira a las 24 horas; no permite descargar con token vencido; comprador autorizado obtiene reemisión segura sin cuota global predeterminada |
| A-57 | Precios de oferta simple | Precio normal y precio de oferta se muestran/cobran correctamente según contrato futuro; V1 no muestra cupones ni aplica promociones automáticas |
| A-58 | Catálogo indexable e interacción aislada | WordPress/PHP entrega catálogo/fichas/bloques indexables sin depender de Angular; carrito/checkout/panel usan Angular sin cargarlo globalmente en páginas ajenas |
| A-59 | Diseño de persistencia | Evaluación documentada de estructuras nativas para catálogo; tablas propias de pedidos, pagos y reservas con esquema, índices, migraciones e invariantes aprobados antes de implementar |
| A-60 | Mapa y respaldo manual | Leaflet permite proveedor sustituible; ante fallo o ausencia de mapas el comprador introduce dirección manual, y el backend valida distrito/cobertura/tarifa sin inventar geolocalización |
| A-61 | Moneda única PEN | Productos, ofertas, delivery, pedidos y pagos usan PEN (S/) sin selector de otra divisa ni conversión en V1 |
| A-62 | Precio final y fiscalidad contextual | El checkout comunica el total final con cargos aplicables antes de confirmar; las reglas fiscales se aplican según matriz de régimen/operación aprobada, sin inventar IGV universal ni motor fiscal general |
| A-63 | Operador limitado | Un operador autorizado gestiona pedidos/productos/inventario/agenda; las API y la UI niegan acceso a credenciales y configuración sensible, reservadas al administrador |
| A-64 | Compatibilidad mantenida | Las versiones mínimas se justifican documentalmente por estabilidad, mantenimiento y compatibilidad de WP/PHP/Angular/BD; no se anuncian soportes antiguos sin evidencia y validación autorizada |
| A-65 | Precio final configurado | El comerciante ingresa precio final en PEN para cada producto/variante aplicable, no se le exige declarar precio neto para que el sistema calcule impuestos genéricos; la cotización del servidor muestra total y cargos aplicables |
| A-66 | Oferta simple manual | Activar/desactivar precio de oferta produce el precio vigente esperado y conserva snapshots de pedidos anteriores; V1 no programa fechas ni activa descuentos automáticamente |
| A-67 | Cobro efectivo registrado por operador | Operador autorizado registra efectivo realmente recibido con actor, fecha, importe y pedido; se impiden acreditaciones duplicadas; API/UI niegan reembolsos y lectura/modificación de credenciales al operador |
| A-68 | Precio y oferta independiente por variante | Dos variantes del mismo producto tienen precios finales/ofertas manuales propios; cambiar una no modifica el importe de la otra; pedido conserva precio de variante vendido |
| A-69 | Corrección exclusiva y no destructiva de efectivo | Solo administrador registra un ajuste de cobro con motivo, actor y referencia; original permanece inalterado; operador no corrige por interfaz ni API |
| A-70 | Reembolso externo registrado | Solo administrador registra resultado comprobado de reembolso hecho en Mercado Pago/medio correspondiente; no se envía una solicitud de reembolso desde el plugin ni se marca como completado sin verificación |
| A-71 | Estados independientes de pedido mixto | Un pedido con pago confirmado y solo una línea entregada conserva estado administrativo, estado de cada pago y cumplimiento por artículo separados; no declara todo cumplido ni confunde pago con entrega |
| A-72 | Cancelación parcial auditable | Cancelar una línea pendiente de cumplimiento y elegible registra ajuste con referencia, preserva otras líneas y sus importes originales; si hay efecto financiero sobre pedido pagado solo administrador autoriza, y eventual devolución externa se registra tras verificarse |
| A-73 | Precio simple versus precio de variante | Producto sin variantes vende con precio final/oferta propios; producto variable exige valores explícitos por variante sin herencia automática; variante sin precio válido no se compra y snapshots preservan precio elegido |
| A-74 | Cancelación ordinaria solo de líneas pendientes | Una línea pendiente y elegible puede cancelarse sin afectar las demás; una ya entregada o un servicio prestado no usa cancelación ordinaria, sino devolución/reclamación/incidencia según el caso, respetando derechos aplicables |
| A-75 | Cambios auditados sin reescritura del pedido | Tras confirmar un pedido, sus datos y snapshots originales permanecen inmutables; un cambio crea evento/ajuste relacionado con actor, fecha, motivo y efectos, sin borrar ni sobrescribir la evidencia |
| A-76 | Autoridad de cancelaciones con impacto financiero | En pedido pagado el ajuste financiero lo autoriza el administrador; además D-64 impide cualquier cancelación por operador con dinero acreditado aunque no haya reintegro estimado. El reembolso sigue externo y su resultado solo lo registra administrador |
| A-77 | Digital habilitado sin descargar | Una solicitud de cancelación o devolución de digital con acceso habilitado y sin descarga se deriva a evaluación del administrador y derechos legales; no se cancela ni devuelve automáticamente |
| A-78 | Físico despachado no entregado | No permite cancelación ordinaria inmediata de un artículo despachado; abre incidencia logística para evaluación y conserva trazabilidad |
| A-79 | Revisión de delivery al cancelar última línea física | Antes de despachar, la cancelación de la última línea física dispara revisión de costo de entrega por servicio/costos reales y derechos aplicables; si el pedido pagado requiere ajuste financiero autoriza administrador, sin importe fijo inventado |
| A-80 | Restricción estricta de cancelación por operador | Operador solo cancela línea elegible con permiso cuando no existe dinero acreditado ni reintegro/ajuste financiero requerido; con cobro acreditado se deniega incluso si el importe estimado a devolver es cero |
| A-81 | Ajuste de dirección confirmada | Modificación de dirección tras confirmar crea solicitud/ajuste auditado sin mutar snapshot original; revalida UBIGEO, cobertura, tarifa y viabilidad logística antes de aplicarse |
| A-82 | Reembolso externo parcial comprobado | Administrador registra resultado externo parcial con importe PEN, referencia y evidencia verificable; conserva pago original y evita doble registro; plugin no ejecuta reembolso por API |
| A-83 | E01-A: pedido administrativo | Estado de pedido solo admite PENDIENTE_PAGO/CONFIRMADO/FINALIZADO/CANCELADO/VENCIDO conforme matriz O; pagado, entregado, parcial e incidencia son dimensiones o indicadores separados |
| A-84 | E01-B: intento de Mercado Pago | MP usa CREADO/PENDIENTE/APROBADO/RECHAZADO/CANCELADO_PROVEEDOR/REQUIERE_CONCILIACION; retorno visual no acredita pago y devolución externa no sobrescribe APROBADO |
| A-85 | E01-C: retención | Hold usa VIGENTE/COMPROMETIDA/VENCIDA/LIBERADA; vencer a los 15 min sin renovación no implica cancelación del pago externo ni resurrección de hold antiguo |
| A-86 | E01-D: línea física | Línea física recorre PENDIENTE/PREPARANDO/LISTO/DESPACHADO/ENTREGADO/CANCELADO; fallo de entrega abre incidencia logística, no estado global de envío o pedido |
| A-87 | E01-E: digital y tokens | Digital usa BLOQUEADO/HABILITADO/CANCELADO/REVOCADO; descargar o vencer token 24 h registra evento sin destruir el derecho por defecto |
| A-88 | E01-F: servicio y cita | Línea de servicio usa PENDIENTE_CONFIRMACION/CONFIRMADA/EXPIRADA/REALIZADA/CANCELADA/NO_ASISTIO; selección/hold sin cita activa, confirmación única y reprogramación histórica sin dobles cupos |
| A-89 | E01-G: expedientes asociados | Incidencias, modificaciones de dirección y resultados de reintegros son registros independientes y no sustituyen un estado administrativo de pedido |
| A-90 | E01-H1: recuperar vencido con pago tardío | Pedido VENCIDO solo vuelve a CONFIRMADO tras cobro validado, resolución auditada y nueva disponibilidad real; hold vencido no se reactiva |
| A-91 | E01-H2: excepción tras despacho | Producto DESPACHADO no entregado solo se cancela tras investigación, no entrega constatada, elegibilidad legal, autorización administrativa y ajustes aplicables |
| A-92 | E01-H3: cierre sin pendientes | FINALIZADO solo se marca con todas las líneas resueltas y sin incidencias, ajustes ni reintegros abiertos al momento del cierre; reclamaciones posteriores son expedientes separados |
| A-93 | E01-H4: sin doble máquina de envío | Cumplimiento físico es por línea con eventos logísticos; no existe una segunda máquina de estados de envío en V1 ni dos campos contradictorios para representar la entrega |
| A-94 | E02-A: datos mínimos de operador | Operador consulta solo pedidos/contacto necesario para atención y entrega; no obtiene secretos ni datos fiscales completos por defecto, por UI ni API |
| A-95 | E02-B: catálogo sin borrado destructivo | Operador puede crear/editar productos, precios/ofertas y ocultar; intentar borrar definitivamente un producto con pedidos vinculados se deniega y conserva historial |
| A-96 | E02-C: stock y agenda auditados | Operador ajusta stock y agenda indicando motivo con auditoría; no puede modificar configuración fiscal, credenciales ni permisos de otros usuarios |
| A-97 | E02-D: cumplimiento y citas operativas | Operador autorizado registra preparación/entrega y reprogramación con evidencia y guardas E-01; no ejecuta reembolso ni cancela pedido con dinero acreditado |
| A-98 | E02-E: exportación y archivos protegidos | Operador no puede exportar masivamente ni acceder a ficheros digitales protegidos; administrador solo con política de datos y autorización correspondiente |
| A-99 | E02-F: dirección posterior | Operador recibe solicitud y propone ajuste de dirección; si cambia costo/cobertura o hay dinero acreditado, solo administrador aprueba tras validar cobertura y conservar snapshot original |
| A-100 | E02-G: configuración y reclamaciones | Solo administrador configura fiscalidad, credenciales, políticas, roles y gestiona expedientes del Libro; operador ve solo avisos operativos, comprador puede presentar reclamación conforme a ley |
| A-101 | E02-H: invitado y verificación adicional | Token privado da acceso solo a consulta del propio pedido; reemitir enlaces, solicitar cambio de dirección o devolución requiere verificación adicional definida en E-07 antes de ejecutar cambios |
| A-102 | E03-A: clasificación de solicitudes | Solicitud distingue cancelación ordinaria elegible, revisión administrativa y devolución/reclamación, sin confundirla con reintegro realizado ni negar cauces legales |
| A-103 | E03-B: política histórica | Pedido conserva referencia a versión inmutable/recuperable de condiciones informadas y evidencia de aceptación; cambio posterior no altera las condiciones originales válidas |
| A-104 | E03-C: físico sin despacho | Línea PREPARANDO o LISTO sin despacho se cancela ordinariamente solo si es elegible y tras validar costos/estado/permisos; incertidumbre se deriva a revisión |
| A-105 | E03-D: devolución y stock | Devolución/garantía de ENTREGADO genera expediente; solicitud sola no restaura stock vendible; requiere recepción/inspección o resolución autorizada |
| A-106 | E03-E: digital habilitado sin descarga | Administrador evalúa con evidencia expediente común tipado, sin cancelar ni reembolsar automáticamente por no haberse descargado |
| A-107 | E03-F: digital descargado defectuoso | Descarga registrada no impide reclamo por archivo o acceso defectuoso; expediente común conserva motivo, respuesta y derechos aplicables |
| A-108 | E03-G: revocación legítima digital | Derecho HABILITADO no se revoca solo por solicitud o token vencido; exige causa válida, resolución administrativa y auditoría |
| A-109 | E03-H: no-show | NO_ASISTIO se documenta con hechos/evidencia y expediente común; no impone multa ni devolución automática sin política válida y ley |
| A-110 | E03-I: reprogramación | Reprogramar requiere franja realmente libre, historial íntegro y constancia de comunicación; no sustituye remedios legales ante incumplimiento; canal E-06 pendiente |
| A-111 | E03-J: línea completa V1 | Cancelación ordinaria afecta línea completa; solicitud de cancelar parte de cantidad usa expediente/ajuste administrativo, sin fraccionamiento ni prorrateo automático; se mantienen devoluciones parciales externas admitidas |
| A-112 | E04-A: céntimos PEN exactos | Montos de pedido y ajuste internos usan enteros de céntimos, operaciones de precisión exacta y límites comprobados; proveedor recibe formato decimal válido sin float acumulado |
| A-113 | E04-B: precios/cantidades válidos | Rechaza precio efectivo cero, negativo o con más de dos decimales, oferta superior al precio regular y cantidades fraccionarias; no vender artículos gratuitos V1 |
| A-114 | E04-C: total comercial | Subtotal = cantidad entera × precio final en céntimos; total suma líneas y un delivery aplicable sin redondeos intermedios, ni añadir impuesto universal sobre precio final |
| A-115 | E04-D: recotizar y preservar snapshot | Precio/tarifa cambiados antes de Pagar obligan mostrar total nuevo y solicitar aceptación; intento válido congela cotización/pedido sin alterarse por catálogo posterior |
| A-116 | E04-E: única entrega física | Pedido físico usa recojo o delivery con una tarifa distrital; servicio presencial/digital no añaden delivery y un pedido mixto no lo cobra por línea |
| A-117 | E04-F: delivery previo y ajuste | Antes de pagar se presenta tarifa validada en servidor; cambio posterior crea ajuste auditable sin reemplazar cargo/condiciones originales |
| A-118 | E04-G: última línea física cancelada | Cuando desaparece última línea física antes de despacho, revisar prestación/costos jurídicamente cobrables; devolver tarifa completa si nada resulta cobrable; duda pasa a admin sin porcentaje fijo |
| A-119 | E04-H: precio histórico de cancelación | Cancelar línea completa calcula desde snapshot, no precio actual; cantidad parcial solo por expediente/ajuste admin y delivery por evaluación separada |
| A-120 | E04-I: cobro bruto y comisión MP | Importe inicial MP coincide con total pactado y suma de ítems; monto bruto, comisión y neto liquidado se conservan diferenciados, sin modificar deuda del comprador |
| A-121 | E04-J: efectivo neto | Registrar efectivo neto aplicado en un evento con auditoría; interfaz no administra billetes/vuelto; monto insuficiente no acredita pedido completo |
| A-122 | E04-K: dirección postpago más cara | Sin segundo cobro automático en mismo pedido; detener cambio hasta resolver con administrador otra dirección compatible o pedido nuevo viable, conservando original |
| A-123 | E04-L: reintegros y asientos inmutables | Pagos/ajustes/reintegros verificados mantienen originales, referencias únicas e idempotencia; acumulados no superan cobro acreditado y solicitud sin prueba no reduce saldo |
| A-124 | E04-M: documentación fiscal externa | Registrar comprobante/nota de crédito externo con referencia y desglose solo si se aporta válido, sin emitir XML SUNAT, calcular IGV universal ni alterar total histórico |
| A-125 | E05-A: InnoDB requerido y verificable | Operaciones de pedido/pagos/reservas usan motor transaccional InnoDB con índices válidos; si el soporte falla, se bloquean mutaciones críticas y no se declara éxito |
| A-126 | E05-B: Stock protegido por variante | Dos pedidos/ediciones simultáneos sobre última unidad no crean sobreventa; bloquear fila estable de variante, orden de locks y confirmar disponibilidad atómicamente |
| A-127 | E05-C: Agenda protegida en día vacío | Bloqueo estable por recurso/día funciona aun sin citas y evita solapamiento con duración/buffers, incluso en reserva concurrente o cruce de día |
| A-128 | E05-D: Crear order MP en fases | Intento idempotente con admisión breve interna anterior a llamada HTTP; solo exponer checkout al confirmar hold de 15 min desde intento válido, sin reservar comercialmente antes ni extender TTL |
| A-129 | E05-E: HTTP fuera de transacción SQL | Ninguna llamada HTTP externa ocurre con transacción SQL abierta; ante resultado ambiguo se reconcilia/correlaciona sin segundo cobro a ciegas |
| A-130 | E05-F: Expiración efectiva sin cron puntual | Hold VIGENTE vence por reloj backend/BD incluso sin tráfico o cron; no se renueva, ni cuenta disponibilidad ya caducada como retenida |
| A-131 | E05-G: Idempotencia durable | Repetición de misma clave/payload devuelve resultado anterior sin doble efecto; clave reutilizada con importe/datos distintos provoca conflicto seguro y queda persistida clave externa |
| A-132 | E05-H: Webhook validado e inbox | Webhook de MP sin firma válida no cambia pago; notificación auténtica se registra en inbox durable antes de confirmar recepción y duplicado no duplica efecto |
| A-133 | E05-I: Acreditación por verificación de proveedor | Solo order/pago consultado y correlacionado autoriza acreditar importe PEN correcto; evento atrasado no revierte acreditación verificada y discrepancia abre conciliación |
| A-134 | E05-J: Contraentrega atómica | Pedido, stock y cita se confirman juntos o se deshacen; pago sigue pendiente hasta efectivo neto registrado idempotentemente y auditado |
| A-135 | E05-K: Cancelación/reprogramación atómicas | Una sola cancelación de línea completa libera recursos/ajuste exactamente una vez; reprogramación asegura nuevo horario y solo luego libera anterior, conservando original ante fallo |
| A-136 | E05-L: Reembolso externo deduplicado | Administrador registra resultado externo total/parcial comprobado una sola vez por referencia/cobro; acumulado se valida bajo transacción y referencias ambiguas van a conciliación manual |
| A-137 | E05-M: Outbox local durable | Evento de negocio produce trabajo persistente recuperable para avisos, sin broker externo obligatorio ni fingir entrega exactly-once en correo/WhatsApp |
| A-138 | E05-N: Reconciliación y recuperación | Webhooks/consulta/WP-Cron y recuperación manual concilian intentos ambiguos; cron atrasado no compromete stock/cita ni duplica cobro, y el administrador dispone de acción de recuperación |
| A-139 | E06-A: Confirmación web sin correo obligatorio físico | Pedido solo físico con teléfono válido muestra confirmación y seguimiento privado; puede terminar sin correo, y cuando existe email recibe aviso transaccional sin duplicar pedido |
| A-140 | E06-B: Correo WordPress y diagnóstico | Correo transaccional usa wp_mail y remitente configurable por administrador, sin SMTP externo obligatorio; falla queda registrada y no se anuncia entrega probada |
| A-141 | E06-C: Sin marketing en V1 | No se envían campañas, carrito abandonado, publicidad o WhatsApp promocional por registrar teléfono/email durante compra |
| A-142 | E06-D: Eventos de comunicación basados en hechos | Los 26 eventos N6 se generan por hechos persistidos y destinatarios pertinentes, sin declarar cobro/entrega o reintegro no verificados ni disparar correo innecesario |
| A-143 | E06-E: Outbox y reenvío auditado | Una intención por evento/destinatario/canal/versión de plantilla, repetición idempotente y reenvío manual auditable sin duplicar hecho comercial |
| A-144 | E06-F: Transporte no equivale a recepción | wp_mail TRUE se clasifica como aceptado por transporte, no entregado al destinatario; error se visualiza y no revierte pedido/stock/pago |
| A-145 | E06-G: Bandeja interna priorizada | Administrador y operador visualizan solo avisos permitidos por rol en bandeja persistente, con incidentes críticos accesibles aunque email falle |
| A-146 | E06-H: Mensajes mínimos y enlaces seguros | Correo no contiene credenciales, tokens administrativos, adjuntos digitales o expediente del Libro; seguimiento usa enlace privado con permisos restringidos |
| A-147 | E06-I: Avisos de citas sin recordatorios extra | Cita confirmada/reprogramada/cancelada comunica hecho real, conserva constancia de cambio y excluye recordatorios automáticos adicionales V1 |
| A-148 | E06-J: Libro con constancia y alertas | Reclamación nativa muestra constancia y registro auditable; administrador ve plazos/alertas con cómputo legal validado, no se declara respuesta solo por envío SMTP |
| A-149 | E06-K: Contacto telefónico manual | Pedido solo físico sin email permite asistencia telefónica tras verificar identidad; no dispone de SMS/WhatsApp API ni atajos WhatsApp V1 |
| A-150 | E06-L: Plantillas versionadas es-PE | Plantillas integradas y versionadas en español de Perú con personalización administrativa limitada, sin editor libre ni desactivación de avisos/evidencias legalmente necesarios |
| A-151 | E07-A: Responsable y ANPD por instalación | El plugin no envía telemetría ni centraliza compradores; muestra checklist de responsable, encargados, banco de datos y trámites ANPD aplicables al comerciante sin registrarlo automáticamente |
| A-152 | E07-B: Datos mínimos por finalidad y rol | Cliente y operadores solo proporcionan/leen campos necesarios; visitante no recibe datos fiscales completos, ni operador datos ajenos, masivos, sensibles o del Libro |
| A-153 | E07-C: Seguimiento 256 bits/90 días | Secreto criptográfico de al menos 256 bits y verificador protegido ligado a un pedido, lectura solamente; vence a 90 días, rota/revoca con recuperación verificada sin perder derechos del pedido |
| A-154 | E07-D: OTP sensible por correo | Reemisión/cambio/devolución vía email exige OTP 8 dígitos, 10 minutos, máximo 5 intentos y controles antiabuso; checkout normal no solicita OTP; token de seguimiento no reemplaza verificación |
| A-155 | E07-E: Recuperación telefónica manual | Comprador físico sin email solicita enlace/cambio mediante verificación proporcional y registro auditado; teléfono, nombre o número de pedido por sí solos no prueban identidad |
| A-156 | E07-F: Descarga segura independiente | Token digital 256 bits separado de seguimiento, 24 h y derecho verificado; archivo no público, reemisión segura y expiración de URL sin destruir el derecho |
| A-157 | E07-G: Identidad WordPress de empleados | Panel usa sesión WordPress y capabilities por operación/recurso/campo en backend; nonce no da autenticación ni permiso y operador no accede a archivos/Libro ni exportación masiva |
| A-158 | E07-H: Privacidad del comercio y evidencia | Aviso por finalidad, responsable, contacto, encargados y tratamiento aplicable, con versión histórica por pedido; sin casilla de marketing obligatoria ni presumir consentimiento irrestricto |
| A-159 | E07-I: Inventario de proveedores y flujos | Por instalación se documentan terceros activos y posibles transferencias de hosting, pagos, email y mapas; sin telemetría central ni trackers por defecto |
| A-160 | E07-J: Mapa solo por decisión del comprador | No pedir geolocalización precisa de forma automática; mapa a elección informada y dirección manual siempre disponible con tarifa UBIGEO |
| A-161 | E07-K: Derechos ARCO con y sin email | Integrar exporter/eraser WP para pedidos con correo y expediente verificado para físicos solo teléfono; exportar datos propios, con retenciones justificadas sin borrar evidencia exigible |
| A-162 | E07-L: Retención y desinstalación | Conservación por categoría y base legal; desinstalar no elimina pedidos, cobros o Libro automáticamente, purga legal separada con permisos y respaldo; sin plazos arbitrarios |
| A-163 | E07-M: Privacidad de enlaces y antiabuso | Vistas y APIs privadas usan HTTPS, rate limits, respuestas no enumerativas, caché privada y protección contra filtración por referer, logs y terceros; no IDOR ni acceso por token ajeno |
| A-164 | E07-N: Incidentes y notificación legal | Incident log administrativo con evaluación condicionada de gravedad y obligación ANPD, alerta sobre plazo 48 h cuando proceda, evidencia y contención sin notificación automática a autoridades |
| A-165 | E08-A: REST versionado y autorización | Rutas peru-commerce/v1 usan método/schema y permission_callback, guardas de estado/recurso/campo; R8-18 es página WordPress y no endpoint REST; no se crean rutas públicas secretas |
| A-166 | E08-B: Angular embebido mismo origen | Distribución Angular va en ZIP WordPress, carga condicional, assets URL correcta en subdirectorio, catálogo PHP indexable y sin Node/segundo servidor productivo |
| A-167 | E08-C: Cotización autoridad backend | Carrito temporal no reserva ni envía precio autoritativo; POST /public/quotes y pulsar Pagar revalidan variantes, cantidad, entrega, turno, stock y total en céntimos |
| A-168 | E08-D: Checkout independiente e idempotente | Rutas MP y contraentrega separadas; misma operación/payload recupera resultado sin duplicar pedido/order/hold; cambio de payload produce conflicto |
| A-169 | E08-E: Canje seguro de seguimiento | Token opaco de fragmento URL pasa por POST HTTPS a cookie HttpOnly/Secure/SameSite de pedido propio solo lectura, sin token en GET inicial/caché/terceros y sin facultad de mutación |
| A-170 | E08-F: Permiso WordPress por recurso/campo | Empleados usan sesión WordPress y X-WP-Nonce CSRF; capability/recurso/campo se comprueban en servidor, aunque UI o nonce permitan solicitar endpoint |
| A-171 | E08-G: OTP sensible con alcance y teléfono manual | Acciones sensibles del cliente con email validan OTP D-138 de 8 dígitos/10 min/5 intentos para propósito concreto; físicos solo teléfono mediante revisión manual; sin OTP en checkout |
| A-172 | E08-H: Orders API con credencial comercial privada | Backend PHP usa HTTP WordPress y credencial MP del comercio por instalación; no exponer secretos a Angular ni cambiar a Preferences/Bricks/SDK por cuenta propia |
| A-173 | E08-I: Payer condicional sujeto a verificación | Sin email en físico puro se omite objeto payer y no se inventa correo; solo habilitar MP tras verificar que cuenta/medio peruano realmente admite ese caso |
| A-174 | E08-J: Cargo logístico no inventariable | Items MP suman total_amount exacto PEN, con Entrega como cargo no inventariable mostrado al comprador; aceptación de payload MP debe validarse antes de habilitar |
| A-175 | E08-K: Vigencia MP independiente del hold | PT15M solo si MP lo admite en país/medio; vencimiento externo no revive ni prolonga hold local de 15 min, y pago tardío entra a incidencia sin sobreventa |
| A-176 | E08-L: Webhook firmado e inbox durable | Webhook Orders verifica HMAC y correlación, guarda inbox antes de acuse y consulta fuente MP; duplicado no acredita dos veces ni retorno visual confirma pago |
| A-177 | E08-M: Errores REST coherentes | Errores usan pc_* con HTTP correcto y datos seguros sin stack/secretos, conflictos de cotización/estado/idempotencia y 202 para operación incierta, nunca 200 por denegación |
| A-178 | E08-N: Permisos administrativos y versiones | R8-19…R8-52 y capabilities perucommerce_* respetan D-64/D-82/D-84, recurso/campo/expected_version y auditoría; operador no corrige efectivo ni registra refund |
| A-179 | E08-O: Descarga digital privada o inhabilitada | Archivo no servido directamente por webroot, streaming PHP autoriza derecho/token y no-store; si hosting no garantiza almacenamiento privado se deshabilita la venta digital insegura |
| A-180 | E08-P: Libro y ARCO segregados | Reclamación pública sin cuenta con constancia legal pendiente P0-25; gestión admin y WordPress Privacy/email o verificación telefónica sin exponer expedientes |
| A-181 | E08-Q: Idempotencia y caché delimitada | API con operación/inbox/outbox y recuperación, paginación y antiabuso; solo caché pública, diagnóstico administrativo sin pagar/probar por defecto ni filtrar datos |
| A-182 | E08-R: Formalización OpenAPI antes de código | Generar matrices completas R8↔PER, OpenAPI/DTO versionados, permisos, estados, idempotencia, errores, auditoría y criterios antes de autorizar implementación; I8-X son documentales |

Estos escenarios no sustituyen pruebas ejecutadas. Cada uno exige luego prerequisitos, datos, pasos y resultado exacto, sin ambigüedad.

## Condiciones de «producto funcional»

No basta con que un ZIP se instale ni con que el frontend coincida con Figma. El producto deberá cubrir caminos felices, transiciones excepcionales, fallos de proveedores, seguridad, obligaciones peruanas, uso en móvil y escritorio, actualización y recuperación. La validación deberá distinguir **revisión estática**, **compilación**, **pruebas**, **integración de proveedores**, **publicación** y **funcionamiento real**.

No se ha ejecutado ninguna de esas etapas. Esta fase solo documenta.

## Nuevos criterios de aceptación **documentales** para el contrato 0.8.0-draft (sin ejecución)

| Ref | Escenario que deberá comprobarse con software autorizado | Resultado contractual |
| --- | --- | --- |
| A-183 | A reabre pedido un día después y consulta case_id de cambio de dirección | Recibe solicitud original, nuevo UBIGEO/dirección y página de eventos bajo permiso; O no recibe case_detail y no hay IDOR |
| A-184 | Política del comercio cambia después de confirmar pedido | R8-09/21 conservan textos y versiones originales, no settings vigentes; elegibilidad considera ese contrato y ley |
| A-185 | Comercio no configura cuota de descargas | `digital_download_limit=null` e historial de derechos sin límite, aunque el token de cada enlace venza en 24h |
| A-186 | Comercio configura nueva cuota y ya vendió digitales | Nuevos derechos obtienen límite; derechos antiguos mantienen `optional_limit` y `download_count` original |
| A-187 | Dos descargas concurrentes del último uso permitido | Bajo lock solo una transferencia autorizada; ninguna ruta pública altera contador ni archivo |
| A-188 | A desactiva o cambia proveedor de tiles | R8-45/46 CAS; R8-01 solo origen HTTPS/atribución válido; delivery por distrito/dirección manual sigue |
| A-189 | El cliente no abre mapa | Ninguna solicitud a servidor externo de tiles, aunque URL esté presente en bootstrap |
| A-190 | R8-12 creó expediente y perdió respuesta tras consumir grant | Mismo contexto/clave/payload devuelve mismo case_id sin segundo caso ni grant, sin ejecutar ajuste |
| A-191 | R8-12 reenvía la clave con contenido distinto o sesión ajena | 409 o denegación no enumerativa, sin revelar expediente ni aceptar modificación |
| A-192 | Solicitud invitada registra primer evento de caso | `actor_kind=guest` y `actor_user_id=NULL`, sin crear usuario WordPress ficticio; event log recuperable solo por A |

Estos son criterios para futura verificación **P-C**, no nuevas decisiones D ni pruebas corridas. Las reglas originales A-01…A-182 mantienen valor como criterios históricos de aceptación, **pero su fuente REST actual es OpenAPI 0.12.0-draft**; el contrato 0.8.0-draft fue una fotografía previa. No autoriza código, compilación, instalación ni release.

## Estado contractual posterior D-199 / DOC-QA30 — 18-11-2026

La puerta documental P-A permanece cerrada **en decisiones de diseño**, no en pruebas de funcionamiento. `docs/42` documenta R8-69: el administrador A puede abrir un caso físico de reducción parcial solicitado por comprador atendido manualmente, con verificación real de control del teléfono registrado y segundo dato privado del pedido, sin exigir email de compra física ni utilizar un grant de invitado ajeno. La apertura de caso no aprueba/ejecuta ajuste, y R8-26 conserva permisos, versiones y transacción separados. OpenAPI `0.11.0-draft` con **68 REST, 65 rutas, 136 DTO**, 39 tablas y 56 PER. El primer módulo de código puede solicitarse mediante P-B explícita; MP/Hostinger/INEI/legal/ZIP/pruebas siguen etapas P-C/P-D sin evidencia ejecutada.

## Criterios complementarios de diseño UX para Codex — 2026-11-18 (sin código ni pruebas)

**Alcance exclusivamente de evaluación y handoff:** al implementar una interfaz, aplicar los **18 principios de interacción pertinentes** descritos en [docs/10](10-ESTANDARES-UX-MOVIL-Y-ACCESIBILIDAD.md) y conservar el [contrato de Figma de 34 frames](11-CONTRATO-FIGMA-Y-DISENO-DE-COMPONENTES.md). No se crean nuevas decisiones D, servicios, rutas REST, tablas, dependencias o variantes Figma obligatorias. Los objetivos **WCAG 2.2 AA (D-16)** y **áreas táctiles independientes ≥ 44 × 44 px CSS (D-17)** son requisitos CONFIRMADOS; las demás heurísticas son guías de diseño y criterios de revisión **propuestos**, no leyes rígidas.

| Ref | Escenario de aceptación UX propuesto (P-C, cuando se autoricen pruebas) | Resultado verificable esperado |
| --- | --- | --- |
| A-193 | Se implementa ficha de producto/catálogo móvil y escritorio | Acciones primarias reconocibles y alcanzables; variante, precio y CTA agrupados por significado; catálogo público renderizado PHP, sin dependencia de Angular para su descubrimiento |
| A-194 | Comprador físico puro abre checkout, luego se compara con carrito digital/servicio/mixto | Campos y medios de entrega/pago cambian por elegibilidad; físico puro no exige correo; fecha, ubicación y datos legales no aplicables no se piden; el total permanece accesible |
| A-195 | Comprador pega teléfono/dirección con espacios/formato habitual; intenta UBIGEO erróneo, importe ambiguo o dato de seguridad inválido | Normalización inocua y reversible en campos razonables, sin pérdida de ceros significativos; servidor rechaza identidades, importes, tokens, fecha/UBIGEO o idempotencia inválidos sin aceptar por exceso de tolerancia |
| A-196 | Usuario confirma checkout, cambia método o regresa desde Mercado Pago con resultado pendiente | Feedback perceptible, estado de proceso y resultado autoritativo distintos; un doble clic/reintento no crea segundo pedido/cobro ni muestra éxito no confirmado |
| A-197 | Se revisa jerarquía de precio, artículo, entrega, cita y CTA en catálogo/checkout y gestión de pedido | Agrupaciones/espacios/continuidad Gestalt y estética legible sin ocultar total, condiciones o información obligatoria; no depender solo de color ni declarar usabilidad por apariencia |
| A-198 | Recotización 409, turno agotado, OTP fallido, caída de red o enlace protegido caducado | Error cercano al problema, causa comprensible y siguiente acción legítima; conservar campos/selecciones válidos cuando sea seguro, permitir recarga/reintento sin duplicar mutaciones ni exponer secretos |
| A-199 | Comprador vuelve, corrige dirección/cantidad o cierra diálogo antes de confirmar | Mantiene contexto y datos seguros, foco retornado y cambio permitido; no presenta cancelaciones, reembolsos o entregas consumadas como reversibles por simple botón |
| A-200 | Operador y administrador trabajan con pedidos y expedientes repetitivos | Búsqueda, filtros y navegación de teclado cuando aporten eficiencia; mismos nombres/estados en ambas vistas; O no puede consultar casos, movimientos financieros ni facultades solo A |
| A-201 | Uso móvil con teclado abierto, zoom/texto ampliado y acceso sin ratón/arrastre | Control táctil independiente ≥ 44 × 44 px CSS y objetivo WCAG 2.2 AA aplicables, reflow, foco visible, lector de pantalla y mensajes de error accesibles; ninguna CTA legal o compra queda tapada |
| A-202 | Codex entrega una pantalla comparada con Figma y docs/10 | Handoff registra frame/version, tarea, heurísticas realmente pertinentes, criterio UX, estado/error y evidencia **según etapa**; un frame estático no se etiqueta «probado» ni se exige cumplir por cuota los 18 principios |

**Interpretación:** Fitts/Hick/Jakob/Tesler/Postel/Doherty, Gestalt, estética, divulgación progresiva, reconocimiento, prevención, carga cognitiva, visibilidad, consistencia, control, eficiencia, accesibilidad y recuperación **no autorizan simplificar reglas de pago/stock/agenda, reclamar consentimiento, omitir datos normativos ni saltar autorización del servidor**. No imponer «máximo tres clics», 400 ms garantizados, «sin desplazamiento» o éxito de conversión inventado.

**Estado de evidencia:** aquí se agregaron **criterios documentales de aceptación**; no se ha implementado interfaz, auditado visualmente Figma en esta tarea, generado capturas de software, medido Core Web Vitals, probado accesibilidad ni autorizado P-C/P-D. Los nuevos criterios A-193…A-202 no reemplazan A-01…A-192 ni reabren P-A o el alcance comercial.
