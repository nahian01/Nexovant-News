<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_bdt_monthly', 10, 2)->default(0);
            $table->decimal('price_bdt_annual', 10, 2)->default(0);
            $table->decimal('price_usd_monthly', 10, 2)->default(0);
            $table->decimal('price_usd_annual', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('feature_key'); // e.g. news.access, company.follow_limit, daily_pdf
            $table->string('value_type')->default('boolean'); // boolean, integer, string
            $table->string('value'); // 'true', '25', 'unlimited'
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_entitlements');
        Schema::dropIfExists('plans');
    }
};
