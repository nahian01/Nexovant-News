<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcategories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('industries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('technologies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('category')->nullable(); // AI, Semiconductor, Cloud, Biotech, Quantum
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('product_type')->nullable(); // hardware, software, platform, cloud_service
            $table->text('summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('editorial_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Pivot tables
        Schema::create('article_topics', function (Blueprint $table) {
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignUuid('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->primary(['article_id', 'topic_id']);
        });

        Schema::create('article_tags', function (Blueprint $table) {
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['article_id', 'tag_id']);
        });

        Schema::create('article_products', function (Blueprint $table) {
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->primary(['article_id', 'product_id']);
        });

        Schema::create('article_series', function (Blueprint $table) {
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignUuid('series_id')->constrained('editorial_series')->cascadeOnDelete();
            $table->integer('order_in_series')->default(1);
            $table->primary(['article_id', 'series_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_series');
        Schema::dropIfExists('article_products');
        Schema::dropIfExists('article_tags');
        Schema::dropIfExists('article_topics');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('editorial_series');
        Schema::dropIfExists('products');
        Schema::dropIfExists('technologies');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subcategories');
    }
};
