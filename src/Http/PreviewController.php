<?php

namespace AmjadIqbal\FilamentRecordPdf\Http;

use AmjadIqbal\FilamentRecordPdf\Support\PreviewStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class PreviewController
{
    public function __invoke(Request $request, string $key): Response
    {
        $entry = PreviewStore::get($key);

        abort_if($entry === null, 404);

        if ($entry['user'] !== null) {
            abort_unless((string) Auth::guard($entry['guard'])->id() === (string) $entry['user'], 403);
        }

        $disposition = HeaderUtils::makeDisposition(
            $request->boolean('download') ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $entry['filename'],
            $entry['filename'],
        );

        return response($entry['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ]);
    }
}
