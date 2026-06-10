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
    public static function secret(): string
    {
        return (string) (Settings::get('stripe.secret') ?: config('convoro.stripe.secret'));
    }

    public static function publishableKey(): string
    {
        return (string) (Settings::get('stripe.key') ?: config('convoro.stripe.key'));
    }

    public static function webhookSecret(): string
    {
        return (string) (Settings::get('stripe.webhook_secret') ?: config('convoro.stripe.webhook_secret'));
    }

    public static function configured(): bool
    {
        return self::secret() !== '';
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

        $params = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => $product->currency,
            'line_items[0][price_data][unit_amount]' => $product->price_cents,
            'line_items[0][price_data][product_data][name]' => $product->name,
            'metadata[product_id]' => $product->id,
            'payment_intent_data[metadata][product_id]' => $product->id,
        ];
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
