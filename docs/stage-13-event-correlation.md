# Nexovant Stage 13 — Event Correlation & Intelligence Fusion
## Cross-Event Fusion, Alternative Hypotheses & Causation Governance

**Document Version:** 1.0.0  
**Status:** Production Ready  

---

## 1. Core Principle: Correlation $\neq$ Causation

In strategic enterprise intelligence, claiming causality without legal contracts or empirical proof is a severe epistemic error. For instance, if an antitrust review opens on the same day a corporation announces a fab delay, asserting that the antitrust inquiry caused the delay is speculative.

### Mandatory Governance Constraint
In all database records, API responses, and frontend displays:
$$\text{is\_causation\_claimed} = \text{false}$$
**Under no circumstances does the engine assert or record a direct causal link without verified legal contracts or binding documentation.**

---

## 2. Correlation Taxonomy

Events and articles are linked using four objective relationship categories:

1. **`precursor`**: A verifiable chronological predecessor involving the same entity or regulatory jurisdiction (e.g., EU Chips Act formal passage occurring 6 months before Dresden fab groundbreaking).
2. **`concurrent`**: Events occurring within a close temporal window ($\le 14$ days) sharing structural themes or supply-chain nodes.
3. **`consequent`**: A subsequent development following an earlier catalyst with temporal consistency.
4. **`parallel`**: Multi-entity simultaneous shifts across independent geographic markets without verified coordination.

---

## 3. Correlation Strength Scoring

The correlation strength score ($S \in [0.0, 1.0]$) is computed objectively:
$$S = 0.4 \cdot E_{\text{overlap}} + 0.3 \cdot K_{\text{overlap}} + 0.3 \cdot T_{\text{proximity}}$$

Where:
- $E_{\text{overlap}}$: Normalized entity intersection ($0.0$ to $1.0$).
- $K_{\text{overlap}}$: Keyword/topic semantic overlap ($0.0$ to $1.0$).
- $T_{\text{proximity}}$: Exponential time decay:
  $$T_{\text{proximity}} = \exp\left(-\frac{\Delta t}{30}\right)$$
  where $\Delta t$ is the difference in days.

---

## 4. Alternative Hypotheses & Unresolved Questions

Every correlation record must supply:
1. **Alternative Explanations:** Non-causal factors that could explain concurrent developments (e.g. parallel macroeconomic pressures, localized supply-chain frictions, seasonal capex budget cycles).
2. **Unresolved Questions:** Precise information gaps that prevent declaring a formal causal relationship (e.g. unreleased regulatory hearing transcripts, unexecuted vendor supply agreements).
