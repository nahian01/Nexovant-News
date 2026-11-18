<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

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
        'category_id',
        'subcategory_id',
        'headquarters_country',
        'continent',
        'country',
        'state',
        'province',
        'division',
        'district',
        'county',
        'upazila',
        'city',
        'municipality',
        'admin_level_1_type',
        'admin_level_1',
        'admin_level_2_type',
        'admin_level_2',
        'admin_level_3_type',
        'admin_level_3',
        'headquarters_address',
        'postal_code',
        'website_url',
        'logo_url',
        'market_cap_tier',
        'employee_count',
        'founded_year',
        'summary',
        'is_active',
    ];

    protected $appends = [
        'geo_summary',
        'geo_hierarchy',
        'admin_labels',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'employee_count' => 'integer',
            'founded_year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Company $company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
            }

            $countryNorm = strtolower(trim((string) ($company->country ?? $company->headquarters_country)));

            // Country-specific geo-administrative taxonomy adaptation
            if ($countryNorm === 'bangladesh' || $countryNorm === 'bd') {
                $company->continent = $company->continent ?: 'Asia';
                $company->country = 'Bangladesh';
                $company->headquarters_country = 'BD';
                $company->admin_level_1_type = 'division';
                $company->admin_level_1 = $company->division ?: $company->admin_level_1;
                $company->division = $company->division ?: $company->admin_level_1;
                $company->admin_level_2_type = 'district';
                $company->admin_level_2 = $company->district ?: $company->admin_level_2;
                $company->district = $company->district ?: $company->admin_level_2;
                $company->admin_level_3_type = 'upazila';
                $company->admin_level_3 = $company->upazila ?: ($company->city ?: $company->admin_level_3);
                $company->upazila = $company->upazila ?: $company->admin_level_3;
            } elseif ($countryNorm === 'united states' || $countryNorm === 'usa' || $countryNorm === 'us') {
                $company->continent = $company->continent ?: 'North America';
                $company->country = 'United States';
                $company->headquarters_country = 'US';
                $company->admin_level_1_type = 'state';
                $company->admin_level_1 = $company->state ?: $company->admin_level_1;
                $company->state = $company->state ?: $company->admin_level_1;
                $company->admin_level_2_type = 'county';
                $company->admin_level_2 = $company->county ?: $company->admin_level_2;
                $company->county = $company->county ?: $company->admin_level_2;
                $company->admin_level_3_type = 'city';
                $company->admin_level_3 = $company->city ?: $company->admin_level_3;
            } elseif ($countryNorm === 'canada' || $countryNorm === 'ca') {
                $company->continent = $company->continent ?: 'North America';
                $company->country = 'Canada';
                $company->headquarters_country = 'CA';
                $company->admin_level_1_type = 'province';
                $company->admin_level_1 = $company->province ?: $company->admin_level_1;
                $company->province = $company->province ?: $company->admin_level_1;
                $company->admin_level_2_type = 'district';
                $company->admin_level_2 = $company->district ?: ($company->county ?: $company->admin_level_2);
                $company->admin_level_3_type = 'city';
                $company->admin_level_3 = $company->city ?: $company->admin_level_3;
            } elseif ($countryNorm === 'united kingdom' || $countryNorm === 'uk' || $countryNorm === 'gb') {
                $company->continent = $company->continent ?: 'Europe';
                $company->country = 'United Kingdom';
                $company->headquarters_country = 'GB';
                $company->admin_level_1_type = 'region';
                $company->admin_level_1 = $company->state ?: ($company->province ?: $company->admin_level_1);
                $company->admin_level_2_type = 'county';
                $company->admin_level_2 = $company->county ?: $company->admin_level_2;
                $company->admin_level_3_type = 'city';
                $company->admin_level_3 = $company->city ?: $company->admin_level_3;
            } elseif ($countryNorm === 'germany' || $countryNorm === 'de') {
                $company->continent = $company->continent ?: 'Europe';
                $company->country = 'Germany';
                $company->headquarters_country = 'DE';
                $company->admin_level_1_type = 'state';
                $company->admin_level_1 = $company->state ?: $company->admin_level_1;
                $company->admin_level_2_type = 'kreis';
                $company->admin_level_2 = $company->district ?: $company->admin_level_2;
                $company->admin_level_3_type = 'city';
                $company->admin_level_3 = $company->city ?: $company->admin_level_3;
            } elseif ($countryNorm === 'japan' || $countryNorm === 'jp') {
                $company->continent = $company->continent ?: 'Asia';
                $company->country = 'Japan';
                $company->headquarters_country = 'JP';
                $company->admin_level_1_type = 'prefecture';
                $company->admin_level_1 = $company->state ?: ($company->province ?: $company->admin_level_1);
                $company->admin_level_2_type = 'district';
                $company->admin_level_3_type = 'city';
                $company->admin_level_3 = $company->city ?: $company->admin_level_3;
            } elseif ($countryNorm === 'india' || $countryNorm === 'in') {
                $company->continent = $company->continent ?: 'Asia';
                $company->country = 'India';
                $company->headquarters_country = 'IN';
                $company->admin_level_1_type = 'state';
                $company->admin_level_1 = $company->state ?: $company->admin_level_1;
                $company->admin_level_2_type = 'district';
                $company->admin_level_2 = $company->district ?: $company->admin_level_2;
                $company->admin_level_3_type = 'city';
                $company->admin_level_3 = $company->city ?: ($company->upazila ?: $company->admin_level_3);
            } else {
                // Generic fallbacks
                if (!$company->admin_level_1) {
                    $company->admin_level_1 = $company->division ?: ($company->state ?: $company->province);
                }
                if (!$company->admin_level_2) {
                    $company->admin_level_2 = $company->district ?: $company->county;
                }
                if (!$company->admin_level_3) {
                    $company->admin_level_3 = $company->upazila ?: ($company->city ?: $company->municipality);
                }
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
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

    public function intelligenceEntity(): MorphOne
    {
        return $this->morphOne(IntelligenceEntity::class, 'entityable');
    }

    /**
     * Get country-specific administrative nomenclature labels.
     */
    public function getAdminLabelsAttribute(): array
    {
        $countryNorm = strtolower(trim((string) ($this->country ?? $this->headquarters_country)));

        return match ($countryNorm) {
            'bangladesh', 'bd' => [
                'level_1' => 'Division',
                'level_2' => 'District',
                'level_3' => 'Upazila / Thana',
            ],
            'united states', 'usa', 'us' => [
                'level_1' => 'State',
                'level_2' => 'County',
                'level_3' => 'City',
            ],
            'canada', 'ca' => [
                'level_1' => 'Province',
                'level_2' => 'Regional District',
                'level_3' => 'City / Municipality',
            ],
            'united kingdom', 'uk', 'gb' => [
                'level_1' => 'Region / Country',
                'level_2' => 'County',
                'level_3' => 'City / Borough',
            ],
            'germany', 'de' => [
                'level_1' => 'Federal State (Land)',
                'level_2' => 'District (Kreis)',
                'level_3' => 'Municipality / City',
            ],
            'japan', 'jp' => [
                'level_1' => 'Prefecture',
                'level_2' => 'District / Subprefecture',
                'level_3' => 'City / Ward',
            ],
            'india', 'in' => [
                'level_1' => 'State',
                'level_2' => 'District',
                'level_3' => 'Taluk / City',
            ],
            default => [
                'level_1' => ucfirst($this->admin_level_1_type ?: 'State'),
                'level_2' => ucfirst($this->admin_level_2_type ?: 'District'),
                'level_3' => ucfirst($this->admin_level_3_type ?: 'City'),
            ],
        };
    }

    /**
     * Get clean, formatted hierarchical geographical summary string.
     */
    public function getGeoSummaryAttribute(): string
    {
        $parts = array_filter([
            $this->admin_level_3 ?: ($this->upazila ?: $this->city),
            $this->admin_level_2 ?: ($this->district ?: $this->county),
            $this->admin_level_1 ?: ($this->division ?: ($this->state ?: $this->province)),
            $this->country,
        ]);

        return !empty($parts) ? implode(', ', $parts) : ($this->headquarters_country ?: 'Global');
    }

    /**
     * Structured multi-level geo-hierarchy dictionary.
     */
    public function getGeoHierarchyAttribute(): array
    {
        $labels = $this->admin_labels;

        return [
            'continent' => $this->continent,
            'country' => $this->country,
            'country_code' => $this->headquarters_country,
            'level_1' => [
                'type' => $this->admin_level_1_type,
                'label' => $labels['level_1'],
                'value' => $this->admin_level_1 ?: ($this->division ?: ($this->state ?: $this->province)),
            ],
            'level_2' => [
                'type' => $this->admin_level_2_type,
                'label' => $labels['level_2'],
                'value' => $this->admin_level_2 ?: ($this->district ?: $this->county),
            ],
            'level_3' => [
                'type' => $this->admin_level_3_type,
                'label' => $labels['level_3'],
                'value' => $this->admin_level_3 ?: ($this->upazila ?: $this->city),
            ],
            'city' => $this->city,
            'address' => $this->headquarters_address,
            'postal_code' => $this->postal_code,
        ];
    }
}
