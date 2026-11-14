<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowedExecutive extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'executive_id',
        'notify_on_movement',
    ];

    protected function casts(): array
    {
        return [
            'notify_on_movement' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function executive(): BelongsTo
    {
        return $this->belongsTo(Executive::class);
    }
}
