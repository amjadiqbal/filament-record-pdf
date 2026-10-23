<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/amjadiqbal/filament-record-pdf/main/art/banner-dark.png">
    <img alt="Record PDF: preview, download, email and save any Filament record as a PDF" src="https://raw.githubusercontent.com/amjadiqbal/filament-record-pdf/main/art/banner-light.png" width="100%">
  </picture>
</p>

# Record PDF

Preview, download, bulk-download as a ZIP, email and save any Filament 4 or 5 record as a PDF, from a Blade view
you write per resource. Rendering happens on the server through [spatie/laravel-pdf](https://github.com/spatie/laravel-pdf),
with the pure-PHP **dompdf** driver by default, so it works on any host without Chromium or Node.

[![Latest Version](https://img.shields.io/packagist/v/amjadiqbal/filament-record-pdf.svg?style=flat-square)](https://packagist.org/packages/amjadiqbal/filament-record-pdf)
[![PHP Version](https://img.shields.io/packagist/php-v/amjadiqbal/filament-record-pdf?style=flat-square)](https://packagist.org/packages/amjadiqbal/filament-record-pdf)
[![License](https://img.shields.io/packagist/l/amjadiqbal/filament-record-pdf.svg?style=flat-square)](LICENSE.md)

![Previewing an invoice PDF in a Filament modal](https://raw.githubusercontent.com/amjadiqbal/filament-record-pdf/main/art/screenshots/preview-light.png)

## What you get

| Action | Where | What it does |
|---|---|---|
| `PreviewPdfAction` | row, page header | Opens the PDF in a modal, with a download button |
| `DownloadPdfAction` | row, page header | Downloads the PDF |
| `EmailPdfAction` | row, page header | Asks for recipients, subject and message, then sends the PDF as an attachment |
| `SavePdfAction` | row, page header | Saves the PDF to a disk (and optionally a column), or to a media library collection |
| `BulkDownloadPdfAction` | table toolbar | Downloads the selected records as a ZIP of PDFs. Large selections are built on the queue |

All five actions take the same options, so one view and one file-name rule cover every action on a resource.

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- Filament 4 or 5
- `ext-zip` for the bulk ZIP action (most PHP builds include it)

## Installation

```bash
composer require amjadiqbal/filament-record-pdf
```

That is all you need for the dompdf driver: `spatie/laravel-pdf` and `dompdf/dompdf` are installed with the plugin.
There are no assets to publish and no migrations to run.

You can publish the config if you want to change it:

```bash
php artisan vendor:publish --tag="filament-record-pdf-config"
```

## Usage

Add the actions to a resource table:

```php
use AmjadIqbal\FilamentRecordPdf\Actions\BulkDownloadPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\DownloadPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\EmailPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\PreviewPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\SavePdfAction;
use Filament\Actions\ActionGroup;

public static function table(Table $table): Table
{
    return $table
        ->columns([/* ... */])
        ->recordActions([
            ActionGroup::make([
                PreviewPdfAction::make()->pdfView('pdf.invoice'),
                DownloadPdfAction::make()->pdfView('pdf.invoice'),
                EmailPdfAction::make()->pdfView('pdf.invoice')
                    ->defaultRecipient(fn (Invoice $record) => $record->customer_email),
                SavePdfAction::make()->pdfView('pdf.invoice')->saveToColumn('pdf_path'),
            ]),
        ])
        ->toolbarActions([
            BulkDownloadPdfAction::make()->pdfView('pdf.invoice'),
        ]);
}
```

On a View or Edit page, put the same actions in `getHeaderActions()`.

If you don't set `pdfView()`, a built-in view lists the record's attributes (hidden attributes are left out). This
lets you try the plugin before you write your own view.

### Write the Blade view

Create a starter view:

```bash
php artisan make:record-pdf-view invoice --preset=invoice
```

This creates `resources/views/pdf/invoice.blade.php`. The view receives `$record`, plus anything you pass with
`pdfData()`:

```php
DownloadPdfAction::make()
    ->pdfView('pdf.invoice')
    ->pdfData(fn (Invoice $record) => [
        'invoice' => [
            'number' => $record->number,
            'date' => $record->issued_at->toDateString(),
            'currency' => 'USD',
            'from' => [config('app.name'), '1 Main Street'],
            'to' => [$record->customer->name, $record->customer->address],
            'items' => $record->lines->map(fn ($line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
            ])->all(),
            'tax_rate' => 10,
        ],
    ]);
```

You can also use the invoice preset (`filament-record-pdf::presets.invoice`) directly, without copying it.

dompdf handles HTML tables, floats and most of CSS 2.1, but not flexbox or grid. Use tables for layout, and the
bundled "DejaVu Sans" font for non-Latin text. spatie/laravel-pdf's `@pageBreak` directive works in these views.

### Shared options

Every action accepts:

```php
->pdfView('pdf.invoice')                                  // string or fn (Model $record)
->pdfData(fn (Invoice $record) => [...])                  // merged with ['record' => $record]
->pdfFilename(fn (Invoice $record) => "Invoice {$record->number}")  // becomes invoice-inv-1042.pdf
->pdfFormat('letter')                                     // a4 (default), letter, legal, a3, a5 ...
->pdfLandscape()
->pdfDriver('browsershot')                                // per action; the default comes from config
->modifyPdfUsing(fn (PdfBuilder $pdf, Invoice $record) => $pdf->margins(10, 10, 10, 10))
->pdfAbility(['downloadPdf', 'view'])                     // see Authorization
->authorizePdfUsing(fn (Invoice $record, ?User $user) => $user?->is($record->owner))
```

File names are cleaned to lowercase letters, digits and dashes, and `.pdf` is added. Record data such as
`../../.env` in an invoice number can never become a path.

### Preview

`PreviewPdfAction` renders the PDF once and shows it in an iframe. The PDF is kept in the cache for `preview_ttl`
minutes (default 10). Its URL is signed, it expires, and it only works for the user who opened the preview. The
modal's download button uses the same file. Hide the button with `->downloadButton(false)`.

### Email

`EmailPdfAction` shows a form with **To** (up to 10 addresses, separated by commas), **Subject** and **Message**.

```php
EmailPdfAction::make()
    ->defaultRecipient(fn (Invoice $record) => $record->customer_email)
    ->defaultSubject(fn (Invoice $record) => "Invoice {$record->number}")
    ->defaultMessage('Please find your invoice attached.')
    ->queueMail()                       // or set filament-record-pdf.mail.queue
    ->sendUsing(fn (array $recipients, RecordPdfMail $mail) => Mail::mailer('postmark')->to($recipients)->send($mail));
```

### Save

```php
SavePdfAction::make()
    ->disk('s3')                                   // default: filament-record-pdf.disk (local)
    ->directory(fn (Invoice $record) => 'invoices/'.$record->issued_at->year)
    ->visibility('private')
    ->saveToColumn('pdf_path')                     // stores the path on the record
    ->overwrite(false)                             // keep old files and add a timestamp to the new one
    ->afterSaving(fn (Invoice $record, string $path, string $disk) => /* ... */);
```

The directory must be relative to the disk root. Absolute paths, drive letters, URLs and `.` or `..` segments throw
an exception before anything is written.

To save into a [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary) collection instead
(the model must implement `HasMedia`):

```php
SavePdfAction::make()->toMediaCollection('pdfs');
```

### Bulk ZIP

```php
BulkDownloadPdfAction::make()
    ->pdfView('pdf.invoice')
    ->zipFilename(fn () => 'invoices-'.now()->toDateString())
    ->queueAfter(25);   // null never queues, 0 always queues
```

For up to `queue_after` records (default 25), the ZIP is built and downloaded straight away. Above that, the
plugin renders the HTML during the request, and a queued job renders the PDFs, stores the ZIP on the configured
disk, and sends the user a Filament database notification with a download link. The link is signed, expires after
`bulk.link_hours` (default 24), and only works for that user.

Queueing needs `->databaseNotifications()` on the panel and a running queue worker. The ZIP is always built straight
away when the panel has no database notifications, or when `modifyPdfUsing()` is set (a closure cannot be queued).
One download can include up to `bulk.max_records` records (default 500). Records the user may not access are
skipped, and the user is told how many were skipped.

Queued ZIPs stay in `zip_directory` (one folder per download) until you delete them, for example with a scheduled
task that removes folders older than a day.

## Authorization

Each record is checked against its model's policy. The first ability that the policy defines decides:

| Action | Abilities, in order |
|---|---|
| Preview | `viewPdf`, `downloadPdf`, `view` |
| Download, bulk download | `downloadPdf`, `view` |
| Email | `emailPdf`, `view` |
| Save | `savePdf`, `update` |

So `InvoicePolicy::view()` already guards downloads. Add `downloadPdf()` to the policy if users may view an invoice
but not download it. If the model has no policy, or the policy defines none of these methods, access to the panel is
the only check. Actions the user may not use are hidden. Change the abilities with `->pdfAbility()`, or replace the
check with `->authorizePdfUsing()`.

## Drivers

| Driver | What the server needs |
|---|---|
| `dompdf` (default) | Nothing extra: pure PHP |
| `browsershot` | `composer require spatie/browsershot`, Node, Puppeteer and Chromium |
| `chrome` | `composer require chrome-php/chrome` and a Chrome binary |
| `gotenberg` | A running [Gotenberg](https://gotenberg.dev) service |
| `cloudflare` | A Cloudflare account with Browser Rendering |
| `weasyprint` | `composer require pontedilana/php-weasyprint` and the WeasyPrint binary |

Set `RECORD_PDF_DRIVER=browsershot` (or `driver` in the config), or use `->pdfDriver()` on a single action. Each
driver's own options are in spatie/laravel-pdf's `config/laravel-pdf.php`. This plugin has its own driver setting,
so laravel-pdf's default (Browsershot) does not apply here unless you choose it.

## Configuration

```php
return [
    'driver' => env('RECORD_PDF_DRIVER', 'dompdf'),
    'format' => 'a4',
    'landscape' => false,
    'default_view' => 'filament-record-pdf::default',
    'disk' => env('RECORD_PDF_DISK', 'local'),
    'directory' => 'record-pdfs',
    'zip_directory' => 'record-pdfs/zips',
    'visibility' => 'private',
    'preview_ttl' => 10,
    'cache_store' => env('RECORD_PDF_CACHE_STORE'),
    'route_prefix' => 'filament-record-pdf',
    'route_middleware' => ['web'],
    'bulk' => ['queue_after' => 25, 'connection' => null, 'queue' => null, 'link_hours' => 24, 'max_records' => 500],
    'mail' => ['queue' => false],
];
```

Publish the translations with `--tag="filament-record-pdf-translations"` and the views with
`--tag="filament-record-pdf-views"`.

## Testing

```bash
composer test
```

`scripts/ci-local.sh` runs Pint, PHPStan and the tests on PHP 8.2 to 8.5, Laravel 12 and 13, and Filament 4 and 5.
It also tests the lowest supported versions of spatie/laravel-pdf and dompdf.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Support

- 🐛 **Bug or feature request**: [open an issue](https://github.com/amjadiqbal/filament-record-pdf/issues)
- 🔒 **Security issue**: please report it privately through GitHub's security tab

## Need this customised, or something built?

I'm available for custom development, package integration, and technical consulting.

[![Hire me on Upwork](https://img.shields.io/badge/Hire%20me-Upwork-6FDA44?style=flat-square&logo=upwork&logoColor=white)](https://www.upwork.com/freelancers/amjadkhatri)

## More packages

This is part of a family of open-source packages. See [all of them](https://github.com/amjadiqbal?tab=repositories).

| | |
|---|---|
| [filament-xlsx-import](https://github.com/amjadiqbal/filament-xlsx-import) | Excel (.xlsx) and .ods files in Filament's core ImportAction |
| [filament-deleted-records](https://github.com/amjadiqbal/filament-deleted-records) | A recycle bin for spatie/laravel-deleted-models in Filament |
| [filament-change-requests](https://github.com/amjadiqbal/filament-change-requests) | Hold record edits in Filament for approval, with a field-by-field diff |

## Credits

- [Amjad Iqbal](https://github.com/amjadiqbal)
- PDF rendering: [spatie/laravel-pdf](https://github.com/spatie/laravel-pdf) by Spatie (MIT) and
  [dompdf](https://github.com/dompdf/dompdf) (LGPL-2.1). This plugin is not affiliated with or endorsed by Spatie.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
