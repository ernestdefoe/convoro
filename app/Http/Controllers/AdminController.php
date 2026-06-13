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
            'onboarding' => $this->onboardingSteps(),
            // System (consolidated onto the dashboard).
            'update' => $this->updateState(),
            'health' => \App\Support\Health::checks(),
            'info' => [
                'version' => config('convoro.version'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'database' => config('database.default'),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
            ],
        ]);
    }

    /** First-run "getting started" checklist for the dashboard. */
    private function onboardingSteps(): array
    {
        $steps = [
            ['label' => __('Brand your community'), 'detail' => __('Add a logo and tune your colors in the live theme editor.'), 'href' => '/', 'done' => (bool) Settings::get('site.logo') || (bool) Settings::get('theme.customized', false)],
            ['label' => __('Create categories'), 'detail' => __('Give members a place to post.'), 'href' => '/admin/content', 'done' => Category::count() > 0],
            ['label' => __('Set up email'), 'detail' => __('So notifications and password resets actually send.'), 'href' => '/admin/email', 'done' => (bool) Settings::get('mail.configured', false)],
            ['label' => __('Add an extension'), 'detail' => __('Browse the marketplace and install your first add-on.'), 'href' => '/admin/marketplace', 'done' => count(\App\Support\ExtensionManager::enabledIds()) > 0],
            ['label' => __('Set your app icon'), 'detail' => __('One image becomes every PWA icon size.'), 'href' => '/admin/pwa', 'done' => (bool) Settings::get('pwa.custom_icon', false) || (bool) Settings::get('site.favicon')],
            ['label' => __('Invite your first members'), 'detail' => __('A community is people — bring some in.'), 'href' => '/members', 'done' => User::count() > 1],
        ];

        return [
            'steps' => $steps,
            'done' => collect($steps)->where('done', true)->count(),
            'total' => count($steps),
            'dismissed' => (bool) Settings::get('onboarding.wizard_dismissed', false),
        ];
    }

    /** Don't auto-open the setup wizard again (admin chose "finish later"). */
    public function dismissOnboarding(): RedirectResponse
    {
        Settings::setMany(['onboarding.wizard_dismissed' => true]);

        return back();
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
            'title' => Settings::get('update.title'),
            'notes' => Settings::get('update.notes'),
            'changelog' => Settings::get('update.changelog', []),
            'checkedAt' => Settings::get('update.checked_at'),
            'enabled' => (bool) config('convoro.update_url'),
            'running' => (bool) Settings::get('update.running', false),
            'lastStatus' => Settings::get('update.last_status'),
        ];
    }

    /** Lightweight JSON poll so the admin UI can auto-refresh when an update finishes. */
    public function updateStatus(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'running' => (bool) Settings::get('update.running', false),
            'current' => (string) config('convoro.version'),
            'latest' => Settings::get('update.latest', (string) config('convoro.version')),
            'lastStatus' => Settings::get('update.last_status'),
        ]);
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
            // Clear hung/stuck jobs: drop pending + failed jobs, then reload the
            // workers so the queue starts fresh and new attempts can proceed.
            'queue' => function () {
                try {
                    Artisan::call('queue:clear', ['--force' => true]);
                } catch (\Throwable) {
                }
                try {
                    Artisan::call('queue:flush');
                } catch (\Throwable) {
                }
                Artisan::call('queue:restart');
            },
        ];
        abort_unless(isset($tasks[$action]), 422);

        try {
            $tasks[$action]();
            $status = __('Done: :action', ['action' => $action]);
        } catch (\Throwable $e) {
            $status = __('Failed: :error', ['error' => $e->getMessage()]);
        }

        return back()->with('status', $status);
    }

    public function checkUpdates(): RedirectResponse
    {
        $url = config('convoro.update_url');
        $current = (string) config('convoro.version');

        if (! $url) {
            Settings::setMany(['update.available' => false, 'update.latest' => $current, 'update.checked_at' => now()->toDateTimeString()]);

            return back()->with('status', __('Update checks are not configured.'));
        }

        try {
            $res = Http::timeout(6)->acceptJson()->get($url);
            $latest = (string) ($res->json('version') ?? $current);
            $changelog = $res->json('changelog');
            Settings::setMany([
                'update.latest' => $latest,
                'update.available' => version_compare($latest, $current, '>'),
                'update.url' => $res->json('url'),
                'update.title' => $res->json('title'),
                'update.notes' => $res->json('notes'),
                'update.changelog' => is_array($changelog) ? $changelog : [],
                'update.checked_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Settings::set('update.checked_at', now()->toDateTimeString());

            return back()->with('status', __('Could not reach the update server.'));
        }

        return back();
    }

    public function applyUpdate(): RedirectResponse
    {
        if (Settings::get('update.running')) {
            return back()->with('status', __('An update is already in progress.'));
        }

        Settings::setMany(['update.running' => true, 'update.last_status' => __('Update started…')]);
        \App\Jobs\ApplyUpdateJob::dispatch();

        return back()->with('status', __('Update started — it runs in the background and finishes in a moment. Refresh to see the new version.'));
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
        abort_unless(\App\Support\StripeService::canConnect(), 422, __('Stripe Connect is not configured (set STRIPE_CONNECT_CLIENT_ID + STRIPE_SECRET).'));

        $request->session()->put('stripe_connect_state', $state = \Illuminate\Support\Str::random(40));

        $redirect = config('convoro.stripe.connect_redirect') ?: route('admin.store.stripe.callback');

        return redirect()->away(\App\Support\StripeService::connectUrl($redirect, $state));
    }

    public function stripeCallback(Request $request): \Illuminate\Http\RedirectResponse
    {
        if ($request->query('state') !== $request->session()->pull('stripe_connect_state')) {
            return redirect('/extensions/manage')->with('status', __('Stripe connection expired — please try again.'));
        }
        if ($request->query('error')) {
            return redirect('/extensions/manage')->with('status', __('Stripe connection cancelled.'));
        }

        try {
            \App\Support\StripeService::completeConnect((string) $request->query('code'));

            return redirect('/extensions/manage')->with('status', __('Stripe account connected — checkout is live.'));
        } catch (\Throwable $e) {
            return redirect('/extensions/manage')->with('status', $e->getMessage());
        }
    }

    public function disconnectStripe(): RedirectResponse
    {
        \App\Support\StripeService::disconnect();

        return back()->with('status', __('Stripe account disconnected.'));
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

        return back()->with('status', __('Stripe settings saved.'));
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

    /** Queue an unbiased AI security review of a GitHub product's source. */
    public function reviewProduct(\App\Models\Product $product): RedirectResponse
    {
        abort_unless(\App\Support\ExtensionReview::reviewable($product), 422);
        if (! \App\Support\ExtensionReview::enabled()) {
            return back()->with('status', __('Add an AI API key under “AI code review” first.'));
        }
        \App\Support\GitHubRegistry::queueReview($product);

        return back()->with('status', __('Security review queued for “:name”.', ['name' => $product->name]));
    }

    /** Save the AI-review config (shares the ai.* keys with the AI Helper). */
    public function updateReviewAi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'provider' => ['required', 'in:anthropic,openai'],
            'model' => ['nullable', 'string', 'max:120'],
            'base_url' => ['nullable', 'string', 'max:300'],
            'api_key' => ['nullable', 'string', 'max:300'],
        ]);
        Settings::setMany([
            'review.enabled' => $request->boolean('enabled'),
            'ai.provider' => $data['provider'],
            'ai.model' => $data['model'] ?? '',
            'ai.base_url' => $data['base_url'] ?? '',
        ]);
        if ($request->filled('api_key')) {
            Settings::set('ai.api_key', $data['api_key']);
        }

        return back()->with('status', __('AI code-review settings saved.'));
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

        // Auto-queue an AI security review of the freshly uploaded package.
        \App\Support\GitHubRegistry::queueReview($product);

        return back()->with('status', __('Download uploaded.'));
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
            'pinned_version' => ['nullable', 'string', 'max:60'],
        ]);

        $newPin = isset($data['pinned_version']) && trim((string) $data['pinned_version']) !== ''
            ? trim($data['pinned_version']) : null;
        unset($data['pinned_version']);
        $pinChanged = $product->source === 'github' && $newPin !== $product->pinned_version;

        $data['slug'] = $product->slug ?: $this->uniqueSlug(\App\Models\Product::class, $data['name']);
        $data['version'] = $data['version'] ?: '1.0.0';
        $product->fill($data);
        $product->pinned_version = $newPin;
        $product->save();

        // When a GitHub product's pin changes, re-resolve to that tag (or back
        // to latest if cleared) so the download URL + version follow the pin.
        if ($pinChanged) {
            try {
                \App\Support\GitHubRegistry::syncProduct($product);
            } catch (\Throwable) {
            }
        }
    }

    /** Link (or refresh) a product from a GitHub repository (the registry). */
    /** (Re)generate branded cover images for every catalog product. */
    public function generateCovers(): RedirectResponse
    {
        $n = 0;
        foreach (\App\Models\Product::all() as $product) {
            try {
                \App\Support\CoverImage::generate($product);
                $n++;
            } catch (\Throwable) {
            }
        }

        return back()->with('status', __('Generated :count cover images.', ['count' => $n]));
    }

    public function linkRepo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'repo' => ['required', 'string', 'max:160'],
            'pin' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $product = \App\Support\GitHubRegistry::linkProduct($data['repo'], $data['pin'] ?? null);

            return back()->with('status', __('Linked “:name” v:version from GitHub.', ['name' => $product->name, 'version' => $product->version]));
        } catch (\Throwable $e) {
            return back()->with('status', __('Could not link: :error', ['error' => $e->getMessage()]));
        }
    }

    public function refreshRepo(\App\Models\Product $product): RedirectResponse
    {
        abort_unless($product->source === 'github' && $product->repo, 422);

        try {
            $changed = \App\Support\GitHubRegistry::syncProduct($product);
            $msg = $changed
                ? __('Updated “:name” → v:version.', ['name' => $product->name, 'version' => $product->version])
                : __('“:name” is already up to date (v:version).', ['name' => $product->name, 'version' => $product->version]);

            return back()->with('status', $msg);
        } catch (\Throwable $e) {
            return back()->with('status', __('Refresh failed: :error', ['error' => $e->getMessage()]));
        }
    }

    public function marketplace(): Response
    {
        $enabled = \App\Support\ExtensionManager::enabledIds();
        $covers = \App\Models\Product::whereNotNull('image')->pluck('image', 'package'); // package => cover URL
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
                'image' => $covers[$m['id']] ?? null,
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
                return back()->with('extResult', ['ok' => false, 'message' => __('Item not found in the catalog.')]);
            }
            if (! ($item['free'] ?? false) || empty($item['download_url'])) {
                return back()->with('extResult', ['ok' => false, 'message' => __('“:name” is premium — redeem your license key below to install it.', ['name' => $item['name']])]);
            }

            $id = \App\Support\ExtensionInstaller::installFromUrl($item['download_url']);

            return back()->with('extResult', ['ok' => true, 'message' => __('Installed “:id”. Enable it to activate.', ['id' => $id])]);
        } catch (\Throwable $e) {
            return back()->with('extResult', ['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function composerInstall(Request $request): RedirectResponse
    {
        abort_unless(\App\Support\ComposerRunner::available(), 422, __('Composer is not available on this server.'));

        $data = $request->validate([
            'action' => ['required', 'in:require,remove'],
            'package' => ['required', 'string', 'regex:#^[a-z0-9]([a-z0-9._-]*)/[a-z0-9]([a-z0-9._-]*)(:.+)?$#'],
        ]);

        \App\Jobs\ComposerTaskJob::dispatch($data['action'], $data['package']);

        return back()->with('extResult', ['ok' => true, 'message' => __('Composer :action queued for “:package”. This can take a minute.', ['action' => $data['action'], 'package' => $data['package']])]);
    }

    public function installLicense(Request $request): RedirectResponse
    {
        $key = $request->validate(['key' => ['required', 'string', 'max:64']])['key'];

        try {
            $res = \Illuminate\Support\Facades\Http::asJson()->acceptJson()->timeout(20)
                ->post(rtrim(config('convoro.store_url'), '/').'/api/licenses/validate', ['key' => trim($key)]);

            if (! $res->successful() || ! $res->json('valid')) {
                return back()->with('extResult', ['ok' => false, 'message' => $res->json('message') ?? __('Invalid license key.')]);
            }

            $download = $res->json('download_url');
            $name = $res->json('product.name') ?? 'item';
            if (! $download) {
                return back()->with('extResult', ['ok' => true, 'message' => __('License for “:name” is valid, but no download is available yet.', ['name' => $name])]);
            }

            $id = \App\Support\ExtensionInstaller::installFromUrl($download);

            return back()->with('extResult', ['ok' => true, 'message' => __('Installed “:id” from your license. Enable it to activate.', ['id' => $id])]);
        } catch (\Throwable $e) {
            return back()->with('extResult', ['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function enableExtension(Request $request): RedirectResponse
    {
        $id = $request->validate(['id' => ['required', 'string']])['id'];
        try {
            \App\Support\ExtensionInstaller::enable($id);

            return back()->with('extResult', ['ok' => true, 'message' => __('Enabled “:id”.', ['id' => $id])]);
        } catch (\Throwable $e) {
            return back()->with('extResult', ['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function disableExtension(Request $request): RedirectResponse
    {
        $id = $request->validate(['id' => ['required', 'string']])['id'];
        \App\Support\ExtensionInstaller::disable($id);

        return back()->with('extResult', ['ok' => true, 'message' => __('Disabled “:id”.', ['id' => $id])]);
    }

    public function uninstallExtension(Request $request): RedirectResponse
    {
        $id = $request->validate(['id' => ['required', 'string']])['id'];
        \App\Support\ExtensionInstaller::uninstall($id);

        return back()->with('extResult', ['ok' => true, 'message' => __('Removed “:id”.', ['id' => $id])]);
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

        return back()->with('extResult', ['ok' => true, 'message' => __('Settings saved.')]);
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
            'icon' => ['nullable', 'string', 'max:40'],
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
            'icon' => ['nullable', 'string', 'max:40'],
            'color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['slug'] = $this->uniqueSlug(Category::class, $data['name'], $category->id);
        $category->update($data);

        return back();
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        abort_if($category->topics()->exists(), 422, __('Move or delete this category\'s topics first.'));
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
                'trust_level' => (int) $u->trust_level,
                'trust_locked' => (bool) $u->trust_locked,
                'reg_ip' => $u->registration_ip,
                'last_ip' => $u->last_ip,
            ]);

        return Inertia::render('Admin/Members', [
            'users' => $users,
            'q' => $q,
            'groups' => Group::orderByDesc('priority')->orderBy('name')->get(['id', 'key', 'name', 'color', 'is_staff', 'priority', 'permissions']),
            'permissionCatalog' => Permissions::catalog(),
            'trustLevels' => \App\Support\TrustLevels::enabled() ? \App\Support\TrustLevels::levels() : null,
        ]);
    }

    /** Geolocate an IP for the admin "look up" action. */
    public function ipLookup(Request $request): \Illuminate\Http\JsonResponse
    {
        $ip = (string) $request->query('ip', '');

        return response()->json(['info' => \App\Support\IpInfo::lookup($ip)]);
    }

    public function updateMember(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'is_admin' => ['required', 'boolean'],
            'groups' => ['array'],
            'groups.*' => ['integer', 'exists:groups,id'],
            // 'auto' lets the ladder manage them; a number pins (locks) the level.
            'trust' => ['nullable', 'string', 'in:auto,0,1,4'],
        ]);

        $user->update(['name' => $data['name'], 'email' => $data['email'], 'is_admin' => (bool) $data['is_admin']]);
        $user->groups()->sync($data['groups'] ?? []);

        if (\App\Support\TrustLevels::enabled() && array_key_exists('trust', $data) && $data['trust'] !== null) {
            if ($data['trust'] === 'auto') {
                $user->update(['trust_locked' => false]);
                \App\Support\TrustLevels::evaluate($user->fresh());
            } else {
                \App\Support\TrustLevels::set($user, (int) $data['trust'], lock: true);
            }
        }

        return back()->with('status', __('Member updated.'));
    }

    /** GDPR: erase personal data but keep the member's posts & topics. */
    public function anonymizeMember(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, __('You can’t anonymize your own account here.'));
        $c = \App\Support\Gdpr::anonymize($user);

        return back()->with('status', __('Member anonymized — personal data erased; :posts posts and :topics topics kept.', ['posts' => $c['posts'], 'topics' => $c['topics']]));
    }

    /** GDPR: full erasure — delete the account and everything they authored. */
    public function destroyMember(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, __('You cannot delete your own account here.'));
        $c = \App\Support\Gdpr::erase($user);

        return back()->with('status', __('Member deleted along with their content (:posts posts, :topics topics).', ['posts' => $c['posts'], 'topics' => $c['topics']]));
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
        // System groups (Admin/Moderator) are recolorable but not deletable.
        abort_if($group->key !== null, 422, __('System groups can’t be deleted.'));
        $group->delete();

        return back();
    }

    // ---- PWA --------------------------------------------------------------

    public function pwa(): Response
    {
        $pub = \App\Support\WebPush::publicKey();

        return Inertia::render('Admin/Pwa', [
            'values' => [
                'short_name' => Settings::get('pwa.short_name'),
                'banner' => (bool) Settings::get('pwa.banner'),
            ],
            'icons' => array_map(fn ($n) => '/icons/'.$n.'?v='.now()->timestamp, array_keys(IconGenerator::SIZES)),
            'push' => [
                'configured' => \App\Support\WebPush::configured(),
                'publicKey' => $pub !== '' ? substr($pub, 0, 12).'…'.substr($pub, -6) : '',
                'subscriptions' => (int) \Illuminate\Support\Facades\DB::table('push_subscriptions')->count(),
            ],
        ]);
    }

    /** Generate a fresh VAPID keypair (rotates push keys; clears all subscriptions). */
    public function regenerateVapid(): RedirectResponse
    {
        try {
            $cleared = \App\Support\WebPush::regenerate();
        } catch (\Throwable $e) {
            return back()->withErrors(['vapid' => __('Could not generate push keys — your server is missing OpenSSL elliptic-curve support. Set VAPID keys in your .env instead.')]);
        }

        return back()->with('status', __('New push keys generated. :n device subscription(s) were cleared — members will be asked to re-enable push.', ['n' => $cleared]));
    }

    public function updatePwa(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'short_name' => ['required', 'string', 'max:30'],
            'banner' => ['required', 'boolean'],
        ]);
        Settings::setMany(['pwa.short_name' => $data['short_name'], 'pwa.banner' => (bool) $data['banner']]);

        return back()->with('status', __('PWA settings saved.'));
    }

    public function uploadIcon(Request $request): RedirectResponse
    {
        $request->validate(['icon' => ['required', 'image', 'max:8192']]);
        IconGenerator::generate(file_get_contents($request->file('icon')->getRealPath()));
        Settings::set('pwa.custom_icon', true); // the admin uploaded their own app icon

        return back()->with('status', __('Icons regenerated from your image.'));
    }

    public function settings(): Response
    {
        return Inertia::render('Admin/Settings', [
            'values' => [
                'name' => Settings::get('site.name'),
                'tagline' => Settings::get('site.tagline'),
                'logo' => Settings::get('site.logo'),
                'logo_dark' => Settings::get('site.logo_dark'),
                'favicon' => Settings::get('site.favicon'),
                'default_view' => Settings::get('forum.default_view'),
                'realtime' => (bool) Settings::get('realtime.enabled'),
                'digests' => (bool) Settings::get('digests.enabled'),
                'pwa_banner' => (bool) Settings::get('pwa.banner'),
                'pwa_short_name' => Settings::get('pwa.short_name'),
                'fa_kit_url' => Settings::get('fa.kit_url'),
                'seo_description' => Settings::get('seo.description'),
                'seo_image' => Settings::get('seo.image'),
                'gif_provider' => Settings::get('composer.gif_provider', 'none'),
                'gif_key_set' => (bool) Settings::get('composer.gif_key', ''),
                'trust_enabled' => (bool) Settings::get('trust.enabled', true),
                'trust_gate_new_users' => (bool) Settings::get('trust.gate_new_users', true),
                'reply_enabled' => (bool) Settings::get('mail.reply_enabled', false),
                'reply_domain' => Settings::get('mail.reply_domain', ''),
                'reply_secret' => (string) Settings::get('mail.reply_secret', ''),
                'reply_webhook' => url('/mail/inbound'),
            ],
            'mobileNav' => \App\Support\MobileNav::share(),
            'federation' => [
                'enabled' => \App\Support\Federation::enabled(),
                'username' => \App\Support\Federation::username(),
                'handle' => \App\Support\Federation::handle(),
                'followers' => \Illuminate\Support\Facades\Schema::hasTable('federation_followers')
                    ? (int) \Illuminate\Support\Facades\DB::table('federation_followers')->count() : 0,
            ],
        ]);
    }

    /** Enable/disable ActivityPub federation + set the public handle. */
    public function updateFederation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'username' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/i'],
        ]);
        Settings::setMany([
            'federation.enabled' => (bool) $data['enabled'],
            'federation.username' => strtolower((string) ($data['username'] ?? '')),
        ]);
        // Make sure a keypair exists once it's switched on.
        if ($data['enabled']) {
            try {
                \App\Support\Federation::keys();
            } catch (\Throwable $e) {
                return back()->withErrors(['federation' => __('Could not generate federation keys — your server is missing OpenSSL support.')]);
            }
        }

        return back()->with('status', __('Federation settings saved.'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'logo_dark' => ['nullable', 'string', 'max:2048'],
            'favicon' => ['nullable', 'string', 'max:2048'],
            'default_view' => ['required', 'in:feed,grid'],
            'realtime' => ['required', 'boolean'],
            'digests' => ['required', 'boolean'],
            'pwa_banner' => ['required', 'boolean'],
            'pwa_short_name' => ['required', 'string', 'max:30'],
            'fa_kit_url' => ['nullable', 'string', 'max:300', 'regex:#^https://[^\s]+\.js$#'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'seo_image' => ['nullable', 'string', 'max:2048'],
            'gif_provider' => ['required', 'in:none,tenor,giphy'],
            'gif_key' => ['nullable', 'string', 'max:200'],
            'trust_enabled' => ['required', 'boolean'],
            'trust_gate_new_users' => ['required', 'boolean'],
            'reply_enabled' => ['required', 'boolean'],
            'reply_domain' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9.\-]*$/i'],
        ]);

        // Generate the reply-token secret the first time reply-by-email is enabled.
        if ($data['reply_enabled'] && ! Settings::get('mail.reply_secret')) {
            Settings::set('mail.reply_secret', bin2hex(random_bytes(16)));
        }

        // Only overwrite the GIF key when a new value is supplied (the field is
        // left blank in the UI once a key is stored, so blank means "keep").
        if (array_key_exists('gif_key', $data) && trim((string) $data['gif_key']) !== '') {
            Settings::set('composer.gif_key', trim((string) $data['gif_key']));
        }
        Settings::set('composer.gif_provider', $data['gif_provider']);

        Settings::setMany([
            'site.name' => $data['name'],
            'site.tagline' => $data['tagline'] ?? '',
            'site.logo' => $data['logo'] ?? '',
            'site.logo_dark' => $data['logo_dark'] ?? '',
            'site.favicon' => $data['favicon'] ?? '',
            'forum.default_view' => $data['default_view'],
            'realtime.enabled' => (bool) $data['realtime'],
            'digests.enabled' => (bool) $data['digests'],
            'pwa.banner' => (bool) $data['pwa_banner'],
            'pwa.short_name' => $data['pwa_short_name'],
            'fa.kit_url' => $data['fa_kit_url'] ?? '',
            'seo.description' => $data['seo_description'] ?? '',
            'seo.image' => $data['seo_image'] ?? '',
            'trust.enabled' => (bool) $data['trust_enabled'],
            'trust.gate_new_users' => (bool) $data['trust_gate_new_users'],
            'mail.reply_enabled' => (bool) $data['reply_enabled'],
            'mail.reply_domain' => strtolower(trim((string) ($data['reply_domain'] ?? ''))),
        ]);

        return back();
    }

    /** Save the mobile bottom tab bar config (enabled + ordered tab keys). */
    public function updateMobileNav(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'tabs' => ['array'],
            'tabs.*' => ['string', \Illuminate\Validation\Rule::in(\App\Support\MobileNav::CATALOG)],
        ]);

        Settings::setMany([
            'mobile.nav_enabled' => (bool) $data['enabled'],
            'mobile.tabs' => json_encode(array_values(array_unique($data['tabs'] ?? []))),
        ]);

        return back();
    }

    /** Single sign-on (generic OpenID Connect) settings. */
    public function sso(): Response
    {
        return Inertia::render('Admin/Sso', [
            'values' => [
                'enabled' => (bool) Settings::get('sso.enabled', false),
                'label' => Settings::get('sso.label', ''),
                'issuer' => Settings::get('sso.issuer', ''),
                'client_id' => Settings::get('sso.client_id', ''),
                'has_secret' => (bool) Settings::get('sso.client_secret'),
                'scopes' => Settings::get('sso.scopes', 'openid profile email'),
                'auto_create' => (bool) Settings::get('sso.auto_create', true),
                'allowed_domain' => Settings::get('sso.allowed_domain', ''),
            ],
            'callbackUrl' => route('oidc.callback'),
        ]);
    }

    public function updateSso(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'label' => ['nullable', 'string', 'max:40'],
            'issuer' => ['nullable', 'string', 'max:300', 'url'],
            'client_id' => ['nullable', 'string', 'max:300'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'scopes' => ['nullable', 'string', 'max:200'],
            'auto_create' => ['required', 'boolean'],
            'allowed_domain' => ['nullable', 'string', 'max:120'],
        ]);

        $set = [
            'sso.enabled' => (bool) $data['enabled'],
            'sso.label' => $data['label'] ?? '',
            'sso.issuer' => rtrim((string) ($data['issuer'] ?? ''), '/'),
            'sso.client_id' => $data['client_id'] ?? '',
            'sso.scopes' => $data['scopes'] ?? '',
            'sso.auto_create' => (bool) $data['auto_create'],
            'sso.allowed_domain' => ltrim(strtolower((string) ($data['allowed_domain'] ?? '')), '@'),
        ];
        // Only overwrite the secret when a new one is provided (it's never sent back).
        if (! empty($data['client_secret'])) {
            $set['sso.client_secret'] = $data['client_secret'];
        }
        Settings::setMany($set);
        \Illuminate\Support\Facades\Cache::forget('sso.discovery:'.md5((string) $set['sso.issuer']));

        return back()->with('status', __('Single sign-on settings saved.'));
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
                __('This is a test email from your Convoro community. If you received it, outgoing mail is working.'),
                function ($m) use ($to) {
                    $m->to($to)->subject(__('Convoro test email'));
                }
            );

            return back()->with('mailTest', ['ok' => true, 'message' => __('Test email sent to :email.', ['email' => $to])]);
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
            'muted' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
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
            'theme.muted' => $data['muted'] ?? '',
            'theme.custom_css' => $data['custom_css'] ?? '',
            'site.logo' => $data['logo'] ?? Settings::get('site.logo', ''),
            'site.favicon' => $data['favicon'] ?? Settings::get('site.favicon', ''),
            'theme.customized' => true, // the admin saved the live theme editor
        ]);

        return back();
    }

    /** Persist the forum sidebar widget layout from the live editor (drag & drop). */
    public function updateWidgets(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'widgets' => ['present', 'array'],
            'widgets.*.key' => ['required', 'string', 'max:64'],
            'widgets.*.enabled' => ['required', 'boolean'],
            'about_html' => ['nullable', 'string', 'max:8000'],
            'about_title' => ['nullable', 'string', 'max:80'],
        ]);

        $clean = [];
        $seen = [];
        foreach ($data['widgets'] as $w) {
            if (isset($seen[$w['key']])) {
                continue;
            }
            $seen[$w['key']] = true;
            $clean[] = ['key' => $w['key'], 'enabled' => (bool) $w['enabled']];
        }
        Settings::set('widgets.sidebar', json_encode(array_values($clean)));

        // The "About / Custom HTML" widget stores its content alongside the layout.
        if ($request->has('about_html')) {
            Settings::set('widgets.about_html', (string) ($data['about_html'] ?? ''));
        }
        if ($request->has('about_title')) {
            Settings::set('widgets.about_title', (string) ($data['about_title'] ?? ''));
        }

        return back();
    }
}
