# NEXOVANT — STAGE 9 DESIGN SYSTEM AUDIT
## Subscription, Entitlements & Billing Experience Design Tokens

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 9  
**Design Audit Status:** **VALIDATED & ENFORCED**  
**Author:** Google Antigravity UI/UX Architecture  
**Date:** 2026-11-16  

---

## 1. Executive Design Philosophy

The Nexovant subscriber billing experience is designed as an executive control cockpit rather than a traditional consumer e-commerce checkout. Decision-makers and enterprise procurement teams require unambiguous visibility into their active intelligence entitlements, quota limits, renewal schedules, and financial records.

The interface prioritizes:
- **Calm Authority:** High-contrast neutral canvases (`#f8faf9`), crisp borders (`#e2e8e4`), and deliberate emerald accents (`#147a67`).
- **Telemetry Transparency:** Precise numeric and percentage usage meters rather than opaque limits.
- **Financial Integrity:** Explicit line-item proration breakdowns, clear currency formatting (USD `$` and BDT `৳`), and non-deceptive cancellation flows.
- **Enterprise Readiness:** Instant visual differentiation between individual and organization-pooled subscriptions.

---

## 2. Status Badge & Lifecycle Color Tokens

Subscription states must map to precise semantic color tokens across badges, cards, and notification banners:

| Lifecycle State | Badge Variant | Background Class | Text Class | Border Class | Context / Meaning |
|---|---|---|---|---|---|
| **Active** | `confirmed` | `bg-emerald-50` | `text-emerald-700` | `border-emerald-200` | Paid, verified subscription with active auto-renew. |
| **Trialing** | `developing` | `bg-amber-50` | `text-amber-700` | `border-amber-200` | Complimentary evaluation period; days remaining countdown. |
| **Past Due** | `risk` | `bg-rose-50` | `text-rose-700` | `border-rose-200` | Renewal payment failed; within grace period. Action required. |
| **Cancel at Period End** | `neutral` | `bg-indigo-50` | `text-indigo-700` | `border-indigo-200` | Canceled; retains full access until expiration date. |
| **Canceled / Expired** | `neutral` | `bg-slate-100` | `text-slate-600` | `border-slate-300` | Subscription terminated; downgraded to standard Free tier. |
| **Free Tier** | `neutral` | `bg-slate-50` | `text-slate-600` | `border-slate-200` | Baseline public intelligence access. |

---

## 3. Usage Meter & Quota Telemetry System

Real-time entitlement meters track subscriber consumption against plan quotas. The visual system uses dynamic color thresholds to guide subscriber behavior:

```
[==================== 45% ====================]  -> Normal (< 60%): Emerald (#10b981)
[================================ 75% ========]  -> Warning (60% - 79%): Amber (#f59e0b)
[========================================== 92%] -> Critical (≥ 80%): Rose (#ef4444) + "Upgrade Quota" CTA
```

### 3.1 Meter Styling Rules
- **Bar Height:** `h-2` (8px) with `rounded-full`.
- **Track Background:** `bg-slate-100` (neutral, low-contrast).
- **Fill Animation:** `transition-all duration-500 ease-out`.
- **Metrics Label:** `text-xs font-mono font-medium text-nexo-text-primary`.
- **Quota Exceeded State:** When `used >= limit`, bar fill is saturated `bg-rose-600`, accompanied by an inline warning alert and a one-click upgrade button.

---

## 4. Dual-Currency & Pricing Presentation

Nexovant serves both global enterprise clients and regional technology leaders in Bangladesh. Pricing presentation must strictly enforce dual-currency standards:

### 4.1 Currency Rules
- **USD ($):** Displayed as `$XX/mo` or `$XXX/yr`. Decimal points omitted for whole dollar amounts (`$49`, not `$49.00`).
- **BDT (৳):** Displayed as `৳XXXX/mo` or `৳XXXXX/yr`. Formatted with Bengali locale thousands separators where applicable.

### 4.2 Billing Interval Toggle
- Dual-segment switch (`Monthly` vs `Annual`).
- Annual toggle features a prominent savings pill: `Save ~20% Annual Plan` (`bg-primary-50 text-primary-700 border border-primary-200 text-[10px] font-semibold px-2 py-0.5 rounded-full`).
- Active pricing cards dynamically update prices, intervals, and savings calculations on toggle switch without page reload.

---

## 5. Modal Experience Standards

### 5.1 Proration Preview Modal
Before any plan change is confirmed, the system opens a dedicated proration preview dialog displaying:
1. **Source Tier:** Current plan, billing interval, and renewal date.
2. **Target Tier:** Selected new plan, billing interval, and next regular billing amount.
3. **Proration Ledger Table:**
   - Unused credit from current plan: `-$XX.XX` (highlighted in emerald).
   - Prorated charge for new plan: `+$XX.XX`.
   - Net balance due immediately: `$XX.XX`.
4. **Promotional Coupon Input:** Live discount preview before committing.
5. **Clear Confirmation:** Button displaying exact charge amount (e.g., `Confirm Upgrade — Charge $14.50`).

### 5.2 Subscription Cancellation Dialog
- Avoid dark patterns: Clear, respectful, and transparent.
- Highlights:
  - Exact date when intelligence features will cease (`ends_at`).
  - Specific features that will be lost (monitors, AI deep research, publication downloads).
  - Retention option: Switch to a lower plan or pause subscription.
  - Radio toggle: `Cancel at period end (Recommended)` vs `Cancel immediately`.
  - Required feedback reason selector for product telemetry.

### 5.3 Manual MFS / Wire Payment Proof Modal
- Tailored for Bangladeshi MFS (bKash, Nagad) and corporate bank wire workflows.
- Clear instructions detailing official merchant numbers and bank wire routing codes.
- Input fields: Transaction Reference ID (TrxID), Sender Account Number, Notes.
- Form validation preventing whitespace submission or duplicate submissions.

---

## 6. Responsive Architecture & Accessibility

- **Mobile Viewport (320px - 767px):**
  - Navigation tabs collapse to horizontally scrollable swipeable pills (`overflow-x-auto no-scrollbar`).
  - Plan comparison cards stack vertically with the popular/recommended tier highlighted on top.
  - Invoice ledger renders as responsive card list on small viewports and full data table on tablet/desktop.
  - Touch targets maintain minimum `44px x 44px` dimensions.
- **Keyboard Navigation & ARIA:**
  - Full keyboard accessibility for tabs (`ArrowLeft`, `ArrowRight`, `Enter`).
  - Modals trap focus and support `Esc` to dismiss.
  - Input fields have explicit label associations and `aria-describedby` for error states.
