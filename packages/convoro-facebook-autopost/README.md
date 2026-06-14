# Facebook Auto Post

A first-party Convoro extension that automatically shares every new topic to your
Facebook Page.

When a member publishes a topic, Convoro posts a link to your Page via the
Facebook Graph API — with a customizable message, an optional excerpt, and
per-category control. It's dependency-free (Laravel's HTTP client only), so it
runs on any host.

## Setup

1. Enable the extension in **Admin → Marketplace**.
2. Open its settings (**Admin → Marketplace → Facebook Auto Post**).
3. Create a **Page Access Token** in the
   [Graph API Explorer](https://developers.facebook.com/tools/explorer/) (or a
   long-lived System User token) with `pages_manage_posts` and
   `pages_read_engagement`.
4. Paste your **Page ID** (Page → About) and the token, then click
   **Send a test post** to confirm it works.
5. Toggle **Enable auto-posting** on.

## How it works

- Listens to the core `App\Events\TopicPublished` event, which fires only for
  genuinely published topics (composer, drafts, scheduled posts) — **never** for
  imports, seeds, or demo content.
- Posting runs on the queue, so it never slows down publishing.
- Each topic is recorded in the `facebook_posts` table (status + FB post id), so
  a topic is never posted twice.

## Message template

The message supports these placeholders:

| Placeholder  | Value                          |
|--------------|--------------------------------|
| `{title}`    | The topic title                |
| `{author}`   | The member who posted it       |
| `{category}` | The topic's category name      |
| `{excerpt}`  | Excerpt of the opening post    |

The topic's URL is always attached — Facebook builds a rich link preview from the
page's Open Graph tags.
