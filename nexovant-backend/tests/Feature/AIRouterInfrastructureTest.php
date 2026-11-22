<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AIModel;
use App\Models\AIProvider;
use App\Models\AIProviderCredential;
use App\Models\AIRoutingLog;
use App\Models\AITaskConfig;
use App\Models\User;
use App\Services\AI\AIBudgetManager;
use App\Services\AI\AICredentialSelector;
use App\Services\AI\AICredentialVault;
use App\Services\AI\AIFallbackManager;
use App\Services\AI\AIRouterService;
use App\Services\AI\AIUsageTracker;
use App\Services\AI\DTO\AIRequest;
use App\Services\AI\DTO\AIResponse;
use App\Services\AI\Exceptions\AIAuthenticationException;
use App\Services\AI\Exceptions\AIProviderUnavailableException;
use App\Services\AI\Exceptions\AIQuotaExceededException;
use App\Services\AI\Exceptions\AIRateLimitException;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\Editorial\ArticleGenerationService;
use App\Services\Intelligence\FactExtractionService;
use App\Services\Taxonomy\AutoTaggingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIRouterInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@nexovant.si')->first() ?? User::factory()->create([
            'email' => 'admin@nexovant.si',
            'role' => 'admin',
        ]);
    }

    /**
     * Test 1: Credential Vault encryption, fingerprint deduplication, and safe masking.
     */
    public function test_credential_vault_encryption_deduplication_and_masking(): void
    {
        $vault = app(AICredentialVault::class);
        $provider = AIProvider::where('slug', 'google-gemini')->firstOrFail();

        $secretKey = 'AIzaSyTestSecretKey_987654321_ValidGoogleGeminiKey';
        $cred = $vault->registerCredential([
            'provider_id' => $provider->id,
            'display_name' => 'Primary Production Gemini Key',
            'api_key' => $secretKey,
            'account_label' => 'Primary GCP',
            'priority' => 1,
            'daily_request_budget' => 500,
        ]);

        $this->assertNotEmpty($cred->id);
        $this->assertNotEquals($secretKey, $cred->encrypted_api_key);
        $this->assertEquals(hash('sha256', $secretKey), $cred->key_fingerprint);

        // Decryption test in memory
        $decrypted = $vault->decryptSecret($cred);
        $this->assertEquals($secretKey, $decrypted);

        // Masked key formatting
        $masked = $vault->maskApiKey($secretKey);
        $this->assertStringStartsWith('AIzaSyT...', $masked);
        $this->assertStringEndsWith('Key', $masked);
        $this->assertStringNotContainsString('SecretKey', $masked);

        // Duplicate registration must throw exception
        $this->expectException(\InvalidArgumentException::class);
        $vault->registerCredential([
            'provider_id' => $provider->id,
            'display_name' => 'Duplicate Key Attempt',
            'api_key' => $secretKey,
        ]);
    }

    /**
     * Test 2: Dynamic routing strategies (Priority, Round Robin, Least Utilized, Lowest Cost, Health, Capability).
     */
    public function test_dynamic_credential_selector_strategies(): void
    {
        $vault = app(AICredentialVault::class);
        $selector = app(AICredentialSelector::class);
        $provider = AIProvider::where('slug', 'openai')->firstOrFail();

        // Clear existing credentials for clean test isolation
        AIProviderCredential::where('provider_id', $provider->id)->delete();

        // Create 3 credentials with varying attributes
        $credA = $vault->registerCredential([
            'provider_id' => $provider->id,
            'display_name' => 'OpenAI Key High Priority (Priority 1)',
            'api_key' => 'sk-test-key-alpha-11111111111111111111111',
            'priority' => 1,
            'daily_request_budget' => 100,
        ]);
        $credA->update(['priority' => 1, 'current_daily_requests' => 10, 'current_monthly_spend_usd' => 25.00]);

        $credB = $vault->registerCredential([
            'provider_id' => $provider->id,
            'display_name' => 'OpenAI Key Low Priority (Priority 2)',
            'api_key' => 'sk-test-key-beta-222222222222222222222222',
            'priority' => 2,
            'daily_request_budget' => 100,
        ]);
        $credB->update(['priority' => 2, 'current_daily_requests' => 2, 'current_monthly_spend_usd' => 2.00]);

        $credC = $vault->registerCredential([
            'provider_id' => $provider->id,
            'display_name' => 'OpenAI Key Damaged Health',
            'api_key' => 'sk-test-key-gamma-3333333333333333333333',
            'priority' => 3,
            'daily_request_budget' => 100,
        ]);
        $credC->update(['priority' => 3, 'current_daily_requests' => 20, 'current_monthly_spend_usd' => 40.00, 'error_count' => 8, 'last_failed_request_at' => now()->subMinute()]);

        // 1. Strategy: Priority (Lowest priority number wins)
        $selectedPriority = $selector->selectCredential($provider, 'gpt-4o-mini', 'priority');
        $this->assertEquals($credA->id, $selectedPriority->id);

        // 2. Strategy: Least Utilized (credB has only 5 requests vs 50)
        $selectedLeastUtilized = $selector->selectCredential($provider, 'gpt-4o-mini', 'least_utilized');
        $this->assertEquals($credB->id, $selectedLeastUtilized->id);

        // 3. Strategy: Lowest Cost (credB has $2.00 spend vs $25.00)
        $selectedLowestCost = $selector->selectCredential($provider, 'gpt-4o-mini', 'lowest_cost');
        $this->assertEquals($credB->id, $selectedLowestCost->id);

        // 4. Strategy: Health (credA is chosen over credC because credC has 8 errors)
        $selectedHealth = $selector->selectCredential($provider, 'gpt-4o-mini', 'health');
        $this->assertNotEquals($credC->id, $selectedHealth->id);

        // 5. Strategy: Round Robin (rotates across eligible credentials)
        $rr1 = $selector->selectCredential($provider, 'gpt-4o-mini', 'round_robin');
        $rr2 = $selector->selectCredential($provider, 'gpt-4o-mini', 'round_robin');
        $this->assertNotNull($rr1);
        $this->assertNotNull($rr2);
    }

    /**
     * Test 3: Budget enforcement stops requests when limits are exceeded.
     */
    public function test_budget_enforcement_prevents_spend_overrun(): void
    {
        $vault = app(AICredentialVault::class);
        $budgetManager = app(AIBudgetManager::class);
        $provider = AIProvider::where('slug', 'google-gemini')->firstOrFail();

        // Register credential with strict 5-request daily budget
        $cred = $vault->registerCredential([
            'provider_id' => $provider->id,
            'display_name' => 'Budget Capped Credential',
            'api_key' => 'AIzaSyTestBudgetCapKey_123456789012345',
            'daily_request_budget' => 5,
            'monthly_spending_budget' => 10.00,
        ]);

        // When under budget, validation succeeds without throwing
        $cred->update(['current_daily_requests' => 4, 'current_monthly_spend_usd' => 5.00]);
        $budgetManager->validateBudget($provider, $cred);
        $this->assertTrue(true);

        // When daily requests reach limit
        $cred->update(['current_daily_requests' => 5]);
        $this->expectException(AIQuotaExceededException::class);
        $budgetManager->validateBudget($provider, $cred);
    }

    /**
     * Test Quota warning and auto-rotation alert dispatched to operator email.
     */
    public function test_quota_threshold_warning_and_rotation_alert_sent_to_operator_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $budgetManager = app(AIBudgetManager::class);
        $provider = AIProvider::where('slug', 'google-gemini')->firstOrFail();
        $cred = AIProviderCredential::where('provider_id', $provider->id)->firstOrFail();

        $cred->update([
            'daily_token_budget' => 100000,
            'current_daily_tokens' => 85000, // 85%
            'status' => 'active',
        ]);

        $budgetManager->evaluateQuotaThresholds($provider, $cred);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\AIQuotaWarningMail::class, function ($mail) {
            return $mail->hasTo('abdullahalnahian0@gmail.com') && $mail->usagePercentage >= 85.0;
        });

        // Test 100% threshold triggers rotation and marks quota_exhausted
        $cred->update(['current_daily_tokens' => 100000]);
        $budgetManager->evaluateQuotaThresholds($provider, $cred);

        $this->assertEquals('quota_exhausted', $cred->fresh()->status);
    }

    /**
     * Test 4: Circuit breaker transitions and failure tripping.
     */
    public function test_circuit_breaker_trips_to_open_on_consecutive_failures(): void
    {
        $fallbackManager = app(AIFallbackManager::class);
        $provider = AIProvider::where('slug', 'anthropic')->firstOrFail();
        $provider->update(['circuit_state' => 'closed', 'consecutive_failures' => 0]);

        $this->assertTrue($fallbackManager->isProviderAvailable($provider));

        // Record 5 failures to trip the circuit breaker
        for ($i = 1; $i <= 5; $i++) {
            $fallbackManager->recordFailure($provider, "Simulated HTTP 503 Overloaded (Failure {$i})");
        }

        $provider->refresh();
        $this->assertEquals('open', $provider->circuit_state);
        $this->assertEquals(5, $provider->consecutive_failures);
        $this->assertNotNull($provider->circuit_tripped_at);

        // When circuit is open, provider is unavailable
        $this->assertFalse($fallbackManager->isProviderAvailable($provider));

        // When success occurs, circuit recovers to closed
        $fallbackManager->recordSuccess($provider);
        $provider->refresh();
        $this->assertEquals('closed', $provider->circuit_state);
        $this->assertEquals(0, $provider->consecutive_failures);
        $this->assertTrue($fallbackManager->isProviderAvailable($provider));
    }

    /**
     * Test 5: End-to-end AI Router execution with mock handler.
     */
    public function test_airouter_service_execution_and_logging(): void
    {
        $router = app(AIRouterService::class);

        $mockResponse = new AIResponse(
            content: 'Synthetic biology and quantum computing represent foundational technological shifts.',
            structuredData: [
                'summary' => 'Quantum computing creates exponential breakthroughs in computational biology.',
                'confidence' => 0.98,
            ],
            inputTokens: 120,
            outputTokens: 45,
            totalTokens: 165,
            estimatedCostUsd: 0.000215,
            latencyMs: 142,
            providerSlug: 'google-gemini',
            modelIdentifier: 'gemini-1.5-pro',
            finishReason: 'stop'
        );

        $router->setMockHandler(fn (AIRequest $req) => $mockResponse);

        $request = AIRequest::fromPrompt(
            taskType: 'article_summarization',
            prompt: 'Explain quantum biology advances in 1 sentence.'
        );

        $response = $router->execute($request);

        $this->assertEquals('google-gemini', $response->providerSlug);
        $this->assertEquals('gemini-1.5-pro', $response->modelIdentifier);
        $this->assertEquals(165, $response->totalTokens);
        $this->assertStringContainsString('quantum computing', $response->content);
        $this->assertEquals(0.98, $response->structuredData['confidence']);
    }

    /**
     * Test 6: Fallback chain execution when primary provider fails with HTTP 429.
     */
    public function test_fallback_chain_triggers_secondary_provider(): void
    {
        $vault = app(AICredentialVault::class);
        $gemini = AIProvider::where('slug', 'google-gemini')->firstOrFail();
        $openai = AIProvider::where('slug', 'openai')->firstOrFail();

        // Ensure both providers have valid credentials
        $vault->registerCredential([
            'provider_id' => $gemini->id,
            'display_name' => 'Gemini Primary Key',
            'api_key' => 'AIzaSyTestGeminiPrimaryFallback_12345678',
            'priority' => 1,
        ]);

        $vault->registerCredential([
            'provider_id' => $openai->id,
            'display_name' => 'OpenAI Secondary Key',
            'api_key' => 'sk-test-openai-secondary-fallback-987654321',
            'priority' => 1,
        ]);

        // Configure task to prefer Gemini with OpenAI as fallback
        $task = AITaskConfig::where('task_type', 'rss_summarization')->firstOrFail();
        $task->update([
            'preferred_provider_id' => $gemini->id,
            'fallback_provider_ids' => [$openai->id],
        ]);

        // Mock Http to return 429 for Gemini, and 200 for OpenAI
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Resource exhausted (quota exceeded).'],
            ], 429, ['Retry-After' => '10']),

            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => ['content' => 'Fallback succeeded via OpenAI.'],
                        'finish_reason' => 'stop',
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 50,
                    'completion_tokens' => 20,
                    'total_tokens' => 70,
                ],
            ], 200),
        ]);

        $router = app(AIRouterService::class);
        $request = AIRequest::fromPrompt(
            taskType: 'rss_summarization',
            prompt: 'Test fallback trigger from Gemini to OpenAI.'
        );

        $response = $router->execute($request);

        // Verification: Response was served by OpenAI fallback
        $this->assertEquals('openai', $response->providerSlug);
        $this->assertEquals('Fallback succeeded via OpenAI.', $response->content);

        // Verify routing log records fallback
        $log = AIRoutingLog::latest('created_at')->first();
        $this->assertNotNull($log);
        $this->assertEquals('fallback_triggered', $log->status);
        $this->assertEquals($gemini->id, $log->fallback_from_provider_id);
    }

    /**
     * Test 7: Editorial, Intelligence, and Taxonomy workflow services integration.
     */
    public function test_editorial_and_intelligence_workflow_services(): void
    {
        $router = app(AIRouterService::class);

        // Mock router responses for domain services
        $router->setMockHandler(function (AIRequest $req) {
            if ($req->taskType === 'headline_generation') {
                return new AIResponse(
                    content: '{"headlines": ["Quantum Leaps in Cyber Defense", "Next-Gen Encryption Revealed"]}',
                    structuredData: ['headlines' => ['Quantum Leaps in Cyber Defense', 'Next-Gen Encryption Revealed']],
                    inputTokens: 80,
                    outputTokens: 30,
                    totalTokens: 110,
                    estimatedCostUsd: 0.0001,
                    latencyMs: 120,
                    providerSlug: 'google-gemini',
                    modelIdentifier: 'gemini-1.5-flash'
                );
            }

            if ($req->taskType === 'fact_extraction') {
                return new AIResponse(
                    content: '{"facts": [{"claim": "QPU qubit count reached 1121", "confidence": 0.99}], "entities": ["IBM", "Condor"]}',
                    structuredData: [
                        'facts' => [['claim' => 'QPU qubit count reached 1121', 'confidence' => 0.99, 'entity' => 'IBM']],
                        'entities' => ['IBM', 'Condor'],
                    ],
                    inputTokens: 100,
                    outputTokens: 40,
                    totalTokens: 140,
                    estimatedCostUsd: 0.00015,
                    latencyMs: 115,
                    providerSlug: 'openai',
                    modelIdentifier: 'gpt-4o-mini'
                );
            }

            return new AIResponse(
                content: '{"category_slug": "quantum-computing", "topics": ["Hardware"], "tags": ["Qubit"], "confidence": 0.95}',
                structuredData: [
                    'category_slug' => 'quantum-computing',
                    'topics' => ['Hardware'],
                    'tags' => ['Qubit'],
                    'confidence' => 0.95,
                ],
                inputTokens: 90,
                outputTokens: 35,
                totalTokens: 125,
                estimatedCostUsd: 0.00012,
                latencyMs: 95,
                providerSlug: 'google-gemini',
                modelIdentifier: 'gemini-1.5-flash'
            );
        });

        // 1. ArticleGenerationService
        $editorial = new ArticleGenerationService($router);
        $headlines = $editorial->generateHeadlines('Quantum computing processor architecture overview.');
        $this->assertCount(2, $headlines);
        $this->assertEquals('Quantum Leaps in Cyber Defense', $headlines[0]);

        // 2. FactExtractionService
        $intelligence = new FactExtractionService($router);
        $facts = $intelligence->extractFacts('IBM unveiled Condor processor with 1121 superconducting qubits.');
        $this->assertCount(1, $facts['facts']);
        $this->assertEquals('QPU qubit count reached 1121', $facts['facts'][0]['claim']);
        $this->assertContains('Condor', $facts['entities']);

        // 3. AutoTaggingService
        $taxonomy = new AutoTaggingService($router);
        $classification = $taxonomy->classifyStory('Breakthrough in trapped-ion quantum coherence time.');
        $this->assertEquals('quantum-computing', $classification['category_slug']);
        $this->assertContains('Hardware', $classification['topics']);
    }

    /**
     * Test 8: Admin API endpoints for testing credentials and prompt execution.
     */
    public function test_admin_api_ai_test_execution_and_connectivity(): void
    {
        $vault = app(AICredentialVault::class);
        $provider = AIProvider::where('slug', 'google-gemini')->firstOrFail();

        $cred = $vault->registerCredential([
            'provider_id' => $provider->id,
            'display_name' => 'Admin Live Test Key',
            'api_key' => 'AIzaSyAdminLiveTestKey_1122334455667788',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Live test executed successfully via Google Gemini API.'],
                            ],
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 25,
                    'candidatesTokenCount' => 15,
                    'totalTokenCount' => 40,
                ],
            ], 200),
            'https://generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
                'displayName' => 'Gemini 2.0 Flash',
                'state' => 'ACTIVE',
            ], 200),
        ]);

        // 1. Test credential connectivity endpoint
        $testConnRes = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/admin/ai-router/credentials/{$cred->id}/test");

        $testConnRes->assertOk();
        $testConnRes->assertJsonFragment([
            'success' => true,
            'status' => 'healthy',
            'is_valid' => true,
        ]);

        // 2. Test live prompt execution endpoint
        $executeRes = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/ai-router/execute-test', [
                'prompt' => 'Test connection and prompt generation',
                'task_type' => 'article_summarization',
                'provider_id' => $provider->id,
                'routing_strategy' => 'priority',
            ]);

        $executeRes->assertOk();
        $executeRes->assertJsonPath('success', true);
        $executeRes->assertJsonPath('data.provider_slug', 'google-gemini');
        $executeRes->assertJsonPath('data.total_tokens', 40);
        $this->assertStringContainsString('Live test executed', $executeRes->json('data.content'));
    }
}
