# Security policy

## Current status

This public repository is **not a published or security-certified product**. Its planning files, the PeruCommerce development source and future MCP descriptions must not be treated as production-ready. No vendor-backed security SLA is offered.

**Before inviting security reports or releasing any plugin:** the maintainer must enable and verify GitHub **private vulnerability reporting** in this repository and record its exact working channel here. As of 2026-11-26 it is not enabled.

## Reporting vulnerabilities

If this repository exposes GitHub's **Security → Report a vulnerability** action, use that private route. Do **not** publish secrets, proof-of-concept exploit payloads, private site data or unpatched vulnerabilities in a public issue or pull request. If no private reporting option is available, do not post the details publicly; ask the maintainer to provide a private channel through the platform before disclosing technical details.

Describe the affected product, version/commit, conditions, impact and a minimal reproduction **without including live credentials or customer data**. The maintainer will evaluate a report on its merits. No response time or patch SLA is guaranteed at this planning stage.

## Security design principles

- All WordPress REST and MCP operations require server-side permission checks for the authenticated user, resource and action.
- External APIs use accounts and credentials owned by each site operator when supported; never share a vendor-funded token among installations by default.
- Avoid storing keys or private user files in repository history, logs or front-end bundles. Rotate any discovered exposed secret.
- Review the **exact files and full reachable history** of any release or newly published repository. History from former private repositories was not imported here ([consolidation record](docs/SOURCE-CONSOLIDATION.md)); never publish a private repository by toggling its visibility.
- Do not represent static review, a license file or a threat model as a completed penetration test.

Further gates: [public release status](docs/RELEASE-STATUS.md).
