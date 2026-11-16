# Stage 14.1 — Geographic Coverage Validation & Freshness Measurement Windows

## 1. Freshness Measurement Windows
Geographic coverage cannot rely on stale, historical records of sources that may have been decommissioned or ceased publishing.
`GeographicCoverageService` introduces dynamic freshness windows:
- `last_24_hours`: Immediate live intake coverage.
- `last_7_days`: Current week active sources.
- `last_30_days`: Rolling month intake reliability.
- `last_90_days`: Quarterly active network baseline.
- `all_time`: Lifetime cumulative source registry.

A source is counted as "actively contributing" to regional coverage in window $W$ only if:
$$\exists \text{ Article } A \text{ such that } A.\text{source\_id} = S.\text{id} \land A.\text{published\_at} \ge \text{now}() - W$$

## 2. Separation of Publisher Country vs Event Location
To prevent misleading geographic analytics:
- **Publisher Jurisdiction (`country_code`)**: Where the news outlet or regulatory agency is incorporated or operates (e.g., Bloomberg in US, BBC in UK).
- **Event Location (`geographic_scope`)**: Where the reported economic or geopolitical event actually took place (e.g. Bangladesh, Germany, Taiwan).
- Coverage metrics report both counts: `COUNTRIES (SOURCES / EVENTS) = 5 / 1`.

## 3. Coverage Gap Detection & Root Cause Classification
`CoverageGapDetectionService` detects regional intelligence deficiencies and explicitly classifies the root cause:
- **`missing_sources`**: The platform lacks registered or approved intelligence feeds for that continent or jurisdiction. Action: Onboard new regional sources.
- **`ingestion_failure`**: Sources exist for the region, but all feeds are unhealthy, failing HTTP requests, or experiencing SSL/network timeouts. Action: Remediate connector configuration.

## 4. UI Integration & Live Verification
Verified in `AdminSourcesView.vue`:
- Measurement window dropdown switcher (`All Time`, `Last 90 Days`, `Last 30 Days`, `Last 7 Days`, `Last 24 Hours`).
- Badge indication for root cause (`Missing Sources` vs `Ingestion Failure`).
- Calculation methodology and recommended source categories displayed inline.
