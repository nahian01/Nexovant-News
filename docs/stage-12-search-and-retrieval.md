# NEXOVANT — STAGE 12: SEARCH & RETRIEVAL SPECIFICATION
## Hybrid Lexical-Semantic Retrieval, Normalization & Scoring

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 12  
**Component:** Hybrid Search & Retrieval Engine  
**Date:** November 2026  
**Status:** Implemented & Verified  

---

## 1. Overview & Capabilities

Nexovant's intelligence discovery engine bridges deterministic keyword precision with latent semantic understanding. Unlike consumer news aggregators that rely on raw recency or simple string matching, Nexovant ranks results based on verified truth value, corroborating consensus, domain credibility, and context.

Supported Content Types:
1. `articles`: Raw ingested and verified news intelligence articles.
2. `stories`: In-depth analytical stories and editorial syntheses.
3. `clusters`: Consolidated multi-source event clusters.
4. `entities`: Companies, executives, technologies, agencies, and institutions.
5. `reports`: Formal intelligence research reports from Phase 6 research projects.
6. `publications`: Periodical executive intelligence releases.
7. `events`: Chronological milestone records.
8. `sources`: Media outlets, research institutions, and official regulatory registries.
9. `collections`: Curated subscriber portfolios and organization dossier collections.

---

## 2. Hybrid Retrieval Architecture

### 2.1 Dual-Stage Query Pipeline
```
[ User Search Input: "EUV High-NA lithography yield" ]
                        |
       +----------------+----------------+
       |                                 |
       v                                 v
[ Lexical Retrieval ]           [ Semantic Vector Retrieval ]
- Full-text search              - 768/1536-dim embedding
- Boolean matching (+/-/")       - Cosine similarity threshold >= 0.70
- Exact phrase boosting         - Semantic chunk retrieval
       |                                 |
       +----------------+----------------+
                        |
                        v
         [ Multi-Tenant Security Scoping ]
         - Public records: (organization_id IS NULL)
         - Tenant records: (organization_id == user.org_id)
                        |
                        v
              [ Composite Re-Ranker ]
              - Lexical score (35%)
              - Semantic score (35%)
              - Credibility weight (15%)
              - Recency decay (10%)
              - Corroboration consensus (5%)
                        |
                        v
     [ Paginated Unified Search Result Response ]
```

### 2.2 Ranking Formula & Factors

$$\text{FinalScore} = (0.35 \cdot S_{\text{lex}}) + (0.35 \cdot S_{\text{sem}}) + (0.15 \cdot S_{\text{cred}}) + (0.10 \cdot S_{\text{rec}}) + (0.05 \cdot S_{\text{corrob}})$$

1. **Lexical Score ($S_{\text{lex}}$)**:
   - Term match frequency, title hits ($2.0\times$ multiplier), and exact phrase occurrences.
2. **Semantic Similarity ($S_{\text{sem}}$)**:
   - Vector cosine distance between query embedding and knowledge vector representations.
3. **Source Credibility ($S_{\text{cred}}$)**:
   - Evaluated from `news_sources.credibility_score` on a normalized $[0.00, 1.00]$ scale. If an unverified blog reports an item, it receives no credibility bonus compared to verified institutional sources (e.g. IEEE, Reuters, SEC filings).
4. **Recency Decay ($S_{\text{rec}}$)**:
   - Exponential decay: $e^{-\lambda \cdot \Delta t}$, where $\Delta t$ is days elapsed since publication and $\lambda = 0.05$.
5. **Corroboration Factor ($S_{\text{corrob}}$)**:
   - Independent non-syndicated corroborating outlets increase confidence without score tampering.

---

## 3. Server-Side Filter Normalization

The `SearchFilterService` enforces deterministic normalization to guard against query injection and prevent empty results caused by schema scale mismatches:

### 3.1 Credibility Scale Normalization
- In the database, `news_sources.credibility_score` is stored as a decimal `NUMERIC(3, 2)` between `0.00` and `1.00`.
- If client requests pass integer percentages (e.g. `min_credibility=80` or `min_credibility=90`), the service detects values $> 1.0$ and divides by `100.0`:
  ```php
  $minCred = $filters['min_credibility'] > 1.0 
      ? (float) $filters['min_credibility'] / 100.0 
      : (float) $filters['min_credibility'];
  ```

### 3.2 Verification Status Normalization
- Articles store verification as `'confirmed'`, `'pending'`, or `'disputed'`, while users query for `'verified'`.
- The filter service automatically expands `'verified'` into `['verified', 'confirmed']` so verified intelligence is never dropped.

### 3.3 Date Range Presets
Supported presets are converted into deterministic UTC timestamps:
- `24h`: `Carbon::now()->subDay()`
- `7d`: `Carbon::now()->subDays(7)`
- `30d`: `Carbon::now()->subDays(30)`
- `1y`: `Carbon::now()->subYear()`
- `custom`: Parses explicit ISO `date_from` and `date_to`.

---

## 4. Autocomplete & Search Suggestions

The `/api/v1/search/suggestions` endpoint provides instant sub-100ms query completions.
- **Tenant Isolation**: Suggestions for organizational collections or proprietary entity tags check `organization_id` so internal project names or ticker symbols are never leaked to external tenants.
- **Deduplication**: Suggestions combine top historical query entities, trending topic clusters, and verified entity canonical titles.

---

## 5. Telemetry, Analytics & Subscriber Feedback

### 5.1 Privacy-Safe Logging (`search_analytics_logs`)
- Query terms are hashed with SHA-256 for anonymized frequency aggregation while preserving original query text for zero-result gap analysis.
- Latency (milliseconds), result count, and tenant context are captured.

### 5.2 4-Tier Relevance Feedback (`search_relevance_feedbacks`)
Subscribers can submit explicit relevance ratings:
- `highly_relevant` (+2)
- `relevant` (+1)
- `not_relevant` (-1)
- `misleading_or_incorrect` (-2)

Feedback is reviewed by admins in `/admin/search-analytics` to calibrate semantic retrieval weights.
