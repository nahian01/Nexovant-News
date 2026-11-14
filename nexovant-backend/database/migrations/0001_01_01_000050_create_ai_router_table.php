<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // anthropic, openai, google, groq, mistral, deepseek
            $table->string('slug')->unique();
            $table->string('api_endpoint')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1); // 1 = highest
            $table->string('circuit_state')->default('closed'); // closed, half_open, open
            $table->integer('consecutive_failures')->default(0);
            $table->timestamp('circuit_tripped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_provider_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('label')->default('Default Key');
            $table->text('encrypted_api_key');
            $table->boolean('is_valid')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_identifier'); // e.g. claude-3-5-sonnet-20241022, gpt-4o, gemini-2.0-flash
            $table->string('display_name');
            $table->decimal('cost_per_1k_input_tokens', 8, 5)->default(0.0015);
            $table->decimal('cost_per_1k_output_tokens', 8, 5)->default(0.0060);
            $table->integer('context_window')->default(128000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider_id', 'model_identifier']);
        });

        Schema::create('ai_model_capabilities', function (Blueprint $table) {
            $table->foreignUuid('model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->string('capability'); // classification, summarization, entity_extraction, impact_analysis, complex_reasoning, embeddings, rag
            $table->primary(['model_id', 'capability']);
        });

        Schema::create('ai_provider_quotas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->integer('rpm_limit')->default(60);
            $table->integer('tpm_limit')->default(100000);
            $table->integer('daily_request_limit')->nullable();
            $table->integer('monthly_token_limit')->nullable();
            $table->integer('current_requests_this_minute')->default(0);
            $table->integer('current_tokens_this_minute')->default(0);
            $table->integer('current_requests_today')->default(0);
            $table->bigInteger('current_tokens_this_month')->default(0);
            $table->timestamp('last_minute_reset_at')->useCurrent();
            $table->timestamp('last_daily_reset_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('ai_routing_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_type'); // e.g. article_summarization, entity_extraction, rag_query
            $table->foreignUuid('selected_provider_id')->constrained('ai_providers')->restrictOnDelete();
            $table->foreignUuid('selected_model_id')->constrained('ai_models')->restrictOnDelete();
            $table->string('fallback_from_provider_id')->nullable();
            $table->string('fallback_reason')->nullable(); // rate_limited, circuit_open, timeout
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 8, 6)->default(0);
            $table->integer('latency_ms')->default(0);
            $table->string('status')->default('success'); // success, failed, fallback_triggered
            $table->text('error_details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['task_type', 'created_at']);
            $table->index(['selected_provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_routing_logs');
        Schema::dropIfExists('ai_provider_quotas');
        Schema::dropIfExists('ai_model_capabilities');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_provider_credentials');
        Schema::dropIfExists('ai_providers');
    }
};
