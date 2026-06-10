<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class License extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'activations' => 'integer',
        'last_validated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Generate a readable, unique CONV-XXXX-XXXX-XXXX-XXXX key. */
    public static function generateKey(): string
    {
        do {
            $blocks = collect(range(1, 4))->map(fn () => strtoupper(Str::random(4)))->implode('-');
            $key = 'CONV-'.$blocks;
        } while (self::where('key', $key)->exists());

        return $key;
    }

    public function isValid(): bool
    {
        return $this->status === 'active';
    }

    /** Issue (or return the existing) license for a purchase. Idempotent per Stripe session. */
    public static function issue(Product $product, array $attrs = []): self
    {
        if (! empty($attrs['stripe_session_id'])) {
            $existing = self::where('stripe_session_id', $attrs['stripe_session_id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        // Match a user by email when not explicitly provided.
        $userId = $attrs['user_id'] ?? (! empty($attrs['email'])
            ? optional(User::where('email', $attrs['email'])->first())->id
            : null);

        return self::create(array_merge([
            'key' => self::generateKey(),
            'product_id' => $product->id,
            'user_id' => $userId,
            'status' => 'active',
        ], $attrs));
    }
}
