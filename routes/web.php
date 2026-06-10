<?php

use App\Http\Controllers\ForumController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// PWA
Route::get('/manifest.webmanifest', [App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');

// Community (forum)
Route::get('/', [ForumController::class, 'index'])->name('forum.index');
Route::get('/t/{topic}', [TopicController::class, 'show'])->name('topics.show');
Route::get('/u/{user}', [App\Http\Controllers\UserProfileController::class, 'show'])->name('profiles.show');

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
    Route::get('/theme', [App\Http\Controllers\AdminController::class, 'theme'])->name('theme');
    Route::post('/theme', [App\Http\Controllers\AdminController::class, 'updateTheme'])->name('theme.update');
    Route::get('/accessibility', [App\Http\Controllers\AdminController::class, 'accessibility'])->name('accessibility');

    Route::get('/content', [App\Http\Controllers\AdminController::class, 'content'])->name('content');
    Route::post('/categories', [App\Http\Controllers\AdminController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{category}', [App\Http\Controllers\AdminController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [App\Http\Controllers\AdminController::class, 'destroyCategory'])->name('categories.destroy');
    Route::post('/tags', [App\Http\Controllers\AdminController::class, 'storeTag'])->name('tags.store');
    Route::put('/tags/{tag}', [App\Http\Controllers\AdminController::class, 'updateTag'])->name('tags.update');
    Route::delete('/tags/{tag}', [App\Http\Controllers\AdminController::class, 'destroyTag'])->name('tags.destroy');

    Route::get('/marketplace', [App\Http\Controllers\AdminController::class, 'marketplace'])->name('marketplace');
    Route::get('/system', [App\Http\Controllers\AdminController::class, 'system'])->name('system');
    Route::post('/system/run', [App\Http\Controllers\AdminController::class, 'runMaintenance'])->name('system.run');
    Route::post('/system/check-updates', [App\Http\Controllers\AdminController::class, 'checkUpdates'])->name('system.check');
    Route::post('/system/update', [App\Http\Controllers\AdminController::class, 'applyUpdate'])->name('system.update');
});

Route::middleware('auth')->group(function () {
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
