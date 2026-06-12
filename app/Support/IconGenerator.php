<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\File;

/**
 * Generates the full PWA / favicon icon set from a single source image
 * (the "one upload → every size" requirement). With no source it draws the
 * default Convoro mark (indigo gradient + three white dots). Pure GD so it
 * runs on shared hosting; outputs PNG for maximum installability.
 */
class IconGenerator
{
    /** filename => pixel size (square). */
    public const SIZES = [
        'icon-192.png' => 192,
        'icon-512.png' => 512,
        'maskable-512.png' => 512,
        'apple-touch-icon.png' => 180,
        'favicon-32.png' => 32,
    ];

    private const DIR = 'icons';

    /**
     * Build every icon size from an optional source image binary.
     *
     * @return array<string, string> filename => public URL
     */
    public static function generate(?string $sourceBinary = null): array
    {
        $master = $sourceBinary !== null
            ? self::squareCover($sourceBinary, 512)
            : self::defaultMaster(512);

        $dir = public_path(self::DIR);
        File::ensureDirectoryExists($dir);

        $urls = [];
        foreach (self::SIZES as $name => $size) {
            $img = imagecreatetruecolor($size, $size);
            imagecopyresampled($img, $master, 0, 0, 0, 0, $size, $size, imagesx($master), imagesy($master));
            imagepng($img, $dir.DIRECTORY_SEPARATOR.$name, 6);
            $urls[$name] = '/'.self::DIR.'/'.$name;
        }

        // Bump the cache-busting revision so browsers re-fetch the new favicon/icons.
        Settings::set('icons.rev', (string) now()->timestamp);

        return $urls;
    }

    /** Decode a source image and center-crop it to a square of $size. */
    private static function squareCover(string $binary, int $size): GdImage
    {
        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return self::defaultMaster($size);
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $side = min($sw, $sh);
        $sx = (int) (($sw - $side) / 2);
        $sy = (int) (($sh - $side) / 2);

        $dst = imagecreatetruecolor($size, $size);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);

        return $dst;
    }

    /**
     * Draw the default Convoro mark: a brand-indigo→violet gradient tile with a
     * bold white "C" speech-bubble (an open ring + a chat tail dot). Matches the
     * in-app SVG logo so the favicon / PWA / app icon are one consistent brand.
     */
    private static function defaultMaster(int $size): GdImage
    {
        // Prefer the committed brand master (speech-bubble mark) so regeneration
        // stays on-brand; fall back to the GD-drawn mark if it's missing.
        $brand = resource_path('brand/convoro-icon-512.png');
        if (is_file($brand) && ($bin = @file_get_contents($brand)) !== false) {
            return self::squareCover($bin, $size);
        }

        $img = imagecreatetruecolor($size, $size);

        // Brand gradient #5B5BD6 → #8B5CF6 along the diagonal.
        [$r1, $g1, $b1] = [0x5B, 0x5B, 0xD6];
        [$r2, $g2, $b2] = [0x8B, 0x5C, 0xF6];
        $max = 2 * ($size - 1);
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $t = ($x + $y) / $max;
                $c = imagecolorallocate(
                    $img,
                    (int) round($r1 + ($r2 - $r1) * $t),
                    (int) round($g1 + ($g2 - $g1) * $t),
                    (int) round($b1 + ($b2 - $b1) * $t),
                );
                imagesetpixel($img, $x, $y, $c);
            }
        }

        // White "C" ring, open on the right, with rounded caps.
        $white = imagecolorallocate($img, 255, 255, 255);
        $cx = $size / 2;
        $cy = $size / 2;
        $outer = $size * 0.33;
        $inner = $size * 0.21;
        $mid = ($outer + $inner) / 2;
        $cap = ($outer - $inner) / 2;
        $open = 0.62; // half-angle (rad) of the right-side opening
        $lo = (int) floor($cx - $outer);
        $hi = (int) ceil($cx + $outer);
        for ($x = $lo; $x <= $hi; $x++) {
            for ($y = $lo; $y <= $hi; $y++) {
                $dx = $x - $cx;
                $dy = $y - $cy;
                $d = sqrt($dx * $dx + $dy * $dy);
                if ($d > $outer || $d < $inner) {
                    continue;
                }
                if (abs(atan2($dy, $dx)) < $open) {
                    continue; // the opening → reads as a "C"
                }
                imagesetpixel($img, $x, $y, $white);
            }
        }

        // Round the two cap ends of the "C".
        $capD = (int) round($cap * 2);
        imagefilledellipse($img, (int) round($cx + $mid * cos(-$open)), (int) round($cy + $mid * sin(-$open)), $capD, $capD, $white);
        imagefilledellipse($img, (int) round($cx + $mid * cos($open)), (int) round($cy + $mid * sin($open)), $capD, $capD, $white);

        // Speech bubble centered inside the "C" (rounded rect + tail).
        $bw = $size * 0.26;
        $bh = $size * 0.165;
        $br = (int) round($size * 0.05);
        $bcx = $cx;
        $bcy = $cy - $size * 0.015;
        $x1 = (int) round($bcx - $bw / 2);
        $x2 = (int) round($bcx + $bw / 2);
        $y1 = (int) round($bcy - $bh / 2);
        $y2 = (int) round($bcy + $bh / 2);
        imagefilledrectangle($img, $x1 + $br, $y1, $x2 - $br, $y2, $white);
        imagefilledrectangle($img, $x1, $y1 + $br, $x2, $y2 - $br, $white);
        foreach ([[$x1 + $br, $y1 + $br], [$x2 - $br, $y1 + $br], [$x1 + $br, $y2 - $br], [$x2 - $br, $y2 - $br]] as [$ex, $ey]) {
            imagefilledellipse($img, $ex, $ey, $br * 2, $br * 2, $white);
        }
        $tx = (int) round($bcx - $size * 0.055);
        imagefilledpolygon($img, [
            $tx, $y2 - 2,
            $tx, (int) round($y2 + $size * 0.10),
            $tx + (int) round($size * 0.10), $y2 - 2,
        ], $white);

        return $img;
    }
}
