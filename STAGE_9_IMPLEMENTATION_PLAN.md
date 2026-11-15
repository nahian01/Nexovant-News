# NEXOVANT — STAGE 9 IMPLEMENTATION PLAN
## Subscription Management, Trials, Usage & Billing Experience

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 9  
**Architecture:** Vue 3 + TypeScript (Frontend) & Laravel 12 + Eloquent + Sanctum (Backend)  
**Author:** Google Antigravity Production Engineering Lead  
**Date:** 2026-11-16  

---

## 1. Executive Summary & Objective

Stage 9 implements a comprehensive, institutional-grade subscription and billing management experience for Nexovant subscribers. Operating within the executive intelligence design language established in Stage 8, subscribers must have total transparency, control, and agility regarding their subscription lifecycle, real-time quota consumption, trial periods, upgrades/downgrades, invoice records, and multi-regional payment options.

This stage builds directly on the foundational Phase 7D billing architecture (`Plan`, `Subscription`, `Invoice`, `Payment`, `Coupon`, `EntitlementService`, `CheckoutService`, `SubscriptionLifecycleService`, `SubscriptionProrationService`, `RegionalPaymentSelectionService`). **No payment gateways are rebuilt, and zero database models or services are duplicated.**

Key objectives:
1. **Subscription Overview (`/billing`):** Live subscription status (`active`, `trialing`, `past_due`, `cancel_at_period_end`, `canceled`), current tier badge, billing interval (monthly/annual), renewal/expiration dates, auto-renewal controls, and quick management triggers.
2. **Entitlement & Quota Telemetry:** Real-time quota consumption meters (`SubscriberWatchlist`, `SubscriberMonitor`, daily research queries, publication downloads) powered by `EntitlementService`. Clear visual warnings when approaching limits (>80%) with direct upgrade pathways.
3. **Plan Comparison & Feature Matrix (`/billing?tab=plans`):** Transparent comparison across all 5 tiers (`Free`, `Standard`, `Professional`, `Ultra`, `Enterprise`), dual-currency support (`USD` / `BDT`), monthly vs annual toggle (highlighting annual discount), feature availability badges, current plan indicator, and upgrade/downgrade action triggers.
4. **Free Trial Management:** Live trial tracking, countdown of days remaining, trial conversion through secure checkout, and backend guardrails preventing duplicate trial usage.
5. **Subscription Lifecycle & Proration:** Instant proration preview via `previewProration()`, plan upgrade/downgrade execution via `changePlan()`, cancellation with period-end grace vs immediate option, and single-click reactivation of auto-renewal.
6. **Billing History & Invoice Management (`/billing?tab=history`):** Paginated invoice ledger with invoice numbers, line items, breakdown, payment status, and authorized PDF receipt downloads strictly protected by tenant ownership boundaries.
7. **Multi-Regional Payment Methods:** Support for tokenized gateway flows (Stripe/SSLCommerz) and offline manual compliance submissions (bKash, Nagad, Bank Wire) with duplicate reference deduplication.
8. **Organization Billing Visibility (`/billing?tab=organization`):** Multi-tenant pooled entitlement display, seat allocation metrics, and owner vs member permission segmentation.
9. **Promotional Coupon Engine:** Server-authoritative coupon preview and redemption preventing client-side pricing tampering.

---

## 2. Existing Codebase Audit & Reuse Strategy

### 2.1 Backend Existing Capabilities to Reuse (Phase 7D & 7E)
- **`BillingPortalController` (`app/Http/Controllers/Api/V1/Subscriber/`):**
  - `overview()`: Returns subscription, entitlements, usage statistics, recent invoices, eligible payment methods.
  - `plans()`: Returns active plans with entitlements and dual pricing.
  - `paymentMethods()`: Returns regional payment methods for country/currency.
  - `validateCoupon()`: Authoritative discount validation.
  - `checkout()`: Idempotent checkout with backend price recalculation.
  - `previewProration()`: Calculates credits and net upgrade charges without database mutation.
  - `changePlan()`: Executes proration adjustment and transitions subscription tier.
  - `cancel()`: Schedules cancellation at period end or terminates immediately.
  - `reactivate()`: Restores auto-renewal for canceled subscriptions.
  - `invoices()`: Paginated invoice ledger.
  - `downloadInvoice()`: Tenant-isolated PDF generation and streaming.
  - `submitManualProof()`: MFS/wire proof submission with duplicate reference protection.
- **Core Models:** `Plan`, `PlanEntitlement`, `Subscription`, `SubscriptionHistory`, `Invoice`, `InvoiceItem`, `Payment`, `PaymentProof`, `Coupon`, `SubscriptionEntitlementOverride`, `Organization`, `OrganizationMember`.
- **Core Services:** `EntitlementService`, `CheckoutService`, `SubscriptionLifecycleService`, `SubscriptionProrationService`, `RegionalPaymentSelectionService`, `CouponService`, `InvoiceService`.

### 2.2 Backend Gaps Identified & Additions Required
1. **Complimentary Trial Endpoint (`POST /api/v1/billing/start-trial`):**
   - Connects to `SubscriptionLifecycleService::startTrial()` to grant 14-day trials on eligible plans.
   - Strictly validates that user has never previously activated a trial or paid subscription.
2. **Organization Telemetry Enrichment in `overview()`:**
   - Injects organization identity, user role (`owner`, `admin`, `member`), seat usage vs seat limits, and pooled plan details into `BillingPortalController@overview`.
3. **Trial Telemetry Attributes in `overview()`:**
   - Adds `is_trialing`, `trial_ends_at`, and `days_left_in_trial` directly into the subscription payload.

### 2.3 Frontend Capabilities to Reuse & Refactor
- **`AuthenticatedLayout.vue`:** Standard 3-column application shell introduced in Stage 8 (`AppHeader`, `Sidebar`, `RightIntelligencePanel`, mobile navigation). We set `:show-right-panel="false"` for wide billing screens.
- **UI Components:** `Card.vue`, `Badge.vue`, `Button.vue`, `Input.vue`, `Modal.vue`, `Alert.vue`.
- **Existing `billing.service.ts`:** Full TypeScript API client for billing endpoints.
- **Existing `BillingView.vue`:** Refactor into an executive subscription center supporting tabs:
  1. `overview`: Active subscription status, trial countdown, usage progress bars, quick actions.
  2. `plans`: Plan comparison grid, dual currency toggle, monthly/annual toggle, upgrade trigger.
  3. `history`: Paginated invoice list, PDF download, manual proof submission button.
  4. `methods`: Supported regional gateways and manual MFS channels.
  5. `organization`: Visible when member of an organization, displaying pooled quota status.

---

## 3. Phased Implementation Schedule

```
Phase 9.1: Backend API Enhancements & Route Additions
   ├── Enrich BillingPortalController@overview with org and trial telemetry
   ├── Implement BillingPortalController@startTrial
   └── Register Route::post('/billing/start-trial')

Phase 9.2: Comprehensive Backend Automated Test Suite
   ├── Create tests/Feature/SubscriptionManagementStage9Test.php
   ├── Verify all 18 acceptance criteria (overview, trial, proration, security, coupons)
   └── Run full test suite and confirm 100% green

Phase 9.3: Frontend TypeScript Service & Interface Alignment
   ├── Update src/services/api/billing.service.ts with startTrial & org types
   └── Validate strict typing for proration preview, checkout, and invoices

Phase 9.4: Executive Subscriber Billing UI Architecture
   ├── Re-architect src/views/subscriber/BillingView.vue with AuthenticatedLayout
   ├── Implement 5-tab workspace navigation (Overview, Plans, Invoices, Methods, Org)
   ├── Implement real-time entitlement meters with color-coded warning states
   ├── Implement Plan Change & Proration Preview Modal with live discount calculation
   ├── Implement Trial Conversion & Expiration Banner
   ├── Implement Cancellation Confirmation & Reactivation Flow
   └── Implement Manual Payment Proof Submission Modal

Phase 9.5: Build, Typecheck & Linter Verification
   ├── Run vue-tsc --noEmit and vite build
   └── Verify 0 compilation or TypeScript errors

Phase 9.6: Browser E2E Scenario Verification (A through F)
   ├── Scenario A: Overview telemetry & quota meters inspection
   ├── Scenario B: Plan upgrade with proration calculation & coupon validation
   ├── Scenario C: Complimentary trial activation & countdown display
   ├── Scenario D: Cancellation with period-end confirmation & reactivation
   ├── Scenario E: Multi-tenant security check (cross-user invoice access blocked with 403)
   └── Scenario F: Organization billing visibility (owner vs member permissions)

Phase 9.7: Author Deliverables & Final Documentation
   ├── STAGE_9_IMPLEMENTATION_PLAN.md
   ├── STAGE_9_DESIGN_SYSTEM_AUDIT.md
   ├── STAGE_9_API_INTEGRATION_REPORT.md
   ├── STAGE_9_BILLING_SECURITY_REPORT.md
   ├── STAGE_9_BROWSER_E2E_REPORT.md
   └── STAGE_9_COMPLETION_REPORT.md
```

---

## 4. Definition of Done (Stage 9)

- [x] Zero mock or fabricated billing data; 100% powered by Eloquent and `EntitlementService`.
- [x] Backend price recalculation strictly enforced; zero trust in client-submitted prices.
- [x] Strict tenant and cross-user ownership isolation on all invoices and payments.
- [x] All 18 automated test scenarios passing in `SubscriptionManagementStage9Test.php`.
- [x] Clean frontend production build (`npm run build`).
- [x] Complete browser E2E validation with visual evidence for Scenarios A through F.
- [x] All 6 mandatory documentation deliverables produced.
