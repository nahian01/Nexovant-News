<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('status')->default('PUBLISHED'); // DRAFT, REVIEW, SCHEDULED, PUBLISHED, UNPUBLISHED, ARCHIVED
            $table->timestamp('scheduled_at')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('featured_image_caption')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();

            // 5 Manual Override Locks (AI pipeline must never overwrite locked fields)
            $table->boolean('lock_category')->default(false);
            $table->boolean('lock_topic')->default(false);
            $table->boolean('lock_content')->default(false);
            $table->boolean('lock_image')->default(false);
            $table->boolean('lock_tags')->default(false);

            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });

        Schema::create('article_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('version_number');
            $table->string('title');
            $table->longText('content_raw');
            $table->longText('content_normalized')->nullable();
            $table->text('summary')->nullable();
            $table->string('change_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['article_id', 'version_number']);
            $table->index(['article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'status',
                'scheduled_at',
                'featured_image',
                'featured_image_caption',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'lock_category',
                'lock_topic',
                'lock_content',
                'lock_image',
                'lock_tags',
            ]);
        });
    }
};
