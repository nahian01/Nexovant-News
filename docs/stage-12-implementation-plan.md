# NEXOVANT — STAGE 12 IMPLEMENTATION PLAN
## Advanced Intelligence Discovery, Search & Research Experience

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 12  
**Target Repositories:** `nexovant-backend` & `nexovant-frontend`  
**Branch:** `dev`  

---

## 1. Executive Summary & Goals

Stage 12 builds a unified, intelligent discovery and research platform across Nexovant's news articles, story clusters, research reports, executive publications, entities (companies, executives, technologies, products, locations, institutions), events, sources, timelines, and multi-tenant organizational knowledge.

The platform combines:
1. **Hybrid Retrieval**: Full-text keyword search, semantic vector retrieval (via `AIRouterService` and `KnowledgeEmbedding`), metadata-based filtering, credibility weighting, recency decay, and cross-source corroboration scoring.
2. **Advanced Filtering & Sorting**: Multi-dimensional server-enforced filters (date ranges, content types, categories, regions, verification status, credibility tiers, tenant scope) with persistent query state.
3. **Natural-Language Intelligence Discovery**: Question interpretation, entity/temporal parameter extraction, grounded answer synthesis with verifiable citations, explicit distinction between facts and inferences, and clear communication of evidence limitations.
4. **Source Transparency & Corroboration**: Identification of primary sources, independent corroborators, and syndicated reprints (preventing score inflation), plus side-by-side source comparison.
5. **Entity-Based Discovery & Relationship Graphs**: Entity resolution, verified vs AI-inferred relationships, interactive network graph generation, and recent developments.
6. **Interactive Intelligence Timelines**: Chronological timelines distinguishing `occurred_at` from `reported_at`, with category and date filtering.
7. **Saved Searches & Automated Monitoring**: Persistent search queries with alert frequencies (`immediate`, `daily_digest`, `weekly_digest`, `disabled`), automated matching against new ingestion, and dispatch through Stage 11's `NotificationEngine`.
8. **Research Workspace & Grounded Briefs**: Structured 13-section research briefs, private/organization collections, research notes, and permitted exports.
9. **Enterprise Knowledge Discovery**: Strict multi-tenant isolation guaranteeing zero cross-tenant leakage in search, vector retrieval, semantic cache, autocomplete, or exports.
10. **Search Analytics & Relevance Feedback**: Privacy-preserving search telemetry, latency tracking, empty query audits, and subscriber relevance feedback.

---

## 2. Codebase Audit & Reuse Strategy

| Module | Existing Infrastructure (To Reuse) | Stage 12 Extension / Enhancement |
| :--- | :--- | :--- |
| **Search Controller** | `SearchController.php` (basic LIKE queries) | Upgrade to `UnifiedSearchService` supporting hybrid ranking, 9 content types, metadata filters, and relevance explanations. |
| **Grounded RAG** | `GroundedRagService.php`, `KnowledgeBaseService.php` | Integrate hybrid search, vector similarity matching, deterministic fallback, and strict tenant boundaries. |
| **Research Engine** | `DeepResearchEngineService.php`, `SubscriberResearchService.php` | Extend to generate structured 13-section grounded research briefs with traceable citations and uncertainty ratings. |
| **Verification & Graph** | `AdvancedFactVerificationService.php`, `RelationshipGraphService.php` | Leverage syndication detection (`is_syndicated_reprint`), stance evaluation, and multi-level relationship graph rendering. |
| **Timelines** | `HistoricalTimelineService.php` | Expose unified timeline endpoints with date range, event category, and entity filtering. |
| **Monitors & Notifications** | `SubscriberMonitor`, `IntelligenceMonitorService`, `NotificationEngine` | Link `SubscriberSavedSearch` into the existing ingestion event matcher and Stage 11 multi-channel dispatch pipeline. |
| **Collections & Library** | `SubscriberCollection`, `SubscriberLibraryItem`, `PersonalLibraryService` | Add `organization_id` and `visibility` (`private`, `organization`, `shared`) to collections for enterprise knowledge sharing. |
| **Frontend Search** | `SearchResultsView.vue`, `PersonalResearchView.vue` | Overhaul search interface with filter chips, hybrid toggle, source comparison modal, brief builder, and feedback ratings. |

---

## 3. Database Schema Plan

### Migration: `0001_01_01_000105_create_discovery_search_and_saved_searches_tables.php`

1. **`subscriber_saved_searches`**:
   - `id` (uuid, primary)
   - `user_id` (foreignUuid, users, cascade)
   - `organization_id` (foreignUuid, organizations, nullable)
   - `name` (string)
   - `description` (text, nullable)
   - `query` (string)
   - `filters` (json, nullable)
   - `alert_frequency` (string, default: `daily_digest`)
   - `delivery_channels` (json, nullable)
   - `is_active` (boolean, default: true)
   - `last_executed_at` (timestamp, nullable)
   - `last_matched_at` (timestamp, nullable)
   - `match_count` (integer, default: 0)
   - `timestamps`

2. **`research_briefs`**:
   - `id` (uuid, primary)
   - `user_id` (foreignUuid, users, cascade)
   - `organization_id` (foreignUuid, organizations, nullable)
   - `collection_id` (foreignUuid, subscriber_collections, nullable)
   - `title` (string)
   - `research_question` (text)
   - `executive_summary` (text)
   - `structured_sections` (json) — 13 configurable sections
   - `citations` (json) — traceable references
   - `conflicting_evidence` (json) — contradictory claims
   - `confidence_score` (decimal 3,2, default: 0.85)
   - `uncertainty_rating` (string, default: `low`)
   - `is_shared_with_org` (boolean, default: false)
   - `timestamps`

3. **`search_analytics_logs`**:
   - `id` (uuid, primary)
   - `user_id` (foreignUuid, users, nullable)
   - `organization_id` (foreignUuid, organizations, nullable)
   - `query` (string)
   - `search_category` (string, default: `all`)
   - `filters` (json, nullable)
   - `result_count` (integer, default: 0)
   - `is_empty` (boolean, default: false)
   - `retrieval_method` (string: `keyword`, `semantic`, `hybrid`)
   - `duration_ms` (integer, default: 0)
   - `clicked_result_id` (string, nullable)
   - `clicked_result_type` (string, nullable)
   - `created_at` (timestamp)

4. **`search_relevance_feedbacks`**:
   - `id` (uuid, primary)
   - `user_id` (foreignUuid, users, cascade)
   - `query` (string)
   - `result_id` (string)
   - `result_type` (string)
   - `feedback_rating` (string: `relevant`, `not_relevant`, `incorrect_result`, `missing_information`)
   - `comments` (text, nullable)
   - `created_at` (timestamp)

5. **Expand `subscriber_collections`**:
   - Add `organization_id` (foreignUuid, organizations, nullable)
   - Add `visibility` (string, default: `private`: `private`, `organization`, `shared`)

---

## 4. Backend Architecture Plan

```
app/Services/Discovery/
├── DTO/
│   ├── SearchQueryDTO.php
│   ├── SearchResultItemDTO.php
│   └── ResearchBriefDTO.php
├── UnifiedSearchService.php         # Hybrid search across 9 content types
├── SearchFilterService.php          # Server-side validation and SQL constraint application
├── SourceCorroborationService.php   # Syndication vs independent corroboration, comparison
├── EntityDiscoveryService.php       # Enriched entity profiles, relationship graphs
├── ResearchBriefService.php         # 13-section grounded synthesis using AI router / RAG
├── SavedSearchService.php           # CRUD, matching, and NotificationEngine integration
└── SearchAnalyticsService.php       # Telemetry, feedback recording, admin metrics
```

### Thin Controllers
- `SearchController.php`: Expanded `search`, `suggestions`, `feedback`, `analyticsClick`.
- `SavedSearchController.php`: CRUD for saved searches, execution, matches.
- `ResearchBriefController.php`: Generate grounded brief, show, save to collection, export.
- `EntityDiscoveryController.php`: Public & authenticated entity profile, graph, timeline.
- `SourceComparisonController.php`: Side-by-side reporting inspection.
- `Admin/SearchAnalyticsAdminController.php`: Overview, trends, unanswered queries, feedback audit.

---

## 5. Frontend Architecture Plan

```
src/
├── services/api/
│   ├── discovery.service.ts         # Unified search, suggestions, feedback, source comparison
│   ├── savedSearch.service.ts       # Saved searches and monitor rules
│   └── researchBrief.service.ts     # Brief generation, export, collection linkage
├── views/
│   ├── public/
│   │   └── SearchResultsView.vue    # Full overhaul: Hybrid toggle, Filter drawer, chips, source comparison modal
│   ├── subscriber/
│   │   ├── PersonalResearchView.vue # Brief generator with 13-section display and citation explorer
│   │   ├── SavedSearchesView.vue    # Saved search management & monitor frequency
│   │   └── PersonalLibraryView.vue  # Collection visibility (private vs org), export
│   └── admin/
│       └── AdminSearchAnalyticsView.vue # Query trends, latency, unanswered queries
└── components/discovery/
    ├── FilterDrawer.vue             # Comprehensive filter controls
    ├── SourceComparisonModal.vue    # Side-by-side article corroboration
    └── RelevanceFeedbackModal.vue   # 4-tier feedback modal
```

---

## 6. Execution Stages & Verification Sequence

1. **Database Migration & Models**: Run migration `0001_01_01_000105`, implement/extend Eloquent models.
2. **Backend Service Layer**: Implement `UnifiedSearchService`, `SearchFilterService`, `SourceCorroborationService`, `EntityDiscoveryService`, `ResearchBriefService`, `SavedSearchService`, `SearchAnalyticsService`.
3. **API Controllers & Routes**: Wire up 20+ discovery and research endpoints with strict tenant scoping.
4. **Backend Automated Testing**: Comprehensive test suite `Stage12AdvancedDiscoverySearchTest.php` covering all functional requirements, security boundaries, and tenant isolation.
5. **Frontend Services & Views**: Build UI components, integrate reactive filter states, and connect to real APIs.
6. **Frontend Type Check & Build**: Validate `vue-tsc --noEmit` and run `vite build`.
7. **Browser E2E Verification**: Execute Scenarios A through K using Chrome DevTools.
8. **Documentation**: Author all 9 required documentation files in `docs/`.
9. **Final Execution Sign-off**: Git commit, push to `dev`, and compile final delivery report.
