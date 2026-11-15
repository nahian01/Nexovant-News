<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'template_id',
        'user_id',
        'approved_by',
        'publication_type',
        'title',
        'subtitle',
        'publication_date',
        'published_at',
        'edition_number',
        'reporting_period',
        'confidentiality_label',
        'file_path',
        'file_size_bytes',
        'page_count',
        'is_public',
        'status', // draft, generating, pending_review, approved, published, archived, generation_failed, rejected, cancelled
        'visibility', // public, registered, premium, enterprise, internal
        'content_json',
        'evidence_completeness_score',
        'generation_key',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'published_at' => 'datetime',
            'file_size_bytes' => 'integer',
            'page_count' => 'integer',
            'is_public' => 'boolean',
            'content_json' => 'array',
            'evidence_completeness_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PublicationTemplate::class, 'template_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PublicationRevision::class)->orderByDesc('version');
    }

    public function downloadTokens(): HasMany
    {
        return $this->hasMany(PublicationDownloadToken::class);
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(PublicationDownloadLog::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function canBeDownloadedBy(?User $user): bool
    {
        if ($this->visibility === 'public') {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($this->visibility === 'registered') {
            return true;
        }

        if ($this->visibility === 'premium') {
            return $user->hasRole(['subscriber', 'premium', 'enterprise', 'analyst', 'editor']) || $user->subscription?->isActive();
        }

        if ($this->visibility === 'enterprise') {
            return $user->hasRole(['enterprise', 'analyst']) || $user->subscription?->plan?->tier === 'enterprise';
        }

        if ($this->visibility === 'internal') {
            return $user->hasRole(['editor', 'analyst', 'admin', 'chief_admin', 'super_admin']);
        }

        return false;
    }
}
