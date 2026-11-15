<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #173b32; margin: 0; padding: 25px; font-size: 13px; line-height: 1.5; }
        .header { border-bottom: 2px solid #005a43; padding-bottom: 15px; margin-bottom: 25px; }
        .logo { font-size: 24px; font-weight: bold; color: #005a43; letter-spacing: -0.5px; }
        .subtitle { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #718096; margin-top: 3px; }
        .invoice-title { font-size: 20px; font-weight: bold; text-align: right; color: #173b32; }
        .meta-table { width: 100%; margin-bottom: 25px; }
        .meta-table td { vertical-align: top; }
        .bill-to { font-weight: bold; margin-bottom: 5px; color: #005a43; text-transform: uppercase; font-size: 11px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .items-table th { background-color: #f4f6f4; border-bottom: 1px solid #cbd5e0; padding: 10px; font-size: 11px; text-transform: uppercase; color: #4a5568; text-align: left; }
        .items-table td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .totals-table { width: 45%; margin-left: auto; margin-bottom: 30px; border-collapse: collapse; }
        .totals-table td { padding: 6px 10px; }
        .total-row { font-size: 15px; font-weight: bold; color: #005a43; border-top: 2px solid #005a43; border-bottom: 2px solid #005a43; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-paid { background-color: #def7ec; color: #03543f; }
        .badge-unpaid { background-color: #feecdc; color: #92400e; }
        .footer { text-align: center; font-size: 10px; color: #a0aec0; border-top: 1px solid #e2e8f0; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="logo">NEXOVANT</div>
                    <div class="subtitle">Executive Intelligence Network</div>
                </td>
                <td class="text-right">
                    <div class="invoice-title">INVOICE</div>
                    <div style="font-family: monospace; font-size: 13px; font-weight: bold; margin-top: 4px;">{{ $invoice->invoice_number }}</div>
                    <div style="margin-top: 5px;">
                        <span class="badge {{ $invoice->status === 'paid' ? 'badge-paid' : 'badge-unpaid' }}">
                            {{ strtoupper($invoice->status) }}
                        </span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 50%;">
                <div class="bill-to">Billed To</div>
                <div style="font-weight: bold;">{{ $invoice->billing_name ?? $invoice->subscription?->user?->name ?? 'Valued Subscriber' }}</div>
                <div>{{ $invoice->billing_email ?? $invoice->subscription?->user?->email }}</div>
                @if(!empty($invoice->billing_address))
                    <div>{{ $invoice->billing_address['line1'] ?? '' }}</div>
                    <div>{{ $invoice->billing_address['city'] ?? '' }}, {{ $invoice->billing_address['country'] ?? '' }}</div>
                @endif
            </td>
            <td style="width: 50%;" class="text-right">
                <div><strong>Issue Date:</strong> {{ $invoice->created_at->format('M d, Y') }}</div>
                <div><strong>Due Date:</strong> {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'Due upon receipt' }}</div>
                @if($invoice->paid_at)
                    <div><strong>Paid Date:</strong> {{ $invoice->paid_at->format('M d, Y H:i UTC') }}</div>
                @endif
                <div><strong>Currency:</strong> {{ $invoice->currency }}</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="width: 15%;" class="text-right">Qty</th>
                <th style="width: 20%;" class="text-right">Rate</th>
                <th style="width: 20%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ $invoice->currency === 'BDT' ? '৳' : '$' }}{{ number_format((float)$item->unit_price, 2) }}</td>
                    <td class="text-right">{{ $invoice->currency === 'BDT' ? '৳' : '$' }}{{ number_format((float)$item->amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td>Nexovant Subscription Access ({{ $invoice->subscription?->plan?->name ?? 'Plan' }})</td>
                    <td class="text-right">1</td>
                    <td class="text-right">{{ $invoice->currency === 'BDT' ? '৳' : '$' }}{{ number_format((float)$invoice->subtotal, 2) }}</td>
                    <td class="text-right">{{ $invoice->currency === 'BDT' ? '৳' : '$' }}{{ number_format((float)$invoice->subtotal, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Subtotal:</td>
            <td class="text-right">{{ $invoice->currency === 'BDT' ? '৳' : '$' }}{{ number_format((float)$invoice->subtotal, 2) }}</td>
        </tr>
        @if((float)$invoice->discount > 0)
            <tr>
                <td style="color: #03543f;">Promotional Discount:</td>
                <td class="text-right" style="color: #03543f;">-{{ $invoice->currency === 'BDT' ? '৳' : '$' }}{{ number_format((float)$invoice->discount, 2) }}</td>
            </tr>
        @endif
        @if((float)$invoice->tax > 0)
            <tr>
                <td>Taxes & Fees:</td>
                <td class="text-right">{{ $invoice->currency === 'BDT' ? '৳' : '$' }}{{ number_format((float)$invoice->tax, 2) }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td>Total:</td>
            <td class="text-right">{{ $invoice->currency === 'BDT' ? '৳' : '$' }}{{ number_format((float)$invoice->total, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        <div>Nexovant Intelligence Ltd. &bull; Registered Office & Corporate Desk &bull; All Rights Reserved.</div>
        <div style="margin-top: 4px;">For accounting or billing inquiries, contact billing@nexovant.si</div>
    </div>
</body>
</html>
