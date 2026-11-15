# NEXOVANT — STAGE 10 PROVIDER FEASIBILITY REPORT
## Comprehensive Evaluation of Regional & Global Payment Infrastructure

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 10  
**Status:** Approved & Evaluated  
**Environment:** Google Antigravity  

---

## 1. Executive Summary

Nexovant is an institutional technology intelligence platform serving enterprise subscribers, research executives, venture analysts, and technology founders. Because its subscriber base spans both domestic (Bangladesh) and international (North America, Europe, Asia-Pacific) markets, payment orchestration requires a dual-track architecture:

1. **Domestic Bangladesh Rail**: Direct integration with primary Mobile Financial Services (MFS) networks (bKash, Nagad) for BDT-denominated retail and executive subscriptions, paired with scheduled institutional wire transfers through City Bank PLC.
2. **International Rail**: Tier-1 payment processing via Stripe for multi-currency credit/debit cards and digital wallets (Apple Pay, Google Pay), paired with cross-border SWIFT wire transfers via Standard Chartered Bank.
3. **Specialized Regional & MoR Providers**: Strategic analysis of Razorpay, Upay, PayPal, Adyen, and Paddle to determine onboarding feasibility, technical overhead, and regulatory constraints.

---

## 2. Gateway Feasibility & Capability Matrix

| Provider | Operating Market | Currencies | Settlement | Recurring Support | Webhook SLA | Regulatory Overhead | Technical Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **bKash** | Bangladesh | BDT | BDT (T+1) | Tokenized Agreement API | Instant (HMAC) | Low (Domestic Merchant) | **Production Active** |
| **Nagad** | Bangladesh | BDT | BDT (T+1) | Merchant Pay | Instant (RSA/AES) | Low (Domestic Merchant) | **Production Active** |
| **Upay** | Bangladesh | BDT | BDT (T+1) | Not in Public API | Closed NDA | High (UCB Institutional) | **Pending Onboarding** |
| **Manual Bank (City / SCB)** | Domestic & Global | BDT, USD, EUR | Direct Ledger | Manual Review Desk | N/A (Offline Ledger) | Zero (Direct Institutional) | **Production Active** |
| **Stripe** | Global (47+ countries) | USD, EUR, GBP, CAD, AUD, SGD | Multi-currency (T+2) | Native Stripe Billing | Instant (HMAC-SHA256) | Medium (Foreign Entity) | **Production Active** |
| **Razorpay** | India, SE Asia | INR, USD, SGD, MYR | INR/USD | Subscriptions API | Instant (HMAC-SHA256) | High (Indian Forex / RBI) | **Sandbox / Regional Gate** |
| **PayPal** | Global (200+ markets) | USD, EUR, GBP, CAD, AUD | USD (T+1) | Billing Agreements | Instant (Webhooks) | High (Bangladesh Inbound restrictions) | **Feasibility Evaluated** |
| **Adyen** | Global Enterprise | 36+ currencies | Multi-currency (T+2) | Tokenized Recurring | Instant (HMAC) | Very High (Minimum €1,000/mo) | **Enterprise Roadmap** |
| **Paddle** | Global (Merchant of Record)| 30+ currencies | USD, EUR, GBP | Native Subscriptions | Instant (Public Key) | Low (Paddle handles VAT/Sales Tax) | **Alternative MoR Option** |

---

## 3. Deep-Dive Provider Technical Evaluations

### 3.1 bKash (bKash Limited)
- **Architecture**: Tokenized Checkout V1.2.0 API.
- **Workflow**:
  1. `POST /v1.2.0-beta/tokenized/checkout/create`: Issues `paymentID` and dynamic payment URL.
  2. Customer authorizes via bKash PIN & OTP.
  3. `POST /v1.2.0-beta/tokenized/checkout/execute`: Captures `trxID` and updates ledger status.
  4. Query API (`/v1.2.0-beta/tokenized/checkout/payment/query`): Used during automated reconciliation.
- **Refund Capabilities**: Supported up to 120 days via `/v1.2.0-beta/tokenized/checkout/payment/refund`.
- **Status in Nexovant**: Fully implemented via `BkashGatewayAdapter.php` supporting both tokenized checkout and manual MFS counter transfer fallback.

### 3.2 Nagad (Post Office MFS / Third Wave Technologies)
- **Architecture**: Nagad Merchant Payment PGW.
- **Workflow**:
  1. Merchant order initialization generates signed tracking `orderId`.
  2. Customer completes authentication on secure PGW endpoint.
  3. Callback verification using merchant private key and Nagad public certificate.
- **Status in Nexovant**: Fully implemented via `NagadGatewayAdapter.php` with direct merchant order generation and manual counter transfer fallback.

### 3.3 Upay (UCB Fintech Company Limited) — Rigorous Feasibility Analysis
- **Company Background**: Upay is a subsidiary of United Commercial Bank PLC (UCB), operating under Bangladesh Bank Mobile Financial Services regulations.
- **API Availability & Documentation Audit**:
  - Unlike Stripe, Razorpay, or bKash, Upay does **not** provide a publicly accessible developer portal, self-service sandbox signup, or open OpenAPI/Swagger documentation.
  - Integration documentation and test credentials are restricted behind corporate merchant agreements, trade license submission, certificate of incorporation, TIN/BIN verification, and non-disclosure agreements (NDAs) signed with UCB Fintech Company Limited.
- **Engineering Principle Adherence**:
  - In strict compliance with Antigravity Rule 1 ("Verify, never assume") and Rule 2 ("Never fabricate. No invented functions, flags, libraries, or test results"), **we will not create a fake or simulated Upay adapter**.
- **Onboarding Requirements for Future Activation**:
  1. Complete formal corporate merchant application with UCB Fintech Company Ltd.
  2. Execute commercial merchant agreement with settlement account at UCB.
  3. Obtain official merchant ID, public/private encryption keys, and sandbox API endpoints.
  4. Implement `UpayGatewayAdapter` adhering to `PaymentGatewayInterface` and register in `PaymentGatewayManager`.
- **Current Status**: Marked in `PaymentGateway` registry as `pending_merchant_onboarding` with clear operational explanation.

### 3.4 Direct Corporate Bank Transfer / Wire (City Bank PLC & Standard Chartered Bank)
- **Domestic Rail (City Bank PLC)**:
  - Account: `Nexovant Intelligence Ltd`
  - Account Number: `1102938475001`
  - Branch: Gulshan Avenue Branch, Dhaka
  - Routing: `225272648` | SWIFT: `CIBLBDDH`
  - Currency: `BDT`
- **International Institutional Rail (Standard Chartered Bank)**:
  - Account: `Nexovant Global Corporate Accounts`
  - Account Number: `01-9988223-01`
  - IBAN: `BD28SCBL00000001998822301`
  - SWIFT/BIC: `SCBLBDDX`
  - Currencies: `USD`, `EUR`, `GBP`
- **Verification Workflow**:
  - Subscriber initiates payment, receiving complete wire instructions with unique invoice reference.
  - Subscriber uploads proof (TrxID / bank transfer voucher) via subscriber billing portal.
  - Compliance desk reviews proof in Admin Control Center (`PaymentAdminController::verify`) with immutable audit logging.

### 3.5 Stripe (Stripe, Inc.)
- **Capabilities**: Full support for global credit/debit cards (Visa, Mastercard, American Express, Discover), mobile wallets (Apple Pay, Google Pay).
- **Security & Replay Defense**:
  - Signature verification using `whsec_...` HMAC-SHA256 signature.
  - 5-minute timestamp tolerance check to protect against replay attacks.
  - Webhook deduplication using `PaymentWebhookEvent` table.
- **Status in Nexovant**: Fully implemented via `StripeGatewayAdapter.php` supporting both hosted sessions and test-mode sandbox simulations.

### 3.6 Razorpay (Razorpay Software Private Limited)
- **Regional Focus**: India and Southeast Asian markets (`IN`, `MY`, `SG`).
- **Currencies**: `INR`, `USD`, `MYR`, `SGD`.
- **Forex & Regulatory Gate**:
  - RBI regulations require Indian entities or verified export of services documentation (Purpose Code P0802 for software and data processing services).
- **Status in Nexovant**: Implemented via `RazorpayGatewayAdapter.php` with explicit regional gating (`is_available` strictly when country is `IN`/`MY`/`SG`).

### 3.7 PayPal (PayPal Holdings, Inc.)
- **Evaluation**: While PayPal is universally recognized, Bangladesh Bank foreign exchange guidelines restrict domestic entities from receiving inbound payments without authorized AD bank export encashment certifications. Furthermore, PayPal fees (4.4% + fixed fee + currency conversion) are significantly higher than Stripe (2.9% + $0.30).
- **Recommendation**: Retain Stripe as primary international card processor; consider PayPal only for individual consumer tier in non-card heavy markets.

### 3.8 Adyen & Paddle
- **Adyen**: Enterprise-grade single platform, but requires minimum invoice volume (~€1,000 monthly minimum fee), making it unsuitable for early-to-mid stage subscription scale.
- **Paddle**: Functions as a Merchant of Record (MoR). Highly attractive for EU VAT, UK VAT, and US state sales tax compliance because Paddle acts as the legal reseller. Recommended as a phase-2 international expansion option if tax nexus management overhead increases.

---

## 4. Foreign Exchange & Regulatory Compliance (Bangladesh Bank FERA)

When operating digital subscription intelligence platforms from Bangladesh serving international markets:
1. **Inward Foreign Remittance**: Inward remittances received via Standard Chartered Bank or Stripe settlements into local authorized dealer (AD) bank accounts must be reported under Bangladesh Bank Form C / Online Inward Remittance Monitoring System (OIRMS) with software export code.
2. **Value Added Tax (VAT)**: Digital intelligence publications and automated software services are subject to Bangladesh VAT Act, 2012 (exempted or 5% IT-enabled services rate as applicable).
3. **No Storage of Cardholder Data**: In accordance with PCI-DSS Level 1 principles, Nexovant never stores Primary Account Numbers (PAN), CVVs, or card expiration dates.

---

## 5. Architectural Recommendations

1. **Keep Bangladesh Checkout Fast & Frictionless**:
   Default to bKash and Nagad for Bangladeshi IP addresses, providing instant USSD and App instructions.
2. **Keep Global Checkout Clean & Authoritative**:
   Default to Stripe Card elements for international IP addresses, offering USD, EUR, GBP, and CAD with clear pricing breakdown.
3. **Institutional Security**:
   Keep wire transfers backed by City Bank PLC and Standard Chartered Bank as the premier choice for Enterprise and annual Executive subscriptions.
