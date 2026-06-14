# Deploying Convoro reliably

Convoro runs as a standard Laravel app, but a few production settings matter a
lot for **uptime**. These were learned the hard way on our own hosting — bake
them in and the "site won't load" / "it went down" class of problems disappears.

## 1. nginx — cache headers (prevents stale service worker)

Use [`nginx.conf.example`](./nginx.conf.example). The critical rules:

- `location = /sw.js` → `Cache-Control: no-cache`. Without this, browsers
  HTTP-cache the service-worker script and **never pick up new deploys** — a
  stale worker keeps serving a dead cached shell, and returning visitors see a
  blank/broken page while incognito works fine. This is the #1 PWA footgun.
- `location /build/` → `Cache-Control: public, max-age=31536000, immutable`.
  Vite content-hashes these files, so caching forever is safe and fast.

The HTML shell is already sent `no-cache, private` by the app, and the bundled
service worker (`public/sw.js`) auto-updates and reloads stuck clients — but the
nginx rules above are what let that mechanism actually fire.

## 2. Redis / Valkey — don't let the cache crash

Convoro uses Redis (or the drop-in Valkey) for cache, sessions and queues. If it
dies, the site 500s. Two settings prevent that:

### Enable memory overcommit (required by Redis/Valkey)
Redis/Valkey warns on every boot if this is off; without it, background saves
(fork) can fail and crash the server under light memory pressure.

```bash
echo "vm.overcommit_memory = 1" | sudo tee /etc/sysctl.d/99-redis.conf
sudo sysctl vm.overcommit_memory=1
```

### Auto-restart on failure (systemd drop-in)
```ini
# /etc/systemd/system/valkey.service.d/override.conf  (or redis-server.service.d)
[Unit]
StartLimitIntervalSec=0          # never give up restarting

[Service]
Restart=always
RestartSec=1
TimeoutStartSec=90               # allow a full AOF/RDB load on boot
TimeoutStopSec=30
```
```bash
sudo systemctl daemon-reload && sudo systemctl restart valkey
```

Enable persistence (AOF) so a restart reloads state in well under a second:
`appendonly yes` in your redis/valkey config.

> Running both `redis-server` and `valkey` will fight over port 6379. Pick one;
> `systemctl disable --now` + `mask` the other.

## 3. Queue worker + scheduler

```ini
# /etc/systemd/system/convoro-worker.service
[Service]
ExecStart=/usr/bin/php /var/www/convoro/artisan queue:work --sleep=1 --tries=3 --max-time=3600
Restart=always
User=www-data
```
```cron
* * * * * www-data cd /var/www/convoro && php artisan schedule:run >> /dev/null 2>&1
```

## 4. After each deploy — **clearing caches so new code is served**

Production runs with **PHP OPcache enabled** (Convoro has its own FPM pool —
`/etc/php/8.5/fpm/pool.d/convoro.conf`, socket `/run/php/convoro8.5-fpm.sock` —
with `opcache.enable=1`, JIT, and `opcache.validate_timestamps=1` /
`revalidate_freq=2`). OPcache keeps compiled bytecode in shared memory, so when
PHP files change you **must reset it or the old code keeps being served.**

**The standard deploy already does all of this** — just run:

```bash
bash scripts/deploy.sh        # builds client+SSR, ships, migrates, caches,
                              # reloads php-fpm (resets OPcache), restarts SSR
```

If you change files **by hand** on the server, run the equivalent:

```bash
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan convoro:cache-bust          # clears app+extension caches; resets OPcache if run in FPM
sudo systemctl reload php8.5-fpm        # ← resets the FPM OPcache (validate_timestamps also self-heals in ~2s)
sudo systemctl restart convoro-ssr      # ← only if you changed FRONTEND code (client/SSR bundle)
```

### Extensions clear caches automatically

Installing, enabling, disabling, updating or removing an extension through the
admin **Marketplace** runs in the web request and calls
`App\Support\Caches::bust()` (clear caches + `opcache_reset()`), so the new
extension code/routes/pages take effect immediately — no shell step needed.
Extension authors don't reimplement this; if your extension writes generated
PHP/config at runtime, call `\App\Support\Caches::bust()` afterwards.

> **Why OPcache reset is required:** `opcache_reset()` only resets the OPcache of
> the SAPI it runs in. The in-app paths run under FPM (✅). The CLI/queue and
> shell deploys reset OPcache by **reloading php-fpm** instead. As a backstop,
> `validate_timestamps=1` makes FPM re-check file mtimes every 2s, so a missed
> reset self-heals within seconds rather than serving stale code forever.

That's the whole reliability checklist. Items 1, 2 and 4 are the ones that
actually keep the lights on.
