#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# NEXOVANT PRODUCTION DEPLOYMENT SCRIPT
# Zero-downtime rolling release for Hostinger VPS pilot
# ==============================================================================

echo "=== [Nexovant Deploy] Starting production deployment ==="

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INFRA_DIR="$(dirname "$SCRIPT_DIR")"
ROOT_DIR="$(dirname "$INFRA_DIR")"

cd "$INFRA_DIR/compose"

# 1. Pull latest code from main branch
echo "--> Verifying clean Git status..."
git -C "$ROOT_DIR" fetch origin main
git -C "$ROOT_DIR" reset --hard origin/main

# 2. Build and restart containers
echo "--> Rebuilding modified Docker services..."
docker compose build --no-cache backend ai-engine

echo "--> Bringing up database and dependencies..."
docker compose up -d db
docker compose exec -T db pg_isready -U "${DB_USERNAME:-nexovant_app}" -d "${DB_DATABASE:-nexovant_production}"

# 3. Run database migrations safely
echo "--> Running PostgreSQL database migrations..."
docker compose run --rm backend php artisan migrate --force

# 4. Cache Laravel configuration, routes and events
echo "--> Optimizing Laravel caches..."
docker compose exec -T backend php artisan config:cache
docker compose exec -T backend php artisan route:cache
docker compose exec -T backend php artisan view:cache
docker compose exec -T backend php artisan event:cache

# 5. Gracefully restart queue workers to reload new code
echo "--> Restarting Laravel queue worker..."
docker compose exec -T backend php artisan queue:restart

# 6. Restart Nginx and FastAPI
echo "--> Rolling reload of application services..."
docker compose up -d --remove-orphans backend queue-worker scheduler ai-engine nginx

# 7. Execute Post-Deployment Health Check
echo "--> Running post-deployment health verification..."
bash "$SCRIPT_DIR/health_check.sh"

echo "=== [Nexovant Deploy] Deployment completed successfully! ==="
