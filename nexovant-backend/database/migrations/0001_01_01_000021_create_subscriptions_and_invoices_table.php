<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('billing_cycle')->default('monthly'); // monthly, annual
            $table->string('currency', 3)->default('BDT'); // BDT, USD
            $table->string('status')->default('pending_payment'); // pending_payment, active, grace_period, past_due, canceled, expired, suspended
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('subscription_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->string('previous_status');
            $table->string('new_status');
            $table->string('reason')->nullable();
            $table->foreignUuid('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subscription_id', 'created_at']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('status')->default('unpaid'); // unpaid, paid, void, refunded
            $table->timestamp('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscription_histories');
        Schema::dropIfExists('subscriptions');
    }
};
