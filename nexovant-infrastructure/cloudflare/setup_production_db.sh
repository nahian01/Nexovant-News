#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# Automated PostgreSQL Database Setup, Migration & Cloudflare Hyperdrive Connector
# ==============================================================================

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-nexovant_production}"
DB_USERNAME="${DB_USERNAME:-nexovant_app}"
DB_PASSWORD="${DB_PASSWORD:-nexovant_secret}"
RUN_SEED="${1:-false}"

echo "=== [Nexovant DB] Initializing PostgreSQL Production Database ==="

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$SCRIPT_DIR/../../nexovant-backend"

cd "$BACKEND_DIR"

export DB_CONNECTION=pgsql
export DB_HOST="$DB_HOST"
export DB_PORT="$DB_PORT"
export DB_DATABASE="$DB_DATABASE"
export DB_USERNAME="$DB_USERNAME"
export DB_PASSWORD="$DB_PASSWORD"

echo "--> Running migrations..."
php artisan migrate --force

if [ "$RUN_SEED" = "true" ] || [ "$RUN_SEED" = "--seed" ]; then
    echo "--> Running seeders..."
    php artisan db:seed --force
fi

echo "--> Caching configuration..."
php artisan config:cache
php artisan route:cache

echo "=== [Nexovant DB] Database migration completed successfully! ==="
echo ""
echo "=== Cloudflare Hyperdrive Setup ==="
echo "Create a pooled edge connection with:"
echo "npx wrangler hyperdrive create nexovant-db --connection-string=\"postgresql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}?sslmode=require\""
