<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'message',
        'link',
        'delivery_channel',
        'delivery_status',
        'retry_count',
        'error_message',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(NotificationDeliveryLog::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }
}
