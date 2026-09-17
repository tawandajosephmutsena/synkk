# Synkk Enterprise Production Readiness Review

**Assessment date:** 17 September 2026  12:02

**Application:** `/Users/mac/Herd/synkk` at `ca4ed4d797275adc12a4b73eb7bcfe3ce36d1c8e`

**Obsidian plugin:** embedded and standalone at `e8e116618acbf48d69043c234a8149d205605d0f`

**Decision:** **NO GO for public, commercial, or enterprise production**

**Readiness score:** **44/100**

## Executive decision

Synkk has a substantial product foundation: tenant-aware models and policies, scoped device tokens, passkeys and two-factor authentication, encrypted-vault support, portal reauthorization, transactional team offboarding, signed billing webhooks, broad automated tests, and clean current dependency audits.

It is not production-ready. Two release-blocking data-lifecycle defects were directly demonstrated: the Community Edition exporter packages the live SQLite database and runtime artifacts, and account deletion can cascade-delete shared vault records while leaving file payloads behind. Other high-risk defects affect tenant routing, email verification, Copilot output handling, and portal tenant invariants. Public licensing, privacy, infrastructure, AI, DLP, and compliance claims also conflict with the implementation or lack auditable evidence.

Do not accept customer data, enable paid acquisition, distribute a Community Edition archive, or make enterprise/security claims until every P0 and P1 item is closed and independently retested.

## Scope and assurance level

This source-level and local-runtime assessment covered the Laravel application, embedded and standalone Obsidian plugin, schema, authentication and authorization, sync/collaboration APIs, E2EE/RAG, portal publishing, billing, packaging, Docker/CI, public copy, privacy/terms, and automated checks. A structural graph of first-party `app/` code covered 103 files, 653 nodes, 785 relationships, and 81 communities.

This was not a production penetration test, cryptographic audit, legal opinion, accessibility conformance audit, cloud review, or disaster-recovery exercise. Docker was unavailable, and no production account, edge configuration, mail, DNS/TLS, billing account, backup target, device farm, or monitoring system was supplied. Those controls remain unverified—not assumed safe.

## Readiness scorecard

| Area | Score | Release position |
|---|---:|---|
| Tenant isolation and authorization | 43/100 | Blocked by global vault binding and portal invariant defect |
| Application and AI security | 40/100 | Blocked by unsafe Copilot HTML and unenforced verification |
| Data protection and lifecycle | 25/100 | Blocked by archive disclosure and destructive deletion |
| Build, tests, and supply chain | 67/100 | Tests/builds strong; static and formatting gates red |
| Reliability and recoverability | 38/100 | Backups, restore, failover, and RPO/RTO unproven |
| Operations and observability | 35/100 | Health, SLO, alerting, incident, and capacity evidence absent |
| Legal, privacy, and product truth | 28/100 | License and data-processing disclosures conflict with code |
| Accessibility | 40/100 | WCAG 2.2 AA conformance unverified |
| Performance and UX quality | 61/100 | Functional build; no production budget or field evidence |
| **Overall** | **44/100** | **No go** |

## Release blockers — P0

### P0-01 — Community Edition archives disclose live databases and runtime state

**Evidence:** `app/Console/Commands/PackageCommunityEdition.php:21-34,63-72,98-126`; `tests/Feature/PackageCommunityEditionTest.php:6-37`.

The exporter recursively packages `database/` and `bootstrap/` but excludes neither SQLite files nor database backups, WAL/SHM files, framework caches, OS metadata, tests, or `phpunit.xml`. A direct run of `php artisan synkk:package-ce --output=/private/tmp/synkk-audit-community.zip` produced an archive containing `database/database.sqlite` (about 14.5 MB), its WAL/SHM files, a dated backup, Bootstrap caches, and `.DS_Store` files. The current test never asserts that secrets, customer data, runtime state, or backups are absent.

**Impact:** distributing this archive can disclose accounts, tenant metadata, device records, vault metadata, billing state, and other persisted data.

**Required closure:** replace recursion with an explicit clean-checkout allowlist; exclude all runtime/database/cache/test/OS artifacts; scan the finished archive for secrets and forbidden paths; add adversarial archive tests; invalidate every prior bundle whose provenance cannot be proved.

### P0-02 — Account deletion can destroy shared vaults and retain orphaned payloads

**Evidence:** `resources/views/pages/settings/delete-user-modal.blade.php:16-24`; `database/migrations/2026_09_04_000001_create_synkk_tables.php:17-24,43-58`; `app/Actions/Vaults/SyncUploadAction.php:114-118`; `app/Models/VaultFile.php`; `app/Console/Commands/PruneDeletedVaultFiles.php:39-59`; `resources/views/privacy.blade.php:137-143`; `tests/Feature/Settings/ProfileUpdateTest.php:46-61`.

The settings action directly deletes the user. `vaults.created_by` cascades, so deleting a creator hard-deletes every vault they created, including shared team vaults. File bodies live separately under `vaults/{id}/...`; database cascades do not run model cleanup, `VaultFile` has no deletion observer, and the pruner can only see rows that remain. The privacy notice promises complete deletion within 24 hours, which this cannot guarantee.

**Impact:** shared tenant history can be destroyed while inaccessible customer files remain stored.

**Required closure:** use one transactional erasure service; block deletion or require ownership transfer for shared assets; separate attribution from ownership; delete storage through an idempotent, observable workflow; define failure recovery/legal holds; test teams, vaults, files, versions, collaboration, portals, tokens, billing, retries, and physical deletion. Do not promise a fixed period until production jobs and backup expiry enforce it.

## High-risk findings — P1

### P1-01 — Vault binding is globally scoped and mutates data on GET

**Evidence:** `app/Providers/AppServiceProvider.php:36-84`; `app/Services/DeviceVaultAccess.php:14-27`; `tests/Feature/VaultAutoCreationTest.php:8-36`; `routes/api.php:35-75`.

The binder selects the first matching slug across all tenants even though slugs are unique only per team. A legitimate tenant can resolve another tenant's vault and be denied instead of receiving its own. If no global match exists, the binder creates a vault for any authenticated request—including `GET /manifest`—before controller authorization, limits, or write-scope checks. The test codifies this side effect.

**Required closure:** bind by authenticated team plus slug; never mutate in route binding; move creation to an explicit authorized write action; enforce scope, membership, plan limits, validation, idempotency, and audit logging; prove GET/HEAD are side-effect-free and test duplicate slugs across tenants.

### P1-02 — Email verification is configured but not enforced

**Evidence:** `config/fortify.php:163-174`; `app/Models/User.php:5,42-45`; `routes/web.php:81-95`.

Fortify verification is enabled and sensitive routes use `verified`, but `User` does not implement `MustVerifyEmail`; its import is commented out. Laravel's middleware therefore admits unverified users to routes that appear protected.

**Required closure:** implement the contract or deliberately remove the feature and claims; prove unverified users cannot reach each protected route/Livewire surface; test resend, expiry, email-change invalidation, and administrator behavior.

### P1-03 — Copilot renders untrusted model output as raw HTML

**Evidence:** `resources/views/pages/vaults/show.blade.php:1350-1382,3660-3663`; `app/Services/VaultRagService.php:548-624,636-700`.

Assistant output uses raw `{!! Str::markdown(...) !!}` without stripping HTML or sanitizing. A direct runtime check confirmed `<img src=x onerror=alert(1)>` survives. Vault notes and graph excerpts enter LLM prompts, model output is returned verbatim, and the deterministic fallback interpolates note metadata. A malicious shared note can indirectly prompt executable markup for another user.

**Impact:** stored XSS in an authenticated team context; OWASP LLM01:2025 Prompt Injection and LLM05:2025 Improper Output Handling.

**Required closure:** disable raw HTML and unsafe links; encode interpolated metadata; sanitize if HTML is intentional; test event handlers, SVG/MathML, unsafe URLs, encoded/malformed HTML, and indirect-injection notes. Treat all model output as hostile.

### P1-04 — Portal tenant invariants can validate stale relationships

**Evidence:** `app/Models/VaultPortal.php:90-105`; current PHPStan errors at lines 93-94.

The saving hook uses an already loaded relationship before querying a changed foreign key. If `vault` or `primaryFile` was loaded and its ID then changes, validation can inspect stale state. PHPStan flags the fallback as unreachable from declared types.

**Required closure:** query candidate IDs directly inside one authorized transaction; derive tenant identity rather than mass-assigning it; add database constraints where feasible; test foreign-key changes after relationships are loaded.

### P1-05 — Licensing terms contradict the repository license

**Evidence:** `LICENSE:1-20`; `composer.json:13`; `README.md:144-146`; `resources/views/terms.blade.php:75-82`.

The repository grants MIT. Public terms state the Community Server is AGPL-3.0 for non-commercial/community use and sell a license to avoid copyleft. MIT permits commercial use and has no AGPL copyleft restriction.

**Required closure:** obtain IP counsel; establish contributor/ownership authority; choose the actual model; make repository, archives, notices, website, checkout, contracts, and sales copy identical; preserve the license with every artifact.

### P1-06 — Privacy and AI disclosures do not match data flows

**Evidence:** `resources/views/privacy.blade.php:75-95,103-143`; `config/synkk.php:214-221`; `app/Services/VaultRagService.php:590-618`; `resources/views/about.blade.php:394-400`.

When configured, RAG sends vault content and queries to OpenAI. The subprocessor table omits it while copy claims no third-party leakage and strictly local operation. The notice also asserts 14-day log retention without enforcement found in the repo and deletion behavior contradicted by P0-02.

**Required closure:** approve data-flow and processing inventories for cloud/self-hosted modes; disclose AI provider selection, content, purpose, legal basis, regions/transfers, retention/training controls, opt-in/disable behavior, and subprocessor changes; enforce retention/deletion; align the DPA, privacy notice, UI, contracts, and marketing; obtain Zimbabwe and target-market counsel.

## Medium-risk findings — P2

### P2-01 — Public security and infrastructure claims exceed evidence

**Evidence:** `resources/views/welcome.blade.php:1494-1498,1547,1693-1698`; `resources/views/about.blade.php:395-400,421-445`; `resources/views/documentation.blade.php:769-777`; `app/Actions/Vaults/SyncUploadAction.php:64-65`.

- “WebAssembly decryptor” is advertised, but the implementation found is WebCrypto JavaScript.
- “Military-Grade,” “Zero-Knowledge Verified,” SOC 2, speed, encrypted-backup, automatic-update, and SLA language lack evidence here.
- DLP is described as stopping secrets before leaving the device, but scanning runs server-side after content arrives.
- Documentation says Compose includes PostgreSQL/Redis; supplied production Compose uses SQLite and database cache/queue/session.
- “Zero cloud leakage” conflicts with optional OpenAI fallback.

Create a claim register with owner, scope, evidence, benchmark, environment, expiry, and approval. Remove unsupported superlatives/compliance implications.

### P2-02 — Production topology is single-node and recovery is unproven

**Evidence:** `.env.production.example:19-34`; `docker-compose.prod.yml:1-68`; `resources/views/terms.blade.php:80-81`.

The default uses local volumes and database-backed queues/cache/sessions. No tested encrypted offsite backup, PITR, restore drill, corruption response, failover, RPO/RTO, capacity boundary, or region strategy was found, while terms claim the cloud service is backed up.

Define deployment tiers; use durable services or prove accepted limits; complete and record a production-like encrypted restore drill before making backup claims.

### P2-03 — Health, observability, and incident controls are incomplete

`docker-compose.prod.yml:30-35` checks only `/up`; it does not prove database writes, queues, scheduler, Reverb, storage, mail, billing callbacks, RAG, or backup freshness. No complete SLO/error budget, alert routing, on-call, incident matrix, customer communication, immutable audit trail, or production runbook evidence was found.

### P2-04 — Deployment hardening is incomplete

**Evidence:** `.env.production.example:23-30`; `docker/nginx.conf:25-31`; `docker-compose.prod.yml:3-7`.

Sessions are unencrypted; secure-cookie/trusted-host/proxy settings are not explicit; CSP permits inline scripts/styles and broad WebSocket origins; HSTS is emitted by an internal HTTP server; the image uses mutable `latest`; the image is not built in CI and could not be built here. Require exact-origin CSP, hardened containers, immutable digests, image scanning/signing/provenance, and clean CI builds.

### P2-05 — Supply-chain controls are below enterprise baseline

Dependabot covers Actions only. No required SBOM, license scan, secret scan, SAST, container scan, signature, provenance, dependency review, or release attestation was found. Automate Composer and both npm projects, publish CycloneDX/SPDX SBOMs, scan archives/images, pin inputs, sign releases, and define disclosure/patch SLAs.

### P2-06 — Static analysis and formatting gates are red

PHPStan exits 1 with two `VaultPortal.php` errors. Pint reports formatting needed in `TeamMessageFactory.php`, `TeamNotificationFactory.php`, `VaultPreflightController.php`, `QAAuditConfig.php`, and `ConfigurationLoader.php`. Green tests do not override red required gates.

### P2-07 — RAG indexing will not scale predictably

`VaultRagService.php:55-58,364-367` loads broad collections and repeats persistence work; the UI can trigger synchronous indexing at `show.blade.php:1395-1402`. Use bounded batches, bulk writes, resumable dedicated jobs, cancellation/resource limits, and representative large-vault tests.

### P2-08 — Portal publishing is not least-privilege

`resources/views/pages/portals/index.blade.php:37-150` permits general members to publish. Restrict create/update/publish/password/delete to explicit existing owner/admin roles or a documented policy; audit changes; test role downgrade and revocation.

### P2-09 — Accessibility conformance is unproven

No complete WCAG 2.2 AA evidence was found. Complete automated and keyboard, VoiceOver/NVDA, zoom/reflow, focus, target-size, contrast, reduced-motion, status-message, error-recovery, graph, modal, editor, auth, billing, portal, and mobile testing.

### P2-10 — Performance has no enforceable budget

The production build passes but warns that the main JavaScript is about 721 kB minified/239 kB gzip. No CI budget, production Core Web Vitals, sustained sync/collaboration load, or low-end/mobile profile was found. Split heavy route code, set budgets, and test production-like latency, concurrency, and vault sizes.

## Legal and safety review

This identifies engineering/disclosure gaps, not legal conclusions. The Zimbabwe Data Protection Act addresses lawful and purpose-limited processing, controller/processor duties, rights, security, breach notification, accountability, and transfers. The 2024 regulations add controller licensing and DPO requirements. Counsel must determine Synkk/Ottomate's classification and obligations in every operating/customer jurisdiction.

Before launch, counsel and security leadership should approve:

- operating entity, address, governing law, jurisdiction, authority, and contacts;
- controller/processor roles across cloud, self-hosted, portals, support, billing, and AI;
- POTRAZ controller registration/licensing and DPO obligations;
- lawful bases, sensitive/child data, DSAR/appeals, retention, deletion/backups, breach notice, and transfers;
- DPA, subprocessors/change mechanism, transfer safeguards, security schedule, SLA, AUP, refunds/trials, and precedence;
- vulnerability disclosure/safe harbor, IP/contributor ownership, third-party notices, and licensing;
- AI safety for prompt injection, output handling, provider data use, logging/redaction, abuse, and tenancy.

## Verification record

| Check | Result |
|---|---|
| `php artisan test --compact` | **Pass:** 409 tests, 1,864 assertions, 1 warning |
| Root JavaScript tests | **Pass:** 55/55 |
| Standalone plugin tests | **Pass:** 54/54 |
| Root production build | **Pass with large-chunk warning** |
| Standalone plugin build and TypeScript | **Pass** |
| Embedded plugin build/type/test | Install rerun hit npm 11 internal error; same commit passed standalone |
| Composer validation/audit | **Pass:** no known advisories |
| Root and standalone plugin production npm audits | **Pass:** 0 known vulnerabilities |
| PHPStan | **Fail:** 2 `VaultPortal.php` errors |
| Pint dirty check | **Fail:** 5 files require formatting |
| Community archive inspection | **Fail:** live SQLite, WAL/SHM, backup, and cache files packaged |
| Docker image build/start | **Not run:** Docker unavailable |
| Penetration, restore, load, WCAG, production edge/billing/mail/DNS | **Not verified** |

## Controls currently positive

- Team offboarding removes membership and device tokens transactionally.
- Portal access reauthorizes on Livewire hydration and uses a server-side grant.
- Legacy E2EE passphrases are stripped; the plugin commit passes standalone build/type/test.
- Device access centralizes team, scope, path, wipe, and suspension checks.
- Portal/editor Markdown strips raw HTML; Copilot is the exception.
- Production boot fails closed without `APP_KEY`; destructive DB commands are prohibited; passwords strengthen in production; billing webhooks verify signatures.
- Current Composer/npm production locks have no known advisories from executed audits.

## Remediation gates

### A — Immediate containment

1. Disable CE archive generation/distribution; quarantine prior bundles.
2. Guard account deletion until transfer/erasure is safe.
3. Disable Copilot raw rendering or Copilot until sanitized.
4. Remove/qualify conflicting security, privacy, backup, AI, license, DLP, compliance, and infrastructure claims.

### B — Security and data correctness

1. Close P0-01/P0-02 with adversarial tests and artifact/storage inspection.
2. Repair team-scoped vault resolution and side-effect-free reads.
3. Enforce email verification and portal invariants.
4. Sanitize all RAG output and threat-model prompt injection.
5. Apply least privilege to publishing and centralize tenant invariants.

### C — Release engineering and operations

1. Require green tests, PHPStan, Pint, builds, audits, archive scans, Docker, and plugin gates from a clean checkout.
2. Generate SBOMs, sign immutable artifacts, preserve provenance, and test rollback.
3. Prove production backups/restores against approved RPO/RTO.
4. Establish SLOs, monitoring, alerting, on-call, incident response, load/capacity evidence, and vulnerability management.

### D — Enterprise assurance

1. Independent penetration testing of tenancy, tokens, Livewire, portals, WebSockets, pairing, E2EE, AI, billing, and packages; retest all critical/high fixes.
2. Independent cryptographic/protocol and multi-device interoperability review.
3. WCAG 2.2 AA assessment and assistive-technology evidence.
4. Counsel approval of licensing, terms, privacy/DPA, subprocessors, transfers, deletion, billing, and applicable law.
5. Claim-by-claim review against the exact production artifact and operations.

## Production acceptance criteria

- Zero P0; zero P1 unless formally accepted by executive, security, and legal owners with scope/expiry.
- Clean-checkout PHP, JS, TypeScript, plugin, PHPStan, Pint, archive, Docker, security, and E2E gates pass.
- Packages contain only allowlisted files and pass secret/runtime-data inspection.
- Lifecycle tests prove shared-data preservation, complete erasure, retries, backup expiry, and auditability.
- Cross-tenant tests cover duplicate slugs, loaded-relationship mutation, scopes, offboarding, collaboration, portals, and bindings.
- Restore/rollback drills meet RPO/RTO; monitoring proves every critical dependency and backup freshness.
- External testing has no unresolved critical/high findings on the release artifact.
- WCAG 2.2 AA evidence includes automated and assistive-technology testing.
- Terms/privacy/DPA/subprocessor/license materials match code and data flows.
- Every performance, encryption, privacy, backup, compliance, availability, and infrastructure claim has reproducible evidence.

## Standards baseline

- [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/releases/tag/v5.0.0): Level 2 product target; Level 3 for administration, cryptography, and tenant isolation.
- [OWASP Top 10:2025](https://top10.owasp.org/2025/).
- [OWASP LLM/GenAI Top 10 2025](https://genai.owasp.org/llm-top-10/), especially prompt injection and improper output handling.
- [NIST SP 800-218 SSDF 1.1](https://csrc.nist.gov/pubs/sp/800/218/final).
- [OWASP SCVS](https://scvs.owasp.org/scvs/) for SBOM, provenance, and component assurance.
- [W3C WCAG 2.2](https://www.w3.org/TR/WCAG22/), Level AA target.
- [Zimbabwe Data Protection Act](https://www.potraz.gov.zw/wp-content/uploads/2022/02/Data-Protection-Act-5-of-2021.pdf) and [SI 155 of 2024](https://www.potraz.gov.zw/wp-content/uploads/2025/02/sI-155-of-2024-Cyber-and-Data-Protection-Normal_240913_1250178.pdf), subject to qualified legal interpretation.

---

**Conclusion:** Synkk can become an excellent enterprise product, but polish and breadth cannot compensate for open data-exposure, deletion, tenancy, authentication, AI-output, licensing, and operational-control gaps. Freeze affected releases, close the evidence above, and independently verify the exact artifact. No new product features are required.
