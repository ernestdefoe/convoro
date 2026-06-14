<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\HostingMetrics;
use Illuminate\Console\Command;

/** Capture a resource-usage sample for every active hosted tenant. */
class SampleHostingMetrics extends Command
{
    protected $signature = 'convoro:sample-metrics {--prune=90 : delete samples older than N days}';

    protected $description = 'Capture per-tenant resource usage for the hosting dashboard graphs.';

    public function handle(): int
    {
        $tenants = Tenant::where('kind', 'tenant')->where('status', 'active')->get();
        foreach ($tenants as $tenant) {
            try {
                HostingMetrics::capture($tenant);
            } catch (\Throwable $e) {
                $this->error("metrics {$tenant->slug}: ".$e->getMessage());
            }
        }

        $days = (int) $this->option('prune');
        if ($days > 0) {
            \App\Models\TenantMetric::where('captured_at', '<', now()->subDays($days))->delete();
        }

        $this->info("Sampled {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }
}
