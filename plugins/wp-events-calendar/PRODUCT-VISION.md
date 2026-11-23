# Events Calendar VSG — Project vision (provisional name)

**Status:** planning concept, **not** an implemented, tested, installed or publicly released plugin. The name is a **working title**, not a registered trademark or approved WordPress plugin slug. Existing source directory: `wp-events-calendar`.

## Intended public value — hypothesis

Publish events, locations and recurrence rules.

**Original research category:** Publicar eventos, lugares y recurrencia.

**Intended distribution:** free software. The maintainer volunteers development/maintenance; if a third-party API is required, users should connect their own provider accounts and cover any provider charges. A suitable open-source license will be reviewed before release.

**Languages:** English source UI (`en_US`) with i18n design. Spanish (`es_ES`, `es_MX`) and Brazilian Portuguese (`pt_BR`) are first translation **targets**, not existing language packs. More locales require real translations and review.

## Future MCP support — not implemented

**Candidate read-only capability:** let an authorized site owner inspect relevant events calendar settings or operational summaries, with redacted data and existing WordPress permissions. Exact tool names, schemas, data visibility and write operations remain **undecided** until product scope is approved.

**Connection approach:** optional adapter (potentially local STDIO MCP bridge to authorized WordPress REST, or an audited HTTP MCP endpoint), compatible with **Claude Code and Codex** when their actual client/protocol versions are verified. **No MCP server, tool, token, hosted service or client configuration exists yet.** The normal plugin must remain usable without AI, MCP or an API subscription.

## Open-source evidence still required

- Approve a bounded user problem, project scope, and overlap analysis.
- Implement and verify the plugin, its license and corresponding source before public release.
- Publish real usage guidance, versioned releases, security procedures and reproducible results.
- Record real feedback, translations, external contributions and adoption without inventing metrics.

## References

- [Existing description](README.md) · [Architecture and phases](PLAN-BASE.md)
- [Portfolio impact and evidence](../../docs/OPEN-SOURCE-IMPACT.md)
- [MCP roadmap](../../docs/MCP-CLAUDE-CODEX-131.md) · [Language policy](../../docs/INTERNACIONALIZACION-131.md)

**This is a project concept/roadmap, not an Anthropic or OpenAI endorsement, qualification, approved integration or proof of impact.**
