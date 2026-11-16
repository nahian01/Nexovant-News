# NEXOVANT — STAGE 14
## Evidence-based Source Reliability & Quality Evaluation

### 1. Scoring Methodology

Nexovant does not assign static subjective scores. `SourceReliabilityService` evaluates source credibility dynamically based on four empirical vectors:

$$S_{\text{overall}} = (0.35 \times A_{\text{factual}}) + (0.25 \times R_{\text{corrob}}) + (0.20 \times P_{\text{primary}} \times 100) + (0.20 \times T_{\text{transparency}})$$

Where:
- $A_{\text{factual}}$: Factual Accuracy Score ($0 - 100$). Base accuracy (98% for regulatory bodies, 88% for standard outlets) penalized by $4\%$ per retracted claim or issued factual correction.
- $R_{\text{corrob}}$: Peer Corroboration Rate ($0 - 100\%$). Rate at which claims from this source are corroborated by independent non-syndicated peers.
- $P_{\text{primary}}$: Primary Source Ratio ($0.0 - 1.0$). Proportion of published items that represent original documents, official gazettes, or direct reporting versus wire reprints.
- $T_{\text{transparency}}$: Transparency & Attribution Score ($0 - 100$). Evaluates domain canonicalization, verifiable publisher identity, documented terms of use, and byline attribution.

### 2. Trust Tier System

| Tier | Name | Criteria | Typical Outlets |
|---|---|---|---|
| **Tier 1** | Official / Primary | Overall Score $\ge 90\%$ OR Official regulatory/academic body | Central Banks, Securities Regulators, arXiv, Official Gazettes |
| **Tier 2** | Established News | Overall Score $75\% - 89\%$ | Major international/national newspapers with editorial boards |
| **Tier 3** | Aggregator / Secondary | Overall Score $60\% - 74\%$ | News aggregators, industry blogs, trade syndications |
| **Tier 4** | Unverified / Emerging | Overall Score $< 60\%$ | Newly onboarded feeds with insufficient history |

### 3. Factual Corrections Audit Log

When a source issues a correction or retraction:
1. `SourceReliabilityService::recordCorrection(NewsSource $source, string $details)` increments `corrections_count`.
2. Appends timestamp and correction details to `corrections_history` inside `reliability_profile`.
3. Recalculates $S_{\text{overall}}$ and updates Tier classification.
4. Historical audit trail is preserved and visible in the Admin Control Center.
