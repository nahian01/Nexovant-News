<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResearchProject extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'research_projects';

    protected $fillable = [
        'user_id',
        'organization_id',
        'title',
        'research_question',
        'topic',
        'entity_id',
        'depth',
        'output_format',
        'time_range_start',
        'time_range_end',
        'geographic_region',
        'status',
        'progress_percent',
        'error_message',
        'parameters',
    ];

    protected function casts(): array
    {
        return [
            'time_range_start' => 'date',
            'time_range_end' => 'date',
            'progress_percent' => 'integer',
            'parameters' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEntity::class, 'entity_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(ResearchReport::class, 'project_id');
    }
}
