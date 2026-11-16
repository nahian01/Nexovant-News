# Nexovant Stage 13 — Statistical Anomaly Detection
## Moving Baselines, Z-Score Calculations & Sample Size Guardrails

**Document Version:** 1.0.0  
**Status:** Production Ready  

---

## 1. Overview

The Anomaly Detection Engine monitors volumetric and velocity anomalies across topics, corporate entities, and industrial technologies. Rather than relying on static keyword alert triggers, the system compares empirical observation rates over defined windows against historical rolling baselines.

---

## 2. Statistical Methodology

### 2.1 Baseline Windows
- **Long-term Baseline Window:** 30 days (default) or 90 days.
- **Short-term Observation Window:** 7 days (default) or 24 hours.

### 2.2 Mathematical Formulas

1. **Daily Baseline Rate ($\mu$):**
   $$\mu = \frac{\sum_{t \in T_{\text{baseline}}} C_t}{|T_{\text{baseline}}|}$$

2. **Daily Observation Rate ($X$):**
   $$X = \frac{\sum_{t \in T_{\text{obs}}} C_t}{|T_{\text{obs}}|}$$

3. **Sample Standard Deviation ($\sigma$):**
   $$\sigma = \sqrt{\frac{\sum_{t \in T_{\text{baseline}}} (C_t - \mu)^2}{|T_{\text{baseline}}| - 1}}$$

4. **Standardized Deviation Z-Score ($Z$):**
   $$Z = \frac{X - \mu}{\max(\sigma, 0.5)}$$

### 2.3 Anomaly Classification Threshold
An observation is formally classified as an empirical anomaly (`is_anomaly = true`) when:
$$|Z| \ge 2.0$$
representing an event more than 2 standard deviations away from historical norms (95.4% confidence threshold under Gaussian assumption).

---

## 3. Sample Size Sufficiency Guardrail

### The "Zero-Divisor / Small-Sample" Vulnerability
In sparse subject domains (e.g. quantum annealing, niche lithography chemistry), an entity may have 0 historical articles. A sudden occurrence of 1 article would produce an infinite or astronomically distorted percentage delta (+1000%).

### Enforced Guardrail
The engine evaluates the empirical sample size before computing deviations:
- If $\sum C_t < 5$ total articles across the baseline window:
  - `data_sufficiency = 'insufficient'`
  - `deviation_z_score = 0.0`
  - `is_anomaly = false`
  - `explanation` states explicitly: *"Insufficient baseline data (total observations < 5). Baseline too sparse to compute reliable statistical variance."*
- If $\sum C_t \ge 5$ total articles:
  - `data_sufficiency = 'sufficient'`
  - Full parametric $Z$-score evaluated and persisted in `anomaly_observations`.
