<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Automation Execution Logs Table
        Schema::create('automation_execution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('command_name', 128)->index();
            $table->string('frequency', 64)->default('manual')->index();
            $table->string('status', 32)->default('running')->index(); // running, success, failed, skipped
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->integer('processed_count')->default(0);
            $table->integer('failures_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->json('metrics')->nullable();
            $table->text('error_message')->nullable();
            $table->string('hostname', 128)->nullable();
            $table->timestamps();

            $table->index(['command_name', 'status', 'started_at']);
        });

        // 2. Harden Anomaly Observations
        Schema::table('anomaly_observations', function (Blueprint $table) {
            $table->string('coverage_vs_reality_type', 48)->default('media_coverage_surge')->nullable()->after('deviation_score');
            $table->boolean('is_suppressed')->default(false)->after('coverage_vs_reality_type')->index();
        });

        // 3. Harden Trend Forecasts with Backtesting & Holdout Evaluations
        Schema::table('trend_forecasts', function (Blueprint $table) {
            $table->json('backtesting_metrics')->nullable()->after('evaluation_metrics');
            $table->json('holdout_evaluation')->nullable()->after('backtesting_metrics');
        });
    }

    public function down(): void
    {
        Schema::table('trend_forecasts', function (Blueprint $table) {
            $table->dropColumn(['backtesting_metrics', 'holdout_evaluation']);
        });

        Schema::table('anomaly_observations', function (Blueprint $table) {
            $table->dropColumn(['coverage_vs_reality_type', 'is_suppressed']);
        });

        Schema::dropIfExists('automation_execution_logs');
    }
};
