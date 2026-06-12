<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invite extends Model
{
    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Still redeemable: not expired and uses remaining. */
    public function isUsable(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $this->uses < $this->max_uses;
    }

    /** Find a usable invite by code (case-insensitive), or null. */
    public static function usable(?string $code): ?self
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }
        $invite = static::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();

        return $invite && $invite->isUsable() ? $invite : null;
    }
}
