<?php

use App\Http\Controllers\ForumController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Marketing + central store, scoped to the apex domain (convoro.co). Registered
// FIRST so its `/` wins over the forum index on that host; the forum keeps `/`
// on every other host. Shared login works via SESSION_DOMAIN=.convoro.co.
Route::domain(config('convoro.marketing_domain'))->group(function () {
    Route::get('/', [App\Http\Controllers\MarketingController::class, 'home'])->name('marketing.home');
    Route::get('/store', [App\Http\Controllers\StoreController::class, 'index'])->name('store.index');
    Route::get('/store/success', [App\Http\Controllers\StoreController::class, 'success'])->name('store.success');
    Route::get('/store/{product}', [App\Http\Controllers\StoreController::class, 'show'])->name('store.show');
    Route::post('/store/{product}/checkout', [App\Http\Controllers\StoreController::class, 'checkout'])->name('store.checkout');
});

// Stripe webhook + license API (any host; CSRF-excepted in bootstrap/app.php).
Route::post('/store/webhook', [App\Http\Controllers\StoreController::class, 'webhook'])->name('store.webhook');
Route::post('/api/licenses/validate', [App\Http\Controllers\LicenseController::class, 'validateKey'])->name('licenses.validate');
Route::get('/api/licenses/download', [App\Http\Controllers\LicenseController::class, 'download'])->name('licenses.download');

// First-run web installer (gated by EnsureInstalled — 404s once installed).
Route::get('/install', [App\Http\Controllers\InstallController::class, 'show'])->name('install');
Route::post('/install/test-db', [App\Http\Controllers\InstallController::class, 'testDatabase'])->name('install.testdb');
Route::post('/install', [App\Http\Controllers\InstallController::class, 'install'])->name('install.run');

// SEO
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [App\Http\Controllers\SitemapController::class, 'robots'])->name('robots');

// PWA
Route::get('/manifest.webmanifest', [App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');

// Enabled extensions' prebuilt frontend bundles (served from storage/).
Route::get('/ext-asset/{id}/{surface}', [App\Http\Controllers\ExtAssetController::class, 'show'])
    ->where('id', '[A-Za-z0-9._-]+')->name('ext.asset');

// Community (forum)
Route::get('/', [ForumController::class, 'index'])->name('forum.index');
Route::get('/t/{topic}', [TopicController::class, 'show'])->name('topics.show');
Route::get('/u/{user}', [App\Http\Controllers\UserProfileController::class, 'show'])->name('profiles.show');
Route::get('/extensions', [App\Http\Controllers\ExtensionsPageController::class, 'index'])->name('extensions.index');
Route::get('/members', [App\Http\Controllers\MembersController::class, 'index'])->name('members.index');
Route::get('/leaderboard', [App\Http\Controllers\MembersController::class, 'leaderboard'])->name('leaderboard');

Route::get('/welcome', fn () => Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('welcome');

// Legacy auth landing → send everyone to the community home.
Route::get('/dashboard', fn () => redirect()->route('forum.index'))->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/new', [TopicController::class, 'create'])->name('topics.create');
    Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::post('/t/{topic}/posts', [PostController::class, 'store'])->name('posts.store');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/react', [ReactionController::class, 'toggle'])->name('posts.react');
    Route::post('/uploads/image', [App\Http\Controllers\UploadController::class, 'image'])->name('uploads.image');

    Route::post('/push/subscribe', [App\Http\Controllers\PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [App\Http\Controllers\PushController::class, 'unsubscribe'])->name('push.unsubscribe');

    Route::get('/messages', [App\Http\Controllers\MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [App\Http\Controllers\MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{conversation}', [App\Http\Controllers\MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [App\Http\Controllers\MessageController::class, 'message'])->name('messages.send');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [App\Http\Controllers\AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/settings', [App\Http\Controllers\AdminController::class, 'settings'])->name('settings');
    Route::post('/settings', [App\Http\Controllers\AdminController::class, 'updateSettings'])->name('settings.update');
    Route::get('/email', [App\Http\Controllers\AdminController::class, 'email'])->name('email');
    Route::post('/email', [App\Http\Controllers\AdminController::class, 'updateEmail'])->name('email.update');
    Route::post('/email/test', [App\Http\Controllers\AdminController::class, 'sendTestEmail'])->name('email.test');
    Route::get('/theme', [App\Http\Controllers\AdminController::class, 'theme'])->name('theme');
    Route::post('/theme', [App\Http\Controllers\AdminController::class, 'updateTheme'])->name('theme.update');
    Route::get('/accessibility', [App\Http\Controllers\AdminController::class, 'accessibility'])->name('accessibility');

    Route::get('/members', [App\Http\Controllers\AdminController::class, 'members'])->name('members');
    Route::put('/members/{user}', [App\Http\Controllers\AdminController::class, 'updateMember'])->name('members.update');
    Route::delete('/members/{user}', [App\Http\Controllers\AdminController::class, 'destroyMember'])->name('members.destroy');
    Route::post('/groups', [App\Http\Controllers\AdminController::class, 'storeGroup'])->name('groups.store');
    Route::put('/groups/{group}', [App\Http\Controllers\AdminController::class, 'updateGroup'])->name('groups.update');
    Route::delete('/groups/{group}', [App\Http\Controllers\AdminController::class, 'destroyGroup'])->name('groups.destroy');

    Route::get('/pwa', [App\Http\Controllers\AdminController::class, 'pwa'])->name('pwa');
    Route::post('/pwa', [App\Http\Controllers\AdminController::class, 'updatePwa'])->name('pwa.update');
    Route::post('/pwa/icon', [App\Http\Controllers\AdminController::class, 'uploadIcon'])->name('pwa.icon');

    Route::get('/content', [App\Http\Controllers\AdminController::class, 'content'])->name('content');
    Route::post('/categories', [App\Http\Controllers\AdminController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{category}', [App\Http\Controllers\AdminController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [App\Http\Controllers\AdminController::class, 'destroyCategory'])->name('categories.destroy');
    Route::post('/tags', [App\Http\Controllers\AdminController::class, 'storeTag'])->name('tags.store');
    Route::put('/tags/{tag}', [App\Http\Controllers\AdminController::class, 'updateTag'])->name('tags.update');
    Route::delete('/tags/{tag}', [App\Http\Controllers\AdminController::class, 'destroyTag'])->name('tags.destroy');

    Route::get('/marketplace', [App\Http\Controllers\AdminController::class, 'marketplace'])->name('marketplace');
    Route::post('/marketplace/install', [App\Http\Controllers\AdminController::class, 'installExtension'])->name('marketplace.install');
    Route::post('/marketplace/license', [App\Http\Controllers\AdminController::class, 'installLicense'])->name('marketplace.license');
    Route::post('/marketplace/enable', [App\Http\Controllers\AdminController::class, 'enableExtension'])->name('marketplace.enable');
    Route::post('/marketplace/disable', [App\Http\Controllers\AdminController::class, 'disableExtension'])->name('marketplace.disable');
    Route::post('/marketplace/uninstall', [App\Http\Controllers\AdminController::class, 'uninstallExtension'])->name('marketplace.uninstall');
    Route::post('/marketplace/settings', [App\Http\Controllers\AdminController::class, 'updateExtensionSettings'])->name('marketplace.settings');
    Route::post('/marketplace/composer', [App\Http\Controllers\AdminController::class, 'composerInstall'])->name('marketplace.composer');
    Route::get('/store', [App\Http\Controllers\AdminController::class, 'store'])->name('store');
    Route::post('/store/stripe', [App\Http\Controllers\AdminController::class, 'updateStripe'])->name('store.stripe');
    Route::post('/store/products', [App\Http\Controllers\AdminController::class, 'storeProduct'])->name('products.store');
    Route::put('/store/products/{product}', [App\Http\Controllers\AdminController::class, 'updateProduct'])->name('products.update');
    Route::delete('/store/products/{product}', [App\Http\Controllers\AdminController::class, 'destroyProduct'])->name('products.destroy');
    Route::post('/store/products/{product}/file', [App\Http\Controllers\AdminController::class, 'uploadProductFile'])->name('products.file');
    Route::get('/system', [App\Http\Controllers\AdminController::class, 'system'])->name('system');
    Route::post('/system/run', [App\Http\Controllers\AdminController::class, 'runMaintenance'])->name('system.run');
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
});

require __DIR__.'/auth.php';
