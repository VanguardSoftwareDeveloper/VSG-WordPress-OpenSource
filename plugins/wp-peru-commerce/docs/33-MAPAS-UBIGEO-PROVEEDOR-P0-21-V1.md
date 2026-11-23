# P0-21 — Contrato de mapas, Leaflet, proveedor y catálogo UBIGEO (Perú V1)

**Corte:** 2026-11-17. **Estado:** REVISIÓN DOCUMENTAL P0-21. La política D-171 (Leaflet, OpenStreetMap como cartografía inicial, dirección manual, catálogo UBIGEO INEI, tarifas por distrito) está aprobada; **no** hay proveedor de producción garantizado, dataset 2026 validado, archivos descargados, licencias de artefactos comprobadas, mapa implementado ni test ejecutado. Este documento propone detalles de importación, validación, UI y seguridad, **no** autoriza nuevas rutas, código, npm, SQL, pruebas, credenciales ni despliegue.

**Cierre de estrategia PRE-05, D-193 (2026-11-17):** el único dato autoritativo de tarifa distrital será un **catálogo INEI local, completo y versionado**. La ficha [INEI 2025-12](https://www.datosabiertos.gob.pe/dataset/c%C3%B3digo-de-ubicaci%C3%B3n-geogr%C3%A1fica-en-el-per%C3%BA-instituto-nacional-de-estad%C3%ADstica-e-inform%C3%A1tica) se revisará primero, pero su nombre/ficha de 2018 modificada no garantiza por sí sola que sea catálogo completo de distritos 2026; compararla contra [INEI 2023 datos completos](https://www.datosabiertos.gob.pe/dataset/ubigeos-c%C3%B3digos-de-ubicaci%C3%B3n-geogr%C3%A1fica-instituto-nacional-de-estad%C3%ADstica-e-inform%C3%A1tica-inei) y [consulta oficial de códigos](https://www.gob.pe/15028-consultar-codigos-estandarizados-del-inei). Se registrará URL, versión, fecha de corte, licencia real del recurso, hash y conjunto distrital validado en la ficha `pc_ubigeo_versions`. No se descarga ni incorpora archivo al plugin por esta política. **Sin artefacto íntegro validado, delivery deshabilitado; recojo sigue disponible**, el mapa externo es opcional y nunca calcula la tarifa. **Estado:** política de fuente cerrada D-193; selección/verificación de los bytes finales, derechos de redistribución y sincronización son puertas pre-distribución, no evidencia afirmada ahora.

**D-196 — elección final de referencia para desarrollo (2026-11-17):** se selecciona como **dato fuente de desarrollo** el recurso de INEI **«Data completa de los Ubigeos»**, ficha publicada 2023-09 bajo licencia de ficha **Open Database License (ODbL)**, con XLSX identificado `UBIGEO 2022_1891 distritos` y opción ZIP. Esta elección **no** declara que los bytes se descargaron, que la licencia individual ya fue auditada, ni que los 1891 distritos sean el catálogo vigente a noviembre de 2026. La ficha INEI con modificación 2025-12 (ODC Attribution) queda como **comparador**, no como fuente seleccionada por fecha de modificación. Al construir el módulo, procesar códigos como strings de 6 dígitos y persistir URL/version/hash/licencia. **Sin completar verificación del artefacto y su vigencia, delivery se deshabilita; recojo continúa**. Esta es una puerta de habilitación del módulo, no una elección de arquitectura pendiente antes del código. Ver docs/41.

## 1. Decisiones heredadas y frontera del producto

- D-23/D-44/D-100/D-101/D-144/D-171: un comercio y local por WordPress; entrega por distrito **UBIGEO**, tarifa definida por comerciante, un cargo de delivery físico por pedido, pin/mapa voluntario, dirección manual siempre utilizable y Leaflet con proveedor reemplazable. PHP es autoridad final de coverage/precio/confirmación; Angular solo UI; las sesiones privadas E-07 y permisos E-02 permanecen.
- El servicio de **tiles** no es el dataset territorial. El **UBIGEO INEI** identifica dónde entrega el negocio; **Leaflet** presenta un mapa; **OSM** presta datos/tiles conforme a su licencia/política. No inferir código UBIGEO de lat/lng ni calcular costo por kilometraje, polígonos, rutas, geocoding o distancia: fuera V1.
- El núcleo de checkout sigue funcionando **sin mapa ni geolocalización**; si no está disponible, el usuario escribe departamento, provincia, distrito y dirección, elegidos de lista local instalada/validada. Si el dataset válido falta, la selección del distrito debe bloquearse **para delivery**; no se permite precio por nombre libre sin código validado.
- No crear un microservicio cartográfico, servidor Node, reverse geocoder propio ni depender de Nominatim público. No incorporar plugins WordPress de terceros por defecto.

## 2. Fuentes oficiales y alcance de lo comprobado

| ID | Fuente | Qué establece la fuente / fecha pertinente | Situación para V1 |
| --- | --- | --- | --- |
| S21-01 | INEI – Consulta de códigos estandarizados | Consulta oficial de códigos de UBIGEO; página institucional modificada 2025-07-07, NO fecha del dataset descargado | https://www.gob.pe/15028-consultar-codigos-estandarizados-del-inei | REFERENCIA PARA CONTRASTAR VIGENCIA |
| S21-02 | INEI – Ubigeos / Datos Abiertos | Dataset con ZIP/CSV/XLSX, metadatos modificados 2023-09-07 y licencia catalogada ODbL; recurso XLSX identificado como UBIGEO 2022, 1891 distritos | https://www.datosabiertos.gob.pe/dataset/ubigeos-c%C3%B3digos-de-ubicaci%C3%B3n-geogr%C3%A1fica-instituto-nacional-de-estad%C3%ADstica-e-inform%C3%A1tica-inei | ANTERIOR; NO AFIRMAR ÚLTIMA VERSION |
| S21-03 | INEI – Código de ubicación geográfica en el Perú / Datos Abiertos | Otra publicación de INEI, metadatos modificados 2025-12-17, licencia catalogada Open Data Commons Attribution; expone XLS y diccionario. No confirma por sí sola que sea catálogo territorial completo a 2026 | https://www.datosabiertos.gob.pe/dataset/c%C3%B3digo-de-ubicaci%C3%B3n-geogr%C3%A1fica-en-el-per%C3%BA-instituto-nacional-de-estad%C3%ADstica-e-inform%C3%A1tica | CANDIDATO A COMPARAR, LICENCIA DISTINTA |
| S21-04 | INEI – Infraestructura de Datos Espaciales | Portal WMS/WFS y capas distritales vectoriales. La ficha muestra capas distritales actualizadas al 2023; geometría NO es requisito para tarifas por código | https://ide.inei.gob.pe/ | REFERENCIA CARTOGRÁFICA OPCIONAL, NO ZIP V1 |
| S21-05 | Leaflet – Quick Start oficial | Leaflet no impone un proveedor tiles y admite atribución en la capa | https://leafletjs.com/examples/quick-start/ | SOPORTE DE UI, SIN COMPILAR |
| S21-06 | OSMF – Tile Usage Policy | Servidores tile.openstreetmap.org sin SLA: HTTPS exacto, atribución, Referer, cache, prohibición de precarga/descarga masiva, identificación y posible bloqueo | https://operations.osmfoundation.org/policies/tiles/ | VINCULANTE SI SE USA ESE SERVIDOR |
| S21-07 | OSMF – Nominatim Usage Policy | API pública sin autocomplete, máximo absoluto 1 request/seg por aplicación/sitio y sin búsquedas sistemáticas | https://operations.osmfoundation.org/policies/nominatim/ | NO INTEGRAR AUTOCOMPLETE EN V1 |
| S21-08 | OpenStreetMap – Copyright/ODbL | Atribuir datos OSM y cumplir condiciones ODbL cuando se reutilizan; distinguir licencia de datos y condiciones de tiles | https://www.openstreetmap.org/copyright/es | LEGAL DE DATOS INDEPENDIENTE |
| S21-09 | WordPress – API REST | Superficie WordPress autorizada, sin servicios añadidos para calcular tarifas | https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/ | CONTEXTO E-08 |
| S21-10 | Leaflet – referencia técnica TileLayer | Módulo de mapa puede recibir URL y attribution configurados; no asigna cobertura legal o tarifaria | https://leafletjs.com/reference.html | CANDIDATO DE PRESENTACIÓN |

**Hallazgo de licencia y actualidad crítico:** no se puede afirmar que el archivo XLSX **UBIGEO 2022_1891 distritos** publicado como recurso del dataset de 2023 sea la tabla distrital vigente de **2026**. Existe un registro INEI distinto con metadatos modificados **2025-12-17** y licencia catalogada **Open Data Commons Attribution**; la fuente 2023 figura como **ODbL**. Son publicaciones y licencias distintas, no versiones intercambiables del mismo artefacto. La ficha más nueva tampoco demuestra que incluya exactamente el catálogo distrital completo del momento. Antes de incorporar un archivo al ZIP, obtener y cotejar los **bytes oficiales reales**, su fecha de corte, códigos/nombres, variaciones territoriales, y licencia aplicable a ese recurso; **no inventar número total de distritos 2026 ni certificar dataset actual**. La consulta en línea INEI es referencia de contraste, no API comercial pública aprobada del plugin.

### 2.1 Estándar de código y significado

- Código distrital como **string de 6 dígitos** `DDPPdd` (2 departamento + 2 provincia + 2 distrito). No normalizar numéricamente ni usar identidad del nombre. Etiquetas con tildes y caracteres oficiales en UTF-8; búsqueda tolerante a acentos separada del dato original.
- Verificar filas y niveles contra INEI; impedir repetición dentro de `dataset_version`. Distritos retirados/renombrados pueden persistir en snapshots históricos, mientras la tarifa actual solo usa códigos válidos de la versión activa.
- `pc_ubigeo_versions`: `source_name`, `source_url`, `dataset_version`, `dataset_hash`, `published_on`, `imported_at`, `is_current`; `pc_ubigeo`: `version_id`, `code`, `department_name`, `province_name`, `district_name`, `valid`; `pc_delivery_rates`: `ubigeo_code`, `enabled`, `fee_cents`, `row_version`. Son tablas **propuestas P0-18**, todavía sin DDL validado.
- La vigencia de un código y la **tarifa del negocio** son cosas distintas: importar nueva fuente no habilita automáticamente delivery en nuevos distritos ni cambia tarifas existentes, no reescribe pedidos confirmados; el administrador revisa los cambios.

**Cotejo adicional de fichas INEI (2026-11-17, solo web):** [Ubigeos del INEI, ficha 2023-09-07](https://www.datosabiertos.gob.pe/dataset/ubigeos-c%C3%B3digos-de-ubicaci%C3%B3n-geogr%C3%A1fica-instituto-nacional-de-estad%C3%ADstica-e-inform%C3%A1tica-inei) informa ZIP completo y licencia ODbL; [Código de ubicación geográfica, ficha modificada 2025-12-17](https://www.datosabiertos.gob.pe/dataset/c%C3%B3digo-de-ubicaci%C3%B3n-geogr%C3%A1fica-en-el-per%C3%BA-instituto-nacional-de-estad%C3%ADstica-e-inform%C3%A1tica) informa XLS y Open Data Commons Attribution. La fecha de metadatos **no prueba** que el XLS más reciente contenga una lista distrital exhaustiva más actual. Se prioriza **revisar primero** esa publicación más nueva como candidato, comparándola con consulta INEI y archivo completo, **sin seleccionarla todavía**; falta acceder a bytes, verificar versión/licencia real e integridad (docs/39 PRE-05). No se descargó dataset ni se generó hash.

## 3. Política de teselas OSM Standard y alternativa de proveedor

**Decisión inicial aprobada D-171:** Leaflet local, `tile.openstreetmap.org` como origen inicial **opcional para mapas interactivos modestos**, con conexión directa desde el navegador únicamente cuando el consumidor abre explícitamente el mapa. Esto **no convierte el servidor comunitario en proveedor garantizado para negocios comerciales**. La política oficial puede cambiar, revocar el uso o bloquear volumen, sin SLA ni compromiso de continuidad. Si una instalación necesita garantía o tráfico significativo, configurar otro proveedor licenciado/contratado por el comerciante, con precio/condiciones verificadas por instalación (no seleccionado ni presupuestado en esta fase). **No añadir un proveedor pago por defecto** ni hacer del cambio una condición del checkout.

Condiciones oficiales si se usan esos servidores:
1. HTTPS y URL estándar `https://tile.openstreetmap.org/{z}/{x}/{y}.png` según política del servidor, no asumir subdominios `a/b/c` históricos.
2. Mostrar atribución `© OpenStreetMap contributors` y enlace a licencia, visible con panel abierto, incluso móvil, sin esconder detrás de botones/overlays.
3. Navegador debe enviar `Referer` válido al servidor de tiles; no aplicar en esa superficie una `Referrer-Policy` que lo suprima. **Precaución de privacidad**: NO cargar tiles desde páginas que contengan enlaces secretos, tracking o documentos personales; en esas páginas mantener política estricta/no terceros. El permiso de Referer se limita a la pantalla de selección de mapa sin secreto.
4. Respetar cabeceras de caché y no enviar `no-cache` por defecto; cuando el caché no puede leerlas, política mínima 7 días para tiles (según política OSM). **Distinguir** estas teselas públicas y cacheables de pedidos/identidad/OTP privados que usan `no-store` E-07.
5. No precargar teselas masivas, crear archivos offline, recorrer zoom/bounding boxes por lotes, ni usar proxies anónimos que oculten identificación y políticas. No hay tiles incluidos en ZIP.
6. No presentar OSMF como servicio de pago con SLA. Preparar apagado/cambio sin actualizar el contrato de tarifas; una capa alternativa requiere nueva revisión de términos, privacidad, atribución y claves antes de activar.

**Geocoding:** el servidor público Nominatim establece máximo absoluto **1 solicitud/segundo por aplicación/sitio**, no tolera autocomplete ni consultas sistemáticas. **PeruCommerce V1 no lo usa como servicio de búsqueda, autocompletado, geocodificación inversa ni deducción de distrito.** Si se solicita después, se necesitará decisión de producto separada y proveedor compatible, no un uso implícito del servicio público.

**Privacidad y transmisión:** el navegador **sí transmite su IP al proveedor de teselas** al pedir imágenes, y un `Referer` de la página de mapa conforme a la política; no se puede prometer que el uso de Leaflet sea anónimo. No colocar nombres, direcciones completas, números de pedido, secretos o parámetros sensibles en la URL de esa pantalla. Informar proveedor conforme J-04/E-07 y no descargar mapas en páginas de tracking/OTP.

## 4. Flujo público sin mapa obligatorio

1. Para **delivery físico** (no servicios presenciales/digitales sin físicos): se ofrece selector departamento → provincia → distrito poblado desde la **versión activa local** de INEI, con búsqueda eficiente/teclado. La dirección y referencia son datos escritos por comprador, sin llamar a geocoding. Si distrito no está cubierto, mostrarlo antes de Pagar y permitir recojo solo si tipo de pedido y política lo admiten.
2. **Mapa opcional y expresamente solicitado** para precisar pin si el comprador lo prefiere; Leaflet y CSS cargan diferidos solo en esa escena (P0-20). Si map/tiles falla, no borrar lo escrito ni impedir pedido válido; sin geolocalización silenciosa. Si permite geolocalización del dispositivo, pedir permiso del navegador y explicar proveedor/categorías de datos.
3. Para **recojo** no exigir UBIGEO/dirección del comprador. Para pedidos mixtos, cobrar **una sola tarifa por pedido** si existe físico a domicilio; digitales y servicios locales no generan por sí mismos cargo de delivery.
4. `R8-16 GET /public/ubigeo` devuelve entradas paginadas/cobertura pública según el borrador OpenAPI y una versión de dataset; `R8-05 POST /public/quotes` calcula precio con **Código UBIGEO + tarifa vigente del servidor**. R8-06/07 revalidan antes de compromiso y evitan compra si distrito/precio cambió sin nueva confirmación. No hacer el `fee_cents` suministrado por Angular una autoridad.
5. El panel administrador `R8-51/52` muestra y modifica cobertura/tarifa, con versión esperada e idempotencia, solo A bajo E-02. La configuración del proveedor cartográfico la gestiona A por R8-45/46 bajo facultades existentes; sin ruta nueva ni privilegio de operador.
6. `R8-28` para cambios de dirección **después de confirmar** crea nueva revisión/evidencia. A debe aprobar cuando afecta cobertura/costo o existe dinero acreditado (D-65/D-83); nunca sobrescribir snapshot original ni modificar historial de tarifas.
7. **Seguridad de enlaces:** páginas de seguimiento, OTP, Libro, ARCO, devolución o descargas digitales privadas no descargan tiles ni comparten datos/fragmentos; tampoco cargar Leaflet global. UI pública y panel necesitan información sobre prestadores, privacidad de IP y atribución según J-04/P0-25.

## 5. Inventario de módulos/proveedor y superficie de datos

| Elemento | V1 propuesto | Condición / bloqueo |
| --- | --- | --- |
| UI mapa | Leaflet local dentro del build Angular (cuando se abra mapa) | BSD-2-Clause del paquete realmente distribuido, carga diferida, no tema global |
| Capa inicial | OpenStreetMap Standard Tiles directa desde navegador bajo política oficial | SIN SLA, opcional, sujeta a atribución/Referer/caché y posibilidad de bloqueo |
| Geocoding | **Ninguno** por defecto | Nominatim público no es autocomplete; no inventar nuevo servicio |
| Coordenadas | Opcionales, solo si aportadas explícitamente para ubicar entrega | No afectan tarifa/cobertura; acceso mínimo, no divulgar al proveedor sin necesidad |
| Entrada manual | Obligatoria para cualquier instalación que ofrezca delivery | Selector INEI local + dirección, sin API externa |
| Catálogo UBIGEO | Datos oficiales INEI por versión importada/validada | Fuente concreta/fecha/licencia por cerrar antes del release |
| Tasas de reparto | `pc_delivery_rates` por distrito habilitado, 1 cargo PEN por pedido | Calculadas en PHP, idempotencia/versión; no por pin ni distancia |
| Config de proveedores | A, tile URL/atribución/enable de origen aprobado, opciones WordPress protegidas | Dominio HTTPS permitido, no URL SSRF/proxy abierto, no exponer secretos de proveedor |
| Cache | Tiles públicos con políticas del origen; REST/pedido/OTP privados sin caché compartida | Verificar LiteSpeed/CDN/Hostinger real; nunca una política común de no-store para ambos |
| Dataset en ZIP | Solo recurso INEI completo y licencia verificadas en release real | **No hay bytes ni licencia definitiva seleccionada**; no incluir dataset ficticio ahora |

## 6. Matriz de verificaciones previas (sin ejecución)

| ID | Control de P0-21 | Criterio de revisión | Resultado ante fallo |
| --- | --- | --- | --- |
| MAP-01 | Fuente de catálogo territorial | Utilizar INEI como autoridad; elegir una publicación concreta con fecha de datos, columnas, licencia y descarga verificadas | Bloquear importación/release de dataset incompleto; no sustituir por datos sintéticos |
| MAP-02 | Versión/cadena de custodia | Registrar URL, fecha de publicación de fuente, hash de bytes, licencia, fecha de importación, versión y número de registros | Sin hash/fuente no marcar versión 'actual' |
| MAP-03 | Código | Guardar UBIGEO distrital como string exacto de 6 dígitos, sin perder ceros iniciales; departamento 2 y provincia 4 como segmentos | Rechazar entradas mal formadas o nivel incorrecto |
| MAP-04 | Integridad de registros | No duplicar (version_id,code), preservar jerarquía dept/prov/dist, normalizar Unicode para búsqueda sin cambiar nombre oficial almacenado | No inventar distrito ni corregir sin cotejo de fuente |
| MAP-05 | Diferencias entre versiones | Importación candidata y comparación de añadidos/retirados/cambios; aprobación de publicación por admin antes de activar | No reescribir código/snapshot de pedidos ya confirmados |
| MAP-06 | Cobertura de delivery | Una tarifa vigente por código distrital y comercio; bool enabled; únicamente administrador modifica R8-52 | Si no hay enabled/tarifa válida, delivery no elegible |
| MAP-07 | Cotización | R8-05 calcula tarifa servidor: 1 modo físico por pedido, máximo un cargo delivery, moneda PEN céntimos | Cambios en carrito implican recotizar antes de MP/COD |
| MAP-08 | Confirmación | R8-06/07 revalidan distrito, dirección, cobertura, tarifa, disponibilidad y snapshot; el pin no es autorización | No crear segundo cobro ante cambio sin confirmación |
| MAP-09 | Revisión posterior | R8-28 guarda nueva revisión inmutable, motivo y autorización A cuando hay cambio tarifa/cobertura o pago acreditado | No alterar datos originales, no autogenerar cobro/refund |
| MAP-10 | Módulo cartográfico | Leaflet local en assets solo al abrir mapa explícitamente; URL tiles HTTPS configurable y atribución visible | Si proveedor cae, no bloquear selección manual de distrito y dirección |
| MAP-11 | OSM Standard Tiles | Atribución visible; navegador Referer correcto; HTTP cache; cero prefetch/offline, sin proxy/CDN genérico que anonimice o salte límites | Deshabilitar capa de mapa o cambiar proveedor lícito si condiciones no se cumplen |
| MAP-12 | Privacidad y geolocalización | Sin mapas/tiles en pantallas de enlaces secretos ni precarga invisible; ubicación solo elección del comprador, consentimiento/información de proveedor según caso | Nunca enviar dirección completa, nombre, teléfono, OTP o tokens a servidor de tiles |
| MAP-13 | Geocoding | V1 sin Nominatim público como autocomplete, reverse geocoding ni cálculo de distritos por coordenada; no nueva API terceros | Elegir UBIGEO por lista local; pin opcional visual no decisor |
| MAP-14 | Cambio de proveedor | Config admin de capa tiles/atribución y habilitación, sin alterar algoritmo de tarifa ni histórico; origen permitido HTTPS, sin SSRF | Prohibir URL arbitraria insegura; config de credencial solo servidor cuando corresponda |
| MAP-15 | Fallo sin mapa | Fallback íntegro de departamento/provincia/distrito y dirección/observaciones manuales, accesible en móvil y escritorio | Ninguna vista se queda bloqueada esperando proveedor |
| MAP-16 | Licencias de distribución | Inventariar Leaflet BSD-2, fuentes/assets, licencia exacta del dataset INEI seleccionado (no asumir que dos catálogos comparten licencia), ODbL y tiles | Sin licencia verificada no incluir bytes de dataset en ZIP final |

## 7. Escenarios de aceptación documental — NO EJECUTADOS

| ID | Escenario | Comportamiento exigido |
| --- | --- | --- |
| M21-X01 | Compra física con delivery en distrito enabled y tarifa válida | Se cotiza una tarifa en PEN según código del servidor y se muestra antes de pagar |
| M21-X02 | El comprador coloca un pin que podría no coincidir con el UBIGEO seleccionado | **Sin geocoding ni polígonos no se puede detectar automáticamente la discrepancia**. Informar que el pin es orientativo y confirmar el UBIGEO elegido, único origen de cobertura y tarifa |
| M21-X03 | Distrito sin cobertura | Delivery no elegible, aunque el mapa muestre coordenadas dentro de un área aparente |
| M21-X04 | El comprador selecciona recojo | Delivery 0; no obligar dirección ni descargar Leaflet |
| M21-X05 | Pedido solo digital/servicio presencial | Sin tarifa delivery por inexistencia de físico entregable |
| M21-X06 | Precio o tarifa cambia entre cotización y pagar | 409/422 seguro, nueva confirmación antes de crear order MP |
| M21-X07 | Compra confirmada con tarifa del mes anterior | Snapshot histórico intacto aunque cambie configuración de R8-52 |
| M21-X08 | Cliente solicita nuevo destino tras confirmar | R8-28 preserva dirección original y aplica D-83 cuando corresponde |
| M21-X09 | No existe Javascript o mapa bloqueado | Selector de ubicación y dirección manual disponibles; PHP valida |
| M21-X10 | Tile server tarda/falla o revoca servicio | Checkout continúa en modo manual; alerta no bloqueante; nunca inventar mapa offline |
| M21-X11 | Sitio en subdirectorio y cache CDN | Leaflet local/URLs no hardcoded, tile desde proveedor permitido; prueba posterior pendiente |
| M21-X12 | Mapa oculto en pantalla de tracking con token | No solicitud a tercero que revele URL/fragmento o datos del comprador |
| M21-X13 | Se intenta cargar tiles para toda Lima antes de que el usuario abra el mapa | Rechazo: precarga/bulk prohibida en OSM Standard |
| M21-X14 | Se quita atribución de OpenStreetMap para diseño limpio | Rechazo de configuración del proveedor OSM |
| M21-X15 | Se usa Nominatim público para sugerir direcciones por pulsación de tecla | Rechazo: autocomplete prohibido |
| M21-X16 | Dataset de 2023 sustituye versión nueva sin verificar cambios territoriales | No marcar 'actual 2026'; requiere cotejo y aprobación de fuente/version |
| M21-X17 | Fuente INEI 2023 ODbL y fuente 2025-12 ODC Attribution | No fusionar ni asignar licencia única sin revisar artefactos exactos |
| M21-X18 | UBIGEO con cero inicial | Preservar seis caracteres; jamás parseInt para ID territorial |
| M21-X19 | Dos administradores cambian tarifa del mismo distrito | Aplicar expected_version/idempotency, conflicto 409 en actualización duplicada |
| M21-X20 | Proveedor nuevo requiere token de servicio | Credencial solo servidor, no poner en Angular; si es obligatorio en client, evaluar si se permite exposición y condiciones antes de activar |

## 8. Riesgos y decisiones físicas por resolver

- **Selección de la base INEI del release:** comparar documentos/datasets oficiales 2023/2025 y sistema de consulta, establecer fecha de vigencia real de **códigos de distritos**; no publicar «UBIGEO 2026 actualizado» sin archivo y contraste de autoridades. Distinguir licencia ODbL y Open Data Commons Attribution según artefacto; inventario P0-24.
- **Condiciones de OSM Standard Tiles:** viable solo cuando el comercio cumpla la política y acepte best-effort; para instalaciones más exigentes usar proveedor externo con contrato comprobado o continuar sin mapa. Ninguna elección final de proveedor comercial, credenciales, costos, plan o límites verificó.
- **Privacidad/Referer:** una página de selección de mapa sin secreto puede enviar Referer conforme OSM; páginas privadas requieren `no-store` y ausencia de servicios tiles terceros. Validar montaje real/angular/CSP/cookies con P0-19/P0-20.
- **Impuestos/delivery:** el monto comercial de entrega es una tarifa de negocio en céntimos, no impuesto calculado universal; reglas fiscales externas continúan J-03.
- **Geocodificación:** no reverse geocode para inferir distrito de coordenadas; mantener el flujo local/manual reduce dependencia, costo y exposición de datos.
- **Implementación y versión:** `docs/23` define tablas de diseño, `docs/31` carga condicional Leaflet, `docs/32` licencias; `docs/30` actualizaciones ZIP. Este contrato no ratifica DDL, ejecuta herramientas o integra proveedores.

## 9. Estado del cierre documental

**Verificado externamente:** políticas oficiales OSM/Nominatim, carácter opcional de Leaflet/proveedor, fuente INEI de datos territoriales, existencia de dos catálogos con distinta ficha y licencia, enlace oficial de consulta de códigos.  
**Propuesto:** reglas de importación, validación por versión, mapa opcional, controles MAP-01…16 y 20 casos de aceptación.  
**No verificado:** dataset INEI 2026 concreto/completo, actualización territorial, licencia del archivo definitivo, URL/proveedor alternativo y costos, pruebas de interfaz, permisos/CSP/caché, instalación/hosting o autorización de implementación. **No se ha descargado ni incorporado UBIGEO al repositorio, tampoco construido mapas/instalado dependencias.**

**Siguiente paso dependiente:** seleccionar y validar el dataset territorial a partir de bytes oficiales, licencias y versión cuando se autorice el empaquetado; revalidar condiciones del servicio tiles antes del release. P0-21 sigue **parcial documental**, no conformidad ni integración probada.

## Contrato de lectura y edición cartográfica — OpenAPI 0.8.0-draft (18-11-2026)

`MapTilesConfig` es el subobjeto de R8-45/46 `AdminSettings.map_tiles`/`SettingsPatch.map_tiles`, gestionado únicamente por A con `perucommerce_settings_manage` y el CAS de `wp_options`. Campos: `enabled:boolean`, `tile_url_template` con HTTPS y placeholders **{z}/{x}/{y}**, `attribution_text` visible y `attribution_url` HTTPS. Activar exige valores completos, origen permitido (no URL a intranet ni proxy SSRF), validación de condiciones de servicio/licencia, privacidad y CSP; no poner API key, token o datos personales en la URL de tiles o en el frontend. PATCH sin `map_tiles` no lo modifica; si se envía el objeto lo reemplaza, `enabled:false` desactiva solo mapa.

R8-01 `PublicBootstrap.map_tiles` ofrece exclusivamente la configuración pública habilitada y verificada; **no dispara conexión de red a tiles**. Angular inicia Leaflet/teselas SOLO después de que el comprador abra el mapa voluntariamente en una escena sin secretos/PII, manteniendo atribución, referencia y caché correspondientes. Un cliente puede elegir ubicación/dirección manual sin tiles. No confundir `map_tiles.enabled` con `delivery_enabled` o `pc_delivery_rates`: desactivar/cambiar proveedor no afecta tarifas/cobertura UBIGEO ni pedidos históricos.

Configuración inicial OSM Standard, cuando se use, debe respetar `https://tile.openstreetmap.org/{z}/{x}/{y}.png` y atribución visible con enlace a `https://www.openstreetmap.org/copyright`, sin SLA ni precarga. Otro proveedor requiere evaluación de origen/atribuciones/licencia y puede permanecer deshabilitado. No hay dataset INEI importado, hosting ni servicio de tiles efectivamente verificados; P-C/P-D quedan pendientes.
