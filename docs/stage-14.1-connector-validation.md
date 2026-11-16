# Stage 14.1 — Connector Validation & Protocol Compliance

## 1. Protocol Architecture & Handshake Verification

### 1.1 HTTP Conditional Caching (ETag & Last-Modified)
`FeedCollectorService` verifies HTTP 304 handling to reduce network bandwidth and prevent redundant parsing:
- First Request: Outbound `GET` headers record server `ETag` and `Last-Modified`.
- Subsequent Request: Injects `If-None-Match: <etag>` and `If-Modified-Since: <timestamp>`.
- Server 304 Response: Connector short-circuits parsing, updates `last_polled_at` and `health_status: healthy`, and returns `not_modified: true` with zero downstream job dispatches.

### 1.2 REST API Connector Authentication & Header Forwarding
`RestApiSourceConnector` supports three secure authentication profiles:
- `bearer` / `bearer_token`: Transmits `Authorization: Bearer <decrypted_token>` without exposing secret in logs.
- `basic` / `basic_auth`: Base64 encoded `username:password` transmitted over TLS.
- `api_key`: Custom header forwarding (e.g. `X-API-KEY: <key>`).

### 1.3 Government Public Disclosure Connector
`GovernmentPublicDisclosureConnector` automatically adapts between REST JSON endpoints and XML feeds:
- Detects regulatory citations via robust regular expressions: `/(?:[A-Za-z0-9\-_]+\s+)?(?:docket|file|gazette|case|release|circular|notice)\s*no\.?\s*:?\s*([a-z0-9\-\/]+)/i`.
- Extracts official circular number (e.g., `BRPD Circular No. 12/2026`) into article metadata.
- Tags incoming disclosures with `is_primary_source: true` and `regulatory_jurisdiction: <country_code>`.

### 1.4 Academic Repository Connector
`AcademicRepositoryConnector` parses academic preprints and publications:
- Extracts preprints from arXiv Atom feeds and OAI-PMH structures.
- Parses authors, abstracts, DOI citations, and category tags (`q-fin`, `cs.AI`, `stat.ML`).
- Preserves verbatim abstract and author citations.

## 2. Connector Automated Unit & Feature Test Matrix

| Connector Class | Test Name | Ingestion Type | Result |
| :--- | :--- | :--- | :--- |
| `RssAtomSourceConnector` | `test_group_a_real_source_connectors_and_protocol_compliance` | XML (RSS 2.0 / Atom) | **PASSED** (200 OK + 304 Not Modified) |
| `RestApiSourceConnector` | `test_group_a_real_source_connectors_and_protocol_compliance` | JSON (Bearer Token) | **PASSED** (200 OK, Auth verified) |
| `GovernmentPublicDisclosureConnector` | `test_group_a_real_source_connectors_and_protocol_compliance` | Regulatory Circular XML | **PASSED** (Legal citation extracted) |
| `AcademicRepositoryConnector` | `test_group_a_real_source_connectors_and_protocol_compliance` | Atom Preprints XML | **PASSED** (Author and summary extracted) |
