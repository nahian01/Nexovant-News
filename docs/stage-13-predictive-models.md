# Nexovant Stage 13 — Predictive Models & Trend Forecasting
## Ordinary Least Squares (OLS) Extrapolation & Prediction Intervals

**Document Version:** 1.0.0  
**Status:** Production Ready  

---

## 1. Predictive Philosophy

Nexovant does not fabricate future events using unconstrained generative text models. Predictive forecasting is strictly grounded in statistical time-series trend extrapolation with explicit prediction intervals, documented assumptions, and statistical limitations.

---

## 2. Statistical Methodology: OLS Linear Trend

### 2.1 Time-Series Discretization
Historical observations over a defined window (e.g. 90 days) are bucketed into weekly aggregation intervals:
$$D = \{(t_i, y_i)\}_{i=0}^{N-1}$$
where $t_i \in \{0, 1, \dots, N-1\}$ is the period index and $y_i$ is the empirical observation volume.

### 2.2 OLS Parameter Estimation
$$\text{Slope } \beta = \frac{\sum_{i=0}^{N-1} (t_i - \bar{t})(y_i - \bar{y})}{\sum_{i=0}^{N-1} (t_i - \bar{t})^2}$$

$$\text{Intercept } \alpha = \bar{y} - \beta \bar{t}$$

### 2.3 Goodness of Fit & Error Metrics
1. **In-Sample Coefficient of Determination ($R^2$):**
   $$R^2 = 1 - \frac{\sum (y_i - \hat{y}_i)^2}{\sum (y_i - \bar{y})^2}$$
2. **Mean Absolute Error (MAE):**
   $$\text{MAE} = \frac{1}{N} \sum_{i=0}^{N-1} |y_i - \hat{y}_i|$$
3. **Root Mean Squared Error (RMSE):**
   $$\text{RMSE} = \sqrt{\frac{1}{N} \sum_{i=0}^{N-1} (y_i - \hat{y}_i)^2}$$

---

## 3. 95% Prediction Intervals

For future projection periods $t_h = N, N+1, \dots, N + H$:
1. **Point Estimate:**
   $$\hat{y}_h = \max(0, \alpha + \beta t_h)$$
2. **Standard Error of the Forecast ($SE_f$):**
   $$SE_f = \text{RMSE} \cdot \sqrt{1 + \frac{1}{N} + \frac{(t_h - \bar{t})^2}{\sum (t_i - \bar{t})^2}}$$
3. **95% Confidence Interval Bounds ($z_{0.975} \approx 1.96$):**
   $$\text{Lower Bound}_{95} = \max(0, \hat{y}_h - 1.96 \cdot SE_f)$$
   $$\text{Upper Bound}_{95} = \hat{y}_h + 1.96 \cdot SE_f$$

Notice that the interval widens as $t_h$ extends further into the future, reflecting increased uncertainty over extended horizons.

---

## 4. Insufficient-Data Guardrail

### Rule Specification
A predictive forecast is marked `is_supported = false` if:
1. Active periods with non-zero observations $< 5$.
2. Total observations across historical window $< 5$.

When triggered:
- `forecasted_values = []`
- `prediction_intervals = []`
- `data_sufficiency_status = 'insufficient_sample_size'`
- `limitations` states: *"Insufficient historical observations (< 5 active periods observed). Reliable predictive modeling requires empirical multi-period historical time-series."*
