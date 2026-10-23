{{-- Styles ship inline so the preview works in a stock panel without publishing assets. --}}
<style>
    .fi-record-pdf-frame { display: block; width: 100%; height: 72vh; min-height: 420px; border: 1px solid rgb(228 228 231); border-radius: 0.5rem; background: rgb(244 244 245); }
    .dark .fi-record-pdf-frame { border-color: rgb(255 255 255 / 0.1); background: rgb(24 24 27); }
    .fi-record-pdf-fallback { margin-top: 0.5rem; font-size: 0.875rem; color: rgb(113 113 122); }
    .fi-record-pdf-fallback a { text-decoration: underline; }
    .dark .fi-record-pdf-fallback { color: rgb(161 161 170); }
</style>
<div class="fi-record-pdf-preview">
    <iframe src="{{ $url }}" title="{{ $title }}" class="fi-record-pdf-frame" loading="eager"></iframe>
    <p class="fi-record-pdf-fallback">
        {{ __('filament-record-pdf::record-pdf.preview.fallback') }}
        <a href="{{ $url }}" target="_blank" rel="noopener">{{ __('filament-record-pdf::record-pdf.preview.open') }}</a>
    </p>
</div>
