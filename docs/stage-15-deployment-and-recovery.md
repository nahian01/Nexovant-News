# Stage 15 — Deployment, Migrations & Disaster Recovery Runbook

## 1. Migration Runbook

Migration File: `database/migrations/0001_01_01_000109_create_stage15_executive_decision_support_tables.php`

### Deploy Steps:
```bash
# 1. Pull latest code from dev branch
git checkout dev
git pull origin dev

# 2. Run backend migrations
cd nexovant-backend
php artisan migrate --force

# 3. Clear and warm configuration/route caches
php artisan config:cache
php artisan route:cache

# 4. Build and deploy frontend assets
cd ../nexovant-frontend
npm ci
npm run build
```

### Rollback Plan:
If deployment must be reverted:
```bash
cd nexovant-backend
# Safely drops all 11 Stage 15 tables in foreign-key reverse order
php artisan migrate:rollback --step=1
```
*Note: Tested and verified during Stage 15 development; rollback executes cleanly without orphaned records.*

---

## 2. Disaster Recovery & Data Protection

- **Point-in-Time Recovery**: SQLite/PostgreSQL daily snapshots and WAL archiving.
- **Organization Isolation**: Every table contains `organization_id`; organization data can be exported to JSON/CSV for tenant backup requests.
- **Audit Immutability**: `intelligence_decision_reviews` and `audit_logs` are insert-only tables with no deletion or editing routes exposed.
