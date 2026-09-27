# Linux web-asset candidates

The `Production-origin web build` workflow builds the admin and public website from the exact PR head or pushed commit, using locked dependencies in Linux x64 / Node 22 / Debian Bookworm. It supplies only the three approved public origins. It never connects to the production host, starts a listener, modifies DNS or changes production data. API PHP dependencies, production secrets, private documents, database data and backups are not included.

The candidate contains:

- `admin/server.js`, traced standalone dependencies, `.next/static` and `public` assets;
- the public website's compiled `public/index.html` and assets;
- `WEB-ASSET-MANIFEST.json` with commit/tree, Node/glibc/architecture, lockfile hashes, Next build identity, origin-check results and every included file's hash/mode or contained relative symlink target.

Next standalone output needs its static/public directories added explicitly, as described in the [official Next documentation](https://nextjs.org/docs/app/api-reference/config/next-config-js/output). The package assembles those directories rather than relying on the development `next start` command.

Five packaging tests check exact-byte inventories, link containment, forbidden runtime/private-key files and expected/misconfigured application origins. After builds, the packager rejects a tracked dirty tree, unexpected application environment files (the committed `.env.example` templates are allowed but not packaged), absent build outputs, missing intended API URLs in served assets, development API origins, escaping/dangling links, runtime/database/credential paths and detected private-key material. These checks are bounded build checks, not a comprehensive secret scanner or runtime acceptance test.

Only successful candidates are uploaded to the workflow run. The archive, `BUILD-RECORD.json` and a separate manifest are retained for 14 days. The build record contains the archive SHA-256. Keep that digest in the trusted release record and match it before use. The [pinned upload action](https://github.com/actions/upload-artifact/releases/tag/v4.6.2) packages the archive as a workflow artifact; the inner archive preserves file modes and links. Artifacts can expire and must be rebuilt from the intended reviewed commit if no longer available.

Every candidate declares `production_ready: false`. Verify authenticated host identity, architecture, Node/glibc compatibility, service ownership, existing application paths, backups/isolated restore, database migrations and actual HTTPS acceptance before cutover. If the host differs from the build platform, build on its approved matching runtime instead. Preserve the authorized admin listener on localhost port 3005; this workflow does not start it. Do not copy a candidate over a live tree or use it as a rollback backup. Follow LAUNCH.md and preserve runtime keys/configuration/private records separately.

The workflow checks build contents; it does not prove the app can reach the production API or that the backend, external providers or pharmacy operating requirements are ready. Pharmacy endpoints remain synthetic-only, and controlled/compounded dispensing remains blocked.
