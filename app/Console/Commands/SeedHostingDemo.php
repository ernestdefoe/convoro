<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Support\HostingMetrics;
use App\Support\TenantProvisioner;
use Illuminate\Console\Command;

/** Seed a couple of sample hosted communities (billing + 30d metrics) to demo the panel. */
class SeedHostingDemo extends Command
{
    protected $signature = 'convoro:seed-hosting-demo';

    protected $description = 'Provision sample hosted tenants with billing + metrics history for the dashboard.';

    public function handle(): int
    {
        $owner = User::where('is_admin', true)->orderBy('id')->first();
        if (! $owner) {
            $this->error('No admin user to own the demo tenants.');

            return self::FAILURE;
        }
        $this->info("Owner: {$owner->email}");

        // Members + bandwidth are unlimited (0) on every plan; only storage caps.
        $plans = [
            ['name' => 'Acme Community', 'plan' => 'pro', 'price' => 4900, 'brand' => 'Visa', 'last4' => '4242', 'storeGb' => 50],
            ['name' => 'Indie Makers', 'plan' => 'starter', 'price' => 1900, 'brand' => 'Mastercard', 'last4' => '5454', 'storeGb' => 10],
        ];

        foreach ($plans as $p) {
            $tenant = Tenant::where('owner_user_id', $owner->id)->where('name', $p['name'])->first();
            if (! $tenant) {
                $this->line("Provisioning “{$p['name']}”…");
                $tenant = TenantProvisioner::provision($owner, $p['name']);
            }
            if ($tenant->status !== 'active') {
                $this->error("  failed: {$tenant->error}");

                continue;
            }

            $tenant->update([
                'kind' => 'tenant',
                'plan' => $p['plan'],
                'price_cents' => $p['price'],
                'billing_interval' => 'month',
                'billing_status' => 'active',
                'current_period_start' => now()->subDays(random_int(3, 20)),
                'current_period_end' => now()->addDays(random_int(8, 27)),
                'card_brand' => $p['brand'],
                'card_last4' => $p['last4'],
                'quota_storage_bytes' => $p['storeGb'] * 1024 * 1024 * 1024,
                'quota_members' => 0,        // unlimited
                'quota_bandwidth_gb' => 0,   // unlimited
            ]);

            $tenant->metrics()->delete();
            $n = HostingMetrics::seedHistory($tenant, 30);
            $this->info("  ✓ {$tenant->name} — active, {$n} metric points");
        }

        return self::SUCCESS;
    }
}
