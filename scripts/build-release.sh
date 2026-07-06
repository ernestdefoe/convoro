#!/usr/bin/env bash
#
# Build the self-contained Convoro installer (convoro-install.zip): all app code
# + vendor libraries + compiled assets, MINUS every secret and local-state file.
# Refreshes the public download so the newest version is always available.
#
# Run from a host that has vendor/ and public/build/ (i.e. a deployed app root).
# Run as root (writes/chowns public/releases). Invoked automatically by deploy.sh.
#
#   sudo bash scripts/build-release.sh [/path/to/app]
#
# ABORTS if any real secret (.env, the live APP_KEY/DB_PASSWORD, or a private key
# outside vendor/) is detected in the archive — the public-zip .env leak must not
# recur (see the release-zip incident note).
set -euo pipefail

APP="${1:-$(pwd)}"
cd "$APP"

[ -f vendor/autoload.php ] || { echo "no vendor/ — run from a deployed app root"; exit 1; }
[ -d public/build ] || { echo "no public/build — assets not compiled"; exit 1; }

VER=$(grep -oE "'version' *=> *'[0-9][0-9.]*'" config/convoro.php | grep -oE "[0-9][0-9.]*" | head -1)
[ -n "$VER" ] || VER="latest"

TMP="$(mktemp -u)-convoro-install.zip"
echo "  building installer ($VER)…"
zip -r -q "$TMP" . \
  -x ".env" -x ".env.*" -x ".git/*" -x "node_modules/*" -x "vendor/vendor/*" \
  -x "storage/app/backups/*" -x "storage/app/downloads/*" -x "storage/app/imports/*" -x "storage/app/public/*" \
  -x "storage/logs/*" -x "storage/framework/sessions/*" -x "storage/framework/cache/*" -x "storage/framework/views/*" \
  -x "bootstrap/cache/*" -x "public/releases/*" -x "public/storage/*" \
  -x "storage/installed" \
  -x "database/database.sqlite" -x "database/tenants/*" -x "*.sqlite" \
  -x "frankenphp" -x "storage/app/frankenphp/*" \
  -x "._*" -x "*/._*" -x ".DS_Store" -x "*/.DS_Store"
# ^ frankenphp: the Octane binary is per-server runtime, not app code — 165MB,
#   and its embedded Go test certificates trip the private-key scan below.
#   `octane:install` downloads it on hosts that opt into Octane.

# ---- secret scan (file-level, against the REAL live secrets) ----
D="$(mktemp -d)"
( cd "$D" && unzip -q "$TMP" )
APPKEY=$(grep -E "^APP_KEY=" .env 2>/dev/null | cut -d= -f2- || true)
DBPW=$(grep -E "^DB_PASSWORD=" .env 2>/dev/null | cut -d= -f2- || true)
fail=0
[ "$(find "$D" -name .env ! -name .env.example | wc -l)" -gt 0 ] && { echo "  LEAK: a .env file is in the archive"; fail=1; }
[ -n "$APPKEY" ] && grep -rqaF "$APPKEY" "$D" && { echo "  LEAK: the live APP_KEY is in the archive"; fail=1; }
[ -n "$DBPW" ]   && grep -rqaF "$DBPW"   "$D" && { echo "  LEAK: the live DB password is in the archive"; fail=1; }
# real keys live in the DB; a PEM in a non-vendor file would be a genuine leak
if grep -rlaE "[-]{5}BEGIN [A-Z ]*PRIVATE KEY[-]{5}" "$D" --exclude-dir=vendor 2>/dev/null | grep -q .; then
  echo "  LEAK: a private key was found outside vendor/"; fail=1
fi
( cd "$D" && php -r "require 'vendor/autoload.php';" ) >/dev/null 2>&1 || { echo "  ERROR: archive autoloader failed to load"; fail=1; }
# The build host is an installed app root; shipping its storage/installed flag
# makes every fresh upload think it's already installed and 404s the installer.
[ -f "$D/storage/installed" ] && { echo "  ERROR: storage/installed is in the archive — fresh installs would be bricked"; fail=1; }
rm -rf "$D"

if [ "$fail" = 1 ]; then
  rm -f "$TMP"
  echo "  ABORT: not publishing — fix the leak above."
  exit 1
fi

# ---- publish ----
REL="$APP/public/releases"
DL="$APP/storage/app/downloads"
mkdir -p "$REL" "$DL"
install -m 0644 "$TMP" "$REL/convoro-install.zip"
install -m 0644 "$TMP" "$REL/convoro-install-${VER}.zip"
install -m 0644 "$TMP" "$DL/convoro-${VER}.zip"
chown www-data:www-data "$REL/convoro-install.zip" "$REL/convoro-install-${VER}.zip" "$DL/convoro-${VER}.zip" 2>/dev/null || true
rm -f "$TMP"
echo "  published convoro-install.zip + convoro-install-${VER}.zip ($(du -h "$REL/convoro-install.zip" | cut -f1))"
