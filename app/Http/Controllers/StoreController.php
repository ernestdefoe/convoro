<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\Product;
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
            'products' => Product::where('published', true)->orderByDesc('featured')->orderBy('name')->get()
                ->map(fn (Product $p) => MarketingController::card($p) + ['description' => $p->tagline]),
            'seo' => Seo::make(['title' => 'Store — premium extensions & themes', 'description' => 'Premium Convoro extensions and themes. Buy once, get a license key and download.']),
        ]);
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
            ],
            'checkoutEnabled' => StripeService::configured(),
            'seo' => Seo::make(['title' => $product->name, 'description' => $product->tagline, 'image' => $product->image, 'type' => 'product']),
        ]);
    }

    public function checkout(Request $request, Product $product): RedirectResponse
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

            return redirect()->away($session['url']);
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
            'seo' => Seo::make(['title' => 'Thank you', 'noindex' => true]),
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
                'version' => $p->version,
                'package' => $p->package,
                'price' => $p->priceLabel(),
                'free' => $p->isFree(),
                // Only free items expose a direct download; premium needs a license key.
                'download_url' => ($p->isFree() && $p->download_path) ? route('catalog.download', $p) : null,
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
            'seo' => Seo::make(['title' => 'My licenses', 'noindex' => true]),
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
        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $productId = $session['metadata']['product_id'] ?? null;
            $product = $productId ? Product::find($productId) : null;

            if ($product) {
                License::issue($product, [
                    'email' => $session['customer_details']['email'] ?? ($session['customer_email'] ?? null),
                    'stripe_session_id' => $session['id'] ?? null,
                    'stripe_payment_intent' => $session['payment_intent'] ?? null,
                ]);
            }
        }

        return response()->json(['received' => true]);
    }
}
