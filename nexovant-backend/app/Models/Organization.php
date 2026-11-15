<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'billing_email',
        'domain',
        'logo_url',
        'domain_verification_token',
        'domain_verified_at',
        'auto_join_domain',
        'max_seats',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_seats' => 'integer',
            'auto_join_domain' => 'boolean',
            'domain_verified_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(OrganizationAuditLog::class);
    }

    public function watchlists(): HasMany
    {
        return $this->hasMany(SubscriberWatchlist::class);
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(SubscriberMonitor::class);
    }

    public function researchProjects(): HasMany
    {
        return $this->hasMany(ResearchProject::class);
    }

    public function knowledgeDocuments(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class, 'tenant_id');
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->latest('created_at')
            ->first();
    }

    public function isDomainVerified(): bool
    {
        return $this->domain_verified_at !== null;
    }

    public function usedSeatsCount(): int
    {
        return $this->members()->count();
    }

    public function availableSeatsCount(): int
    {
        return max(0, $this->max_seats - $this->usedSeatsCount());
    }

    public function hasAvailableSeats(): bool
    {
        return $this->usedSeatsCount() < $this->max_seats;
    }
}
