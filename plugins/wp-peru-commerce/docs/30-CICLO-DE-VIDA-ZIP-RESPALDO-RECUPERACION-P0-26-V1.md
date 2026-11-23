**ACTUALIZACIÓN IMP-68 — 2026-11-21:** Las piezas de código estático para una barrera de mantenimiento (opción InnoDB/epoch externa, lock compartido al iniciar TX, exclusión admin, worker y archivos privados), migraciones aditivas 1.0.0/1.0.1→1.0.2 y verificadores exigidos de respaldo y reapertura están publicadas en main. El procedimiento concreto y su evidencia P-C pendiente se detallan en [distribution/RECOVERY-RUNBOOK.md](../distribution/RECOVERY-RUNBOOK.md) y docs/43 §8. **No se han ejecutado** backup/restore, PHP, SQL, pruebas, compilación, migración, consulta MP, ZIP o despliegue; no hay proveedor externo de atestación conectado ni epoch de comercio. Sin backup real íntegro y conciliación MP/caja, el código permanece fail-closed. El estado P0-26 documental histórico de 2026-11-17 se conserva abajo como antecedente.

**ACTUALIZACIÓN IMP-67 — 2026-11-21:** Quedan publicados los scripts de generación/verificación de assets reales y de preparación **dual** del plugin y su fuente correspondiente, con inventario, GPL y vinculación a commit/lockfile. Son archivos de código no ejecutados; **no** han producido bundle Angular, manifest, LICENSE de terceros emitido, ZIP, fuente instalable, upgrade, respaldo, restauración ni despliegue. El proceso real de ZIP y firma/hashes finales pertenece a IMP-72, y el procedimiento de actualización/restauración se implementa en IMP-68. Ver `distribution/SOURCE-INSTRUCTIONS.md` y docs/43 §8. La fecha/estado histórico del runbook 2026-11-17 se conserva por trazabilidad.

# P0-26 — Contrato de ciclo de vida ZIP, migraciones, respaldo y recuperación V1

**Corte de documentación:** 2026-11-17.  
**Estado:** P0-26 **APROBADO EN POLÍTICA D-175**, runbook **DOCUMENTAL PROPUESTO**. No es un ZIP, instalador, migrador, script de restauración ni plan operativamente probado. El propietario autorizó continuar la documentación, **NO implementar código, ejecutar SQL/compilar/pruebas, conectar hPanel/MP, publicar o desplegar**.

## 1. Precedencia y requisitos de producto

- D-175 define **ZIP manual, migraciones versionadas, respaldos consistentes, recuperación y desinstalación no destructiva**. Esta especificación concreta procedimientos, no amplía decisiones.
- E-01 (`docs/14`) conserva estados O/MP/H/F/G/V; E-04(`docs/17`) dinero PEN en céntimos; E-05 (`docs/18`) bloqueos/idempotencia/inbox-outbox; E-07 (`docs/20`) identidad/retención; E-08 (`docs/21`) **68 entradas R8 (67 REST + página WP, OpenAPI 0.5.0-draft)**, sin ejecución de refund y con evidencias legales protegidas D-198; `docs/23` y `docs/28` describen **39 tablas propuestas** y 16 observaciones DB-A; `docs/29` define 12 verificaciones HOST por instalación.
- Un solo comercio/local por instalación WordPress; `$wpdb->prefix` aísla sitio. El ZIP es autónomo, con frontend Angular precompilado fuera del hosting, backend PHP y catálogo SEO WP. No instalar Node ni un servidor SaaS por defecto.
- GPL-2.0-or-later D-173 en ZIP libre de cobro, con licencia y fuentes correspondientes de lo distribuido. **Repositorio privado no sustituye los derechos de redistribución ni la disponibilidad de fuentes para receptores del ZIP.** Las dependencias necesitan inventario compatible y avisos P0-24.

**Especificaciones de distribución posteriores (2026-11-17):** `docs/31-CONTRATO-ANGULAR-WORDPRESS-ASSETS-P0-20-V1.md` fija el contrato candidato del manifest/entrada ESM/caché y la carga por superficie; `docs/32-LICENCIA-FUENTES-Y-DISTRIBUCION-P0-24-V1.md` desarrolla licencia GPL2-or-later, avisos y archivo de fuentes correspondientes que deben acompañar cada release. Ambos son documentos, **no** manifest generado, LICENSE, inventario npm, ZIP, source archive, prueba de instalación ni publicación real.

## 2. Contrato de empaquetado — directorio raíz fijo

**Árbol lógico objetivo, NO archivos existentes ni scaffold a generar todavía:** `wp-peru-commerce/` como directorio raíz; archivo principal PHP/plugin header; `includes/` lógica servidor (y módulos propios que correspondan); `assets/` con Angular **ya compilado** y manifiesto de nombres con hash/versiones; `languages/` si hay traducciones; `LICENSE`, avisos de licencias de dependencias, instrucciones de instalación/recuperación y código fuente correspondiente según GPL.

- No confiar en nombres concretos de subcarpetas internos hasta fijar P0-20 y el manifiesto de build real. Lo fijo hoy es **una raíz única**, identidad/slug, licencia, límites de ejecución y ausencia de claves en paquete.
- Todos los archivos servibles deben vivir en rutas propias de plugin; nunca incluir en ZIP: credenciales MP, claves de firma, OTP/seguimiento, HMAC/verificadores, dumps SQL de clientes, archivos privados vendidos, logs con PII, copias de identidad, certificados privados ni `node_modules` de build.
- El ZIP debe tener versión de código/plugin y referencia a versión mínima/máxima de esquema compatible. La comprobación de hash/cadena de procedencia del paquete es **propuesta de seguridad**, todavía no se ha definido firma de distribuidor ni canal de entrega; no afirmar cadena autenticada sin sistema real.
- No depender de WordPress.org para actualizar ni registrar automáticamente el plugin. D-175 define distribución por ZIP manual y el dueño decide cuándo instalarlo.

**Dataset y proveedor cartográfico P0-21 (2026-11-17):** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` agrega la verificación de fuente/licencia/version del UBIGEO territorial, catálogo activo por distrito, proveedor OSM best-effort y alternativa manual. **Un fallo de tiles no bloquea checkout; un fallo de catálogo territorial/coverage sí bloquea delivery**, sin generar precio por nombre o coordenadas. No se ha empacado el dataset, ni probado Hostinger, Leaflet, atribución o caché.

## 3. Verificaciones de preflight por instalación (no ejecutadas)

| ID | Verificación que se debe realizar antes de instalar o actualizar | Resultado permitido si falla | Evidencia actual |
| --- | --- | --- | --- |
| LC-01 | Archivo ZIP, directorio raíz | Una raíz `wp-peru-commerce/` con plugin PHP, módulos/archivos estáticos y manifiesto Angular válidos | Confirma solo consistencia documental; ninguna compilación Angular ni ZIP real disponible |
| LC-02 | Compatibilidad | Objetivos WP7.1.4+/PHP8.3+, BCMath, MariaDB10.11+ o MySQL8.4+ InnoDB, HTTPS | Requiere HOST-01…HOST-05 de doc.29; no probado en sitio real |
| LC-03 | Licencias y fuente | GPL-2.0-or-later/avisos de dependencias y fuente correspondiente, según `docs/32-LICENCIA-FUENTES-Y-DISTRIBUCION-P0-24-V1.md` | Pendiente LICENSE íntegro, inventario por versión, fuente real Angular/PHP y mecanismo de entrega del mismo release; repo privado no suple obligaciones |
| LC-04 | Módulos del plugin | Exclusión/activación por capacidad: catálogo SEO, checkout, agenda, pagos, Libro, privacidad, digitales y mapas | No bloquear canal legal obligatorio al deshabilitar componente técnico |
| LC-05 | Esquema | Versión de plugin y versión/etapas del esquema para el sitio; 39 tablas propuestas, PK/índices/FK | Falta DDL implementable, 16 observaciones DB-A, autenticidad del engine |
| LC-06 | Privacidad / secreto | Acceso restringido a settings/credenciales; ninguna clave MP, token, PII sensible o ruta privada en JS/logs/ZIP | No incluir credenciales en artefacto genérico |
| LC-07 | Integración MP | Un solo comercio con cuenta/ambiente propio, tres verificaciones payer ausente, Entrega y PT15M | No probadas en cuenta peruana; bloquea MP sin imponer correo al físico |
| LC-08 | Almacenamiento privado | Descargas PHP autorizadas, protección ante acceso directo y LiteSpeed/CDN; versiones históricas | No verificado en hosting; si falla, deshabilitar venta/descarga digital |
| LC-09 | Banco de datos y retención | Registros de pedidos/pagos/Libro/ARCO/documentos/expedientes protegidos por finalidad | Plazos normativos por comerciante todavía requieren revisión J-01…J-05 |
| LC-10 | Copia previa | Snapshot identificable de BD y archivos, versión ZIP, configuración, secretos necesarios, evidencia MP y hash | No se ha generado copia ni probado restauración |
| LC-11 | Reconciliación | Pagos externos posteriores al punto de recuperación se identifican y gestionan antes del cobro nuevo | Ninguna prueba real de recuperación/MP autorizada |
| LC-12 | Desinstalación | Desactivar/retirar código no purga datos; eliminación final segregada y jurídicamente autorizada | Eliminar plugin WordPress NO certifica retención/supresión de datos |

**Gates propuestos, no estados E-01:** `NO_INSTALADO`, `PREPARACION`, `MIGRACION_INCOMPLETA`, `ESTRUCTURA_VERIFICADA`, `REQUISITO_AUSENTE`, `RECUPERACION_PENDIENTE` son etiquetas **técnicas de diagnóstico**, no nuevos estados de pedidos ni valores a guardar sin contrato físico posterior. La condición de seguridad del backend se calcula en cada petición crítica: plugin/schema, engine, recurso, sistema de pago y almacenamiento. No basta esconder un botón Angular.

## 4. Proceso de primera instalación, upgrade y recuperación

| Fase | Contexto | Procedimiento contractual | Regla de bloqueo o seguridad |
| --- | --- | --- | --- |
| L0 | Preparación ZIP fuera de hosting | Confirmar versión, árbol de archivos, artefactos Angular ya compilados en equipo de construcción, hash/release manifest, LICENSE/avisos y fuente distribuible | Si falla estructura/licencia/fuente no distribuir; no ejecutar Node en Hostinger |
| L1 | Verificación de instalación | Leer compatibilidad WP/PHP/extensiones/InnoDB/HTTPS/almacenamiento, determinar prefijo/sitio, si hay versiones previas, obligaciones de backup y permisos admin | No crear estructura ni habilitar checkout si requisitos críticos faltan |
| L2 | Activación primera vez | Registrar estado de instalación en progreso, semilla de recurso único y versiones, esquema previsto y permisos mínimos según contrato, validar estructuralmente al final | Sólo tras autorización de implementación; sin asumir transacción DDL ni que todas las tablas existan por un retorno exitoso |
| L3 | Preparación de upgrade | Verificar paquete nuevo/firmado por origen y checksum, ruta/versión compatible, backup consistente, reserva de espacio, plan de restauración y estado financiero | Si no existe copia coherente, NO migrar ni sobrescribir |
| L4 | Bloqueo de escrituras incompatibles | Denegar temporalmente en backend checkout/stock/cita/ajustes/pagos privados y nuevas confirmaciones mientras esquema diverge; conservar consulta segura y vías legales cuando sea posible | El frontend no es control de bloqueo; sin dependencias de WP-Cron para imponerlo |
| L5 | Sustitución ZIP y upgrade esquema | Actualizar archivos a través de WordPress, detectar versión de BD en carga segura y correr cada migración idempotente con journal/etapas, validación de invariantes, transición/expansión y backfill | No confiar en register_activation_hook para upgrade; no suponer rollback de DDL |
| L6 | Salida de mantenimiento | Confirmar versión instalada/esquema, tablas InnoDB, invariantes de dinero/agenda, opciones, assets, privacidad, protección de ficheros y colas, enlazar evento de release | Solo levantar módulos con garantías verificadas; MP continúa sujeto a validación peruana aparte |
| L7 | Fallo/restore | Congelar nuevas escrituras comerciales, capturar estado actual/diagnóstico protegido, identificar punto de recuperación y restaurar conjunto completo compatible | No restaurar BD, ZIP o privados en épocas distintas ni reabrir ventas automáticamente |
| L8 | Conciliación posterior | Comparar historial local con MP y caja externa desde corte, importar/reconstruir hechos verificables, comprobar nuevos pedidos/stock/citas/tokens y desatascar outbox sin duplicar | Hasta conciliar, rechazar segundo cargo o doble fulfillment; no afirmar RPO/RTO no medidos |

### 4.1 WordPress y la detección de upgrade

- WordPress documenta instalación manual de ZIP mediante **Plugins → Añadir nuevo → Subir plugin**; si el archivo reemplaza un plugin existente, el flujo real depende del core y sus controles de reemplazo. **No autoriza** instalar el ZIP ahora.
- El hook de **activación no se ejecuta automáticamente en cada actualización**. WordPress documenta comprobar versión de esquema en otro hook; diseñar activación inicial ligera y un control independiente, protegido y serializado de actualización. Un `upgrader_process_complete` registrado en el propio plugin puede ejecutarse en la **versión anterior**, así que no basar la migración crítica solo en ese hook.
- La identidad de la instalación se comprueba por slug/archivo principal, versión del ZIP, prefijo de WordPress y versión DB. `dbDelta()` puede administrar algunos índices/columnas con formato restringido, **no garantiza FK, checks, backfills, DDL atómico, reparación ni reversión**.
- Fijar responsabilidades de metadatos: una versión de plugin distribuido, una versión de esquema aplicada por **sitio**, un historial de migraciones con checksum y una barrera de capacidad para cada operación de escritura. La versión de frontend/manifest debe corresponder exactamente al backend que sirve sus DTO; nunca aceptar cache antigua de checkout con API incompatible.
- En multisite, no inferir que activar en red crea 39 tablas para cada blog ni comparte identidades: cada sitio necesita su propio esquema y control. El alcance de activación de red es **PENDIENTE** de compatibilidad; no se promete soporte multisitio sin diseño/autorización específicos.

### 4.2 Migraciones idempotentes, separadas de transacciones de venta

1. Al cargar una versión nueva, detectar **esquema esperado vs. instalado** antes de habilitar escrituras comerciales. Evitar ejecutar DDL costoso en todas las peticiones; solo iniciar/retomar proceso protegido y serializado cuando hay desfase de versión.
2. Requerir **backup utilizable antes de mutar esquema** y reportar los datos que faltan; si el administrador no lo tiene, la instalación queda detenida, no inventar restauración.
3. Un único dueño de proceso por sitio con identificador de intento y marca de tiempo recuperable; impedir dos migradores simultáneos. El mecanismo de lock físico se especificará cuando P0-18/P0-19 estén confirmados (no asumir que un `transient` WordPress es mutex suficiente).
4. Plan por etapas de `docs/28`: M0 preflight, M1 dominio, M2 pedidos, M3 dinero/stock, M4 archivos/derechos, M5 casos/normativa, M6 aceptación. Para cambios evolutivos: **expandir** primero, migrar/backfill por lotes acotados, **verificar** invariantes y activar lectura/escritura nueva; cualquier contracción destructiva requiere política legal y una release independiente.
5. Cada etapa registra versión, checksum, intento, progreso y resultado verificado; no marcar éxito hasta comprobar PK/UQ, engine InnoDB, columnas, locks necesarios y referencias. Las migraciones fallidas tienen diagnóstico y reentrada idempotente, **no un rollback automático prometido**.
6. DDL (`CREATE`/`ALTER`/`DROP`) puede provocar commit implícito en MariaDB/MySQL, incluso frente a fallo; no mezclarlo con transacciones financieras ni pretender que un único `ROLLBACK` revierte upgrade. Restaurar un respaldo coherente después de confirmar qué cambios externos ocurrieron.
7. No usar `activate`/inicialización como permiso para purgar, emitir MP, enviar comunicaciones masivas ni recalcular importes históricos. Bloquear rutas que pueden romper integridad, mantener seguridad de reclamos/legal y diagnóstico donde sea posible.
8. **La función de reparación/migración y sus endpoints no están en E-08**. La V1 usa flujo administrador WordPress nativo/activación, sin inventar REST comercial adicional ni capability global. Ningún SQL o código de migrador se redacta en esta fase.

## 5. Backup: conjunto mínimo, consistencia, seguridad y límites de Hostinger

### 5.1 Contenido del respaldo lógico

| Parte | Qué incluye | Por qué importa |
| --- | --- | --- |
| BD WordPress y tablas PeruCommerce | Catálogo WP, pedidos/líneas, intentos MP, movimientos, holds/stock, calendario/citas, inbox/outbox, idempotencia, casos/Libro/ARCO, versiones, opciones y permisos | Recuperar eventos y correlaciones evitando segundo cargo/doble asignación |
| Archivos plugin y assets publicados | ZIP/versiones PHP/Angular/manifest, LICENSE y avisos de dependencias | Evitar código/DTO incompatible tras restaurar |
| Almacenamiento digital **privado** | Todos los binarios ligados a derechos históricos y sus versiones; hashes y permisos | Restaurar BD sin binarios puede privar al comprador de su derecho; **nunca** copiar al webroot público |
| Configuración protegida | Parámetros necesarios y secretos de MP/HMAC/cookie/firma bajo custodia cifrada y accesos mínimos, referencia de ambiente y host | Si se restaura BD pero falta la clave criptográfica legítima, no prometer acceso/conciliación; jamás loguear/restaurar credenciales a un sitio de pruebas sin control |
| Manifiesto del punto de recuperación | Fecha UTC, sitio/prefijo, versión plugin/schema, IDs/checksum de copia BD y archivos, zona horaria, alcance, ubicación privada y quién creó copia | Demostrar que BD/archivos/secretos pertenecen a **la misma época**, o advertir inconsistencias |
| Libro operativo externo | Referencia del último pedido/operación y última conciliación MP; evidencia contable accesible a administrador con finalidad | Reconstruir período entre backup y fallo sin pretender que un backup local captura operaciones del proveedor |

**Ninguna copia del ZIP genérico debe contener secretos o datos de un comercio.** El conjunto de recuperación es independiente, cifrado/limitado y no se distribuye junto al plugin.

### 5.2 Coherencia y punto de recuperación

- Antes de tomar una copia que se vaya a usar como recovery point, establecer una **barrera de escritura local** de checkout, ajustes, agenda y mutaciones de archivos. Esperar a que transacciones SQL activas terminen o se reconcilien y capturar de forma compatible **BD + ficheros privados + configuración**; registrar un mismo ID de punto de recuperación y hashes de cada parte. Si la copia se hace con ventas simultáneas sin snapshot/pausa coherente, **no afirmar consistencia**.
- La barrera de escritura local **no detiene pagos iniciados en Mercado Pago** ni eventos externos en vuelo. El manifiesto de recuperación documentará corte UTC, intentos MP ambiguos/pendientes, último recurso externo verificado y referencias de correlación; incluso con snapshot local consistente, conciliar la cuenta del proveedor sigue siendo **obligatorio** antes de reabrir.
- Un backup de Hostinger de sitio WordPress puede restaurar **archivos y BD juntos**. **No se ha verificado** que incluya archivos privados ubicados fuera del alcance del backup, claves almacenadas por medios externos, todos los sitios multisitio o integraciones remotas. Se requiere inventario explícito por instalación, sin dar por cubierto lo que no entra en la copia.
- Hostinger publica backups automáticos **semanales** en web/cloud y diarios en algunos planes, con retenciones publicadas que pueden variar por producto/plan; **no elegir RPO/RTO comercial** solo con esas cifras. La guía de descarga permite obtener copias de ficheros y BD; una descarga o archivo existente **no demuestra restore exitoso**.
- Restaurar una copia revierte el estado al instante de captura: **se pueden perder modificaciones posteriores** del WordPress, incluidas órdenes que el comprador pagó en MP. No ejecutar reembolso ni cobrar de nuevo por reflejo; reconciliar primero.
- Conservar backup de **estado actual previo a restaurar**, para no perder evidencia más reciente, siempre que pueda protegerse y no sobreescribir respaldos. Registrarlo como copia distinta del punto elegido; no mezclar automáticamente con el respaldo anterior.
- Retención de backup **por categoría/fundamento** D-146 y J-01…J-05; cuenta de hosting determina ubicación/terceros y eventual transferencia. No declarar purga instantánea de todas las copias por un pedido ARCO, ni usar respaldos indefinidos sin finalidad/seguridad.

### 5.3 RPO/RTO y controles

**RPO** = máximo de datos temporalmente perdidos admisible; **RTO** = tiempo de recuperación admisible. **Ambos NO DEFINIDOS NI DEMOSTRADOS** en este proyecto. Se deberán fijar por instalación y probar con autorización antes de prometerlos. No convertir frecuencia nominal Hostinger en recuperación de operaciones externas garantizada.

## 6. Restauración segura y reconciliación de pagos

1. **Contención:** deshabilitar mutaciones de checkout/stock/cita/caja, preservar evidencia local existente y rechazar nuevas órdenes MP mientras no haya verdad reconciliada. Una página de catálogo y canal legal se pueden mantener si seguros; no exponer estado privado.
2. **Identificar respaldo:** elegir punto con versión de plugin/schema, hash y ubicación, examinar si incluye BD, ficheros privados, configuración y secretos pertinentes. Si falta un elemento, no tratarlo como restore total.
3. **Restaurar conjunto compatible** en una operación autorizada/planificada. Almacenes de binarios protegidos permanecen fuera de URLs públicas y se valida versión activa/histórica.
4. **Preflight posterior:** engine/tablas/índices/versiones y barreras críticas, integridad de hashes, cuenta/ambiente MP, credenciales protegidas, permisos/finalidades, cookies/tokens y asset manifest. Si cambian claves usadas para verificadores, pueden quedar invalidaciones; atender vía reemisión con identidad, nunca reconstruir secreto en claro.
5. **Conciliación MP por servidor y periodo:** partir de IDs de pedido, idempotency key y provider_order_id locales que sobrevivan; revisar transacciones externas **desde el corte del backup hasta el momento del incidente** (y casos ambiguos previos), contra cuenta/ambiente PEN e importes pactados. Cualquier acreditación externa sin pedido local o con hold vencido → expediente de conciliación admin y nueva verificación de recursos D-74, no segundo cargo ni resurrección automática de hold.
   **Vigencia posterior al restore:** el UTC efectivo manda: restaurar una fila antigua no renueva el hold de 15 min, OTP de 10 min, seguimiento de 90 días ni token digital de 24 h; tampoco borra el derecho de compra por caducar el acceso. Los verificadores revocados desde el último backup requieren análisis/rotación para no reactivar acceso de forma indebida.
6. **Dinero y caja:** recuperar asientos locales de cash, devoluciones externas y comisiones con evidencia no contaminada; no declarar efectivo recibido sin comprobación de caja, no registrar refund sin resultado externo verificado.
7. **Recursos:** recalcular compromisos/stock/slots desde eventos reconciliados con locks bajo E-05; si la reserva se perdió y hay venta acreditada, resolver por administrador conforme derechos del cliente, sin sobreventa ni cita duplicada.
8. **Comunicaciones:** procesar inbox/outbox durable idempotente, deduplicar avisos cuando exista prueba, no fingir email recibido por `wp_mail()` y no publicar enlace secreto en auditoría. Reestablecer consulta de pedido solo con autorización propia.
9. **Salida controlada:** levantar operaciones compatibles por módulo solo tras datos financieros, disponibilidad y privacidad comprobados, documentar intervalo no recuperado y pendientes, y no afirmar producción sana desde una revisión estática.

**Si no se puede consultar fuente MP completa o el sitio no tiene correlación suficiente**, mantener bloqueo de nuevo cobro y abrir expediente manual; es preferible operación no disponible a doble cargo. Un backup local **no es autoridad del estado externo**.

## 7. Deactivación, uninstall y eliminación separada

| Acción | Efecto admitido según D-146/D-175 | Prohibición |
| --- | --- | --- |
| Desactivar temporalmente | Detener rutas/recursos operativos; conservar tablas, versiones, Libro, pedidos, token hashes y referencias; recordar avisos en cola que queden fuera de transporte | No purgar ni desprogramar legalmente el derecho del consumidor |
| Reinstalar ZIP compatible | Reconocer esquema/datos existentes por sitio y versión, exigir preflight y reconciliar dinero ambiguo si hubo intervalo apagado | No crear nueva tienda vacía sobre transacciones previas |
| Borrar archivos del plugin desde WordPress | Mantener datos comerciales y privados legalmente retenibles; la desinstalación del core puede llamar un `uninstall.php`/hook, **debe diseñarse para ser no destructivo por defecto** | Ningún DROP TABLE, borrado de pedido/Libro ni carpetas de binarios sin aprobación separada |
| Purga real al finalizar obligaciones | Procedimiento **fuera de desinstalación automática**, administrador y responsable validan categorías, plazos, disputas, soporte del comprador, backup y constancias | No suponer que `DELETE` en BD borra todo en backups externos ni afirmar borrado de MP |
| Solicitud ARCO | Evaluar derechos por identidad/finalidad, conservar solo lo legalmente necesario y registrar causa/respuesta | No borrar instantáneamente impuestos, Libro, eventos de reembolso o derecho digital vigente |

**Matiz WordPress:** su manual distingue desactivación (limpiar estados temporales) de uninstall (puede eliminar opciones/tablas). **Para PeruCommerce se elige explícitamente una política más conservadora** D-146/D-175: no purgar datos automáticamente al desinstalar, aun cuando WordPress permita técnicamente hacerlo. Si se utiliza `uninstall.php`, debe controlar `WP_UNINSTALL_PLUGIN` y aplicar *solo* limpieza no destructiva expresamente aprobada; ninguna ruta de purga REST está autorizada en V1.

**Implicación importante:** si se borran todos los archivos del plugin, el comercio podría perder acceso práctico a la bandeja del Libro/ARCO y compras históricas. Advertir y exigir exportación/custodia administrativa y continuidad del canal legal **antes** de retirar el código. No prometer cumplimiento por conservar las tablas pero dejar la atención inaccesible.

## 8. Matriz de fallos y reacción exigida

| ID | Incidencia | Contención / recuperación esperada |
| --- | --- | --- |
| F26-01 | Falló carga/estructura ZIP | No activar; conservar versión previa intacta si WordPress no completó sustitución; verificar directorio/manifest antes de reintentar |
| F26-02 | PHP/BCMath/BD incompatibles | Negar rutas comerciales que dependen de exactitud/integridad; conservar catálogo/reclamo cuando sea seguro |
| F26-03 | Falló DDL parcial o CHECK de índice | Persistir marcador de esquema incompleto; impedir ventas/agenda, respaldar estado y reparar/restore completo; no marcar versión final |
| F26-04 | Falló filesystem durante ZIP reemplazo | Mantener mantenimiento y restaurar artefactos compatibles; no volver a un ZIP incompatible con BD actual |
| F26-05 | Falló SMTP/correo | Outbox pendiente, sin revertir pedido ni suponer recibido; informar al administrador |
| F26-06 | Falló WP-Cron | Retenciones vencen al leer/actualizar estado con hora efectiva; avisos y conciliación quedan pendientes de recuperación |
| F26-07 | MP timeout o webhook después de restore | Bloquear nuevos intentos, recuperar correlación con MP y asentar hechos externos comprobados idempotentes |
| F26-08 | Pago aprobado fuera, pedido local desapareció tras restore | No cobrar de nuevo; crear expediente de conciliación y reconstrucción auditada según evidencia y derechos del comprador, sujeto a nueva asignación de recursos |
| F26-09 | Backup sólo BD o sólo webroot | No declarar restore completo: faltan archivos privados/credenciales/instalación/estado financiero; mantener módulos afectados deshabilitados |
| F26-10 | Token HMAC deja de verificar tras cambio de claves | No copiar secreto a logs ni revalidarlo artificialmente; invalidar/reemitir por identidad comprobada, sin caducar derechos comerciales |
| F26-11 | Se recuperó un archivo digital antiguo | Derechos históricos y versiones activas deben apuntar al binario correcto; si no hay archivo, bloquear descarga y abrir expediente sin servir público |
| F26-12 | Uninstall/delete plugin WordPress | No interpretar como autorización a borrar órdenes/cobros/Libro; ejecutar política no destructiva; purga distinta tras revisión legal |
| F26-13 | Dos procesos de upgrade en paralelo | Un único dueño de migración por sitio; otro proceso informa pendiente/error, no ejecuta dos backfills ni reescribe versión |
| F26-14 | Reproducción de notificaciones tras restore | Eventos outbox/inbox conservan idempotencia; diferencia entre intento enviado y recepción real, no repetir hechos comerciales |

## 9. Escenarios de aceptación documental — NO EJECUTADOS

| ID | Escenario | Resultado correcto |
| --- | --- | --- |
| ZIP-X01 | ZIP de plugin sin LICENSE o sin código fuente correspondiente | Bloquear distribución comercial hasta completar avisos y licencia |
| ZIP-X02 | ZIP con dos carpetas raíz, dist Angular incompleto | Rechazar instalación; no aceptar pantalla vacía como activación válida |
| ZIP-X03 | Primer install en Hostinger sin BCMath | Bloquear módulo que requiere cálculos exactos; no cambiar a flotantes |
| ZIP-X04 | DB tiene MyISAM en tabla de pedidos | No habilitar checkout/citas ni prometer atomicidad |
| ZIP-X05 | Código nuevo y schema previo, hook activación no dispara | Detector versión lanza proceso autorizado en mantenimiento, nunca operar con esquema viejo |
| ZIP-X06 | Migración agrega columna pero falla índice único | Estado schema incompleto, persistencia histórica intacta, no marcar éxito |
| ZIP-X07 | Dos solicitudes activan migración simultáneamente | Lock de migración y verificación de ownership/version; sin duplicar |
| ZIP-X08 | Usuario reinstala ZIP previo después de DDL nuevo | No prometer rollback; requerir restore compatible o impedir ejecución |
| ZIP-X09 | Copia Hostinger de webroot no incluye privado fuera de webroot | Backup no supera preflight, digital deshabilitado hasta recuperar archivos |
| ZIP-X10 | MP aprobó pedido durante/tras punto de copia | No crear segundo cargo; conciliación servidor y expediente antes de reabrir |
| ZIP-X11 | WP-Cron suspendido al actualizar | Holds se evalúan por tiempo UTC y expiración efectiva |
| ZIP-X12 | Restaurar DB anterior con ficheros digitales nuevos | No reasignar binario histórico ni entregar versión distinta; bloqueo controlado |
| ZIP-X13 | Cookie/token privado aparece en backup de logs | Backup invalido para distribución no restringida; secretos filtrados y medidas E-07 |
| ZIP-X14 | Físico solo teléfono exige reemitir enlace tras restore | Validación humana proporcional, no pedir email ficticio ni DNI universal |
| ZIP-X15 | El comerciante desactiva módulo Libro obligatorio | Mantener alternativa legal accesible y advertir configuración inválida |
| ZIP-X16 | Desinstalar plugin con obligaciones fiscales sin vencer | No purgar historial ni datos requeridos |
| ZIP-X17 | Backup hoster automático se encuentra fuera del RPO requerido | Informar indisponibilidad de punto exacto; no prometer recuperar cobros/trabajos entre copias |
| ZIP-X18 | Se restablecen envíos de email tras corte | Revisar outbox e identidad de eventos, no afirmar entrega ni duplicar decisiones de negocio |
| ZIP-X19 | Restaurar sin conservar identificadores MP ni secreto del entorno | No reabrir MP; conciliación manual por cuenta/periodo, no regenerar order a ciegas |
| ZIP-X20 | Actualización y checkout coinciden | Servidor rechaza escritura crítica por version gate hasta integridad comprobada |

Los escenarios son **criterios futuros**. No son pruebas completadas, ni resultados de WordPress/Hostinger/Mercado Pago reales.

## 10. Fuentes oficiales contrastadas

| Ref | Publicación | Consecuencia para P0-26 | URL |
| --- | --- | --- | --- |
| SRC-01 | WordPress, Create Tables with Plugins | `dbDelta()` y versiones DB; **activation hook no corre en upgrade** | https://developer.wordpress.org/plugins/creating-tables-with-plugins/ |
| SRC-02 | WordPress, `upgrader_process_complete` | Puede ejecutarse desde la **versión anterior** del plugin durante update | https://developer.wordpress.org/reference/hooks/upgrader_process_complete/ |
| SRC-03 | WordPress, Activation/Deactivation Hooks | La deactivación no es eliminación final de datos | https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/ |
| SRC-04 | WordPress, Uninstall Methods | Es posible borrar datos con uninstall, pero aquí **NO por defecto** | https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/ |
| SRC-05 | WordPress, Manage Plugins | Subida manual de ZIP en admin | https://wordpress.org/documentation/article/manage-plugins/ |
| SRC-06 | MariaDB, Implicit Commit / DDL | DDL puede confirmar transacción implícita y no revertirse por rollback SQL | https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit |
| SRC-07 | Hostinger, Restore WordPress Website | Restaurar sitio y DB a fecha previa; archivos/BD separables según disponibilidad | https://www.hostinger.com/support/3989447-how-to-restore-a-wordpress-website-from-backups-in-hostinger/ |
| SRC-08 | Hostinger, Download Backups | Backups por plan; ficheros y BD; límites de retención publicados y ubicación | https://www.hostinger.com/support/5981435-how-to-download-backups-at-hostinger/ |
| SRC-09 | Hostinger, Restore Backups | El restore revierte también cambios **posteriores** al punto seleccionado | https://www.hostinger.com/support/4283700-how-to-restore-backups-at-hostinger/ |
| SRC-10 | WordPress, Upgrader ZIP | WordPress soporta sustitución de archivo ZIP previo con controles propios | https://developer.wordpress.org/reference/classes/plugin_upgrader/install/ |

## 11. Pendientes de cierre técnico y estado de autorización

**Documentado ahora:** procedimiento L0…L8 (9 fases), 12 preflights LC, 14 fallos F26 y 20 escenarios ZIP-X; límites de backups, uninstall, instalación/upgrade y reconciliación externa, con fuentes oficiales.

**Todavía sin cierre físico/operativo:** (a) ZIP y manifiesto Angular P0-20 reales; (b) 16 fallos de DDL DB-A corregidos y SQL/migraciones revisados contra MariaDB/MySQL; (c) checks HOST-01…12 en la instalación real; (d) política de claves/backups y ubicación privada por host; (e) validaciones de MP Perú de E-08; (f) plazos normativos J-01…J-05 por comercio; (g) pruebas autorizadas de upgrade/migración/restore y definición de RPO/RTO; (h) autorización explícita de implementación.

**No ejecutado:** pruebas, compilación, migraciones, instalación ZIP, restore de copias, consulta de hPanel, creación de cuentas ni conectores/servicios MP. La aprobación anterior de D-175 es **política**, no autorización de ejecutar.
