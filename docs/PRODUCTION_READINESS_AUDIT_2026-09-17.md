# Synkk Production Readiness Re-audit

**Re-audit date:** 17 September 2026 10:42

**Application:** `/Users/mac/Herd/synkk` at `c608ce9`, including current uncommitted portal tests and 404 change

**Plugin:** embedded submodule and `/Users/mac/Herd/obsidian-synkk-sync` at `e91448a`

**Verdict:** **NO GO FOR PUBLIC OR ENTERPRISE PRODUCTION**

**Overall readiness:** **62/100**

## Executive decision

The latest changes close most previously demonstrated direct vulnerabilities. Cross-tenant vault selection is team-scoped, initial private portal access checks membership, removed members' tokens are deleted and rejected, raw Markdown HTML is stripped, encrypted Livewire saves use the validated envelope factory, containers fail when `APP_KEY` is absent, and the automated Laravel and JavaScript suites pass.

Production remains blocked. A password-protected portal still trusts a client-controlled Livewire unlock flag, and private portal authorization runs only in `mount()`, leaving already-mounted sessions without continuous membership enforcement. The plugin persists its E2EE passphrase and bearer token. The TypeScript gate is red, Docker uses an unsupported Node version for Vite 8, and legal, recovery, operational, accessibility, and claim-substantiation controls remain incomplete.

## Readiness scorecard

| Area | Score | Decision |
|---|---:|---|
| Tenant isolation and authorization | 68/100 | Blocked by portal reauthorization |
| Application security | 70/100 | Blocked by portal unlock state |
| Data protection and cryptography | 58/100 | Blocked by local secret persistence |
| Build, CI, and supply chain | 64/100 | Plugin type gate is red |
| Reliability and recoverability | 52/100 | Restore capability unproven |
| Operations and observability | 48/100 | Enterprise controls incomplete |
| Legal, privacy, and product truth | 38/100 | Required public controls absent |
| Accessibility | 48/100 | WCAG 2.2 AA unverified |
| **Overall** | **62/100** | **No go** |

## Release blockers

### P0-01 — Password-protected portal access depends on client-controlled Livewire state

**Evidence:** `resources/views/pages/portals/show.blade.php:36-37,65-95,349-375`

`$isUnlocked` is a public Livewire property and directly controls protected content. The authoritative session value is read only during `mount()`. Public properties must be treated as untrusted request input, so a crafted component update can attempt to set the flag without passing `unlock()`.

**Fix:** Remove `isUnlocked` as an authorization source. Compute access on every request from a server-side portal session grant. Bind the grant to portal ID and a password version or hash fingerprint, expire it, and invalidate it on password change. Lock identity-bearing state. Add a tampering test that sets `isUnlocked=true` and proves no note, file list, graph, search result, or computed content is returned.

### P0-02 — Private portal membership is checked only during initial mount

**Evidence:** `resources/views/pages/portals/show.blade.php:42-63,97-209`

The membership check runs in `mount()`. Livewire updates rehydrate the component without using `mount()` as a per-request authorization hook. A member who loads a portal and is then removed can retain a component snapshot and invoke actions or computed content.

**Fix:** Centralize `authorizePortalAccess()` and execute it during hydration or before every action and protected computed property. Verify current membership, portal visibility, vault/team consistency, account status, and password grant. Test mounting as a member, removing membership, then reusing the same component for note selection, search, graph, and rendering.

## High priority findings

### P1-01 — Plugin settings persist the E2EE passphrase and bearer token

**Evidence:** `obsidian-plugin/src/types.ts:1-23,51-73`; `obsidian-plugin/src/settings.ts:299-310`; `obsidian-plugin/src/main.ts:34-46`

`e2eePassphrase` and `deviceToken` are saved with the full settings object and restored on startup. A process that reads plugin data can recover both the decryption secret and API credential.

**Fix:** Never persist the passphrase. Keep the derived key only in memory for a bounded session and require unlock after restart. Use a reviewed native credential store where available. Minimize token scope, make rotation/revocation visible, remove legacy saved passphrases, and rotate exposed tokens.

### P1-02 — Plugin TypeScript release gate fails

**Evidence:** `/Users/mac/Herd/obsidian-synkk-sync/src/migrationWizardModal.ts:409`; `.github/workflows/tests.yml:38-43`

The build succeeds, but `tsc --noEmit` fails because the disabled expression returns `boolean | null`. CI runs this type check.

**Fix:** Convert it to a strict boolean, such as `this.preflightResponse !== null && !this.preflightResponse.authorized`. Combine plugin build, typecheck, and tests into one required local and CI command.

### P1-03 — Docker uses a Node version outside Vite 8's supported range

**Evidence:** `Dockerfile:28-33`; `package.json:24`; `.github/workflows/tests.yml:30-33`

Docker uses Node 20.18.3; Vite 8 requires Node 20.19+ or 22.12+, while CI uses Node 22.

**Fix:** Pin Docker and CI to the same supported Node 22 patch, declare `engines.node`, and build the exact Docker image in CI. Docker was unavailable on the audit host, so the image was not built or started.

### P1-04 — Portal passwords allow brute force and weak credentials

**Evidence:** `resources/views/pages/portals/index.blade.php:71-88`; `resources/views/pages/portals/show.blade.php:85-95`

Passwords allow four characters and `unlock()` has no rate limit, cooldown, attempt audit, or alert. The session grant has no explicit expiry or password-version binding.

**Fix:** Require a strong or generated access secret; rate limit by portal plus a privacy-preserving client identifier; add progressive delay and security events; expire grants; invalidate them on password change.

### P1-05 — Tenant consistency is enforced only in the portal UI path

**Evidence:** `resources/views/pages/portals/index.blade.php:71-77,108-134`; `app/Models/VaultPortal.php:38-58`; `database/migrations/2026_09_16_183000_create_vault_portals_table.php:16-36`

Team-scoped validation blocks the demonstrated UI exploit. The model still mass assigns `team_id`, `vault_id`, and `primary_file_id`, and the database does not enforce that they belong together.

**Fix:** Use one authorized domain action that derives team ID from context. Validate vault and primary file ownership. Add model-level fail-closed validation and supported composite database constraints. Test direct service/model creation and primary-file reassignment.

### P1-06 — Portal defense still depends on inline script execution

**Evidence:** `app/Services/PortalRendererService.php:114-118,182,243-244,319-328`; `docker/nginx.conf:25-31`

Raw HTML is stripped, but generated output still includes inline `onclick` and dynamically assembled Alpine expressions. CSP permits `'unsafe-inline'` and broad `ws:` and `wss:`. Hostile tests cover only script and image-handler examples.

**Fix:** Replace inline handlers with static listeners and data attributes. Use context-correct encoding. Add SVG, MathML, iframe, srcdoc, malformed HTML, encoded payload, CSS URL, unsafe scheme, frontmatter, and wikilink tests. Adopt nonce or hash CSP and restrict connection origins.

### P1-07 — Public product claims are inconsistent or unsubstantiated

**Evidence:** `README.md:100-132`; `resources/views/welcome.blade.php:1495,1550,1695`; `resources/views/documentation.blade.php:883`

The README presents E2EE, ghost files, and CRDT as current capabilities and also as “Next.” Marketing references WebAssembly while implementation uses WebCrypto. SOC 2 positioning and fixed performance language lack certification or benchmark evidence in this repository.

**Fix:** Create a claim register with exact wording, owner, implementation evidence, test or benchmark, release, scope, and approval. Clearly label shipped, beta, roadmap, self-hosted, and cloud behavior. Remove compliance implications until independently supported.

### P1-08 — Customer legal and privacy surfaces are absent

**Evidence:** Public routes contain no privacy notice, terms, acceptable use, cookie notice, DPA, subprocessor list, retention policy, refund terms, or vulnerability disclosure page.

**Fix:** Before processing customer data or payments, have counsel approve applicable terms and disclosures. Cover controller/processor roles, lawful bases, data inventory, subprocessors and transfers, retention/deletion, export/access requests, breach notification, billing/refunds, age limits, acceptable use, and cloud versus self-hosted responsibility. Provide working privacy and private security contacts.

## Medium priority findings

### P2-01 — Portal publication is available to every team member

**Evidence:** Existing test `team member can create a new portal via studio`; `resources/views/pages/portals/index.blade.php:35-148`.

Add dedicated create, update, publish, password-rotation, and delete permissions, default them to owner/admin roles, and audit every publication action.

### P2-02 — Member removal and token revocation are not transactional

**Evidence:** `resources/views/pages/teams/remove-member-modal.blade.php:32-55`.

Middleware now prevents continued access, but membership and token deletions are separate writes. Use a transaction and central offboarding action; revoke pairing and collaboration sessions and test concurrent requests.

### P2-03 — Dependency maintenance omits Composer and both npm projects

**Evidence:** `.github/dependabot.yml:1-11`.

Add Composer, root npm, and plugin npm updates. Add SBOM generation, secret and license scanning, SAST, and release provenance. Current audits are clean only for the present lockfiles.

### P2-04 — Default SQLite deployment lacks verified recovery

**Evidence:** `docker-compose.yml:18-38`; `.env.example:23-38`.

Define RPO/RTO, encrypted offsite backups, consistent database and object snapshots, restore drills, corruption handling, supported scale, and failover. Managed enterprise service should use durable managed services or formally test and accept single-node limits.

### P2-05 — Health and operations do not prove service readiness

**Evidence:** `docker-compose.yml:29-34`; `docker/supervisord.conf:25-48`; `.env.example:18-21`.

`/up` does not verify queues, scheduler, Reverb, database writes, storage, or dependencies. Add structured redacted logs, immutable audit events, metrics, alerts, synthetic checks, SLOs, on-call ownership, incident runbooks, status communication, and capacity alarms.

### P2-06 — RAG indexing remains memory and query intensive

**Evidence:** `app/Services/VaultRagService.php:55-105`; `app/Jobs/IndexVaultRagJob.php:20-58`.

Use lazy/chunked iteration, batch preload/upsert, a dedicated queue, memory limits, resumable checkpoints, and representative load tests.

### P2-07 — Accessibility conformance is unverified

No complete WCAG 2.2 AA audit, screen-reader record, keyboard matrix, zoom/reflow evidence, contrast report, status-message review, or accessibility statement was found. Run automated checks plus manual keyboard, VoiceOver/NVDA, focus, error recovery, motion, and mobile reflow testing.

### P2-08 — Frontend bundle lacks a production performance budget

The build reports the app JavaScript at about 721 kB minified and 239 kB gzip and emits a chunk warning. Split editor, graph, collaboration, and admin code by route; set CI budgets; verify Core Web Vitals on production-like devices and networks.

### P2-09 — Security headers improved, but CSP remains permissive

**Evidence:** `docker/nginx.conf:25-31`.

Remove inline dependencies, use nonces or hashes, narrow WebSocket origins, add reviewed cache controls, and verify the same policy at the actual edge. Apply HSTS only on HTTPS production hosts.

### P2-10 — Production environment defaults are development-oriented

**Evidence:** `.env.example:18-23`.

Provide a production template with debug disabled, suitable logging, secure cookies, trusted proxies/hosts, durable cache/session/queue/database services, mail, distributed rate limiting, and secret-manager references. Validate required settings at boot.

## Previously critical findings now resolved

| Prior finding | Current evidence | Status |
|---|---|---|
| Cross-tenant portal vault selection | Team-scoped rule and create/update tests | UI path resolved; invariant work remains P1-05 |
| Any authenticated user could view a private portal | Membership check and 404 tests | Initial access fixed; continuous authorization remains P0-02 |
| Removed member device tokens remained valid | Token deletion, middleware check, four tests | Resolved |
| Raw Markdown HTML enabled stored XSS | `html_input=strip` for notes and callouts | Demonstrated payload fixed; broader hardening remains P1-06 |
| Ephemeral `APP_KEY` | Entrypoint and Compose fail closed | Resolved |
| Malformed encrypted Livewire envelopes | Validated envelope factory used | Resolved |
| Red Laravel and JavaScript suites | Current suites pass | Resolved; TypeScript remains red |
| Tracked `cookies.txt` | Removed and ignored | Resolved |
| Queue retry below job timeout | 630-second retry for 600-second job | Resolved |

## Verification record

| Check | Result |
|---|---|
| `php artisan test --compact` | **Pass:** 400 tests, 1,809 assertions, one unspecified warning |
| Focused portal/revocation/security tests | **Pass:** 29 tests, 122 assertions |
| Root `npm run test:js` | **Pass:** 55/55 |
| Embedded plugin `npm test` | **Pass:** 54/54 |
| Standalone plugin `npm test` | **Pass:** 54/54 |
| PHPStan | **Pass:** 0 errors |
| Composer audit | **Pass:** no known advisories |
| Root and plugin production npm audits | **Pass:** 0 known vulnerabilities |
| Composer validation | **Pass** |
| Root production build | **Pass with large-chunk warning** |
| Standalone plugin build | **Pass** |
| Standalone plugin TypeScript | **Fail:** `migrationWizardModal.ts:409` |
| Embedded plugin TypeScript | Local dependencies absent; shared line 409 remains applicable after install |
| Docker image build/start | **Not run:** Docker unavailable on audit host |
| Live billing/mail/DNS/TLS, backups, restore, load, accessibility, penetration test | **Not verified** |

## Required remediation order

1. Close P0-01 and P0-02 with adversarial Livewire tests.
2. Remove persisted passphrases, migrate old settings, and rotate affected tokens.
3. Repair TypeScript, align Node versions, and require exact artifact builds in CI.
4. Centralize portal authorization, invariants, permissions, password controls, and audit events.
5. Remove inline script execution and deploy strict CSP.
6. Complete legal/privacy documents and reconcile public claims.
7. Implement monitored backups and complete a production-scale restore drill.
8. Add observability, incident response, dependency automation, SBOM/provenance, and rollback.
9. Complete WCAG 2.2 AA testing, load testing, and an independent penetration test.

## Production acceptance criteria

- No open P0; every P1 closed or formally accepted by an accountable owner with expiry.
- Portal tampering and post-removal reauthorization tests pass for every data path.
- Passphrases are absent from persisted plugin data and legacy copies are removed.
- PHP, JavaScript, TypeScript, plugin, build, Docker, and end-to-end gates pass on a clean checkout and exact artifact.
- Backup and restore meets documented RPO/RTO.
- External testing covers tenant isolation, Livewire, portals, WebSockets, pairing, E2EE, billing, and packages; all critical/high findings are retested closed.
- Approved legal/privacy pages, export/deletion, subprocessors, and security reporting are live.
- WCAG 2.2 AA evidence includes automated and manual assistive-technology testing.
- Every product and compliance claim has approved evidence.

## Standards baseline

- OWASP Application Security Verification Standard 5.0
- NIST SP 800-218 Secure Software Development Framework 1.1
- W3C Web Content Accessibility Guidelines 2.2, Level AA target
- GDPR data protection by design and by default, plus applicable Zimbabwe and customer-market privacy law

This is a technical readiness assessment, not legal advice. Exact obligations depend on the operating entity, markets, hosting model, contracts, subprocessors, and production data flows.
