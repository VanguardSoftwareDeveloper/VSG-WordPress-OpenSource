# Instructions for agents — VSG WordPress OpenSource

Canonical instruction source for Claude Code, Codex and Antigravity in this repository. `CLAUDE.md` imports this file; the client folders (`.claude/`, `.codex/`, `.agents/`) only wrap it and must not restate its rules.

## Precedence

1. The owner's operating policy outside this repository and the current task order.
2. This file.
3. The nearest `plugins/<slug>/AGENTS.md`, for files under that plugin.
4. Skills and rules: they add procedure and knowledge, **never permissions**.

On conflict or missing authorization, stop, state the conflict and propose the minimum change.

## Repository

- Public canonical repository for **131 VSG WordPress plugin candidates**. It is not 131 released plugins. Licensing: [LICENSE-SCOPE.md](LICENSE-SCOPE.md).
- Each `plugins/<slug>/` is an independent candidate with its own slug, identity, license, contracts, frontend and ZIP. Product names end in **VSG**. Never make one plugin require another plugin, `shared/`, WooCommerce, Elementor or a SaaS.
- `plugins/wp-peru-commerce/` (PeruCommerce VSG) is the only folder with source code. It is Peru-specific with a Spanish (es-PE) UI. The other 130 folders are plans (`README.md`, `PLAN-BASE.md`, `PRODUCT-VISION.md`); their UX and technical targets remain proposals pending explicit product scope. International candidates are English-first and translatable.
- This repository is the source of truth. [docs/SOURCE-CONSOLIDATION.md](docs/SOURCE-CONSOLIDATION.md) records how the PeruCommerce source arrived. Older mentions of a separate private or future public repository are historical and superseded **only for repository location**, never for security, domain or release contracts.
- `VanguardSoftwareDeveloper/VSG-WordPress` is the separate private platform product: never delete it, modify it or change its visibility from work in this repository. Retiring the former private `Vanguard-WordPress-Plugins` was an owner-only operation; its one-off script remains only in Git history.

## Routing: load context on demand

- Before reading or editing anything under `plugins/<slug>/`, read that folder's `AGENTS.md` when present, then its `PLAN-BASE.md` and `PRODUCT-VISION.md`. Do not load other plugins' documents unless the task needs them.
- Technical targets (WordPress, PHP, Angular, Angular Material, Node, TypeScript) live only in [docs/ESTANDAR-TECNICO-131.md](docs/ESTANDAR-TECNICO-131.md). Do not copy version numbers elsewhere; verify the latest stable compatible versions in official sources before implementing.
- Catalogue and phases: [docs/CATALOGO.md](docs/CATALOGO.md), [docs/INDICE-PLANES-131.md](docs/INDICE-PLANES-131.md), [docs/FASES-131.md](docs/FASES-131.md). MCP stance: [docs/MCP-CLAUDE-CODEX-131.md](docs/MCP-CLAUDE-CODEX-131.md).

## Product invariants

- **Free by default.** External services run on accounts and billing belonging to the site owner. Never commit vendor secrets, credentials, customer data, dumps or personal configuration.
- **Frontend:** Angular with Angular Material at the latest verified stable compatible versions, precompiled per plugin and loaded only on that plugin's screens; no production Node server. Create a plugin's Angular workspace only after its scope is approved, never 131 workspaces or empty suites.
- **PHP/WordPress governs** authorization (capabilities, `permission_callback`, nonces where applicable), validation, escaping, prepared queries and secrets.
- **Product MCP is separate from development tooling.** No MCP server is implemented in this repository. A plugin may register WordPress Abilities when its approved scope justifies it. The WordPress MCP Adapter stays an optional site-owner plugin and never appears in `Requires Plugins`. Do not create MCP servers, skills or workspaces per plugin by default.
- Do not invent downloads, adoption metrics, integrations or releases.

## Authorization matrix

| Action | Status |
|---|---|
| Read, search, static review; edit docs and plans within the task | Allowed |
| Install dependencies, build, test, run wp-env or Docker, migrations, signing, packaging ZIPs | Only with explicit owner authorization for that run |
| Commit | Only when asked; stage explicit paths, never `git add -A` |
| Push, merge, release, tag, publish to WordPress.org or any site | Only with explicit owner authorization |
| Force-push, history rewrite, `git reset --hard`, `git clean -fdx`, worktrees, extra clones, temporary branches | Forbidden unless the owner grants a bounded exception |

Report static inspection, build, tests, deployment and compliance as distinct things. Never claim that a release, build, test result, ZIP or integrated POS exists without real evidence; the presence of source code is not evidence.

## One writer, one checkout

Single permanent branch `main`. Before writing, check the root, `git status`, branch, HEAD and concurrent sessions; only one assistant writes to this repository at a time. Read-only reviewers may work alongside. Do not revert others' work.

## Continuity

Durable state lives in a GitHub issue opened from the "Unidad de trabajo" template, not in chat or automatic memory. Record objective, SHA, files, real evidence, pending items and next step, and update it before stopping or when quota runs out.

## Storage

Temporary probes go outside the repository, in the owner's temporary area, and are removed when the task closes. Do not commit generated output (`node_modules/`, `dist/`, `.angular/`, `coverage/`, ZIPs); PeruCommerce's own `.gitignore` governs its build outputs.

## Skills and controls

Portable skills live in `.agents/skills/`; Claude Code wrappers in `.claude/skills/` point to them:

- `vsg-wp-inicio`: start or resume a unit of work.
- `vsg-wp-investigacion`: verify versions and APIs in official sources.
- `vsg-wp-implementacion`: implement inside one plugin after scope approval.
- `vsg-wp-auditoria`: static review.
- `vsg-wp-publicacion-autorizada`: release steps, only on an explicit owner order.
- `vsg-wp-continuidad`: handoff to the tracking issue.

Official WordPress and Angular agent skills are installed at user scope, not vendored here.

Native client rules are the primary barrier: deny/ask in `.claude/settings.json` and forbidden/prompt in `.codex/rules/vsg.rules`. The policy hook `tools/agent-policy/guard.mjs` is a second layer for variants that prefix rules miss; it fails closed when it cannot load (Claude Code `onFailure: "block"`; the Codex and Antigravity loaders deny). Neither layer is an authorization. Start every session at the repository root so these controls load; Codex also needs the project trusted and its hook approved in `/hooks`.
