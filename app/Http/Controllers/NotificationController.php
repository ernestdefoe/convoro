<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /** Mutable notification types the user can opt out of. */
    public const TYPES = ['reply', 'mention', 'reaction', 'wall', 'message', 'invite'];

    /** Full notifications page. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $items = $user->notifications()->latest()->limit(60)->get()
            ->map(fn ($n) => Present::notification($n))->all();

        return Inertia::render('Notifications/Index', [
            'items' => $items,
            'unread' => $user->unreadNotifications()->count(),
            'digestFrequency' => $user->digest_frequency,
            'preferences' => collect(self::TYPES)
                ->mapWithKeys(fn ($t) => [$t => $user->wantsNotification($t)])
                ->all(),
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

    /**
     * One-click digest unsubscribe reached from the email footer via a signed
     * URL (the 'signed' middleware validates it). Turns digests off and shows
     * a standalone confirmation page — no login required.
     */
    public function unsubscribe(User $user): HttpResponse
    {
        $user->update(['digest_frequency' => 'off']);

        return response()->view('digest.unsubscribed', ['user' => $user]);
    }

    /**
     * Update notification preferences: digest-email frequency and/or
     * per-type opt-outs. Both keys are optional so the page can save
     * either independently.
     */
    public function preferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'digest_frequency' => ['sometimes', 'in:off,daily,weekly'],
            'notifications' => ['sometimes', 'array'],
            'notifications.*' => ['boolean'],
        ]);

        $user = $request->user();
        $attrs = [];

        if (array_key_exists('digest_frequency', $data)) {
            $attrs['digest_frequency'] = $data['digest_frequency'];
        }

        if (array_key_exists('notifications', $data)) {
            $prefs = (array) ($user->notification_prefs ?? []);
            foreach ($data['notifications'] as $type => $enabled) {
                if (in_array($type, self::TYPES, true)) {
                    $prefs[$type] = (bool) $enabled;
                }
            }
            $attrs['notification_prefs'] = $prefs;
        }

        if ($attrs) {
            $user->update($attrs);
        }

        return back();
    }
}
