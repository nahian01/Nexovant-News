<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->decimal('provider_budget_monthly_usd', 10, 4)->nullable();
            $table->decimal('current_monthly_spend_usd', 10, 4)->default(0.0000);
            $table->string('default_routing_strategy')->default('priority'); // priority, round_robin, least_utilized, lowest_cost, health
            $table->string('default_model')->nullable();
        });

        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            $table->string('display_name')->nullable();
            $table->string('key_fingerprint', 64)->nullable()->index();
            $table->string('account_label')->nullable();
            $table->json('allowed_models')->nullable();
            $table->string('status')->default('active'); // active, inactive, suspended, cooldown, rate_limited
            $table->integer('priority')->default(1);
            $table->boolean('routing_eligibility')->default(true);
            $table->json('rate_limit_config')->nullable();
            $table->integer('daily_request_budget')->nullable();
            $table->integer('daily_token_budget')->nullable();
            $table->decimal('monthly_spending_budget', 10, 4)->nullable();
            $table->integer('current_daily_requests')->default(0);
            $table->integer('current_daily_tokens')->default(0);
            $table->decimal('current_monthly_spend_usd', 10, 4)->default(0.0000);
            $table->timestamp('last_successful_request_at')->nullable();
            $table->timestamp('last_failed_request_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->integer('error_count')->default(0);
            $table->timestamp('cooldown_expires_at')->nullable();
            $table->text('last_error_reason')->nullable();
        });

        Schema::table('ai_models', function (Blueprint $table) {
            $table->boolean('supports_structured_output')->default(true);
            $table->boolean('supports_tool_calling')->default(true);
            $table->boolean('supports_vision')->default(false);
            $table->boolean('supports_embedding')->default(false);
            $table->integer('max_output_tokens')->default(4096);
        });

        Schema::table('ai_routing_logs', function (Blueprint $table) {
            $table->foreignUuid('credential_id')->nullable()->constrained('ai_provider_credentials')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module')->default('core');
            $table->integer('total_tokens')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->integer('retry_count')->default(0);
            $table->boolean('fallback_attempted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ai_routing_logs', function (Blueprint $table) {
            $table->dropForeign(['credential_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'credential_id',
                'user_id',
                'module',
                'total_tokens',
                'currency',
                'retry_count',
                'fallback_attempted',
            ]);
        });

        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn([
                'supports_structured_output',
                'supports_tool_calling',
                'supports_vision',
                'supports_embedding',
                'max_output_tokens',
            ]);
        });

        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'key_fingerprint',
                'account_label',
                'allowed_models',
                'status',
                'priority',
                'routing_eligibility',
                'rate_limit_config',
                'daily_request_budget',
                'daily_token_budget',
                'monthly_spending_budget',
                'current_daily_requests',
                'current_daily_tokens',
                'current_monthly_spend_usd',
                'last_successful_request_at',
                'last_failed_request_at',
                'last_health_check_at',
                'error_count',
                'cooldown_expires_at',
                'last_error_reason',
            ]);
        });

        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn([
                'provider_budget_monthly_usd',
                'current_monthly_spend_usd',
                'default_routing_strategy',
                'default_model',
            ]);
        });
    }
};
