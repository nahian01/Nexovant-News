<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Company;
use App\Models\ExtractedFact;
use App\Models\IntelligenceEvent;
use App\Models\NewsSource;
use App\Models\Publication;
use App\Models\PublicationDownloadLog;
use App\Models\PublicationDownloadToken;
use App\Models\PublicationRevision;
use App\Models\PublicationSchedule;
use App\Models\PublicationTemplate;
use App\Models\Role;
use App\Models\SocialTrend;
use App\Models\StoryCluster;
use App\Models\Technology;
use App\Models\User;
use App\Services\Publication\PdfRenderingService;
use App\Services\Publication\PublicationDistributionService;
use App\Services\Publication\PublicationGenerationService;
use App\Services\Publication\PublicationScheduleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExecutivePublicationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $subscriberUser;
    protected User $guestUser;
    protected PublicationTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@nexovant.si')->firstOrFail();

        $subscriberRole = Role::where('name', 'subscriber')->first();
        $this->subscriberUser = User::factory()->create([
            'account_type' => 'individual',
        ]);
        if ($subscriberRole) {
            $this->subscriberUser->roles()->attach($subscriberRole->id);
        }

        $this->guestUser = User::factory()->create([
            'account_type' => 'individual',
        ]);

        $this->template = PublicationTemplate::where('is_default', true)->firstOrFail();

        // Seed supporting intelligence evidence records
        $source = NewsSource::firstOrCreate(
            ['slug' => 'reuters-tech-wire'],
            [
                'name' => 'Reuters Tech Wire',
                'url' => 'https://reuters.com/technology',
                'feed_url' => 'https://reuters.com/technology/rss',
                'source_type' => 'wire',
                'is_active' => true,
            ]
        );

        $article = Article::firstOrCreate(
            ['slug' => 'asml-high-na-euv-foundry-shipments'],
            [
                'title' => 'ASML High-NA EUV Scanner Delivery to European Hub',
                'source_id' => $source->id,
                'canonical_url' => 'https://reuters.com/technology/asml-high-na-euv-foundry-shipments',
                'content_raw' => 'ASML delivers first commercial High-NA tool to IMEC in Belgium.',
                'content_normalized' => 'ASML delivers first commercial High-NA tool to IMEC in Belgium.',
                'published_at' => now(),
                'verification_status' => 'verified',
            ]
        );

        $cluster = StoryCluster::create([
            'article_id' => $article->id,
            'title' => 'Next-Gen High-NA EUV Tooling Deployment',
            'headline' => 'Next-Gen High-NA EUV Tooling Deployment',
            'slug' => 'next-gen-high-na-euv-tooling-deployment',
            'summary' => 'ASML and IMEC partner for sub-2nm lithography characterization.',
            'confidence_score' => 96,
            'status' => 'synthesized',
        ]);

        ExtractedFact::create([
            'source_id' => $source->id,
            'cluster_id' => $cluster->id,
            'claim' => 'IMEC cleanroom receives first High-NA 0.55 EUV pilot scanner.',
            'verification_status' => 'supported_multiple',
            'confidence_score' => 98,
        ]);

        Company::firstOrCreate(
            ['ticker' => 'ASML'],
            ['name' => 'ASML Holding N.V.', 'slug' => 'asml-holding', 'sector' => 'Semiconductor Equipment', 'summary' => 'Monopoly provider of EUV photolithography machines.']
        );

        Technology::firstOrCreate(
            ['slug' => 'high-na-euv'],
            ['name' => 'High-NA EUV Lithography', 'description' => '0.55 NA extreme ultraviolet lithography printing 8nm critical dimensions.', 'maturity_level' => 'pilot']
        );

        IntelligenceEvent::create([
            'title' => 'First European High-NA EUV Installation',
            'summary' => 'ASML and IMEC announce operational readiness of joint high-NA cleanroom.',
            'event_category' => 'breakthrough',
            'occurred_at' => now()->subDays(5),
            'reported_at' => now()->subDays(5),
            'discovered_at' => now()->subDays(5),
            'verification_status' => 'confirmed',
        ]);
    }

    public function test_publication_generation_from_ground_truth_records(): void
    {
        $generator = app(PublicationGenerationService::class);

        $pub = $generator->generate([
            'publication_type' => 'daily_briefing',
            'title' => 'European Lithography & Advanced Foundry Briefing',
            'subtitle' => 'High-NA Tooling & Regional Substrate Footprint',
            'reporting_period' => 'Nov 08, 2026 – Nov 15, 2026',
            'template_id' => $this->template->id,
            'visibility' => 'premium',
            'user_id' => $this->adminUser->id,
        ]);

        $this->assertInstanceOf(Publication::class, $pub);
        $this->assertEquals('daily_briefing', $pub->publication_type);
        $this->assertEquals('pending_review', $pub->status);
        $this->assertEquals('premium', $pub->visibility);
        $this->assertNotEmpty($pub->edition_number);
        $this->assertGreaterThanOrEqual(70, $pub->evidence_completeness_score);

        // Verify Content Structure
        $content = $pub->content_json;
        $this->assertIsArray($content);
        $this->assertArrayHasKey('executive_summary', $content);
        $this->assertArrayHasKey('key_intelligence', $content);
        $this->assertArrayHasKey('company_intelligence', $content);
        $this->assertArrayHasKey('technology_intelligence', $content);
        $this->assertArrayHasKey('trend_intelligence', $content);
        $this->assertArrayHasKey('historical_timeline', $content);
        $this->assertArrayHasKey('strategic_implications', $content);
        $this->assertArrayHasKey('sources', $content);

        // Verify Initial Revision
        $this->assertEquals(1, $pub->revisions()->count());
        $revision = $pub->revisions()->first();
        $this->assertEquals(1, $revision->version);
    }

    public function test_pdf_rendering_engine_produces_valid_pdf(): void
    {
        $generator = app(PublicationGenerationService::class);
        $renderer = app(PdfRenderingService::class);

        $pub = $generator->generate([
            'publication_type' => 'weekly_report',
            'title' => 'Weekly European Silicon Sovereignty Monitor',
            'template_id' => $this->template->id,
            'visibility' => 'public',
        ]);

        $pdfPath = $renderer->render($pub);

        $this->assertNotEmpty($pdfPath);
        $this->assertTrue(Storage::disk('local')->exists($pdfPath));

        $binary = Storage::disk('local')->get($pdfPath);
        $this->assertGreaterThan(5000, strlen($binary));
        $this->assertStringStartsWith('%PDF-', $binary);
        $this->assertGreaterThanOrEqual(1, $pub->page_count);
    }

    public function test_idempotent_generation_key_prevents_duplicate_editions(): void
    {
        $generator = app(PublicationGenerationService::class);

        $params = [
            'publication_type' => 'daily_briefing',
            'title' => 'Daily Idempotency Test Publication',
            'reporting_period' => '2026-11-15',
            'template_id' => $this->template->id,
        ];

        $pub1 = $generator->generate($params);
        $pub2 = $generator->generate($params);

        $this->assertEquals($pub1->id, $pub2->id);
        $this->assertEquals(1, Publication::where('title', 'Daily Idempotency Test Publication')->count());
    }

    public function test_editorial_workflow_lifecycle_and_revisions(): void
    {
        $generator = app(PublicationGenerationService::class);

        $pub = $generator->generate([
            'publication_type' => 'company_dossier',
            'title' => 'TSMC & ASML Joint Ecosystem Dossier',
            'template_id' => $this->template->id,
            'visibility' => 'premium',
        ]);

        $this->assertEquals('pending_review', $pub->status);

        // 1. Edit Content (saves new revision)
        $updatedContent = $pub->content_json;
        $updatedContent['executive_summary']['highlight'] = 'Edited by Chief Analyst: Strategic wafer commitments confirmed.';

        $resEdit = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/v1/admin/publications/{$pub->id}", [
            'content_json' => $updatedContent,
            'change_summary' => 'Updated executive summary highlight.',
        ]);
        $resEdit->assertOk();

        $pub->refresh();
        $this->assertEquals(2, $pub->revisions()->count());
        $this->assertEquals('Edited by Chief Analyst: Strategic wafer commitments confirmed.', $pub->content_json['executive_summary']['highlight']);

        // 2. Approve
        $resApprove = $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/publications/{$pub->id}/approve");
        $resApprove->assertOk();
        $this->assertEquals('approved', $pub->fresh()->status);
        $this->assertEquals($this->adminUser->id, $pub->fresh()->approved_by);

        // 3. Publish
        $resPublish = $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/publications/{$pub->id}/publish");
        $resPublish->assertOk();
        $this->assertEquals('published', $pub->fresh()->status);
        $this->assertNotNull($pub->fresh()->published_at);

        // 4. Archive
        $resArchive = $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/publications/{$pub->id}/archive");
        $resArchive->assertOk();
        $this->assertEquals('archived', $pub->fresh()->status);
    }

    public function test_secure_hmac_signed_download_and_entitlement(): void
    {
        $generator = app(PublicationGenerationService::class);
        $distributor = app(PublicationDistributionService::class);

        $pub = $generator->generate([
            'publication_type' => 'daily_briefing',
            'title' => 'Subscriber Secure Download Test Briefing',
            'visibility' => 'premium',
        ]);
        $pub->update(['status' => 'published']);

        // 1. Guest user cannot download premium publication
        $guestTokenAttempt = $this->actingAs($this->guestUser, 'sanctum')
            ->getJson("/api/v1/publications/{$pub->id}/download");
        $guestTokenAttempt->assertForbidden();

        // 2. Subscriber can generate signed download token
        $subTokenRes = $this->actingAs($this->subscriberUser, 'sanctum')
            ->getJson("/api/v1/publications/{$pub->id}/download");
        $subTokenRes->assertOk();
        $subTokenRes->assertJsonStructure([
            'success',
            'download_url',
            'token',
            'expires_at',
            'publication',
        ]);

        $rawToken = $subTokenRes->json('token');
        $this->assertNotEmpty($rawToken);

        // 3. Stream PDF using valid token
        $streamRes = $this->get("/api/v1/publications/{$pub->id}/stream?token=" . urlencode($rawToken));
        $streamRes->assertOk();
        $this->assertEquals('application/pdf', $streamRes->headers->get('Content-Type'));

        // Verify download was logged
        $this->assertEquals(1, PublicationDownloadLog::where('publication_id', $pub->id)->count());

        // 4. Tampered token is rejected
        $tamperedToken = $rawToken . 'invalid';
        $tamperedRes = $this->get("/api/v1/publications/{$pub->id}/stream?token=" . urlencode($tamperedToken));
        $tamperedRes->assertForbidden();

        // 5. Revoked token is rejected
        $distributor->revokePublicationTokens($pub->id);
        $revokedRes = $this->get("/api/v1/publications/{$pub->id}/stream?token=" . urlencode($rawToken));
        $revokedRes->assertForbidden();
    }

    public function test_publication_template_management(): void
    {
        // 1. List templates
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/publications/templates');
        $res->assertOk();
        $this->assertGreaterThanOrEqual(1, count($res->json('templates')));

        // 2. Create custom template
        $createRes = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/v1/admin/publications/templates', [
            'name' => 'Custom Executive Landscape Template',
            'page_size' => 'letter',
            'orientation' => 'landscape',
            'confidentiality_label' => 'BOARD OF DIRECTORS ONLY',
            'brand_config' => [
                'logo_text' => 'NEXOVANT BOARD BRIEF',
                'primary_color' => '#1A202C',
                'accent_color' => '#ED8936',
            ],
            'disclosure_text' => 'Exclusive board-level intelligence dossier.',
        ]);
        $createRes->assertCreated();
        $newTemplateId = $createRes->json('template.id');
        $this->assertNotNull($newTemplateId);

        // 3. Generate publication with new template
        $generator = app(PublicationGenerationService::class);
        $pub = $generator->generate([
            'publication_type' => 'custom_deep_research',
            'title' => 'Landscape Board Intelligence Dossier',
            'template_id' => $newTemplateId,
        ]);

        $this->assertEquals('letter', $pub->template->page_size);
        $this->assertEquals('landscape', $pub->template->orientation);
    }

    public function test_automated_publication_scheduler(): void
    {
        $schedule = PublicationSchedule::create([
            'publication_type' => 'daily_briefing',
            'template_id' => $this->template->id,
            'title_pattern' => 'Automated Scheduled Briefing — {date}',
            'frequency' => 'daily',
            'schedule_time' => '06:00',
            'timezone' => 'UTC',
            'require_review' => false,
            'is_enabled' => true,
            'next_run_at' => now()->subMinute(), // due
        ]);

        $scheduler = app(PublicationScheduleService::class);
        $generated = $scheduler->runDueSchedules();

        $this->assertCount(1, $generated);
        $pub = $generated[0];
        $this->assertEquals('published', $pub->status); // auto-published because require_review = false

        // Check schedule updated
        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertTrue(now()->isBefore($schedule->next_run_at));
    }

    public function test_admin_publication_api_overview_and_crud(): void
    {
        // 1. Overview
        $resOverview = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/publications/overview');
        $resOverview->assertOk();
        $resOverview->assertJsonStructure([
            'success',
            'metrics' => [
                'total_publications',
                'published_editions',
                'pending_reviews',
                'drafts',
                'active_schedules',
                'total_downloads',
            ],
        ]);

        // 2. Store Publication via API
        $resStore = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/v1/admin/publications', [
            'publication_type' => 'technology_report',
            'title' => 'API Created Technology Intelligence Report',
            'subtitle' => 'High-NA EUV Commercialization Roadmap',
            'template_id' => $this->template->id,
            'visibility' => 'premium',
        ]);
        $resStore->assertCreated();
        $pubId = $resStore->json('publication.id');

        // 3. Show Publication
        $resShow = $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/v1/admin/publications/{$pubId}");
        $resShow->assertOk();
        $this->assertEquals('API Created Technology Intelligence Report', $resShow->json('publication.title'));

        // 4. Delete Publication
        $resDel = $this->actingAs($this->adminUser, 'sanctum')->deleteJson("/api/v1/admin/publications/{$pubId}");
        $resDel->assertOk();
        $this->assertDatabaseMissing('publications', ['id' => $pubId]);
    }
}
