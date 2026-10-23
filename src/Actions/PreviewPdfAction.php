<?php

namespace AmjadIqbal\FilamentRecordPdf\Actions;

use AmjadIqbal\FilamentRecordPdf\Concerns\InteractsWithRecordPdf;
use AmjadIqbal\FilamentRecordPdf\Support\Authorizer;
use AmjadIqbal\FilamentRecordPdf\Support\PreviewStore;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class PreviewPdfAction extends Action
{
    use InteractsWithRecordPdf;

    /** @var array<string, array{key: string, inline: string, download: string}> */
    protected array $previewUrls = [];

    protected bool $hasDownloadButton = true;

    public static function getDefaultName(): ?string
    {
        return 'previewPdf';
    }

    protected function getDefaultPdfAbilities(): array
    {
        return ['viewPdf', 'downloadPdf', 'view'];
    }

    public function downloadButton(bool $condition = true): static
    {
        $this->hasDownloadButton = $condition;

        return $this;
    }

    /**
     * Render once per record and request, keep the bytes in the cache behind signed, user-bound URLs.
     *
     * @return array{key: string, inline: string, download: string}
     */
    public function getPreviewUrls(Model $record): array
    {
        $id = $record::class.':'.$record->getKey();

        return $this->previewUrls[$id] ??= PreviewStore::put(
            $this->renderPdf($record),
            $this->getPdfFilename($record),
            Authorizer::user()?->getAuthIdentifier(),
            Authorizer::guardName(),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-record-pdf::record-pdf.preview.label'));

        $this->icon(Heroicon::OutlinedEye);

        $this->authorize(fn (?Model $record): bool => $record === null || $this->canAccessRecordPdf($record));

        $this->modalHeading(fn (Model $record): string => $this->getPdfFilename($record));

        $this->modalWidth(Width::FiveExtraLarge);

        $this->modalContent(fn (Model $record): View => view('filament-record-pdf::preview', [
            'url' => $this->getPreviewUrls($record)['inline'],
            'title' => $this->getPdfFilename($record),
        ]));

        $this->modalSubmitAction(false);

        $this->modalCancelActionLabel(__('filament-record-pdf::record-pdf.preview.close'));

        $this->extraModalFooterActions(fn (Model $record): array => $this->hasDownloadButton ? [
            Action::make('downloadPreviewedPdf')
                ->label(__('filament-record-pdf::record-pdf.download.label'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url($this->getPreviewUrls($record)['download']),
        ] : []);

        $this->action(fn () => null);
    }
}
