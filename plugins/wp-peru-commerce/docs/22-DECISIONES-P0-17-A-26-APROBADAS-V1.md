# P0-17…P0-26 — Registro de aprobación de nueve decisiones V1

**Fecha de aprobación:** 2026-11-17.  
**Estado:** APROBADO EN POLÍTICA (D-167…D-175).  
**Origen:** aprobación expresa del propietario: «Ok apruebo todas que sigue?», referida a las nueve opciones A propuestas para P0-17, P0-18, P0-19, P0-20, P0-21, P0-23, P0-24, P0-25 y P0-26.

> La aprobación de las decisiones NO equivale a DDL físico completo, OpenAPI implementable, conformidad legal verificada, interoperabilidad probada con Mercado Pago, compatibilidad comprobada con el hosting, compilación Angular, instalación funcional ni autorización de implementación. No existen todavía código del plugin ni pruebas ejecutadas. Mantener bloqueo de implementación vigente en AGENTS.md.

## Alcance y precedencia

Estas nueve decisiones **complementan**, sin sustituir, D-01…D-166 y E-01…E-08. En caso de aparente conflicto conservar reglas específicas: E-04 usa enteros de céntimos PEN; E-05 controla concurrencia sin HTTP en SQL; E-07 regula identidad; E-08 **original** fijó 52 entradas R8, con R8-18 página WordPress no REST. **Extensión posterior ratificada D-176…D-187** incorpora 12 operaciones R8-53…R8-64: total vigente de diseño **64 entradas / 63 REST**, sin nuevas capabilities ni estados E-01. No inventar otras rutas, permisos ni acciones.

| Código | Decisión | Registro | Estado de la elección | Trabajo físico o verificación aún pendiente |
| --- | --- | --- | --- | --- |
| P0-17 | Reglas monetarias y tributarias | D-167 | APROBADA EN POLÍTICA | J-03; redondeo fiscal específico, DTO persistido y compatibilidad real de payload MP |
| P0-18 | Persistencia e índices | D-168 | APROBADA EN POLÍTICA | Diagrama ER, DDL completo, claves, migraciones, consultas/locks y restauración |
| P0-19 | Versiones y hosting | D-169 | APROBADA COMO OBJETIVO | Diagnóstico autorizado del hosting real y recursos del proveedor |
| P0-20 | Distribución y montaje Angular | D-170 | APROBADA EN POLÍTICA | Manifiesto y build reales, comportamiento de rutas/chunks/CSS/caché |
| P0-21 | Mapa y UBIGEO | D-171 | APROBADA EN POLÍTICA | Dataset oficial vigente, atribución/licencias, límites del proveedor y verificación |
| P0-23 | REST, permisos y DTO | D-172 | APROBADA EN POLÍTICA | OpenAPI 3.1 por ruta/campo y matriz R8↔PER↔capabilities |
| P0-24 | Licencia/distribución | D-173 | LICENCIA ELEGIDA | Texto de licencia y avisos de dependencias en paquete; inventario final |
| P0-25 | Matriz normativa peruana | D-174 | APROBADA COMO MÉTODO | J-01…J-05 por supuesto, norma vigente, evidencias y revisión jurídica |
| P0-26 | Instalación/ZIP/respaldo | D-175 | APROBADA EN POLÍTICA | Procedimientos implementables y recuperación comprobada en entorno autorizado |

## D-167 / P0-17 — Contrato monetario y tributario

- Moneda única PEN; comerciante introduce el **precio final** y se muestra antes de confirmar, conforme a D-45/D-46/D-49.
- La representación persistida de **importes comerciales** es `BIGINT` de **céntimos enteros**, no `DECIMAL(18,2)`. En DTO, los valores monetarios en céntimos se transportan como **cadenas decimales**; no se usan float/double para resultados vinculantes.
- Cálculo comercial de subtotal = cantidad entera × precio efectivo en céntimos. Total = suma de líneas + **una tarifa de entrega cuando corresponda**, preservando snapshots históricos, recotizaciones, modificaciones y ajustes conforme a E-04.
- El cálculo fiscal contextual es **distinto del total comercial**. No agregar IGV universalmente a un precio ya final; distinguir régimen, emisor, operación, gravado/exonerado/inafecto y vigencia de las tasas. Cuando se haga desglose fiscal, definir precisión decimal, redondeo y fuente normativa por caso antes de programar; operaciones decimales exactas con la extensión PHP BCMath aprobada como dependencia obligatoria, no float. BCMath no reemplaza los enteros de céntimos del motor comercial.
- Comprobantes (boleta, factura o recibo por honorarios **solo si aplica**) y notas de crédito: **registro de referencias emitidas externamente**, sin emisión SUNAT incorporada ni motor tributario general V1.
- Diferenciar el bruto de Mercado Pago, comisión, neto y reembolsos **externos verificados**. No ofrecer ejecución de reembolso por API del plugin. Mantener un cobro inicial por pedido y las reglas de efectivo E-04.
- El importe enviado a MP debe coincidir con la cotización vigente validada en servidor; las tres condiciones proveedor de E-08 (pedido sin `payer` para físico sin email, ítem Entrega y `PT15M`) siguen **bloqueantes hasta comprobación autorizada**.
- **Pendiente físico/jurídico:** J-03 y P0-25, fórmulas de desglose fiscal y redondeo por supuesto, tipado completo, payload/conciliación real. No registrar fiscalidad como «cerrada técnicamente».

## D-168 / P0-18 — Persistencia transaccional e índices

- Catálogo público: estructuras WordPress con tipo de contenido propio; sin WooCommerce. Pedidos, líneas, pagos, stock, retenciones, agenda, incidencias y auditoría: tablas propias InnoDB.
- Nombres conceptuales con prefijo WordPress dinámico: `pc_sellables`, `pc_stock_movements`, `pc_holds`, `pc_orders`, `pc_order_lines`, `pc_payment_attempts`, `pc_payment_events`, `pc_calendar_days`, `pc_schedule_rules`, `pc_appointments`, `pc_ubigeo`, `pc_delivery_rates`, `pc_idempotency`, `pc_webhook_inbox`, `pc_outbox`, `pc_guest_sessions`, `pc_otp_challenges`, `pc_digital_files`, `pc_download_rights`, `pc_complaints`, `pc_cases`, `pc_privacy_requests`, `pc_external_documents`, `pc_audit_log`, `pc_policy_versions`, `pc_migrations`. El prefijo `wp_` **no se fija**.
- Identificadores internos `BIGINT UNSIGNED` cuando corresponda; importes comerciales `BIGINT` céntimos; instantes UTC y zona del comercio para presentación; snapshots inmutables y versiones de registro. Evitar cascadas que destruyan historial.
- Índices/candidatos: `pc_sellables(product_id,variant_key)` único; `pc_orders(order_number)` único y `(status,created_at)`; `pc_order_lines(order_id,id)` y `(sellable_id,fulfillment_status)`; `pc_payment_attempts(provider,provider_order_id)` único cuando exista; `pc_payment_events(provider,provider_event_id)` único; `pc_holds(status,expires_at)` y `(sellable_id,status)`; `pc_calendar_days(resource_id,local_date)` único; `pc_appointments(resource_id,starts_at_utc,ends_at_utc)`; `pc_delivery_rates(ubigeo)` único; `pc_idempotency(scope,key_hash)` único; `pc_webhook_inbox(provider,event_id)` único; `pc_outbox(deduplication_key)` único y `(status,next_attempt_at)`; `pc_guest_sessions(token_hash)` único; numeración de reclamaciones única por ámbito/periodo normativamente definido; `pc_audit_log(entity_type,entity_id,created_at)`.
- Son **índices objetivo**, sujetos a normalización y DDL sin alterar comportamiento aprobado. En particular, unicidad de variantes simples, nullable y reclamos requerirá especificación y tratamiento de compatibilidad MySQL/MariaDB.
- Las ventas/holds/ajustes bloquean la fila estable de variante. Agenda bloquea fila estable por recurso/día y verifica solapamiento de intervalos `[inicio, fin)`, incluyendo buffers y días implicados. No confiar en una lectura de disponibilidad sin lock.
- Conservar E-05: transacciones SQL cortas, HTTP externo fuera de transacciones, admisión interna breve, hold comercial de 15 minutos tras intento MP válido, idempotencia persistente, webhooks/inbox, outbox y recuperación.
- **Pendiente físico:** ER, columnas/nullability/constraints/índices definitivos, modelo de aislamiento y consultas, vida de admisión y migraciones; no escribir SQL ejecutable todavía.

## D-169 / P0-19 — Versiones objetivo y alojamiento

- Objetivo inicial de soporte: **WordPress 7.1.4+ estable**, **PHP 8.3+** dentro de ciclo de soporte, **MariaDB 10.11+ o MySQL 8.4+ con InnoDB**, HTTPS.
- Frontend: **Angular 22.x**, compilado fuera del alojamiento. Compatibilidad de su entorno de compilación según tabla oficial Angular 22: Node `^22.22.3 || ^24.15.0 || ^26.0.0`, TypeScript `>=6.0.0 <6.1.0`, RxJS `^6.5.3 || ^7.4.0`. La instalación de producción **no requiere Node ni servidor Angular**.
- Requisito adicional de integración: extensión PHP BCMath obligatoria y restantes extensiones PHP necesarias realmente disponibles, SQL transaccional y permisos DDL, almacenamiento privado/streaming, HTTPS, correo transaccional, carga y caché de assets.
- **No verificado:** versión/motor/privilegios de Hostinger u otro alojamiento real. Si falta garantía crítica, bloquear funcionalidad afectada; no degradar silenciosamente consistencia ni seguridad.
- Referencias oficiales para revalidación en implementación: https://wordpress.org/download/releases/ ; https://wordpress.org/about/requirements/ ; https://angular.dev/reference/versions .

## D-170 / P0-20 — Compilación y montaje Angular

- Un solo frontend Angular con composiciones móvil y escritorio para carrito, checkout, agenda y panel. WordPress/PHP renderiza catálogo indexable y bloques/shortcodes.
- Compilar en entorno de desarrollo y distribuir **assets estáticos precompilados dentro del ZIP**, con nombres hasheados y manifiesto que identifique entrypoints, dependencias y versión.
- WordPress monta raíces solo donde correspondan, carga JS/CSS condicionalmente con APIs oficiales y expone **solo** configuración pública de runtime (REST, idioma `es-PE`, moneda `PEN`, versión); nonce de WordPress solo cuando haya sesión autenticada pertinente. No introducir secretos/credenciales en JS.
- Same-origin y rutas no absolutas; evitar depender de rewrite del servidor para navegación interna y de Angular SSR para SEO. No cargar Angular globalmente en páginas ajenas.
- **Contrato documental:** `docs/31-CONTRATO-ANGULAR-WORDPRESS-ASSETS-P0-20-V1.md` desarrolla 8 superficies, 10 propiedades de manifiesto, WordPress ESM, rutas, caché y 20 A20-X. **Pendiente físico:** build Angular, manifest generado, CSS/chunks y verificación WordPress real, rendimiento/accesibilidad, sin código autorizado.

## D-171 / P0-21 — Leaflet, proveedor y UBIGEO

- Leaflet local para mostrar mapa de manera **opcional**. Fuente cartográfica inicial elegida: OpenStreetMap Standard Tiles, con respeto de política de uso, atribución, caché y límites; proveedor configurable y reemplazable. No garantizar disponibilidad del servicio público.
- Ingreso manual de dirección siempre utilizable; geolocalización solo tras elección del usuario. No hacer del mapa ni de geocoding una dependencia para vender; Nominatim público **no** se utilizará como autocompletado general.
- Entrega y costo **exclusivamente** por distrito UBIGEO elegido y validado en backend; coordenadas/pin nunca anulan cobertura/tarifa del distrito. Un pedido confirmado conserva su snapshot de entrega.
- Dataset de distritos oficial INEI, versionado con procedencia, fecha, integridad y actualizaciones sin alterar históricos; licencia y atribución del dataset a verificar en el paquete.
- **Avance documental:** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` reúne fuentes oficiales OSM/INEI, matrices de proveedor/UBIGEO, 16 controles MAP y 20 escenarios M21-X. **Pendiente:** elegir archivo territorial completo y vigente (la ficha INEI 2023 ODbL y publicación 2025-12 ODC Attribution difieren), revisar atribución/derechos por artefacto, condiciones reales del proveedor y comprobar fallback/caché/privacidad. No se distribuyó ni importó dataset.
- Referencias: https://operations.osmfoundation.org/policies/tiles/ ; https://www.openstreetmap.org/copyright .

## D-172 / P0-23 — REST, DTO, capabilities y OpenAPI

- Mantener namespace `peru-commerce/v1` y **exactamente** las 52 entradas existentes R8-01…R8-52 del contrato E-08; **R8-18 es página WordPress y no endpoint REST**. No inventar API alternativa.
- Especificación física **OpenAPI 3.1** versionada por método/ruta: actor, capability por operación y campo, DTO/request/response, tipos, validación, propiedad de recurso, estado/versionado, errores `pc_*`, HTTP, idempotencia y efectos laterales. Matriz completa R8↔PER-01…PER-56.
- Público: R8-01…07 y 15/16 según riesgo, rate limits y datos mínimos. Invitados: R8-08…14 requieren secret/sesión/verificación específica; seguimiento de pedido **solo lectura** y verificación adicional para acciones sensibles. R8-17 firma webhook. Administración: R8-19…52 con capacidades granulares y campos protegidos, respetando restricciones E-02/E-07.
- Operador no accede a credenciales, exportaciones masivas, expedientes completos de reclamaciones ni reembolsos; registrar cobro efectivo no concede permiso de corregirlo; aprobaciones financieras/ajustes sensibles solo administrador según D-78…D-85.
- Id/importe de 64 bits como cadena decimal en JSON para no perder precisión. Importes en **céntimos PEN**; backend única autoridad de totales y validación; WordPress nonce **no autentica invitados**.
- **Pendiente físico:** especificar cada DTO y permiso campo a campo, errores diferenciados CSRF/IDOR/antifalsificación/replay, y aceptación de seguridad. No iniciar controladores sin ello.

## D-173 / P0-24 — Licencia de distribución

- Opción elegida y **aprobada**: código original del plugin distribuido bajo **GPL-2.0-or-later**; descarga gratuita mediante ZIP, fuera de WordPress.org en V1; repositorio de desarrollo privado.
- Distribuir código fuente correspondiente y avisos de dependencias sujetos a sus licencias; no declarar al receptor privado ni prohibir la redistribución compatible con GPL. Mantener marcas/identidad comercial jurídicamente diferenciadas.
- **Contrato documental:** `docs/32-LICENCIA-FUENTES-Y-DISTRIBUCION-P0-24-V1.md` registra requisitos de código fuente por release, licencias/avisos de terceros, 18 controles LIC y 20 LIC-X. **Pendientes:** LICENSE íntegro en el paquete real, fuentes TS/PHP/lockfile y herramientas de build, avisos exactos según dependencias, método de entrega conforme, sin publicación de repo ni ZIP por inferencia.
- Referencia: https://wordpress.org/about/license/ .

## D-174 / P0-25 — Matriz normativa y sujetos responsables

- Preparar matriz por norma/artículo, vigencia, sujeto obligado, régimen/actividad/operación, regla/UI/dato afectado, evidencia, responsable y validación:
  - **J-01:** Libro de Reclamaciones: acceso, requisitos y constancia, correlativo, atención/plazos y conservación.
  - **J-02:** protección al consumidor: información antes de compra, garantías, devoluciones, requisitos sectoriales y canales de atención; prohibir patrones coercitivos.
  - **J-03:** SUNAT: régimen, emisor, clase de operación, tributo/gravabilidad, comprobante externo, notas de crédito, delivery y conservación; **sin motor fiscal automático general**.
  - **J-04:** protección de datos: responsable/encargado, finalidades, datos mínimos, ARCO, bancos de datos, terceros y transferencias.
  - **J-05:** incidentes, evidencia, retención y eliminación legal, recuperación y notificaciones cuando corresponda.
- Las políticas comerciales o las capacidades del plugin no eliminan obligaciones legales. No inventar derecho universal de arrepentimiento, plazos únicos de devolución ni emisión automática SUNAT. No proclamar «cumplimiento total».
- Confirmar reglamentos y eventuales modificaciones 2025–2026 antes de firmar la matriz final. Usar las fuentes institucionales y los supuestos ya reunidos en `docs/07-CUMPLIMIENTO-PERU.md`.
- **Pendiente:** J-01…J-05 completo por supuesto legal y sector; comprobación de texto definitivo/vigencia; evidencias y mecanismos efectivamente implementados.

## D-175 / P0-26 — ZIP, instalación, backups y recuperación

- ZIP manual con raíz fija `wp-peru-commerce/`, PHP, backend, assets Angular precompilados, manifiesto, avisos/licencia y directorios pertinentes. No procesos Node en hosting.
- Activación con diagnóstico de WordPress/PHP/extensiones/HTTPS/InnoDB/DDL/almacenamiento privado; si una garantía crítica falla, bloquear checkout o módulo afectado sin fingir funcionamiento.
- Versionar plugin y esquema. Migraciones idempotentes y ordenadas, sin pérdida de snapshots/historial; bloquear operaciones incompatibles durante migraciones. `dbDelta()` puede servir para algunos cambios, pero no asumir que resuelve todo DDL.
- Antes de actualizar: copia **consistente** de BD, almacenamiento privado y configuración junto con versiones. Volver al ZIP anterior **no revierte** por sí solo una migración SQL; restaurar conjunto compatible.
- Al restaurar operaciones financieras, reconciliar con proveedor antes de permitir nuevos cobros; conservar idempotencia y trazabilidad. Desinstalación **no destructiva** por defecto conforme E-07 y conservación legal; eliminar solo mediante acción y política expresa permitida.
- Archivos digitales deben permanecer inaccesibles por URL pública directa. Si el hosting no garantiza protección, bloquear descargas digitales.
- **Runbook documental propuesto:** `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md` organiza 9 fases, 12 LC, 14 F26 y 20 ZIP-X, con fuentes oficiales WordPress/Hostinger/MariaDB. **Pendiente físico:** versión ZIP/manifiesto real, migrador/DDL, pruebas autorizadas de actualización y restore, RPO/RTO, seguridad de backups y verificación de conciliación MP. No implica que se hayan ejecutado.

**Consolidación vigente (2026-11-17):** `docs/34-MATRIZ-CIERRE-FASE-0-Y-VALIDACIONES-PENDIENTES-V1.md` agrupa **19 puertas** de las decisiones D-167…D-175 y la extensión C23. Diferencia cierre documental, verificación jurídica por comerciante, pruebas de Hostinger/MP y la exclusión de alcance **PER-09 cerrada por D-188**. Ninguna elección P0 se revoca ni se declara probado el producto por disponer de matriz.

**Documentos de cierre propuestos posteriores (2026-11-17):** `docs/35-CONTRATOS-LEGALES-PERMISOS-DTO-P0-23-P0-25-V1.md` detalla obligaciones por finalidad, 14 operaciones/10 LEG sin ratificación legal universal; `docs/36-RESOLUCIONES-PROPUESTAS-DB-A01-A16-P0-18-V1.md` precisa tratamientos de las 16 DB-A sobre el modelo de 39 tablas, sin SQL definitivo ni pruebas. Se preservan D-01…D-188; las propuestas no redefinen decisiones de alcance aprobadas.

## Puertas restantes antes de implementar

1. **P0-18: modelo ampliado en docs/23 y auditoría de riesgos en `docs/28-AUDITORIA-ESTATICA-MODELO-FISICO-P0-18-V1.md` (16 hallazgos), con esquema sin ejecución.** Contiene 39 tablas propuestas (36 de origen + 3 C23), junto a tipos/índices/relaciones y 16 ajustes DB-A pendientes de ratificación. Pendientes DDL/migraciones implementables, verificación de motor, respaldos y restauración; aprobación de política no prueba SQL.
2. **P0-23 C23 ratificado D-176…D-187:** `docs/24-MATRIZ-REST-PERMISOS-DTO-P0-23-V1.md` cubre 64 R8 y 56 PER; `docs/openapi/peru-commerce-v1.openapi.draft.json` fue en el corte C23 OpenAPI 3.1 `0.3.0-draft` con 63 REST/113 DTO; **el vigente posterior D-198 es 0.5.0-draft con 67 REST/64 rutas/117 DTO**. El anexo 27 y los anexos 11/12 de docs/23 trazan rutas/esquema; el OpenAPI C23 separado conserva **solo historia**, no es API vigente. **Falta comprobar** campos legales, controles de tokens/CSRF, DDL, seguridad y hosting/MP, sin nuevas rutas por inferencia.
3. **J-01…J-05/P0-17/P0-25: investigación documental en `docs/25-MATRICES-NORMATIVAS-J01-J05-P0-25-V1.md` (59 controles) y `docs/26-CONTRATO-TRIBUTARIO-P0-17-J03-V1.md` (15 supuestos fiscales).** Ratificar vigencia del Reglamento del Libro, aplicación por comerciante/actividad, datos y procesos legales; no confundir las matrices propuestas con certificación o implementación.
4. **P0-19/P0-20/P0-21/P0-24/P0-26:** `docs/29-COMPATIBILIDAD-WORDPRESS-HOSTING-P0-19-V1.md` fija objetivos y 12 verificaciones HOST por instalación; `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md` documenta runbook sin ejecución. Los diseños de manifest/montaje y licencias/fuentes están en docs/31 y docs/32; faltan build/manifest auténticos, fuentes de release/aviso de cada dependencia, dataset UBIGEO concreto/validado según `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md`, evidencias de instalación real y ZIP/restore probados.
5. Verificar condiciones de **MP peruano** aprobadas condicionalmente en E-08, además de hosting/BD/archivos, solo en entorno y con pruebas que autorice específicamente el propietario.
6. Diseñar y aprobar vistas Figma móvil/escritorio y sus estados, preservando WCAG 2.2 AA y objetivos táctiles de 44×44 CSS aprobados.
7. Obtener autorización específica para implementar. **Nada de esta aprobación global autoriza código, pruebas, compilaciones, cuentas ni despliegue.**

## Complemento ratificado de cobertura (D-176…D-187)

La aprobación contextual «OK adelante» del 2026-11-17 incorpora **12 operaciones C23** al diseño de V1, documentadas en `docs/27-COBERTURA-OPERACIONES-P0-23-PROPUESTA-V1.md` y en el OpenAPI principal: R8-53…R8-64. El rol O no recibe privilegios antes pendientes de PER-20/27/28/31; en esas funciones opera sólo el administrador. Las reglas de reemisión de secreto una sola vez, evidencia de caso/agenda, versiones de binarios y referencias fiscales externas siguen sujetas a validación física y legal; **no son código ni pagos reales**.

## Evidencia y clasificación

- **Aprobadas por el propietario:** nueve decisiones D-167…D-175 registradas aquí.
- **Revisado documentalmente:** consistencia básica con E-01…E-08 y documentos existentes.
- **No ejecutado ni demostrado:** SQL, validaciones de proveedor/hosting, OpenAPI exhaustivo, CI, compilación Angular, licencias de cada dependencia, instalación real, pago efectivo, verificación jurídica sectorial o experiencia Figma.
