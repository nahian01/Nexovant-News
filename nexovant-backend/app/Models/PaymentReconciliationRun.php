<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliationRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'run_date',
        'gateway_key',
        'total_internal_transactions',
        'total_gateway_transactions',
        'matched_count',
        'discrepancy_count',
        'discrepancy_details',
        'executed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'discrepancy_details' => 'array',
            'total_internal_transactions' => 'integer',
            'total_gateway_transactions' => 'integer',
            'matched_count' => 'integer',
            'discrepancy_count' => 'integer',
        ];
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }
}
