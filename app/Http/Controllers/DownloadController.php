<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the latest self-contained Convoro release archive (built + dropped
 * into storage/app/downloads). Linked from the docs so "download the zip"
 * actually downloads a zip.
 */
class DownloadController extends Controller
{
    public function zip(): BinaryFileResponse
    {
        return $this->latest('zip');
    }

    public function targz(): BinaryFileResponse
    {
        return $this->latest('tar.gz');
    }

    private function latest(string $ext): BinaryFileResponse
    {
        $dir = storage_path('app/downloads');
        $files = glob($dir.'/convoro-*.'.$ext) ?: [];
        abort_if(! $files, 404, __('No release is available for download yet.'));

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $path = $files[0];

        return response()->download($path, basename($path), [
            'Content-Type' => $ext === 'zip' ? 'application/zip' : 'application/gzip',
        ]);
    }
}
