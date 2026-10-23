<?php

declare(strict_types=1);

use AmjadIqbal\FilamentRecordPdf\RecordPdf;
use AmjadIqbal\FilamentRecordPdf\Support\PdfRenderOptions;
use Spatie\LaravelPdf\PdfBuilder;

it('renders with dompdf by default, without Chromium', function (): void {
    expect(config('laravel-pdf.driver'))->toBe('browsershot')
        ->and(PdfRenderOptions::defaults()->driver)->toBe('dompdf');

    $pdf = RecordPdf::render('<h1>Hello</h1>');

    expect($pdf)->toStartWith('%PDF-');
});

it('applies landscape and format', function (): void {
    $portrait = RecordPdf::render('<p>x</p>', new PdfRenderOptions('dompdf', 'a4', false));
    $landscape = RecordPdf::render('<p>x</p>', new PdfRenderOptions('dompdf', 'a4', true));

    // A4 is 595.28 x 841.89 points; dompdf writes the MediaBox in that order or swapped.
    expect($portrait)->toMatch('/MediaBox \[0\.0+ 0\.0+ 595\.280* 841\.890*\]/')
        ->and($landscape)->toMatch('/MediaBox \[0\.0+ 0\.0+ 841\.890* 595\.280*\]/');
});

it('lets a callback change the builder', function (): void {
    $called = false;

    RecordPdf::render('<p>x</p>', null, function ($builder) use (&$called): void {
        $called = $builder instanceof PdfBuilder;
    });

    expect($called)->toBeTrue();
});

it('renders the default view without hidden attributes', function (): void {
    $invoice = $this->makeInvoice(['number' => 'INV-77']);

    $html = view('filament-record-pdf::default', ['record' => $invoice])->render();

    expect($html)->toContain('Invoice #'.$invoice->id)
        ->toContain('INV-77')
        ->toContain('Jane Doe')
        ->not->toContain('do-not-print');
});

it('renders the invoice preset with totals', function (): void {
    $html = view('filament-record-pdf::presets.invoice', ['invoice' => [
        'number' => 'INV-9', 'currency' => 'USD', 'tax_rate' => 10,
        'items' => [['description' => 'Design', 'quantity' => 2, 'unit_price' => 150], ['description' => 'Hosting', 'unit_price' => 20]],
    ]])->render();

    expect($html)->toContain('INV-9')
        ->toContain('USD 320.00')
        ->toContain('Tax (10%)')
        ->toContain('USD 32.00')
        ->toContain('USD 352.00');

    expect(RecordPdf::render($html))->toStartWith('%PDF-');
});
