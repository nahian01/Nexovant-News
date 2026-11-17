<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExecutiveBriefing extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'executive_briefings';

    protected $fillable = [
        'title',
        'slug',
        'period_type',
        'coverage_start',
        'coverage_end',
        'industry_scope',
        'geographic_scope',
        'organization_id',
        'user_id',
        'status',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'executive_summary',
        'what_happened',
        'why_it_matters',
        'what_has_changed',
        'what_could_happen_next',
        'executive_considerations',
        'evidence_and_sources',
        'citations',
        'metadata',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'coverage_start' => 'datetime',
            'coverage_end' => 'datetime',
            'reviewed_at' => 'datetime',
            'what_happened' => 'array',
            'why_it_matters' => 'array',
            'what_has_changed' => 'array',
            'what_could_happen_next' => 'array',
            'executive_considerations' => 'array',
            'evidence_and_sources' => 'array',
            'citations' => 'array',
            'metadata' => 'array',
            'version' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ExecutiveBriefing $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title) . '-' . Str::random(6);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ExecutiveBriefingVersion::class, 'briefing_id')->orderByDesc('version_number');
    }
}
