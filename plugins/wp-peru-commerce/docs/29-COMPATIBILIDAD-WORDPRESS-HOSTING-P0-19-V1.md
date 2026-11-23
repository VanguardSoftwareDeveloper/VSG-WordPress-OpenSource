# P0-19 — Compatibilidad de plataforma y alojamiento (revisión documental 2026-11-17)

**Estado:** CONTRATO DE OBJETIVO TÉCNICO Y MATRIZ DE PREVUELO **DOCUMENTALES** (D-169 aprobada como objetivo); sin acceso ni comprobaciones al alojamiento real. No implica compatibilidad certificada, ni autorización de cambiar ajustes de Hostinger, instalar, consultar la BD, ejecutar SQL, compilar, iniciar servicios o publicar el plugin.

## 1. Versiones estables contrastadas con fuentes oficiales

| Pieza | Versión objetivo V1 | Estado de evidencia |
| --- | --- | --- |
| WordPress | 7.1.4 o una publicación estable posterior, sin RC/beta | WordPress.org registra **7.1.4, 2026-10-28**. WP 7.2 seguía RC al corte. Es objetivo, NO versión instalada en el comercio. |
| PHP runtime | 8.3+ bajo soporte, arquitectura de 64 bits para IDs/aritmética seguros | Recomendación oficial WordPress; depender de `mysqli`, `curl`, `mbstring`, `fileinfo`, `openssl`/primitivas criptográficas, `json` y **BCMath obligatorio según D-167**. Extensiones y parches se deben comprobar en instalación concreta. |
| BD transaccional | MariaDB 10.11+ **o** MySQL 8.4+, todas las tablas críticas InnoDB | Recomendación WordPress. Hostinger Web/Cloud **publica MariaDB e InnoDB**; **la versión, permisos y configuración del sitio real no están comprobados**. |
| Angular de desarrollo | 22.x; Node ^22.22.3 / ^24.15.0 / ^26.0.0; TypeScript >=6.0.0 <6.1.0; RxJS ^6.5.3 / ^7.4.0 | Matriz Angular oficial. Node solo para compilar **fuera del hosting**; PHP/WordPress monta JS/CSS precompilados en ZIP. |
| Transporte | HTTPS real con certificados válidos y URL canónica WP | Invariante para pagos, tokens y descargas; no basta que la URL de configuración use https si existe proxy/caché que filtre secretos. |
| Core WordPress | API REST, roles/capabilities, `wp_mail`, opciones, cron, plugins, hooks, `$wpdb` | No hay WooCommerce ni otro plugin obligatorio. Probar compatibilidad después de recibir autorización. |

**Fuentes:**  
WordPress releases: https://wordpress.org/download/releases/ ; requisitos: https://wordpress.org/about/requirements/ ; Angular 22: https://angular.dev/reference/versions ; Hostinger PHP: https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/ ; Hostinger motor: https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/ ; Hostinger InnoDB: https://www.hostinger.com/support/which-databases-and-data-tools-are-supported-at-hostinger/ .

**Verificación pública adicional (2026-11-17):** [WordPress Release Archive](https://wordpress.org/download/releases/) sigue mostrando 7.1.4 (28-10-2026) como estable; [Angular releases](https://angular.dev/reference/releases) marca la rama 22 como activa y su [matriz de compatibilidad](https://angular.dev/reference/versions) publica Node/TypeScript/RxJS. Estos datos solo confirman el **objetivo documental**, nunca los binarios instalados o un build. Para priorizar los controles del docs/39, HOST-01…12 son **puertas de verificación de un entorno real** durante las etapas P-C/P-D, no pruebas ejecutables antes de que exista el software; no se habilitan mutaciones de stock/cita/cobro sin pasar HOST-03/04/06/07 y lo que aplique.

## 2. Distinción decisiva de Hostinger

- **Documentado públicamente:** los planes Web/Cloud utilizan **MariaDB**, admiten **InnoDB**, ofrecen control de versión y extensiones PHP en hPanel y permiten consultar la versión real del motor desde phpMyAdmin.
- **No documentado como atributo de esta instalación:** plan contratado, versión exacta y configuración de MariaDB, privilegios DDL, `sql_mode`, aislamiento predeterminado, `innodb_page_size`, `max_allowed_packet`, índice máximo efectivo, tablas existentes, PHP/BCMath activos, capacidad de archivos privados, límites de disco, credenciales y configuración de caché.
- **No se debe confundir** compatibilidad genérica del proveedor con soporte probado para el checkout de una tienda ni con un contrato de SLA.
- En planes compartidos puede existir límite de concurrencia, tamaño por base, email, memoria/CPU/I/O o funciones bloqueadas, variable por plan y fecha. El plugin debe detectar **requisitos**, no asumir cifras publicitarias universales.

### 2.1 Matriz de diagnóstico futuro — solo lectura, no ejecutar en esta fase

| ID | Evidencia que deberá obtenerse en una instalación autorizada | Condición necesaria | Reacción de diseño ante incumplimiento |
| --- | --- | --- | --- |
| HOST-01 | Versión estable WordPress/PHP y arquitectura de PHP | WP >=7.1.4, PHP >=8.3 mantenido, funciones 64 bits verificadas | Impedir activación de operaciones no compatibles; no ocultar incompatibilidad |
| HOST-02 | Extensiones `mysqli`, `curl`, `mbstring`, `json`, `fileinfo`, `openssl`/crypto y **bcmath** | Todas las funciones obligatorias realmente disponibles | Bloquear instalación/checkout dependiente; nunca sustituir precisión por float |
| HOST-03 | Nombre, versión y configuración del motor SQL, engine efectivo de **cada** tabla y permisos necesarios | MariaDB >=10.11 / MySQL >=8.4, InnoDB real, transacciones y privilegios autorizados | **Bloquear checkout, stock y citas** si no hay garantías ACID |
| HOST-04 | Aislamiento y comportamiento de bloqueos de fila, deadlocks y timeouts | `SELECT FOR UPDATE` dentro de transacción real, locks por variante/recurso-día | No prometer reserva concurrente; checkout deshabilitado si no se puede garantizar |
| HOST-05 | Charset/collation reales, tamaño de índices y longitud total de identificadores con `$wpdb->prefix` | Índices y 39 tablas de diseño físicamente viables sin colisiones | No intentar migración incompatible ni degradar uniques silenciosamente |
| HOST-06 | HTTPS, cabeceras, URL WordPress y ruta REST real (raíz o subdirectorio) | Cookies HttpOnly/Secure/SameSite y CSRF compatibles con hosting y caché | Bloquear enlaces privados/acciones sensibles que no puedan protegerse |
| HOST-07 | Directorio privado no accesible por HTTP, permisos, enlaces simbólicos, streaming, Range y tamaño máximo | Protegido ante acceso directo, LiteSpeed, Nginx y CDN, sin bypass | Deshabilitar descarga/venta digital; no dejar URL pública |
| HOST-08 | Versión/config de `wp_mail`, límites de envío y cola operativa | Guardar aviso/estado antes de intentar transporte; mostrar no entregado correctamente | Mantener confirmación web y bandeja, no fingir correo recibido |
| HOST-09 | WP-Cron/cron real, frecuencia, límites y carga | No depender de puntualidad para expirar 15 min ni para evitar doble pago | Expiración **lógica en operaciones**, cola durable, recuperación oportunista |
| HOST-10 | Memoria y tiempo PHP, `upload_max_filesize`, `post_max_size`, disco/inodos, máximo de paquete SQL, límites de base | Admitir ZIP, archivos y snapshots con límites **configurados** y copia restaurable | Restringir tamaño por módulo/hosting; no extrapolar límites de un plan a otro |
| HOST-11 | ZIP manual, permisos de filesystem y capacidad de activar/desactivar módulos | Migraciones versionadas, respaldo consistente de BD+archivos privados+configuración | Bloquear escritura crítica durante transición o instalación incompleta |
| HOST-12 | Configuración de Mercado Pago de **una cuenta peruana autorizada para pruebas** | Corroborar Orders sin payer para físico sin email, ítem Entrega y PT15M | Mantener **MP deshabilitado** si alguna condición falla; no cambiar a Preferences ni inventar correo |

**Importante:** no se ha conectado hPanel, phpMyAdmin, WordPress instalado ni Mercado Pago. Esta tabla es un **contrato de futura comprobación autorizada**, no una lista de checks ya completados.

## 3. Particularidades operativas verificadas del proveedor

1. **PHP:** Hostinger permite elegir rama PHP; la versión activa y extensiones se consultan en PHP Info y configuración PHP. Las extensiones varían por rama, por lo que `bcmath` no se da por activa hasta comprobación.
2. **MariaDB:** Hostinger Web/Cloud usa MariaDB; una guía oficial indica cómo comprobar el número de versión real en phpMyAdmin. No se infiere su versión por la fecha de la página pública.
3. **InnoDB:** el proveedor declara soporte del motor. Para E-05, `FOR UPDATE` **sólo funciona como bloqueo transaccional** y el nivel de aislamiento cambia las lecturas/gaps. Se exige fila estable por variante y recurso/día, no bloqueo sobre intervalo vacío.
4. **Email:** la documentación de límites Hostinger publica para envío integrado **10 correos/min y 100 correos/día** en las categorías listadas (cifra orientativa de proveedor, verificar plan). E-06 conserva eventos en outbox, administra reintentos y nunca confunde `wp_mail()` TRUE con entrega. Si volumen excede capacidad, un proveedor SMTP independiente sería una decisión operacional aparte, **no requisito de compra de cliente**.
5. **WP-Cron:** WordPress documenta que se dispara al cargar páginas, no en horario garantizado. Hold de 15 min se determina por UTC efectivo durante cotización/checkout/cancelación, no por último cron.
6. **Capacidad:** Hostinger publica límites de BD/disco/PHP por plan y pueden variar; no fijar tamaño permitido de archivos digitales a partir de un plan no conocido. Los blobs digitales son archivos privados, no grandes `LONGTEXT` en SQL.
7. **Despliegue:** WordPress no ejecuta `register_activation_hook()` como parte automática de cada actualización; instalador/upgrade necesita comprobación explícita de versión de esquema. No usar el ZIP anterior como reversión de migración SQL.

**Fuentes:**  
Hostinger PHP versiones https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/ ; PHP extensions https://www.hostinger.com/support/4667515-how-to-manage-php-extensions-and-options-in-hostinger/ ; PHP Info https://www.hostinger.com/support/1583467-how-to-check-the-php-information-of-your-website-in-hostinger/ ; versión de base https://www.hostinger.com/support/how-to-check-your-database-version-in-phpmyadmin-at-hostinger/ ; motor InnoDB https://www.hostinger.com/support/which-databases-and-data-tools-are-supported-at-hostinger/ ; límites https://www.hostinger.com/support/6976044-parameters-and-limits-of-hosting-plans-in-hostinger/ ; WordPress Cron https://developer.wordpress.org/plugins/cron/ ; plugin upgrade https://developer.wordpress.org/plugins/creating-tables-with-plugins/ .

## 4. Semántica de transacciones y migraciones aplicable a Hostinger

- **Lógica transaccional:** nunca se asume protección porque una consulta tenga `FOR UPDATE` si está fuera de una transacción real. En MariaDB InnoDB hay diferencias entre READ COMMITTED y REPEATABLE READ; la estrategia V1 será bloquear **filas estables** de variante o recurso/día, independientemente de gap locks.
- **No bloquear a terceros innecesariamente:** filas/índices estables, bloqueos en orden determinista, límites de duración y reintentos de deadlocks **acotados** con respuesta HTTP semántica `pc_*` E08-M.
- **Sin HTTP con SQL abierta:** MP Orders, correo, mapas y datos externos se consultan fuera de la transacción; confirmar un pago requiere recurso/hold local real y verificación externa.
- **dbDelta:** útil para tablas/columnas/índices según el formato que exige WordPress, pero no es un motor integral de FK, migraciones reversibles y validaciones de invariantes de negocio. Cada esquema nuevo/upgrade requiere paso explícito y resultados comprobados. Las instrucciones del plugin no autorizan todavía crear DDL ejecutable.
- **DDL no es rollback transaccional:** CREATE/ALTER/DROP pueden confirmar implícitamente. Respaldo consistente y plan de restauración, con reconciliación MP después de recuperar, son obligatorios antes de ofrecer actualización ZIP.

Fuentes oficiales: https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update ; https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/set-commands/set-transaction ; https://mariadb.com/docs/server/reference/sql-statements/transactions/start-transaction ; https://developer.wordpress.org/reference/functions/dbDelta/ ; https://developer.wordpress.org/plugins/creating-tables-with-plugins/ .

**Runbook P0-26 complementario (2026-11-17):** `docs/30-CICLO-DE-VIDA-ZIP-RESPALDO-RECUPERACION-P0-26-V1.md` utiliza estos requisitos HOST para 12 gates LC de instalación/upgrade/restore, copias coherentes y módulos con fail-closed. Que Hostinger ofrezca backups no demuestra que incluya binarios fuera del webroot, claves externas o que exista restore probado de esta instalación. No se abrió hPanel ni se ejecutaron verificaciones.

**Contrato P0-20 de empaquetado posterior (2026-11-17):** `docs/31-CONTRATO-ANGULAR-WORDPRESS-ASSETS-P0-20-V1.md` detalla compilación Angular 22 fuera del hosting, módulos ESM/carga condicional WordPress, manifest y rutas en subdirectorio; estos son **objetivos de diseño sin build emitido**. El hosting real sigue sin verificarse y Node no es requisito del WordPress instalado. El contrato de licencia/fuentes por versión se encuentra en `docs/32-LICENCIA-FUENTES-Y-DISTRIBUCION-P0-24-V1.md`.

## 5. Evidencia y siguiente puerta

**Verificado desde documentación pública:** versiones objetivo WP/PHP/MariaDB/Angular; Hostinger Web/Cloud usa MariaDB y soporta InnoDB; PHP/BCMath comprobables por sitio; límites de correo publicados; WordPress cron no puntual; dbDelta y DDL con limitaciones conocidas.

**No comprobado ni autorizado:** versión/phpinfo/credenciales y permisos de instalación Hostinger específica, engine y aislamiento reales, existencia de carpetas privadas, parámetros PHP/SQL por plan, correo entregado, seguridad de streaming, MP Perú, instalación WordPress/ZIP o carga de 39 tablas. No declarar HOST-01…HOST-12 como superados.

**Próximo trabajo complementario P0-18:** `docs/28-AUDITORIA-ESTATICA-MODELO-FISICO-P0-18-V1.md` define trazabilidad de 39 entidades, PK/únicos, claves y migraciones **sin ejecutar SQL**. Solo tras ratificación del esquema/hosting se podrá proponer DDL ejecutable con autorización específica.
