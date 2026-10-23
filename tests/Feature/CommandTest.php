<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

afterEach(function (): void {
    File::deleteDirectory(resource_path('views/pdf'));
});

it('creates a starter view', function (): void {
    $this->artisan('make:record-pdf-view', ['name' => 'orders/packing-slip'])
        ->expectsOutputToContain("->pdfView('pdf.orders.packing-slip')")
        ->assertSuccessful();

    expect(File::exists(resource_path('views/pdf/orders/packing-slip.blade.php')))->toBeTrue();

    $this->artisan('make:record-pdf-view', ['name' => 'orders/packing-slip'])->assertFailed();
});

it('creates an invoice view from the preset', function (): void {
    $this->artisan('make:record-pdf-view', ['name' => 'invoice', '--preset' => 'invoice'])->assertSuccessful();

    expect(File::get(resource_path('views/pdf/invoice.blade.php')))->toContain('bill_to');
});

it('refuses names that leave the views directory', function (string $name): void {
    $this->artisan('make:record-pdf-view', ['name' => $name])->assertFailed();
})->with(['../evil', '/tmp/evil', '..']);
