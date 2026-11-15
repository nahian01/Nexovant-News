<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ResearchReport extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'research_reports';

    protected $fillable = [
        'project_id',
        'title',
        'slug',
        'executive_summary',
        'content_markdown',
        'structured_findings',
        'conflicting_claims',
        'information_gaps',
        'confidence_score',
        'uncertainty_rating',
        'editorial_status',
        'is_public',
        'reviewer_id',
        'reviewed_at',
        'revisions_count',
    ];

    protected function casts(): array
    {
        return [
            'structured_findings' => 'array',
            'conflicting_claims' => 'array',
            'information_gaps' => 'array',
            'confidence_score' => 'float',
            'is_public' => 'boolean',
            'reviewed_at' => 'datetime',
            'revisions_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ResearchReport $report): void {
            if (empty($report->slug)) {
                $base = Str::slug(substr($report->title, 0, 70));
                $report->slug = $base . '-' . Str::lower(Str::random(5));
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class, 'project_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function citations(): HasMany
    {
        return $this->hasMany(ResearchCitation::class, 'report_id')->orderBy('citation_number');
    }
}
