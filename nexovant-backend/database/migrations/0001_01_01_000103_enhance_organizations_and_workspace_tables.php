<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance organizations table
        Schema::table('organizations', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('logo_url')->nullable()->after('domain');
            $table->string('domain_verification_token', 64)->nullable()->after('domain');
            $table->timestamp('domain_verified_at')->nullable()->after('domain_verification_token');
            $table->boolean('auto_join_domain')->default(false)->after('domain_verified_at');
            $table->json('settings')->nullable()->after('is_active');
        });

        // 2. Enhance subscriber_watchlists with team organization sharing
        Schema::table('subscriber_watchlists', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('user_id')->constrained('organizations')->cascadeOnDelete();
            $table->boolean('is_shared')->default(false)->after('is_default');

            $table->index(['organization_id', 'is_shared']);
        });

        // 3. Enhance subscriber_monitors with team organization sharing
        Schema::table('subscriber_monitors', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('user_id')->constrained('organizations')->cascadeOnDelete();
            $table->boolean('is_shared')->default(false)->after('is_active');

            $table->index(['organization_id', 'is_shared']);
        });

        // 4. Enhance research_projects with organization scoping
        Schema::table('research_projects', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('user_id')->constrained('organizations')->cascadeOnDelete();

            $table->index(['organization_id']);
        });

        // 5. Create organization_audit_logs table
        Schema::create('organization_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'action']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_audit_logs');

        Schema::table('research_projects', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('subscriber_monitors', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'is_shared']);
        });

        Schema::table('subscriber_watchlists', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'is_shared']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'logo_url',
                'domain_verification_token',
                'domain_verified_at',
                'auto_join_domain',
                'settings',
            ]);
        });
    }
};
