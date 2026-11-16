<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Saved Searches & Automated Monitoring
        if (!Schema::hasTable('subscriber_saved_searches')) {
            Schema::create('subscriber_saved_searches', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('query');
                $table->json('filters')->nullable();
                $table->string('alert_frequency', 20)->default('daily_digest'); // immediate, daily_digest, weekly_digest, disabled
                $table->json('delivery_channels')->nullable(); // ['in_app', 'email', 'telegram']
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_executed_at')->nullable();
                $table->timestamp('last_matched_at')->nullable();
                $table->integer('match_count')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
                $table->index(['organization_id', 'is_active']);
            });
        }

        // 2. Expand subscriber_collections with organization_id and visibility
        if (Schema::hasTable('subscriber_collections')) {
            Schema::table('subscriber_collections', function (Blueprint $table) {
                if (!Schema::hasColumn('subscriber_collections', 'organization_id')) {
                    $table->foreignUuid('organization_id')->nullable()->after('user_id')->constrained('organizations')->nullOnDelete();
                }
                if (!Schema::hasColumn('subscriber_collections', 'visibility')) {
                    $table->string('visibility', 20)->default('private')->after('color'); // private, organization, shared
                    $table->index(['organization_id', 'visibility']);
                }
            });
        }

        // 3. Grounded Research Briefs
        if (!Schema::hasTable('research_briefs')) {
            Schema::create('research_briefs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->foreignUuid('collection_id')->nullable()->constrained('subscriber_collections')->nullOnDelete();
                $table->string('title');
                $table->text('research_question');
                $table->text('executive_summary');
                $table->json('structured_sections'); // 13 configurable sections
                $table->json('citations'); // Grounded verified citations
                $table->json('conflicting_evidence')->nullable(); // Contradictory reports
                $table->decimal('confidence_score', 3, 2)->default(0.85);
                $table->string('uncertainty_rating', 20)->default('low'); // low, moderate, high
                $table->boolean('is_shared_with_org')->default(false);
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['organization_id', 'is_shared_with_org']);
            });
        }

        // 4. Search Analytics Logs
        if (!Schema::hasTable('search_analytics_logs')) {
            Schema::create('search_analytics_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->string('query');
                $table->string('search_category', 32)->default('all');
                $table->json('filters')->nullable();
                $table->integer('result_count')->default(0);
                $table->boolean('is_empty')->default(false);
                $table->string('retrieval_method', 32)->default('keyword'); // keyword, semantic, hybrid
                $table->integer('duration_ms')->default(0);
                $table->string('clicked_result_id')->nullable();
                $table->string('clicked_result_type', 32)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['created_at', 'search_category']);
                $table->index(['query']);
                $table->index(['is_empty', 'created_at']);
            });
        }

        // 5. Search Relevance Feedbacks
        if (!Schema::hasTable('search_relevance_feedbacks')) {
            Schema::create('search_relevance_feedbacks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('query');
                $table->string('result_id');
                $table->string('result_type', 32);
                $table->string('feedback_rating', 32); // relevant, not_relevant, incorrect_result, missing_information
                $table->text('comments')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['query', 'feedback_rating']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_relevance_feedbacks');
        Schema::dropIfExists('search_analytics_logs');
        Schema::dropIfExists('research_briefs');

        if (Schema::hasTable('subscriber_collections')) {
            Schema::table('subscriber_collections', function (Blueprint $table) {
                if (Schema::hasColumn('subscriber_collections', 'organization_id')) {
                    $table->dropForeign(['organization_id']);
                    $table->dropColumn('organization_id');
                }
                if (Schema::hasColumn('subscriber_collections', 'visibility')) {
                    $table->dropColumn('visibility');
                }
            });
        }

        Schema::dropIfExists('subscriber_saved_searches');
    }
};
