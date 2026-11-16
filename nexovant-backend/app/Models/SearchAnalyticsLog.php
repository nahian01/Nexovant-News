<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchAnalyticsLog extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'search_analytics_logs';

    protected $fillable = [
        'user_id',
        'organization_id',
        'query',
        'search_category',
        'filters',
        'result_count',
        'is_empty',
        'retrieval_method',
        'duration_ms',
        'clicked_result_id',
        'clicked_result_type',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'result_count' => 'integer',
            'is_empty' => 'boolean',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
