# Nexovant — Stage 13.1 Severity Standardization

## 1. Overview & Separation of Concerns

A fundamental architectural mandate in Nexovant is maintaining a strict operational distinction between:
1. **Signal Severity**: The objective magnitude, credibility, and reach of an observed factual event or reporting cluster.
2. **Early-Warning Threat Level**: The forward-looking organizational, industrial, or regulatory risk posed by the development.

---

## 2. Taxonomy & Allowed Values

| Concept | Allowed Values | Source Model | Evaluation Criteria |
|---|---|---|---|
| **Signal Severity** | `informational`<br>`medium`<br>`high`<br>`critical` | `IntelligenceSignal` (`severity`) | Objective facts: Independent source count, wire publisher tier, corporate capital scale, regulatory agency mandate. |
| **Early-Warning Level** | `informational`<br>`watch`<br>`elevated`<br>`critical` | `EarlyWarning` (`warning_level`) | Threat assessment: Factor breakdown, baseline reporting surge, cross-industry supply-chain exposure, systemic vulnerability. |

---

## 3. Mapping & Escalation Logic

The transformation from an ingested signal to an early-warning level follows deterministic rules implemented in `EarlyWarningService::evaluateSignalForWarning`:

```php
$warningLevel = match ($signal->severity) {
    'critical' => 'critical',
    'high' => 'elevated',
    'medium' => 'watch',
    default => 'informational'
};
```

### Threshold Rules

1. **Informational Gating**: Informational signals (`severity = 'informational'`) do NOT generate early warnings unless supported by $\ge 2$ independent corroborated sources.
2. **Automated Escalation**: If subsequent articles increase corroboration or credibility, elevating signal severity to `critical`, the linked early warning is automatically upgraded to `critical`, transitioning status to `escalated` and dispatching urgent alerts.
3. **De-escalation & Resolution**: Status transitions (`resolved`, `dismissed`) do not mutate the historical signal severity, ensuring historical audit trails remain immutable.
