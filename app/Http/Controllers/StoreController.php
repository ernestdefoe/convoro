<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\Product;
use App\Support\GitHubOAuth;
use App\Support\GitHubRegistry;
use App\Support\Seo;
use App\Support\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The central premium store on convoro.co. */
class StoreController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Store/Index', [
            'products' => Product::where('published', true)->where('listed', true)->orderByDesc('featured')->orderBy('name')->get()
                ->map(fn (Product $p) => MarketingController::card($p) + ['description' => $p->tagline]),
            'seo' => Seo::make(['title' => __('Store — premium extensions & themes'), 'description' => __('Premium Convoro extensions and themes. Buy once, get a license key and download.')]),
        ]);
    }

    /** Public "submit your extension/theme" form on convoro.co. */
    public function submitForm(Request $request): Response
    {
        return Inertia::render('Store/Submit', [
            'checkoutEnabled' => StripeService::configured(),
            'github' => $this->githubState($request),
            'seo' => Seo::make([
                'title' => __('Submit your extension or theme'),
                'description' => __('Publish your Convoro extension or theme to the directory. Link a public GitHub repo — free listings are instant; premium items sell through the Convoro store.'),
            ]),
        ]);
    }

    /** GitHub connection state for the seller UI. */
    private function githubState(Request $request): array
    {
        $user = $request->user();

        return [
            'configured' => GitHubOAuth::configured(),
            'connected' => (bool) $user?->hasGithub(),
            'login' => $user?->github_login,
        ];
    }

    /** Begin the seller GitHub OAuth flow. */
    public function connectGithub(Request $request): RedirectResponse
    {
        if (! GitHubOAuth::configured()) {
            return back()->with('storeError', __('GitHub Connect isn\'t set up on this store yet.'));
        }
        $request->session()->put('github_oauth_state', $state = \Illuminate\Support\Str::random(40));

        return redirect()->away(GitHubOAuth::authorizeUrl(GitHubOAuth::redirectUri(), $state));
    }

    /** GitHub OAuth callback: store the seller's token + login. */
    public function githubCallback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('github_oauth_state');
        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('store.submit')->with('storeError', __('GitHub connection failed (invalid state). Please try again.'));
        }
        if (! ($code = (string) $request->query('code'))) {
            return redirect()->route('store.submit')->with('storeError', __('GitHub connection was cancelled.'));
        }

        try {
            $creds = GitHubOAuth::exchangeCode($code, GitHubOAuth::redirectUri());
        } catch (\Throwable $e) {
            return redirect()->route('store.submit')->with('storeError', $e->getMessage());
        }

        $request->user()->forceFill([
            'github_token' => $creds['token'],
            'github_login' => $creds['login'],
        ])->save();

        return redirect()->route('store.submit')->with('status',
            __('GitHub connected as :login — you can now list private repositories.', ['login' => $creds['login']]));
    }

    /** Disconnect the seller's GitHub account. */
    public function disconnectGithub(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['github_token' => null, 'github_login' => null])->save();

        return back()->with('status', __('GitHub disconnected.'));
    }

    /**
     * Accept a public submission: validate the GitHub repo's manifest, then
     * create an UNPUBLISHED, pending product (the store owner approves it).
     */
    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'repo' => ['required', 'string', 'max:255'],
            'pricing' => ['required', 'in:free,premium'],
            'price' => ['nullable', 'numeric', 'min:1', 'max:9999'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $repo = GitHubRegistry::normalizeRepo($data['repo']);
        if (! $repo) {
            return back()->with('storeError', __('That does not look like a GitHub repository. Use the owner/name or full URL.'));
        }

        // Use the seller's connected token so they can submit their PRIVATE repos.
        $token = $request->user()?->github_token;
        try {
            $r = GitHubRegistry::resolve($repo, null, $token);
        } catch (\Throwable $e) {
            return back()->with('storeError', $e->getMessage());
        }

        $m = $r['manifest'];
        $priceCents = $data['pricing'] === 'premium' ? (int) round(((float) ($data['price'] ?? 0)) * 100) : 0;
        if ($data['pricing'] === 'premium' && $priceCents < 100) {
            return back()->with('storeError', __('Set a price of at least $1 for a premium listing.'));
        }

        $existing = Product::where('package', $m['id'])->first();
        if ($existing && $existing->published) {
            return back()->with('storeError', __('“:name” is already listed in the directory.', ['name' => $m['name']]));
        }

        $product = Product::updateOrCreate(
            ['package' => $m['id']],
            [
                'slug' => \Illuminate\Support\Str::slug($m['id']),
                'name' => $m['name'],
                'type' => in_array(($m['type'] ?? 'extension'), ['extension', 'theme'], true) ? ($m['type'] ?? 'extension') : 'extension',
                'tagline' => \Illuminate\Support\Str::limit(strip_tags($m['description'] ?? ''), 180),
                'description' => $m['description'] ?? '',
                'version' => $r['version'],
                'source' => 'github',
                'repo' => $repo,
                'download_url' => $r['download_url'],
                'ref' => $r['ref'],
                'price_cents' => $priceCents,
                'submitter_email' => $data['email'],
                'owner_id' => $request->user()?->id,
                'status' => 'pending',
                'published' => false,
            ],
        );

        // Private repos: buyers can't fetch them, so cache the archive store-side
        // (served via the gated download) using the seller's token.
        if (! empty($r['private']) && GitHubRegistry::cacheArchive($product, $r['download_url'], $token)) {
            $product->save();
        }

        return redirect()->route('store.index')->with('status',
            __('Thanks! “:name” was submitted for review. We\'ll email :email once it\'s approved.', ['name' => $m['name'], 'email' => $data['email']]));
    }

    /** Author dashboard: the listings this member owns (price etc. editable here). */
    public function manage(Request $request): Response
    {
        $user = $request->user();
        // The convoro.co operator (admin on the store-owner deployment) gets the
        // full store console here; everyone else manages only their own listings.
        $isOwner = (bool) config('convoro.store_owner') && (bool) $user->is_admin;

        if ($isOwner) {
            $products = Product::orderByDesc('featured')->orderBy('name')->withCount('licenses')->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id, 'slug' => $p->slug, 'name' => $p->name, 'type' => $p->type,
                    'tagline' => $p->tagline, 'description' => $p->description, 'package' => $p->package,
                    'version' => $p->version, 'price_cents' => $p->price_cents, 'image' => $p->image,
                    'published' => (bool) $p->published, 'featured' => (bool) $p->featured,
                    'status' => $p->status, 'submitter' => $p->submitter_email,
                    'source' => $p->source, 'repo' => $p->repo,
                    'pinnedVersion' => $p->pinned_version,
                    'synced' => $p->last_synced_at?->diffForHumans(),
                    'review' => $p->reviewPayload(),
                    'hasDownload' => $p->source === 'github' ? (bool) $p->download_url : (bool) $p->download_path,
                    'sales' => $p->licenses_count,
                ]);
        } else {
            $products = Product::query()
                ->where('owner_id', $user->id)
                ->orWhere(fn ($q) => $q->whereNull('owner_id')->where('submitter_email', $user->email))
                ->orderBy('name')->get()
                ->map(fn (Product $p) => [
                    'slug' => $p->slug, 'name' => $p->name, 'type' => $p->type,
                    'tagline' => $p->tagline, 'description' => $p->description,
                    'free' => $p->isFree(), 'price' => round($p->price_cents / 100, 2),
                    'published' => (bool) $p->published, 'status' => $p->status,
                ]);
        }

        return Inertia::render('Store/Manage', [
            'products' => $products,
            'isOwner' => $isOwner,
            'registryWebhookUrl' => $isOwner ? url('/api/registry/github') : null,
            'reviewAi' => $isOwner ? [
                'enabled' => (bool) \App\Support\Settings::get('review.enabled', true),
                'configured' => \App\Support\Llm::configured(),
                'provider' => \App\Support\Settings::get('ai.provider', 'anthropic'),
                'model' => \App\Support\Settings::get('ai.model', ''),
                'base_url' => \App\Support\Settings::get('ai.base_url', ''),
            ] : null,
            'checkoutEnabled' => StripeService::configured(),
            'stripe' => $isOwner ? [
                'key' => \App\Support\Settings::get('stripe.key'),
                'secretSet' => trim((string) \App\Support\Settings::get('stripe.secret')) !== '',
                'webhookSet' => trim((string) \App\Support\Settings::get('stripe.webhook_secret')) !== '',
                'webhookUrl' => url('/store/webhook'),
                'canConnect' => StripeService::canConnect(),
                'connectedAccount' => StripeService::connectedAccount(),
            ] : null,
            'seo' => Seo::make(['title' => $isOwner ? __('Store console') : __('Manage your extensions')]),
        ]);
    }

    /** Author updates their own listing (pricing, copy). Owner or admin only. */
    public function updateListing(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->is_admin || (int) $product->owner_id === (int) $user->id
                || ($product->owner_id === null && $product->submitter_email && $product->submitter_email === $user->email),
            403,
        );

        $data = $request->validate([
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'pricing' => ['required', 'in:free,premium'],
            'price' => ['nullable', 'numeric', 'min:1', 'max:9999'],
        ]);

        $cents = $data['pricing'] === 'premium' ? (int) round(((float) ($data['price'] ?? 0)) * 100) : 0;
        if ($data['pricing'] === 'premium' && $cents < 100) {
            return back()->with('storeError', __('Set a price of at least $1 for a premium listing.'));
        }

        // Claim ownership on first edit if it was matched by email.
        if ($product->owner_id === null) {
            $product->owner_id = $user->id;
        }
        $product->tagline = $data['tagline'] ?? $product->tagline;
        $product->description = $data['description'] ?? $product->description;
        $product->price_cents = $cents;
        $product->save();

        return back()->with('status', __('Updated “:name”.', ['name' => $product->name]));
    }

    public function show(Product $product): Response
    {
        abort_unless($product->published, 404);

        return Inertia::render('Store/Show', [
            'product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'type' => $product->type,
                'tagline' => $product->tagline,
                'description' => $product->description,
                'image' => $product->image,
                'version' => $product->version,
                'price' => $product->priceLabel(),
                'free' => $product->isFree(),
                'source' => $product->source,
                'repo' => $product->repo,
                'review' => $product->reviewPayload(),
                // The extension's own icon (inline SVG) for the header tile, so it
                // shows a real glyph next to the name instead of a monogram. Comes
                // from the repo (captured on sync) or the installed manifest; null
                // → monogram fallback.
                'icon' => $product->resolvedIcon(),
            ],
            'checkoutEnabled' => StripeService::configured(),
            'seo' => Seo::make(['title' => $product->name, 'description' => $product->tagline, 'image' => $product->image, 'type' => 'product']),
        ]);
    }

    public function checkout(Request $request, Product $product): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($product->published, 404);
        abort_if($product->isFree(), 422);

        $email = $request->user()?->email;

        try {
            $session = StripeService::createCheckout(
                $product,
                route('store.success').'?session_id={CHECKOUT_SESSION_ID}',
                route('store.show', $product),
                $email,
            );

            // The buy button posts via Inertia (XHR). A plain redirect to Stripe
            // would be followed by axios and fail CORS — Inertia::location sends a
            // 409 + X-Inertia-Location so the client does a full-page visit.
            return Inertia::location($session['url']);
        } catch (\Throwable $e) {
            return back()->with('storeError', $e->getMessage());
        }
    }

    public function success(Request $request): Response
    {
        $license = $request->query('session_id')
            ? License::with('product')->where('stripe_session_id', $request->query('session_id'))->first()
            : null;

        return Inertia::render('Store/Success', [
            'license' => $license ? [
                'key' => $license->key,
                'product' => $license->product->name,
            ] : null,
            'seo' => Seo::make(['title' => __('Thank you'), 'noindex' => true]),
        ]);
    }

    /** Public catalog consumed by remote installs' Marketplace. */
    public function catalog(): JsonResponse
    {
        $items = Product::where('published', true)->orderByDesc('featured')->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'type' => $p->type,
                'tagline' => $p->tagline,
                'description' => $p->description,
                'version' => $p->version,
                'package' => $p->package,
                'price' => $p->priceLabel(),
                'free' => $p->isFree(),
                'image' => $p->image,
                // Inline icon SVG so a remote install's Marketplace shows the same
                // icon as the directory (it can't resolve a manifest it lacks).
                'icon' => $p->resolvedIcon(),
                'review' => ['rating' => $p->review_rating, 'score' => $p->review_score],
                'source' => $p->source,
                'repo' => $p->repo,
                // Public GitHub repos install straight from the repo archive. A
                // PRIVATE repo is cached store-side (download_path): free → gated
                // catalog download; premium → null (buyer installs via license).
                // Non-GitHub: free hosted uses the catalog download; premium → license.
                'download_url' => $p->source === 'github'
                    ? ($p->download_path
                        ? ($p->isFree() ? route('catalog.download', $p) : null)
                        : $p->download_url)
                    : (($p->isFree() && $p->download_path) ? route('catalog.download', $p) : null),
            ]);

        return response()->json(['items' => $items]);
    }

    /** Stream a FREE product's package (no license needed). */
    public function freeDownload(Product $product): BinaryFileResponse
    {
        abort_unless($product->published && $product->isFree() && $product->download_path, 404);
        $abs = storage_path('app/'.ltrim($product->download_path, '/'));
        abort_unless(is_file($abs), 404);

        return response()->download($abs, $product->slug.'.zip');
    }

    /** The buyer's licenses + download links (auth). */
    public function account(Request $request): Response
    {
        $user = $request->user();
        $licenses = License::with('product')
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('email', $user->email))
            ->latest()->get()
            ->map(fn (License $l) => [
                'key' => $l->key,
                'product' => $l->product->name,
                'slug' => $l->product->slug,
                'status' => $l->status,
                'purchased' => optional($l->created_at)->format('M j, Y'),
                'download' => $l->product->download_path ? route('licenses.download', ['key' => $l->key]) : null,
            ]);

        return Inertia::render('Store/Account', [
            'licenses' => $licenses,
            'seo' => Seo::make(['title' => __('My licenses'), 'noindex' => true]),
        ]);
    }

    /** Stripe webhook — fulfill the purchase by issuing a license. */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        if (! StripeService::verifyWebhook($payload, $request->header('Stripe-Signature'))) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? null;
        $obj = $event['data']['object'] ?? [];

        if ($type === 'checkout.session.completed') {
            $product = ($pid = $obj['metadata']['product_id'] ?? null) ? Product::find($pid) : null;
            if ($product) {
                License::issue($product, [
                    'email' => $obj['customer_details']['email'] ?? ($obj['customer_email'] ?? null),
                    'stripe_session_id' => $obj['id'] ?? null,
                    'stripe_payment_intent' => $obj['payment_intent'] ?? null,
                    // Subscriptions: remember the sub id so later invoice/cancel
                    // events can keep this license in sync.
                    'stripe_subscription_id' => $obj['subscription'] ?? null,
                ]);
            }
        } elseif (in_array($type, ['invoice.paid', 'invoice.payment_succeeded'], true)) {
            // Renewal succeeded → (re)activate the subscription's license.
            if ($sub = $obj['subscription'] ?? null) {
                $end = $obj['lines']['data'][0]['period']['end'] ?? ($obj['period_end'] ?? null);
                License::where('stripe_subscription_id', $sub)->update(array_filter([
                    'status' => 'active',
                    'current_period_end' => $end ? now()->setTimestamp($end) : null,
                ]));
            }
        } elseif ($type === 'invoice.payment_failed') {
            if ($sub = $obj['subscription'] ?? null) {
                License::where('stripe_subscription_id', $sub)->update(['status' => 'past_due']);
            }
        } elseif ($type === 'customer.subscription.deleted') {
            // The subscription object's own id is the subscription id.
            if ($sub = $obj['id'] ?? null) {
                License::where('stripe_subscription_id', $sub)->update(['status' => 'canceled']);
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * GitHub registry webhook — instant Packagist-style refresh. Point a repo's
     * webhook (or org/GitHub-App) at this URL for the `release` (and `push`)
     * events; a newly published release re-syncs the matching product at once.
     */
    public function githubWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        // Optional shared secret (Settings 'github.webhook_secret') → HMAC verify.
        $secret = (string) \App\Support\Settings::get('github.webhook_secret', '');
        if ($secret !== '') {
            $sig = (string) $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);
            if (! hash_equals($expected, $sig)) {
                return response()->json(['error' => 'invalid signature'], 400);
            }
        }

        $event = (string) $request->header('X-GitHub-Event', '');
        if ($event === 'ping') {
            return response()->json(['ok' => true, 'pong' => true]);
        }
        // Only act on events that mean "new code is available".
        if (! in_array($event, ['release', 'push', 'create'], true)) {
            return response()->json(['ok' => true, 'ignored' => $event]);
        }

        $data = json_decode($payload, true) ?: [];
        $repo = $data['repository']['full_name'] ?? null;
        if (! $repo) {
            return response()->json(['error' => 'no repository'], 422);
        }

        // Match by repo; pinned products stay frozen (manual bump only).
        $product = Product::where('source', 'github')
            ->whereRaw('LOWER(repo) = ?', [strtolower($repo)])
            ->whereNull('pinned_version')
            ->first();
        if (! $product) {
            return response()->json(['ok' => true, 'matched' => false]);
        }

        try {
            $changed = \App\Support\GitHubRegistry::syncProduct($product);

            return response()->json(['ok' => true, 'matched' => true, 'updated' => $changed, 'version' => $product->version]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }
    }
}
