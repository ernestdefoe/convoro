# Quorum — Architecture

Quorum is a modern community/forum platform — the best of Flarum, Invision Community and XenForo, built independently. Self-hostable, Composer-installable, no paid third-party services required.

## Stack

| Layer | Choice | Why |
| --- | --- | --- |
| Core | **Laravel 13 (PHP 8.3+)** | Composer-native; ships auth, Eloquent + migrations, queues, scheduler, mail, broadcasting, filesystem, policies. |
| Frontend | **Inertia.js + Vue 3 + TypeScript + Vite** | Single app, server-driven routing, no separate API for the main app; first-class TipTap support. |
| Styling | **Tailwind CSS** driven by Quorum design tokens (CSS custom properties) | Live theme editor recompiles the tokens at runtime. |
| Editor | **TipTap (ProseMirror)**, full extension set | Stored as **sanitized HTML (+ ProseMirror JSON)**. **No Markdown anywhere** — not in storage, not in display. |
| Database | **MariaDB / MySQL** | Matches existing infra (Herd, crimsoncrew VPS). |
| Realtime | **Laravel Reverb** (WebSockets) | Live threads, presence, typing — self-hosted, first-party. |
| Queue / cache | **Redis / Valkey** | Digests, image processing, broadcast fan-out. |
| Images | **Intervention Image v3** | Compress → WebP + responsive sizes; one-upload → all PWA icon sizes. |
| Search | DB full-text → **Meilisearch (Scout)** later | Start simple, scale up. |
| PWA / Push | Generated manifest + service worker; **Web Push (VAPID)** | Installable, offline, push notifications. |
| Extensions | **Composer packages + Quorum extender API + admin marketplace** | True click-to-install (`composer require` server-side). |

## Directory layout

```
quorum/
├── app/
│   ├── Models/            Eloquent: User, Category, Topic, Post, Tag, Reaction, ...
│   ├── Http/Controllers/  Forum, Topic, Post, Admin/*, Api/*
│   ├── Policies/          Authorization
│   ├── Extend/            Quorum extender API (Extension, Hooks, ThemeVar, ...)
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
├── extensions/            installed Quorum extensions (Composer path/registry)
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

- All theming via **CSS custom properties** (`--q-primary`, `--q-bg`, radius, density, …) defined on `:root`.
- Theme settings stored as JSON; a compiler writes the `:root` block (and a `[data-theme=dark]` block).
- The admin **Live Theme Editor** edits the tokens and updates an iframe/preview in real time (postMessage), then publishes.

## Extension system

- An extension is a **Composer package** (`type: quorum-extension`) with a root `extend.php` returning an array of extenders, mirroring the modern extender pattern but Quorum-native:
  - `Extend\Routes`, `Extend\Frontend` (Vue page/component registration + assets), `Extend\Model`, `Extend\Policy`, `Extend\Settings`, `Extend\Event`, `Extend\ThemeVar`, `Extend\AdminPage`.
- Frontend extension points: named slots + an event bus so extensions inject Vue components without forking core.
- **Extension Manager (admin)**: browses a registry (JSON marketplace), and click-to-install runs `composer require` server-side (queued job), then rebuilds caches + assets. Enable/disable toggles a stored flag.

## Roadmap (phases)

- **P0 — Foundations** ✅ scaffolded: Laravel + Inertia/Vue3/TS + Tailwind + auth + MySQL.  *In progress:* design tokens + app shell.
- **P1 — Core forum**: categories/topics/posts/tags/reactions; feed + grid; topic view; TipTap composer; reaction picker; search.
- **P2 — Media**: WebP compression + responsive sizes.
- **P3 — Live threads** (Reverb presence + live posts + typing).
- **P4 — Notifications + digest email**.
- **P5 — PWA + push** (manifest, service worker, VAPID, icon generator).
- **P6 — Admin + Live Theme Editor**.
- **P7 — Extension system + click-to-install Extension Manager**.
- **P8 — Polish, performance, packaging, docs**.

## Conventions

- TypeScript everywhere on the front end; Vue SFCs with `<script setup lang="ts">`.
- Everything user-facing is translatable (Laravel `__()` / a JS i18n layer) — zero hardcoded strings.
- Authorization through Policies; never trust client input; sanitize all rich content.
- Tests: PHPUnit/Pest for the core; component tests for critical Vue pieces.
