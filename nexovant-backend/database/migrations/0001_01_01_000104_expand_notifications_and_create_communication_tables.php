<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand notifications table
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('priority', 20)->default('medium')->after('is_read');
            $table->string('source_module', 50)->nullable()->after('priority');
            $table->uuid('organization_id')->nullable()->after('source_module');
            $table->string('idempotency_key', 128)->nullable()->after('organization_id');
            $table->json('metadata')->nullable()->after('idempotency_key');

            $table->index(['idempotency_key']);
            $table->index(['organization_id']);
            $table->index(['source_module', 'priority']);
        });

        // 2. Expand subscriber_notification_preferences with telegram_chat_id and whatsapp_phone
        Schema::table('subscriber_notification_preferences', function (Blueprint $table) {
            $table->string('telegram_chat_id', 64)->nullable()->after('quiet_hours_end');
            $table->string('whatsapp_phone', 32)->nullable()->after('telegram_chat_id');
            $table->string('timezone', 64)->default('UTC')->after('whatsapp_phone');
        });

        // 3. Create web_push_subscriptions table
        Schema::create('web_push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('user_agent', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        // 4. Create telegram_link_tokens table
        Schema::create('telegram_link_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['token', 'expires_at']);
        });

        // 5. Create notification_rules table
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('event_type', 100)->unique();
            $table->string('source_module', 50);
            $table->string('priority', 20)->default('medium');
            $table->json('channels');
            $table->string('template_name', 100)->nullable();
            $table->string('frequency', 20)->default('realtime'); // realtime, hourly_digest, daily_digest, weekly_digest
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'is_active']);
            $table->index(['source_module', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
        Schema::dropIfExists('telegram_link_tokens');
        Schema::dropIfExists('web_push_subscriptions');

        Schema::table('subscriber_notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'whatsapp_phone', 'timezone']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['source_module', 'priority']);
            $table->dropColumn(['priority', 'source_module', 'organization_id', 'idempotency_key', 'metadata']);
        });
    }
};
