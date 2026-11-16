<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriberSavedSearch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscriber_saved_searches';

    protected $fillable = [
        'user_id',
        'organization_id',
        'name',
        'description',
        'query',
        'filters',
        'alert_frequency',
        'delivery_channels',
        'is_active',
        'last_executed_at',
        'last_matched_at',
        'match_count',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'delivery_channels' => 'array',
            'is_active' => 'boolean',
            'match_count' => 'integer',
            'last_executed_at' => 'datetime',
            'last_matched_at' => 'datetime',
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
