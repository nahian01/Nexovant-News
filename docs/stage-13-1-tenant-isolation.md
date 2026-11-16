# Nexovant — Stage 13.1 Multi-Tenant Authorization Isolation

## 1. Multi-Tenant Tenet & Scoping Policy

Nexovant supports global intelligence (public platform feeds) alongside private, proprietary enterprise workspaces. 

**Core Tenet**: Public records (`organization_id = null`) are accessible to all authenticated and authorized subscribers. Private organization records (`organization_id !== null`) are **strictly confidential** and must never be exposed, leaked, or exported to users outside that organization.

---

## 2. Hardened Controller Endpoints

Stage 13.1 audits and enforces strict tenant verification across all automation endpoints:

### 1. Automated Intelligence Reports (`AutomatedReportController`)
- **Listing (`index`)**: Filters queries with:
  ```php
  $query->where(function ($q) use ($tenantId) {
      $q->whereNull('organization_id')->orWhere('organization_id', $tenantId);
  });
  ```
- **Detail View (`show`)**: If `$report->organization_id !== null && $report->organization_id !== $tenantId`, returns `403 Forbidden`.
- **Export Streaming (`export`)**: Re-evaluates `$report->organization_id !== null && $report->organization_id !== $tenantId` before generating markdown or JSON payloads. Unauthorized requests return `403 Forbidden`.

### 2. Predictive Trend Forecasts (`TrendForecastController`)
- **Detail View (`show`)**: Confirms `$forecast->organization_id === null || $forecast->organization_id === $tenantId`. Rejects unauthorized cross-tenant requests with `403 Forbidden`.

### 3. Early Warnings (`EarlyWarningController`)
- **Detail View (`show`)**: Strictly scopes access to the tenant or public warnings (`403 Forbidden` on mismatch).
- **Status Progression (`updateStatus`)**: Only members of the owning organization or platform admins can transition warning status.

### 4. Intelligence Signals (`SignalController`)
- **Detail View (`show`)**: Rejects requests attempting to access organization-scoped signals with `403 Forbidden`.

---

## 3. Automated Verification Evidence

The test suite in `tests/Feature/Stage13_1AutomationReliabilityTest.php` contains explicit cross-tenant penetration scenarios:
- Tenant A creates private report, forecast, signal, and early warning.
- User belonging exclusively to Tenant B executes GET, POST, and export requests against Tenant A resource IDs.
- Every cross-tenant operation asserts `assertStatus(403)`.
