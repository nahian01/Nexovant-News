# NEXOVANT — PHASE 7F PRODUCTION DEPLOYMENT RUNBOOK
## Operational Deployment, Infrastructure Configuration & Rollback Procedures

**Date:** 2026-11-16  
**Auditor:** Google Antigravity Production Reliability & Security Audit Engine  
**Target Systems:** `nexovant-backend` (Laravel 12 API) & `nexovant-frontend` (Vue 3 SPA)  
**Runbook Status:** **VERIFIED & PRODUCTION-READY**

---

## 1. System Prerequisites

The following infrastructure must be provisioned and verified prior to production deployment:

### 1.1 Server & Runtime Specifications
- **Operating System:** Linux (Ubuntu 22.04 LTS or 24.04 LTS recommended).
- **Web Server:** Nginx (with HTTP/2, TLS 1.3, and Gzip/Brotli compression).
- **PHP Runtime:** PHP 8.4+ with extensions:
  - `pdo_pgsql`, `mbstring`, `bcmath`, `xml`, `curl`, `zip`, `redis`, `gd`, `intl`, `pcntl`.
- **Database:** PostgreSQL 16+ with `pgvector` extension installed (`CREATE EXTENSION IF NOT EXISTS vector;`).
- **Cache & Queue Broker:** Redis 7.0+.
- **Node.js Environment:** Node.js 20.x or 22.x LTS (for building frontend assets in CI).

---

## 2. Environment Configuration Audit (`.env`)

Never commit `.env` to git. Ensure production secrets are supplied securely via AWS Secrets Manager, HashiCorp Vault, or deployment pipeline secrets.

### 2.1 Essential Production Environment Variables

```ini
APP_NAME=Nexovant
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nexovant.com
FRONTEND_URL=https://app.nexovant.com
APP_KEY=base64:GENERATE_HIGH_ENTROPY_KEY_HERE

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nexovant_production
DB_USERNAME=nexovant_app
DB_PASSWORD=STRONG_DB_PASSWORD_HERE

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=STRONG_REDIS_PASSWORD_HERE
REDIS_PORT=6379

# AI Provider Credentials (Store master fallback or register in Admin Panel)
GEMINI_API_KEY=ENCRYPTED_OR_ENV_KEY
ANTHROPIC_API_KEY=ENCRYPTED_OR_ENV_KEY
OPENAI_API_KEY=ENCRYPTED_OR_ENV_KEY

# Payment Webhook Secrets (Must be set; fail-closed if omitted)
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
RAZORPAY_KEY=rzp_live_...
RAZORPAY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...
BKASH_APP_KEY=...
BKASH_APP_SECRET=...
NAGAD_MERCHANT_ID=...

# Seeding & Administrative Setup
SEED_ADMIN_PASSWORD=MANDATORY_HIGH_ENTROPY_PASSWORD_IF_RUNNING_SEED
```

---

## 3. Step-by-Step Deployment Procedure

### Step 1: Deploy Backend Application
```bash
cd /var/www/nexovant/nexovant-backend

# 1. Enable Maintenance Mode
php artisan down --render="errors::503" --secret="deployment-bypass-key"

# 2. Pull approved release tag
git fetch origin --tags
git checkout tags/v1.0.0

# 3. Install production PHP dependencies (strictly from lockfile)
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Run database migrations safely
php artisan migrate --force

# 5. Optimize configurations, routes, and views for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Restart Queue Workers and Scheduled Daemons
php artisan queue:restart
```

### Step 2: Build & Deploy Frontend Application
```bash
cd /var/www/nexovant/nexovant-frontend

# 1. Install dependencies strictly from lockfile
npm ci

# 2. Compile optimized production bundles
npm run build

# 3. Deploy build artifacts to webroot
rsync -av --delete dist/ /var/www/html/nexovant-frontend/
```

### Step 3: Bring Application Online & Verify Health
```bash
cd /var/www/nexovant/nexovant-backend

# Disable maintenance mode
php artisan up

# Smoke test health endpoint
curl -f -s https://nexovant.com/api/v1/health | grep '"status":"healthy"'
```

---

## 4. Background Services & Cron Daemon Configuration

### 4.1 Systemd Queue Worker (`/etc/systemd/system/nexovant-queue.service`)
```ini
[Unit]
Description=Nexovant Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/nexovant/nexovant-backend/artisan queue:work redis --queue=high,default,ingestion --sleep=3 --tries=3 --timeout=120
RestartSec=5s

[Install]
WantedBy=multi-user.target
```
Enable and start:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nexovant-queue
```

### 4.2 Crontab Configuration
Add to `www-data` user crontab (`crontab -e -u www-data`):
```cron
* * * * * cd /var/www/nexovant/nexovant-backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Rollback & Emergency Recovery Plan

If a critical Sev 1/2 regression, data corruption, or payment anomaly occurs during or immediately after deployment:

### 5.1 Immediate Traffic Mitigation
```bash
cd /var/www/nexovant/nexovant-backend
php artisan down --render="errors::503"
```

### 5.2 Rollback Code & Cache
```bash
# 1. Checkout previous known-good release tag
git checkout tags/v0.9.9

# 2. Re-install corresponding dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# 3. If a migration needs rolling back (only if safe and reversible):
php artisan migrate:rollback --step=1

# 4. Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Restart queue workers
php artisan queue:restart

# 6. Restore previous frontend build
rsync -av --delete /var/www/backups/frontend-v0.9.9/ /var/www/html/nexovant-frontend/

# 7. Bring application back online
php artisan up
```

### 5.3 Post-Rollback Validation
1. Verify `/api/v1/health` returns HTTP 200 `healthy`.
2. Verify `/api/v1/auth/login` and `/admin/login` function cleanly.
3. Monitor application logs in `storage/logs/laravel.log`.

---

## 6. Post-Deployment Monitoring & Observability

Monitor the following key metrics for 60 minutes post-release:
1. **HTTP Error Rate:** 5xx responses must be < 0.05% of total requests.
2. **API P95 Latency:** Latency for public endpoints < 150 ms.
3. **Queue Health:** `failed_jobs` count remains 0.
4. **AI Router Spend:** Track spend velocity in `/admin/ai-router`.
5. **Subscription & Payment Processing:** Confirm webhook deliveries return HTTP 200.
