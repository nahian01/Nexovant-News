<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentProviderConfig extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'provider_key',
        'display_name',
        'is_enabled',
        'is_test_mode',
        'instructions',
        'encrypted_credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_test_mode' => 'boolean',
        ];
    }
}
