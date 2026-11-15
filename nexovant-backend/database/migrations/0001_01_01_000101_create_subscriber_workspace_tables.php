<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Subscriber Watchlists
        Schema::create('subscriber_watchlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('notification_frequency', 20)->default('daily'); // real_time, daily, weekly, disabled
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->index(['user_id', 'sort_order']);
        });

        // 2. Subscriber Watchlist Items
        Schema::create('subscriber_watchlist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('watchlist_id')->constrained('subscriber_watchlists')->cascadeOnDelete();
            $table->string('entity_type', 32); // company, executive, product, technology, industry, institution, investor, government_org, region, event, topic
            $table->uuid('entity_id')->nullable();
            $table->string('entity_name');
            $table->string('entity_slug')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['watchlist_id', 'entity_type', 'entity_name']);
            $table->index(['entity_type', 'entity_id']);
        });

        // 3. Subscriber Custom Intelligence Monitors
        Schema::create('subscriber_monitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->json('keywords');
            $table->json('excluded_terms')->nullable();
            $table->json('entity_names')->nullable();
            $table->json('categories')->nullable();
            $table->json('industries')->nullable();
            $table->json('regions')->nullable();
            $table->decimal('min_credibility_score', 3, 2)->default(0.70);
            $table->boolean('require_verified_only')->default(false);
            $table->string('alert_frequency', 20)->default('real_time'); // real_time, daily_digest, weekly
            $table->json('delivery_channels')->nullable(); // ['in_app', 'email']
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('match_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['is_active', 'last_triggered_at']);
        });

        // 4. Subscriber Personal Collections
        Schema::create('subscriber_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#d4af37');
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        // 5. Subscriber Personal Library Items
        Schema::create('subscriber_library_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('item_type', 32); // article, publication, research_report, intelligence_event
            $table->uuid('item_id');
            $table->foreignUuid('collection_id')->nullable()->constrained('subscriber_collections')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->text('private_notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'item_type', 'item_id']);
            $table->index(['user_id', 'item_type', 'created_at']);
            $table->index(['collection_id']);
        });

        // 6. Subscriber Content Interactions (Reading History & Signals)
        Schema::create('subscriber_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('interaction_type', 32); // view, save, follow, like, dislike, hide, search
            $table->string('target_type', 32); // article, publication, entity, topic, search_query
            $table->string('target_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'interaction_type', 'created_at']);
            $table->index(['user_id', 'target_type', 'target_id']);
        });

        // 7. Subscriber Notification Preferences
        Schema::create('subscriber_notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('channels')->nullable();
            $table->json('category_settings')->nullable();
            $table->string('digest_frequency', 20)->default('daily');
            $table->string('quiet_hours_start', 10)->nullable();
            $table->string('quiet_hours_end', 10)->nullable();
            $table->timestamps();
        });

        // 8. Notification Delivery Logs
        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->string('channel', 20); // in_app, email, whatsapp, telegram, webhook
            $table->string('status', 30); // delivered, sent, failed, unconfigured_channel
            $table->integer('attempt_count')->default(1);
            $table->string('recipient')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notification_id', 'status']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('subscriber_notification_preferences');
        Schema::dropIfExists('subscriber_interactions');
        Schema::dropIfExists('subscriber_library_items');
        Schema::dropIfExists('subscriber_collections');
        Schema::dropIfExists('subscriber_monitors');
        Schema::dropIfExists('subscriber_watchlist_items');
        Schema::dropIfExists('subscriber_watchlists');
    }
};
