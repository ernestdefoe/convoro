<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Group;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use App\Support\IconGenerator;
use App\Support\Permissions;
use App\Support\Present;
use App\Support\Settings;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
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

    // ---- System / maintenance / updates -----------------------------------

    private function updateState(): array
    {
        $current = (string) config('convoro.version');

        return [
            'current' => $current,
            'latest' => Settings::get('update.latest', $current),
            'available' => (bool) Settings::get('update.available', false),
            'url' => Settings::get('update.url'),
            'checkedAt' => Settings::get('update.checked_at'),
            'enabled' => (bool) config('convoro.update_url'),
            'running' => (bool) Settings::get('update.running', false),
            'lastStatus' => Settings::get('update.last_status'),
        ];
    }

    public function system(): Response
    {
        return Inertia::render('Admin/System', [
            'info' => [
                'version' => config('convoro.version'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'database' => config('database.default'),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
            ],
            'update' => $this->updateState(),
        ]);
    }

    /** Run a maintenance task in-process (no shell needed — shared-hosting friendly). */
    public function runMaintenance(Request $request): RedirectResponse
    {
        $action = (string) $request->input('action');
        $tasks = [
            'cache' => fn () => Artisan::call('optimize:clear'),
            'optimize' => fn () => Artisan::call('optimize'),
            'migrate' => fn () => Artisan::call('migrate', ['--force' => true]),
            'storage' => fn () => Artisan::call('storage:link'),
            'icons' => fn () => Artisan::call('convoro:icons'),
        ];
        abort_unless(isset($tasks[$action]), 422);

        try {
            $tasks[$action]();
            $status = 'Done: '.$action;
        } catch (\Throwable $e) {
            $status = 'Failed: '.$e->getMessage();
        }

        return back()->with('status', $status);
    }

    public function checkUpdates(): RedirectResponse
    {
        $url = config('convoro.update_url');
        $current = (string) config('convoro.version');

        if (! $url) {
            Settings::setMany(['update.available' => false, 'update.latest' => $current, 'update.checked_at' => now()->toDateTimeString()]);

            return back()->with('status', 'Update checks are not configured.');
        }

        try {
            $res = Http::timeout(6)->acceptJson()->get($url);
            $latest = (string) ($res->json('version') ?? $current);
            Settings::setMany([
                'update.latest' => $latest,
                'update.available' => version_compare($latest, $current, '>'),
                'update.url' => $res->json('url'),
                'update.checked_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Settings::set('update.checked_at', now()->toDateTimeString());

            return back()->with('status', 'Could not reach the update server.');
        }

        return back();
    }

    public function applyUpdate(): RedirectResponse
    {
        if (Settings::get('update.running')) {
            return back()->with('status', 'An update is already in progress.');
        }

        Settings::setMany(['update.running' => true, 'update.last_status' => 'Update started…']);
        \App\Jobs\ApplyUpdateJob::dispatch();

        return back()->with('status', 'Update started — it runs in the background and finishes in a moment. Refresh to see the new version.');
    }

    public function marketplace(): Response
    {
        return Inertia::render('Admin/Marketplace', [
            'update' => $this->updateState(),
        ]);
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

    // ---- Members & Groups -------------------------------------------------

    public function members(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w
                ->whereLike('name', "%{$q}%", caseSensitive: false)
                ->orWhereLike('email', "%{$q}%", caseSensitive: false)))
            ->with('groups')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_admin' => (bool) $u->is_admin,
                'joined' => optional($u->created_at)->isoFormat('MMM D, YYYY'),
                'avatar' => $u->avatar_path,
                'initials' => Present::avatar($u)['initials'],
                'color' => Present::avatar($u)['color'],
                'groups' => $u->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'color' => $g->color]),
            ]);

        return Inertia::render('Admin/Members', [
            'users' => $users,
            'q' => $q,
            'groups' => Group::orderBy('name')->get(['id', 'name', 'color', 'is_staff', 'permissions']),
            'permissionCatalog' => Permissions::catalog(),
        ]);
    }

    public function updateMember(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'is_admin' => ['required', 'boolean'],
            'groups' => ['array'],
            'groups.*' => ['integer', 'exists:groups,id'],
        ]);

        $user->update(['name' => $data['name'], 'email' => $data['email'], 'is_admin' => (bool) $data['is_admin']]);
        $user->groups()->sync($data['groups'] ?? []);

        return back()->with('status', 'Member updated.');
    }

    public function destroyMember(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account here.');
        $user->delete();

        return back()->with('status', 'Member deleted.');
    }

    private function groupRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'is_staff' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::keys())],
        ];
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $data = $request->validate($this->groupRules());
        Group::create([
            'name' => $data['name'],
            'color' => $data['color'],
            'is_staff' => $request->boolean('is_staff'),
            'permissions' => array_values($data['permissions'] ?? []),
        ]);

        return back();
    }

    public function updateGroup(Request $request, Group $group): RedirectResponse
    {
        $data = $request->validate($this->groupRules());
        $group->update([
            'name' => $data['name'],
            'color' => $data['color'],
            'is_staff' => $request->boolean('is_staff'),
            'permissions' => array_values($data['permissions'] ?? []),
        ]);

        return back();
    }

    public function destroyGroup(Group $group): RedirectResponse
    {
        $group->delete();

        return back();
    }

    // ---- PWA --------------------------------------------------------------

    public function pwa(): Response
    {
        return Inertia::render('Admin/Pwa', [
            'values' => [
                'short_name' => Settings::get('pwa.short_name'),
                'banner' => (bool) Settings::get('pwa.banner'),
            ],
            'icons' => array_map(fn ($n) => '/icons/'.$n.'?v='.now()->timestamp, array_keys(IconGenerator::SIZES)),
        ]);
    }

    public function updatePwa(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'short_name' => ['required', 'string', 'max:30'],
            'banner' => ['required', 'boolean'],
        ]);
        Settings::setMany(['pwa.short_name' => $data['short_name'], 'pwa.banner' => (bool) $data['banner']]);

        return back()->with('status', 'PWA settings saved.');
    }

    public function uploadIcon(Request $request): RedirectResponse
    {
        $request->validate(['icon' => ['required', 'image', 'max:8192']]);
        IconGenerator::generate(file_get_contents($request->file('icon')->getRealPath()));

        return back()->with('status', 'Icons regenerated from your image.');
    }

    public function settings(): Response
    {
        return Inertia::render('Admin/Settings', [
            'values' => [
                'name' => Settings::get('site.name'),
                'tagline' => Settings::get('site.tagline'),
                'default_view' => Settings::get('forum.default_view'),
                'realtime' => (bool) Settings::get('realtime.enabled'),
                'digests' => (bool) Settings::get('digests.enabled'),
                'pwa_banner' => (bool) Settings::get('pwa.banner'),
                'pwa_short_name' => Settings::get('pwa.short_name'),
                'fa_kit_url' => Settings::get('fa.kit_url'),
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
            'digests' => ['required', 'boolean'],
            'pwa_banner' => ['required', 'boolean'],
            'pwa_short_name' => ['required', 'string', 'max:30'],
            'fa_kit_url' => ['nullable', 'string', 'max:300', 'regex:#^https://[^\s]+\.js$#'],
        ]);

        Settings::setMany([
            'site.name' => $data['name'],
            'site.tagline' => $data['tagline'] ?? '',
            'forum.default_view' => $data['default_view'],
            'realtime.enabled' => (bool) $data['realtime'],
            'digests.enabled' => (bool) $data['digests'],
            'pwa.banner' => (bool) $data['pwa_banner'],
            'pwa.short_name' => $data['pwa_short_name'],
            'fa.kit_url' => $data['fa_kit_url'] ?? '',
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
