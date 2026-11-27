---
name: vsg-wp-implementacion
description: Implement an approved scope inside a single plugin folder of VSG-WordPress-OpenSource (PHP/WordPress backend, Angular with Angular Material frontend, optional WordPress Abilities). Use only when the owner has ordered implementation for a named plugin whose scope is approved.
license: GPL-2.0-or-later
compatibility: Claude Code, Codex and Antigravity in the VSG-WordPress-OpenSource repository.
metadata:
  owner: VSG
  version: "1"
---

# Implement inside one plugin

## Preconditions (stop if any fails)

1. `vsg-wp-inicio` is done: checkout, issue and authorization are clear.
2. The owner's order names the plugin and the scope to implement.
3. The plugin's scope is approved (phases F1/F2 in `docs/FASES-131.md`). The 130 planning folders have no approved scope yet: do not scaffold them.
4. PeruCommerce: follow gate P-B and the phase in `plugins/wp-peru-commerce/docs/43-ESTADO-IMPLEMENTACION.md`; its `AGENTS.md` prevails inside that folder.

## Boundaries

- Write only under `plugins/<slug>/`, plus shared documents the order names.
- No runtime dependency on another plugin, `shared/`, WooCommerce, Elementor or a SaaS. `Requires Plugins` never lists the MCP Adapter.
- Versions come from `docs/ESTANDAR-TECNICO-131.md`; if they may be stale, run `vsg-wp-investigacion` first.
- Add a dependency, table, REST route, capability or external service only when the approved scope requires it, and justify it in the plugin docs.

## Backend (PHP/WordPress)

- Every REST route has a real `permission_callback` that checks capabilities on the server. Admin forms and state-changing requests use nonces against CSRF; a nonce is not an authorization.
- Validate and sanitize input, escape output late, and use `$wpdb->prepare()` for every query with variables.
- Secrets stay server-side: never in browser-exposed options, logs or URLs.
- Strings are translatable with the plugin's text domain (its slug). Uninstall is non-destructive unless the owner decides otherwise.
- Register WordPress Abilities only when the approved scope justifies them, with input and output schemas and a permission check. The plugin works fully without the MCP Adapter or any AI client.

## Frontend (Angular + Angular Material)

- One Angular workspace per plugin under `plugins/<slug>/frontend/`, created only when its UI scope is approved.
- Standalone components with Angular Material and the CDK; follow the official Angular skills installed at user scope.
- Build outside the hosting. The output goes to the plugin's ignored assets folder and is enqueued only on that plugin's screens. No production Node server and no runtime CDN.
- The browser never decides permissions, prices, stock or payment state; it calls the plugin's REST API with the WordPress REST nonce.
- Accessibility (WCAG 2.2 AA) and touch-target sizes are acceptance targets to verify later, not claims.

## Closing a change

1. Review the diff and its direct consumers statically with `vsg-wp-auditoria`.
2. Update the plugin's status document with what changed and what was **not** executed.
3. Do not install, build, test, run wp-env or Docker, migrate or package without a separate explicit order.
4. If the unit stays open, hand off with `vsg-wp-continuidad`.
