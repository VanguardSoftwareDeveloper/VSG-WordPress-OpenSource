# Security policy

## Current status

The underlying private development monorepository is **not a published or security-certified product**. Its planning files, draft source and future MCP descriptions must not be treated as production-ready. No vendor-backed security SLA is offered.

**Before any public release:** enable and verify a **private vulnerability reporting** mechanism in the destination public GitHub repository and record its exact working channel here. A private vulnerability reporting channel must be enabled and checked by the public catalog maintainer before inviting security reports. Its availability is not established by this snapshot.

## Reporting vulnerabilities

If the public destination repository exposes GitHub's **Security → Report a vulnerability** action, use that private route. Do **not** publish secrets, proof-of-concept exploit payloads, private site data or unpatched vulnerabilities in a public issue or pull request. If no private reporting option is available, do not post the details publicly; ask the maintainer to provide a private channel through the platform before disclosing technical details.

Describe the affected product, version/commit, conditions, impact and a minimal reproduction **without including live credentials or customer data**. The maintainer will evaluate a report on its merits. No response time or patch SLA is guaranteed at this planning stage.

## Security design principles

- All WordPress REST and MCP operations require server-side permission checks for the authenticated user, resource and action.
- External APIs use accounts and credentials owned by each site operator when supported; never share a vendor-funded token among installations by default.
- Avoid storing keys or private user files in repository history, logs or front-end bundles. Rotate any discovered exposed secret.
- Require a review of the **exact public export and its full reachable history** before visibility changes. This private repository's imported history must **not** be published by toggling its visibility.
- Do not represent static review, a license file or a threat model as a completed penetration test.

Further gates: [public release status](docs/RELEASE-STATUS.md).
