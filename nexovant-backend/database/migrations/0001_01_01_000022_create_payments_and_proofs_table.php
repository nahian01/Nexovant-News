<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('payment_method'); // manual_bkash, manual_nagad, manual_bank, stripe
            $table->string('provider_reference')->nullable()->index(); // transaction ID or stripe payment_intent
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('status')->default('pending_review'); // pending_review, verified, rejected, refunded
            $table->foreignUuid('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('transaction_id')->index();
            $table->string('sender_account_number')->nullable();
            $table->string('file_path')->nullable(); // R2 signed object key
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['transaction_id', 'payment_id']);
        });

        Schema::create('payment_provider_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_key')->unique(); // bkash, nagad, bank_transfer, stripe
            $table->string('display_name');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_test_mode')->default(true);
            $table->text('instructions')->nullable();
            $table->text('encrypted_credentials')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_key');
            $table->string('event_id')->index();
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_key', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_provider_configs');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('payments');
    }
};
