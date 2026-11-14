<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineJob extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pipeline_jobs';

    protected $fillable = [
        'source_id',
        'article_id',
        'cluster_id',
        'stage',
        'status',
        'item_title',
        'item_url',
        'content_hash',
        'ai_provider_id',
        'ai_model_id',
        'tokens_consumed',
        'cost_usd',
        'duration_seconds',
        'extracted_facts',
        'conflicting_reports',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'tokens_consumed' => 'integer',
            'cost_usd' => 'decimal:6',
            'duration_seconds' => 'decimal:2',
            'extracted_facts' => 'array',
            'conflicting_reports' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AIModel::class, 'ai_model_id');
    }
}
