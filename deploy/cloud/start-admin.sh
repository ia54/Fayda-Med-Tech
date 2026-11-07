#!/bin/bash
set -euo pipefail
source /workspace/.cloud-tools/env.sh
cd /workspace/Fayda-Med-Tech/admin-panel
export NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api
export NEXT_PUBLIC_API_URL=http://127.0.0.1:8000
exec corepack pnpm dev --hostname 127.0.0.1
