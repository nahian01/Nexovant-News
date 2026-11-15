<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Central Intelligence Entities Table
        Schema::create('intelligence_entities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 32)->index(); // company, executive, product, technology, industry, institution, investor, government, region, event
            $table->string('canonical_name');
            $table->string('slug')->unique();
            $table->string('legal_name')->nullable();
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->string('country', 10)->nullable();
            $table->string('headquarters')->nullable();
            $table->string('industry')->nullable();
            $table->integer('founding_year')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(1.00);
            $table->string('verification_status', 32)->default('unverified'); // verified, unverified, disputed, merged, archived
            $table->foreignUuid('merged_into_id')->nullable()->constrained('intelligence_entities')->nullOnDelete();
            $table->nullableMorphs('entityable'); // Optional polymorphic link to Company, Executive, Product, Technology
            $table->json('metadata')->nullable(); // Extended type-specific profile fields (career history, versions, etc.)
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'verification_status']);
            $table->index('canonical_name');
        });

        // 2. Entity Aliases Table (resolution & synonyms)
        Schema::create('entity_aliases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')->constrained('intelligence_entities')->cascadeOnDelete();
            $table->string('alias')->index();
            $table->string('alias_type', 32)->default('alternative_name'); // abbreviation, legal_name, former_name, brand, spelling_variant
            $table->string('source')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(1.00);
            $table->timestamps();

            $table->unique(['entity_id', 'alias']);
        });

        // 3. Entity Resolution Review Queue (low-confidence matches)
        Schema::create('entity_resolution_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')->nullable()->constrained('intelligence_entities')->nullOnDelete();
            $table->string('candidate_name');
            $table->string('candidate_type', 32);
            $table->foreignUuid('potential_match_entity_id')->nullable()->constrained('intelligence_entities')->nullOnDelete();
            $table->decimal('confidence_score', 3, 2);
            $table->string('status', 32)->default('pending'); // pending, merged, split_as_new, rejected
            $table->text('match_reason')->nullable();
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'confidence_score']);
        });

        // 4. Entity Audit History
        Schema::create('entity_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_id')->constrained('intelligence_entities')->cascadeOnDelete();
            $table->string('action', 32); // created, updated, merged, split, verified, disputed, archived
            $table->json('changes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Entity Relationships Graph
        Schema::create('entity_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_entity_id')->constrained('intelligence_entities')->cascadeOnDelete();
            $table->string('relationship_type', 40); // owns, develops, acquires, partners_with, invests_in, competes_with, employs, founded_by, uses_technology, supplies, subsidiary_of, parent_of, operates_in, announced, collaborates_with
            $table->foreignUuid('target_entity_id')->constrained('intelligence_entities')->cascadeOnDelete();
            $table->foreignUuid('supporting_source_id')->nullable()->constrained('news_sources')->nullOnDelete();
            $table->foreignUuid('story_id')->nullable()->constrained('discovered_stories')->nullOnDelete();
            $table->foreignUuid('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->text('evidence_excerpt')->nullable();
            $table->text('source_url')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(0.85);
            $table->string('verification_status', 32)->default('single_source'); // verified, single_source, ai_inferred, disputed
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_entity_id', 'relationship_type']);
            $table->index(['target_entity_id', 'relationship_type']);
            $table->index(['relationship_type', 'verification_status']);
        });

        // 6. Claim Evidence & Multi-Source Stance Tracking (Fact Verification Extension)
        Schema::create('claim_evidences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fact_id')->constrained('extracted_facts')->cascadeOnDelete();
            $table->foreignUuid('source_id')->constrained('news_sources')->cascadeOnDelete();
            $table->foreignUuid('story_id')->nullable()->constrained('discovered_stories')->nullOnDelete();
            $table->string('stance', 20)->default('supports'); // supports, contradicts, neutral, refines
            $table->text('evidence_text');
            $table->text('source_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_syndicated_reprint')->default(false);
            $table->decimal('reliability_score', 3, 2)->default(0.85);
            $table->string('reviewer_decision', 32)->default('pending'); // accepted, disputed, rejected
            $table->timestamps();

            $table->index(['fact_id', 'stance']);
        });

        // 7. Fact Verification Audit History
        Schema::create('fact_verification_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fact_id')->constrained('extracted_facts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32); // claim_extracted, status_updated, evidence_attached, editor_approved, disputed, rejected
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->text('editorial_reason')->nullable();
            $table->json('dimension_scores')->nullable(); // 10 dimensions evaluated
            $table->timestamps();
        });

        // 8. Historical Intelligence Events & Timeline
        Schema::create('intelligence_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('event_category', 40); // product_launch, acquisition, executive_change, benchmark, funding_round, partnership, regulatory, breakthrough
            $table->text('summary')->nullable();
            $table->timestamp('occurred_at'); // when it actually took place
            $table->timestamp('reported_at')->nullable(); // when public news reported it
            $table->timestamp('discovered_at')->nullable();
            $table->string('verification_status', 32)->default('reported_reliable'); // confirmed, reported_reliable, developing, disputed
            $table->foreignUuid('primary_entity_id')->nullable()->constrained('intelligence_entities')->nullOnDelete();
            $table->foreignUuid('cluster_id')->nullable()->constrained('story_clusters')->nullOnDelete();
            $table->foreignUuid('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['occurred_at', 'event_category']);
            $table->index(['primary_entity_id', 'occurred_at']);
        });

        // 9. Intelligence Event Entities Pivot
        Schema::create('intelligence_event_entities', function (Blueprint $table) {
            $table->foreignUuid('event_id')->constrained('intelligence_events')->cascadeOnDelete();
            $table->foreignUuid('entity_id')->constrained('intelligence_entities')->cascadeOnDelete();
            $table->string('role', 32)->default('subject'); // subject, counterparty, investor, affected_party
            $table->primary(['event_id', 'entity_id', 'role']);
        });

        // 10. Intelligence Trends & Signals Table
        Schema::create('intelligence_trends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('trend_category', 32); // technology, industry, corporate_strategy, market_shift, regulatory
            $table->string('status', 32)->default('emerging'); // emerging, accelerating, peak, maturing, declining
            $table->decimal('composite_score', 5, 2)->default(0.00); // 0.00 to 100.00
            $table->decimal('volume_score', 5, 2)->default(0.00);
            $table->decimal('growth_rate_score', 5, 2)->default(0.00);
            $table->decimal('source_diversity_score', 5, 2)->default(0.00);
            $table->decimal('source_credibility_score', 5, 2)->default(0.00);
            $table->decimal('entity_relevance_score', 5, 2)->default(0.00);
            $table->integer('story_count')->default(1);
            $table->integer('independent_sources_count')->default(1);
            $table->timestamp('first_observed_at')->nullable();
            $table->timestamp('last_observed_at')->nullable();
            $table->json('metadata')->nullable(); // scoring formula components, breakdown, sentiment
            $table->timestamps();

            $table->index(['status', 'composite_score']);
        });

        // 11. Trend Entities Pivot
        Schema::create('intelligence_trend_entities', function (Blueprint $table) {
            $table->foreignUuid('trend_id')->constrained('intelligence_trends')->cascadeOnDelete();
            $table->foreignUuid('entity_id')->constrained('intelligence_entities')->cascadeOnDelete();
            $table->decimal('relevance_weight', 3, 2)->default(1.00);
            $table->primary(['trend_id', 'entity_id']);
        });

        // 12. Deep Research Projects
        Schema::create('research_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('research_question');
            $table->string('topic')->nullable();
            $table->foreignUuid('entity_id')->nullable()->constrained('intelligence_entities')->nullOnDelete();
            $table->string('depth', 20)->default('standard'); // brief, standard, comprehensive, exhaustive
            $table->string('output_format', 40)->default('executive_summary'); // executive_summary, company_profile, technology_assessment, industry_analysis, competitive_intelligence, executive_profile, product_comparison, historical_event_analysis, trend_report, custom_research
            $table->date('time_range_start')->nullable();
            $table->date('time_range_end')->nullable();
            $table->string('geographic_region', 20)->nullable();
            $table->string('status', 32)->default('queued'); // queued, analyzing, retrieving_intelligence, synthesizing, completed, failed
            $table->integer('progress_percent')->default(0);
            $table->text('error_message')->nullable();
            $table->json('parameters')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // 13. Research Reports (Synthesized Evidence-Backed Intelligence)
        Schema::create('research_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('research_projects')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('executive_summary');
            $table->longText('content_markdown');
            $table->json('structured_findings')->nullable(); // Key developments, competitive dynamics, industry implications
            $table->json('conflicting_claims')->nullable(); // Conflicting reports with stances
            $table->json('information_gaps')->nullable(); // Known unknowns & pending verifications
            $table->decimal('confidence_score', 3, 2)->default(0.90);
            $table->string('uncertainty_rating', 20)->default('low'); // low, moderate, high
            $table->string('editorial_status', 32)->default('draft'); // draft, in_review, editorial_approved, archived
            $table->boolean('is_public')->default(false);
            $table->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->integer('revisions_count')->default(1);
            $table->timestamps();

            $table->index(['editorial_status', 'is_public']);
        });

        // 14. Research Citations (Grounding Evidence)
        Schema::create('research_citations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('research_reports')->cascadeOnDelete();
            $table->integer('citation_number');
            $table->text('claim_text');
            $table->foreignUuid('fact_id')->nullable()->constrained('extracted_facts')->nullOnDelete();
            $table->foreignUuid('story_id')->nullable()->constrained('discovered_stories')->nullOnDelete();
            $table->foreignUuid('source_id')->nullable()->constrained('news_sources')->nullOnDelete();
            $table->string('source_name');
            $table->text('source_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['report_id', 'citation_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_citations');
        Schema::dropIfExists('research_reports');
        Schema::dropIfExists('research_projects');
        Schema::dropIfExists('intelligence_trend_entities');
        Schema::dropIfExists('intelligence_trends');
        Schema::dropIfExists('intelligence_event_entities');
        Schema::dropIfExists('intelligence_events');
        Schema::dropIfExists('fact_verification_histories');
        Schema::dropIfExists('claim_evidences');
        Schema::dropIfExists('entity_relationships');
        Schema::dropIfExists('entity_histories');
        Schema::dropIfExists('entity_resolution_reviews');
        Schema::dropIfExists('entity_aliases');
        Schema::dropIfExists('intelligence_entities');
    }
};
