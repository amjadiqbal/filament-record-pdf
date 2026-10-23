<?php

namespace AmjadIqbal\FilamentRecordPdf\Support;

/**
 * Plain, serializable render settings, so a queued job renders exactly what the action would.
 */
final class PdfRenderOptions
{
    public function __construct(
        public readonly string $driver,
        public readonly string $format,
        public readonly bool $landscape,
    ) {}

    public static function defaults(): self
    {
        return new self(
            driver: (string) config('filament-record-pdf.driver', 'dompdf'),
            format: (string) config('filament-record-pdf.format', 'a4'),
            landscape: (bool) config('filament-record-pdf.landscape', false),
        );
    }
}
