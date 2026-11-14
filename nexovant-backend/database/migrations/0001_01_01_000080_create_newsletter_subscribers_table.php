<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('frequency')->default('daily'); // daily, weekly, breaking
            $table->string('source')->default('public_blog');
            $table->string('offer_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('ip_address')->nullable();
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamps();

            $table->index(['is_active', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
