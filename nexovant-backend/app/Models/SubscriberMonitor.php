<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriberMonitor extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscriber_monitors';

    protected $fillable = [
        'user_id',
        'organization_id',
        'name',
        'keywords',
        'excluded_terms',
        'entity_names',
        'categories',
        'industries',
        'regions',
        'min_credibility_score',
        'require_verified_only',
        'alert_frequency',
        'delivery_channels',
        'is_active',
        'is_shared',
        'last_triggered_at',
        'match_count',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'excluded_terms' => 'array',
            'entity_names' => 'array',
            'categories' => 'array',
            'industries' => 'array',
            'regions' => 'array',
            'min_credibility_score' => 'float',
            'require_verified_only' => 'boolean',
            'delivery_channels' => 'array',
            'is_active' => 'boolean',
            'is_shared' => 'boolean',
            'last_triggered_at' => 'datetime',
            'match_count' => 'integer',
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
}
