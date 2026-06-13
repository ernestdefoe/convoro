<?php

/*
 * Documentation content. Each guide renders on /docs/{slug}. Sections support
 * paragraphs (strings) and code blocks (['code' => '...', 'lang' => '...']).
 * Plain data so guides are easy to edit without touching Vue.
 */

return [

    'install' => [
        'icon' => '🚀',
        'title' => 'Installing Convoro',
        'intro' => 'Get a community online in minutes — pick the no-terminal zip for shared hosting, or Composer if you prefer the command line.',
        'sections' => [
            [
                'h' => 'Requirements',
                'body' => [
                    'Convoro runs on a standard PHP host. You need PHP 8.3+ with the pdo, mbstring, openssl, gd, fileinfo, ctype, json and zip extensions, plus a MySQL/MariaDB or PostgreSQL database.',
                ],
            ],
            [
                'h' => 'Option A — Prebuilt zip (no terminal)',
                'body' => [
                    'The simplest route, ideal for shared hosting. Download the prebuilt archive — it already includes the vendor libraries and compiled assets, so there is nothing to build.',
                    ['link' => 'https://convoro.co/releases/convoro-install.zip', 'label' => 'Download Convoro (.zip)'],
                    'Upload and unzip it to your web root (point the document root at the public/ folder), then open your domain in a browser. The guided installer checks requirements, writes your config, runs migrations, and creates your admin account.',
                    ['code' => "# after uploading + unzipping on the server, visit:\nhttps://your-domain.com/install", 'lang' => 'bash'],
                    'The installer also flags production recommendations (OPcache, memory overcommit) so you start on solid footing.',
                ],
            ],
            [
                'h' => 'Option B — Composer (command line)',
                'body' => [
                    'Prefer the terminal? Clone the repository and let Composer do the rest. You need PHP 8.3+, Composer and Node.js on the machine.',
                    ['code' => "git clone https://github.com/ernestdefoe/convoro.git\ncd convoro\n\n# install PHP + JS deps, copy .env, generate a key,\n# run migrations and build the assets — all in one:\ncomposer setup\n\n# then serve it (or point your web server at public/):\nphp artisan serve", 'lang' => 'bash'],
                    'Set your database credentials in the .env file before running migrations. `composer setup` runs composer install, copies .env.example to .env, generates the app key, migrates the database, and builds the frontend. To do it by hand:',
                    ['code' => "composer install\ncp .env.example .env\nphp artisan key:generate\n# edit .env — set DB_DATABASE / DB_USERNAME / DB_PASSWORD\nphp artisan migrate\nnpm install && npm run build\nphp artisan storage:link", 'lang' => 'bash'],
                ],
            ],
            [
                'h' => 'Option C — Docker',
                'body' => [
                    'For container fans, Convoro ships a production-ready Docker setup: one app container (FrankenPHP — Caddy and PHP in a single process), plus MySQL, Redis, a queue worker, the realtime server and the scheduler. No separate nginx or php-fpm to wire up.',
                    ['code' => "git clone https://github.com/ernestdefoe/convoro.git\ncd convoro\n\ncp docker/env.example .env\n# generate an app key and paste it into APP_KEY in .env:\ndocker compose run --rm app php artisan key:generate --show\n\ndocker compose up -d --build", 'lang' => 'bash'],
                    'The site comes up on http://localhost:8080 — the first start builds the image, waits for the database, and runs migrations automatically. Put HTTPS in front of it in production (Caddy, nginx, Traefik or a Cloudflare Tunnel).',
                    'Prefer not to build? The image is published on Docker Hub — pull it and set it in your compose instead of building from source:',
                    ['code' => "docker pull ernestdefoe/convoro:latest\n\n# in docker-compose.yml, replace \"build: .\" with:\n#   image: ernestdefoe/convoro:latest", 'lang' => 'bash'],
                    'Full details, the service list and configuration options are in docs/install-docker.md in the repository.',
                ],
            ],
        ],
    ],

    'ask-convoro' => [
        'icon' => '✦',
        'title' => 'Ask Convoro (AI)',
        'intro' => 'Turn your community into an instantly-answerable knowledge base. Ask Convoro answers members’ questions from your own threads, with citations.',
        'sections' => [
            [
                'h' => 'What it does',
                'body' => [
                    'Ask Convoro adds an answer bar to your community home. A member types a question in plain language and gets a concise answer synthesized from your forum’s existing discussions — every answer cites the threads it drew from, so members can dig deeper and trust the source.',
                    'Because it draws on real content, a brand-new community is useful from day one, and every post members write makes future answers better.',
                ],
            ],
            [
                'h' => 'Connect a language model',
                'body' => [
                    'Open Admin → AI and connect any Anthropic (Claude) or OpenAI-compatible model — paste a key and pick a model. This one key powers Ask Convoro and every AI feature; nothing is sent anywhere until you configure it.',
                ],
            ],
            [
                'h' => 'Turn on semantic search (recommended)',
                'body' => [
                    'Add a Voyage AI embeddings key under Admin → AI → Semantic search, then click “Rebuild index.” Convoro embeds your posts so Ask understands meaning, not just keywords — it finds the right thread even when the wording is different.',
                    'New and edited posts are indexed automatically. Without an embeddings key, Ask still works using full-text search as a fallback.',
                ],
            ],
            [
                'h' => 'Privacy & cost',
                'body' => [
                    'You bring your own model and keys, so you stay in control of cost and data. Answers are generated on demand and rate-limited. Keys are stored in your database and never shown back in the admin once saved.',
                ],
            ],
        ],
    ],

    'configuration' => [
        'icon' => '⚙️',
        'title' => 'Configuration',
        'intro' => 'Everything is configured from the admin — no config files to hand-edit.',
        'sections' => [
            [
                'h' => 'Settings',
                'body' => ['Admin → Settings controls your community name, registration, and core behavior. Changes save instantly.'],
            ],
            [
                'h' => 'Email',
                'body' => [
                    'Admin → Email lets you send via the host’s built-in mail or any SMTP provider. Set a from-address and send a test to confirm delivery before going live.',
                ],
            ],
            [
                'h' => 'PWA & push',
                'body' => ['Admin → PWA turns your community into an installable app with push notifications. Upload one icon and Convoro generates every size.'],
            ],
        ],
    ],

    'theming' => [
        'icon' => '🎨',
        'title' => 'Theming',
        'intro' => 'Reskin your whole community live, with no CSS required.',
        'sections' => [
            [
                'h' => 'The live theme editor',
                'body' => [
                    'Open the editor from the floating button on your site. Adjust colors, fonts, radius, density, header, and avatars with an instant preview. Changes autosave and publish to everyone.',
                ],
            ],
            [
                'h' => 'Widgets',
                'body' => ['Drag widgets (stats, online now, newest members, custom HTML…) into the sidebar — grab a widget’s header to reorder.'],
            ],
            [
                'h' => 'Accessibility',
                'body' => ['The A11y tab live-audits color contrast; click a failing check to jump straight to the control that fixes it.'],
            ],
        ],
    ],

    'extensions' => [
        'icon' => '🧩',
        'title' => 'Extensions & themes',
        'intro' => 'Extend Convoro with drop-in add-ons — installable from the admin, no terminal.',
        'sections' => [
            [
                'h' => 'The manifest',
                'body' => [
                    'An extension is a folder with an extension.json manifest plus optional PHP (src/), migrations, and prebuilt assets. No build step runs on the server.',
                    ['code' => "{\n  \"id\": \"convoro-hello\",\n  \"name\": \"Hello\",\n  \"version\": \"1.0.0\",\n  \"type\": \"extension\"\n}", 'lang' => 'json'],
                ],
            ],
            [
                'h' => 'Publishing from GitHub',
                'body' => [
                    'Link a public GitHub repo in the store console — Convoro reads the manifest and lists it. Updates pull from the latest release (or pin a tag), Packagist-style.',
                ],
            ],
            [
                'h' => 'AI security review',
                'body' => ['Every directory extension is audited from its real source by an LLM and gets an unbiased safety verdict shown on its listing — a strong signal before you install.'],
            ],
            [
                'h' => 'Build with AI',
                'body' => ['Convoro publishes a machine-readable build spec at /llms.txt so AI assistants can scaffold a correct, installable extension on the first try.'],
            ],
        ],
    ],

    'deploy' => [
        'icon' => '🛠️',
        'title' => 'Production deployment',
        'intro' => 'Run Convoro reliably — the settings that actually keep the lights on.',
        'sections' => [
            [
                'h' => 'Web server',
                'body' => [
                    'Use the bundled deploy/nginx.conf.example. Two rules matter most: serve sw.js as no-cache (or stale service workers break returning visitors) and cache /build/ assets immutably.',
                ],
            ],
            [
                'h' => 'Redis / Valkey',
                'body' => [
                    'If you use Redis (or Valkey) for cache/queues, enable memory overcommit and auto-restart so a blip never takes the site down.',
                    ['code' => "echo 'vm.overcommit_memory = 1' | sudo tee /etc/sysctl.d/99-redis.conf\nsudo sysctl vm.overcommit_memory=1", 'lang' => 'bash'],
                ],
            ],
            [
                'h' => 'Workers & scheduler',
                'body' => ['Run a queue worker and the scheduler (one cron entry) for background jobs, digests, and the extension registry auto-refresh. Full details in deploy/DEPLOYMENT.md.'],
            ],
        ],
    ],

    'migrating' => [
        'icon' => '🔁',
        'title' => 'Migrating from another forum',
        'intro' => 'Bring your whole community across from Flarum, XenForo, phpBB, Discourse or vBulletin in one pass — members, categories, topics and posts.',
        'sections' => [
            [
                'h' => 'How it works',
                'body' => [
                    'Open Admin → Import, pick your current platform, and enter your old forum’s database connection (host, database, username, password — and the table prefix for phpBB/vBulletin). The importer reads the source read-only; it never writes to it.',
                    'Hit Test connection to see how many members, categories, topics and posts will come across, then Start import. It runs in the background, so you can leave the page and come back — a progress bar and live counts show where it’s up to.',
                ],
            ],
            [
                'h' => 'What comes across',
                'body' => [
                    'Forums/categories → categories, members → members, discussions/threads → topics, and replies → posts. Old formatting (BBCode, or Discourse’s rendered HTML) is converted to Convoro’s. Set your old forum’s URL to also bring over avatars and embedded images.',
                    'Members keep their passwords wherever the old hashes are portable (Flarum, XenForo, phpBB 3.1+). Where they aren’t (Discourse, vBulletin), members simply reset their password on first login.',
                ],
            ],
            [
                'h' => 'Safe to re-run',
                'body' => [
                    'The import dedupes by email and by a source-derived slug, so existing members, categories and topics are skipped rather than duplicated — you can run it again to pick up new content.',
                    'The Flarum importer is the most battle-tested; the XenForo, phpBB, Discourse and vBulletin importers are covered by automated mapping tests but every real forum differs slightly — back up your Convoro database first and review the result.',
                ],
            ],
        ],
    ],

    'members' => [
        'icon' => '👥',
        'title' => 'Members, groups & permissions',
        'intro' => 'Organize who can do what — with colored groups, a granular permission system, and invite-only signups.',
        'sections' => [
            [
                'h' => 'Groups & permissions',
                'body' => [
                    'Admin → Members → Groups lets you create groups, give each a color, mark it as staff, and tick exactly which permissions its members get. Admin and Moderator are built-in system groups — you can recolor or rename them, but not delete them.',
                    'Staff-group members show a small colored badge beneath their avatar (it follows the Admin group’s color), so moderators are recognizable everywhere.',
                ],
            ],
            [
                'h' => 'Invites & invite-only signup',
                'body' => [
                    'In Admin → Invites you can create shareable invite links — optionally with a maximum number of uses and an expiry — and turn on invite-only registration so new members must enter a code to join.',
                ],
            ],
        ],
    ],

    'moderation' => [
        'icon' => '🛡️',
        'title' => 'Moderation & safety',
        'intro' => 'Keep your community healthy with a moderation queue, an AI copilot, and IP tools.',
        'sections' => [
            [
                'h' => 'The moderation queue',
                'body' => ['Reported and flagged posts land in Admin → Moderation. From there you can approve, hide, move a reply to another topic, or remove content, and resolve reports with one click.'],
            ],
            [
                'h' => 'AI moderation copilot',
                'body' => ['Turn on the copilot in Admin → AI to have every new post screened for spam, toxicity and exposed personal data. Risky posts are flagged with a score and labels; very high-risk posts are held (hidden) until a moderator approves them.'],
            ],
            [
                'h' => 'IP info & bans',
                'body' => ['Posts record the author’s IP for moderation. You can look up a member’s IPs and ban an address outright when you need to stop a persistent abuser.'],
            ],
        ],
    ],

    'privacy' => [
        'icon' => '🔒',
        'title' => 'Privacy & GDPR',
        'intro' => 'The tools to run a privacy-respecting community and honour data requests worldwide.',
        'sections' => [
            [
                'h' => 'Anonymize or delete a member',
                'body' => [
                    'To honour a “delete my account” request, open Admin → Members, edit the member, and use the Data & privacy (GDPR) section.',
                    'Anonymize erases their personal data — name, email, avatar, bio and stored IP addresses — but keeps their posts and topics readable as “Deleted user”, so discussions stay intact. This is the recommended option. Delete account & content fully removes the member and everything they authored.',
                ],
            ],
            [
                'h' => 'Cookie consent',
                'body' => [
                    'Install the Advanced Cookie Consent add-on for a GDPR/CCPA banner with granular categories and per-category script gating. It honours Global Privacy Control / Do Not Track signals, frames a “Do Not Sell or Share” notice for US visitors, and keeps a persistent “Privacy choices” link so members can change their mind.',
                ],
            ],
            [
                'h' => 'Data minimization',
                'body' => ['Convoro only stores what a forum needs. IP addresses are kept for moderation and can be cleared by anonymizing the member; sessions and push subscriptions are removed when an account is anonymized or deleted.'],
            ],
        ],
    ],

    'languages' => [
        'icon' => '🌐',
        'title' => 'Languages & translation',
        'intro' => 'Run a community in your members’ languages — and let them read each other across the language barrier.',
        'sections' => [
            [
                'h' => 'Interface languages',
                'body' => [
                    'Admin → Languages shows translation coverage per language. Add any of 45+ languages, set the site default, and fill a language instantly with one-click AI translation (powered by your connected model). Right-to-left languages flip the whole layout automatically.',
                ],
            ],
            [
                'h' => 'Per-reader translation',
                'body' => [
                    'A Translate link on any post or direct message renders it in the reader’s language with a “Show original” toggle — so a member can write in Arabic and another can read it in English. Turn on Auto-translate to have everything arrive already translated.',
                    'Notification and digest emails are sent in each recipient’s own language and text direction.',
                ],
            ],
        ],
    ],

    'marketplace' => [
        'icon' => '🛒',
        'title' => 'Selling extensions',
        'intro' => 'Run your own extension & theme store, with Stripe payouts and a GitHub-linked registry.',
        'sections' => [
            [
                'h' => 'The store console',
                'body' => ['Manage listings from the front-facing /extensions/manage — no admin section needed. Connect your account with Stripe to take payments, and your earnings are paid out via Stripe Connect.'],
            ],
            [
                'h' => 'Publish from GitHub',
                'body' => [
                    'Link a public repo and Convoro tracks its releases Packagist-style — auto-refreshing on new tags (hourly, or instantly via a webhook) and letting you pin to a version. Every directory extension is also audited from its real source by an LLM, which posts an unbiased safety verdict on the listing.',
                ],
            ],
            [
                'h' => 'Licenses',
                'body' => ['Buyers find their purchases under /account/licenses, where they can download files and retrieve license keys.'],
            ],
        ],
    ],

    'updates' => [
        'icon' => '⬆️',
        'title' => 'Updates & backups',
        'intro' => 'Keep Convoro current with one click — safely.',
        'sections' => [
            [
                'h' => 'One-click updates',
                'body' => [
                    'Admin → Dashboard checks for new releases and shows a “What’s new” summary before you install. Click Apply and Convoro downloads the release, copies the new files into place (preserving your .env, uploads and storage), runs any database migrations, and reloads — no Composer or terminal required, even on shared hosting.',
                ],
            ],
            [
                'h' => 'Back up first',
                'body' => ['Updates are designed to be safe and protected paths are never overwritten, but take a database backup before any update or import — it’s the one habit that turns a bad day into a non-event.'],
            ],
        ],
    ],
];
