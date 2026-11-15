<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'gateway_key',
        'provider_key',
        'event_id',
        'event_type',
        'payload',
        'signature',
        'status',
        'idempotency_key',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
