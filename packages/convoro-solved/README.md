# Accepted Answers (convoro-solved)

A first-party Convoro extension that turns categories into **Q&A**.

In a Q&A category, the person who started the topic — or any moderator/admin —
can mark a reply as the **accepted answer**. The reply gets a green check and
the thread reads as solved. Ideal for help and support communities.

## How it works

- **Admin → Marketplace → Accepted Answers** (`/admin/ext/solved`): tick which
  categories should behave as Q&A.
- In a topic inside a Q&A category, a **Mark as answer** control appears in each
  reply's action row (for the asker and staff). Marking one clears any previous
  answer; clicking the accepted reply again unmarks it.

## What it adds

- `categories.is_qa` and `topics.solved_post_id` columns (removed on uninstall).
- Read/toggle API under `/api/ext/solved/...`.
- A forum bundle that renders into the core `post:actions` slot.

No configuration is needed beyond choosing the Q&A categories. Nothing changes
for categories you don't flag.

MIT licensed.
