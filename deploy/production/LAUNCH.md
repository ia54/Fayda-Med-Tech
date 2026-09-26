# Production cutover gate — FaydaMedTech

Target only 144.126.132.98 and api.faydamed.tech, admin.faydamed.tech, faydamed.tech. This is a runbook, not an executed deployment. Do not use the excluded server, touch unrelated sites, create public listeners or alter DNS.

## Current decision

24 September 2026: all three production origins answer HTTPS 200, but root SSH rejected both available-key and supplied-password authentication. Production backup/restore, current configuration and data upgrade remain unverified. Do not cut over while any of these remain unverified. Existing local admin builds use localhost API URLs and cannot be deployed as production artifacts.

## Before changes

1. Restore authorized server access. Confirm pinned host identity. Inspect only the three application roots and the admin process on existing port 3005. Reconfirm active roots: API /home/api.faydamed.tech/public_html, admin /var/www/admin-faydamed-tech, public website /home/faydamed.tech/public_html. Record actual service/process ownership, runtime and release identity; do not assume yesterday's map remains current.
2. Capture an operator-recoverable snapshot covering the application/database/private and legacy files, current environment, application encryption key, Passport signing keys and service configuration. Keep secrets and patient records on the approved host/backup destination, outside web roots and reports. Verify checksums and a separate isolated restore. CI restore of synthetic data does not satisfy this step.
3. Rehearse the exact pending migrations on an approved isolated copy or production-shaped synthetic fixture against the host's engine. Check existing duplicate identifiers, orphaned organization/case links, legacy private/public document references and users requiring MFA enrollment. Report aggregate findings only. Do not run migrate:fresh, default seeders, destructive cleanup or key regeneration.
4. Confirm one owner-controlled administrator can complete MFA and recovery. Establish password-reset delivery or an owner-operated recovery path. Public registration remains closed. External payments, insurer messages, e-sign vendors, OCR and AI must be either separately verified or clearly unavailable; do not market untested functions as live.

## Prepare release

5. Pin the reviewed branch commit and build outside live roots. Install locked dependencies and run host platform checks. Preserve existing keys/data/configuration. Configure APP_ENV=production, APP_DEBUG=false, APP_URL=https://api.faydamed.tech, FRONTEND_URL=https://admin.faydamed.tech, PUBLIC_URL=https://faydamed.tech, CORS_ALLOWED_ORIGINS=https://admin.faydamed.tech,https://faydamed.tech and secure HTTP-only cookies. Verify non-root database grants and private storage access at the web-server layer.
6. Rebuild admin with NEXT_PUBLIC_API_URL=https://api.faydamed.tech and NEXT_PUBLIC_API_BASE_URL=https://api.faydamed.tech/api; rebuild website with VITE_BASE_URL=https://api.faydamed.tech. Verify generated assets contain the intended origins and no test credentials/local endpoints. Include standalone static/public assets. Run production:check in the configured release; its PASS covers configuration only.
7. Inventory legacy documents with documents:privatize (dry run). Back up and validate migration using copies/checksums. Prevent direct public access before accepting patient documents. Do not remove originals until verified backup/access rules exist. Retain file/key recovery evidence.

## Controlled cutover and rollback

8. Establish a brief maintenance window with writes and relevant workers paused. Take the final consistent backup. Apply reviewed forward migrations once, verify schema, and switch only the three application releases. Keep the admin behind its existing loopback port 3005 and existing trusted HTTPS proxy. Do not restart unrelated services.
9. Before reopening writes, verify real HTTPS API/admin origins, MFA/login/logout/recovery, organization boundaries, private upload/preview, case editing, provider drafts, billing review, recorded payments/reversals, settlements, coverage, liens and correspondence with explicitly synthetic records. Check logs without printing PHI or secrets. Verify all six roles and mobile critical actions.
10. On failure before reopening writes, restore the prior application/configuration and validated database/file snapshot as a coordinated unit. A code-only rollback is unsafe after incompatible schema changes. After writes reopen, pause writes and reconcile new records before any rollback; never blindly restore a stale snapshot. Keep the original release and backups until acceptance and recovery ownership are recorded.

## Launch record

Record exact deployed commit and artifact hashes, migration results, backup/restore target and verification time, role acceptance results, enabled/disabled integrations, rollback owner and observed HTTPS checks. A successful build, root HTTP 200, or a GitHub push is not a deployed working release.
