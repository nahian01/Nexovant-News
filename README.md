# Nexovant — Global Executive Intelligence Platform

**Product Tagline:** Intelligence Beyond Headlines  
**Architecture:** Decoupled 4-Tier SaaS (Frontend SPA, Laravel Backend API, Python Cognitive Engine, VPS & Edge Infrastructure)

---

## Service Architecture

| Repository | Stack | Port / URL | Production Role |
| :--- | :--- | :--- | :--- |
| **`nexovant-frontend`** | Vue 3, Vite, TypeScript, Tailwind CSS, Pinia | [http://127.0.0.1:3000](http://127.0.0.1:3000) | Subscriber command center, public website, admin console |
| **`nexovant-backend`** | Laravel 12, PHP 8.4, Sanctum, SQLite/PgSQL | [http://127.0.0.1:8000](http://127.0.0.1:8000) | REST API, 10-dimension intelligence, payments, multi-tenancy |
| **`nexovant-ai-engine`** | Python 3.12+, FastAPI, ReportLab, Pydantic | [http://127.0.0.1:8001](http://127.0.0.1:8001) | Multi-provider router, circuit breaker, RAG, PDF generator |
| **`nexovant-infrastructure`** | Docker Compose, Nginx, Shell Scripts | Docker | VPS deployment, SSL reverse proxy, R2 backups, health checks |

---

## Instant One-Click Launch

Double-click `run_all.bat` (or run `./run_all.ps1` in PowerShell). This launches all 3 decoupled services in separate windows and automatically opens the platform in your default web browser.

---

## Seeded Demo Accounts

### 1. Enterprise Subscriber (Full Command Center & 10-Dimension Feed)
- **URL:** [http://127.0.0.1:3000/login](http://127.0.0.1:3000/login)
- **Email:** `executive@enterprise.corp`
- **Password:** `ExecutivePass2026!`
- **Access:** Executive Dashboard, Global Intelligence Feed, 10-dimension NVIDIA assessment, Social Trend Radar, Executive PDF Briefings, Organization Workspace.

### 2. Chief Administrator (Gateway & Financial Review)
- **URL:** [http://127.0.0.1:3000/login](http://127.0.0.1:3000/login)
- **Email:** `admin@nexovant.si`
- **Password:** `AdminSecret2026!`
- **Access:** AI Router & Provider Circuit Breaker Console, Manual Payment Proof Verification Queue (bKash/Nagad/Bank wire), Editorial Approvals.

---

## Key Feature Tours

1. **10-Dimension Executive Analysis:** Navigate to **Global Feed** and click on *NVIDIA Board Approves $15B Custom Silicon Foundry Expansion in EMEA* to inspect all 10 verified dimensions.
2. **Social Trend Velocity:** Click **Trending Velocity** to view cross-platform sentiment analysis and mention spikes.
3. **Executive PDF Reports:** Click **Publications & PDFs** to generate and download branded intelligence briefing documents generated live by the Python ReportLab engine.
4. **AI Gateway Monitor:** As Admin, navigate to **AI Router & Gateway** to view active connection health, token consumption, and circuit breaker status across Anthropic, OpenAI, Gemini, and Groq.
5. **Subscription & Payment Review:** As Subscriber, visit **Subscription & Invoices** to review plan entitlements or submit a manual bKash/Nagad transaction proof; as Admin, verify and approve it in **Payment Review Queue**.
