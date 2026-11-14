<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            $table->string('health_status')->default('healthy'); // healthy, degraded, failed, paused
            $table->text('last_error_message')->nullable();
            $table->integer('fetch_interval_minutes')->default(15);
            $table->integer('consecutive_failures')->default(0);
            $table->integer('total_items_ingested')->default(0);
        });

        Schema::create('pipeline_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->nullable()->constrained('news_sources')->nullOnDelete();
            $table->foreignUuid('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('cluster_id')->nullable()->index();
            $table->string('stage'); // 1. Source Discovery, 2. Extraction, 3. Normalization, 4. Classification, 5. Entity Recognition, 6. Duplicate Detection, 7. Story Clustering, 8. Verification, 9. Summarization, 10. Article Generation, 11. Image Selection, 12. Knowledge Indexing, 13. Editorial Validation, 14. Publishing
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->string('item_title')->nullable();
            $table->string('item_url')->nullable();
            $table->string('content_hash', 64)->nullable()->index();
            $table->foreignUuid('ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->foreignUuid('ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->integer('tokens_consumed')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0.000000);
            $table->decimal('duration_seconds', 8, 2)->default(0.00);
            $table->json('extracted_facts')->nullable();
            $table->json('conflicting_reports')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['stage', 'status']);
            $table->index(['source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_jobs');

        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn([
                'health_status',
                'last_error_message',
                'fetch_interval_minutes',
                'consecutive_failures',
                'total_items_ingested',
            ]);
        });
    }
};
