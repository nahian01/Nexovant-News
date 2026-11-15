<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    use HasFactory, HasUuids;

    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_ANALYST = 'analyst';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_MEMBER = 'member';
    public const ROLE_VIEWER = 'viewer';

    protected $fillable = [
        'organization_id',
        'user_id',
        'role', // owner, admin, manager, analyst, member, viewer
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function isAnalystOrHigher(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_ANALYST], true);
    }

    public function canManageMembers(): bool
    {
        return $this->isAdmin();
    }

    public function canManageAssets(): bool
    {
        return $this->isAnalystOrHigher();
    }
}
