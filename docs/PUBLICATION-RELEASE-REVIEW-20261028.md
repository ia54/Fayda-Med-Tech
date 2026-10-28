# Publication diagnosis and recovery-evidence review — October 28, 2026

This evidence-only continuation branches from `7bce3ca7a10b47ac88a61e0a8273250b7ea9dfbf`. The recovery candidate and environment draft revision **5** remain selected at that exact commit; no configuration update, publication retry, duplicate environment or production operation was performed. The owner reported two failed UI publication attempts, including the built-in Retry, with “Couldn’t publish environment. Your settings are saved. Try publishing again.”

## What the supported diagnostics establish

The shell is usable and the checkout was clean at the preserved candidate. Supported environment status reports running/connected, current observations, spec and observed spec revision **3**, and **failure: null**. It now lists the exact approved custom hosts `api.github.com` and `packagist.org` with the existing package-manager preset. Policy state is **unknown**, so the status does not establish enforcement. Retained diagnostics report `history_status: not_found`, zero records and no legacy snapshot. The legacy current-diagnostic endpoint directs callers to retained diagnostics and supplies no additional failure evidence.

**The publication root cause is not available in these results.** Running/connected, absent diagnostics and a null runtime failure do not prove successful publication, snapshot capture or fresh-task restoration. The environment was previously reported unavailable; its current usability is a new observation, not proof of the failed operation's recovery. No unsupported publication, daemon restart or policy bypass was attempted. The workspace had about 1.8 GiB free during inspection; no provider quota or snapshot-size error was reported, so disk usage is not established as the publication cause.

Actual operations now succeed through the inherited supported proxy:

- GitHub API repository GET returned **HTTP 200**. This proves that read, not arbitrary API write authorization.
- Local `composer audit --locked --abandoned=report --no-interaction` exited **0**, reporting **no vulnerability advisories**. It separately reports abandoned `doctrine/annotations` with no suggested replacement. This is maintenance debt, not a reported vulnerability or an invented replacement plan.

These checks supersede the earlier denied-operation observations only for the current runtime/attempt. No network configuration was changed by this review. [Sanitized observation and audit](evidence/publication-review-20261028/index.json) retain scope and hashes; no credentials or full diagnostic logs are included.

## Release, migrations and recovery safeguards inspected

| Area | Source-backed evidence | Remaining limit |
| --- | --- | --- |
| Immutable source release | `release_package.py` requires a full commit, excludes runtime/private/build/backup files, refuses links/submodules and unsafe paths, records per-file/migration hashes, verifies a separate trusted digest, and refuses overwrites and checkout output | This is source packaging, not an installed release, runtime backup or rollback image. A self-declared commit in a manifest must still be paired with the separately trusted artifact digest. |
| Archive rejection | Three focused existing tests passed: mutable refs/overwrite/checkout boundaries; wrong digest/commit/changed bytes; unsafe/duplicate/missing/link entries without extraction | These checks validate the packager's boundaries, not actual hosting permissions or deployment. No application-code defect was established in this review. |
| Migration inventory | Rebuilt and independently verified exact `7bce3ca` source archive: **1,261 files / 137 migrations**, SHA-256 `4f582511d78e7e21d6cc32b7911d4a21fb697f99966d7dfcb232f940805eff9c`, `source_only=true`, `production_ready=false` | An inventory is not a forward-upgrade rehearsal against the real hosted schema and retained records. Existing source-bound MySQL/MariaDB CI remains valid for its disposable fixtures. |
| Disposable database restore | `database-restore-smoke.sh` checks testing/CI/MySQL/loopback/exact synthetic database/container preconditions, refuses an existing recovery target, dumps with the engine's matching client, and restores into a separate database. Its verifier compares every row, populated financial tables, and schema definitions/constraints | The positive restore was already verified by CI at `7bce3ca`; no unchanged full database suite was repeated. A new negative guard check returned 1 before any Docker/database operation when CI preconditions were missing. These scripts must not be pointed at an operational database. |
| Private files | CI archives/restores a standalone synthetic private file and checks it byte-for-byte. Source-browser proof separately binds an uploaded prescription file to a database hash | The CI file rehearsal does **not** demonstrate a coordinated database-to-document backup with retained application/Passport keys, configuration, signed-file trees or hosted permissions. Those remain explicit evidence gaps, not passed gates. |
| Rollback | LAUNCH.md and GODADDY-SPLIT-PILOT.md require paused writes, a coordinated final snapshot, separate restore rehearsal, matching code/configuration/keys/database/private files, and reconciliation of writes before rollback | No current hosted snapshot, tested rollback capacity, retention/RPO/RTO, recovery owner, or physical/operator acceptance was verified here. Do not use code-only rollback across incompatible schema changes or restore stale data after writes reopen. |

The source archive remains outside the checkout under `/workspace/.cloud-tools/publication-diagnosis-20261028/`; its trusted digest and verification result are retained in Git. The targeted tests and guard are bounded, synthetic and non-production. No engineering defect was reproduced that justifies changing application or restore behavior; the observed gaps concern the scope of existing rehearsals and required external evidence.

## Required next evidence

1. The product must expose or resolve the failed publication operation; the current supported tools provide neither a publication failure record nor a publish/reload repair control. Do not repeat publication attempts without a meaningful new diagnosis or state change. Keep draft 5, the approved restricted policy, Only me and zero-secret scope intact.
2. After an actual successful publication, verify the resulting configuration/version and fresh runtime, normal Git, dependency/tool restoration, network observations, actual advisory/API operations and synthetic service startup. Do not substitute this current-runtime check for that proof.
3. Before any separately authorized hosted release, obtain current nonsecret hosting/capacity/private-storage evidence and a coordinated isolated synthetic backup/restore of the database, database-referenced files, keys/configuration and signed-file trees, with recorded hashes, access checks, recovery targets/owners and rollback/write-reconciliation procedure. No production access is authorized by this report.

The six CI successes at `7bce3ca` were independently confirmed already and were not polled again. Controlled and compound release remains disabled. No merge, deployment, provider call, PHI, payment or dispensing occurred.
