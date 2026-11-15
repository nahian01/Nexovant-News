<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityAlias extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'entity_aliases';

    protected $fillable = [
        'entity_id',
        'alias',
        'alias_type',
        'source',
        'confidence_score',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEntity::class, 'entity_id');
    }
}
