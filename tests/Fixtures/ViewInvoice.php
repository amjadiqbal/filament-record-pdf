<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentRecordPdf\Tests\Fixtures;

use AmjadIqbal\FilamentRecordPdf\Actions\DownloadPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\PreviewPdfAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getHeaderActions(): array
    {
        return [
            PreviewPdfAction::make(),
            DownloadPdfAction::make()->pdfFilename(fn (Invoice $record) => 'Invoice '.$record->number),
        ];
    }
}
