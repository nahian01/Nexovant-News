<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'publication_id',
        'user_id',
        'version',
        'content_json',
        'change_summary',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
