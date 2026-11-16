<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EarlyWarningHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'early_warning_histories';

    protected $fillable = [
        'warning_id',
        'from_status',
        'to_status',
        'user_id',
        'reason',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function warning(): BelongsTo
    {
        return $this->belongsTo(EarlyWarning::class, 'warning_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
