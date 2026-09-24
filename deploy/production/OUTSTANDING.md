# Outstanding launch gates — 2026-09-24

Production has not been switched. A passing build is not release approval.

## Dependency remediation

Raw before/after audits are retained in the workspace audit-evidence/dependencies-20260924 folder.

- Website: 16 affected-package findings initially; compatible lockfile refresh yields zero known findings and production-origin build passes.
- API PHP: 37 advisories across 11 packages initially, plus one ignored JWT advisory. Compatible updates first reduced active findings to three Laravel advisories. Laravel 12.69.2, Swagger 9 and PHPUnit 11 upgrades now resolve all known PHP advisories, including JWT 7.2.0; the prior JWT advisory ignore has been removed. Local regression passes: 65 tests / 776 assertions. GitHub run 35989584311 passes PHP 8.3 plus MySQL 8 and MariaDB 10.11 schema, finance and recovery checks at backend commit 50d3509. doctrine/annotations remains abandoned (maintenance finding, not a current advisory).
- API asset tooling: ten affected-package findings reduced to zero after updating Vite and its Laravel plugin. Final asset production build passes. Use Node 20.19+ or 22.12+ for Vite 7; host Node runtime must be reverified.
- Admin: Next 14.2.16 had critical advisories. Upgrade to 15.5.26 with refreshed dependencies and PostCSS override reduces the scan to one low Quill advisory; final production-origin build passes (109 pages); changed upload components pass lint. A full browser journey pass on the upgraded build remains required. Quill 2.0.3 has an unresolved HTML-export advisory (GHSA-v3m3-f69x-jf25), with no patched version listed. Complete rich-text input/output review before treating it as accepted risk. Actual unsanitized HTML sinks were found in frontend/src/utils/ConvertMarkup.jsx and the admin FAQ/testimonial pages; input/output sanitization and browser regression are required, not just a package-version exception.

Audit counts differ across tools: npm counts affected package entries; pnpm counts advisories. These are dependency matches, not evidence of exploitation. No vulnerabilities have been deliberately suppressed in this remediation batch.

## External-service findings from source

- StripeController requests services.stripe.secret and services.stripe.webhook_secret, but config/services.php defines neither. Its webhook is nested under authenticated routes despite a comment saying otherwise. Intent creation accepts a globally existing invoice ID and a caller-supplied amount; payment completion needs scoped ownership, server-derived amounts, durable idempotency and ledger reconciliation. Do not enable real payments until repaired and sandbox-tested. Installing stripe-php does not establish working payments.
- Google Vision OCR sends private PDF bytes to its configured API. No production delivery or provider agreement is verified. Existing code breaks the page loop on a failed response, so partial failure handling requires review before marking a document processed.
- AiOcrController expects services.openai.api_key absent from services.php; no active route to that controller was found in the inspected route file. Do not advertise AI extraction as verified.
- DocuSign defaults to its demo endpoint. Account, consent/key, webhook signature handling and actual synthetic delivery remain unverified.
- Mail example settings use a sandbox mailbox. Production delivery, sender domain, reset/invitation delivery and failure handling remain unverified.

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
