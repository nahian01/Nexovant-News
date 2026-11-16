# Nexovant — Stage 13.1 Operations & Observability Runbook

## 1. System Services & Background Daemons

Nexovant's automated surveillance and early-warning engine relies on two daemon processes in production:

1. **Laravel Scheduler Daemon**:
   ```bash
   php artisan schedule:work
   ```
   Or system cron:
   ```cron
   * * * * * cd /path/to/nexovant-backend && php artisan schedule:run >> /dev/null 2>&1
   ```

2. **Laravel Queue Worker Daemon**:
   ```bash
   php artisan queue:work --tries=3 --timeout=120 --sleep=3 --max-jobs=1000 --memory=512
   ```

---

## 2. Operational Health Verification

### 1. Scheduler Heartbeat Check
Administrators can inspect live heartbeat telemetry via the Control Room (`/admin/automation` -> Tab 5: "Scheduler & Queue Telemetry") or via the API:
```bash
curl -H "Authorization: Bearer <ADMIN_TOKEN>" http://localhost:8000/api/v1/admin/automation-control/telemetry
```

Expected JSON response:
```json
{
  "success": true,
  "data": {
    "heartbeat": {
      "status": "healthy",
      "last_monitor_eval_at": "2026-11-16T03:17:18.000000Z",
      "last_saved_search_eval_at": "2026-11-16T03:17:07.000000Z",
      "last_duration_ms": 1305
    },
    "queue": {
      "status": "healthy",
      "pending_jobs": 0,
      "failed_jobs": 0,
      "executions_24h": 2,
      "avg_duration_ms": 1413
    }
  }
}
```

### 2. Manual Command Invocations
If the scheduler is delayed, operations can invoke commands manually:
```bash
# Evaluate intelligence monitors (default lookback 24 hours)
php artisan intelligence:evaluate-monitors --hours=24

# Evaluate subscriber saved searches (daily cadence)
php artisan discovery:evaluate-saved-searches --cadence=daily
```

---

## 3. Incident Troubleshooting

### Issue: Command shows "already running. Skipping execution"
- **Cause**: An earlier run crashed without releasing its cache lock, or a long-running batch is still active.
- **Resolution**:
  1. Verify if an active process exists: `ps aux | grep evaluate-monitors`
  2. If process terminated, release the lock key:
     ```bash
     php artisan cache:forget command:lock:intelligence:evaluate-monitors
     ```

### Issue: Failed Queue Jobs Accumulating
- **Cause**: Persistent third-party API timeout, database lock wait, or malformed article data.
- **Resolution**:
  1. Inspect failed jobs table:
     ```bash
     php artisan queue:failed
     ```
  2. Retry specific failed job:
     ```bash
     php artisan queue:retry <JOB_UUID>
     ```
  3. Flush permanent poison-pill jobs:
     ```bash
     php artisan queue:forget <JOB_UUID>
     ```
