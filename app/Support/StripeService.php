<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Http;

/**
 * Thin Stripe Checkout client over the REST API (no SDK — keeps the dependency
 * surface small and shared-host friendly). Keys come from admin Settings first,
 * then .env/config. Until keys are set, the store is browsable but checkout is
 * disabled.
 */
class StripeService
{
    /** Secret used for charges: the Connect-linked account's token if present, else a manual/platform key. */
    public static function secret(): string
    {
        return (string) (Settings::get('stripe.access_token')
            ?: Settings::get('stripe.secret')
            ?: config('convoro.stripe.secret'));
    }

    public static function publishableKey(): string
    {
        return (string) (Settings::get('stripe.connected_publishable')
            ?: Settings::get('stripe.key')
            ?: config('convoro.stripe.key'));
    }

    public static function webhookSecret(): string
    {
        return (string) (Settings::get('stripe.webhook_secret') ?: config('convoro.stripe.webhook_secret'));
    }

    public static function configured(): bool
    {
        return self::secret() !== '';
    }

    // -- "Connect with Stripe" (OAuth) ------------------------------------

    /** The platform secret used for the OAuth token exchange. */
    public static function platformSecret(): string
    {
        return (string) (config('convoro.stripe.secret') ?: Settings::get('stripe.secret'));
    }

    public static function connectClientId(): string
    {
        return (string) config('convoro.stripe.connect_client_id');
    }

    /** Is the "Connect with Stripe" button available (platform configured)? */
    public static function canConnect(): bool
    {
        return self::connectClientId() !== '' && self::platformSecret() !== '';
    }

    public static function connectedAccount(): ?string
    {
        return Settings::get('stripe.account_id') ?: null;
    }

    /** Build the Stripe OAuth authorize URL. */
    public static function connectUrl(string $redirectUri, string $state): string
    {
        return 'https://connect.stripe.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => self::connectClientId(),
            'scope' => 'read_write',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * Exchange the OAuth code for the connected account's credentials and store them.
     *
     * @throws \RuntimeException
     */
    public static function completeConnect(string $code): void
    {
        $res = Http::asForm()->post('https://connect.stripe.com/oauth/token', [
            'client_secret' => self::platformSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if (! $res->successful() || ! $res->json('access_token')) {
            throw new \RuntimeException('Stripe connection failed: '.($res->json('error_description') ?? $res->status()));
        }

        Settings::setMany([
            'stripe.account_id' => $res->json('stripe_user_id'),
            'stripe.access_token' => $res->json('access_token'),
            'stripe.connected_publishable' => $res->json('stripe_publishable_key'),
        ]);
    }

    public static function disconnect(): void
    {
        Settings::setMany([
            'stripe.account_id' => '',
            'stripe.access_token' => '',
            'stripe.connected_publishable' => '',
        ]);
    }

    /**
     * Create a one-time-payment Checkout Session for a product.
     *
     * @return array{id:string,url:string}
     *
     * @throws \RuntimeException
     */
    public static function createCheckout(Product $product, string $successUrl, string $cancelUrl, ?string $email = null): array
    {
        if (! self::configured()) {
            throw new \RuntimeException('Payments are not configured yet.');
        }

        $recurring = (bool) ($product->recurring ?? false);

        $params = [
            'mode' => $recurring ? 'subscription' : 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => $product->currency,
            'line_items[0][price_data][unit_amount]' => $product->price_cents,
            'line_items[0][price_data][product_data][name]' => $product->name,
            'metadata[product_id]' => $product->id,
        ];

        if ($recurring) {
            // Monthly subscription. Metadata also rides on the subscription so the
            // later invoice/subscription webhooks can map back to the product.
            $params['line_items[0][price_data][recurring][interval]'] = 'month';
            $params['subscription_data[metadata][product_id]'] = $product->id;
        } else {
            // One-time purchase: tag the payment intent for the webhook.
            $params['payment_intent_data[metadata][product_id]'] = $product->id;
        }
        if ($email) {
            $params['customer_email'] = $email;
        }

        $res = Http::asForm()
            ->withToken(self::secret())
            ->post('https://api.stripe.com/v1/checkout/sessions', $params);

        if (! $res->successful()) {
            throw new \RuntimeException('Stripe error: '.($res->json('error.message') ?? $res->status()));
        }

        return ['id' => $res->json('id'), 'url' => $res->json('url')];
    }

    /** Verify a webhook signature (Stripe-Signature header) without the SDK. */
    public static function verifyWebhook(string $payload, ?string $sigHeader): bool
    {
        $secret = self::webhookSecret();
        if ($secret === '' || ! $sigHeader) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $sigHeader) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$k][] = $v;
        }
        $t = $parts['t'][0] ?? null;
        $sigs = $parts['v1'] ?? [];
        if (! $t || ! $sigs) {
            return false;
        }

        $expected = hash_hmac('sha256', $t.'.'.$payload, $secret);
        foreach ($sigs as $sig) {
            if (hash_equals($expected, $sig)) {
                // Reject events older than 5 minutes (replay protection).
                return abs(time() - (int) $t) < 300;
            }
        }

        return false;
    }
}
