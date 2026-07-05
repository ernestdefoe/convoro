#!/usr/bin/env bash
#
# Convoro production deploy.
#
# Builds the client AND SSR bundles in lockstep, ships them with the app code,
# runs migrations + caches, then restarts PHP-FPM and the SSR sidecar. The SSR
# restart is mandatory: if the sidecar keeps serving an old Vue tree against a
# freshly-built client bundle, every page hydrates with a mismatch.
#
# SAFETY: only the explicit paths below are shipped. .env, storage/, vendor/,
# node_modules/, bootstrap/cache/ and public/releases/ are NEVER touched, so a
# deploy can't clobber prod secrets or runtime state.
#
# Usage:  bash scripts/deploy.sh          # full build + deploy
#         SKIP_BUILD=1 bash scripts/deploy.sh   # reuse the current build output
#
set -euo pipefail

# Default target is the CURRENT prod box (convoro-prod / 103.195.100.103,
# PHP 8.4 + Octane). `cc` is the OLD box — convoro.co no longer points at it;
# deploying there looks successful but changes nothing the public sees.
REMOTE="${CONVORO_REMOTE:-convoro-prod}"
APP="/var/www/convoro"
STAMP="$(git rev-parse --short HEAD 2>/dev/null || echo manual)"
LOCAL_TGZ="$(mktemp -t convoro-deploy.XXXXXX).tgz"

# Paths that are safe to extract over the live app (additive — tar never deletes
# files outside the archive). Deliberately excludes .env, storage, vendor, etc.
SHIP_PATHS=(
  public/build
  bootstrap/ssr
  bootstrap/app.php
  app
  config
  database/migrations
  resources
  routes
  extensions
  composer.json
  scripts
)

echo "==> [1/5] Building client + SSR bundles..."
if [ "${SKIP_BUILD:-0}" = "1" ]; then
  echo "    SKIP_BUILD=1 — reusing existing build output"
else
  npm run build
fi

[ -f bootstrap/ssr/ssr.js ] || { echo "ERROR: bootstrap/ssr/ssr.js missing — SSR build failed"; exit 1; }

echo "==> [2/5] Packaging ($STAMP)..."
COPYFILE_DISABLE=1 tar -czf "$LOCAL_TGZ" "${SHIP_PATHS[@]}"   # COPYFILE_DISABLE stops macOS tar adding ._ AppleDouble files

echo "==> [3/5] Uploading to $REMOTE..."
scp -q "$LOCAL_TGZ" "$REMOTE:/tmp/convoro-deploy.tgz"
rm -f "$LOCAL_TGZ"

echo "==> [4/5] Extracting + migrating + caching on $REMOTE..."
ssh "$REMOTE" APP="$APP" 'bash -se' <<'REMOTE'
set -euo pipefail
sudo tar -xzf /tmp/convoro-deploy.tgz -C "$APP"
sudo chown -R www-data:www-data \
  "$APP/public/build" "$APP/bootstrap/ssr" "$APP/app" \
  "$APP/config" "$APP/database/migrations" "$APP/resources" "$APP/routes"
# lang/ stays www-data-writable for the translation queue (known gotcha).
[ -d "$APP/lang" ] && sudo chown -R www-data:www-data "$APP/lang" || true
cd "$APP"
sudo -u www-data php artisan migrate --force
# Drop the compiled extension manifest so newly-shipped extension files (icons,
# covers, manifest edits) are picked up immediately — tar restores old mtimes,
# which can otherwise leave the mtime-keyed cache looking fresh. Rebuilt lazily
# on the next request.
sudo -u www-data rm -f bootstrap/cache/convoro-extensions.php
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
# Keep the UI translation catalog in sync with the shipped code: lang/_catalog.json
# is generated from t()/__() calls and is NOT part of the deployed lang/ snapshot,
# so without this it drifts and newly-added strings silently fall back to English.
# Then queue translation of anything now missing per active locale (the jobs
# self-chain the rest in the background). Both are non-fatal — a translation
# hiccup must never fail an otherwise-good deploy.
sudo -u www-data php artisan convoro:i18n-scan || true
sudo -u www-data php artisan convoro:i18n-translate || true
rm -f /tmp/convoro-deploy.tgz
REMOTE

echo "==> [5/5] Restarting services (SSR sidecar must match the new bundle)..."
ssh "$REMOTE" 'bash -se' <<'REMOTE'
set -euo pipefail
sudo systemctl restart convoro-ssr.service
# Graceful php-fpm reload starts fresh workers → empties OPcache, so the new
# PHP bytecode is served immediately (OPcache is ON for the convoro pool).
# convoro-prod runs 8.4; fall back to 8.5 for any box still on the old stack.
sudo systemctl reload php8.4-fpm.service 2>/dev/null || sudo systemctl reload php8.5-fpm.service
# Octane serves the main site on convoro-prod — old workers keep the previous
# code in memory until restarted. No-op on boxes without the unit.
sudo systemctl restart convoro-octane.service 2>/dev/null || true
sudo systemctl restart convoro-horizon.service || true
sleep 2
SSR=$(systemctl is-active convoro-ssr.service)
HEALTH=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:13714/health || echo 000)
echo "    convoro-ssr: $SSR (health $HEALTH)"
[ "$SSR" = "active" ] && [ "$HEALTH" = "200" ] || { echo "ERROR: SSR sidecar unhealthy after restart"; exit 1; }
REMOTE

echo "==> [6/6] Refreshing self-contained installer (convoro-install.zip)..."
scp -q scripts/build-release.sh "$REMOTE:/tmp/convoro-build-release.sh"
# Keep the newest download current on every deploy. Non-fatal: a failed installer
# rebuild (e.g. the secret scan tripping) must not fail an otherwise-good deploy.
ssh "$REMOTE" "sudo bash /tmp/convoro-build-release.sh '$APP'; rm -f /tmp/convoro-build-release.sh" || echo "    (installer refresh skipped — see output above)"

echo "==> Done. Deployed $STAMP. SSR rendering live."
