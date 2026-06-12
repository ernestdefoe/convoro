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
}
