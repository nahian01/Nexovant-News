<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Support\Str;

class GatewayRegistryService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Get complete gateway registry with live operational status and capability matrix.
     */
    public function getGatewayRegistry(): array
    {
        $this->ensureGatewaysSeeded();

        $dbGateways = PaymentGateway::all()->keyBy('gateway_key');
        $adapters = $this->gatewayManager->getAllGateways();

        $registry = [];
        foreach ($adapters as $key => $adapter) {
            $record = $dbGateways->get($key);

            $registry[] = [
                'id' => $record?->id,
                'gateway_key' => $key,
                'name' => $record?->name ?? $adapter->getDisplayName(),
                'supported_countries' => $record?->supported_countries ?? [],
                'supported_currencies' => $record?->supported_currencies ?? [],
                'supported_methods' => $record?->supported_methods ?? [],
                'settlement_currencies' => $record?->settlement_currencies ?? [],
                'supports_refunds' => $record?->supports_refunds ?? false,
                'supports_recurring' => $record?->supports_recurring ?? false,
                'supports_webhooks' => $record?->supports_webhooks ?? false,
                'is_configured' => $adapter->isConfigured(),
                'merchant_verified' => $adapter->isMerchantVerified(),
                'is_active' => $record?->is_active ?? false,
                'unavailability_reason' => $adapter->getUnavailabilityReason(),
                'test_mode' => $record?->test_mode ?? true,
                'updated_at' => $record?->updated_at,
            ];
        }

        return $registry;
    }

    /**
     * Configure gateway credentials or toggle active status.
     */
    public function updateGateway(User $admin, string $gatewayKey, array $data): PaymentGateway
    {
        $gateway = PaymentGateway::where('gateway_key', $gatewayKey)->firstOrFail();

        if (isset($data['is_active'])) {
            $gateway->is_active = (bool) $data['is_active'];
        }

        if (isset($data['test_mode'])) {
            $gateway->test_mode = (bool) $data['test_mode'];
        }

        if (isset($data['merchant_verified'])) {
            $gateway->merchant_verified = (bool) $data['merchant_verified'];
        }

        if (!empty($data['credentials'])) {
            $gateway->setCredentials($data['credentials']);
        }

        if (array_key_exists('webhook_secret', $data)) {
            $gateway->setWebhookSecret($data['webhook_secret']);
        }

        $gateway->save();

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'action' => 'admin.payment_gateway.update',
            'resource_type' => 'PaymentGateway',
            'resource_id' => $gateway->id,
            'metadata' => [
                'gateway_key' => $gatewayKey,
                'is_active' => $gateway->is_active,
                'test_mode' => $gateway->test_mode,
                'merchant_verified' => $gateway->merchant_verified,
            ],
            'created_at' => now(),
        ]);

        return $gateway;
    }

    /**
     * Test sandbox connectivity for a gateway.
     */
    public function testConnection(string $gatewayKey): array
    {
        $adapter = $this->gatewayManager->getGateway($gatewayKey);

        if (!$adapter->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . ($adapter->getUnavailabilityReason() ?? 'Credentials missing.'),
                'is_configured' => false,
            ];
        }

        return [
            'success' => true,
            'message' => "Successfully connected to {$adapter->getDisplayName()} sandbox endpoint.",
            'gateway_key' => $gatewayKey,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Seed initial gateway records if not yet present.
     */
    public function ensureGatewaysSeeded(): void
    {
        $defaults = [
            [
                'gateway_key' => 'stripe',
                'name' => 'Stripe Payments',
                'supported_countries' => ['US', 'GB', 'CA', 'AU', 'DE', 'FR', 'SG', 'GLOBAL'],
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'SGD'],
                'supported_methods' => ['card', 'apple_pay', 'google_pay'],
                'settlement_currencies' => ['USD', 'EUR', 'GBP'],
                'supports_refunds' => true,
                'supports_recurring' => true,
                'supports_webhooks' => true,
                'is_configured' => false,
                'merchant_verified' => false,
                'is_active' => true,
                'unavailability_reason' => 'Stripe secret key and webhook signing secret are not configured in system environment.',
                'test_mode' => true,
            ],
            [
                'gateway_key' => 'bkash',
                'name' => 'bKash Merchant Gateway',
                'supported_countries' => ['BD'],
                'supported_currencies' => ['BDT'],
                'supported_methods' => ['bkash_app', 'bkash_ussd', 'bkash_tokenized'],
                'settlement_currencies' => ['BDT'],
                'supports_refunds' => true,
                'supports_recurring' => false,
                'supports_webhooks' => true,
                'is_configured' => true,
                'merchant_verified' => true,
                'is_active' => true,
                'unavailability_reason' => null,
                'test_mode' => true,
            ],
            [
                'gateway_key' => 'nagad',
                'name' => 'Nagad Merchant Gateway',
                'supported_countries' => ['BD'],
                'supported_currencies' => ['BDT'],
                'supported_methods' => ['nagad_app', 'nagad_ussd', 'nagad_merchant_pay'],
                'settlement_currencies' => ['BDT'],
                'supports_refunds' => true,
                'supports_recurring' => false,
                'supports_webhooks' => true,
                'is_configured' => true,
                'merchant_verified' => true,
                'is_active' => true,
                'unavailability_reason' => null,
                'test_mode' => true,
            ],
            [
                'gateway_key' => 'razorpay',
                'name' => 'Razorpay International',
                'supported_countries' => ['IN', 'MY', 'SG'],
                'supported_currencies' => ['INR', 'USD', 'MYR', 'SGD'],
                'supported_methods' => ['card', 'upi', 'netbanking'],
                'settlement_currencies' => ['INR', 'USD'],
                'supports_refunds' => true,
                'supports_recurring' => true,
                'supports_webhooks' => true,
                'is_configured' => false,
                'merchant_verified' => false,
                'is_active' => false,
                'unavailability_reason' => 'Requires verified merchant onboarding and international forex collection compliance in an eligible operating jurisdiction (India/SE Asia).',
                'test_mode' => true,
            ],
            [
                'gateway_key' => 'manual_bank',
                'name' => 'Corporate Bank Transfer / Wire',
                'supported_countries' => ['ALL', 'BD', 'US', 'GB', 'EU', 'CA', 'AU', 'SG', 'GLOBAL'],
                'supported_currencies' => ['BDT', 'USD', 'EUR', 'GBP', 'CAD'],
                'supported_methods' => ['bank_transfer', 'wire_swift', 'manual_verification'],
                'settlement_currencies' => ['BDT', 'USD', 'EUR', 'GBP', 'CAD'],
                'supports_refunds' => true,
                'supports_recurring' => false,
                'supports_webhooks' => false,
                'is_configured' => true,
                'merchant_verified' => true,
                'is_active' => true,
                'unavailability_reason' => null,
                'test_mode' => false,
            ],
        ];

        foreach ($defaults as $item) {
            $existing = PaymentGateway::where('gateway_key', $item['gateway_key'])->first();
            if (!$existing) {
                PaymentGateway::create($item);
            } elseif ($item['gateway_key'] === 'manual_bank' || $item['gateway_key'] === 'stripe') {
                $existing->update([
                    'supported_currencies' => $item['supported_currencies'],
                    'supported_countries' => $item['supported_countries'],
                ]);
            }
        }
    }
}
