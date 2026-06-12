<?php

/*
 * Trust pages content — changelog + roadmap. Plain data so it's easy to edit
 * without touching code. Rendered on /changelog and /roadmap.
 */

return [

    // Most recent first. `tag` is the version, `date` is YYYY-MM-DD.
    'changelog' => [
        [
            'tag' => '0.36.0',
            'date' => '2026-06-11',
            'title' => 'Mentions, DM translation, Horizon & translation fixes',
            'items' => [
                ['type' => 'fixed', 'text' => '@mentions now render as styled links to the person’s profile (e.g. “@Riley West”) instead of showing raw text like “@riley.west”.'],
                ['type' => 'added', 'text' => 'Direct messages now auto-translate into your language, just like posts — with a “Show original” toggle. Turn on auto-translate in your preferences.'],
                ['type' => 'fixed', 'text' => 'AI language translation no longer gets stuck near 100% or hangs — it runs in short, resumable batches and retries stubborn strings individually.'],
                ['type' => 'fixed', 'text' => 'The Horizon queue dashboard now follows the forum’s light/dark theme and no longer has its own mismatched theme switcher.'],
                ['type' => 'improved', 'text' => 'Mobile: a floating “Start a topic” button now appears on phones and tablets, so you don’t have to hunt for it.'],
                ['type' => 'added', 'text' => 'Write a post with AI — describe what you want to say and it drafts the full post for you (the “Write from a brief…” option in the composer’s AI menu).'],
                ['type' => 'fixed', 'text' => 'The “Suggest a title” and “Suggest tags” buttons now give feedback instead of doing nothing when there’s not enough to work from yet.'],
                ['type' => 'improved', 'text' => 'The live theme-editor button is now hidden on mobile, where it isn’t useful.'],
            ],
        ],
        [
            'tag' => '0.35.0',
            'date' => '2026-06-11',
            'title' => 'Favicon refresh fix',
            'items' => [
                ['type' => 'fixed', 'text' => 'The favicon and app icons now refresh immediately after you change them — the icon links are cache-busted, so browsers no longer keep showing the old icon.'],
            ],
        ],
        [
            'tag' => '0.34.0',
            'date' => '2026-06-11',
            'title' => 'New mark in the header',
            'items' => [
                ['type' => 'improved', 'text' => 'The forum header now shows the new Convoro speech-bubble “typing” mark, matching the favicon and app icons.'],
            ],
        ],
        [
            'tag' => '0.33.0',
            'date' => '2026-06-11',
            'title' => 'New Convoro brand',
            'items' => [
                ['type' => 'new', 'text' => 'A refreshed Convoro brand — a speech-bubble “typing” mark across the favicon, PWA / home-screen icons and apple-touch icon, plus horizontal logos and an Open Graph share card.'],
            ],
        ],
        [
            'tag' => '0.32.0',
            'date' => '2026-06-11',
            'title' => 'Polished quotes & fixes',
            'items' => [
                ['type' => 'improved', 'text' => 'Quoted text in posts now renders as a clean, on-brand card — accent edge, subtle background, and a clear “…wrote:” attribution — instead of a plain indent.'],
                ['type' => 'fixed', 'text' => 'Fixed a 500 error on the Admin → AI page for sites that previously had the AI Helper add-on installed.'],
                ['type' => 'fixed', 'text' => 'Browser-push notifications to stale devices no longer show up as failed background jobs.'],
            ],
        ],
        [
            'tag' => '0.31.0',
            'date' => '2026-06-11',
            'title' => 'Tidier post actions + reply notifications',
            'items' => [
                ['type' => 'improved', 'text' => 'Each post’s Edit, Move, Delete and Report actions are now tucked into a “⋯” menu, with Reply and Share kept up front on the right.'],
                ['type' => 'new', 'text' => 'Hitting Reply on a post now mentions its author, so they get a notification that you replied to them.'],
            ],
        ],
        [
            'tag' => '0.30.0',
            'date' => '2026-06-11',
            'title' => 'A sharper look for listings',
            'items' => [
                ['type' => 'improved', 'text' => 'Auto-generated extension & theme covers have a refreshed, on-brand look — a dark “developer” style with the install command, a bold title, your tagline, and a glowing monogram. Run “php artisan convoro:covers” to refresh existing listings.'],
                ['type' => 'improved', 'text' => 'Quote now works by selecting text in a post — a “Quote” button pops up by your selection and drops it into the reply box (plus the per-post Reply button).'],
            ],
        ],
        [
            'tag' => '0.29.0',
            'date' => '2026-06-11',
            'title' => 'Reply & quote',
            'items' => [
                ['type' => 'new', 'text' => 'Every post now has Reply and Quote actions. “Quote” drops the post (attributed and linked back) into the composer and jumps you to it; “Reply” takes you straight to the composer.'],
            ],
        ],
        [
            'tag' => '0.28.0',
            'date' => '2026-06-11',
            'title' => 'AI usage & budget in core',
            'items' => [
                ['type' => 'new', 'text' => 'Admin → AI now shows this month’s AI spend, calls and tokens broken down by feature (Ask, translation, moderation, writing assistant…), with a monthly budget cap that pauses AI when reached. Set your model’s token prices for accurate estimates.'],
                ['type' => 'improved', 'text' => 'The separate “AI Helper” add-on is retired — everything it did is now built into core, including this usage dashboard.'],
                ['type' => 'fixed', 'text' => 'Browser-push notifications no longer pile up as failed jobs when a device subscription is stale.'],
            ],
        ],
        [
            'tag' => '0.27.0',
            'date' => '2026-06-11',
            'title' => 'Localized end to end',
            'items' => [
                ['type' => 'new', 'text' => 'Localization now covers the whole product, not just the interface: notification & digest emails arrive in each member’s own language, and every server-side message — confirmations, errors, the moderation queue, the status page and the install wizard — is translatable from the same Admin → Languages dictionaries.'],
                ['type' => 'improved', 'text' => 'Emails are rendered in the recipient’s language (with the correct text direction), and the catalog now includes server-side strings so one-click AI translation fills them too.'],
            ],
        ],
        [
            'tag' => '0.26.0',
            'date' => '2026-06-11',
            'title' => 'AI moderation copilot',
            'items' => [
                ['type' => 'new', 'text' => 'Every new post is screened by AI for spam, toxicity and exposed personal data. Risky posts are flagged in the moderation queue with a risk score and labels; very high-risk posts are automatically held (hidden from the public) until a moderator approves them.'],
                ['type' => 'new', 'text' => 'One-click “Approve & restore” in the moderation queue. Turn the copilot on in Admin → AI.'],
            ],
        ],
        [
            'tag' => '0.25.0',
            'date' => '2026-06-11',
            'title' => 'AI writing assistant',
            'items' => [
                ['type' => 'new', 'text' => 'An AI button in the composer: improve writing, fix spelling & grammar, make it shorter or longer, simplify, or switch to a professional or friendly tone — on your whole draft or just the selected text.'],
                ['type' => 'new', 'text' => 'When starting a topic, get one-click AI title suggestions and auto-suggested tags from your draft.'],
            ],
        ],
        [
            'tag' => '0.24.0',
            'date' => '2026-06-11',
            'title' => 'Read every post in your language',
            'items' => [
                ['type' => 'new', 'text' => 'Per-reader post translation: a “Translate” link on any post renders it in your language, powered by AI — so a member can write in Arabic and you can read it in English (and reply in English, which they read in Arabic). Turn on “Auto-translate” in the header to have every post arrive in your language automatically.'],
                ['type' => 'new', 'text' => 'Many more languages out of the box — 45+ of the world’s most-spoken languages are now selectable and AI-translatable, including right-to-left languages.'],
            ],
        ],
        [
            'tag' => '0.23.0',
            'date' => '2026-06-11',
            'title' => 'Full translation support — every screen, any language',
            'items' => [
                ['type' => 'new', 'text' => 'The entire interface is now translatable — over a thousand strings across the forum, profiles, messages, store, admin and marketing pages, not just the home page.'],
                ['type' => 'new', 'text' => 'New Admin → Languages: see translation coverage per language, add any language, and fill it instantly with one-click AI translation (powered by your connected AI provider). Edit any string by hand, and set the site’s default language.'],
                ['type' => 'improved', 'text' => 'Right-to-left languages (e.g. Arabic) flip the whole layout automatically.'],
            ],
        ],
        [
            'tag' => '0.22.0',
            'date' => '2026-06-11',
            'title' => 'Chinese — Simplified & Traditional',
            'items' => [
                ['type' => 'new', 'text' => 'Two new languages out of the box: Simplified Chinese (简体中文) and Traditional Chinese (繁體中文) — bringing Convoro to eight built-in interface languages.'],
            ],
        ],
        [
            'tag' => '0.21.0',
            'date' => '2026-06-11',
            'title' => 'More languages, more translated',
            'items' => [
                ['type' => 'new', 'text' => 'Four new languages out of the box — French, German, Portuguese and Arabic (with full right-to-left layout) — joining Spanish and English.'],
                ['type' => 'improved', 'text' => 'More of the interface is now translated: the sign-in/join dialog, the community page (sorting, categories, empty states), and the home Ask bar.'],
            ],
        ],
        [
            'tag' => '0.20.0',
            'date' => '2026-06-11',
            'title' => 'Speak your members’ language',
            'items' => [
                ['type' => 'new', 'text' => 'Multi-language interface: members can switch the UI language from a picker in the header, and their choice sticks. Ships with Spanish, and any community can add more languages by dropping a translation file — more of the interface becomes translatable in upcoming updates.'],
            ],
        ],
        [
            'tag' => '0.19.0',
            'date' => '2026-06-11',
            'title' => 'Move replies between topics',
            'items' => [
                ['type' => 'new', 'text' => 'Moderators can now move a reply to a different topic right from the post — a “Move” action opens a topic search so you can re-home off-topic replies in seconds.'],
            ],
        ],
        [
            'tag' => '0.18.0',
            'date' => '2026-06-11',
            'title' => 'A faster first “wow”',
            'items' => [
                ['type' => 'improved', 'text' => 'The Ask Convoro bar now greets visitors with one-click starter questions drawn from your community — so a brand-new arrival gets a useful, cited answer the moment they land, before they’ve even signed up.'],
            ],
        ],
        [
            'tag' => '0.17.0',
            'date' => '2026-06-11',
            'title' => 'Cross-references between topics',
            'items' => [
                ['type' => 'new', 'text' => 'Link to another discussion in a post and it automatically shows a “Mentioned in” back-link on the topic you referenced — keeping related conversations connected.'],
            ],
        ],
        [
            'tag' => '0.16.0',
            'date' => '2026-06-11',
            'title' => 'Invites & invite-only signups',
            'items' => [
                ['type' => 'new', 'text' => 'Create shareable invite links and run an invite-only community: flip on “invite-only registration” in Admin → Invites, generate codes (with optional max-uses and expiry), and share the link. New members enter the code to join.'],
            ],
        ],
        [
            'tag' => '0.15.0',
            'date' => '2026-06-11',
            'title' => 'A unified admin Dashboard',
            'items' => [
                ['type' => 'improved', 'text' => 'The admin Dashboard now holds everything in one place: software updates, a live server-status panel, maintenance tools, and environment info — the separate System tab is gone.'],
                ['type' => 'new', 'text' => 'Server-status panel on the Dashboard — at-a-glance health for the website, database, cache, background jobs, email and disk.'],
            ],
        ],
        [
            'tag' => '0.14.0',
            'date' => '2026-06-11',
            'title' => 'Edit a topic’s category & tags',
            'items' => [
                ['type' => 'improved', 'text' => 'Editing a topic now lets you change its category and tags too — not just the title and body. Pick them right in the edit dialog.'],
            ],
        ],
        [
            'tag' => '0.13.0',
            'date' => '2026-06-11',
            'title' => 'Catch me up',
            'items' => [
                ['type' => 'new', 'text' => 'On long threads, a “Catch me up” button gives you an instant AI summary — a short overview plus the key points and decisions — so you can join the conversation without reading every reply. Summaries are cached and refresh as the thread grows.'],
            ],
        ],
        [
            'tag' => '0.12.0',
            'date' => '2026-06-11',
            'title' => 'Instant email notifications',
            'items' => [
                ['type' => 'new', 'text' => 'Get an email the moment someone replies to you, mentions you, messages you, or posts on your profile — sent in the background, with a per-member on/off toggle in Settings. Together with digests and password resets, Convoro’s email is now complete.'],
            ],
        ],
        [
            'tag' => '0.11.0',
            'date' => '2026-06-11',
            'title' => 'Search that understands you',
            'items' => [
                ['type' => 'new', 'text' => 'Full community search — the header search box and a dedicated results page now work. With Voyage embeddings enabled it’s semantic (finds threads by meaning, not just keywords); otherwise it uses fast full-text search.'],
            ],
        ],
        [
            'tag' => '0.10.0',
            'date' => '2026-06-11',
            'title' => 'Asked before?',
            'items' => [
                ['type' => 'new', 'text' => 'Smart compose: as you type a new topic’s title, Convoro surfaces existing discussions that may already answer it — so members find answers faster and the forum stays tidy. Uses semantic search when enabled, full-text otherwise.'],
            ],
        ],
        [
            'tag' => '0.9.0',
            'date' => '2026-06-11',
            'title' => 'No question goes unanswered',
            'items' => [
                ['type' => 'new', 'text' => 'Optional AI auto-answers: when a member starts a topic and no one replies, your assistant posts a citation-backed draft answer the community can confirm or improve. Keeps a young community feeling alive — turn it on in Admin → AI.'],
            ],
        ],
        [
            'tag' => '0.8.0',
            'date' => '2026-06-11',
            'title' => 'Semantic Ask Convoro',
            'items' => [
                ['type' => 'improved', 'text' => 'Ask Convoro now uses Voyage AI semantic search: it understands the meaning of a question and finds the right threads even when the wording differs — far better answers. New posts are indexed automatically; rebuild anytime from Admin → AI.'],
                ['type' => 'new', 'text' => 'Convoro is now positioned as an AI-native platform end to end — refreshed home page, docs, and an “Ask Convoro (AI)” guide.'],
            ],
        ],
        [
            'tag' => '0.7.0',
            'date' => '2026-06-11',
            'title' => 'Ask Convoro — your community, answerable',
            'items' => [
                ['type' => 'new', 'text' => 'Ask Convoro: an AI answer bar on the community home. Members ask a question in plain language and get an instant answer synthesized from your forum’s own threads — with citations to the real discussions. Every empty forum is useful on day one, and your knowledge becomes instantly searchable.'],
                ['type' => 'new', 'text' => 'A core AI settings page (Admin → AI): connect any Anthropic or OpenAI-compatible model once to power Ask Convoro and future AI features.'],
            ],
        ],
        [
            'tag' => '0.6.0',
            'date' => '2026-06-11',
            'title' => 'Deeper Flarum import',
            'items' => [
                ['type' => 'improved', 'text' => 'The Flarum importer now brings across far more: secondary tags become Convoro tags, likes become reactions, and avatars plus embedded images are carried over (set your old Flarum URL).'],
            ],
        ],
        [
            'tag' => '0.5.0',
            'date' => '2026-06-11',
            'title' => 'Import from Flarum',
            'items' => [
                ['type' => 'new', 'text' => 'New Import wizard (Admin → Import): migrate your whole community from Flarum — members, categories, topics and posts — by connecting your old database. Members keep their passwords, and post formatting is converted automatically.'],
                ['type' => 'fixed', 'text' => 'The self-updater now recovers from stray file permissions instead of aborting, so updates apply reliably.'],
            ],
        ],
        [
            'tag' => '0.4.0',
            'date' => '2026-06-11',
            'title' => 'New brand & clearer updates',
            'items' => [
                ['type' => 'new', 'text' => 'A refreshed Convoro brand — a real logo plus matching favicon, PWA and home-screen app icons.'],
                ['type' => 'improved', 'text' => 'The admin update panel now shows a “What’s new” changelog for the available version before you install it.'],
            ],
        ],
        [
            'tag' => '0.3.0',
            'date' => '2026-06-11',
            'title' => 'Post numbers & permalinks',
            'items' => [
                ['type' => 'new', 'text' => 'Every post now shows its number in the topic (#1, #2, …) — click it to copy a permalink straight to that post.'],
            ],
        ],
        [
            'tag' => '0.2.0',
            'date' => '2026-06-11',
            'title' => 'Mobile menu & post sharing',
            'items' => [
                ['type' => 'new', 'text' => 'Mobile navigation menu with a one-tap “Start a discussion” button.'],
                ['type' => 'new', 'text' => 'Share any post via the native share sheet or a copied permalink.'],
                ['type' => 'new', 'text' => 'New Social Login extension (in the Marketplace): sign in with GitHub, Google, Facebook, or X — dependency-free, configured from its own settings page.'],
            ],
        ],
        [
            'tag' => '0.1.0',
            'date' => '2026-06-11',
            'title' => 'Marketplace, AI security review & live theming',
            'items' => [
                ['type' => 'new', 'text' => 'AI security review — every directory extension is audited from its source and gets an unbiased safety verdict.'],
                ['type' => 'new', 'text' => 'GitHub registry: link a public repo, auto-refresh on new releases, pin to a tag (Packagist-style).'],
                ['type' => 'new', 'text' => 'Front-facing store console: Stripe Connect, listings, and publishing — no admin section needed.'],
                ['type' => 'new', 'text' => 'Live theme editor with autosave, drag-to-reorder widgets, and an accessibility auditor.'],
                ['type' => 'improved', 'text' => 'Shared-host performance: compiled extension manifest cache + authoritative autoloader.'],
                ['type' => 'fixed', 'text' => 'Extension cover images now render on every domain (host-relative paths).'],
            ],
        ],
        [
            'tag' => '0.0.9',
            'date' => '2026-06-09',
            'title' => 'Moderation, GDPR & one-click install',
            'items' => [
                ['type' => 'new', 'text' => 'Moderation suite: move posts, spam controls, IP info & bans, flagging.'],
                ['type' => 'new', 'text' => 'GDPR tools and a guided in-browser installer for shared hosting.'],
                ['type' => 'new', 'text' => 'PWA install + web push, profiles, DMs, and scheduled email digests.'],
            ],
        ],
        [
            'tag' => '0.0.8',
            'date' => '2026-06-07',
            'title' => 'Realtime, WYSIWYG & the extension system',
            'items' => [
                ['type' => 'new', 'text' => 'Realtime threads (Reverb) with presence + typing and a polling fallback.'],
                ['type' => 'new', 'text' => 'TipTap WYSIWYG composer with drag/drop images auto-converted to WebP.'],
                ['type' => 'new', 'text' => 'Extension manifest format + installer — drop-in add-ons, no Composer.'],
            ],
        ],
    ],

    // Roadmap buckets. Move items between buckets as they progress.
    'roadmap' => [
        'shipped' => [
            'Live theme editor + widgets', 'Built-in marketplace & GitHub registry',
            'AI security review', 'Realtime threads, DMs & notifications',
            'PWA + web push', 'Moderation & GDPR tools', 'One-click installer',
        ],
        'now' => [
            'Public trust pages (changelog, roadmap, status)',
            'Product-wide UI polish (skeletons, empty states, ⌘K palette)',
        ],
        'next' => [
            'Importers — migrate your community from Flarum, XenForo, Invision Community, phpBB & Discourse',
            'Email digests v2 + notification preferences',
            'Full documentation site',
            'Onboarding wizard for new admins',
        ],
        'later' => [
            'Native mobile wrapper', 'Single sign-on (OAuth/SAML)',
            'Advanced analytics & search', 'Multi-language UI',
        ],
    ],
];
