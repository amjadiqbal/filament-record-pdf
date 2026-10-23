<?php

namespace AmjadIqbal\FilamentRecordPdf\Http;

use AmjadIqbal\FilamentRecordPdf\Support\SafePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ZipDownloadController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $disk = (string) $request->query('disk');
        $path = (string) $request->query('path');
        $guard = $request->query('guard');

        abort_unless($disk === config('filament-record-pdf.disk', 'local'), 404);
        abort_unless(str_ends_with($path, '.zip') && SafePath::isInside($path, (string) config('filament-record-pdf.zip_directory', 'record-pdfs/zips')), 404);
        abort_unless((string) Auth::guard(is_string($guard) ? $guard : null)->id() === (string) $request->query('user'), 403);

        $storage = Storage::disk($disk);

        abort_unless($storage->exists($path), 404);

        return $storage->download($path, basename($path), ['Content-Type' => 'application/zip']);
    }
}
