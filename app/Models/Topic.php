<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Topic extends Model {
    public function getRouteKeyName(): string { return "slug"; }
    protected $guarded = [];
    protected $casts = [
        'last_post_at' => 'datetime',
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'is_live' => 'boolean',
        'hidden' => 'boolean',
    ];
    /** Exclude topics held for moderator approval from public listings. */
    public function scopeVisible($q) { return $q->where($this->getTable().'.hidden', false); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function posts(): HasMany { return $this->hasMany(Post::class); }
    public function firstPost(): HasOne { return $this->hasOne(Post::class)->where('is_first', true); }
    public function poll(): HasOne { return $this->hasOne(Poll::class); }
    public function tags(): BelongsToMany { return $this->belongsToMany(Tag::class, 'topic_tag'); }
}
