<?php

namespace AmjadIqbal\FilamentRecordPdf;

use AmjadIqbal\FilamentRecordPdf\Support\PdfRenderOptions;
use Closure;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class RecordPdf
{
    /**
     * Render HTML to PDF bytes with spatie/laravel-pdf.
     *
     * @param  (Closure(PdfBuilder): mixed)|null  $modify
     */
    public static function render(string $html, ?PdfRenderOptions $options = null, ?Closure $modify = null): string
    {
        $options ??= PdfRenderOptions::defaults();

        $builder = static::builder($html, $options);

        if ($modify) {
            $modify($builder);
        }

        return $builder->generatePdfContent();
    }

    public static function builder(string $html, PdfRenderOptions $options): PdfBuilder
    {
        $builder = Pdf::html($html)
            ->driver($options->driver)
            ->format($options->format);

        return $options->landscape ? $builder->landscape() : $builder->portrait();
    }
}
