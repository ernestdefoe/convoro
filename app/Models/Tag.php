<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    protected $guarded = [];

    protected $casts = ['hero' => 'array'];

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'topic_tag');
    }

    /** A sub-tag points at its parent tag. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'parent_id');
    }

    /** Top-level tags have child sub-tags. */
    public function children(): HasMany
    {
        return $this->hasMany(Tag::class, 'parent_id')->orderBy('position');
    }

    /**
     * The Prism hero config for this space — tag fields with optional overrides
     * from the `hero` JSON. `c2` is left null so the component derives it.
     */
    public function heroConfig(): array
    {
        $h = $this->hero ?: [];
        $icon = $h['icon'] ?? $this->icon;

        return [
            'title' => $this->name,
            'subtitle' => $h['subtitle'] ?? ($this->description ?: null),
            'icon' => (is_string($icon) && str_starts_with($icon, 'fa-')) ? $icon : 'fa-solid fa-hashtag',
            'c1' => $h['c1'] ?? ($this->color ?: '#7c3aed'),
            'c2' => $h['c2'] ?? null,
            'image' => $h['image'] ?? null,
        ];
    }
}
