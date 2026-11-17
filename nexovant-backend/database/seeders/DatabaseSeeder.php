<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AIModel;
use App\Models\AIProvider;
use App\Models\AIProviderQuota;
use App\Models\AITaskConfig;
use App\Models\Article;
use App\Models\ArticleAssessment;
use App\Models\ArticleRevision;
use App\Models\ArticleSummary;
use App\Models\Category;
use App\Models\Company;
use App\Models\Executive;
use App\Models\NewsSource;
use App\Models\PaymentProviderConfig;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\Role;
use App\Models\SocialTopic;
use App\Models\SocialTrend;
use App\Models\SocialTrendMetric;
use App\Models\Subcategory;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Seed Roles and Permissions
        $rolesData = [
            ['name' => 'super_admin', 'display_name' => 'Super Administrator', 'description' => 'Full unrestricted platform access.'],
            ['name' => 'admin', 'display_name' => 'Platform Administrator', 'description' => 'Administrative and operations management.'],
            ['name' => 'editorial_admin', 'display_name' => 'Editorial Lead', 'description' => 'Article publishing, review, and verification oversight.'],
            ['name' => 'support_admin', 'display_name' => 'Support Desk Lead', 'description' => 'User account and payment proof verification.'],
            ['name' => 'subscriber', 'display_name' => 'Active Subscriber', 'description' => 'Access to authenticated intelligence and saved workspace.'],
        ];

        $roles = [];
        foreach ($rolesData as $r) {
            $roles[$r['name']] = Role::updateOrCreate(['name' => $r['name']], $r);
        }

        $permissionsData = [
            // Admin operations
            ['name' => 'admin.dashboard.view', 'display_name' => 'View Admin Dashboard', 'module' => 'admin'],
            ['name' => 'admin.users.manage', 'display_name' => 'Manage Platform Users', 'module' => 'admin'],
            ['name' => 'admin.settings.manage', 'display_name' => 'Update System Settings', 'module' => 'admin'],
            ['name' => 'admin.audit.view', 'display_name' => 'Inspect Security Audit Logs', 'module' => 'admin'],

            // Editorial
            ['name' => 'editorial.articles.manage', 'display_name' => 'Full Editorial CRUD and Publishing', 'module' => 'editorial'],
            ['name' => 'editorial.review.verdict', 'display_name' => 'Editorial Verification and Overrides', 'module' => 'editorial'],

            // AI Management
            ['name' => 'ai.router.view', 'display_name' => 'Inspect AI Router Metrics', 'module' => 'ai_router'],
            ['name' => 'ai.credentials.manage', 'display_name' => 'Register and Manage API Credentials', 'module' => 'ai_router'],
            ['name' => 'ai.models.manage', 'display_name' => 'Manage Model Registry and Tasks', 'module' => 'ai_router'],

            // Sources & Pipeline
            ['name' => 'sources.manage', 'display_name' => 'Manage News Sources and Polling', 'module' => 'sources'],
            ['name' => 'pipeline.manage', 'display_name' => 'Control Automation Pipeline and Jobs', 'module' => 'sources'],

            // Billing & Payments
            ['name' => 'billing.payments.verify', 'display_name' => 'Verify Manual Payment Proofs', 'module' => 'billing'],

            // Intelligence Research
            ['name' => 'research.entities.manage', 'display_name' => 'Manage Intelligence Entities and Graph', 'module' => 'research'],
            ['name' => 'research.verification.manage', 'display_name' => 'Verify Claims and Conflicting Evidence', 'module' => 'research'],
            ['name' => 'research.workspace.manage', 'display_name' => 'Execute Deep Research Projects', 'module' => 'research'],
            ['name' => 'research.reports.manage', 'display_name' => 'Publish and Review Research Reports', 'module' => 'research'],
        ];

        foreach ($permissionsData as $p) {
            $perm = Permission::updateOrCreate(['name' => $p['name']], $p);
            $roles['super_admin']->permissions()->syncWithoutDetaching([$perm->id]);
            $roles['admin']->permissions()->syncWithoutDetaching([$perm->id]);

            if (str_starts_with($p['module'], 'editorial') || str_starts_with($p['module'], 'research')) {
                $roles['editorial_admin']->permissions()->syncWithoutDetaching([$perm->id]);
            }
            if (str_starts_with($p['module'], 'billing')) {
                $roles['support_admin']->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }

        // 1. Create Default Admin & Subscriber Accounts
        // Security Hardening: In production, never use default demo passwords; rely on env or generate secure random string
        $adminPassword = env('SEED_ADMIN_PASSWORD');
        if (empty($adminPassword)) {
            $adminPassword = app()->environment('production') ? Str::random(40) : (env('APP_DEBUG') ? 'NexoDev-' . Str::random(16) : Str::random(40));
        }

        $execPassword = env('SEED_EXECUTIVE_PASSWORD');
        if (empty($execPassword)) {
            $execPassword = app()->environment('production') ? Str::random(40) : (env('APP_DEBUG') ? 'NexoDev-' . Str::random(16) : Str::random(40));
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@nexovant.si'],
            [
                'name' => 'Nexovant Chief Administrator',
                'password' => Hash::make($adminPassword),
                'account_type' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->roles()->syncWithoutDetaching([$roles['super_admin']->id, $roles['admin']->id]);

        $executive = User::firstOrCreate(
            ['email' => 'executive@enterprise.corp'],
            [
                'name' => 'Alexander Vance',
                'password' => Hash::make($execPassword),
                'account_type' => 'individual',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $executive->roles()->syncWithoutDetaching([$roles['subscriber']->id]);

        // 2. Seed Subscription Plans & Entitlements
        $plansData = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Essential business headlines with delayed summaries.',
                'price_bdt_monthly' => 0,
                'price_bdt_annual' => 0,
                'price_usd_monthly' => 0,
                'price_usd_annual' => 0,
                'is_popular' => false,
                'sort_order' => 1,
                'entitlements' => [
                    'news.access' => 'true',
                    'news.daily_limit' => '10',
                    'company.follow_limit' => '3',
                    'executive.follow_limit' => '3',
                    'competitor.monitoring' => 'false',
                    'personalized_briefing' => 'false',
                    'daily_pdf' => 'false',
                    'rag.query_limit' => '0',
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For independent analysts, entrepreneurs and business operators.',
                'price_bdt_monthly' => 499,
                'price_bdt_annual' => 4990,
                'price_usd_monthly' => 9,
                'price_usd_annual' => 90,
                'is_popular' => false,
                'sort_order' => 2,
                'entitlements' => [
                    'news.access' => 'true',
                    'news.daily_limit' => 'unlimited',
                    'company.follow_limit' => '25',
                    'executive.follow_limit' => '25',
                    'competitor.monitoring' => 'true',
                    'personalized_briefing' => 'true',
                    'daily_pdf' => 'false',
                    'rag.query_limit' => '50',
                ],
            ],
            [
                'name' => 'Executive',
                'slug' => 'executive',
                'description' => 'For corporate executives, venture partners, and institutional investors.',
                'price_bdt_monthly' => 999,
                'price_bdt_annual' => 9990,
                'price_usd_monthly' => 19,
                'price_usd_annual' => 190,
                'is_popular' => true,
                'sort_order' => 3,
                'entitlements' => [
                    'news.access' => 'true',
                    'news.daily_limit' => 'unlimited',
                    'company.follow_limit' => '100',
                    'executive.follow_limit' => '100',
                    'competitor.monitoring' => 'true',
                    'personalized_briefing' => 'true',
                    'daily_pdf' => 'true',
                    'weekly_pdf' => 'true',
                    'rag.query_limit' => '250',
                ],
            ],
            [
                'name' => 'Executive Pro',
                'slug' => 'executive-pro',
                'description' => 'For enterprise leadership, strategy desks and private equity teams.',
                'price_bdt_monthly' => 1999,
                'price_bdt_annual' => 19990,
                'price_usd_monthly' => 39,
                'price_usd_annual' => 390,
                'is_popular' => false,
                'sort_order' => 4,
                'entitlements' => [
                    'news.access' => 'true',
                    'news.daily_limit' => 'unlimited',
                    'company.follow_limit' => 'unlimited',
                    'executive.follow_limit' => 'unlimited',
                    'competitor.monitoring' => 'true',
                    'personalized_briefing' => 'true',
                    'daily_pdf' => 'true',
                    'weekly_pdf' => 'true',
                    'custom_reports' => 'true',
                    'breaking_alerts' => 'true',
                    'organization.members' => '5',
                    'rag.query_limit' => '1000',
                ],
            ],
        ];

        foreach ($plansData as $planItem) {
            $entitlements = $planItem['entitlements'];
            unset($planItem['entitlements']);

            $plan = Plan::updateOrCreate(['slug' => $planItem['slug']], $planItem);

            foreach ($entitlements as $key => $val) {
                PlanEntitlement::updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_key' => $key],
                    ['value_type' => is_numeric($val) ? 'integer' : 'string', 'value' => $val]
                );
            }
        }

        // 3. Seed News Categories & Subcategories
        $categories = [
            [
                'name' => 'Leadership Transitions',
                'slug' => 'leadership-transitions',
                'color' => '#00ADB5',
                'subcategories' => ['CEO Transitions', 'Boardroom Moves', 'CFO & Finance', 'Executive Resignations'],
            ],
            [
                'name' => 'Financial Performance & Earnings',
                'slug' => 'financial-performance',
                'color' => '#10B981',
                'subcategories' => ['Earnings Disclosures', 'Guidance Revisions', 'Credit Ratings', 'Revenue Misses'],
            ],
            [
                'name' => 'Acquisitions & Mergers',
                'slug' => 'acquisitions-mergers',
                'color' => '#D4AF37',
                'subcategories' => ['Strategic Buyouts', 'Antitrust Review', 'Hostile Bids', 'Divestitures'],
            ],
            [
                'name' => 'Restructuring & Workforce',
                'slug' => 'restructuring-workforce',
                'color' => '#F59E0B',
                'subcategories' => ['Workforce Realignment', 'Operational Spinoffs', 'Plant Closures', 'Cost Optimization'],
            ],
            [
                'name' => 'Capital Expansion & Capex',
                'slug' => 'expansion-capex',
                'color' => '#3B82F6',
                'subcategories' => ['Foundry & Fabs', 'Data Centers', 'Geographic Expansion', 'Joint Ventures'],
            ],
            [
                'name' => 'Emerging Business Risks',
                'slug' => 'emerging-risks',
                'color' => '#EF4444',
                'subcategories' => ['Sanctions & Geopolitics', 'Supply Chain Snarls', 'Patent Litigation', 'Regulatory Scrutiny'],
            ],
            [
                'name' => 'Regulatory & Legal Filings',
                'slug' => 'regulatory-legal',
                'color' => '#8B5CF6',
                'subcategories' => ['SEC Filings', 'EU Antimonopoly', 'Export Controls', 'Data Privacy Mandates'],
            ],
        ];

        foreach ($categories as $catData) {
            $subcats = $catData['subcategories'];
            unset($catData['subcategories']);

            $cat = Category::updateOrCreate(['slug' => $catData['slug']], $catData);

            foreach ($subcats as $subName) {
                Subcategory::updateOrCreate(
                    ['category_id' => $cat->id, 'slug' => Str::slug($subName)],
                    ['name' => $subName, 'is_active' => true]
                );
            }
        }

        // 4. Seed News Sources
        $source = NewsSource::updateOrCreate(
            ['slug' => 'sec-edgar-official'],
            [
                'name' => 'SEC EDGAR Official Disclosures',
                'source_type' => 'regulatory',
                'url' => 'https://www.sec.gov/edgar',
                'feed_url' => 'https://www.sec.gov/cgi-bin/browse-edgar?action=getcurrent',
                'credibility_score' => 1.00,
                'is_active' => true,
                'health_status' => 'healthy',
                'fetch_interval_minutes' => 15,
            ]
        );

        $financialSource = NewsSource::updateOrCreate(
            ['slug' => 'global-business-wire'],
            [
                'name' => 'Global Regulatory Disclosures Wire',
                'source_type' => 'official_disclosure',
                'url' => 'https://www.businesswire.com',
                'credibility_score' => 0.95,
                'is_active' => true,
                'health_status' => 'healthy',
                'fetch_interval_minutes' => 15,
            ]
        );

        // 5. Seed Companies & Executives
        $nvidia = Company::updateOrCreate(
            ['slug' => 'nvidia-corporation'],
            [
                'name' => 'NVIDIA Corporation',
                'ticker' => 'NVDA',
                'isin' => 'US67066G1040',
                'sector' => 'Technology',
                'industry' => 'Semiconductors',
                'headquarters_country' => 'US',
                'market_cap_tier' => 'mega',
                'summary' => 'Global leader in accelerated computing platforms and artificial intelligence hardware.',
            ]
        );

        $jensen = Executive::updateOrCreate(
            ['slug' => 'jensen-huang'],
            [
                'full_name' => 'Jensen Huang',
                'current_title' => 'Chief Executive Officer & Co-Founder',
                'biography' => 'Founder and Chief Executive Officer of NVIDIA Corporation.',
                'verified_profile' => true,
            ]
        );

        $nvidia->executives()->syncWithoutDetaching([
            $jensen->id => ['title' => 'President & CEO', 'is_current' => true, 'appointed_date' => '1993-01-01']
        ]);

        $novartis = Company::updateOrCreate(
            ['slug' => 'novartis-ag'],
            [
                'name' => 'Novartis AG',
                'ticker' => 'NVS',
                'isin' => 'CH0012005267',
                'sector' => 'Healthcare',
                'industry' => 'Pharmaceuticals',
                'headquarters_country' => 'CH',
                'market_cap_tier' => 'large',
                'summary' => 'Global medicines company focused on innovative prescription therapeutics.',
            ]
        );

        // 6. Seed Sample 10-Dimension Articles & Initial Revision
        $article1 = Article::updateOrCreate(
            ['slug' => 'nvidia-custom-silicon-foundry-emea'],
            [
                'source_id' => $source->id,
                'title' => 'NVIDIA Board Approves $15B Custom Silicon Foundry Expansion in EMEA',
                'canonical_url' => 'https://www.sec.gov/Archives/edgar/data/1045810/nvda-expansion-emea',
                'content_raw' => 'NVIDIA Corporation announced board approval for a $15 billion long-term infrastructure and packaging facility located across Germany and France.',
                'content_normalized' => 'NVIDIA Corporation approved a $15B packaging and custom silicon program in EMEA, directly reinforcing EU AI chip autonomy.',
                'content_hash' => hash('sha256', 'nvidia-custom-silicon-foundry-emea-2026'),
                'published_at' => now()->subMinutes(14),
                'verification_status' => 'confirmed',
                'importance_score' => 9.40,
                'is_breaking' => true,
                'is_editorial_approved' => true,
                'status' => 'PUBLISHED',
                'lock_category' => true,
                'lock_content' => true,
            ]
        );

        ArticleSummary::updateOrCreate(
            ['article_id' => $article1->id],
            [
                'executive_summary' => 'NVIDIA initiates a $15B semiconductor fabrication packaging initiative in EMEA, establishing regional sovereign compute capacity under European Chips Act incentives.',
                'key_takeaways' => [
                    'Reinforces supply chain autonomy against APAC geopolitical frictions',
                    'Capital expenditure financed through internal operating cash flows',
                    'Operational packaging expected online Q1 FY28'
                ],
                'model_version' => 'claude-3-5-sonnet',
            ]
        );

        ArticleAssessment::updateOrCreate(
            ['article_id' => $article1->id],
            [
                'what_happened' => 'Board authorization of a $15B EMEA custom silicon packaging and test campus.',
                'when_happened' => now()->subMinutes(30)->toIso8601String(),
                'why_happened' => 'To comply with sovereign compute mandates and de-risk exclusive reliance on Pacific fabrication routes.',
                'business_significance' => 'Expands gross margin potential in regulated European defence and public sector AI tenders.',
                'who_affected' => 'TSMC, Intel Foundry Services, ASML, European cloud operators.',
                'potential_consequences' => 'Accelerates EU state aid approvals; potential short-term margin dampening during ramp phase.',
                'what_to_monitor_next' => 'Formal announcement of municipal site selections and EU Chips Act subsidy grants.',
                'confidence_score' => 0.98,
            ]
        );

        ArticleRevision::updateOrCreate(
            ['article_id' => $article1->id, 'version_number' => 1],
            [
                'user_id' => $admin->id,
                'title' => $article1->title,
                'content_raw' => $article1->content_raw,
                'content_normalized' => $article1->content_normalized,
                'summary' => 'Initial verified executive publication with complete 10-dimension assessment.',
                'change_summary' => 'Initial editorial approval and publication.',
                'created_at' => now()->subMinutes(14),
            ]
        );

        $article1->companies()->syncWithoutDetaching([$nvidia->id => ['relevance' => 'primary']]);
        $article1->categories()->syncWithoutDetaching([Category::where('slug', 'expansion-capex')->value('id')]);

        // 7. Seed Social Intelligence Trends
        $topic = SocialTopic::updateOrCreate(
            ['slug' => 'eu-semiconductor-sovereignty'],
            [
                'title' => 'European Semiconductor Sovereignty Mandate',
                'industry' => 'Semiconductors',
                'company_id' => $nvidia->id,
            ]
        );

        $trend = SocialTrend::updateOrCreate(
            ['title' => 'Silicon Sovereignty & EU AI Foundry Movement'],
            [
                'topic_id' => $topic->id,
                'status' => 'accelerating',
                'composite_trend_score' => 84.50,
                'velocity_score' => 88.00,
                'credibility_score' => 85.00,
                'business_relevance_score' => 92.00,
                'cross_platform_count' => 3,
                'first_detected_at' => now()->subHours(4),
                'peak_at' => now()->subHour(),
            ]
        );

        SocialTrendMetric::create([
            'trend_id' => $trend->id,
            'total_mentions' => 14200,
            'velocity_per_hour' => 3450.00,
            'sentiment_positive_ratio' => 0.68,
            'sentiment_negative_ratio' => 0.12,
            'recorded_at' => now(),
        ]);

        // 8. Seed AI Providers, Models, Capabilities & Tasks
        $providers = [
            ['name' => 'Google Gemini', 'slug' => 'google-gemini', 'priority' => 1, 'circuit_state' => 'closed', 'rpm' => 120, 'tpm' => 300000, 'default_model' => 'gemini-3.8-flash'],
            ['name' => 'Anthropic Claude', 'slug' => 'anthropic', 'priority' => 2, 'circuit_state' => 'closed', 'rpm' => 100, 'tpm' => 200000, 'default_model' => 'claude-3-5-sonnet-20241022'],
            ['name' => 'OpenAI GPT', 'slug' => 'openai', 'priority' => 3, 'circuit_state' => 'closed', 'rpm' => 100, 'tpm' => 250000, 'default_model' => 'gpt-4o-mini'],
        ];

        $seededProviders = [];
        $seededModels = [];

        foreach ($providers as $provData) {
            $rpm = $provData['rpm'];
            $tpm = $provData['tpm'];
            unset($provData['rpm'], $provData['tpm']);

            $prov = AIProvider::updateOrCreate(['slug' => $provData['slug']], $provData);
            $seededProviders[$prov->slug] = $prov;

            AIProviderQuota::updateOrCreate(
                ['provider_id' => $prov->id],
                ['rpm_limit' => $rpm, 'tpm_limit' => $tpm]
            );

            // Register default initial encrypted credential
            $sampleKey = match ($prov->slug) {
                'google-gemini' => 'AIzaSyA88901234567890abcdefghijklmnopqrst',
                'anthropic' => 'sk-ant-api03-abcdef1234567890abcdef123456-8877',
                'openai' => 'sk-proj-openai1234567890abcdef1234567890-9900',
                default => 'sk-generic-credential-key-9999',
            };
            $fp = hash('sha256', $sampleKey);

            \App\Models\AIProviderCredential::updateOrCreate(
                ['key_fingerprint' => $fp],
                [
                    'provider_id' => $prov->id,
                    'label' => $prov->name . ' Primary Key',
                    'display_name' => $prov->name . ' Production Gateway',
                    'encrypted_api_key' => \Illuminate\Support\Facades\Crypt::encryptString($sampleKey),
                    'account_label' => 'Primary Production Pool',
                    'status' => 'active',
                    'priority' => 1,
                    'routing_eligibility' => true,
                    'is_valid' => true,
                    'daily_request_budget' => 5000,
                    'daily_token_budget' => 5000000,
                    'monthly_spending_budget' => 500.00,
                    'rate_limit_config' => ['rpm' => $rpm, 'tpm' => $tpm],
                    'error_count' => 0,
                ]
            );
        }

        // Models configuration
        $modelsData = [
            // Google
            [
                'provider_slug' => 'google-gemini',
                'model_identifier' => 'gemini-3.8-flash',
                'display_name' => 'Google Gemini 3.8 Flash',
                'cost_per_1k_input_tokens' => 0.00010,
                'cost_per_1k_output_tokens' => 0.00040,
                'context_window' => 1048576,
                'max_output_tokens' => 8192,
                'supports_structured_output' => true,
                'supports_tool_calling' => true,
                'supports_vision' => true,
                'supports_embedding' => false,
            ],
            [
                'provider_slug' => 'google-gemini',
                'model_identifier' => 'gemini-1.5-pro-002',
                'display_name' => 'Google Gemini 1.5 Pro 002',
                'cost_per_1k_input_tokens' => 0.00125,
                'cost_per_1k_output_tokens' => 0.00500,
                'context_window' => 2097152,
                'max_output_tokens' => 8192,
                'supports_structured_output' => true,
                'supports_tool_calling' => true,
                'supports_vision' => true,
                'supports_embedding' => false,
            ],
            // Anthropic
            [
                'provider_slug' => 'anthropic',
                'model_identifier' => 'claude-3-5-sonnet-20241022',
                'display_name' => 'Anthropic Claude 3.5 Sonnet',
                'cost_per_1k_input_tokens' => 0.00300,
                'cost_per_1k_output_tokens' => 0.01500,
                'context_window' => 200000,
                'max_output_tokens' => 8192,
                'supports_structured_output' => true,
                'supports_tool_calling' => true,
                'supports_vision' => true,
                'supports_embedding' => false,
            ],
            [
                'provider_slug' => 'anthropic',
                'model_identifier' => 'claude-3-5-haiku-20241022',
                'display_name' => 'Anthropic Claude 3.5 Haiku',
                'cost_per_1k_input_tokens' => 0.00080,
                'cost_per_1k_output_tokens' => 0.00400,
                'context_window' => 200000,
                'max_output_tokens' => 8192,
                'supports_structured_output' => true,
                'supports_tool_calling' => true,
                'supports_vision' => false,
                'supports_embedding' => false,
            ],
            // OpenAI
            [
                'provider_slug' => 'openai',
                'model_identifier' => 'gpt-4o',
                'display_name' => 'OpenAI GPT-4o',
                'cost_per_1k_input_tokens' => 0.00250,
                'cost_per_1k_output_tokens' => 0.01000,
                'context_window' => 128000,
                'max_output_tokens' => 16384,
                'supports_structured_output' => true,
                'supports_tool_calling' => true,
                'supports_vision' => true,
                'supports_embedding' => false,
            ],
            [
                'provider_slug' => 'openai',
                'model_identifier' => 'gpt-4o-mini',
                'display_name' => 'OpenAI GPT-4o Mini',
                'cost_per_1k_input_tokens' => 0.00015,
                'cost_per_1k_output_tokens' => 0.00060,
                'context_window' => 128000,
                'max_output_tokens' => 16384,
                'supports_structured_output' => true,
                'supports_tool_calling' => true,
                'supports_vision' => true,
                'supports_embedding' => false,
            ],
            [
                'provider_slug' => 'openai',
                'model_identifier' => 'text-embedding-3-small',
                'display_name' => 'OpenAI Text Embedding 3 Small',
                'cost_per_1k_input_tokens' => 0.00002,
                'cost_per_1k_output_tokens' => 0.00000,
                'context_window' => 8191,
                'max_output_tokens' => 0,
                'supports_structured_output' => false,
                'supports_tool_calling' => false,
                'supports_vision' => false,
                'supports_embedding' => true,
            ],
        ];

        foreach ($modelsData as $m) {
            $provSlug = $m['provider_slug'];
            unset($m['provider_slug']);
            $prov = $seededProviders[$provSlug];

            $model = AIModel::updateOrCreate(
                ['provider_id' => $prov->id, 'model_identifier' => $m['model_identifier']],
                $m
            );
            $seededModels[$m['model_identifier']] = $model;
        }

        // 9. Seed Central AI Task Registry (Section 5)
        $tasksData = [
            ['task_type' => 'rss_summarization', 'display_name' => 'RSS Article Summarization', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.2, 'max_tokens' => 800],
            ['task_type' => 'multi_source_synthesis', 'display_name' => 'Multi-Source Story Synthesis', 'pref_prov' => 'anthropic', 'pref_model' => 'claude-3-5-sonnet-20241022', 'temperature' => 0.3, 'max_tokens' => 2000],
            ['task_type' => 'fact_extraction', 'display_name' => 'Fact & Verification Extraction', 'pref_prov' => 'openai', 'pref_model' => 'gpt-4o-mini', 'temperature' => 0.1, 'max_tokens' => 1000],
            ['task_type' => 'story_classification', 'display_name' => 'Story Category & Importance Classification', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.1, 'max_tokens' => 500],
            ['task_type' => 'topic_identification', 'display_name' => 'Taxonomy & Topic Identification', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.2, 'max_tokens' => 500],
            ['task_type' => 'entity_extraction', 'display_name' => 'Company & Executive Entity Recognition', 'pref_prov' => 'openai', 'pref_model' => 'gpt-4o-mini', 'temperature' => 0.1, 'max_tokens' => 800],
            ['task_type' => 'duplicate_detection', 'display_name' => 'Duplicate & Clustering Analysis', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.1, 'max_tokens' => 500],
            ['task_type' => 'similarity_analysis', 'display_name' => 'Story Similarity & Conflict Scoring', 'pref_prov' => 'openai', 'pref_model' => 'gpt-4o-mini', 'temperature' => 0.1, 'max_tokens' => 500],
            ['task_type' => 'headline_generation', 'display_name' => 'Executive Headline Generation', 'pref_prov' => 'anthropic', 'pref_model' => 'claude-3-5-sonnet-20241022', 'temperature' => 0.4, 'max_tokens' => 250],
            ['task_type' => 'seo_generation', 'display_name' => 'SEO Title & Meta Description Generation', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.3, 'max_tokens' => 400],
            ['task_type' => 'editorial_quality', 'display_name' => 'Editorial Quality & Accuracy Assessment', 'pref_prov' => 'anthropic', 'pref_model' => 'claude-3-5-sonnet-20241022', 'temperature' => 0.2, 'max_tokens' => 1500],
            ['task_type' => 'translation', 'display_name' => 'Multilingual Intelligence Translation', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.1, 'max_tokens' => 2000],
            ['task_type' => 'research_report', 'display_name' => 'Deep Research Report Synthesis', 'pref_prov' => 'anthropic', 'pref_model' => 'claude-3-5-sonnet-20241022', 'temperature' => 0.3, 'max_tokens' => 4000],
            ['task_type' => 'premium_intelligence', 'display_name' => '10-Dimension Executive Assessment', 'pref_prov' => 'anthropic', 'pref_model' => 'claude-3-5-sonnet-20241022', 'temperature' => 0.25, 'max_tokens' => 2500],
            ['task_type' => 'embedding_generation', 'display_name' => 'Knowledge Chunk Embedding', 'pref_prov' => 'openai', 'pref_model' => 'text-embedding-3-small', 'temperature' => 0.0, 'max_tokens' => 0],
            ['task_type' => 'search_enhancement', 'display_name' => 'Search Query Semantic Enhancement', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.2, 'max_tokens' => 300],
            ['task_type' => 'rag_query', 'display_name' => 'Grounded Knowledge Retrieval Question Answering', 'pref_prov' => 'anthropic', 'pref_model' => 'claude-3-5-sonnet-20241022', 'temperature' => 0.2, 'max_tokens' => 2000],
            ['task_type' => 'entity_resolution', 'display_name' => 'Intelligence Entity Resolution & Alias Matching', 'pref_prov' => 'openai', 'pref_model' => 'gpt-4o-mini', 'temperature' => 0.1, 'max_tokens' => 800],
            ['task_type' => 'relationship_extraction', 'display_name' => 'Entity Relationship & Edge Extraction', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.1, 'max_tokens' => 1000],
            ['task_type' => 'claim_verification', 'display_name' => '10-Dimension Claim Verification Assistance', 'pref_prov' => 'anthropic', 'pref_model' => 'claude-3-5-sonnet-20241022', 'temperature' => 0.15, 'max_tokens' => 1500],
            ['task_type' => 'historical_event_matching', 'display_name' => 'Historical Event Matching & Sequencing', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.2, 'max_tokens' => 800],
            ['task_type' => 'trend_detection', 'display_name' => 'Intelligence Trend & Signal Analysis', 'pref_prov' => 'google-gemini', 'pref_model' => 'gemini-3.8-flash', 'temperature' => 0.2, 'max_tokens' => 1000],
            ['task_type' => 'deep_research_synthesis', 'display_name' => 'Deep Research Structured Report Synthesis', 'pref_prov' => 'anthropic', 'pref_model' => 'claude-3-5-sonnet-20241022', 'temperature' => 0.25, 'max_tokens' => 4000],
        ];

        foreach ($tasksData as $t) {
            $prov = $seededProviders[$t['pref_prov']] ?? null;
            $mod = $seededModels[$t['pref_model']] ?? null;

            AITaskConfig::updateOrCreate(
                ['task_type' => $t['task_type']],
                [
                    'display_name' => $t['display_name'],
                    'preferred_provider_id' => $prov?->id,
                    'preferred_model_id' => $mod?->id,
                    'fallback_provider_ids' => array_values(array_filter(
                        array_map(fn($p) => $p->id, $seededProviders),
                        fn($id) => $id !== $prov?->id
                    )),
                    'temperature' => $t['temperature'],
                    'max_output_tokens' => $t['max_tokens'],
                    'timeout_seconds' => 30,
                    'retry_policy' => ['max_retries' => 3, 'backoff_factor' => 2],
                    'priority' => 1,
                    'is_active' => true,
                ]
            );
        }

        // 10. Seed Payment Provider Configurations
        PaymentProviderConfig::updateOrCreate(
            ['provider_key' => 'bkash'],
            [
                'display_name' => 'bKash Merchant Pay',
                'is_enabled' => true,
                'is_test_mode' => true,
                'instructions' => "1. Go to your bKash Mobile App or Dial *247#\n2. Select 'Make Payment'\n3. Enter Merchant Account Number: 017XXXXXXXX\n4. Enter the exact Invoice Total as Amount\n5. Enter Invoice Number as Reference\n6. Enter your PIN to confirm\n7. Submit your Transaction ID (TrxID) below for immediate administrative review.",
            ]
        );

        PaymentProviderConfig::updateOrCreate(
            ['provider_key' => 'nagad'],
            [
                'display_name' => 'Nagad Merchant',
                'is_enabled' => true,
                'is_test_mode' => true,
                'instructions' => "1. Go to your Nagad App or Dial *167#\n2. Select 'Payment'\n3. Enter Merchant Number: 018XXXXXXXX\n4. Enter the exact Invoice Total as Amount\n5. Enter Invoice Number as Reference\n6. Submit the 8-character Transaction ID for verification.",
            ]
        );

        PaymentProviderConfig::updateOrCreate(
            ['provider_key' => 'bank_wire'],
            [
                'display_name' => 'Corporate Bank Transfer / Wire',
                'is_enabled' => true,
                'is_test_mode' => true,
                'instructions' => "Account Name: Nexovant Technologies Ltd.\nAccount Number: 102938475610\nBank Name: Standard Chartered Bank / City Bank\nBranch: Corporate Financial District, Dhaka\nRouting / SWIFT: SCBLBDDX\nReference: Please state your Invoice Number in the payment narration.",
            ]
        );

        PaymentProviderConfig::updateOrCreate(
            ['provider_key' => 'stripe'],
            [
                'display_name' => 'Credit / Debit Card (Stripe)',
                'is_enabled' => false,
                'is_test_mode' => true,
                'instructions' => 'Secure international credit card processing via Stripe Checkout.',
            ]
        );

        // 11. Seed Publication Templates & Schedules
        $defaultTemplate = \App\Models\PublicationTemplate::updateOrCreate(
            ['slug' => 'global-executive-briefing'],
            [
                'name' => 'Global Executive Briefing Template',
                'description' => 'Flagship editorial briefing template with dark executive styling, multi-column metrics, and citation footnotes.',
                'page_size' => 'a4',
                'orientation' => 'portrait',
                'brand_config' => [
                    'logo_text' => 'NEXOVANT',
                    'primary_color' => '#0A0E17',
                    'accent_color' => '#00E5FF',
                    'font_family' => 'Helvetica, Arial, sans-serif',
                    'header_text' => 'NEXOVANT INTELLIGENCE BRIEFING',
                    'footer_text' => 'VERIFIED GROUNDED INTELLIGENCE • ALL RIGHTS RESERVED',
                ],
                'section_visibility' => [
                    'cover' => true,
                    'executive_summary' => true,
                    'key_intelligence' => true,
                    'company_intelligence' => true,
                    'technology_intelligence' => true,
                    'trend_intelligence' => true,
                    'historical_timeline' => true,
                    'conflicting_evidence' => true,
                    'strategic_implications' => true,
                    'sources_and_methodology' => true,
                ],
                'confidentiality_label' => 'PREMIUM EXECUTIVE INTELLIGENCE',
                'disclosure_text' => 'This intelligence document was generated from Nexovant audited story clusters, cross-verified source disclosures, and grounded research models. Interpretation is assisted by AI with human editorial oversight.',
                'version' => 1,
                'is_default' => true,
            ]
        );

        \App\Models\PublicationTemplate::updateOrCreate(
            ['slug' => 'deep-research-dossier'],
            [
                'name' => 'Deep Research Dossier Template',
                'description' => 'In-depth research layout for corporate profiles, executive movement dossiers, and patent investigations.',
                'page_size' => 'a4',
                'orientation' => 'portrait',
                'brand_config' => [
                    'logo_text' => 'NEXOVANT RESEARCH',
                    'primary_color' => '#0B132B',
                    'accent_color' => '#48E5C2',
                    'font_family' => 'Helvetica, Arial, sans-serif',
                    'header_text' => 'NEXOVANT DEEP RESEARCH DOSSIER',
                    'footer_text' => 'PROPRIETARY SUBSCRIBER INTELLIGENCE',
                ],
                'section_visibility' => [
                    'cover' => true,
                    'executive_summary' => true,
                    'company_intelligence' => true,
                    'technology_intelligence' => true,
                    'historical_timeline' => true,
                    'sources_and_methodology' => true,
                ],
                'confidentiality_label' => 'STRICTLY CONFIDENTIAL',
                'disclosure_text' => 'Verified corporate disclosures compiled through Nexovant Grounded Knowledge Engine.',
                'version' => 1,
                'is_default' => false,
            ]
        );

        \App\Models\PublicationSchedule::updateOrCreate(
            ['title_pattern' => 'Nexovant Global Tech Intelligence Briefing — {date}'],
            [
                'publication_type' => 'daily_briefing',
                'template_id' => $defaultTemplate->id,
                'frequency' => 'daily',
                'schedule_time' => '06:00',
                'timezone' => 'UTC',
                'scope_config' => [
                    'region' => 'Global',
                    'min_evidence_score' => 70,
                ],
                'require_review' => true,
                'is_enabled' => true,
                'next_run_at' => now()->tomorrow()->setTime(6, 0),
            ]
        );

        // Promotional Coupons
        \App\Models\Coupon::updateOrCreate(
            ['code' => 'NEXO25'],
            [
                'name' => '25% Executive Launch Discount',
                'discount_type' => 'percentage',
                'discount_value' => 25.00,
                'is_active' => true,
                'valid_from' => now()->subDay(),
                'valid_until' => now()->addYear(),
            ]
        );

        \App\Models\Coupon::updateOrCreate(
            ['code' => 'WELCOME50'],
            [
                'name' => '50% First Month Welcome Credit',
                'discount_type' => 'percentage',
                'discount_value' => 50.00,
                'is_active' => true,
                'valid_from' => now()->subDay(),
                'valid_until' => now()->addYear(),
            ]
        );

        // 12. Seed Default Notification Rules (Stage 11)
        $rulesData = [
            [
                'name' => 'Breaking Intelligence Alerts',
                'event_type' => 'intelligence.breaking_news',
                'source_module' => 'intelligence',
                'priority' => 'urgent',
                'channels' => ['in_app', 'email', 'web_push', 'telegram'],
                'template_name' => 'breaking_alert',
                'frequency' => 'realtime',
                'is_active' => true,
            ],
            [
                'name' => 'Watchlist & Entity Monitoring',
                'event_type' => 'watchlist.entity_alert',
                'source_module' => 'workspace',
                'priority' => 'high',
                'channels' => ['in_app', 'email', 'web_push'],
                'template_name' => 'watchlist_alert',
                'frequency' => 'realtime',
                'is_active' => true,
            ],
            [
                'name' => 'Research Dossier Completion',
                'event_type' => 'research.completed',
                'source_module' => 'research',
                'priority' => 'high',
                'channels' => ['in_app', 'email', 'web_push'],
                'template_name' => 'research_completed',
                'frequency' => 'realtime',
                'is_active' => true,
            ],
            [
                'name' => 'Billing Payment Confirmed',
                'event_type' => 'billing.payment_confirmed',
                'source_module' => 'billing',
                'priority' => 'high',
                'channels' => ['in_app', 'email'],
                'template_name' => 'payment_receipt',
                'frequency' => 'realtime',
                'is_active' => true,
            ],
            [
                'name' => 'Billing Payment Failed / Grace Period',
                'event_type' => 'billing.payment_failed',
                'source_module' => 'billing',
                'priority' => 'urgent',
                'channels' => ['in_app', 'email'],
                'template_name' => 'payment_failed',
                'frequency' => 'realtime',
                'is_active' => true,
            ],
            [
                'name' => 'Subscription Trial Expiring',
                'event_type' => 'subscription.trial_expiring',
                'source_module' => 'subscription',
                'priority' => 'medium',
                'channels' => ['in_app', 'email'],
                'template_name' => 'trial_expiring',
                'frequency' => 'realtime',
                'is_active' => true,
            ],
            [
                'name' => 'Organization Team Invitation',
                'event_type' => 'organization.member_invited',
                'source_module' => 'organization',
                'priority' => 'medium',
                'channels' => ['in_app', 'email'],
                'template_name' => 'org_invitation',
                'frequency' => 'realtime',
                'is_active' => true,
            ],
            [
                'name' => 'Platform System Announcement',
                'event_type' => 'system.announcement',
                'source_module' => 'system',
                'priority' => 'high',
                'channels' => ['in_app', 'email', 'web_push'],
                'template_name' => 'system_announcement',
                'frequency' => 'realtime',
                'is_active' => true,
            ],
            [
                'name' => 'Daily Executive Intelligence Summary',
                'event_type' => 'digest.daily_intelligence',
                'source_module' => 'intelligence',
                'priority' => 'low',
                'channels' => ['in_app', 'email'],
                'template_name' => 'daily_digest',
                'frequency' => 'daily_digest',
                'is_active' => true,
            ],
        ];

        foreach ($rulesData as $rule) {
            \App\Models\NotificationRule::updateOrCreate(
                ['event_type' => $rule['event_type']],
                $rule
            );
        }
    }
}
