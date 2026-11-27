# Herramientas de desarrollo con IA — Claude Code, Codex y Antigravity

**Decisión (26/11/2026):** los tres asistentes comparten un núcleo común de skills y MCP oficiales, gratuitos y de mínimo privilegio. Las especializaciones se activan **bajo demanda**, cuando un plugin tenga alcance aprobado. No hay 131 skills, 131 servidores MCP ni 131 configuraciones.

Este documento trata del **entorno de desarrollo**. Las integraciones MCP que algún plugin pudiera ofrecer a sus usuarios son otra cosa: [MCP-CLAUDE-CODEX-131.md](MCP-CLAUDE-CODEX-131.md). Ninguna herramienta concede permisos; manda la matriz de autorización de [AGENTS.md](../AGENTS.md).

## Principios

- **Una sola copia de cada skill oficial**, a nivel de usuario en `~/.agents/skills`, gestionada con `npx skills` (CLI usado por WordPress/agent-skills y angular/skills). No se copia al repositorio ni se duplica por plugin.
- **Descubrimiento por descripción.** Cada cliente carga el cuerpo de una skill solo cuando la tarea coincide con su descripción, así que tener skills disponibles no gasta contexto. Exponer solo las necesarias evita activaciones erróneas.
- **MCP de solo lectura.** Las herramientas que arrancan procesos o escriben quedan fuera o piden aprobación.
- **Sin secretos ni rutas personales en Git.** Lo personal queda en el almacén de cada cliente; `tools/agent-policy/check-instructions.mjs` lo comprueba en `.codex/config.toml` y en los enlaces de `.claude/skills/`.
- **Versiones.** Los objetivos técnicos viven solo en [ESTANDAR-TECNICO-131.md](ESTANDAR-TECNICO-131.md). El MCP de Angular fija el mismo major que Angular y el checker compara ambos.

## Núcleo común

| Herramienta | Claude Code | Codex | Antigravity |
|---|---|---|---|
| Skills propias `vsg-wp-*` | Contenedores en `.claude/skills/` (existente) | `.agents/skills/` (existente) | `.agents/skills/` (existente) |
| Skills oficiales WordPress y Angular | Enlaces locales ignorados en `.claude/skills/` (**incorporado**) | `~/.agents/skills` (existente) | Catálogo de usuario enlazado a `~/.agents/skills` (existente) |
| MCP Angular CLI, solo lectura | Ámbito local del proyecto, fuera de Git (**incorporado**) | `.codex/config.toml` del proyecto (**incorporado**; carga cuando el proyecto es de confianza) | Entrada global con `--read-only` (existente; verificada el 27/11/2026) |
| GitHub | `gh` y MCP `github` de usuario (existente) | `gh`; el MCP `github` de usuario sigue deshabilitado | `gh` |
| Documentación técnica | `context7`, `microsoftLearn` (existente) | `context7`, `microsoftLearn`, `openaiDeveloperDocs` (existente) | `context7`, `microsoftLearn` (existente) |

Además, la herramienta `search_documentation` del MCP de Angular consulta angular.dev.

**Skills oficiales comunes**, de [WordPress/agent-skills](https://github.com/WordPress/agent-skills) y [angular/skills](https://github.com/angular/skills):

- `wordpress-router`: clasifica el repositorio y deriva al flujo adecuado.
- `wp-project-triage`: inventario del proyecto antes de cambiar nada.
- `wp-plugin-development`: estructura, hooks, seguridad y ciclo de vida del plugin.
- `wp-rest-api`: rutas, `permission_callback` y validación.
- `wp-plugin-directory-guidelines`: normas de WordPress.org.
- `wp-phpstan`: análisis estático PHP, sin ejecutarlo mientras no esté autorizado.
- `angular-developer`: Angular moderno (signals, formularios, rutas, accesibilidad, pruebas).

**MCP Angular CLI con `--read-only`:** expone seis herramientas marcadas de solo lectura.

- `get_best_practices`
- `search_documentation`
- `list_projects`, que descubre los workspaces Angular del repositorio.
- `ai_tutor`
- `onpush_zoneless_migration`, que solo analiza.
- `devserver_wait_for_build`

Quedan fuera `run_target`, `devserver_start` y `devserver_stop`.

**Sin MCP de WordPress.** El [MCP Adapter](https://github.com/WordPress/mcp-adapter) es un plugin de sitio: exige una instalación WordPress en marcha y queda pendiente hasta que el propietario autorice un entorno local. Tampoco se usan los MCP de Hostinger ni `vsg_pos`, que son operativos y no de desarrollo.

## Especializaciones bajo demanda

Se enlazan cuando un plugin con alcance aprobado las necesita y se registran en el issue de la unidad de trabajo. Si no están ya en `~/.agents/skills`, se instalan desde su fuente oficial con `npx skills add`.

| Necesidad del plugin | Skills o MCP | Condición |
|---|---|---|
| Abilities API o exposición a asistentes | `wp-abilities-api`, `wp-ability-development`, `wp-abilities-audit`, `wp-abilities-verify` | Alcance que justifique Abilities |
| Bloques e Interactivity API | `wp-block-development`, `wp-interactivity-api` | El plugin registra bloques |
| Entorno local WordPress | `wp-env`, `wp-playground`, `blueprint` | Autorización expresa para ejecutar el entorno |
| Rendimiento | `wp-performance` | Problema de rendimiento medido |
| Workspace Angular nuevo | `angular-new-app` | Alcance aprobado; nunca un workspace anticipado |
| PeruCommerce: SUNAT | Fuentes oficiales: [orientación SUNAT](https://orientacion.sunat.gob.pe/), [CPE](https://cpe.sunat.gob.pe/) y [legislación](https://www.sunat.gob.pe/legislacion/). Opcional: la skill de usuario `sunat-documentacion-oficial`, fuera del repositorio | Solo consulta documental. No es dependencia del plugin ni acredita cumplimiento tributario |
| PeruCommerce: pagos | `search_documentation` del MCP `mercadopago` de usuario; documentación oficial de PayPal por `context7` o la web | Solo documentación. El resto de herramientas de los MCP `mercadopago` y `paypal` opera cuentas reales y queda fuera |

No aplican a estos plugins `wp-block-themes`, `wp-patterns`, `wpds` ni `wp-wpcli-and-ops`.

## Puesta en marcha por cliente

Todos los comandos se ejecutan desde la raíz del repositorio; ninguno escribe secretos.

**Skills oficiales en `~/.agents/skills`.** Si faltan, instálalas a nivel de usuario. El instalador pregunta por los clientes de destino. El CLI `skills` envía telemetría anónima; para desactivarla, ejecuta antes `$env:DO_NOT_TRACK = '1'` (en macOS o Linux, `export DO_NOT_TRACK=1`).

```powershell
npx skills add WordPress/agent-skills --global --skill wordpress-router wp-project-triage wp-plugin-development wp-rest-api wp-plugin-directory-guidelines wp-phpstan
```

```powershell
npx skills add https://github.com/angular/skills --global --skill angular-developer
```

**Claude Code.** Claude no lee `~/.agents/skills`. Crea enlaces locales e ignorados en `.claude/skills/`; el checker rechaza cualquier enlace que no sea una skill oficial ignorada por Git.

```powershell
foreach ($s in 'wordpress-router','wp-project-triage','wp-plugin-development','wp-rest-api','wp-plugin-directory-guidelines','wp-phpstan','angular-developer') { New-Item -ItemType Junction -Path ".claude\skills\$s" -Target "$HOME\.agents\skills\$s" }
```

En macOS o Linux: `ln -s ~/.agents/skills/<skill> .claude/skills/<skill>`.

Registra el MCP de Angular solo para este proyecto. Queda en el ámbito local de Claude Code, fuera del repositorio.

```powershell
claude mcp add --scope local angular-cli -- cmd /c npx -y '@angular/cli@22' mcp --read-only
```

En macOS o Linux se omite `cmd /c`. Compruébalo con `claude mcp get angular-cli`. En Windows, regístralo desde PowerShell: Git Bash convierte `/c` en una ruta.

**Codex.** Las skills ya se leen de `~/.agents/skills`. Codex carga `.codex/config.toml`, sus reglas y el hook del proyecto solo si el proyecto es de confianza. Al abrir Codex por primera vez en la raíz, acepta la confianza y aprueba el hook en `/hooks`; después, `codex mcp list` debe mostrar `angular-cli`. El asistente no modifica `~/.codex/`.

**Antigravity.** Las skills llegan por el catálogo de usuario enlazado a `~/.agents/skills`. Su MCP de Angular es global y arranca con `--read-only` y el mismo major que fija el estándar técnico (comprobado en la configuración MCP del cliente el 27/11/2026). Si el propietario necesitara el modo completo en otro proyecto, debe hacerlo en una entrada aparte, sin quitar `--read-only` a esta.

## Almacenamiento

- Las skills tienen una sola copia en el perfil de usuario; el repositorio solo contiene enlaces ignorados en máquinas locales.
- `npx` usa la caché de npm del perfil de usuario: es una ruta de herramienta externa, no un directorio VSG. No se instalan paquetes en el repositorio.
- No se crean configuraciones por plugin. Si un plugin necesita una especialización, se enlaza en local y se anota en su issue.

## Pendientes y decisiones del propietario

- **Resuelto (27/11/2026):** la copia de usuario de `angular-developer` ya incluye la guía sobre trabajo costoso en plantillas, y el MCP de Angular de Antigravity arranca con `--read-only`.
- **Codex:** confiar el proyecto para que cargue su configuración.
- **WordPress local:** `wp-env`, Playground y MCP Adapter, solo con autorización expresa.

## Referencias oficiales

- Angular CLI MCP: https://angular.dev/ai/mcp
- Agent skills de Angular: https://github.com/angular/skills
- Agent skills de WordPress: https://github.com/WordPress/agent-skills
- WordPress MCP Adapter: https://github.com/WordPress/mcp-adapter
- Claude Code, skills: https://code.claude.com/docs/en/skills
- Claude Code, MCP: https://code.claude.com/docs/en/mcp
- Codex, MCP: https://learn.chatgpt.com/docs/extend/mcp
