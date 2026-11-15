<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'subscription_id',
        'invoice_number',
        'subtotal',
        'discount',
        'coupon_id',
        'tax',
        'total',
        'currency',
        'status',
        'billing_name',
        'billing_email',
        'billing_address',
        'pdf_path',
        'notes',
        'due_date',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'billing_address' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
