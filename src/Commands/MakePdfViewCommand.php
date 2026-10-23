<?php

namespace AmjadIqbal\FilamentRecordPdf\Commands;

use AmjadIqbal\FilamentRecordPdf\Support\SafePath;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

class MakePdfViewCommand extends Command
{
    protected $signature = 'make:record-pdf-view {name : For example "invoice" or "orders/packing-slip"} {--preset=record : record or invoice} {--force : Overwrite an existing view}';

    protected $description = 'Create a dompdf-friendly Blade view for Record PDF actions in resources/views/pdf';

    public function handle(Filesystem $files): int
    {
        $preset = (string) $this->option('preset');

        if (! in_array($preset, ['record', 'invoice'], true)) {
            $this->error('The preset must be "record" or "invoice".');

            return self::FAILURE;
        }

        try {
            $relative = SafePath::directory(str_replace('.', '/', (string) $this->argument('name')));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($relative === '') {
            $this->error('Give the view a name.');

            return self::FAILURE;
        }

        $path = resource_path('views/pdf/'.$relative.'.blade.php');

        if ($files->exists($path) && ! $this->option('force')) {
            $this->error("[{$path}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->copy(__DIR__.'/../../stubs/'.$preset.'.blade.php.stub', $path);

        $view = 'pdf.'.str_replace('/', '.', $relative);

        $this->info("Created [{$path}].");
        $this->line("Use it with ->pdfView('{$view}').");

        return self::SUCCESS;
    }
}
