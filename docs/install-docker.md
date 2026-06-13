# Install Convoro with Docker

Convoro ships a production-ready Docker setup: one app container (FrankenPHP —
Caddy + PHP in a single process), plus MySQL, Redis, a queue worker (Horizon),
the realtime server (Reverb) and the scheduler. No separate nginx/php-fpm to
wire up.

## Requirements

- Docker Engine 24+ and the Docker Compose plugin (`docker compose`).

## Quick start

```bash
git clone https://github.com/ernestdefoe/convoro.git
cd convoro

cp docker/env.example .env

# Generate an app key and paste it into APP_KEY in .env:
docker compose run --rm app php artisan key:generate --show

docker compose up -d --build
```

Open <http://localhost:8080>. The first start builds the image, waits for the
database, and runs migrations automatically. To seed demo content:

```bash
docker compose exec app php artisan db:seed
```

Create the first admin (or promote a user):

```bash
docker compose exec app php artisan tinker --execute="\App\Models\User::query()->first()->update(['is_admin' => true]);"
```

## What's in the stack

| Service     | Purpose                                            |
|-------------|----------------------------------------------------|
| `app`       | The site (FrankenPHP). Runs migrations on startup. |
| `queue`     | Background jobs (Laravel Horizon).                 |
| `scheduler` | Scheduled tasks (digests, reindex, cleanup…).      |
| `reverb`    | Realtime websockets (live threads, presence).      |
| `db`        | MySQL 8.4.                                          |
| `redis`     | Cache, queue and sessions.                          |

## Configuration

Everything is driven by `.env`. Common changes:

- **Port** — set `APP_PORT` (default `8080`).
- **Public URL** — set `APP_URL` to your real domain and put a TLS-terminating
  reverse proxy (Caddy, nginx, Traefik, Cloudflare Tunnel…) in front of `app`.
- **Mail** — `MAIL_MAILER=smtp` with your SMTP credentials for real delivery.
- **Realtime** — set strong `REVERB_APP_KEY`/`REVERB_APP_SECRET`, and point
  `REVERB_HOST`/`REVERB_SCHEME` at your public websocket endpoint.

## Day-to-day

```bash
docker compose logs -f app          # tail the app logs
docker compose exec app php artisan migrate   # run new migrations after an update
docker compose pull && docker compose up -d --build   # update
docker compose down                 # stop (data is kept in named volumes)
```

Uploaded files live in the `storage` volume; the database in `db`; Redis in
`redis`. Back those volumes up as you would any Docker data volume — or use
Convoro's built-in **Admin → Backups**.

## Production notes

- Put HTTPS in front (the container speaks plain HTTP on port 80 internally).
- Set `APP_DEBUG=false` (the default here) and a unique `APP_KEY`.
- For reply-by-email and other inbound mail, see **Admin → Settings**.
