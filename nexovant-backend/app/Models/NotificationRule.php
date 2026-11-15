<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'notification_rules';

    protected $fillable = [
        'name',
        'event_type',
        'source_module',
        'priority',
        'channels',
        'template_name',
        'frequency',
        'is_active',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'conditions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
