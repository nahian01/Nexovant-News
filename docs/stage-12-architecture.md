# NEXOVANT — STAGE 12 ARCHITECTURAL SPECIFICATION
## Advanced Intelligence Discovery, Search & Research Experience

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 12  
**Component:** System Architecture, Hybrid Retrieval & Grounded Synthesis  
**Date:** November 2026  
**Status:** Implemented & Verified  

---

## 1. Executive Architecture Overview

Stage 12 establishes Nexovant's unified intelligence discovery and deep research platform. It integrates news articles, intelligence stories, verified entities, deep research reports, executive publications, sources, events, collections, and multi-tenant organizational knowledge into a cohesive, high-performance retrieval and synthesis engine.

```
+----------------------------------------------------------------------------------------------------+
|                                    NEXOVANT SUBSCRIBER & ADMIN WEB UI                              |
|   - SearchResultsView (Hybrid / Full-Text / Semantic)     - SourceComparisonModal (Corroboration)  |
|   - PersonalResearchView (13-Section Brief Generator)    - SaveSearchModal & SavedSearchesView    |
|   - AdminSearchAnalyticsView (Telemetry & Feedback)      - RelevanceFeedbackModal                 |
+----------------------------------------------------------------------------------------------------+
                                                  |
                                                  v  JSON / Sanctum REST APIs (v1)
+----------------------------------------------------------------------------------------------------+
|                                      API & CONTROLLER LAYER                                        |
|   - SearchController (hybrid, suggestions, feedback, telemetry click)                              |
|   - EntityDiscoveryController (profiles, graph, chronological timeline)                            |
|   - SourceComparisonController (breakdown, side-by-side claim comparison)                          |
|   - Subscriber\SavedSearchController (CRUD, match previews, toggle alert)                          |
|   - Subscriber\ResearchBriefController (generate, export Markdown/JSON)                            |
|   - Admin\SearchAnalyticsAdminController (telemetry overview, feedback audit)                     |
+----------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+----------------------------------------------------------------------------------------------------+
|                                     DISCOVERY SERVICE LAYER                                        |
|   +--------------------------------------------------------------------------------------------+   |
|   | UnifiedSearchService: Hybrid Ranker (Full-Text SQL + Semantic RAG + Credibility + Recency)|   |
|   +--------------------------------------------------------------------------------------------+   |
|         |                     |                      |                        |                    |
|         v                     v                      v                        v                    |
|   SearchFilterService  SourceCorroboration   EntityDiscoveryService   ResearchBriefService         |
|   (Dates, Credibility, (Reprint vs Indep,    (Verified vs Inferred,   (13-Section Grounded         |
|    Tenant Scoping)      Contradictions)       D3 Graph, Timeline)      Brief & Citations)          |
|         |                     |                      |                        |                    |
|         +---------------------+----------------------+------------------------+                    |
|                                                  |                                                 |
|                                                  v                                                 |
|                        SavedSearchService <---------> NotificationEngine (Stage 11)                |
|                        SearchAnalyticsService (Privacy-safe metrics & logging)                     |
+----------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+----------------------------------------------------------------------------------------------------+
|                                       PERSISTENCE LAYER (DB)                                       |
|   - subscriber_saved_searches (User queries, filters, alert frequencies, channels, last run)       |
|   - research_briefs (User briefs, grounded synthesis, citation map, contradiction log)             |
|   - search_analytics_logs (Query hash, result count, execution latency, tenant ID)                 |
|   - search_relevance_feedbacks (User relevance rating, feedback note, target item)                 |
|   - Core Domain: articles, story_clusters, research_reports, publications, intelligence_entities   |
+----------------------------------------------------------------------------------------------------+
```

---

## 2. Core Subsystems & Boundaries

### 2.1 Unified Hybrid Search Engine (`UnifiedSearchService`)
Combines lexical keyword matching (Boolean operators, exact quotes, wildcards) with semantic embeddings (via Phase 4/6 Vector Storage and RAG Services).
- **Multi-Modal Query Routing**: Evaluates query intent (`all`, `articles`, `stories`, `entities`, `reports`, `sources`, `collections`).
- **Composite Scoring Formula**:
  $$\text{FinalScore} = (w_f \cdot \text{LexicalScore}) + (w_s \cdot \text{SemanticScore}) + (w_c \cdot \text{CredibilityScore}) + (w_r \cdot \text{RecencyFactor}) + (w_o \cdot \text{CorroborationWeight})$$
  Where default weights:
  - Lexical weight ($w_f$): $0.35$
  - Semantic similarity ($w_s$): $0.35$
  - Source credibility ($w_c$): $0.15$
  - Recency decay ($w_r$): $0.10$
  - Corroboration bonus ($w_o$): $0.05$

### 2.2 Source Corroboration Engine (`SourceCorroborationService`)
Prevents artificial credibility inflation caused by syndicated news wire distribution:
1. **Primary Source Detection**: Detects original publication timestamp, explicit wire attribution (`via Reuters`, `reported by AP`), and domain reputation.
2. **Duplicate Syndication Filtering**: Evaluates Levenshtein and cosine n-gram text similarity. Articles exhibiting $>85\%$ text similarity from known syndication partners are categorized as **Syndicated Wire Reprints** rather than independent corroborating witnesses.
3. **Independent Outlets**: Confirms distinct journalistic parent corporations, independent sourcing, and divergent lead passages.
4. **Contradiction Flagging**: Compares extracted numerical claims, timeline assertions, and sentiment poles to flag diverging reports.

### 2.3 Entity Discovery & Graph System (`EntityDiscoveryService`)
1. **Profile Construction**: Aggregates verified metadata, executive leadership, operating sectors, and timeline anchors.
2. **Relationship Classification**:
   - **Verified Relationships**: Extracted from audited filings, official press releases, and structured taxonomy relationships (`edge_type = 'verified'`).
   - **AI-Inferred Relationships**: Discovered via LLM knowledge extraction with confidence scores $< 0.85$ or co-occurrence analysis (`edge_type = 'inferred'`). Displayed with dashed lines and explicit confidence warnings.
3. **Graph Serialization**: Generates node and link structures suitable for interactive D3 / SVG rendering with degree centrality and node categorization (`company`, `executive`, `technology`, `institution`, `regulatory_body`).
4. **Chronological Timeline**: Synthesizes verified events and breaking alerts into a chronological trajectory with significance weighting.

### 2.4 Grounded Research Brief Generator (`ResearchBriefService`)
Synthesizes verifiable executive research documents answering natural-language inquiries across 13 required sections:
1. **Executive Summary**: High-level strategic briefing.
2. **Core Facts**: Audited factual developments.
3. **Key Findings**: Structured empirical takeaways.
4. **Corroborating Evidence**: Supporting coverage matrix.
5. **Divergent Points & Contradictions**: Unresolved inconsistencies.
6. **Key Entities & Roles**: Companies, people, and agencies involved.
7. **Timeline of Key Developments**: Chronological chain of events.
8. **Underlying Technologies & Architectures**: Technical specifications and systems.
9. **Market & Industry Implications**: Commercial and sector impact.
10. **Geopolitical & Regulatory Dimensions**: Policy and jurisdictional friction.
11. **Confidence & Evidence Ratings**: Methodological assessment score.
12. **Future Outlook & What to Watch**: Leading indicators and milestones.
13. **Methodology & Source Citations**: Traceable citations linking directly to indexed records.

### 2.5 Saved Searches & Automated Monitor (`SavedSearchService`)
1. **Alert Frequencies**: `immediate`, `daily_digest`, `weekly_digest`.
2. **Dispatch Channels**: Integrates directly with Stage 11 `NotificationEngine` (`in_app`, `email`, `push`, `telegram`, `whatsapp`).
3. **Automated Ingestion Trigger**: `evaluateIngestedItem(Article $article)` checks all active saved searches matching query terms, filters, and categories, generating notifications under category `monitor_alert`.

### 2.6 Tenant Boundary Enforcement (`SearchFilterService`)
Enforces strict multi-tenant isolation across search queries, autocomplete suggestions, research briefs, and collections:
- Unauthenticated requests: Restricted strictly to public global records (`organization_id IS NULL` and `visibility = 'public'`).
- Tenant requests: Authenticated users can access public records plus items matching `organization_id = auth()->user()->current_organization_id`.
- Zero Data Leakage: Autocomplete prefix queries, RAG chunk retrieval, and relevance feedback logs enforce tenant boundaries at the SQL query generator level.

---

## 3. Database Schema Overview

| Table | Purpose | Key Indices |
|---|---|---|
| `subscriber_saved_searches` | Persists user search criteria, alert preferences, and monitoring cadence | `(user_id, is_active)`, `last_executed_at` |
| `research_briefs` | Stores synthesized research documents, citations, and contradiction flags | `(user_id, created_at)`, `organization_id` |
| `search_analytics_logs` | Logs query latency, result counts, and zero-match alerts (anonymized) | `created_at`, `query_hash`, `organization_id` |
| `search_relevance_feedbacks` | Tracks user relevance ratings (`highly_relevant` to `irrelevant`) | `(user_id, result_type, result_id)` |
| `subscriber_collections` | Enhanced with `organization_id` and `visibility` (`private`, `organization`, `shared`) | `(user_id, visibility)`, `organization_id` |

---

## 4. Architectural Verification Summary
- Tests: `Stage12AdvancedDiscoverySearchTest` executed 12 test methods with 108 assertions covering all services and API routes.
- Integration: Reuses Phase 6 Vector Storage, Stage 8 Feeds, Stage 11 Notification Engine without duplicated schemas.
