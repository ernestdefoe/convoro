<?php

/*
 * Trust pages content — changelog + roadmap. Plain data so it's easy to edit
 * without touching code. Rendered on /changelog and /roadmap.
 */

return [

    // Most recent first. `tag` is the version, `date` is YYYY-MM-DD.
    'changelog' => [
        [
            'tag' => '1.2.1',
            'date' => '2026-06-12',
            'title' => 'Pin important topics',
            'items' => [
                ['type' => 'added', 'text' => 'Moderators can now pin a topic so it stays at the top of the discussion list. Pin or unpin from the topic’s “⋯” menu; pinned topics show a “Pinned” badge and sort above the rest. Control who can pin under Members → Groups (the “Pin / unpin topics” permission).'],
            ],
        ],
        [
            'tag' => '1.2.0',
            'date' => '2026-06-12',
            'title' => 'Polls — plus smarter bot & polished uploads',
            'items' => [
                ['type' => 'new', 'text' => 'Polls! Attach a poll to any new topic — a question with up to 10 options, single or multiple choice, and an optional auto-close date. Members vote with a tap and see beautiful, animated results bars with live percentages and counts, can change their vote, and view results before voting.'],
                ['type' => 'improved', 'text' => 'The AI assistant no longer answers every new topic by default — under Admin → AI you can give it trigger keywords so it only replies to topics that mention them (e.g. “error”, “how do I”, “billing”).'],
                ['type' => 'improved', 'text' => 'Every upload button across the app — logo, favicon, app icon, avatar, cover and topic images — is now a single, consistent, on-brand button with an uploading state, instead of the plain browser “Choose file” control.'],
            ],
        ],
        [
            'tag' => '1.1.2',
            'date' => '2026-06-12',
            'title' => 'The LIVE badge is now actually live',
            'items' => [
                ['type' => 'improved', 'text' => 'A discussion now shows a “LIVE” badge automatically when a few members are reading it at the same time — driven by who’s actually on the page right now. The badge appears and clears on its own as a thread heats up and quiets down, and the forum refreshes it every few seconds without a reload.'],
            ],
        ],
        [
            'tag' => '1.1.1',
            'date' => '2026-06-12',
            'title' => 'Advanced search — filters & sorting',
            'items' => [
                ['type' => 'added', 'text' => 'Search now has filters and sorting: narrow results by category, author and time window (past week / month / year), and sort by relevance, newest or most replies. You can even browse with filters alone — no search term needed.'],
            ],
        ],
        [
            'tag' => '1.1.0',
            'date' => '2026-06-12',
            'title' => 'Single sign-on (OpenID Connect)',
            'items' => [
                ['type' => 'new', 'text' => 'Built-in single sign-on: connect any OpenID Connect identity provider — Okta, Auth0, Azure AD, Google Workspace, Authentik, Keycloak and more — under Admin → Single sign-on. Paste your issuer URL plus a client ID and secret, add the shown redirect URI to your provider, and members get a “Sign in with …” button. Accounts can be created automatically on first login, optionally restricted to a single email domain.'],
            ],
        ],
        [
            'tag' => '1.0.9',
            'date' => '2026-06-12',
            'title' => 'Onboarding & polish fixes',
            'items' => [
                ['type' => 'fixed', 'text' => 'The “Brand your community” and “Set your app icon” getting-started steps now tick off correctly once you’ve actually done them — they were checking the wrong signal before.'],
                ['type' => 'fixed', 'text' => 'The “Send invites” button on the Invite friends page showed a literal “{n}” instead of the number — placeholders now substitute everywhere they appear.'],
                ['type' => 'improved', 'text' => 'Footer links added by add-ons (like RSS and the privacy choices link) now sit in a tidy centered footer instead of crowding the live-theme-editor button in the corner.'],
            ],
        ],
        [
            'tag' => '1.0.8',
            'date' => '2026-06-12',
            'title' => 'New-post indicator on discussions',
            'items' => [
                ['type' => 'added', 'text' => 'Discussions with replies you haven’t seen yet now show a “New” badge in the top-right corner of their card (in both feed and grid views, and in search). Opening a discussion clears its badge, and a fresh reply brings it back.'],
            ],
        ],
        [
            'tag' => '1.0.7',
            'date' => '2026-06-12',
            'title' => 'Invite your friends + dark-mode fix',
            'items' => [
                ['type' => 'added', 'text' => 'Members can now invite friends: a new “Invite friends” page (in the account menu) gives everyone a personal referral link and lets them send email invitations. Sign-ups through a member’s link are credited back to them, so they can see who they brought in. Admins can turn member invites on or off under Admin → Invites.'],
                ['type' => 'fixed', 'text' => 'Code blocks in the documentation (and the terminal preview on the homepage) were showing as a white box with invisible text in dark mode — they’re now properly dark with readable text in both themes.'],
            ],
        ],
        [
            'tag' => '1.0.6',
            'date' => '2026-06-12',
            'title' => 'Brand it as yours + easier install',
            'items' => [
                ['type' => 'added', 'text' => 'Upload your own favicon right in Admin → Settings, next to your logo — so the browser-tab icon is your brand, not Convoro’s. (Your logo already replaces the header mark.)'],
                ['type' => 'added', 'text' => 'The install guide now has a real download button for a prebuilt zip (nothing to compile — ideal for shared hosting) plus copy-paste Composer commands for those who prefer the command line.'],
                ['type' => 'improved', 'text' => 'The Flarum importer is now covered by an automated end-to-end mapping test, alongside XenForo, phpBB, Discourse and vBulletin.'],
            ],
        ],
        [
            'tag' => '1.0.5',
            'date' => '2026-06-12',
            'title' => 'Guided setup for new admins',
            'items' => [
                ['type' => 'added', 'text' => 'A friendly setup wizard now greets new admins on the dashboard and walks you step-by-step through getting your community ready — branding, categories, email, your app icon and first members. Each step links straight to where you do it, and you can dismiss it and pick up later from the “Guided setup” button.'],
            ],
        ],
        [
            'tag' => '1.0.4',
            'date' => '2026-06-12',
            'title' => 'A fuller documentation site',
            'items' => [
                ['type' => 'added', 'text' => 'Seven new documentation guides at /docs: migrating from another forum, members & permissions, moderation & safety, privacy & GDPR, languages & translation, selling extensions, and updates & backups — covering the whole platform end to end.'],
            ],
        ],
        [
            'tag' => '1.0.3',
            'date' => '2026-06-12',
            'title' => 'Importers, verified end-to-end',
            'items' => [
                ['type' => 'improved', 'text' => 'The XenForo, phpBB, Discourse and vBulletin importers are now covered by automated end-to-end tests that run each one against a sample database of that platform — verifying members, categories, topics, posts, first-post detection, reply counts and formatting conversion all map across correctly.'],
                ['type' => 'fixed', 'text' => 'Tightened importer handling of deleted/hidden threads, private messages, bot accounts and table prefixes uncovered while building those tests.'],
            ],
        ],
        [
            'tag' => '1.0.2',
            'date' => '2026-06-12',
            'title' => 'GDPR: anonymize or fully delete a member',
            'items' => [
                ['type' => 'added', 'text' => 'New “Data & privacy (GDPR)” tools on each member (Admin → Members → edit a member): Anonymize erases their personal data — name, email, avatar, bio and stored IP addresses — while keeping their posts and topics readable as “Deleted user”. This is the recommended way to honour a deletion request without breaking discussions.'],
                ['type' => 'added', 'text' => 'Delete account & content fully removes the member and everything they authored — their topics (and the replies inside them), posts, reactions and messages.'],
                ['type' => 'fixed', 'text' => 'The old member “Delete” actually kept their posts (orphaned) despite saying otherwise — deletion behaviour is now explicit and correct, with a clear choice between anonymize and full delete.'],
            ],
        ],
        [
            'tag' => '1.0.1',
            'date' => '2026-06-12',
            'title' => 'Built-on showcase + a forum comparison page',
            'items' => [
                ['type' => 'added', 'text' => 'The homepage now shows the modern open-source stack Convoro is built on — Laravel, Vue, Inertia, Tailwind, TypeScript and Vite — with their logos.'],
                ['type' => 'added', 'text' => 'A new Compare page (linked from the homepage and nav) lays out, feature by feature, how Convoro stacks up against Flarum, Discourse, XenForo, phpBB and Invision Community.'],
            ],
        ],
        [
            'tag' => '1.0.0',
            'date' => '2026-06-12',
            'title' => 'Convoro 1.0 — the first stable release',
            'items' => [
                ['type' => 'new', 'text' => 'Convoro is 1.0. After a fast run of releases, the platform is feature-complete and stable: a realtime, AI-native community with a WYSIWYG composer, live theme editor, built-in marketplace, full multi-language support, moderation and GDPR tooling, PWA + push, and a self-updater — ready to run a real community.'],
                ['type' => 'new', 'text' => 'Rich media embeds — paste a YouTube, Vimeo, Spotify, X, Facebook or Instagram link on its own line in a post and it renders inline automatically, no shortcodes needed.'],
                ['type' => 'new', 'text' => 'RSS feeds — a new RSS add-on publishes site-wide, per-category and per-topic feeds with auto-discovery links, so members can follow your community in any reader.'],
                ['type' => 'new', 'text' => 'Move from almost any forum — the Import wizard now migrates from XenForo, phpBB, Discourse and vBulletin in addition to Flarum, bringing across members, categories, topics, posts and formatting (older forum software is marked beta — back up first).'],
                ['type' => 'improved', 'text' => 'Email digests now include a one-click unsubscribe link that turns digests off without signing in, meeting bulk-email requirements.'],
                ['type' => 'improved', 'text' => 'Worldwide privacy compliance — the cookie-consent add-on now honors Global Privacy Control / Do Not Track signals, adds a CCPA “Do Not Sell or Share” notice, keeps a persistent “Privacy choices” link, and remembers prior choices.'],
            ],
        ],
        [
            'tag' => '0.41.0',
            'date' => '2026-06-12',
            'title' => 'Member Badges',
            'items' => [
                ['type' => 'added', 'text' => 'New Member Badges add-on — members automatically earn badges (Founding Member, First Post, milestones, reactions received and more) as they take part, shown right on their profile. Install it from the extensions directory.'],
                ['type' => 'improved', 'text' => 'Profiles can now display add-on content, and the notifications system supports badge awards.'],
            ],
        ],
        [
            'tag' => '0.40.0',
            'date' => '2026-06-12',
            'title' => 'Bigger, bolder avatars',
            'items' => [
                ['type' => 'improved', 'text' => 'Member avatars are now a bit larger across topic cards, posts, profiles, member lists, and the sidebar widgets — for a more prominent, modern look.'],
            ],
        ],
        [
            'tag' => '0.39.0',
            'date' => '2026-06-12',
            'title' => 'Avatar alignment, category colors save, widgets refresh cleanly',
            'items' => [
                ['type' => 'fixed', 'text' => 'Member avatars now line up consistently — non-staff members’ avatars were getting nudged out of alignment next to their names.'],
                ['type' => 'fixed', 'text' => 'Changing a category’s color now saves correctly even when the category has a Font Awesome icon (the icon field was rejecting longer icon names).'],
                ['type' => 'improved', 'text' => 'Sidebar widget updates now take effect immediately instead of being held back by a stale browser cache.'],
            ],
        ],
        [
            'tag' => '0.38.0',
            'date' => '2026-06-12',
            'title' => 'Theme carries across the site, auto-refresh on update, richer Online & Trending',
            'items' => [
                ['type' => 'fixed', 'text' => 'Your light/dark choice now carries between the homepage and the forum — it’s saved to a site-wide cookie instead of per-page storage.'],
                ['type' => 'added', 'text' => 'After an update finishes installing, the admin page now refreshes itself automatically so you can see the new version without manually reloading.'],
                ['type' => 'improved', 'text' => 'The Online now widget shows who’s actually online (avatars), not just a count — the way it used to.'],
                ['type' => 'improved', 'text' => 'The Trending widget is more reliable: it falls back to the most-viewed topics when nothing has replies yet.'],
                ['type' => 'improved', 'text' => 'The bundled sidebar widgets now have proper cover art in the extensions directory, like every other add-on.'],
            ],
        ],
        [
            'tag' => '0.37.0',
            'date' => '2026-06-11',
            'title' => 'Sidebar widgets are now drag-and-drop extensions',
            'items' => [
                ['type' => 'added', 'text' => 'Every sidebar widget — Community stats, Online now, Newest members, Top posters, Categories, Trending and a new About / Custom HTML card — is now a first-party extension you can drag to reorder and toggle on or off in the live theme editor.'],
                ['type' => 'added', 'text' => 'A “Trending” widget highlights your most active topics, and an “About / Custom HTML” widget lets you add a free-form welcome card to the rail.'],
                ['type' => 'improved', 'text' => 'The live theme editor’s Widgets tab now shows one unified, draggable list with on/off switches, and previews changes instantly. Newly-installed widget add-ons appear in the list automatically.'],
                ['type' => 'improved', 'text' => 'Your existing sidebar layout (including any custom text card) is carried over automatically on upgrade.'],
                ['type' => 'fixed', 'text' => 'Display names can no longer show as a blank “diamond”: a name must contain a real letter or number, invisible/control characters are stripped on save, and any older broken name safely falls back to “Member #”.'],
                ['type' => 'added', 'text' => 'Staff badges: admins and staff-group members now show a small colored badge beneath their avatar that matches their group’s color.'],
                ['type' => 'added', 'text' => 'Editable Admin & Moderator groups — pick their color under Members → Groups (the Admin color drives the staff badge). These system groups can be recolored or renamed but not deleted.'],
                ['type' => 'improved', 'text' => 'Category colors now reach the left sidebar — each category’s icon and name are tinted with its chosen color, matching the topic cards.'],
                ['type' => 'added', 'text' => 'A “Clear queue” button under Admin → Dashboard → Maintenance drops stuck pending and failed jobs and restarts the workers, so a hung-up queue can be cleared and retried.'],
            ],
        ],
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
            'Convoro 1.0 — first stable release',
            'Live theme editor + widgets', 'Built-in marketplace & GitHub registry',
            'AI security review', 'Realtime threads, DMs & notifications',
            'PWA + web push', 'Moderation & GDPR tools', 'One-click installer',
            'Multi-language UI (45+ languages, AI-assisted)',
            'Rich media embeds (YouTube, Vimeo, Spotify, X, Facebook, Instagram)',
            'Importers — Flarum, XenForo, phpBB, Discourse & vBulletin',
            'Email digests + one-click unsubscribe & notification preferences',
            'Earnable badges & richer member profiles', 'RSS feeds',
            'Automated importer mapping tests (XenForo · phpBB · Discourse · vBulletin)',
            'Documentation site — install, migrate, moderate, theme, extend, deploy',
            'Guided setup wizard for new admins',
            'Member invites & referrals', 'Unread / new-post indicators',
            'Single sign-on (OpenID Connect)', 'Advanced search filters',
            'Polls with live results',
        ],
        'now' => [
            'Importer polish from real-world migrations',
            'Advanced analytics & search',
        ],
        'next' => [
            'Scheduled posts & drafts',
            'More importers (MyBB, SMF, Invision)',
        ],
        'later' => [
            'Native mobile wrapper',
            'Invision Community importer',
            'Federation / cross-community follow',
        ],
    ],
];
