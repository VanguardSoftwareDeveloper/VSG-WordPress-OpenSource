# Instructions for agents — VSG WordPress OpenSource

This is the **public canonical repository** for the 131 VSG WordPress project plans. It is not 131 released plugins. Each `plugins/<slug>/` is a separate candidate; preserve its slug, identity, license and contract boundaries. The product names end in **VSG**.

**PeruCommerce:** source is being consolidated from the former private `Vanguard-WordPress-Plugins` repository. The tree and `docs/SOURCE-CONSOLIDATION.md` determine whether consolidation has actually completed; never assert that a PHP/Angular release, POS, build, tests or ZIP exist merely because sources are present. After consolidation, this public repo is the source of truth; any older nested README/AGENTS instructions referring to a future separate private/public repo are historical and superseded **only for repository location**, not security, domain or release contracts.

Maintain free-by-default plugin policy. External services are normally configured under accounts and billing belonging to the site owner. Never embed vendor secrets or commit customer data.

The other 130 plugin folders are plans, not implementations. Their UX and technical targets remain proposals pending explicit product scope. International candidates are English-first and translatable; PeruCommerce is Peru-specific with Spanish (es-PE) UI. MCP for Claude Code/Codex is a future option, not an implemented server.

Respect the local `PLAN-BASE.md`, `PRODUCT-VISION.md`, `AGENTS.md` (when present), and actual implementation contracts. Do not conflate static inspection with tests, deployment or compliance. Do not run builds, tests, installations, schema migrations or deploy without authorization. Use current official stable compatibility sources when implementing. Do not invent community downloads, adoption metrics, integrations or releases.

The retirement helper in `tools/consolidate-perucommerce.ps1` is an owner-only migration operation, not a general development command. It must verify remote blob hashes before attempting to delete **only** `VanguardSoftwareDeveloper/Vanguard-WordPress-Plugins`. It must never delete or modify `VanguardSoftwareDeveloper/VSG-WordPress`, the private platform product.
