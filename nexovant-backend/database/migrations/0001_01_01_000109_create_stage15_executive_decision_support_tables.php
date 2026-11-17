<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Executive Intelligence Briefings
        Schema::create('executive_briefings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->index();
            $table->string('period_type', 32)->default('daily')->index(); // daily, weekly, monthly, custom
            $table->timestamp('coverage_start')->nullable();
            $table->timestamp('coverage_end')->nullable();
            $table->string('industry_scope', 64)->nullable()->index();
            $table->string('geographic_scope', 64)->nullable()->index();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('generated')->index(); // draft, generated, reviewed, approved, published
            $table->string('review_status', 32)->default('pending')->index(); // pending, approved, rejected
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('executive_summary');
            $table->json('what_happened');
            $table->json('why_it_matters');
            $table->json('what_has_changed');
            $table->json('what_could_happen_next');
            $table->json('executive_considerations');
            $table->json('evidence_and_sources');
            $table->json('citations')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['period_type', 'coverage_start']);
        });

        // 2. Executive Briefing Versions
        Schema::create('executive_briefing_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('briefing_id')->constrained('executive_briefings')->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('title');
            $table->json('sections');
            $table->json('citations')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary')->nullable();
            $table->timestamps();

            $table->index(['briefing_id', 'version_number']);
        });

        // 3. Strategic Impact Assessments
        Schema::create('strategic_impact_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->index();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('signal_id')->nullable()->constrained('intelligence_signals')->nullOnDelete();
            $table->foreignUuid('early_warning_id')->nullable()->constrained('early_warnings')->nullOnDelete();
            $table->json('affected_entities');
            $table->string('affected_industry_or_market', 128)->nullable()->index();
            $table->string('geographic_scope', 64)->nullable()->index();
            $table->string('dimension', 64)->index(); // financial_exposure, operational_disruption, supply_chain_impact, regulatory_compliance, competitive_positioning, customer_market_demand, technology_disruption, reputation_perception, geopolitical_exposure, strategic_opportunity
            $table->string('potential_direction', 32)->default('neutral'); // negative, positive, neutral, bifurcated
            $table->string('estimated_magnitude', 32)->default('moderate'); // low, moderate, severe, transformative
            $table->string('time_horizon', 32)->default('near_term_90d'); // immediate_30d, near_term_90d, medium_term_12m, long_term_24m_plus
            $table->json('supporting_evidence');
            $table->json('contradictory_evidence')->nullable();
            $table->json('assumptions')->nullable();
            $table->string('confidence_level', 32)->default('medium'); // low, medium, high
            $table->text('uncertainty_explanation')->nullable();
            $table->text('methodology_explanation')->nullable();
            $table->string('status', 32)->default('active')->index(); // active, archived, invalidated
            $table->timestamps();

            $table->index(['organization_id', 'dimension']);
            $table->index(['dimension', 'status']);
        });

        // 4. Intelligence Opportunities
        Schema::create('intelligence_opportunities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->index();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('opportunity_type', 64)->index(); // emerging_markets, new_technologies, industry_expansion, competitor_weaknesses, regulatory_openings, supply_chain_alternatives, customer_demand_shift, strategic_partnerships, market_consolidation
            $table->string('category', 64)->nullable()->index();
            $table->json('affected_entities')->nullable();
            $table->string('geographic_scope', 64)->nullable()->index();
            $table->text('description');
            $table->json('supporting_evidence');
            $table->text('potential_impact')->nullable();
            $table->decimal('likelihood_score', 4, 3)->nullable();
            $table->string('time_horizon', 32)->default('near_term_90d');
            $table->string('confidence', 32)->default('medium');
            $table->json('assumptions')->nullable();
            $table->json('recommended_monitoring_indicators')->nullable();
            $table->string('status', 32)->default('detected')->index(); // detected, evaluating, pursuing, archived, dismissed
            $table->timestamps();

            $table->index(['organization_id', 'opportunity_type']);
            $table->index(['status', 'created_at']);
        });

        // 5. Intelligence Risks
        Schema::create('intelligence_risks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->index();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('risk_type', 64)->index(); // regulatory_changes, geopolitical_instability, supply_chain_disruption, competitor_expansion, technology_obsolescence, cybersecurity_developments, market_contraction, operational_dependencies, reputation_threats
            $table->string('category', 64)->nullable()->index();
            $table->json('affected_entities')->nullable();
            $table->string('geographic_scope', 64)->nullable()->index();
            $table->text('description');
            $table->json('supporting_evidence');
            $table->text('potential_impact')->nullable();
            $table->decimal('likelihood_score', 4, 3)->nullable();
            $table->string('severity', 32)->default('medium')->index(); // low, medium, high, critical
            $table->string('time_horizon', 32)->default('near_term_90d');
            $table->string('confidence', 32)->default('medium');
            $table->json('assumptions')->nullable();
            $table->json('recommended_monitoring_indicators')->nullable();
            $table->string('status', 32)->default('detected')->index(); // detected, escalating, mitigating, resolved, dismissed
            $table->timestamps();

            $table->index(['organization_id', 'risk_type']);
            $table->index(['severity', 'status']);
        });

        // 6. Scenario Analysis Engine
        Schema::create('intelligence_scenarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->index();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject')->index();
            $table->string('scenario_type', 32)->index(); // optimistic, baseline, adverse, alternative
            $table->text('description');
            $table->text('initial_conditions')->nullable();
            $table->json('key_assumptions')->nullable();
            $table->json('trigger_events')->nullable();
            $table->json('influencing_factors')->nullable();
            $table->text('potential_outcomes')->nullable();
            $table->json('risks')->nullable();
            $table->json('opportunities')->nullable();
            $table->string('time_horizon', 32)->default('medium_term_12m');
            $table->json('supporting_evidence')->nullable();
            $table->json('contradictory_evidence')->nullable();
            $table->json('indicators_to_monitor')->nullable();
            $table->string('uncertainty_rating', 32)->default('medium'); // low, medium, high, extreme
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_archived')->default(false)->index();
            $table->timestamps();

            $table->index(['organization_id', 'subject']);
            $table->index(['scenario_type', 'is_archived']);
        });

        // 7. Structured Decision Support Engine
        Schema::create('intelligence_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->index();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('business_question');
            $table->foreignUuid('decision_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deadline')->nullable();
            $table->text('context_and_background')->nullable();
            $table->string('status', 32)->default('draft')->index(); // draft, in_review, decision_recorded, implemented, post_review
            $table->string('approval_status', 32)->default('pending_approval')->index(); // pending_approval, approved, rejected
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('recorded_decision')->nullable();
            $table->timestamp('decision_date')->nullable();
            $table->timestamp('review_date')->nullable();
            $table->text('next_steps')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['decision_owner_id', 'deadline']);
        });

        // 8. Decision Options
        Schema::create('intelligence_decision_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('decision_id')->constrained('intelligence_decisions')->cascadeOnDelete();
            $table->string('option_name');
            $table->text('description')->nullable();
            $table->json('advantages')->nullable();
            $table->json('disadvantages')->nullable();
            $table->json('risks')->nullable();
            $table->json('assumptions')->nullable();
            $table->json('evidence_references')->nullable();
            $table->text('expected_outcomes')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('is_selected')->default(false);
            $table->timestamps();

            $table->index(['decision_id', 'is_selected']);
        });

        // 9. Decision Evaluation Criteria
        Schema::create('intelligence_decision_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('decision_id')->constrained('intelligence_decisions')->cascadeOnDelete();
            $table->string('criterion_name');
            $table->decimal('weight', 4, 2)->default(1.0);
            $table->string('description')->nullable();
            $table->string('scoring_methodology')->default('linear_1_to_10');
            $table->timestamps();

            $table->index(['decision_id']);
        });

        // 10. Decision Reviews & Audit History
        Schema::create('intelligence_decision_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('decision_id')->constrained('intelligence_decisions')->cascadeOnDelete();
            $table->foreignUuid('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('review_type', 32); // pre_decision_evaluation, approval, post_decision_review
            $table->text('notes');
            $table->json('changed_assumptions')->nullable();
            $table->text('observed_results_vs_expected')->nullable();
            $table->string('action_taken', 64);
            $table->timestamps();

            $table->index(['decision_id', 'review_type']);
        });

        // 11. Decision Implementation Milestones
        Schema::create('intelligence_decision_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('decision_id')->constrained('intelligence_decisions')->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('target_date')->nullable();
            $table->string('status', 32)->default('pending'); // pending, in_progress, completed, delayed
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['decision_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_decision_milestones');
        Schema::dropIfExists('intelligence_decision_reviews');
        Schema::dropIfExists('intelligence_decision_criteria');
        Schema::dropIfExists('intelligence_decision_options');
        Schema::dropIfExists('intelligence_decisions');
        Schema::dropIfExists('intelligence_scenarios');
        Schema::dropIfExists('intelligence_risks');
        Schema::dropIfExists('intelligence_opportunities');
        Schema::dropIfExists('strategic_impact_assessments');
        Schema::dropIfExists('executive_briefing_versions');
        Schema::dropIfExists('executive_briefings');
    }
};
