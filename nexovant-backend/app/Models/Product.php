<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'products';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'product_type',
        'summary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_products');
    }

    public function intelligenceEntity(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(IntelligenceEntity::class, 'entityable');
    }
}
