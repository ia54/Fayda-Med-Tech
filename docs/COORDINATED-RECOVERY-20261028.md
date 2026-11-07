# Bounded coordinated source-document recovery — October 28, 2026

Continuation from verified `7bce3ca7a10b47ac88a61e0a8273250b7ea9dfbf`, on separate branch `codex/coordinated-recovery-20261028`. Environment draft **5**, its exact publication candidate, the recovery branch and evidence PR #6 are not changed. This closes the narrow synthetic database-to-private-source-file rehearsal gap identified in PR #6; it does not authorize or certify an operational backup/restore.

## Implemented scope

`api/tests/Support/SyntheticDocumentRecovery.php` is test-only. It accepts a PDO connection only with explicit synthetic opt-in, testing environment, SQLite `:memory:` configuration, and no file-backed or attached database. It cannot accept a production source database or arbitrary input/output directory. It creates a new unguessable mode-0700 workspace under `/tmp`; original application storage, keys and runtime databases are not adopted.

The existing pharmacy test fixture uploads two original/correction PDF sources through the actual API. The rehearsal takes a consistent SQLite `VACUUM INTO` snapshot, reads the source references **from that snapshot**, and copies only files whose relative path, stored size and SHA-256 match `pharmacy_source_documents`. The manifest binds the whole database digest and the actual ID/prescription/creator/path/size/hash inventory. The caller retains its separate manifest SHA-256.

Restore requires that trusted digest, verifies the database before opening it, compares its actual reference inventory to the manifest, and copies matching private files into a **new** target. It returns only after successful verification. Failed captures/restores remove their own partial outputs; pre-existing targets are refused and preserved. Absolute/traversing paths, symlinks, missing files, same-size corruption and digest/inventory substitution are rejected. Database and private-file modes are verified as 0600.

Limits: **16 files**, **1 MiB per file**, **8 MiB total source files**, **32 MiB database**, **256 KiB manifest**. Database page size/count is checked before snapshot copying. Source data and role assignments stay unchanged. This narrow rehearsal covers pharmacy source records that already store hashes; it does not invent hash columns for generic documents, back up application/Passport keys, or cover signed-file retention or hosted MySQL storage. Those remain separate engineering/operator evidence.

## Reproduction and validation

```sh
source /workspace/.cloud-tools/env.sh
cd /workspace/Fayda-Med-Tech/api
php vendor/bin/phpunit --colors=never --filter test_coordinated_ tests/Recovery/CoordinatedDocumentRecoveryTest.php
```

The file is outside the default Feature discovery path and uses the existing pharmacy fixture. The explicit filter runs only the new coordinated tests, not all inherited pharmacy methods. The backend workflow registers that exact focused command. Existing fixtures, the MySQL/MariaDB restore harness and application authorization code remain unchanged.

[Retained test output](evidence/coordinated-recovery-20261028/recovery-tests.txt) reports **7 tests / 98 assertions passed** and records successful whole-database row/schema comparison, two restored file hashes/sizes, original-database preservation and pre/post-restore API permissions: pharmacist and assigned technician 200 with exact bytes; biller, attorney, firm admin and provider 403; foreign organization and unassigned site 404; anonymous 401. Payments and fills remain zero. No external HTTP request was sent.

Negative coverage includes missing/corrupted/linked bundled files; manifest and database digest changes; a forged file list even with a newly supplied manifest hash; existing-target preservation; absolute and traversing paths; missing/corrupt/oversized source references; file-count/aggregate/database-size bounds; production/non-opted-in/file-backed/attached-database denial. No failing negative case is counted as a restored dataset.

An expanded local run initially failed because stopped disposable containers retained filesystem layers. Only recorded exited synthetic containers and disposable download-cache mirrors were removed; bind-mounted original fixtures remained. The successful final test run is authoritative. `start-synthetic.sh` now uses Docker `--rm`; default ordinary startup/readiness and automatic removal after stop were also exercised. No publication retry or environment-policy change occurred.

## Actual role UI and recordings

See [the role-demo instructions and videos](SYNTHETIC-ROLE-DEMOS-20261028.md). Provider, biller, attorney and firm-admin views were exercised on the built platform using the unchanged application source at `7bce3ca`, fresh shared-role fixtures, existing loopback ports and no write requests. This is a read-only demonstration with seeded MFA-attested sessions, not authentication acceptance.

The [evidence index](evidence/coordinated-recovery-20261028/index.json) binds code, logs, screenshots and recordings. No database, session token, private key, production information or real patient record is included. CI outcomes must be read at their exact source commit; no result at the older candidate is represented as validation of this change.
