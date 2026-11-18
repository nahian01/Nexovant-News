<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'industry',
        'slug',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    public function getIndustryAttribute(?string $value): string
    {
        if (!empty($value)) {
            return $value;
        }

        return match ($this->slug) {
            'semiconductors', 'strategic-technology', 'artificial-intelligence', 'cybersecurity' => 'Technology',
            'automotive-mobility' => 'Automotive & Mobility',
            'biotechnology-healthcare' => 'Healthcare & Life Sciences',
            'financial-services-fintech' => 'Financial Services',
            'aerospace-defense' => 'Aerospace & Defense',
            'energy-sustainability' => 'Energy & CleanTech',
            'expansion-capex', 'leadership-transitions', 'financial-performance', 'acquisitions-mergers',
            'restructuring-workforce', 'emerging-risks', 'regulatory-legal' => 'Corporate & Governance',
            default => 'Technology',
        };
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_categories');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class, 'category_id')->where('is_active', true);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'category_id')->where('is_active', true);
    }
}
