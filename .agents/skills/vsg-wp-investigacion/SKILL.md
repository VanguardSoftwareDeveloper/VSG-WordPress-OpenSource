---
name: vsg-wp-investigacion
description: Verify current stable versions, compatibility and APIs (WordPress, PHP, Angular, Angular Material, Node.js, TypeScript, Abilities API, MCP) in official sources for VSG-WordPress-OpenSource. Use when a plan or change depends on a version, an API signature or a platform policy.
license: GPL-2.0-or-later
compatibility: Claude Code, Codex and Antigravity in the VSG-WordPress-OpenSource repository. Needs web or documentation access.
metadata:
  owner: VSG
  version: "1"
---

# Verify versions and APIs in official sources

## Sources, in this order

1. Official documentation and release pages:
   - WordPress: developer.wordpress.org, make.wordpress.org/core (dev notes, field guides), wordpress.org/download/releases.
   - Abilities API and MCP Adapter: the WordPress developer handbook and the `WordPress/mcp-adapter` repository.
   - PHP: php.net supported versions and the WordPress server requirements.
   - Angular, CDK and Angular Material: angular.dev (version compatibility, release schedule) and material.angular.dev.
   - Node.js: the nodejs.org release schedule. TypeScript: the Angular compatibility table.
   - MCP: the modelcontextprotocol.io specification.
2. Package registry metadata for exact versions and peer ranges, read without installing (registry page, or `npm view <package> version peerDependencies`).
3. Official agent skills installed at user scope (WordPress/agent-skills, angular/skills) and documentation tools such as Context7, as pointers to the sources above. Blogs, forums and model memory are not evidence.

## Procedure

1. Write down the question: component, assumed version, and why it matters to this task.
2. Check the latest stable release and its constraints: peer dependencies, supported Node and TypeScript ranges, minimum WordPress and PHP.
3. Choose the latest stable **compatible** set, not the newest of each component in isolation.
4. Record each finding: component, version, constraint, URL, date checked (YYYY-MM-DD).
5. Mark each value **verified** (read in the official source today), **inherited** (taken from `docs/ESTANDAR-TECNICO-131.md` without rechecking) or **proposed**.

## Where results go

- Version targets live only in `docs/ESTANDAR-TECNICO-131.md`. Edit it only when the order covers documentation; never copy version numbers into other files.
- A plugin's headers (`Requires at least`, `Requires PHP`) and its `package.json` change only during authorized implementation of that plugin.
- A newer version is a recommendation, not an authorization to upgrade. Upgrades that touch distributed contracts or data need a migration note and an owner order.

## Output

A short table (component, version, constraint, source, date), the impact on the current task and any conflict with existing documents.
