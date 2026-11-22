#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# One-Click Full Platform Deployment to Cloudflare
# ==============================================================================

PAGES_PROJECT="${1:-nexovant-frontend}"

echo "=== [Nexovant Cloudflare] Starting Platform Deployment ==="

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"
FRONTEND_DIR="$ROOT_DIR/nexovant-frontend"
WORKER_DIR="$ROOT_DIR/cloudflare-worker"

# 1. Build and Deploy Frontend
echo "--> [1/3] Building Vue 3 Frontend..."
cd "$FRONTEND_DIR"
npm run build

echo "--> [2/3] Deploying to Cloudflare Pages..."
npx wrangler pages deploy dist --project-name="$PAGES_PROJECT"

# 2. Deploy Edge Worker Gateway
echo "--> [3/3] Deploying Cloudflare Worker Gateway..."
cd "$WORKER_DIR"
npx wrangler deploy

echo "=== [Nexovant Cloudflare] Full deployment completed successfully! ==="
