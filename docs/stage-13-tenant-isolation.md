# Nexovant Stage 13 — Multi-Tenant Isolation
## Tenant Boundaries Across Signals, Warnings, Forecasts & Reports

**Document Version:** 1.0.0  
**Status:** Production Ready  

---

## 1. Multi-Tenant Architecture

Nexovant supports both public global intelligence (accessible across all subscribers) and private organizational intelligence (restricted to authenticated members of a specific tenant).

---

## 2. Boundary Matrix

| Entity | Global Records (`organization_id = null`) | Tenant Records (`organization_id = UUID`) | Cross-Tenant Protection |
| :--- | :--- | :--- | :--- |
| **`IntelligenceSignal`** | Public disclosures, press wires, patent filings | Internal company memos, private partner disclosures | Tenant B receives 403 Forbidden on Tenant A's signals |
| **`EarlyWarning`** | System-wide macro & supply-chain warnings | Private supply-chain risks specific to an enterprise | Excluded from index queries unless member |
| **`TrendForecast`** | Macro technology adoption trajectories | Proprietary internal capex projections | Private projections never leak to public indexes |
| **`AutomatedReport`** | Platform-wide daily executive summaries | Custom organizational intelligence dossiers | Tenant isolation enforced in controller and service |

---

## 3. Query Scoping Enforcement

In all API controllers (`SignalController`, `EarlyWarningController`, `TrendForecastController`, `AutomatedReportController`):

```php
$user = $request->user();
$tenantId = null;

if ($user) {
    $membership = OrganizationMember::where('user_id', $user->id)->first();
    $tenantId = $membership?->organization_id;
}

$query = Model::query();

if ($tenantId !== null) {
    // Subscriber sees global public intelligence + their organization's private records
    $query->where(function ($q) use ($tenantId) {
        $q->whereNull('organization_id')
          ->orWhere('organization_id', $tenantId);
    });
} else {
    // Unaffiliated user only sees public records
    $query->whereNull('organization_id');
}
```

### Direct Access Guard
```php
if ($record->organization_id !== null && $record->organization_id !== $tenantId) {
    return response()->json([
        'success' => false,
        'message' => 'Unauthorized access to organization intelligence.'
    ], 403);
}
```

---

## 4. Test Verification
Verified by automated feature test:
`Stage13IntelligenceAutomationTest::test_strict_multi_tenant_isolation_on_automation_records`:
- Asserts private signal created under Tenant Alpha is invisible in Tenant Beta's index.
- Asserts direct GET request from Tenant Beta user to Tenant Alpha's signal returns HTTP 403 Forbidden.
