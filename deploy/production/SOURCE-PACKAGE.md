# Prepare a pinned source package

This tool prepares files only. It makes no network request, starts no listener, extracts no archive and changes no database, service, domain or server. Its manifest always identifies the result as source-only and not production-ready. The approved targets remain 144.126.132.98 and the three FaydaMedTech origins, with the existing admin port 3005; this metadata is not proof of host access or configuration.

## Build from the reviewed source

Run from the repository root with Python 3.9 or newer and Git available. First confirm the exact reviewed commit and CI result. The tool requires a full SHA, reads committed Git blobs directly, and does not include uncommitted changes. Write outside the checkout to an approved local directory. Existing outputs are never overwritten.

```bash
release_commit=$(git rev-parse HEAD)
mkdir -p ../release-artifacts
python3 deploy/production/release_package.py build \
  --commit "$release_commit" \
  --output "../release-artifacts/source-$release_commit.tar.gz"
```

The JSON result records the archive SHA-256, commit, source tree, file count and migration count. Keep that result in a separate trusted release record. The archive contains `RELEASE-MANIFEST.json` and the source under `source/`. Every included file has a SHA-256, size and mode; migrations are listed in committed order. Archive metadata and gzip timestamps are fixed for reproducibility.

Included source covers `api/`, `admin-panel/`, `frontend/` and `deploy/production/`. Environment files, dependencies, generated builds, runtime storage/cache/uploads (apart from directory `.gitignore` files), logs, backups and unrelated roots are excluded. Symlinks, submodules, unresolved LFS pointers, credential/database file patterns and detected private-key material are refused. The public Passport key fixture is explicitly permitted. These path/content checks are not a substitute for reviewing the repository for hardcoded secrets or sensitive data.

## Verify before transfer or use

Use the digest from the separately trusted build record, not a digest supplied alongside an untrusted replacement archive. Replace the value below with that recorded digest; leaving the placeholder causes refusal.

```bash
release_sha256='COPY_SHA256_FROM_TRUSTED_BUILD_RECORD'
python3 deploy/production/release_package.py verify \
  --archive "../release-artifacts/source-$release_commit.tar.gz" \
  --commit "$release_commit" \
  --sha256 "$release_sha256"
```

Verification reads without extraction. It rejects changed digests, mismatched commits/targets, unsupported paths or member types, duplicate/missing/unexpected members, appended archive members, and file hash/mode/size discrepancies. A digest establishes integrity relative to the trusted record; it does not independently authenticate whoever provided that record.

## What still must happen on the approved host

Follow LAUNCH.md after authenticated host identity/access is verified. Confirm the live folders and service ownership; preserve existing secrets, encryption/Passport keys, private records and host configuration. A source package carries none of those runtime values or production backups. Do not unpack over live application/data folders or treat this archive as a rollback snapshot.

Build locked dependencies and production-origin assets for the actual Linux runtime in an approved private release location. Local macOS/localhost builds are not included. Include Next standalone static/public assets and validate all three real origins. Rehearse forward migrations on an isolated approved copy, verify recoverable database/private-file/key backups, then follow controlled cutover and coordinated rollback. No new domains or ports are authorized by this tool.

Pharmacy remains synthetic-only. Source integrity and CI do not establish operational dispensing, professional acceptance, external service delivery or production readiness.
