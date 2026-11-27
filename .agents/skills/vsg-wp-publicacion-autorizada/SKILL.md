---
name: vsg-wp-publicacion-autorizada
description: Release procedure for one VSG WordPress plugin in VSG-WordPress-OpenSource (verification, ZIP, corresponding source, tag, GitHub release or WordPress.org). Use only when the owner's current message explicitly orders a release of a named plugin and version; never on your own initiative.
license: GPL-2.0-or-later
compatibility: Claude Code, Codex and Antigravity in the VSG-WordPress-OpenSource repository. Execution steps need tools the owner authorizes per run.
metadata:
  owner: VSG
  version: "1"
---

# Authorized release of one plugin

**Guard:** continue only if the owner's current message orders this release and names the plugin, the version and the channel. Each execution step below must be covered by that order; otherwise stop at the last allowed step and report.

## 1. Gates

- The plugin's scope is implemented and reviewed (phase F5 in `docs/FASES-131.md`).
- PeruCommerce: gates P-C and P-D and IMP-72 in its `docs/43` and `docs/47`. The integrated POS must be implemented and verified before the first usable release.
- `SECURITY.md` records a working private vulnerability reporting channel.
- `docs/RELEASE-STATUS.md` reflects the real state.

## 2. Verification (each run authorized)

1. Resolve dependencies from a committed lockfile and record tool versions.
2. Build the frontend outside the hosting; run the plugin's tests and WordPress Plugin Check.
3. Record commands, versions, results and failures as evidence. Never report a failure as a pass.

## 3. Package

1. Use the plugin's own staging script (PeruCommerce: `tools/stage-distribution.mjs`) to produce the ZIP and the corresponding source from the same commit.
2. Inspect the package: allowlisted files only; no dotfiles, secrets, `node_modules`, unintended source maps or personal data; licence and third-party notices present.
3. Record SHA-256 checksums and the source commit.

## 4. Publish (explicit order for each)

- Tag and GitHub release for that plugin only, with ZIP, source and notes. Never toggle repository visibility.
- WordPress.org: only after the owner submits or approves the listing, following its guidelines.
- No force-push, no history rewrite, and no publishing other plugins in the same step.

## 5. Record

Update `docs/RELEASE-STATUS.md` and the tracking issue with version, commit, checksums, evidence and everything not executed.
