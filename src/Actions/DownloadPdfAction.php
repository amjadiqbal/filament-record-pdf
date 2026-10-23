<?php

namespace AmjadIqbal\FilamentRecordPdf\Actions;

use AmjadIqbal\FilamentRecordPdf\Concerns\InteractsWithRecordPdf;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPdfAction extends Action
{
    use InteractsWithRecordPdf;

    public static function getDefaultName(): ?string
    {
        return 'downloadPdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-record-pdf::record-pdf.download.label'));

        $this->icon(Heroicon::OutlinedArrowDownTray);

        $this->authorize(fn (?Model $record): bool => $record === null || $this->canAccessRecordPdf($record));

        $this->action(function (Model $record): StreamedResponse {
            $pdf = $this->renderPdf($record);

            return response()->streamDownload(function () use ($pdf): void {
                echo $pdf;
            }, $this->getPdfFilename($record), ['Content-Type' => 'application/pdf']);
        });
    }
}
