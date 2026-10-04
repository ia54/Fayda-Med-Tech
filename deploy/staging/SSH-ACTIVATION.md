# Authorized private staging activation

The owner approved a separate synthetic database and localhost-only ports on 23 September 2026. Activation is pending: the latest root SSH attempt to the confirmed host was rejected. These files have not been installed or started on the server. No other host is authorized.

## Intended map

| Copy | Prepared root | Remote and forwarded local port |
| --- | --- | --- |
| API | /home/api.faydamed.tech/staging/api | 127.0.0.1:18080 |
| Admin | /home/admin.faydamed.tech/staging/admin-panel | 127.0.0.1:13005 |
| Website | /home/faydamed.tech/staging/frontend | 127.0.0.1:18081 |

There are no DNS changes. Do not replace the production admin on port 3005 or edit production vhosts. Templates in services/ use a dedicated fmd-stage system account, memory/task limits, read-only system paths, restricted write paths and loopback-only IP access. Validate these directives on the host before starting anything. The PHP built-in servers are intended solely for limited synthetic staging acceptance, not production or load testing.

## Activation sequence after SSH access is restored

1. Recheck the three prepared roots, intended source revision, free ports and absence of existing staging users/database/services. Snapshot any prior staging state before updating it; never overwrite a live .env or rerun initial-key generation on existing data.
2. Create fmd-stage as a non-login system account with no sudo and no production groups. Give it ownership only of the three staging trees. Do not change ownership of the live roots or install root SSH keys.
3. Create a new faydamed_staging database and a unique faydamed_staging@localhost database user with grants on that database only. Generate the password on the server; store it solely in the staging .env with mode 600. Verify grants and prove that this account cannot query the live database. Do not copy production data, accounts or stored integration credentials.
4. Transfer this kit to /home/api.faydamed.tech/staging/activation. Copy api.ssh.env.example to the staging API .env, fill a unique staging key and database password, and keep outbound mail on array and external integrations unconfigured. Before migration, explicitly connect as the staging account and verify DATABASE() and zero tables. Run migrations against that database only. Stop on any MariaDB incompatibility; do not alter production migrations or data to get around it.
5. Generate fresh staging Passport signing keys and one personal-access client. Keep its secret in the protected staging configuration. Create only synthetic organizations and accounts with fresh unique passwords, all six roles, and an unrelated organization for boundary tests. Require MFA; preserve test credentials outside public web roots and Git. Do not run legacy default/demo seeders.
6. Rebuild the admin with admin.ssh.env.example and the website with frontend.ssh.env.example. The website VITE_BASE_URL must omit /api because its client appends that path. Assemble admin standalone output with public and .next/static assets. Preserve the originals until the replacement build passes.
7. Run config:cache and staging:check --loopback. Confirm PHP platform requirements and private document paths. Install only the three named service units after systemd-analyze verify succeeds. Reload systemd unit metadata without restarting any existing service. Start these units and verify they listen only on the three loopback addresses and that service egress restrictions are effective.
8. Open an SSH tunnel with all three local bindings explicitly restricted to 127.0.0.1 and ExitOnForwardFailure=yes. Use a separate local browser origin from production; never browse a public-IP staging port. The API and frontends must agree on these port numbers.
9. Verify API login/MFA, token rotation/logout, uploads/private previews and cross-organization denial. Verify the actual browser flows for all six roles. Then test the case-to-billing-to-payment journey using synthetic records only. A compiled frontend or root HTTP 200 does not prove this journey.
10. Snapshot only staging database/storage and test restoration into a second isolated target before claiming recovery readiness. Record results and limitations. Leave production untouched.

The latest deployment restriction is private staging activation, superseding the earlier files-only restriction. SSH authentication is the current blocker. The existing SQLite 3.34.1 host limitation and frontend type-check backlog remain; no shared runtime upgrade is included.
