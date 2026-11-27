---
name: vsg-wp-continuidad
description: Write the handoff of a unit of work in VSG-WordPress-OpenSource to its GitHub tracking issue. Use before stopping, when quota or context runs low, when switching tasks, or when the owner asks for status. Keeps durable state out of chat and automatic memory.
license: GPL-2.0-or-later
compatibility: Claude Code, Codex and Antigravity in the VSG-WordPress-OpenSource repository. Posting needs GitHub access and an order that allows it.
metadata:
  owner: VSG
  version: "1"
---

# Handoff to the tracking issue

## When

Before stopping, before context or quota runs out, before switching to another task, and when a unit closes.

## Steps

1. Collect facts with read-only commands: `git status --short`, `git rev-parse HEAD`, `git log --oneline origin/main..HEAD`, `git diff --stat`.
2. Retire your own temporary files (probes, scratch copies) and list what you removed. Keep deliverables and evidence.
3. Fill in the template below with facts only; if something was not run, say so.
4. Post it as a comment on the "Unidad de trabajo" issue when the order allows writing to GitHub; otherwise give it to the owner to post. Do not create handoff files in the repository and do not rely on automatic memory.

## Template

```markdown
## Relevo — YYYY-MM-DD

- Objetivo y corte: …
- Rama y HEAD: main @ <sha>. Publicados: <shas o «ninguno»>.
- Archivos modificados sin commit: …
- Evidencia real (comando → resultado con cifras): …
- No ejecutado: …
- Bloqueos: …
- Siguiente orden exacta: …
- Temporales retirados: …
```
