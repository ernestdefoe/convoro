<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Draft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
        'poll' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_at !== null;
    }
}
