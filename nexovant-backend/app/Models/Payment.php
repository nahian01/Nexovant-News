<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'invoice_id',
        'payment_method',
        'gateway_key',
        'gateway_charge_id',
        'provider_reference',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'raw_response',
        'failure_reason',
        'verified_by_user_id',
        'verified_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function proof(): HasOne
    {
        return $this->hasOne(PaymentProof::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(PaymentDispute::class);
    }
}
