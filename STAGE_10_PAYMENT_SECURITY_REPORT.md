# NEXOVANT — STAGE 10 PAYMENT SECURITY REPORT
## Threat Modeling, Cryptographic Verification & Financial Defense in Depth

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 10  
**Status:** Security Hardened & Validated  
**Environment:** Google Antigravity  

---

## 1. Executive Summary

Financial security in Nexovant is governed by an absolute zero-trust policy. Because payment transactions represent irreversible financial exchange and grant access to classified technology research dossiers, the payment subsystem implements multi-layered defensive controls spanning cryptographic signature validation, replay defense, strict multi-tenant isolation, idempotency deduplication, and zero-storage PCI-DSS hygiene.

---

## 2. Threat Modeling & Attack Surface Defense

| Threat Vector | Attack Mechanism | Nexovant Stage 10 Defensive Control | Severity |
| :--- | :--- | :--- | :--- |
| **Price Tampering** | Client modifies POST payload with lower amount or currency arbitrage | Backend ignores client amount completely; recalculates `subtotal` strictly from database `Plan` model. | **Critical** |
| **Cross-Tenant Inspection** | Subscriber attempts accessing another company's invoice or payment status | Authoritative ownership check on `subscription.user_id === user.id`. Returns `403 Forbidden`. | **High** |
| **Webhook Replay Attack** | Malicious actor captures valid webhook payload and resends to grant fraudulent subscription | Timestamp tolerance check (&lt; 300 seconds) + HMAC-SHA256 signature verification + event deduplication table. | **Critical** |
| **Double Spending / Duplicate Charge** | Fast double-click on checkout button or network retry | Client-generated deterministic `idempotency_key` checked in database before transaction initiation. | **High** |
| **Card Data Compromise (PCI Scope)** | Interception or leakage of PAN, CVV, or card expiry | Zero card data touches Nexovant servers. Tokenized checkout via Stripe Elements/Checkout and bKash Tokenized API. | **Critical** |
| **Fake TrxID Submission** | Subscriber enters fabricated or already-used TrxID | Unique constraint on `payment_proofs.transaction_id`; duplicate submissions rejected with `422 DUPLICATE_TRANSACTION`. | **Medium** |

---

## 3. Server-Authoritative Price Calculation

Nexovant adheres strictly to Rule 6 and Rule 12 of production engineering:
- Under **no circumstances** does the backend accept an `amount` parameter from the frontend during checkout.
- Price calculation is deterministic and calculated exclusively in `CheckoutService.php`:
  ```php
  // Backend Price Recalculation (NEVER trust client price)
  if ($currency === 'BDT') {
      $subtotal = $billingCycle === 'annual'
          ? (float) $plan->price_bdt_annual
          : (float) $plan->price_bdt_monthly;
  } else {
      $baseUsd = $billingCycle === 'annual'
          ? (float) $plan->price_usd_annual
          : (float) $plan->price_usd_monthly;

      $subtotal = match ($currency) {
          'EUR' => round($baseUsd * 0.92, 2),
          'GBP' => round($baseUsd * 0.79, 2),
          'CAD' => round($baseUsd * 1.35, 2),
          default => round($baseUsd, 2),
      };
  }
  ```

---

## 4. Webhook Security & Replay Attack Defense

Incoming webhook callbacks from external gateways (Stripe, bKash, Nagad) are verified prior to parsing:

```
Webhook Callback (HTTP POST)
         │
         ▼
[Step 1: Gateway Key & Secret Lookup]
         │ (Database: payment_gateways or encrypted config)
         ▼
[Step 2: Cryptographic Signature Verification]
         │ Stripe: HMAC-SHA256 (Stripe-Signature header)
         │ Nagad: Public/Private RSA Key verification
         ▼
[Step 3: Timestamp Replay Defense]
         │ abs(now() - event_timestamp) <= 300 seconds (5 minutes)
         │ If timestamp > 300s -> Reject with 400 Bad Request
         ▼
[Step 4: Idempotent Event Deduplication]
         │ Check PaymentWebhookEvent where event_id = id
         │ If already processed -> Return 200 OK immediately
         ▼
[Step 5: Ledger State Machine Update & Subscription Activation]
```

---

## 5. Tenant Isolation & Access Security

Strict authorization barriers protect invoice receipts, transaction references, and payment proofs:
1. **Invoice Status Inspection (`/api/v1/billing/checkout/status/{reference}`)**:
   ```php
   if ($invoice->subscription && $invoice->subscription->user_id !== $user->id && !$user->isAdmin()) {
       return response()->json([
           'success' => false,
           'message' => 'Unauthorized access to payment status.',
       ], 403);
   }
   ```
2. **Automated Test Evidence**:
   - `test_payment_status_endpoint_enforces_subscriber_tenant_isolation_403`: Verifies that attempting to read another subscriber's transaction reference triggers an immediate `403 Forbidden` response.
   - `test_cross_user_invoice_access_blocked_with_403_forbidden`: Verifies that unauthorized PDF invoice download attempts are blocked.

---

## 6. Audit Logging & Immutable Compliance Ledger

All administrative actions on payment transactions (gateway configuration changes, manual payment verifications, and financial ledger reconciliations) create immutable audit log records via `AuditLog::create()`:

- **Recorded Metadata**:
  - `user_id`: Administrator ID executing the action.
  - `action`: E.g. `admin.payment.verify`, `admin.payment.reconciliation_run`, `admin.payment_gateway.update`.
  - `ip`: Client IP address.
  - `user_agent`: Administrative browser fingerprint.
  - `timestamp`: High-precision ISO 8601 UTC timestamp.
