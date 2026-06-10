<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->tokens()->latest()->get()->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'lastUsed' => $t->last_used_at ? $t->last_used_at->diffForHumans() : 'never',
                'created' => optional($t->created_at)->diffForHumans(),
            ])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $new = $request->user()->createToken($data['name']);

        return response()->json([
            'plainTextToken' => $new->plainTextToken,
            'token' => [
                'id' => $new->accessToken->id,
                'name' => $new->accessToken->name,
                'lastUsed' => 'never',
                'created' => 'just now',
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->tokens()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }
}
