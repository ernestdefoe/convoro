<?php

namespace App\Http\Controllers;

use App\Support\Gifs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GifController extends Controller
{
    /** Proxy a GIF search to the configured provider (keeps the API key server-side). */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        return response()->json(['results' => Gifs::search($query)]);
    }
}
