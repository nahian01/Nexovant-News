# Nexovant — Stage 14 Implementation Plan
## Intelligence Platform Expansion, Source Network & Global Coverage

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 14  
**Date:** November 16, 2026  
**Status:** In Progress  

---

## 1. Executive Overview

Stage 14 expands Nexovant from an initial technology-focused collection system into a globally distributed, multilingual intelligence source network with rigorous source governance, automated connector ingestion, continuous health surveillance, evidence-based reliability profiling, geographic coverage management, coverage gap detection, and centralized source management.

---

## 2. Architecture & Components

```
                                  [Source Onboarding & Governance]
                                                 │
                                                 ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                CENTRALIZED SOURCE REGISTRY                             │
│   (NewsSource: RSS, Atom, REST API, Gov Disclosures, Academic, Private Org Sources)   │
└──────────────────────────────────────┬─────────────────────────────────────────────────┘
                                       │
                                       ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                             EXPANDED INGESTION CONNECTORS                              │
│   (SourceConnectorInterface: RssAtom, RestApi, GovPublicDisclosure, AcademicPreprint)  │
│   - Conditional HTTP (ETag / If-Modified-Since)                                       │
│   - SSRF Protection (DNS verification, loopback/private/metadata IP blocks)           │
│   - Safe XML Processing (LIBXML_NONET, entity injection prevention)                   │
└──────────────────────────────────────┬─────────────────────────────────────────────────┘
                                       │
                                       ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        SOURCE HEALTH & RELIABILITY EVALUATION                          │
│   - SourceHealthService (Availability, Latency, HTTP Status, Failures, Health Status) │
│   - SourceReliabilityService (Factual Accuracy, Primary Source, Independence Rate)    │
│   - SourceDiversityService (Syndication Ratio, Wire Reprints vs Original Reporting)    │
└──────────────────────────────────────┬─────────────────────────────────────────────────┘
                                       │
                                       ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                      MULTILINGUAL PROCESSING & INTELLIGENCE                            │
│   - LanguageDetectionService (en, bn, fr, de, es, ar)                                  │
│   - TranslationService (Preserves names, entities, numbers, quotes, citations)        │
│   - ArticleTranslation Storage (Separate from immutable original text)                │
└──────────────────────────────────────┬─────────────────────────────────────────────────┘
                                       │
                                       ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                     GEOGRAPHIC COVERAGE & COVERAGE GAP DETECTION                       │
│   - GeographicCoverageService (Continent, Country, Region, Publisher vs Event Loc)    │
│   - CoverageGapDetectionService (Under-represented Regions, Topics, Languages)        │
└──────────────────────────────────────┬─────────────────────────────────────────────────┘
                                       │
                                       ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                   SOURCE MANAGEMENT CONTROL CENTER (Frontend SPA)                      │
│   (AdminSourcesView: Registry, Onboarding Queue, Testing Modal, Health, Coverage, Gaps)│
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Work Breakdown & Phased Execution

### Phase 1: Database Foundation & Model Extensions
- Migration `0001_01_01_000108_create_source_network_and_multilingual_tables.php`:
  - Extend `news_sources`: `canonical_domain`, `country_code`, `continent`, `supported_languages`, `publication_frequency`, `api_endpoint`, `auth_type`, `encrypted_credentials`, `editorial_status`, `onboarding_notes`, `attribution_requirements`, `terms_of_use_url`, `reliability_profile`, `last_health_check_at`, `last_response_time_ms`, `last_http_status`, `organization_id`.
  - Extend `articles`: `original_language`, `detected_language`.
  - Create `article_translations`: `id`, `article_id`, `original_language`, `detected_language`, `target_language`, `translated_title`, `translated_content`, `translated_summary`, `translation_provider`, `translation_model`, `quality_confidence_score`, `status`, `translated_at`.
  - Create `source_health_logs`: `id`, `source_id`, `check_type`, `status`, `http_status`, `response_time_ms`, `error_message`, `checked_at`.
- Update Models: `NewsSource`, `Article`, create `ArticleTranslation`, `SourceHealthLog`.

### Phase 2: Connectors & Ingestion Architecture
- `App\Services\Ingestion\Connectors\SourceConnectorInterface`
- `RssAtomConnector`: RSS 2.0, Atom 1.0, RDF feeds with ETag and Last-Modified conditional queries.
- `RestApiSourceConnector`: JSON endpoints with bearer tokens, API key headers, basic auth.
- `GovernmentPublicDisclosureConnector`: Official regulatory publication feeds with legal citations.
- `AcademicRepositoryConnector`: Scientific/academic archive endpoints with DOI and author metadata.
- `SourceConnectorManager`: Resolves connector by source type.

### Phase 3: Source Onboarding & Health Surveillance
- `SourceOnboardingService`: Lifecycle management (`discovered`, `pending_review`, `approved`, `active`, `temporarily_suspended`, `rejected`, `archived`), validation, SSRF checks, admin approval.
- `SourceHealthService`: HTTP pings, latency tracking, health status classifications (`healthy`, `degraded`, `unavailable`, `auth_required`, `rate_limited`, `format_changed`, `suspended`), alert dispatching.

### Phase 4: Reliability, Diversity & Coverage Gap Services
- `SourceReliabilityService`: Factual accuracy history, primary-source indicators, transparent scoring.
- `SourceDiversityService`: Syndication detection, wire reprint clustering, independent corroboration count.
- `GeographicCoverageService`: Regional and continental distribution analytics.
- `CoverageGapDetectionService`: Algorithmic detection of under-represented countries, languages, and topics.

### Phase 5: Multilingual Processing & Cross-Language Discovery
- `LanguageDetectionService`: Multilingual text classification.
- `TranslationService`: Separate translation storage preserving entities, quotes, numbers, and original text.
- Extend `UnifiedSearchService`: Search across translations with language filtering and attribution indicators.

### Phase 6: Source Management Control Center (Frontend SPA)
- Update `AdminSourcesView.vue` with:
  - 5 comprehensive tabs: Registry, Onboarding Queue, Source Health, Coverage Analytics & Gaps, Source Diversity.
  - Connector Testing Modal with sample item preview, latency metrics, and SSRF status.
  - Onboard New Source Modal with credential encryption support.
  - Source Reliability Profile inspection.
- Update `admin.service.ts` with Stage 14 API endpoints.

### Phase 7: Verification & Testing
- Unit & Feature Tests: `tests/Feature/Stage14SourceNetworkAndGlobalCoverageTest.php`.
- Full regression suite across Stages 11, 12, 13, 13.1, 14.
- Chrome DevTools MCP browser E2E verification across Scenarios A through P.
- Complete documentation files in `docs/`.
