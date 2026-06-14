<?php

use App\Http\Controllers\ForumController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ActivityPub federation (off unless enabled in admin). Not host-scoped — these
// live on the community domain. WebFinger/NodeInfo must be at the well-known root.
Route::get('/.well-known/webfinger', [App\Http\Controllers\FederationController::class, 'webfinger']);
Route::get('/.well-known/nodeinfo', [App\Http\Controllers\FederationController::class, 'nodeinfo']);
Route::get('/nodeinfo/2.0', [App\Http\Controllers\FederationController::class, 'nodeinfoData']);
Route::get('/federation/actor', [App\Http\Controllers\FederationController::class, 'actor'])->name('federation.actor');
Route::get('/federation/outbox', [App\Http\Controllers\FederationController::class, 'outbox']);
Route::get('/federation/followers', [App\Http\Controllers\FederationController::class, 'followers']);
Route::post('/federation/inbox', [App\Http\Controllers\FederationController::class, 'inbox']);
// Per-user (Person) actors — each member federates as @username@host (Phase 3).
Route::get('/u/{user}/actor', [App\Http\Controllers\FederationController::class, 'userActor'])->name('federation.user.actor');
Route::get('/u/{user}/outbox', [App\Http\Controllers\FederationController::class, 'userOutbox']);
Route::get('/u/{user}/followers', [App\Http\Controllers\FederationController::class, 'userFollowers']);
Route::post('/u/{user}/inbox', [App\Http\Controllers\FederationController::class, 'userInbox']);

// One-click digest unsubscribe — a permanent signed link from the digest email,
// no login required. Sets the member's digest to off and shows a confirmation.
Route::get('/digest/unsubscribe/{user}', function (\App\Models\User $user) {
    $user->forceFill(['digest_frequency' => 'off'])->save();
    $site = e(\App\Support\Settings::get('site.name') ?: config('app.name', 'Convoro'));
    $home = e(config('app.url'));
    $heading = e(__('You’re unsubscribed'));
    $body = e(__('You won’t receive any more digest emails. You can re-enable them anytime in your profile settings.'));
    $back = e(__('Back to :site', ['site' => $site]));

    return response(<<<HTML
    <!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$heading} — {$site}</title><style>
      body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f4f5f9;color:#1b2030;margin:0;display:grid;place-items:center;min-height:100vh}
      .card{background:#fff;border:1px solid #e6e8f0;border-radius:16px;max-width:440px;padding:36px 32px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.05)}
      h1{font-size:22px;margin:0 0 10px}p{color:#5a6178;line-height:1.5;margin:0 0 22px}
      a{display:inline-block;background:#5b5bd6;color:#fff;text-decoration:none;font-weight:700;padding:11px 24px;border-radius:10px}
    </style></head><body><div class="card"><div style="font-size:40px;margin-bottom:8px">✉️</div>
    <h1>{$heading}</h1><p>{$body}</p><a href="{$home}">{$back}</a></div></body></html>
    HTML);
})->middleware('signed')->name('digest.unsubscribe');

// Marketing + central store, scoped to the apex domain (convoro.co). Registered
// FIRST so its `/` wins over the forum index on that host; the forum keeps `/`
// on every other host. Shared login works via SESSION_DOMAIN=.convoro.co.
// The public extension directory + store console. Defined once and mirrored on
// BOTH the apex (convoro.co) and the community host (community.convoro.co) so
// members can browse/buy without leaving the forum.
$convoroStoreRoutes = function () {
    Route::get('/extensions', [App\Http\Controllers\StoreController::class, 'index'])->name('store.index');
    Route::get('/extensions/submit', [App\Http\Controllers\StoreController::class, 'submitForm'])->name('store.submit');
    Route::post('/extensions/submit', [App\Http\Controllers\StoreController::class, 'submit'])->name('store.submit.post');
    // Author self-service: manage your own listings (price etc.) — no admin access needed.
    Route::get('/extensions/manage', [App\Http\Controllers\StoreController::class, 'manage'])->middleware('auth')->name('store.manage');
    Route::post('/extensions/{product}/manage', [App\Http\Controllers\StoreController::class, 'updateListing'])->middleware('auth')->name('store.manage.update');
    Route::get('/extensions/purchased', [App\Http\Controllers\StoreController::class, 'success'])->name('store.success');

    // Store-OWNER console actions (front-facing; operator only — the old
    // /admin/store section now lives here). admin + store.owner gated.
    Route::middleware(['auth', 'admin', 'store.owner'])->group(function () {
        Route::post('/extensions/manage/stripe', [App\Http\Controllers\AdminController::class, 'updateStripe'])->name('store.manage.stripe');
        Route::get('/extensions/manage/stripe/connect', [App\Http\Controllers\AdminController::class, 'connectStripe'])->name('store.manage.stripe.connect');
        Route::post('/extensions/manage/stripe/disconnect', [App\Http\Controllers\AdminController::class, 'disconnectStripe'])->name('store.manage.stripe.disconnect');
        Route::post('/extensions/manage/covers', [App\Http\Controllers\AdminController::class, 'generateCovers'])->name('store.manage.covers');
        Route::post('/extensions/manage/link', [App\Http\Controllers\AdminController::class, 'linkRepo'])->name('store.manage.link');
        Route::post('/extensions/manage/products', [App\Http\Controllers\AdminController::class, 'storeProduct'])->name('store.manage.products.store');
        Route::put('/extensions/manage/products/{product:id}', [App\Http\Controllers\AdminController::class, 'updateProduct'])->name('store.manage.products.update');
        Route::delete('/extensions/manage/products/{product:id}', [App\Http\Controllers\AdminController::class, 'destroyProduct'])->name('store.manage.products.destroy');
        Route::post('/extensions/manage/products/{product:id}/refresh', [App\Http\Controllers\AdminController::class, 'refreshRepo'])->name('store.manage.products.refresh');
        Route::post('/extensions/manage/products/{product:id}/file', [App\Http\Controllers\AdminController::class, 'uploadProductFile'])->name('store.manage.products.file');
        Route::post('/extensions/manage/products/{product:id}/review', [App\Http\Controllers\AdminController::class, 'reviewProduct'])->name('store.manage.products.review');
        Route::post('/extensions/manage/review-settings', [App\Http\Controllers\AdminController::class, 'updateReviewAi'])->name('store.manage.review');
    });

    Route::get('/extensions/{product}', [App\Http\Controllers\StoreController::class, 'show'])->name('store.show');
    Route::post('/extensions/{product}/checkout', [App\Http\Controllers\StoreController::class, 'checkout'])->name('store.checkout');
};

// Apex marketing host: home + the full store. Registered FIRST so `/` wins.
Route::domain(config('convoro.marketing_domain'))->group(function () use ($convoroStoreRoutes) {
    Route::get('/', [App\Http\Controllers\MarketingController::class, 'home'])->name('marketing.home');
    Route::get('/compare', [App\Http\Controllers\MarketingController::class, 'compare'])->name('compare');
    // Trust pages — signals of an actively-maintained product.
    Route::get('/changelog', [App\Http\Controllers\TrustController::class, 'changelog'])->name('changelog');
    Route::get('/roadmap', [App\Http\Controllers\TrustController::class, 'roadmap'])->name('roadmap');
    Route::get('/status', [App\Http\Controllers\TrustController::class, 'status'])->name('status');
    Route::get('/about', [App\Http\Controllers\TrustController::class, 'about'])->name('about');
    Route::get('/security', [App\Http\Controllers\TrustController::class, 'security'])->name('security');
    // Documentation hub + guides.
    Route::get('/docs', [App\Http\Controllers\DocsController::class, 'index'])->name('docs');
    Route::get('/docs/{guide}', [App\Http\Controllers\DocsController::class, 'show'])->name('docs.show');
    $convoroStoreRoutes();
});

// Community/forum host: mirror the same directory + console (own route names so
// they don't override the apex ones; the frontend uses host-relative links).
Route::domain(config('convoro.community_domain'))->name('community.')->group($convoroStoreRoutes);

// Stripe webhook + license API (any host; CSRF-excepted in bootstrap/app.php).
Route::post('/store/webhook', [App\Http\Controllers\StoreController::class, 'webhook'])->name('store.webhook');
// GitHub registry webhook — instant refresh on new releases (Packagist-style).
Route::post('/api/registry/github', [App\Http\Controllers\StoreController::class, 'githubWebhook'])->name('registry.github');

// AI builder spec — machine-readable so AI models can scaffold themes/extensions.
Route::get('/api/ai/spec.json', [App\Http\Controllers\AiSpecController::class, 'json'])->name('ai.spec');

// Switch UI language (public — guests use the session).
Route::post('/locale', [App\Http\Controllers\LocaleController::class, 'store'])->name('locale.store');
Route::post('/preferences/auto-translate', [App\Http\Controllers\LocaleController::class, 'autoTranslate'])->middleware('auth')->name('locale.auto');

// "Ask Convoro" — public, throttled AI answer endpoint (the flagship AI feature).
Route::post('/api/ask', [App\Http\Controllers\AiController::class, 'ask'])->middleware('throttle:30,1')->name('ask');
// "Asked before?" — related existing threads while composing (auth, retrieval only).
Route::post('/api/related', [App\Http\Controllers\AiController::class, 'related'])->middleware(['auth', 'throttle:90,1'])->name('ask.related');
// "Catch me up" — on-demand cached AI summary of a topic.
Route::get('/api/topics/{topic}/summary', [App\Http\Controllers\AiController::class, 'summarize'])->middleware('throttle:60,1')->name('topics.summary');
// Per-reader post translation — translate one post into the viewer's language (cached).
Route::post('/api/posts/{post}/translate', [App\Http\Controllers\AiController::class, 'translatePost'])->middleware('throttle:120,1')->name('posts.translate');
// Per-reader DM translation — same, for a conversation message (auth + participant-checked).
Route::post('/api/messages/{message}/translate', [App\Http\Controllers\AiController::class, 'translateMessage'])->middleware(['auth', 'throttle:120,1'])->name('messages.translate');
// Writing assistant — transform a draft, suggest titles/tags (auth, throttled).
Route::post('/api/ai/assist', [App\Http\Controllers\AiController::class, 'assist'])->middleware(['auth', 'throttle:40,1'])->name('ai.assist');
Route::post('/api/ai/compose', [App\Http\Controllers\AiController::class, 'compose'])->middleware(['auth', 'throttle:30,1'])->name('ai.compose');
Route::post('/api/ai/suggest-title', [App\Http\Controllers\AiController::class, 'suggestTitle'])->middleware(['auth', 'throttle:30,1'])->name('ai.suggest.title');
Route::post('/api/ai/suggest-tags', [App\Http\Controllers\AiController::class, 'suggestTags'])->middleware(['auth', 'throttle:30,1'])->name('ai.suggest.tags');
Route::get('/api/gifs/search', [App\Http\Controllers\GifController::class, 'search'])->middleware(['auth', 'throttle:60,1'])->name('gifs.search');
Route::post('/mail/inbound', [App\Http\Controllers\MailInboundController::class, 'inbound'])->middleware('throttle:120,1')->name('mail.inbound');
Route::get('/llms.txt', [App\Http\Controllers\AiSpecController::class, 'llms'])->name('ai.llms');
Route::get('/api/catalog', [App\Http\Controllers\StoreController::class, 'catalog'])->name('catalog');
Route::get('/api/catalog/download/{product}', [App\Http\Controllers\StoreController::class, 'freeDownload'])->name('catalog.download');
Route::post('/api/licenses/validate', [App\Http\Controllers\LicenseController::class, 'validateKey'])->name('licenses.validate');
Route::get('/api/licenses/download', [App\Http\Controllers\LicenseController::class, 'download'])->name('licenses.download');

// First-run web installer (gated by EnsureInstalled — 404s once installed).
Route::get('/install', [App\Http\Controllers\InstallController::class, 'show'])->name('install');
Route::post('/install/test-db', [App\Http\Controllers\InstallController::class, 'testDatabase'])->name('install.testdb');
Route::post('/install', [App\Http\Controllers\InstallController::class, 'install'])->name('install.run');

// Release downloads (latest self-contained archive).
Route::get('/download', [App\Http\Controllers\DownloadController::class, 'zip'])->name('download');
Route::get('/download/targz', [App\Http\Controllers\DownloadController::class, 'targz'])->name('download.targz');

// SEO
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [App\Http\Controllers\SitemapController::class, 'robots'])->name('robots');

// PWA
Route::get('/manifest.webmanifest', [App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');

// Enabled extensions' prebuilt frontend bundles (served from storage/).
Route::get('/ext-asset/{id}/{surface}', [App\Http\Controllers\ExtAssetController::class, 'show'])
    ->where('id', '[A-Za-z0-9._-]+')->name('ext.asset');

// One-click public demo: signs in the preset demo account (if configured) and
// drops the visitor into the community. Disabled when CONVORO_DEMO_EMAIL is blank.
Route::get('/demo', function () {
    $email = (string) config('convoro.demo_email');
    // The public demo logs in a NON-admin account by default so a one-click login
    // can never hand out admin. A dedicated demo install can set
    // CONVORO_DEMO_ADMIN=true to let visitors explore admin features too.
    $query = $email !== '' ? \App\Models\User::where('email', $email) : null;
    if ($query && ! config('convoro.demo_admin')) {
        $query->where('is_admin', false);
    }
    $user = $query?->first();
    if (! $user) {
        return redirect('/');
    }
    \Illuminate\Support\Facades\Auth::login($user);

    return redirect('/')->with('status', 'You\'re exploring the Convoro demo — post, react, and try the live theme editor freely.');
})->middleware('throttle:60,1')->name('demo.login');

// POC — public "request a demo" flow (gated). Each request provisions an
// isolated, time-boxed demo and emails the requester their login.
if (config('convoro.demo_requests')) {
    Route::get('/request-demo', [App\Http\Controllers\DemoController::class, 'show'])->name('demo.request');
    Route::post('/request-demo', [App\Http\Controllers\DemoController::class, 'request'])
        ->middleware('throttle:5,10')->name('demo.request.submit');
}

// Get-started / order flow — sign up for managed hosting (provisions a community
// + support account) or a free self-host support account. Guests only; only
// when managed hosting is offered on this install.
if (config('convoro.hosting')) {
    Route::middleware('guest')->group(function () {
        Route::get('/get-started', [App\Http\Controllers\OnboardController::class, 'show'])->name('onboard');
        Route::post('/get-started', [App\Http\Controllers\OnboardController::class, 'store'])
            ->middleware('throttle:10,10')->name('onboard.store');
    });
}

// Community (forum)
Route::get('/', [ForumController::class, 'index'])->name('forum.index');
Route::get('/search', [App\Http\Controllers\SearchController::class, 'index'])->name('search');
Route::get('/t/{topic}', [TopicController::class, 'show'])->name('topics.show');
// Live viewers — fresh "LIVE" badges driven by who's reading right now.
Route::get('/api/live-topics', [App\Http\Controllers\ForumController::class, 'liveTopics'])->name('topics.live');
Route::get('/api/presence', [App\Http\Controllers\ForumController::class, 'presence'])->name('users.presence');
Route::post('/t/{topic}/heartbeat', [TopicController::class, 'heartbeat'])->middleware(['auth', 'throttle:40,1'])->name('topics.heartbeat');
Route::get('/u/{user}', [App\Http\Controllers\UserProfileController::class, 'show'])->name('profiles.show');
Route::get('/extensions', [App\Http\Controllers\ExtensionsPageController::class, 'index'])->middleware('store.owner')->name('extensions.index');
Route::get('/members', [App\Http\Controllers\MembersController::class, 'index'])->name('members.index');

Route::get('/welcome', fn () => Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('welcome');

// Legacy auth landing → send everyone to the community home.
Route::get('/dashboard', fn () => redirect()->route('forum.index'))->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/new', [TopicController::class, 'create'])->name('topics.create');
    Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');

    // POC ONLY — managed-hosting panel. Gated off in production (config flag).
    if (config('convoro.hosting')) {
        Route::get('/panel', [App\Http\Controllers\PanelController::class, 'index'])->name('panel');
        Route::post('/panel/communities', [App\Http\Controllers\PanelController::class, 'store'])->name('panel.communities.store');
        Route::post('/panel/communities/{tenant}/enter', [App\Http\Controllers\PanelController::class, 'enter'])->name('panel.communities.enter');
        Route::get('/panel/communities/{tenant}/manage', [App\Http\Controllers\PanelController::class, 'manage'])->name('panel.communities.manage');
        Route::get('/panel/communities/{tenant}/metrics', [App\Http\Controllers\PanelController::class, 'metrics'])->name('panel.communities.metrics');
        Route::put('/panel/communities/{tenant}', [App\Http\Controllers\PanelController::class, 'update'])->name('panel.communities.update');
        Route::put('/panel/communities/{tenant}/domain', [App\Http\Controllers\PanelController::class, 'setDomain'])->name('panel.communities.domain');
        Route::post('/panel/communities/{tenant}/domain/verify', [App\Http\Controllers\PanelController::class, 'verifyDomain'])->name('panel.communities.domain.verify');
        Route::post('/panel/communities/{tenant}/billing-portal', [App\Http\Controllers\PanelController::class, 'billingPortal'])->name('panel.communities.billing');
        Route::delete('/panel/communities/{tenant}', [App\Http\Controllers\PanelController::class, 'destroy'])->name('panel.communities.destroy');
        Route::post('/panel/exit', [App\Http\Controllers\PanelController::class, 'exit'])->name('panel.exit');
        Route::get('/panel/order', [App\Http\Controllers\PanelController::class, 'order'])->name('panel.order');
    }

    // Drafts + scheduled posts.
    Route::get('/drafts', [App\Http\Controllers\DraftController::class, 'index'])->name('drafts.index');
    Route::post('/drafts', [App\Http\Controllers\DraftController::class, 'store'])->name('drafts.store');
    Route::post('/drafts/autosave', [App\Http\Controllers\DraftController::class, 'autosave'])->name('drafts.autosave');
    Route::delete('/drafts/autosave', [App\Http\Controllers\DraftController::class, 'discardAutosave'])->name('drafts.autosave.discard');
    Route::post('/drafts/{draft}/publish', [App\Http\Controllers\DraftController::class, 'publish'])->name('drafts.publish');
    Route::delete('/drafts/{draft}', [App\Http\Controllers\DraftController::class, 'destroy'])->name('drafts.destroy');
    Route::post('/t/{topic}/posts', [PostController::class, 'store'])->name('posts.store');
    Route::post('/polls/{poll}/vote', [App\Http\Controllers\PollController::class, 'vote'])->middleware('throttle:30,1')->name('polls.vote');
    Route::post('/t/{topic}/pin', [TopicController::class, 'togglePin'])->name('topics.pin');
    Route::post('/t/{topic}/lock', [TopicController::class, 'toggleLock'])->name('topics.lock');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/react', [ReactionController::class, 'toggle'])->name('posts.react');
    Route::post('/report', [App\Http\Controllers\ReportController::class, 'store'])->name('report.store');
    Route::post('/uploads/image', [App\Http\Controllers\UploadController::class, 'image'])->name('uploads.image');

    Route::post('/push/subscribe', [App\Http\Controllers\PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [App\Http\Controllers\PushController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/push/test', [App\Http\Controllers\PushController::class, 'test'])->name('push.test');

    Route::get('/messages', [App\Http\Controllers\MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [App\Http\Controllers\MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{conversation}', [App\Http\Controllers\MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [App\Http\Controllers\MessageController::class, 'message'])->name('messages.send');
    Route::post('/messages/{conversation}/participants', [App\Http\Controllers\MessageController::class, 'addParticipants'])->name('messages.participants');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::delete('/notifications', [NotificationController::class, 'clearAll'])->name('notifications.clearAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'clear'])->name('notifications.clear');
    Route::post('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::post('/notifications/channel-prefs', [NotificationController::class, 'channelPrefs'])->name('notifications.channel-prefs');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [App\Http\Controllers\AdminController::class, 'dashboard'])->name('dashboard');

    // Moderation suite
    Route::get('/moderation', [App\Http\Controllers\ModerationController::class, 'index'])->name('moderation');
    Route::get('/audit-log', [App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-log');
    Route::post('/moderation/reports/{report}/resolve', [App\Http\Controllers\ModerationController::class, 'resolveReport'])->name('moderation.reports.resolve');
    Route::post('/moderation/reports/{report}/dismiss', [App\Http\Controllers\ModerationController::class, 'dismissReport'])->name('moderation.reports.dismiss');
    Route::delete('/moderation/posts/{post}', [App\Http\Controllers\ModerationController::class, 'deletePost'])->name('moderation.posts.delete');
    Route::post('/moderation/posts/{post}/approve', [App\Http\Controllers\ModerationController::class, 'approvePost'])->name('moderation.posts.approve');
    Route::get('/moderation/topics/search', [App\Http\Controllers\ModerationController::class, 'topicSearch'])->name('moderation.topics.search');
    Route::post('/moderation/posts/{post}/move', [App\Http\Controllers\ModerationController::class, 'movePost'])->name('moderation.posts.move');
    Route::post('/moderation/topics/{topic}/move', [App\Http\Controllers\ModerationController::class, 'moveTopic'])->name('moderation.topics.move');
    Route::post('/moderation/users/{user}/ban', [App\Http\Controllers\ModerationController::class, 'banUser'])->name('moderation.users.ban');
    Route::post('/moderation/users/{user}/unban', [App\Http\Controllers\ModerationController::class, 'unbanUser'])->name('moderation.users.unban');
    Route::post('/moderation/ip-bans', [App\Http\Controllers\ModerationController::class, 'banIp'])->name('moderation.ip.ban');
    Route::delete('/moderation/ip-bans/{ipBan}', [App\Http\Controllers\ModerationController::class, 'unbanIp'])->name('moderation.ip.unban');

    Route::get('/settings', [App\Http\Controllers\AdminController::class, 'settings'])->name('settings');
    Route::post('/settings', [App\Http\Controllers\AdminController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/mobile-nav', [App\Http\Controllers\AdminController::class, 'updateMobileNav'])->name('settings.mobile-nav');
    Route::post('/settings/federation', [App\Http\Controllers\AdminController::class, 'updateFederation'])->name('settings.federation');
    Route::get('/email', [App\Http\Controllers\AdminController::class, 'email'])->name('email');
    Route::post('/email', [App\Http\Controllers\AdminController::class, 'updateEmail'])->name('email.update');
    Route::post('/email/test', [App\Http\Controllers\AdminController::class, 'sendTestEmail'])->name('email.test');
    Route::get('/theme', [App\Http\Controllers\AdminController::class, 'theme'])->name('theme');
    Route::post('/theme', [App\Http\Controllers\AdminController::class, 'updateTheme'])->name('theme.update');
    Route::post('/theme/widgets', [App\Http\Controllers\AdminController::class, 'updateWidgets'])->name('theme.widgets');
    Route::get('/accessibility', [App\Http\Controllers\AdminController::class, 'accessibility'])->name('accessibility');

    Route::get('/members', [App\Http\Controllers\AdminController::class, 'members'])->name('members');
    Route::put('/members/{user}', [App\Http\Controllers\AdminController::class, 'updateMember'])->name('members.update');
    Route::get('/ip-lookup', [App\Http\Controllers\AdminController::class, 'ipLookup'])->name('ip.lookup');
    Route::post('/onboarding/dismiss', [App\Http\Controllers\AdminController::class, 'dismissOnboarding'])->name('onboarding.dismiss');
    Route::post('/members/{user}/anonymize', [App\Http\Controllers\AdminController::class, 'anonymizeMember'])->name('members.anonymize');
    Route::delete('/members/{user}', [App\Http\Controllers\AdminController::class, 'destroyMember'])->name('members.destroy');
    Route::post('/groups', [App\Http\Controllers\AdminController::class, 'storeGroup'])->name('groups.store');
    Route::put('/groups/{group}', [App\Http\Controllers\AdminController::class, 'updateGroup'])->name('groups.update');
    Route::delete('/groups/{group}', [App\Http\Controllers\AdminController::class, 'destroyGroup'])->name('groups.destroy');

    Route::get('/pwa', [App\Http\Controllers\AdminController::class, 'pwa'])->name('pwa');
    Route::post('/pwa', [App\Http\Controllers\AdminController::class, 'updatePwa'])->name('pwa.update');
    Route::post('/pwa/icon', [App\Http\Controllers\AdminController::class, 'uploadIcon'])->name('pwa.icon');
    Route::post('/pwa/vapid', [App\Http\Controllers\AdminController::class, 'regenerateVapid'])->name('pwa.vapid');

    // Single sign-on (generic OpenID Connect).
    Route::get('/sso', [App\Http\Controllers\AdminController::class, 'sso'])->name('sso');
    Route::post('/sso', [App\Http\Controllers\AdminController::class, 'updateSso'])->name('sso.update');

    Route::get('/content', [App\Http\Controllers\AdminController::class, 'content'])->name('content');
    Route::post('/categories', [App\Http\Controllers\AdminController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{category}', [App\Http\Controllers\AdminController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [App\Http\Controllers\AdminController::class, 'destroyCategory'])->name('categories.destroy');
    Route::post('/tags', [App\Http\Controllers\AdminController::class, 'storeTag'])->name('tags.store');
    Route::put('/tags/{tag}', [App\Http\Controllers\AdminController::class, 'updateTag'])->name('tags.update');
    Route::delete('/tags/{tag}', [App\Http\Controllers\AdminController::class, 'destroyTag'])->name('tags.destroy');

    Route::get('/marketplace', [App\Http\Controllers\AdminController::class, 'marketplace'])->name('marketplace');
    Route::post('/marketplace/license', [App\Http\Controllers\AdminController::class, 'installLicense'])->name('marketplace.license');
    Route::post('/marketplace/enable', [App\Http\Controllers\AdminController::class, 'enableExtension'])->name('marketplace.enable');
    Route::post('/marketplace/disable', [App\Http\Controllers\AdminController::class, 'disableExtension'])->name('marketplace.disable');
    Route::post('/marketplace/uninstall', [App\Http\Controllers\AdminController::class, 'uninstallExtension'])->name('marketplace.uninstall');
    Route::post('/marketplace/settings', [App\Http\Controllers\AdminController::class, 'updateExtensionSettings'])->name('marketplace.settings');
    Route::post('/marketplace/composer', [App\Http\Controllers\AdminController::class, 'composerInstall'])->name('marketplace.composer');
    Route::post('/marketplace/catalog/install', [App\Http\Controllers\AdminController::class, 'installCatalogItem'])->name('marketplace.catalog.install');
    Route::get('/extensions/{id}', [App\Http\Controllers\AdminController::class, 'extensionSettings'])->name('extensions.settings');
    // The store console moved front-facing to /extensions/manage. Keep the
    // Stripe Connect OAuth callback at its ORIGINAL URL (it's the registered
    // redirect URI), and redirect the old /admin/store to the new home.
    Route::middleware('store.owner')->group(function () {
        Route::get('/store/stripe/callback', [App\Http\Controllers\AdminController::class, 'stripeCallback'])->name('store.stripe.callback');
        Route::get('/store', fn () => redirect('/extensions/manage'))->name('store');
    });
    // Core AI configuration (powers "Ask Convoro").
    Route::get('/ai', [App\Http\Controllers\AiController::class, 'settings'])->name('ai');
    Route::post('/ai', [App\Http\Controllers\AiController::class, 'updateSettings'])->name('ai.update');
    Route::post('/ai/index/build', [App\Http\Controllers\AiController::class, 'buildIndex'])->name('ai.index.build');
    Route::post('/ai/knowledge/reindex', [App\Http\Controllers\AiController::class, 'reindexKnowledge'])->name('ai.knowledge.reindex');

    // Curated AI knowledge base (admin-authored support articles).
    Route::get('/knowledge', [App\Http\Controllers\KnowledgeController::class, 'index'])->name('knowledge');
    Route::post('/knowledge', [App\Http\Controllers\KnowledgeController::class, 'store'])->name('knowledge.store');
    Route::put('/knowledge/{id}', [App\Http\Controllers\KnowledgeController::class, 'update'])->name('knowledge.update');
    Route::delete('/knowledge/{id}', [App\Http\Controllers\KnowledgeController::class, 'destroy'])->name('knowledge.destroy');
    Route::get('/ai/index/status', [App\Http\Controllers\AiController::class, 'indexStatus'])->name('ai.index.status');

    // Invites — invite codes + invite-only registration.
    Route::get('/invites', [App\Http\Controllers\InviteController::class, 'index'])->name('invites');
    Route::post('/invites', [App\Http\Controllers\InviteController::class, 'store'])->name('invites.store');
    Route::delete('/invites/{invite}', [App\Http\Controllers\InviteController::class, 'destroy'])->name('invites.destroy');
    Route::post('/invites/only', [App\Http\Controllers\InviteController::class, 'setOnly'])->name('invites.only');
    Route::post('/invites/members', [App\Http\Controllers\InviteController::class, 'setMembers'])->name('invites.members');

    // Import wizard — migrate from other forum software (Flarum in v1).
    Route::get('/import', [App\Http\Controllers\ImportController::class, 'index'])->name('import');
    Route::post('/import/test', [App\Http\Controllers\ImportController::class, 'test'])->name('import.test');
    Route::post('/import/upload', [App\Http\Controllers\ImportController::class, 'upload'])->name('import.upload');
    Route::post('/import/start', [App\Http\Controllers\ImportController::class, 'start'])->name('import.start');
    Route::get('/import/progress', [App\Http\Controllers\ImportController::class, 'progress'])->name('import.progress');

    // Languages — UI translation management (coverage, AI auto-translate, editing).
    Route::get('/languages', [App\Http\Controllers\Admin\LanguagesController::class, 'index'])->name('languages');
    Route::get('/languages/status', [App\Http\Controllers\Admin\LanguagesController::class, 'status'])->name('languages.status');
    Route::get('/languages/strings', [App\Http\Controllers\Admin\LanguagesController::class, 'strings'])->name('languages.strings');
    Route::post('/languages/translate', [App\Http\Controllers\Admin\LanguagesController::class, 'translate'])->name('languages.translate');
    Route::post('/languages/add', [App\Http\Controllers\Admin\LanguagesController::class, 'add'])->name('languages.add');
    Route::post('/languages/string', [App\Http\Controllers\Admin\LanguagesController::class, 'updateString'])->name('languages.string');
    Route::post('/languages/default', [App\Http\Controllers\Admin\LanguagesController::class, 'setDefault'])->name('languages.default');
    Route::delete('/languages', [App\Http\Controllers\Admin\LanguagesController::class, 'destroy'])->name('languages.destroy');

    // Database backups — download/restore + scheduled offsite backups.
    Route::get('/backups', [App\Http\Controllers\BackupController::class, 'index'])->name('backups');
    Route::get('/backups/status', [App\Http\Controllers\BackupController::class, 'status'])->name('backups.status');
    Route::post('/backups/run', [App\Http\Controllers\BackupController::class, 'run'])->name('backups.run');
    Route::post('/backups/restore', [App\Http\Controllers\BackupController::class, 'restore'])->name('backups.restore');
    Route::post('/backups/settings', [App\Http\Controllers\BackupController::class, 'updateSettings'])->name('backups.settings');
    Route::get('/backups/{name}/download', [App\Http\Controllers\BackupController::class, 'download'])->name('backups.download');
    Route::delete('/backups/{name}', [App\Http\Controllers\BackupController::class, 'destroy'])->name('backups.destroy');

    // System moved into the Dashboard; keep the URL working as a redirect.
    Route::get('/system', fn () => redirect('/admin'))->name('system');
    Route::post('/system/run', [App\Http\Controllers\AdminController::class, 'runMaintenance'])->name('system.run');
    Route::get('/system/update-status', [App\Http\Controllers\AdminController::class, 'updateStatus'])->name('system.update-status');
    Route::post('/system/check-updates', [App\Http\Controllers\AdminController::class, 'checkUpdates'])->name('system.check');
    Route::post('/system/update', [App\Http\Controllers\AdminController::class, 'applyUpdate'])->name('system.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/account/licenses', [App\Http\Controllers\StoreController::class, 'account'])->name('store.account');
    Route::get('/users/search', [App\Http\Controllers\UserProfileController::class, 'search'])->name('users.search');
    Route::post('/u/{user}/wall', [App\Http\Controllers\UserProfileController::class, 'storeWall'])->name('profiles.wall.store');
    Route::delete('/profile-posts/{profilePost}', [App\Http\Controllers\UserProfileController::class, 'destroyWall'])->name('profiles.wall.destroy');
    Route::post('/profile/details', [App\Http\Controllers\UserProfileController::class, 'updateDetails'])->name('profile.details');

    Route::get('/user/tokens', [App\Http\Controllers\AccessTokenController::class, 'index'])->name('tokens.index');
    Route::post('/user/tokens', [App\Http\Controllers\AccessTokenController::class, 'store'])->name('tokens.store');
    Route::delete('/user/tokens/{id}', [App\Http\Controllers\AccessTokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Member-facing invitations — referral link + email invites.
    Route::get('/invite', [App\Http\Controllers\MemberInviteController::class, 'index'])->name('invite');
    Route::post('/invite/send', [App\Http\Controllers\MemberInviteController::class, 'send'])->middleware('throttle:10,10')->name('invite.send');
});

// Generic OpenID Connect SSO (guest-accessible login flow).
Route::get('/auth/oidc/redirect', [App\Http\Controllers\Auth\OidcController::class, 'redirect'])->name('oidc.redirect');
Route::get('/auth/oidc/callback', [App\Http\Controllers\Auth\OidcController::class, 'callback'])->name('oidc.callback');

require __DIR__.'/auth.php';
