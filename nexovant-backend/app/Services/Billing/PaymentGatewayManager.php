<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Services\Billing\Adapters\BkashGatewayAdapter;
use App\Services\Billing\Adapters\ManualBankGatewayAdapter;
use App\Services\Billing\Adapters\NagadGatewayAdapter;
use App\Services\Billing\Adapters\RazorpayGatewayAdapter;
use App\Services\Billing\Adapters\StripeGatewayAdapter;
use App\Services\Billing\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $gateways = [];

    public function __construct(
        StripeGatewayAdapter $stripe,
        BkashGatewayAdapter $bkash,
        NagadGatewayAdapter $nagad,
        RazorpayGatewayAdapter $razorpay,
        ManualBankGatewayAdapter $bank
    ) {
        $this->gateways = [
            StripeGatewayAdapter::GATEWAY_KEY => $stripe,
            BkashGatewayAdapter::GATEWAY_KEY => $bkash,
            NagadGatewayAdapter::GATEWAY_KEY => $nagad,
            RazorpayGatewayAdapter::GATEWAY_KEY => $razorpay,
            ManualBankGatewayAdapter::GATEWAY_KEY => $bank,
        ];
    }

    public function getGateway(string $gatewayKey): PaymentGatewayInterface
    {
        $key = strtolower(trim($gatewayKey));
        if (!isset($this->gateways[$key])) {
            throw new InvalidArgumentException("Payment gateway adapter '{$key}' is not registered.");
        }

        return $this->gateways[$key];
    }

    /**
     * @return array<string, PaymentGatewayInterface>
     */
    public function getAllGateways(): array
    {
        return $this->gateways;
    }

    /**
     * @return array<string, PaymentGatewayInterface>
     */
    public function getAvailableGateways(string $country, string $currency): array
    {
        $available = [];
        foreach ($this->gateways as $key => $gateway) {
            if ($gateway->isAvailableFor($country, $currency)) {
                $available[$key] = $gateway;
            }
        }

        return $available;
    }
}
