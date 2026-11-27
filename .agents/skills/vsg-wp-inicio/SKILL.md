---
name: vsg-wp-inicio
description: Start or resume a unit of work in VSG-WordPress-OpenSource. Use at the beginning of any task in this repository, after an interruption, or when the plugin or scope is unclear. Checks the checkout, concurrent writers, the tracking issue and the authorization of the order before any edit.
license: GPL-2.0-or-later
compatibility: Claude Code, Codex and Antigravity in the VSG-WordPress-OpenSource repository. Needs git; GitHub access is optional.
metadata:
  owner: VSG
  version: "1"
---

# Start or resume a unit of work

Procedure only. Rules and permissions come from the root `AGENTS.md`; this skill grants none.

## 1. Checkout

1. Confirm the root (`git rev-parse --show-toplevel`), branch `main`, `git status --short` and HEAD.
2. Run `git fetch` and inspect `git log --oneline HEAD..origin/main`. If the tree is clean and only behind, `git pull --ff-only`; otherwise stop and report.
3. Uncommitted changes or stashes you did not make belong to the owner or another agent: keep them, do not mix them into your work, and ask before writing near them.
4. If another assistant is writing in this repository, stay read-only or stop. Never create a worktree, clone or branch to get around it.

## 2. Order and authorization

1. Restate the order in one line: plugin slug(s), deliverable, closing criterion.
2. Classify every requested action with the authorization matrix in `AGENTS.md`. Installing, building, testing, running services, packaging, committing, pushing or publishing each need an explicit owner order for that run.
3. If the order is ambiguous or conflicts with `AGENTS.md` or a plugin `AGENTS.md`, stop, name the conflict and propose the minimum change.

## 3. Context, on demand

1. For `plugins/<slug>/`: read its `AGENTS.md` (if present), `PLAN-BASE.md` and `PRODUCT-VISION.md`. For PeruCommerce also read `docs/43-ESTADO-IMPLEMENTACION.md` and the contracts its `AGENTS.md` names for the area.
2. Technical targets: `docs/ESTANDAR-TECNICO-131.md` only.
3. Do not load other plugins' folders unless the task needs them.

## 4. Tracking issue

1. Find the open "Unidad de trabajo" issue for this unit (search by slug or title). Read its latest handoff and compare its SHA with HEAD.
2. If none exists, draft one from `.github/ISSUE_TEMPLATE/unidad-de-trabajo.yml`. Creating or commenting on issues writes to GitHub: do it only when the order covers it; otherwise hand the draft to the owner.

## 5. Start note

Before the first edit, state briefly:

- root, branch, HEAD and tree state;
- issue number, or "draft";
- scope, files expected to change and closing criterion;
- actions authorized and actions explicitly not authorized.
