<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_task_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_type')->unique();
            $table->string('display_name');
            $table->foreignUuid('preferred_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->foreignUuid('preferred_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->json('fallback_provider_ids')->nullable();
            $table->json('fallback_model_ids')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->integer('max_output_tokens')->default(1024);
            $table->integer('timeout_seconds')->default(30);
            $table->json('retry_policy')->nullable();
            $table->decimal('budget_limit_usd', 8, 4)->nullable();
            $table->integer('priority')->default(1);
            $table->string('output_format')->default('json'); // json, text, markdown
            $table->text('system_prompt_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['task_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_task_configs');
    }
};
