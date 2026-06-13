#!/bin/sh
# Convoro container entrypoint: wait for the database, run one-time setup on the
# primary (app) container, then hand off to the service command.
set -e

echo "Convoro: waiting for the database…"
until php -r '
  $h = getenv("DB_HOST") ?: "db";
  $p = (int) (getenv("DB_PORT") ?: 3306);
  $u = getenv("DB_USERNAME") ?: "convoro";
  $pw = getenv("DB_PASSWORD") ?: "";
  try { new PDO("mysql:host=$h;port=$p", $u, $pw, [PDO::ATTR_TIMEOUT => 2]); exit(0); }
  catch (Throwable $e) { exit(1); }
' 2>/dev/null; do
  sleep 2
done
echo "Convoro: database is up."

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
  # Generate an app key once if the operator left it blank.
  if [ -z "${APP_KEY:-}" ]; then
    echo "Convoro: APP_KEY is empty — generating an ephemeral one (set APP_KEY in .env for a stable key)."
    export APP_KEY="$(php artisan key:generate --show)"
  fi

  php artisan migrate --force --no-interaction || true
  php artisan storage:link --no-interaction 2>/dev/null || true
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
  echo "Convoro: setup complete."
fi

exec "$@"
