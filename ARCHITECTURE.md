# Convoro — Architecture

Convoro is a modern community/forum platform — the best of Flarum, Invision Community and XenForo, built independently. Self-hostable, no paid third-party services required.

**Deployment targets (a hard design constraint):** Convoro must install and run on **cheap shared hosting with no SSH and no Composer** — upload a pre-built archive over FTP/cPanel File Manager, point a browser at it, done. It must also scale up to a real VPS. Everything below is chosen so the *baseline* works without a shell, persistent processes, or Redis; richer features (live threads) light up only when the host supports them and **degrade gracefully** when it doesn't. See **Shared hosting & distribution** below.

## Stack

| Layer | Choice | Why |
| --- | --- | --- |
| Core | **Laravel 13 (PHP 8.3+)** | Composer-native; ships auth, Eloquent + migrations, queues, scheduler, mail, broadcasting, filesystem, policies. |
| Frontend | **Inertia.js + Vue 3 + TypeScript + Vite** | Single app, server-driven routing, no separate API for the main app; first-class TipTap support. |
| Styling | **Tailwind CSS** driven by Convoro design tokens (CSS custom properties) | Live theme editor recompiles the tokens at runtime. |
| Editor | **TipTap (ProseMirror)**, full extension set | Stored as **sanitized HTML (+ ProseMirror JSON)**. **No Markdown anywhere** — not in storage, not in display. |
| Database | **MySQL / MariaDB / PostgreSQL** | Driver-agnostic: portable column types (JSON stored as `longText`/`text` + encoded in PHP), no raw SQL, grouping done in PHP not SQL, casts for booleans. Connections for all three (+ sqlite) ship in `config/database.php`. |
| Realtime | **Laravel Reverb** (WebSockets) — **optional** | Live threads, presence, typing. Needs a persistent process, so it's **off by default**; absent it, the UI degrades to polling/manual refresh (no errors). Push notifications (VAPID) cover shared hosts since they need no socket server. |
| Queue / cache / session | **Database & file drivers by default** (Redis optional) | Shared hosting has no Redis. Defaults: `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`. Queue drained by the cron-driven scheduler (or `sync` as a last resort). On a VPS, switch to Redis and use **Laravel Horizon** (installed; dashboard at `/horizon`, gated to admins) to manage queues. |
| Images | **Intervention Image v3** | Compress → WebP + responsive sizes; one-upload → all PWA icon sizes. |
| Search | DB full-text → **Meilisearch (Scout)** later | Start simple, scale up. |
| PWA / Push | Generated manifest + service worker; **Web Push (VAPID)** | Installable, offline, push notifications. |
| Extensions | **Convoro extender API; install via Composer OR archive upload** | VPS: click-to-install runs `composer require` server-side. Shared host (no Composer): the Marketplace **downloads the extension's pre-bundled zip and extracts it** into `extensions/` — no shell needed. |

## Directory layout

```
convoro/
├── app/
│   ├── Models/            Eloquent: User, Category, Topic, Post, Tag, Reaction, ...
│   ├── Http/Controllers/  Forum, Topic, Post, Admin/*, Api/*
│   ├── Policies/          Authorization
│   ├── Extend/            Convoro extender API (Extension, Hooks, ThemeVar, ...)
│   ├── Support/           Content (TipTap HTML sanitizer), ImagePipeline, Theme compiler
│   └── Events/            PostCreated, TopicLive, ... (broadcast)
├── resources/
│   ├── js/
│   │   ├── Pages/         Inertia pages (Forum/Index, Topic/Show, Admin/*)
│   │   ├── Layouts/       AppLayout (app bar + sidebars), AdminLayout
│   │   ├── Components/    Editor (TipTap), ReactionPicker, TopicCard, ...
│   │   ├── theme/         design tokens (CSS vars) + Tailwind preset
│   │   └── app.ts
│   └── css/app.css
├── extensions/            installed Convoro extensions (Composer path/registry)
├── design/renders/        the original HTML/CSS visual renders (reference)
└── ARCHITECTURE.md
```

## Data model (core)

- **users** — auth + profile (display name, avatar, bio, role, last_seen_at, preferences JSON).
- **categories** — forums/sections (name, slug, icon, color, position, parent_id, permissions).
- **topics** — threads (title, slug, user_id, category_id, first_post_id, reply_count, view_count, last_post_at, is_pinned, is_locked, is_live).
- **posts** — (topic_id, user_id, body_html, body_json, edited_at). Body is sanitized TipTap HTML + ProseMirror JSON; **no markdown**.
- **tags** + **taggables** — many-to-many tags with color.
- **reactions** — (reactable_type, reactable_id, user_id, emoji); reaction set is admin-configurable.
- **attachments** — (post_id, disk, path, mime, width, height, variants JSON) — WebP + responsive sizes.
- **notifications** (Laravel) + **push_subscriptions** (endpoint, p256dh, auth) for Web Push.
- **settings** — key/value (incl. theme tokens JSON, PWA config).
- **extensions** — installed registry (id, version, enabled).

## Content pipeline (no markdown)

1. TipTap edits → emits **ProseMirror JSON + HTML**.
2. Server **sanitizes** the HTML (allow-list of tags/attrs; block scripts, javascript:/data: URLs) and stores both `body_html` (render) and `body_json` (re-edit).
3. Render = the stored sanitized HTML directly. No parse step, no markdown round-trip.

## Realtime (live threads)

- Reverb channels per topic: `topic.{id}` (new posts, edits) + `presence-topic.{id}` (who's here, typing).
- `PostCreated` / `PostUpdated` events broadcast; Vue subscribes via Laravel Echo and live-appends.
- "LIVE · N here" pill driven by presence-channel membership.

## Theme system + live editor

- All theming via **CSS custom properties** (`--c-primary`, `--c-bg`, radius, density, …) defined on `:root`.
- Theme settings stored as JSON; a compiler writes the `:root` block (and a `[data-theme=dark]` block).
- The admin **Live Theme Editor** edits the tokens and updates an iframe/preview in real time (postMessage), then publishes.

## Extension system

**Design goal: zero Composer, zero shell, zero `dump-autoload`** — extensions must install by uploading a zip through the admin Marketplace on the cheapest shared host. (A Composer/registry path can layer on top later for VPS power users, but the archive path is the ground truth.)

### Package layout
```
{id}/                       e.g. ernestdefoe-hello
  extension.json            manifest (required)
  src/                      PHP, PSR-4 — autoloaded by ExtensionManager (no Composer)
    Extension.php           optional Laravel ServiceProvider (register()/boot())
  migrations/               standard Laravel migrations (run on enable)
  assets/
    forum.js                prebuilt ESM, injected on the forum (shipped built)
    admin.js                prebuilt ESM, injected in admin (optional)
  icon.svg, README.md
```

### Manifest (`extension.json`)
```json
{
  "id": "ernestdefoe-hello",
  "name": "Hello",
  "version": "1.0.0",
  "description": "…",
  "author": "Ernest DeFoe",
  "convoro": ">=0.1.0",
  "namespace": "Convoro\\Ext\\Hello\\",
  "provider": "Convoro\\Ext\\Hello\\Extension",
  "migrations": "migrations",
  "permissions": [{ "key": "hello.use", "label": "Use Hello", "category": "Hello", "baseline": true }],
  "assets": { "forum": "assets/forum.js", "admin": "assets/admin.js" },
  "premium": false,
  "price": 0
}
```

### Runtime (`App\Support\ExtensionManager`)
- **Two roots scanned**: `base_path('extensions')` (first-party, shipped with the release) and `storage_path('app/extensions')` (uploaded via Marketplace — writable, survives self-updates since deploy/update skip `storage/`).
- **Custom PSR-4 autoloader** registered in `ExtensionServiceProvider::register()` maps each manifest `namespace` → its `src/` — so extension classes load without Composer knowing they exist.
- **Enabled set** stored in settings (`extensions.enabled` = array of ids). Booting an enabled extension: register its `provider` (a real Laravel `ServiceProvider`) + add its migration path.
- **Permissions** declared in the manifest are merged into `Permissions::catalog()/keys()/baseline()` for enabled extensions, so they appear in the group editor automatically.
- **Frontend** assets for enabled extensions are served by a guarded route `/ext-asset/{id}/{surface}` (path-traversal-checked) and injected as `<script type="module">`; they hook into core through a `window.Convoro` client API (named slots + registration), so no core rebuild is needed.

### Marketplace (admin)
The in-app manager (named **"Marketplace"**, not "Extension Manager"): lists installed extensions with enable/disable toggles + uninstall, **upload-a-zip** installer (extract → validate manifest + `convoro` version constraint → run migrations on enable), and (later) a remote catalog with click-to-install + license-gated premium items.
- **Premium / paid items**: the Marketplace supports both free and **paid** extensions/themes. Paid items are gated by a **license key** issued by the convoro.co members/store backend; the in-app Marketplace authenticates the key against the store API before `composer require` (from a licensed/authenticated Composer registry). Free items install with no key. See the post-build launch plan for the storefront side.

## Shared hosting & distribution (no SSH, no Composer)

The headline distribution is a **pre-built release archive** (`.zip` and `.tar.gz`) offered on the docs/download page — not "clone + composer install". The archive is self-contained:

- **`vendor/` is bundled** (production deps, `composer install --no-dev -o` baked in by CI).
- **`public/build/` is bundled** (Vite assets already compiled — no Node/npm on the server).
- Ships `.env.example`; no secrets committed.

**Web installer (`/install`)** — the entire setup happens in the browser, mirroring Flarum/WordPress:
1. **Requirements check** — PHP ≥ 8.3, required extensions (pdo_mysql, mbstring, openssl, gd/imagick, fileinfo, zip), and **writable** paths (`storage/`, `bootstrap/cache/`, `.env`).
2. **Database step** — collect host/db/user/pass, test the connection.
3. Installer **writes `.env`** (generates `APP_KEY`), **runs migrations + the base seed**, and **creates the admin account** — all via internal calls, never the CLI.
4. Locks itself afterward (`storage/installed` flag); `/install` 404s once installed.

**Scheduler without a daemon** — document a single **cPanel cron** entry hitting `php artisan schedule:run` every minute (drives digests + queue draining). Provide a **web-cron fallback** (a signed URL the user pings from an external cron service) for hosts without cron. `php artisan queue:work` is never *required*: the scheduler runs `queue:work --stop-when-empty` each tick; `sync` is the absolute fallback.

**Runtime posture for shared hosting**
- No persistent processes assumed. Reverb/live-threads are **opt-in** and the frontend no-ops cleanly when realtime config is absent (see `resources/js/echo.ts` guard).
- DB/file drivers for queue/cache/session (above). Local filesystem for uploads (`storage/app/public` + `public/storage` symlink; installer offers a **copy fallback** when symlinks are disabled).
- Image pipeline uses **GD** (near-universal on shared hosts), Imagick if present.
- Keep memory/exec modest: stream large ops, cap image dimensions, paginate everything.

**Optimization budget** — first paint from a warm cache target < 200ms server time; lean Inertia payloads (the `Present` shapers already trim models); HTTP cache + ETag on static-ish JSON; `config:cache`/`route:cache`/`view:cache` baked or triggered by the installer.

## Roadmap (phases)

- **P0 — Foundations** ✅ scaffolded: Laravel + Inertia/Vue3/TS + Tailwind + auth + MySQL.  *In progress:* design tokens + app shell.
- **P1 — Core forum**: categories/topics/posts/tags/reactions; feed + grid; topic view; TipTap composer; reaction picker; search.
- **P2 — Media**: WebP compression + responsive sizes.
- **P3 — Live threads** (Reverb presence + live posts + typing).
- **P4 — Notifications + digest email**.
- **P5 — PWA + push** (manifest, service worker, VAPID, icon generator).
- **P6 — Admin + Live Theme Editor**.
- **P7 — Extension system + click-to-install Marketplace** (free + license-gated paid items; Composer *and* archive-upload install).
- **P8 — Shared-hosting distribution**: web installer (`/install`), CI-built `.zip`/`.tar.gz` release archives (vendor + assets bundled), cron/web-cron scheduler, symlink-copy fallback.
- **P9 — Polish, performance, docs**.

> The shared-hosting **constraints** (no Redis, graceful realtime degradation, GD images, no required daemons) apply from now on, not just at P8 — every phase keeps the no-SSH baseline working.

## Launch / go-live plan (post-build, after P8)

When the platform is built, ship the public presence and migrate infra off the old demo box:

1. **Infra migration** — decommission `crimsoncrew.info` on the existing VPS (the Flarum demo set up 2026-06-08: nginx, PHP-FPM, MariaDB, Redis, mail, MediaMTX). Repurpose the box for Convoro: point DNS for **convoro.co** at it, issue TLS (certbot) for `convoro.co` + `community.convoro.co`, deploy the Laravel/Reverb/queue/scheduler stack (production wss via reverse proxy).
2. **Forum** — install the Convoro forum (this app) at **`community.convoro.co`** (the dogfooding community + support forum).
3. **Marketing site** — `convoro.co` root: a built website highlighting the software's features (the renders' visual language: indigo #5B5BD6 + Inter).
4. **Docs** — a fully documented **install page** (step-by-step setup instructions) + **developer docs** on how to extend the software: building **extensions** and **themes** against the Convoro extender API.
5. **Marketplace storefront** — an **extension/theme discovery page** + a **members section** (accounts) to **purchase premium items**, issuing **license keys**; hook the store into the in-app **Marketplace** (renamed from "Extension Manager") so purchased items install via click-to-install (license-key auth → licensed Composer registry).

## Conventions

- TypeScript everywhere on the front end; Vue SFCs with `<script setup lang="ts">`.
- Everything user-facing is translatable (Laravel `__()` / a JS i18n layer) — zero hardcoded strings.
- Authorization through Policies; never trust client input; sanitize all rich content.
- Tests: PHPUnit/Pest for the core; component tests for critical Vue pieces.
