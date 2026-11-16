<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalEvidence extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'signal_evidences';

    protected $fillable = [
        'signal_id',
        'article_id',
        'source_id',
        'evidence_type',
        'excerpt',
        'source_name',
        'source_url',
        'is_independent',
        'credibility_score',
        'published_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_independent' => 'boolean',
            'credibility_score' => 'float',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(IntelligenceSignal::class, 'signal_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }
}
