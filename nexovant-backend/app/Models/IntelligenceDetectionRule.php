<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceDetectionRule extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_detection_rules';

    protected $fillable = [
        'name',
        'rule_type',
        'category',
        'thresholds',
        'is_active',
        'organization_id',
        'created_by_user_id',
        'version',
        'audit_trail',
    ];

    protected function casts(): array
    {
        return [
            'thresholds' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
            'audit_trail' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
