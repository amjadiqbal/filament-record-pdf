@php
    /**
     * $invoice = [
     *   'number' => 'INV-1042', 'date' => '2026-10-23', 'due' => '2026-11-06', 'currency' => 'USD',
     *   'from' => ['Acme Ltd', '1 Main St'], 'to' => ['Jane Doe', 'Jane Co'],
     *   'items' => [['description' => 'Design', 'quantity' => 2, 'unit_price' => 150]],
     *   'tax_rate' => 10, 'notes' => 'Thank you.', 'logo' => public_path('logo.png'),
     * ]
     */
    $invoice = $invoice ?? [];
    $currency = $invoice['currency'] ?? '';
    $money = fn ($amount): string => trim($currency.' '.number_format((float) $amount, 2));
    $items = $invoice['items'] ?? [];
    $subtotal = array_sum(array_map(fn ($item) => (float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0), $items));
    $taxRate = (float) ($invoice['tax_rate'] ?? 0);
    $tax = round($subtotal * $taxRate / 100, 2);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('filament-record-pdf::record-pdf.invoice.title') }} {{ $invoice['number'] ?? '' }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: "DejaVu Sans", Helvetica, Arial, sans-serif; font-size: 11px; color: #0f172a; }
        .top td { vertical-align: top; }
        h1 { font-size: 26px; margin: 0; color: #0369a1; letter-spacing: 1px; }
        .muted { color: #64748b; }
        .block { margin-top: 26px; }
        table { width: 100%; border-collapse: collapse; }
        .items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #475569; border-bottom: 2px solid #0f172a; padding: 8px 6px; }
        .items td { padding: 8px 6px; border-bottom: 1px solid #e2e8f0; }
        .num { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 14px; }
        .totals td { padding: 5px 6px; }
        .grand td { font-size: 14px; font-weight: bold; border-top: 2px solid #0f172a; padding-top: 8px; }
        .notes { margin-top: 30px; padding: 12px; background: #f1f5f9; }
    </style>
</head>
<body>
    <table class="top">
        <tr>
            <td style="width: 55%">
                @if (! empty($invoice['logo']))
                    <img src="{{ $invoice['logo'] }}" style="max-height: 48px; margin-bottom: 10px;" alt="">
                @endif
                @foreach ($invoice['from'] ?? [config('app.name')] as $line)
                    <div @class(['muted' => ! $loop->first])>@if ($loop->first)<strong>{{ $line }}</strong>@else{{ $line }}@endif</div>
                @endforeach
            </td>
            <td class="num">
                <h1>{{ __('filament-record-pdf::record-pdf.invoice.title') }}</h1>
                <div><strong>{{ $invoice['number'] ?? '' }}</strong></div>
                @isset($invoice['date'])<div class="muted">{{ __('filament-record-pdf::record-pdf.invoice.date') }}: {{ $invoice['date'] }}</div>@endisset
                @isset($invoice['due'])<div class="muted">{{ __('filament-record-pdf::record-pdf.invoice.due') }}: {{ $invoice['due'] }}</div>@endisset
            </td>
        </tr>
    </table>

    <div class="block">
        <div class="muted">{{ __('filament-record-pdf::record-pdf.invoice.bill_to') }}</div>
        @foreach ($invoice['to'] ?? [] as $line)
            <div>@if ($loop->first)<strong>{{ $line }}</strong>@else{{ $line }}@endif</div>
        @endforeach
    </div>

    <table class="items block">
        <thead>
            <tr>
                <th>{{ __('filament-record-pdf::record-pdf.invoice.description') }}</th>
                <th class="num">{{ __('filament-record-pdf::record-pdf.invoice.quantity') }}</th>
                <th class="num">{{ __('filament-record-pdf::record-pdf.invoice.unit_price') }}</th>
                <th class="num">{{ __('filament-record-pdf::record-pdf.invoice.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td class="num">{{ $item['quantity'] ?? 1 }}</td>
                    <td class="num">{{ $money($item['unit_price'] ?? 0) }}</td>
                    <td class="num">{{ $money((float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('filament-record-pdf::record-pdf.invoice.subtotal') }}</td><td class="num">{{ $money($subtotal) }}</td></tr>
        @if ($taxRate > 0)
            <tr><td>{{ __('filament-record-pdf::record-pdf.invoice.tax', ['rate' => rtrim(rtrim(number_format($taxRate, 2), '0'), '.')]) }}</td><td class="num">{{ $money($tax) }}</td></tr>
        @endif
        <tr class="grand"><td>{{ __('filament-record-pdf::record-pdf.invoice.total') }}</td><td class="num">{{ $money($subtotal + $tax) }}</td></tr>
    </table>

    @if (! empty($invoice['notes']))
        <div class="notes">{{ $invoice['notes'] }}</div>
    @endif
</body>
</html>
