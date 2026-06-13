# syntax=docker/dockerfile:1
# ---------------------------------------------------------------------------
# Convoro — production container image.
#   Stage 1 builds the front-end (Vite); stage 2 is the app served by FrankenPHP
#   (Caddy + PHP in one process — no separate nginx/php-fpm to wire up).
# ---------------------------------------------------------------------------

# --- Stage 1: composer deps (also feeds the front-end's Ziggy JS module) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# --- Stage 2: front-end assets (needs vendor/tightenco/ziggy to resolve) ---
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# --- Stage 3: application ---
FROM dunglas/frankenphp:1-php8.4-bookworm AS app

# PHP extensions Convoro needs (image conversion, intl, queues, redis, crypto…).
RUN install-php-extensions pdo_mysql gd zip intl bcmath pcntl exif gmp redis opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# Composer deps first (cached unless composer.json/lock change).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# App code + the built front-end from the frontend stage.
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-interaction \
 && cp docker/php.ini "$PHP_INI_DIR/conf.d/convoro.ini" \
 && cp docker/Caddyfile /etc/caddy/Caddyfile \
 && mkdir -p storage/framework/{cache,sessions,views} storage/app/public bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache public

COPY docker/entrypoint.sh /usr/local/bin/convoro-entrypoint
RUN chmod +x /usr/local/bin/convoro-entrypoint

ENTRYPOINT ["convoro-entrypoint"]
# Default role serves the site; worker services override this command.
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
