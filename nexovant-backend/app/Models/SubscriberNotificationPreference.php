<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriberNotificationPreference extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscriber_notification_preferences';

    protected $fillable = [
        'user_id',
        'channels',
        'category_settings',
        'digest_frequency',
        'quiet_hours_start',
        'quiet_hours_end',
        'telegram_chat_id',
        'whatsapp_phone',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'category_settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
