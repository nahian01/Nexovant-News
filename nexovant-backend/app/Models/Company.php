<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'ticker',
        'isin',
        'sector',
        'industry',
        'headquarters_country',
        'website_url',
        'market_cap_tier',
        'summary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CompanyAlias::class);
    }

    public function executives(): BelongsToMany
    {
        return $this->belongsToMany(Executive::class, 'company_executives')
            ->withPivot(['title', 'is_current', 'appointed_date', 'resigned_date'])
            ->withTimestamps();
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_companies')
            ->withPivot('relevance');
    }

    public function competitors(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_relationships', 'company_id', 'related_company_id')
            ->wherePivot('relationship_type', 'competitor');
    }
}
