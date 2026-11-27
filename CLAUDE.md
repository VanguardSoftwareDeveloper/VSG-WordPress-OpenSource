@AGENTS.md

## Claude Code only

- Skills in `.claude/skills/vsg-wp-*` are wrappers; each procedure lives in `.agents/skills/<name>/SKILL.md`.
- Controls live in `.claude/settings.json` (deny/ask rules and the PreToolUse policy hook). Personal overrides belong in `.claude/settings.local.json` or `CLAUDE.local.md`, both ignored by Git.
- Start Claude Code at the repository root (open this folder in the desktop app, or run `claude` from it): project settings and the hook load only from the start directory.
- Automatic memory is not project state; handoffs go to the tracking issue.
- `plugins/<slug>/CLAUDE.md` imports that plugin's `AGENTS.md` and loads when you work in that folder.
