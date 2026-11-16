<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\EarlyWarning;
use App\Models\IntelligenceDetectionRule;
use App\Models\IntelligenceEvent;
use App\Models\IntelligenceSignal;
use App\Models\NewsSource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SubscriberMonitor;
use App\Models\User;
use App\Services\Automation\AnalystReviewService;
use App\Services\Automation\AnomalyDetectionService;
use App\Services\Automation\AutomatedIntelligenceReportService;
use App\Services\Automation\EarlyWarningService;
use App\Services\Automation\EventCorrelationService;
use App\Services\Automation\IntelligenceAlertService;
use App\Services\Automation\IntelligenceMonitoringService;
use App\Services\Automation\IntelligenceSignalDetectionService;
use App\Services\Automation\PredictiveTrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Stage13IntelligenceAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $subscriberUser;
    protected NewsSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'account_type' => 'admin',
            'email' => 'admin.automation@nexovant.test',
        ]);

        $this->subscriberUser = User::factory()->create([
            'account_type' => 'subscriber',
            'email' => 'subscriber.automation@nexovant.test',
        ]);

        $this->source = NewsSource::create([
            'name' => 'IEEE Spectrum Technology Review',
            'slug' => 'ieee-spectrum-tech',
            'source_type' => 'rss',
            'url' => 'https://spectrum.ieee.org',
            'publisher_name' => 'IEEE',
            'credibility_score' => 0.95,
            'trust_tier' => 'established_primary',
            'is_active' => true,
        ]);
    }

    public function test_signal_detection_from_ingested_article(): void
    {
        $article = Article::create([
            'source_id' => $this->source->id,
            'title' => 'High-NA EUV Scanner Deployment Achieves 2nm Lithography Milestone',
            'slug' => 'high-na-euv-lithography-milestone',
            'canonical_url' => 'https://spectrum.ieee.org/high-na-euv-2nm',
            'content_raw' => 'Intel and ASML confirmed deployment of 0.55 NA High-NA scanners with successful test wafer exposure.',
            'content_normalized' => 'Intel and ASML announced successful 2nm wafer exposure breakthrough utilizing High-NA lithography tools.',
            'content_hash' => hash('sha256', 'high-na-2nm-sample'),
            'published_at' => now(),
            'verification_status' => 'confirmed',
            'importance_score' => 9.5,
            'is_breaking' => true,
        ]);

        /** @var IntelligenceSignalDetectionService $service */
        $service = app(IntelligenceSignalDetectionService::class);
        $signal = $service->detectSignalFromArticle($article);

        $this->assertNotNull($signal);
        $this->assertEquals('technology_breakthrough', $signal->signal_category);
        $this->assertContains($signal->severity, ['high', 'critical']);
        $this->assertDatabaseHas('intelligence_signals', [
            'id' => $signal->id,
            'signal_category' => 'technology_breakthrough',
        ]);
        $this->assertDatabaseHas('signal_evidences', [
            'signal_id' => $signal->id,
            'is_independent' => true,
        ]);
    }

    public function test_duplicate_signal_prevention(): void
    {
        $article1 = Article::create([
            'source_id' => $this->source->id,
            'title' => 'TSMC Expands Sub-2nm Foundry Capex by $15B Across Europe',
            'slug' => 'tsmc-foundry-expansion-europe-1',
            'canonical_url' => 'https://spectrum.ieee.org/tsmc-europe-1',
            'content_raw' => 'TSMC announced board authorization for a $15B fab expansion.',
            'content_normalized' => 'TSMC approved $15B capital expansion for European foundry facilities.',
            'content_hash' => hash('sha256', 'tsmc-1'),
            'published_at' => now()->subHours(2),
            'verification_status' => 'confirmed',
        ]);

        $article2 = Article::create([
            'source_id' => $this->source->id,
            'title' => 'TSMC Expands Sub-2nm Foundry Capex in Dresden Germany',
            'slug' => 'tsmc-foundry-expansion-europe-2',
            'canonical_url' => 'https://spectrum.ieee.org/tsmc-europe-2',
            'content_raw' => 'Dresden facility confirmed receiving TSMC capex funding.',
            'content_normalized' => 'TSMC confirmed Dresden fab expansion funding.',
            'content_hash' => hash('sha256', 'tsmc-2'),
            'published_at' => now(),
            'verification_status' => 'confirmed',
        ]);

        /** @var IntelligenceSignalDetectionService $service */
        $service = app(IntelligenceSignalDetectionService::class);
        $sig1 = $service->detectSignalFromArticle($article1);
        $sig2 = $service->detectSignalFromArticle($article2);

        $this->assertEquals($sig1->id, $sig2->id);
        $this->assertEquals(2, $sig2->evidence_count);
    }

    public function test_anomaly_detection_with_sample_size_guardrails(): void
    {
        /** @var AnomalyDetectionService $service */
        $service = app(AnomalyDetectionService::class);

        // Case 1: Insufficient sample size (< 5 articles)
        $insufficientAnomaly = $service->detectReportingFrequencyAnomaly(
            subjectType: 'topic',
            subjectName: 'Quantum Annealing Cryptography',
            baselineDays: 30,
            observationDays: 7
        );

        $this->assertEquals('insufficient', $insufficientAnomaly->data_sufficiency);
        $this->assertEquals(0.0, $insufficientAnomaly->deviation_score);
        $this->assertStringContainsString('Insufficient sample size', $insufficientAnomaly->limitations);

        // Case 2: Create sufficient baseline articles and test surge
        for ($i = 0; $i < 6; $i++) {
            Article::create([
                'source_id' => $this->source->id,
                'title' => "Silicon Photonics Fab Report {$i}",
                'slug' => "silicon-photonics-fab-{$i}",
                'canonical_url' => "https://spectrum.ieee.org/silicon-photonics-{$i}",
                'content_raw' => 'Silicon Photonics production scaling.',
                'content_normalized' => 'Silicon photonics optical interconnects in hyperscale compute.',
                'content_hash' => hash('sha256', "sp-{$i}"),
                'published_at' => now()->subDays(2),
                'verification_status' => 'confirmed',
            ]);
        }

        $sufficientAnomaly = $service->detectReportingFrequencyAnomaly(
            subjectType: 'topic',
            subjectName: 'Silicon Photonics',
            baselineDays: 30,
            observationDays: 7
        );

        $this->assertEquals('sufficient', $sufficientAnomaly->data_sufficiency);
        $this->assertGreaterThan(0.0, $sufficientAnomaly->observed_value);
    }

    public function test_event_correlation_never_claims_causation(): void
    {
        $historicalEvent = IntelligenceEvent::create([
            'title' => 'EU Chips Act Subsidies Formalized for Semiconductor Foundries',
            'slug' => 'eu-chips-act-formalized',
            'event_category' => 'regulatory',
            'occurred_at' => now()->subDays(10),
            'reported_at' => now()->subDays(10),
            'verification_status' => 'confirmed',
        ]);

        $newArticle = Article::create([
            'source_id' => $this->source->id,
            'title' => 'Foundry Consortium Breaks Ground on New Dresden Fab',
            'slug' => 'foundry-dresden-groundbreak',
            'canonical_url' => 'https://spectrum.ieee.org/dresden-fab',
            'content_raw' => 'Consortium begins fabrication facility construction.',
            'content_normalized' => 'Groundbreaking ceremony for semiconductor fab in Dresden.',
            'content_hash' => hash('sha256', 'dresden-groundbreak'),
            'published_at' => now(),
            'verification_status' => 'confirmed',
        ]);

        /** @var EventCorrelationService $service */
        $service = app(EventCorrelationService::class);
        $correlations = $service->correlateArticleEvents($newArticle);

        $this->assertNotEmpty($correlations);
        $first = $correlations->first();
        // MANDATORY AUDIT: Causation must never be claimed
        $this->assertFalse($first->is_causation_claimed);
        $this->assertNotNull($first->alternative_explanations);
        $this->assertNotNull($first->unresolved_questions);
    }

    public function test_early_warning_lifecycle_and_9_point_explanation(): void
    {
        $article = Article::create([
            'source_id' => $this->source->id,
            'title' => 'Critical Rare Earth Export Controls Enacted for Gallium and Germanium',
            'slug' => 'export-controls-gallium-germanium',
            'canonical_url' => 'https://spectrum.ieee.org/gallium-germanium-controls',
            'content_raw' => 'Ministry announces immediate licensing restrictions on gallium shipments.',
            'content_normalized' => 'Immediate export licensing mandate imposed on semiconductor materials.',
            'content_hash' => hash('sha256', 'gallium-controls'),
            'published_at' => now(),
            'verification_status' => 'confirmed',
            'is_breaking' => true,
        ]);

        /** @var IntelligenceSignalDetectionService $signalService */
        $signalService = app(IntelligenceSignalDetectionService::class);
        $signal = $signalService->detectSignalFromArticle($article);

        /** @var EarlyWarningService $warningService */
        $warningService = app(EarlyWarningService::class);
        $warning = $warningService->evaluateSignalForWarning($signal);

        $this->assertNotNull($warning);
        $this->assertContains($warning->warning_level, ['elevated', 'critical', 'watch']);
        $this->assertEquals('newly_detected', $warning->status);

        // Verify all 9 explanation parts exist
        $exp = $warning->explanation;
        $this->assertArrayHasKey('what_changed', $exp);
        $this->assertArrayHasKey('when_first_observed', $exp);
        $this->assertArrayHasKey('supporting_sources', $exp);
        $this->assertArrayHasKey('independent_sources_count', $exp);
        $this->assertArrayHasKey('what_makes_it_unusual', $exp);
        $this->assertArrayHasKey('what_is_verified', $exp);
        $this->assertArrayHasKey('what_remains_uncertain', $exp);
        $this->assertArrayHasKey('why_considered_important', $exp);
        $this->assertArrayHasKey('additional_info_needed', $exp);

        // Test status transition
        $updated = $warningService->transitionStatus(
            warning: $warning,
            newStatus: 'under_analysis',
            user: $this->adminUser,
            reason: 'Analyst assigned to inspect primary trade gazette text.'
        );

        $this->assertEquals('under_analysis', $updated->status);
        $this->assertDatabaseHas('early_warning_histories', [
            'warning_id' => $warning->id,
            'from_status' => 'newly_detected',
            'to_status' => 'under_analysis',
        ]);
    }

    public function test_predictive_trend_forecasting_and_insufficient_data(): void
    {
        /** @var PredictiveTrendService $service */
        $service = app(PredictiveTrendService::class);

        // Case 1: Insufficient data points
        $forecastUnsupported = $service->generateForecast('Quantum Annealing Algorithms', 'technology_adoption', '90d');
        $this->assertFalse($forecastUnsupported->is_supported);
        $this->assertEquals('insufficient_sample_size', $forecastUnsupported->data_sufficiency_status);
        $this->assertEmpty($forecastUnsupported->forecasted_values);

        // Case 2: Create enough historical weekly data across past 8 weeks
        for ($w = 0; $w < 8; $w++) {
            Article::create([
                'source_id' => $this->source->id,
                'title' => "High-NA EUV Lithography Weekly Report Week {$w}",
                'slug' => "high-na-euv-lithography-weekly-{$w}",
                'canonical_url' => "https://spectrum.ieee.org/high-na-weekly-{$w}",
                'content_raw' => 'High-NA EUV tool deliveries.',
                'content_normalized' => 'High-NA EUV lithography optical scanner integration.',
                'content_hash' => hash('sha256', "euv-{$w}"),
                'published_at' => now()->subDays(7 * $w),
                'verification_status' => 'confirmed',
            ]);
        }

        $forecastSupported = $service->generateForecast('High-NA EUV Lithography', 'technology_adoption', '90d');
        $this->assertTrue($forecastSupported->is_supported);
        $this->assertEquals('sufficient', $forecastSupported->data_sufficiency_status);
        $this->assertNotEmpty($forecastSupported->forecasted_values);
        $this->assertNotEmpty($forecastSupported->prediction_intervals);
        $this->assertNotNull($forecastSupported->evaluation_metrics['mae']);
    }

    public function test_analyst_review_workflow_and_false_positive_marking(): void
    {
        $article = Article::create([
            'source_id' => $this->source->id,
            'title' => 'Rumors Surface Concerning Semiconductor Startup Restructuring',
            'slug' => 'semiconductor-startup-restructuring-rumor',
            'canonical_url' => 'https://spectrum.ieee.org/startup-rumor',
            'content_raw' => 'Unconfirmed reports suggest restructuring.',
            'content_normalized' => 'Unconfirmed assertions of organizational restructuring.',
            'content_hash' => hash('sha256', 'startup-rumor'),
            'published_at' => now(),
            'verification_status' => 'unverified',
        ]);

        /** @var IntelligenceSignalDetectionService $signalService */
        $signalService = app(IntelligenceSignalDetectionService::class);
        $signal = $signalService->detectSignalFromArticle($article);

        /** @var AnalystReviewService $reviewService */
        $reviewService = app(AnalystReviewService::class);

        $review = $reviewService->reviewSignal(
            signal: $signal,
            analyst: $this->adminUser,
            decision: 'marked_false_positive',
            feedbackNotes: 'Rumor debunked by official regulatory filings. Discarding noise.'
        );

        $this->assertEquals('marked_false_positive', $review->decision);
        $this->assertEquals('rejected', $signal->fresh()->review_status);
        $this->assertEquals('dismissed', $signal->fresh()->status);
    }

    public function test_alert_dispatch_integration_with_notification_engine(): void
    {
        $subscriber = User::factory()->create([
            'account_type' => 'subscriber',
            'email' => 'tech.monitor@nexovant.test',
        ]);

        // Create active subscriber monitor
        SubscriberMonitor::create([
            'user_id' => $subscriber->id,
            'name' => 'Lithography Monitor',
            'keywords' => ['lithography', 'euv', 'high-na'],
            'min_credibility_score' => 0.70,
            'alert_frequency' => 'real_time',
            'is_active' => true,
        ]);

        $article = Article::create([
            'source_id' => $this->source->id,
            'title' => 'ASML Finalizes Delivery of EXE:5000 High-NA EUV Tool',
            'slug' => 'asml-finalizes-exe5000-delivery',
            'canonical_url' => 'https://spectrum.ieee.org/asml-exe5000',
            'content_raw' => 'ASML completes commercial delivery of High-NA EUV scanner.',
            'content_normalized' => 'ASML finalized shipment of High-NA EUV lithography tool.',
            'content_hash' => hash('sha256', 'asml-delivery-alert'),
            'published_at' => now(),
            'verification_status' => 'confirmed',
            'is_breaking' => true,
        ]);

        /** @var IntelligenceMonitoringService $monitoringService */
        $monitoringService = app(IntelligenceMonitoringService::class);
        $summary = $monitoringService->evaluateIngestedArticle($article);

        $this->assertNotNull($summary['signal_id']);
        $this->assertGreaterThan(0, $summary['alerts_dispatched']);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $subscriber->id,
            'category' => 'early_warning',
        ]);
    }

    public function test_12_section_automated_intelligence_report_generation(): void
    {
        $article = Article::create([
            'source_id' => $this->source->id,
            'title' => 'Intel Achieves Sub-2nm Power Delivery Milestone with PowerVia',
            'slug' => 'intel-powervia-milestone',
            'canonical_url' => 'https://spectrum.ieee.org/powervia-milestone',
            'content_raw' => 'Intel reports backside power delivery achievement.',
            'content_normalized' => 'Intel PowerVia backside power delivery demonstrated on test silicon.',
            'content_hash' => hash('sha256', 'powervia-hash'),
            'published_at' => now()->subHours(6),
            'verification_status' => 'confirmed',
        ]);

        /** @var IntelligenceMonitoringService $monitoringService */
        $monitoringService = app(IntelligenceMonitoringService::class);
        $monitoringService->evaluateIngestedArticle($article);

        /** @var AutomatedIntelligenceReportService $reportService */
        $reportService = app(AutomatedIntelligenceReportService::class);
        $report = $reportService->generateReport('daily_summary', now()->subDay(), now(), $this->adminUser);

        $this->assertNotNull($report);
        $content = $report->structured_content;

        // Verify all 12 discrete sections
        $this->assertArrayHasKey('1_executive_summary', $content);
        $this->assertArrayHasKey('2_reporting_period', $content);
        $this->assertArrayHasKey('3_important_developments', $content);
        $this->assertArrayHasKey('4_emerging_signals', $content);
        $this->assertArrayHasKey('5_early_warnings', $content);
        $this->assertArrayHasKey('6_supporting_evidence', $content);
        $this->assertArrayHasKey('7_contradictory_evidence', $content);
        $this->assertArrayHasKey('8_trend_analysis', $content);
        $this->assertArrayHasKey('9_forecasts', $content);
        $this->assertArrayHasKey('10_changes_since_previous_report', $content);
        $this->assertArrayHasKey('11_unresolved_questions', $content);
        $this->assertArrayHasKey('12_sources_and_references', $content);

        // Verify Markdown export
        $markdown = $reportService->exportMarkdown($report);
        $this->assertStringContainsString('## 1. Executive Summary', $markdown);
        $this->assertStringContainsString('## 12. Traceable Sources & References', $markdown);
    }

    public function test_strict_multi_tenant_isolation_on_automation_records(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $org1 = Organization::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'owner_id' => $user1->id,
            'name' => 'Apex Microelectronics',
            'slug' => 'apex-microelectronics',
            'is_active' => true,
        ]);
        $org2 = Organization::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'owner_id' => $user2->id,
            'name' => 'Vortex Technologies',
            'slug' => 'vortex-technologies',
            'is_active' => true,
        ]);

        OrganizationMember::create(['organization_id' => $org1->id, 'user_id' => $user1->id, 'role' => 'admin']);
        OrganizationMember::create(['organization_id' => $org2->id, 'user_id' => $user2->id, 'role' => 'admin']);

        // Org1 private signal
        $signalOrg1 = IntelligenceSignal::create([
            'title' => 'Project Titan Sovereign Foundries Internal Memo',
            'slug' => 'titan-foundry-memo',
            'signal_category' => 'corporate_strategy_change',
            'severity' => 'critical',
            'status' => 'detected',
            'organization_id' => $org1->id,
            'evidence_count' => 1,
            'independent_sources_count' => 1,
        ]);

        // User2 requests signals
        $response = $this->actingAs($user2)->getJson('/api/v1/automation/signals');
        $response->assertStatus(200);

        // Assert Org1 signal is NOT leaked to Org2
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($signalOrg1->id, $ids);

        // User2 direct access to Org1 signal returns 403 Forbidden
        $directResponse = $this->actingAs($user2)->getJson("/api/v1/automation/signals/{$signalOrg1->id}");
        $directResponse->assertStatus(403);
    }

    public function test_admin_automation_control_and_rule_configuration(): void
    {
        // 1. Overview KPIs
        $res = $this->actingAs($this->adminUser)->getJson('/api/v1/admin/automation-control/overview');
        $res->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['total_signals', 'active_signals', 'false_positive_rate']]);

        // 2. Create Detection Rule
        $ruleRes = $this->actingAs($this->adminUser)->postJson('/api/v1/admin/automation-control/rules', [
            'name' => 'High-NA Surge Threshold Rule',
            'rule_type' => 'volume_velocity',
            'category' => 'technology_breakthrough',
            'thresholds' => [
                'min_independent_sources' => 3,
                'min_credibility' => 85.0,
                'velocity_percent_delta' => 50.0,
            ],
        ]);
        $ruleRes->assertStatus(201);
        $ruleId = $ruleRes->json('data.id');

        // 3. Update Rule Thresholds with Version Increment
        $updateRes = $this->actingAs($this->adminUser)->putJson("/api/v1/admin/automation-control/rules/{$ruleId}", [
            'thresholds' => [
                'min_independent_sources' => 4,
                'min_credibility' => 90.0,
                'velocity_percent_delta' => 60.0,
            ],
            'reason' => 'Tightened false-positive threshold for high-NA lithography.',
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals(2, $updateRes->json('data.version'));
    }
}
