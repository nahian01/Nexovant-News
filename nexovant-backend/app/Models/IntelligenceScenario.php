<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntelligenceScenario extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_scenarios';

    protected $fillable = [
        'title',
        'slug',
        'organization_id',
        'user_id',
        'subject',
        'scenario_type',
        'description',
        'initial_conditions',
        'key_assumptions',
        'trigger_events',
        'influencing_factors',
        'potential_outcomes',
        'risks',
        'opportunities',
        'time_horizon',
        'supporting_evidence',
        'contradictory_evidence',
        'indicators_to_monitor',
        'uncertainty_rating',
        'is_custom',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'key_assumptions' => 'array',
            'trigger_events' => 'array',
            'influencing_factors' => 'array',
            'risks' => 'array',
            'opportunities' => 'array',
            'supporting_evidence' => 'array',
            'contradictory_evidence' => 'array',
            'indicators_to_monitor' => 'array',
            'is_custom' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (IntelligenceScenario $model) {
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
}
