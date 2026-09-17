# Synkk Production Readiness Audit

**Audit date:** 17 September 2026  
**Target:** Synkk Laravel application at commit `79f07c9728ab2627508fd10fd1bb5afdd5ec66b5`, embedded Obsidian plugin at `dac75e22361c23300b715b91511f348a5b8a0a25`, and standalone plugin checkout  
**Verdict:** **NOT READY FOR PRODUCTION**  
**Release gate:** Block production and commercial launch until every P0 item and the P1 release controls are closed and independently retested.

## Executive assessment

Synkk has a strong technical base: Laravel 13, explicit team and vault policies, hashed device tokens, signed and replay bounded billing webhooks, passkeys and two factor support, E2EE transport envelopes, conflict preservation, dependency pinning, static analysis, and a substantial automated test suite.

The current build is not safe to market or operate as an enterprise service. The audit found direct cross tenant portal exposure paths, persistent device access after team membership removal, stored XSS in public portals, broken release gates, non durable key generation in the container entrypoint, and material discrepancies between product claims and verified behavior. Privacy, terms, data processing, retention, incident response, accessibility conformance, backup recovery, and enterprise assurance artifacts are also absent or unverified.

### Readiness scorecard

| Area | Score | Status |
|---|---:|---|
| Tenant isolation and authorization | 35/100 | Blocked |
| Application security | 42/100 | Blocked |
| Data protection and cryptography | 48/100 | Blocked |
| Reliability and recoverability | 44/100 | Blocked |
| Build, CI, and supply chain | 51/100 | Blocked |
| Product truth and legal readiness | 24/100 | Blocked |
| Accessibility and user safety | 45/100 | Unverified |
| Observability and operations | 40/100 | Incomplete |
| Overall production readiness | **41/100** | **No go** |

## Release blocking findings

### P0-01: A tenant member can publish another tenant's vault through a portal

**Confidence:** High  
**Evidence:** `resources/views/pages/portals/index.blade.php:70-84,110-134`; `app/Models/VaultPortal.php:155-169`; `resources/views/pages/portals/show.blade.php:42-69`.

`savePortal()` validates `vault_id` only with `exists:vaults,id`. It does not require the selected vault to belong to the current team. The new portal is assigned the attacker's `team_id` but keeps the foreign `vault_id`. `VaultPortal::getAccessibleFiles()` then retrieves files solely by that foreign vault ID, and the public portal renders them.

**Impact:** Cross tenant disclosure of entire vault contents to a public URL. A normal team member is explicitly allowed by the existing test suite to create portals.

**Required correction:** Validate ownership with a team scoped rule or query; authorize portal creation and publication; enforce the invariant at the database or service layer; reject inconsistent `team_id` and `vault_id`; add a regression test that uses two teams and proves create, update, primary file, and render paths cannot cross the boundary.

### P0-02: Any signed in user can open another tenant's private portal

**Confidence:** High  
**Evidence:** `resources/views/pages/portals/show.blade.php:42-50`.

A private portal checks only `auth()->check()`. It does not verify that the user belongs to the portal team or has permission to the underlying vault and path.

**Impact:** Cross tenant disclosure to any authenticated account when a portal slug is known or discovered.

**Required correction:** Authorize the portal and its vault for every request and Livewire action. Return 404 for unauthorized tenants. Add tests for unrelated users, removed members, suspended teams, hidden paths, password protected portals, and tampered Livewire state.

### P0-03: Removed team members retain working device tokens

**Confidence:** High  
**Evidence:** `resources/views/pages/teams/remove-member-modal.blade.php:31-47`; `app/Http/Middleware/AuthenticateDeviceToken.php:63-107`; `app/Services/DeviceVaultAccess.php:14-26`; `app/Models/Vault.php:141-202`.

Removing a member deletes only the membership row. Existing device tokens remain. Device authentication verifies the token's user and team records, but not current membership. Vault authorization verifies matching team IDs and token scope. A former member without a role falls through to the vault's default permission, which is commonly `read_write`.

**Impact:** Offboarded users can continue reading and modifying vault data indefinitely.

**Required correction:** Revoke all team device tokens transactionally when membership ends; independently require live team membership in device authorization; invalidate pairing sessions and collaboration presence; test removal, role downgrade, team deletion, suspension, and token replay.

### P0-04: Public portals allow stored script injection from vault Markdown

**Confidence:** High  
**Evidence:** `app/Services/PortalRendererService.php:109-129,239-249`; `resources/views/pages/portals/show.blade.php:671-674`.

Portal Markdown uses CommonMark with `html_input => allow`, and the result is emitted with unescaped Blade output. Callout bodies also allow raw HTML. A vault editor can persist active HTML into a public or password protected portal.

**Impact:** Stored XSS in visitors' Synkk origin, including account actions for signed in users, portal session access, phishing, content alteration, and possible data extraction.

**Required correction:** Strip raw HTML or sanitize against a strict allowlist after all transformations. Remove inline event handlers generated by the renderer. Add hostile payload tests for script tags, SVG, MathML, `iframe`, `srcdoc`, event handlers, CSS URLs, malformed HTML, and link schemes. Deploy a nonce or hash based CSP as defense in depth.

## High priority findings

### P1-01: Container builds are not reproducible with the declared frontend stack

**Evidence:** `Dockerfile:29-33`; installed Vite 8 declares Node `^20.19.0 || >=22.12.0`, while the image uses Node `20.18.3`.

The production image can fail or behave outside the supported runtime. Align local development, CI, Docker, and documented versions, then build and run the exact release image in CI.

### P1-02: APP_KEY is generated inside an ephemeral container

**Evidence:** `docker/entrypoint.sh:12-20`; `docker-compose.yml:23-25`.

When `APP_KEY` is absent, the entrypoint writes a generated key into the container filesystem. The `.env` path is not persisted. Recreating the container can rotate the key and invalidate sessions, encrypted framework values, recovery data, and any application encryption that relies on it.

Require an externally managed, stable production key and fail closed when it is missing. Document rotation and recovery procedures. Never auto generate a production key at boot.

### P1-03: E2EE passphrases are persisted in Obsidian plugin settings

**Evidence:** `obsidian-plugin/src/types.ts:65-79`; `obsidian-plugin/src/settings.ts:291-336`; `obsidian-plugin/src/main.ts:44-46`.

The plugin model includes `e2eePassphrase`, and the UI assigns the entered passphrase to saved plugin settings. Obsidian plugin settings are normally stored in the vault's plugin data file. That undermines the claimed protection if the device or synced configuration is exposed.

Use OS credential storage where available or require an unlock per session. Never sync the passphrase. Publish a clear threat model covering local compromise, browser memory, recovery, sharing, revocation, and metadata leakage.

### P1-04: Encrypted save metadata is trusted without strict validation

**Evidence:** `resources/views/pages/vaults/show.blade.php:295-368`.

The public Livewire action decodes Base64 without strict mode, constructs an encryption envelope directly, and does not apply the validation performed by `VaultContentEnvelope::fromValidated()`. It can persist malformed IVs, tags, or ciphertext marked as encrypted.

Use one validated envelope boundary for API and Livewire traffic. Reject malformed Base64, enforce algorithm parameters and size bounds, verify vault E2EE state, and use authenticated format versioning.

### P1-05: Product claims conflict with source behavior and roadmap status

**Evidence:** `README.md:100-102,124-132`; `resources/views/welcome.blade.php:678-682,1332-1343,1488-1528,1547-1573,1877-1940`; `resources/views/about.blade.php:420-443`.

Examples include XChaCha20-Poly1305 in the README versus AES-256-GCM in the product; README roadmap language versus categorical shipped claims; WebAssembly claims while the code uses WebCrypto; fixed subsecond performance claims without production benchmarks; "military grade", "Zero-Knowledge Verified", encrypted backups, automatic updates, custom SLAs, and SOC 2 positioning without audit evidence in this repository.

Create a claim register that maps every public claim to a test, benchmark, operating control, or approved roadmap label. Remove unverifiable superlatives and compliance implications before launch.

### P1-06: Required customer legal and privacy surfaces are absent

**Evidence:** No privacy, terms, cookie, retention, subprocessor, data rights, acceptable use, security disclosure, or refund routes were found in the public application.

Before collecting accounts, vault content, device and IP data, support data, or payments, publish jurisdiction reviewed documents covering controller and processor roles, purposes and lawful bases, retention and deletion, subprocessors and transfers, security measures, breach handling, data subject requests, children's use, acceptable use, billing, cancellation and refunds, open source versus cloud responsibilities, and contact details. Execute DPAs with relevant vendors. This section is an engineering readiness assessment and is not legal advice.

### P1-07: Release test gates are currently red

**Evidence:**

- Main JavaScript: 49 passed, 1 failed because `tests/JavaScript/sync-policy.test.js` imports an export absent from the embedded plugin.
- Standalone plugin: 10 passed, 10 failed because `package.json` declares ESM while ten test files use CommonJS `require()`.
- Full Pest invocation was initially blocked by the sandbox's local port restriction; the focused suite passed outside the sandbox. The full suite also contains five landing assertions that fail against the current uncommitted copy.

No release should proceed with a red gate. Make the plugin module format coherent, eliminate duplicated or drifting source copies, run the same commands locally and in CI, and require all release checks on the exact commit and artifact.

## Medium priority findings

### P2-01: Security headers are incomplete and inconsistent

`docker/nginx.conf` lacks HSTS and CSP; HSTS exists only in the optional Caddy layer. Both configs retain the obsolete `X-XSS-Protection` header. Define one reviewed header policy at the public edge: HSTS after HTTPS readiness, a restrictive CSP, frame protection, nosniff, referrer policy, permissions policy, and cache controls for sensitive responses.

### P2-02: Dependency maintenance covers only GitHub Actions

`.github/dependabot.yml` has no Composer or npm ecosystems. Current `composer audit` and production `npm audit` checks returned zero known advisories, but continuous coverage is missing. Add Composer, root npm, and plugin npm update streams; include audit, lockfile review, SBOM, license policy, secret scanning, static analysis, and artifact provenance in CI.

### P2-03: SQLite deployment is a single node design without verified recovery

The default Docker deployment combines Nginx, PHP FPM, queue, scheduler, Reverb, and SQLite in one container. Database and private storage volumes are persistent, but no backup automation, offsite copy, restore validation, point in time objective, capacity limit, corruption procedure, or failover design is supplied.

Define RPO and RTO; back up the database and object data consistently; encrypt and test restores; monitor WAL, disk, queue, and worker health; document supported scale. Enterprise cloud operation should use managed durable services or explicitly accept the single node limits.

### P2-04: Operations and incident response are incomplete

No evidence was found for centralized structured logs, audit log immutability, alert routing, SLOs, synthetic checks, tracing, capacity alarms, on call ownership, incident runbooks, customer notification procedures, status page, or post incident review. Application health alone is insufficient.

### P2-05: A development cookie jar is tracked

`cookies.txt` is tracked and contains a `synkk.test` XSRF cookie record. Even if it is local and expired, credential artifacts must never be versioned. Remove it from history as appropriate, add the pattern to ignore and secret scanning, and rotate any still valid local sessions.

### P2-06: Packaging commands are stale and inconsistent

`ExportSelfHostedBundle` identifies v1.0.0, advertises Lemon Squeezy, and assembles files differently from `PackageCommunityEdition`. The latter recursively includes database and bootstrap directories but relies on a partial exclusion list. Consolidate the packaging pipeline, generate from a clean export allowlist, inspect archive contents automatically, and test absence of databases, caches, cookies, secrets, logs, user content, and commercial only code.

### P2-07: RAG indexing is memory bound

`app/Services/VaultRagService.php` loads all eligible files and embeddings with `get()`. Large vaults can exhaust memory and degrade request or worker service. Index with bounded chunks or cursors, isolate workloads on a dedicated queue, set memory and time limits, expose progress, and benchmark representative vault sizes.

### P2-08: Accessibility conformance is unverified

The UI has positive semantic and reduced motion work, but no complete WCAG 2.2 AA audit, assistive technology test record, keyboard matrix, zoom/reflow proof, contrast report, error announcement review, or accessibility statement. Public claims should target WCAG 2.2 AA only after automated and manual verification.

## Existing strengths confirmed

- Laravel 13.30.1 on PHP 8.5 with typed code and PHPStan level checks.
- Device tokens are random and stored as SHA-256 hashes rather than plaintext.
- Vault API routes apply throttling and a dedicated device authentication middleware.
- Cross team vault access and read only mutation restrictions have focused tests.
- Dodo webhooks validate signed payloads with timestamp tolerance and idempotent event records.
- E2EE payload handling uses AES-256-GCM in the clients and disables server RAG for encrypted vaults.
- Conflict files preserve concurrent edits instead of silently overwriting them.
- Docker context excludes local SQLite files, private storage, logs, `.env` files, dependencies, tests, and developer artifacts.
- Composer and production npm advisory scans returned no known vulnerabilities on the audited lockfiles.
- Focused Laravel security, billing, portal, membership, and packaging tests passed: 39 tests, 158 assertions.
- PHPStan passed with zero errors.

These controls reduce risk but do not establish production readiness while the release blockers remain.

## Required release plan

### Gate 1: Containment

1. Disable portal creation and public portal serving until P0-01, P0-02, and P0-04 are fixed.
2. Revoke every device token belonging to removed or inactive members; add live membership enforcement.
3. Remove or qualify unsupported security, performance, compliance, backup, and availability claims.
4. Stop commercial onboarding until privacy and contract documents are approved.

### Gate 2: Engineering correction

1. Centralize tenant scoped authorization for portals, vaults, files, collaboration documents, billing, devices, exports, and Livewire actions.
2. Sanitize all user authored HTML and add CSP.
3. Harden E2EE secret storage, envelope validation, recovery, key rotation, and threat model documentation.
4. Fix Node and module version drift, unify the plugin source of truth, and restore all test gates.
5. Make the container fail closed on missing secrets and build the exact deployable image in CI.
6. Consolidate packaging around a clean allowlist and archive inspection.

### Gate 3: Enterprise operations

1. Define availability, RPO, RTO, retention, deletion, support, and incident objectives.
2. Implement monitored backups and complete a documented restore drill.
3. Add centralized audit logs, metrics, alerting, tracing, synthetic checks, and incident runbooks.
4. Add dependency update automation, SAST, secret scanning, SBOM, license review, artifact signing and provenance.
5. Commission an independent penetration test after fixes; retest authorization, XSS, WebSockets, pairing, E2EE, billing, and packages.

### Gate 4: Product and legal verification

1. Approve privacy notice, terms, DPA, subprocessor list, security page, acceptable use, billing and refund terms, deletion process, and vulnerability disclosure policy with qualified counsel.
2. Complete a data inventory and retention schedule, including logs, device data, IP addresses, vault content, embeddings, support records, and billing identifiers.
3. Complete WCAG 2.2 AA automated and manual testing.
4. Publish only claims backed by repeatable evidence and name roadmap items clearly.

## Final acceptance criteria

Production approval requires all of the following:

- Zero open P0 findings and documented acceptance for every remaining P1.
- Cross tenant negative tests at every data boundary, including Livewire state tampering.
- All PHP, JavaScript, plugin, build, Docker, and end to end checks green on a clean checkout.
- Clean dependency advisories plus SBOM and provenance for the released artifact.
- Successful backup and restore drill against production equivalent data volume.
- External penetration test with critical and high findings remediated and retested.
- Approved legal and privacy materials and a working deletion or export process.
- WCAG 2.2 AA verification with manual keyboard and assistive technology evidence.
- A claim register showing evidence for each public security, privacy, performance, availability, and compliance statement.

## Standards baseline used

- OWASP Application Security Verification Standard 5.0.0: https://owasp.org/projects/asvs
- NIST Secure Software Development Framework 1.1, SP 800-218: https://csrc.nist.gov/pubs/sp/800/218/final
- W3C Web Content Accessibility Guidelines 2.2: https://www.w3.org/TR/WCAG22/
- EU GDPR, including data protection by design and processor obligations: https://eur-lex.europa.eu/eli/reg/2016/679/oj

The exact legal obligations depend on Synkk's operating entity, customer locations, hosting model, subprocessors, and data flows. Obtain jurisdiction specific advice, including Zimbabwe's Cyber and Data Protection Act and rules in every target market.

## Verification record and limitations

| Check | Result |
|---|---|
| `composer audit --locked --no-interaction` | Pass: no advisories |
| Root `npm audit --omit=dev --json` | Pass: 0 vulnerabilities |
| Standalone plugin `npm audit --omit=dev --json` | Pass: 0 vulnerabilities |
| `vendor/bin/phpstan analyse` | Pass: 0 errors |
| Focused Pest suite outside sandbox | Pass: 39 tests, 158 assertions |
| Root `npm run test:js` | Fail: 49 pass, 1 fail |
| Standalone plugin `npm test` | Fail: 10 pass, 10 fail |
| Full Pest suite | Inconclusive as a clean release gate: browser plugin could not bind inside the sandbox and current landing edits also fail five assertions |
| Codex Security Deep Scan | Incomplete: coordinator failed before discovery with `error: unexpected argument '--thread-source' found` |
| Production infrastructure, external services, live billing, email delivery, DNS, TLS, backups, restore, load, browser accessibility, and penetration testing | Not verified in this repository audit |

This audit is based on the current local working tree, which contained pre-existing and concurrent uncommitted changes. It did not modify application code.
