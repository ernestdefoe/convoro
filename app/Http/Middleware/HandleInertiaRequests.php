<?php

namespace App\Http\Middleware;

use App\Support\Present;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => (bool) $request->user()?->is_admin,
            ],
            'site' => fn () => Settings::public(),
            'themeFonts' => fn () => $request->user()?->is_admin ? \App\Support\Theme::fontOptions() : null,
            'adminExtNav' => fn () => $request->user()?->is_admin
                ? collect(\App\Support\ExtensionManager::enabled())
                    ->filter(fn ($m) => ! empty($m['settings']) || ! empty($m['admin_url']))
                    ->map(fn ($m) => [
                        'id' => $m['id'],
                        'name' => $m['name'],
                        'href' => $m['admin_url'] ?: '/admin/extensions/'.$m['id'],
                    ])->values()->all()
                : null,
            'seo' => fn () => \App\Support\Seo::make(),
            'extAssets' => fn () => \App\Support\ExtensionManager::assetsFor('forum'),
            'notifications' => fn () => $this->notifications($request),
            'dmUnread' => fn () => $this->dmUnread($request),
            'pushKey' => config('webpush.vapid.public_key'),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'mailTest' => fn () => $request->session()->get('mailTest'),
                'extResult' => fn () => $request->session()->get('extResult'),
                'storeError' => fn () => $request->session()->get('storeError'),
            ],
            'updateBanner' => fn () => $request->user()?->is_admin ? [
                'available' => (bool) Settings::get('update.available', false),
                'latest' => Settings::get('update.latest'),
            ] : null,
        ];
    }

    /**
     * Recent notifications + unread count for the bell (lazily evaluated).
     *
     * @return array{items: array<int, mixed>, unread: int}
     */
    private function notifications(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return ['items' => [], 'unread' => 0];
        }

        return [
            'items' => $user->notifications()->latest()->limit(12)->get()
                ->map(fn ($n) => Present::notification($n))->all(),
            'unread' => $user->unreadNotifications()->count(),
        ];
    }

    /** Number of conversations with unread messages for the current user. */
    private function dmUnread(Request $request): int
    {
        $user = $request->user();
        if (! $user) {
            return 0;
        }

        return \Illuminate\Support\Facades\DB::table('conversation_user as cu')
            ->join('messages as m', 'm.conversation_id', '=', 'cu.conversation_id')
            ->where('cu.user_id', $user->id)
            ->where('m.user_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('cu.last_read_at')->orWhereColumn('m.created_at', '>', 'cu.last_read_at'))
            ->distinct()
            ->count('cu.conversation_id');
    }
}
