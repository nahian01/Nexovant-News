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
        'organization_id',
        'name',
        'description',
        'color',
        'visibility', // private, organization, shared
    ];

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
        return $this->hasMany(SubscriberLibraryItem::class, 'collection_id');
    }

    public function briefs(): HasMany
    {
        return $this->hasMany(ResearchBrief::class, 'collection_id');
    }
}
