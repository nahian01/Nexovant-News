# Nexovant Stage 13 — Notification Engine Integration
## Centralized Dispatch, Multi-Channel Routing & Idempotency

**Document Version:** 1.0.0  
**Status:** Production Ready  

---

## 1. Overview

Stage 13 does not build isolated alerting or notification silos. All automated early warnings and critical signals route directly through the centralized Stage 11 `NotificationEngine`.

---

## 2. Event Dispatch Architecture

```mermaid
sequenceDiagram
    participant Ingestion as Ingestion Surveillance
    participant WarningService as EarlyWarningService
    participant AlertService as IntelligenceAlertService
    participant Stage11 as NotificationEngine (Stage 11)
    participant Delivery as Multi-Channel Dispatch (In-App, Email, Push)

    Ingestion->>WarningService: Detect Critical / Elevated Signal
    WarningService->>AlertService: dispatchWarningAlert(EarlyWarning)
    AlertService->>AlertService: Construct NotificationEventDTO
    Note over AlertService: EventType: 'early_warning_alert'<br/>Category: 'watchlist_updates'<br/>Priority: 'urgent' / 'high'
    AlertService->>Stage11: dispatch(eventDTO)
    Stage11->>Stage11: Evaluate Quiet Hours & User Preferences
    Stage11->>Stage11: Check Idempotency Token
    Stage11->>Delivery: Deliver to Authorized Recipients
```

---

## 3. Data Transfer Object (DTO) Mapping

`IntelligenceAlertService` constructs a standardized `NotificationEventDTO`:
- `eventType`: `'early_warning_alert'` or `'critical_signal_alert'`
- `category`: `'watchlist_updates'` (mapped to Stage 11 notification category matrix)
- `priority`:
  - `urgent`: For `critical` warnings or `breaking` technology disruptions.
  - `high`: For `elevated` warnings.
  - `medium`: For `watch` warnings.
- `title`: `"[Early Warning] {$warning->title}"`
- `summary`: Primary `what_changed` explanation excerpt.
- `actionUrl`: `"/workspace/monitors"`
- `organizationId`: Warning's `organization_id` (enforcing strict tenant boundaries).
- `metadata`:
  - `warning_id`: UUID
  - `warning_level`: `critical` | `elevated` | `watch`
  - `signal_id`: UUID
  - `independent_sources`: Count

---

## 4. Idempotency Gate

To prevent notification floods when multiple articles arrive regarding the same breaking event:
- Idempotency key generated as:
  $$\text{IdempotencyKey} = \text{hash}('sha256', \text{"warning-alert-{$warning->id}-{$warning->status}"})$$
- `NotificationEngine` evaluates the key against active delivery logs, ensuring each warning level/status transition is dispatched exactly once.
