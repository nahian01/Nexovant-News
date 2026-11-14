<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category')->default('system'); // breaking, trend_alert, publication, system, billing, editorial
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable();
            $table->string('delivery_channel')->default('in_app'); // in_app, email, whatsapp, telegram, pdf_digest
            $table->string('delivery_status')->default('sent'); // pending, sent, failed, delivered
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['delivery_channel', 'delivery_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
