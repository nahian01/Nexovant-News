<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessIngestedArticleIntelligenceJob;
use App\Models\AnomalyObservation;
use App\Models\Article;
use App\Models\AutomatedIntelligenceReport;
use App\Models\AutomationExecutionLog;
use App\Models\EarlyWarning;
use App\Models\IntelligenceSignal;
use App\Models\NewsSource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SubscriberMonitor;
use App\Models\SubscriberSavedSearch;
use App\Models\TrendForecast;
use App\Models\User;
use App\Services\Automation\AnomalyDetectionService;
use App\Services\Automation\EarlyWarningService;
use App\Services\Automation\IntelligenceAlertService;
use App\Services\Automation\IntelligenceMonitoringService;
use App\Services\Automation\PredictiveTrendService;
use App\Services\Discovery\SavedSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Stage13_1AutomationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $subscriberUserA;
    protected User $subscriberUserB;
    protected Organization $tenantA;
    protected Organization $tenantB;
    protected NewsSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'account_type' => 'admin',
            'email' => 'admin.surveillance@nexovant.test',
        ]);

        $this->subscriberUserA = User::factory()->create([
            'account_type' => 'subscriber',
            'email' => 'tenant.a.user@nexovant.test',
        ]);

        $this->subscriberUserB = User::factory()->create([
            'account_type' => 'subscriber',
            'email' => 'tenant.b.user@nexovant.test',
        ]);

        $this->tenantA = Organization::create([
            'name' => 'Acme Quantum Defense Corp',
            'slug' => 'acme-quantum-defense',
            'status' => 'active',
        ]);

        $this->tenantB = Organization::create([
            'name' => 'Apex Global Analytics',
            'slug' => 'apex-global-analytics',
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->subscriberUserA->id,
            'role' => 'admin',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->tenantB->id,
            'user_id' => $this->subscriberUserB->id,
            'role' => 'admin',
        ]);

        $this->source = NewsSource::create([
            'name' => 'Reuters Global Intelligence',
            'slug' => 'reuters-global-intel',
            'source_type' => 'rss',
            'url' => 'https://reuters.com/tech',
            'publisher_name' => 'Reuters Wire',
            'credibility_score' => 0.95,
            'trust_tier' => 'established_primary',
            'is_active' => true,
        ]);
    }

    /**
     * 1. Automated Scheduler Validation & Overlapping Prevention.
     */
    public function test_scheduler_commands_record_execution_logs_and_prevent_overlapping(): void
    {
        // 1.1 Run discovery:evaluate-saved-searches
        $exitCode1 = Artisan::call('discovery:evaluate-saved-searches', ['--cadence' => 'daily']);
        $this->assertSame(0, $exitCode1);

        $log1 = AutomationExecutionLog::where('command_name', 'discovery:evaluate-saved-searches')->latest('started_at')->first();
        $this->assertNotNull($log1);
        $this->assertSame('success', $log1->status);
        $this->assertSame('hourly', $log1->frequency);
        $this->assertNotNull($log1->completed_at);
        $this->assertGreaterThanOrEqual(0, $log1->duration_ms);

        // 1.2 Run intelligence:evaluate-monitors
        $exitCode2 = Artisan::call('intelligence:evaluate-monitors', ['--hours' => 24]);
        $this->assertSame(0, $exitCode2);

        $log2 = AutomationExecutionLog::where('command_name', 'intelligence:evaluate-monitors')->latest('started_at')->first();
        $this->assertNotNull($log2);
        $this->assertSame('success', $log2->status);
        $this->assertSame('everyFifteenMinutes', $log2->frequency);

        // 1.3 Overlapping Lock Protection
        $lock = Cache::lock('command:lock:intelligence:evaluate-monitors', 300);
        $this->assertTrue($lock->get());

        // When locked, another invocation should gracefully warn and exit 0 without running duplicate work
        $exitCodeOverlapping = Artisan::call('intelligence:evaluate-monitors', ['--hours' => 24]);
        $this->assertSame(0, $exitCodeOverlapping);

        $lock->release();
    }

    /**
     * 2. Real Ingestion Pipeline: ProcessIngestedArticleIntelligenceJob.
     */
    public function test_ingested_article_intelligence_job_lifecycle_and_retry_attributes(): void
    {
        $article = Article::create([
            'source_id' => $this->source->id,
            'title' => 'EU Antitrust Commission Opens Formal Probe into NextGen Cloud GPU Bundling',
            'slug' => 'eu-antitrust-cloud-gpu-bundling-' . uniqid(),
            'canonical_url' => 'https://reuters.com/antitrust-gpu-' . uniqid(),
            'content_raw' => 'The European Commission launched an in-depth antitrust inquiry into commercial bundling of cloud computing access with dedicated artificial intelligence accelerators.',
            'content_normalized' => 'The European Commission launched an in-depth antitrust inquiry into commercial bundling of cloud computing access with dedicated artificial intelligence accelerators.',
            'published_at' => now(),
            'status' => 'PUBLISHED',
            'verification_status' => 'reported_reliable',
            'importance_score' => 8.50,
        ]);

        $job = new ProcessIngestedArticleIntelligenceJob($article->id, $this->tenantA->id);

        // Verify required retry and timeout policies
        $this->assertSame(3, $job->tries);
        $this->assertSame(120, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff);

        // Execute job synchronously
        $monitoringService = app(IntelligenceMonitoringService::class);
        $savedSearchService = app(SavedSearchService::class);
        $job->handle($monitoringService, $savedSearchService);

        // Signal should be detected and evaluated
        $signal = IntelligenceSignal::where('title', 'like', '%EU Antitrust Commission%')->first();
        $this->assertNotNull($signal);
        $this->assertSame('regulatory_development', $signal->signal_category);
    }

    /**
     * 3. Early-Warning Notification Hardening (Escalation, Contradiction, Resolution, Reopening).
     */
    public function test_early_warning_notifications_across_full_lifecycle(): void
    {
        $alertService = app(IntelligenceAlertService::class);
        $warningService = app(EarlyWarningService::class);

        $signal = IntelligenceSignal::create([
            'title' => 'Critical Semiconductor Substrate Foundry Outage',
            'slug' => 'semi-substrate-foundry-outage-' . uniqid(),
            'signal_category' => 'supply_chain_disruption',
            'severity' => 'high',
            'status' => 'detected',
            'independent_sources_count' => 3,
            'verification_status' => 'corroborated',
            'organization_id' => $this->tenantA->id,
            'first_observed_at' => now(),
        ]);

        // Evaluate warning
        $warning = $warningService->evaluateSignalForWarning($signal);
        $this->assertNotNull($warning);
        $this->assertSame('elevated', $warning->warning_level);

        // 3.1 Escalation Alert Dispatch
        $escalatedCount = $alertService->dispatchWarningEscalatedAlert(
            warning: $warning,
            previousLevel: 'watch',
            recipientUserIds: [(string) $this->subscriberUserA->id]
        );
        $this->assertSame(1, $escalatedCount);

        // 3.2 Contradictory Evidence Alert Dispatch
        $contradictionAlerts = $alertService->dispatchWarningEvidenceUpdateAlert(
            warning: $warning,
            evidenceType: 'contradictory',
            summary: 'Secondary foundry operator reports backup production capacity restored in Dresden.',
            recipientUserIds: [(string) $this->subscriberUserA->id]
        );
        $this->assertSame(1, $contradictionAlerts);

        // 3.3 Supporting Corroboration Alert Dispatch
        $corroborationAlerts = $alertService->dispatchWarningEvidenceUpdateAlert(
            warning: $warning,
            evidenceType: 'supporting',
            summary: 'Trade association confirms wafer shipment lead times extended by 14 weeks.',
            recipientUserIds: [(string) $this->subscriberUserA->id]
        );
        $this->assertSame(1, $corroborationAlerts);

        // 3.4 Resolved Warning Transition & Alert
        $warningService->transitionStatus($warning, 'confirmed', $this->adminUser, 'Confirmed impact.');
        $warningService->transitionStatus($warning, 'resolved', $this->adminUser, 'Foundry capacity fully restored.');
        $this->assertSame('resolved', $warning->fresh()->status);

        // 3.5 Reopened Warning Transition & Alert
        $warningService->transitionStatus($warning, 'reopened', $this->adminUser, 'Secondary supply shock observed.');
        $this->assertSame('reopened', $warning->fresh()->status);
    }

    /**
     * 4. Anomaly Detection Validation: Zero-Variance Baseline Floor & Small Sample Guardrail.
     */
    public function test_anomaly_detection_zero_variance_safety_and_small_sample_guardrail(): void
    {
        $anomalyService = app(AnomalyDetectionService::class);

        // 4.1 Small sample size guardrail (N < 5)
        $insufficientAnomaly = $anomalyService->detectReportingFrequencyAnomaly(
            subjectType: 'topic',
            subjectName: 'Hypothetical Novel Quantum Mechanism',
            baselineDays: 30,
            observationDays: 7
        );

        $this->assertSame('insufficient', $insufficientAnomaly->data_sufficiency);
        $this->assertEquals(0.0, $insufficientAnomaly->deviation_score);
        $this->assertStringContainsString('Insufficient sample size', $insufficientAnomaly->limitations);

        // 4.2 Zero-variance baseline safety (Create 5 identical recent articles, 0 in baseline)
        for ($i = 0; $i < 6; $i++) {
            Article::create([
                'source_id' => $this->source->id,
                'title' => "Zero Variance Benchmark Subject Surge #{$i}",
                'slug' => "zero-variance-bench-{$i}-" . uniqid(),
                'canonical_url' => "https://reuters.com/zero-var-{$i}-" . uniqid(),
                'content_raw' => 'Consistent observation regarding Zero Variance Benchmark Subject.',
                'content_normalized' => 'Consistent observation regarding Zero Variance Benchmark Subject.',
                'published_at' => now()->subDays(2),
                'status' => 'PUBLISHED',
                'verification_status' => 'reported_reliable',
            ]);
        }

        $surgeAnomaly = $anomalyService->detectReportingFrequencyAnomaly(
            subjectType: 'topic',
            subjectName: 'Zero Variance Benchmark Subject',
            baselineDays: 30,
            observationDays: 7
        );

        // Standard deviation safe floor prevents division by zero or infinite z-scores
        $this->assertGreaterThan(0.0, $surgeAnomaly->deviation_score);
        $this->assertFalse(is_nan($surgeAnomaly->deviation_score));
        $this->assertFalse(is_infinite($surgeAnomaly->deviation_score));

        // 4.3 Duplicate observation suppression
        $duplicateAnomaly = $anomalyService->detectReportingFrequencyAnomaly(
            subjectType: 'topic',
            subjectName: 'Zero Variance Benchmark Subject',
            baselineDays: 30,
            observationDays: 7
        );

        $this->assertTrue($duplicateAnomaly->is_suppressed);
    }

    /**
     * 5. Walk-Forward Predictive Trend Backtesting & Holdout Refusal.
     */
    public function test_predictive_trend_walk_forward_backtesting_and_holdout_guardrail(): void
    {
        $forecastService = app(PredictiveTrendService::class);

        // 5.1 When historical weekly observations are < 8, holdout split is safely refused
        for ($w = 0; $w < 5; $w++) {
            Article::create([
                'source_id' => $this->source->id,
                'title' => "Optical Computing Waveguide Milestone Week {$w}",
                'slug' => "optical-computing-week-{$w}-" . uniqid(),
                'canonical_url' => "https://reuters.com/optical-{$w}-" . uniqid(),
                'content_raw' => 'Weekly disclosures regarding Optical Computing Waveguide innovations.',
                'content_normalized' => 'Weekly disclosures regarding Optical Computing Waveguide innovations.',
                'published_at' => now()->subDays(90)->addWeeks($w)->addDays(1),
                'status' => 'PUBLISHED',
            ]);
        }

        $forecast = $forecastService->generateForecast(
            subject: 'Optical Computing Waveguide',
            category: 'technology_adoption',
            horizon: '90d',
            tenantId: $this->tenantA->id
        );

        $this->assertTrue($forecast->is_supported);
        $this->assertSame('insufficient_sample_for_holdout', $forecast->holdout_evaluation['status']);
        $this->assertStringContainsString('minimum 8 required', $forecast->holdout_evaluation['message']);
        $this->assertNotNull($forecast->evaluation_metrics['in_sample_mae']);

        // 5.2 When historical weekly observations are >= 8, walk-forward holdout is executed
        for ($w = 5; $w < 10; $w++) {
            Article::create([
                'source_id' => $this->source->id,
                'title' => "Optical Computing Waveguide Milestone Week {$w}",
                'slug' => "optical-computing-week-{$w}-" . uniqid(),
                'canonical_url' => "https://reuters.com/optical-{$w}-" . uniqid(),
                'content_raw' => 'Weekly disclosures regarding Optical Computing Waveguide innovations.',
                'content_normalized' => 'Weekly disclosures regarding Optical Computing Waveguide innovations.',
                'published_at' => now()->subDays(90)->addWeeks($w)->addDays(1),
                'status' => 'PUBLISHED',
            ]);
        }

        $validatedForecast = $forecastService->generateForecast(
            subject: 'Optical Computing Waveguide',
            category: 'technology_adoption',
            horizon: '90d',
            tenantId: $this->tenantA->id
        );

        $this->assertSame('validated', $validatedForecast->holdout_evaluation['status']);
        $this->assertNotNull($validatedForecast->backtesting_metrics);
        $this->assertArrayHasKey('out_of_sample_mae', $validatedForecast->backtesting_metrics);
        $this->assertArrayHasKey('out_of_sample_rmse', $validatedForecast->backtesting_metrics);
        $this->assertArrayHasKey('prediction_interval_coverage_pct', $validatedForecast->backtesting_metrics);
        $this->assertArrayHasKey('naive_baseline_mae', $validatedForecast->backtesting_metrics);
    }

    /**
     * 6. Severity Standardization: Signal Severity vs Early-Warning Level.
     */
    public function test_severity_standardization_distinction(): void
    {
        $warningService = app(EarlyWarningService::class);

        // Signal severity: informational, medium, high, critical
        // Early-warning level: informational, watch, elevated, critical

        $highSignal = IntelligenceSignal::create([
            'title' => 'High Severity Policy Directive',
            'slug' => 'high-sev-policy-' . uniqid(),
            'signal_category' => 'policy_change',
            'severity' => 'high',
            'status' => 'detected',
            'independent_sources_count' => 2,
            'verification_status' => 'corroborated',
            'first_observed_at' => now(),
        ]);

        $warning = $warningService->evaluateSignalForWarning($highSignal);
        $this->assertNotNull($warning);
        $this->assertSame('high', $highSignal->severity);
        $this->assertSame('elevated', $warning->warning_level);

        $criticalSignal = IntelligenceSignal::create([
            'title' => 'Critical Infrastructure Disruption Alert',
            'slug' => 'crit-infra-alert-' . uniqid(),
            'signal_category' => 'supply_chain_disruption',
            'severity' => 'critical',
            'status' => 'detected',
            'independent_sources_count' => 4,
            'verification_status' => 'verified',
            'first_observed_at' => now(),
        ]);

        $critWarning = $warningService->evaluateSignalForWarning($criticalSignal);
        $this->assertNotNull($critWarning);
        $this->assertSame('critical', $criticalSignal->severity);
        $this->assertSame('critical', $critWarning->warning_level);
    }

    /**
     * 7. Multi-Tenant Authorization Isolation Across Automation Resources.
     */
    public function test_multi_tenant_authorization_isolation(): void
    {
        // 7.1 Private Report Isolation
        $tenantAReport = AutomatedIntelligenceReport::create([
            'title' => 'Confidential Intelligence Briefing: Acme Defense',
            'slug' => 'confidential-intel-briefing-acme-' . uniqid(),
            'report_type' => 'ad_hoc_brief',
            'published_at' => now(),
            'executive_summary' => 'Proprietary defense intelligence overview.',
            'synthesized_markdown' => '# Confidential Briefing',
            'key_developments' => [],
            'early_warnings_snapshot' => [],
            'active_trends_snapshot' => [],
            'structured_content' => ['executive_takeaways' => 'Sample content'],
            'methodology_notes' => 'Internal tenant analysis.',
            'organization_id' => $this->tenantA->id,
        ]);

        // Tenant A user can view report
        $resA = $this->actingAs($this->subscriberUserA)->getJson("/api/v1/automation/reports/{$tenantAReport->id}");
        $resA->assertOk();

        // Tenant B user is strictly rejected (403)
        $resB = $this->actingAs($this->subscriberUserB)->getJson("/api/v1/automation/reports/{$tenantAReport->id}");
        $resB->assertStatus(403);

        // Tenant B cannot export Tenant A report
        $exportB = $this->actingAs($this->subscriberUserB)->getJson("/api/v1/automation/reports/{$tenantAReport->id}/export?format=json");
        $exportB->assertStatus(403);

        // 7.2 Private Trend Forecast Isolation
        $tenantAForecast = TrendForecast::create([
            'subject' => 'Secret Quantum Radar Project',
            'forecast_category' => 'technology_adoption',
            'forecast_horizon' => '90d',
            'historical_window_days' => 90,
            'methodology' => 'linear_trend_extrapolation',
            'historical_data_points' => [],
            'forecasted_values' => [],
            'prediction_intervals' => [],
            'is_supported' => true,
            'organization_id' => $this->tenantA->id,
        ]);

        $forecastA = $this->actingAs($this->subscriberUserA)->getJson("/api/v1/automation/forecasts/{$tenantAForecast->id}");
        $forecastA->assertOk();

        $forecastB = $this->actingAs($this->subscriberUserB)->getJson("/api/v1/automation/forecasts/{$tenantAForecast->id}");
        $forecastB->assertStatus(403);

        // 7.3 Private Early Warning Isolation
        $tenantASignal = IntelligenceSignal::create([
            'title' => 'Tenant A Classified Signal',
            'slug' => 'tenant-a-classified-signal-' . uniqid(),
            'signal_category' => 'corporate_strategy_change',
            'severity' => 'high',
            'status' => 'detected',
            'organization_id' => $this->tenantA->id,
            'first_observed_at' => now(),
        ]);

        $tenantAWarning = EarlyWarning::create([
            'signal_id' => $tenantASignal->id,
            'title' => 'Tenant A Classified Early Warning',
            'slug' => 'tenant-a-classified-warning-' . uniqid(),
            'warning_level' => 'elevated',
            'status' => 'newly_detected',
            'factor_breakdown' => ['independent_sources' => 2],
            'explanation' => ['what_changed' => 'Proprietary strategic shift.'],
            'organization_id' => $this->tenantA->id,
        ]);

        $warningA = $this->actingAs($this->subscriberUserA)->getJson("/api/v1/automation/early-warnings/{$tenantAWarning->id}");
        $warningA->assertOk();

        $warningB = $this->actingAs($this->subscriberUserB)->getJson("/api/v1/automation/early-warnings/{$tenantAWarning->id}");
        $warningB->assertStatus(403);
    }

    /**
     * 8. Operational Telemetry & Administrative Observability.
     */
    public function test_operational_telemetry_endpoint_returns_heartbeat_and_execution_logs(): void
    {
        // Unauthenticated access rejected
        $this->getJson('/api/v1/admin/automation-control/telemetry')->assertUnauthorized();

        // Non-admin user forbidden
        $this->actingAs($this->subscriberUserA)
            ->getJson('/api/v1/admin/automation-control/telemetry')
            ->assertForbidden();

        // Admin access permitted
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/automation-control/telemetry');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'heartbeat' => [
                    'status',
                    'last_monitor_eval_at',
                    'last_saved_search_eval_at',
                ],
                'queue' => [
                    'status',
                    'pending_jobs',
                    'failed_jobs',
                    'executions_24h',
                    'failures_24h',
                    'avg_duration_ms',
                ],
                'recent_executions',
            ],
        ]);
    }
}
