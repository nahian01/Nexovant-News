# NEXOVANT — STAGE 14
## Ingestion Performance, Conditional Caching & Cost Optimization

### 1. HTTP Conditional Ingestion Caching

To scale across thousands of global sources without excessive bandwidth consumption or server rate-limiting:
- When a source response includes an `ETag` or `Last-Modified` header, it is persisted to `news_sources.etag` and `news_sources.last_modified_header`.
- Subsequent polls send `If-None-Match: <etag>` and `If-Modified-Since: <timestamp>`.
- If the publisher server returns `304 Not Modified`, the worker exits immediately with 0 bytes of content transfer and 0 database writes, saving upstream bandwidth and database I/O.

### 2. Translation Caching & Cost Safeguards

AI translation can become expensive if executed naively. Nexovant implements a 3-tier cost guard:
1. **Deduplication Check:** `TranslationService` checks `article_translations` table for `(article_id, target_language)` before invoking any model API.
2. **Passthrough for Native Targets:** When the detected language matches the target language, content is mirrored locally with 0 API tokens consumed.
3. **Lexical Normalization:** Repeated boilerplate phrases and wire service headers are normalized to reduce token counts.

### 3. Rate-Limiting & Backoff

- Every source enforces `rate_limit_per_minute` (default: 30–60 req/min).
- Automated health probes back off exponentially upon encountering `429 Too Many Requests` or `degraded` latency (> 3500ms).
- Health check polling is executed in batched worker queues rather than synchronous HTTP requests during page load.
