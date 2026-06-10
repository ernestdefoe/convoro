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

    /** Draw the default Convoro mark: diagonal indigo→violet gradient + three white dots. */
    private static function defaultMaster(int $size): GdImage
    {
        $img = imagecreatetruecolor($size, $size);

        // gradient #6366f1 → #a855f7 along the diagonal
        [$r1, $g1, $b1] = [0x63, 0x66, 0xf1];
        [$r2, $g2, $b2] = [0xa8, 0x55, 0xf7];
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

        // three white dots in a triangle (the brand mark)
        $white = imagecolorallocate($img, 255, 255, 255);
        $r = (int) round($size * 0.09);
        $dots = [
            [0.50, 0.34], // top
            [0.35, 0.64], // bottom-left
            [0.65, 0.64], // bottom-right
        ];
        foreach ($dots as [$fx, $fy]) {
            imagefilledellipse($img, (int) round($size * $fx), (int) round($size * $fy), $r * 2, $r * 2, $white);
        }

        return $img;
    }
}
