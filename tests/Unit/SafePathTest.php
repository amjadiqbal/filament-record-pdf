<?php

declare(strict_types=1);

use AmjadIqbal\FilamentRecordPdf\Support\SafePath;

it('cleans file names taken from record data', function (?string $input, string $expected): void {
    expect(SafePath::filename($input))->toBe($expected);
})->with([
    ['Invoice INV-1042', 'invoice-inv-1042.pdf'],
    ['../../.env', 'env.pdf'],
    ['..\\..\\windows\\system32', 'windows-system32.pdf'],
    ["evil\0name.pdf", 'evil-name.pdf'],
    ['report.PDF', 'report.pdf'],
    ['Ünïcödé façade', 'unicode-facade.pdf'],
    ['', 'document.pdf'],
    [null, 'document.pdf'],
    ['////', 'document.pdf'],
]);

it('caps long file names', function (): void {
    expect(strlen(SafePath::filename(str_repeat('a', 400))))->toBe(154);
});

it('accepts relative directories', function (): void {
    expect(SafePath::directory('invoices/2026'))->toBe('invoices/2026')
        ->and(SafePath::directory('invoices//2026/'))->toBe('invoices/2026')
        ->and(SafePath::directory('a\\b'))->toBe('a/b')
        ->and(SafePath::directory(''))->toBe('');
});

it('refuses directories that leave the disk root', function (string $directory): void {
    SafePath::directory($directory);
})->with(['../secrets', 'pdfs/../../etc', '/etc', 'C:\\Windows', 's3://bucket', "pdfs\0", 'pdfs/./x', 'pdfs/<script>'])
    ->throws(InvalidArgumentException::class);

it('joins only a plain file name', function (): void {
    expect(SafePath::join('pdfs', 'a.pdf'))->toBe('pdfs/a.pdf')
        ->and(SafePath::join('', 'a.pdf'))->toBe('a.pdf');

    expect(fn () => SafePath::join('pdfs', '../a.pdf'))->toThrow(InvalidArgumentException::class);
});

it('knows whether a path is inside a directory', function (string $path, bool $inside): void {
    expect(SafePath::isInside($path, 'record-pdfs/zips'))->toBe($inside);
})->with([
    ['record-pdfs/zips/abc/pdfs.zip', true],
    ['record-pdfs/zips/pdfs.zip', true],
    ['record-pdfs/zips/../../.env', false],
    ['record-pdfs/zipsx/a.zip', false],
    ['.env', false],
    ['/record-pdfs/zips/a.zip', false],
    ['record-pdfs/zips/./a.zip', false],
]);
