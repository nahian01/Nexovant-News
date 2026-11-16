# Stage 14.1 — Source Network Validation & Multilingual Intelligence Reliability Plan

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 14.1 (Integration Validation, Reliability Engineering, Security Hardening)  
**Date:** 2026-11-16  
**Status:** In Progress  

---

## 1. Executive Summary & Mission Scope

Stage 14 introduced the multi-type Source Network (RSS/Atom, REST API, Government/Regulatory Disclosures, Academic Repositories), multilingual detection and translation, geographic coverage tracking, source diversity scoring, and administrative management.

Stage 14.1 provides the essential operational hardening, live-integration verification, and scientific calibration required before considering the Source Network production-ready. The objective is to verify that every stage of the cognitive pipeline:

$$\text{Registration} \longrightarrow \text{Approval} \longrightarrow \text{Connector Execution} \longrightarrow \text{Article Normalization} \longrightarrow \text{Deduplication} \longrightarrow \text{Story Clustering} \longrightarrow \text{Verification} \longrightarrow \text{Translation} \longrightarrow \text{Cross-Language Discovery} \longrightarrow \text{Search} \longrightarrow \text{Presentation}$$

operates reliably with actual permitted external sources, enforces immutable content guarantees, transparently discloses reliability evidence and sample sizes, blocks advanced SSRF bypasses (including IP representations and port probing), and guarantees strict multi-tenant boundary isolation.

---

## 2. Architecture & Audit Findings

| Component | Stage 14 Status | Stage 14.1 Identified Gaps & Required Hardening |
| :--- | :--- | :--- |
| **Source Reliability** | Computes scores and assigns Tier 1/2 even on sources with 0 articles. | Must enforce sample size threshold ($N \ge 5$); display `insufficient_evidence` status and avoid misleading authoritative percentages when evidence is inadequate. Add evaluation windows and claim outcome tracking. |
| **Geographic Coverage** | Aggregates all registered sources statically without time filtering. | Must introduce configurable measurement windows (24h, 7d, 30d, 90d); distinguish publisher jurisdiction (`country_code`) from event location (`geographic_scope`); separate registered sources from operationally active sources with recent ingestions. |
| **Coverage Gap Detection** | Flags missing countries/continents with general recommendations. | Must explain gap calculation methodology, support evidence data, and distinguish between "missing registered sources" vs "ingestion failure of registered sources". |
| **Source Diversity** | Checks wire service names in text and title hashes. | Must refine wire detection patterns (AP, Reuters, Bloomberg, AFP, BSS, UNB, PR Newswire, Business Wire); isolate wire syndications from independent reporting to prevent corroboration score inflation. |
| **Language Detection** | Fallbacks to English when confidence or word count is low. | Must output `unknown` classification when confidence is $< 0.60$ or text is ambiguous; benchmark against 6 target languages (`en`, `bn`, `ar`, `fr`, `de`, `es`) with edge cases (short, mixed, unicode, symbols). |
| **Translation Engine** | Translates via AI router with heuristic fallback. | Must visibly label machine-generated translations; ensure translations never overwrite original content; preserve named entities, numerals, and direct quotations. |
| **SSRF Defense** | Validates IPv4/IPv6 private CIDR and metadata (`169.254.169.254`). | Must block non-standard ports (SSH, SMTP, MySQL, Redis); handle alternate numeric IP formats (decimal integer, octal, hex) and redirect chains. |
| **Credentials & Multi-Tenant** | Encrypts credentials via Laravel `Crypt` (AES-256-CBC) and masks secrets. | Audit compatibility with `AICredentialVault`; ensure tenant isolation completely blocks cross-tenant source viewing, private article searching, and translation leakage (`403 Forbidden`). |

---

## 3. Validation Methodology & Verification Groups

### Group A: Real-Source & Connector Integration
- Execute real connector fetches against permitted public endpoints (e.g. Bangladesh Bank, arXiv scholarly atom feed, open regulatory/disclosure feeds).
- Test conditional requests (`ETag`, `If-Modified-Since`, HTTP 304 Not Modified).
- Test error handling, timeouts, malformed XML, and HTTP status handling.

### Group B: Complete Intelligence Pipeline Verification
- Track end-to-end execution of `ProcessIngestedArticleIntelligenceJob`.
- Verify idempotency locks (`Cache::lock`), retry exponential backoff (`[10, 30, 60]`), dead-letter observability, and correlation identifiers.
- Ensure normalized items flow into Story Clustering, Fact Extraction, and Unified Search Index.

### Group C: Multilingual Evaluation & Immutability
- Evaluate `LanguageDetectionService` against a manually reviewed dataset covering 6 languages plus edge cases.
- Confirm that `Article::title` and `Article::content` remain completely untouched when translated, storing machine translations exclusively in `article_translations`.
- Verify that translated articles display a machine-translated disclaimer badge.

### Group D: Reliability & Diversity Calibration
- Verify that sources with $< 5$ evaluated articles receive `status: insufficient_evidence` and low confidence indicators.
- Test that syndicated reprints from Reuters, AP, AFP, or BSS do not count as independent corroboration.

### Group E: Geographic Coverage & Freshness Windows
- Verify coverage metrics across 24h, 7d, 30d, 90d, and all-time windows.
- Ensure only sources with successful recent ingestions count as operationally active.
- Confirm separation of publisher jurisdiction from event location.

### Group F: Security Hardening & Tenant Isolation
- Execute security tests against SSRF bypasses: hex IPs, decimal integer IPs, octal IPs, unexpected ports, and redirect chains.
- Verify tenant boundary enforcement: Organization B user cannot access Organization A private sources, search private articles, or decrypt credentials.

---

## 4. Execution Schedule

1. **Phase 1:** Core service calibration (`SourceReliabilityService`, `GeographicCoverageService`, `CoverageGapDetectionService`, `SourceDiversityService`, `LanguageDetectionService`, `FeedCollectorService`).
2. **Phase 2:** Real-source verification command (`intelligence:validate-source-ingestion`) and live pipeline execution.
3. **Phase 3:** Frontend UI enhancements in `AdminSourcesView.vue` (sample size, measurement window selector, machine translation badges).
4. **Phase 4:** Comprehensive automated test suite (`Stage14_1SourceValidationAndReliabilityTest.php`) and full regression run (Stages 11–14.1).
5. **Phase 5:** Browser E2E verification across Scenarios A through U via Chrome DevTools MCP.
6. **Phase 6:** Technical documentation (18 files in `docs/`) and 26-section completion report.
