<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** POC — someone waiting for a demo slot to free up. */
class DemoWaitlist extends Model
{
    protected $table = 'demo_waitlist';

    protected $guarded = [];

    protected $casts = [
        'notified_at' => 'datetime',
    ];
}
