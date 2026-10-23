<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentRecordPdf\Tests\Fixtures;

use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;
}
