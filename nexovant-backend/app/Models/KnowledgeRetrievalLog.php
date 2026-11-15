<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeRetrievalLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'query_text',
        'cache_hit',
        'retrieved_chunks_count',
        'total_context_tokens',
        'latency_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cache_hit' => 'boolean',
            'retrieved_chunks_count' => 'integer',
            'total_context_tokens' => 'integer',
            'latency_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }
}
