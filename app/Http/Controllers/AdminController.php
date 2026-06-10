<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    public function accessibility(): Response
    {
        return Inertia::render('Admin/Accessibility');
    }

    // ---- Categories & Tags ------------------------------------------------

    public function content(): Response
    {
        return Inertia::render('Admin/Content', [
            'categories' => Category::orderBy('position')->withCount('topics')->get()
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'description' => $c->description,
                    'icon' => $c->icon, 'color' => $c->color, 'position' => $c->position, 'topics' => $c->topics_count,
                ]),
            'tags' => Tag::orderBy('name')->withCount('topics')->get()
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'color' => $t->color, 'topics' => $t->topics_count]),
        ]);
    }

    private function uniqueSlug(string $model, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;
        while ($model::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:200'],
            'icon' => ['nullable', 'string', 'max:8'],
            'color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ]);
        $data['slug'] = $this->uniqueSlug(Category::class, $data['name']);
        $data['position'] = (int) (Category::max('position') ?? 0) + 1;
        Category::create($data);

        return back();
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:200'],
            'icon' => ['nullable', 'string', 'max:8'],
            'color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['slug'] = $this->uniqueSlug(Category::class, $data['name'], $category->id);
        $category->update($data);

        return back();
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        abort_if($category->topics()->exists(), 422, 'Move or delete this category\'s topics first.');
        $category->delete();

        return back();
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ]);
        $data['slug'] = $this->uniqueSlug(Tag::class, $data['name']);
        Tag::create($data);

        return back();
    }

    public function updateTag(Request $request, Tag $tag): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ]);
        $data['slug'] = $this->uniqueSlug(Tag::class, $data['name'], $tag->id);
        $tag->update($data);

        return back();
    }

    public function destroyTag(Tag $tag): RedirectResponse
    {
        $tag->topics()->detach();
        $tag->delete();

        return back();
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
            'fonts' => \App\Support\Theme::fontOptions(),
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
