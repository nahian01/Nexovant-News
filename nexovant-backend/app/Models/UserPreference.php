<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasUuids;

    protected $table = 'user_preferences';

    protected $fillable = [
        'user_id',
        'preferred_industries',
        'preferred_companies',
        'preferred_topics',
        'preferred_executives',
        'digest_frequency',
        'email_frequency',
        'receive_breaking_alerts',
        'receive_pdf_briefings',
        'personalization_enabled',
    ];

    protected function casts(): array
    {
        return [
            'preferred_industries' => 'array',
            'preferred_companies' => 'array',
            'preferred_topics' => 'array',
            'preferred_executives' => 'array',
            'receive_breaking_alerts' => 'boolean',
            'receive_pdf_briefings' => 'boolean',
            'personalization_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
