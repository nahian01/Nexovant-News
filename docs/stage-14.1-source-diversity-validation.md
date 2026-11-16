# Stage 14.1 — Source Diversity & Syndication Safeguards

## 1. Problem Statement: The Syndication Echo-Chamber
When wire agencies (such as Reuters, Associated Press, AFP, BSS, or UNB) release an article, hundreds of regional outlets republish the exact text or slight variations under their own bylines.
Without safeguards, a naive multi-source corroboration algorithm would count 15 syndicated reprints as 15 "independent corroborations," artificially inflating a story's credibility and anomaly severity.

## 2. Syndication Detection Logic
`SourceDiversityService` implements automated wire agency detection:
1. **Wire Credit & Attribution Extraction**: Inspects text and metadata for signatures:
   - `Reuters`, `Thomson Reuters`
   - `Associated Press`, `AP`
   - `Agence France-Presse`, `AFP`
   - `Bloomberg News`
   - `Bangladesh Sangbad Sangstha`, `BSS`
   - `United News of Bangladesh`, `UNB`
   - `PR Newswire`, `Business Wire`, `GlobeNewswire`
2. **Canonical Organization Mapping**: Reprints referencing the same originating wire agency are mapped to a single parent organization identifier (`reuters`, `ap`, `bss`, etc.).
3. **Exact Content Hash Collision**: Articles sharing identical or near-identical body hashes are classified as syndicated reprints.

## 3. Corroboration Inflation Prevention
When calculating cluster corroboration:
- **Rule**: Distinct reporting organizations only.
- If 10 outlets publish the same AP dispatch, `corroborating_sources_count` equals **1**, not 10.
- Corroboration score only increases when an independent organization (e.g. primary corporate filing or independent investigative publication) reports the event.

## 4. Empirical Test Verification
In `test_group_d_source_reliability_sample_size_guardrails_and_syndication_diversity`:
- Seeded three distinct publications:
  1. `Daily Financial Post` (reprinting Reuters)
  2. `Capital Metro Herald` (reprinting Reuters)
  3. `Global Wire Express` (reprinting Reuters)
- Evaluated diversity index and cluster corroboration:
  - Both wire articles were correctly identified as syndicated reprints (`is_syndicated: true`, `wire_agency: reuters`).
  - Corroborator count remained strictly **1**.
  - Corroboration inflation was completely prevented.
