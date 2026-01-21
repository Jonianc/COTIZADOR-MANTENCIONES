#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
docker compose up -d

echo "WordPress: http://localhost:8080"
echo "DB: mariadb (internal)"
