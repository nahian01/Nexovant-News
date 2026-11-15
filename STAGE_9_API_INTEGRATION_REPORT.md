# NEXOVANT — STAGE 9 API INTEGRATION REPORT
## Subscription Management, Trials, Usage & Billing Gateway Endpoints

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 9  
**Audit & Integration Status:** **VERIFIED & OPERATIONAL**  
**Author:** Google Antigravity Production Engineering Lead  
**Date:** 2026-11-16  

---

## 1. Executive Summary

Stage 9 establishes a unified subscriber billing and entitlement management API suite across the Laravel 12 backend and Vue 3 frontend. All routes enforce strict Sanctum token authentication, multi-tenant isolation, server-side price recalculation, and cryptographic idempotency deduplication.

Zero pricing parameters, trial durations, or usage counters are trusted from client payloads. Quotas are authoritatively evaluated through `EntitlementService`, proration is calculated via `SubscriptionProrationService`, and lifecycle state changes are auditable via `SubscriptionLifecycleService`.

---

## 2. Comprehensive Endpoint Catalog

### 2.1 Subscriber Billing Overview
- **Route:** `GET /api/v1/billing/overview`
- **Controller:** `BillingPortalController@overview`
- **Auth:** `auth:sanctum`
- **Headers:** `CF-IPCountry: BD|US` (optional geo-IP header)
- **Response Shape (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "01a1e0b9-5dcd-72be-9e58-a748df63adb5",
      "name": "Dr. Aris Thorne",
      "email": "subscriber@nexovant.test"
    },
    "subscription": {
      "id": "01a1e0b9-6231-71ca-811d-ef1381c8ea41",
      "status": "trialing",
      "is_active": true,
      "is_trialing": true,
      "trial_ends_at": "2026-11-30T19:51:04.000000Z",
      "days_left_in_trial": 14,
      "billing_cycle": "monthly",
      "currency": "USD",
      "starts_at": "2026-11-16T19:51:04.000000Z",
      "ends_at": "2026-11-30T19:51:04.000000Z",
      "next_renewal_at": "2026-11-30T19:51:04.000000Z",
      "canceled_at": null,
      "auto_renew": false,
      "plan": {
        "id": "01a1dff5-1a88-751d-b586-218206d2c4e2",
        "name": "Professional",
        "slug": "pro",
        "price_usd_monthly": 9.00,
        "price_usd_annual": 90.00,
        "price_bdt_monthly": 499.00,
        "price_bdt_annual": 4990.00
      }
    },
    "organization": null,
    "entitlements": {
      "plan_name": "Professional",
      "plan_slug": "pro",
      "limits": {
        "max_watchlists": 25,
        "max_active_monitors": 25,
        "daily_research_queries": 50,
        "historical_timeline_days": 180
      },
      "features": {
        "ai_deep_research": true,
        "pdf_downloads": true,
        "publication_access": true
      }
    },
    "usage": {
      "watchlists": { "used": 0, "limit": 25 },
      "monitors": { "used": 0, "limit": 25 },
      "daily_queries": { "used": 0, "limit": 50 }
    },
    "recent_invoices": [],
    "eligible_payment_methods": [ ... ]
  }
}
```

---

### 2.2 Complimentary Evaluation Trial Initiation
- **Route:** `POST /api/v1/billing/start-trial`
- **Controller:** `BillingPortalController@startTrial`
- **Auth:** `auth:sanctum`
- **Request Body:**
```json
{
  "plan_id": "01a1dff5-1a88-751d-b586-218206d2c4e2"
}
```
- **Business Logic & Guardrails:**
  - Validates user has never previously activated a trial or paid tier.
  - Queries `Plan::trial_period_days` (defaults to 14 days).
  - Creates `Subscription` in `trialing` status with `trial_ends_at = now() + 14 days`.
  - Dispatches `trial_started` notification through `SubscriptionLifecycleService`.
- **Response Shape (201 Created):**
```json
{
  "success": true,
  "message": "Your 14-day trial of Professional has started.",
  "data": { ... }
}
```
- **Error Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Free trial is only available once per account, or you already have an active subscription."
}
```

---

### 2.3 Promotional Coupon Validation
- **Route:** `POST /api/v1/billing/validate-coupon`
- **Controller:** `BillingPortalController@validateCoupon`
- **Auth:** `auth:sanctum`
- **Request Body:**
```json
{
  "code": "NEXO25",
  "plan_id": "01a1dff5-1a88-751d-b586-218206d2c4e2",
  "country": "US",
  "subtotal": 181.00
}
```
- **Validation:**
  - Code existence, `is_active` check, `valid_from` / `valid_until` date boundaries.
  - Plan restrictions (`eligible_plans`) and regional eligibility (`eligible_countries`).
  - Single redemption per user enforcement via `CouponRedemption` table.
- **Response Shape (200 OK):**
```json
{
  "success": true,
  "data": {
    "coupon_id": "01a1e0c6-6bc8-735f-a7e1-884b2e8bb332",
    "code": "NEXO25",
    "name": "25% Executive Launch Discount",
    "discount_type": "percentage",
    "discount_value": 25.0,
    "discount_amount": 45.25,
    "final_amount": 135.75
  }
}
```

---

### 2.4 Proration Preview (Zero DB Mutation)
- **Route:** `POST /api/v1/billing/preview-proration`
- **Controller:** `BillingPortalController@previewProration`
- **Auth:** `auth:sanctum`
- **Request Body:**
```json
{
  "target_plan_id": "01a1dff5-1a88-751d-b586-218206d2c4e3",
  "new_interval": "annual"
}
```
- **Proration Math Formulation:**
  $$\text{Fraction Remaining} = \frac{\text{Remaining Cycle Days}}{\text{Total Cycle Days}}$$
  $$\text{Unused Credit} = \text{Current Plan Base Price} \times \text{Fraction Remaining}$$
  $$\text{Net Amount Due} = \max(0, \text{Target Plan Price} - \text{Unused Credit})$$
- **Response Shape (200 OK):**
```json
{
  "success": true,
  "data": {
    "type": "upgrade",
    "current_plan": "Professional",
    "target_plan": "Executive",
    "current_price": 9.0,
    "target_price": 190.0,
    "remaining_days": 14,
    "unused_credit": 9.0,
    "amount_due": 181.0
  }
}
```

---

### 2.5 Plan Change Execution
- **Route:** `POST /api/v1/billing/change-plan`
- **Controller:** `BillingPortalController@changePlan`
- **Auth:** `auth:sanctum`
- **Request Body:**
```json
{
  "target_plan_id": "01a1dff5-1a88-751d-b586-218206d2c4e3",
  "new_interval": "annual"
}
```
- **Execution Workflow:**
  1. Computes proration ledger.
  2. Creates prorated `Invoice` with line items for target plan and unused credit adjustment.
  3. Updates `Subscription` plan ID, cycle, and resets renewal date.
  4. Appends record to `subscription_histories` table for compliance audit.

---

### 2.6 Subscription Cancellation & Reactivation
- **Cancellation (`POST /api/v1/billing/cancel`):**
  - Parameter `at_period_end: true` (default): Transitions status to `cancel_at_period_end`, stops auto-renew, preserves entitlement access through `ends_at`.
  - Parameter `at_period_end: false`: Transitions immediately to `canceled`, user reverts to Free tier.
- **Reactivation (`POST /api/v1/billing/reactivate`):**
  - Restores `cancel_at_period_end` subscriptions back to `active`.
  - Clears cancellation schedule and reenables auto-renew.

---

### 2.7 Secure Invoice PDF Download
- **Route:** `GET /api/v1/billing/invoices/{id}/download`
- **Controller:** `BillingPortalController@downloadInvoice`
- **Auth:** `auth:sanctum`
- **Security Check:**
  $$\text{Invoice Owner ID} == \text{Request User ID} \quad \lor \quad \text{User is Admin}$$
  Cross-user attempts return `403 Forbidden`.
- **Response:** `200 OK`, `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="INV-2026-0001.pdf"`.

---

### 2.8 Offline MFS / Wire Payment Proof Submission
- **Route:** `POST /api/v1/billing/submit-manual-payment`
- **Controller:** `BillingPortalController@submitManualProof`
- **Auth:** `auth:sanctum`
- **Request Body:**
```json
{
  "invoice_id": "01a1e0b9-5dcd-72be-9e58-a748df63adb5",
  "payment_method": "manual_bkash",
  "transaction_id": "TRX_BKASH_999888",
  "sender_account": "01711000000",
  "notes": "Executive Q4 payment"
}
```
- **Guardrails:**
  - Verifies invoice ownership (rejects 403 if unauthorized).
  - Checks `PaymentProof` table for `transaction_id` uniqueness (rejects 422 with `DUPLICATE_TRANSACTION` if already registered).
  - Creates `Payment` record with status `pending_review` awaiting compliance review.
