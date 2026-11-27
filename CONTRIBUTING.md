# Contributing to VSG WordPress projects

Thank you for considering a contribution. **Current status:** this repository contains a public planning catalog, **not** 131 working WordPress plugins. It also holds the PeruCommerce VSG development source in `plugins/wp-peru-commerce/`; its POS, build, tests and release validation are not complete. Each plugin folder may add its own contribution rules in its `AGENTS.md` or README.

## Useful contributions

- Evidence-based use cases and bounded feature proposals for a named category.
- Review of accessibility, security design, WordPress/PHP/Angular compatibility, and third-party licensing.
- Corrections to documentation with relevant official sources and dates.
- Real translations when a plugin is implemented, respecting its language plan (PeruCommerce targets Spanish for Peru only).
- For code (currently only PeruCommerce): small, self-contained changes inside one plugin folder, with affected contracts, permissions, data and tests documented accurately.

## Proposal and review process

1. Choose the existing product identity from [the catalog](docs/IDENTIDADES-131.md). Check potential overlap with WordPress core, existing plugins and PeruCommerce before proposing duplicated software.
2. Describe the real problem, affected users, proposed solution, limits, compatibility and external API costs. Do not claim a feature is implemented merely because it appears in a plan.
3. For a public repository, file an issue or pull request when enabled. No account outside GitHub is required solely to suggest changes. Use the relevant plugin's local guidelines when available.
4. Never put passwords, tokens, customer data, installation dumps, private URLs or exploit details in issues, patches or test fixtures. Use synthetic data.
5. Preserve plugin boundaries: no mandatory common framework, WooCommerce or third-party service without an accepted product-specific reason.
6. Keep meaningful commit history and credit to actual contributors. Artificial PRs, copied contributions and inflated activity are not acceptable.

**License:** by submitting material that you have the right to contribute, you agree that the contribution can be distributed under the relevant file/project's documented license (see [license scope](LICENSE-SCOPE.md)). Do not submit content you cannot license, including restricted third-party assets.

## What is not promised

A submitted proposal does not become a committed feature. Plans are not released plugins; this document does not guarantee support, release dates, a working MCP integration, translations, Anthropic approval or payment.
