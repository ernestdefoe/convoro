<?php

namespace App\Support;

/**
 * Builds the runtime CSS that overrides the Convoro design tokens (`--c-*`)
 * from admin settings — this is what powers the live theme editor. Emitted in
 * <head> after the compiled stylesheet so :root here wins.
 */
class Theme
{
    /** Font key => [display label, CSS stack, Google Fonts family spec or null]. */
    public const FONTS = [
        'Inter' => ['Inter', "'Inter', ui-sans-serif, system-ui, sans-serif", 'Inter:wght@400;500;600;700;800'],
        'System' => ['System UI', "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", null],
        'Poppins' => ['Poppins', "'Poppins', ui-sans-serif, system-ui, sans-serif", 'Poppins:wght@400;500;600;700;800'],
        'Roboto' => ['Roboto', "'Roboto', ui-sans-serif, system-ui, sans-serif", 'Roboto:wght@400;500;700;900'],
        'Nunito' => ['Nunito Sans', "'Nunito Sans', ui-sans-serif, system-ui, sans-serif", 'Nunito+Sans:wght@400;600;700;800'],
        'Lora' => ['Lora (serif)', "'Lora', Georgia, 'Times New Roman', serif", 'Lora:wght@400;500;600;700'],
    ];

    public static function css(): string
    {
        $primary = self::hexToRgb((string) Settings::get('theme.primary', '#5b5bd6'));
        $radius = (int) Settings::get('theme.radius', 12);
        $container = (int) Settings::get('theme.container', 1240);
        $fontSize = (int) Settings::get('theme.font_size', 16);

        $p600 = self::darken($primary, 0.90);
        $p700 = self::darken($primary, 0.80);
        $soft = self::mixWhite($primary, 0.86);

        $vars = [
            '--c-primary' => self::triplet($primary),
            '--c-primary-600' => self::triplet($p600),
            '--c-primary-700' => self::triplet($p700),
            '--c-primary-soft' => self::triplet($soft),
            '--c-radius' => $radius.'px',
            '--c-container' => $container > 0 ? $container.'px' : '100%',
            '--c-font' => self::fontStack((string) Settings::get('theme.font', 'Inter')),
            '--c-font-size' => max(12, min(20, $fontSize)).'px',
        ];

        $body = '';
        foreach ($vars as $k => $v) {
            $body .= "{$k}:{$v};";
        }

        return ":root{{$body}}";
    }

    public static function fontStack(string $key): string
    {
        return self::FONTS[$key][1] ?? self::FONTS['Inter'][1];
    }

    /** Google Fonts <link> href for the active font, or null (System / Inter handled in app.css). */
    public static function googleFontHref(): ?string
    {
        $key = (string) Settings::get('theme.font', 'Inter');
        $spec = self::FONTS[$key][2] ?? null;
        if ($spec === null || $key === 'Inter') {
            return null; // Inter is already imported by app.css; System needs no font
        }

        return 'https://fonts.googleapis.com/css2?family='.$spec.'&display=swap';
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [91, 91, 214]; // fallback to brand indigo
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Space-separated RGB triplet for `rgb(var(--c-x) / <alpha>)`. */
    private static function triplet(array $rgb): string
    {
        return "{$rgb[0]} {$rgb[1]} {$rgb[2]}";
    }

    private static function darken(array $rgb, float $factor): array
    {
        return array_map(fn ($c) => (int) max(0, min(255, round($c * $factor))), $rgb);
    }

    private static function mixWhite(array $rgb, float $whiteAmount): array
    {
        return array_map(fn ($c) => (int) round($c + (255 - $c) * $whiteAmount), $rgb);
    }
}
