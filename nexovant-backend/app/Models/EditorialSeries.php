<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EditorialSeries extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'editorial_series';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_series', 'series_id', 'article_id')
            ->withPivot('order_in_series')
            ->orderBy('order_in_series');
    }
}
