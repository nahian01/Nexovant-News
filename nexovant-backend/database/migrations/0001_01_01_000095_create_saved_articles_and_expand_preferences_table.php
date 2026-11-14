<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_articles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'article_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('user_preferences', function (Blueprint $table) {
            $table->json('preferred_companies')->nullable();
            $table->json('preferred_topics')->nullable();
            $table->json('preferred_executives')->nullable();
            $table->string('email_frequency')->default('daily'); // daily, weekly, real_time
            $table->boolean('personalization_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_companies',
                'preferred_topics',
                'preferred_executives',
                'email_frequency',
                'personalization_enabled',
            ]);
        });

        Schema::dropIfExists('saved_articles');
    }
};
