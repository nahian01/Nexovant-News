<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SemanticCache extends Model
{
    use HasUuids;

    protected $table = 'semantic_cache';

    protected $fillable = [
        'tenant_id',
        'query_hash',
        'query_text',
        'response_text',
        'source_citations',
        'ttl_seconds',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'source_citations' => 'array',
            'ttl_seconds' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }
}
