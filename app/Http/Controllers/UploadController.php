<?php

namespace App\Http\Controllers;

use App\Support\ImagePipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function image(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
        ]);

        return response()->json(ImagePipeline::processPost($request->file('file')));
    }
}
