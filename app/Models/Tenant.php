<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * POC — one provisioned Convoro community (managed hosting). Its forum data
 * lives in an isolated database; this row is the central record that ties the
 * community to the Convoro Account that owns it.
 */
class Tenant extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'reminded_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'custom_domain_verified_at' => 'datetime',
    ];

    public function metrics(): HasMany
    {
        return $this->hasMany(TenantMetric::class);
    }

    /** The most recent metrics sample (current resource snapshot). */
    public function latestMetric(): ?TenantMetric
    {
        return $this->metrics()->latest('captured_at')->first();
    }

    /** Structured billing summary for the panel. */
    public function billingSummary(): array
    {
        return [
            'plan' => ucfirst($this->plan ?? 'starter'),
            'priceCents' => (int) $this->price_cents,
            'price' => '$'.number_format(($this->price_cents ?? 0) / 100, 2),
            'interval' => $this->billing_interval ?? 'month',
            'status' => $this->billing_status ?? 'active',
            'currentPeriodStart' => optional($this->current_period_start)->toDateString(),
            'nextPaymentDate' => optional($this->current_period_end)->toFormattedDateString(),
            'nextPaymentInDays' => $this->current_period_end ? max(0, (int) now()->diffInDays($this->current_period_end, false)) : null,
            'card' => $this->card_last4 ? ['brand' => $this->card_brand, 'last4' => $this->card_last4] : null,
        ];
    }

    /**
     * Demos that occupy a slot against the concurrent cap. Includes demos still
     * provisioning (a job is in flight) so concurrent requests can't oversubscribe.
     */
    public function scopeActiveDemos($q)
    {
        return $q->where('kind', 'demo')->whereIn('status', ['provisioning', 'active']);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** A live read into the tenant's OWN database — proves isolation + the auto-admin grant. */
    public function peek(): array
    {
        try {
            $name = 'tenant_'.$this->id;
            config(["database.connections.{$name}" => array_merge(
                (array) config('database.connections.sqlite'),
                ['database' => $this->database],
            )]);
            DB::purge($name);
            $conn = DB::connection($name);

            return [
                'users' => $conn->table('users')->count(),
                'admins' => $conn->table('users')->where('is_admin', true)->pluck('email')->all(),
                'categories' => $conn->table('categories')->count(),
            ];
        } catch (\Throwable $e) {
            return ['users' => null, 'admins' => [], 'categories' => null, 'error' => $e->getMessage()];
        }
    }
}
