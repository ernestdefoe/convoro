<?php

namespace App\Support;

/**
 * Builds the runtime CSS that overrides the Convoro design tokens (`--c-*`)
 * from admin settings — this is what powers the live theme editor. Emitted in
 * <head> after the compiled stylesheet so :root here wins.
 */
class Theme
{
    /**
     * Supported fonts: family name => generic category ('sans' | 'serif' | 'system').
     * All non-system families are loaded live from Google Fonts (css2 endpoint is
     * lenient on weights, so no API key needed). Add families here to offer more.
     */
    public const FONTS = [
        'System' => 'system',
        // sans
        'Inter' => 'sans', 'Roboto' => 'sans', 'Open Sans' => 'sans', 'Lato' => 'sans',
        'Montserrat' => 'sans', 'Poppins' => 'sans', 'Nunito Sans' => 'sans', 'Work Sans' => 'sans',
        'DM Sans' => 'sans', 'Manrope' => 'sans', 'Plus Jakarta Sans' => 'sans', 'Sora' => 'sans',
        'Outfit' => 'sans', 'Space Grotesk' => 'sans', 'Source Sans 3' => 'sans', 'Rubik' => 'sans',
        'Karla' => 'sans', 'Mulish' => 'sans', 'Figtree' => 'sans', 'Albert Sans' => 'sans',
        'Raleway' => 'sans', 'Public Sans' => 'sans', 'IBM Plex Sans' => 'sans', 'Onest' => 'sans',
        // serif
        'Lora' => 'serif', 'Merriweather' => 'serif', 'Playfair Display' => 'serif',
        'Source Serif 4' => 'serif', 'Bitter' => 'serif', 'PT Serif' => 'serif',
        'Roboto Slab' => 'serif', 'Libre Baskerville' => 'serif', 'Crimson Pro' => 'serif',
        'EB Garamond' => 'serif', 'Noto Serif' => 'serif', 'Cormorant' => 'serif',
        // mono
        'JetBrains Mono' => 'mono', 'IBM Plex Mono' => 'mono',
    ];

    /** Options for the admin select: [{value, label, type}]. */
    public static function fontOptions(): array
    {
        $out = [];
        foreach (self::FONTS as $family => $type) {
            $out[] = ['value' => $family, 'label' => $family === 'System' ? 'System default' : $family, 'type' => $type];
        }

        return $out;
    }

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

    public static function fontStack(string $family): string
    {
        $type = self::FONTS[$family] ?? 'sans';
        if ($type === 'system') {
            return "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
        }
        $fallback = match ($type) {
            'serif' => "Georgia, 'Times New Roman', serif",
            'mono' => "ui-monospace, 'SF Mono', Menlo, monospace",
            default => "ui-sans-serif, system-ui, -apple-system, sans-serif",
        };

        return "'{$family}', {$fallback}";
    }

    /** Google Fonts <link> href for the active font, or null (System / Inter handled locally). */
    public static function googleFontHref(): ?string
    {
        $family = (string) Settings::get('theme.font', 'Inter');
        if (! isset(self::FONTS[$family]) || $family === 'System' || $family === 'Inter') {
            return null; // System needs nothing; Inter is already imported by app.css
        }

        return 'https://fonts.googleapis.com/css2?family='
            .str_replace(' ', '+', $family).':wght@400;500;600;700&display=swap';
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
