<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    /** Store (or refresh) a browser push subscription for the current user. */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
        );

        return response()->json(['ok' => true]);
    }

    /** Forget a push subscription (user disabled push or unsubscribed). */
    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            $request->user()->deletePushSubscription($endpoint);
        }

        return response()->json(['ok' => true]);
    }
}
