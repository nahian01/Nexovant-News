# NEXOVANT — STAGE 10 API INTEGRATION REPORT
## Comprehensive Regional Payment & Checkout API Specification

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 10  
**Status:** Implemented, Tested & Verified  
**Environment:** Google Antigravity  

---

## 1. Overview

Stage 10 expands Nexovant's billing API surface with dedicated, region-aware checkout, payment status resolution, safe recovery retry, and administrative reconciliation endpoints.

All financial calculations, gateway compatibility filters, currency restrictions, and coupon redemptions are strictly server-authoritative.

---

## 2. API Endpoints Specification

### 2.1 Checkout Configuration & Options
- **Endpoint**: `GET /api/v1/billing/checkout-options`
- **Access**: Public / Authenticated (`auth:sanctum` optional)
- **Description**: Returns dynamic list of supported countries, supported settlement currencies, and server-filtered eligible payment methods.

#### Request Parameters (Query String):
| Parameter | Type | Required | Default | Description |
| :--- | :--- | :--- | :--- | :--- |
| `country` | string | No | `US` (or `CF-IPCountry`) | ISO 3166-1 alpha-2 code (e.g. `BD`, `US`, `GB`) |
| `currency` | string | No | `USD` (or `BDT` for BD) | Currency ISO code (`BDT`, `USD`, `EUR`, `GBP`, `CAD`) |
| `interval` | string | No | `monthly` | `monthly` or `annual` |

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "country": "BD",
  "currency": "BDT",
  "interval": "annual",
  "countries": [
    {
      "code": "BD",
      "name": "Bangladesh",
      "flag": "🇧🇩",
      "default_currency": "BDT",
      "supported_currencies": ["BDT", "USD"],
      "region": "South Asia"
    },
    {
      "code": "US",
      "name": "United States",
      "flag": "🇺🇸",
      "default_currency": "USD",
      "supported_currencies": ["USD"],
      "region": "North America"
    }
  ],
  "currencies": [
    {
      "code": "BDT",
      "name": "Bangladeshi Taka",
      "symbol": "৳",
      "flag": "🇧🇩",
      "decimals": 2,
      "is_default_for": ["BD"]
    },
    {
      "code": "USD",
      "name": "US Dollar",
      "symbol": "$",
      "flag": "🇺🇸",
      "decimals": 2,
      "is_default_for": ["US", "GLOBAL"]
    }
  ],
  "eligible_payment_methods": [
    {
      "id": "bkash",
      "gateway_key": "bkash",
      "name": "bKash Mobile Payment",
      "type": "mfs",
      "currency": "BDT",
      "icon": "smartphone",
      "description": "Fast and secure payment via bKash App or USSD (*247#).",
      "supports_recurring": false,
      "is_hosted": true,
      "badge": "Instant & Local"
    },
    {
      "id": "nagad",
      "gateway_key": "nagad",
      "name": "Nagad Digital Payment",
      "type": "mfs",
      "currency": "BDT",
      "icon": "smartphone",
      "description": "Pay via Nagad App or USSD (*167#).",
      "supports_recurring": false,
      "is_hosted": true,
      "badge": "Instant & Local"
    },
    {
      "id": "manual_bank",
      "gateway_key": "manual_bank",
      "name": "Corporate Bank Transfer / Wire",
      "type": "bank_transfer",
      "currency": "BDT",
      "icon": "building-2",
      "description": "Direct institutional transfer to Nexovant Corporate Accounts (City Bank / SCB).",
      "supports_recurring": false,
      "is_hosted": false,
      "badge": "Verified Review"
    }
  ]
}
```

---

### 2.2 Secure Checkout Initiation
- **Endpoint**: `POST /api/v1/billing/checkout`
- **Access**: Authenticated (`auth:sanctum`)
- **Description**: Recomputes plan prices on the server, creates an invoice, applies validated coupons, enforces idempotency, and initializes transaction with selected gateway.

#### Request Body (`application/json`):
```json
{
  "plan_id": "01a1e0f0-c112-70b1-912a-3e8111985da0",
  "billing_cycle": "annual",
  "currency": "BDT",
  "country": "BD",
  "payment_method": "bkash",
  "coupon_code": "LAUNCH25",
  "idempotency_key": "chk_89x12a9c401b22",
  "billing_name": "Dr. Elena Rostova",
  "billing_email": "subscriber@nexovant.test",
  "billing_address": "House 14, Road 11, Gulshan-1, Dhaka"
}
```

#### Success Response (`201 Created`):
```json
{
  "success": true,
  "data": {
    "success": true,
    "invoice": {
      "id": "01a1e0f2-d482-7368-a1c0-b4cfc5638812",
      "invoice_number": "INV-2026-FF9RAE",
      "subtotal": "9990.00",
      "discount": "0.00",
      "total": "9990.00",
      "currency": "BDT",
      "status": "unpaid"
    },
    "payment": {
      "id": "01a1e0f2-d482-7368-a1c0-b4cfc5638813",
      "gateway_key": "bkash",
      "provider_reference": "BK7819284XLA",
      "amount": "9990.00",
      "currency": "BDT",
      "status": "pending"
    },
    "gateway_key": "bkash",
    "transaction_reference": "BK7819284XLA",
    "instructions": {
      "type": "mfs_tokenized",
      "merchant_number": "01700000000",
      "counter": "1",
      "reference": "NEXOVANT",
      "steps": [
        "1. Open bKash App or dial *247#",
        "2. Select Make Payment",
        "3. Enter Merchant Account Number: 01700000000",
        "4. Enter exact amount: ৳9,990.00",
        "5. Enter reference: NEXOVANT",
        "6. Complete PIN and submit the TrxID here for instant verification"
      ]
    }
  }
}
```

---

### 2.3 Authoritative Payment Status Resolution
- **Endpoint**: `GET /api/v1/billing/checkout/status/{reference}`
- **Access**: Authenticated (`auth:sanctum`)
- **Description**: Resolves status by transaction reference, payment ID, or invoice ID. Strict tenant authorization prevents cross-user inspection (returns `403 Forbidden` if unauthorized).

#### Success Response (`200 OK` - Paid State):
```json
{
  "success": true,
  "data": {
    "reference": "WIRE-YKPZC3ZQLC",
    "status": "paid",
    "invoice": {
      "id": "01a1e0f2-d482-7368-a1c0-b4cfc5638812",
      "invoice_number": "INV-2026-FF9RAE",
      "status": "paid",
      "subtotal": 9990,
      "discount": 0,
      "total": 9990,
      "currency": "BDT",
      "due_date": "2026-11-23T02:42:52+06:00",
      "paid_at": "2026-11-16T02:43:55+06:00"
    },
    "payment": {
      "id": "01a1e0f2-d482-7368-a1c0-b4cfc5638813",
      "gateway_key": "manual_bank",
      "payment_method": "manual_bank",
      "amount": 9990,
      "currency": "BDT",
      "status": "verified",
      "provider_reference": "WIRE-YKPZC3ZQLC"
    },
    "subscription": {
      "id": "01a1e0e8-0912-70b1-88f2-1110091823ab",
      "status": "active",
      "is_active": true,
      "plan_name": "Executive Intelligence",
      "billing_cycle": "annual",
      "next_renewal_at": "2027-11-16T02:43:55+06:00"
    },
    "receipt_url": "/api/v1/billing/invoices/01a1e0f2-d482-7368-a1c0-b4cfc5638812/download"
  }
}
```

---

### 2.4 Safe Payment Retry
- **Endpoint**: `POST /api/v1/billing/checkout/retry`
- **Access**: Authenticated (`auth:sanctum`)
- **Description**: Retries an unpaid invoice with a new payment method without generating duplicate invoices.

#### Request Body (`application/json`):
```json
{
  "invoice_id": "01a1e0f2-d482-7368-a1c0-b4cfc5638812",
  "payment_method": "nagad"
}
```

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Payment retry initiated.",
  "data": {
    "invoice": {
      "id": "01a1e0f2-d482-7368-a1c0-b4cfc5638812",
      "invoice_number": "INV-2026-FF9RAE",
      "status": "unpaid"
    },
    "payment": {
      "id": "01a1e0f3-9912-70a1-89ab-910293847501",
      "payment_method": "nagad",
      "status": "pending"
    },
    "transaction_reference": "NG9928172034A"
  }
}
```

---

### 2.5 Manual Payment Proof Submission
- **Endpoint**: `POST /api/v1/billing/submit-manual-payment`
- **Access**: Authenticated (`auth:sanctum`)
- **Description**: Submits TrxID or wire voucher for compliance verification. Enforces global deduplication on `transaction_id`.

#### Duplicate Rejection Response (`422 Unprocessable Content`):
```json
{
  "success": false,
  "error": {
    "code": "DUPLICATE_TRANSACTION",
    "message": "This transaction reference ID has already been submitted for verification."
  }
}
```

---

### 2.6 Admin Payment Verification Desk
- **Endpoint**: `POST /api/v1/admin/payments/{id}/verify`
- **Access**: Authenticated Admin (`auth:sanctum`, `role:admin`)
- **Description**: Verifies bank wire or MFS transaction, credits parent invoice, transitions subscription to `active`, and records immutable audit log.

---

### 2.7 Automated Reconciliation Run
- **Endpoint**: `POST /api/v1/admin/billing/reconcile`
- **Access**: Authenticated Admin (`auth:sanctum`, `role:admin`)
- **Description**: Executes comprehensive reconciliation audit across all invoices, payments, and subscription statuses.
