<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Publication Templates
        Schema::create('publication_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('page_size')->default('a4'); // a4, letter
            $table->string('orientation')->default('portrait'); // portrait, landscape
            $table->json('brand_config')->nullable();
            $table->json('section_visibility')->nullable();
            $table->string('confidentiality_label')->default('EXECUTIVE CONFIDENTIAL');
            $table->text('disclosure_text')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 2. Extend existing publications table
        Schema::table('publications', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('title');
            $table->string('status')->default('draft')->after('is_public'); // draft, generating, pending_review, approved, published, archived, generation_failed, rejected, cancelled
            $table->string('visibility')->default('premium')->after('status'); // public, registered, premium, enterprise, internal
            $table->string('edition_number')->nullable()->after('publication_date');
            $table->string('reporting_period')->nullable()->after('edition_number');
            $table->string('confidentiality_label')->default('PREMIUM EXECUTIVE INTELLIGENCE')->after('reporting_period');
            $table->foreignUuid('template_id')->nullable()->after('tenant_id')->constrained('publication_templates')->nullOnDelete();
            $table->json('content_json')->nullable()->after('file_path');
            $table->integer('evidence_completeness_score')->default(85)->after('content_json');
            $table->string('generation_key')->nullable()->index()->after('evidence_completeness_score');
            $table->foreignUuid('approved_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('publication_date');
            $table->integer('page_count')->default(1)->after('file_size_bytes');

            $table->index(['status', 'visibility']);
        });

        // 3. Publication Revisions
        Schema::create('publication_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->json('content_json');
            $table->string('change_summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 4. Publication Schedules
        Schema::create('publication_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('publication_type');
            $table->foreignUuid('template_id')->nullable()->constrained('publication_templates')->nullOnDelete();
            $table->string('title_pattern');
            $table->string('frequency')->default('daily'); // daily, weekly
            $table->string('schedule_time')->default('06:00');
            $table->string('timezone')->default('UTC');
            $table->json('scope_config')->nullable();
            $table->boolean('require_review')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'frequency']);
        });

        // 5. Download Tokens (HMAC Signed)
        Schema::create('publication_download_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->boolean('is_revoked')->default(false);
            $table->integer('max_downloads')->default(5);
            $table->integer('download_count')->default(0);
            $table->timestamps();

            $table->index(['token_hash', 'expires_at']);
        });

        // 6. Download Logs
        Schema::create('publication_download_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('downloaded_at')->useCurrent();

            $table->index(['publication_id', 'downloaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_download_logs');
        Schema::dropIfExists('publication_download_tokens');
        Schema::dropIfExists('publication_schedules');
        Schema::dropIfExists('publication_revisions');

        Schema::table('publications', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'subtitle',
                'status',
                'visibility',
                'edition_number',
                'reporting_period',
                'confidentiality_label',
                'template_id',
                'content_json',
                'evidence_completeness_score',
                'generation_key',
                'approved_by',
                'published_at',
                'page_count',
            ]);
        });

        Schema::dropIfExists('publication_templates');
    }
};
