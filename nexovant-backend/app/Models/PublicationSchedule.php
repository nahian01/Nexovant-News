<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'publication_type',
        'template_id',
        'title_pattern',
        'frequency',
        'schedule_time',
        'timezone',
        'scope_config',
        'require_review',
        'is_enabled',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_config' => 'array',
            'require_review' => 'boolean',
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PublicationTemplate::class, 'template_id');
    }
}
