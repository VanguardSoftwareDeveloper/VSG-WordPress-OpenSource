# Matriz de huecos, escenarios y preparación para Codex — PeruCommerce V1

**Estado:** DOCUMENTO DE CONTROL EN FASE 0, sin aprobación para implementar. Contiene escenarios derivados de decisiones confirmadas y pendientes que no pueden resolver ni Codex ni Figma por su cuenta. No equivale a pruebas ejecutadas, auditoría legal, compatibilidad real ni funcionamiento en producción.
**Fuente:** [01-DECISIONES-Y-ESTADO.md](01-DECISIONES-Y-ESTADO.md), [09-DECISIONES-PENDIENTES.md](09-DECISIONES-PENDIENTES.md), [12-CONTRATO-ESTADOS-TRANSICIONES-Y-PERMISOS.md](12-CONTRATO-ESTADOS-TRANSICIONES-Y-PERMISOS.md). Para E-01 aprobado, ver [14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md](14-MAQUINAS-DE-ESTADO-EXACTAS-V1.md) (D-67…D-77); **política E-02 aprobada** en [15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md](15-MATRIZ-PERMISOS-Y-AUTORIZACIONES-V1.md) (D-78…D-85). **E-03 está APROBADO EN POLÍTICA FUNCIONAL (D-86…D-95)** en [16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md](16-ELEGIBILIDAD-CANCELACIONES-DEVOLUCIONES-Y-SERVICIOS-V1.md). Su matriz jurídica J-01/J-02/P0-25 sigue sin validación completa; E-04…E-08 y capacidades técnicas continúan abiertos.

## 1. Qué significa «sin huecos» para la entrega a Codex

La meta es que el propietario decida el **qué** y la política comercial/legal, y que un contrato aprobado detalle el **comportamiento observable**. Codex elegirá decisiones de implementación internas proporcionadas al contrato y al permiso vigente, no cambios de negocio, impuestos, licencias, terceros o estados de pago.

Categorías de incertidumbre:
- **D — Decisión de producto:** requiere confirmación explícita; no deducir desde ejemplos o recomendaciones.
- **T — Diseño técnico:** requiere contrato aprobado con alternativas evaluadas y compatibilidad; no seleccionar dependencias/versiones sin fuente.
- **L — Legal/tributaria:** requiere fuente normativa actual, sujeto obligado y escenario concreto; no usar reglas fiscales universales.
- **E — Proveedor/infraestructura:** requiere política vigente, capacidad/costos, fallo/recuperación y autorización para probar.
- **UX — Presentación:** Figma y prototipos deben cubrir todo estado/permiso aprobados, no inventar procesos.

**Advertencia:** alta completitud documental reduce interpretación, pero no garantiza porcentaje fijo de código correcto. Validación de concurrencia, pagos, seguridad, prestaciones y hosting requiere etapas autorizadas posteriores.

## E-04 — política de pagos/cargos APROBADA (D-96…D-108), escenarios NO EJECUTADOS

E-04 monetario D-96…D-108, E-05 técnico D-109…D-122 y **E-06 comunicación D-123…D-134 están aprobados en política**. Los escenarios M4-X, C5-X y N6-X **no se han ejecutado**. Siguen abiertos J-03/P0-17/P0-25, SQL/hosting, E-07/E-08, licencias y datos.

## E-07 — privacidad, sesiones y protección de invitados APROBADA EN POLÍTICA (D-135…D-148)

El contrato [20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md](20-PRIVACIDAD-IDENTIDAD-ENLACES-Y-CONSERVACION-V1.md) tiene **14 opciones E07-A…N APROBADAS D-135…D-148** y **42 escenarios P7-X documentales NO ejecutados** sobre enlace de seguimiento, OTP sensible, recuperación telefónica, archivos digitales, roles/campos, ARCO, mapas, retención y gestión de incidentes. **J-04/J-05/P0-25 siguen abiertos** para banco de datos, responsables/encargados, flujos, plazos y cumplimiento; E-08/P0-18/P0-19 para controles efectivos.

## E-06 — política APROBADA D-123…D-134, 38 casos NO EJECUTADOS

El contrato `19-COMUNICACIONES-NOTIFICACIONES-Y-RECUPERACION-V1.md` tiene las 12 opciones E06-A…L **APROBADAS D-123…D-134** y criterios A-139…A-150. Los 26 eventos N6 y 38 casos N6-X siguen siendo documentos **sin ejecutar**. Persisten validación de entrega de correo P0-19/E-08, datos E-07 y cumplimiento de reclamos J-01/J-02/P0-25.

## E-05 — 14 políticas técnicas APROBADAS (D-109…D-122), 48 escenarios NO EJECUTADOS

El documento [18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md](18-CONCURRENCIA-TRANSACCIONES-IDEMPOTENCIA-Y-RECUPERACION-V1.md) contiene **14 políticas técnicas E05-A…N APROBADAS** como D-109…D-122, con 48 escenarios C5-X01…C5-X48 **NO ejecutados**. Abarca InnoDB/locks, stock/citas, orders MP, idempotencia, webhooks, expiración y recuperación. P0-18/P0-19/E-08 exigen esquema/host/API reales y pruebas autorizadas antes de declarar funcionamiento.

## E-08 — integración y 60 escenarios APROBADOS EN POLÍTICA (D-149…D-166)

El [contrato E-08](21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md) establece 18 decisiones E08-A…R y un inventario R8-01…R8-52 (una página WordPress no REST), con códigos `pc_*`, capacidades por recurso/campo y condiciones MP/archivos protegidos. Los **60 casos I8-X son documentales, NO pruebas ejecutadas**. La ratificación de E-08 no demuestra que haya controladores, cuentas, tablas o seguridad operativos.

**Persisten nueve P0** de fiscalidad, esquema SQL, hosting/versiones, Angular, mapas, permisos, licencias, cumplimiento legal y distribución ZIP, además de J-01…J-05. Es bloqueante formalizar R8↔PER↔OpenAPI/DTO, validar cuenta MP peruana/almacenamiento privado y contar con autorización expresa antes de implementar o probar.

## 2. Nueve áreas P0 todavía abiertas y entregables para cerrarlas

| P0 | Nivel | Entregable que falta | Evidencia previa al inicio de implementación |
| --- | --- | --- | --- |
| P0-17: precios | Crítico | **E-04 APROBADO D-96…D-108** para céntimos, cantidades, total comercial, envío, ajustes, reembolsos; **matriz fiscal J-03 abierta** | Matriz SUNAT por operación/régimen, redondeo fiscal, almacenamiento y validación de integración externa |
| P0-18: persistencia | Crítico | ER/SQL/índices/aislamiento/migración por definir; **E-05 política InnoDB/locks/idempotencia aprobada D-109…D-122**, doc.18 | Diseño físico y verificación de motor/host, atomicidad y restauración autorizadas |
| P0-19: compatibilidad | Alto | Versiones mínimas/objetivo mantenidas WordPress/PHP/DB/Angular, límites reales de hosting, dependencias | Referencias oficiales de requisitos y matriz de compatibilidad; pruebas **solo cuando se autoricen** |
| P0-20: Angular en WordPress | Alto | Puente UI/API, carga condicional, contratos REST, aislamiento CSS, empaquetado ZIP y SEO del catálogo | Contratos endpoints/error/autorización y presupuesto de carga del frontend |
| P0-21: mapas/UBIGEO | Alto | Proveedor de teselas, uso/licencias/costos, catálogo UBIGEO versionado, cobertura/dirección manual | Política del proveedor, reglas de cobertura y casos sin mapa ni geocodificación |
| P0-23: operaciones/permisos | Crítico | **E-01…E-07 APROBADOS en sus políticas D-67…D-148**, E-07 define identidad/secretos, no garantiza seguridad implementada | Capabilities/API E-08, J-04/J-05/P0-25, datos por endpoint, SQL/hosting, evidencias de seguridad |
| P0-24: licencia/distribución | Crítico | Licencia del ZIP, créditos, obligaciones WordPress/dependencias y entrega con código privado | Revisión de licencias y decisión del propietario; no crear LICENSE por inferencia |
| P0-25: cumplimiento Perú | Crítico | DL 1729/2026 y remedios consumidores revisados en docs.07/16; **E-03 política funcional APROBADA D-86…D-95**, sin cierre normativo | Norma/artículo/sujeto/sector/pantalla/acción, J-01/J-02, verificación de reglamento definitivo y SUNAT, validación por obligación |
| P0-26: ciclo ZIP/datos | Alto | Instalación, migración idempotente, ZIP de actualización manual, rollback, desinstalación y retención | Flujos documentados con recuperación de datos y compatibilidad |

**Prioridad de secuencia sugerida (NO decisión aprobada):** contratos de estados + importes + autorización → persistencia/concurrencia → cumplimiento → API/UX → compatibilidad/mapas/licencia → instalación y recuperación. Las áreas se retroalimentan; ninguna se declara cerrada solo por crear un documento.

## 3. Escenarios de aceptación que no deben quedar implícitos

**Clave:** R = resultado basado en decisión confirmada; P = resultado de implementación/negocio pendiente de definición. Los identificadores X se proponen como trazabilidad nueva y **no** son casos ejecutados. No utilizar resultados P para afirmar comportamiento autorizado.

### 3.1 Catálogo, precios y variantes

| ID | Caso | Resultado exigible R | Contrato pendiente P |
| --- | --- | --- | --- |
| X-C01 | Producto simple sin variantes | Usa precio final/oferta propios en PEN | Precio faltante, cero, importes mínimos y máximo |
| X-C02 | Dos variantes del mismo producto | Cada una tiene precio/oferta manual propios, nunca herencia del padre | SKU, combinación inválida, precio ausente |
| X-C03 | Cambiar variante con producto en carrito | Recalcular precio y stock de la variante correcta desde servidor | Qué sucede si ya se inició un pago |
| X-C04 | Activar/desactivar oferta en administración | Cambia solo precios de nuevas cotizaciones; pedidos confirmados conservan snapshot | Versión de cotización y manejo de carrera |
| X-C05 | Combinación de físico, digital y servicio | Carrito mixto; un cobro inicial por pedido; total final visible PEN | Impuestos, costos de envío, ajustes por línea y redondeo |
| X-C06 | Categoría/regla tributaria del comerciante varía | No aplicar una tasa de impuesto universal ni emitir comprobantes | Matriz fiscal exacta por supuesto |

### 3.2 Disponibilidad, reservas, agenda

| ID | Caso | Resultado exigible R | Contrato pendiente P |
| --- | --- | --- | --- |
| X-R01 | Dos clientes intentan última unidad | Nunca venta confirmada con stock inexistente | Índices, bloqueos/transacciones |
| X-R02 | Dos clientes eligen última cita | Un recurso, un cliente por franja sin solapamiento con buffers | Atomicidad exacta |
| X-R03 | Seleccionar hora en ficha | No reservar hasta pulsar «Pagar» e iniciar intento válido en servidor | Mensaje UX ante competencia |
| X-R04 | Crear intento de pago fallido | No afirmar retención confirmada ni crear pagos duplicados | Retry/idempotencia/rollback |
| X-R05 | Vencen 15 minutos sin tareas programadas | Revalidar vencimiento efectivo; no renovar automáticamente | Limpieza y consulta consistente |
| X-R06 | Proveedor confirma pago tras expiración y sin recurso | Incidencia; comerciante reprograma o tramita reintegro externo | Notificación, evento de reconciliación |
| X-R07 | Confirmación contraentrega | Comprometer stock/cita de inmediato y pago sigue pendiente | Fallo parcial antes de persistir |
| X-R08 | Comerciante reprograma cita | Solo desde panel, registro auditable, una agenda sin solapamientos | Reglas de anticipación y atomicidad del traslado |

### 3.3 Pagos y asientos

| ID | Caso | Resultado exigible R | Contrato pendiente P |
| --- | --- | --- | --- |
| X-P01 | Retorno del navegador indica «aprobado» | No acreditar sin verificación autoritativa | Método y temporalidad de conciliación |
| X-P02 | Webhook duplicado/fuera de orden | No duplicar efectos de negocio ni sobrescribir evidencia | Llave de idempotencia y reconciliación |
| X-P03 | Cobro en efectivo registrado por operador | Operador autorizado puede acreditar evento con auditoría | Verificación de monto, lugar, actor, doble clic |
| X-P04 | Operador intenta corregir efectivo | Denegado en servidor, no solo interfaz | Código y detalle seguro del error |
| X-P05 | Administrador corrige efectivo | Ajuste relacionado, original intacto | Semántica del saldo y reversión |
| X-P06 | Reembolso solicitado pero no ejecutado | No marcar como reembolsado | Documentación de solicitud e incidencia |
| X-P07 | Reembolso externo realmente comprobado | Administrador registra resultado total o parcial verificado; plugin no ordena reintegro (D-54/D-66) | Evidencia, acumulación de parciales y contracargos |
| X-P08 | Carrito con artículo digital y efectivo | Efectivo solo si **todas** las líneas se cobran en el mismo acto presencial; digital bloqueado hasta acreditación | Qué artículos son «cobrables allí», ubicación exacta |
| X-P09 | Pedido pagado parcialmente cancelado | El cobro original no se borra ni se divide retroactivamente | Cuánto devolver, prorrateo de costos e impuestos |
| X-P10 | Administrador registra dos veces mismo reembolso parcial externo | No duplicar resultado ni superar monto devuelto comprobado, conservando original (D-66) | Identificador único externo, acumulados, conciliación |

### 3.4 Cumplimiento y cancelación de líneas

| ID | Caso | Resultado exigible R | Contrato pendiente P |
| --- | --- | --- | --- |
| X-L01 | Pedido pagado, físico sin entregar, digital descargado | Estados de pedido/pago/líneas independientes; no marcar todo entregado | Nombre de estados de cumplimiento |
| X-L02 | Cancelar físico pendiente y elegible de pedido mixto | Mantener demás líneas, historial y ajustes auditables | Cálculo de cargos y reposición de stock |
| X-L03 | Intentar cancelar físico ya entregado | No usar cancelación ordinaria; derivar a devolución/reclamación | Política legal e interfaz |
| X-L04 | Intentar cancelar servicio ya prestado | No cancelar como pendiente; gestionar incidencia/reclamación/devolución aplicable | Evidencia del servicio y condiciones |
| X-L05 | Digital habilitado, ninguna descarga registrada | Evaluación del administrador bajo derechos legales, sin cancelación ni reintegro automáticos (D-61) | Estado legal/de prestación, evidencia y autorización |
| X-L06 | Pedido pagado y cancelación con efecto financiero | Solo administrador autoriza; eventual reintegro externo | Detección de efecto y autorización |
| X-L07 | Operador cancela línea pendiente sin efecto monetario | Operador únicamente sin dinero acreditado, reintegro o ajuste exigible y con capacidad/elegibilidad (D-64) | Matriz granular y verificación transaccional de cobros |
| X-L08 | Dos actores cancelan al mismo tiempo | Una sola cancelación efectiva y sin doble liberación/doble ajuste | Concurrencia, versionado, idempotencia |
| X-L09 | Producto cambia de precio después del pedido | Snapshot original permanece intacto | Versión de catálogos/cotización |
| X-L10 | Revertir operación administrativa por error | Ajuste auditable, no sobrescribir pedido confirmado | Eventos compensatorios aprobados |
| X-L11 | Digital con acceso habilitado sin descarga solicita devolución | Administrador evalúa con política/derechos, no automatiza cancelación (D-61) | Condición jurídica, evidencia, comunicación |
| X-L12 | Comprador pide cancelar físico despachado no entregado | Abrir incidencia logística, no cancelación inmediata (D-62) | Investigación, resolución y trazabilidad |
| X-L13 | Cancela última línea física antes del despacho con delivery en pedido | Revisar delivery según servicio/costos reales y derechos aplicables; autorizar ajuste si pedido pagado (D-63) | Fórmula, tarifa, impuestos, evidencia |
| X-L14 | Operador intenta cancelar línea de pedido con dinero acreditado pero sin reintegro estimado | Denegado para operador (D-64); requiere vía administrativa | Comprobación de acreditación, permisos |
| X-L15 | Cliente pide cambio de dirección de entrega confirmada | Ajuste sin sobrescribir original, validar nueva cobertura/tarifa y viabilidad antes de aplicar (D-65) | Reglas logísticas, diferencia de importe |

### 3.5 Descargas, privacidad, mapas y cumplimiento

| ID | Caso | Resultado exigible R | Contrato pendiente P |
| --- | --- | --- | --- |
| X-D01 | Descarga digital sin pago acreditado | Acceso denegado | Procedimiento manual de cobro confirmado |
| X-D02 | Enlace de descarga cumple 24 horas | No sirve el token vencido; reemisión segura al autorizado | Método de identificación y revocación |
| X-D03 | Invitado consulta pedido con enlace privado | Mostrar solo su pedido | Caducidad, recuperación, revocación |
| X-D04 | Usuario cambia identificador en URL de pedido | No obtener pedido ajeno ni archivo | Protección contra enumeración, rate limits |
| X-M01 | Leaflet o proveedor cartográfico falla | Aceptar dirección manual si cobertura UBIGEO validable | Geocodificación opcional/proveedor |
| X-M02 | Dirección con distrito fuera de cobertura | No prometer entrega ni calcular tarifa por coordenadas | Catálogo UBIGEO/versionado |
| X-N01 | Cliente exige boleta/factura/recibo externo | Mostrar datos solo si aplican al régimen/operación | Matriz SUNAT por tipo de contribuyente |
| X-N02 | Compra con reclamación | Libro nativo o enlace conforme visible y trazable | Reglamento vigente, constancia y plazos |
| X-N03 | Solicitud de supresión de datos con pedido fiscal histórico | Cumplir derechos sin destruir evidencia legal obligatoria | Categorías, plazos, base jurídica |
| X-S01 | Usuario operador llama endpoint de administrador | Rechazo backend; jamás exponer credenciales | Capabilities y respuestas seguras |
| X-S02 | Plugin desactivado y vuelve a activarse | No borrar historial de pedidos ni reclamaciones por efecto indirecto | Política de migración/reconciliación |

Los escenarios X documentan **qué se debe cubrir**, no resultados demostrados. No convertir una recomendación P de la última columna en requisito aprobado.

## 4. Plantilla obligatoria de contrato por operación (antes de autorizar código)

Cada mutación del backend debe tener un registro compacto que incluya:

| Campo | Definición mínima |
| --- | --- |
| Identificador de operación | Nombre estable, módulo, versión de contrato y origen UI/evento |
| Permiso | Actor y capability explícita; recursos visibles y alcance de datos |
| Entrada | Tipos, campos obligatorios, moneda PEN, identificadores y validaciones |
| Precondición | Estado actual, versión de pedido, disponibilidad/retención, elegibilidad legal/funcional |
| Resultado | Estados de cada dimensión, eventos añadidos y efectos externos |
| Persistencia | Inmutabilidad, transacción, índices/llaves de unicidad y rollback/compensación |
| Reintentos | Idempotency key, repetición de webhook, vencimiento, recuperación de respuesta perdida |
| Errores | Códigos de dominio, estado HTTP cuando aplique, mensaje seguro y recuperación UX |
| Auditoría | Actor, causa, instante, referencia de pedido/línea, monto, relación con original |
| Seguridad | Autenticación o enlace invitado, acceso mínimo, protección de secretos y abuso |
| Aceptación | Casos de éxito/fallo/competencia, evidencia estática o prueba futura autorizada |
| Dependencias | Proveedor, versión, licencia/costo, entorno y alternativas sin servicio |

**Contratos por definir primero (recomendación técnica, NO aprobación implícita de endpoints):** cotizar carrito, iniciar intento, verificar pago externo, confirmar contraentrega, registrar efectivo, corregir cobro, revalidar agenda, cancelar línea, solicitar atención de devolución, registrar reembolso externo comprobado, habilitar/reemitir descarga, consultar pedido invitado y actualizar cobertura UBIGEO.

No asumir nombres de rutas, campos ni JSON como aprobados hasta documentarlos.

## 5. Bloqueos transversales que una documentación superficial ocultaría

- **Pago después de 15 minutos:** el proveedor puede procesar cobros cuando el hold local ya caducó. No confundir expiración de disponibilidad con anulación del pago externo.
- **Un cobro por pedido, varias líneas:** permitir cancelación parcial no implica cobros originales múltiples. Ajustes y devoluciones posteriores son otros movimientos con referencias distintas.
- **Estado «cumplido» no único:** un digital con acceso habilitado, un archivo descargado, un físico entregado y un servicio prestado son hechos diferentes; la política de cancelación depende de ellos y de ley.
- **Contraentrega + digitales:** solo si todo el pedido es cobrable en un mismo evento físico; los derechos digitales siguen bloqueados hasta acreditar realmente el efectivo.
- **Pedidos inmutables:** un pedido confirmado no puede reescribirse para ajustar el importe; eventos correctivos deben permitir reconstruir qué ocurrió.
- **Roles en backend:** ocultar un botón al operador no impide que invoque directamente un endpoint; D-64 exige denegar cancelaciones de operador cuando haya dinero acreditado o ajuste/reintegro exigible, incluso si D-60 permitiría una cancelación sin efecto financiero. Comprobar frente a requests directos.
- **Concurrencia en hosting compartido:** confirmar existencia de transacciones/índices y estrategia de bloqueo; la inexactitud de cron no puede causar sobreventa.
- **WordPress y Angular:** catálogo indexable sin requerir el runtime Angular; el ZIP debe instalarse sin herramientas de desarrollo en el servidor, con aislamiento de estilos y dependencias justificadas.
- **Distribución gratuita y repositorio privado:** una licencia de código y las de terceros no se infieren de la visibilidad de GitHub.
- **Información peruana:** los regímenes SUNAT, requisitos de Libro de Reclamaciones y protección de datos requieren matriz vigente; la simple presencia de un campo no demuestra cumplimiento.

## 6. Excepciones S-01…S-06: CERRADAS (2026-11-16)

El propietario aprobó expresamente **«Todo A»**. Las opciones A de S-01 a S-06 fueron registradas como decisiones D-61 a D-66. **No volver a proponerlas** ni interpretar sus detalles técnicos todavía abiertos como aprobados.

| Ref. | Política confirmada | Decisión | Pendiente real |
| --- | --- | --- | --- |
| S-01 | Digital habilitado sin descarga requiere evaluación de administrador conforme a derechos, sin cancelación/reintegro automático | D-61 | Evidencia, condición de cumplimiento y política legal |
| S-02 | Físico despachado no entregado abre incidencia/revisión logística, no cancelación inmediata | D-62 | Reglas, pruebas y salida de la incidencia |
| S-03 | Cancelación de última línea física antes del despacho obliga a revisar delivery según servicio prestado/derechos | D-63 | Importes, prorrateos, normas, aprobación |
| S-04 | Operador cancela solo sin dinero acreditado ni reintegro/ajuste, con permisos y auditoría | D-64 | Matriz granular y consistencia de acreditaciones |
| S-05 | Dirección confirmada modificable vía solicitud/ajuste no destructivo, validando cobertura/costo | D-65 | Viabilidad logística, aceptación y diferencias monetarias |
| S-06 | Reembolso externo parcial comprobado registrable por administrador con monto/referencia y cobro intacto, sin API | D-66 | Evidencia, múltiples parciales, saldos y conciliación |

**Subcontratos abiertos:** S-01…S-06 no fijan fórmulas tributarias, importes de devolución, plazos legales, documentación de entrega ni autorizaciones finas de APIs. La matriz de estados del documento 12 debe incorporar estos seis requisitos, no decidir sus variables pendientes.

**Consolidación posterior:** `docs/34-MATRIZ-CIERRE-FASE-0-Y-VALIDACIONES-PENDIENTES-V1.md` agrupa 19 puertas de cierre con evidencias separadas. El inventario REST vigente es **64 entradas R8: 63 operaciones REST y una página WordPress no REST**; la matriz E-02 sigue teniendo 56 PER y existen 39 tablas **propuestas**. **La mera ratificación de una ruta/tabla no acredita permisos, seguridad ni funcionamiento.**

## 7. Puerta de entrada a implementación (Definition of Ready)

El proyecto solo podrá etiquetarse **LISTO_PARA_IMPLEMENTAR** con autorización del propietario y evidencia de:

1. Todos los P0 cerrados o excluidos explícitamente, junto con **E-08, J-01…J-05/P0-17/P0-25, SQL/hosting P0-18/P0-19, ZIP P0-26 y capacidades exactas**. E-01…E-07 aprobados en sus políticas D-67…D-148; no equivalen a seguridad/funcionamiento comprobados.
2. **E-01…E-07 APROBADOS EN POLÍTICA** (docs.14…20, D-67…D-148). **Pendientes:** E-08 APIs/autenticación técnica, matriz legal J-01…J-05, capacidades por campo/recurso y SQL/hosting/ZIP. Codex no debe inventarlos.
3. Contratos verificables de persistencia, API, pagos, stock/citas, archivos, avisos, datos, reintegros y fiscalidad. E-04…E-07 aprobados en política; M4-X/C5-X/N6-X/P7-X **no son pruebas**. Faltan E-08, J-01…J-05/P0-17/P0-25 y SQL/hosting/ZIP.
4. Diseños Figma aprobados para **móvil y escritorio**, incluidos estados extremos, accesibilidad y contratos de handoff.
5. Matriz de compatibilidad WordPress/PHP/BD/Angular y de proveedor cartográfico, costos/licencias y estrategia de ZIP/actualización/desinstalación.
6. Matriz normativa peruana vigente para regímenes, documentos externos, reclamaciones, privacidad y condiciones del consumidor.
7. Trazabilidad: cada decisión apunta a operación, pantalla y escenarios; cada operación tiene precondiciones, permiso, resultado, error, auditoría y criterio de aceptación.
8. Permiso explícito para implementar. **No hay autorización automática para compilar, probar, integrar credenciales o publicar.**

## 8. Puerta de salida a entrega operativa (Definition of Done)

Al implementarse, únicamente si cada etapa está autorizada, se requiere revisión estática, compilación/instalación en entornos compatibles, validaciones automatizadas y manuales de concurrencia y permisos, pruebas con sandbox del proveedor y las reales con autorización específica, verificación de accesibilidad/rendimiento, seguridad, cumplimiento y ZIP de actualización/recuperación. Cada etapa se registra con fecha, evidencia y límites; **no declarar producción funcional porque una lista de decisiones esté completa**.

**Regla de trabajo:** evitar expandir funcionalidad. Ante un hueco bloqueante, abrir una decisión con opciones/impacto y resolverla antes de generar código relacionado. Las **187 decisiones confirmadas D-01…D-187** permanecen vigentes hasta que el propietario cambie alguna expresamente; la aprobación en política no equivale a autorización de código ni resultados de pruebas.
