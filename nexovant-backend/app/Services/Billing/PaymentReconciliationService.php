<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReconciliationRun;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

class PaymentReconciliationService
{
    /**
     * Run financial reconciliation audit across payments, invoices, and subscriptions.
     */
    public function runReconciliation(User $admin, ?string $gatewayKey = null): array
    {
        $discrepancies = [];

        // 1. Invoices marked 'paid' without any verified payment record
        $unbackedPaidInvoices = Invoice::where('status', 'paid')
            ->where('total', '>', 0)
            ->whereDoesntHave('payments', function ($q) {
                $q->whereIn('status', ['verified', 'paid']);
            })
            ->get();

        foreach ($unbackedPaidInvoices as $inv) {
            $discrepancies[] = [
                'type' => 'unbacked_paid_invoice',
                'severity' => 'high',
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'amount' => $inv->total,
                'currency' => $inv->currency,
                'message' => "Invoice marked as PAID but lacks verified transaction record.",
            ];
        }

        // 2. Active subscriptions with unpaid latest invoice
        $inconsistentActiveSubs = Subscription::where('status', 'active')
            ->whereHas('invoices', function ($q) {
                $q->where('status', 'unpaid')->where('due_date', '<', now());
            })
            ->get();

        foreach ($inconsistentActiveSubs as $sub) {
            $discrepancies[] = [
                'type' => 'active_sub_unpaid_invoice',
                'severity' => 'medium',
                'subscription_id' => $sub->id,
                'user_id' => $sub->user_id,
                'plan' => $sub->plan?->name,
                'message' => "Subscription is ACTIVE despite overdue unpaid invoice.",
            ];
        }

        // 3. Payments with verified status but invoice is still marked 'unpaid'
        $uncreditedPayments = Payment::where('status', 'verified')
            ->whereHas('invoice', function ($q) {
                $q->where('status', '!=', 'paid');
            })
            ->get();

        foreach ($uncreditedPayments as $p) {
            $discrepancies[] = [
                'type' => 'uncredited_verified_payment',
                'severity' => 'high',
                'payment_id' => $p->id,
                'invoice_id' => $p->invoice_id,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'message' => "Payment is VERIFIED but parent invoice is not marked PAID.",
            ];
        }

        $totalPayments = Payment::when($gatewayKey, fn($q) => $q->where('gateway_key', $gatewayKey))->count();
        $verifiedPayments = Payment::where('status', 'verified')
            ->when($gatewayKey, fn($q) => $q->where('gateway_key', $gatewayKey))
            ->count();

        $run = PaymentReconciliationRun::create([
            'id' => (string) Str::uuid(),
            'run_date' => now()->toDateString(),
            'gateway_key' => $gatewayKey ?? 'all_gateways',
            'total_internal_transactions' => $totalPayments,
            'total_gateway_transactions' => $verifiedPayments,
            'matched_count' => max(0, $verifiedPayments - count($discrepancies)),
            'discrepancy_count' => count($discrepancies),
            'discrepancy_details' => $discrepancies,
            'executed_by_user_id' => $admin->id,
        ]);

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'action' => 'admin.payment.reconciliation_run',
            'resource_type' => 'PaymentReconciliationRun',
            'resource_id' => $run->id,
            'metadata' => [
                'discrepancy_count' => count($discrepancies),
                'total_checked' => $totalPayments,
            ],
            'created_at' => now(),
        ]);

        return [
            'run_id' => $run->id,
            'run_date' => $run->run_date,
            'total_transactions' => $totalPayments,
            'verified_transactions' => $verifiedPayments,
            'discrepancies_found' => count($discrepancies),
            'discrepancies' => $discrepancies,
            'status' => count($discrepancies) === 0 ? 'reconciled' : 'discrepancies_detected',
        ];
    }
}
