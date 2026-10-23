<?php

namespace AmjadIqbal\FilamentRecordPdf\Jobs;

use AmjadIqbal\FilamentRecordPdf\RecordPdf;
use AmjadIqbal\FilamentRecordPdf\Support\PdfRenderOptions;
use AmjadIqbal\FilamentRecordPdf\Support\SafePath;
use AmjadIqbal\FilamentRecordPdf\Support\ZipWriter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Renders pre-rendered HTML files to PDFs, zips them on the disk, and sends the user a database
 * notification with a signed download link that only they can use.
 */
class BuildPdfZip implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * @param  array<int, array{html: string, name: string, options: PdfRenderOptions}>  $entries
     */
    public function __construct(
        public string $disk,
        public string $workDirectory,
        public string $zipName,
        public array $entries,
        public string $notifiableType,
        public int|string $notifiableId,
        public ?string $guard = null,
        public ?string $panelId = null,
        public ?string $rootUrl = null,
    ) {}

    public function handle(): void
    {
        $storage = Storage::disk($this->disk);
        $zip = new ZipWriter;

        foreach ($this->entries as $entry) {
            $zip->add($entry['name'], RecordPdf::render((string) $storage->get($entry['html']), $entry['options']));
        }

        $local = $zip->close();
        $path = SafePath::join($this->workDirectory, SafePath::filename($this->zipName, 'zip', 'pdfs'));

        $stream = fopen($local, 'r');
        $storage->writeStream($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($local);

        $storage->deleteDirectory($this->workDirectory.'/html');

        // A queue worker builds URLs from APP_URL, so sign the path only and put it under the address the user
        // was on when they asked. The route checks the relative signature.
        $relative = URL::temporarySignedRoute('filament-record-pdf.zip', now()->addHours((int) config('filament-record-pdf.bulk.link_hours', 24)), [
            'disk' => $this->disk,
            'path' => $path,
            'user' => $this->notifiableId,
            'guard' => $this->guard,
        ], absolute: false);

        $url = $this->rootUrl ? rtrim($this->rootUrl, '/').$relative : url($relative);

        $this->notify(
            Notification::make()
                ->success()
                ->title(__('filament-record-pdf::record-pdf.bulk.ready', ['count' => count($this->entries)]))
                ->body($this->zipName)
                ->actions([
                    Action::make('download')
                        ->label(__('filament-record-pdf::record-pdf.bulk.download_zip'))
                        ->url($url)
                        ->button(),
                ])
        );
    }

    public function failed(?Throwable $exception): void
    {
        Storage::disk($this->disk)->deleteDirectory($this->workDirectory);

        $this->notify(
            Notification::make()
                ->danger()
                ->title(__('filament-record-pdf::record-pdf.bulk.failed'))
                ->body($exception?->getMessage())
        );
    }

    protected function notify(Notification $notification): void
    {
        $user = $this->notifiableType::query()->find($this->notifiableId);

        if ($user !== null) {
            $notification->sendToDatabase($user);
        }
    }
}
