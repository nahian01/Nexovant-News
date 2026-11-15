<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AIModel;
use App\Models\AIProvider;
use App\Models\AIProviderCredential;
use App\Models\AIRoutingLog;
use App\Models\AITaskConfig;
use App\Models\AuditLog;
use App\Services\AI\AIRouterService;
use App\Services\AI\DTO\AIRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class AIRouterAdminController
{
    public function __construct(
        protected AIRouterService $routerService
    ) {
    }
    /**
     * Dashboard: List all AI provider connections, health, credentials count, and live quota usage.
     */
    public function index(): JsonResponse
    {
        $providers = AIProvider::with([
            'models',
            'quota',
            'credentials' => function ($q) {
                $q->select([
                    'id',
                    'provider_id',
                    'label',
                    'display_name',
                    'key_fingerprint',
                    'account_label',
                    'allowed_models',
                    'status',
                    'priority',
                    'routing_eligibility',
                    'daily_request_budget',
                    'daily_token_budget',
                    'monthly_spending_budget',
                    'current_daily_requests',
                    'current_daily_tokens',
                    'current_monthly_spend_usd',
                    'is_valid',
                    'last_tested_at',
                    'last_successful_request_at',
                    'last_failed_request_at',
                    'last_health_check_at',
                    'error_count',
                    'created_at',
                ]);
            }
        ])
        ->orderBy('priority')
        ->get();

        $recentLogs = AIRoutingLog::with(['provider:id,name,slug', 'model:id,display_name,model_identifier', 'credential:id,label,display_name'])
            ->latest('created_at')
            ->limit(25)
            ->get();

        $totalCredentials = AIProviderCredential::count();
        $activeCredentials = AIProviderCredential::where('status', 'active')
            ->where('is_valid', true)
            ->where('routing_eligibility', true)
            ->count();

        $stats = [
            'total_connections' => $providers->count(),
            'active_connections' => $providers->where('is_active', true)->count(),
            'circuit_open_count' => $providers->where('circuit_state', 'open')->count(),
            'total_credentials' => $totalCredentials,
            'active_credentials' => $activeCredentials,
            'total_routing_events_24h' => AIRoutingLog::where('created_at', '>=', now()->subDay())->count(),
            'total_tokens_24h' => (int) AIRoutingLog::where('created_at', '>=', now()->subDay())->sum('total_tokens'),
            'total_cost_24h_usd' => (float) AIRoutingLog::where('created_at', '>=', now()->subDay())->sum('estimated_cost_usd'),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'providers' => $providers,
            'recent_logs' => $recentLogs,
        ]);
    }

    /**
     * Update provider configuration (status, priority, circuit breaker, budget, routing strategy).
     */
    public function update(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'circuit_state' => ['sometimes', 'in:closed,half_open,open'],
            'provider_budget_monthly_usd' => ['nullable', 'numeric', 'min:0'],
            'default_routing_strategy' => ['sometimes', 'in:priority,round_robin,least_utilized,lowest_cost,health'],
            'default_model' => ['nullable', 'string', 'max:100'],
        ]);

        $admin = $request->user();
        $provider = AIProvider::findOrFail($id);
        $provider->update($request->only([
            'is_active',
            'priority',
            'circuit_state',
            'provider_budget_monthly_usd',
            'default_routing_strategy',
            'default_model',
        ]));

        if ($request->input('circuit_state') === 'closed') {
            $provider->update(['consecutive_failures' => 0, 'circuit_tripped_at' => null]);
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'ai_provider.updated',
            'resource_type' => 'AIProvider',
            'resource_id' => $provider->id,
            'new_values' => $request->all(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "AI connection '{$provider->name}' updated successfully.",
            'provider' => $provider->load(['quota', 'models']),
        ]);
    }

    // =========================================================================
    // CREDENTIAL MANAGEMENT (Section 4.2: Multiple API Credentials)
    // =========================================================================

    /**
     * List registered API credentials with masked keys.
     */
    public function credentials(Request $request): JsonResponse
    {
        $query = AIProviderCredential::with('provider:id,name,slug');

        if ($providerId = $request->query('provider_id')) {
            $query->where('provider_id', $providerId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $credentials = $query->orderBy('priority')->get()->map(function ($cred) {
            $maskedKey = 'sk-***-hidden';
            try {
                $decrypted = Crypt::decryptString($cred->encrypted_api_key);
                $maskedKey = substr($decrypted, 0, 7) . '...' . substr($decrypted, -4);
            } catch (\Throwable $e) {
                // If legacy plaintext or corrupted
                $maskedKey = 'sk-***-encrypted';
            }

            return array_merge($cred->toArray(), [
                'masked_key' => $maskedKey,
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $credentials,
        ]);
    }

    /**
     * Register a new API credential.
     */
    public function storeCredential(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'exists:ai_providers,id'],
            'display_name' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'min:10'],
            'account_label' => ['nullable', 'string', 'max:100'],
            'allowed_models' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:100'],
            'daily_request_budget' => ['nullable', 'integer', 'min:1'],
            'daily_token_budget' => ['nullable', 'integer', 'min:1'],
            'monthly_spending_budget' => ['nullable', 'numeric', 'min:0'],
            'routing_eligibility' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ]);

        $admin = $request->user();
        $apiKey = trim($validated['api_key']);
        $fingerprint = hash('sha256', $apiKey);

        // Duplicate detection via key fingerprint
        $existing = AIProviderCredential::where('key_fingerprint', $fingerprint)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_CREDENTIAL',
                    'message' => "This API credential is already registered under label '{$existing->display_name}'.",
                ]
            ], 422);
        }

        // Encrypt secret at rest using authenticated AES-256-GCM via APP_KEY
        $encryptedKey = Crypt::encryptString($apiKey);

        $credential = AIProviderCredential::create([
            'provider_id' => $validated['provider_id'],
            'label' => $validated['display_name'],
            'display_name' => $validated['display_name'],
            'encrypted_api_key' => $encryptedKey,
            'key_fingerprint' => $fingerprint,
            'account_label' => $validated['account_label'] ?? null,
            'allowed_models' => $validated['allowed_models'] ?? null,
            'priority' => $validated['priority'] ?? 1,
            'daily_request_budget' => $validated['daily_request_budget'] ?? null,
            'daily_token_budget' => $validated['daily_token_budget'] ?? null,
            'monthly_spending_budget' => $validated['monthly_spending_budget'] ?? null,
            'routing_eligibility' => $validated['routing_eligibility'] ?? true,
            'status' => $validated['status'] ?? 'active',
            'is_valid' => true,
            'last_tested_at' => now(),
            'last_health_check_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'ai_credential.registered',
            'resource_type' => 'AIProviderCredential',
            'resource_id' => $credential->id,
            'new_values' => [
                'provider_id' => $credential->provider_id,
                'display_name' => $credential->display_name,
                'fingerprint' => $fingerprint,
            ],
            'created_at' => now(),
        ]);

        $maskedKey = substr($apiKey, 0, 7) . '...' . substr($apiKey, -4);

        return response()->json([
            'success' => true,
            'message' => "API credential '{$credential->display_name}' registered and encrypted successfully.",
            'credential' => array_merge($credential->load('provider:id,name,slug')->toArray(), [
                'masked_key' => $maskedKey,
            ]),
        ], 201);
    }

    /**
     * Edit credential metadata and limits.
     */
    public function updateCredential(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'account_label' => ['nullable', 'string', 'max:100'],
            'allowed_models' => ['nullable', 'array'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'daily_request_budget' => ['nullable', 'integer', 'min:1'],
            'daily_token_budget' => ['nullable', 'integer', 'min:1'],
            'monthly_spending_budget' => ['nullable', 'numeric', 'min:0'],
            'routing_eligibility' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,inactive,suspended,cooldown,rate_limited'],
        ]);

        $credential = AIProviderCredential::findOrFail($id);
        $credential->update($validated);

        if (isset($validated['display_name'])) {
            $credential->update(['label' => $validated['display_name']]);
        }

        return response()->json([
            'success' => true,
            'message' => "Credential '{$credential->display_name}' updated successfully.",
            'credential' => $credential->load('provider:id,name,slug'),
        ]);
    }

    /**
     * Test credential connectivity using the AI Provider Integration Engine.
     */
    public function testCredential(string $id): JsonResponse
    {
        $credential = AIProviderCredential::with('provider')->findOrFail($id);

        $testResult = $this->routerService->testProviderConnection($credential->provider_id, $credential->id);

        if ($testResult->success) {
            return response()->json([
                'success' => true,
                'message' => $testResult->message,
                'latency_ms' => $testResult->latencyMs,
                'status' => 'healthy',
                'is_valid' => true,
                'model_verified' => $testResult->modelVerified,
                'last_tested_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $testResult->message,
            'latency_ms' => $testResult->latencyMs,
            'status' => 'error',
            'is_valid' => false,
            'status_code' => $testResult->statusCode,
            'details' => $testResult->details,
        ], 422);
    }

    /**
     * Execute live test prompt routing from Admin Panel.
     */
    public function executeTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'task_type' => ['nullable', 'string', 'max:100'],
            'provider_id' => ['nullable', 'exists:ai_providers,id'],
            'model_identifier' => ['nullable', 'string', 'max:100'],
            'routing_strategy' => ['nullable', 'in:priority,round_robin,least_utilized,lowest_cost,health,capability'],
        ]);

        $taskType = $validated['task_type'] ?? 'article_summarization';
        $options = [];
        if (!empty($validated['provider_id'])) {
            $options['provider_id'] = $validated['provider_id'];
        }
        if (!empty($validated['model_identifier'])) {
            $options['model_identifier'] = $validated['model_identifier'];
        }
        if (!empty($validated['routing_strategy'])) {
            $options['routing_strategy'] = $validated['routing_strategy'];
        }

        try {
            $aiRequest = AIRequest::fromPrompt(
                taskType: $taskType,
                prompt: $validated['prompt'],
                options: $options
            );
            $aiRequest->userId = $request->user()?->id;
            $aiRequest->module = 'admin_test_console';

            $response = $this->routerService->execute($aiRequest);

            return response()->json([
                'success' => true,
                'data' => $response->toArray(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AI_EXECUTION_FAILED',
                    'message' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * Delete an API credential.
     */
    public function destroyCredential(string $id, Request $request): JsonResponse
    {
        $admin = $request->user();
        $credential = AIProviderCredential::findOrFail($id);
        $displayName = $credential->display_name;

        $credential->delete();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'ai_credential.deleted',
            'resource_type' => 'AIProviderCredential',
            'resource_id' => $id,
            'old_values' => ['display_name' => $displayName],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "API credential '{$displayName}' has been deleted.",
        ]);
    }

    // =========================================================================
    // MODEL REGISTRY (Section 4.7)
    // =========================================================================

    /**
     * List all models in the registry.
     */
    public function models(Request $request): JsonResponse
    {
        $models = AIModel::with('provider:id,name,slug')
            ->orderBy('provider_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $models,
        ]);
    }

    /**
     * Register a new AI model in the registry.
     */
    public function storeModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'exists:ai_providers,id'],
            'model_identifier' => ['required', 'string', 'max:100'],
            'display_name' => ['required', 'string', 'max:255'],
            'cost_per_1k_input_tokens' => ['required', 'numeric', 'min:0'],
            'cost_per_1k_output_tokens' => ['required', 'numeric', 'min:0'],
            'context_window' => ['required', 'integer', 'min:1000'],
            'max_output_tokens' => ['nullable', 'integer', 'min:100'],
            'supports_structured_output' => ['nullable', 'boolean'],
            'supports_tool_calling' => ['nullable', 'boolean'],
            'supports_vision' => ['nullable', 'boolean'],
            'supports_embedding' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $model = AIModel::updateOrCreate(
            [
                'provider_id' => $validated['provider_id'],
                'model_identifier' => $validated['model_identifier']
            ],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => "Model '{$model->display_name}' registered successfully.",
            'model' => $model->load('provider:id,name,slug'),
        ], 201);
    }

    /**
     * Update model capabilities, context window, or pricing.
     */
    public function updateModel(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'cost_per_1k_input_tokens' => ['sometimes', 'numeric', 'min:0'],
            'cost_per_1k_output_tokens' => ['sometimes', 'numeric', 'min:0'],
            'context_window' => ['sometimes', 'integer', 'min:1000'],
            'max_output_tokens' => ['sometimes', 'integer', 'min:100'],
            'supports_structured_output' => ['sometimes', 'boolean'],
            'supports_tool_calling' => ['sometimes', 'boolean'],
            'supports_vision' => ['sometimes', 'boolean'],
            'supports_embedding' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model = AIModel::findOrFail($id);
        $model->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Model '{$model->display_name}' updated successfully.",
            'model' => $model->load('provider:id,name,slug'),
        ]);
    }

    // =========================================================================
    // AI TASK CONFIGURATIONS (Section 5)
    // =========================================================================

    /**
     * List all AI task configurations.
     */
    public function tasks(): JsonResponse
    {
        $tasks = AITaskConfig::with([
            'preferredProvider:id,name,slug',
            'preferredModel:id,display_name,model_identifier',
        ])
        ->orderBy('priority')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Update task configuration and fallback sequence.
     */
    public function updateTask(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferred_provider_id' => ['nullable', 'exists:ai_providers,id'],
            'preferred_model_id' => ['nullable', 'exists:ai_models,id'],
            'fallback_provider_ids' => ['nullable', 'array'],
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'max_output_tokens' => ['sometimes', 'integer', 'min:100', 'max:16384'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'budget_limit_usd' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $task = AITaskConfig::findOrFail($id);
        $task->update($validated);

        return response()->json([
            'success' => true,
            'message' => "AI task configuration for '{$task->display_name}' updated successfully.",
            'task' => $task->load(['preferredProvider', 'preferredModel']),
        ]);
    }

    // =========================================================================
    // USAGE & COST TRACKING (Section 4.8)
    // =========================================================================

    /**
     * Get aggregated usage and cost analytics.
     */
    public function usage(Request $request): JsonResponse
    {
        $logs = AIRoutingLog::with(['provider:id,name', 'model:id,display_name', 'credential:id,display_name'])
            ->latest('created_at')
            ->paginate((int) $request->query('per_page', 50));

        $totalTokens = (int) AIRoutingLog::sum('total_tokens');
        $totalCostUsd = (float) AIRoutingLog::sum('estimated_cost_usd');
        $totalRequests = AIRoutingLog::count();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_requests' => $totalRequests,
                'total_tokens' => $totalTokens,
                'total_cost_usd' => $totalCostUsd,
            ],
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ]
        ]);
    }
}
