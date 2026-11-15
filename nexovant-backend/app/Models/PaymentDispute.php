<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDispute extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'payment_id',
        'gateway_dispute_id',
        'amount',
        'currency',
        'reason',
        'status',
        'evidence',
        'due_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'evidence' => 'array',
            'due_by' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
