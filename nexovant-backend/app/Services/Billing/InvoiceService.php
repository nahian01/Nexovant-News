<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Publication\PdfRenderingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(
        protected ?PdfRenderingService $pdfRenderer = null
    ) {}

    /**
     * Create an invoice for a subscription billing event.
     */
    public function createInvoice(
        Subscription $subscription,
        float $subtotal,
        float $discount = 0.0,
        float $taxRate = 0.0,
        ?string $couponId = null,
        array $billingDetails = [],
        array $lineItems = []
    ): Invoice {
        $tax = round(($subtotal - $discount) * ($taxRate / 100), 2);
        $total = max(0.0, round($subtotal - $discount + $tax, 2));

        $invoiceNumber = 'INV-' . date('Y') . '-' . strtoupper(Str::random(6));

        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'invoice_number' => $invoiceNumber,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'coupon_id' => $couponId,
            'tax' => $tax,
            'total' => $total,
            'currency' => $subscription->currency,
            'status' => $total == 0 ? 'paid' : 'unpaid',
            'billing_name' => $billingDetails['name'] ?? $subscription->user?->name,
            'billing_email' => $billingDetails['email'] ?? $subscription->user?->email,
            'billing_address' => $billingDetails['address'] ?? null,
            'due_date' => now()->addDays(3),
            'paid_at' => $total == 0 ? now() : null,
        ]);

        if (empty($lineItems)) {
            $lineItems = [
                [
                    'description' => "Nexovant {$subscription->plan->name} ({$subscription->billing_cycle})",
                    'quantity' => 1,
                    'unit_price' => $subtotal,
                    'amount' => $subtotal,
                ]
            ];
        }

        foreach ($lineItems as $item) {
            InvoiceItem::create([
                'id' => (string) Str::uuid(),
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? $item['amount'],
                'amount' => $item['amount'],
            ]);
        }

        return $invoice;
    }

    /**
     * Mark an invoice as paid.
     */
    public function markPaid(Invoice $invoice, ?string $paidAt = null): void
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => $paidAt ? new \DateTime($paidAt) : now(),
        ]);

        // Generate PDF
        $this->generatePdf($invoice);
    }

    /**
     * Generate PDF invoice document.
     */
    public function generatePdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['subscription.plan', 'subscription.user', 'items', 'payments']);

        $html = view('invoices.receipt', ['invoice' => $invoice])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setWarnings(false);

        $fileName = "invoices/{$invoice->invoice_number}.pdf";
        Storage::disk('local')->put($fileName, $pdf->output());

        $invoice->update(['pdf_path' => $fileName]);

        return $fileName;
    }
}
