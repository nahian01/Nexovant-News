<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PaymentGateway extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'gateway_key',
        'name',
        'supported_countries',
        'supported_currencies',
        'supported_methods',
        'settlement_currencies',
        'supports_refunds',
        'supports_recurring',
        'supports_webhooks',
        'is_configured',
        'merchant_verified',
        'is_active',
        'unavailability_reason',
        'credentials_encrypted',
        'webhook_secret_encrypted',
        'test_mode',
    ];

    protected function casts(): array
    {
        return [
            'supported_countries' => 'array',
            'supported_currencies' => 'array',
            'supported_methods' => 'array',
            'settlement_currencies' => 'array',
            'supports_refunds' => 'boolean',
            'supports_recurring' => 'boolean',
            'supports_webhooks' => 'boolean',
            'is_configured' => 'boolean',
            'merchant_verified' => 'boolean',
            'is_active' => 'boolean',
            'test_mode' => 'boolean',
        ];
    }

    public function getCredentials(): array
    {
        if (empty($this->credentials_encrypted)) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->credentials_encrypted), true) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function setCredentials(array $credentials): void
    {
        $this->credentials_encrypted = Crypt::encryptString(json_encode($credentials));
        $this->is_configured = !empty($credentials);
    }

    public function getWebhookSecret(): ?string
    {
        if (empty($this->webhook_secret_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->webhook_secret_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setWebhookSecret(?string $secret): void
    {
        $this->webhook_secret_encrypted = $secret ? Crypt::encryptString($secret) : null;
    }
}
