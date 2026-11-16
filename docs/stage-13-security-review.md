# Nexovant Stage 13 — Security & RBAC Review
## Access Control, Role Governance & Data Protection

**Document Version:** 1.0.0  
**Status:** Production Ready  

---

## 1. Security Architecture & Threat Model

Stage 13 introduces real-time surveillance rules, machine learning parameters, and automated alert dispatch. The security model addresses four primary threat vectors:
1. **Unauthorized Access & Tenant Leakage:** Private corporate intelligence leaks to competitors.
2. **Administrative Override Exploitation:** Unauthorized manipulation of detection rules or sensitivity floors.
3. **Denial-of-Service via Unbounded Aggregations:** Costly analytical queries or OLS calculations exhausting database CPU.
4. **Information Integrity / Fabricated Claims:** Unsubstantiated causal assertions causing defamation or commercial damage.

---

## 2. Role-Based Access Control (RBAC) Matrix

| Route / Capability | Public (Guest) | Subscriber | Org Admin | System Administrator |
| :--- | :--- | :--- | :--- | :--- |
| `GET /api/v1/automation/signals` | Public only | Public + Tenant | Public + Tenant | Full Access |
| `GET /api/v1/automation/early-warnings` | Public only | Public + Tenant | Public + Tenant | Full Access |
| `PATCH /api/v1/automation/early-warnings/{id}/status` | Denied (401) | Allowed (Tenant) | Allowed (Tenant) | Full Access |
| `POST /api/v1/automation/forecasts` | Denied (401) | Allowed | Allowed | Full Access |
| `POST /api/v1/automation/reports` | Denied (401) | Allowed | Allowed | Full Access |
| `GET /api/v1/admin/automation-control/*` | Denied (401/403) | Denied (403) | Denied (403) | Allowed (`admin` middleware) |
| `POST /api/v1/admin/automation-control/reviews` | Denied (401/403) | Denied (403) | Denied (403) | Allowed (`admin` middleware) |
| `POST /api/v1/admin/automation-control/scan` | Denied (401/403) | Denied (403) | Denied (403) | Allowed (`admin` middleware) |

---

## 3. Input Validation & Parameter Guardrails

1. **Window Boundaries:**
   - Time windows restricted to validated enumerations (`30d`, `90d`, `180d`, `1y`).
   - Maximum query horizon capped at 1 year.
2. **Rate Limiting:**
   - On-demand report and forecast generation throttled via Laravel Sanctum middleware (`throttle:20,1`).
3. **Database Injection Defense:**
   - All analytical aggregation queries use parameterized Eloquent ORM bindings.
   - Zero raw SQL string concatenation.

---

## 4. Security Verification
- Feature test `test_admin_automation_control_and_rule_configuration` asserts non-admins receive HTTP 403 Forbidden on administrative automation endpoints.
- Browser E2E Scenario M verified subscriber cannot load or edit admin automation control resources.
