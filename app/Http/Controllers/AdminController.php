<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Reaction;
use App\Models\Topic;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'topics' => Topic::count(),
                'posts' => Post::count(),
                'reactions' => Reaction::count(),
            ],
            'newUsers' => User::latest()->limit(5)->get(['id', 'name', 'created_at'])
                ->map(fn ($u) => [
                    'name' => $u->name,
                    'joined' => optional($u->created_at)->diffForHumans(),
                ]),
        ]);
    }

    public function settings(): Response
    {
        return Inertia::render('Admin/Settings', [
            'values' => [
                'name' => Settings::get('site.name'),
                'tagline' => Settings::get('site.tagline'),
                'default_view' => Settings::get('forum.default_view'),
                'realtime' => (bool) Settings::get('realtime.enabled'),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'default_view' => ['required', 'in:feed,grid'],
            'realtime' => ['required', 'boolean'],
        ]);

        Settings::setMany([
            'site.name' => $data['name'],
            'site.tagline' => $data['tagline'] ?? '',
            'forum.default_view' => $data['default_view'],
            'realtime.enabled' => (bool) $data['realtime'],
        ]);

        return back();
    }

    public function theme(): Response
    {
        return Inertia::render('Admin/Theme', [
            'theme' => [
                'primary' => Settings::get('theme.primary'),
                'radius' => (int) Settings::get('theme.radius'),
                'mode' => Settings::get('theme.mode'),
                'font' => Settings::get('theme.font'),
                'font_size' => (int) Settings::get('theme.font_size'),
                'container' => (int) Settings::get('theme.container'),
            ],
            'fonts' => collect(\App\Support\Theme::FONTS)
                ->map(fn ($f, $key) => ['value' => $key, 'label' => $f[0]])->values(),
        ]);
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'primary' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'radius' => ['required', 'integer', 'min:0', 'max:28'],
            'mode' => ['required', 'in:light,dark'],
            'font' => ['required', 'in:'.implode(',', array_keys(\App\Support\Theme::FONTS))],
            'font_size' => ['required', 'integer', 'min:12', 'max:20'],
            'container' => ['required', 'integer', 'in:0,1100,1240,1400,1600'],
        ]);

        Settings::setMany([
            'theme.primary' => $data['primary'],
            'theme.radius' => (int) $data['radius'],
            'theme.mode' => $data['mode'],
            'theme.font' => $data['font'],
            'theme.font_size' => (int) $data['font_size'],
            'theme.container' => (int) $data['container'],
        ]);

        return back();
    }
}
