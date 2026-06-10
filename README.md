<p align="center">
  <img src="public/assets/convoro-logo.svg" width="320" alt="Convoro" onerror="this.style.display='none'">
</p>

<h1 align="center">Convoro</h1>

<p align="center">
  <strong>The modern, AI-native community platform.</strong><br>
  A fast, beautiful forum you can theme live, extend without a build step, and run anywhere.
</p>

<p align="center">
  <a href="https://convoro.co">Website</a> ·
  <a href="https://community.convoro.co">Live demo</a> ·
  <a href="https://convoro.co/docs/install.html">Install guide</a> ·
  <a href="https://convoro.co/docs/extensions.html">Build extensions</a> ·
  <a href="https://convoro.co/extensions">Directory</a>
</p>

---

## What is Convoro?

Convoro is a self-hosted community/forum platform that brings together the best ideas from
modern discussion software into one cohesive, contemporary package. It pairs a polished
discussion experience with a **live theme editor**, a **built-in extension directory**, and a
first-class **AI build pipeline** so anyone — human or AI assistant — can extend it quickly.

### Highlights

- **🎨 Live theme editor** — a front-facing drawer to restyle your whole community in real time
  (colors, radius, fonts, layout, avatars, post style) with a built-in accessibility/contrast
  auditor. Save to publish for everyone. Every install gets the exact same experience.
- **🧩 Extensions with no build step** — drop a folder (or one-click install from the directory),
  Convoro autoloads its PHP, runs its migrations, and injects its prebuilt frontend bundle. No
  Composer, no `dump-autoload`, no shell required.
- **🛒 Built-in directory & store** — a Packagist-style registry. Authors **submit a public
  GitHub repo**; free add-ons install in one click and premium ones sell with a license key.
- **🤖 AI-native** — point any AI model at [`/llms.txt`](https://convoro.co/llms.txt) or
  [`/api/ai/spec.json`](https://convoro.co/api/ai/spec.json) and it has the full manifest schema,
  frontend slots, theme tokens, and publish flow to scaffold a working extension on the first try.
- **⚡ Real-time** — live topics, presence, and typing indicators over WebSockets (Laravel Reverb).
- **📨 Messaging** — direct and multi-participant group conversations built into core.
- **📈 Admin** — categorized navigation, dashboard analytics, a queue/jobs overview, members &
  moderation tools, SEO, PWA, and email (SMTP or PHP mail).

## Tech stack

- **Backend:** Laravel 13 (PHP 8.3+), MariaDB/MySQL, Redis (cache / queue / session)
- **Frontend:** Inertia.js + Vue 3 + TypeScript, Vite, Tailwind CSS
- **Real-time:** Laravel Reverb + Echo
- **Media:** GD-powered image pipeline (auto-WebP + responsive sizes)

## Requirements

- PHP **8.4+** with the usual extensions (`mbstring`, `pdo`, `gd`, `intl`, `zip`)
- MySQL **8+** / MariaDB **10.6+**
- Redis (recommended) for cache, queue, and sessions
- Node 18+ (only to build assets from source)

## Quick start (from source)

```bash
git clone https://github.com/ernestdefoe/convoro.git
cd convoro

composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate

# point DB_* / REDIS_* at your services, then:
php artisan migrate
php artisan storage:link

# serve (dev)
php artisan serve
```

Or visit `/install` in a browser for the guided setup wizard (requirements check, database test,
admin account) — no command line needed.

> **Production:** run a queue worker (`php artisan queue:work`), the scheduler
> (`php artisan schedule:run` every minute via cron), and `php artisan reverb:start` for real-time.
> See the [install guide](https://convoro.co/docs/install.html).

## Download

Tagged releases — including ready-to-deploy archives with vendored dependencies and prebuilt
assets — are published on the [**Releases**](https://github.com/ernestdefoe/convoro/releases) page.
Grab the latest `convoro-x.y.z.zip`, unzip on your server, and run the install wizard.

## Building extensions

A Convoro extension is a self-contained folder with an `extension.json` manifest — no build step on
the server. Read the [**developer guide**](https://convoro.co/docs/extensions.html), or hand your AI
assistant [`/llms.txt`](https://convoro.co/llms.txt) and let it scaffold one for you. Publish by
linking your public GitHub repo at [convoro.co/extensions/submit](https://convoro.co/extensions/submit).

## License

Convoro core is open-source software licensed under the [MIT license](LICENSE).
Premium extensions and themes distributed through the Convoro store carry their own licenses.
