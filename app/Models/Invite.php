<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invite extends Model
{
    protected $fillable = ['inviter_id', 'code', 'email', 'max_uses', 'uses', 'expires_at', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /** The member who created this invite. */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /** A random, URL-safe, unguessable code. */
    public static function generateCode(): string
    {
        return Str::lower(Str::random(24));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->uses >= $this->max_uses;
    }

    /** Whether the invite can still be redeemed at all. */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isExhausted();
    }

    /** A bound email invite is only redeemable by that address. */
    public function acceptsEmail(string $email): bool
    {
        return $this->email === null || Str::lower($this->email) === Str::lower($email);
    }

    /** Record one redemption. */
    public function redeem(): void
    {
        $this->forceFill([
            'uses' => $this->uses + 1,
            'last_used_at' => now(),
        ])->save();
    }

    /** Is this a reusable shareable link (vs. a one-shot email invite)? */
    public function isLink(): bool
    {
        return $this->email === null;
    }
}
