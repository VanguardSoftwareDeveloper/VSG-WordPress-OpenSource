# Arquitectura preliminar — PeruCommerce

**Estado:** arquitectura híbrida, estrategia de persistencia, mapas y criterios de moneda/permisos/compatibilidad CONFIRMADOS (D-42…D-48). Contratos físicos, fiscales y versiones concretas todavía PENDIENTES; no autoriza implementación.

## Restricciones confirmadas

- Plugin propio e independiente de WooCommerce; WordPress como sistema anfitrión.
- Un comercio y local principal por instalación.
- Catálogo mediante bloques y shortcodes; checkout dedicado; panel propio dentro de wp-admin.
- Interfaz móvil y escritorio concebidas por separado en Figma.
- Productos físicos/digitales/servicios **presenciales en el local**, carrito mixto de hasta **una cita por pedido**, una sola agenda/recurso y reservas de stock.
- ZIP manual en V1; datos y contratos deben sobrevivir a una actualización.

## Separación de responsabilidades (dirección confirmada; detalles aún propuestos)

| Capa | Responsabilidad | No debe hacer |
| --- | --- | --- |
| WordPress | Activación, permisos, usuarios administrativos, enrutamiento, medios, integración visual en contenido | Decidir cobros por lógica del navegador |
| PHP del plugin | Validación, precios, impuestos, stock, agenda, pedidos, autorización, persistencia, integraciones | Depender de sesiones efímeras de frontend para garantizar disponibilidad |
| API REST versionada | Contratos entre frontend y backend, errores, filtrado de exposición y permisos | Exponer credenciales o datos de terceros innecesarios |
| Renderizado público | Catálogo indexable y enlaces estables | Requerir Angular para mostrar contenido indexable, salvo decisión justificada |
| Frontend interactivo | Carrito, checkout, mapa, calendario y panel de comerciante | Ser fuente autoritativa de precio, stock, reserva o pago |
| Almacenamiento | Catálogo, variante, pedido, líneas, reservas, registros de pago y auditoría | Guardar secretos de forma recuperable desde el cliente |
| Adaptadores | Mercado Pago, proveedor cartográfico, mailer y sistemas externos | Propagar estados externos directamente sin validación |

**CONFIRMADO (D-42):** WordPress/PHP renderiza catálogo público indexable, incluidos bloques y shortcodes; Angular se utiliza para carrito, checkout y panel interactivo. PHP conserva el cálculo/validación autoritativos de operaciones. No se desarrollarán dos aplicaciones separadas de móvil/escritorio: son dos disposiciones visuales con los mismos contratos.

**PENDIENTE:** versión estable compatible de Angular, montaje (elementos personalizados u otra integración justificada), API REST y estados, aislamiento de estilos frente a temas WordPress, carga condicional, presupuesto de JavaScript, SEO y accesibilidad. El ZIP de distribución deberá incluir assets listos para ejecutarse sin instalar Node en el hosting, como restricción de empaquetado por especificar. No imponer `@angular/elements` ni Angular Material hasta comparar sus implicaciones.

Documentación oficial para orientar la decisión: https://angular.dev/guide/elements y https://developer.wordpress.org/block-editor/getting-started/fundamentals/static-dynamic-rendering/.

## WordPress REST, Angular y Mercado Pago — E-08 APROBADO EN POLÍTICA (D-149…D-166)

El contrato `21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` fija como política **E08-A…R**: API `peru-commerce/v1`, Angular embebido en ZIP/same-origin con carga condicional, PHP autoritativo y checkout MP/COD separado. Seguimiento privado se canjea por sesión de solo lectura; empleados usan sesión WordPress/nonce y capacidades por operación/recurso/campo. Webhooks Orders se autentican y concilian, y archivos digitales se sirven solo por autorización. **No se ha creado código ni esquema físico**.

**Pendientes P0-18/P0-19/P0-20/E08-R:** ER/SQL/índices, versión/build/manifest y OpenAPI/DTO por cada R8/PER. **MP bloqueado hasta verificación autorizada** de `payer` ausente en físicos, ítem Entrega y `expiration_time=PT15M` en cuenta peruana. Ninguna ruta/estado/error se considera implementado, y siguen P0-25/J-01…J-05.

## Diseño modular — propuesta

Núcleo: configuración del negocio, productos/variantes, carrito, pedidos, pagos/estados, permisos, eventos/registro, reglas de checkout. Módulos acoplados por contratos internos definidos, no por acceso indiscriminado a tablas.

Verticales: inventario físico; delivery/recojo; descargas digitales; agendas y reservas de servicios; Mercado Pago; contraentrega; Libro de Reclamaciones; datos para comprobantes; comunicaciones; mapas. Activar/desactivar no borra historial ni debilita controles legales.

**Principio:** modularidad lógica en un plugin, no una arquitectura de microservicios ni una red de plugins dependientes sin necesidad.

**Restricciones de dominio CONFIRMADAS (D-18…D-26):** una agenda y recurso para servicios en local; solo una cita por pedido; Mercado Pago para todos, efectivo solo si el importe del pedido completo puede cobrarse presencialmente; cambios de cita gestionados por comerciante; delivery por distrito UBIGEO y tarifa; correo obligatorio con digitales/servicios, teléfono obligatorio con físicos; dos perfiles administrativos (administrador/operador de permisos limitados); registro externo de documentos tributarios permitidos por el supuesto. Estas restricciones no resuelven por sí solas transacciones, calendarios, autorización granular ni reembolsos.

## Contratos adicionales confirmados (D-27…D-35)

- **Retenciones:** 15 min desde inicio del intento de pago, sin renovación; validación de vencimiento en backend, no solo en WP-Cron.
- **Pago tardío sin recursos:** incidencia, sin inventar existencias ni horarios; resolución manual por comerciante (reprogramación/reintegro).
- **Agenda:** único recurso en el local, horario semanal, duración por servicio, pausas/buffers y fechas bloqueadas.
- **Cancelación/reprogramación:** intervención manual auditada; reembolso separado de la cancelación.
- **Inventario:** stock independiente por variante cuando se habilite.
- **Digital:** enlaces de acceso temporales protegidos (24 h según D-40), sin cuota de descargas por defecto y con límites configurables.
- **Carrito mixto:** un cobro por pedido; efectivo solo si todo se cobra en un mismo acto presencial.
- **Invitados:** enlace privado no adivinable mostrado tras finalizar pedido, enviado por correo cuando exista; posible entrega manual por comerciante después de verificar al comprador.
- **UX:** el comprador elige fecha/hora en ficha antes de agregar el servicio al carrito; revalidar en checkout. Elegir un turno en ficha **no lo reserva**; la retención comienza al iniciar intento de pago.

Ya están definidos el disparador funcional del intento («Pagar» y creación válida en servidor), el compromiso al confirmar contraentrega y la vigencia de 24 horas del enlace digital. Quedan pendientes límites transaccionales, reintentos, reemisión segura, momento de cobro contraentrega, compensaciones y permisos. Consultar `09-DECISIONES-PENDIENTES.md`.

## Contratos confirmados adicionales (D-36…D-41)

- El evento funcional que inicia la reserva de 15 minutos es pulsar **«Pagar»** y **crear un intento válido en el servidor**; si falla la creación, no afirmar que hay retención. El diseño transaccional, idempotencia y errores siguen pendientes.
- En **contraentrega** no dejar la orden indefinidamente en retención: al confirmar el pedido válido, comprometer el stock y la cita, manteniendo el **pago pendiente** hasta acreditación manual.
- Un recurso/agenda atiende **un cliente por franja**; duración real y buffers deben excluir superposiciones, incluso entre servicios de distinta duración.
- Cada comercio configura su **política de cancelación**, condicionada por derechos legales aplicables. Ninguna regla privada suprime obligaciones normativas; devolución sigue siendo operación separada.
- **Cada URL individual de descarga es válida 24 horas** y admite reemisión al comprador autorizado. El derecho de descarga no se extingue automáticamente al vencer el token; límites por descarga configurables, sin cuota por defecto.
- V1 admite **precio regular y precio de oferta simple**. No construir motor de cupones/promociones automáticas sin autorización nueva.

## Contrato monetario de dominio: E-04 APROBADO EN POLÍTICA (D-96…D-108)

- **Fuente APROBADA:** `17-CONTRATO-MONETARIO-COTIZACIONES-AJUSTES-Y-REEMBOLSOS-V1.md`, D-96…D-108. Importes comerciales en enteros de céntimos PEN; cotización revalidada en servidor, snapshots congelados al intento válido, un delivery por pedido y libro inmutable de cobros/ajustes/reintegros externos verificados. Representación SQL concreta, índices y límites de almacenamiento siguen pendientes P0-18/E-05.
- Separar **total pactado**, **importe acreditado bruto**, **comisiones/liquidación neta del proveedor**, **ajustes comerciales** y **reintegros externos verificados**; no actualizar pedido original al corregir, cancelar o variar dirección. D-59/D-54/D-66 son invariantes ya aprobados.
- Un cambio de dirección con tarifa/cobertura distinta o dinero acreditado requiere administrador D-83. **D-106/E04-K confirmado:** si sube tarifa tras pago, no generar cargo adicional automático en el mismo pedido; resolución administrativa con nueva dirección compatible o pedido nuevo cuando sea lícito/viable. Se preservan precios originales.
- **PENDIENTE:** esquema físico/migraciones/índices P0-18, límites de entero y validación de almacenamiento, redondeo fiscal SUNAT J-03, implementación idempotente conforme E-05 aprobado, proveedor/API E-08 y evidencia de reintegros E-07. No representar cobro como un booleano.

## Elegibilidad en backend y límites de E-03 (política aprobada D-86…D-95)

Para cualquier intento de cancelación, devolución, reprogramación o revocación, el backend debe distinguir **cancelación ordinaria de línea no cumplida**, **expediente común tipado de devolución/garantía/reclamación**, **movimiento de dinero externo verificado** y **documento tributario externo**. El contrato funcional E-03 está **APROBADO como D-86…D-95** en `16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md`. Un físico PREPARANDO/LISTO solo es cancelable si procede; no restock vendible al solicitar devolución; digital con o sin descarga conserva derechos y requiere cauce adecuado; revocaciones y no-show nunca automáticos; cancelar cantidad parcial usa expediente/ajuste. No crear nuevos enums de pedido ni automotor de reintegros.

La separación de responsabilidades exige revalidar estado/permiso en backend y permitir reclamaciones aunque una cancelación ordinaria se deniegue. **E-06 aprobado D-123…D-134** no acredita recepción ni conformidad del Libro. Pendientes J-01/J-02/J-03/P0-17/P0-25, SQL/hosting P0-18/P0-19, privacidad E-07, REST/proveedor E-08 y verificaciones autorizadas.

## E-02 — política de autorización aprobada (D-78…D-85)

- Backend PHP/WordPress verifica actor, permiso, recurso, estado E-01 y condición financiera D-64; la UI Angular **no** concede permisos por ocultar/mostrar controles.
- Operador gestiona catálogo/precios/ofertas, stock, agenda, preparación, entrega y reprogramación bajo auditoría y acceso **mínimo** a información de pedidos/contactos. No dispone de credenciales, datos fiscales completos por defecto, exportación masiva, archivos digitales protegidos, parámetros fiscales, políticas o expedientes completos de reclamación.
- Administrador gestiona integraciones, fiscalidad, Libro de Reclamaciones, políticas, roles, exportaciones y ficheros protegidos sujetos a requisitos de privacidad; corrige cobros y registra reembolsos externos verificados.
- Cambio de dirección: operador propone y administra el trámite; solo administrador aprueba si cambia costo, cobertura o existe dinero acreditado. Pedido confirmado conserva snapshot original.
- Invitado con enlace privado solo consulta el propio pedido; operaciones sensibles requieren verificación de identidad adicional E-07.
- **Pendientes de implementación:** nombres de capabilities, filtros de datos por endpoint, protección CSRF/sesión, alcance de usuarios, registro de auditoría, privacidad E-07 y REST E-08. Ver `15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md`.

## Tratamiento aprobado de escenarios límite (D-61…D-66)

- **Digital con derecho habilitado y sin descarga:** mantener hechos de autorización y de descarga separados; bloqueo de cancelación/reintegro automático, evaluación por administrador conforme a términos y derechos aplicables. No establecer estados de cumplimiento por ausencia de evento de descarga.
- **Físico ya despachado:** apertura de incidencia logística y revisión antes de resolver; no transición directa de «en ruta» a «cancelado pendiente».
- **Última línea física cancelada antes de despacho:** evaluar costo de delivery efectivamente prestado e impuestos según contrato/legalidad, no retener ni devolver una tarifa fija por defecto; autorizar ajuste de pedido pagado por administrador.
- **Operador:** capacidad de cancelación solo si **no existe dinero acreditado**, ni deuda de devolución o ajuste financiero, además de elegibilidad y autorización (D-64). En caso de incertidumbre, exigir revisión administrativa, no clasificar automáticamente como «sin impacto».
- **Dirección confirmada:** preservar versión/snapshot original y añadir cambio vigente auditable; validar nueva cobertura UBIGEO y tarifa, factibilidad logística e impacto financiero antes de aplicar. No sobrescribir dirección del documento original.
- **Reembolso externo parcial:** registrar monto PEN, referencia y confirmación verificable por administrador y vincular al pago original y líneas involucradas, sin mutarlo ni ejecutar reintegro API. Política de acumulados/conciliación pendiente.

**E-01 APROBADO:** máquinas O/MP/H/F/G/V (doc. 14). **E-02 APROBADO en política funcional:** restricciones por actor D-78…D-85 (doc. 15). **PENDIENTES:** capabilities técnicas y datos por campo, clasificación contable de delivery, conciliación de parciales, legalidad, atomicidad y REST. No fijar esquema físico ni API desde esta sección.

## Restricciones aprobadas para cancelaciones y pedidos confirmados (D-58…D-60)

- La **cancelación ordinaria por línea** únicamente opera sobre cumplimiento pendiente y cuando resulte elegible; las líneas entregadas/prestadas se resuelven mediante otro flujo (devolución, reclamación o incidencia) con garantías legales aplicables.
- Un **pedido confirmado es inmutable en su representación original**: persistir snapshots y referencias de compra sin edición destructiva. Las rectificaciones son eventos/ajustes relacionados, con identificadores estables, actor, instante, motivo y efectos, conservando trazabilidad e idempotencia.
- El backend determina si una cancelación de línea de **pedido pagado tiene impacto financiero**. Solo el administrador puede autorizarla. **Más restrictivo (D-64): el operador no puede cancelar con ningún dinero acreditado**, ni con reintegro/ajuste pendiente, aunque el ajuste estimado sea cero; requiere línea elegible, permiso y auditoría. Ni una UI oculta ni capacidad genérica de edición sustituyen comprobaciones del servidor.
- Las devoluciones de dinero siguen **externas a PeruCommerce** (D-54). Autorizar una cancelación no registra un reembolso realizado; el administrador registra después el resultado comprobado.
- **PENDIENTES:** categorías exactas de líneas pendientes, transiciones/estados, algoritmo de impacto financiero, reparto de tarifas, tasas y ajustes, reglas de devolución legal, atomicidad y compensaciones; sin inventar esquema físico ni endpoints.

## Contratos de estados, parcialidad y precios explícitos (D-55…D-57)

- **Tres dimensiones de estado separadas:** pedido administrativo; intentos, cobros y reembolsos de pago; cumplimiento de cada línea vendida. Un pedido puede estar pagado pero no entregado, parcialmente cumplido o con incidencia. No colapsar los tres ejes en un campo global.
- **Cancelación parcial de una línea:** afecta exclusivamente la línea **pendiente de cumplimiento** y elegible; artículos entregados/servicios consumados van a devolución, reclamación o incidencia (D-58). Conservar las restantes líneas y sus estados. Registrar motivo, actor, momento, ajuste y relación con el pedido sin alterar originales (D-59). Si el pedido está pagado y exige ajuste financiero, autoriza solo el administrador (D-60); devolución eventual sigue externa (D-54).
- **Precios explícitos:** producto simple tiene precio final y oferta manual propios; producto variable tiene valores propios explícitos por variante vendible. No generar ni heredar automáticamente el precio base de una variante faltante; cotizaciones y snapshots guardan el precio de la selección real.
- **PENDIENTES:** enumeración completa de estados/eventos, actor/autorización por transición, criterios de cancelación de línea ya entregada/descargada/prestada, ajustes de flete y tributos, atomicidad, reposición de inventario, liberación de agenda y compensaciones o reembolsos externos.

## Contratos confirmados para variantes y movimientos financieros (D-52…D-54)

- **Precios por variante:** cada combinación vendible debe usar precio final y oferta manual propios explícitos en PEN (D-52/D-57). Un producto sin variantes tiene sus importes propios; resolver precio desde el artículo/variante real y registrar snapshot, sin herencia implícita entre ambos.
- **Corrección de cobros:** permiso exclusivo del administrador. Conservar el evento original de cobro y registrar un ajuste/reversión auditables, enlazados al asiento fuente, sin eliminación ni edición destructiva. Diseño de saldo, idempotencia y reconciliación todavía pendiente.
- **Reembolsos:** V1 solo registra en servidor el resultado **verificado** de un reembolso efectuado mediante Mercado Pago u otro medio correspondiente, con actuación del administrador. No crear endpoint ni SDK para ejecutar reembolsos en Mercado Pago desde el plugin. Diferenciar solicitud/pending externa de resultado confirmado, conservar actor/fecha/importes/medio/referencia si aplica.
- **Seguridad:** el operador no puede corregir cobros, ejecutar reembolsos ni registrar sus resultados, tampoco llamando directamente a la API. No escribir credenciales ni datos financieros sensibles innecesarios en los logs.
- **PENDIENTE:** esquema de movimientos/estados, identificadores externos y conciliación de reembolsos **parciales externos ya admitidos (D-66)**, efectos sobre cumplimiento, descarga/stock, trazabilidad y discrepancias.

## Contratos de precio y registro de efectivo (D-49…D-51)

- El precio de catálogo/variante ingresado por el comerciante representa el **precio final en PEN**. El backend sigue siendo responsable de validar rangos, resolver la oferta activa, calcular totales/cargos y conservar snapshots por línea. No deducir automáticamente una estructura de impuestos universal desde ese precio.
- La oferta simple tiene un estado **manual** activado/desactivado por usuario autorizado; no se requiere planificación de fechas, cron de activación ni motor promocional en V1.
- El **operador autorizado** puede registrar cobro de efectivo recibido en pedidos elegibles, con comprobación de permisos, importe/moneda y estado del pedido, auditoría de actor/instante/monto y control contra acreditación duplicada. No puede ejecutar reembolsos ni leer/modificar secretos de integraciones.
- Detalles PENDIENTES: esquema monetario y redondeo, políticas tributarias por régimen, semántica de movimientos correctivos y registro de resultado externo, consistencia de saldos, efectos de cancelación y bitácora exacta. **La exclusividad de administrador para correcciones/reembolsos y conservación del original ya está CONFIRMADA.** Estas reglas no autorizan código.

## Restricciones confirmadas de moneda, fiscalidad, perfiles y versiones (D-45…D-48)

- Todos los productos, ofertas, tarifas, reservas de importe, pagos y pedidos de V1 utilizan **PEN exclusivamente**. La API/backend preservará la identidad de moneda y verificará los totales; el formato `S/` se utiliza en vistas de compra. Política numérica y de redondeo pendiente.
- **Precio final visible:** la cotización calculada por servidor debe informar importe final antes de confirmar, con costo de entrega/cargos aplicables cuando corresponda. No fijar mecánica fiscal sin matriz aprobada por régimen y operación; V1 no será motor tributario general ni emisor SUNAT.
- **Roles:** el operador solo accede a operaciones concedidas sobre pedidos, productos, stock y agenda. Credenciales, integraciones y configuración sensible son exclusivas del administrador. La matriz exacta de capacidades, permisos de reembolso/datos personales y auditoría siguen pendientes.
- **Compatibilidad:** usar componentes WordPress/PHP/Angular y librerías en versiones estables y mantenidas. No seleccionar mínimos ni versiones «compatibles» por intuición; registrar matriz real a partir de documentación oficial y requisitos de alojamiento, y verificar después mediante etapas autorizadas.

## Persistencia: evaluar antes de fijar tablas

**Dirección CONFIRMADA (D-43):** evaluar tipos de contenido, taxonomías y metadatos nativos de WordPress para el catálogo; diseñar **tablas propias** para pedidos, pagos y reservas, cuyas necesidades de concurrencia e integridad son distintas al contenido editorial. Considerar tablas para líneas, retenciones, descargas, reclamos y auditoría si el modelo y los requisitos reales las justifican. La selección final requiere comparar consultas, consistencia, índices, atomicidad, migraciones y backups. **No hay esquema físico aprobado**, ni se presupone que un CPT sea suficiente para todas las características de catálogo.

Los pedidos no deben depender de que el autor edite o elimine un producto en el catálogo: cada línea conserva **snapshot** de nombre, tipo, variante, cantidad, precio y condiciones relevantes de la compra. Precios y totales se calculan y persisten del lado servidor.

**PENDIENTE:** modelo lógico, claves/índices, estrategias de actualización, retiros/anonimización, auditoría, copias y restauración.

## E-07 — privacidad y límites de autorización APROBADOS (D-135…D-148)

El contrato `20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md` **APRUEBA E07-A…N** como D-135…D-148: identidad por pedido, token de seguimiento 256 bits con 90 días de vigencia inicial, OTP de 8 dígitos/10 min/5 intentos para acciones sensibles, recuperación telefónica manual, descargas independientes 24 h, control WordPress por recurso/campo, minimización, WordPress Privacy, mapa voluntario, conservación legal y alertas de incidentes. **Pendientes E-08/P0-18/P0-19:** endpoints, firma/almacenamiento de secretos, caché, controles y verificación real.

WordPress nonces protegen CSRF y **no son autenticación ni permiso**; la API de empleados requerirá sesión/capability/recurso, y los invitados no deben heredar acceso administrativo. No publicar descargas en Media Library, cachear páginas privadas por LiteSpeed ni exponer secretos de enlace a terceros, logs o emails ajenos. **Pendientes E-08/P0-18/P0-19/P0-21/P0-26:** rutas y permisos, protección real de archivos, almacenamiento y cabeceras, mapas, backups y pruebas. Registro/transferencias ANPD se evalúan por comerciante (J-04/J-05/P0-25).

## E-06 — política de canales y avisos APROBADA (D-123…D-134)

El contrato `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` **APRUEBA E06-A…L**, con 26 eventos N6, wp_mail, bandeja administrativa, outbox, correo transaccional cuando existe, avisos de citas y Libro, plantillas versionadas, sin marketing/WhatsApp/SMS. **D-121/D-127** exigen outbox durable e idempotencia, pero quedan pendientes su modelo físico P0-18, campos y acceso E-07, proveedor/transporte E-08 y correo real P0-19.

No activar nuevo estado de pago/entrega desde una notificación. El resultado TRUE de wp_mail no demuestra entrega real. Los envíos diferidos pueden atrasarse sin tráfico WP-Cron; nunca condicionar integridad de stock/dinero a correos. Nombres de tablas, envío real, seguridad/token, plazos, persistencia y API siguen P0-18/P0-19/P0-25/E-07/E-08.

## E-05: arquitectura de transacciones y recuperación APROBADA COMO POLÍTICA (D-109…D-122)

**Fuente APROBADA EN POLÍTICA TÉCNICA:** `18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md`, opciones E05-A…N registradas D-109…D-122. Contiene 48 casos documentales **no ejecutados**. Las reglas funcionales y el tipo de coordinación transaccional están elegidos, pero todavía no se ha autorizado ni especificado totalmente SQL físico, índices, migrador, motor probado, jobs ni integración MP real.

- Candidato de persistencia: operaciones SQL críticas sobre tablas propias transaccionales InnoDB, comprobando motor/índices/versiones/privilegios del hosting; prohibido asumir que una lectura normal protege la última unidad/cita. La agenda del día vacío exige recurso de lock estable.
- Crear orden MP no puede ser atómico con SQL local. Propuesta de admisión interna breve y correlación durable, llamada HTTP fuera de transacción, y **hold comercial de 15 minutos solo después del intento válido en servidor**, sin mostrar checkout si no hay hold. Un fallo ambiguo se reconcilia sin otro cobro.
- WP-Cron no tiene puntualidad garantizada: los holds deben caducar por hora de servidor al calcular disponibilidad. Webhooks deben autenticar firma, persistir evento y consultar el proveedor antes de acreditar.
- Contraentrega compromete pedido/stock/cita atómicamente, con dinero pendiente; registro posterior del efectivo neto, cancelación, restock, reprogramación y reintegro externo verificado exigen idempotencia e historial, sin doble efecto.
- **Pendientes:** ER/SQL/migraciones P0-18, hosting/servicio de correo P0-19, proveedor E-08, datos E-07, constancias/plazos legales P0-25 y ZIP P0-26. **E-05 y E-06 aprobados en política no implican verificación de ejecución.**

Fuentes oficiales: https://developer.wordpress.org/plugins/cron/ · https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html · https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-orders/notifications

## Concurrencia: invariantes confirmados y contrato técnico E-05 aprobado

- Confirmar una reserva de stock/cupo con operación atómica de disponibilidad; nunca únicamente lectura seguida de escritura separada sin protección.
- Usar identificador de operación e idempotencia para creación de pedido e intentos de pago.
- Diferenciar cotización, reserva temporal y compromiso definitivo.
- Revalidar disponibilidad y precio al confirmar compra y en operaciones tardías.
- Mantener pagos aprobados pero imposibles de cumplir en un estado excepcional que requiera conciliación/reembolso; no prometer stock inexistente.
- El vencimiento de reservas puede ejecutarse de forma diferida, pero todas las validaciones deben tratar los vencimientos efectivos aun si no corrió un trabajo programado.
- Separar estado de pago del estado de cada línea de cumplimiento/entrega.

**E-05 CERRADO EN POLÍTICA TÉCNICA (D-109…D-122):** InnoDB, locks por variante/recurso-día, admisión breve antes de orden MP, HTTP fuera de transacción SQL, idempotencia y recuperación quedan fijados en doc.18. **PENDIENTE:** SQL/índices/aislamiento físico P0-18, host real P0-19, parámetros exactos de orden MP E-08, migración/rollback P0-26 y pruebas autorizadas. Duración del hold, no renovación y compromiso contraentrega siguen confirmados.

## API REST y autorización

Contrato propuesto: namespace propio versionado; lecturas públicas de catálogo publicable sin datos privados; mutaciones de compra anónima con validación, límites y antifraude; operaciones de comerciante con autenticación/capacidades específicas. Acceso de invitado a pedidos mediante enlace privado no adivinable y acceso a descargas mediante enlace temporal protegido de 24 h, sin enumerabilidad; caducidad del enlace de seguimiento, revocación, verificación de identidad y protocolo de reemisión segura por definir.

Todo endpoint debe definir entrada/salida, visibilidad, capacidad requerida, errores y efectos secundarios. Usar autorización explícita, HTTPS para pagos y datos personales, validación/escape, protección contra abuso y manejo seguro de logs.

Referencia WordPress: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/

## Despliegue y compatibilidad

- Distribución V1: ZIP manual; no actualizar al destruir o reinstalar los datos; migraciones con versionado, rollback/recuperación por definir.
- Compatibilidad orientada a **versiones estables/mantenidas (D-48)** en un hosting WordPress convencional; **versiones mínimas exactas, extensiones PHP, soporte de base de datos y matriz de validación aún no verificadas**. No prometer soporte de instalaciones antiguas sin mantenimiento.
- No exigir servicio en nube propio para operar; proveedores externos solo en funcionalidades activadas.
- Tratar los procesos programados de WordPress como no estrictamente puntuales; el backend siempre valida la expiración real.
- No habilitar rastreo ni telemetría externa por defecto.

## Riesgos de arquitectura que bloquean implementación

1. Método de implementación y protección de la reserva concurrente.
2. Elección de representación de catálogo y entidad de pedidos.
3. Fuente de verdad del calendario y zonas de tiempo.
4. Resolver el punto/momento de cobro presencial de todos los artículos de un carrito mixto elegible, así como bloqueo digital y compensación, respetando D-21.
5. Matriz fina de capacidades del operador y administrador (D-47), acceso del invitado, límites a datos personales, cancelación parcial por línea (D-56) y funciones de auditoría.
6. Fijar versiones mínimas estables/mantenidas con compatibilidad oficial demostrable, presupuesto y gestión de dependencias/licencias.
7. Implementación de URL públicas indexables en WordPress, montaje y assets de Angular sin incluirlo en páginas que no lo necesitan.
8. Contrato del proveedor cartográfico, clave/costo/atribución, privacidad y comportamiento manual sin mapa; visor Leaflet confirmado (D-44).

Consultar documentos 09/12/13 y contratos **E-01…E-07 APROBADOS en política (docs.14…20, D-67…D-148)**. Pendientes capabilities/API E-08, matriz jurídica J-01…J-05/P0-17/P0-25, esquema SQL/hosting/migraciones/ZIP y pruebas futuras autorizadas.
