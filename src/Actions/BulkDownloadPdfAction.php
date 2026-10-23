<?php

namespace AmjadIqbal\FilamentRecordPdf\Actions;

use AmjadIqbal\FilamentRecordPdf\Concerns\InteractsWithRecordPdf;
use AmjadIqbal\FilamentRecordPdf\Jobs\BuildPdfZip;
use AmjadIqbal\FilamentRecordPdf\Support\Authorizer;
use AmjadIqbal\FilamentRecordPdf\Support\SafePath;
use AmjadIqbal\FilamentRecordPdf\Support\ZipWriter;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkDownloadPdfAction extends BulkAction
{
    use InteractsWithRecordPdf;

    protected string|Closure|null $zipFilename = null;

    protected int|Closure|null $queueAfter = null;

    protected bool $queueAfterIsSet = false;

    public static function getDefaultName(): ?string
    {
        return 'downloadPdfs';
    }

    public function zipFilename(string|Closure|null $filename): static
    {
        $this->zipFilename = $filename;

        return $this;
    }

    /**
     * Build on the queue when more than this many records are selected. null never queues, 0 always does.
     */
    public function queueAfter(int|Closure|null $count): static
    {
        $this->queueAfter = $count;
        $this->queueAfterIsSet = true;

        return $this;
    }

    public function getZipFilename(): string
    {
        return SafePath::filename((string) ($this->evaluate($this->zipFilename) ?? 'pdfs-'.now()->format('Y-m-d-His')), 'zip', 'pdfs');
    }

    public function shouldQueue(int $count): bool
    {
        $threshold = $this->queueAfterIsSet ? $this->evaluate($this->queueAfter) : config('filament-record-pdf.bulk.queue_after', 25);

        if ($threshold === null || $count <= (int) $threshold) {
            return false;
        }

        // A queued ZIP is announced with a database notification, and a closure cannot be queued.
        return (bool) Filament::getCurrentOrDefaultPanel()?->hasDatabaseNotifications()
            && Authorizer::user() !== null
            && ! $this->hasPdfModifier();
    }

    /**
     * File names that stay unique inside one ZIP.
     *
     * @param  array<string, true>  $used
     */
    public static function uniqueName(string $filename, array &$used): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $name = $filename;

        for ($i = 2; isset($used[$name]); $i++) {
            $name = "{$base}-{$i}.pdf";
        }

        $used[$name] = true;

        return $name;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-record-pdf::record-pdf.bulk.label'));

        $this->icon(Heroicon::OutlinedArchiveBoxArrowDown);

        $this->deselectRecordsAfterCompletion();

        $this->action(function (Collection $records): ?StreamedResponse {
            $max = (int) config('filament-record-pdf.bulk.max_records', 500);

            if ($records->count() > $max) {
                Notification::make()->danger()
                    ->title(__('filament-record-pdf::record-pdf.bulk.too_many', ['max' => $max]))
                    ->send();

                return null;
            }

            $allowed = $records->filter(fn (Model $record): bool => $this->canAccessRecordPdf($record))->values();
            $skipped = $records->count() - $allowed->count();

            if ($allowed->isEmpty()) {
                Notification::make()->danger()
                    ->title(__('filament-record-pdf::record-pdf.bulk.none_allowed'))
                    ->send();

                return null;
            }

            if (! class_exists(\ZipArchive::class)) {
                Notification::make()->danger()
                    ->title(__('filament-record-pdf::record-pdf.bulk.no_zip'))
                    ->send();

                return null;
            }

            $zipName = $this->getZipFilename();

            if ($this->shouldQueue($allowed->count())) {
                $this->dispatchZipJob($allowed, $zipName);

                Notification::make()->info()
                    ->title(__('filament-record-pdf::record-pdf.bulk.queued', ['count' => $allowed->count()]))
                    ->body($skipped ? __('filament-record-pdf::record-pdf.bulk.skipped', ['count' => $skipped]) : null)
                    ->send();

                return null;
            }

            $zip = new ZipWriter;
            $used = [];

            foreach ($allowed as $record) {
                $zip->add(static::uniqueName($this->getPdfFilename($record), $used), $this->renderPdf($record));
            }

            $path = $zip->close();

            if ($skipped) {
                Notification::make()->warning()
                    ->title(__('filament-record-pdf::record-pdf.bulk.skipped', ['count' => $skipped]))
                    ->send();
            }

            return response()->streamDownload(function () use ($path): void {
                readfile($path);
                @unlink($path);
            }, $zipName, ['Content-Type' => 'application/zip']);
        });
    }

    /**
     * Blade is rendered now, while closures and the request are available; the PDFs are rendered by the job.
     *
     * @param  Collection<int, Model>  $records
     */
    protected function dispatchZipJob(Collection $records, string $zipName): void
    {
        $disk = (string) config('filament-record-pdf.disk', 'local');
        $root = SafePath::directory((string) config('filament-record-pdf.zip_directory', 'record-pdfs/zips'));
        $work = SafePath::join($root, (string) Str::uuid());
        $storage = Storage::disk($disk);
        $entries = [];
        $used = [];

        foreach ($records as $i => $record) {
            $htmlPath = SafePath::join($work.'/html', $i.'.html');
            $storage->put($htmlPath, $this->renderPdfHtml($record));

            $entries[] = [
                'html' => $htmlPath,
                'name' => static::uniqueName($this->getPdfFilename($record), $used),
                'options' => $this->getPdfRenderOptions($record),
            ];
        }

        $user = Authorizer::user();

        $job = new BuildPdfZip(
            disk: $disk,
            workDirectory: $work,
            zipName: $zipName,
            entries: $entries,
            notifiableType: $user::class,
            notifiableId: $user->getAuthIdentifier(),
            guard: Authorizer::guardName(),
            panelId: Filament::getCurrentOrDefaultPanel()?->getId(),
        );

        dispatch($job
            ->onConnection(config('filament-record-pdf.bulk.connection'))
            ->onQueue(config('filament-record-pdf.bulk.queue')));
    }
}
