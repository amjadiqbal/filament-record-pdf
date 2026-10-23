<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentRecordPdf\Tests\Fixtures;

class InvoicePolicy
{
    /** @var array<string, array<int>> ability => denied invoice ids */
    public static array $deny = [];

    public static bool $denyViewAny = false;

    public function viewAny(User $user): bool
    {
        return ! static::$denyViewAny;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return ! in_array($invoice->id, static::$deny['view'] ?? [], true);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return ! in_array($invoice->id, static::$deny['update'] ?? [], true);
    }

    public function downloadPdf(User $user, Invoice $invoice): bool
    {
        return ! in_array($invoice->id, static::$deny['downloadPdf'] ?? [], true);
    }
}
