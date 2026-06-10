#!/usr/bin/env bash
#
# Deploy Convoro to the VPS.
#
# Builds production assets locally (realtime ON), then rsyncs ONLY application
# code. It must NEVER touch server-managed, stateful data:
#   - storage/        (user uploads in storage/app/public, logs, caches, sessions)
#   - .env            (production secrets)
#   - public/storage  (symlink to storage/app/public)
#   - public/releases, public/update-feed.json (self-updater artifacts)
#   - vendor, node_modules, bootstrap/cache (rebuilt on the server)
#
# ⚠️  Do NOT rsync `storage/` — doing so with --delete destroys user uploads.
#
# Usage:  bin/deploy.sh [ssh-host]   (default host: cc)
set -euo pipefail

HOST="${1:-cc}"
REMOTE="/var/www/convoro"

# Production Reverb app key (public — it ships in the JS bundle anyway).
: "${VITE_REVERB_APP_KEY:=bbd715011149ce3c57abd7031e0132de}"

echo "▶ Building production assets (realtime on)…"
VITE_REALTIME=1 \
VITE_REVERB_APP_KEY="$VITE_REVERB_APP_KEY" \
VITE_REVERB_HOST=community.convoro.co \
VITE_REVERB_PORT=443 \
VITE_REVERB_SCHEME=https \
  npm run build

echo "▶ Syncing code to $HOST:$REMOTE …"
rsync -az --delete \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'vendor' \
  --exclude '.env' \
  --exclude 'storage' \
  --exclude 'public/storage' \
  --exclude 'public/releases' \
  --exclude 'public/update-feed.json' \
  --exclude 'design' \
  --exclude '.github' \
  --exclude 'bootstrap/cache/*.php' \
  ./ "$HOST:$REMOTE/"

echo "▶ Running post-deploy on $HOST …"
ssh "$HOST" "cd $REMOTE \
  && chown -R www-data:www-data . \
  && php artisan migrate --force \
  && php artisan optimize:clear \
  && php artisan config:cache && php artisan route:cache \
  && (systemctl reload php8.5-fpm 2>/dev/null || true) \
  && systemctl restart convoro-worker convoro-reverb"

echo "✓ Deployed."
