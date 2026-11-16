# Stage 14.1 — Source Reliability Calibration & Sample Size Guardrails

## 1. Reliability Scoring Formula & Bayesian Prior
Reliability cannot be assigned arbitrarily based on reputation alone. `SourceReliabilityService` calculates a calibrated score (0.0 to 1.0) derived from four empirically verifiable signals:
1. **Factual Accuracy Ratio ($w_1 = 0.40$)**: Proportion of published stories without retractions or verified dispute flags.
2. **Primary Source Ratio ($w_2 = 0.25$)**: Whether disclosures originate directly from filing entities or secondary aggregators.
3. **Transparency & Attribution Score ($w_3 = 0.15$)**: Bylines, clear sourcing, and named contributors.
4. **Peer Corroboration Rate ($w_4 = 0.20$)**: Independent verification by other distinct publishing organizations.

$$S_{\text{raw}} = 0.40 \cdot A + 0.25 \cdot P + 0.15 \cdot T + 0.20 \cdot C$$

## 2. Sample Size Guardrail ($N < 5$)
To prevent unearned high scores for newly added sources with minimal history:
- If a source has published fewer than 5 articles ($N < 5$):
  - `status` is explicitly set to `'insufficient_evidence'`
  - `confidence` is capped at `0.30`
  - A prominent disclosure warning is rendered in the UI:
    > "Sample size is insufficient (N < 5). Reliability scores are uncalibrated and subject to significant uncertainty."
- Only when $N \ge 5$ does the status advance to `'calibrated'`, `'established'`, or `'high_confidence'`.

## 3. Evaluation Periods & Historical Windows
The reliability service supports filtering by time windows:
- `last_30_days`: Reflects immediate, short-term editorial volatility.
- `last_90_days` (Default): Standard quarterly calibration period.
- `all_time`: Lifetime cumulative scoring.

## 4. UI Disclosure & Transparency Modal
In the Admin Sources Control Room, opening the Detailed Reliability Profile surfaces:
- Sample size ($N$ articles) and active measurement window.
- Verified claims count vs disputed claims count.
- Count of editorial corrections issued.
- Explicit methodology statement: *"Evidence-weighted Bayesian credibility with publication volume gate"*.
