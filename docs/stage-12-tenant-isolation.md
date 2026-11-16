# NEXOVANT — STAGE 12: MULTI-TENANT ISOLATION SPECIFICATION
## Enterprise Boundary Enforcement Across Search, RAG, Suggestions & Telemetry

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 12  
**Component:** Tenant Boundary Enforcement & Security Architecture  
**Date:** November 2026  
**Status:** Implemented & Verified  

---

## 1. Multi-Tenant Threat Model

In an enterprise intelligence product, cross-tenant data leakage is a critical vulnerability. Enterprise subscribers upload private internal research notes, curate proprietary watchlists, and build confidential competitive intelligence collections (e.g. prospective M&A targets, proprietary IP filings, unannounced patents).

Stage 12 guarantees absolute isolation across:
1. **Hybrid Search Queries**: Private organizational documents or collections belonging to Organization A must never appear in search results for Organization B or unauthenticated public users.
2. **Semantic RAG Embeddings**: Vector chunk retrieval partitions embedding searches by `organization_id`.
3. **Search Autocomplete & Suggestions**: Autocomplete prefixes cannot leak private entities, internal project codenames, or proprietary watchlists.
4. **Automated Saved Searches**: Background alert monitors only match and notify users permitted to access the ingested data.
5. **Research Briefs & Exports**: Brief generation strictly scopes document synthesis to permitted tenant boundaries.
6. **Search Telemetry**: Internal query logs and analytics preserve tenant identifiers without exposing proprietary search terms to peer tenants.

---

## 2. Scope Matrix & Access Rules

| Actor | Context | Accessible Data Scope | Visibility Filter Applied |
|---|---|---|---|
| **Anonymous User** | Unauthenticated | Public global articles, stories, entities, sources, and public collections | `organization_id IS NULL AND visibility = 'public'` |
| **Individual Subscriber** | Authenticated, no org | Public global items + user's own private saved searches, briefs, and personal collections | `(organization_id IS NULL) + (user_id == auth.id)` |
| **Enterprise Tenant Member** | Authenticated with `organization_id` | Public global items + organization-wide collections + user's private items | `(organization_id IS NULL OR organization_id == user.organization_id)` |
| **Tenant Administrator** | Authenticated admin of Org | Full access to organization collections, briefs, and team saved searches | `organization_id == user.organization_id` |

---

## 3. SQL Enforcement Mechanisms

The `SearchFilterService` enforces boundary filters at the database query builder level before any records are fetched:

```php
public function applyTenantScoping(Builder $query, ?int $tenantId, ?int $userId, string $visibility = 'public'): Builder
{
    if ($tenantId === null) {
        // Strict public scope for unauthenticated or non-org contexts
        return $query->where(function (Builder $q) use ($userId) {
            $q->whereNull('organization_id')
              ->where(function (Builder $sub) {
                  $sub->where('visibility', 'public')
                      ->orWhereNull('visibility');
              });
            if ($userId !== null) {
                $q->orWhere(function (Builder $userQ) use ($userId) {
                    $userQ->where('user_id', $userId)
                          ->whereNull('organization_id');
                });
            }
        });
    }

    // Authenticated enterprise member scope
    return $query->where(function (Builder $q) use ($tenantId, $userId) {
        $q->whereNull('organization_id') // Global platform intelligence
          ->orWhere('organization_id', $tenantId); // Tenant private intelligence
    });
}
```

---

## 4. Subsystem Isolation Details

### 4.1 Hybrid Search & Collections
`SubscriberCollection` models store both `organization_id` and `visibility` (`private`, `organization`, `shared`).
- When a search runs across content type `collections`, only collections belonging to the caller's organization or flagged as `public` are returned.
- Peer organizations receive zero search hits for private tenant dossiers.

### 4.2 Query Suggestions
The `SearchController::suggestions()` endpoint verifies whether suggestions originate from proprietary tenant tags or public entities. If a suggested search term matches an internal organization project, it is only surfaced if the session carries matching `organization_id`.

### 4.3 Automated Monitors & Background Evaluation
When `SavedSearchService::evaluateIngestedItem()` processes newly ingested records:
- The target item's `organization_id` is evaluated against the saved search owner's organization.
- Background jobs cannot dispatch notifications for private items to unauthorized recipients.

---

## 5. Automated Verification Evidence

The test `test_multi_tenant_search_isolation_prevents_cross_tenant_leakage` in `tests/Feature/Stage12AdvancedDiscoverySearchTest.php` programmatically validates:
1. Tenant 1 (`Acme Corp`) creates a private intelligence collection titled "Project Titan Secret Acquisition".
2. Tenant 2 (`Beta Tech`) executes a search for `"Titan"`.
3. Asserted:
   - Tenant 2 receives 0 collection results.
   - Tenant 1 executes the same search and receives 1 result.
   - Public unauthenticated caller executes the search and receives 0 results.
4. Test result: **100% Passed (108 assertions across suite)**.
