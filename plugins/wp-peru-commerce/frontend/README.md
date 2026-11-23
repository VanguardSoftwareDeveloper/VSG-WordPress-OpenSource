# Fuente Angular — PeruCommerce (IMP-54…IMP-67)

Este directorio contiene **fuente**, no un release compilado ni un frontend instalado. Angular 22, TypeScript 6.0.x y RxJS 7.8.x según [compatibilidad oficial](https://angular.dev/reference/versions). La instalación de dependencias, lockfile, compilación, manifest, verificaciones y ZIP corresponden a IMP-67/69/72 con permiso de ejecución. No se ejecutó npm/Node/Angular.

## Montaje WordPress

El plugin registra shortcodes:
- [perucommerce_app scene="cart"] en página publicada con slug carrito.
- [perucommerce_app scene="checkout"] en página publicada con slug checkout.
- [perucommerce_app scene="return"] en página publicada exclusiva de retorno.
- Menú propio PeruCommerce en administración.

El catálogo HTML sigue renderizado por PHP. Enlaza al carrito solo si hay página y bundle válidos. La plantilla privada de seguimiento (seguimiento) y la de descarga (descarga) son autónomas y no importan Angular ni scripts de terceros.

El backend lee exclusivamente el manifest assets/app/pc-assets.json producido a partir de una compilación real: frontend_api=peru-commerce/v1, entry_module, stylesheets, files (array con path, sha256 hex minúsculo y bytes de cada JS/CSS/chunk/media). Comprueba rutas, inventario, tamaño y SHA-256 físico de cada archivo. **Sin manifest válido, no se carga el frontend.** IMP-67 debe producirlo a partir del output real del builder, no de nombres inventados.

## Responsabilidades

- Carrito: IDs de variante, cantidades y una franja temporal; sin hold. Solo se usa sessionStorage para estos datos no sensibles. Precio y disponibilidad siempre proceden del servidor.
- Checkout: recotización R8-05, contacto mínimo por tipo, entrega manual, políticas versionadas, una cita y medio elegible. 202, error de red y 409 no confirman fondos ni autorizan otra compra. El secreto de seguimiento se mantiene en memoria y se canjea por cookie HttpOnly al entrar en el proveedor MP.
- Retorno R8-18: ningún parámetro del proveedor acredita un pago. GET privado únicamente con sesión guest válida.
- Panel A/O: verifica sesión en R8-19. IMP-61 incorpora catálogo, stock y archivos con permisos A/O reales. IMP-62 incorpora agenda y citas. IMP-63 incorpora operaciones de pedidos. IMP-64 incorpora casos y finanzas exclusivamente A. IMP-65 incorpora Libro y privacidad. IMP-66 incorpora configuración, bandeja y diagnóstico. IMP-67 prepara scripts/fuente de build y manifest (sin ejecutarlos). Las pistas de UI nunca sustituyen current_user_can ni el permiso por recurso/estado del backend.
- Acciones sensibles: la página privada seguimiento permite solicitar OTP, verificar grant por finalidad y abrir expediente. Solo solicita revisión, no ejecuta reembolso/cambio ni elimina el derecho a un archivo cuando caduca el enlace. Canal manual para físico solo teléfono.

## IMP-61: gestión administrativa

El catálogo privado ofrece lista paginada R8-31, alta y edición R8-32/33, visibilidad R8-34, ajustes de stock R8-35, archivo digital inicial R8-48, descarga auditada R8-62 y reemplazo versionado R8-63. Un administrador A puede publicar y gestionar binarios; el operador O solo puede editar, ocultar y ajustar inventario cuando tiene sus capacidades vigentes. Los datos de asignación digital son exclusivos de A con catálogo+archivos, y los archivos de ventas previas no se sustituyen al cambiar versiones para ventas futuras. El navegador mantiene la misma clave y carga en memoria ante resultados inciertos, y la autorización final sigue siempre en el servidor.

## IMP-62: agenda y citas

La pantalla administrativa de agenda consume R8-36 para leer citas, cierres (closure_id y row_version) y la revisión semanal del recurso único 1; R8-37 distingue create/update/retire de cierres; R8-53 registra un nuevo horario semanal versionado sin desplazar citas; R8-38 reprograma solo citas CONFIRMADA manteniendo duración; R8-54 registra REALIZADA o NO_ASISTIO exclusivamente para administrador A con momento de observación y evidencia. La consulta opcional R8-04 de franjas no genera reservas.

La fecha y las horas semanales respetan la zona IANA del negocio. Los campos de edición de instantes se etiquetan UTC y se convierten a RFC3339 con Z; la interfaz muestra su equivalencia en la zona comercial. Un 409, respuesta incierta o fallo de red conserva el body y la misma clave de idempotencia, la cita seleccionada y versiones originales. Cada mutation REST comprueba permisos y estado en PHP; ocultar controles para O no los autoriza. Sin validación funcional/cross-browser ni compilación.

## IMP-63: gestión operativa de pedidos

El componente administrativo de pedidos usa R8-20/21 para listar y leer exclusivamente las proyecciones permitidas al actor. Las líneas físicas presentan por separado quantity histórica, committed_units, released_units, stock_out_units y pending_stock_out_units. El indicador de pago NO autoriza la cancelación ni demuestra el dinero real recibido; el backend verifica hechos y estado por operación.

R8-22 cambia PREPARANDO/LISTO/DESPACHADO/ENTREGADO conforme estado, modalidad de pickup/delivery y evidencia, con CAS de versión de línea y salida física única. R8-23 cancela la línea completa solo en un contexto ordinario elegible sin dinero; R8-24 registra efectivo neto declarado y realmente recibido solo si la cuenta de contraentrega y saldo completo se revalidan en el backend. R8-28 queda como propuesta del operador O: la aprobación A puede generar crédito por cambio de tarifa y se gestionará con contexto financiero en IMP-64. R8-55 permite abrir expediente de no entrega solo A, sin resolverlo ni alterar el saldo.

Los intentos mutables conservan el cuerpo JSON, ID, versión y clave de idempotencia mientras el resultado sea incierto, incluso tras GET para revisar; los errores 409 no se convierten en nuevo intento. El administrador y el operador solo ven pistas UI compatibles con R8-19; la API WordPress revalida cookie/nonce, actor, capacidad, recurso y estado. Se conserva el estado de esta pantalla al navegar entre secciones y se bloquea su desmontaje mientras tenga una operación ambigua.

**No son entregables de IMP-63:** conciliación, corrección de efectivo, reembolsos externos, resolución de casos, aprobación financiera de dirección, cambios masivos, importaciones ni confirmaciones de cobro inferidas de la vista.
## IMP-64: administración de casos y finanzas

La sección administrativa exclusiva A en admin-page carga `AdminFinance`, `AdminCases` y `AdminReconciliation` diferidamente. Cada operación se muestra según las capacidades reportadas por R8-19, y cada REST revalida actor, instalación, nonce, recurso, caso, estado y versión. No se transmite información financiera/casos a O por compartir interfaz.

Los casos físicos parciales siguen el flujo R8-69 → R8-21?case_id → R8-26 (aceptar delta exacto y cerrar atómicamente) O R8-70 (rechazar/retirar con nueva verificación). La apertura exige revisión real de teléfono original y segundo hecho privado; el acuse solo abre expediente, sin efecto financiero. Los casos logísticos R8-56/57 se proponen/resuelven documentalmente con última versión de caso, sin simular entrega o refund. Casos cerrados no reabren.

La sección financiera separa R8-25 correctivo de efectivo, R8-26 ajuste comercial, R8-27 registro de devolución EXTERNA ya ejecutada, R8-64 referencia de documento EXTERNO sin SUNAT y R8-28 aprobación A de dirección con consecuencias posibles de crédito comercial. R8-29/30 consulta conciliaciones MP: nunca crea segunda order, revive holds, inventa un pedido pre-HTTP, acredita un webhook sin GET oficial ni ejecuta reembolso. Para servicio con nueva cita exige verificador de acuerdo realmente registrado en backend. El emisor fiscal P-C es obligatorio; si falta, R8-64 permanece denegado.

Un `AdminOrder` existente NO expone propuesta completa de dirección/fee previo y nuevo; no deducir impacto monetario del `payment_indicator`. La aprobación requiere comprobación independiente de propuesta, cobertura y crédito antes de POST, además de revalidación transaccional del servidor. Es un riesgo explícito a evaluar antes de habilitar uso comercial.

El navegador mantiene un solo intento mutador entre finanzas, casos y conciliación: mismo cuerpo, expected_version y UUID ante 409/red/5xx. Permite GET para comprobar, sin replicar el efecto; lectura fallida tras POST confirmado no revoca el resultado. El gestor evita navegar a otra sección con un intento incierto y no persiste secretos/PII en Web Storage.
## IMP-65: Libro, ARCO y constancias legales

El formulario público legal es el shortcode WordPress `perucommerce_libro_reclamaciones` de IMP-48 (no escena Angular adicional). Mantiene Origin/CSRF, idempotencia, campos propios de la modalidad vigente del Libro y entrega `ComplaintReceiptCopy` tras 201, sin cuenta ni compra. IMP-65 aporta estilo responsive/print dedicado y guarda el JSON y la misma clave en memoria si la red no determina el resultado; solo 400/422 explícito libera la edición para un nuevo intento, nunca consulta folios desde URL. Tras 201 se imprime la copia original sin depender de notificación por email.

El panel interno A-only incorpora `AdminLegal`, `AdminComplaints`, `AdminPrivacy` (R8-41/42/43/44/50/65/66/67/68) con secciones separadas y carga diferida. El listado no expone expediente íntegro; cada GET de detalle requiere capacidad específica, caso e instalación. La respuesta de Libro R8-42 se distingue entre **preparada sin entrega comprobada** y **respuesta con evidencia de entrega real verificada**, usando versión y referencia privada del mismo expediente. La resolución ARCO R8-44 distingue texto preparado de entrega/resolución acreditada y respeta inventario legal de retención.

R8-50 entrega 202 = aceptada/pendiente; el progreso se consulta en `PrivacyRequestDetail.export_operations` de R8-66 (`pending`, `ready`, `failed`, `expired`). Solo un archivo listo, vinculado a este expediente y aún vigente puede descargarse mediante R8-68. Evidencias ordinarias R8-67/68 se descargan en memoria por HTTPS same-origin, nonce, caso/propósito y validadores PHP; URL Blob temporal revocable, no Storage ni enlaces de webroot. Los formularios congelan cuerpo, expected_version y clave ante 409/red/5xx; no navegan a otro expediente mientras haya intento incierto.

Para ARCO se conservan la solicitud WordPress confirmada por correo y el canal manual legítimo existente. **No se creó un endpoint ni formulario público alternativo**, porque no están aprobados y una cuenta/correo/pedido no puede ser obligatoria para ejercer derechos; la verificación de identidad legal sigue siendo independiente de la confirmación de correo. La recepción manual depende de verificadores P-C reales y no se presenta como disponible universalmente.
## IMP-66: configuración, bandeja y diagnóstico

admin-operations.ts/html agrupa con carga diferida cuatro superficies. admin-settings.ts/html usa R8-45/46 para identidad comercial/legal, régimen/tipos declarativos, condiciones/privacidad append-only, cuotas futuras, flags delivery/pago, remitente wp_mail sin SMTP, preferencia de emails rutinarios y proveedor de mapa con atribución/licencia/CSP. R8-46 envía solo campos modificados: omitir conserva; map_tiles enviado reemplaza todo el subobjeto y digital_download_limit=null elimina la cuota solo para ventas futuras. No desactiva Libro ni altera avisos esenciales, pedidos, derechos digitales históricos o comunicaciones ya en cola.

R8-47 requiere rol A y ambas capacidades settings_manage + integrations_manage con row_version de R8-45. El secreto MP solo se introduce en PUT desde formulario protegido, no vuelve en GET ni se guarda en Web Storage; se limpia tras confirmación o abandono, y ante resultado incierto conserva exactamente el mismo cuerpo en memoria. Una rotación sin nuevo webhook_secret deja la entrada nueva sin ese secret, mientras versiones históricas cifradas siguen disponibles para conciliación. provider_configured no demuestra integración MP real.

admin-coverage.ts/html consume R8-51/52 con versiones/dataset actual, búsqueda local (máximo 30 filas visibles) y un distrito UBIGEO/tarifa PEN exacta por PATCH, conservando todas las demás reglas y pedidos históricos. El servidor revisa cobertura y dataset INEI bajo locks; habilitar mapa no sustituye esa revisión y no carga teselas desde el panel.

admin-notifications.ts/html consume R8-39/40 A/O: solo metadatos mínimos de la intención, prioridad e indicador de transporte. `can_resend` habilita únicamente reintento de EMAIL original fallido/autorizado; no convierte PANEL, avisos omitidos por preferencia ni hechos antiguos en un correo nuevo, y reencolar no significa entregar. admin-diagnostics.ts/html R8-49 es solo lectura A de indicadores seguros, no prueba real de InnoDB, locks, SMTP, cron, storage, MP o licencia.

Los formularios retienen expected_version e idempotency_key en operaciones inciertas, bloquean navegación y no transmiten secretos a otros componentes. Sin ejecución de PHP/SQL, npm/Angular/TypeScript, pruebas, HTTP de negocio, servicios, migraciones, ZIP o despliegue. P-C/P-D y WCAG siguen pendientes; IMP-67 aborda fuente/configuración de distribución sin inventar manifest/hash/lockfile ya resuelto.
## IMP-67: fuente de assets, manifest y distribución

Se separa el output temporal del builder Angular (`dist/peru-commerce-ui/browser`) de `../assets/app` servido por WordPress. La configuración de producción usa ESM application, `outputMode=static`, `outputHashing=all`, CSS crítico NO inline y extracción de avisos de licencia. El componente raíz usa cuatro bloques `@defer (on immediate)` por escena y solo el WordPress de esa página encola el ESM; el catálogo indexable PHP no carga Angular por defecto. El navegador generado y los chunks NO están presentes hasta ejecución autorizada.

El comando `npm run build` invocará en una fase autorizada el wrapper `scripts/build-browser.mjs` con checkout limpio, npm lockfile real confirmado y Angular CLI instalado. El wrapper borra únicamente el dist temporal, compila y registra procedencia con Git SHA completo, SHA de lockfile/config y hashes del browser realmente emitido. `npm run prepare:assets` exige esa procedencia, descubre `entry_module`/styles desde `index.html` generado, comprueba rutas de chunks/media, licencias extraídas y bytes/hash, y prepara `assets/app/pc-assets.json` del mismo release sin sobreescribir otro build. `npm run verify:assets` contrasta el output manifest contra los archivos reales. **Ninguna herramienta se ejecutó en IMP-67.**

El lector `includes/angular-surfaces.php` ahora exige schema de manifest `perucommerce-assets-v1`, release/plugin/schema/API, `source_release`, `build_id`, inventario SHA-256 de cada JS/CSS/media y notices reales. Faltan fuentes/assets/hash o no coincide la versión: no se monta Angular. El root WordPress utiliza URLs relativas `plugins_url`, importación ESM y CSS separado, sin Node/SSR en hosting.

Existe `LICENSE` GPL v2 íntegra y `tools/stage-distribution.mjs` como fuente para staging de dos árboles vinculados al mismo commit: plugin instalable PHP+assets vs fuente correspondiente con PHP/TS/HTML/CSS, scripts, lockfile, LICENSE y notices. No crea ZIP; la auditoría de licencias de dependencias y el empaquetado final se difieren al release real IMP-72. Instrucciones en `distribution/SOURCE-INSTRUCTIONS.md`.

**Bloqueos actuales:** `frontend/package-lock.json` no existe ni se inventó, no se instalaron dependencias, no hay `frontend/dist`, `assets/app`, `pc-assets.json`, notices de build, staging, ZIP ni paquete fuente. No se ejecutaron Node/npm, TypeScript, build Angular, PHP/SQL, tests, navegador o despliegue. La correspondencia de salida, capacidades WP, CSP/cache/LiteSpeed y GPL real se validarán en IMP-72 autorizada.
## Validación pendiente

No se ejecutaron PHP/lint, SQL, compilaciones, pruebas, navegación, CSP, servicio, instalación, migración ni despliegue. Permanecen pendientes WP real en sitio/subdirectorio, concurrencia, privacidad, provisión P-C por comercio, reglas MP, WCAG 2.2 AA, toque 44×44 CSS, dependencias verificadas, manifiesto/asset fingerprint, licencia GPL, ZIP y restauración.
