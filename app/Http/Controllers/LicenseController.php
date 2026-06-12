<?php

namespace App\Http\Controllers;

use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public license API. Remote Convoro installs call this to validate a buyer's
 * license key and pull the product download — the link between the central
 * convoro.co store and any site's Marketplace.
 */
class LicenseController extends Controller
{
    public function validateKey(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'package' => ['nullable', 'string'],
        ]);

        $license = License::with('product')->where('key', strtoupper(trim($data['key'])))->first();

        if (! $license || ! $license->isValid()) {
            return response()->json(['valid' => false, 'message' => __('Invalid or inactive license key.')], 404);
        }

        if (! empty($data['package']) && $license->product->package && $license->product->package !== $data['package']) {
            return response()->json(['valid' => false, 'message' => __('This key is for a different product.')], 422);
        }

        $license->increment('activations');
        $license->update(['last_validated_at' => now()]);

        return response()->json([
            'valid' => true,
            'product' => [
                'name' => $license->product->name,
                'package' => $license->product->package,
                'version' => $license->product->version,
                'type' => $license->product->type,
            ],
            'download_url' => $license->product->download_path
                ? route('licenses.download', ['key' => $license->key])
                : null,
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $key = strtoupper(trim((string) $request->query('key')));
        $license = License::with('product')->where('key', $key)->first();

        abort_if(! $license || ! $license->isValid(), 403, __('Invalid license.'));

        $path = $license->product->download_path;
        $abs = $path ? storage_path('app/'.ltrim($path, '/')) : null;
        abort_if(! $abs || ! is_file($abs), 404, __('Download not available.'));

        return response()->download($abs, $license->product->slug.'.zip');
    }
}
