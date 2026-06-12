<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Best-effort operational health checks (each: ok | degraded | down). */
class Health
{
    public static function checks(): array
    {
        $checks = [];

        // Web app is responding (we're rendering this).
        $checks[] = ['name' => __('Website'), 'state' => 'ok', 'detail' => __('Responding normally.')];

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $checks[] = ['name' => __('Database'), 'state' => 'ok', 'detail' => __('Connected.')];
        } catch (\Throwable) {
            $checks[] = ['name' => __('Database'), 'state' => 'down', 'detail' => __('Unreachable.')];
        }

        try {
            Cache::put('health.ping', '1', 10);
            $ok = Cache::get('health.ping') === '1';
            $checks[] = ['name' => __('Cache'), 'state' => $ok ? 'ok' : 'degraded', 'detail' => $ok ? __('Read/write OK.') : __('Not persisting.')];
        } catch (\Throwable) {
            $checks[] = ['name' => __('Cache'), 'state' => 'degraded', 'detail' => __('Unavailable.')];
        }

        try {
            $failed = DB::table('failed_jobs')->count();
            $checks[] = [
                'name' => __('Background jobs'),
                'state' => $failed > 0 ? 'degraded' : 'ok',
                'detail' => $failed > 0 ? __(':count failed job(s).', ['count' => $failed]) : __('No failures.'),
            ];
        } catch (\Throwable) {
            $checks[] = ['name' => __('Background jobs'), 'state' => 'ok', 'detail' => __('Queue not in use.')];
        }

        $mailReady = (bool) Settings::get('mail.configured', false);
        $checks[] = [
            'name' => __('Email delivery'),
            'state' => $mailReady ? 'ok' : 'degraded',
            'detail' => $mailReady ? __('Configured.') : __('Not configured yet.'),
        ];

        // Disk space (best-effort).
        try {
            $free = @disk_free_space(base_path());
            $total = @disk_total_space(base_path());
            if ($free && $total) {
                $usedPct = (int) round(($total - $free) / $total * 100);
                $checks[] = [
                    'name' => __('Disk space'),
                    'state' => $usedPct >= 92 ? 'degraded' : 'ok',
                    'detail' => __(':percent% used · :free free', ['percent' => $usedPct, 'free' => self::human($free)]),
                ];
            }
        } catch (\Throwable) {
            // skip
        }

        return $checks;
    }

    private static function human(float $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($u) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$u[$i];
    }
}
