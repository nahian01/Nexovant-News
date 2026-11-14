<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController
{
    /**
     * Public system health check endpoint.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'healthy';
        $dbError = null;

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $dbStatus = 'unhealthy';
            $dbError = config('app.debug') ? $e->getMessage() : 'Database connection error';
        }

        $isHealthy = $dbStatus === 'healthy';

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $dbStatus,
                'queue' => 'active',
            ],
            ...($dbError ? ['database_error' => $dbError] : [])
        ], $isHealthy ? 200 : 503);
    }
}
