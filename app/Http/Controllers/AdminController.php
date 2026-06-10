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
            'queue' => $this->queueOverview(),
            'analytics' => \App\Support\ExtensionManager::isEnabled('convoro-analytics')
                ? $this->analyticsOverview() : null,
        ]);
    }

    /** Queue / jobs health for the dashboard panel. Best-effort — never throws. */
    private function queueOverview(): array
    {
        $connection = (string) config('queue.default');
        $pending = null;
        try {
            $pending = \Illuminate\Support\Facades\Queue::size();
        } catch (\Throwable) {
        }

        $failed = 0;
        try {
            $failed = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        } catch (\Throwable) {
        }

        return [
            'connection' => $connection,
            'pending' => $pending,
            'failed' => $failed,
            'horizon' => \App\Support\ExtensionManager::isEnabled('convoro-horizon'),
        ];
    }

    /** Analytics summary surfaced on the dashboard when the extension is enabled. */
    private function analyticsOverview(): array
    {
        $since = now()->subDays(13)->startOfDay();
        $rows = \Illuminate\Support\Facades\DB::table('users')->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');
        $signups = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $signups[] = [
                'date' => \Illuminate\Support\Carbon::parse($d)->format('M j'),
                'day' => \Illuminate\Support\Carbon::parse($d)->format('j'),
                'count' => (int) ($rows[$d] ?? 0),
            ];
        }

        $topPosters = \Illuminate\Support\Facades\DB::table('posts')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->select('users.name', \Illuminate\Support\Facades\DB::raw('COUNT(*) c'))
            ->groupBy('users.id', 'users.name')->orderByDesc('c')->limit(5)->get()
            ->map(fn ($p) => ['name' => $p->name, 'count' => (int) $p->c])->all();

        $activeTopics = \Illuminate\Support\Facades\DB::table('topics')
            ->orderByDesc('reply_count')->limit(5)->get(['title', 'slug', 'reply_count'])
            ->map(fn ($t) => ['title' => $t->title, 'slug' => $t->slug, 'replies' => (int) $t->reply_count])->all();

        return [
            'online' => \Illuminate\Support\Facades\DB::table('users')->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'signups' => $signups,
            'topPosters' => $topPosters,
            'activeTopics' => $activeTopics,
        ];
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

    // ---- Store (central, convoro.co) ------------------------------------

    public function store(): Response
    {
        return Inertia::render('Admin/Store', [
            'products' => \App\Models\Product::orderByDesc('featured')->orderBy('name')->withCount('licenses')->get()
                ->map(fn ($p) => [
                    'id' => $p->id, 'slug' => $p->slug, 'name' => $p->name, 'type' => $p->type,
                    'tagline' => $p->tagline, 'description' => $p->description, 'package' => $p->package,
                    'version' => $p->version, 'price_cents' => $p->price_cents, 'image' => $p->image,
                    'published' => $p->published, 'featured' => $p->featured,
                    'status' => $p->status, 'submitter' => $p->submitter_email,
                    'source' => $p->source, 'repo' => $p->repo,
                    'hasDownload' => $p->source === 'github' ? (bool) $p->download_url : (bool) $p->download_path,
                    'sales' => $p->licenses_count,
                ]),
            'stripe' => [
                'key' => Settings::get('stripe.key'),
                'secretSet' => trim((string) Settings::get('stripe.secret')) !== '',
                'webhookSet' => trim((string) Settings::get('stripe.webhook_secret')) !== '',
                'webhookUrl' => url('/store/webhook'),
                'canConnect' => \App\Support\StripeService::canConnect(),
                'connectedAccount' => \App\Support\StripeService::connectedAccount(),
            ],
        ]);
    }

    public function connectStripe(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless(\App\Support\StripeService::canConnect(), 422, 'Stripe Connect is not configured (set STRIPE_CONNECT_CLIENT_ID + STRIPE_SECRET).');

        $request->session()->put('stripe_connect_state', $state = \Illuminate\Support\Str::random(40));

        $redirect = config('convoro.stripe.connect_redirect') ?: route('admin.store.stripe.callback');

        return redirect()->away(\App\Support\StripeService::connectUrl($redirect, $state));
    }

    public function stripeCallback(Request $request): \Illuminate\Http\RedirectResponse
    {
        if ($request->query('state') !== $request->session()->pull('stripe_connect_state')) {
            return redirect()->route('admin.store')->with('status', 'Stripe connection expired — please try again.');
        }
        if ($request->query('error')) {
            return redirect()->route('admin.store')->with('status', 'Stripe connection cancelled.');
        }

        try {
            \App\Support\StripeService::completeConnect((string) $request->query('code'));

            return redirect()->route('admin.store')->with('status', 'Stripe account connected — checkout is live.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.store')->with('status', $e->getMessage());
        }
    }

    public function disconnectStripe(): RedirectResponse
    {
        \App\Support\StripeService::disconnect();

        return back()->with('status', 'Stripe account disconnected.');
    }

    public function updateStripe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);
        Settings::set('stripe.key', $data['key'] ?? '');
        if ($request->filled('secret')) {
            Settings::set('stripe.secret', $data['secret']);
        }
        if ($request->filled('webhook_secret')) {
            Settings::set('stripe.webhook_secret', $data['webhook_secret']);
        }

        return back()->with('status', 'Stripe settings saved.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $this->saveProduct($request, new \App\Models\Product);

        return back();
    }

    public function updateProduct(Request $request, \App\Models\Product $product): RedirectResponse
    {
        $this->saveProduct($request, $product);

        return back();
    }

    public function destroyProduct(\App\Models\Product $product): RedirectResponse
    {
        $product->delete();

        return back();
    }

    public function uploadProductFile(Request $request, \App\Models\Product $product): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:zip', 'max:51200']]);
        $path = 'products/'.$product->slug.'.zip';
        $request->file('file')->storeAs('products', $product->slug.'.zip');
        $product->update(['download_path' => $path]);

        return back()->with('status', 'Download uploaded.');
    }

    private function saveProduct(Request $request, \App\Models\Product $product): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:extension,theme'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'package' => ['nullable', 'string', 'max:120'],
            'version' => ['nullable', 'string', 'max:20'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'string', 'max:2048'],
            'published' => ['boolean'],
            'featured' => ['boolean'],
        ]);

        $data['slug'] = $product->slug ?: $this->uniqueSlug(\App\Models\Product::class, $data['name']);
        $data['version'] = $data['version'] ?: '1.0.0';
        $product->fill($data)->save();
    }

    /** Link (or refresh) a product from a GitHub repository (the registry). */
    public function linkRepo(Request $request): RedirectResponse
    {
        $repo = $request->validate(['repo' => ['required', 'string', 'max:160']])['repo'];

        try {
            $product = \App\Support\GitHubRegistry::linkProduct($repo);

            return back()->with('status', "Linked “{$product->name}” v{$product->version} from GitHub.");
        } catch (\Throwable $e) {
            return back()->with('status', 'Could not link: '.$e->getMessage());
        }
    }

    public function refreshRepo(\App\Models\Product $product): RedirectResponse
    {
        abort_unless($product->source === 'github' && $product->repo, 422);

        try {
            \App\Support\GitHubRegistry::linkProduct($product->repo);

            return back()->with('status', "Refreshed “{$product->name}” from GitHub.");
        } catch (\Throwable $e) {
            return back()->with('status', 'Refresh failed: '.$e->getMessage());
        }
    }

    public function marketplace(): Response
    {
        $enabled = \App\Support\ExtensionManager::enabledIds();
        $installed = collect(\App\Support\ExtensionManager::all())
            ->values()
            ->map(fn ($m) => [
                'id' => $m['id'],
                'name' => $m['name'],
                'version' => $m['version'],
                'description' => $m['description'],
                'author' => $m['author'],
                'type' => $m['type'],
                'premium' => $m['premium'],
                'enabled' => in_array($m['id'], $enabled, true),
                'removable' => $m['_writable'] && str_starts_with($m['_path'], \App\Support\ExtensionInstaller::targetRoot()),
                'settings' => $m['settings'],
                'values' => \App\Support\ExtensionManager::settingValues($m['id']),
                'adminUrl' => $m['admin_url'],
            ]);

        // Browsable catalog from the central store (free items install directly;
        // premium need a license key). Best-effort — never block the page.
        $installedPackages = collect(\App\Support\ExtensionManager::all())->pluck('id')->all();
        $catalog = [];
        try {
            $res = \Illuminate\Support\Facades\Http::acceptJson()->timeout(6)
                ->get(rtrim(config('convoro.store_url'), '/').'/api/catalog');
            if ($res->successful()) {
                $catalog = collect($res->json('items', []))
                    ->reject(fn ($i) => in_array($i['package'] ?? null, $installedPackages, true))
                    ->values()->all();
            }
        } catch (\Throwable) {
        }

        return Inertia::render('Admin/Marketplace', [
            'update' => $this->updateState(),
            'extensions' => $installed,
            'composer' => [
                'available' => \App\Support\ComposerRunner::available(),
                'task' => \App\Jobs\ComposerTaskJob::status(),
            ],
            'catalog' => $catalog,
        ]);
    }

    public function installCatalogItem(Request $request): RedirectResponse
    {
        $slug = $request->validate(['slug' => ['required', 'string']])['slug'];

        try {
            $res = \Illuminate\Support\Facades\Http::acceptJson()->timeout(6)
                ->get(rtrim(config('convoro.store_url'), '/').'/api/catalog');
            $item = collect($res->json('items', []))->firstWhere('slug', $slug);

            if (! $item) {
                return back()->with('extResult', ['ok' => false, 'message' => 'Item not found in the catalog.']);
            }
            if (! ($item['free'] ?? false) || empty($item['download_url'])) {
                return back()->with('extResult', ['ok' => false, 'message' => "“{$item['name']}” is premium — redeem your license key below to install it."]);
            }

            $id = \App\Support\ExtensionInstaller::installFromUrl($item['download_url']);

            return back()->with('extResult', ['ok' => true, 'message' => "Installed “{$id}”. Enable it to activate."]);
        } catch (\Throwable $e) {
            return back()->with('extResult', ['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function composerInstall(Request $request): RedirectResponse
    {
        abort_unless(\App\Support\ComposerRunner::available(), 422, 'Composer is not available on this server.');

        $data = $request->validate([
            'action' => ['required', 'in:require,remove'],
            'package' => ['required', 'string', 'regex:#^[a-z0-9]([a-z0-9._-]*)/[a-z0-9]([a-z0-9._-]*)(:.+)?$#'],
        ]);

        \App\Jobs\ComposerTaskJob::dispatch($data['action'], $data['package']);

        return back()->with('extResult', ['ok' => true, 'message' => "Composer {$data['action']} queued for “{$data['package']}”. This can take a minute."]);
    }

    public function installLicense(Request $request): RedirectResponse
    {
        $key = $request->validate(['key' => ['required', 'string', 'max:64']])['key'];

        try {
            $res = \Illuminate\Support\Facades\Http::asJson()->acceptJson()->timeout(20)
                ->post(rtrim(config('convoro.store_url'), '/').'/api/licenses/validate', ['key' => trim($key)]);

            if (! $res->successful() || ! $res->json('valid')) {
                return back()->with('extResult', ['ok' => false, 'message' => $res->json('message') ?? 'Invalid license key.']);
            }

            $download = $res->json('download_url');
            $name = $res->json('product.name') ?? 'item';
            if (! $download) {
                return back()->with('extResult', ['ok' => true, 'message' => "License for “{$name}” is valid, but no download is available yet."]);
            }

            $id = \App\Support\ExtensionInstaller::installFromUrl($download);

            return back()->with('extResult', ['ok' => true, 'message' => "Installed “{$id}” from your license. Enable it to activate."]);
        } catch (\Throwable $e) {
            return back()->with('extResult', ['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function enableExtension(Request $request): RedirectResponse
    {
        $id = $request->validate(['id' => ['required', 'string']])['id'];
        try {
            \App\Support\ExtensionInstaller::enable($id);

            return back()->with('extResult', ['ok' => true, 'message' => "Enabled “{$id}”."]);
        } catch (\Throwable $e) {
            return back()->with('extResult', ['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function disableExtension(Request $request): RedirectResponse
    {
        $id = $request->validate(['id' => ['required', 'string']])['id'];
        \App\Support\ExtensionInstaller::disable($id);

        return back()->with('extResult', ['ok' => true, 'message' => "Disabled “{$id}”."]);
    }

    public function uninstallExtension(Request $request): RedirectResponse
    {
        $id = $request->validate(['id' => ['required', 'string']])['id'];
        \App\Support\ExtensionInstaller::uninstall($id);

        return back()->with('extResult', ['ok' => true, 'message' => "Removed “{$id}”."]);
    }

    public function extensionSettings(string $id): Response
    {
        $m = collect(\App\Support\ExtensionManager::all())->firstWhere('id', $id);
        abort_if(! $m, 404);

        return Inertia::render('Admin/ExtensionSettings', [
            'ext' => [
                'id' => $m['id'],
                'name' => $m['name'],
                'version' => $m['version'],
                'description' => $m['description'],
                'author' => $m['author'],
                'type' => $m['type'],
                'enabled' => \App\Support\ExtensionManager::isEnabled($m['id']),
                'settings' => $m['settings'],
                'values' => \App\Support\ExtensionManager::settingValues($m['id']),
                'adminUrl' => $m['admin_url'],
            ],
        ]);
    }

    public function updateExtensionSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string'],
            'values' => ['present', 'array'],
        ]);

        $id = $data['id'];
        $schema = \App\Support\ExtensionManager::settingsSchema($id);
        abort_if(empty($schema), 404);

        // Only persist keys the extension actually declares; coerce by type.
        foreach ($schema as $field) {
            $key = $field['key'];
            if (! array_key_exists($key, $data['values'])) {
                continue;
            }
            $value = $data['values'][$key];
            $value = match ($field['type'] ?? 'text') {
                'boolean' => (bool) $value,
                'number' => is_numeric($value) ? $value + 0 : 0,
                default => is_scalar($value) ? (string) $value : '',
            };
            \App\Support\Settings::set(\App\Support\ExtensionManager::settingKey($id, $key), $value);
        }

        return back()->with('extResult', ['ok' => true, 'message' => 'Settings saved.']);
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
                'logo' => Settings::get('site.logo'),
                'default_view' => Settings::get('forum.default_view'),
                'realtime' => (bool) Settings::get('realtime.enabled'),
                'digests' => (bool) Settings::get('digests.enabled'),
                'pwa_banner' => (bool) Settings::get('pwa.banner'),
                'pwa_short_name' => Settings::get('pwa.short_name'),
                'fa_kit_url' => Settings::get('fa.kit_url'),
                'seo_description' => Settings::get('seo.description'),
                'seo_image' => Settings::get('seo.image'),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'default_view' => ['required', 'in:feed,grid'],
            'realtime' => ['required', 'boolean'],
            'digests' => ['required', 'boolean'],
            'pwa_banner' => ['required', 'boolean'],
            'pwa_short_name' => ['required', 'string', 'max:30'],
            'fa_kit_url' => ['nullable', 'string', 'max:300', 'regex:#^https://[^\s]+\.js$#'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'seo_image' => ['nullable', 'string', 'max:2048'],
        ]);

        Settings::setMany([
            'site.name' => $data['name'],
            'site.tagline' => $data['tagline'] ?? '',
            'site.logo' => $data['logo'] ?? '',
            'forum.default_view' => $data['default_view'],
            'realtime.enabled' => (bool) $data['realtime'],
            'digests.enabled' => (bool) $data['digests'],
            'pwa.banner' => (bool) $data['pwa_banner'],
            'pwa.short_name' => $data['pwa_short_name'],
            'fa.kit_url' => $data['fa_kit_url'] ?? '',
            'seo.description' => $data['seo_description'] ?? '',
            'seo.image' => $data['seo_image'] ?? '',
        ]);

        return back();
    }

    public function email(): Response
    {
        return Inertia::render('Admin/Email', [
            'values' => [
                'configured' => (bool) Settings::get('mail.configured'),
                'transport' => Settings::get('mail.transport'),
                'from_address' => Settings::get('mail.from_address'),
                'from_name' => Settings::get('mail.from_name'),
                'smtp_host' => Settings::get('mail.smtp_host'),
                'smtp_port' => (int) Settings::get('mail.smtp_port'),
                'smtp_username' => Settings::get('mail.smtp_username'),
                // Never echo the stored password back; show only whether one is set.
                'smtp_password_set' => trim((string) Settings::get('mail.smtp_password')) !== '',
                'smtp_encryption' => Settings::get('mail.smtp_encryption'),
            ],
        ]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transport' => ['required', 'in:sendmail,smtp'],
            'from_address' => ['nullable', 'email', 'max:160'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'smtp_host' => ['nullable', 'string', 'max:160'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:160'],
            'smtp_password' => ['nullable', 'string', 'max:300'],
            'smtp_encryption' => ['required', 'in:tls,ssl,none'],
        ]);

        $values = [
            'mail.configured' => true,
            'mail.transport' => $data['transport'],
            'mail.from_address' => $data['from_address'] ?? '',
            'mail.from_name' => $data['from_name'] ?? '',
            'mail.smtp_host' => $data['smtp_host'] ?? '',
            'mail.smtp_port' => $data['smtp_port'] ?? 587,
            'mail.smtp_username' => $data['smtp_username'] ?? '',
            'mail.smtp_encryption' => $data['smtp_encryption'],
        ];
        // Only overwrite the password when a new one was typed (blank = keep existing).
        if ($request->filled('smtp_password')) {
            $values['mail.smtp_password'] = $data['smtp_password'];
        }

        Settings::setMany($values);

        return back();
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $to = $request->validate(['email' => ['required', 'email']])['email'];

        // Re-apply settings to this request before sending.
        \App\Support\MailConfig::apply();

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "This is a test email from your Convoro community. If you received it, outgoing mail is working.",
                function ($m) use ($to) {
                    $m->to($to)->subject('Convoro test email');
                }
            );

            return back()->with('mailTest', ['ok' => true, 'message' => "Test email sent to {$to}."]);
        } catch (\Throwable $e) {
            return back()->with('mailTest', ['ok' => false, 'message' => $e->getMessage()]);
        }
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
        $fonts = implode(',', array_keys(\App\Support\Theme::FONTS));
        $data = $request->validate([
            'primary' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'accent' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'radius' => ['required', 'integer', 'min:0', 'max:28'],
            'button_radius' => ['nullable', 'integer', 'min:0', 'max:28'],
            'mode' => ['required', 'in:light,dark'],
            'font' => ['required', 'in:'.$fonts],
            'heading_font' => ['nullable', 'in:,'.$fonts],
            'font_size' => ['required', 'integer', 'min:12', 'max:20'],
            'container' => ['required', 'integer', 'in:0,1100,1240,1400,1600'],
            'avatar_shape' => ['nullable', 'in:circle,rounded,square'],
            'post_style' => ['nullable', 'in:card,bordered,flat'],
            'header_bg' => ['nullable', 'in:surface,brand,custom'],
            'header_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'density' => ['nullable', 'in:compact,comfortable,spacious'],
            'link_color' => ['nullable', 'in:primary,accent'],
            'custom_css' => ['nullable', 'string', 'max:20000'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'favicon' => ['nullable', 'string', 'max:2048'],
        ]);

        Settings::setMany([
            'theme.primary' => $data['primary'],
            'theme.accent' => $data['accent'] ?? Settings::get('theme.accent', '#8b5cf6'),
            'theme.radius' => (int) $data['radius'],
            'theme.button_radius' => isset($data['button_radius']) && $data['button_radius'] !== null ? (string) $data['button_radius'] : '',
            'theme.mode' => $data['mode'],
            'theme.font' => $data['font'],
            'theme.heading_font' => $data['heading_font'] ?? '',
            'theme.font_size' => (int) $data['font_size'],
            'theme.container' => (int) $data['container'],
            'theme.avatar_shape' => $data['avatar_shape'] ?? Settings::get('theme.avatar_shape', 'circle'),
            'theme.post_style' => $data['post_style'] ?? Settings::get('theme.post_style', 'card'),
            'theme.header_bg' => $data['header_bg'] ?? 'surface',
            'theme.header_color' => $data['header_color'] ?? Settings::get('theme.header_color', '#5b5bd6'),
            'theme.density' => $data['density'] ?? 'comfortable',
            'theme.link_color' => $data['link_color'] ?? 'primary',
            'theme.custom_css' => $data['custom_css'] ?? '',
            'site.logo' => $data['logo'] ?? Settings::get('site.logo', ''),
            'site.favicon' => $data['favicon'] ?? Settings::get('site.favicon', ''),
        ]);

        return back();
    }

    /** Persist the forum sidebar widget layout from the live editor (drag & drop). */
    public function updateWidgets(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'widgets' => ['present', 'array'],
            'widgets.*.id' => ['required', 'string', 'max:40'],
            'widgets.*.type' => ['required', 'string', 'in:text,stats,newest_members,online_now,top_posters,categories'],
            'widgets.*.title' => ['nullable', 'string', 'max:80'],
            'widgets.*.body' => ['nullable', 'string', 'max:8000'],
        ]);

        $clean = array_map(fn ($w) => [
            'id' => $w['id'],
            'type' => $w['type'],
            'title' => $w['title'] ?? '',
            'body' => $w['type'] === 'text' ? ($w['body'] ?? '') : '',
        ], $data['widgets']);

        Settings::set('widgets.sidebar', json_encode(array_values($clean)));

        return back();
    }
}
