<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public "trust" pages — the signals of an actively-maintained product:
 * changelog, roadmap, and a live system status page.
 */
class TrustController extends Controller
{
    public function changelog(): Response
    {
        $releases = config('trust.changelog', []);
        $current = (string) config('convoro.version');

        // Surface the available (not-yet-installed) version from the update feed
        // so the changelog always shows what's new even before you update.
        $feed = $this->updateFeed();
        $latest = (string) ($feed['version'] ?? '');
        if ($latest !== '' && version_compare($latest, $current, '>')
            && ! in_array($latest, array_column($releases, 'tag'), true)) {
            array_unshift($releases, [
                'tag' => $latest,
                'date' => $feed['date'] ?? null,
                'title' => $feed['title'] ?? __('Available now'),
                'items' => is_array($feed['changelog'] ?? null) ? $feed['changelog'] : [],
                'available' => true,
            ]);
        }

        return Inertia::render('Marketing/Changelog', [
            'releases' => $releases,
            'version' => $current,
            'seo' => Seo::make(['title' => __('Changelog — Convoro'), 'description' => __('Every Convoro release and what changed. We ship continuously.')]),
        ]);
    }

    /** Cached fetch of the update feed (best-effort; null on any failure). */
    private function updateFeed(): ?array
    {
        $url = config('convoro.update_url');
        if (! $url) {
            return null;
        }

        try {
            return Cache::remember('trust.update_feed', 300, fn () => Http::timeout(5)->acceptJson()->get($url)->json());
        } catch (\Throwable) {
            return null;
        }
    }

    public function roadmap(): Response
    {
        return Inertia::render('Marketing/Roadmap', [
            'roadmap' => config('trust.roadmap', []),
            'seo' => Seo::make(['title' => __('Roadmap — Convoro'), 'description' => __('What we have shipped, what we are building now, and what is coming next.')]),
        ]);
    }

    public function status(): Response
    {
        return Inertia::render('Marketing/Status', [
            'checks' => \App\Support\Health::checks(),
            'version' => config('convoro.version'),
            'seo' => Seo::make(['title' => __('System status — Convoro'), 'description' => __('Live operational status of the Convoro platform.')]),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Marketing/About', [
            'seo' => Seo::make(['title' => __('About Convoro'), 'description' => __('Modern community forum software, built for the AI era and runnable on cheap shared hosting.')]),
        ]);
    }

    public function security(): Response
    {
        return Inertia::render('Marketing/Security', [
            'seo' => Seo::make(['title' => __('Security — Convoro'), 'description' => __('How Convoro keeps communities safe: extension review, updates, and responsible disclosure.')]),
        ]);
    }

    /** Live, best-effort health checks. Each returns ok|degraded|down. */
    private function statusChecks(): array
    {
        $checks = [];

        // Web app is responding (we're rendering this).
        $checks[] = ['name' => __('Website'), 'state' => 'ok', 'detail' => __('Responding normally.')];

        // Database
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $checks[] = ['name' => __('Database'), 'state' => 'ok', 'detail' => __('Connected.')];
        } catch (\Throwable) {
            $checks[] = ['name' => __('Database'), 'state' => 'down', 'detail' => __('Unreachable.')];
        }

        // Cache
        try {
            Cache::put('status.ping', '1', 10);
            $ok = Cache::get('status.ping') === '1';
            $checks[] = ['name' => __('Cache'), 'state' => $ok ? 'ok' : 'degraded', 'detail' => $ok ? __('Read/write OK.') : __('Not persisting.')];
        } catch (\Throwable) {
            $checks[] = ['name' => __('Cache'), 'state' => 'degraded', 'detail' => __('Unavailable.')];
        }

        // Background jobs
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

        // Mail
        $mailReady = (bool) \App\Support\Settings::get('mail.configured', false);
        $checks[] = [
            'name' => __('Email delivery'),
            'state' => $mailReady ? 'ok' : 'degraded',
            'detail' => $mailReady ? __('Configured.') : __('Not configured yet.'),
        ];

        return $checks;
    }
}
