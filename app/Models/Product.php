<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price_cents' => 'integer',
        'published' => 'boolean',
        'featured' => 'boolean',
        'last_synced_at' => 'datetime',
        'review_findings' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /** Public-facing AI security-review summary for the directory + detail page. */
    public function reviewPayload(): array
    {
        return [
            'status' => $this->review_status ?: 'none',
            'rating' => $this->review_rating,
            'score' => $this->review_score,
            'summary' => $this->review_summary,
            'findings' => $this->review_findings ?: [],
            'model' => $this->review_model,
            'reviewedVersion' => $this->reviewed_version,
            'reviewedAt' => $this->reviewed_at?->diffForHumans(),
            'stale' => $this->reviewed_version && $this->reviewed_version !== $this->version,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isFree(): bool
    {
        return $this->price_cents <= 0;
    }

    public function priceLabel(): string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        return '$'.number_format($this->price_cents / 100, ($this->price_cents % 100 === 0) ? 0 : 2);
    }
}
