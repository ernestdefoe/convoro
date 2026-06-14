<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One time-series sample of a hosted tenant's resource usage. */
class TenantMetric extends Model
{
    protected $guarded = [];

    protected $casts = [
        'captured_at' => 'datetime',
        'cpu_pct' => 'float',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
