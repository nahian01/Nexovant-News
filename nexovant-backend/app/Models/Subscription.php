<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'organization_id',
        'plan_id',
        'billing_cycle',
        'currency',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'canceled_at',
        'renewals_count',
        'next_renewal_at',
        'payment_method_id',
        'gateway_key',
        'gateway_subscription_id',
        'billing_country',
        'prorated_credit',
        'paused_at',
        'grace_period_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'next_renewal_at' => 'datetime',
            'paused_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'renewals_count' => 'integer',
            'prorated_credit' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(SubscriptionEntitlementOverride::class);
    }

    public function isActive(): bool
    {
        if (in_array($this->status, ['active', 'trialing', 'cancel_at_period_end'], true)) {
            return $this->ends_at === null || $this->ends_at->isFuture();
        }

        // Within grace period
        if ($this->status === 'past_due' && $this->grace_period_ends_at && $this->grace_period_ends_at->isFuture()) {
            return true;
        }

        return false;
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function isCanceled(): bool
    {
        return in_array($this->status, ['canceled', 'cancel_at_period_end'], true);
    }
}
