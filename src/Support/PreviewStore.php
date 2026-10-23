<?php

namespace AmjadIqbal\FilamentRecordPdf\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Keeps a rendered PDF for a few minutes so the preview modal's iframe (and its download button)
 * can load it without rendering twice. Each entry is bound to the user who opened it.
 */
class PreviewStore
{
    public const PREFIX = 'filament-record-pdf:preview:';

    /**
     * @return array{key: string, inline: string, download: string}
     */
    public static function put(string $pdf, string $filename, int|string|null $userId, ?string $guard): array
    {
        $key = Str::random(40);
        $minutes = max(1, (int) config('filament-record-pdf.preview_ttl', 10));

        static::cache()->put(static::PREFIX.$key, [
            'pdf' => base64_encode($pdf),
            'filename' => $filename,
            'user' => $userId,
            'guard' => $guard,
        ], now()->addMinutes($minutes));

        $expires = now()->addMinutes($minutes);

        return [
            'key' => $key,
            'inline' => URL::temporarySignedRoute('filament-record-pdf.preview', $expires, ['key' => $key]),
            'download' => URL::temporarySignedRoute('filament-record-pdf.preview', $expires, ['key' => $key, 'download' => 1]),
        ];
    }

    /**
     * @return array{pdf: string, filename: string, user: int|string|null, guard: ?string}|null
     */
    public static function get(string $key): ?array
    {
        $entry = static::cache()->get(static::PREFIX.$key);

        if (! is_array($entry) || ! isset($entry['pdf'], $entry['filename'])) {
            return null;
        }

        $entry['pdf'] = (string) base64_decode($entry['pdf'], true);

        return $entry;
    }

    public static function cache(): Repository
    {
        return Cache::store(config('filament-record-pdf.cache_store'));
    }
}
