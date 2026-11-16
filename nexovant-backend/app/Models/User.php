<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'account_type',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function savedArticles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'saved_articles')
            ->withPivot('notes')
            ->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function watchlists(): HasMany
    {
        return $this->hasMany(SubscriberWatchlist::class);
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(SubscriberMonitor::class);
    }

    public function libraryItems(): HasMany
    {
        return $this->hasMany(SubscriberLibraryItem::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(SubscriberCollection::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(SubscriberInteraction::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(SubscriberNotificationPreference::class);
    }

    public function webPushSubscriptions(): HasMany
    {
        return $this->hasMany(WebPushSubscription::class);
    }

    public function telegramLinkTokens(): HasMany
    {
        return $this->hasMany(TelegramLinkToken::class);
    }

    public function entitlementOverrides(): HasMany
    {
        return $this->hasMany(SubscriptionEntitlementOverride::class);
    }

    public function paymentCustomers(): HasMany
    {
        return $this->hasMany(PaymentCustomer::class);
    }

    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function activeOrganization(): ?Organization
    {
        $membership = $this->organizationMemberships()
            ->with('organization')
            ->whereHas('organization', fn ($q) => $q->where('is_active', true))
            ->first();

        return $membership?->organization;
    }

    public function getOrganizationIdAttribute(): ?string
    {
        return $this->activeOrganization()?->id;
    }

    public function organizationRole(?Organization $org = null): ?string
    {
        $orgId = $org?->id ?? $this->activeOrganization()?->id;
        if (! $orgId) {
            return null;
        }

        $membership = $this->organizationMemberships()
            ->where('organization_id', $orgId)
            ->first();

        return $membership?->role;
    }

    public function hasRole(string|array $roles): bool
    {
        // Platform admin account_type check backward compatibility
        if (in_array($this->account_type, ['admin', 'chief_admin', 'super_admin'])) {
            return true;
        }

        $roleList = is_array($roles) ? $roles : [$roles];

        return $this->roles()->whereIn('name', $roleList)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if (in_array($this->account_type, ['admin', 'chief_admin', 'super_admin'])) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($q) use ($permission) {
                $q->where('name', $permission);
            })
            ->exists();
    }

    public function isAdmin(): bool
    {
        return in_array($this->account_type, ['admin', 'chief_admin', 'super_admin'])
            || $this->roles()->whereIn('name', ['admin', 'chief_admin', 'super_admin'])->exists();
    }
}
