<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_bdt_monthly',
        'price_bdt_annual',
        'price_usd_monthly',
        'price_usd_annual',
        'trial_period_days',
        'promotional_price_monthly',
        'promotional_price_annual',
        'features',
        'regional_availability',
        'grace_period_days',
        'tax_category',
        'is_active',
        'is_popular',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_bdt_monthly' => 'decimal:2',
            'price_bdt_annual' => 'decimal:2',
            'price_usd_monthly' => 'decimal:2',
            'price_usd_annual' => 'decimal:2',
            'promotional_price_monthly' => 'decimal:2',
            'promotional_price_annual' => 'decimal:2',
            'features' => 'array',
            'regional_availability' => 'array',
            'trial_period_days' => 'integer',
            'grace_period_days' => 'integer',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getEntitlementValue(string $featureKey, mixed $default = null): mixed
    {
        $ent = $this->entitlements()->where('feature_key', $featureKey)->first();
        if (!$ent) {
            return $default;
        }

        if ($ent->value_type === 'boolean') {
            return filter_var($ent->value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($ent->value_type === 'number' || is_numeric($ent->value)) {
            return (int) $ent->value;
        }

        return $ent->value;
    }
}
