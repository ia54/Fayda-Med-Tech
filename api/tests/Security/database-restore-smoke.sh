#!/usr/bin/env bash
# Disposable CI only. Never use this script to restore an operational database.
set -euo pipefail
if [[ "${GITHUB_ACTIONS:-}" != true || "${APP_ENV:-}" != testing || "${DB_CONNECTION:-}" != mysql || "${DB_HOST:-}" != 127.0.0.1 || "${DB_DATABASE:-}" != faydamed_ci || ! "${FAYDAMED_CI_DB_CONTAINER:-}" =~ ^[a-f0-9]{12,64}$ ]]; then
  echo 'Refusing restore rehearsal outside the disposable CI database.' >&2
  exit 1
fi
umask 077
recovery_dir=$(mktemp -d)
trap 'rm -rf "$recovery_dir"' EXIT
# Use each service image's own client so dumps match its database engine.
client=$(docker exec "$FAYDAMED_CI_DB_CONTAINER" sh -c 'if command -v mariadb >/dev/null 2>&1; then echo mariadb; else echo mysql; fi')
if [[ "$client" == mariadb ]]; then
  dump_client=mariadb-dump
  dump_options=()
else
  dump_client=mysqldump
  dump_options=(--set-gtid-purged=OFF --column-statistics=0)
fi
# Refuse an existing target, including one left by another process.
printf 'CREATE DATABASE faydamed_ci_restore;\n' | docker exec -i -e MYSQL_PWD="$DB_PASSWORD" "$FAYDAMED_CI_DB_CONTAINER" "$client" -uroot
php tests/Security/database-restore-verify.php snapshot "$recovery_dir/expected.json"
docker exec -e MYSQL_PWD="$DB_PASSWORD" "$FAYDAMED_CI_DB_CONTAINER" "$dump_client" -uroot --single-transaction --quick --hex-blob --no-tablespaces "${dump_options[@]}" faydamed_ci > "$recovery_dir/database.sql"
test -s "$recovery_dir/database.sql"
docker exec -i -e MYSQL_PWD="$DB_PASSWORD" "$FAYDAMED_CI_DB_CONTAINER" "$client" -uroot faydamed_ci_restore < "$recovery_dir/database.sql"
php tests/Security/database-restore-verify.php verify "$recovery_dir/expected.json"
# Exercise the matching private-file archive independently of public web roots.
mkdir -p "$recovery_dir/private/documents" "$recovery_dir/restored"
printf 'SYNTHETIC CI ONLY: private recovery document\n' > "$recovery_dir/private/documents/recovery.txt"
tar -C "$recovery_dir/private" -czf "$recovery_dir/private-files.tar.gz" .
tar -C "$recovery_dir/restored" -xzf "$recovery_dir/private-files.tar.gz"
cmp "$recovery_dir/private/documents/recovery.txt" "$recovery_dir/restored/documents/recovery.txt"
printf 'DROP DATABASE faydamed_ci_restore;\n' | docker exec -i -e MYSQL_PWD="$DB_PASSWORD" "$FAYDAMED_CI_DB_CONTAINER" "$client" -uroot
echo 'PASS: synthetic database restored into a separate target and private-file archive restored byte-for-byte.'
