# Aurora — a theme for Convoro

A cosmic dark theme. A deep night-sky background with a slowly drifting
aurora-borealis backdrop, frosted-glass surfaces, soft cyan/pink glow and a
gradient that runs through buttons, badges and the scrollbar.

## Features

- **Animated aurora backdrop** — drifting violet/cyan/magenta/teal blobs over a
  starfield (respects `prefers-reduced-motion`).
- **Glassmorphism** — frosted, translucent cards, header and panels.
- **6 palettes** — Aurora, Sunset, Ocean, Forest, Nebula, Ember. Switch from the
  header; your choice is remembered per browser.
- **Light "Dawn" mode** — when the forum is in light mode, Aurora becomes a soft
  lavender variant.

## How it works

Aurora is a `type: theme` extension. Its `forum.js` injects a stylesheet that
remaps Convoro's `--c-*` design tokens to the Aurora palette, so the entire
forum restyles instantly, then layers on the backdrop, glass and glow. No build
step — it ships as plain JS.

Enable it from **Admin → Marketplace** (or the Extensions directory). Disable to
return to the default theme.
