# NEXOVANT — PHASE 7F BASELINE AUDIT
## Full-Platform Technical, Security, Reliability & Operational Baseline

**Inspection Timestamp:** 2026-11-15T23:55:00+06:00  
**Project:** Nexovant — Intelligence Beyond Headlines  
**Audit Target:** `nexovant-backend` (Laravel 12, PHP 8.4) & `nexovant-frontend` (Vue 3, Vite, TypeScript)  
**Lead Auditor:** Google Antigravity Production Reliability & Security Audit Engine  

---

## 1. Baseline System Status

### 1.1 Git Working Tree & Branch
- **Current Git Branch:** `dev`
- **Working Tree State:** All Phase 1 through Phase 7E modules present. Uncommitted working files preserved.
- **Git Tracking Verification:** `.env` and sensitive local files are strictly untracked and excluded via `.gitignore`. Only `.env.example` is committed.

### 1.2 Backend Automated Test Baseline
- **Execution Command:** `./vendor/bin/phpunit`
- **Total Tests:** 106 tests, 789 assertions
- **Status:** 100% Passed (0 failures, 0 errors, 0 skipped)
- **Execution Duration:** 1m 43s

### 1.3 Frontend Build & Typecheck Baseline
- **Execution Command:** `npm run build` (`vue-tsc --noEmit && vite build`)
- **TypeScript Errors:** 0 errors
- **Vite Production Bundler:** Successfully transformed 1,779 modules. Production bundle output generated in `dist/`.

### 1.4 Database Migrations Baseline
- **Migration Status:** 31 total migrations executed (Batch 1: 000000 through 000102; Batch 2: 000103). All migrations in status `Ran`.
- **Database Engine Tested:** SQLite in-memory for testing; SQLite database file for local development; strict PostgreSQL pgvector DDL configured in migration 000060.

---

## 2. Severity-Ranked Findings Matrix

| Finding ID | Repository & Module | Severity | Confirmed / Suspected | Status |
|---|---|---|---|---|
| **VULN-SEC-01** | `nexovant-frontend`: `AdminLoginView.vue`<br>`nexovant-backend`: `DatabaseSeeder.php` | **CRITICAL** | Confirmed | **REMEDIATED** |
| **VULN-AUTH-01** | `nexovant-backend`: `routes/api.php` | **HIGH** | Confirmed | Open (Remediation Planned) |
| **VULN-TENANT-01** | `nexovant-backend`: `OrganizationService.php` | **HIGH** | Confirmed | Open (Remediation Planned) |
| **VULN-DEP-01** | `nexovant-frontend`: `package.json` (`npm audit`) | **HIGH** | Confirmed | Open (Remediation Planned) |
| **VULN-TENANT-02** | `nexovant-backend`: `WatchlistService.php` | **MEDIUM** | Confirmed | Open (Remediation Planned) |
| **VULN-RAG-01** | `nexovant-backend`: `KnowledgeBaseService.php` | **MEDIUM** | Confirmed | Open (Remediation Planned) |
| **VULN-GATEWAY-01** | `nexovant-backend`: `StripeGatewayAdapter.php`, `RazorpayGatewayAdapter.php` | **MEDIUM** | Confirmed | Open (Remediation Planned) |
| **ARCH-REL-01** | `nexovant-backend`: `Console/Kernel` / Scheduled Commands | **LOW** | Confirmed | Open (Remediation Planned) |

---

## 3. Detailed Findings & Analysis

### Finding VULN-SEC-01: Hardcoded Demonstration Credentials in Code & Seed Data
- **Module:** `AdminLoginView.vue` & `DatabaseSeeder.php`
- **Severity:** **CRITICAL**
- **Status:** **CONFIRMED & REMEDIATED**
- **Description:** Previous demo setup included a frontend button `fillDemoAdmin` that auto-filled plaintext administrative credentials into the login form. Furthermore, `DatabaseSeeder.php` contained hardcoded static fallback strings for demonstration users.
- **Potential Impact:** Anyone inspecting client-side bundle or source code could obtain administrative credentials. If `db:seed` were executed on staging or production, default demonstration passwords would allow unauthorized full administrative takeover.
- **Remediation Implemented:**
  1. Rotated the compromised administrator password using cryptographically secure high-entropy string (`Str::random(40)`) via newly implemented `auth:rotate-admin-credentials` artisan command.
  2. Deleted and revoked all active Sanctum personal access tokens and database sessions.
  3. Removed `fillDemoAdmin` and all hardcoded credentials from `AdminLoginView.vue`.
  4. Updated `DatabaseSeeder.php` to strictly require `SEED_ADMIN_PASSWORD` in production, generating randomized strings if absent.
  5. Updated test fixtures in `AdminAuthTest.php` to use dynamic in-test fixtures.

---

### Finding VULN-AUTH-01: Missing Rate Limiting on Authentication Endpoints
- **Module:** `nexovant-backend/routes/api.php` (`/api/v1/auth/login`, `/api/v1/auth/admin-login`, `/api/v1/auth/register`)
- **Severity:** **HIGH**
- **Status:** Confirmed
- **Description:** Authentication routes are defined without rate limiting (`throttle` middleware).
- **Reproduction / Evidence:** Lines 106–110 of `routes/api.php` group `Route::prefix('auth')` without `throttle:login` or `throttle:5,1`.
- **Potential Impact:** An automated attacker can execute brute-force dictionary attacks against subscriber and administrator accounts without experiencing rate limiting or account lockout.
- **Recommended Remediation:** Attach dedicated rate limiters:
  - `throttle:6,1` (6 attempts per minute per IP/email) on login and admin-login.
  - `throttle:10,1` on account registration.
  - Log warning audit events on repeated throttled authentication failures.

---

### Finding VULN-TENANT-01: Unverified Domain Verification Bypass
- **Module:** `nexovant-backend/app/Services/Organization/OrganizationService.php` (`confirmDomainVerification`)
- **Severity:** **HIGH**
- **Status:** Confirmed
- **Description:** `confirmDomainVerification()` sets `domain_verified_at = now()` without performing an actual DNS TXT challenge resolution.
- **Reproduction / Evidence:** Lines 320–335 of `OrganizationService.php` verify whether `domain` and `domain_verification_token` are non-empty and immediately mark the domain verified without performing a `dns_get_record()` lookup.
- **Potential Impact:** Any tenant administrator could claim any high-profile corporate domain (e.g. `google.com`, `apple.com`) and achieve domain verification status without proving ownership, potentially hijacking auto-join user routing.
- **Recommended Remediation:**
  1. Implement real DNS TXT lookup using PHP's `dns_get_record($host, DNS_TXT)` querying `_nexovant-challenge.<domain>`.
  2. Securely compare resolved TXT record against stored challenge token.
  3. Enforce domain uniqueness across verified organizations: prevent Organization B from claiming a domain already verified by Organization A unless transferred.
  4. Support configurable test resolver or test domain bypass strictly in `testing` environment.

---

### Finding VULN-DEP-01: Frontend Development Dependencies Vulnerabilities
- **Module:** `nexovant-frontend/package.json` (`npm audit`)
- **Severity:** **HIGH**
- **Status:** Confirmed
- **Description:** `npm audit` reported 15 vulnerabilities (12 moderate, 1 high, 2 critical) primarily in development dependencies:
  - `tinypool` (Critical): Prototype pollution in worker options leading to potential RCE during build/test execution.
  - `esbuild` (Moderate): Dev server request handling.
  - `postcss-selector-parser` (Moderate): ReDoS via quadratic complexity.
- **Potential Impact:** Risk during development/CI execution if untrusted worker inputs are parsed.
- **Recommended Remediation:** Execute safe non-breaking dependency updates (`npm audit fix` or targeted patch updates for `tinypool`/`vitest`).

---

### Finding VULN-TENANT-02: Tenant Colleague Watchlist IDOR / Access Mismatch
- **Module:** `nexovant-backend/app/Services/Workspace/WatchlistService.php`
- **Severity:** **MEDIUM**
- **Status:** Confirmed
- **Description:** `WatchlistService::getWatchlist()` restricts queries strictly with `where('user_id', $user->id)`. When an analyst shares a watchlist with their organization (`is_shared = true`, `organization_id = $org->id`), colleagues in that same organization cannot retrieve it by ID (`/api/v1/workspace/watchlists/{id}`) because the service assumes only the creator can query it.
- **Potential Impact:** Broken collaborative team workspace experience; team members cannot open shared watchlists directly in workspace views.
- **Recommended Remediation:** Update `getWatchlist()` so that access is granted if the user is the owner OR if the watchlist is shared with an active organization of which the user is an active member. Mutations (update, delete) remain restricted to the owner or workspace administrators.

---

### Finding VULN-RAG-01: Stale Semantic Cache Retention on Knowledge Document Deletion
- **Module:** `nexovant-backend/app/Services/RAG/KnowledgeBaseService.php`
- **Severity:** **MEDIUM**
- **Status:** Confirmed
- **Description:** When `deleteDocument()` is called, it deletes the `KnowledgeDocument` record (cascading to chunks and embeddings), but does not invalidate or purge corresponding `SemanticCache` records for that tenant.
- **Potential Impact:** Grounded RAG queries could continue returning cached answers with citations to deleted proprietary documents until the 24-hour cache TTL expires.
- **Recommended Remediation:** Add `clearTenantCache($doc->tenant_id)` inside `deleteDocument()` to guarantee instantaneous cache invalidation upon proprietary document removal.

---

### Finding VULN-GATEWAY-01: Insecure Default Webhook Secrets in Gateway Adapters
- **Module:** `StripeGatewayAdapter.php` & `RazorpayGatewayAdapter.php`
- **Severity:** **MEDIUM**
- **Status:** Confirmed
- **Description:** Gateway adapters read webhook secrets with fallback defaults (e.g. `'whsec_test_secret'`, `'rzp_test_secret'`).
- **Potential Impact:** In non-testing environments where webhook secrets are left unconfigured, attackers knowing the default string could forge valid webhook signatures and simulate payment completion.
- **Recommended Remediation:** Remove static fallback defaults in production and local environments; fail closed (reject verification) if webhook secret is null or unconfigured.

---

### Finding ARCH-REL-01: Missing Scheduled Token and Session Hygiene Pruning
- **Module:** Scheduled Tasks (`routes/console.php`)
- **Severity:** **LOW**
- **Status:** Confirmed
- **Description:** Personal access tokens (`personal_access_tokens`), publication download tokens (`publication_download_tokens`), and expired invitations (`organization_invitations`) accumulate over time without automated pruning.
- **Potential Impact:** Unbounded table growth and residual expired tokens in database.
- **Recommended Remediation:** Register automated pruning commands in `routes/console.php` (e.g. `sanctum:prune-expired`, prune expired download tokens > 30 days old).

---

## 4. Scope of Remediation for Phase 7F

The following remediation workstreams are prioritized:
1. **Security Remediation (Immediate)**:
   - Fix `VULN-AUTH-01`: Apply throttle middleware on login, admin-login, and registration routes.
   - Fix `VULN-TENANT-01`: Implement real DNS TXT lookup with domain uniqueness check in `OrganizationService`.
   - Fix `VULN-GATEWAY-01`: Remove fallback webhook secrets; fail closed on unconfigured secrets.
   - Fix `VULN-TENANT-02`: Enhance `WatchlistService` to support authorized team member reading of shared watchlists.
   - Fix `VULN-RAG-01`: Purge semantic cache upon document deletion.
   - Fix `VULN-DEP-01`: Run safe dependency fixes in frontend.
2. **Verification Suite Expansion**:
   - Write comprehensive tests in `tests/Feature/SecurityHardeningTest.php` covering all remediated vulnerabilities, rate limiting, DNS TXT challenge resolution, and IDOR boundary assertions.
3. **Operational Validation & Deliverables**:
   - Execute the 6 mandatory end-to-end scenarios (A through F).
   - Generate all 10 required report documents.
