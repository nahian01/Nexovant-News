<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Billing\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController
{
    public function __construct(
        protected PaymentWebhookService $webhookService
    ) {}

    /**
     * Handle incoming provider payment webhook.
     */
    public function handle(string $gatewayKey, Request $request): JsonResponse
    {
        $result = $this->webhookService->handleWebhook(strtolower($gatewayKey), $request);

        return response()->json($result, $result['status'] ?? 200);
    }
}
