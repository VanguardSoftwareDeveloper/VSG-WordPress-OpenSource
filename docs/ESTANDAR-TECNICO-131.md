# Estándar técnico de los 131 plugins

**Fecha: 22/11/2026.** Este estándar es **planificación**, no código, requisitos de instalación ya comprobados ni certificación de rendimiento. Cada plugin tendrá su identidad, dependencias, licencia y distribución independientes.

## Compatibilidad inicial objetivo

| Tecnología | Decisión |
| --- | --- |
| WordPress | **7.1.5**, última estable verificada del 18/11/2026. Para nuevos plugins declarar `Requires at least: 7.1.5` cuando el código esté implementado y compatible; actualizar para futuras estables. |
| PHP | **8.3 mínimo** para nuevo desarrollo; comprobar también compatibilidad con ramas estables más recientes antes de distribuir. |
| Angular | **22.3.0 estable** observada a 18/11/2026; actualizar a la última estable compatible al iniciar cada plugin. |
| Angular Material + CDK | **22.3.0 estable** observada; usar componentes realmente necesarios y versión coordinada con Angular. |
| Build | Angular 22: Node `^22.22.3 || ^24.15.0 || ^26.0.0`, TypeScript `>=6.0.0 <6.1.0`, RxJS `^6.5.3 || ^7.4.0`. Registrar lockfile real al implementar. |

**Hostinger:** su documentación dice que PHP 8.3 es predeterminado para sitios **nuevos**, con opción de PHP 8.5. No se consultó el PHP real de tu instalación. Cambiar la versión en hPanel puede impactar varios sitios del mismo plan.

## Arquitectura objetivo

```text
Navegador: Angular 22 + Angular Material
             │ REST autenticada / permisos
WordPress: PHP >= 8.3, WP Hooks y dominio del plugin
             │ WP APIs, wpdb, tablas propias solo justificadas
Hosting del usuario: WordPress >= 7.1.5 + BD + archivos
```

- **PHP/WordPress manda:** autorización por capacidad y recurso, `permission_callback`, protección CSRF donde aplique, validación, consultas parametrizadas, escaping y secretos únicamente en backend.
- **Angular Material es la UI acordada**, con pantallas móvil/escritorio cuando se aprueben y carga del bundle exclusivamente en superficies del plugin. Assets precompilados fuera de Hostinger, **sin servidor Node de producción**.
- **Datos:** reutilizar funcionalidades nativas de WordPress (posts, opciones, meta, taxonomías) cuando basten. Crear SQL propio solo por necesidad, con migraciones y validación del motor real si se requiere consistencia transaccional.
- **Independencia:** no instalar todo el monorepo como un plugin; no introducir WooCommerce, Elementor, SaaS o componentes `shared/` obligatorios. Ningún bundle Angular global debe afectar a otros plugins.
- **Gratuidad:** código gratuito; APIs pagadas se configuran con cuenta/credenciales y facturación del usuario, nunca API keys personales del desarrollador sin un servicio opcional explícito.
- **Seguridad/licencia:** privacidad, acceso mínimo, límites de consumo, licencias/notices de componentes, backups y migraciones según riesgos de cada dominio.
- **Excepción existente:** PeruCommerce ya tiene código que declara mínimo WordPress **7.1.4** y PHP **8.3**, además de contratos propios. WordPress 7.1.5 es objetivo de comprobación; no cambiar su cabecera sin autorización técnica específica.

## Mínimo documental

En esta fase, cada carpeta debe contener **README.md** ya existente (identidad/finalidad) y **PLAN-BASE.md** nuevo (objetivo, arquitectura orientativa, dependencias, riesgos, pendientes y fases). Documentos futuros **solo después de aprobar alcance**: `docs/ALCANCE.md` para V1/exclusiones/UX/aceptación, `docs/CONTRATOS.md` si el dominio necesita REST/SQL/estados complejos, `docs/VERIFICACION.md` para pruebas reales autorizadas y `LICENSE`/notices/fuentes al publicar. No fabricar 131 workspaces Angular ni 131 suites vacías.

## Fuentes oficiales

- https://wordpress.org/news/2026/11/wordpress-7-1-5-release/
- https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
- https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/
- https://angular.dev/reference/versions
- https://github.com/angular/angular/releases
- https://github.com/angular/components/releases
- https://material.angular.dev/

## Política ampliada: idiomas e interfaces MCP

- Para **130 candidatos internacionales**, construir textos fuente ingleses localizables (`en_US`), y preparar traducciones `es_ES`, `es_MX`, `pt_BR` con evidencia antes de anunciarlas. **PeruCommerce** conserva interfaz `es_PE` exclusivamente. Ver [idiomas](INTERNACIONALIZACION-131.md).
- La integración **MCP con Claude Code y Codex es futura y opcional**. Solo después de definir alcance y permisos decidir si hace falta bridge local STDIO o endpoint HTTP conforme al protocolo vigente. No introducir infraestructura en la cuenta del fabricante ni crear endpoints públicos por esta nota. Ver [MCP](MCP-CLAUDE-CODEX-131.md).
- Identidades de presentación y slugs son distintos: conservar `Text Domain` real igual al slug de instalación, sin renombrar software distribuido o datos de clientes. Ver [131 nombres provisionales](IDENTIDADES-131.md).
