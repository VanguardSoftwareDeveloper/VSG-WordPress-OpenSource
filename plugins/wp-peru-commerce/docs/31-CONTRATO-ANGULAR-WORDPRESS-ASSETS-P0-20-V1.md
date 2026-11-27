**PRECEDENCIA VIGENTE D-210 (27-11-2026):** el contrato vigente es OpenAPI **0.13.0-draft (82 REST/78 rutas/162 esquemas)**, con 83 entradas R8, 62 PER, 41 tablas declarativas y 210 decisiones; el POS V1 consta en [docs/48](48-CONTRATO-POS-V1-P2.md) como **DOCUMENTADO/NO IMPLEMENTADO**. Las cifras D-200/0.12.0-draft o anteriores de este archivo quedan como corte histórico; el código y las pruebas siguen alineados con 0.12.0-draft hasta P3.

**ACTUALIZACIÓN IMP-67 — 2026-11-21, FUENTE ESTÁTICA:** `frontend/angular.json` ahora construye en `frontend/dist/peru-commerce-ui/browser/` (builder de aplicación moderno), con hashing de bundles, `outputMode=static`, CSS crítico no inline y notices extraídos; `pc-app.html` usa `@defer` por escena (cart/checkout/return/admin). `frontend/scripts/build-browser.mjs` registra procedencia commit/config/lockfile/bytes solo tras una build FUTURA autorizada; `prepare-assets.mjs` exige ese registro y genera `assets/app/pc-assets.json` desde archivos reales con inventario sha256/bytes, release/schema/API/source_release y build_id. `includes/angular-surfaces.php` rechaza manifiesto incompleto o incompatible y valida rutas, bytes, licencia de terceros e integridad. `tools/stage-distribution.mjs` prepara árboles separados plugin/fuente correspondiente, pero no ZIP. **No se ejecutó npm, build, Node, PHP, instalación, ZIP o despliegue; no existe manifiesto ni lockfile resuelto.** Ver `distribution/SOURCE-INSTRUCTIONS.md` y docs/43 §8. Los párrafos históricos de esta especificación que dicen «no existe código» pertenecen a 2026-11-17 y no describen el estado 2026-11-21.

**PRECEDENCIA VIGENTE D-200 / DOC-QA31 (18-11-2026):** OpenAPI **`0.12.0-draft`, 69 REST/66 rutas/138 DTO**. R8-69 abre expediente de solicitud física parcial; R8-70 rechaza o registra retiro con nueva verificación sin efecto económico; R8-26 exige `expected_case_version` y al ajustar termina el caso `closed_adjusted` de modo atómico, sin acreditar devolución externa. **Solo administrador A** con `orders_read` Y `orders_financial_adjust` gestiona el expediente, A consulta estado actual por R8-21. La UI deshabilita mutaciones con caso terminal, ante 409 recarga y no crea nueva clave del mismo intento. No compartir factores telefónicos ni expediente con O, invitado o analítica. Las secciones de 0.8/0.9/0.10 son históricas; no hay frontend compilado ni probado.

# P0-20 — Contrato de montaje Angular 22, assets y WordPress V1

**PRECEDENCIA ACTUAL — 18-11-2026:** OpenAPI principal **0.10.0-draft, 67 REST/64 rutas/134 esquemas**. Los conteos y tablas de 0.3/0.5/0.6 que describen cortes anteriores son antecedentes; el contrato vigente y las correcciones de este documento prevalecen. Ningún endpoint está implementado.

**Corte:** 2026-11-17. **Estado:** DESARROLLO DOCUMENTAL PROPUESTO de la política **D-170**, E08-B D-150 y D-42. No es build, manifest generado, código Angular/PHP ni ZIP. **No se ejecutó npm/Node/Angular, no se instalaron dependencias y no se compiló ni publicó ningún archivo.** El contrato físico debe ratificarse después de existir el código autorizado.

**Handoff R8-69 / DOC-QA30 (18-11-2026):** para un pedido físico con solicitud telefónica de **reducción estrictamente parcial**, el administrador A abre un caso desde el panel mediante `POST /admin/orders/{id}/cases` con `AdminManualCaseCreate`. Mostrar la acción solo a A que tenga `perucommerce_orders_read` **Y** `perucommerce_orders_financial_adjust`; obtener `expected_order_version`/`expected_line_version` de R8-21 y comparar las unidades solicitadas con `physical_quantities.pending_stock_out_units`. Antes de enviar, A verifica en la realidad control del teléfono registrado y un segundo dato de pedido no público; la UI no almacena los valores literales, DNI/OTP o teléfono íntegro en logs, analytics o almacenamiento local. `201` devuelve `case_id`/`row_version`; `200` es replay idéntico sin caso nuevo; `409` obliga recarga de versiones y revisión del caso existente por R8-21, sin crear otra clave ciegamente. A obtiene detalle `GET /admin/orders/{id}?case_id=...` y, si corresponde, ejecuta **otra operación R8-26** bajo sus guardas; abrir caso NO aprueba ajuste ni afecta dinero/stock. No mostrar esta acción ni sus datos al operador O. Contrato vigente `0.11.0-draft` con **68 REST/65 rutas/136 esquemas**, sin código Angular probado.

## 1. Frontera invariable del producto

- **Una sola aplicación Angular 22** como frontend interactivo del comercio, con composiciones móvil/escritorio, para carrito, checkout, disponibilidad y panel interno; no crear servidor Node, servicio SSR, microfrontend por negocio, CMS Angular ni segunda capa de backend. **WordPress/PHP sirve catálogo público indexable** por bloques/shortcodes y controla stock, dinero, cita, MP, permisos, Libro y privacidad.
- La única API comercial es **REST WordPress** namespace `peru-commerce/v1`; contrato vigente en `docs/21-CONTRATOS-REST-ANGULAR-MERCADO-PAGO-Y-ARCHIVOS-V1.md` y `docs/openapi/peru-commerce-v1.openapi.draft.json` **0.12.0-draft (70 entradas R8; 69 operaciones REST, una página WP R8-18)**. No añadir endpoints por preferencias de Angular.
- La instalación es WordPress convencional en Hostinger o compatible, en raíz/subdirectorio, con sesión y cookies WP/huésped, **same-origin** y HTTPS. Nada de dependencia de un plugin WP de terceros por defecto; Mapas/Leaflet solo en las escenas que los utilizan, según P0-21 aún pendiente.
- El ZIP `wp-peru-commerce/` guarda PHP y **solo artefactos de navegador compilados fuera del hosting**, más licencias/fuentes entregables según P0-24. El código fuente TS/Angular y sus herramientas se conservan en el repositorio privado, pero se proveerán al receptor del ZIP conforme GPL en la distribución, sin publicar obligatoriamente todo el repositorio de trabajo.

**Consumo de documentos legales D-198 (2026-11-18):** en panel Angular solo A, `R8-65/66` proveen detalle del expediente y `R8-67/68` descarga binario **privado por caso/instalación/finalidad** con cookie WP, `X-WP-Nonce` anti-CSRF y capability legal; `evidence_ref` es índice no autorizante. El frontend debe descargar con cabeceras `attachment/no-store` sin guardar blob ni PII en localStorage/SessionStorage/analytics ni construir URL a `wp-content/uploads`. `R8-15` es formulario **público sin cuenta/compra**, pero el Anexo I exige sus propios datos de identidad y contacto: no confundir con checkout físico que no requiere correo. La hoja legal se entrega imprimible mediante `ComplaintReceiptCopy` al contexto original de formulario, sin añadir GET por folio. No existe código ni se verificó descarga o impresión.

## 2. Matriz de superficies y carga condicional

| ID | Superficie | Punto de montaje | Comportamiento contractual |
| --- | --- | --- | --- |
| A20-S01 | Catálogo público WordPress | PHP/block/shortcode genera título, descripción, precio visible y URL indexable | No descargar Angular para mostrar el catálogo; UX de carrito solo si está realmente presente |
| A20-S02 | Carrito integrado en una página pública | Raíz Angular declarada por bloque/shortcode; mismo bundle, escena carrito | Estado temporal, sin hold; servidor recalcula al cotizar; nunca precio como autoridad JS |
| A20-S03 | Página de checkout | Página real WordPress con root de Angular y título/URL propios | No necesita reescritura SPA ni SSR; revalidación precio/cita/stock antes de iniciar MP/COD |
| A20-S04 | Retorno de pago MP (R8-18) | Página WordPress dedicada con estado derivado de GET servidor | Parámetros retorno/callback no acreditan pago; evitar caché y exponer datos privados |
| A20-S05 | Seguimiento y enlaces privados | Página real WordPress con intercambio de fragmento por cookie privada; JS sin terceros en URL secreta | Sin fragmento en GET ni Referer; solo lectura salvo OTP grant, acceso por pedido |
| A20-S06 | Agenda de servicios pública | Disponibilidad mostrada en UI Angular y GET slots sobre REST WP | Consulta no reserva ni garantiza slot hasta transacción |
| A20-S07 | Panel comerciante WordPress | Menú WP propio en admin, enqueue por hook/screen identificada | Cookie WP + nonce anti-CSRF y capacidades por operación; operador no recibe campos admin |
| A20-S08 | Otra página del sitio | No hay bloque/shortcode/screen propio ni transición interactiva | Ningún JS/CSS Angular ni carga de mapas o REST privado |

**Regla única de carga:** PHP decide la presencia de un root de app por página/vista; registra los handles temprano y los encola cuando esa página o screen tiene superficie propia. Si WordPress incluye varios bloques/shortcodes en un mismo documento, no emitir dos veces entry module, estilos o import maps ni inicializar dos aplicaciones Angular competidoras. La estrategia exacta de múltiples puntos de interacción (botón mini-carrito y panel) se define como escena/componente dentro de la aplicación única; no múltiples runtimes Angular.

**No hay fallback SPA global:** las URL compartibles de checkout/retorno/seguimiento deben resolver como páginas WP. La navegación interna de componentes no exige rewrites que secuestren URLs del resto del sitio. El estado del carrito es temporal; acceso con enlace y sesión digital usan únicamente el contrato E-07/E-08.

## 3. Cadena de build, versiones y rutas

| Etapa | Entrada/output documental | Criterio |
| --- | --- | --- |
| B20-01 | Fuente TS/Angular 22, lockfile de dependencias, `angular.json`, metadatos de release | Toolchain compatible: Node `^22.22.3 || ^24.15.0 || ^26.0.0`, TypeScript `>=6.0.0 <6.1.0`, RxJS `^6.5.3 || ^7.4.0` según tabla oficial Angular (objetivo; lockfile inexistente) |
| B20-02 | Compilación production con AOT, tree-shaking, optimización y `outputHashing=all` como configuración elegida de release | Nombres no fijos; no afirmar que el default es hashing/all, hay que configurarlo y comprobar el output |
| B20-03 | `dist/<nombre-app>/browser/` (o outputPath configurado) → `wp-peru-commerce/assets/app/` | Solo archivos de navegador; copiar **toda** la estructura de chunks y media relativa, no `dist/.../server/` ni `index.html` como página del WordPress |
| B20-04 | Inventario de salida + manifest propio `assets/app/pc-assets.json` (nombre propuesto) | El manifest de esta integración **no es una salida nativa de Angular que se pueda asumir**; se produce desde archivos reales y se comprueba antes de empaquetar |
| B20-05 | Verificación de hash/versiones y fuente correspondiente, y empaquetado de PHP+assets | Release debe mapear plugin/schema/API, bundle y ZIP; no incluir claves ni datos de clientes |
| B20-06 | Instalación/actualización por ZIP manual de WordPress según P0-26 | No se construye en Hostinger ni se autoactualiza desde WordPress.org, sin pruebas de producción autorizadas |

**Importante:** Angular documenta el directorio `browser/` para el builder moderno de aplicación. La forma exacta de cada chunk, cómo se importa el módulo, el preloading y las rutas de CSS/fonts deben **comprobarse con la build real antes de aprobar el manifest**; no se genera un manifest auténtico en esta fase. Una build con URLs absolutas `/assets/` o `/wp-json/` es incompatible con WordPress en subdirectorio.

## 4. Contrato propuesto del manifest local (diseño, no archivo generado)

El backend PHP leerá un manifest **versionado, único por release**, jamás nombres JS predecibles. El producto necesita estas propiedades:

| Campo | Tipo / significado | Guarda |
| --- | --- | --- |
| `schema` | string, versión del contrato del manifiesto | Compatible con lector PHP; independiente de esquema SQL |
| `release` | string, versión plugin/paquete | Debe coincidir con header WordPress y docs de release, no con la versión WordPress |
| `build_id` | string/hash opaco de build | Relaciona bytes/archivo fuente; no inferir build real de ejemplo |
| `frontend_api` | string, namespace esperado | El backend/manifest deben compartir contrato E-08; rechazar desfase incompatible |
| `entry_module` | ruta relativa al directorio estático del ZIP | Módulo JS ESM inicial emitido por build; no nombre `main.js` fijo |
| `stylesheets` | arreglo de rutas CSS locales | Todas las hojas críticas de la escena, desde salida real |
| `files` | rutas de JS/CSS/chunks/fonts/images estáticos + hash SHA-256/bytes/MIME | Inventario para validar integridad del empaquetado y detectar archivos faltantes |
| `media_root` | ruta relativa del output browser | Reescritura/URL correcta para imágenes y fuentes anidadas en CSS |
| `source_release` | ID del paquete fuente correspondiente | Entrega reproducible/legal P0-24; no incluir enlace secreto ni repositorio privado como único acceso |
| `integrity` | opcional, SRI para entradas externas si se implementa | No prometer SRI operativo sin estrategia de preloads, importaciones y CSP |

**Comprobación antes de distribuir:** `entry_module` y cada stylesheet/chunk/media referenciados existen bajo la raíz publicada, con rutas relativas saneadas (sin `../`, esquema `http:`, enlaces a otro dominio ni escape del directorio), tamaños/hashes calculados sobre bytes reales, CSS y JS correctos. La lista de archivos no debe contener PHP, dumps, `.map` públicos, secretos o ficheros de cliente. La estrategia de hash/integrity es complementaria al acceso del servidor; no proporciona seguridad del checkout por sí sola.

**Sin inventar estructura de output:** el nombre lógico `pc-assets.json`, los campos anteriores y `assets/app/` son PROPUESTA de contrato de empaquetado, **no rutas existentes en GitHub ni archivos Angular ya generados**. No crear JSON falso y presentarlo como salida de build.

## 5. WordPress → módulo ESM → app Angular

- **API nativa candidata:** registrar/cargar **un módulo JavaScript ES** mediante `wp_register_script_module()` y `wp_enqueue_script_module()` (disponibles desde WP 6.5) con ID exclusivo `peru-commerce/app`; los estilos se registran/cargan por `wp_enqueue_style()`. Las dependencias/import maps de módulos deben resolverse según los JS emitidos por Angular 22; **no utilizar `wp_enqueue_script` clásico como si ESM fuese script normal**, ni inyectar tags globales manuales de ejecución.
- **Carga en público:** `wp_enqueue_scripts` para páginas WordPress con superficie propia; bloques dinámicos y shortcodes pueden renderizar tarde, por lo que registro de assets y detección de superficie deben planificarse antes del momento de impresión y contemplar renderizado condicional. No prometer que `has_shortcode()` encuentra todos los bloques o widgets; verificar el flujo real.
- **Carga interna:** `admin_enqueue_scripts` restringido al hook/screen del panel PeruCommerce, nunca toda el área WP ni página de plugins ajena. El backend define root por pantalla y pasa las capacidades visibles solo como pista UI; cada REST hace `current_user_can` y autoriza campo/recurso/estado.
- **URL de assets:** derivar de `plugins_url()` / URL de plugin efectiva y manifest de release, nunca dominio/site-root fijo ni CDN externo obligatorio. Preservar los caminos relativos entre entry ESM y sus importaciones dinámicas para que `chunk-*.js`, `media/` y fuentes de CSS se resuelvan en sitio/subdirectorio. Resolver REST con `rest_url('peru-commerce/v1')`, nunca concatenar `/wp-json/` absoluto.
- **Bootstrapping:** PHP imprime un root de montaje acotado al contexto con configuración mínima codificada/escapada como dato, **sin inline JS arbitrario y sin HTML que revele secretos**. Angular selecciona escena `cart|checkout|tracking|agenda|admin` al iniciar. Un root por app en una página; evitar que shortcode extra duplique `bootstrapApplication`.
- **Configuración lícita en navegador:** locale `es-PE`, `currency=PEN`, API `rest_url`, versión bundle/schema esperada, flags de superficie y, para empleados autenticados, nonce WP para CSRF; nunca `access_token`, webhook secret, OTP, token del enlace, HMAC, ubicación real de archivos, campos financieros protegidos ni datos de otros pedidos.
- **Seguridad:** el nonce WP por sí mismo no autentica invitados; sesión invitado HttpOnly limita lectura propia, OTP grant se valida por propósito y sin ampliación de permisos, MP callbacks no acreditan pagos. El módulo Angular no decide precios, stock, tarifas, cita, reembolsos ni capacidades. Cookies/rest same-origin sin CORS `*` con credenciales.

### CSS, Angular Material, accesibilidad y compatibilidad

- Encapsular estilos bajo root `.pc-app-root` o selector exclusivo y prefijos propios; no aplicar reset `body,*,button` global que altere el tema. La encapsulación de componentes Angular no garantiza que estilos globales y overlays del UI queden aislados; decidir contenedor de overlays, z-index/foco y contrastes con WordPress al implementarse. **Shadow DOM no es obligatorio ni se adopta por defecto**: añadir complejidad de Material/overlays necesita evidencia.
- Composiciones específicas móvil/escritorio con misma lógica/contrato, reglas D-16/D-17: WCAG 2.2 AA y controles táctiles independientes al menos 44×44 px CSS. Figma y ejecución accesible pendientes; no reclamar conformidad por este documento.
- Cargar mapa/Leaflet **solo cuando corresponde**, con atribución y alternativa de dirección manual; no acoplar al render principal del catálogo, ni asumir que mapas o redes externas siempre funcionan.
- Las fuentes/tipos/íconos de terceros requieren licencias de P0-24, no copiar fuentes del sistema/conectores ni depender de Google Fonts sin consentimiento/condiciones verificadas.

**Integración P0-21 propuesta (2026-11-17):** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` regula carga diferida de Leaflet local solo cuando usuario abre el mapa, atribución visible y cache de teselas públicas conforme OSMF. No aplicar `no-store` al servidor de tiles ni compartir tiles/referrer en pantallas privadas con secretos; la dirección manual y selector de UBIGEO funcionan sin el mapa. **No es un widget compilado ni servicio probado.**

## 6. Cache, versiones, fallos y límites de rendimiento

1. **Assets hashed**: CSS/JS y media hashada pueden tener caché duradera e inmutable **si la URL incluye hash real**, pero el manifest de versión, la configuración de boot, páginas de checkout/seguimiento, REST privado, tokens y archivos privados no se pueden servir con caché pública compartida. Verificar LiteSpeed/Cloudflare/Hostinger real antes del release.
2. **Upgrade concurrente**: WordPress puede sustituir archivos ZIP mientras un navegador mantiene bundle viejo. El backend debe comparar compatibilidad de API/versión de esquema, rechazar mutaciones incompatibles y ofrecer refresco seguro sin doble pago; conservar archivos de release anterior **solo si se diseña y verifica** el mecanismo. No asumir disponibilidad de chunks antiguos luego de reemplazo ZIP.
3. **Barrera de seguridad**: sin manifest válido, entry faltante, bundle incongruente, esquema incompatible, certificado HTTPS incorrecto o módulo con recursos privados expuestos, bloquear operaciones críticas de ese módulo (no ocultar los errores). El catálogo PHP y canal legal siguen solo si son seguros.
4. **CSP**: un sitio puede bloquear inline scripts; no ampliar a `unsafe-inline`/CORS `*` como arreglo general. Debe verificarse que los módulos externos, import maps y transmisión de configuración funcionen con las cabeceras reales; el soporte de `wp_enqueue_script_module()` no demuestra compatibilidad automática de todas las CSP.
5. **Source maps**: no servir `.map` públicos por defecto ni empaquetar rutas locales de la máquina en ZIP; **esto no sustituye entregar el código TypeScript correspondiente a receptores bajo GPL**, P0-24.
6. **Rendimiento**: usar budgets de Angular, división diferida de escenas, empaquetado sin bibliotecas WP de terceros por defecto, carga sólo donde hay root, ninguna duplicación de runtime. **No fijar cifras de KB/LCP/INP como medidas reales** antes de la primera build y pruebas autorizadas. La protección de precio/pago corresponde a PHP independientemente del tamaño del bundle.
7. **Compatibilidad de browser**: Angular 22 publica Baseline para navegadores; no prometer un navegador concreto/no soportado sin revisar matriz vigente. Asegurar fallback seguro si JavaScript, módulos o permisos de red fallan.

## 7. Aceptación documental — NO SON PRUEBAS

| ID | Escenario | Comportamiento obligatorio al implementarse |
| --- | --- | --- |
| A20-X01 | Catálogo sin bloque interactivo | HTML PHP indexable, no scripts/css Angular globales |
| A20-X02 | WordPress instalado en subdirectorio y plugin instalado con nombre de raíz fijo | Rutas REST por rest_url(), assets por plugins_url(); ningún `/wp-json` o `/assets` absoluto |
| A20-X03 | Dos shortcodes que requieren carrito en misma página | Módulo principal se registra/enqueue una vez; no dos bootstrap de app ni importmaps duplicados |
| A20-X04 | Página admin ajena a PeruCommerce | No scripts/css ni capacidad expuesta globalmente |
| A20-X05 | Operador entra a panel | Datos/acciones server por permisos reales, nonce WP no se trata como permiso |
| A20-X06 | Build emite hashed `main-*`, `chunk-*`, CSS y media | Manifest sale del output real, assets locales existen y chunks importan desde URL correcta |
| A20-X07 | Falta manifest o chunk hashed | Bloquear escena interactiva y ofrecer error seguro; no cargar JS previo al azar |
| A20-X08 | Se carga app con root sin configuración REST válida | No iniciar checkout; mensaje de error y traza sin secretos |
| A20-X09 | Angular contiene en bundle Access Token MP o endpoint admin secreto | Rechazar release; secretos solo PHP backend |
| A20-X10 | Se reciben deep links o refresh de página de checkout/retorno | WordPress sirve una página real que contiene root; no necesita rewrite global de SPA |
| A20-X11 | No funciona JavaScript en checkout | El catálogo sigue indexable; el comprador ve estado claro de indisponibilidad, no simular pago |
| A20-X12 | CSP del sitio prohíbe ejecución inline | El módulo y configuración deben adaptarse a CSP real; no añadir `unsafe-inline` global como solución silenciosa |
| A20-X13 | LiteSpeed/CDN cachean respuesta privada | No exposición de sesión/OTP/derechos; no-store y exclusiones verificadas por superficie |
| A20-X14 | Se actualiza ZIP mientras hay navegador con bundle antiguo | Gating de API/esquema, invalidación de recursos y recuperación/refresco; no escribir con contrato incompatible |
| A20-X15 | Archivos .map aparecen bajo URL pública sin decisión | No distribuirlos públicamente por defecto; fuente GPL se entrega por paquete fuente aparte |
| A20-X16 | Sitio sin geolocalización consentida | Alternativa de dirección y UBIGEO manual; no dependencia de Leaflet para todo el checkout |
| A20-X17 | Vista móvil/desktop, distintas composiciones | Una sola lógica y API; objetivos WCAG 2.2 AA, 44x44 px táctiles pendientes de comprobación |
| A20-X18 | Falla un chunk lazy tras update o navegación | Sin doble pedido/cobro por reintento; volver a estado seguro y solicitar refresco si compatible |
| A20-X19 | El backend sirve un error `pc_*` con 409/422/503 | UI preserva datos editables sin fingir éxito y no repite mutación sin idempotencia |
| A20-X20 | ZIP contiene assets de otro build | Rechazar: manifiesto y hashes difieren de release/source/package |

## 8. Fuentes oficiales verificadas

| Fuente | URL | Qué sustenta |
| --- | --- | --- |
| Angular CLI deployment | https://angular.dev/tools/cli/deployment | Output browser estático, build production y compatibilidad con hosting sin Node |
| Angular workspace config | https://angular.dev/reference/configs/workspace-config | Configuración outputPath, source maps, assets y budgets |
| Angular CLI build | https://angular.dev/cli/build | output-hashing all, optimizaciones y build browser |
| Angular version compatibility | https://angular.dev/reference/versions | Node/TypeScript/RxJS de Angular 22 |
| Angular bootstrap | https://angular.dev/api/platform-browser/bootstrapApplication | App standalone raíz con ciclo de bootstrap propio |
| WordPress script modules | https://developer.wordpress.org/reference/functions/wp_enqueue_script_module/ | Carga condicional de ESM; identificador y versión |
| WordPress script enqueue | https://developer.wordpress.org/plugins/javascript/enqueuing/ | Registro/enqueue por superficie WordPress |
| WordPress plugin URLs | https://developer.wordpress.org/reference/functions/plugins_url/ | URLs correctas para raíz/subdirectorio/instalación filtrada |
| WordPress requirements | https://wordpress.org/about/requirements/ | Requisitos oficiales de runtime y BD |

## 9. Pendientes físicos de cierre

1. Build Angular 22 **real** autorizada y sus archivos, más un manifest generado desde el output; validar chunk paths, CSS, imágenes, compresión, compatibilidad de script modules y CSP.
2. Confirmar en WordPress/Hostinger real carga condicional público/admin, sitio en subdirectorio, shortcode/bloque y versión de API con CDN/LiteSpeed.
3. Diseñar CSS/overlays, estados de errores de pago, accesibilidad y UX Figma; medir budgets en pruebas autorizadas, no inventar resultados.
4. P0-24: LICENSE y avisos según dependencias **realmente elegidas**; fuente TS/Angular distribuible y build reproducible; no se ha distribuido ZIP ni abierto repo.
5. P0-26: integridad de backup/restore, actualización sin recursos mixtos y version gate; MP peruano continúa bloqueado hasta verificar `payer` ausente, ítem Entrega y `PT15M`.

**Aprobado:** estrategia D-42/D-150/D-170 de Angular compilado y WordPress PHP indexable. **Propuesto aquí:** detalles de manifest, módulos, caché, empaquetado y controles técnicos. **No ejecutado:** código/compilación, browser, WP, paquete ZIP, análisis de rendimiento, instalación o publicación.


## Consumo de DTO corregidos por el frontend (2026-11-18)

Sin cambiar el montaje, los componentes interactivos deben leer `QuoteResponse.policy_notice/privacy_notice` y sus versiones antes de llamar R8-06/07. En la primera respuesta segura de checkout, guardar para mostrar/copiar **solo en memoria de la vista** `tracking_url_once`, sin persistirla en storage ni enviarla como parámetro a Mercado Pago. Ante 202 o replay sin secreto, mostrar estado real y recuperación verificada, no inventar un enlace ni afirmar aprobación. El editor R8-31/32/33 usa `AdminProduct/AdminVariant` (no el DTO público), diferencia variante nueva sin ID y existente con ID, y obtiene duración/buffers/stock opcional; R8-35 ajusta unidades con auditoría. Para reprogramar, editar horario, reenviar avisos y resolver conciliación, usar `row_version` de R8-36/39/29, nunca constante o versión del estado visual; ante 409 recargar la consulta. La agenda muestra `weekly_schedule` con ventanas ISO 1..7 y cruce de día. La administración financiera consume `AdminOrder.financial_movements/external_documents` solo si el backend las incluye para A, sin exponerlas a O. La carga de archivos R8-48 envía `product_id/sellable_id/slot_key` y no sustituye versión vendida. No requiere nueva pantalla, ruta REST, runtime Angular ni cambios de Figma de negocio.

## Consumo histórico del panel en OpenAPI 0.7.0-draft (18-11-2026)
- Mostrar condiciones/privacidad de R8-01 **antes** de recoger correo/teléfono; R8-05 las vuelve a mostrar con versiones y R8-06/07 valida el mismo fingerprint y versiones. Recotizar y confirmar de nuevo cuando cambien.
- Para editar digitales después de cerrar/reabrir el panel, R8-31 ofrece `AdminProduct.digital_assignments` en producto simple o `AdminVariant.digital_assignments` en variantes: archivo activo y versión CAS del slot. R8-48 devuelve versión al subir; R8-63 recibe `active_file_id` en ruta y `expected_version = AdminDigitalSlot.assignment_row_version` en body, no versión del binario. Solo A con permisos dobles; O no recibe los campos.
- R8-21 entrega `AdminOrder.lines[].row_version` para operar líneas y abrir incidencias, `digital_rights` para R8-59/60 y `cases` para R8-56/57/61 bajo autorización A. No inventar IDs ni usar la versión global del pedido para mutaciones de otra entidad.
- R8-39 devuelve `resource_type/resource_id` solo cuando se puede navegar con permiso. Sin referencia, mostrar aviso sin abrir recurso. No crear enlaces a expedientes protegidos para O.
- R8-29 puede presentar conciliación MP prepedido con `source_operation_id` pero sin `order_id`; navegar al `case_id` y resolver con R8-30 solo tras validación del proveedor, nunca ofreciendo un nuevo cobro a ciegas.

No existe frontend compilado ni prueba de comportamiento/seguridad; el contrato de UI no concede permisos.

## Handoff Angular del contrato 0.8.0-draft (18-11-2026)

- **Caso en panel:** primero R8-21 sin case_id para orden y resúmenes; solo A con permiso del expediente solicita `GET /admin/orders/{id}?case_id=...&case_events_page=1` y consume `AdminOrder.case_detail.original_request` junto a eventos históricos paginados. No resolver un caso usando únicamente el último evento/resumen, ni enseñar a O relato, dirección solicitada o evidencia.
- **Condiciones históricas:** R8-09 invitado del propio pedido y R8-21 A/O autorizado leen `agreed_notices.policy_version/privacy_version/policy_notice/privacy_notice` **de la compra original**; nunca sustituir por R8-45 actual. Mostrar al evaluar elegibilidad/cancelación donde corresponda.
- **Digitales:** en settings admin usar `digital_download_limit=null` (ilimitado) o entero positivo, afectar **nuevos derechos**; lectura A de `AdminDigitalRight.download_limit/download_count`. R8-14 deniega por cuota al autorizar antes del stream; el navegador no inventa conteo ni puede aumentar cuota. En reemplazo R8-63, enviar en body **`expected_version=AdminDigitalSlot.assignment_row_version`**, no un campo `assignment_row_version` adicional.
- **Mapa:** R8-45/46 configura `map_tiles` y R8-01 publica plantilla y atribución solo cuando validadas; conocer la URL NO autoriza a pedir tiles por adelantado. Cargar Leaflet/teselas solo por elección explícita, en página sin secretos, atribución y Referer conforme al proveedor. Si falla, dirección y UBIGEO manual permanecen.
- **Solicitud invitado:** para R8-12 generar una `idempotency_key` aleatoria de 16…128 por acción lógica y conservarla **solo en memoria de ese intento** durante reintentos con mismo payload; no regenerar por timeout. Una respuesta 202 retorna case_id existente aunque se haya consumido el grant, sujeto a sesión legítima; nunca almacenar OTP/grant/token en analítica o URL. Cambiar datos exige clave nueva y verificación válida.

Los 34 frames Figma siguen baseline UX, no pruebas. Ningún cambio añade runtime Angular, ruta REST, capacidad O ni obligación de compilar en hosting.

## Antecedente contractual DOC-QA22…26 — 18-11-2026

**Corte histórico de DOC-QA22…26: OpenAPI 0.9.0-draft (67 REST, 64 rutas y 134 esquemas).** Sus reglas se conservan con las precisiones DOC-QA27…29 del contrato vigente 0.10.0-draft; ningún corte anterior es una fuente alternativa. D-01…D-198, 56 PER, 39 tablas lógicas, límites de rol y exclusiones V1 permanecen intactos. Sin código ni comprobación en entorno real.

- **R8-26 / ajuste parcial por expediente:** si se disminuyen unidades de una línea, `FinancialAdjustment` requiere `affected_units` (positivo), `unit_effect=release_unfulfilled_units`, `line_id` y `case_id`. No modificar snapshot de venta; `pc_order_adjustments` conserva `delta_cents`/motivo/caso/idempotencia y la cantidad afectada en evento protegido, asociado con `pc_case_events`; dentro de la misma transacción se incrementa `pc_stock_commitments.released_units` y se revalida `committed_units - fulfilled_units - released_units` bajo locks. No liberar dos veces, no restock de físico ya entregado, no reembolso automático; saldo separado del ajuste.
- **R8-30 / pago tardío y primer turno:** conciliación de un pago efectivamente acreditado con `resolution=reassign_resources` exige `order_id` existente/vinculado. Si perdió franja y se requiere servicio, exige `new_service_start_at_utc`, `new_service_end_at_utc` y `buyer_agreement_evidence`; el backend comprueba acuerdo, importe, estado del proveedor, unicidad por pedido y recurso/día, y crea la PRIMERA cita `pc_appointments` junto con nueva asignación. No usar R8-38 sin appointment existente, revivir hold ni cobrar por segunda vez. Sin franja validada, incidencia abierta y ningún pedido confirmado ficticio.
- **R8-45/46 / proveedor del comercio:** `PublicBusinessSettings` puede transportar razón social `legal_name`, `tax_id_ruc` condicionado y dirección de local `premises_address`; `AdminSettings/SettingsPatch.mail_sender` configura nombre, correo y firma transaccionales, no credenciales SMTP. Persisten en el CAS/versionado de opciones WP; el Libro R8-15 usa identidad legal y dirección **congeladas** al ingresar reclamación y solo exige campos propios del formulario jurídico, jamás DNI/correo de forma universal al checkout. Agenda y avisos de servicio muestran dirección real del único local si está configurada; envío técnico no demuestra recepción.
- **R8-50/66/68 / exportación ARCO:** `privacy_request_id` y `expected_version` vinculan cada exportación a `pc_privacy_requests` e identidad verificada; `scope=order_legal_copy` obliga `order_id` del mismo titular/instalación. `PrivacyExportAck` da operación y expediente; el estado se consulta desde `PrivacyRequestDetail.export_operations` por R8-66. El artefacto terminado se custodia mediante `evidence_manifest/result_reference` protegido del mismo expediente (sin nueva tabla); solo R8-68 A puede transmitir el archivo por evidencia con finalidad/no-store y expiración. No API pública de exportación ni PER-09 comercial.
- **R8-36/37 / excepción de calendario:** cada cierre expone `closure_id`, `row_version`, `active`. La misma R8-37 recibe `action=create|update|retire`; update y retire requieren ID+versión y create/update requieren instantes. Con locks de todos los días afectados, comprobar solapamientos, pertenencia de cierre al recurso, disponibilidad y citas; retirar se audita sin borrar el cierre de `pc_schedule_blocks` ni cambiar citas automáticamente. R8-53 sigue gestionando solo el horario semanal versionado.

**Precedencia del corte histórico DOC-QA27…29:** el OpenAPI 0.10.0-draft define propiedades y reglas condicionales, `docs/42` su motivación, y este anexo su consumo por la capa pertinente. P-A permanece cerrado en diseño, P-B necesita autorización específica para implementar y P-C/P-D siguen sin pruebas, compilación, migraciones, MP, hosting, conformidad jurídica, ZIP o release.

## Consumo HISTÓRICO DOC-QA27…29 — OpenAPI 0.10.0-draft

1. **Preparación y seguimiento físico:** conservar la cantidad original visible como historia; tomar del servidor `physical_quantities.pending_stock_out_units` para lo que falta despachar o entregar en recojo, junto con estado del pedido/línea. No preparar desde `quantity` tras un ajuste ni calcular cantidades desde dinero o texto de eventos. Mostrar que DESPACHADO aún puede estar en tránsito aunque pendiente de salida sea 0; ese cero no demuestra entrega. En preconfirmación, los ceros no significan pedido cumplido. La misma proyección operativa corresponde a A/O y al invitado de su pedido propio; no solicitar expedientes financieros para calcularla.
2. **Concurrencia de la línea:** R8-26 y toda mutación de cantidades incrementan `AdminOrderLine.row_version`. R8-22/23 usan la versión leída; ante 409 recargar cantidades y estados antes de otra acción. Una reducción de todo el remanente utiliza el recorrido de cancelación R8-23 con guardas existentes, sin tratarla como ajuste parcial ni inventar estados.
3. **Configuración de correo:** leer y editar `mail_sender` y `routine_buyer_emails_enabled` en R8-45/46, únicamente A. El interruptor muestra el valor efectivo (true inicial) y explica: «Se aplica a nuevos avisos rutinarios de productos físicos; no cancela correos ya en cola ni desactiva mensajes esenciales». PATCH omitido conserva; no asumir false por campo ausente o error de red. Reintentos de PATCH conservan clave/versión según contrato; ante conflicto recargar.
4. **Bandeja:** una omisión por preferencia no se presenta como fallo de transporte. Si el aviso la representa, respetar `can_resend=false`; no ofrecer reenvío por convertir PANEL en EMAIL. Cambiar a true no ofrece envío retroactivo masivo.
5. **Exportación de privacidad:** después del ACK R8-50, A consulta R8-66 y correlaciona `export_operations.operation_id`; estado pending no equivale a archivo listo. Solo ready con referencia privada y vigencia permite solicitar R8-68 del mismo expediente. No usar URLs públicas ni abrir este recorrido al operador.

Los montajes Angular, pantallas y permisos permanecen; no se creó frontend ni se ejecutó compilación o prueba visual.
