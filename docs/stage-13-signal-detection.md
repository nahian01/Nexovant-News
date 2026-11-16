# Nexovant Stage 13 — Intelligence Signal Detection
## Multi-Source Surveillance, Classification & Corroboration

**Document Version:** 1.0.0  
**Status:** Production Ready  

---

## 1. Objective

The Signal Detection Engine continuously scans incoming articles, corporate disclosures, regulatory gazettes, and technical announcements to identify verified developments across the technology ecosystem.

## 2. 12 Signal Categories

The engine classifies all incoming disclosures into 12 standardized categories:

1. **`technology_breakthrough`**: Verifiable technical milestones (e.g., sub-2nm test exposure, quantum annealing, backside power delivery).
2. **`supply_chain_disruption`**: Logistics bottlenecks, wafer shortages, fab halts, embargoes, or critical component delays.
3. **`regulatory_development`**: Antitrust investigations, export control bans, national security reviews, chips act subsidies.
4. **`market_shift`**: Structural pricing changes, margin compressions, sudden ASP shifts, market share movements.
5. **`corporate_strategy_change`**: Executive leadership transitions, major acquisitions, core technology pivots, fab capex reallocations.
6. **`conflicting_information`**: Official denials, contradicted executive statements, unresolved commercial disputes.
7. **`geopolitical_alignment`**: State-backed sovereign foundry programs, bilateral chip alliances, trade pacts.
8. **`executive_turnover`**: C-level appointments, key foundry architect departures, board resignations.
9. **`intellectual_property_dispute`**: Patent litigation, cross-licensing negotiations, trade secret actions.
10. **`standards_formation`**: Consortium protocol agreements (e.g., UCIe, CXL, 3GPP specifications).
11. **`capex_expansion`**: Multi-billion-dollar fabrication facility groundbreaking, cleanroom equipment purchase orders.
12. **`emerging_trend`**: Baseline default category for multi-source converging narratives.

---

## 3. Independent Source Counting & Syndication Filtering

A critical vulnerability in automated intelligence is "echo-chamber" inflation, where one wire release syndicated across 50 regional republishers is counted as 50 independent corroborating sources.

### Guardrail Implementation
- Leverages `SourceCorroborationService` (Stage 12).
- Extracts text fingerprints and computes Levenshtein/Jaccard similarity.
- Texts exceeding an 85% similarity threshold are classified as `is_syndicated_reprint = true`.
- Reprints are preserved as evidence items for tracking distribution reach, but **strictly excluded** from the `independent_sources_count`.
- Corroboration formula:
  $$\text{Independent Count} = 1 + |\{S_i \in \text{Sources} : \text{Similarity}(S_i, S_{\text{primary}}) < 0.85 \land S_i.\text{domain} \neq S_{\text{primary}}.\text{domain}\}|$$

---

## 4. Severity Calibration Matrix

Signals are assigned objective severity ratings according to verifiable parameters:

| Severity | Independent Sources | Min Credibility | Status Requirement | Breaking Flag |
| :--- | :--- | :--- | :--- | :--- |
| **Critical** | $\ge 3$ | $\ge 90.0\%$ | Verified / Confirmed | Required |
| **High** | $\ge 2$ | $\ge 80.0\%$ | Verified / Confirmed | Optional (or Breaking $\ge 70\%$) |
| **Medium** | $\ge 1$ | $\ge 60.0\%$ | Single Source / Verified | Optional |
| **Informational**| 1 | $< 60.0\%$ | Single Source | N/A |

---

## 5. 7-Day Rolling Deduplication

When a new article is ingested concerning an existing active signal:
1. The engine checks active signals within the past 7 days matching the category and shared entity/keyword roots.
2. If matched, the existing signal is updated:
   - `last_updated_at` refreshed.
   - `evidence_count` incremented.
   - `independent_sources_count` updated if new independent outlet.
   - Severity elevated if additional corroboration warrants upgrade.
3. If no match exists, a new `IntelligenceSignal` is generated.
