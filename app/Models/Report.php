<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** Resolve the reported model (post or user). */
    public function subject(): ?Model
    {
        return match ($this->reportable_type) {
            'post' => Post::with('user', 'topic')->find($this->reportable_id),
            'user' => User::find($this->reportable_id),
            default => null,
        };
    }
}
