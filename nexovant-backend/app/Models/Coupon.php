<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'currency',
        'max_redemptions',
        'times_redeemed',
        'valid_from',
        'valid_until',
        'eligible_plans',
        'eligible_countries',
        'min_order_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'eligible_plans' => 'array',
            'eligible_countries' => 'array',
            'is_active' => 'boolean',
            'max_redemptions' => 'integer',
            'times_redeemed' => 'integer',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isValid(?string $planSlug = null, ?string $country = null, float $orderAmount = 0.0): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->valid_from && now()->isBefore($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && now()->isAfter($this->valid_until)) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->times_redeemed >= $this->max_redemptions) {
            return false;
        }

        if ($this->min_order_amount !== null && $orderAmount < (float) $this->min_order_amount) {
            return false;
        }

        if (!empty($this->eligible_plans) && $planSlug && !in_array($planSlug, $this->eligible_plans, true)) {
            return false;
        }

        if (!empty($this->eligible_countries) && $country && !in_array(strtoupper($country), array_map('strtoupper', $this->eligible_countries), true)) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === 'percentage') {
            $discount = ($subtotal * (float) $this->discount_value) / 100;
            return round(min($discount, $subtotal), 2);
        }

        return round(min((float) $this->discount_value, $subtotal), 2);
    }
}
