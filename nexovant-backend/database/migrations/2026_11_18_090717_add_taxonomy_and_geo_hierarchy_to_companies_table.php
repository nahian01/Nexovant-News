<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Continental and Country Taxonomy
            $table->string('continent', 50)->nullable()->index()->after('headquarters_country');
            $table->string('country', 100)->nullable()->index()->after('continent');

            // Country-Specific Administrative Jurisdictions
            $table->string('state', 100)->nullable()->index()->after('country'); // US, India, Australia, Germany etc.
            $table->string('province', 100)->nullable()->index()->after('state'); // Canada, China etc.
            $table->string('division', 100)->nullable()->index()->after('province'); // Bangladesh etc.
            $table->string('district', 100)->nullable()->index()->after('division'); // Bangladesh, India, Germany etc.
            $table->string('county', 100)->nullable()->index()->after('district'); // US, UK etc.
            $table->string('upazila', 100)->nullable()->index()->after('county'); // Bangladesh etc.
            $table->string('city', 100)->nullable()->index()->after('upazila');
            $table->string('municipality', 100)->nullable()->after('city');

            // Normalized Hierarchical Administrative Levels (Level 1, Level 2, Level 3)
            $table->string('admin_level_1_type', 30)->default('state')->after('municipality'); // division, state, province, prefecture, region
            $table->string('admin_level_1', 100)->nullable()->index()->after('admin_level_1_type');
            $table->string('admin_level_2_type', 30)->default('district')->after('admin_level_1'); // district, county, borough, kreis
            $table->string('admin_level_2', 100)->nullable()->index()->after('admin_level_2_type');
            $table->string('admin_level_3_type', 30)->default('city')->after('admin_level_2'); // upazila, city, municipality, township, ward
            $table->string('admin_level_3', 100)->nullable()->index()->after('admin_level_3_type');

            // Categorical Linkage
            $table->foreignUuid('category_id')->nullable()->after('industry')->constrained('categories')->nullOnDelete();
            $table->foreignUuid('subcategory_id')->nullable()->after('category_id')->constrained('subcategories')->nullOnDelete();

            // Extended Metadata
            $table->text('headquarters_address')->nullable()->after('admin_level_3');
            $table->string('postal_code', 30)->nullable()->after('headquarters_address');
            $table->integer('employee_count')->nullable()->after('market_cap_tier');
            $table->integer('founded_year')->nullable()->after('employee_count');
            $table->string('logo_url')->nullable()->after('website_url');

            // Compound Indexes for High-Velocity Multi-Dimensional Filtering
            $table->index(['continent', 'country']);
            $table->index(['country', 'admin_level_1']);
            $table->index(['country', 'division']);
            $table->index(['industry', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['continent', 'country']);
            $table->dropIndex(['country', 'admin_level_1']);
            $table->dropIndex(['country', 'division']);
            $table->dropIndex(['industry', 'category_id']);

            $table->dropForeign(['category_id']);
            $table->dropForeign(['subcategory_id']);

            $table->dropColumn([
                'continent',
                'country',
                'state',
                'province',
                'division',
                'district',
                'county',
                'upazila',
                'city',
                'municipality',
                'admin_level_1_type',
                'admin_level_1',
                'admin_level_2_type',
                'admin_level_2',
                'admin_level_3_type',
                'admin_level_3',
                'category_id',
                'subcategory_id',
                'headquarters_address',
                'postal_code',
                'employee_count',
                'founded_year',
                'logo_url',
            ]);
        });
    }
};
