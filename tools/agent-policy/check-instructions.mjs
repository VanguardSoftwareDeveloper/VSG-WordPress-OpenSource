#!/usr/bin/env node
// Static checks for the agent instruction system of VSG-WordPress-OpenSource.
// Zero dependencies, read-only: it never writes files or calls the network.
// Usage (needs Node and an order that allows running it): node tools/agent-policy/check-instructions.mjs
// Exit code 0 when every check passes, 1 when any check fails.

import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const LIMITS = { rootAgents: 8 * 1024, pluginAgents: 16 * 1024, rootClaude: 2 * 1024, rule: 24000, skillLines: 500, description: 1024 };
const TRIGGERS = new Set(['always_on', 'model_decision', 'glob', 'manual']);

const failures = [];
let checks = 0;
const rel = (p) => relative(ROOT, p).replace(/\\/g, '/');
const fail = (file, msg) => failures.push(`${rel(file)}: ${msg}`);
const read = (p) => readFileSync(p, 'utf8').replace(/^﻿/, '');
const bytes = (p) => statSync(p).size;
const dirs = (p) => (existsSync(p) ? readdirSync(p, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name) : []);
const files = (p, ext) => (existsSync(p) ? readdirSync(p).filter((n) => n.endsWith(ext)).map((n) => join(p, n)) : []);

function check(cond, file, msg) {
  checks++;
  if (!cond) fail(file, msg);
  return cond;
}

// Top-level "key: value" pairs of a leading YAML frontmatter block (nested keys are ignored).
function frontmatter(text) {
  const m = /^---\r?\n([\s\S]*?)\r?\n---(\r?\n|$)/.exec(text);
  if (!m) return null;
  const out = {};
  for (const line of m[1].split(/\r?\n/)) {
    const kv = /^([A-Za-z0-9_-]+):\s*(.*)$/.exec(line);
    if (kv) out[kv[1]] = kv[2].trim().replace(/^(["'])(.*)\1$/, '$2');
  }
  return out;
}

function fmLine(text, key) {
  const m = new RegExp(`^${key}:.*$`, 'm').exec(text);
  return m ? m[0].trimEnd() : null;
}

function checkLinks(file) {
  const text = read(file).replace(/```[\s\S]*?```/g, '').replace(/`[^`\n]*`/g, '');
  for (const m of text.matchAll(/\[[^\]\n]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g)) {
    const target = m[1];
    if (/^([a-z][a-z0-9+.-]*:|#)/i.test(target)) continue;
    let path = target.split('#')[0];
    try { path = decodeURIComponent(path); } catch { /* keep raw */ }
    check(existsSync(resolve(dirname(file), path)), file, `broken relative link "${target}"`);
  }
}

// 1. Instruction files: size, imports and links.
const rootAgents = join(ROOT, 'AGENTS.md');
if (check(existsSync(rootAgents), rootAgents, 'missing')) {
  check(bytes(rootAgents) <= LIMITS.rootAgents, rootAgents, `${bytes(rootAgents)} B exceeds ${LIMITS.rootAgents} B`);
  checkLinks(rootAgents);
}

const rootClaude = join(ROOT, 'CLAUDE.md');
if (check(existsSync(rootClaude), rootClaude, 'missing')) {
  const first = read(rootClaude).split(/\r?\n/).find((l) => l.trim());
  check(first?.trim() === '@AGENTS.md', rootClaude, 'first line must be the import "@AGENTS.md"');
  check(bytes(rootClaude) <= LIMITS.rootClaude, rootClaude, `${bytes(rootClaude)} B exceeds ${LIMITS.rootClaude} B; rules belong in AGENTS.md`);
  checkLinks(rootClaude);
}

for (const slug of dirs(join(ROOT, 'plugins'))) {
  const agents = join(ROOT, 'plugins', slug, 'AGENTS.md');
  const claude = join(ROOT, 'plugins', slug, 'CLAUDE.md');
  if (existsSync(agents)) {
    check(bytes(agents) <= LIMITS.pluginAgents, agents, `${bytes(agents)} B exceeds ${LIMITS.pluginAgents} B`);
    checkLinks(agents);
  }
  if (existsSync(claude)) {
    const lines = read(claude).split(/\r?\n/).filter((l) => l.trim());
    check(lines.length > 0 && lines.every((l) => l.trim().startsWith('@')), claude, 'must contain only imports such as "@AGENTS.md"');
    check(existsSync(agents), claude, 'imports an AGENTS.md that does not exist');
  }
}

// 2. Antigravity rules: trigger, required companion keys and size.
for (const file of files(join(ROOT, '.agents', 'rules'), '.md')) {
  check(bytes(file) <= LIMITS.rule, file, `${bytes(file)} B exceeds ${LIMITS.rule} B`);
  const fm = frontmatter(read(file));
  if (!check(fm && TRIGGERS.has(fm.trigger), file, 'frontmatter needs trigger: always_on | model_decision | glob | manual (otherwise the rule is silently ignored)')) continue;
  if (fm.trigger === 'glob') check(Boolean(fm.globs), file, 'trigger glob needs globs');
  if (fm.trigger === 'model_decision') check(Boolean(fm.description), file, 'trigger model_decision needs description');
  checkLinks(file);
}

// 3. Portable skills and their Claude Code wrappers.
const portable = new Set(dirs(join(ROOT, '.agents', 'skills')));
const wrappers = new Set(dirs(join(ROOT, '.claude', 'skills')));

for (const name of portable) {
  const file = join(ROOT, '.agents', 'skills', name, 'SKILL.md');
  if (!check(existsSync(file), file, 'missing SKILL.md')) continue;
  const text = read(file);
  const fm = frontmatter(text) ?? {};
  check(fm.name === name, file, `frontmatter name "${fm.name ?? ''}" must equal the folder name "${name}"`);
  check(Boolean(fm.description) && fm.description.length <= LIMITS.description, file, `description must be present and at most ${LIMITS.description} characters`);
  check(text.split(/\r?\n/).length < LIMITS.skillLines, file, `must stay under ${LIMITS.skillLines} lines`);
  checkLinks(file);

  const wrapper = join(ROOT, '.claude', 'skills', name, 'SKILL.md');
  if (!check(existsSync(wrapper), wrapper, `missing Claude Code wrapper for portable skill "${name}"`)) continue;
  const wtext = read(wrapper);
  check(fmLine(wtext, 'name') === fmLine(text, 'name'), wrapper, 'name line differs from the portable skill');
  check(fmLine(wtext, 'description') === fmLine(text, 'description'), wrapper, 'description line differs from the portable skill (wrapper drift)');
  check(wtext.includes(`.agents/skills/${name}/SKILL.md`), wrapper, `must point to .agents/skills/${name}/SKILL.md`);
}
for (const name of wrappers) {
  check(portable.has(name), join(ROOT, '.claude', 'skills', name), 'wrapper without a portable skill in .agents/skills');
}

// 4. Client controls: valid JSON that calls the shared guard.
const guard = join(ROOT, 'tools', 'agent-policy', 'guard.mjs');
check(existsSync(guard), guard, 'missing policy guard');
for (const p of ['.claude/settings.json', '.codex/hooks.json', '.agents/hooks.json']) {
  const file = join(ROOT, p);
  if (!existsSync(file)) continue;
  let parsed = null;
  try { parsed = JSON.parse(read(file)); } catch (err) { check(false, file, `invalid JSON: ${err.message}`); continue; }
  check(JSON.stringify(parsed).includes('tools/agent-policy/guard.mjs'), file, 'does not call tools/agent-policy/guard.mjs');
  // Fail closed: Claude Code blocks the call when the hook fails; the Codex and Antigravity loaders deny themselves.
  if (p === '.claude/settings.json') {
    const handlers = (parsed.hooks?.PreToolUse ?? []).flatMap((group) => group.hooks ?? []);
    check(handlers.length > 0 && handlers.every((h) => h.onFailure === 'block'), file, 'PreToolUse hooks must set onFailure: "block"');
  } else {
    check(JSON.stringify(parsed).includes('fail closed'), file, 'hook loader must deny when the guard cannot load');
  }
}
const codexRules = join(ROOT, '.codex', 'rules');
for (const file of files(codexRules, '.rules')) {
  const text = read(file);
  check(/prefix_rule\(/.test(text), file, 'no prefix_rule found');
  check(!/decision\s*=\s*"(?!allow"|prompt"|forbidden")/.test(text), file, 'decision must be "allow", "prompt" or "forbidden"');
}

// Report.
if (failures.length) {
  process.stderr.write(`Instruction check failed: ${failures.length} of ${checks} checks.\n${failures.map((f) => `- ${f}`).join('\n')}\n`);
  process.exitCode = 1;
} else {
  process.stdout.write(`Instruction check passed: ${checks} checks.\n`);
}
