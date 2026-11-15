<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriberLibraryItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscriber_library_items';

    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'collection_id',
        'tags',
        'private_notes',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(SubscriberCollection::class, 'collection_id');
    }

    /**
     * Resolve the underlying resource.
     */
    public function resolveResource(): ?Model
    {
        return match ($this->item_type) {
            'article' => Article::find($this->item_id),
            'publication' => Publication::find($this->item_id),
            'research_report' => ResearchReport::find($this->item_id),
            'intelligence_event' => IntelligenceEvent::find($this->item_id),
            default => null,
        };
    }
}
