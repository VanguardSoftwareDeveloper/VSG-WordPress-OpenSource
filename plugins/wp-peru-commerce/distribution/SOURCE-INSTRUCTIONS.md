# Fuente correspondiente y distribución manual — PeruCommerce

Estado: **fuente de preparación IMP-67**, sin compilación, lockfile resuelto, ZIP, instalación ni publicación. Código propio declarado `GPL-2.0-or-later` y texto íntegro en `LICENSE`.

## Estructura y herramienta de construcción

El comercio ejecuta PHP/WordPress, **no Node**. Angular 22 se compila fuera del hosting con la versión compatible de Node y TypeScript. La configuración `frontend/angular.json` genera navegador en `frontend/dist/peru-commerce-ui/browser/`; la opción `outputHashing=all` se fija para producción. `@defer` separa las escenas cart/checkout/return/admin; WordPress solo monta la que corresponde. La extracción de CSS crítico a `index.html` está deshabilitada: WordPress encola los estilos físicos CSS, no usa el HTML generado como página.

La generación real requiere código limpio en un commit y un **`frontend/package-lock.json` obtenido de npm para las versiones efectivas**, no un lockfile redactado a mano. El lockfile debe incluirse en el commit cuyo ID figure en el manifiesto.

Comandos previstos **para una fase de ejecución expresamente autorizada** (NO ejecutados en IMP-67):

```powershell
cd frontend
npm ci
npm run build
npm run prepare:assets
npm run verify:assets
cd ..
node tools/stage-distribution.mjs
```

Primero se debe crear y confirmar el lockfile auténtico mediante el procedimiento de resolución de dependencias autorizado, y comprobar versiones/licencias reales antes de estos comandos. `prepare:assets` no ejecuta npm ni Angular: **solo** examina `index.html`, archivos reales, sus hashes, referencias JS/CSS y notices extraídos por el builder, y los copia a `assets/app/` si no hay una salida anterior. Nunca crea hashes o nombres para archivos que no existan. `verify:assets` contrasta el inventario contra bytes realmente presentes. Una build anterior en `assets/app/` no se sobrescribe silenciosamente.

El manifiesto `assets/app/pc-assets.json` tiene versión de contrato `perucommerce-assets-v1` y campos `release`, `schema_version`, `source_release` (commit SHA completo), `frontend_api`, `entry_module`, `stylesheets`, `media_root`, `files[]` (path, sha256, bytes), `third_party_notices` y `build_id`. WordPress compara versión de plugin/esquema/API, rutas relativas, bytes, hashes, notices y build_id antes de encolar ESM/CSS; sin archivo completo hay fallback seguro.

## Dos artefactos, un mismo código

`tools/stage-distribution.mjs` prepara **dos árboles** bajo `dist/stage/<release>/`, sin crear archivos ZIP:

- `plugin/wp-peru-commerce/`: PHP original, JS/CSS públicos propios, licencia íntegra, salida Angular REAL con manifiesto y avisos de terceros generados, y `SOURCE-NOTICE.txt`.
- `source/wp-peru-commerce-source/`: código PHP, TypeScript, plantillas HTML, estilos, lockfile exacto, configuraciones, scripts de construcción, licencia, inventario de archivos y correspondencia al commit/build_id. Sin repositorio Git privado, Node instalado, datos de comercio, bóveda, certificados, backups o almacenamiento privado.

El archivo `RELEASE-STAGING.json` es **un inventario de staging, no una firma ni prueba de ZIP/instalación**. IMP-72 debe generar y comprobar por separado el ZIP instalable y la fuente correspondiente, con hashes finales, conformidad de licencias y canal de entrega. Entregar ambos al receptor del mismo release bajo un mecanismo conforme a la GPL; un GitHub privado inaccesible no satisface esa entrega.

### Barreras no omitibles antes de distribuir

Debe verificarse que `LICENSE`, los `3rdpartylicenses.txt` **realmente extraídos** de la dependencia instaladas, sus titulares, sus licencias, fuentes multimedia/iconos y eventuales datasets son compatibles con lo distribuido. El script valida existencia e integridad, pero **no certifica** que todas las dependencias de runtime estén cubiertas, ni demuestra reproducibilidad bit a bit ni disponibilidad de fuente al público. La auditoría de licencias en P0-24 y pruebas de hosting/caché/CSP/ESM/WordPress siguen pendientes.

No llevar al ZIP los repositorios de trabajo, tests privados, informes de cliente, secretos de Mercado Pago, claves de WordPress, archivos digitales del comercio, dumps, hojas de reclamación, datos ARCO, logs o evidencias. La raíz del ZIP instalable debe ser una única carpeta `wp-peru-commerce/`.

Las páginas WordPress se montan desde `perucommerce_app` solo para cart, checkout y return, además del menú propio A/O. El catálogo SEO permanece en PHP; el Libro público y los canales ARCO nativos tienen su propia superficie. Si falta cualquier asset, licencia, nonce, compatibilidad de esquema o evidencia de gate comercial, se deniega esa operación en vez de usar una build vieja o un falso fallback financiero.

## Recuperación y custodia por comercio (IMP-68)

La fuente correspondiente incorpora [RECOVERY-RUNBOOK.md](RECOVERY-RUNBOOK.md) con el procedimiento para bloquear, respaldar, restaurar y conciliar por instalación. El ZIP distribuido no debe contener este manifiesto operativo por comercio, respaldos reales, claves ni la constante privada PERUCOMMERCE_RECOVERY_EPOCH. El release requiere comprobaciones P-C, y **ningún** comando de staging crea backups, restaura WordPress, consulta Mercado Pago o libera pagos/stock de forma automática.

## Estado actual

- Configuración e instrucciones publicadas como código fuente.
- `frontend/package-lock.json`: **no generado ni verificado**.
- `frontend/dist/`, `assets/app/`, `dist/stage/`: **no producidos**.
- ZIP de instalación, archivo fuente, hash final, pruebas y despliegue: **pendientes de ejecución autorizada**.

