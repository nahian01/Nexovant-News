<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationDownloadToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'publication_id',
        'user_id',
        'token_hash',
        'expires_at',
        'is_revoked',
        'max_downloads',
        'download_count',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_revoked' => 'boolean',
            'max_downloads' => 'integer',
            'download_count' => 'integer',
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

    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if (now()->isAfter($this->expires_at)) {
            return false;
        }

        if ($this->max_downloads > 0 && $this->download_count >= $this->max_downloads) {
            return false;
        }

        return true;
    }
}
