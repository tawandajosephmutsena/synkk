# Synkk production-readiness remediation and release decision

Assessment: 17 September 2026. Scope: `/Users/mac/Herd/synkk`, `/Users/mac/Herd/synkk-community-core`, `/Users/mac/Herd/synkk-pro`, `/Users/mac/Herd/synkk-saas`, the embedded Obsidian plugin, and `/Users/mac/Herd/obsidian-synkk-sync`.

This is the current remediation status and supersedes the pre-remediation findings and score in `PRODUCTION_READINESS_AUDIT_2026-09-17.md`. The earlier document was updated externally during this review and has been left untouched.

**Decision: NOT YET AUTHORIZED FOR ENTERPRISE PRODUCTION.** The identified application-level critical/high defects are repaired in the inspected current states and regression tests pass. The remaining release blockers are operational proof, independent security/accessibility assessment, and legal approval. Green source tests are not a production certification. No new product features were added.

## What was fixed

| Finding | Original consequence | Current remediation and evidence |
|---|---|---|
| P0 Community package disclosure | A distributed archive could contain live SQLite, WAL/SHM, backup, cache, and OS files. | Packaging now uses explicit source allowlists, a forbidden-entry scan, and unsafe-archive rejection. Adversarial package tests pass. Prior distributed archives still need inventory and exposure assessment. |
| P0 creator deletion | Deleting an account cascaded through shared vaults/portals while file bodies could remain. | Creator attribution is nullable with `nullOnDelete`; shared content survives account deletion, tested. The UI no longer promises immediate comprehensive physical erasure. Complete erasure and backup expiry remain unproven. |
| P1 vault binding | Global slug lookup crossed tenant boundaries; GET could create a vault. | Binding is scoped to the authenticated team or device token, rejects invalid bearer tokens, and does not mutate on reads. Duplicate-slug and no-side-effect tests pass. |
| P1 email verification | `verified` middleware did not enforce verification without the user contract. | `User` implements `MustVerifyEmail`; verified/unverified access tests pass. Mail delivery and abuse controls still need production-like testing. |
| P1 Copilot output | Raw model HTML could execute in a team member's browser. | Markdown strips raw HTML and unsafe links. Malicious-answer regression tests pass. Treat all model output and retrieved notes as untrusted. |
| P1 portal invariant | Stale loaded relationships could validate a changed foreign key against the wrong tenant. | Saving re-fetches candidate vault/file IDs and validates tenant consistency. Mutation tests and configured PHPStan pass. |
| P1 terms/privacy | Community MIT licensing conflicted with AGPL terms; AI and deletion copy overstated implementation. | Terms and copy were reconciled with MIT Community, separate commercial Pro terms, optional external AI, qualified DLP/E2EE, and realistic retention/deletion language. Counsel has not signed off. |
| P2 portal privileges | General members could manage public portals. | Portal management actions now require team update authorization; member-denial tests pass. |
| P2 deployment and claims | Session settings and marketing/security assertions exceeded evidence. | Production example enables encrypted, secure, HttpOnly, SameSite cookies. Unsupported WebAssembly, zero-leak, military-grade, SOC 2, topology, and performance statements were removed/qualified. CI adds dependency audits and a container-build gate. |

These changes require review against the final release commit and production database engine. Local SQLite tests do not prove migration safety on another engine. No historical archive is made safe retroactively by the new exporter.

## Cross-repository verification record

| Checkout | Result | Boundary |
|---|---|---|
| Synkk main | 418 Pest tests, 6,014 assertions; configured PHPStan and Pint pass; 55 JavaScript tests and Vite build pass. | Vite warns about a roughly 720 kB main JS chunk. One Pest warning. |
| Synkk Pro | 418 Pest tests, 6,002 assertions; configured PHPStan and Pint pass. | One Pest warning; test-generated storage artifacts are not source. |
| Synkk SaaS | 424 Pest tests, 6,154 assertions; configured PHPStan and targeted Pint pass. | This checkout changed externally during the assessment. Its current state was preserved and verified, not treated as wholly authored here. |
| Community Core | 333 Pest tests, 4,602 assertions; 14 proprietary-only tests skipped. Laravel-aware PHPStan and Pint pass. | Its previously missing PHPStan configuration and 21 actual QA typing diagnostics were repaired without changing dependencies or suppressing errors; the affected 16 QA tests and complete suite pass after those edits. |
| Embedded and standalone Obsidian plugins | 54 tests each, TypeScript checks, and builds pass in main, Pro, SaaS, and standalone checkouts after pinning esbuild 0.28.2. | The four plugin manifests and lockfiles match exactly. esbuild now requires Node 18 or newer; the local check used Node 24. |
| Locked PHP and plugin npm dependencies | Composer audits and full plugin npm audits found no known advisories at assessment time. | The prior moderate esbuild 0.20.0 development-tool advisory is resolved by the approved upgrade. Repeat audits on release day. |

Docker is unavailable locally, so the CI container-build gate was added but not observed passing here. No production penetration test, real-device interoperability test, restore drill, load test, full browser accessibility assessment, live billing/mail test, or cloud configuration review was possible. The externally updated checkouts must be frozen to a single release candidate before interpreting these results as one product state.

## Release-blocking gates

### 1. Reproducible artifact and supply chain

- Build each edition and plugin from a clean, pinned commit. Run all PHP, JS, TypeScript, PHPStan, Pint, audit, package-inspection, and Docker gates on the **same artifact**; inspect the final Community archive for data, secrets, proprietary code, and third-party notices.
- Run the image in a production-like environment with migrations, route/config caches, health probes, least-privilege filesystem permissions, and rollback. Docker was not available for this local review.
- Publish SBOMs, immutable image digests, signed artifacts/provenance, container and secret scans, dependency-review evidence, and a vulnerability disclosure/patch process. The new audit/build CI jobs do not satisfy this whole program by themselves.
- Keep the now-configured Community PHPStan gate required in CI; do not suppress future findings without triage. Repeat full plugin audits, tests, typecheck, and builds on the frozen release candidate and supported Node versions.

### 2. Data lifecycle, recovery, and operations

- Define and demonstrate encrypted offsite backups, retention, RPO/RTO, production-like restore, corruption recovery, and rollback. Explicitly accept the default single-node SQLite topology for a limited tier or adopt durable services for the enterprise tier; do not claim HA from the current Compose example.
- Prove deletion across database rows, vault files, versions, portals, exports, queues, logs, provider records, snapshots, and backup expiry. Specify ownership transfer, legal holds, retries, monitoring, and customer-visible completion. Do not advertise a fixed erasure period until enforced.
- Instrument database, queues, scheduler, storage, collaboration/WebSockets, mail, billing webhooks, AI/RAG, backup freshness, latency, errors, and capacity. Define SLOs, alerts, on-call ownership, incident communication, and tested recovery runbooks. `/up` is not a complete service-readiness check.
- Load-test representative sync, collaboration, large vaults, RAG, exports, and portals. Record p95/p99 latency, queue lag, error rates, memory, and capacity. Resolve the main bundle warning against an explicit mobile/performance budget.

### 3. Independent security and privacy assurance

- Independently test tenant isolation, device tokens and pairing, Livewire actions, portals, WebSockets, admin impersonation, billing, exports, E2EE, AI output, and packaged artifacts; retest critical/high findings on the frozen candidate. Obtain a cryptographic/protocol review before making strong multi-device E2EE or key-recovery assurances.
- Validate actual production TLS, trusted hosts/proxies, cookies, CSP, CORS/WebSocket origins, rate limits, secrets/KMS rotation, logging redaction, network egress, infrastructure permissions, and access reviews. Config examples are not deployment evidence.
- Document AI data flows, provider opt-in and terms, regions/transfers, retention/training, redaction, prompt-injection and output-handling controls, and E2EE plaintext boundaries. Verify each path in deployed configuration.
- Obtain counsel approval for entity and jurisdictions; MIT Community vs commercial Pro rights; contributor/IP chain; Terms, Privacy, DPA, subprocessors, controller/processor roles, cross-border transfer, retention, breach notices, billing/refunds, and applicable Zimbabwe and customer-market law. This report is not legal advice.

### 4. Accessibility and product truth

- Complete automated **and human** WCAG 2.2 AA assessment across authentication, editor, graph, sync status, modals, public portals, billing, and mobile. Include keyboard-only, VoiceOver/NVDA, 200–400% zoom/reflow, reduced motion, focus, target size, contrast, and error recovery; fix and retest findings. Unit tests do not establish conformance.
- Maintain a claim register for encryption, E2EE, backups, availability/SLA, AI locality, DLP, accessibility, certifications, performance, and compatibility. Each claim needs scoped reproducible evidence, an owner, approval date, and expiration. Do not restore “SOC 2 compliant,” “zero leakage,” or “military grade” without independent evidence.

## Release authorization rule

The inspected code-level P0/P1 findings are closed, but **production remains NO GO** until the artifact and operational gates, independent security review, and legal approvals are evidenced; accessibility evidence is required before enterprise-quality/conformance claims. Accept no unresolved critical/high security issue without a named owner, narrow scope, expiry, and rollback trigger. A private pilot is a separate governance decision and must be isolated, reversible, contractually controlled, backed up, and free of unsupported claims or contaminated archives.

Use [OWASP ASVS 5.0.0](https://owasp.org/projects/asvs) as an application verification checklist, [NIST SP 800-218 SSDF](https://csrc.nist.gov/pubs/sp/800/218/final) for secure development/release evidence, the [OWASP Top 10 for LLM Applications 2025](https://genai.owasp.org/resource/owasp-top-10-for-llm-applications-2025/) for Copilot, and [WCAG 2.2](https://www.w3.org/TR/wcag/) AA for accessibility. These are targets, **not certifications** earned here. Assess [GDPR Article 28](https://eur-lex.europa.eu/legal-content/EN/TXT/PDF/?uri=CONSIL%3APE_17_2016_INIT), Zimbabwe data-protection requirements, and the [EU AI Act](https://eur-lex.europa.eu/legal-content/EN/ALL/?uri=CELEX%3A32024R1689) only where the actual processing, markets, use cases, and effective dates make them applicable, with qualified counsel.

No code was deployed or committed by this assessment. The main application's embedded plugin is a Git submodule: its package changes must be reviewed and committed in that repository before the parent gitlink can reference the new version. Externally changed files were preserved. Engineering, security, operations, product, and counsel should sign their respective gates against one frozen release artifact before the requested enterprise launch.
