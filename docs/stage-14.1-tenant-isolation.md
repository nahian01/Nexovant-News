# Stage 14.1 — Multi-Tenant Isolation & Private Source Security

## 1. Multi-Tenant Architecture & Data Scoping
Nexovant supports private enterprise news sources and custom RSS feeds owned by specific organizations:
- `news_sources.organization_id`: `foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete()`.
- Public sources have `organization_id = NULL` and are visible to all subscribers.
- Private organizational sources have `organization_id = '<uuid>'` and must be strictly isolated to authenticated members of that organization.

## 2. API & Controller Authorization Gates
In `SourceAdminController`:
```php
// Enforce organization scoping for non-platform-admins
if ($user->account_type !== 'admin') {
    $userOrgId = $user->organizationMember?->organization_id;
    if ($source->organization_id !== null && $source->organization_id !== $userOrgId) {
        abort(403, 'Unauthorized access to organization source.');
    }
}
```
When listing sources via `/api/v1/admin/sources`:
```php
$query->where(function ($sub) use ($userOrgId) {
    $sub->whereNull('organization_id');
    if ($userOrgId) {
        $sub->orWhere('organization_id', $userOrgId);
    }
});
```

## 3. Search Retrieval Tenant Scoping
In `UnifiedSearchService::searchArticles` and `searchSources`:
- Added `$tenantId` scoping parameter:
```php
->when($tenantId, function ($q) use ($tenantId) {
    $q->where(function ($sub) use ($tenantId) {
        $sub->whereNull('organization_id')
            ->orWhere('organization_id', $tenantId);
    });
}, function ($q) {
    // If not authenticated in a tenant, only public sources
    $q->whereNull('organization_id');
})
```

## 4. Test Verification Evidence
Verified in `test_group_f_ssrf_port_and_alternate_ip_defense_and_tenant_isolation`:
- Tenant A created private source `Tenant A Confidential Regulatory Feed`.
- Tenant B user queried `GET /api/v1/admin/sources/{id}` for Tenant A's source $\to$ Received **HTTP 403 Forbidden**.
- Tenant B executed search query $\to$ Tenant A's private source was completely excluded from search results.
- Zero cross-tenant document or source leakage.
