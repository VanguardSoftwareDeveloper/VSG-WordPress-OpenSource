---
name: vsg-wp-auditoria
description: Static, read-only review of a diff, a plugin folder or the agent instruction system in VSG-WordPress-OpenSource. Use when asked to review, audit or check a change for security, contracts, plugin independence, licensing or documentation accuracy. Executes nothing.
license: GPL-2.0-or-later
compatibility: Claude Code, Codex and Antigravity in the VSG-WordPress-OpenSource repository.
metadata:
  owner: VSG
  version: "1"
---

# Static review

Read-only unless the order also asks for fixes. No build, test, install or service runs.

## Scope

State what is reviewed: a diff (`git diff`, `git diff --cached` or a commit range), a folder, or the instruction system. Load only the plugin documents the scope touches.

## Checklist

**Authorization and data**

- REST routes: `permission_callback` checks capabilities server-side; no `__return_true` on routes that read private data or change state.
- Nonces on admin forms and state-changing requests, treated as CSRF control and not as permission.
- Input validated and sanitized, output escaped, SQL through `$wpdb->prepare()`, file paths constrained.
- No secrets, tokens, customer data or personal configuration in code, fixtures or docs.

**Independence and contracts**

- No dependency on other plugins, `shared/`, WooCommerce, Elementor or a SaaS; `Requires Plugins` does not list the MCP Adapter.
- Plugin contracts respected. PeruCommerce: OpenAPI `0.12.0-draft`, decisions D-01…D-200 and gates P-A…P-D.
- Abilities, when present, have schemas and a permission check, and the plugin works without AI clients.

**Frontend**

- Angular and Angular Material match `docs/ESTANDAR-TECNICO-131.md`.
- Assets are enqueued only on the plugin's screens; no production Node server.
- The browser decides nothing authoritative.

**Distribution and licensing**

- GPL-2.0-or-later headers and `LICENSE` present; third-party assets and notices accounted for.
- Generated output is not committed.

**Documentation accuracy**

- No claim of a build, test, release, ZIP, adoption or integration without real evidence.
- Version numbers appear only in the technical standard; links resolve.

**Instruction system** (when in scope)

- Root `AGENTS.md` ≤ 8 KB, plugin `AGENTS.md` ≤ 16 KB; `CLAUDE.md` files only import.
- Claude skill wrappers match `.agents/skills`; rules carry a `trigger`.
- `tools/agent-policy/check-instructions.mjs` automates these checks; running it needs Node and an order that allows it.

## Report

For each finding give the file and line, what is wrong, a concrete failure scenario, the severity (blocking, important or minor) and whether it is confirmed or plausible. Then list what was reviewed and what was not, and confirm that nothing was executed.
