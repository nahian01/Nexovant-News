# Nexovant — Stage 13.1 Predictive Trend Forecasting & Holdout Backtesting

## 1. Overview & Statistical Principles

Predictive trend forecasting extrapolates empirical multi-week time-series observations using Ordinary Least Squares (OLS) regression with 95% confidence bounds.

Stage 13.1 implements **Walk-Forward Holdout Backtesting**: separating historical training periods from evaluation periods to empirically measure out-of-sample forecast accuracy, prediction interval coverage, and performance relative to a naive persistence baseline.

---

## 2. Walk-Forward Train/Test Holdout Methodology

When evaluating an empirical series of $N$ weekly observation points:

```
[Week 0] [Week 1] ... [Week N-5] │ [Week N-4] [Week N-3] [Week N-2] [Week N-1]
└─────────── Training Set ───────┘ │ └─────────── Holdout Test Set ─────────┘
            ($N - 4$ points)      │                (4 points)
```

1. **Training Split**: Fits OLS model parameters ($s_{train}$, $b_{train}$) and standard error of estimate ($SE_{train}$) on the training slice $[0, N - 5]$.
2. **Out-of-Sample Projections**: Projects point estimates $\hat{y}_{j}$ and 95% prediction interval bounds for each test period $j \in [N-4, N-1]$.
3. **Naive Persistence Baseline**: Projections from a naive baseline model where $\hat{y}_{naive} = y_{train, last}$ are calculated concurrently.

---

## 3. Backtesting Performance Metrics

The backtesting engine calculates and stores:

- **Out-of-Sample MAE**:
  $$MAE = \frac{1}{k} \sum_{j=1}^k |y_j - \hat{y}_j|$$
- **Out-of-Sample RMSE**:
  $$RMSE = \sqrt{\frac{1}{k} \sum_{j=1}^k (y_j - \hat{y}_j)^2}$$
- **Out-of-Sample MAPE (%)**:
  $$MAPE = \frac{1}{k} \sum_{j=1}^k \frac{|y_j - \hat{y}_j|}{\max(1.0, y_j)} \times 100\%$$
- **Out-of-Sample $R^2$**:
  $$R^2 = 1 - \frac{\sum (y_j - \hat{y}_j)^2}{\max(1.0, \sum (y_j - \bar{y}_{test})^2)}$$
- **Prediction Interval Coverage (%)**: Percentage of holdout actuals falling strictly within the calculated $[Lower_{95}, Upper_{95}]$ interval.
- **Naive Baseline Comparison**: Relative improvement over naive persistence:
  $$\Delta_{naive} = \frac{MAE_{naive} - MAE_{model}}{MAE_{naive}} \times 100\%$$

---

## 4. Insufficient Data Holdout Refusal Guardrail

Reliable walk-forward validation requires sufficient empirical periods to support both a meaningful training fit and a non-trivial holdout sample.

**Rule**: If the active non-zero historical period count is less than 8 ($N_{active} < 8$), the system **explicitly refuses** holdout backtesting:

```json
{
  "status": "insufficient_sample_for_holdout",
  "sample_size": 5,
  "min_required": 8,
  "message": "Sample size of 5 historical active periods is insufficient for robust walk-forward holdout validation (minimum 8 required). Reporting in-sample metrics only.",
  "metrics": null
}
```

This prevents misleading backtesting statistics from being reported when historical data volume is too sparse to evaluate.
