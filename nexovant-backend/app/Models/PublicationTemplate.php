<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicationTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'page_size',
        'orientation',
        'brand_config',
        'section_visibility',
        'confidentiality_label',
        'disclosure_text',
        'version',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'brand_config' => 'array',
            'section_visibility' => 'array',
            'version' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'template_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PublicationSchedule::class, 'template_id');
    }
}
