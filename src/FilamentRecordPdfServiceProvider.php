<?php

namespace AmjadIqbal\FilamentRecordPdf;

use AmjadIqbal\FilamentRecordPdf\Commands\MakePdfViewCommand;
use AmjadIqbal\FilamentRecordPdf\Http\PreviewController;
use AmjadIqbal\FilamentRecordPdf\Http\ZipDownloadController;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentRecordPdfServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-record-pdf';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasCommand(MakePdfViewCommand::class);
    }

    public function packageBooted(): void
    {
        Route::middleware(config('filament-record-pdf.route_middleware', ['web']))
            ->prefix(config('filament-record-pdf.route_prefix', 'filament-record-pdf'))
            ->group(function (): void {
                Route::get('preview/{key}', PreviewController::class)
                    ->middleware('signed')
                    ->where('key', '[A-Za-z0-9]{40}')
                    ->name('filament-record-pdf.preview');

                Route::get('zip', ZipDownloadController::class)
                    ->middleware('signed')
                    ->name('filament-record-pdf.zip');
            });
    }
}
