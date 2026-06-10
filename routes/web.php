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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/t/{topic}/posts', [PostController::class, 'store'])->name('posts.store');
    Route::post('/posts/{post}/react', [ReactionController::class, 'toggle'])->name('posts.react');
    Route::post('/uploads/image', [App\Http\Controllers\UploadController::class, 'image'])->name('uploads.image');

    Route::post('/push/subscribe', [App\Http\Controllers\PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [App\Http\Controllers\PushController::class, 'unsubscribe'])->name('push.unsubscribe');

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
});

Route::middleware('auth')->group(function () {
    Route::post('/u/{user}/wall', [App\Http\Controllers\UserProfileController::class, 'storeWall'])->name('profiles.wall.store');
    Route::delete('/profile-posts/{profilePost}', [App\Http\Controllers\UserProfileController::class, 'destroyWall'])->name('profiles.wall.destroy');
    Route::post('/profile/details', [App\Http\Controllers\UserProfileController::class, 'updateDetails'])->name('profile.details');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
