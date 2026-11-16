<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Intelligence Signals Table
        Schema::create('intelligence_signals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('signal_category', 64)->index(); // emerging_trend, significant_development, policy_change, market_shift, technology_breakthrough, supply_chain_disruption, regulatory_development, geopolitical_development, corporate_strategy_change, scientific_discovery, conflicting_information, information_gap
            $table->string('severity', 32)->default('informational')->index(); // informational, low, medium, high, critical
            $table->string('status', 32)->default('detected')->index(); // detected, under_review, confirmed, dismissed, escalated, resolved
            $table->json('related_entities')->nullable();
            $table->json('related_topics')->nullable();
            $table->string('geographic_scope', 64)->nullable()->index();
            $table->timestamp('first_observed_at')->nullable()->index();
            $table->timestamp('last_updated_at')->nullable()->index();
            $table->integer('evidence_count')->default(1);
            $table->integer('independent_sources_count')->default(1);
            $table->string('verification_status', 32)->default('single_source'); // unverified, single_source, corroborated, verified, disputed
            $table->string('detection_methodology', 64)->default('rule_match');
            $table->string('review_status', 32)->default('pending')->index(); // pending, accepted, rejected, escalated, reassessed
            $table->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('analysis_history')->nullable();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['signal_category', 'status']);
            $table->index(['organization_id', 'status']);
        });

        // 2. Signal Evidence Table
        Schema::create('signal_evidences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('signal_id')->constrained('intelligence_signals')->cascadeOnDelete();
            $table->foreignUuid('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignUuid('source_id')->nullable()->constrained('news_sources')->nullOnDelete();
            $table->string('evidence_type', 32)->default('supporting'); // supporting, contradictory, refining
            $table->text('excerpt');
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->boolean('is_independent')->default(true);
            $table->decimal('credibility_score', 5, 2)->default(80.00);
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['signal_id', 'evidence_type']);
        });

        // 3. Anomaly Observations Table
        Schema::create('anomaly_observations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('signal_id')->nullable()->constrained('intelligence_signals')->nullOnDelete();
            $table->string('subject_type', 32)->index(); // topic, entity, technology, geography, industry
            $table->string('subject_id', 64)->nullable()->index();
            $table->string('subject_name')->index();
            $table->string('indicator_name', 64)->index(); // reporting_frequency, topic_distribution, contradiction_frequency, entity_cooccurrence, velocity_spike
            $table->integer('baseline_period_days')->default(30);
            $table->integer('observation_period_days')->default(7);
            $table->decimal('observed_value', 12, 4);
            $table->decimal('expected_value', 12, 4);
            $table->decimal('deviation_score', 12, 4); // z-score or delta percentage
            $table->string('detection_method', 64)->default('moving_average_zscore');
            $table->string('data_sufficiency', 32)->default('sufficient'); // insufficient, minimal, sufficient, robust
            $table->integer('sample_count')->default(0);
            $table->integer('min_sample_threshold')->default(5);
            $table->text('limitations')->nullable();
            $table->timestamp('detected_at')->index();
            $table->timestamps();

            $table->index(['subject_type', 'detected_at']);
        });

        // 4. Correlated Events Table (Strict Correlation != Causation)
        Schema::create('correlated_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('primary_event_title');
            $table->string('primary_event_id', 64)->nullable()->index();
            $table->string('correlated_event_title');
            $table->string('correlated_event_id', 64)->nullable()->index();
            $table->string('correlation_type', 64)->index(); // temporal, entity_shared, topic_cascade, supply_chain, regulatory_impact
            $table->decimal('correlation_strength', 3, 2)->default(0.70);
            $table->boolean('is_causation_claimed')->default(false); // MUST REMAIN FALSE
            $table->text('evidence_summary')->nullable();
            $table->json('supporting_sources')->nullable();
            $table->text('alternative_explanations')->nullable();
            $table->text('unresolved_questions')->nullable();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->timestamps();

            $table->index(['primary_event_id', 'correlation_type']);
        });

        // 5. Early Warnings Table
        Schema::create('early_warnings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('signal_id')->constrained('intelligence_signals')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('warning_level', 32)->default('watch')->index(); // informational, watch, elevated, critical
            $table->string('status', 32)->default('newly_detected')->index(); // newly_detected, under_analysis, awaiting_analyst_review, confirmed, escalated, monitoring, resolved, dismissed, reopened
            $table->json('factor_breakdown'); // independent_sources, credibility, verification_status, rate_of_change, persistence, geographic_relevance, baseline_deviation, potential_impact
            $table->json('explanation'); // 9 answers: what_changed, when_first_observed, supporting_sources, independent_sources_count, what_makes_it_unusual, what_is_verified, what_remains_uncertain, why_considered_important, additional_info_needed
            $table->json('recommended_watch_points')->nullable();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->timestamps();

            $table->index(['warning_level', 'status']);
            $table->index(['organization_id', 'warning_level']);
        });

        // 6. Early Warning Histories Table
        Schema::create('early_warning_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warning_id')->constrained('early_warnings')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['warning_id', 'created_at']);
        });

        // 7. Predictive Trend Forecasts Table
        Schema::create('trend_forecasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trend_id')->nullable()->constrained('intelligence_trends')->nullOnDelete();
            $table->string('subject')->index();
            $table->string('forecast_category', 64)->index(); // technology_adoption, capex_expansion, supply_chain_velocity, regulatory_timeline
            $table->string('forecast_horizon', 32)->default('90d'); // 30d, 90d, 180d, 1y
            $table->integer('historical_window_days')->default(90);
            $table->string('methodology', 64)->default('linear_trend_extrapolation');
            $table->json('historical_data_points');
            $table->json('forecasted_values');
            $table->json('prediction_intervals');
            $table->json('assumptions')->nullable();
            $table->text('limitations')->nullable();
            $table->boolean('is_supported')->default(true);
            $table->string('data_sufficiency_status', 64)->default('sufficient'); // sufficient, insufficient_sample_size, high_uncertainty
            $table->json('evaluation_metrics')->nullable(); // MAE, RMSE, MAPE
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject', 'forecast_horizon']);
        });

        // 8. Intelligence Impact Assessments Table
        Schema::create('intelligence_impact_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('signal_id')->constrained('intelligence_signals')->cascadeOnDelete();
            $table->foreignUuid('early_warning_id')->nullable()->constrained('early_warnings')->nullOnDelete();
            $table->string('dimension', 40)->index(); // economic, technological, regulatory, industrial, environmental, geographic, organizational, public_policy
            $table->string('impact_severity', 32)->default('moderate'); // low, moderate, significant, severe
            $table->string('time_horizon', 32)->default('short_term_30d'); // immediate, short_term_30d, medium_term_6m, long_term_1y_plus
            $table->json('affected_entities')->nullable();
            $table->json('affected_sectors')->nullable();
            $table->text('supporting_evidence')->nullable();
            $table->text('potential_consequences')->nullable();
            $table->text('alternative_interpretations')->nullable();
            $table->text('uncertainty_and_limitations')->nullable();
            $table->timestamps();

            $table->index(['signal_id', 'dimension']);
        });

        // 9. Analyst Reviews Table
        Schema::create('analyst_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reviewable_type', 64)->index();
            $table->uuid('reviewable_id')->index();
            $table->foreignUuid('analyst_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 32)->index(); // accepted, dismissed, escalated, reassessed, marked_false_positive
            $table->text('feedback_notes')->nullable();
            $table->json('added_evidence')->nullable();
            $table->json('suggested_threshold_adjustments')->nullable();
            $table->boolean('is_applied_to_rules')->default(false);
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id']);
            $table->index(['analyst_user_id', 'decision']);
        });

        // 10. Automated Intelligence Reports Table
        Schema::create('automated_intelligence_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('report_type', 40)->index(); // daily_summary, weekly_summary, emerging_trend, entity_development, industry_monitoring, early_warning_dossier
            $table->string('title');
            $table->string('slug')->unique();
            $table->timestamp('reporting_period_start')->nullable();
            $table->timestamp('reporting_period_end')->nullable();
            $table->text('executive_summary');
            $table->json('structured_content'); // 12 discrete sections
            $table->json('citations')->nullable();
            $table->foreignUuid('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'is_published']);
            $table->index(['organization_id', 'report_type']);
        });

        // 11. Intelligence Detection Rules Table (Admin Configurable)
        Schema::create('intelligence_detection_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('rule_type', 40)->index(); // volume_velocity, source_corroboration, anomaly_threshold, early_warning_escalation
            $table->string('category', 64)->index();
            $table->json('thresholds');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->json('audit_trail')->nullable();
            $table->timestamps();

            $table->index(['rule_type', 'is_active']);
            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_detection_rules');
        Schema::dropIfExists('automated_intelligence_reports');
        Schema::dropIfExists('analyst_reviews');
        Schema::dropIfExists('intelligence_impact_assessments');
        Schema::dropIfExists('trend_forecasts');
        Schema::dropIfExists('early_warning_histories');
        Schema::dropIfExists('early_warnings');
        Schema::dropIfExists('correlated_events');
        Schema::dropIfExists('anomaly_observations');
        Schema::dropIfExists('signal_evidences');
        Schema::dropIfExists('intelligence_signals');
    }
};
