# NEXOVANT — STAGE 13 IMPLEMENTATION PLAN
## Intelligence Automation, Predictive Monitoring & Early-Warning System

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 13  
**Status:** In Progress — Architecture & Execution Roadmap  
**Target Repositories:** `nexovant-backend` (Laravel) & `nexovant-frontend` (Vue 3 + TypeScript + Vite)  
**Date:** November 2026  

---

## 1. Executive Mission & Strategic Goals

Stage 13 advances Nexovant from an on-demand search and retrieval system into a **proactive, automated intelligence monitoring and early-warning platform**. The platform continuously inspects incoming news articles, filings, disclosures, and story clusters to:
1. Detect emerging signals before they become mainstream consensus.
2. Quantify statistical anomalies in reporting velocity, topic distribution, and entity co-occurrence.
3. Correlate related developments across time, entities, and supply chains while strictly separating **correlation from causation**.
4. Issue transparent, evidence-backed early warnings across 4 severity tiers (`Informational`, `Watch`, `Elevated`, `Critical`) answering 9 essential questions.
5. Provide evaluated predictive trend forecasts with prediction intervals and explicit insufficient-data guardrails.
6. Enable analyst-in-the-loop human review and feedback to refine detection rules and reduce false positives.
7. Dispatch alerts seamlessly through the Stage 11 `NotificationEngine` without duplication.
8. Synthesize 12-section automated intelligence summaries with traceable citations.
9. Enforce strict multi-tenant isolation across all monitors, signals, forecasts, and reports.

---

## 2. Mandatory Codebase Audit & Infrastructure Reuse

To satisfy the engineering operating principles (extend, never duplicate; preserve Stages 1–12):

| Functional Need | Existing Component Reused | How Reused in Stage 13 |
|---|---|---|
| **Ingestion Pipeline** | `Article`, `StoryCluster`, `NewsSource` | Listens to ingestion events and evaluates incoming items against active monitoring rules. |
| **Corroboration & Deduplication** | `SourceCorroborationService` (Stage 12) | Distinguishes genuine independent corroborating outlets from syndicated wire reprints (>85% text similarity). Prevents artificial signal inflation. |
| **Entity Graph & Relationships** | `IntelligenceEntity`, `EntityRelationship`, `RelationshipGraphService` | Traces multi-degree entity connections and identifies new or changing relationships. |
| **Historical Events & Timelines** | `IntelligenceEvent`, `HistoricalTimelineService` | Links new signals to chronological event trajectories and historical analogies. |
| **Fact Extraction & Stance** | `ExtractedFact`, `ClaimEvidence` | Evaluates supporting vs contradictory claims when calculating warning confidence. |
| **Centralized Dispatch** | `NotificationEngine`, `NotificationEventDTO` (Stage 11) | Dispatches alerts across in-app, email, web-push, Telegram, and WhatsApp with quiet-hour checks. |
| **Subscriber Rule Models** | `SubscriberMonitor`, `SubscriberSavedSearch` | Serves as personal and organization monitoring rule definitions for automated scanning. |
| **RAG Knowledge & Citations** | `KnowledgeDocument`, `KnowledgeChunk`, `ResearchBriefService` | Grounds early-warning explanations and automated intelligence reports. |
| **Publication Engine** | `PublicationTemplate`, `Publication` (Stage 7B) | Renders formatted executive daily/weekly intelligence summaries. |

---

## 3. System Architecture & Service Layer

The Stage 13 service layer will be located in `app/Services/Automation/`:

```
+----------------------------------------------------------------------------------------------------+
|                                    NEXOVANT INGESTION PIPELINE                                     |
|                      (Articles, Discovered Stories, Clusters, Disclosures)                          |
+----------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+----------------------------------------------------------------------------------------------------+
|                                  INTELLIGENCE MONITORING ENGINE                                    |
|   +--------------------------------------------------------------------------------------------+   |
|   | IntelligenceMonitoringService: Orchestrates rule evaluation, entity checks, and pipelines |   |
|   +--------------------------------------------------------------------------------------------+   |
|         |                     |                      |                        |                    |
|         v                     v                      v                        v                    |
|   IntelligenceSignal   AnomalyDetectionService  EventCorrelationService EarlyWarningService        |
|   DetectionService     (Z-Scores, Baselines,    (Entity/Topic/Temporal, (4 Severity Tiers,         |
|   (12 Categories,      Moving Averages,         Strict Correlation !=   9-Point Explanation,       |
|    Indep. Sources)      Sample Guardrails)       Causation Labeling)     Lifecycle State Machine)  |
|         |                     |                      |                        |                    |
|         +---------------------+----------------------+------------------------+                    |
|                                                  |                                                 |
|                                                  v                                                 |
|   +--------------------------------------------------------------------------------------------+   |
|   | PredictiveTrendService: Time-Series Projections, Prediction Intervals, Insufficient Data   |   |
|   | IntelligenceImpactAssessmentService: 8-Dimension Structured Consequence Matrix             |   |
|   | AnalystReviewService: Human Review Workflow, Status Escalation, False-Positive Tuning      |   |
|   | AutomatedIntelligenceReportService: 12-Section Executive Briefs with Traceable Citations   |   |
|   | IntelligenceAlertService: Deduplicated Dispatch via Stage 11 NotificationEngine            |   |
|   +--------------------------------------------------------------------------------------------+   |
+----------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+----------------------------------------------------------------------------------------------------+
|                                    REST APIS & FRONTEND VIEWS                                      |
|   - Subscriber: MonitorsView, TrendsView, EarlyWarningsView, AutomatedReportsView                 |
|   - Admin: IntelligenceControlCenter, AnomalySettings, AnalystReviewQueue, DetectionRulesConfig    |
+----------------------------------------------------------------------------------------------------+
```

---

## 4. Database Schema (Migration `0001_01_01_000106`)

1. `intelligence_signals`: Stores detected intelligence signals with category, severity, evidence counts, independent source counts, verification status, and review status.
2. `signal_evidences`: Normalized evidence items (supporting, contradictory, refining) linking to articles, sources, and excerpts.
3. `anomaly_observations`: Statistical anomaly detections recording baseline period, observation period, observed vs expected values, z-scores, and sample sufficiency.
4. `correlated_events`: Links related developments with correlation type, strength, and explicit `is_causation_claimed = false` labeling.
5. `early_warnings`: Structured early warnings with 4 severity levels, 9-point transparent explanations, factor weights, and lifecycle states.
6. `early_warning_histories`: Audit trail of all warning status transitions (`under_analysis`, `confirmed`, `escalated`, `resolved`, `dismissed`).
7. `trend_forecasts`: Projections with forecast horizon, historical observations, prediction intervals, and explicit `is_supported` data sufficiency flags.
8. `intelligence_impact_assessments`: 8-dimensional structured consequence evaluations (Economic, Technological, Regulatory, etc.).
9. `analyst_reviews`: Human review feedback, false-positive markers, and rule tuning suggestions.
10. `automated_intelligence_reports`: 12-section daily/weekly executive summaries with citation appendices.
11. `intelligence_detection_rules`: Admin-configurable detection thresholds and escalation policies with full audit versioning.

---

## 5. Implementation Sequence

1. **Database Migration**: Create `0001_01_01_000106_create_intelligence_automation_and_early_warning_tables.php` and run `php artisan migrate`.
2. **Eloquent Models**: Implement typed models with relationships, casts, and tenant scoping.
3. **Core Services Layer (`app/Services/Automation/`)**:
   - `IntelligenceSignalDetectionService`
   - `AnomalyDetectionService`
   - `EventCorrelationService`
   - `EarlyWarningService`
   - `PredictiveTrendService`
   - `IntelligenceImpactAssessmentService`
   - `AnalystReviewService`
   - `IntelligenceAlertService`
   - `AutomatedIntelligenceReportService`
   - `IntelligenceMonitoringService`
4. **Artisan Automation Commands**:
   - `php artisan intelligence:evaluate-monitors`: Periodic monitoring and signal evaluation.
   - `php artisan discovery:evaluate-saved-searches`: Stage 12 follow-up for daily/weekly digest alerts.
   - `php artisan intelligence:generate-reports`: Automated daily/weekly report generation.
5. **Controllers & API Routes**:
   - `SignalController`: Public / subscriber signal feeds and details.
   - `EarlyWarningController`: Early-warning listing, explanations, and lifecycle updates.
   - `TrendForecastController`: Predictive forecasts, methodology, and evaluations.
   - `AutomatedReportController`: Executive summary generation, listing, and export.
   - `Admin\AnalystReviewAdminController`: Analyst review queue, status transitions, false-positive management.
   - `Admin\IntelligenceAutomationAdminController`: Detection rules, anomaly settings, queue health.
6. **Frontend Experience (`nexovant-frontend`)**:
   - `automation.service.ts`: Client API layer.
   - Upgrade `MonitorsView.vue` with active signals and early-warning badges.
   - Upgrade `TrendsView.vue` with predictive trend projections, confidence intervals, and data sufficiency flags.
   - Upgrade `IntelligenceControlCenter.vue` with early-warning overview, signal reviews, and rule configuration.
   - Add Early Warnings and Signal Review modal components.
7. **Verification & Testing**:
   - Comprehensive backend PHPUnit suite (`Stage13IntelligenceAutomationTest`).
   - Frontend TypeScript check (`vue-tsc --noEmit`) and production build.
   - Real browser E2E verification across Scenarios A through N via Chrome DevTools MCP.
8. **Documentation**:
   - Complete all 14 Stage 13 markdown specifications in `docs/`.
9. **Git Commit & Push**:
   - Commit and push to `origin/dev`.
10. **Final Execution Report**:
    - Comprehensive Stage 13 delivery audit.
