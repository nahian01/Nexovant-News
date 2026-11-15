<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeChunk extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'tenant_id',
        'chunk_index',
        'content',
        'token_count',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'token_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(KnowledgeEmbedding::class, 'chunk_id');
    }
}
