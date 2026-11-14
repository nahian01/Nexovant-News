<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('news_sources')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('canonical_url');
            $table->longText('content_raw');
            $table->longText('content_normalized')->nullable();
            $table->string('content_hash', 64)->unique(); // SHA-256 deduplication
            $table->timestamp('published_at');
            $table->string('verification_status')->default('developing'); // confirmed, reported_reliable, developing, disputed, unverified, retracted
            $table->decimal('importance_score', 4, 2)->default(5.00); // 0.00 to 10.00
            $table->boolean('is_breaking')->default(false);
            $table->boolean('is_editorial_approved')->default(false);
            $table->timestamps();

            $table->index(['published_at', 'importance_score']);
            $table->index(['verification_status', 'is_editorial_approved']);
        });

        Schema::create('article_categories', function (Blueprint $table) {
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['article_id', 'category_id']);
        });

        Schema::create('article_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->text('executive_summary');
            $table->json('key_takeaways')->nullable();
            $table->string('model_version');
            $table->timestamps();
        });

        Schema::create('article_companies', function (Blueprint $table) {
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('relevance')->default('primary'); // primary, secondary, mentioned
            $table->primary(['article_id', 'company_id']);
        });

        Schema::create('article_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->text('what_happened');
            $table->text('when_happened');
            $table->text('why_happened')->nullable();
            $table->text('business_significance');
            $table->text('who_affected')->nullable();
            $table->text('potential_consequences')->nullable();
            $table->text('what_to_monitor_next');
            $table->decimal('confidence_score', 3, 2)->default(0.90);
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('event_type'); // acquisition, ceo_change, restructuring, crisis, expansion
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('status')->default('active'); // active, resolved, archived
            $table->text('overview')->nullable();
            $table->timestamps();
        });

        Schema::create('event_articles', function (Blueprint $table) {
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->primary(['event_id', 'article_id']);
        });

        Schema::create('event_timeline', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->timestamp('occurred_at');
            $table->string('title');
            $table->text('description');
            $table->foreignUuid('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_timeline');
        Schema::dropIfExists('event_articles');
        Schema::dropIfExists('events');
        Schema::dropIfExists('article_assessments');
        Schema::dropIfExists('article_companies');
        Schema::dropIfExists('article_summaries');
        Schema::dropIfExists('article_categories');
        Schema::dropIfExists('articles');
    }
};
