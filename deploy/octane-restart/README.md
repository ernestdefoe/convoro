# Auto-restart Octane/SSR after updates & extension changes

An in-app self-update or a Marketplace extension enable/install swaps PHP code
(routes, service providers) and the compiled Vue tree. **PHP-FPM** picks this up
via `opcache_reset()`, but **Octane workers** and the **SSR sidecar** are
long-lived — they keep the old code in memory until restarted. The web user
(`www-data`) can't `systemctl restart` them, so without this the update or
install silently "doesn't take" until someone restarts the services by hand.

The app signals a restart by writing `storage/app/services-restart.flag`
(`App\Support\ServiceRestart::request()`, called from `Caches::bust()` and
`Updater::apply()`). This root-owned systemd **path unit** watches that file and
restarts the install's `<instance>-octane` / `<instance>-ssr` units.

> Only needed on installs that actually run Octane and/or an SSR sidecar
> (managed/dedicated). Plain PHP-FPM / shared-hosting installs don't need it —
> `opcache_reset()` already reloads code and there's no long-lived worker; the
> flag is simply never consumed.

## Install (once per box, as root)

```bash
install -m755 convoro-service-restart /usr/local/bin/convoro-service-restart
install -m644 convoro-restart@.path    /etc/systemd/system/convoro-restart@.path
install -m644 convoro-restart@.service /etc/systemd/system/convoro-restart@.service
systemctl daemon-reload

# Enable one instance per install. The instance name must match BOTH the install
# dir (/var/www/<name>) AND the service prefix (<name>-octane / <name>-ssr):
systemctl enable --now convoro-restart@convoro.path
systemctl enable --now convoro-restart@fbsfb.path
```

## Test

```bash
sudo -u www-data touch /var/www/fbsfb/storage/app/services-restart.flag
# ~3s later fbsfb-octane (and fbsfb-ssr) restart; the flag is removed.
journalctl -t convoro-service-restart -n 20
```

## Notes

- The restart script re-runs `config:cache` for performance but **never**
  `route:cache` — enabled extensions register closure routes that can't be
  route-cached.
- `PathExists` re-arms once the flag is removed, so each request restarts once.
  A 3s `sleep` lets the triggering request finish and coalesces bursts.
