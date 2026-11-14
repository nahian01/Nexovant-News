<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Executive extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'full_name',
        'slug',
        'current_title',
        'biography',
        'linkedin_url',
        'verified_profile',
    ];

    protected function casts(): array
    {
        return [
            'verified_profile' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_executives')
            ->withPivot(['title', 'is_current', 'appointed_date', 'resigned_date'])
            ->withTimestamps();
    }
}
