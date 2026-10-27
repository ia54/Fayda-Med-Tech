#!/bin/bash
# Synthetic cloud development only. Never import production configuration.
set -euo pipefail
docker() { env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH /usr/local/bin/docker --host=unix:///var/run/docker.sock "$@"; }
repo=/workspace/Fayda-Med-Tech
tools=/workspace/.cloud-tools
mkdir -p "$tools/downloads"
if [ ! -x "$tools/node-v22.23.3-linux-x64/bin/node" ]; then
  curl -fsS https://nodejs.org/dist/v22.23.3/SHASUMS256.txt -o "$tools/downloads/SHASUMS256.txt"
  curl -fsS https://nodejs.org/dist/v22.23.3/node-v22.23.3-linux-x64.tar.xz -o "$tools/downloads/node-v22.23.3-linux-x64.tar.xz"
  (cd "$tools/downloads"; rg ' node-v22.23.3-linux-x64.tar.xz$' SHASUMS256.txt | sha256sum -c)
  tar -xf "$tools/downloads/node-v22.23.3-linux-x64.tar.xz" -C "$tools"
fi
cat > "$tools/env.sh" <<'FAYDAMED_FILE_EOF'
export PATH=/workspace/.cloud-tools/node-v22.23.3-linux-x64/bin:$PATH
export COREPACK_HOME=/workspace/.cloud-tools/corepack
export NODE_USE_ENV_PROXY=1
export NEXT_TELEMETRY_DISABLED=1
export npm_config_cache=/workspace/.npm-cache
export XDG_DATA_HOME=/workspace/.cloud-tools/data
export XDG_CACHE_HOME=/workspace/.cloud-tools/cache
export XDG_CONFIG_HOME=/workspace/.cloud-tools/config
export PNPM_HOME=/workspace/.cloud-tools/pnpm
export PATH=/workspace/.cloud-tools:$PATH
export FAYDAMED_PLAYWRIGHT_MODULE=/workspace/.cloud-tools/browser-tools/node_modules/playwright-core
FAYDAMED_FILE_EOF
cat > "$tools/php" <<'FAYDAMED_FILE_EOF'
#!/bin/bash
set -euo pipefail
exec env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH /usr/local/bin/docker --host=unix:///var/run/docker.sock run --rm --network none --user "$(id -u):$(id -g)" -v /workspace:/workspace -e APP_ENV -e DB_CONNECTION -e DB_DATABASE -e FAYDAMED_BROWSER_FIXTURE -e FAYDAMED_BROWSER_FIXTURE_DIR -w "$PWD" faydamed-recovery-php83 php "$@"
FAYDAMED_FILE_EOF
cat > "$tools/composer" <<'FAYDAMED_FILE_EOF'
#!/bin/bash
set -euo pipefail
exec env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH /usr/local/bin/docker --host=unix:///var/run/docker.sock run --rm --user "$(id -u):$(id -g)" -v /workspace:/workspace -v /etc/ssl/certs/ca-certificates.crt:/run/ca.pem:ro -e GIT_SSL_CAINFO=/run/ca.pem -e COMPOSER_CAFILE=/run/ca.pem -e COMPOSER_HOME=/workspace/.cloud-tools/composer-home -e COMPOSER_CACHE_DIR=/workspace/.cloud-tools/composer-cache -w "$PWD" faydamed-recovery-php83 composer "$@"
FAYDAMED_FILE_EOF
cat > "$tools/Dockerfile.php" <<'FAYDAMED_FILE_EOF'
FROM composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc AS composer
FROM php:8.3-cli-bookworm@sha256:cdb77e306d6bb99db40e513fbe036ecc6c53b575cbcb7e581387845fef26d755
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN --mount=type=secret,id=ca,target=/run/ca.pem,required=true \
    apt-get -o Acquire::https::CaInfo=/run/ca.pem update && \
    apt-get -o Acquire::https::CaInfo=/run/ca.pem install -y --no-install-recommends libsqlite3-dev libicu-dev libzip-dev libonig-dev libxml2-dev unzip git && \
    docker-php-ext-install -j4 bcmath intl mbstring pdo_sqlite zip dom && rm -rf /var/lib/apt/lists/*
WORKDIR /workspace/Fayda-Med-Tech/api
FAYDAMED_FILE_EOF
cat > "$tools/router.php" <<'FAYDAMED_FILE_EOF'
<?php
// Disposable cloud-only HTTP kernel; no provider credentials or network operations.
require '/workspace/Fayda-Med-Tech/api/vendor/autoload.php';
$app = require '/workspace/Fayda-Med-Tech/api/bootstrap/app.php';
$app->useStoragePath(getenv('FAYDAMED_BROWSER_FIXTURE_DIR').'/storage');
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
Illuminate\Support\Facades\Http::preventStrayRequests();
$response = $kernel->handle($request = Illuminate\Http\Request::capture());
$response->send();
$kernel->terminate($request, $response);
FAYDAMED_FILE_EOF
chmod +x "$tools/php" "$tools/composer"
source "$tools/env.sh"
env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH docker --host=unix:///var/run/docker.sock info >/dev/null
BUILDX_CONFIG="$tools/buildx" docker build --secret id=ca,src=/etc/ssl/certs/ca-certificates.crt -f "$tools/Dockerfile.php" -t faydamed-recovery-php83 "$tools"
cd "$repo/admin-panel"
corepack pnpm install --frozen-lockfile
cd "$repo/frontend"
npm ci --no-audit --no-fund
cd "$repo/api"
# Git source is a supported alternative while archive API egress is unavailable.
composer install --prefer-source --no-interaction --no-progress --no-scripts --no-plugins
composer check-platform-reqs
mkdir -p "$tools/browser-tools"
npm install --prefix "$tools/browser-tools" --no-audit --no-fund playwright-core@1.56.1
command -v chromium
