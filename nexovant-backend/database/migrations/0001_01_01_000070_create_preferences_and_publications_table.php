<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('preferred_industries')->nullable();
            $table->string('digest_frequency')->default('daily'); // daily, weekly, real_time
            $table->boolean('receive_breaking_alerts')->default(true);
            $table->boolean('receive_pdf_briefings')->default(true);
            $table->timestamps();
        });

        Schema::create('followed_companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->boolean('notify_on_events')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'company_id']);
        });

        Schema::create('followed_executives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('executive_id')->constrained('executives')->cascadeOnDelete();
            $table->boolean('notify_on_movement')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'executive_id']);
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('publication_type'); // daily_briefing, weekly_report, company_intelligence, custom
            $table->string('title');
            $table->date('publication_date');
            $table->string('file_path'); // Cloudflare R2 object path
            $table->integer('file_size_bytes')->default(0);
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['publication_type', 'publication_date']);
        });

        Schema::create('agent_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_type'); // news_ingestion, social_trend, rag_indexer, pdf_generator
            $table->string('status')->default('running'); // running, completed, failed
            $table->json('metrics')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
        Schema::dropIfExists('publications');
        Schema::dropIfExists('followed_executives');
        Schema::dropIfExists('followed_companies');
        Schema::dropIfExists('user_preferences');
    }
};
