<!DOCTYPE html>
<html>
<body style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #0f172a;">
    @if (filled($body))
        <div style="white-space: pre-wrap;">{{ $body }}</div>
    @endif
    <p style="color: #64748b;">{{ __('filament-record-pdf::record-pdf.email.attached', ['file' => $filename]) }}</p>
</body>
</html>
