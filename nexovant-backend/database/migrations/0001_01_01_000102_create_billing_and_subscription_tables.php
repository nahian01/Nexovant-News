<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Coupons Table
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->string('discount_type', 20); // 'percentage', 'fixed_amount'
                $table->decimal('discount_value', 12, 2);
                $table->string('currency', 10)->nullable();
                $table->unsignedInteger('max_redemptions')->nullable();
                $table->unsignedInteger('times_redeemed')->default(0);
                $table->dateTime('valid_from')->nullable();
                $table->dateTime('valid_until')->nullable();
                $table->json('eligible_plans')->nullable();
                $table->json('eligible_countries')->nullable();
                $table->decimal('min_order_amount', 12, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['code', 'is_active']);
            });
        }

        // 2. Coupon Redemptions Table
        if (!Schema::hasTable('coupon_redemptions')) {
            Schema::create('coupon_redemptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('coupon_id')->constrained('coupons')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->decimal('discount_applied', 12, 2);
                $table->string('currency', 10)->default('USD');
                $table->dateTime('redeemed_at');
                $table->timestamps();

                $table->index(['coupon_id', 'user_id']);
            });
        }

        // 3. Payment Gateways Registry Table
        if (!Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('gateway_key', 50)->unique(); // 'stripe', 'bkash', 'nagad', 'razorpay', 'manual_bank'
                $table->string('name', 100);
                $table->json('supported_countries')->nullable();
                $table->json('supported_currencies')->nullable();
                $table->json('supported_methods')->nullable();
                $table->json('settlement_currencies')->nullable();
                $table->boolean('supports_refunds')->default(false);
                $table->boolean('supports_recurring')->default(false);
                $table->boolean('supports_webhooks')->default(false);
                $table->boolean('is_configured')->default(false);
                $table->boolean('merchant_verified')->default(false);
                $table->boolean('is_active')->default(false);
                $table->string('unavailability_reason', 255)->nullable();
                $table->text('credentials_encrypted')->nullable();
                $table->text('webhook_secret_encrypted')->nullable();
                $table->boolean('test_mode')->default(true);
                $table->timestamps();
            });
        }

        // 4. Payment Customers Table (Gateway Customer Tokens)
        if (!Schema::hasTable('payment_customers')) {
            Schema::create('payment_customers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('gateway_key', 50);
                $table->string('gateway_customer_id', 150);
                $table->string('default_payment_method_id', 150)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'gateway_key']);
                $table->index(['gateway_key', 'gateway_customer_id']);
            });
        }

        // 5. Expand existing payment_webhook_events if needed
        if (Schema::hasTable('payment_webhook_events')) {
            Schema::table('payment_webhook_events', function (Blueprint $table) {
                if (!Schema::hasColumn('payment_webhook_events', 'gateway_key')) {
                    $table->string('gateway_key', 50)->nullable()->after('id');
                }
                if (!Schema::hasColumn('payment_webhook_events', 'signature')) {
                    $table->text('signature')->nullable()->after('payload');
                }
                if (!Schema::hasColumn('payment_webhook_events', 'idempotency_key')) {
                    $table->string('idempotency_key', 150)->nullable()->after('status');
                }
            });
        } else {
            Schema::create('payment_webhook_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('gateway_key', 50);
                $table->string('event_id', 150);
                $table->string('event_type', 100);
                $table->json('payload');
                $table->text('signature')->nullable();
                $table->string('status', 30)->default('received');
                $table->string('idempotency_key', 150)->unique();
                $table->text('error_message')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->timestamps();

                $table->index(['gateway_key', 'event_type', 'status']);
            });
        }

        // 6. Invoice Items Table
        if (!Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->string('description', 255);
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('amount', 12, 2);
                $table->timestamps();

                $table->index('invoice_id');
            });
        }

        // 7. Refunds Table
        if (!Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 10);
                $table->string('reason', 255);
                $table->string('status', 30)->default('pending'); // 'pending', 'processed', 'failed', 'rejected'
                $table->string('gateway_refund_id', 150)->nullable();
                $table->foreignUuid('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->timestamps();

                $table->index(['payment_id', 'status']);
            });
        }

        // 8. Payment Disputes Table
        if (!Schema::hasTable('payment_disputes')) {
            Schema::create('payment_disputes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->string('gateway_dispute_id', 150);
                $table->decimal('amount', 12, 2);
                $table->string('currency', 10);
                $table->string('reason', 150);
                $table->string('status', 30)->default('needs_response'); // 'needs_response', 'under_review', 'won', 'lost'
                $table->json('evidence')->nullable();
                $table->dateTime('due_by')->nullable();
                $table->timestamps();

                $table->index(['payment_id', 'status']);
            });
        }

        // 9. Subscription Entitlement Overrides Table
        if (!Schema::hasTable('subscription_entitlement_overrides')) {
            Schema::create('subscription_entitlement_overrides', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->cascadeOnDelete();
                $table->string('feature_key', 100);
                $table->string('value', 255);
                $table->foreignUuid('granted_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason', 255);
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'feature_key']);
            });
        }

        // 10. Payment Reconciliation Runs Table
        if (!Schema::hasTable('payment_reconciliation_runs')) {
            Schema::create('payment_reconciliation_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->date('run_date');
                $table->string('gateway_key', 50);
                $table->unsignedInteger('total_internal_transactions')->default(0);
                $table->unsignedInteger('total_gateway_transactions')->default(0);
                $table->unsignedInteger('matched_count')->default(0);
                $table->unsignedInteger('discrepancy_count')->default(0);
                $table->json('discrepancy_details')->nullable();
                $table->foreignUuid('executed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 11. Add missing fields to existing tables
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'trial_period_days')) {
                $table->unsignedSmallInteger('trial_period_days')->default(0)->after('sort_order');
            }
            if (!Schema::hasColumn('plans', 'promotional_price_monthly')) {
                $table->decimal('promotional_price_monthly', 12, 2)->nullable()->after('trial_period_days');
            }
            if (!Schema::hasColumn('plans', 'promotional_price_annual')) {
                $table->decimal('promotional_price_annual', 12, 2)->nullable()->after('promotional_price_monthly');
            }
            if (!Schema::hasColumn('plans', 'features')) {
                $table->json('features')->nullable()->after('promotional_price_annual');
            }
            if (!Schema::hasColumn('plans', 'regional_availability')) {
                $table->json('regional_availability')->nullable()->after('features');
            }
            if (!Schema::hasColumn('plans', 'grace_period_days')) {
                $table->unsignedSmallInteger('grace_period_days')->default(3)->after('regional_availability');
            }
            if (!Schema::hasColumn('plans', 'tax_category')) {
                $table->string('tax_category', 50)->default('digital_services')->after('grace_period_days');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'renewals_count')) {
                $table->unsignedInteger('renewals_count')->default(0)->after('canceled_at');
            }
            if (!Schema::hasColumn('subscriptions', 'next_renewal_at')) {
                $table->dateTime('next_renewal_at')->nullable()->after('renewals_count');
            }
            if (!Schema::hasColumn('subscriptions', 'payment_method_id')) {
                $table->string('payment_method_id', 150)->nullable()->after('next_renewal_at');
            }
            if (!Schema::hasColumn('subscriptions', 'gateway_key')) {
                $table->string('gateway_key', 50)->nullable()->after('payment_method_id');
            }
            if (!Schema::hasColumn('subscriptions', 'gateway_subscription_id')) {
                $table->string('gateway_subscription_id', 150)->nullable()->after('gateway_key');
            }
            if (!Schema::hasColumn('subscriptions', 'billing_country')) {
                $table->string('billing_country', 10)->default('BD')->after('gateway_subscription_id');
            }
            if (!Schema::hasColumn('subscriptions', 'prorated_credit')) {
                $table->decimal('prorated_credit', 12, 2)->default(0)->after('billing_country');
            }
            if (!Schema::hasColumn('subscriptions', 'paused_at')) {
                $table->dateTime('paused_at')->nullable()->after('prorated_credit');
            }
            if (!Schema::hasColumn('subscriptions', 'grace_period_ends_at')) {
                $table->dateTime('grace_period_ends_at')->nullable()->after('paused_at');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'discount')) {
                $table->decimal('discount', 12, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('invoices', 'coupon_id')) {
                $table->foreignUuid('coupon_id')->nullable()->after('discount')->constrained('coupons')->nullOnDelete();
            }
            if (!Schema::hasColumn('invoices', 'billing_name')) {
                $table->string('billing_name', 150)->nullable()->after('coupon_id');
            }
            if (!Schema::hasColumn('invoices', 'billing_email')) {
                $table->string('billing_email', 150)->nullable()->after('billing_name');
            }
            if (!Schema::hasColumn('invoices', 'billing_address')) {
                $table->json('billing_address')->nullable()->after('billing_email');
            }
            if (!Schema::hasColumn('invoices', 'pdf_path')) {
                $table->string('pdf_path', 255)->nullable()->after('billing_address');
            }
            if (!Schema::hasColumn('invoices', 'notes')) {
                $table->text('notes')->nullable()->after('pdf_path');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'gateway_key')) {
                $table->string('gateway_key', 50)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('payments', 'gateway_charge_id')) {
                $table->string('gateway_charge_id', 150)->nullable()->after('gateway_key');
            }
            if (!Schema::hasColumn('payments', 'idempotency_key')) {
                $table->string('idempotency_key', 150)->nullable()->unique()->after('gateway_charge_id');
            }
            if (!Schema::hasColumn('payments', 'raw_response')) {
                $table->json('raw_response')->nullable()->after('idempotency_key');
            }
            if (!Schema::hasColumn('payments', 'failure_reason')) {
                $table->string('failure_reason', 255)->nullable()->after('raw_response');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_runs');
        Schema::dropIfExists('subscription_entitlement_overrides');
        Schema::dropIfExists('payment_disputes');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('payment_customers');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
