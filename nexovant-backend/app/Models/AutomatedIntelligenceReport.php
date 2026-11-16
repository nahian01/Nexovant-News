<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AutomatedIntelligenceReport extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'automated_intelligence_reports';

    protected $fillable = [
        'report_type',
        'title',
        'slug',
        'reporting_period_start',
        'reporting_period_end',
        'executive_summary',
        'structured_content',
        'citations',
        'generated_by_user_id',
        'organization_id',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'structured_content' => 'array',
            'citations' => 'array',
            'is_published' => 'boolean',
            'reporting_period_start' => 'datetime',
            'reporting_period_end' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AutomatedIntelligenceReport $report): void {
            if (empty($report->slug)) {
                $base = Str::slug(substr($report->title, 0, 70));
                $report->slug = $base . '-' . Str::lower(Str::random(5));
            }
            if (empty($report->published_at) && $report->is_published) {
                $report->published_at = now();
            }
        });
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
