<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\PaymentProviderConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController
{
    /**
     * List enabled payment methods and transfer instructions.
     */
    public function methods(): JsonResponse
    {
        $configs = PaymentProviderConfig::where('is_enabled', true)
            ->get(['provider_key', 'display_name', 'instructions', 'is_test_mode']);

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Submit payment proof and transaction reference for manual administrative verification.
     */
    public function submitProof(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'payment_method' => ['required', 'string', 'in:manual_bkash,manual_nagad,manual_bank,stripe'],
            'transaction_id' => ['required', 'string', 'max:100'],
            'sender_account' => ['nullable', 'string', 'max:50'],
        ]);

        $invoice = Invoice::findOrFail($request->input('invoice_id'));

        // Prevent duplicate transaction ID submission across the platform
        $existingProof = PaymentProof::where('transaction_id', $request->input('transaction_id'))->first();
        if ($existingProof) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_TRANSACTION',
                    'message' => 'This transaction ID has already been submitted for verification.',
                ]
            ], 422);
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => $request->input('payment_method'),
            'provider_reference' => $request->input('transaction_id'),
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'status' => 'pending_review',
        ]);

        PaymentProof::create([
            'payment_id' => $payment->id,
            'transaction_id' => $request->input('transaction_id'),
            'sender_account_number' => $request->input('sender_account'),
            'metadata' => [
                'submitted_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment reference submitted successfully. Our compliance team will review and activate your subscription.',
            'payment' => $payment->load('proof'),
        ], 201);
    }

    /**
     * List user invoices.
     */
    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();

        $invoices = Invoice::whereHas('subscription', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->with('payments.proof')
        ->latest('created_at')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }
}
