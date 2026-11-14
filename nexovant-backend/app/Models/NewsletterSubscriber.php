<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasUuids;

    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'email',
        'frequency',
        'source',
        'offer_code',
        'is_active',
        'ip_address',
        'subscribed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'subscribed_at' => 'datetime',
    ];
}
