<?php

declare(strict_types=1);

use AmjadIqbal\FilamentRecordPdf\Actions\BulkDownloadPdfAction;
use AmjadIqbal\FilamentRecordPdf\Jobs\BuildPdfZip;
use AmjadIqbal\FilamentRecordPdf\Support\PdfRenderOptions;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\Invoice;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\InvoicePolicy;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\InvoiceResource;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\ListInvoices;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = $this->makeUser();
    $this->actingAs($this->user);
});

/**
 * @return array<string, string> entry name => contents
 */
function zipEntries(string $bytes): array
{
    $file = tempnam(sys_get_temp_dir(), 'zip-test-');
    file_put_contents($file, $bytes);
    $zip = new ZipArchive;
    $zip->open($file);
    $out = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $out[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }
    $zip->close();
    unlink($file);
    ksort($out);

    return $out;
}

it('downloads selected records as a ZIP of PDFs', function (): void {
    $invoices = collect([$this->makeInvoice(['number' => 'A-1']), $this->makeInvoice(['number' => 'A-2']), $this->makeInvoice(['number' => 'A-1'])]);

    InvoiceResource::$configure['downloadPdfs'] = fn ($action) => $action
        ->pdfFilename(fn (Invoice $record) => $record->number)
        ->zipFilename('October invoices');

    $component = livewire(ListInvoices::class)
        ->selectTableRecords($invoices->pluck('id')->all())
        ->callAction(TestAction::make('downloadPdfs')->table()->bulk())
        ->assertFileDownloaded('october-invoices.zip');

    $entries = zipEntries(base64_decode($component->effects['download']['content']));

    expect(array_keys($entries))->toBe(['a-1-2.pdf', 'a-1.pdf', 'a-2.pdf']);
    foreach ($entries as $pdf) {
        expect($pdf)->toStartWith('%PDF-');
    }
});

it('skips records the policy denies', function (): void {
    $invoices = collect([$this->makeInvoice(), $this->makeInvoice(), $this->makeInvoice()]);
    InvoicePolicy::$deny['downloadPdf'] = [$invoices[1]->id];

    $component = livewire(ListInvoices::class)
        ->selectTableRecords($invoices->pluck('id')->all())
        ->callAction(TestAction::make('downloadPdfs')->table()->bulk())
        ->assertNotified();

    expect(zipEntries(base64_decode($component->effects['download']['content'])))->toHaveCount(2);
});

it('refuses when no selected record is allowed', function (): void {
    $invoice = $this->makeInvoice();
    InvoicePolicy::$deny['downloadPdf'] = [$invoice->id];

    $component = livewire(ListInvoices::class)
        ->selectTableRecords([$invoice->id])
        ->callAction(TestAction::make('downloadPdfs')->table()->bulk())
        ->assertNotified();

    expect($component->effects['download'] ?? null)->toBeNull();
});

it('refuses more than the maximum', function (): void {
    config()->set('filament-record-pdf.bulk.max_records', 1);
    $invoices = collect([$this->makeInvoice(), $this->makeInvoice()]);

    $component = livewire(ListInvoices::class)
        ->selectTableRecords($invoices->pluck('id')->all())
        ->callAction(TestAction::make('downloadPdfs')->table()->bulk())
        ->assertNotified();

    expect($component->effects['download'] ?? null)->toBeNull();
});

it('builds large selections on the queue and notifies the user with a private link', function (): void {
    Storage::fake('local');
    $invoices = collect([$this->makeInvoice(['number' => 'Q-1']), $this->makeInvoice(['number' => 'Q-2'])]);

    InvoiceResource::$configure['downloadPdfs'] = fn ($action) => $action
        ->queueAfter(1)
        ->pdfFilename(fn (Invoice $record) => $record->number)
        ->zipFilename('queued');

    $component = livewire(ListInvoices::class)
        ->selectTableRecords($invoices->pluck('id')->all())
        ->callAction(TestAction::make('downloadPdfs')->table()->bulk())
        ->assertNotified();

    expect($component->effects['download'] ?? null)->toBeNull();

    // The sync queue already ran the job: one ZIP, no HTML left behind.
    $files = Storage::disk('local')->allFiles('record-pdfs/zips');
    expect($files)->toHaveCount(1)->and($files[0])->toEndWith('/queued.zip');

    $notification = $this->user->notifications()->first();
    expect($notification)->not->toBeNull();
    $url = $notification->data['actions'][0]['url'];

    $response = $this->get($url)->assertOk();
    expect(array_keys(zipEntries($response->streamedContent())))->toBe(['q-1.pdf', 'q-2.pdf']);

    $this->actingAs($this->makeUser('Other'));
    $this->get($url)->assertForbidden();
});

it('does not queue when the panel cannot notify, or a builder callback is set', function (): void {
    $invoices = collect([$this->makeInvoice(), $this->makeInvoice()]);

    InvoiceResource::$configure['downloadPdfs'] = fn ($action) => $action
        ->queueAfter(0)
        ->modifyPdfUsing(fn ($pdf) => $pdf->margins(5, 5, 5, 5));

    $component = livewire(ListInvoices::class)
        ->selectTableRecords($invoices->pluck('id')->all())
        ->callAction(TestAction::make('downloadPdfs')->table()->bulk());

    expect(zipEntries(base64_decode($component->effects['download']['content'])))->toHaveCount(2);
});

it('serves only ZIPs inside the zip directory, even with a valid signature', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('.env', 'APP_KEY=secret');
    Storage::disk('local')->put('record-pdfs/zips/x/a.zip', 'zip');

    $sign = fn (string $path, string $disk = 'local') => URL::temporarySignedRoute('filament-record-pdf.zip', now()->addHour(), [
        'disk' => $disk, 'path' => $path, 'user' => $this->user->id, 'guard' => 'web',
    ], absolute: false);

    $this->get($sign('.env'))->assertNotFound();
    $this->get($sign('record-pdfs/zips/../../.env'))->assertNotFound();
    $this->get($sign('record-pdfs/zips/x/a.zip', 'public'))->assertNotFound();
    $this->get($sign('record-pdfs/zips/x/a.zip'))->assertOk();
});

it('signs the queued link for the address the user was on, not APP_URL', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('record-pdfs/zips/job/html/0.html', '<p>x</p>');

    (new BuildPdfZip(
        disk: 'local',
        workDirectory: 'record-pdfs/zips/job',
        zipName: 'x.zip',
        entries: [['html' => 'record-pdfs/zips/job/html/0.html', 'name' => 'a.pdf', 'options' => PdfRenderOptions::defaults()]],
        notifiableType: $this->user::class,
        notifiableId: $this->user->id,
        guard: 'web',
        rootUrl: 'https://panel.example.test',
    ))->handle();

    expect($this->user->notifications()->first()->data['actions'][0]['url'])->toStartWith('https://panel.example.test/filament-record-pdf/zip?')
        ->and(url('/'))->toBe('http://localhost')
        ->and($this->get(str_replace('https://panel.example.test', '', $this->user->notifications()->first()->data['actions'][0]['url']))->assertOk())
        ->and(Storage::disk('local')->exists('record-pdfs/zips/job/x.zip'))->toBeTrue()
        ->and(Storage::disk('local')->exists('record-pdfs/zips/job/html/0.html'))->toBeFalse();
});

it('hides the bulk action when the policy denies viewAny', function (): void {
    $this->makeInvoice();

    livewire(ListInvoices::class)->assertActionVisible(TestAction::make('downloadPdfs')->table()->bulk());

    InvoicePolicy::$denyViewAny = true;

    // viewAny also gates the list page itself, so check the action's own rule directly.
    expect(BulkDownloadPdfAction::make()->model(Invoice::class)->canAccessAnyRecordPdf())->toBeFalse()
        ->and(BulkDownloadPdfAction::make()->model(Invoice::class)->pdfAnyAbility('notDefinedOnThePolicy')->canAccessAnyRecordPdf())->toBeTrue();
});
