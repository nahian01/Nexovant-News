<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'entity_histories';

    protected $fillable = [
        'entity_id',
        'action',
        'changes',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEntity::class, 'entity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
