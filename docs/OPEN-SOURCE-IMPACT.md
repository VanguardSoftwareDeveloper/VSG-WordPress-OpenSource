# Open-Source Portfolio: Impact and Evidence

> **Planning-stage overview for independent reviewers, including Anthropic's Claude for Open Source Program. Last updated 2026-11-22.** This document is not an endorsement, eligibility claim, or application submission.

## Mission

Develop and eventually distribute useful, **free-by-default WordPress plugins**, prioritizing site-owner control of infrastructure and costs. The maintainer volunteers development and maintenance work. When an external API charges money, site operators should ordinarily provide and pay for their **own provider accounts**. This avoids charging users a plugin license solely to recover the maintainer's time.

The portfolio currently contains **131 distinct category directories and research plans**. It does **not** contain 131 implemented or released plugins. **PeruCommerce is the only category with substantial existing source**, whose source is now publicly available as development code, statically reviewed and not yet validated as a working release; its integrated POS is still planned. The other 130 are project concepts and documentation, not packages.

## Potential ecosystem value — hypotheses, not proven impact

- **Accessible infrastructure:** site operators could gain control of useful WordPress capabilities without paying the maintainer for installation rights.
- **Internationalization:** 130 candidates are intended to use English-first localizable UI, initially targeting Spanish and Brazilian Portuguese translations. PeruCommerce serves Peru in Spanish only.
- **Open integration:** optional future MCP connectivity could let Claude Code, Codex or other clients inspect authorized WordPress resources. No MCP server for these projects has yet been implemented.
- **User-funded integrations:** payment gateways, messaging and storage would normally be configured with accounts belonging to each site owner, avoiding unbounded central costs.
- **Smaller-business relevance:** a potential focus is practical workflow tools for organizations with modest budgets and WordPress hosting rather than a new hosted SaaS requirement.

These are intended **design characteristics**, not claims of user adoption, contributor activity, vulnerability reduction, completed localizations or operational availability.

## Public evidence register

| Signal a reviewer may assess | Verified status at this documentation cut |
|---|---|
| 131 documented product concepts | Documented publicly in this repository, without plugin releases |
| PeruCommerce PHP/Angular source | Present, with code review documentation; build/tests and production behavior **not verified** |
| PeruCommerce integrated POS | Planned, not implemented |
| Public releases / package downloads | No verifiable evidence supplied for this portfolio |
| External organizations relying on packages | No verifiable evidence supplied |
| Merged contributions from external developers | No verifiable evidence supplied |
| OpenSSF criticality score | Not measured for a qualifying public project |
| Working MCP tools and multi-language translations | Not implemented or verified |
| License for PeruCommerce | Source declares **GPL-2.0-or-later**; full distribution license audit is pending |
| Licenses for remaining 130 concepts | Must be selected and audited before each public release |

**Do not replace missing evidence with fabricated numbers.** Document what can actually be reproduced: tags, source history, licenses, releases, dependencies, community issues, user reports, verified downloads, independent contributions, language packs and security fixes.

## How a reviewer could evaluate the work once public

1. Inspect the **actual public source and one working release** of a project, especially its tests, user installation guide and license.
2. Confirm the concrete problem solved, accessibility and locality of data, security boundaries and distribution terms.
3. Review actual adoption or dependency statistics using independent public sources, and the substance of outside contributions.
4. Compare claimed MCP capability to the **tool schemas and demonstrations** rather than to future plans.
5. Read the [131 working names and mission statements](IDENTIDADES-131.md), [MCP roadmap](MCP-CLAUDE-CODEX-131.md) and [localization plan](INTERNACIONALIZACION-131.md) for future direction.

## Claude for Open Source: current published eligibility context

At this cut, Anthropic advertises **six complimentary months of Claude Ultra**, subject to selection and program limits. The official maintainer criteria include, among alternatives, 200,000 monthly downloads, 500 dependent repositories, 100 dependent packages, 100 meaningful merged PRs in others' repos within 12 months, 20 external contributors with merged PRs, or OpenSSF criticality >= 0.4. A discretionary ecosystem-impact track also exists. Anthropic also requires general eligibility including a GitHub account at least two years old, recent **public** open-source activity, and participation in a project with an OSI-approved license. Eligibility and selection are discretionary, and **documentation, many folders, AI-generated activity or an intention to publish do not meet those thresholds**.

Source of truth (recheck at application time):
- https://claude.com/contact-sales/claude-for-oss
- https://www.anthropic.com/claude-for-oss-terms

## Realistic next evidence milestones

- Prepare a usable, security-audited release for one meaningful project (not an unreviewed mirror of the full private monorepo).
- Publish a license, corresponding source, clear scope, installation instructions, user examples and reproducible verification evidence.
- Invite genuine issue reports, translations and pull requests; respond to them based on merit.
- Measure **real** usage and downstream reliance over time and cite the source for every claim.
- Only apply through Anthropic's official form when the application can truthfully describe substantial open-source contribution or community impact.

**No sponsor, endorsement, program approval or Claude Ultra benefit is claimed.** This is an evidence-based planning record, not promotional copy.
