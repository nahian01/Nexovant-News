<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriberWatchlist extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscriber_watchlists';

    protected $fillable = [
        'user_id',
        'organization_id',
        'name',
        'description',
        'is_default',
        'is_shared',
        'notification_frequency',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_shared' => 'boolean',
            'sort_order' => 'integer',
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

    public function items(): HasMany
    {
        return $this->hasMany(SubscriberWatchlistItem::class, 'watchlist_id');
    }
}
