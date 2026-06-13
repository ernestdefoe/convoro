<?php

namespace App\Http\Controllers;

use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /** Full notifications page. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $items = $user->notifications()->latest()->limit(60)->get()
            ->map(fn ($n) => Present::notification($n))->all();

        return Inertia::render('Notifications/Index', [
            'items' => $items,
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Mark a single notification read. */
    public function read(Request $request, string $id): RedirectResponse
    {
        $n = $request->user()->notifications()->whereKey($id)->first();
        $n?->markAsRead();

        return back();
    }

    /** Mark every notification read. */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /** Remove a single notification. */
    public function clear(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->delete();

        return back();
    }

    /** Remove every notification (clears the list). */
    public function clearAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back();
    }

    /** Update the digest-email preference (off | daily | weekly). */
    public function preferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'digest_frequency' => ['required', 'in:off,daily,weekly'],
            'notify_email' => ['boolean'],
        ]);

        $request->user()->update([
            'digest_frequency' => $data['digest_frequency'],
            'notify_email' => $request->boolean('notify_email'),
        ]);

        return back();
    }

    /** Save the per-type notification channel matrix (email + push per type). */
    public function channelPrefs(Request $request): RedirectResponse
    {
        $types = \App\Models\User::NOTIFY_TYPES;
        $rules = [];
        foreach ($types as $t) {
            $rules["prefs.$t.email"] = ['boolean'];
            $rules["prefs.$t.push"] = ['boolean'];
        }
        $request->validate($rules);

        $in = (array) $request->input('prefs', []);
        $clean = [];
        foreach ($types as $t) {
            $clean[$t] = [
                'email' => (bool) ($in[$t]['email'] ?? false),
                'push' => (bool) ($in[$t]['push'] ?? false),
            ];
        }
        $request->user()->update(['notification_prefs' => $clean]);

        return back();
    }
}
