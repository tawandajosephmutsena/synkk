# Synkk: plan to reach production

17 September 2026

## Where we are

The serious code defects found in the readiness review have been fixed in the inspected working trees, and the Laravel and plugin test suites pass. The esbuild advisory has also been cleared. That is a strong starting point, but it does not yet prove the service can be deployed, recovered, supported, and sold safely. **Do not launch to paying customers or make enterprise assurance claims yet.**

This plan adds no product features. It is about finishing the existing product, proving the release artifacts, and being ready when something fails. The detailed evidence and remaining risks are in [the remediation assessment](PRODUCTION_READINESS_REMEDIATION_2026-09-17.md).

## First: settle the release scope

Decide whether the next launch is (a) hosted Synkk Cloud/SaaS, (b) downloadable Community and Pro, or (c) both. They share code but need different operational proof. Name one release owner and choose the target region, customer type, support coverage, and claims that will appear on the website and contracts. Do not change the product scope while hardening it.

For hosted service, decide the accepted uptime and data-loss targets before choosing infrastructure. The current single-node SQLite/volume example is not evidence of enterprise high availability. Either use durable production services and prove failover, or explicitly launch with the narrower limits of a single-node service and avoid HA promises. The exact choice needs a documented owner and cost decision.

## Phase 1 — make one trustworthy release candidate

**Engineering owns this. Finish it before staging sign-off.**

1. Review and commit the outstanding Community fixes and PHPStan configuration. Review the readiness documents. Commit the Obsidian plugin upgrade in its own repository first, then update the main application's submodule pointer; align the Pro and SaaS plugin copies. Do not overwrite externally changed work.
2. Record the exact commit SHA for every repository and submodule in a release manifest. Build from clean checkouts with locked dependencies. No local-only file or dirty worktree should be needed to reproduce a release.
3. Make CI require the complete PHP, JavaScript, TypeScript, PHPStan, Pint, dependency-audit, plugin, archive-inspection, and Docker-build checks. Run them on those exact commits. Verify that the Community archive contains no database, backup, cache, secret, proprietary code, or unexpected file.
4. Generate software bill of materials and third-party notices; scan source, dependencies, container image, and final archives. Keep immutable artifact digests and a rollback artifact. Recheck advisories on release day.

**Done when:** a second person can fetch the release manifest and reproduce the same passing artifacts without your laptop. A reviewer signs the archive contents and security-sensitive changes.

## Phase 2 — prove the hosted deployment and recovery

**Operations and engineering own this. Do this in production-like staging, not only on a developer machine.**

1. Choose and document the production database, queue/cache, file storage, secret store, mail, WebSocket, billing, and optional AI-provider configuration. Set up TLS, trusted hosts/proxies, restricted network access, least-privilege service accounts, protected secrets, and key rotation. Keep AI disabled unless its data flow and terms are approved.
2. Deploy the frozen image to staging with the real deployment procedure. Test a fresh install, migration from the current schema, restart, failed migration, rollback, queue/scheduler operation, storage permissions, mail verification, billing webhooks, portals, collaboration, and device sync. Test on the database engine actually selected for production.
3. Set an agreed recovery point and recovery time (RPO/RTO). Run encrypted offsite backups, then restore one into an isolated environment and prove that vault content, accounts, billing state, and plugin sync work. Rehearse rollback after a bad release and record the measured result.
4. Add alerts for the things customers depend on: app errors and latency, database, queue lag, scheduler, storage, collaboration/WebSockets, mail, billing callbacks, and backup freshness. Name an on-call person, an incident escalation path, and a customer-communication template. `/up` by itself is not enough.
5. Test normal and high-load workloads with realistic vault sizes and concurrent devices. Set a measured performance budget, including the large main JavaScript bundle noted in the assessment. Fix actual bottlenecks before launch.

**Done when:** staging can be deployed and rolled back repeatably, a restore meets the agreed RPO/RTO, critical failures alert a human, and realistic load stays within agreed limits.

## Phase 3 — prove security, privacy, and accessibility

**Security, product, and qualified outside reviewers own this. It can run alongside Phase 2, but must be closed before general launch.**

1. Commission an independent penetration test of tenant boundaries, tokens and pairing, admin access, Livewire actions, portals, WebSockets, billing, exports, AI output, and packaged editions. Retest the final fixes on the release candidate. Review E2EE/key handling with a cryptography specialist before making strong encryption claims. Use [OWASP ASVS 5.0.0](https://owasp.org/projects/asvs) as a verification checklist, not a badge.
2. Finish a data map and deletion/retention procedure: database rows, vault bodies and versions, exports, logs, AI-provider records, and backup expiry. Test an actual account and tenant deletion end to end, including retries and legal holds. Do not promise a fixed deletion time without measured evidence.
3. Do a real [WCAG 2.2 AA](https://www.w3.org/TR/WCAG22/) review of login/2FA, editor, graph, sync, portals, billing, and mobile. Use automated checks plus keyboard-only and screen-reader testing. Fix and retest the findings; unit tests do not prove accessibility.
4. Have counsel approve the MIT Community and commercial Pro terms, Privacy Policy, DPA, subprocessor list, AI-provider use, cross-border transfers, retention/deletion language, billing/refunds, and the laws of launch markets. Legal requirements depend on where Synkk operates and serves customers; this plan is not legal advice.
5. Check every public security, backup, uptime, AI, DLP, and performance claim against what the final artifact and operations actually prove. Remove anything unverified. Follow [NIST's Secure Software Development Framework](https://csrc.nist.gov/pubs/sp/800/218/final) for the release evidence trail.

**Done when:** there are no unresolved critical/high security findings, accessibility issues are remediated for the launch scope, privacy/deletion behavior is demonstrated, and counsel signs off the customer-facing documents and claims.

## Phase 4 — release each product track

**Hosted SaaS:** first run a small, explicitly limited pilot with real monitoring, backup, support, and a rollback trigger. Exercise signup, verification, team creation, device pairing, sync/conflicts, E2EE, portals, billing, cancellation, and support. Expand only after the pilot stays within the agreed error, performance, and support limits.

**Community/Pro packages:** install the final archive on a clean machine with only its documented prerequisites. Verify migrations, upgrade from the prior release, licensing/entitlement behavior, plugin pairing and sync, backup/restore instructions, and a safe rollback. Publish checksums, license notices, supported environments, and security-contact details. Assess any older Community archives that might have contained live data before promoting a new one.

**Done when:** each track has its own signed release record. Passing SaaS checks do not sign off the downloadable packages, or vice versa.

## The actual go/no-go rule

Launch only when all of these are true for one frozen release candidate:

- Clean, reproducible builds and all required CI/security scans pass on the exact commits and artifacts.
- No open critical/high security issue remains, unless a named security and business owner formally accepts a narrow, time-limited risk with a rollback trigger.
- Backup restoration and deployment rollback have been demonstrated within the agreed targets; monitoring and on-call response are working.
- The independent security review, accessibility assessment, and legal/customer-document review are signed off.
- The pilot has passed its agreed customer, reliability, and support checks.

The first hands-on work is **Phase 1**. The decisions needed from you are the launch track, hosting/availability target, launch markets, and who can sign off security, operations, and legal risk. Those decisions affect the infrastructure and outside reviews, not the product feature set.
