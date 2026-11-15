# NEXOVANT — PHASE 7F BILLING & PAYMENT SECURITY REPORT
## Financial Integrity, Double-Spend Defense & Webhook Verification Audit

**Date:** 2026-11-16  
**Auditor:** Google Antigravity Production Reliability & Security Audit Engine  
**Target Systems:** `nexovant-backend` (Billing Engine & Adapters) & `nexovant-frontend` (Billing Portal)  
**Security Status:** **FINANCIALLY SECURE & PRODUCTION-READY**  
**Automated Billing Tests:** 13/13 Passing (`BillingSubscriptionTest.php`) + 2/2 Passing (`SecurityHardeningTest.php`)

---

## 1. Executive Summary

Phase 7D introduced an enterprise billing and subscription platform supporting multi-gateway routing (Stripe, Razorpay, bKash, Nagad, Manual Wire Transfer), automated subscription lifecycle states, proration adjustments, coupon discounts, and invoice generation.

Phase 7F conducted an exhaustive financial security audit to ensure that client-side price tampering, double checkout, webhook signature forgery, coupon abuse, and invoice unauthorized disclosure are structurally impossible.

---

## 2. Core Billing Security Controls

### 2.1 Server-Side Price Calculation & Tamper Resistance
- **Threat:** Malicious client submits altered `amount` in checkout payload (e.g. `$0.01` instead of `$499.00`).
- **Defense Implementation:**
  - In `BillingPortalController::checkout()`, the backend ignores any client-supplied price.
  - The pricing engine queries the active `Plan` model directly from the database based on `plan_id` and `billing_cycle` (`monthly` vs `annual`).
  - Applicable discounts (from validated `Coupon` records) are calculated strictly on the backend.
  - Currency conversion rates and regional rules are applied server-side.
- **Verification Evidence:** `BillingSubscriptionTest::test_checkout_recalculates_price_on_backend_and_creates_invoice` (Passed). Client cannot dictate transaction value.

### 2.2 Idempotency & Double-Spend Prevention
- **Threat:** Network retries or rapid double-clicking results in multiple charges for the same subscription checkout.
- **Defense Implementation:**
  - Checkout requests accept an `idempotency_key` (UUID or high-entropy string).
  - The `BillingService` checks for an existing payment or pending invoice with the same key within the transaction window (24 hours).
  - If a matching key exists, the original checkout session/invoice is returned without re-initiating gateway charges.
- **Verification Evidence:** `BillingSubscriptionTest::test_checkout_enforces_idempotency_key_deduplication` (Passed). Repeated submissions return the identical invoice record.

### 2.3 Coupon Single-Redemption & Expiry Enforcement
- **Threat:** Re-use of one-time promotional discount codes or redemption of expired coupons.
- **Defense Implementation:**
  - `CouponRedemption` table tracks redemptions indexed by `(coupon_id, user_id)`.
  - In `BillingService::validateCoupon()`, the coupon must be active, not expired (`valid_until >= now()`), within max global redemptions (`current_redemptions < max_redemptions`), and unredeemed by the requesting user.
- **Verification Evidence:** `BillingSubscriptionTest::test_coupon_validates_and_enforces_single_redemption_per_user` (Passed). Second redemption attempts are rejected with HTTP 422.

---

## 3. Gateway Webhook Security & Signature Verification

### 3.1 Vulnerability Remediation (VULN-GATEWAY-01)
During Phase 7F audit, it was discovered that `StripeGatewayAdapter` and `RazorpayGatewayAdapter` used static fallback test secrets (`'whsec_test_secret'`, `'rzp_test_secret'`) if environment variables were omitted.

**Remediation Applied:**
1. In non-testing environments, missing secrets now **fail closed immediately** (`WebhookResult(success: false, errorMessage: '... webhook secret is not configured.')`).
2. Webhooks require valid cryptographically signed headers:
   - **Stripe:** Validates `Stripe-Signature` parsing timestamp (`t=`) and v1 signature (`v1=`). Enforces replay window (rejects timestamps older than 300 seconds). Computes HMAC-SHA256 signature and verifies with constant-time `hash_equals()`.
   - **Razorpay:** Validates `X-Razorpay-Signature` against HMAC-SHA256 of the raw payload using constant-time `hash_equals()`.
3. In testing environments without configured secrets, fallback is restricted to explicit test mocks.

### 3.2 Automated Test Evidence
- `SecurityHardeningTest::test_stripe_webhook_rejects_missing_or_invalid_signature`: Passed (HTTP 400).
- `SecurityHardeningTest::test_razorpay_webhook_rejects_missing_or_invalid_signature`: Passed (HTTP 400).

---

## 4. Regional Payment Routing & Compliance

### 4.1 Payment Routing Matrix
The gateway registry evaluates incoming checkout requests using geographical and currency rules:

| Region / Currency | Available Payment Gateways | Verification Method |
|---|---|---|
| Bangladesh (`BDT`) | bKash, Nagad, Manual Bank Wire | Automated API Token / Admin Proof Review |
| India (`INR`) | Razorpay, Stripe, Manual Bank Wire | Webhook Signature / Hosted Checkout |
| United States (`USD`) | Stripe (Cards, Apple Pay, Google Pay) | Webhook HMAC-SHA256 Signature |
| European Union (`EUR`) | Stripe (SEPA, Cards, SOFORT) | Webhook HMAC-SHA256 Signature |
| Global Other | Stripe, Manual Wire Transfer | Webhook / Administrative Reconciliation |

- Verified by `BillingSubscriptionTest::test_regional_payment_method_selection_filters_by_country_and_currency` (Passed).

---

## 5. Proration Engine & Subscription State Machine

### 5.1 Proration Mathematics
When a subscriber upgrades from an active plan to a higher tier mid-cycle:
1. Calculates remaining days in the active billing period.
2. Credits the unused monetary portion of the current plan toward the new plan.
3. Invoices only the net prorated difference.
- Verified by `BillingSubscriptionTest::test_proration_engine_credits_unused_time_towards_upgrade` (Passed).

### 5.2 Subscription State Machine
Subscriptions follow strict state transitions:
- `pending_payment` -> `active` (on payment confirmation)
- `active` -> `canceled` (auto-cancel at end of billing period or admin cancel)
- `canceled` -> `active` (reactivation before billing cycle end)
- `active` -> `expired` (failed renewal or grace period expiration)
- Verified by `BillingSubscriptionTest::test_subscription_lifecycle_state_machine_transitions` (Passed).

---

## 6. Invoices, Receipts & Financial Auditability

### 6.1 Tamper-Proof Invoice Records
- Every invoice generated by Nexovant is assigned an immutable sequential number (`INV-YYYYMM-XXXXX`).
- Line items, subtotal, tax, discount, and grand total are frozen in database columns upon invoice creation.
- Invoice PDF receipts are generated on demand via `PdfRenderingService` and accessible only by the owning subscriber or an authorized admin.
- Verified by `BillingSubscriptionTest::test_invoice_receipt_pdf_generation_and_access_security` (Passed).

### 6.2 Administrative Payment Reconciliation
- In `PaymentAdminController`, administrators can review manual payment proofs, reconcile discrepancies, audit transaction logs, and issue refunds.
- Refunds trigger gateway adapter refund calls and transition payment records to `refunded` with full audit logging.
- Verified by `BillingSubscriptionTest::test_admin_refund_processing_and_validation` (Passed).

---

## 7. Conclusion

The billing engine provides institutional-grade financial integrity, prevents double-spending, enforces cryptographic signature verification across external gateways, and maintains strict access control on all financial records.
