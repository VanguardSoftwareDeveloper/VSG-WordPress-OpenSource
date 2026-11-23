**ACTUALIZACIÓN IMP-67 — 2026-11-21:** Se incorporó `LICENSE` con el texto íntegro de GPL v2 tomado de `spdx/license-list-data` (`GPL-2.0-or-later.txt`); la cabecera real del plugin mantiene `GPL-2.0-or-later`. El frontend declara esa licencia y scripts de reconstrucción. `tools/stage-distribution.mjs` queda preparado para crear en una ejecución FUTURA dos árboles separados correspondientes al mismo `source_release` y `build_id`, con avisos `3rdpartylicenses.txt` de una compilación real y `SOURCE-INVENTORY.json`. El script rechaza la ausencia de `frontend/package-lock.json` real/versionado, notices, hashes, fuente sin commit, secretos evidentes y discrepancias de release. **No se han instalado dependencias, auditado paquetes runtime, creado lockfile, ejecutado compilación, generado ZIP/fuente de release ni distribuido nada.** Las tablas históricas sobre LICENSE «aún ausente» se refieren al corte 2026-11-17; licencias de dependencias/datos e inventario final siguen pendientes IMP-72. Ver `distribution/SOURCE-INSTRUCTIONS.md` y docs/43 §8.

# P0-24 — Licencia GPL-2.0-or-later, fuentes correspondientes y distribución gratuita V1

**Corte:** 2026-11-17. **Estado:** D-173 **APROBADA EXPRESAMENTE** (GPL-2.0-or-later, ZIP gratuito, repo de desarrollo privado). Este documento es **CONTRATO DISTRIBUTIVO DOCUMENTAL PROPUESTO** para cumplir esa decisión, **no constituye asesoramiento jurídico definitivo, un ZIP de distribución ni el archivo LICENSE del producto**, y no modifica el carácter privado del repositorio. No se ha publicado, compilado, auditado un lockfile, instalado o distribuido el plugin.

**Cierre de PRE-06, D-194 (2026-11-17):** para cada release se entregarán **ZIP instalable y archivo fuente correspondiente del mismo identificador de versión**, desde un canal legible por el receptor sin permisos del repositorio GitHub privado; incluir el texto GPL-2.0-or-later aplicable, avisos de dependencias, derechos sobre assets, información de reconstrucción y hashes de artefactos reales. El canal concreto de descargas se definirá al publicar, sin inventar URL o plataforma hoy. La política ya está ratificada; **no** equivale a auditar lockfiles, licencias de todas las dependencias efectivamente usadas, ZIP, códigos fuente o infraestructura de distribución todavía inexistentes. Si no es posible entregar fuente/licencia/avisos de un componente, bloquear su publicación.

## 1. Regla contractual principal

PeruCommerce se desarrollará en un repositorio GitHub **privado**, pero **cuando se entregue un ZIP a otra persona/organización**, el código propio que se distribuye estará bajo **GPL-2.0-or-later**. El destinatario obtiene las facultades de uso, estudio, modificación y redistribución que reconoce la licencia. No se debe presentar «gratuito» o «repositorio privado» como sinónimo de «prohibido copiar el plugin» o «sin derecho a obtener fuente».

- **Privacidad del repositorio ≠ secreto del código distribuido.** La GPL no obliga por sí sola a abrir todas las ramas de desarrollo o documentos internos que jamás se distribuyeron. Sí exige proporcionar **fuente correspondiente de la versión entregada** en la forma y modalidad legal aplicable; Angular/TypeScript minificado sin sus fuentes no basta.
- **Una copia al receptor puede redistribuirse.** No imponer EULA que prohíba modificar, compartir, revender la copia o hacer versiones compatibles con GPL; se puede proteger marca/servicios comerciales separadamente sin negar derechos de código.
- **No hay obligación de mantener un SaaS, repositorio público ni WordPress.org.** El canal V1 elegido es descarga ZIP fuera del directorio WordPress.org; la fuente debe ser accesible al receptor por mecanismo que cumpla la licencia **sin depender exclusivamente de permisos del GitHub privado**.
- La licencia del código propio es **GPL-2.0-or-later**, no `GPL-2.0-only` ni una «licencia libre modificada». Los terceros conservan sus licencias/notices originales; no se eliminan copyright y avisos de MIT/BSD cuando se integra su código.
- Esto condiciona la economía del producto: aunque se ofrezca soporte, instalación o mantenimiento comercial, el cliente puede redistribuir código GPL que recibió. No introducir verificaciones de licencia, ofuscación o bloqueo de modificación que contradigan esa concesión.

**Soporte normativo:** [WordPress GPL](https://wordpress.org/about/license/), [WordPress Plugin Handbook licencia](https://developer.wordpress.org/plugins/plugin-basics/including-a-software-license/), [FSF FAQ distribución y repositorios privados](https://www.gnu.org/licenses/gpl-faq.html), [GPL 2.0 §3](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html). La licencia exacta y la compatibilidad de todas las dependencias se revisarán para cada release concreto.

## 2. Archivos jurídicos futuros (objetivos, NO existentes todavía)

| Elemento en entrega V1 | Finalidad y contenido mínimo | Estado actual |
| --- | --- | --- |
| `wp-peru-commerce/LICENSE` | Texto **íntegro y sin alterar** de GNU GPL v2.0, con concesión `or later` expresada en header/avisos del código propio | **Pendiente de crear en fase de paquete**. El texto GPL v2 sin concesión `or later` en la obra sería ambiguo; no confiar solo en archivo de texto |
| Header archivo principal PHP | `Plugin Name: PeruCommerce`, `Version`, `Requires at least`, `Requires PHP`, `License: GPL v2 or later`, `License URI`, `Text Domain`, etc. | Pendiente de código/versión real. `Update URI` propio sólo si se define URI real para evitar colisiones WordPress.org; no inventar dominio |
| Avisos del código original | Copyright conforme titular efectivo y `SPDX-License-Identifier: GPL-2.0-or-later` donde corresponda | Titular y archivos fuente todavía por materializar |
| `THIRD-PARTY-NOTICES` / carpeta `licenses` del paquete | Copyright, texto MIT/BSD/otras licencias pertinentes y datos de versiones utilizadas | No puede completarse antes de conocer el árbol exacto de dependencias/assets |
| Inventario de componentes por release | Nombre + versión + URL origen + checksum + `SPDX` + bundle/superficie + redistribución + obligación de crédito + revisión | Esquema propuesto aquí; no existen versiones bloqueadas |
| Archivo `SOURCES` / instrucciones de reconstrucción | Correspondencia ZIP binario ↔ fuente PHP, TS/HTML/CSS, config CLI, lockfile, scripts reproducibles y dependencias requeridas | Requiere proyecto autorizado; sin fuente compilable ahora |
| Página/nota de distribución | Enlace al ZIP de plugin y **fuente correspondiente del MISMO release**, con licencia y avisos visibles; método de acceso conforme GPL | No hay página ni descarga pública creada |
| Manifest de build/release | Versiones/hashes de ZIP, assets y paquete fuente P0-20/P0-26 | Propuesto, sin checksum real |

**Protocolo propuesto de entrega PRE-06:** ofrecer **el ZIP instalable** con GPL/avisos + **un archivo de fuente correspondiente** en el mismo punto de distribución y por la misma versión/release, accesibles al receptor sin acceso al repositorio privado; entregar además notas y hashes de integridad del paquete exacto cuando existan artefactos reales. No empaquetar credenciales, backups ni datos de clientes; los scripts/lockfile/plantillas necesarios para modificar o reconstruir el frontend van en el archivo fuente. Esta política conserva D-173, limita el número de canales de soporte y minimiza el tamaño del ZIP WordPress y evita subir toolchain/Node al hosting. El método exacto de cumplimiento GPLv2 §3 y licencias de dependencias se revisará con los bytes del release; **no se puede certificar conformidad sin ZIP/fuente/notices reales**. No se escogió plataforma de hosting de descarga ni se publicó archivo alguno. Ver separación P-A/P-D en docs/39.

## 3. Inventario de componentes existentes, previstos y condiciones

**Regla:** se audita la licencia **del paquete y versión realmente instalada y distribuida**, no de la página principal actual de un repositorio distinto ni una rama futura. Solo el código propio tiene licencia decidida hoy; ninguna librería npm está demostrada como instalada.

| Pieza | Licencia informada por fuente oficial o condición | Cuándo entra al paquete | Aviso y riesgo relevante | Estado de evidencia |
| --- | --- | --- | --- | --- |
| Código propio PeruCommerce | GPL-2.0-or-later (D-173) | Por definir en implementación, PHP/TS/CSS/plantillas originales | Licencia GPL2 completa, concesión 'or later' explícita en archivos/cabecera, copyright según titular real | CONFIRMADO criterio; no fuente construida |
| WordPress core | GPLv2 o posterior (licencia declarada WP) | Plataforma receptora; core NO se mete en ZIP | No incluir core WordPress; usar sus APIs, revisar cualquier copia de fragmentos de fuente | VERIFICADO licencia core |
| Angular Framework 22 | MIT (licencia oficial Angular) | Bundled runtime si se usa Angular 22 conforme D-170 | Copiar copyright/licencia pertinente al código incorporado (version específica) | CANDIDATO V1, no lockfile |
| Angular Material/CDK | MIT (licencia Angular Components) | Solo si finalmente se importa en el bundle de UI | Texto MIT, notices y compatibilidad de versión concreta | POSIBLE; no dependencia confirmada por repo |
| RxJS 7.x | MIT (licencia oficial RxJS 7 estable); otros branches/versiones pueden diferir | Solo versión REAL que se bloquea en lockfile y que efectivamente se redistribuye | No atribuir licencia del master/paquete futuro a RxJS 7; comprobar licencia del tarball empaquetado | CANDIDATO Angular; no lockfile |
| Leaflet | BSD-2-Clause (repo Leaflet) | Solo si módulo mapa V1 lo incluye | Preservar copyright/condiciones del código; atribución de mapa separada | CANDIDATO P0-21, sin build |
| Tiles OSM / proveedor de mapas | Licencia de datos ODbL y condiciones particulares del servidor tiles | Servicios/datos externos, no automáticamente contenidos en ZIP | Attribution, términos del proveedor, caché, límites y prohibición de presentar tiles como 'sin restricciones' | CONDICIONAL P0-21 |
| UBIGEO INEI | **Difiere por dataset:** ficha INEI 2023 = ODbL; otra ficha INEI 2025-12 = Open Data Commons Attribution (ODC-By) | Solo artefacto distrital concreto que se valide e incorpore al ZIP | Comparar bytes/versión/fecha de datos, seleccionar licencia real del archivo, atribución y derechos de redistribución por fuente; no mezclar licencias por nombre 'INEI' | FICHAS VERIFICADAS; **ARTEFACTO FINAL SIN ELEGIR** P0-21 |
| Tipografías, íconos, imágenes, sonidos | Dependen de cada archivo; no hay inventario real | Solo assets seleccionados y redistribuidos | Guardar nombre/titular/versión/licencia/atribución y comprobante de autorización | NO DETERMINADO |
| Herramientas Node/CLI y TypeScript | Licencias individuales por versión | Normalmente desarrollo/build fuera de producción; no necesariamente redistribuidos como binarios | Fuente del proyecto incluye scripts/config/lockfile para reconstrucción, pero no afirmar obligación de copiar todo node_modules | CANDIDATO herramienta, sin entorno creado |

**Detalle de versionado RxJS:** la documentación del **RxJS 7 estable** muestra licencia MIT; el repositorio principal de desarrollo puede mostrar Apache-2.0 para trabajo de una línea futura. **No** copiar el valor del repo master como licencia universal de todo `rxjs`. La versión aceptada por Angular 22 se verifica mediante lockfile concreto del release y su licencia en el paquete. Fuente: https://rxjs.dev/license y https://github.com/ReactiveX/rxjs .

**Compatibilidad delicada:** Apache-2.0 es compatible con GPLv3, pero **no** con GPLv2-only según FSF; si alguna dependencia runtime bajo Apache-2.0 aparece, la combinación/condiciones efectivas de distribución con `GPL-2.0-or-later` requieren un análisis explícito de compatibilidad y elección de versión aplicable (posiblemente GPLv3 para la combinación). **No** cambiar la licencia del código propio por decisión implícita de Codex ni mezclar licencias incompatibles. Fuente: https://www.gnu.org/licenses/license-list.html .

**OSM/INEI:** la BSD de Leaflet aplica al código Leaflet. La licencia de los datos OSM, los términos de la infraestructura de tiles/geocoding y el derecho a redistribuir el dataset UBIGEO son cuestiones **separadas**, sujetas a P0-21. Un ícono/imagen/diseño importado desde Figma tampoco hereda automáticamente GPL por estar en el ZIP; se necesita licencia de su titular o sustitución.

## 4. Fuentes correspondientes: qué entregar con el frontend compilado

Para un release que contenga JS/CSS minificados de Angular, el material fuente correspondiente debe cubrir **al menos** lo que se necesite como forma preferida para modificar y reconstruir el código distribuido, sujeto a los términos legales concretos:

- Archivos TypeScript, plantillas Angular HTML y estilos fuente, utilidades internas y archivos PHP del plugin con cabeceras/licencia y modificaciones respectivas.
- Configuración Angular CLI y TypeScript (`angular.json`, `tsconfig*.json` si se usan), metadatos (`package.json` y lockfile exacto), configuración de env/build sin secretos, scripts de instalación/compilación/generación de manifest/paquete, parches propios y archivos de configuración críticos. No bastan los `.js` minificados ni `.map` de depuración como sustituto.
- Fuentes originales de recursos JS/CSS u otros componentes en el ZIP cuando su licencia o la GPL lo exija, junto con licencia/avisos. Bibliotecas externas deben permitir que se consiga la versión exacta para reconstruir la obra y conservar avisos. **No afirmar que copiar `node_modules` entero es siempre requerido**, ni que entregar un link a npm sea siempre suficiente: revisar según §3 y licencias de componentes.
- Un README técnico que identifique herramienta de build, versiones exactas, configuración/release, instrucciones y mapeo de hashes/manifest. No exponer secretos de MP, .env, claves de comercio, SQL dumps de clientes, tokens reales, origen privado de datos ni logs. El código fuente puede contener placeholders documentados sin secretos.
- Mantener historial de **cada release distribuido**: la fuente `v1.1` no sustituye la fuente del ZIP `v1.0`. Los hashes respaldan correspondencia de artefactos pero por sí solos **no prueban reproducibilidad bit a bit**, que requiere validar un build cuando esté autorizado.

**Ruta comercial:** el propio receptor puede ejecutar/modificar el plugin GPL; el autor puede mantener privado código futuro que no ha entregado y cobrar asistencia/mantenimiento. No hacer afirmaciones sobre garantías, soporte contractual o titularidad de marca que contradigan derechos de código.

## 5. Proceso de verificación documental por release

| ID | Control que deberá superar la distribución V1 | Evidencia necesaria | Estado |
| --- | --- | --- | --- |
| LIC-01 | Identidad de licencia | Código ORIGINAL del plugin bajo GPL-2.0-or-later D-173; header, licencia completa y avisos deben reflejarlo | Pendiente LICENSE real y titulares/copyright identificados por fuente/release |
| LIC-02 | Separación legal | Repositorio de desarrollo privado; distribución gratuita por ZIP; ninguna cláusula prohíbe a receptores redistribuir/modificar | Política aprobada, no ZIP ofrecido ni usuarios receptores |
| LIC-03 | Código fuente PHP/WordPress | PHP propio en forma preferida para editar, sin ofuscar; cabeceras/licencias sin contradicción | Sin PHP implementado |
| LIC-04 | Código fuente Angular | TypeScript, plantillas y estilos fuente, configuraciones, lockfile y herramientas/scripts de build necesarios para generar bundle distribuido | No existe workspace ni build; fuente correspondiente aún no entregable |
| LIC-05 | Artefacto fuente vinculante | Release ID del ZIP de instalación y archive fuente, ambas versiones y hashes integridad verificables | No existen paquetes release ni checksums reales |
| LIC-06 | Terceros runtime | Inventario de dependencias realmente incorporadas en PHP/JS/CSS/archivos, versión exacta, licencia SPDX y obligaciones | Sin lockfile ni dependencias instaladas verificables |
| LIC-07 | Terceros desarrollo | Distinguir herramientas que NO se redistribuyen de librerías embebidas por build, incluso indirectas | Clasificación pendiente build real |
| LIC-08 | Notices MIT/BSD | Conservar copyright, texto de licencia y disclaimers según cada componente empaquetado | Angular/Material MIT y Leaflet BSD-2 solo referencias candidatas confirmadas |
| LIC-09 | Casos Apache/AGPL/no libres | Evaluar compatibilidad de versión concreta con GPL-2.0-or-later y artefacto combinado; bloquear incompatibles | Sin dependencia de ese tipo autorizada por inferencia |
| LIC-10 | Fuentes, íconos y multimedia | Origen/titular, redistribución, obligación de atribución y archivos incorporados | No incluir fuentes con licencias desconocidas o ajenas |
| LIC-11 | Mapa y datos externos | Distinguir licencia Leaflet de datos OSM/INEI y condiciones tiles/geocoding; atribución visible cuando aplique | Proveedor y dataset P0-21 todavía no ratificados ni testeados |
| LIC-12 | Derechos del receptor | Permitir ejecución, modificación y redistribución bajo GPL; sin EULA contradictoria ni prohibición contractual de auditoría | No hay términos de entrega definitivos |
| LIC-13 | Credenciales/PII | ZIP y fuente no incluyen claves privadas, comprobantes de clientes, datasets privados, backups o rutas de storage | No liberar datos del repositorio privado por exportación indiscriminada |
| LIC-14 | Canal de entrega | WordPress ZIP con fuentes correspondientes en paquete/entrega asociada a cada versión; sin depender de credencial del repositorio privado | Aún no existe hosting de distribución; método concreto a ratificar |
| LIC-15 | Historial de versiones | Conservar acceso a fuente correspondiente de releases distribuidos durante período exigible por licencia/método | Sin releases; política de custodia por definir |
| LIC-16 | Validación independiente de lanzamiento | Revisión documental de licencias, licencias embebidas y checksums de artifacts realmente generados | Ningún build, scanner ni verificador ejecutado |
| LIC-17 | Marca y nombre comercial | Diferenciar derechos sobre marca/logo del código GPL, sin usar marca para restringir licencias del código | Tratamiento marcario específico aún sin documento de titularidad |
| LIC-18 | Avisos a usuarios | Información de software libre, licencia, fuentes, créditos y terceros accesible al receptor al descargar | Sin web/ZIP distribuido |

**Bloqueo de publicación:** si un archivo/tercero carece de autorización de redistribución verificable, o si las fuentes correspondientes del Angular compilado no están disponibles por método conforme a la GPL, el ZIP **no se ofrece**. Resolver sustituyendo componente o obteniendo licencia compatible; no hacer suposición de «gratis = libre» o «está en internet = redistribuible».

## 6. Escenarios de revisión documental — NO EJECUTADOS

| ID | Situación | Respuesta de cumplimiento exigible |
| --- | --- | --- |
| LIC-X01 | Se entrega solo Angular minificado sin TypeScript/plantillas | Rechazar release hasta suministrar fuente correspondiente y scripts de build |
| LIC-X02 | Se omite GPL completa del ZIP y solo indica 'gratis' | Rechazar: licencia y concesión legal no sustituidas por precio |
| LIC-X03 | Descarga solo disponible desde repo privado inaccesible al receptor | No usar repositorio privado como único mecanismo de entrega de fuentes |
| LIC-X04 | Usuario redistribuye ZIP GPL a otra tienda | Derecho de redistribución preservado; no imponer cláusula contraria |
| LIC-X05 | Plugin deprecado pero usuarios conservan release anterior | Mantener fuente correspondiente de ese release según vía legal elegida |
| LIC-X06 | Se usa Angular MIT y se quita copyright original | Rechazar: redistribución debe conservar aviso MIT |
| LIC-X07 | Se usa Leaflet BSD2 pero no se incluye licencia en documentación/binario | Rechazar: conservar avisos BSD y separar obligación de tiles |
| LIC-X08 | Se usa RxJS 7 y se le asigna Apache2 por licencia del master futuro | Revisar paquete instalado: no inferir licencia de rama diferente |
| LIC-X09 | Dependencia Apache-2.0 se combina en la distribución bajo GPLv2-only | Bloquear sin análisis: Apache2 requiere evaluación GPLv3 para obra combinada |
| LIC-X10 | Se introduce biblioteca propietaria con prohibición de redistribuir | No incorporar al ZIP GPL sin licencia/permiso compatible |
| LIC-X11 | Fuente TS entregada pero falta build config y lockfile | No declarar fuente correspondiente completa; completar scripts y dependencias necesarias |
| LIC-X12 | Se incluye un archivo .map público pero no se entrega código fuente | Source map no equivale al código preferido para modificar; entregar fuente |
| LIC-X13 | ZIP trae Node modules, .git y backups del desarrollador | Rechazar por tamaño, privacidad, credenciales y no necesidad |
| LIC-X14 | Se reemplaza UI con una tipografía sin permiso de empaquetado | No distribuir hasta licencia verificada o sustituir por alternativa compatible |
| LIC-X15 | Proveedor de tiles exige atribución y se oculta logo | Incumplimiento potencial independiente del código Leaflet, P0-21 debe validar |
| LIC-X16 | ZIP de versión 1.0 acompañado del fuente de versión 1.1 | No es fuente correspondiente; hash/versiones deben coincidir |
| LIC-X17 | El desarrollador quiere cobrar soporte o instalación de plugin gratuito GPL | Puede cobrar servicios aparte sin restringir libertades del software |
| LIC-X18 | Un texto legal del plugin dice 'prohibida toda copia o modificación' | Contradice GPL; retirar/reescribir antes de distribuir |
| LIC-X19 | No hay permiso de un icono Figma de terceros | No incorporarlo ni afirmar derechos de distribución del diseño |
| LIC-X20 | Un comprador solicita fuente del paquete que recibió | Entregar por mecanismo conforme a la GPL y el release exacto, no negar por repositorio privado |

**Investigación P0-21 posterior:** `docs/33-MAPAS-UBIGEO-PROVEEDOR-P0-21-V1.md` documenta 10 fuentes y la diferencia de licencias de las publicaciones oficiales INEI de 2023/2025-12. La publicación reciente no certifica datos territoriales completos a 2026. No se han redistribuido archivos INEI/OSM ni evaluado la licencia del dataset finalmente elegido. Leaflet/OSM y UBIGEO continúan sujetos a sus licencias independientes y obligaciones de atribución.

## 7. Relación con P0-20, P0-21 y P0-26

- `docs/31-CONTRATO-ANGULAR-WORDPRESS-ASSETS-P0-20-V1.md`: el manifest lógico incluye referencia a release y **source_release**; sólo un build verdadero permitirá comprobar bytes, dependencias incorporadas, minificación y mapas. Los archivos fuente no deben hacer público el repositorio completo, pero sí cumplir el derecho del receptor a fuente correspondiente.
- `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md`: su preflight **LC-03** exige licencia/fuente/avisos; release y backup son cosas diferentes. No archivar datos personales de clientes en el paquete fuente.
- **P0-21:** licencias/atribuciones de mapas, tiles e INEI se verifican por proveedor/dataset antes de incluir sus bytes o usar servicios. No inventar precio/coste/atribución. No añadir servicio de terceros obligatorio para el carrito por un tema de licencia.
- **Figma:** solo diseño visual a aprobar, pero las imágenes/íconos extraídos requieren permiso; el objetivo de accesibilidad WCAG 2.2 AA no reemplaza permiso de redistribución de recursos.
- **Repositorio privado:** puede seguir privado en GitHub y trabajar con Codex; cuando efectivamente se distribuya un ZIP, preparar **paquete fuente por receptor/release** conforme a GPL, no ocultarlo tras credenciales de GitHub.

## 8. Fuentes oficiales y límites de verificación

| Referencia | Fuente | Uso |
| --- | --- | --- |
| FSF GNU GPL 2.0 texto | https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html | Copia y fuente correspondiente §3 |
| FSF preguntas GPL | https://www.gnu.org/licenses/gpl-faq.html | Diferencia entre desarrollo privado y distribución |
| FSF compatibilidad | https://www.gnu.org/licenses/license-list.html | Apache 2.0 compatible con GPLv3, no GPLv2-only |
| WordPress licencia | https://wordpress.org/about/license/ | WordPress GPLv2-or-later |
| WordPress plugin license | https://developer.wordpress.org/plugins/plugin-basics/including-a-software-license/ | Avisos y concesión GPL en plugin |
| WordPress plugin headers | https://developer.wordpress.org/plugins/plugin-basics/header-requirements/ | Campos Version/Requires/License/Update URI |
| Angular framework MIT | https://angular.dev/license | Copyright y MIT Angular |
| Angular Components MIT | https://github.com/angular/components/blob/main/LICENSE | Copyright y MIT Material/CDK (si se usa) |
| RxJS 7 MIT | https://rxjs.dev/license | Revisar licencia de versión realmente incluida |
| Leaflet BSD 2-Clause | https://github.com/Leaflet/Leaflet/blob/main/LICENSE | Obligación de conservar aviso y licencia |
| Leaflet FAQ de tiles | https://github.com/Leaflet/Leaflet/blob/main/FAQ.md | Tiles proveedores distintos de licencia Leaflet |

## 9. Pendientes antes de distribuir

**Aprobado:** D-173 GPL-2.0-or-later y ZIP gratuito con repositorio privado, y D-170 frontend precompilado. **Documentado ahora:** contrato de licencia/fuente, 18 controles LIC y 20 escenarios no ejecutados, con fuentes oficiales y separación de dependencias por versión.

**Todavía pendiente:** crear `LICENSE` físico íntegro y cabecera real cuando exista el plugin; inventario real del lockfile/dependencias/assets y sus textos legales; manifest/build Angular 22; ZIP de release y paquete fuente correspondiente; verificar derechos de fuentes/íconos/dataset/mapas; canal de entrega y distribución GPL legalmente suficiente; revisar ley y condiciones particulares. **No se publicó código ni ZIP, no se abrió el repositorio privado, no se ejecutaron compilaciones, pruebas ni verificaciones reales de terceros.**
