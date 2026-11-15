# NEXOVANT — STAGE 11 API INTEGRATION REPORT
## Centralized Communication & Notification Intelligence Engine

**Document Version:** 1.0.0  
**Phase:** Stage 11  
**Project:** Nexovant — Intelligence Beyond Headlines  
**Author:** Principal Platform Architect  
**Date:** November 16, 2026  
**Status:** Complete & Verified  

---

## 1. Executive Summary

Stage 11 establishes a unified, event-driven communication fabric that consolidates notifications across all Nexovant subsystems (Ingestion, Verification, RAG Knowledge, Executive Publications, Personalized Workspace, Billing, and Enterprise Organizations).

This report specifies the complete subscriber and administrator API contracts, payload schemas, query parameters, authentication guards, status codes, and idempotency mechanisms implemented in `nexovant-backend` and consumed by `nexovant-frontend`.

---

## 2. Subscriber Workspace API Endpoints

All subscriber notification routes require authenticated Bearer token authentication via Laravel Sanctum (`auth:sanctum`).

### 2.1 List Notifications
- **Method / URI:** `GET /api/v1/workspace/notifications` (and alias `GET /api/v1/notifications`)
- **Query Parameters:**
  - `category` (optional, string): Filter by category (`all`, `breaking`, `trend_alert`, `publication`, `system`, `billing`, `editorial`).
  - `priority` (optional, string): Filter by priority (`all`, `urgent`, `high`, `medium`, `low`).
  - `is_read` (optional, string): `all`, `true`, `false`.
  - `search` (optional, string): Full-text substring search across `title` and `message`.
  - `per_page` (optional, int, default: 25).
- **Response `200 OK`:**
```json
{
  "success": true,
  "notifications": {
    "current_page": 1,
    "data": [
      {
        "id": "01a1e127-2b57-70ff-bd64-4adc66bd1fe1",
        "category": "breaking",
        "priority": "urgent",
        "source_module": "intelligence",
        "title": "NVIDIA Board Approves $15B EMEA Silicon Packaging Facility",
        "message": "NVIDIA Corporation finalized sovereign compute infrastructure investment in Germany and France.",
        "link": "/workspace",
        "delivery_channel": "multi_channel",
        "delivery_status": "sent",
        "is_read": false,
        "read_at": null,
        "created_at": "2026-11-16T03:40:03.000000Z",
        "metadata": {
          "ticker": "NVDA",
          "capex": "$15B",
          "channels_routed": ["in_app", "broadcast", "email", "web_push"]
        }
      }
    ],
    "total": 3
  },
  "unread_count": 3,
  "categories": ["all", "breaking", "trend_alert", "publication", "system", "billing", "editorial"],
  "priorities": ["all", "urgent", "high", "medium", "low"]
}
```

### 2.2 Mark Single Notification Read
- **Method / URI:** `POST /api/v1/workspace/notifications/{id}/read` (and `PATCH /api/v1/notifications/{id}/read`)
- **Response `200 OK`:**
```json
{
  "success": true,
  "notification": {
    "id": "01a1e127-2b57-70ff-bd64-4adc66bd1fe1",
    "is_read": true,
    "read_at": "2026-11-16T03:42:00.000000Z"
  }
}
```

### 2.3 Mark All Read
- **Method / URI:** `POST /api/v1/workspace/notifications/mark-all-read` (and `POST /api/v1/notifications/mark-all-read`)
- **Payload:** `{ "category": "breaking" }` (optional)
- **Response `200 OK`:**
```json
{
  "success": true,
  "message": "Marked 3 notifications as read.",
  "updated_count": 3
}
```

### 2.4 Notification Preferences & Quiet Hours
- **Method / URI:** `GET|PUT /api/v1/workspace/notifications/preferences` (and `/api/v1/notifications/preferences`)
- **PUT Payload:**
```json
{
  "channels": {
    "in_app": true,
    "email": true,
    "web_push": true,
    "telegram": false,
    "whatsapp": false
  },
  "category_settings": {
    "breaking": true,
    "trend_alert": true,
    "publication": true,
    "billing": true,
    "system": true
  },
  "digest_frequency": "daily",
  "quiet_hours_start": "22:00",
  "quiet_hours_end": "07:00",
  "timezone": "UTC"
}
```
- **Response `200 OK`:**
```json
{
  "success": true,
  "message": "Notification settings updated.",
  "preferences": {
    "user_id": "01a1e098-1f80-706d-a624-6d58cbf858d5",
    "digest_frequency": "daily",
    "quiet_hours_start": "22:00",
    "quiet_hours_end": "07:00",
    "timezone": "UTC"
  }
}
```

### 2.5 Web Push Endpoints
- `GET /api/v1/notifications/push/vapid-key`: Returns `{ "success": true, "public_key": "BEl62iUY..." }`.
- `POST /api/v1/notifications/push/subscribe`: Registers device subscription. Returns `201 Created`.
- `POST /api/v1/notifications/push/unsubscribe`: Revokes device endpoint. Returns `200 OK`.

### 2.6 Telegram Bot Integration Endpoints
- `POST /api/v1/notifications/telegram/link-token`: Generates secure 15-minute token. Returns `{ "token": "...", "bot_username": "NexovantIntelBot", "deep_link": "https://t.me/NexovantIntelBot?start=..." }`.
- `POST /api/v1/notifications/telegram/verify`: Verifies token and binds `telegram_chat_id`.
- `POST /api/v1/notifications/telegram/disconnect`: Clears `telegram_chat_id`.
- `POST /api/v1/notifications/telegram/test`: Sends immediate verified test greeting.

---

## 3. Administrator Control Center Endpoints

Protected by `admin` middleware verifying `user->isAdmin()` and `user->is_active`.

### 3.1 Platform Telemetry Metrics
- **Method / URI:** `GET /api/v1/admin/notifications/metrics`
- **Response `200 OK`:**
```json
{
  "success": true,
  "metrics": {
    "total_24h": 3,
    "unread_total": 2,
    "delivered_24h": 6,
    "failed_24h": 0,
    "delivery_rate": 100.0,
    "channel_breakdown": {
      "in_app": 3,
      "broadcast": 3
    },
    "priority_breakdown": {
      "urgent": 1,
      "high": 2
    },
    "active_rules_count": 9
  }
}
```

### 3.2 Granular Delivery Audit Logs
- **Method / URI:** `GET /api/v1/admin/notifications/logs`
- **Query Parameters:** `channel`, `status`, `search`, `per_page`.
- **Response `200 OK`:** Lists per-attempt delivery logs with `attempt_count`, `sent_at`, and `error_message`.

### 3.3 Authoritative Broadcast Announcement
- **Method / URI:** `POST /api/v1/admin/notifications/broadcast`
- **Payload:**
```json
{
  "target_audience": "all",
  "category": "system",
  "priority": "high",
  "title": "Scheduled Maintenance Window Notice",
  "message": "Platform maintenance scheduled for Saturday 02:00 UTC.",
  "preview": false
}
```
- **Response `201 Created`:**
```json
{
  "success": true,
  "message": "Broadcast announcement dispatched to 3 recipient(s).",
  "dispatched_count": 3
}
```

### 3.4 Notification Rules Engine Management
- `GET /api/v1/admin/notifications/rules`: Lists all system event rules.
- `PUT /api/v1/admin/notifications/rules/{id}`: Updates rule priority, active channels, frequency, and active state.
