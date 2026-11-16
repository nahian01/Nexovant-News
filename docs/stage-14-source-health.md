# NEXOVANT — STAGE 14
## Source Health Monitoring & Diagnostics

### 1. Health State Taxonomy

Nexovant classifies source health into seven discrete operational states:

| Status | Definition | Trigger Conditions | Automated Remediation |
|---|---|---|---|
| `healthy` | Fully operational | HTTP 200, Latency < 2000ms, Valid schema | Normal polling cadence |
| `degraded` | Intermittent or slow | HTTP 200 with Latency > 3500ms | Increased poll interval to reduce server pressure |
| `unavailable` | Server down or unreachable | 5xx errors, Connection timeouts, DNS failures | Exponential backoff retry |
| `auth_required` | Credentials invalid or expired | HTTP 401 or 403 | Immediate alert; suspends polling to prevent lockouts |
| `rate_limited` | Exceeded provider quota | HTTP 429 Too Many Requests | Pauses worker until Retry-After window expires |
| `format_changed` | Schema mismatch or zero items | HTTP 200 but 0 items parsed or malformed XML/JSON | Flags for administrative schema inspection |
| `suspended` | Administratively stopped | `is_active = false` or `editorial_status = suspended` | Inactive |

### 2. Telemetry Logging: `source_health_logs`

Every probe and automated check creates an immutable diagnostic entry:
- `source_id`: UUID foreign key to `news_sources`.
- `status`: Health status string.
- `response_time_ms`: Precision round-trip time in milliseconds.
- `http_status`: Actual HTTP status code returned.
- `error_message`: Captured diagnostic failure string.
- `items_parsed`: Number of items parsed during check.
- `checked_at`: Timestamp.

### 3. Network Health Overview API

`GET /api/v1/admin/sources/health-overview` computes:
- Total and active source counts.
- Platform availability percentage:
  $$\text{Availability Rate} = \frac{\text{Healthy Sources}}{\text{Active Sources}} \times 100$$
- Average network latency in milliseconds across all live connectors.
- Status breakdown count across all seven health categories.
- Recent incident stream with source names, HTTP codes, and root cause errors.
