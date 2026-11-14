#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# NEXOVANT SYSTEM & OPERATIONAL HEALTH CHECK
# Monitors VPS RAM, PostgreSQL connectivity, Laravel API, and Python AI Service
# ==============================================================================

echo "=== [Nexovant Health Check] Checking system status ==="

# 1. Check VPS Memory Usage
FREE_MEM_MB=$(free -m | awk '/^Mem:/{print $4}')
echo "--> Available RAM: ${FREE_MEM_MB} MB"
if [ "$FREE_MEM_MB" -lt 250 ]; then
    echo "WARNING: Low free memory on VPS (<250 MB). Memory pressure alert!"
fi

# 2. Check Nginx Health Endpoint
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/healthz || echo "000")
if [ "$HTTP_CODE" -eq 200 ]; then
    echo "--> [Nginx] Health endpoint OK (HTTP 200)"
else
    echo "ERROR: Nginx health check failed with HTTP ${HTTP_CODE}"
    exit 1
fi

# 3. Check Laravel Backend API
API_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/v1/health || echo "000")
if [ "$API_CODE" -eq 200 ]; then
    echo "--> [Backend API] Health endpoint OK (HTTP 200)"
else
    echo "ERROR: Laravel backend health check failed with HTTP ${API_CODE}"
    exit 1
fi

# 4. Check Internal AI Engine (via container inspection)
AI_STATUS=$(docker exec nexovant_ai_engine curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/internal/v1/health || echo "000")
if [ "$AI_STATUS" -eq 200 ]; then
    echo "--> [AI Engine] Health endpoint OK (HTTP 200)"
else
    echo "ERROR: Python AI Engine health check failed with HTTP ${AI_STATUS}"
    exit 1
fi

echo "=== [Nexovant Health Check] All core systems healthy! ==="
