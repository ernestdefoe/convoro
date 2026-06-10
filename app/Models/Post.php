<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model {
    protected $guarded = [];
    protected $casts = ['is_first' => 'boolean', 'edited_at' => 'datetime'];
    public function topic(): BelongsTo { return $this->belongsTo(Topic::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reactions(): HasMany { return $this->hasMany(Reaction::class); }
}
