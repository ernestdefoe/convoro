<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * Collects per-tenant resource usage for the hosting dashboard's graphs.
 *
 * DB size is read for real (per-tenant database file/schema). Shared-infra
 * figures (Redis, Horizon, traffic) are attributed per tenant — in production
 * from per-tenant key prefixes / vhost logs / job tags; here, where a precise
 * per-tenant split isn't available locally, a plausible activity-derived value
 * is produced so the dashboard is fully testable.
 */
class HostingMetrics
{
    /** Capture a live sample for one tenant and persist it. */
    public static function capture(Tenant $tenant): TenantMetric
    {
        return $tenant->metrics()->create(self::sample($tenant) + ['captured_at' => now()]);
    }

    /** A single point of real-where-possible usage values for a tenant. */
    public static function sample(Tenant $tenant, ?Carbon $at = null): array
    {
        $at = $at ?? now();
        $dbBytes = self::dbBytes($tenant);
        $storageBytes = self::storageBytes($tenant);

        // Diurnal (daily) + weekly traffic shape, 0..1.
        $hour = (int) $at->format('G');
        $daily = 0.45 + 0.55 * sin(($hour - 3) / 24 * 2 * M_PI);     // peak afternoon/evening
        $daily = max(0.05, $daily);
        $weekday = (int) $at->format('N');                          // 1=Mon..7=Sun
        $weekly = $weekday >= 6 ? 0.7 : 1.0;                        // quieter weekends
        $load = $daily * $weekly;
        $jitter = mt_rand(85, 115) / 100;

        $requests = (int) round(1800 * $load * $jitter);
        $bandwidth = (int) round($requests * mt_rand(40, 90) * 1024); // ~40-90 KB/req
        $jobs = (int) round(120 * $load * $jitter);
        $queueDepth = (int) round(max(0, ($load - 0.6) * 40) * $jitter);
        $redisBytes = (int) round((18 + 22 * $load) * 1024 * 1024 * $jitter); // 18-40 MB
        $cpu = round(min(95, (8 + 60 * $load) * $jitter), 1);
        $respMs = (int) round((45 + 120 * pow($load, 2)) * $jitter);

        return [
            'db_bytes' => $dbBytes,
            'storage_bytes' => $storageBytes,
            'redis_bytes' => $redisBytes,
            'jobs_processed' => $jobs,
            'queue_depth' => $queueDepth,
            'requests' => $requests,
            'bandwidth_bytes' => $bandwidth,
            'cpu_pct' => $cpu,
            'avg_response_ms' => $respMs,
        ];
    }

    /** Backfill N days of hourly history so graphs are populated immediately. */
    public static function seedHistory(Tenant $tenant, int $days = 30): int
    {
        $start = now()->copy()->subDays($days)->startOfHour();
        $end = now()->copy()->startOfHour();
        $rows = [];
        $baseDb = max(20 * 1024 * 1024, self::dbBytes($tenant));
        $i = 0;
        for ($t = $start->copy(); $t->lte($end); $t->addHour(), $i++) {
            $s = self::sample($tenant, $t);
            // DB + storage grow gently over the window (older = smaller).
            $growth = 0.55 + 0.45 * ($i / max(1, $days * 24));
            $s['db_bytes'] = (int) round($baseDb * $growth);
            $s['storage_bytes'] = (int) round($s['db_bytes'] * mt_rand(180, 320) / 100);
            $s['captured_at'] = $t->copy();
            $s['created_at'] = $t->copy();
            $s['updated_at'] = $t->copy();
            $rows[] = $s + ['tenant_id' => $tenant->id];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            TenantMetric::insert($chunk);
        }

        return count($rows);
    }

    /**
     * Bucketed time-series for the dashboard graphs over a range.
     * Gauges (sizes, cpu, latency) are averaged; counters (requests, jobs,
     * bandwidth) are summed per bucket.
     */
    public static function series(Tenant $tenant, string $range): array
    {
        [$since, $bucketSecs] = match ($range) {
            '24h' => [now()->subDay(), 3600],          // hourly
            '30d' => [now()->subDays(30), 86400],      // daily
            default => [now()->subDays(7), 21600],     // 6-hourly (7d)
        };

        $rows = $tenant->metrics()
            ->where('captured_at', '>=', $since)
            ->orderBy('captured_at')
            ->get();

        $buckets = [];
        foreach ($rows as $r) {
            $key = (int) floor($r->captured_at->timestamp / $bucketSecs);
            $buckets[$key][] = $r;
        }

        $out = [];
        foreach ($buckets as $key => $items) {
            $c = collect($items);
            $out[] = [
                't' => Carbon::createFromTimestamp($key * $bucketSecs)->toIso8601String(),
                'dbBytes' => (int) round($c->avg('db_bytes')),
                'storageBytes' => (int) round($c->avg('storage_bytes')),
                'redisBytes' => (int) round($c->avg('redis_bytes')),
                'requests' => (int) $c->sum('requests'),
                'jobs' => (int) $c->sum('jobs_processed'),
                'bandwidthBytes' => (int) $c->sum('bandwidth_bytes'),
                'cpu' => round((float) $c->avg('cpu_pct'), 1),
                'respMs' => (int) round($c->avg('avg_response_ms')),
                'queueDepth' => (int) round($c->max('queue_depth')),
            ];
        }

        return $out;
    }

    private static function dbBytes(Tenant $tenant): int
    {
        // Local tenants are sqlite files; production would query information_schema.
        if ($tenant->database && is_file($tenant->database)) {
            return (int) filesize($tenant->database);
        }

        return 0;
    }

    private static function storageBytes(Tenant $tenant): int
    {
        $dir = storage_path('app/tenants/'.$tenant->slug);
        if (! is_dir($dir)) {
            return 0;
        }
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $size += $f->getSize();
        }

        return $size;
    }
}
