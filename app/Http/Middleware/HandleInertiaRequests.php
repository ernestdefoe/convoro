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
                'canModerate' => (bool) $request->user()?->hasAnyPermission(\App\Support\Permissions::moderationKeys()),
                'canInvite' => (bool) $request->user() && (bool) Settings::get('invites.members_enabled', true),
            ],
            'site' => fn () => Settings::public(),
            'extNav' => fn () => \App\Support\ExtensionManager::navLinks(),
            'mobileNav' => fn () => \App\Support\MobileNav::share(),
            'storeOwner' => (bool) config('convoro.store_owner'),
            'hostingPanel' => (bool) config('convoro.hosting'),
            // POC — when the owner has stepped into one of their hosted communities.
            'activeTenant' => fn () => app()->bound('currentTenant')
                ? ['name' => app('currentTenant')->name, 'slug' => app('currentTenant')->slug, 'kind' => app('currentTenant')->kind]
                : null,
            'appVersion' => config('convoro.version'),
            'ask' => fn () => ['enabled' => \App\Support\Ask::enabled(), 'suggestions' => \App\Support\Ask::suggestions()],
            // Optional Stripe "buy button" donate widget in the sidebar — only
            // appears once an admin sets a (public) buy-button id + key.
            'donate' => fn () => [
                'buyButtonId' => (string) Settings::get('donate.buy_button_id', ''),
                'publishableKey' => (string) Settings::get('donate.publishable_key', ''),
                'heading' => (string) (Settings::get('donate.heading', '') ?: 'Donate'),
                'blurb' => (string) Settings::get('donate.blurb', ''),
            ],
            'composer' => fn () => ['gifs' => \App\Support\Gifs::enabled()],
            'inviteOnly' => fn () => (bool) Settings::get('invites.only', false),
            'sso' => fn () => \App\Support\Oidc::enabled() ? ['enabled' => true, 'label' => \App\Support\Oidc::label(), 'only' => (bool) Settings::get('sso.force', false)] : ['enabled' => false],
            'i18n' => fn () => [
                'locale' => app()->getLocale(),
                'rtl' => \App\Support\I18n::isRtl(app()->getLocale()),
                'locales' => \App\Support\I18n::available(),
                'messages' => \App\Support\I18n::messages(app()->getLocale()),
            ],
            'themeFonts' => fn () => $request->user()?->is_admin ? \App\Support\Theme::fontOptions() : null,
            'adminExtNav' => fn () => $request->user()?->is_admin
                ? collect(\App\Support\ExtensionManager::enabled())
                    ->filter(fn ($m) => ! empty($m['settings']) || ! empty($m['admin_url']))
                    ->map(fn ($m) => [
                        'id' => $m['id'],
                        'name' => $m['name'],
                        // Always route through the in-shell settings page so the
                        // extension UI loads in the admin content pane instead of
                        // navigating away to a standalone page.
                        'href' => '/admin/extensions/'.$m['id'],
                        // The extension's own icon (inline SVG) so each one is
                        // distinct in the nav rather than a shared fallback glyph.
                        'icon' => \App\Support\ExtensionManager::iconSvgFor($m),
                    ])->values()->all()
                : null,
            'seo' => fn () => \App\Support\Seo::make(),
            'extAssets' => fn () => \App\Support\ExtensionManager::assetsFor('forum'),
            'notifications' => fn () => $this->notifications($request),
            'dmUnread' => fn () => $this->dmUnread($request),
            'pushKey' => config('webpush.vapid.public_key'),
            // Marketing CTA switches to "Request a demo" when on-demand demos
            // are enabled; otherwise the one-click shared demo link is shown.
            'demoRequests' => (bool) config('convoro.demo_requests'),
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

        $items = $user->notifications()->latest()->limit(12)->get()
            ->map(fn ($n) => Present::notification($n))->all();

        // Show previews in the READER's language when they have auto-translate
        // on — otherwise a message/reply notification stays in the sender's
        // language. translateTexts caches forever, so this is ~free after the
        // first time a given snippet is seen.
        if ($user->auto_translate && \App\Support\ContentTranslator::enabled()) {
            $locale = $user->locale ?: app()->getLocale();
            $excerpts = \App\Support\ContentTranslator::translateTexts(
                array_map(fn ($i) => (string) ($i['excerpt'] ?? ''), $items), $locale);
            $titles = \App\Support\ContentTranslator::translateTexts(
                array_map(fn ($i) => (string) ($i['topic']['title'] ?? ''), $items), $locale);
            foreach ($items as $i => &$it) {
                if (! empty($it['excerpt'])) {
                    $it['excerpt'] = $excerpts[$i] ?? $it['excerpt'];
                }
                if (! empty($it['topic']['title'])) {
                    $it['topic']['title'] = $titles[$i] ?? $it['topic']['title'];
                }
            }
            unset($it);
        }

        return [
            'items' => $items,
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
