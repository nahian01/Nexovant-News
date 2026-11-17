<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutiveBriefingVersion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'executive_briefing_versions';

    protected $fillable = [
        'briefing_id',
        'version_number',
        'title',
        'sections',
        'citations',
        'created_by',
        'change_summary',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'sections' => 'array',
            'citations' => 'array',
        ];
    }

    public function briefing(): BelongsTo
    {
        return $this->belongsTo(ExecutiveBriefing::class, 'briefing_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
