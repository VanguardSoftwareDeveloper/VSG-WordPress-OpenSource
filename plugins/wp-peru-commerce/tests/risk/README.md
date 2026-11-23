# IMP-69 — Banco de escenarios, fixtures y verificaciones de riesgo

**Estado: IMPLEMENTADO EN FUENTE; NO EJECUTADO.** Exclusivamente para PeruCommerce 0.12.0-draft, schema declarativo 1.0.2 y el código actualmente en main. Este banco de pruebas NO certifica un checkout, webhook, almacenamiento, WCAG, compatibilidad Hostinger ni recuperación. No añade dependencias, rutas REST, tablas o permisos de producción.

## Inventario y propósito

| Pieza | Evidencia futura y límite |
| --- | --- |
| fixtures.json | Productos, carrito físico con efectivo, carrito mixto MP, 2 binarios históricos, recurso/slot/stock único, fechas UTC, actores A/O y claves/identificadores **ficticios**; no contiene cuentas ni correos reales. No siembra BD. |
| scenarios.json | 36 escenarios con entrada, preparación, efecto y resultado observable **independiente** del algoritmo: dinero, stock, citas, pérdida de respuesta, Orders MP, webhook, idempotencia, claims, expedientes, legal, permisos, privacidad, expiración, upgrades, restore, frontend/UX. Cada uno tiene referencia de aceptación A y clasifica el entorno. |
| contract.test.mjs | Verificación **offline** de OpenAPI principal, rutas/DTO, referencias, statuses, cobertura de escenarios, integridad de fixtures y plazos sintéticos. Confirma consistencia documental, **no** comportamiento PHP. Node nativo test/assert; sin npm ni dependencias. |
| domain.php | Invoca directamente Money, Business_Time y Domain_Validation reales desde CLI, con expectativas numéricas y temporales explícitas, entradas inválidas, límites y carrito sintético. No bootstrapea WordPress ni modifica nada. |
| http-denials.test.mjs | Envía peticiones malformadas/anónimas a REST del WP **local** para comprobar denegación de admin/guest/checkout/credenciales/webhook sin firma, no fuga de secretos. Solo host loopback + opt-in explícito; no crea pedidos válidos ni usa credenciales MP. |
| db-evidence.php | Para WP-CLI en staging **local**, solo SELECTs sobre las tablas reales; cuenta órdenes con importes inconsistentes, crédito inicial duplicado, recursos duplicados, holds excesivos, descargas por encima del límite y duplicación de idempotencia. No guarda datos personales ni accede a MP. Cero anomalías **no** demuestra que hubo concurrencia o restore correcto. |

Todas las pruebas se mantienen bajo tests/risk y **no** se incorporan al ZIP instalable ni a la fuente GPL distribuible por el script de staging, que conserva su alcance de entrega de código del plugin/Angular. Este banco pertenece al repositorio de desarrollo privado.

## Extensión de inventarios y consumidores — IMP-70 (sin ejecución)

El archivo coverage.test.mjs añade una comprobación de **paridad de fuente**, para una futura ejecución autorizada con Node nativo: 69 REST/66 rutas/138 schemas, R8-18 página WP, 69 gates y Access/RestContract, 63 bindings JSON + seis rutas especiales, controladores existentes y 70 filas documentales R8, 56 PER, 39 tablas, 16 DB-A y A-01…A-202. Se limita a comparar textos/rutas e inventarios; **no prueba** permisos en runtime, persistencia, contadores de stock ni la calidad UX real. Las matrices en docs/44 y docs/45 incluyen consumidor ausente cuando corresponde (R8-16, R8-60/61). No atribuir éxito de un test de inventario a una operación comercial.

Comando previsto solo si existe permiso de ejecutar esta verificación:

~~~powershell
node --test tests/risk/coverage.test.mjs
~~~

## IMP-71 — escenarios específicos tras auditoría integrada (NO ejecutados)

La integración de UI y backend requiere verificaciones **nuevas** sobre estos recorridos, sin considerarlos probados por las inspecciones estáticas:

- **C71-UBIGEO:** buscar distrito por nombre/acento/tilde/código con R8-16 `page=1&per_page=30&q=` (incluye query codificada), elegir uno habilitado, comprobar que la tarifa final solo sale de R8-05, nunca de la lista, y que el cambio de cobertura obliga a recotizar. Repetir con gate INEI no verificado: sin compra ni falsa disponibilidad.
- **C71-DIGITAL-A:** cuenta A con `orders_read` y `digital_files_manage` crea, mediante POST nativo `admin-post.php` + nonce, un caso tipado desde solicitud guest R8-12 para un derecho de compra multiarchivo; exigir HTTPS, pertenencia real, un caso original, idempotencia y fallo sin modificar derechos/finanzas. Probar sesiones O/anónimas/CSRF y replay con misma clave, cuerpo distinto, pérdida de respuesta. Las referencias nunca aparecen en URL de redirect.
- **C71-DIGITAL-B:** R8-61 rechaza decisión sin evidencia y cuenta O; A decide `authorize_revocation` por caso legitimado, CAS de fila, evento inmutable y `R8-21` solo A proyecta referencia opaca de ese evento en caso cerrado. R8-60 se activa **aparte**, usando `case_id`, `right_id`, `expected_version` y referencia idéntica; revoca tokens de **un** derecho sin alterar otros archivos/derechos originalmente vendidos, ni dinero o stock. Intentos simultáneos, replays y referencias ajenas deben fallar cerrado.
- **C71-COMPILACIÓN:** confirmar tipos Angular/FormsModule, `ngNoForm` nativo, propagación de atributos data desde PHP, URLs HTTPS same-origin y respuestas DTO reales. Una inspección textual no es compilación ni prueba de navegador.
- **C71-MAINTENIMIENTO:** con epoch externa rotada, verificar que el restore de un estado `open` histórico no habilita un checkout; ningún callback de prueba falso debe desbloquear caja o proveedor.

Estos casos completan las expectativas de `docs/46-AUDITORIA-INTEGRADA-IMP-71.md` y se agregan a la planificación de IMP-72; **no** existe todavía código que automatice una integración WordPress+MP de estos recorridos ni resultados registrados. No simular proveedor como evidencia real.

## Protocolo para una futura ejecución autorizada (NO ejecutado en IMP-69)

Antes de ejecutar cualquier comando debe existir autorización P-C específica, PHP 8.3+ y/o Node compatible reales y, para pruebas de integración, una **instalación WordPress exclusivamente de laboratorio** con MySQL/MariaDB, PHP, HTTPS y configuración/factores de cada caso verificables. Los scripts no crean ese entorno, no conectan credenciales ni fuerzan la apertura de gates.

**Verificaciones locales sin WordPress (revisan código/contratos):**

~~~powershell
node --test tests/risk/contract.test.mjs
php tests/risk/domain.php
~~~

**Negativas REST, solo servidor local explícitamente autorizado:**

~~~powershell
$env:PC_RISK_ALLOW_HTTP_NEGATIVE = 'I_CONFIRM_LOCAL_STAGING'
$env:PC_RISK_BASE_URL = 'http://127.0.0.1:8080/wp-json/peru-commerce/v1/'
node --test tests/risk/http-denials.test.mjs
Remove-Item Env:PC_RISK_ALLOW_HTTP_NEGATIVE,Env:PC_RISK_BASE_URL
~~~

Un host remoto se rechaza incluso con opt-in. Se requieren credenciales falsas de laboratorio y **ningún** webhook legítimo, correo real, dinero de proveedor o checkout válido es enviado por el script. Un 404/423 puede indicar gate cerrado y NO acredita que la ruta funcional esté probada. Un 500 es fallo, no denegación válida.

**Snapshot SQL de solo lectura, sobre WP-CLI local de laboratorio:**

~~~powershell
$env:PC_RISK_ALLOW_DB_READ = 'I_CONFIRM_ISOLATED_STAGING'
wp eval-file tests/risk/db-evidence.php
Remove-Item Env:PC_RISK_ALLOW_DB_READ
~~~

Sólo acepta WP_CLI y URL home loopback, requiere plugin activo y nunca hace UPDATE, INSERT, ALTER, DROP ni llamadas HTTP. Imprime contadores generales sin IDs, correo ni secretos. Un informe sin anomalías es solo un snapshot y **no** demuestra separación de dos transacciones, delivery de correo, identidad, integridad de recursos externos o restaurabilidad. Para una prueba de carrera, comparar snapshots antes/después **más** trazas de dos conexiones SQL realmente simultáneas.

## Ejecución futura de casos que requieren laboratorio e intervención controlada

1. **Dinero e idempotencia:** sembrar ficticiamente un comercio de pruebas autorizado con variantes 101/102/103 y dos usuarios de laboratorio. Congelar datos para cada escenario; capturar IDs/ledger/commitments/casos antes de la acción. Ejecutar dos peticiones con sockets/sesiones realmente separados y controlar el punto de carrera; comparar movimientos/recursos después. No usar la misma conexión para aparentar concurrencia.
2. **Mercado Pago Perú:** solo con cuenta y entorno expresamente autorizados; usar firma y Orders API reales para casos R69-04/10/13/14/29. Capturar POST vs GET de referencia, cuenta/ambiente, importes, webhook firmado y estados financieros por servidor, **sin** grabar tokens en resultados. Cuando el proveedor no permita el ensayo, marcar BLOQUEADA_POR_PROVEEDOR; jamás mockear un pago confirmado y contarlo como prueba real. Un stub puede probar código local, no conformidad de MP.
3. **A/O/guest:** sesiones WP de laboratorio distintas, grants por pedido/finalidad y evidencias ficticias. Comparar el JSON bruto (no solo botones Angular) entre roles y verificar denegación cross-order. No reenviar OTP a un buzón externo ni usar datos de reclamantes.
4. **Storage/digital:** crear dos ficheros de laboratorio de bytes controlados, calcular hash SHA-256 con una herramienta independiente; vender versiones y sustituir asignación actual. Comprobar que cada stream corresponde al archivo histórico, que último uso concurrente solo permite un stream y que revocaciones resisten restauración. No exponer filesystem en logs/URL ni descargar datos comerciales.
5. **Upgrade/restore:** elegir baselines 1.0.0 y 1.0.1 de prueba con journal y backup físico reales; forzar una interrupción tras ALTER del segundo proceso, comprobar columnas, versión y reentrada. Comparar copia BD+privados+config+assets con el mismo corte. Cambiar epoch externa antes de exponer un restore de opción open previa. Conciliar sucesos externos desde corte antes de abrir; no tratar revertir ZIP como rollback SQL.
6. **UX:** comprobar A-193…A-202 en Chrome/Firefox/teclado/lector y móvil/escritorio; medir rectángulos reales de acción táctil ≥ 44×44 px CSS, reflow/zoom, foco y estados 409/202 sin mensajes falsos de pago. Un test estático o Figma no demuestra WCAG 2.2 AA.
7. **Evidencia y estado:** por cada caso conservar ID, commit, WP/PHP/SQL/Angular efectivos, host/ambiente redacted, fixture exacto y digest, condición inicial, dos trazas con tiempo UTC, operaciones reales, snapshots antes/después, fuentes externas verificadas, resultado esperado vs observado, y fallo/bloqueo. No usar status "passed" cuando solo se ejecutó el verificador offline. No incluir datos personales ni credenciales.

## Criterio de cierre de esta fase y límites restantes

**IMP-69 se puede cerrar solo estáticamente** cuando existan los archivos con casos independientes, fixtures sintéticos y código de comprobaciones sin dependencia de terceros para las pruebas automatizables, integrados con el OpenAPI vigente. No es válido generar tests que repitan la función o aceptar que un JSON describe un éxito real. El operador debe conservar los pasos de integración, fallo de COMMIT/HTTP, carreras y validaciones externas pendientes de P-C/P-D. IMP-70 verifica la cobertura de contratos/consumidores, IMP-71 audita la fuente, IMP-72 se reserva las verificaciones/autorizaciones y artefactos finales.

**No ejecutado ahora:** Node, PHP, WP CLI, SQL, pruebas, migraciones, servicios, usuarios de prueba, credenciales, navegador, provider, ZIP y despliegue. Se preserva main y la política fail-closed por módulo.
