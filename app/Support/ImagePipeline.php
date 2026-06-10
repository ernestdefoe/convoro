<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Processes uploaded images: downscales to a sane max and (re-)encodes to WebP
 * via GD. Returns the public URL + dimensions. A single source upload is
 * downscaled to the display cap here; the same approach powers the PWA icon
 * set generation in P5.
 */
class ImagePipeline
{
    private const MAX_WIDTH = 1600;
    private const QUALITY = 82;

    public static function processPost(UploadedFile $file): array
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($src === false) {
            throw new RuntimeException('Unsupported or corrupt image.');
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = $w > self::MAX_WIDTH ? self::MAX_WIDTH / $w : 1.0;
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagewebp($dst, null, self::QUALITY);
        $webp = (string) ob_get_clean();


        $path = 'uploads/' . date('Y/m') . '/' . Str::random(24) . '.webp';
        Storage::disk('public')->put($path, $webp, 'public');

        return [
            'url' => Storage::disk('public')->url($path),
            'width' => $nw,
            'height' => $nh,
        ];
    }
}
