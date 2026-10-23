<?php

declare(strict_types=1);

use AmjadIqbal\FilamentRecordPdf\Actions\EmailPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\PreviewPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\SavePdfAction;
use AmjadIqbal\FilamentRecordPdf\Mail\RecordPdfMail;
use AmjadIqbal\FilamentRecordPdf\Support\PreviewStore;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\Invoice;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\InvoicePolicy;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\InvoiceResource;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\ListInvoices;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\ViewInvoice;
use Filament\Actions\Testing\TestAction;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = $this->makeUser();
    $this->actingAs($this->user);
});

function downloadedContent($component): string
{
    return base64_decode($component->effects['download']['content']);
}

it('downloads a record as a PDF from a table row', function (): void {
    $invoice = $this->makeInvoice(['number' => 'INV-1']);

    $component = livewire(ListInvoices::class)
        ->callAction(TestAction::make('downloadPdf')->table($invoice))
        ->assertFileDownloaded('invoice-'.$invoice->id.'.pdf');

    expect(downloadedContent($component))->toStartWith('%PDF-');
});

it('uses the configured view, data, file name and paper', function (): void {
    $invoice = $this->makeInvoice(['number' => 'INV-2']);
    $seen = [];

    InvoiceResource::$configure['downloadPdf'] = function ($action) use (&$seen) {
        $action
            ->pdfView('filament-record-pdf::presets.invoice')
            ->pdfData(function (Invoice $record) use (&$seen): array {
                $seen[] = $record->number;

                return ['invoice' => ['number' => $record->number, 'items' => [['description' => 'Work', 'unit_price' => $record->total]]]];
            })
            ->pdfFilename(fn (Invoice $record) => "Invoice {$record->number}")
            ->pdfLandscape()
            ->pdfFormat('letter');
    };

    $component = livewire(ListInvoices::class)
        ->callAction(TestAction::make('downloadPdf')->table($invoice))
        ->assertFileDownloaded('invoice-inv-2.pdf');

    expect($seen)->toContain('INV-2')
        ->and(downloadedContent($component))->toMatch('/MediaBox \[0\.0+ 0\.0+ 792\.0+ 612\.0+\]/');
});

it('downloads from a view page header action', function (): void {
    $invoice = $this->makeInvoice(['number' => 'INV-3']);

    livewire(ViewInvoice::class, ['record' => $invoice->getKey()])
        ->callAction('downloadPdf')
        ->assertFileDownloaded('invoice-inv-3.pdf');
});

it('hides actions the policy denies, preferring downloadPdf over view', function (): void {
    $invoice = $this->makeInvoice();

    InvoicePolicy::$deny['downloadPdf'] = [$invoice->id];

    livewire(ListInvoices::class)
        ->assertActionHidden(TestAction::make('downloadPdf')->table($invoice))
        ->assertActionHidden(TestAction::make('previewPdf')->table($invoice))
        // emailPdf falls back to view(), which still allows it.
        ->assertActionVisible(TestAction::make('emailPdf')->table($invoice))
        ->assertActionVisible(TestAction::make('savePdf')->table($invoice));
});

it('checks update() for saving', function (): void {
    $invoice = $this->makeInvoice();

    InvoicePolicy::$deny['update'] = [$invoice->id];

    livewire(ListInvoices::class)
        ->assertActionHidden(TestAction::make('savePdf')->table($invoice))
        ->assertActionVisible(TestAction::make('downloadPdf')->table($invoice));
});

it('accepts a custom authorization callback', function (): void {
    $invoice = $this->makeInvoice(['paid' => false]);

    InvoiceResource::$configure['downloadPdf'] = fn ($action) => $action
        ->authorizePdfUsing(fn (Invoice $record, $user) => $record->paid && $user !== null);

    livewire(ListInvoices::class)->assertActionHidden(TestAction::make('downloadPdf')->table($invoice));

    $invoice->update(['paid' => true]);

    livewire(ListInvoices::class)->assertActionVisible(TestAction::make('downloadPdf')->table($invoice));
});

it('stores a preview behind a signed URL only its user can open', function (): void {
    $invoice = $this->makeInvoice(['number' => 'INV-4']);

    $urls = PreviewPdfAction::make()->getPreviewUrls($invoice);

    $this->get($urls['inline'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Disposition', 'inline; filename=invoice-'.$invoice->id.'.pdf');

    $this->get($urls['download'])
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=invoice-'.$invoice->id.'.pdf');

    expect($this->get($urls['inline'])->getContent())->toStartWith('%PDF-');

    // Tampered or unsigned links fail.
    $this->get(str_replace('signature=', 'signature=x', $urls['inline']))->assertForbidden();
    $this->get(route('filament-record-pdf.preview', ['key' => $urls['key']]))->assertForbidden();

    // Another user cannot use the link.
    $this->actingAs($this->makeUser('Other'));
    $this->get($urls['inline'])->assertForbidden();
});

it('expires previews', function (): void {
    $urls = PreviewPdfAction::make()->getPreviewUrls($this->makeInvoice());

    PreviewStore::cache()->forget(PreviewStore::PREFIX.$urls['key']);

    $this->get($urls['inline'])->assertNotFound();
});

it('opens the preview modal from a table row', function (): void {
    $invoice = $this->makeInvoice();

    livewire(ListInvoices::class)
        ->mountAction(TestAction::make('previewPdf')->table($invoice))
        ->assertActionMounted(TestAction::make('previewPdf')->table($invoice));
});

it('emails the PDF to several recipients', function (): void {
    Mail::fake();
    $invoice = $this->makeInvoice(['number' => 'INV-5']);

    livewire(ListInvoices::class)
        ->mountAction(TestAction::make('emailPdf')->table($invoice))
        ->assertActionDataSet(['to' => 'jane@example.test', 'subject' => 'invoice-'.$invoice->id.'.pdf'])
        ->setActionData(['to' => 'jane@example.test, bob@example.test', 'subject' => 'Your invoice', 'message' => 'Thanks!'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified();

    Mail::assertSent(RecordPdfMail::class, function (RecordPdfMail $mail): bool {
        $attachment = $mail->attachments()[0];

        return $mail->hasTo('jane@example.test') && $mail->hasTo('bob@example.test')
            && $mail->subjectLine === 'Your invoice'
            && str_starts_with(base64_decode($mail->pdfBase64), '%PDF-')
            && $attachment->as === $mail->filename;
    });
});

it('validates email recipients', function (string $to): void {
    Mail::fake();

    livewire(ListInvoices::class)
        ->callAction(TestAction::make('emailPdf')->table($this->makeInvoice()), ['to' => $to, 'subject' => 'x'])
        ->assertHasActionErrors(['to']);

    Mail::assertNothingSent();
})->with(['not-an-email', 'a@example.test, nope', 'a1@x.test,a2@x.test,a3@x.test,a4@x.test,a5@x.test,a6@x.test,a7@x.test,a8@x.test,a9@x.test,a10@x.test,a11@x.test']);

it('can queue the email', function (): void {
    Mail::fake();

    InvoiceResource::$configure['emailPdf'] = fn ($action) => $action->queueMail();

    livewire(ListInvoices::class)
        ->callAction(TestAction::make('emailPdf')->table($this->makeInvoice()), ['to' => 'a@example.test', 'subject' => 'x'])
        ->assertHasNoActionErrors();

    Mail::assertQueued(RecordPdfMail::class);
    Mail::assertNotSent(RecordPdfMail::class);
});

it('renders the queued mail with its attachment after serialization', function (): void {
    $mail = new RecordPdfMail('Subject', "Line 1\nLine 2", base64_encode('%PDF-1.7 test'), 'a.pdf');
    $mail = unserialize(serialize($mail));

    expect($mail->render())->toContain('Line 1')->toContain('a.pdf')
        ->and($mail->attachments())->toHaveCount(1);
});

it('saves the PDF to the disk and the record column', function (): void {
    Storage::fake('local');
    $invoice = $this->makeInvoice(['number' => 'INV-6']);

    InvoiceResource::$configure['savePdf'] = fn ($action) => $action
        ->pdfFilename(fn (Invoice $record) => $record->number)
        ->directory('invoices/2026')
        ->saveToColumn('pdf_path');

    livewire(ListInvoices::class)
        ->callAction(TestAction::make('savePdf')->table($invoice))
        ->assertNotified();

    Storage::disk('local')->assertExists('invoices/2026/inv-6.pdf');
    expect(Storage::disk('local')->get('invoices/2026/inv-6.pdf'))->toStartWith('%PDF-')
        ->and($invoice->fresh()->pdf_path)->toBe('invoices/2026/inv-6.pdf');
});

it('keeps record data from escaping the save directory', function (): void {
    Storage::fake('local');
    $invoice = $this->makeInvoice(['number' => '../../../.env']);

    InvoiceResource::$configure['savePdf'] = fn ($action) => $action->pdfFilename(fn (Invoice $record) => $record->number);

    livewire(ListInvoices::class)->callAction(TestAction::make('savePdf')->table($invoice));

    expect(Storage::disk('local')->allFiles())->toBe(['record-pdfs/env.pdf']);
});

it('refuses an unsafe save directory', function (): void {
    Storage::fake('local');
    $invoice = $this->makeInvoice();

    $action = SavePdfAction::make()->directory(fn (Invoice $record) => '../'.$record->customer);

    expect(fn () => $action->savePdf($invoice))->toThrow(InvalidArgumentException::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('adds a timestamp instead of overwriting when asked', function (): void {
    Storage::fake('local');
    $invoice = $this->makeInvoice();

    $action = SavePdfAction::make()->overwrite(false);
    [, $first] = $action->savePdf($invoice);
    $this->travel(2)->seconds();
    [, $second] = $action->savePdf($invoice);

    expect($first)->not->toBe($second)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(2);
});

it('saves into a media library collection', function (): void {
    $invoice = $this->makeInvoice(['number' => 'INV-8']);

    [$disk, $path] = SavePdfAction::make()
        ->pdfFilename('Invoice INV-8')
        ->toMediaCollection('pdfs')
        ->savePdf($invoice);

    $media = $invoice->getFirstMedia('pdfs');

    expect($media)->not->toBeNull()
        ->and($media->file_name)->toBe('invoice-inv-8.pdf')
        ->and($media->mime_type)->toBe('application/pdf')
        ->and(file_get_contents($media->getPath()))->toStartWith('%PDF-');
});

it('parses recipient lists', function (): void {
    expect(EmailPdfAction::parseRecipients(' a@x.test; b@x.test,  a@x.test '))->toBe(['a@x.test', 'b@x.test'])
        ->and(EmailPdfAction::parseRecipients(''))->toBe([]);
});

it('says clearly when the cache store refuses a large preview', function (): void {
    Cache::extend('refusing', fn () => Cache::repository(new class extends ArrayStore
    {
        public function put($key, $value, $seconds)
        {
            return false;
        }
    }));
    config()->set('cache.stores.refusing', ['driver' => 'refusing']);
    config()->set('filament-record-pdf.cache_store', 'refusing');

    expect(fn () => PreviewPdfAction::make()->getPreviewUrls($this->makeInvoice()))
        ->toThrow(RuntimeException::class, 'RECORD_PDF_CACHE_STORE');
});
