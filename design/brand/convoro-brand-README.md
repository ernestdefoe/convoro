# Convoro brand pack

Brand color: #5b5bd6 (indigo). Gradient: #6366f1 → #4338ca → #312e81.
Mark: speech bubble with typing-indicator dots. Wordmark: Poppins Bold.

## Logos
- convoro-logo-horizontal-{light,dark}.(svg/png) — primary, header use
- convoro-logo-stacked-{light,dark}.(svg/png) — square-ish spaces
- convoro-mark-{light,dark}.(svg/png) — bubble only (light = indigo bubble; dark = white bubble)

## Icons (app/site)
- convoro-icon.svg + convoro-icon-{512,192,180,152,144,120,96,72,64,48,32,16}.png
- convoro-icon-maskable-512.png — Android/PWA maskable (safe-zone padded)
- convoro-apple-touch-180.png — <link rel="apple-touch-icon">
- favicon.ico (16/32/48) + convoro-favicon-{16,32,48}.png
- site.webmanifest — PWA manifest (icons + theme color)

## Social / avatar
- convoro-avatar-{512,256}.png + ...-circle-{512,256}.png
- convoro-facebook-profile-1024.png — page profile pic (full-bleed, circle-crop safe)
- convoro-facebook-cover-1640x624.png — page cover
- convoro-og-1200x630.png — Open Graph / Twitter card

## HTML <head> snippet
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/convoro-favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/convoro-apple-touch-180.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#5b5bd6">
