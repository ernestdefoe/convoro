<?php

namespace App\Http\Controllers;

use App\Support\ImagePipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function image(Request $request): JsonResponse
    {
        // Avatars accept animated images (APNG / animated GIF / WebP) and sanitized
        // SVG, so they skip the strict raster-only `image` rule used for post images.
        if ($request->input('kind') === 'avatar') {
            $request->validate([
                'file' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp,svg', 'max:8192'],
            ]);

            return response()->json(ImagePipeline::processAvatar($request->file('file')));
        }

        $request->validate([
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
        ]);

        return response()->json(ImagePipeline::processPost($request->file('file')));
    }
}
