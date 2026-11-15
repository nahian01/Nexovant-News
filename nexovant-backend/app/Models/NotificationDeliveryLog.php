<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeliveryLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'notification_delivery_logs';

    protected $fillable = [
        'notification_id',
        'channel',
        'status',
        'attempt_count',
        'recipient',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
