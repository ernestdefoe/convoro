// Deterministic per-product accent + cover helpers, shared by the store cards
// and the product detail page so a product keeps the same colour everywhere.
const ACCENTS: [string, string][] = [
  ['#f472b6', '#db2777'], ['#60a5fa', '#2563eb'], ['#34d399', '#059669'], ['#fbbf24', '#d97706'],
  ['#a78bfa', '#7c3aed'], ['#22d3ee', '#0891b2'], ['#fb7185', '#e11d48'], ['#818cf8', '#4f46e5'],
];

// Brand-locked accents — these products always use their brand colour instead
// of a hashed one (e.g. Facebook Auto Post stays Facebook blue, in the new style).
const BRAND: { match: RegExp; colors: [string, string] }[] = [
  { match: /facebook/i, colors: ['#1877F2', '#0b57c4'] },
];

export function accentColors(key?: string | null): [string, string] {
  const k = String(key ?? '');
  for (const b of BRAND) if (b.match.test(k)) return b.colors;
  let h = 0;
  for (let i = 0; i < k.length; i++) h = (h * 31 + k.charCodeAt(i)) >>> 0;
  return ACCENTS[h % ACCENTS.length];
}

// A genuine uploaded screenshot only. Auto-generated covers (/storage/covers)
// and extension-shipped covers (/ext-asset, the old pinned style) are dropped so
// every card uses the uniform designed cover instead.
export function realScreenshot(img?: string | null): string | null {
  if (typeof img !== 'string' || !img) return null;
  return img.includes('/storage/covers/') || img.includes('/ext-asset/') ? null : img;
}

export function coverGradient(key?: string | null): string {
  const [a, b] = accentColors(key);
  return `linear-gradient(135deg,${a},${b})`;
}

export function coverBg(key?: string | null): string {
  const [a, b] = accentColors(key);
  return [
    `radial-gradient(60% 85% at 15% 15%, ${a}cc 0%, transparent 60%)`,
    `radial-gradient(55% 80% at 88% 25%, ${b}b3 0%, transparent 60%)`,
    `radial-gradient(70% 90% at 65% 115%, ${a}80 0%, transparent 60%)`,
    `#0a0d1a`,
  ].join(',');
}

// Drop any full-canvas background rect so an icon reads as a clean silhouette on
// the accent tile — this is what makes a 48×48 "logo" icon and a 24×24 line
// glyph render as the same uniform image style across every card.
export function stripIconBg(svg?: string | null): string | null {
  if (!svg) return null;
  return svg.replace(/<rect\b[^>]*\b(?:width|height)=["']?(?:48|100%)[^>]*?\/?>/gi, '');
}
