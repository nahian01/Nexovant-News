<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class IntelligenceDecision extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_decisions';

    protected $fillable = [
        'title',
        'slug',
        'organization_id',
        'user_id',
        'business_question',
        'decision_owner_id',
        'deadline',
        'context_and_background',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'recorded_decision',
        'decision_date',
        'review_date',
        'next_steps',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'approved_at' => 'datetime',
            'decision_date' => 'datetime',
            'review_date' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (IntelligenceDecision $model) {
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

    public function decisionOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_owner_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(IntelligenceDecisionOption::class, 'decision_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(IntelligenceDecisionCriterion::class, 'decision_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(IntelligenceDecisionReview::class, 'decision_id')->orderByDesc('created_at');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(IntelligenceDecisionMilestone::class, 'decision_id')->orderBy('target_date');
    }
}
