<?php

declare(strict_types=1);

/**
 * Amjad's rule: no brown anywhere. Checks every hex colour in the views, stubs and art sources, and
 * Tailwind classes that render brown (stone-*, amber/orange/yellow 700+, red-900).
 */
function recordPdfIsBrown(string $hex): bool
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split(substr($hex, 0, 6), 2));
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $v = $max;
    $s = $max == 0 ? 0 : ($max - $min) / $max;
    if ($max == $min) {
        return false;
    }
    $h = match ($max) {
        $r => 60 * fmod((($g - $b) / ($max - $min)), 6),
        $g => 60 * ((($b - $r) / ($max - $min)) + 2),
        default => 60 * ((($r - $g) / ($max - $min)) + 4),
    };
    $h = $h < 0 ? $h + 360 : $h;

    return ($h <= 50 || $h >= 345) && $s > 0.25 && $v > 0.15 && $v < 0.75;
}

it('uses no brown colours', function (): void {
    $root = dirname(__DIR__, 2);
    $files = array_merge(
        glob($root.'/resources/views/*.blade.php'),
        glob($root.'/resources/views/*/*.blade.php'),
        glob($root.'/stubs/*.stub'),
        glob($root.'/art/*.svg') ?: [],
        glob($root.'/art/*.py') ?: [],
    );

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $text = file_get_contents($file);
        preg_match_all('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', $text, $m);
        foreach ($m[0] as $hex) {
            expect(recordPdfIsBrown($hex))->toBeFalse("{$hex} in ".basename($file).' is brown');
        }
        expect($text)->not->toMatch('/\b(?:bg|text|border|fill|stroke)-(?:stone-\d+|(?:amber|orange|yellow)-(?:7|8|9)\d\d|red-900)\b/');
    }
});
