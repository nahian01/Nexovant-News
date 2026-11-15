<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriberCollection extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscriber_collections';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'color',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriberLibraryItem::class, 'collection_id');
    }
}
