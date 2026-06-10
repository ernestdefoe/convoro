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
            'notifications' => fn () => $this->notifications($request),
            'pushKey' => config('webpush.vapid.public_key'),
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
}
