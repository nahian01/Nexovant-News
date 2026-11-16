<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationExecutionLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'automation_execution_logs';

    protected $fillable = [
        'command_name',
        'frequency',
        'status',
        'started_at',
        'completed_at',
        'duration_ms',
        'processed_count',
        'failures_count',
        'skipped_count',
        'metrics',
        'error_message',
        'hostname',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_ms' => 'integer',
            'processed_count' => 'integer',
            'failures_count' => 'integer',
            'skipped_count' => 'integer',
            'metrics' => 'array',
        ];
    }

    /**
     * Start a new execution log entry.
     */
    public static function startExecution(string $commandName, string $frequency = 'manual'): self
    {
        return self::create([
            'command_name' => $commandName,
            'frequency' => $frequency,
            'status' => 'running',
            'started_at' => now(),
            'hostname' => gethostname() ?: 'nexovant-worker',
        ]);
    }

    /**
     * Complete an execution log entry successfully.
     */
    public function completeSuccess(int $processed = 0, int $failures = 0, int $skipped = 0, array $metrics = []): void
    {
        $completedAt = now();
        $durationMs = (int) round(($completedAt->getTimestampMs() - $this->started_at->getTimestampMs()));

        $this->update([
            'status' => 'success',
            'completed_at' => $completedAt,
            'duration_ms' => max(0, $durationMs),
            'processed_count' => $processed,
            'failures_count' => $failures,
            'skipped_count' => $skipped,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Record execution failure.
     */
    public function failExecution(string $errorMessage, array $metrics = []): void
    {
        $completedAt = now();
        $durationMs = (int) round(($completedAt->getTimestampMs() - $this->started_at->getTimestampMs()));

        $this->update([
            'status' => 'failed',
            'completed_at' => $completedAt,
            'duration_ms' => max(0, $durationMs),
            'error_message' => $errorMessage,
            'metrics' => $metrics,
        ]);
    }
}
