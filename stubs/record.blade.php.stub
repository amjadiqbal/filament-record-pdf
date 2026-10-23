@php
    /** @var \Illuminate\Database\Eloquent\Model $record */
    $title = $title ?? \Illuminate\Support\Str::headline(class_basename($record)).' #'.$record->getKey();
    $attributes = $attributes ?? $record->attributesToArray();
    $format = function ($value): string {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? __('filament-record-pdf::record-pdf.pdf.yes') : __('filament-record-pdf::record-pdf.pdf.no'),
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
            is_array($value), is_object($value) => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            default => (string) $value,
        };
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28mm 18mm 22mm 18mm; }
        body { font-family: "DejaVu Sans", Helvetica, Arial, sans-serif; font-size: 11px; color: #0f172a; }
        .brand { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        h1 { font-size: 22px; margin: 4px 0 2px; }
        .meta { color: #64748b; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }
        th { width: 32%; color: #334155; font-weight: bold; background: #f8fafc; }
        td { white-space: pre-wrap; word-wrap: break-word; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 9px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="brand">{{ config('app.name') }}</div>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ __('filament-record-pdf::record-pdf.pdf.generated', ['date' => now()->format('Y-m-d H:i')]) }}</div>

    <table>
        @foreach ($attributes as $key => $value)
            <tr>
                <th>{{ \Illuminate\Support\Str::headline($key) }}</th>
                <td>{{ $format($value) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="footer">{{ $title }}</div>
</body>
</html>
