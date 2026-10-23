# Changelog

All notable changes to `filament-record-pdf` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
