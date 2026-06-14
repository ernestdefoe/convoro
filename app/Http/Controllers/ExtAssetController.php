<?php

namespace App\Http\Controllers;

use App\Support\ExtensionManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ExtAssetController extends Controller
{
    /**
     * Serve an enabled extension's prebuilt frontend bundle. Extensions live
     * under storage/ (not web-accessible), so this route streams the file with
     * the right MIME + caching. Path traversal is blocked in assetPath().
     */
    public function show(string $id, string $surface): Response
    {
        if (! in_array($surface, ['forum', 'admin'], true)) {
            abort(404);
        }

        $path = ExtensionManager::assetPath($id, $surface);
        abort_if($path === null, 404);

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'js', 'mjs' => 'text/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };

        $response = new BinaryFileResponse($path);
        $response->setMaxAge(3600)->setPublic();
        $response->headers->set('Content-Type', $mime);

        return $response;
    }

    /**
     * Serve an extension's manifest `cover` image (marketplace card cover).
     * Not gated on the enable toggle — covers show for installed-but-disabled
     * extensions too. Path traversal is blocked in coverPath().
     */
    public function cover(string $id): Response
    {
        $path = ExtensionManager::coverPath($id);
        abort_if($path === null, 404);

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        $response = new BinaryFileResponse($path);
        $response->setMaxAge(86400)->setPublic();
        $response->headers->set('Content-Type', $mime);

        return $response;
    }
}
