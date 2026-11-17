# Stage 15 — API Contracts & Endpoints Specification

## 1. Route Map Overview

All Stage 15 endpoints are registered under `/api/v1/executive/` and protected by `auth:sanctum` and entitlement verification middleware (`App\Http\Middleware\EnsureEntitlement`).

| Endpoint | Method | Middleware / Policy | Description |
|---|---|---|---|
| `/executive/cockpit` | GET | `entitlement:executive.briefings` | Consolidated executive cockpit overview data |
| `/executive/briefings` | GET | `entitlement:executive.briefings` | List briefings for current user/organization |
| `/executive/briefings` | POST | `entitlement:executive.briefings` | Synthesize new structured briefing |
| `/executive/briefings/{id}` | GET | `entitlement:executive.briefings` | Get briefing detail with versions & citations |
| `/executive/briefings/{id}` | PUT | `entitlement:executive.briefings` | Update briefing metadata and narrative |
| `/executive/briefings/{id}/review` | POST | `entitlement:executive.briefings` | Approve or review briefing status |
| `/executive/briefings/{id}/export` | GET | `entitlement:executive.briefings` | Export briefing as Markdown or JSON |
| `/executive/strategic-impacts` | GET | `entitlement:strategic.impact` | List strategic impact assessments |
| `/executive/strategic-impacts` | POST | `entitlement:strategic.impact` | Create or run strategic impact assessment |
| `/executive/strategic-impacts/{id}` | GET | `entitlement:strategic.impact` | Get impact assessment detail |
| `/executive/opportunities` | GET | `entitlement:opportunity.risk` | List strategic opportunities |
| `/executive/opportunities` | POST | `entitlement:opportunity.risk` | Record new strategic opportunity |
| `/executive/opportunities/{id}/status` | PATCH | `entitlement:opportunity.risk` | Update opportunity status |
| `/executive/risks` | GET | `entitlement:opportunity.risk` | List strategic risk vectors |
| `/executive/risks` | POST | `entitlement:opportunity.risk` | Record new strategic risk |
| `/executive/risks/{id}/status` | PATCH | `entitlement:opportunity.risk` | Update risk status |
| `/executive/scenarios` | GET | `entitlement:scenario.analysis` | List strategic scenarios |
| `/executive/scenarios` | POST | `entitlement:scenario.analysis` | Create custom scenario |
| `/executive/scenarios/generate-suite` | POST | `entitlement:scenario.analysis` | Generate Optimistic/Baseline/Adverse triad |
| `/executive/scenarios/compare` | POST | `entitlement:scenario.analysis` | Compare 2–4 scenarios side-by-side |
| `/executive/scenarios/{id}` | GET | `entitlement:scenario.analysis` | Get scenario detail |
| `/executive/scenarios/{id}` | PUT | `entitlement:scenario.analysis` | Update scenario |
| `/executive/scenarios/{id}/duplicate` | POST | `entitlement:scenario.analysis` | Branch / duplicate scenario |
| `/executive/scenarios/{id}/archive` | POST | `entitlement:scenario.analysis` | Archive scenario |
| `/executive/decisions` | GET | `entitlement:decision.support` | List decision workspaces |
| `/executive/decisions` | POST | `entitlement:decision.support` | Initiate new decision workspace |
| `/executive/decisions/{id}` | GET | `entitlement:decision.support` | Get decision detail with options & milestones |
| `/executive/decisions/{id}/options` | POST | `entitlement:decision.support` | Add strategic option to decision matrix |
| `/executive/decisions/{id}/milestones` | POST | `entitlement:decision.support` | Add execution milestone |
| `/executive/decisions/{id}/milestones/{mid}` | PATCH | `entitlement:decision.support` | Update milestone status |
| `/executive/decisions/{id}/approve` | POST | `entitlement:decision.support` | Formal human authorization of decision |
| `/executive/decisions/{id}/post-review` | POST | `entitlement:decision.support` | Record retrospective review |
| `/executive/assistant/ask` | POST | `entitlement:executive.assistant` | Conversational grounded assistant query |

---

## 2. Request & Response Payloads

### 2.1 Synthesize Briefing (POST `/api/v1/executive/briefings`)
**Request:**
```json
{
  "title": "Global Technology & Energy Transition Daily Briefing",
  "period_type": "daily",
  "industry_scope": "semiconductor-autonomy",
  "geographic_scope": "Global"
}
```
**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": "01a1e786-6cf7-71aa-84a3-838f3013c584",
    "title": "Global Technology & Energy Transition Daily Briefing",
    "slug": "global-technology-energy-transition-daily-briefing-20261117",
    "period_type": "daily",
    "coverage_start": "2026-11-16T03:21:47+00:00",
    "coverage_end": "2026-11-17T03:21:47+00:00",
    "status": "generated",
    "review_status": "pending",
    "version": 1,
    "executive_summary": "Executive Intelligence Briefing covering Nov 16...",
    "what_happened": [ ... ],
    "why_it_matters": { ... },
    "what_has_changed": { ... },
    "what_could_happen_next": [ ... ],
    "executive_considerations": { ... },
    "evidence_and_sources": { ... },
    "citations": [ ... ]
  }
}
```

### 2.2 Human Executive Authorization (POST `/api/v1/executive/decisions/{id}/approve`)
**Request:**
```json
{
  "selected_option_id": "01a1e794-c616-727c-9587-06d18e0d3d0a",
  "recorded_decision": "Approved execution of recommended strategic path.",
  "approval_action": "approved",
  "next_steps": "Proceed with binding LOI with state economic development agency."
}
```
**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": "01a1e794-c616-727c-9587-06d18e0d3d0a",
    "title": "EU Foundry Co-Investment & Supply Assurance Commitment",
    "status": "decision_recorded",
    "approval_status": "approved",
    "recorded_decision": "Approved execution of recommended strategic path.",
    "approved_at": "2026-11-17T03:39:01+00:00",
    "approver": {
      "id": "01a1e012-e4c1-7333-b086-d7fe4c2be756",
      "name": "Alexander Vance"
    }
  }
}
```
