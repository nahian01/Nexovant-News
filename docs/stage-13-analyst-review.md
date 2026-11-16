# Nexovant Stage 13 — Human Analyst Review Workflow
## False-Positive Marking, Heuristic Gating & Threshold Calibration

**Document Version:** 1.0.0  
**Status:** Production Ready  

---

## 1. Overview

While automated algorithms continuously process millions of tokens, human intelligence analysts maintain ultimate editorial authority. Stage 13 provides an analyst review workflow that enables domain experts to inspect detected signals, flag false positives, and calibrate detection thresholds with a complete audit trail.

---

## 2. Review Workflow Actions

Analysts in the Admin Control Room (`/admin/automation`) can triage pending signals and warnings into three states:

1. **`confirmed`**:
   - The signal represents genuine, verified technology intelligence.
   - Preserves active state and allows automated notification dispatch.
2. **`false_positive`**:
   - The signal was triggered by sensationalist phrasing, PR misdirection, or rumor re-reporting.
   - Marks signal as `review_status = 'false_positive'` and `status = 'dismissed'`.
   - Records reviewer ID and rationale.
3. **`calibrated`**:
   - Acknowledges the signal but identifies that detection sensitivity is too loose or overly broad.
   - Applies a sensitivity threshold adjustment ($\Delta \in [-0.10, +0.10]$) to the associated `IntelligenceDetectionRule`.

---

## 3. Feedback Loop & Threshold Calibration

When an analyst calibrates a rule:
1. `analyst_reviews` stores:
   - `reviewable_type` (`signal` or `early_warning`)
   - `reviewable_id`
   - `decision` (`confirmed`, `false_positive`, `calibrated`)
   - `reason` (mandatory explanation)
   - `threshold_adjustment` (e.g. $+0.05$ to raise credibility cutoff)
   - `rule_id`
2. `IntelligenceDetectionRule` is updated:
   $$\text{min\_credibility\_threshold}_{\text{new}} = \min(0.95, \max(0.50, \text{min\_credibility\_threshold}_{\text{old}} + \Delta))$$
3. Future automated evaluations under that category immediately adhere to the calibrated credibility floor, preventing recurring noise.
