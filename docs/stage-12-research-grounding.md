# NEXOVANT — STAGE 12: RESEARCH GROUNDING & SYNTHESIS SPECIFICATION
## Grounded Briefs, 13 Structured Sections, Traceable Citations & Anti-Hallucination Guardrails

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 12  
**Component:** Grounded Research Brief Generator (`ResearchBriefService`)  
**Date:** November 2026  
**Status:** Implemented & Verified  

---

## 1. Grounded Research Principles

Conventional LLM question-answering frequently invents citations, misattributes quotes, or flattens conflicting reports into synthetic harmony. In contrast, Nexovant's `ResearchBriefService` enforces strict grounded synthesis:
1. **Fact Verifiability**: Every stated claim must resolve to a retrieved, verified database document.
2. **Traceable Attribution**: Superscript citations `[1]`, `[2]` link directly to canonical records in the citation appendix.
3. **Contradiction Transparency**: Divergent claims (e.g. differing revenue projections, conflicting yield rates, opposing regulatory stances) are prominently isolated rather than averaged out.
4. **Quantified Confidence**: The brief calculates an empirical confidence score ($0.0 - 1.0$) based on source credibility, corroborating breadth, and date recency.

---

## 2. The 13 Mandatory Grounded Sections

Every synthesized research brief contains 13 discrete sections:

```
+----------------------------------------------------------------------------------------------------+
| 1. Executive Summary               | High-level strategic takeaway and core decision thesis        |
| 2. Core Facts                      | Bulleted, verified factual statements established as true     |
| 3. Key Findings                    | Analytical interpretations and empirical conclusions          |
| 4. Corroborating Evidence          | Independent reporting outlets and confirming data points      |
| 5. Divergent Points & Contradictions | Unresolved discrepancies, conflicting stats, opposing claims|
| 6. Key Entities & Roles            | Companies, executives, government agencies, and associations  |
| 7. Timeline of Key Developments    | Chronological progression of events leading to current state  |
| 8. Underlying Technologies         | Technical specifications, architectures, standards, and nodes |
| 9. Market & Industry Implications  | Commercial market shifts, supply chain impact, pricing trends |
| 10. Geopolitical & Regulatory      | Export restrictions, antitrust investigations, policy shifts  |
| 11. Confidence & Evidence Ratings  | Empirical confidence score and uncertainty assessment         |
| 12. Future Outlook & What to Watch | Leading indicators, upcoming earnings, regulatory deadlines   |
| 13. Methodology & Source Citations | Exhaustive bibliography linking claim IDs to verified URLs    |
+----------------------------------------------------------------------------------------------------+
```

---

## 3. Citation Schema & Traceability

Citations are persisted in the `research_briefs.citations` JSON column:

```json
[
  {
    "citation_id": 1,
    "document_id": "a984f18d-...",
    "document_type": "article",
    "document_title": "TSMC 2nm High-NA Tool Delivery Finalized for Fab 20",
    "source_name": "EE Times",
    "source_url": "https://www.eetimes.com/tsmc-2nm-high-na-fab20",
    "excerpt": "TSMC completed installation of the first ASML EXE:5000 High-NA EUV scanner at its Baoshan R&D facility.",
    "verified": true,
    "credibility_score": 0.94
  }
]
```

When rendering in the frontend (`PersonalResearchView.vue`):
- Clicking any `[1]` citation opens a popover displaying the exact excerpt, source name, credibility rating, and verified checkmark.
- Users can click through to read the full article within Nexovant or visit the canonical external source URL.

---

## 4. Contradiction Detection Engine

When multiple sources report on the same event cluster or entity action, the engine inspects numerical quantities and stance:
- **Numerical Conflicts**: If Source A reports "$4.2 billion" and Source B reports "$3.8 billion", both figures are retained with explicit source attribution in Section 5.
- **Timeline Conflicts**: If reports disagree on deployment dates (e.g. Q4 2026 vs Q2 2027), the divergence is flagged.
- **Stance Discrepancies**: Official corporate statements vs investigative whistleblower reports are paired side by side.

---

## 5. Export Capabilities

The `ResearchBriefController` provides two export endpoints:
1. `GET /api/v1/workspace/research-briefs/{id}/export?format=markdown`:
   - Returns a formatted `# Markdown` publication complete with table of contents, bold callouts, tables, and citation appendix.
2. `GET /api/v1/workspace/research-briefs/{id}/export?format=json`:
   - Returns full structured JSON payload for programmatic ingestion by subscriber enterprise SIEM / BI dashboards.
