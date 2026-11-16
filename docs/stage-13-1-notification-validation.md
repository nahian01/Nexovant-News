# Nexovant — Stage 13.1 Early-Warning Notification Validation

## 1. Architecture & Stage 11 Integration

The early-warning surveillance engine interfaces directly with Stage 11's centralized `NotificationEngine`. All notifications are transmitted as strongly-typed `NotificationEventDTO` instances through the centralized dispatch pipeline.

```
[EarlyWarning Event]
        │
        ▼
[IntelligenceAlertService]
        │
        ├── Resolve Recipients (SubscriberMonitors + Tenant Members + Fallback)
        ├── Assemble NotificationEventDTO (Idempotency Key, Category, Priority)
        │
        ▼
[NotificationEngine::dispatch]
        │
        ├── Channel Filtering (In-App, Real-Time, Email, Web Push, Telegram)
        ├── User Preference Checks
        ├── Quiet Hours Enforcement
        └── Delivery Tracking & Audit Log
```

---

## 2. Notification Event Catalog

Stage 13.1 defines dedicated notification events covering the complete early-warning lifecycle:

| Event Type | Trigger Condition | Notification Priority | Dedup / Idempotency Window | Category |
|---|---|---|---|---|
| `early_warning.watch` | Newly detected warning with `warning_level = 'watch'` | Medium | 1 hour | `early_warning` |
| `early_warning.elevated` | Newly detected warning with `warning_level = 'elevated'` | High | 1 hour | `early_warning` |
| `early_warning.critical` | Newly detected warning with `warning_level = 'critical'` | Urgent | 1 hour | `early_warning` |
| `early_warning.escalated` | Warning upgraded to a higher threat level (e.g., watch $\to$ elevated) | Urgent (if critical), High | 2 hours | `early_warning_escalation` |
| `early_warning.evidence_corroborated` | New independent corroborating evidence attached | High | 1 hour | `early_warning_evidence` |
| `early_warning.evidence_disputed` | New contradictory or disputed evidence attached | Urgent | 1 hour | `early_warning_evidence` |
| `early_warning.resolved` | Warning transitioned to status `resolved` | Medium | 4 hours | `early_warning_status` |
| `early_warning.reopened` | Resolved warning reopened due to resurgence | High | 2 hours | `early_warning_status` |
| `signal.high` | High severity intelligence signal detected | High | 2 hours | `signal_alert` |
| `signal.critical` | Critical severity intelligence signal detected | Urgent | 2 hours | `signal_alert` |

---

## 3. Recipient Resolution Logic

Recipients are resolved hierarchically:
1. **Keyword & Entity Matches**: Active `SubscriberMonitor` records matching the signal's keywords or tracked entities.
2. **Multi-Tenant Scoping**: When a warning or signal belongs to an organization (`organization_id !== null`), all active `OrganizationMember` users of that organization are automatically notified.
3. **Admin / Analyst Fallback**: If no specific custom monitors match, the platform notifies designated administrative or analyst users to guarantee no critical development goes unobserved.
