<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\PaymentGateway;

class RegionalPaymentSelectionService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected GatewayRegistryService $registryService
    ) {}

    /**
     * Resolve eligible payment options based on subscriber country, currency, and gateway capabilities.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEligiblePaymentMethods(string $country, string $currency, string $billingInterval = 'monthly'): array
    {
        $country = strtoupper(trim($country));
        $currency = strtoupper(trim($currency));

        $this->registryService->ensureGatewaysSeeded();

        $activeGateways = PaymentGateway::where('is_active', true)->get()->keyBy('gateway_key');
        $adapters = $this->gatewayManager->getAllGateways();

        $methods = [];

        foreach ($adapters as $key => $adapter) {
            $record = $activeGateways->get($key);
            if (!$record) {
                continue;
            }

            // Must be available for the country & currency
            if (!$adapter->isAvailableFor($country, $currency)) {
                continue;
            }

            // Country whitelist check if specified
            $countries = array_map('strtoupper', $record->supported_countries ?? []);
            if (!empty($countries) && !in_array('ALL', $countries, true) && !in_array('GLOBAL', $countries, true) && !in_array($country, $countries, true)) {
                continue;
            }

            // Currency check
            $currencies = array_map('strtoupper', $record->supported_currencies ?? []);
            if (!empty($currencies) && !in_array($currency, $currencies, true)) {
                continue;
            }

            // If recurring is required (e.g. future auto-renew) check capability
            $supportsRecurring = $record->supports_recurring;

            // Generate user-friendly payment option descriptors
            if ($key === 'bkash') {
                $methods[] = [
                    'id' => 'bkash',
                    'gateway_key' => 'bkash',
                    'name' => 'bKash Mobile Payment',
                    'type' => 'mfs',
                    'currency' => 'BDT',
                    'icon' => 'smartphone',
                    'description' => 'Fast and secure payment via bKash App or USSD (*247#).',
                    'supports_recurring' => false,
                    'is_hosted' => true,
                    'badge' => 'Instant & Local',
                ];
            } elseif ($key === 'nagad') {
                $methods[] = [
                    'id' => 'nagad',
                    'gateway_key' => 'nagad',
                    'name' => 'Nagad Digital Payment',
                    'type' => 'mfs',
                    'currency' => 'BDT',
                    'icon' => 'smartphone',
                    'description' => 'Pay via Nagad App or USSD (*167#).',
                    'supports_recurring' => false,
                    'is_hosted' => true,
                    'badge' => 'Instant & Local',
                ];
            } elseif ($key === 'stripe') {
                // Show if configured OR in sandbox
                $methods[] = [
                    'id' => 'stripe_card',
                    'gateway_key' => 'stripe',
                    'name' => 'Credit / Debit Card (Visa, Mastercard, Amex)',
                    'type' => 'card',
                    'currency' => $currency,
                    'icon' => 'credit-card',
                    'description' => 'International credit and debit cards, Apple Pay, and Google Pay.',
                    'supports_recurring' => true,
                    'is_hosted' => true,
                    'badge' => $record->test_mode ? 'Sandbox / Card' : 'Global',
                ];
            } elseif ($key === 'razorpay' && $record->merchant_verified && $record->is_configured) {
                $methods[] = [
                    'id' => 'razorpay',
                    'gateway_key' => 'razorpay',
                    'name' => 'Razorpay (Cards, UPI & NetBanking)',
                    'type' => 'upi_card',
                    'currency' => $currency,
                    'icon' => 'credit-card',
                    'description' => 'Regional payment methods for India and Southeast Asia.',
                    'supports_recurring' => true,
                    'is_hosted' => true,
                    'badge' => 'Regional',
                ];
            } elseif ($key === 'manual_bank') {
                $methods[] = [
                    'id' => 'manual_bank',
                    'gateway_key' => 'manual_bank',
                    'name' => 'Corporate Bank Transfer / Wire',
                    'type' => 'bank_transfer',
                    'currency' => $currency,
                    'icon' => 'building-2',
                    'description' => 'Direct institutional transfer to Nexovant Corporate Accounts (City Bank / SCB).',
                    'supports_recurring' => false,
                    'is_hosted' => false,
                    'badge' => 'Verified Review',
                ];
            }
        }

        return $methods;
    }

    /**
     * Validate that selected country and currency are compatible.
     */
    public function validateCountryCurrency(string $country, string $currency): bool
    {
        $country = strtoupper(trim($country));
        $currency = strtoupper(trim($currency));

        if ($currency === 'BDT' && $country !== 'BD') {
            // Only BD residents use BDT
            return false;
        }

        return true;
    }
}
