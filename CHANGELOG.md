# Changelog

All notable changes to `filament-record-pdf` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.1] - 2026-10-24

### Fixed
- The bulk ZIP action is now hidden when the model policy's `downloadAnyPdf()` or `viewAny()` denies (new `pdfAnyAbility()`); it used to show and only skip records when run.
- The invoice preset embeds a local `logo` file as a data URI, so dompdf's chroot no longer drops it.
- The preview action now reports a clear error when the cache store refuses a large PDF, instead of an empty preview.

### Changed
- README: recipient separators, the full `sendUsing()` closure signature, the no-user case for queued ZIPs, bulk authorization, image embedding and cache store size.
- Composer and GitHub homepage point at https://amjad.com.pk/filament/plugins/filament-record-pdf.

## [1.0.0] - 2026-10-23

### Added
- `PreviewPdfAction`: the PDF in a modal, served from the cache behind a signed URL bound to the user, with a download button.
- `DownloadPdfAction`, `EmailPdfAction` (up to 10 recipients, optional queueing) and `SavePdfAction` (disk, column or media library collection).
- `BulkDownloadPdfAction`: a ZIP of PDFs for the selected records, built on the queue above `bulk.queue_after` with a database notification and a private download link.
- Shared options on every action: `pdfView()`, `pdfData()`, `pdfFilename()`, `pdfFormat()`, `pdfLandscape()`, `pdfDriver()`, `modifyPdfUsing()`, `pdfAbility()`, `authorizePdfUsing()`.
- Policy checks per record (`viewPdf`/`downloadPdf`/`emailPdf`/`savePdf`, falling back to `view` or `update`).
- dompdf by default, any spatie/laravel-pdf driver optional. A built-in attribute view and an invoice preset.
- `make:record-pdf-view` command.
- File names and save directories are cleaned and checked against path traversal.
