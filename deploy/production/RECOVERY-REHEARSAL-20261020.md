# Migration, backup/restore and rollback preparation — October 20, 2026

This is non-production evidence and an operator checklist. No production host, live database, DNS, service, provider or patient record was accessed. It does not authorize cutover.

## Executed on disposable services

At source `ce839f40d36656c147166d63c88689071ee40617`, the saved MySQL 8 and MariaDB 10.11 containers were restarted on their internal Docker network with disposable tmpfs database storage. The checked-in migration, `mysql-upgrade-smoke.php` and `database-restore-smoke.sh` safeguards were retained. The PHP helper only accepts `fayda-cloud-mysql` or `fayda-cloud-mariadb`; it cannot select an arbitrary target.

- Both engines migrated successfully.
- Legacy upgrade checks passed on each engine: four additive migrations preserve legacy payment/settlement values, unknown fields stay null, and repeat migration succeeds.
- Separate `faydamed_ci_restore` databases matched **141 tables**, every row and schema definition on each engine.
- Synthetic private-file archives restored byte-for-byte.
- Restore targets were removed by the checked-in successful rehearsal. No operational data was overwritten.

The first MySQL restore attempt failed because its host `/tmp` evidence path was not mounted inside the PHP container. Its newly created, empty restore target was inspected (zero tables) and removed. A retry of the entire sequence encountered the upgrade fixture's intentionally unique synthetic organization; the already-passed upgrade seed was not run again. The corrected restore uses `TMPDIR=/workspace/.cloud-tools/tmp`, visible to both processes. Final MySQL restore and full MariaDB migration/upgrade/restore passed. These are harness/setup failures, retained in the evidence, not suppressed application failures.

Evidence logs: `docs/evidence/release-20261020/recovery-initial.txt`, `recovery-retry.txt`, and `recovery-final.txt`. This is cloud-local reproduction, not a new hosted Actions run. Existing exact-head hosted MySQL/MariaDB checks also pass (backend run 39307195215).

## Before an operational rehearsal

1. Hosting administrator supplies approved access and independently verified host identity, current application roots/services, database engine/version, and a separate approved restore target. Confirm existing documented target inventory; do not assume it remains current.
2. Name release/rollback/backup operators, maintenance authority, recovery objectives (RPO/RTO), retention and approved backup destination. Keep credentials and patient records outside Git, reports and web roots.
3. Inventory pending migrations against the **selected exact release** and actual migration table. Record forward operations, compatibility and row/schema validation criteria. Preserve unknown historical values. No `migrate:fresh`, default seeding, key regeneration or destructive cleanup on live data.
4. Capture a consistent database backup plus private/legacy files, encryption and Passport keys, environment/service configuration and prior release identity. Protect backups with existing approved access controls; record hashes and timestamps without exposing secrets.
5. Restore into the isolated target; verify tables/rows/schema, private-file hashes, file/database links, decryption and authentication with approved test records. Record restore duration and usable recovery point; a backup existing is insufficient evidence.
6. Rehearse pending forward migrations, repeat migration and privacy/role/financial invariants. Run approved synthetic HTTPS acceptance against the isolated candidate. CI restore does not establish actual host/data compatibility.

## Coordinated rollback verification to complete before cutover

- Record previous application/configuration artifact hashes and matching database/file snapshot. Establish a write-pause boundary and explicit go/no-go owner.
- Before writes reopen: test switching back the prior application/configuration **and** restoring the matching database/private files as one unit. Verify schema, linked documents, key compatibility, login and financial/stock totals. Do not assume reverse migrations or code-only rollback are safe.
- After writes reopen: pause writes, capture and reconcile new transactions and documents, then obtain an approved recovery plan. Never overwrite new records with a stale snapshot.
- Record actual recovery times, restored target identity, independent verifier, outcome and final cleanup. A failed rehearsal blocks cutover until corrected and repeated.

These production-shaped migration/rollback and real-backup restore steps are **pending approved hosting inputs**, not executed or denied. Source-packager and synthetic database restore evidence are available; actual operational recovery remains a release gate. See [LAUNCH.md](LAUNCH.md) for the existing constrained cutover runbook.
