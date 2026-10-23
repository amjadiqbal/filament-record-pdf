<?php

namespace AmjadIqbal\FilamentRecordPdf\Actions;

use AmjadIqbal\FilamentRecordPdf\Concerns\InteractsWithRecordPdf;
use AmjadIqbal\FilamentRecordPdf\Support\SafePath;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Spatie\MediaLibrary\HasMedia;

class SavePdfAction extends Action
{
    use InteractsWithRecordPdf;

    protected string|Closure|null $disk = null;

    protected string|Closure|null $directory = null;

    protected string|Closure|null $visibility = null;

    protected string|Closure|null $column = null;

    protected string|Closure|null $mediaCollection = null;

    protected bool|Closure $overwrite = true;

    protected ?Closure $afterSaving = null;

    public static function getDefaultName(): ?string
    {
        return 'savePdf';
    }

    protected function getDefaultPdfAbilities(): array
    {
        return ['savePdf', 'update'];
    }

    public function disk(string|Closure|null $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Relative to the disk root. Absolute paths and ".." are refused.
     */
    public function directory(string|Closure|null $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function visibility(string|Closure|null $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * Also store the saved path in this column on the record.
     */
    public function saveToColumn(string|Closure|null $column): static
    {
        $this->column = $column;

        return $this;
    }

    /**
     * Save into a spatie/laravel-medialibrary collection on the record instead of a plain disk path.
     */
    public function toMediaCollection(string|Closure|null $collection = 'default'): static
    {
        $this->mediaCollection = $collection;

        return $this;
    }

    /**
     * When false, an existing file is kept and the new one gets a timestamp suffix.
     */
    public function overwrite(bool|Closure $condition = true): static
    {
        $this->overwrite = $condition;

        return $this;
    }

    /**
     * fn (Model $record, string $path, string $disk) after the file is written.
     */
    public function afterSaving(?Closure $callback): static
    {
        $this->afterSaving = $callback;

        return $this;
    }

    public function getDiskName(Model $record): string
    {
        return (string) ($this->evaluateForRecord($this->disk, $record) ?? config('filament-record-pdf.disk', 'local'));
    }

    public function getDirectory(Model $record): string
    {
        return SafePath::directory((string) ($this->evaluateForRecord($this->directory, $record)
            ?? config('filament-record-pdf.directory', 'record-pdfs')));
    }

    /**
     * Write the PDF and return where it went: [disk, path].
     *
     * @return array{0: string, 1: string}
     */
    public function savePdf(Model $record): array
    {
        $pdf = $this->renderPdf($record);
        $filename = $this->getPdfFilename($record);
        $collection = $this->evaluateForRecord($this->mediaCollection, $record);

        if ($collection !== null) {
            if (! interface_exists(HasMedia::class) || ! $record instanceof HasMedia) {
                throw new LogicException(sprintf('%s must implement Spatie\MediaLibrary\HasMedia to save PDFs to a media collection.', $record::class));
            }

            /** @var Model&HasMedia $record */
            $media = $record->addMediaFromString($pdf)
                ->usingFileName($filename)
                ->usingName(pathinfo($filename, PATHINFO_FILENAME))
                ->toMediaCollection((string) $collection);

            $result = [$media->disk, $media->getPathRelativeToRoot()];
        } else {
            $disk = $this->getDiskName($record);
            $path = SafePath::join($this->getDirectory($record), $filename);
            $storage = Storage::disk($disk);

            if (! $this->evaluateForRecord($this->overwrite, $record) && $storage->exists($path)) {
                $path = SafePath::join(dirname($path) === '.' ? '' : dirname($path), pathinfo($filename, PATHINFO_FILENAME).'-'.now()->format('Ymd-His').'.pdf');
            }

            $storage->put($path, $pdf, (string) ($this->evaluateForRecord($this->visibility, $record)
                ?? config('filament-record-pdf.visibility', 'private')));

            $result = [$disk, $path];
        }

        if ($column = $this->evaluateForRecord($this->column, $record)) {
            $record->forceFill([(string) $column => $result[1]])->save();
        }

        if ($this->afterSaving) {
            $this->evaluateForRecord($this->afterSaving, $record, ['path' => $result[1], 'disk' => $result[0]]);
        }

        return $result;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-record-pdf::record-pdf.save.label'));

        $this->icon(Heroicon::OutlinedDocumentArrowDown);

        $this->authorize(fn (?Model $record): bool => $record === null || $this->canAccessRecordPdf($record));

        $this->requiresConfirmation();

        $this->modalIcon(Heroicon::OutlinedDocumentArrowDown);

        $this->modalHeading(fn (Model $record): string => __('filament-record-pdf::record-pdf.save.heading', ['file' => $this->getPdfFilename($record)]));

        $this->modalDescription(__('filament-record-pdf::record-pdf.save.description'));

        $this->action(function (Model $record): void {
            [, $path] = $this->savePdf($record);

            Notification::make()
                ->success()
                ->title(__('filament-record-pdf::record-pdf.save.saved'))
                ->body($path)
                ->send();
        });
    }
}
