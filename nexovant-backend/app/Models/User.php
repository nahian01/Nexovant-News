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
