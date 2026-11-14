<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform'); // x, reddit, youtube, linkedin
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('rate_limit_policy')->nullable();
            $table->timestamps();
        });

        Schema::create('social_topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('industry')->nullable();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUuid('executive_id')->nullable()->constrained('executives')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('social_trends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('topic_id')->constrained('social_topics')->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('emerging'); // emerging, accelerating, trending, viral, declining, confirmed_development, unverified_discussion, disputed_claim
            $table->decimal('composite_trend_score', 5, 2)->default(0); // 0.00 to 100.00
            $table->decimal('velocity_score', 5, 2)->default(0);
            $table->decimal('credibility_score', 5, 2)->default(0);
            $table->decimal('business_relevance_score', 5, 2)->default(0);
            $table->integer('cross_platform_count')->default(1);
            $table->timestamp('first_detected_at');
            $table->timestamp('peak_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'composite_trend_score']);
        });

        Schema::create('social_trend_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trend_id')->constrained('social_trends')->cascadeOnDelete();
            $table->integer('total_mentions')->default(0);
            $table->decimal('velocity_per_hour', 8, 2)->default(0);
            $table->decimal('sentiment_positive_ratio', 3, 2)->default(0.50);
            $table->decimal('sentiment_negative_ratio', 3, 2)->default(0.50);
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['trend_id', 'recorded_at']);
        });

        Schema::create('social_trend_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trend_id')->constrained('social_trends')->cascadeOnDelete();
            $table->string('platform');
            $table->text('sample_url')->nullable();
            $table->text('sample_text')->nullable();
            $table->integer('engagement_volume')->default(0);
            $table->timestamps();
        });

        Schema::create('social_verification_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trend_id')->constrained('social_trends')->cascadeOnDelete();
            $table->foreignUuid('related_article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('verification_verdict'); // confirmed, disputed, unverified_rumor
            $table->text('audited_reason');
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_verification_records');
        Schema::dropIfExists('social_trend_sources');
        Schema::dropIfExists('social_trend_metrics');
        Schema::dropIfExists('social_trends');
        Schema::dropIfExists('social_topics');
        Schema::dropIfExists('social_sources');
    }
};
