#!/bin/bash
# Fresh, disposable synthetic data only. No provider or production credentials.
set -euo pipefail
docker() { env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH /usr/local/bin/docker --host=unix:///var/run/docker.sock "$@"; }
source /workspace/.cloud-tools/env.sh
cd /workspace/Fayda-Med-Tech/api
fixture=$(realpath -m "${1:?Pass a NEW directory below /workspace/.cloud-tools/}")
case "${2:-ordinary}" in
 ordinary) fixture_class=CloudOrdinaryFixtureTest; seed_filter=test_seed_cloud_ordinary_browser_fixture; database_name=ordinary.sqlite ;;
 shared) fixture_class=CloudSharedRoleFixtureTest; seed_filter=test_seed_cloud_shared_role_browser_fixture; database_name=shared.sqlite ;;
 *) echo 'Supported fixtures: ordinary or shared' >&2; exit 1 ;;
esac
case "$fixture" in /workspace/.cloud-tools/*) ;; *) echo 'Fixture must stay under .cloud-tools' >&2; exit 1;; esac
[ ! -e "$fixture" ] || { echo 'Refusing to overwrite an existing fixture' >&2; exit 1; }
if curl --max-time 2 -sS http://127.0.0.1:8000/api/profile >/dev/null 2>&1; then echo 'Port 8000 is occupied; inspect and stop only your own server' >&2; exit 1; fi
umask 077
mkdir -p "$fixture"
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs
if [ ! -f storage/oauth-private.key ] && [ ! -f storage/oauth-public.key ]; then
 openssl genrsa -out storage/oauth-private.key 2048
 openssl rsa -in storage/oauth-private.key -pubout -out storage/oauth-public.key 2>/dev/null
 sha256sum storage/oauth-public.key | cut -d ' ' -f 1 > /workspace/.cloud-tools/synthetic-passport-public.sha256
fi
# Existing keys must already match the synthetic provenance; never adopt unknown keys.
[ -f /workspace/.cloud-tools/synthetic-passport-public.sha256 ]
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: FAYDAMED_BROWSER_FIXTURE=1 FAYDAMED_BROWSER_FIXTURE_DIR="$fixture" php vendor/bin/phpunit --colors=never --filter "$seed_filter" "tests/Browser/$fixture_class.php"
mkdir -p "$fixture/storage/framework/"{cache/data,sessions,views} "$fixture/storage/logs" "$fixture/storage/app/private/documents"
cp storage/oauth-private.key storage/oauth-public.key "$fixture/storage/"
name="faydamed-synthetic-$(date +%s)"
docker run --rm -d --name "$name" --user "$(id -u):$(id -g)" --network bridge -p 127.0.0.1:8000:8000 \
 -v /workspace:/workspace -w /workspace/Fayda-Med-Tech/api \
 -e APP_ENV=testing -e APP_DEBUG=false -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
 -e DB_CONNECTION=sqlite -e DB_DATABASE="$fixture/$database_name" \
 -e CACHE_DRIVER=array -e SESSION_DRIVER=array -e MAIL_MAILER=array -e QUEUE_CONNECTION=sync \
 -e FAYDAMED_BROWSER_FIXTURE_DIR="$fixture" \
 faydamed-recovery-php83 php -S 0.0.0.0:8000 /workspace/.cloud-tools/router.php >/dev/null
printf '%s\n' "$name" > "$fixture/container-name"
# Only the synthetic API is published, on loopback. No provider credentials are passed.
for attempt in $(seq 1 30); do
 status=$(curl --max-time 2 -sS -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/api/profile || true)
 if [ "$status" = 401 ]; then printf 'Synthetic API ready; fixture: %s\n' "$fixture"; exit 0; fi
 sleep 1
done
echo 'API readiness failed; inspect docker logs for the named container' >&2
exit 1
