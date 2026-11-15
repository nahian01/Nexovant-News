<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand news_sources with detailed ingestion metadata
        Schema::table('news_sources', function (Blueprint $table) {
            $table->string('publisher_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('publisher_name');
            $table->string('website_url')->nullable()->after('url');
            $table->string('source_category')->default('technology')->after('source_type');
            $table->string('geographic_region', 10)->default('GLOBAL')->after('source_category');
            $table->string('language', 10)->default('en')->after('geographic_region');
            $table->string('trust_tier')->default('reputable_secondary')->after('credibility_score'); // established_primary, official_announcement, reputable_secondary, specialized_industry, unverified
            $table->timestamp('last_successful_fetch_at')->nullable()->after('last_polled_at');
            $table->timestamp('last_attempted_fetch_at')->nullable()->after('last_successful_fetch_at');
            $table->integer('max_items_per_fetch')->default(25)->after('rate_limit_per_minute');
            $table->integer('timeout_seconds')->default(15)->after('max_items_per_fetch');
            $table->string('etag')->nullable()->after('timeout_seconds');
            $table->string('last_modified_header')->nullable()->after('etag');
            $table->json('source_config')->nullable()->after('last_modified_header');
        });

        // 2. Create story_clusters table
        Schema::create('story_clusters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->string('status')->default('discovered'); // discovered, clustered, synthesizing, synthesized, published, archived
            $table->uuid('lead_story_id')->nullable()->index();
            $table->foreignUuid('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->decimal('confidence_score', 3, 2)->default(0.85);
            $table->integer('story_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('entities')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // 3. Create discovered_stories table
        Schema::create('discovered_stories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('news_sources')->cascadeOnDelete();
            $table->foreignUuid('cluster_id')->nullable()->constrained('story_clusters')->nullOnDelete();
            $table->string('title');
            $table->string('normalized_title')->index();
            $table->text('url');
            $table->text('canonical_url');
            $table->string('author')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('summary')->nullable();
            $table->longText('content_raw')->nullable();
            $table->longText('content_normalized')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('language', 10)->default('en');
            $table->string('content_hash', 64)->unique();
            $table->string('status')->default('discovered'); // discovered, clustered, synthesized, duplicate, excluded, failed
            $table->foreignUuid('duplicate_of_id')->nullable()->constrained('discovered_stories')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'published_at']);
            $table->index(['status', 'created_at']);
        });

        // 4. Create cluster_stories pivot table
        Schema::create('cluster_stories', function (Blueprint $table) {
            $table->foreignUuid('cluster_id')->constrained('story_clusters')->cascadeOnDelete();
            $table->foreignUuid('story_id')->constrained('discovered_stories')->cascadeOnDelete();
            $table->decimal('similarity_score', 4, 3)->default(1.000);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->primary(['cluster_id', 'story_id']);
        });

        // 5. Create extracted_facts table
        Schema::create('extracted_facts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cluster_id')->nullable()->constrained('story_clusters')->cascadeOnDelete();
            $table->foreignUuid('story_id')->nullable()->constrained('discovered_stories')->cascadeOnDelete();
            $table->foreignUuid('source_id')->constrained('news_sources')->cascadeOnDelete();
            $table->text('claim');
            $table->string('entity')->nullable()->index();
            $table->decimal('confidence_score', 3, 2)->default(0.85);
            $table->string('verification_status')->default('supported_single'); // supported_multiple, supported_single, conflicting, unverified, contradicted
            $table->text('evidence_excerpt')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('editorial_notes')->nullable();
            $table->foreignUuid('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['cluster_id', 'verification_status']);
        });

        // 6. Create article_sources pivot table for multi-source provenance
        Schema::create('article_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignUuid('story_id')->nullable()->constrained('discovered_stories')->nullOnDelete();
            $table->foreignUuid('source_id')->constrained('news_sources')->cascadeOnDelete();
            $table->string('publisher_name')->nullable();
            $table->text('source_url');
            $table->timestamp('source_published_at')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_sources');
        Schema::dropIfExists('extracted_facts');
        Schema::dropIfExists('cluster_stories');
        Schema::dropIfExists('discovered_stories');
        Schema::dropIfExists('story_clusters');

        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn([
                'publisher_name',
                'description',
                'website_url',
                'source_category',
                'geographic_region',
                'language',
                'trust_tier',
                'last_successful_fetch_at',
                'last_attempted_fetch_at',
                'max_items_per_fetch',
                'timeout_seconds',
                'etag',
                'last_modified_header',
                'source_config',
            ]);
        });
    }
};
