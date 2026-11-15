# NEXOVANT — STAGE 10 IMPLEMENTATION PLAN
## Regional Payment Integration & Intelligent Checkout Engine

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 10  
**Status:** In Progress / Active Execution  
**Environment:** Google Antigravity  
**Repositories:**
- `nexovant-backend` (Laravel 12, PHP 8.4, Eloquent, Sanctum)
- `nexovant-frontend` (Vue 3, TypeScript, Vite, TailwindCSS)

---

## 1. Executive Summary & Architecture Foundation

Nexovant Stage 10 develops a production-grade, region-aware payment and checkout architecture. Building upon the Phase 7D billing foundation and Stage 9 subscription lifecycle portal, Stage 10 transforms payment acquisition into an intelligent, adaptive checkout journey.

### Strategic Priorities:
1. **Regional Prioritization**:
   - **Primary**: Bangladesh (`BD`) with local Mobile Financial Services (bKash, Nagad) and domestic corporate banking (City Bank PLC).
   - **Secondary**: International Markets (`US`, `GB`, `EU`, `CA`, `AU`, `SG`) via Stripe tokenized/hosted cards, digital wallets (Apple Pay, Google Pay), and international corporate wire (Standard Chartered Bank).
   - **Tertiary**: Southeast Asia & India (`IN`, `MY`, `SG`) conditional routing via Razorpay.
2. **Zero Client-Side Financial Authority**:
   - The frontend never determines eligibility, recalculates final prices, or establishes exchange rates.
   - All gateway eligibility, currency constraints, tax calculations, and coupon redemptions are evaluated server-side.
3. **Strict Compliance & Security**:
   - No raw PAN or CVV storage.
   - Signature verification on all webhooks with 5-minute timestamp replay tolerance.
   - Cross-tenant isolation on all checkout, status, and invoice endpoints.
   - Transparent, un-fabricated provider policies (e.g. Upay documented as pending corporate merchant onboarding).

---

## 2. Pre-Implementation Audit Findings

| Component | Current State | Stage 10 Enhancement Requirement |
| :--- | :--- | :--- |
| `RegionalPaymentSelectionService` | Evaluates country & currency for bKash, Nagad, Stripe, Razorpay, Manual Bank. | Add `getSupportedCountries()`, `getSupportedCurrencies()`, expand support to `EUR`, `GBP`, `CAD`. |
| `CheckoutService` | Backend price calculation, coupon redemption, idempotency check, invoice creation. | Support multi-currency checkout, return formatted instructions for MFS/Wire, support retry flows. |
| `BillingPortalController` | Handles `/checkout`, `/plans`, `/invoices`, `/payment-methods`. | Add `/checkout/options`, `/checkout/status/{reference}`, `/checkout/retry` endpoints. |
| `PaymentReconciliationService` | Detects unbacked paid invoices, overdue active subscriptions, uncredited payments. | Formalize automated anomaly report output with gateway filtering and audit logging. |
| `Frontend Routes` | Only `/billing` and `/billing/history` modals. | Add dedicated standalone `/checkout` and `/checkout/status/:reference` views. |
| `Gateway Adapters` | 5 active adapters implemented (`Stripe`, `Bkash`, `Nagad`, `Razorpay`, `ManualBank`). | Maintain integrity; document Upay status formally without fake adapters. |

---

## 3. Intelligent 6-Step Checkout Journey

The intelligent checkout process is structured into 6 deterministic stages:

```
+--------------------------------------------------------------------------+
|                       INTELLIGENT CHECKOUT JOURNEY                       |
+--------------------------------------------------------------------------+
  [Step 1: Plan Summary & Cadence]
      │ - Select plan (Professional / Executive / Enterprise)
      │ - Toggle Billing Cadence (Monthly / Annual with 2 months free)
      │
  [Step 2: Customer Billing Country]
      │ - Country auto-detected from IP / User Profile (Default: BD or US)
      │ - Searchable ISO 3166-1 alpha-2 selector
      │
  [Step 3: Payment Currency]
      │ - Filtered by country compatibility (BDT for BD; USD/EUR/GBP/CAD for International)
      │ - Transparent exchange rate notice (Settlement in native currency)
      │
  [Step 4: Server-Authoritative Payment Methods]
      │ - Dynamic retrieval from GET /api/v1/checkout/options
      │ - Bangladesh: bKash MFS, Nagad MFS, City Bank Corporate Wire
      │ - International: Stripe Credit/Debit Cards, SCB SWIFT Wire
      │
  [Step 5: Promotional Coupon & Price Breakdown]
      │ - Live server-validated coupon code
      │ - Subtotal, Discount, Tax, Total recalculation
      │
  [Step 6: Payment Execution & Status Tracking]
      │ - MFS: Step-by-step counter instructions & instant TrxID submission
      │ - Stripe: Client secret / hosted redirect session
      │ - Wire: Bank account, branch, routing, IBAN, and SWIFT instructions
      │ - Dedicated Status Resolution: /checkout/status/:reference
```

---

## 4. Multi-Currency Specification & Precision

Nexovant maintains exact financial precision using standard decimal format (`DECIMAL(10, 2)`) across all database schemas and ledger tables:

| Currency Code | ISO Code | Symbol | Decimals | Primary Gateway | Permitted Countries |
| :--- | :--- | :--- | :--- | :--- | :--- |
| Bangladeshi Taka | `BDT` | `৳` | 2 | bKash, Nagad, City Bank | `BD` only |
| United States Dollar | `USD` | `$` | 2 | Stripe, Standard Chartered | Global (`US`, `GB`, `EU`, `CA`, etc.) |
| Euro | `EUR` | `€` | 2 | Stripe, Standard Chartered | `DE`, `FR`, `NL`, `IT`, `ES`, `EU` |
| British Pound | `GBP` | `£` | 2 | Stripe, Standard Chartered | `GB` |
| Canadian Dollar | `CAD` | `CA$` | 2 | Stripe | `CA` |

**Rule**: Cross-currency transactions are never converted silently. If a user selects BDT outside Bangladesh, the backend returns a `422 Unprocessable Content` error explaining currency-country incompatibility.

---

## 5. Upay Bangladesh Feasibility Decision

Following live developer portal investigation:
- **Upay** (UCB Fintech Company Limited) operates a closed, enterprise-gated merchant API.
- Integration requires physical corporate merchant agreement, trade license submission, Bangladesh Bank NOC verification, and signing an NDA before API credentials and sandbox access are provisioned.
- Public self-serve developer sandboxes are **not** provided by Upay.
- **Architectural Decision**: Per Antigravity engineering rules, Upay is documented in `STAGE_10_PROVIDER_FEASIBILITY_REPORT.md` and flagged in the registry as `pending_merchant_onboarding`. No fake or simulated adapter will be built.

---

## 6. API Endpoint Architecture

### Subscriber Endpoints:
1. `GET /api/v1/checkout/options`:
   - Query: `country` (string), `currency` (string), `interval` (monthly|annual).
   - Response: Supported country list, supported currencies list, eligible payment methods, and default pricing.
2. `POST /api/v1/billing/checkout`:
   - Body: `plan_id`, `billing_cycle`, `currency`, `country`, `payment_method`, `coupon_code`, `idempotency_key`, `billing_name`, `billing_email`, `billing_address`.
   - Response: Invoice record, payment record, transaction reference, instructions / redirect URL.
3. `GET /api/v1/checkout/status/{reference}`:
   - Path: `reference` (Payment transaction reference, payment ID, or invoice ID).
   - Response: Payment status (`paid`, `pending_review`, `pending`, `failed`), invoice snapshot, receipt download URL, retry options.
4. `POST /api/v1/checkout/retry`:
   - Body: `payment_id` or `invoice_id`, `new_payment_method`.
   - Response: Re-initiated payment attempt on existing unpaid invoice without orphan invoice generation.

### Administrative Endpoints:
1. `GET /api/v1/admin/billing/gateways`: List all 5 gateways with live credentials status and test mode toggles.
2. `PUT /api/v1/admin/billing/gateways/{key}`: Update gateway status and credentials.
3. `POST /api/v1/admin/billing/gateways/{key}/test`: Sandbox connectivity verification.
4. `POST /api/v1/admin/billing/reconcile`: Trigger automated ledger reconciliation audit.
5. `POST /api/v1/admin/payments/{id}/verify`: Manual bank/MFS verification and instant subscription activation.

---

## 7. Implementation Roadmap & Milestones

- [x] Pre-Implementation Audit & Adapter Inspection
- [x] Deliverable 1: `STAGE_10_IMPLEMENTATION_PLAN.md`
- [ ] Deliverable 2: `STAGE_10_PROVIDER_FEASIBILITY_REPORT.md`
- [ ] Backend Service Enhancements (`RegionalPaymentSelectionService`, `BillingPortalController`, `api.php`)
- [ ] Frontend Intelligent Checkout Flow (`CheckoutView.vue`, `CheckoutStatusView.vue`, `router`, `billing.service.ts`)
- [ ] Automated Test Suite (`RegionalPaymentCheckoutStage10Test.php`)
- [ ] Real Browser E2E Scenarios (Scenarios A through G)
- [ ] Deliverables 3–7 Authoring (`API_INTEGRATION`, `PAYMENT_SECURITY`, `PAYMENT_RECONCILIATION`, `BROWSER_E2E`, `COMPLETION_REPORT`)
- [ ] Git commit and push to `origin/dev`
