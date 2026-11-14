<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\SubscriptionHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentAdminController
{
    /**
     * List payments pending manual compliance verification.
     */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with(['invoice.subscription.plan', 'invoice.subscription.user', 'proof', 'verifiedBy'])
            ->latest('created_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ]
        ]);
    }

    /**
     * Approve or reject a manual payment transaction.
     */
    public function verify(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:verified,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $admin = $request->user();
        $status = $request->input('status');
        $notes = $request->input('admin_notes');

        $result = DB::transaction(function () use ($id, $admin, $status, $notes) {
            $payment = Payment::with('invoice.subscription')->findOrFail($id);

            if ($payment->status === 'verified') {
                throw new \InvalidArgumentException('This payment has already been verified.');
            }

            $payment->update([
                'status' => $status,
                'verified_by_user_id' => $admin->id,
                'verified_at' => now(),
                'admin_notes' => $notes,
            ]);

            $invoice = $payment->invoice;
            $subscription = $invoice->subscription;

            if ($status === 'verified') {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                $prevStatus = $subscription->status;
                $durationDays = $subscription->billing_cycle === 'annual' ? 365 : 30;

                $subscription->update([
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => now()->addDays($durationDays),
                ]);

                SubscriptionHistory::create([
                    'subscription_id' => $subscription->id,
                    'previous_status' => $prevStatus,
                    'new_status' => 'active',
                    'reason' => "Manual payment approved by {$admin->name} (TrxID: {$payment->provider_reference})",
                    'changed_by_user_id' => $admin->id,
                    'created_at' => now(),
                ]);
            }

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => "payment.{$status}",
                'resource_type' => 'Payment',
                'resource_id' => $payment->id,
                'new_values' => ['status' => $status, 'notes' => $notes],
                'created_at' => now(),
            ]);

            return $payment->load(['invoice.subscription', 'proof']);
        });

        return response()->json([
            'success' => true,
            'message' => $status === 'verified' ? 'Payment verified and subscription activated.' : 'Payment marked as rejected.',
            'payment' => $result,
        ]);
    }

    /**
     * List all payment provider configurations.
     */
    public function methods(): JsonResponse
    {
        $configs = \App\Models\PaymentProviderConfig::all();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Update payment provider instructions or enabled status.
     */
    public function updateMethod(string $id, Request $request): JsonResponse
    {
        $config = \App\Models\PaymentProviderConfig::findOrFail($id);
        $config->update($request->only('display_name', 'is_enabled', 'is_test_mode', 'instructions'));

        return response()->json([
            'success' => true,
            'message' => "Payment configuration for '{$config->display_name}' updated.",
            'data' => $config,
        ]);
    }
}
