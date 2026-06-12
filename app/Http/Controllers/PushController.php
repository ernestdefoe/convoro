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

    /** Fire a test browser push to the current user's devices (verify it works). */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $subs = $user->pushSubscriptions()->count();
        if ($subs === 0) {
            return response()->json(['ok' => false, 'message' => __('No devices are subscribed yet. Turn on push notifications first.')], 422);
        }

        \App\Jobs\SendWebPush::dispatch((int) $user->id, [
            'title' => \App\Support\Settings::get('site.name') ?: config('app.name', 'Convoro'),
            'body' => __('🔔 Push notifications are working!'),
            'url' => '/',
            'tag' => 'test',
        ]);

        return response()->json(['ok' => true, 'devices' => $subs]);
    }
}
