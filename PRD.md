# Nexovant Product Requirements Document

**Document status:** Draft for product and engineering alignment  
**Product:** Nexovant Global Executive Intelligence Platform  
**Version:** 1.0  
**Prepared:** 14 November 2026  
**Source of truth:** Current Nexovant repository, README, route map, API controllers, data models, seed data, and service configuration

> This PRD describes the intended product and acceptance criteria. The implementation notes identify areas that are currently present as working application flows, prototypes, or scaffolding. A visible screen or API route does not by itself mean that the underlying capability is production complete.

## 1. Product summary

Nexovant is a global business intelligence service for executives, analysts, investors, and strategy teams. It monitors corporate and market developments, gives each item a visible verification status, connects it to related entities and events, and explains why it may matter and what to monitor next.

The product combines a public intelligence feed with an authenticated subscriber workspace. Paid workspaces add personalized monitoring, saved intelligence, question answering over a source-grounded knowledge base, downloadable briefings, billing, and team collaboration. Editorial and platform administrators manage content quality, sources, subscriptions, providers, and system operations.

**Product promise:** Move from a headline to a sourced, decision-useful understanding of the event and its likely implications.

## 2. Problem and opportunity

Business decision-makers receive more corporate, regulatory, market, and social information than they can reliably assess. Conventional news coverage often separates the headline from the source evidence, related entities, second-order impacts, and follow-up signals. Analysts therefore spend time reconciling sources and reconstructing context before they can act.

Nexovant aims to reduce that work by combining source-aware reporting, structured analysis, entity relationships, monitoring, and executive-ready summaries in one workspace.

## 3. Vision, goals, and non-goals

### Vision

Become a trusted intelligence workspace that helps decision-makers understand material developments earlier and with better evidence, context, and follow-through.

### Product goals

1. Make important developments easy to discover and scan across companies, executives, sectors, regions, and themes.
2. Make evidence quality visible and distinguish confirmed information from developing or unverified claims.
3. Give every material story a consistent strategic analysis, including implications and next signals.
4. Let subscribers monitor entities and topics relevant to their work.
5. Provide useful, cited answers and briefings from an access-controlled intelligence corpus.
6. Support a sustainable subscription business with individual and organizational plans, billing, and operational review tools.
7. Give editors and administrators the controls needed to maintain quality, manage access, and operate the platform.

### Non-goals for the initial product

- Replacing licensed market-data terminals, financial advice, legal advice, or investment research.
- Executing trades, providing price targets, or guaranteeing the outcome of a business decision.
- Publishing unverified social content as established fact.
- Offering an unrestricted general-purpose chatbot unrelated to Nexovant’s evidence base.
- Supporting automated card processing until a payment provider is explicitly enabled and approved.
- Claiming complete global coverage before source availability and coverage quality are measured.

## 4. Users and roles

| Persona | Needs | Primary capabilities |
|---|---|---|
| Public reader | Understand what Nexovant covers and inspect available intelligence | Public feed, story pages, search, categories, trends, marketing and newsletter pages |
| Individual subscriber / executive | Follow material developments relevant to their decisions | Personalized feed, watchlists, saved stories, alerts, analysis, Q&A, reports, subscription management |
| Organization member | Share intelligence in a company workspace with appropriate access | Organization context, shared or personal monitoring, team publications, role-based access |
| Organization owner / administrator | Manage team membership, seats, and organizational access | Invitations, membership roles, seat visibility, billing coordination |
| Editor / analyst | Review evidence and prepare accurate, consistent intelligence | Editorial queue, source inspection, verification, article approval, cluster management |
| Platform administrator / operator | Keep content, accounts, billing, AI services, and platform settings healthy | Admin dashboard, source and pipeline controls, payment review, AI gateway, RAG knowledge, users, audit logs, system settings |

**Authorization requirement:** Application roles and organization roles must be enforced by the backend on every protected operation. Hiding a control in the UI is not sufficient authorization.

## 5. Product principles

1. **Evidence before synthesis.** Show source provenance and verification status beside the claim it supports.
2. **Separate fact from interpretation.** Clearly distinguish confirmed facts, attributed reporting, analysis, and forward-looking scenarios.
3. **Explain uncertainty.** Make developing, disputed, and unverified information legible without overstating confidence.
4. **Useful at a glance, detailed on demand.** Support scanning in the feed and deeper examination in the story dossier.
5. **Every answer should be traceable.** AI-generated responses and reports must link their claims to eligible source material.
6. **Respect tenant boundaries.** Organization-private knowledge and user data must never leak across workspaces.
7. **Accessible on desktop and mobile.** Navigation, filters, forms, tables, and dialogs must remain usable at narrow widths and with assistive technology.

## 6. Product scope and requirements

Priorities: **P0** required for a trustworthy paid release; **P1** important follow-on; **P2** later expansion. Each requirement below defines target behavior, even where the current repository only contains a prototype.

### 6.1 Public intelligence discovery

**FR-001 — Global intelligence feed (P0)**  
Readers can browse published stories ordered by publication time or editorial relevance. Each card shows a concise headline, publication time, category, source context, verification status, and associated companies or executives when available.

**Acceptance criteria**

- The feed supports pagination or an equivalent incremental-load pattern and preserves filter state while paging.
- Loading, empty, unavailable, and populated states are distinct and understandable.
- A story marked breaking is visually prominent and links to its detail view.
- Counts and status labels are derived from the currently returned data; sample values are not presented as live platform metrics.

**FR-002 — Categories and filters (P0)**  
Readers can filter by category, verification status, breaking status, and supported entity filters. Filters can be combined where applicable and can be cleared.

**Acceptance criteria:** The results and visible filter state agree; changing filters refreshes results; an empty filter result provides a clear reset path.

**FR-003 — Story dossier (P0)**  
Each published intelligence item has a detail page with source information, normalized summary, entity links, chronology, and the structured analysis dimensions below.

The dossier must support these ten questions when relevant source material exists:

1. What happened?
2. When did it happen?
3. Who is involved?
4. Why did it happen?
5. What is the business significance?
6. Who may be affected?
7. What are the potential consequences?
8. What is the verification status and supporting evidence?
9. What related events provide context?
10. What should decision-makers monitor next?

**Acceptance criteria:** Missing analysis is shown as unavailable or pending, never filled with invented facts. Each material claim is attributed to one or more accessible source records. The page separates observed facts from interpretation and forward-looking analysis.

**FR-004 — Source coverage and provenance (P0)**  
Readers can inspect the source records behind an article, including publisher, source type, original headline, publication time, canonical URL, and whether a source contributes new information.

**Acceptance criteria:** Source counts and records are supplied by the service; placeholder publishers or source counts are not shown as real evidence. Broken or unavailable source links are identified.

**FR-005 — Global search (P0)**  
Search covers articles, companies, executives, and trends. Autocomplete suggestions are available after a short query, and a full results page groups matches by entity type.

**Acceptance criteria:** Search input is debounced; keyboard and screen-reader users can use suggestions; no-result and service-error states are distinct; selecting a suggestion opens the correct entity.

**FR-006 — Company and executive intelligence (P1)**  
Users can inspect entity profiles, related stories, relevant events, and leadership context. Authenticated users can follow or unfollow entities and manage alert preferences.

**FR-007 — Trend radar (P1)**  
Users can inspect emerging topics, mention or activity velocity, time-series history, cross-platform source coverage, linked entities, and verification records.

**Acceptance criteria:** Each metric displays its measurement window, source coverage, and last-updated time. A trend score is not described as market sentiment or fact verification unless that is the defined metric.

### 6.2 Accounts and personalized workspace

**FR-008 — Account registration and access (P0)**  
Users can create an individual or organization-associated account, sign in, sign out, and retrieve their account profile. Suspended accounts cannot sign in. Authentication state survives page navigation and is cleared on logout or invalid credentials.

**Acceptance criteria:** Input validation is server-side; passwords are securely hashed; sessions/tokens are invalidated appropriately; protected pages redirect to sign-in and return the user to the requested page after successful authentication.

**FR-009 — Preferences and onboarding (P1)**  
Users can record preferred industries, digest frequency, breaking-alert preference, and briefing preference. Onboarding helps users choose initial topics and entities.

**FR-010 — Following, saved stories, and feedback (P0)**  
Subscribers can follow companies and executives, save and unsave stories, request more or less similar items, hide a story, and report an issue.

**Acceptance criteria:** These actions persist to the correct user, update the interface immediately, and are reversible where applicable. The saved view returns only that user’s saved items.

**FR-011 — Subscriber dashboard (P0)**  
The dashboard summarizes the user’s actual monitored developments, watchlist activity, alerts, and available briefings. It links to the relevant story, watchlist, trend, or report view.

**Acceptance criteria:** Dashboard metrics are backed by live account/service data and expose their time period. Static demonstration content is explicitly identified as a demo until connected to production data.

**FR-012 — Notifications (P1)**  
Users receive in-product notifications for configured breaking developments, followed-entity events, and relevant workspace activity. Users can mark items read and open the related object.

**FR-013 — Responsive application shell (P0)**  
Public, subscriber, and admin navigation remains reachable on supported mobile screens. Menus and drawers support touch, keyboard, focus management, and dismissal.

### 6.3 AI question answering and reports

**FR-014 — Source-grounded intelligence Q&A (P0 for paid AI tier)**  
Subscribers can ask a question over content they are authorized to access. The system retrieves relevant records, creates an answer using an eligible AI provider, and returns citations, retrieval status, model/provider metadata, and applicable usage information.

**Acceptance criteria**

- Every material factual claim has a citation to a retrieved record with a title, source, date, and link or document reference.
- The system says when evidence is insufficient and does not fabricate citations or claim certainty without evidence.
- Retrieval enforces public/private and organization-tenant access rules before model invocation.
- A provider failure is reported or safely retried; fallback behavior is recorded and does not silently return a generic answer as if it were retrieved evidence.
- Queries, citations, and usage are logged under the correct tenant with an appropriate retention policy.

**FR-015 — Executive briefings and PDF publications (P0)**  
Users can browse available daily, weekly, company, and custom publications. Eligible users can download a generated PDF with title, issue date, overview, sourced developments, verification labels, and an AI-use disclosure.

**Acceptance criteria:** A generated report uses the selected user/workspace scope, respects publication permissions, and includes only available evidence. Download links are authenticated or time-limited as appropriate. Generation failures are visible and retryable.

**FR-016 — AI provider routing and safeguards (P0 for operating AI features)**  
The platform routes supported tasks to configured providers using task capability, availability, quota, cost, and administrator priority. It supports bounded retries/fallback, circuit breaking, quota tracking, and per-request usage/cost logs.

**FR-017 — AI transparency and cost controls (P1)**  
Administrators can inspect active provider/model, capability, quota headroom, circuit state, recent failures, estimated usage, and configured priority. Sensitive credentials are never displayed after entry.

### 6.4 Subscription, payments, and teams

**FR-018 — Plan and entitlement discovery (P0)**  
Visitors and subscribers can compare active plans, monthly/annual pricing, supported currencies (BDT and USD), and explicit feature entitlements.

**FR-019 — Subscription checkout and invoice lifecycle (P0)**  
An authenticated user can select a plan, billing cycle, and currency. The service creates a subscription/invoice in a clear state. Free plans activate immediately; paid plans remain pending until payment is verified or a supported gateway confirms payment.

**FR-020 — Manual payment submission and review (P0 for manual-payment launch)**  
Users can view enabled payment methods and instructions, submit a transaction/reference ID and sender account, and see the review status. Administrators can inspect the invoice and proof, approve or reject it with notes, and produce an auditable status change.

Supported local rails in the current product definition: bKash, Nagad, and bank transfer. Card processing remains out of scope while Stripe or another gateway is disabled.

**Acceptance criteria:** Duplicate transaction references are flagged; payment approval is transactional and updates the invoice/subscription consistently; users see a confirmation or actionable rejection reason; all review actions are audited.

**FR-021 — Organization workspace (P1)**  
An organization owner can view organization details and seat allocation, invite members, assign a permitted role, and revoke or change access. Invitees can securely accept or decline an invitation.

**FR-022 — Tenant entitlements (P0 for organization plans)**  
Organization plan features, seat limits, private knowledge, and reports are enforced on the server. A user can belong to multiple organizations only if the role and tenant selection model is defined.

### 6.5 Editorial, administration, and operations

**FR-023 — Editorial review (P0)**  
Editors can inspect pending articles and provenance, approve or reject with notes, and view review state and actor. Only approved content is presented as published intelligence.

**FR-024 — Story clustering (P1)**  
Authorized editors can inspect related source items, merge duplicate clusters, split misgrouped items, and preserve the audit trail and source-level evidence.

**FR-025 — Source registry (P0)**  
Administrators can create, edit, test, enable, and disable sources; manage source type, feed URL, credibility metadata, and rate limits; and see the last fetch result and freshness.

**FR-026 — Ingestion and automation monitoring (P0)**  
Operators can inspect pipeline stages, job status, source/target, timestamps, model/provider usage, cost, error, and retry/cancel controls. Reprocessing must be idempotent and must not publish duplicate stories.

**FR-027 — Platform dashboard (P1)**  
Administrators can inspect sourced operational metrics for users, subscriptions, pending payments, editorial queue, trends, provider health, job queue, and recent audited activity. Every metric shows its time window and source.

**FR-028 — User and role administration (P0)**  
Authorized administrators can find users, inspect account and organization associations, suspend/reactivate an account, and assign only permitted roles. Sensitive role changes require an audit record.

**FR-029 — Knowledge-base operations (P1)**  
Administrators can inspect indexed documents, chunk counts, indexing state, errors, and cache status; trigger reindexing; and clear cache with confirmation and an audit event.

**FR-030 — System settings and audit logs (P1)**  
Authorized administrators can edit supported configuration and inspect immutable audit events with actor, action, resource, timestamp, outcome, and relevant request context.

**FR-031 — Sponsored content administration (P2)**  
Administrators can manage campaign state and view campaign delivery metrics. Sponsored material must be clearly labeled and must not influence verification status or editorial ranking without an explicit, disclosed policy.

## 7. Key user journeys

### Journey A: Public reader to informed reader

1. Reader opens the global feed or marketing page.
2. Reader filters a category or searches for an entity.
3. Reader opens a story and reviews its status, sources, analysis, and related events.
4. Reader can continue to a company, executive, trend, or related story.
5. Reader can opt into a daily or weekly newsletter with explicit consent.

### Journey B: Subscriber establishes a personal signal set

1. User registers, signs in, and completes preference onboarding.
2. User follows relevant entities/topics and saves useful stories.
3. User sees a personalized dashboard and feed.
4. User receives configured alerts and inspects their cited context.
5. User can ask a source-grounded question or open a generated briefing.

### Journey C: Subscriber purchases access

1. User compares plans and entitlements.
2. User selects cycle and currency and receives an invoice.
3. User follows payment instructions and submits a transaction reference.
4. Administrator verifies or rejects the proof with a reason.
5. On approval, the service updates subscription, invoice, entitlements, and audit history; the user sees the result.

### Journey D: Editor publishes a reliable development

1. A new source item enters the monitored ingestion pipeline.
2. The pipeline normalizes and links entities, detects possible duplicates, and attaches source metadata.
3. Analysis and verification are prepared with clear evidence references and uncertainty.
4. An editor reviews provenance and analysis, then approves, edits, or rejects.
5. Approved content appears in feed/search and can trigger configured alerts and reports.

### Journey E: Operator handles a degraded AI provider

1. Operator sees provider health, quota/circuit state, and recent failures.
2. Routing uses an eligible fallback or pauses the affected task safely.
3. The system records provider attempts, latency, cost, and outcome.
4. Operator can adjust provider availability/priority and review the impact.

## 8. Information architecture

### Public

- `/` and `/feed`: global intelligence feed
- `/news/:slug`: story dossier
- `/search`: grouped global search results
- `/companies`: company discovery
- `/trends`: trend radar
- `/blog` and `/blog/:slug`: editorial analysis and detail
- `/landing`: product, methodology, verification, and pricing
- `/login`, `/register`: account access

### Subscriber

- `/dashboard`: personalized briefing workspace
- `/saved`: saved intelligence
- `/reports`: publications and downloads
- `/billing`: plan, invoices, and manual payment
- `/organization`: team workspace

### Administration

- Dashboard overview
- Articles and editorial review
- Story clusters
- News sources
- Automation pipeline
- Social intelligence
- AI router and gateway
- RAG knowledge center
- Users and organizations
- Payment review
- Advertising
- Reports and publications
- System configuration
- Security and audit logs

## 9. Data domains

The repository currently models or references these product data domains:

- **Identity and access:** users, account types, organizations, organization members, invitations, roles/permissions, personal access tokens.
- **Commercial:** plans, entitlements, subscriptions, subscription history, invoices, payment methods/configuration, payments, payment proofs.
- **Intelligence content:** news sources, categories, articles, summaries, assessments, events, timelines, companies, aliases, executives.
- **Personalization:** user preferences, followed companies/executives, saved articles, interaction signals, notifications.
- **Social intelligence:** topics, trends, metrics, sources, verification records.
- **Knowledge and AI:** knowledge documents/chunks/embeddings, semantic cache, provider/model credentials and quotas, routing logs.
- **Publications and operations:** publications, agent runs, audit logs, newsletter subscribers, queued work.

Every tenant-owned record must have an explicit ownership and access rule. Public content, private organization material, and personal user data must not be conflated.

## 10. External systems and dependencies

| Dependency | Product purpose | Requirement |
|---|---|---|
| News and regulatory sources | Ingest source records and verify developments | Maintain source attribution, license/usage rules, freshness, and source health |
| AI providers (configured set includes Anthropic, OpenAI, Google, Groq) | Summarization, classification, extraction, reasoning, and Q&A | Provider credentials secured; model use logged; fallback and budget safeguards applied |
| PostgreSQL with pgvector in deployment configuration | Relational records and vector retrieval | Tenant-aware access, backup, restore, migration, and index lifecycle |
| Email service | Newsletter, account lifecycle, invitations, and alerts | Consent, unsubscribe, deliverability, retry, and suppression handling |
| Payment rails | Local manual transfer initially; card gateway only when enabled | Reconciliation, idempotency, review, and status audit |
| Object storage (Cloudflare R2 in deployment scripts) | Store publications and encrypted backups | Private objects, scoped access, lifecycle/retention, and restore verification |
| Nginx / Docker Compose | Service routing, rate limits, operations | TLS at deployment edge, health checks, internal service isolation, safe rollout |

## 11. Non-functional requirements

Targets below are proposed release criteria and must be measured in the production-like environment before being treated as an SLA.

### Performance and availability

- Public feed and profile read APIs: target p95 response under 2.5 seconds, excluding client/network time.
- Search results: target p95 under 3 seconds for standard queries.
- Source-grounded AI Q&A: provide visible progress and target p95 under 20 seconds; return an explicit timeout/degraded state when exceeded.
- PDF generation: target p95 under 30 seconds for the standard briefing size.
- Initial service availability target: 99.5% monthly for public feed and authenticated workspace APIs.
- Source freshness targets must be configured per source; the UI displays the last successful fetch rather than implying real-time coverage.

### Security and privacy

- Enforce least-privilege role and tenant authorization server-side.
- Use TLS for external traffic and secure service-to-service credentials for internal AI calls.
- Store secrets in deployment secret management; remove development defaults from production configuration.
- Apply rate limits and abuse controls to authentication, search, newsletter, and AI endpoints.
- Protect authentication against session fixation, token leakage, brute force, and account enumeration.
- Encrypt sensitive data in transit and at rest where supported; restrict payment proof visibility.
- Log administrative and billing actions without logging passwords, provider secrets, or unnecessary personal data.
- Define retention, deletion, backup, and incident response policies for user, query, and source data.
- Validate and sanitize source content and user-submitted text; protect generated HTML/PDF from injection.

### Accessibility and usability

- Meet WCAG 2.2 AA for primary flows: feed, story dossier, search, authentication, billing, organization, and admin review.
- Full keyboard operation, visible focus, semantic headings/landmarks, labels, error association, and screen-reader announcements are required.
- Support 360px mobile width through desktop, reduced-motion preferences, and browser zoom without losing core actions.
- Tables, dialogs, menus, and bottom navigation remain operable on touch and keyboard.

### Reliability and observability

- Background work is retryable, idempotent, and observable by job ID and source.
- Provider failures, source failures, queue depth, publication failures, and payment review delays emit actionable logs/metrics.
- Database and object storage backups are encrypted, monitored, and periodically restore-tested.
- Deployments run migrations safely and provide health checks for each service.
- AI usage/cost records reconcile with provider usage where provider adapters support it.

### Localization

- Support BDT and USD pricing and payment instructions.
- Store timestamps consistently (UTC) and render them in the user’s selected/local timezone with the source’s original publication time available.
- User-facing dates, numbers, and currencies use locale-aware formatting.
- English is the initial interface language; localization beyond English is not assumed without product approval.

## 12. Analytics and success measures

Instrumentation should answer whether Nexovant improves discovery, trust, follow-through, and commercial conversion. Establish baselines before setting numeric growth targets.

### Activation

- Registration completion rate.
- Share of new subscribers who choose preferences or follow at least one entity.
- Time from registration to first story opened, first save, and first briefing viewed.

### Engagement and utility

- Weekly active subscribers and returning subscriber rate.
- Stories opened per active user; completion or meaningful interaction with analysis sections.
- Searches that lead to an entity/story open.
- Followed-entity alert open rate and saved-story revisit rate.
- Q&A answer citation-open rate and evidence-insufficient rate.
- Briefing generation and download rate.

### Trust and content quality

- Published stories with complete required provenance.
- Editorial rejection, correction, and post-publication correction rates.
- Source freshness and source-health coverage by category/region.
- Citation coverage and citation-link failure rate for AI answers/reports.
- User-reported inaccuracy rate and time to resolution.

### Commercial and operational

- Trial/free-to-paid conversion and paid plan distribution.
- Invoice-to-payment completion and payment-proof review time.
- Subscription renewal/churn once renewal flows are available.
- API/service availability, queue age, source ingestion failure rate, and AI provider fallback rate.
- AI usage/cost per task, per active subscriber, and by plan.

All analytics must follow consent, retention, and data minimization requirements. Do not use engagement as a substitute for factual quality.

## 13. Current implementation assessment

This assessment is based on repository inspection, not a production readiness audit.

| Area | Observed state | PRD implication |
|---|---|---|
| Vue application | Public, subscriber, and admin routes exist; the front end uses Vue 3, TypeScript, Pinia, and an API client. Several routes are protected by a client-side token check. | Keep the three surfaces; enforce all access rules in the backend and preserve return-to-route behavior. |
| Article feed and dossiers | Laravel article listing, filtering, detail loading, summaries, assessments, sources, categories, and related entities are modeled. | Complete pagination/filter UX and guarantee published source/analysis completeness. |
| Ten-dimension analysis | The product UI and API describe a structured 10-dimension assessment. | Treat the ten dimensions and evidence/uncertainty rules as the canonical editorial schema. |
| Search and trends | Search endpoints cover articles, companies, executives, and trends. Trend records include metrics, sources, and verification relations. | Replace canned suggestions and ensure every displayed metric has a real time window and source. |
| Account and subscriptions | Register/login/profile/token flows, plans, invoices, subscriptions, and BDT/USD checkout records exist. | Complete account recovery/verification decisions, robust role checks, entitlements, and lifecycle states. |
| Manual payments | bKash/Nagad/bank instructions and payment-proof review are modeled; Stripe is seeded disabled. | Manual review is the initial payment scope; card payments are not available until integrated and enabled. |
| Organization | Organization records, memberships, and invitation creation are present. | Complete invite acceptance, expiry/revocation, role boundaries, and tenant context handling. |
| Saved stories and preferences | Backend routes and models exist for saved items, preferences, interactions, and notifications. | Ensure the UI views are fully wired and server data is scoped to the authenticated user. |
| Subscriber dashboard | The current subscriber dashboard uses hard-coded example developments and a sample watchlist. | Replace fixture data with account-specific API data before calling dashboard metrics live. |
| AI router | Provider registry, task capabilities, quota/circuit controls, and admin UI exist. The current model router simulates provider responses rather than calling provider APIs. | Provider adapters, credential management, real usage reconciliation, retries, and failure policy are release blockers for production AI. |
| RAG | Database structures and Laravel bridge exist, but the Python RAG endpoint uses fixed context and citations; Laravel has a generic fallback response. | Implement real tenant-filtered retrieval and citation validation; never present fallback text as retrieved evidence. |
| PDF reports | ReportLab generates a PDF, but default summary and development rows are sample content when no real report payload is supplied. | Connect report generation to scoped, cited publication data and remove sample content from production output. |
| Ingestion/automation | Admin controls and pipeline entities exist; several automation, retry, reindex, and operational responses are placeholders or return queued/success messages without a demonstrated worker. | Implement observable, durable, idempotent jobs and source adapters before promising automated continuous coverage. |
| Admin dashboard/metrics | Some counts query database records; several health, activity, cache, queue, and cost values are static examples. | Label/derive all operational metrics and provide provenance/time windows. |
| Admin authorization | Admin pages are authenticated; the API route group is protected by authentication, but the product must verify explicit role/permission enforcement for every admin operation. | Backend authorization audit is a P0 security requirement. |
| Newsletter and alerts | Newsletter storage, user notification endpoints, and preferences are present. Delivery provider and end-to-end dispatch are not evident in the repository. | Add consent, unsubscribe, delivery, retry, suppression, and alert-dispatch requirements. |
| Infrastructure | Docker Compose includes PostgreSQL/pgvector, Laravel, queue worker, scheduler, Python engine, and Nginx; deployment and encrypted backup scripts are present. | Validate configuration consistency, secrets, TLS, restore, resource limits, and the actual production topology before launch. |

## 14. Delivery plan

### Phase 0 — Product and security hardening (P0)

- Confirm personas, plan entitlements, role matrix, source policy, and geography/currency rules.
- Audit server-side authorization and organization data isolation.
- Replace or clearly isolate seeded/demo data and static metrics.
- Define content statuses, evidence requirements, corrections, takedowns, and editorial ownership.
- Define production secrets, privacy/retention, backup/restore, and monitoring.

### Phase 1 — Trustworthy intelligence MVP (P0)

- Public feed, categories, search, company/entity linking, story dossier, and source provenance.
- Real source ingestion and editor review with idempotent jobs and auditable publication.
- Account lifecycle, saved stories, follows/preferences, and data-backed subscriber dashboard.
- Plans, invoices, manual payment submission/review, entitlement activation, and audit trail.
- Production AI provider adapters only for explicitly supported tasks; grounded RAG with tenant-safe retrieval and citations.
- Real PDF generation from selected authorized intelligence records.

### Phase 2 — Team and operational expansion (P1)

- Invitation acceptance and complete organization administration.
- Trend radar with measured source/time-window metadata.
- Notifications and newsletter delivery preferences with unsubscribe handling.
- Admin knowledge-base operations, provider cost visibility, audit search, and accurate operational metrics.
- Customizable and scheduled briefings.

### Phase 3 — Scale and optimization (P2)

- More source coverage and languages after licensing and quality review.
- Payment gateway integration, if commercially approved.
- Advanced team workflows, saved searches, alert rules, and integrations based on customer research.
- More sophisticated relevance ranking and organization-specific knowledge connectors.

## 15. Risks and mitigations

| Risk | Impact | Mitigation / decision needed |
|---|---|---|
| AI synthesis invents or overstates claims | Loss of trust and potential business harm | Retrieval-first generation, citation checks, uncertainty labels, editorial review, correction workflow, and evaluation against source records |
| Weak or inconsistent source licensing | Content takedown or legal exposure | Maintain source terms/usage metadata, use permitted excerpts, retain canonical links, and obtain legal review before launch |
| Cross-tenant data leakage | Confidentiality breach | Central tenant authorization policy, adversarial isolation tests, audit logs, and least-privilege service credentials |
| Simulated services appear operational | Misleading users and operators | Remove fixtures from production builds or label demo data; only show measured health/usage values |
| Manual payment errors or fraud | Revenue loss and customer access errors | Duplicate checks, review notes, transaction reconciliation, dual control for exceptional adjustments, and immutable history |
| Source ingestion gaps or stale information | Missed or late intelligence | Per-source freshness SLOs, health dashboards, retries, failure alerts, and explicit last-updated indicators |
| Provider outage or unexpected AI cost | Degraded experience or spend exposure | Budgets, quotas, circuit breakers, model allowlists, provider fallback, and clear degraded states |
| Broad admin access | Unauthorized changes | Backend RBAC, scoped roles, step-up authentication for sensitive actions, and audit review |
| Ambiguous subscription and seat semantics | Billing disputes and support burden | Publish entitlements, define organization billing ownership, seat changes, proration, cancellation, and refund rules |

## 16. Assumptions and open decisions

1. **Initial target market:** The repository supports BDT and USD. Confirm launch geographies and whether other currencies are required.
2. **Editorial responsibility:** Name the role accountable for verification, corrections, and publication standards.
3. **Source policy:** Confirm which sources may be ingested, stored, summarized, quoted, and redistributed.
4. **Plan entitlements:** Confirm final plan names, prices, trial policy, seat counts, alert limits, and AI/report quotas.
5. **Organization roles:** Confirm owner/admin/manager/member/viewer permissions and multi-organization membership behavior.
6. **Payment operations:** Confirm review SLA, refund/cancellation policy, duplicate-reference handling, and settlement reconciliation.
7. **Account lifecycle:** Confirm email verification, password reset, MFA, account deletion, and support recovery requirements.
8. **AI governance:** Confirm allowed providers/models, data residency restrictions, retention controls, and user-visible model disclosure.
9. **Freshness and availability:** Approve source-specific freshness objectives, service availability target, support hours, and incident communications.
10. **Product telemetry:** Approve analytics events, consent model, retention periods, and baseline/target values.
11. **Deployment topology:** Confirm whether frontend hosting is separate from the current Nginx/Laravel compose topology and define production TLS/domain routing.

## 17. Glossary

- **Article / intelligence item:** A normalized, editorially reviewed business development with sources and analysis.
- **Event:** A business occurrence that may connect multiple articles, companies, executives, or dates.
- **Verification status:** The evidence-quality label assigned to a claim or story (confirmed, reliable reporting, developing, disputed, or unverified).
- **Assessment:** The structured analysis of an intelligence item across the ten product dimensions.
- **Trend:** A measured topic or entity signal with source, time-series, velocity, and verification context.
- **Tenant:** An organization-level data and access boundary.
- **RAG:** Retrieval-augmented generation; an answer is generated using retrieved, permission-checked source material and must expose citations.
- **Publication:** A generated or curated report made available to an individual or organization.
- **Agent run / pipeline job:** A traceable background processing task such as ingestion, analysis, indexing, or report generation.

## 18. Requirement ownership and sign-off

| Function | Owner | Approval |
|---|---|---|
| Product scope, personas, and priorities | Product | Pending |
| Editorial policy and verification taxonomy | Editorial | Pending |
| Authentication, authorization, and tenant isolation | Engineering / Security | Pending |
| Plans, payment operations, and entitlements | Product / Finance | Pending |
| AI sourcing, model policy, and evaluation | Product / Engineering / Legal | Pending |
| Privacy, source licensing, and retention | Legal / Privacy | Pending |
| Availability, observability, and restore objectives | Engineering / Operations | Pending |

