<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'publication_type', // daily_briefing, weekly_report, company_intelligence, custom
        'title',
        'publication_date',
        'file_path',
        'file_size_bytes',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'file_size_bytes' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }
}
