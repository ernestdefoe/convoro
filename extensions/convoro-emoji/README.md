# Convoro Emoji Picker

A standalone [Convoro](https://convoro.co) extension that adds an **emoji picker** to the composer toolbar — in topics, replies and direct messages. Click the 😊 button to browse by category, search by name, or pick from your recents; the emoji is inserted at the cursor.

- Themed with the forum's CSS variables, so it matches light/dark automatically.
- No build step — `assets/forum.js` is a prebuilt vanilla-JS ESM that hooks into the Convoro extension runtime.
- Frontend-only: no PHP, no migrations, no database.

## Requirements

Convoro **>= 0.36.0** (which adds the `composer:toolbar` extension slot the picker registers into).

## Install

Install from the in-app **Marketplace** (Admin → Marketplace):

- **Upload a zip:** download a release archive of this repo and upload it, or
- **Composer/registry:** add the repo to the Convoro extension registry.

Then enable **Emoji Picker**. That's it — the 😊 button appears in every composer.

## How it works

`assets/forum.js` calls `window.Convoro.registerSlot('composer:toolbar', { mount })`. The host passes an `insertText(text)` helper in the slot context, which the picker uses to insert the chosen emoji into the active editor — so it never depends on the editor's internals.

## License

MIT © Convoro
