# Nexovant — Stage 13.1 Anomaly Detection Validation & Hardening

## 1. Overview & Statistical Methodology

The anomaly detection engine identifies statistical surges in reporting volume and claim contradictions over rolling baseline windows.

Stage 13.1 implements three critical hardening safeguards:
1. **Zero-Variance Baseline Safety ($\sigma = 0$)**: Prevents division by zero and infinite z-scores when historical baseline reporting is constant or zero.
2. **Small Sample Size Guardrails ($N < 5$)**: Refuses false precision on trivial fluctuations.
3. **Media Coverage Surge vs Underlying Real-World Event Classification**: Distinguishes superficial media republishing from verified event clusters.

---

## 2. Zero-Variance & Division Safety Floor

In standard z-score normalization:
$$z = \frac{X - \mu}{\sigma}$$

When historical reporting in the baseline window is zero or uniform across days, empirical standard deviation $\sigma = 0$. Dividing by zero produces `INF` or fatal runtime exceptions.

Stage 13.1 enforces a safe standard deviation floor:

```php
$stdDev = max(0.5, sqrt(max(0.25, (float) $expectedObserved)));
$deviationScore = round(($observedArticles - $expectedObserved) / $stdDev, 2);
```

This guarantees:
- If expected observed volume is 0 (subject never mentioned in historical baseline), standard deviation defaults to $0.5$, producing an interpretable, finite deviation score (e.g., $+12.0\sigma$ on 6 observations).
- Numerical outputs are guaranteed non-NaN and finite.

---

## 3. Media Coverage Surge vs Underlying Event Classification

A sudden increase in reporting frequency does not automatically signify a real-world event; it often reflects syndicated republishings of an unverified press release or viral rumor.

Stage 13.1 introduces `coverage_vs_reality_type` on `anomaly_observations`:

```php
$coverageVsRealityType = 'media_coverage_surge';
if ($signal) {
    $isCorroborated = in_array($signal->verification_status, ['corroborated', 'verified', 'reported_reliable'], true);
    if ($signal->independent_sources_count >= 3 && $isCorroborated) {
        $coverageVsRealityType = 'underlying_event_cluster';
    } elseif ($signal->independent_sources_count === 1 || $signal->verification_status === 'unverified') {
        $coverageVsRealityType = 'media_coverage_surge';
    }
}
```

### Classification Taxonomy

- `underlying_event_cluster`: $\ge 3$ independent publishers corroborate primary facts with verified wire or regulatory status. Indicates a physical or corporate development.
- `media_coverage_surge`: Single-source syndication or high publication volume without independent verification. Indicates narrative momentum rather than confirmed underlying shift.
- `contested_reporting_anomaly`: Elevated proportion of contradictory assertions across outlets ($> 25\%$ claim dispute rate).

---

## 4. Duplicate Observation Suppression

To prevent repetitive cron runs from creating duplicate anomaly records for the same subject within the same observation period, `AnomalyDetectionService` inspects existing records:

```php
$recentDuplicate = AnomalyObservation::where('subject_type', $subjectType)
    ->where('subject_name', $subjectName)
    ->where('indicator_name', 'reporting_frequency')
    ->where('detected_at', '>=', now()->subHours(12))
    ->first();

$isSuppressed = $recentDuplicate !== null;
```

Suppressed observations are persisted for auditability with `is_suppressed = true`, while user feeds and alert engines ignore suppressed duplicates.
