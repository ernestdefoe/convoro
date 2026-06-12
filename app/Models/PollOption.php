<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollOption extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }
}
