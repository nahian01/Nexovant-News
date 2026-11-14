<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('source_type'); // rss, official_disclosure, regulatory, financial_api
            $table->string('url');
            $table->string('feed_url')->nullable();
            $table->decimal('credibility_score', 3, 2)->default(0.85); // 0.00 to 1.00
            $table->boolean('is_active')->default(true);
            $table->integer('rate_limit_per_minute')->default(30);
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 10)->default('#00ADB5');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('news_sources');
    }
};
