<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('ticker', 20)->nullable()->index();
            $table->string('isin', 12)->nullable()->index();
            $table->string('sector')->nullable();
            $table->string('industry')->nullable();
            $table->string('headquarters_country', 2)->nullable();
            $table->string('website_url')->nullable();
            $table->string('market_cap_tier')->default('mid'); // mega, large, mid, small, micro
            $table->text('summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['sector', 'industry']);
        });

        Schema::create('company_aliases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('alias')->index();
            $table->timestamps();
        });

        Schema::create('executives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name');
            $table->string('slug')->unique();
            $table->string('current_title')->nullable();
            $table->text('biography')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->boolean('verified_profile')->default(false);
            $table->timestamps();

            $table->index('full_name');
        });

        Schema::create('company_executives', function (Blueprint $table) {
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('executive_id')->constrained('executives')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_current')->default(true);
            $table->date('appointed_date')->nullable();
            $table->date('resigned_date')->nullable();
            $table->timestamps();

            $table->primary(['company_id', 'executive_id', 'title']);
        });

        Schema::create('company_relationships', function (Blueprint $table) {
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('related_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('relationship_type'); // competitor, supplier, customer, acquirer, target, partner
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->primary(['company_id', 'related_company_id', 'relationship_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_relationships');
        Schema::dropIfExists('company_executives');
        Schema::dropIfExists('executives');
        Schema::dropIfExists('company_aliases');
        Schema::dropIfExists('companies');
    }
};
