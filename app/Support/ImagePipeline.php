<?php

namespace App\Support;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Processes uploaded images: downscales to a sane max and (re-)encodes to WebP
 * via GD. Returns the public URL + dimensions. A single source upload is
 * downscaled to the display cap here; the same approach powers the PWA icon
 * set generation in P5.
 *
 * Avatars take a richer path (processAvatar): animated images (APNG / animated
 * GIF / animated WebP) are stored untouched so the animation survives — GD would
 * flatten them to a single frame — and SVGs are accepted after being sanitized
 * so a user can't smuggle a <script> into an avatar.
 */
class ImagePipeline
{
    private const MAX_WIDTH = 1600;
    private const QUALITY = 82;

    private const AVATAR_MAX = 512;
    private const PASSTHROUGH_MAX_BYTES = 8 * 1024 * 1024; // keep in step with the upload validation cap

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

    /**
     * Avatar upload: keeps animation (APNG / animated GIF / WebP) and accepts
     * sanitized SVG; static rasters are downscaled to a small WebP.
     */
    public static function processAvatar(UploadedFile $file): array
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        // SVG — including animated SVG (CSS/SMIL), which survives untouched once sanitized.
        if ($ext === 'svg' || self::looksLikeSvg($bytes)) {
            return self::storeSvg($bytes);
        }

        if (strlen($bytes) > self::PASSTHROUGH_MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'That image is too large (8 MB max).']);
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            throw ValidationException::withMessages(['file' => 'Unsupported or corrupt image.']);
        }

        // Animated raster → store the ORIGINAL so the animation is preserved.
        if (self::isAnimated($bytes)) {
            return self::storeRaw($bytes, self::extForMime($info['mime'] ?? '', $ext), (int) $info[0], (int) $info[1]);
        }

        // Static raster → downscale + WebP.
        return self::raster($bytes, self::AVATAR_MAX);
    }

    private static function looksLikeSvg(string $b): bool
    {
        $head = ltrim(substr($b, 0, 512));

        return stripos($head, '<svg') !== false
            || (str_starts_with($head, '<?xml') && stripos($b, '<svg') !== false);
    }

    private static function storeSvg(string $bytes): array
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($bytes);

        if ($clean === false || trim($clean) === '') {
            throw ValidationException::withMessages(['file' => 'That SVG could not be processed safely.']);
        }

        [$w, $h] = self::svgDimensions($clean);

        $path = 'uploads/' . date('Y/m') . '/' . Str::random(24) . '.svg';
        Storage::disk('public')->put($path, $clean, 'public');

        return [
            'url' => Storage::disk('public')->url($path),
            'width' => $w,
            'height' => $h,
        ];
    }

    /** Best-effort intrinsic size from width/height attrs or the viewBox; default square. */
    private static function svgDimensions(string $svg): array
    {
        if (preg_match('/<svg\b[^>]*>/i', $svg, $tag)) {
            $t = $tag[0];
            if (preg_match('/\bwidth="([\d.]+)/i', $t, $w) && preg_match('/\bheight="([\d.]+)/i', $t, $h)) {
                $ww = (int) round((float) $w[1]);
                $hh = (int) round((float) $h[1]);
                if ($ww > 0 && $hh > 0) {
                    return [$ww, $hh];
                }
            }
            if (preg_match('/viewBox="\s*[\d.+-]+\s+[\d.+-]+\s+([\d.]+)\s+([\d.]+)/i', $t, $vb)) {
                $ww = (int) round((float) $vb[1]);
                $hh = (int) round((float) $vb[2]);
                if ($ww > 0 && $hh > 0) {
                    return [$ww, $hh];
                }
            }
        }

        return [256, 256];
    }

    /** Detect animation across the formats we pass through. */
    private static function isAnimated(string $b): bool
    {
        // APNG: an 'acTL' control chunk before the first 'IDAT'.
        if (str_starts_with($b, "\x89PNG")) {
            $actl = strpos($b, 'acTL');
            $idat = strpos($b, 'IDAT');

            return $actl !== false && ($idat === false || $actl < $idat);
        }

        // Animated GIF: more than one frame / a NETSCAPE looping block.
        if (str_starts_with($b, 'GIF')) {
            return substr_count($b, "\x21\xF9\x04") > 1 || str_contains($b, 'NETSCAPE2.0');
        }

        // Animated WebP: RIFF/WEBP carrying an 'ANIM' chunk.
        if (str_starts_with($b, 'RIFF') && substr($b, 8, 4) === 'WEBP') {
            return str_contains($b, 'ANIM');
        }

        return false;
    }

    private static function storeRaw(string $bytes, string $ext, int $w, int $h): array
    {
        $path = 'uploads/' . date('Y/m') . '/' . Str::random(24) . '.' . $ext;
        Storage::disk('public')->put($path, $bytes, 'public');

        return [
            'url' => Storage::disk('public')->url($path),
            'width' => $w,
            'height' => $h,
        ];
    }

    private static function extForMime(string $mime, string $fallbackExt): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => in_array($fallbackExt, ['png', 'gif', 'webp'], true) ? $fallbackExt : 'png',
        };
    }

    /** Downscale a static raster to a max edge and encode as WebP. */
    private static function raster(string $bytes, int $max): array
    {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw ValidationException::withMessages(['file' => 'Unsupported or corrupt image.']);
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $longest = max($w, $h);
        $scale = $longest > $max ? $max / $longest : 1.0;
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
