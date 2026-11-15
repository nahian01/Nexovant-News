<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriberWatchlistItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscriber_watchlist_items';

    protected $fillable = [
        'watchlist_id',
        'entity_type',
        'entity_id',
        'entity_name',
        'entity_slug',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(SubscriberWatchlist::class, 'watchlist_id');
    }

    public function intelligenceEntity(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEntity::class, 'entity_id');
    }
}
