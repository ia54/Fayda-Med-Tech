# Outstanding launch gates — 2026-09-24

Production has not been switched. A passing build is not release approval.

## Dependency remediation

Raw before/after audits are retained in the workspace audit-evidence/dependencies-20260924 folder.

- Website: 16 affected-package findings initially; compatible lockfile refresh yields zero known findings and production-origin build passes.
- API PHP: 37 advisories across 11 packages initially, plus one ignored JWT advisory. Compatible updates first reduced active findings to three Laravel advisories. Laravel 12.69.2, Swagger 9 and PHPUnit 11 upgrades now resolve all known PHP advisories, including JWT 7.2.0; the prior JWT advisory ignore has been removed. Local regression passes: 65 tests / 776 assertions. GitHub run 35989584311 passes PHP 8.3 plus MySQL 8 and MariaDB 10.11 schema, finance and recovery checks at backend commit 50d3509. doctrine/annotations remains abandoned (maintenance finding, not a current advisory).
- API asset tooling: ten affected-package findings reduced to zero after updating Vite and its Laravel plugin. Final asset production build passes. Use Node 20.19+ or 22.12+ for Vite 7; host Node runtime must be reverified.
- Admin: Next 15.5.26 and PostCSS override leave one low Quill HTML-export advisory with no listed patched version. DOMPurify now sanitizes all identified HTML display sinks and rich-editor values/changes. Four DOM-based security tests cover malicious markup, protocol tricks, embeds/clobbering, retained basic formatting and identical policies across both frontends. The advisory remains visible rather than suppressed; final browser acceptance remains required. The sanitizer excludes inline styles and embedded media by design.


Audit counts differ across tools: npm counts affected package entries; pnpm counts advisories. These are dependency matches, not evidence of exploitation. No vulnerabilities have been deliberately suppressed in this remediation batch.

## External-service findings from source

- Payment scope decided by the owner: record previously received payments only for launch. Online collection routes and the unsafe Stripe controller are removed, as is the backend Stripe SDK. A six-role regression proves old collection/webhook endpoints are absent and cannot add payment records. The existing receipt, duplicate/overpayment prevention and reversal workflows remain tested. Future collections require a separate receiving-account decision and a complete sandbox-tested integration; no Stripe credentials are required for this launch scope.
- Google Vision OCR now uses the provider's reported total page count, requests every page in batches of at most five, validates page identity/count and file/page errors, and rejects partial success. Language hints are transmitted. Failures expose no raw provider messages and store no partial extracted text. Four synthetic provider tests cover full extraction, later-batch failure, malformed/missing pages and tenant/role boundaries. The synchronous path is limited to 100 pages; larger documents require a separate asynchronous workflow. Actual provider delivery and provider agreement remain unverified; no patient files were sent by these tests.
- AiOcrController expects services.openai.api_key absent from services.php; no active route to that controller was found in the inspected route file. Do not advertise AI extraction as verified.
- DocuSign authentication now selects demo/production OAuth, verifies the configured account and discovers its API region. Document-organization credentials, validated provider origins, timeouts and sanitized failures are enforced. A persistent dispatch claim blocks duplicate/ambiguous resends; HMAC callbacks use the document tenant secret, deduplicate identical payloads and protect terminal states. Synthetic tests cover these behaviors. Completed PDF/certificate retrieval and private archival now have synthetic coverage, with authenticated downloads and no unsigned fallback. Real consent/account configuration, delivery/completion and artifact acceptance, actual operator-recovery acceptance and rendered full-application browser acceptance remain outstanding. Reconciliation tooling now supports read-only lookup and explicit audited linking against a saved dispatch snapshot; unresolved or legacy claims cannot be automatically cleared. See SIGNING.md; electronic signing is not release-ready.
- Browser access to raw environment secrets is retired. The admin status endpoint returns non-secret service status; arbitrary .env writes return 410.
- Password recovery now submits to the API rather than showing simulated success. Links use the configured admin origin and actual reset route. Responses avoid account disclosure; recovery/reset endpoints have dedicated rate limits. Password changes, reset-token consumption, session revocation and MFA-challenge removal are transactional. Password console logging is removed; authentication URLs are excluded from analytics and the reset page sets no-referrer/noindex metadata. Six synthetic API tests cover link encoding, hashed tokens, single use, expiry, validation, delivery-error sanitization, throttling, revocation and rollback. Two frontend request tests verify request privacy and failure handling. Production mail delivery, sender domain and invitation delivery remain unverified; example settings use a sandbox mailbox.

## Hosting and recovery

- Authorized host: 144.126.132.98 only; sites api.faydamed.tech, admin.faydamed.tech, faydamed.tech only. Never use the discarded 66.94.122.48 host.
- Working SSH/provider terminal remains unavailable. CyberPanel sign-in works but is not shell access.
- CyberPanel API backup reports 635 MB; contents/database/keys/isolated restore unverified. Admin and website backups report zero size; do not rely on them.
- Back up the actual admin tree /var/www/admin-faydamed-tech in addition to site home folders. Verify encryption and OAuth keys, database, private uploads and process configuration; rehearse isolated recovery.
- Verify installed Linux dependencies/extensions, database grants, permissions, queue/scheduler behavior and production configuration on the exact host.
- Rehearse reviewed migrations and all six role journeys with synthetic data before production cutover. Do not seed/reset production or regenerate existing keys.

## Evidence required for completion

Clean audited lockfiles or explicit reviewed remediation for every remaining advisory; current Linux build and DB regression results; restorable full backups; read-only production configuration gate; synthetic integration acceptance; six-role sign-in, access boundaries, documents and financial lifecycle acceptance; scoped deployment and rollback verification.

Upgrade compatibility: retained existing Laravel application structure, removed obsolete native-schema test switch, and preserved inactivity-timeout direction explicitly for Carbon 3. A real-token regression verifies recent activity succeeds and stale activity revokes access. Passport remains on its compatible 12.x major. Production PHP must be 8.3+ as declared.

References: [Next 15 upgrade guide](https://nextjs.org/docs/app/guides/upgrading/version-15); [Quill advisory](https://github.com/advisories/GHSA-v3m3-f69x-jf25).
[Laravel 11 upgrade guide](https://laravel.com/docs/11.x/upgrade); [Laravel 12 upgrade guide](https://laravel.com/docs/12.x/upgrade).

## 2026-09-24 follow-up validation

Commits 3f8b5b9, a4080c9 and aeea271 implement record-only payments, complete-or-failed OCR, non-secret configuration status and rich-text sanitization. GitHub run 35990886721 at aeea271 passes 71 PHP tests / 813 assertions, plus MySQL 8 and MariaDB 10.11 with 10 tests / 347 assertions each and 64-table synthetic restore verification. Website and admin production builds pass (109 admin pages); changed admin file lint passes. Website dependency audit remains zero; admin retains the one low Quill advisory.

Sanitizer guidance: https://github.com/cure53/DOMPurify . Provider page-count contract: https://docs.cloud.google.com/nodejs/docs/reference/vision/latest/vision/protos.google.cloud.vision.v1.iannotatefileresponse .

## Signing recovery and component browser acceptance

Backend commit 0c4068e passes 111 local PHP tests / 1,125 assertions. GitHub run 35999512415 passes PHP 8.3, MySQL 8 and MariaDB 10.11, including recovery, migration, legacy-upgrade and synthetic restore checks. Recovery defaults to read-only provider lookup; explicit linking requires an exact envelope ID, an authorized actor, a reason, and matching saved dispatch evidence. No automatic resend or guard reset is provided.

Chrome component acceptance used the actual shared preview with mocked responses and synthetic PDFs at 390x667 and 1280x900. It verified signed/certificate selection, protected download links, unavailable-file errors and retry controls. The preview now overrides the generic dialog width and limits PDF height so mobile headings, close buttons and download controls remain visible. These checks do not establish downloaded-file persistence, full-app six-role acceptance or real DocuSign delivery. No external provider request or patient data was used.

## Six-role application browser smoke checks — 2026-09-24

The current application was built for the existing local-only 127.0.0.1:3102 frontend and 127.0.0.1:8000 API, with a separate synthetic SQLite database, non-delivery mail and no production data. All six roles completed password plus authenticator sign-in and reached their expected dashboards in Chrome. Provider claims data, biller invoice balances and receipt/reversal history, patient case/payment summaries and authenticated original-document retrieval, attorney assigned-case visibility, firm administrator insurance data and Super Admin totals were observed. This is a smoke check, not exhaustive six-role workflow acceptance or real-provider acceptance.

The checks exposed and fixed insurance links offered to provider and billing roles despite backend denial; the menu and direct-page message now follow the existing backend boundary. Super Admin totals distinguish loading/errors from zero, total cases are labeled correctly, and hard-coded uptime and month-over-month claims are removed. The notification shortcut uses the shared working route. Backend permissions have not changed.

Still needed: complete create/edit/review/correction workflows across all roles, adverse-network UI acceptance, real mail/OCR/signing acceptance, verified server access, backups and scoped deployment verification. Local production-mode builds use local API origins and must never be deployed as production artifacts.

## Provider-to-biller financial browser acceptance — 2026-09-24

Real local Chrome/Next/Laravel acceptance completed: provider draft creation and revision ($120.50 to $125.50), submission with locked provider editing, biller review, receipt recording, explained reversal preserving history, replacement receipt, duplicate-reference rejection, and final $125.50 paid / $0 balance. Database read-back confirms four ledger entries netting to $125.50 and retained review metadata. The patient dashboard, invoice list and detail show correct totals and correction history without rendered staff notes. Local synthetic fixtures only; no money moved or external messages were sent. Other role write workflows, adverse-network behavior and real-provider acceptance remain outstanding.

Diagnostic CI commit 08e3c02 adds per-test progress, an eight-minute financial-suite limit and disposable-database failure diagnostics. Branch run 36008537897 passes PHP 8.3, MySQL 8 and MariaDB 10.11, including 39 database workflow tests per engine. Earlier run 36004060394 hit its MySQL job limit and is not a pass. Separate PR run 36008547448 also passes all three jobs at the same commit. MySQL took about three minutes in the branch run and about six minutes in the PR run; the earlier timeout cause is unproven. Retain bounded diagnostics for any recurrence.

## Legal workflow and report repair — 2026-09-24

Synthetic full-app Chrome acceptance verified pending settlement creation/editing, completion, required correction reason, preserved read-only original, linked replacement and corrected case/report totals without double counting. The report page exposed inert generation/export/history controls; the repair connects all six existing report types and saved results, adds CSV output and real history pagination, and distinguishes loading/failure/unknown values from zero. It preserves the existing API authorization and visual system. Numeric report display avoids binary floating-point artifacts; report controls wrap within narrow cards. Report results use a wrapping field/value list because the shared table minimum width clipped values in the dialog.

All six report types, saved-result retrieval and history page 2 returned actual API data in Chrome. Temporarily stopping only the synthetic local API produced a safe report error without stale values; restoring it and retrying succeeded. Two CSV regression tests cover unknowns/zeros/nesting/rounding and spreadsheet-formula neutralization; changed-file lint passes. Browser download persistence remains unverified: the download event timed out, and browser policy blocked download-history inspection. No workaround was attempted. These are local synthetic checks, not production or real-provider acceptance.

Mobile report acceptance remains open: the browser ignored the requested 390x667 override and stayed at a measured 960x830. Final visual results must be interpreted at that actual size.

Final report UI build passes type checks and generates 110 pages. Chrome at measured 960x830 verifies unclipped field/value results, wrapped actions and corrected numeric display. The local-origin artifact must not be deployed.
