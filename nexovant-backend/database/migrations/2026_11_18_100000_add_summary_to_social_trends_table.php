<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_trends', function (Blueprint $table) {
            if (!Schema::hasColumn('social_trends', 'summary')) {
                $table->text('summary')->nullable()->after('cross_platform_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('social_trends', function (Blueprint $table) {
            if (Schema::hasColumn('social_trends', 'summary')) {
                $table->dropColumn('summary');
            }
        });
    }
};
