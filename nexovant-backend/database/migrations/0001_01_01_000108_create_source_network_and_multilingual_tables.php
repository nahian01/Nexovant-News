<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand news_sources table with global network, onboarding, and reliability metadata
        Schema::table('news_sources', function (Blueprint $table) {
            $table->string('canonical_domain', 150)->nullable()->index()->after('slug');
            $table->string('country_code', 10)->default('US')->index()->after('geographic_region');
            $table->string('continent', 64)->default('Global')->index()->after('country_code');
            $table->json('supported_languages')->nullable()->after('language');
            $table->string('publication_frequency', 32)->default('hourly')->after('fetch_interval_minutes');
            $table->text('api_endpoint')->nullable()->after('feed_url');
            $table->string('auth_type', 32)->default('none')->after('api_endpoint'); // none, bearer_token, api_key, basic_auth
            $table->text('encrypted_credentials')->nullable()->after('auth_type');
            $table->string('editorial_status', 32)->default('active')->index()->after('is_active'); // discovered, pending_review, approved, active, temporarily_suspended, rejected, archived
            $table->text('onboarding_notes')->nullable()->after('editorial_status');
            $table->text('attribution_requirements')->nullable()->after('onboarding_notes');
            $table->text('terms_of_use_url')->nullable()->after('attribution_requirements');
            $table->json('reliability_profile')->nullable()->after('credibility_score');
            $table->timestamp('last_health_check_at')->nullable()->after('health_status');
            $table->integer('last_response_time_ms')->nullable()->after('last_health_check_at');
            $table->integer('last_http_status')->nullable()->after('last_response_time_ms');
            $table->foreignUuid('organization_id')->nullable()->after('total_items_ingested')->constrained('organizations')->nullOnDelete();

            $table->index(['source_type', 'editorial_status']);
            $table->index(['organization_id', 'editorial_status']);
        });

        // 2. Expand articles with multilingual language tracking
        Schema::table('articles', function (Blueprint $table) {
            $table->string('original_language', 12)->default('en')->index()->after('verification_status');
            $table->string('detected_language', 12)->default('en')->index()->after('original_language');
        });

        // 3. Create article_translations table
        Schema::create('article_translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('original_language', 12)->index();
            $table->string('detected_language', 12);
            $table->string('target_language', 12)->index();
            $table->string('translated_title');
            $table->text('translated_content');
            $table->text('translated_summary')->nullable();
            $table->string('translation_provider', 64)->default('nexovant_ai_router');
            $table->string('translation_model', 64)->default('gemini-1.5-pro');
            $table->decimal('quality_confidence_score', 4, 3)->default(0.950);
            $table->string('status', 32)->default('completed')->index(); // pending, completed, failed
            $table->timestamp('translated_at')->index();
            $table->timestamps();

            $table->unique(['article_id', 'target_language']);
            $table->index(['target_language', 'status']);
        });

        // 4. Create source_health_logs table
        Schema::create('source_health_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('news_sources')->cascadeOnDelete();
            $table->string('check_type', 32)->default('http_ping'); // http_ping, feed_parse, api_fetch, auth_check
            $table->string('status', 32)->index(); // healthy, degraded, unavailable, auth_required, rate_limited, format_changed, suspended
            $table->integer('http_status')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            $table->index(['source_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_health_logs');
        Schema::dropIfExists('article_translations');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['original_language', 'detected_language']);
        });

        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn([
                'canonical_domain',
                'country_code',
                'continent',
                'supported_languages',
                'publication_frequency',
                'api_endpoint',
                'auth_type',
                'encrypted_credentials',
                'editorial_status',
                'onboarding_notes',
                'attribution_requirements',
                'terms_of_use_url',
                'reliability_profile',
                'last_health_check_at',
                'last_response_time_ms',
                'last_http_status',
                'organization_id',
            ]);
        });
    }
};
