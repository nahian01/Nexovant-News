<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeEmbedding extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'chunk_id',
        'tenant_id',
        'model_name',
        'dimensions',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'integer',
            'embedding' => 'array',
        ];
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'chunk_id');
    }
}
