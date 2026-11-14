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
}
