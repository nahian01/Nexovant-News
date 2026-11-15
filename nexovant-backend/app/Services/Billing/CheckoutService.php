<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\DTO\PaymentInitiationResult;
use App\Services\Billing\DTO\PaymentIntentDTO;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected RegionalPaymentSelectionService $regionalService,
        protected CouponService $couponService,
        protected InvoiceService $invoiceService,
        protected SubscriptionLifecycleService $lifecycleService
    ) {}

    /**
     * Complete checkout workflow with backend price recalculation and idempotency.
     */
    public function processCheckout(User $user, array $data): array
    {
        $plan = Plan::where('id', $data['plan_id'])
            ->orWhere('slug', $data['plan_id'])
            ->firstOrFail();

        $billingCycle = strtolower($data['billing_cycle'] ?? 'monthly');
        $currency = strtoupper($data['currency'] ?? 'USD');
        $country = strtoupper($data['country'] ?? 'US');
        $gatewayKey = strtolower($data['payment_method'] ?? 'stripe');
        $couponCode = !empty($data['coupon_code']) ? trim($data['coupon_code']) : null;
        $idempotencyKey = $data['idempotency_key'] ?? ('idemp_' . Str::random(16));

        // 1. Prevent duplicate checkout submissions
        $existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first();
        if ($existingPayment) {
            $invoice = $existingPayment->invoice;
            return [
                'success' => true,
                'is_duplicate' => true,
                'invoice' => $invoice,
                'payment' => $existingPayment,
                'redirect_url' => null,
            ];
        }

        // 2. Validate country & currency compatibility
        if (!$this->regionalService->validateCountryCurrency($country, $currency)) {
            throw ValidationException::withMessages([
                'currency' => ["Currency {$currency} is not compatible with country {$country}."],
            ]);
        }

        // 3. Backend Price Recalculation (NEVER trust client price)
        $subtotal = $currency === 'BDT'
            ? ($billingCycle === 'annual' ? (float) $plan->price_bdt_annual : (float) $plan->price_bdt_monthly)
            : ($billingCycle === 'annual' ? (float) $plan->price_usd_annual : (float) $plan->price_usd_monthly);

        // Check if promotional price applies
        if ($billingCycle === 'annual' && (float) $plan->promotional_price_annual > 0) {
            $subtotal = (float) $plan->promotional_price_annual;
        } elseif ($billingCycle === 'monthly' && (float) $plan->promotional_price_monthly > 0) {
            $subtotal = (float) $plan->promotional_price_monthly;
        }

        // 4. Validate and apply coupon
        $discount = 0.0;
        $coupon = null;
        if ($couponCode) {
            $couponResult = $this->couponService->validateCoupon($couponCode, $plan, $country, $subtotal, $user);
            $discount = $couponResult['discount_amount'];
            $coupon = \App\Models\Coupon::find($couponResult['coupon_id']);
        }

        // 5. Calculate taxes (configurable per country)
        $taxRate = $country === 'BD' ? 0.0 : 0.0; // digital services tax rate if configured

        // 6. Create or update user subscription record
        $subscription = $user->subscription;
        if (!$subscription) {
            $subscription = Subscription::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'currency' => $currency,
                'status' => 'incomplete',
                'billing_country' => $country,
            ]);
        } else {
            $subscription->update([
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'currency' => $currency,
                'billing_country' => $country,
            ]);
        }

        // 7. Create invoice
        $invoice = $this->invoiceService->createInvoice(
            $subscription,
            $subtotal,
            $discount,
            $taxRate,
            $coupon?->id,
            [
                'name' => $data['billing_name'] ?? $user->name,
                'email' => $data['billing_email'] ?? $user->email,
                'address' => $data['billing_address'] ?? null,
            ],
            [
                [
                    'description' => "Nexovant {$plan->name} ({$billingCycle})",
                    'quantity' => 1,
                    'unit_price' => $subtotal,
                    'amount' => $subtotal,
                ]
            ]
        );

        if ($coupon && $discount > 0) {
            $this->couponService->redeemCoupon($coupon, $user, $invoice, $discount);
        }

        // 8. If Total is zero (Free plan or 100% coupon discount), activate immediately
        if ($invoice->total == 0) {
            $this->invoiceService->markPaid($invoice);
            $this->lifecycleService->activateSubscription($subscription, null, $user, 'Zero-amount plan checkout completed.');

            return [
                'success' => true,
                'is_free' => true,
                'invoice' => $invoice,
                'subscription' => $subscription->fresh(['plan.entitlements']),
                'message' => 'Subscription activated successfully.',
            ];
        }

        // 9. Initiate payment gateway transaction
        $adapter = $this->gatewayManager->getGateway($gatewayKey);

        $intent = new PaymentIntentDTO(
            invoiceId: $invoice->id,
            userId: $user->id,
            amount: (float) $invoice->total,
            currency: $currency,
            paymentMethod: $gatewayKey,
            country: $country,
            customerEmail: $user->email,
            customerName: $user->name,
            idempotencyKey: $idempotencyKey,
            returnUrl: $data['return_url'] ?? null
        );

        $initiation = $adapter->initiatePayment($intent);

        // 10. Record payment attempt
        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'payment_method' => $gatewayKey,
            'gateway_key' => $gatewayKey,
            'provider_reference' => $initiation->transactionReference,
            'idempotency_key' => $idempotencyKey,
            'amount' => $invoice->total,
            'currency' => $currency,
            'status' => $initiation->status,
            'raw_response' => $initiation->rawResponse,
        ]);

        return [
            'success' => $initiation->success,
            'invoice' => $invoice,
            'payment' => $payment,
            'gateway_key' => $gatewayKey,
            'transaction_reference' => $initiation->transactionReference,
            'redirect_url' => $initiation->redirectUrl,
            'client_secret' => $initiation->clientSecret,
            'instructions' => $initiation->instructions,
            'error_message' => $initiation->errorMessage,
        ];
    }
}
