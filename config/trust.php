<?php

/*
 * Trust pages content — changelog + roadmap. Plain data so it's easy to edit
 * without touching code. Rendered on /changelog and /roadmap.
 */

return [

    // Most recent first. `tag` is the version, `date` is YYYY-MM-DD.
    'changelog' => [
        [
            'tag' => '1.43.6',
            'date' => '2026-07-05',
            'title' => 'Email verification actually asks',
            'items' => [
                ['type' => 'fixed', 'text' => 'New members now receive a verification email at signup. The profile checkmark has always meant "email verified," but the verification mail was never being sent — so most members had no way to earn it. Existing members can verify any time from Settings → Profile ("resend verification email"). Nothing is locked behind verification; the checkmark is a trust signal.'],
                ['type' => 'improved', 'text' => 'Updated the Inertia frontend library (2.3.27) and Laravel framework (13.18.1).'],
            ],
        ],
        [
            'tag' => '1.43.5',
            'date' => '2026-07-05',
            'title' => 'Sandbox tags',
            'items' => [
                ['type' => 'new', 'text' => 'Tags can be marked as a Sandbox in Admin → Tags. Sandbox tags are practice areas — perfect for a "test posting here" space where members can play with the editor, images and formatting. Topics in them work exactly like normal topics, but they never appear in Trending and don\'t count toward community totals or members\' topic/post counts.'],
            ],
        ],
        [
            'tag' => '1.43.4',
            'date' => '2026-07-03',
            'title' => 'Readable notifications panel + tidy editor toolbar',
            'items' => [
                ['type' => 'fixed', 'text' => 'The notifications panel is solid again — the frosted-glass styling made it see-through (especially where the blur isn\'t supported), so page content showed through the list.'],
                ['type' => 'fixed', 'text' => 'The editor\'s AI button no longer stacks its icon and label on two lines and poke out of the toolbar row.'],
            ],
        ],
        [
            'tag' => '1.43.3',
            'date' => '2026-07-03',
            'title' => 'Visible checkmarks',
            'items' => [
                ['type' => 'fixed', 'text' => 'Checkboxes across the admin (and a few member forms) toggled correctly but didn\'t draw their checkmark on some themes, so settings looked impossible to enable. The stray background utility that suppressed the checked state is gone — checkmarks render everywhere again.'],
            ],
        ],
        [
            'tag' => '1.43.2',
            'date' => '2026-07-03',
            'title' => 'Your brand on the login modal',
            'items' => [
                ['type' => 'fixed', 'text' => 'The log in / sign up modal and guest pages (password reset, verification) now show your community\'s own logo and name instead of the Convoro mark. Convoro branding only appears until you upload a logo in Admin → Branding.'],
            ],
        ],
        [
            'tag' => '1.43.1',
            'date' => '2026-07-03',
            'title' => 'Approving held posts from the reports queue',
            'items' => [
                ['type' => 'fixed', 'text' => 'The moderation queue now shows "Approve & restore" for every held post — including posts held by the spam guard or first-post approval, not just AI-held ones. Previously, resolving such a report closed it without publishing the post, so a post could look approved but stay pending.'],
            ],
        ],
        [
            'tag' => '1.43.0',
            'date' => '2026-07-03',
            'title' => 'Big-board performance',
            'items' => [
                ['type' => 'improved', 'text' => 'Long discussions now load a window of posts around where you are instead of the entire thread, and stream the rest in as you scroll or jump — a 10,000-post discussion opens as fast as a 10-post one. Every post has a permanent number you can link to.'],
                ['type' => 'improved', 'text' => 'Reply notifications are sent in the background and members with an unseen notification for a discussion aren\'t re-notified on every new reply — busy threads stay fast for the poster and calmer for everyone else.'],
                ['type' => 'improved', 'text' => 'The community home page caches its statistics and sidebar widgets, so it stays quick on forums with millions of posts.'],
                ['type' => 'improved', 'text' => 'Built-in database search is smarter on very large forums: it searches titles first and automatically avoids slow full-body scans, so a search can never tie up the server.'],
                ['type' => 'new', 'text' => 'Convoro can now run on Laravel Octane with FrankenPHP for memory-resident performance — an optional deployment mode for VPS and dedicated servers; standard PHP hosting keeps working exactly as before.'],
            ],
        ],
        [
            'tag' => '1.42.12',
            'date' => '2026-06-27',
            'title' => 'Readable theme editor',
            'items' => [
                ['type' => 'fixed', 'text' => 'The theme editor controls are no longer shown through the frosted-glass effect, so swatches, labels and inputs stay crisp and readable while you customize. The live preview still shows the real frosted styling.'],
            ],
        ],
        [
            'tag' => '1.42.11',
            'date' => '2026-06-27',
            'title' => 'Reliable updates + animated & SVG avatars',
            'items' => [
                ['type' => 'improved', 'text' => 'Software updates are far more reliable: they now run in the background without needing a queue worker (the most common cause of an update appearing to get "stuck"), the admin Updates page shows a pre-flight checklist of anything that would block an update, and you can apply an update straight from the terminal with "php artisan convoro:update" (or clear a stuck update flag with --reset).'],
                ['type' => 'new', 'text' => 'Avatars now support animated images — animated PNG (APNG), GIF and WebP keep their animation instead of being flattened to a still frame — and SVG avatars are accepted too. Uploaded SVGs are sanitized first, so they can\'t carry scripts.'],
            ],
        ],
        [
            'tag' => '1.42.10',
            'date' => '2026-06-26',
            'title' => 'Hero size controls + translatable sidebar widgets',
            'items' => [
                ['type' => 'improved', 'text' => 'The Hero Studio now has Height and Max width controls, so you can set the banner\'s exact size — per page and per category.'],
                ['type' => 'fixed', 'text' => 'The Trending, Upcoming events and Donate sidebar widgets now follow your forum\'s language instead of always showing English (their strings weren\'t reaching the translator). A couple of stuck/garbled translations were corrected too.'],
            ],
        ],
        [
            'tag' => '1.42.9',
            'date' => '2026-06-26',
            'title' => 'Translatable server-side messages (emails & access notices)',
            'items' => [
                ['type' => 'fixed', 'text' => 'The remaining English-only server-side text now translates too: the "access restricted" / "account suspended" notices, and the demo emails (subjects and body) that were still hardcoded.'],
            ],
        ],
        [
            'tag' => '1.42.8',
            'date' => '2026-06-26',
            'title' => 'Much more of the interface is now translatable',
            'items' => [
                ['type' => 'fixed', 'text' => 'A lot of the chrome that always stayed English regardless of your language — the admin sidebar, the marketing site nav/footer, the forum header (Skip to content, logo alt text, Messages/Menu), the update banner and various button/label text — now goes through the translation system.'],
                ['type' => 'fixed', 'text' => 'Rebuilt the UI translation catalog, which had drifted out of date: hundreds of strings were already wrapped for translation but missing from the catalog, so they silently fell back to English. They are now picked up and translated for every language.'],
            ],
        ],
        [
            'tag' => '1.42.7',
            'date' => '2026-06-26',
            'title' => 'Self-hosting polish: own-domain federation, default update checks & themed settings',
            'items' => [
                ['type' => 'fixed', 'text' => 'Self-hosted installs now federate under their own domain instead of community.convoro.co — the forum/federation domain defaults to your APP_URL host. (Convoro\'s own dual-domain setup is unaffected.)'],
                ['type' => 'improved', 'text' => 'Admin "Check for updates" now works out of the box — it points at Convoro\'s release feed by default, so you no longer have to set CONVORO_UPDATE_URL by hand.'],
                ['type' => 'fixed', 'text' => 'Editor: typing immediately after a link no longer continues in the link style.'],
                ['type' => 'fixed', 'text' => 'Profiles: the verified checkmark now only appears once an email is actually confirmed (an unverified profile previously showed a grey check with a contradicting "not verified" tooltip).'],
                ['type' => 'improved', 'text' => 'Profile/account settings now follow your theme — colours, surfaces and the accent are picked up from the live theme editor like the rest of the site.'],
            ],
        ],
        [
            'tag' => '1.42.6',
            'date' => '2026-06-26',
            'title' => 'Pro Font Awesome icons fixed for logged-in members',
            'items' => [
                ['type' => 'fixed', 'text' => 'If you use a Font Awesome Pro kit, some Pro-only icons (e.g. duotone or regular-weight icons) showed as empty boxes for signed-in members while working for logged-out visitors. The bundled free icon set is no longer compiled into the stylesheet — it was being re-applied after the kit on logged-in navigations and overriding it. Free icons are unaffected (the kit covers them for Pro installs; the CDN covers them otherwise).'],
            ],
        ],
        [
            'tag' => '1.42.5',
            'date' => '2026-06-26',
            'title' => 'Self-healing Font Awesome kit cache',
            'items' => [
                ['type' => 'fixed', 'text' => 'If you use a Font Awesome Pro kit, the kit script is now cache-busted on each release. Changing your kit’s version (or any FA update) now takes effect on visitors’ next load instead of waiting out their cached kit files — which previously left some icons showing as empty boxes even after a hard-refresh.'],
            ],
        ],
        [
            'tag' => '1.42.4',
            'date' => '2026-06-26',
            'title' => 'Font Awesome 7.3 & a message-composer fix',
            'items' => [
                ['type' => 'improved', 'text' => 'Updated Font Awesome to 7.3 for the built-in free icons (Pro kits are unaffected — they keep using your own kit). The refreshed icon stylesheet also clears stale browser caches that could leave some icons showing as empty boxes.'],
                ['type' => 'fixed', 'text' => 'The message composer no longer pushes the Send button off-screen when you add several images — the editor area now scrolls and images are sized to fit.'],
            ],
        ],
        [
            'tag' => '1.42.3',
            'date' => '2026-06-25',
            'title' => 'Live profile walls & a tidier mobile bar',
            'items' => [
                ['type' => 'improved', 'text' => 'Profile-wall posts now appear in real time — when someone posts on a wall, everyone viewing that profile sees it instantly instead of having to reload.'],
                ['type' => 'improved', 'text' => 'Cleaner mobile navigation: the top bar no longer duplicates the actions already in the bottom tab bar (messages, alerts, the account menu and “new discussion”). Account essentials like Settings and Log out moved into the slide-out menu.'],
            ],
        ],
        [
            'tag' => '1.42.2',
            'date' => '2026-06-25',
            'title' => 'Smoother self-hosted setup',
            'items' => [
                ['type' => 'improved', 'text' => 'Installing from source is friendlier: `composer setup` now generates your app key without stalling on the production prompt, and stops with a clear, fix-it message when the database isn’t configured yet or Node.js isn’t installed — instead of a cryptic error.'],
            ],
        ],
        [
            'tag' => '1.42.0',
            'date' => '2026-06-21',
            'title' => 'Redesigned messages, multilingual search & a fresh look',
            'items' => [
                ['type' => 'new', 'text' => 'Direct messages have a brand-new two-pane layout — your conversation list sits beside the open chat, with a polished mobile view that switches between the two.'],
                ['type' => 'new', 'text' => 'A new Categories view for the forum home, alongside Feed and Grid — browse the community as a tidy directory of categories.'],
                ['type' => 'new', 'text' => 'Optional Typesense search: a bundled extension that plugs into a Typesense engine for faster, typo-tolerant results, with an automatic fall-back to the built-in search.'],
                ['type' => 'improved', 'text' => 'Search now understands languages that don’t put spaces between words (Chinese, Japanese, Korean, Thai and more).'],
                ['type' => 'improved', 'text' => 'The theme editor can now set a forum-wide background image and recolour the sidebar widget headers.'],
                ['type' => 'improved', 'text' => 'Grid view can show a default cover image for topics that don’t have their own.'],
                ['type' => 'improved', 'text' => 'Categories can be reordered by drag-and-drop in the admin panel.'],
                ['type' => 'improved', 'text' => 'The AI assistant’s auto-answer can now be limited to specific categories with a simple click-to-choose list.'],
            ],
        ],
        [
            'tag' => '1.41.4',
            'date' => '2026-06-20',
            'title' => 'Extensions can add multiple menu links',
            'items' => [
                ['type' => 'improved', 'text' => 'An extension can now contribute more than one navigation link — so OnAir+ surfaces both its creator Studio and a public VODs (past broadcasts) library.'],
            ],
        ],
        [
            'tag' => '1.41.2',
            'date' => '2026-06-20',
            'title' => 'Tidier navigation',
            'items' => [
                ['type' => 'improved', 'text' => 'When several extensions add links to the top navigation, the extras now tuck into a “More” menu so the bar stays clean instead of sprawling.'],
            ],
        ],
        [
            'tag' => '1.41.1',
            'date' => '2026-06-20',
            'title' => 'Avatar badge + link-card polish',
            'items' => [
                ['type' => 'fixed', 'text' => 'Avatar badges (staff/LIVE) now sit snugly on the avatar in the discussion list instead of drifting below it.'],
                ['type' => 'improved', 'text' => 'Link-preview cards now match Convoro’s current card design — rounded corners, a soft shadow and a subtle hover lift.'],
            ],
        ],
        [
            'tag' => '1.41.0',
            'date' => '2026-06-20',
            'title' => 'Staff badges on avatars',
            'items' => [
                ['type' => 'new', 'text' => 'A member’s staff badge (Admin, Mod, etc.) now sits right on their avatar — with its group colour and a clean white ring — wherever the avatar appears, not just on their profile.'],
                ['type' => 'improved', 'text' => 'If a staff member is live-streaming, the LIVE badge stacks neatly under their staff badge, and the online/offline dot moved to the top-right so nothing overlaps.'],
            ],
        ],
        [
            'tag' => '1.40.0',
            'date' => '2026-06-20',
            'title' => 'LIVE badges on avatars',
            'items' => [
                ['type' => 'new', 'text' => 'Avatars can now show a pulsing LIVE badge wherever they appear. The OnAir extension uses this so members who go live are marked across the whole forum — in topics, on profiles and in member lists.'],
            ],
        ],
        [
            'tag' => '1.39.8',
            'date' => '2026-06-20',
            'title' => 'Click a post number to cross-reference it',
            'items' => [
                ['type' => 'new', 'text' => 'Click any post’s #number to copy a link to it. Paste that link into a post anywhere and it cross-references the original — the target gains a “Mentioned in” backlink and its author is notified.'],
                ['type' => 'new', 'text' => 'Forums can now show a Donate card in the sidebar, backed by a Stripe buy button (set it up in admin settings).'],
            ],
        ],
        [
            'tag' => '1.39.7',
            'date' => '2026-06-20',
            'title' => 'GitHub-style #123 cross-references',
            'items' => [
                ['type' => 'new', 'text' => 'Type #123 in a post to reference another topic — it renders as a chip showing that topic’s current title (so it updates automatically if the topic is renamed), and the reference is recorded just like pasting its link.'],
                ['type' => 'new', 'text' => 'When someone references your topic — by a #123 shorthand or a pasted link — you now get a notification, and the reference shows in the target’s “Mentioned in” box.'],
            ],
        ],
        [
            'tag' => '1.39.6',
            'date' => '2026-06-19',
            'title' => 'Movie & TV cards in the Marketplace',
            'items' => [
                ['type' => 'new', 'text' => 'The free TMDB extension is now in the Marketplace. Pick your movie/TV boards, then title a topic with a film or show name — Convoro pulls the poster, overview, cast, where-to-watch and a trailer from The Movie Database and renders it as a rich card on the first post.'],
                ['type' => 'new', 'text' => 'As you type the title you get a live match preview, and in those boards a movie thread needs only a title — the card is the post, so there’s nothing to write.'],
            ],
        ],
        [
            'tag' => '1.39.5',
            'date' => '2026-06-19',
            'title' => 'Downloads in the Marketplace',
            'items' => [
                ['type' => 'new', 'text' => 'The free Downloads extension is now in the Marketplace — a downloads directory. Organise downloads into categories, link a GitHub release or URL, or upload a file that’s served privately through a counted route. Cards show a cover image, a short excerpt and per-download counts.'],
                ['type' => 'new', 'text' => 'Category icons can be a preset emoji or your own uploaded image.'],
                ['type' => 'fixed', 'text' => 'Embedded extension management panels in the admin area now follow the light/dark theme immediately instead of only after a refresh.'],
            ],
        ],
        [
            'tag' => '1.39.4',
            'date' => '2026-06-19',
            'title' => 'Rich link previews',
            'items' => [
                ['type' => 'new', 'text' => 'Paste a link on its own line and it becomes a rich preview card — the page’s title, description, image and site, pulled from its Open Graph tags. Works alongside the existing YouTube/Spotify/X embeds; previews are cached and fetched safely.'],
            ],
        ],
        [
            'tag' => '1.39.3',
            'date' => '2026-06-19',
            'title' => 'Role-Play in the Marketplace',
            'items' => [
                ['type' => 'new', 'text' => 'The free Role-Play extension is now in the Marketplace — install it on demand to let members create characters and post as them, with role-play topics, a dice roller, character sheets and a character directory.'],
            ],
        ],
        [
            'tag' => '1.39.2',
            'date' => '2026-06-19',
            'title' => 'Role-play groundwork',
            'items' => [
                ['type' => 'new', 'text' => 'New PostIdentity hook lets an extension show a different author identity on a post — the foundation for role-play forums, where members post as characters. Powers the new free Role-Play extension.'],
                ['type' => 'new', 'text' => 'The composer now tells extensions which topic and category a post is being written in, so add-ons can adapt the toolbar to the context (e.g. show a “post as character” picker only in role-play threads).'],
                ['type' => 'improved', 'text' => 'Ask {site} now sits in the left sidebar under the categories list, freeing the main column for the discussion list.'],
                ['type' => 'fixed', 'text' => 'The reading-progress scrubber no longer overlaps the footer at the bottom of a thread — it stays visible and slides up to sit just above it.'],
            ],
        ],
        [
            'tag' => '1.39.1',
            'date' => '2026-06-16',
            'title' => 'Themes in the Marketplace + polish',
            'items' => [
                ['type' => 'new', 'text' => 'Themes can now be installed from the Marketplace — starting with Aurora, a cosmic dark theme with an animated backdrop and six colour palettes. Like all add-ons, themes install on demand; no software update needed.'],
                ['type' => 'improved', 'text' => 'Marketplace cards have a cleaner, designed look — each gets its own colour-themed cover and shows the add-on’s icon.'],
                ['type' => 'fixed', 'text' => 'The theme-editor button no longer overlaps the back-to-top button.'],
            ],
        ],
        [
            'tag' => '1.39.0',
            'date' => '2026-06-15',
            'title' => 'Social Groups',
            'items' => [
                ['type' => 'new', 'text' => 'Members can create their own Groups — public or private — each with its own discussions, right from the new Groups link in the header.'],
                ['type' => 'new', 'text' => 'Group discussions use the same editor, @mentions, reactions and polls as the rest of the forum; private groups keep their discussions hidden from the main feed, search and everywhere else unless you’re a member.'],
                ['type' => 'new', 'text' => 'Open, request-to-join or invite-only membership, owner and moderator roles, banning and join-request approvals — plus a custom icon and banner for each group and an owner analytics view.'],
            ],
        ],
        [
            'tag' => '1.38.13',
            'date' => '2026-06-14',
            'title' => 'Cleaner extension icons',
            'items' => [
                ['type' => 'improved', 'text' => 'Extension icons now sit on their own, without the tinted rounded box behind them, so icons that ship their own coloured tile no longer look like a box inside a box.'],
            ],
        ],
        [
            'tag' => '1.38.12',
            'date' => '2026-06-14',
            'title' => 'Matching favicon + app icons',
            'items' => [
                ['type' => 'improved', 'text' => 'The browser tab favicon and installed-app (PWA) icons now use the Convoro speech-bubble mark, matching the logo across the marketing site and the forum.'],
            ],
        ],
        [
            'tag' => '1.38.11',
            'date' => '2026-06-14',
            'title' => 'Refreshed Convoro logo',
            'items' => [
                ['type' => 'improved', 'text' => 'The Convoro logo is now the indigo speech-bubble mark with the pointed tail, used consistently across the header, footer, sign-in and onboarding screens.'],
            ],
        ],
        [
            'tag' => '1.38.10',
            'date' => '2026-06-14',
            'title' => 'Extension detail page shows the real icon',
            'items' => [
                ['type' => 'fixed', 'text' => 'An extension’s detail page now shows its own icon in the header instead of a single-letter initial.'],
            ],
        ],
        [
            'tag' => '1.38.9',
            'date' => '2026-06-14',
            'title' => 'No more generic puzzle tiles on extension cards',
            'items' => [
                ['type' => 'improved', 'text' => 'The public Extensions directory and every product/extension card now show the add-on’s own icon (or a clean lettered tile) instead of a generic puzzle/palette emoji.'],
            ],
        ],
        [
            'tag' => '1.38.8',
            'date' => '2026-06-14',
            'title' => 'Fix: grid/server icons missing on extension cards',
            'items' => [
                ['type' => 'fixed', 'text' => 'Extension icons drawn from rectangles — Projects’ grid, Horizon’s server stack, Giveaways’ box — now render in full on their cards. The cover generator was resizing the icon in a way that flattened those shapes, so Horizon showed only a stray “:”.'],
            ],
        ],
        [
            'tag' => '1.38.7',
            'date' => '2026-06-14',
            'title' => 'Categories icon + cleaner Marketplace cards',
            'items' => [
                ['type' => 'improved', 'text' => 'The Categories add-on has its own distinct icon (it previously shared the Projects grid), and the Marketplace cards dropped the generic puzzle tile since each card already shows the extension’s own cover.'],
            ],
        ],
        [
            'tag' => '1.38.6',
            'date' => '2026-06-14',
            'title' => 'Extension card icons & covers cleanup',
            'items' => [
                ['type' => 'fixed', 'text' => 'Facebook Auto Post’s card is its branded blue cover again, and the cover generator no longer overwrites an extension’s own custom cover.'],
                ['type' => 'fixed', 'text' => 'Added icons to the Member Badges and RSS cards, and cover images now refresh correctly after a change (no more stale cached card showing the old initials).'],
            ],
        ],
        [
            'tag' => '1.38.5',
            'date' => '2026-06-14',
            'title' => 'Fix: admin Members page error',
            'items' => [
                ['type' => 'fixed', 'text' => 'The admin Members page (and member profiles) could fail to load with a date-formatting error under some languages. Join dates now use a locale-independent format that can’t trip it.'],
            ],
        ],
        [
            'tag' => '1.38.4',
            'date' => '2026-06-14',
            'title' => 'Real icons on extension cards + Facebook Auto Post unbundled',
            'items' => [
                ['type' => 'improved', 'text' => 'Extension & theme cards (in the Marketplace and the public directory) now show each add-on’s own icon instead of an initials monogram, so they’re easier to recognize at a glance.'],
                ['type' => 'improved', 'text' => 'Facebook Auto Post is now an installable first-party extension from the store rather than bundled into core, matching the other first-party add-ons.'],
            ],
        ],
        [
            'tag' => '1.38.3',
            'date' => '2026-06-14',
            'title' => 'Leaner pages — every response ~25KB smaller',
            'items' => [
                ['type' => 'improved', 'text' => 'Every page was shipping the full list of ~360 internal routes (~26KB) in its HTML; now only the handful the frontend actually uses are included (~1KB). Pages are smaller and a little quicker to build and parse, across every install.'],
            ],
        ],
        [
            'tag' => '1.38.2',
            'date' => '2026-06-14',
            'title' => 'Icon & quote-button polish',
            'items' => [
                ['type' => 'fixed', 'text' => 'Category icons now load free Font Awesome automatically when no Pro kit is configured, so they render on any domain — no more broken icons or “Forbidden” console errors from a domain-locked kit.'],
                ['type' => 'fixed', 'text' => 'The floating “Quote” button in messages now uses theme colours so it stays readable in both light and dark mode.'],
            ],
        ],
        [
            'tag' => '1.38.1',
            'date' => '2026-06-14',
            'title' => 'Realtime fixed: live notifications, messages & typing',
            'items' => [
                ['type' => 'fixed', 'text' => 'New notifications and direct messages now appear instantly, without refreshing — the realtime connection was pointing at the wrong address in the published build and now reads your site’s own address at runtime. (Realtime must be enabled in Admin → Settings.)'],
                ['type' => 'new', 'text' => 'Typing indicator in direct messages — see when the other person is typing, just like in the forum.'],
            ],
        ],
        [
            'tag' => '1.38.0',
            'date' => '2026-06-14',
            'title' => 'Messages: reply, quote & translated notifications',
            'items' => [
                ['type' => 'new', 'text' => 'Reply and quote in direct messages — tap Reply on any message to quote it, or select text in a message to get a one-tap Quote button, just like the forum.'],
                ['type' => 'fixed', 'text' => 'Message notifications now read “{name} sent you a message” and show the preview in YOUR language when auto-translate is on — previously the title and preview were stuck in the sender’s language.'],
                ['type' => 'fixed', 'text' => 'Fixed scrolling in direct messages on mobile — the message list now scrolls smoothly on its own without the page jumping, and the composer stays in view.'],
            ],
        ],
        [
            'tag' => '1.37.1',
            'date' => '2026-06-14',
            'title' => 'Faster marketing pages',
            'items' => [
                ['type' => 'improved', 'text' => 'The homepage, changelog, roadmap and other public pages now serve their server-rendered HTML from a cache, cutting their render time dramatically. The cache refreshes itself automatically on every update, so you never see stale content.'],
            ],
        ],
        [
            'tag' => '1.37.0',
            'date' => '2026-06-14',
            'title' => 'Spam, flood & abuse controls',
            'items' => [
                ['type' => 'new', 'text' => 'Slow mode — set a minimum gap between a member’s posts site-wide, with a stricter gap for brand-new accounts, plus a per-category cooldown you set when editing a category. Great for keeping busy threads readable.'],
                ['type' => 'new', 'text' => 'Flood protection — hourly caps on posts and new topics, and automatic blocking of identical reposts within a window you choose.'],
                ['type' => 'new', 'text' => 'Content filters — a banned words/phrases list and a banned link-domains list (subdomains included), plus an optional max-links-per-post limit. Choose whether a match is blocked outright or quietly held for a moderator.'],
                ['type' => 'new', 'text' => 'First-post approval — hold a new member’s first post(s) for review. Held posts and topics are hidden from everyone but their author and the moderators, and appear in Moderation to approve in one click.'],
                ['type' => 'new', 'text' => 'A new admin “Spam & flood” page brings it all together. Administrators are always exempt, and you can exempt trusted members from the flood limits too.'],
            ],
        ],
        [
            'tag' => '1.36.6',
            'date' => '2026-06-14',
            'title' => 'Extension icons & covers refresh themselves',
            'items' => [
                ['type' => 'fixed', 'text' => 'When an extension ships a new icon, cover, or updated details, the admin shows them immediately — the extension cache now notices changes inside an extension, not just when one is added or removed. No more manual cache clear after an update.'],
            ],
        ],
        [
            'tag' => '1.36.5',
            'date' => '2026-06-14',
            'title' => 'Connect several accounts + extension covers',
            'items' => [
                ['type' => 'improved', 'text' => 'Social Login now lets a member link several providers to one account at once — sign in with GitHub today and Facebook tomorrow, both landing on the same profile. Connect and disconnect each provider independently from your Settings page.'],
                ['type' => 'improved', 'text' => 'Facebook Auto Post now shows its own Facebook icon in the admin sidebar and a branded cover in the Marketplace, so it’s easy to spot.'],
                ['type' => 'improved', 'text' => 'Bundled extensions can ship their own Marketplace cover image (a “cover” in the manifest), giving first-party add-ons a polished listing.'],
            ],
        ],
        [
            'tag' => '1.36.4',
            'date' => '2026-06-14',
            'title' => 'Facebook auto-post + import from a database file',
            'items' => [
                ['type' => 'new', 'text' => 'New first-party Facebook Auto Post extension — automatically share every new topic to your Facebook Page, with a customizable message, an optional excerpt and per-category control. Enable it from the Marketplace and add your Page access token (there’s a one-click “Send a test post” to confirm it’s working).'],
                ['type' => 'new', 'text' => 'The importer can now read a database FILE, not just a live connection: upload a MySQL dump (.sql or .sql.gz) or a SQLite file. Perfect for managed hosts that only give you your database — it’s loaded and read exactly like a live import, with the same one-click scan and background run.'],
            ],
        ],
        [
            'tag' => '1.36.3',
            'date' => '2026-06-13',
            'title' => 'Reply auto-save',
            'items' => [
                ['type' => 'improved', 'text' => 'Replies are now auto-saved as you type, just like new topics, with a live “Saving… / Draft saved” indicator. If you refresh, crash or navigate away mid-reply, it’s waiting for you when you return — and it clears automatically once you post.'],
            ],
        ],
        [
            'tag' => '1.36.1',
            'date' => '2026-06-13',
            'title' => 'Wider audit coverage',
            'items' => [
                ['type' => 'improved', 'text' => 'The staff audit log now also records extension changes (install, enable, disable, remove) and member-group changes (create, edit, delete), alongside the content-moderation actions it already tracked.'],
            ],
        ],
        [
            'tag' => '1.36.0',
            'date' => '2026-06-13',
            'title' => 'Auto-saving drafts',
            'items' => [
                ['type' => 'new', 'text' => 'The topic composer now auto-saves your work as you type, so a crash, refresh or accidental tab-close never loses a post. When you come back, Convoro offers to restore your unsaved draft in one tap — and a subtle “Draft saved” indicator shows when your work is safely stored.'],
            ],
        ],
        [
            'tag' => '1.35.0',
            'date' => '2026-06-13',
            'title' => 'Staff audit log',
            'items' => [
                ['type' => 'new', 'text' => 'Every moderation and admin action is now recorded in a new Audit log (Admin → Audit log): who did what, to whom, when — with the reason and the staff member’s IP. Covers deleting and approving posts, banning and reinstating members, IP bans, moving posts and topics, pinning and locking, resolving reports, role and group changes, and GDPR anonymize/erase. It’s searchable and filterable by action, and each entry keeps a snapshot so it stays readable even after the target is deleted.'],
            ],
        ],
        [
            'tag' => '1.34.3',
            'date' => '2026-06-13',
            'title' => 'Faster first loads & smoother updates',
            'items' => [
                ['type' => 'improved', 'text' => 'A big speed-up across the whole site: enabled HTTP/2 and a server-side bytecode cache so pages respond in roughly half the time, and made web fonts load without blocking the first paint.'],
                ['type' => 'fixed', 'text' => 'Fixed a service-worker behaviour that forced every brand-new visitor through a full second page load on their very first visit — first loads are now fast too.'],
                ['type' => 'improved', 'text' => 'Installing, enabling or updating an extension now clears the right caches automatically, so new extension code and pages take effect immediately.'],
            ],
        ],
        [
            'tag' => '1.34.0',
            'date' => '2026-06-13',
            'title' => 'Server-side rendering — instant first paint & better SEO',
            'items' => [
                ['type' => 'new', 'text' => 'Convoro now renders pages on the server, so the forum appears fully formed on first load instead of only after scripts run. First paint is faster, and search-engine crawlers receive the complete page.'],
            ],
        ],
        [
            'tag' => '1.33.0',
            'date' => '2026-06-13',
            'title' => 'A much faster forum, site-wide',
            'items' => [
                ['type' => 'improved', 'text' => 'Site-wide performance pass: JavaScript and CSS are now compressed (around 70% smaller over the wire) and cached long-term, so first loads are quicker and repeat visits are near-instant.'],
                ['type' => 'improved', 'text' => 'The icon library now loads deferred, fonts no longer block the first paint, and the admin theme editor is split out of the main bundle so regular visitors don’t download it.'],
            ],
        ],
        [
            'tag' => '1.32.0',
            'date' => '2026-06-13',
            'title' => 'Recurring events, visitor counts & SEO hardening',
            'items' => [
                ['type' => 'new', 'text' => 'Events can now repeat — daily, weekly, every two weeks or monthly, with an optional end date. Recurring events expand across the calendar, show their next occurrence in the list, remind attendees before each occurrence, and add to Apple/Outlook/Google calendars with a proper recurrence rule.'],
                ['type' => 'new', 'text' => 'The “Online now” widget now also shows how many logged-out visitors are browsing.'],
                ['type' => 'improved', 'text' => 'Hardened the topic structured data (DiscussionForumPosting) against Google Search Console issues — every posting and comment now always has an author, date and content (text or image), so image- and emoji-only replies no longer disqualify a thread from rich results.'],
            ],
        ],
        [
            'tag' => '1.31.0',
            'date' => '2026-06-13',
            'title' => 'Instant extension nav links + Google structured data',
            'items' => [
                ['type' => 'improved', 'text' => 'Extension nav links (Leaderboard, Events, Feed) now render instantly with the page instead of popping in a moment later — extensions can declare a header link in their manifest that’s rendered server-side.'],
                ['type' => 'new', 'text' => 'Added Google-friendly JSON-LD structured data: a site-wide WebSite (with sitelinks search) and Organization, plus DiscussionForumPosting on every topic — the schema Google recommends for forum threads.'],
                ['type' => 'fixed', 'text' => 'Tidied the event detail page: Edit and Delete are now grouped in a single “Manage event” card so the delete button no longer looks out of place.'],
            ],
        ],
        [
            'tag' => '1.30.0',
            'date' => '2026-06-13',
            'title' => 'Events categories, editing & timezones — plus nicer quotes',
            'items' => [
                ['type' => 'new', 'text' => 'Events now supports admin-managed coloured categories (with filtering), editing an event after you create it, a community timezone, and a calendar date badge in the upcoming-events widget. There’s also a new “Event reminders” notification toggle in your preferences.'],
                ['type' => 'improved', 'text' => 'Quoted replies now render as a tidy quote card — an “{name} said:” header with a jump-to-post link and a quote glyph — instead of a plain indented block.'],
                ['type' => 'fixed', 'text' => 'Fixed a crash in the notification bell when an extension sent a notification without an author (such as an event reminder).'],
            ],
        ],
        [
            'tag' => '1.29.0',
            'date' => '2026-06-13',
            'title' => 'First-party extension pages use the real forum shell',
            'items' => [
                ['type' => 'improved', 'text' => 'The Leaderboard, Events, Bookmarks and Feed pages now render inside the exact same header, footer and theme as the rest of the forum — full navigation, notifications, theme toggle and account menu — instead of a lookalike chrome, so they no longer feel out of place. Update those extensions from the Marketplace to get it.'],
            ],
        ],
        [
            'tag' => '1.28.1',
            'date' => '2026-06-13',
            'title' => 'Translation reliability fix',
            'items' => [
                ['type' => 'fixed', 'text' => 'Fixed UI translations silently failing to save when the language directory wasn’t writable — the translator now detects this, stops retrying instead of looping, and shows a clear error on the admin Languages page so it’s obvious what to fix.'],
            ],
        ],
        [
            'tag' => '1.28.0',
            'date' => '2026-06-13',
            'title' => 'Events 2.0 — calendar, RSVPs & reminders',
            'items' => [
                ['type' => 'new', 'text' => 'The free Events extension got a major upgrade: a month calendar and list view, rich event pages with attendee lists, going / maybe / can’t-go RSVPs with optional capacity, one-tap add-to-calendar (Apple/Outlook .ics and Google Calendar), and automatic reminders to attendees a day and an hour before each event. Plus an “Events” header link and an upcoming-events sidebar widget. Install or update it from the Marketplace.'],
                ['type' => 'improved', 'text' => 'Extensions can now send their own rich notifications (with custom text and links) through the notification bell, email and push — used by the new event reminders.'],
            ],
        ],
        [
            'tag' => '1.27.0',
            'date' => '2026-06-13',
            'title' => 'Online indicators on posts & footer polish',
            'items' => [
                ['type' => 'new', 'text' => 'Posts now show an online/offline presence dot on the author’s avatar (green when they’ve been active in the last few minutes), refreshed live while you read the thread.'],
                ['type' => 'improved', 'text' => 'The Footer Builder now autosaves as you edit (no more lost changes), the accent line runs full-width to clearly separate the footer, and links from other extensions (RSS, Privacy choices) are tucked neatly into the footer’s bottom bar instead of floating loose.'],
                ['type' => 'improved', 'text' => 'Extension pages now always use the community’s assigned logo in the header, including the dark-mode variant.'],
            ],
        ],
        [
            'tag' => '1.26.0',
            'date' => '2026-06-13',
            'title' => 'Footer builder, redesigned leaderboard & search above the list',
            'items' => [
                ['type' => 'new', 'text' => 'A new free “Footer Builder” extension lets you design a site-wide footer — a brand column with logo and social buttons, up to four link columns, a copyright bar and a back-to-top button — with a live preview in the admin editor. It matches your active theme automatically. Install it from the Marketplace.'],
                ['type' => 'improved', 'text' => 'The Leaderboard has been completely redesigned: a podium for the top three, a card grid for the next contenders, an honorable-mentions list, and time-period filters (All Time, Yearly, Quarterly, Monthly, Weekly, Daily).'],
                ['type' => 'improved', 'text' => 'Extension pages (Leaderboard, Bookmarks, Feed) now show the full site header — logo and navigation — instead of a bare back link, so they feel like part of the community.'],
                ['type' => 'improved', 'text' => 'The search box has moved out of the header and now sits prominently above the discussion list, where it’s easier to find and use.'],
            ],
        ],
        [
            'tag' => '1.25.1',
            'date' => '2026-06-13',
            'title' => 'Follow members & a personal feed',
            'items' => [
                ['type' => 'new', 'text' => 'A new free “Follow & Feed” extension lets members follow each other — a Follow button on profiles (with a follower count) and a Feed page showing the latest topics and replies from the people you follow. Install it from the Marketplace.'],
            ],
        ],
        [
            'tag' => '1.25.0',
            'date' => '2026-06-13',
            'title' => 'Install with Docker',
            'items' => [
                ['type' => 'new', 'text' => 'Convoro now ships a production-ready Docker setup — a Dockerfile and docker-compose.yml that bring up the app (FrankenPHP), MySQL, Redis, the queue worker, the realtime server and the scheduler with one command. See docs/install-docker.md: copy .env.docker, generate an app key, and run “docker compose up -d --build”.'],
            ],
        ],
        [
            'tag' => '1.24.0',
            'date' => '2026-06-13',
            'title' => 'Colourful store covers & themed scrollbars',
            'items' => [
                ['type' => 'improved', 'text' => 'Auto-generated store/extension cover images now use a distinct accent colour per listing across the whole spectrum, instead of all being the same shade of violet.'],
                ['type' => 'improved', 'text' => 'Scrollbars now match the site theme (in both light and dark) rather than the default grey browser scrollbar.'],
            ],
        ],
        [
            'tag' => '1.23.0',
            'date' => '2026-06-13',
            'title' => 'See & look up member IP addresses',
            'items' => [
                ['type' => 'new', 'text' => 'Admin → Members now shows each member’s last-seen and registration IP, with a one-click geolocation look-up (location, ISP, and flags for VPN/proxy/datacenter IPs) and a Ban IP button — so you can spot and block an abusive address right from their profile.'],
            ],
        ],
        [
            'tag' => '1.22.0',
            'date' => '2026-06-13',
            'title' => 'Staff posts, category accents & sharper badges',
            'items' => [
                ['type' => 'new', 'text' => 'Posts from staff (admins & moderators) now have a coloured accent bar down the left of the post, so official replies stand out at a glance.'],
                ['type' => 'new', 'text' => 'Each discussion in the list now has a left-edge accent in its category colour.'],
                ['type' => 'improved', 'text' => 'Admin & moderator badges are now solid (filled) so they clearly outrank the Leader trust badge.'],
                ['type' => 'improved', 'text' => 'Store cards each get their own tinted background, not just a coloured cover.'],
            ],
        ],
        [
            'tag' => '1.21.0',
            'date' => '2026-06-13',
            'title' => 'Reply by email',
            'items' => [
                ['type' => 'new', 'text' => 'Members can now reply to a notification email and have it posted as their reply — no need to open the site. Notification emails carry a signed Reply-To, quoted history and signatures are stripped automatically, and the same trust-level rules apply.'],
                ['type' => 'new', 'text' => 'Two inbound paths: a self-hosted Postfix pipe (convoro:receive-email) and a provider webhook (POST /mail/inbound). Turn it on and set your reply domain in Admin → Settings → Reply by email.'],
            ],
        ],
        [
            'tag' => '1.20.3',
            'date' => '2026-06-13',
            'title' => 'Trust badge on the Members directory',
            'items' => [
                ['type' => 'improved', 'text' => 'The public Members directory now shows each member’s trust level beside their name, matching the profile and admin views.'],
            ],
        ],
        [
            'tag' => '1.20.2',
            'date' => '2026-06-13',
            'title' => 'Trust level shown on profiles & the Members list',
            'items' => [
                ['type' => 'improved', 'text' => 'A member’s trust level (New, Member or Leader) now shows as a badge on their profile — for every level, not just Leader — and beside each member in Admin → Members, with a pin marker when an admin has pinned the level.'],
            ],
        ],
        [
            'tag' => '1.20.1',
            'date' => '2026-06-13',
            'title' => 'Polish: avatars, icon check & store cards',
            'items' => [
                ['type' => 'fixed', 'text' => 'The ring behind a profile avatar now follows your avatar shape, so it no longer shows a circle poking out behind rounded or square avatars.'],
                ['type' => 'improved', 'text' => 'Admin → Settings → Icons now previews Font Awesome Free vs Pro icons live, so you can tell at a glance whether your Pro kit is actually loading.'],
                ['type' => 'improved', 'text' => 'Store cards now each get their own accent colour, so the directory looks lively instead of a wall of identical tiles.'],
            ],
        ],
        [
            'tag' => '1.20.0',
            'date' => '2026-06-13',
            'title' => 'Trust levels',
            'items' => [
                ['type' => 'new', 'text' => 'Members now earn trust by taking part. Everyone starts at New, becomes a Member automatically once they’ve posted and read a little, and admins can grant Leader to veterans — with a celebratory notification when someone levels up.'],
                ['type' => 'new', 'text' => 'New (level 0) accounts have links and images stripped from their posts by default, which quietly blunts drive-by spam without bothering established members. Toggle it in Admin → Settings.'],
                ['type' => 'new', 'text' => 'A trust badge appears on Member and Leader profiles, and admins can pin or reset anyone’s level from the Members page.'],
            ],
        ],
        [
            'tag' => '1.19.2',
            'date' => '2026-06-13',
            'title' => 'Polished reading scrubber',
            'items' => [
                ['type' => 'improved', 'text' => 'The reading-progress scrubber on a discussion now sits in a tidy panel right beside the posts (instead of out against the browser scrollbar), with a dot per reply and a position counter so you can see where you are in a thread at a glance.'],
            ],
        ],
        [
            'tag' => '1.19.0',
            'date' => '2026-06-13',
            'title' => 'Richer composer — code, spoilers & GIFs',
            'items' => [
                ['type' => 'new', 'text' => 'Code blocks now have syntax highlighting for 20+ languages — pick the language from the toolbar and your code is colourised both as you type and in the posted reply.'],
                ['type' => 'new', 'text' => 'Spoilers: hide plot twists or answers behind a click. Wrap any text in a collapsible spoiler from the composer toolbar.'],
                ['type' => 'new', 'text' => 'A GIF picker — search and drop in a GIF without leaving the composer. Admins turn it on in Settings by choosing Tenor or Giphy and pasting a free API key.'],
            ],
        ],
        [
            'tag' => '1.18.0',
            'date' => '2026-06-13',
            'title' => 'Bookmarks — save posts to read later',
            'items' => [
                ['type' => 'new', 'text' => 'A new free Bookmarks extension lets members save any post to a private reading list, reachable from a Bookmarks item in their account menu. Install it from the Marketplace.'],
                ['type' => 'changed', 'text' => 'Extensions can now add links to the account (avatar) menu, so personal features like Bookmarks live alongside Settings and Drafts instead of cluttering the header.'],
            ],
        ],
        [
            'tag' => '1.17.1',
            'date' => '2026-06-13',
            'title' => 'Extension nav links sit with the main nav',
            'items' => [
                ['type' => 'changed', 'text' => 'Extensions can now add links to the primary header navigation (next to Community and Members) rather than off to the side. The Leaderboard link now sits with the other nav links.'],
            ],
        ],
        [
            'tag' => '1.17.0',
            'date' => '2026-06-13',
            'title' => 'Leaderboard is now an opt-in extension',
            'items' => [
                ['type' => 'changed', 'text' => 'The top-contributors leaderboard moved out of core into a free, opt-in Marketplace extension, so communities that do not want it are not carrying it. Install the “Leaderboard” extension from the Marketplace to keep it (and its header link).'],
            ],
        ],
        [
            'tag' => '1.16.5',
            'date' => '2026-06-13',
            'title' => 'Open a discussion at the first unread reply',
            'items' => [
                ['type' => 'changed', 'text' => 'Opening a discussion now jumps you to the first new reply since your last visit (with a New divider), instead of always starting from the top. Permalinks to a specific post still go straight there.'],
            ],
        ],
        [
            'tag' => '1.16.4',
            'date' => '2026-06-13',
            'title' => 'Fix: AI budget cap label',
            'items' => [
                ['type' => 'fixed', 'text' => 'The AI Usage & budget card showed a raw ":cap cap" placeholder instead of your actual monthly cap amount.'],
            ],
        ],
        [
            'tag' => '1.16.3',
            'date' => '2026-06-13',
            'title' => 'Tag the assistant to get an answer',
            'items' => [
                ['type' => 'new', 'text' => 'Mention the AI assistant (e.g. @Convoro Bot) in any post — a topic or a reply — and it answers right there in the thread, grounded in your docs and knowledge base, with citations. Toggle it in Admin → AI.'],
            ],
        ],
        [
            'tag' => '1.16.2',
            'date' => '2026-06-13',
            'title' => 'Knowledge Base: longer articles + citation links',
            'items' => [
                ['type' => 'new', 'text' => 'Knowledge Base articles can now be any length — they’re automatically split into passages so the assistant retrieves and cites the relevant part of a long article.'],
                ['type' => 'new', 'text' => 'Each article can carry an optional link (a doc, a thread, or an external page) that the assistant uses when it cites the article.'],
            ],
        ],
        [
            'tag' => '1.16.1',
            'date' => '2026-06-13',
            'title' => 'Curated knowledge base for the AI',
            'items' => [
                ['type' => 'new', 'text' => 'A new Admin → Knowledge Base where you can write your own support articles — answers and fixes that aren’t in the official docs. The assistant indexes them and grounds answers in them too, with citations.'],
            ],
        ],
        [
            'tag' => '1.16.0',
            'date' => '2026-06-13',
            'title' => 'Grounded AI answers — Ask from your docs, not just posts',
            'items' => [
                ['type' => 'new', 'text' => '“Ask” can now answer from your built-in documentation and changelog — the authoritative source of how the software works — and cite the exact guide, instead of paraphrasing whatever members happened to write in threads.'],
                ['type' => 'new', 'text' => 'New “Strict support mode” (Admin → AI): the assistant answers only from your knowledge base and says when something isn’t documented rather than guessing — ideal for a help/support site.'],
            ],
        ],
        [
            'tag' => '1.15.2',
            'date' => '2026-06-13',
            'title' => 'Clear notifications',
            'items' => [
                ['type' => 'new', 'text' => 'You can now clear notifications: dismiss a single one with the × on its row, or use “Clear all” to empty the whole list — not just mark them read.'],
            ],
        ],
        [
            'tag' => '1.15.1',
            'date' => '2026-06-13',
            'title' => 'Each extension shows its own icon',
            'items' => [
                ['type' => 'changed', 'text' => 'Installed extensions now display their own icon in the admin sidebar instead of a shared generic glyph, so each is easy to tell apart.'],
            ],
        ],
        [
            'tag' => '1.15.0',
            'date' => '2026-06-13',
            'title' => 'Accepted Answers + a leaner extension platform',
            'items' => [
                ['type' => 'new', 'text' => 'New “Accepted Answers” extension (free, in the Marketplace): mark a category as Q&A, and the asker or your staff can mark a reply as the accepted answer — it gets a green check and the thread reads as solved. It’s opt-in, so nothing changes unless you install it and flag a category.'],
                ['type' => 'new', 'text' => 'Under the hood: extensions can now add controls to a post’s action row and transform post content, so more features can ship as small, installable add-ons instead of bloating the core.'],
            ],
        ],
        [
            'tag' => '1.14.0',
            'date' => '2026-06-13',
            'title' => 'Database backups from the admin',
            'items' => [
                ['type' => 'new', 'text' => 'A new Admin → Backups page: back up your database with one click and download it, restore from a backup (a safety snapshot of the current database is always taken first), and schedule automatic daily or weekly backups with a retention limit.'],
                ['type' => 'new', 'text' => 'Optional offsite backups — point Convoro at an S3-compatible bucket and scheduled backups are mirrored there, so they survive losing the server.'],
                ['type' => 'changed', 'text' => 'Category icons: simplified to just the Font Awesome class field (the preset icon picker was removed).'],
            ],
        ],
        [
            'tag' => '1.13.1',
            'date' => '2026-06-12',
            'title' => 'Tidy up the discussion list',
            'items' => [
                ['type' => 'changed', 'text' => 'Staff badges no longer appear under avatars in the discussion list — they’re kept for the places they matter (inside a discussion and on profiles), so the list stays clean.'],
            ],
        ],
        [
            'tag' => '1.13.0',
            'date' => '2026-06-12',
            'title' => 'Federation Phase 3 — every member federates as themselves',
            'items' => [
                ['type' => 'new', 'text' => 'Each member now has their own fediverse identity — @you@your-community — instead of everything posting from a single community account. People on Mastodon and other servers can follow individual members, and your topics and replies go out attributed to you personally.'],
                ['type' => 'new', 'text' => 'Your fediverse handle appears on your profile so you can share it, and members can be discovered by handle from any fediverse server.'],
                ['type' => 'new', 'text' => 'A reply now reaches the right people automatically: the author’s own followers, the community’s followers, and the remote members already taking part in the thread.'],
            ],
        ],
        [
            'tag' => '1.12.2',
            'date' => '2026-06-12',
            'title' => 'Fix: federation handshake with Mastodon',
            'items' => [
                ['type' => 'fixed', 'text' => 'Outgoing federation messages (like the follow acknowledgement) were being rejected by Mastodon because the request signature covered the content-type header, which Mastodon validates differently. Signatures now match what Mastodon expects, so follows complete and posts deliver.'],
            ],
        ],
        [
            'tag' => '1.12.1',
            'date' => '2026-06-12',
            'title' => 'Fix: federation follows from the fediverse',
            'items' => [
                ['type' => 'fixed', 'text' => 'Follows from Mastodon and other fediverse servers were being rejected (the ActivityPub inbox was incorrectly subject to CSRF protection), so nobody could actually follow a federated community. Remote follows now go through.'],
            ],
        ],
        [
            'tag' => '1.12.0',
            'date' => '2026-06-12',
            'title' => 'Federation Phase 2 — two-way conversations',
            'items' => [
                ['type' => 'new', 'text' => 'Federation is now two-way. When someone on the fediverse replies to one of your discussions, their reply appears in the topic — shown with their fediverse handle and avatar and a 🌐 badge. Likes from the fediverse become reactions, and when a remote post is deleted it’s removed here too.'],
                ['type' => 'new', 'text' => 'Replies posted in your community are cross-posted back out to followers and to the fediverse users taking part in the thread, so the conversation stays in sync across servers.'],
            ],
        ],
        [
            'tag' => '1.11.0',
            'date' => '2026-06-12',
            'title' => 'Federation — join the fediverse',
            'items' => [
                ['type' => 'new', 'text' => 'Convoro now speaks ActivityPub. Turn on Federation under Admin → Settings and your community becomes a followable account on the fediverse — people on Mastodon, Lemmy and elsewhere can follow @your-community and every new discussion shows up in their feed, linking back to your forum. Discoverable via WebFinger; follows are accepted automatically and new topics are delivered to followers’ inboxes, signed.'],
            ],
        ],
        [
            'tag' => '1.10.1',
            'date' => '2026-06-12',
            'title' => 'Separate light & dark logos',
            'items' => [
                ['type' => 'added', 'text' => 'You can now upload a separate dark-mode logo under Admin → Settings. The header shows your light logo on light themes and your dark logo on dark themes; set just one and it’s used in both.'],
            ],
        ],
        [
            'tag' => '1.10.0',
            'date' => '2026-06-12',
            'title' => 'Translate the whole forum + pull-to-refresh',
            'items' => [
                ['type' => 'new', 'text' => 'With Auto-translate on, the forum list now translates too — discussion titles, post excerpts and category names appear in your language, not just the post bodies inside a topic. (Translations are cached, so it only costs an AI call the first time.)'],
                ['type' => 'new', 'text' => 'Pull to refresh: on phones and the installed app, pull down from the top to reload the page.'],
                ['type' => 'improved', 'text' => 'The mobile menu now includes the language picker and the auto-translate toggle, which were previously only on desktop.'],
                ['type' => 'fixed', 'text' => 'The mobile tab bar’s centre button now shows your community’s app icon (it was falling back to a plain plus when no separate logo/favicon was set).'],
            ],
        ],
        [
            'tag' => '1.9.1',
            'date' => '2026-06-12',
            'title' => 'Regenerate push keys from the admin',
            'items' => [
                ['type' => 'added', 'text' => 'Admin → PWA now has a “Push notification keys (VAPID)” panel showing whether push keys are set and how many devices are subscribed, with a one-click button to generate or regenerate the keys — handy if browser push ever stops working. (Regenerating asks members to re-enable push.)'],
            ],
        ],
        [
            'tag' => '1.9.0',
            'date' => '2026-06-12',
            'title' => 'Finer notification controls',
            'items' => [
                ['type' => 'new', 'text' => 'Notification preferences: for each kind of notification — replies, mentions, direct messages, reactions, profile posts and badges — choose whether you get an email, a browser push, both or neither. (The in-app bell is always on.) Set it under your profile → Settings → Notifications.'],
                ['type' => 'added', 'text' => 'A “Send a test notification” button in your push settings, so you can confirm browser push is actually working on your device.'],
                ['type' => 'improved', 'text' => 'The mobile tab bar’s centre button now shows your community’s logo or icon instead of a generic plus.'],
            ],
        ],
        [
            'tag' => '1.8.0',
            'date' => '2026-06-12',
            'title' => 'A mobile bottom tab bar',
            'items' => [
                ['type' => 'new', 'text' => 'Phones now get a bottom navigation tab bar — quick access to Home, Search, posting, notifications and your account, with a raised “Post” button in the centre and unread badges. Configure it under Admin → Settings → “Mobile tab bar”: turn it on or off, pick which tabs appear, and reorder them.'],
            ],
        ],
        [
            'tag' => '1.7.0',
            'date' => '2026-06-12',
            'title' => 'Import from Vanilla Forums & NodeBB',
            'items' => [
                ['type' => 'new', 'text' => 'Two more sources in the Import wizard: Vanilla Forums and NodeBB. Vanilla brings across categories, members, discussions and comments — converting each post from whatever format it was written in (HTML, Markdown, BBCode or Rich). NodeBB is unusual in that it stores its data in Redis rather than a SQL database, so connect it with your Redis host/port and Convoro reads it directly, converting Markdown posts to formatting. Members reset their password on first login.'],
            ],
        ],
        [
            'tag' => '1.6.0',
            'date' => '2026-06-12',
            'title' => 'Import from vBulletin 5 & 6',
            'items' => [
                ['type' => 'new', 'text' => 'The vBulletin importer now also handles vBulletin 5 and 6, which use a completely different “node” database. The wizard detects which generation you’re on automatically — one option covers vBulletin 3, 4, 5 and 6 — bringing across forums, members, threads and posts (with BBCode converted to formatting). As with vBulletin 3/4, members reset their password on first login.'],
            ],
        ],
        [
            'tag' => '1.5.1',
            'date' => '2026-06-12',
            'title' => 'Per-extension admin icons + Discourse import verified',
            'items' => [
                ['type' => 'improved', 'text' => 'Each add-on in the admin sidebar now has its own icon (announcement bar, analytics, privacy, social login, badges, RSS, queue dashboard…) instead of every one sharing the same puzzle-piece.'],
                ['type' => 'improved', 'text' => 'The Discourse importer is now verified end-to-end against a real Discourse 3.x instance (categories, users, topics and posts with their formatted HTML) — no longer “beta”.'],
            ],
        ],
        [
            'tag' => '1.5.0',
            'date' => '2026-06-12',
            'title' => 'MyBB & SMF imports — plus hardened XenForo & phpBB',
            'items' => [
                ['type' => 'new', 'text' => 'Two more sources in the Import wizard: MyBB (1.8) and SMF / Simple Machines Forum (2.0 & 2.1) — bringing across members, boards/forums, topics and posts, with BBCode converted to formatting.'],
                ['type' => 'improved', 'text' => 'The XenForo and phpBB importers are now verified against their real install schemas (no longer “beta”). XenForo member passwords now carry over correctly — a bug was skipping them — and phpBB no longer creates empty “category” placeholders for container forums.'],
                ['type' => 'improved', 'text' => 'Where a platform’s passwords can’t safely transfer (MyBB and SMF salt the hash in a non-portable way), those members simply reset their password on first login — clearly the safe choice rather than silently breaking sign-in.'],
            ],
        ],
        [
            'tag' => '1.4.0',
            'date' => '2026-06-12',
            'title' => 'Move in from Invision Community',
            'items' => [
                ['type' => 'new', 'text' => 'The Import wizard can now migrate from Invision Community (IPS 4 & 5) — members, forums, topics, posts, tags and reactions all come across. Member passwords carry over where possible (modern accounts log straight in; older legacy accounts simply reset on first login). Invision stores posts as rich HTML, so quotes and @mentions are converted to Convoro’s format automatically.'],
            ],
        ],
        [
            'tag' => '1.3.0',
            'date' => '2026-06-12',
            'title' => 'Drafts & scheduled posts',
            'items' => [
                ['type' => 'new', 'text' => 'Save a topic as a draft and finish it later — your title, body, category, tags, cover image and poll are all kept. Pick up where you left off from the new “Drafts” page in your account menu.'],
                ['type' => 'new', 'text' => 'Schedule a post to publish itself at a set date and time. Write it now, choose when it goes live, and Convoro posts it for you automatically — perfect for announcements and timing across time zones.'],
            ],
        ],
        [
            'tag' => '1.2.2',
            'date' => '2026-06-12',
            'title' => 'Lock topics',
            'items' => [
                ['type' => 'added', 'text' => 'Moderators can now lock a topic to stop new replies — useful for resolved questions or heated threads. Lock or unlock from the topic’s “⋯” menu; a locked topic shows a “Locked” badge and a clear notice in place of the reply box. Control who can lock under Members → Groups (the “Lock / unlock topics” permission).'],
            ],
        ],
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
            'Pin & lock topics',
            'Drafts & scheduled posts',
            'Invision Community importer (IPS 4 & 5)',
            'MyBB & SMF importers; XenForo & phpBB schema-verified',
            'vBulletin 5 & 6 (node) import + Discourse verified live',
            'Vanilla Forums & NodeBB (Redis) importers',
            'Advanced analytics & search',
            'Importers polished — every source schema-verified, Discourse & NodeBB tested live',
            'Configurable mobile bottom tab bar',
            'Per-type notification controls (email & push)',
            'Translate forum content (titles, excerpts, categories) + pull-to-refresh',
            'Federation (ActivityPub) — followable from Mastodon, Lemmy & the fediverse',
            'Federation Phase 2 — two-way replies, likes & cross-posting',
            'Federation Phase 3 — every member federates as their own fediverse identity',
            'Database backups — one-click, scheduled & offsite (S3)',
            'Grounded AI — Ask answers from your docs + a curated knowledge base, with citations',
            '@mention the assistant in any post for a grounded answer',
            'Accepted Answers extension — mark a reply as the solution in Q&A categories',
            'Top-contributors leaderboard extension',
            'Bookmarks extension — save posts to a reading list, from your account menu',
            'Richer composer — code blocks with syntax highlighting, spoilers & GIFs',
            'Trust levels — standing earned through participation (+ new-account spam gating)',
            'Reply by email — reply to a notification email to post',
            'Follow members + a personalized feed (extension)',
            'Events 2.0 — calendar & list views, RSVPs, reminders, categories, timezones & recurring events',
            'Server-side rendering + a site-wide performance pass (HTTP/2, asset compression & long-term caching, OPcache)',
            'Staff audit log — every moderation & admin action recorded, searchable & filterable',
            'Auto-saving drafts — the composer saves as you type and offers to restore unsaved work',
            'Grounded “support mode” — the assistant answers strictly from your knowledge base, with inline citations',
            'Expanded audit log — extension install/enable/disable and group changes are now recorded too',
            'Reply auto-save — replies are kept as you type too, and restored if you come back',
            'Spam, flood & abuse controls — slow mode, per-category cooldowns, hourly limits, duplicate-post blocking, banned words & link domains, and first-post approval',
        ],
        'now' => [],
        'next' => [],
        'later' => [],
    ],
];
