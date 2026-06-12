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
        'intro' => 'Get a community online in minutes — even on cheap shared hosting, entirely in your browser.',
        'sections' => [
            [
                'h' => 'Requirements',
                'body' => [
                    'Convoro runs on a standard PHP host. You need:',
                    'PHP 8.3+ with the pdo, mbstring, openssl, gd, fileinfo, ctype, json and zip extensions, plus a MySQL/MariaDB or PostgreSQL database.',
                    'No SSH, Composer, or Node is required on the server — the release archive ships with everything prebuilt.',
                ],
            ],
            [
                'h' => 'One-click install',
                'body' => [
                    'Download the latest release zip, upload and unzip it to your web root, then open your domain in a browser. The guided installer checks requirements, writes your config, runs migrations, and creates your admin account.',
                    ['code' => "# unzip on the server, then visit:\nhttps://your-domain.com/install", 'lang' => 'bash'],
                    'The installer also flags production recommendations (OPcache, memory overcommit) so you start on solid footing.',
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
];
