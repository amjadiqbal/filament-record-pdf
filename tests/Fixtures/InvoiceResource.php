<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentRecordPdf\Tests\Fixtures;

use AmjadIqbal\FilamentRecordPdf\Actions\BulkDownloadPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\DownloadPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\EmailPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\PreviewPdfAction;
use AmjadIqbal\FilamentRecordPdf\Actions\SavePdfAction;
use Closure;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    /** @var array<string, Closure> action name => configure callback, set by tests */
    public static array $configure = [];

    public static function configureAction(Action $action): Action
    {
        if ($callback = static::$configure[$action->getName()] ?? null) {
            $callback($action);
        }

        return $action;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('customer'),
            ])
            ->recordActions([
                static::configureAction(PreviewPdfAction::make()),
                static::configureAction(DownloadPdfAction::make()),
                static::configureAction(EmailPdfAction::make()->defaultRecipient(fn (Invoice $record) => $record->email)),
                static::configureAction(SavePdfAction::make()),
            ])
            ->toolbarActions([
                static::configureAction(BulkDownloadPdfAction::make()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
