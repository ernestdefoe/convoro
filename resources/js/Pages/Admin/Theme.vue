<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  theme: { primary: string; radius: number; mode: string; font: string; font_size: number; container: number };
  fonts: { value: string; label: string; type: string }[];
}>();

const form = useForm({
  primary: props.theme.primary,
  radius: props.theme.radius,
  mode: props.theme.mode,
  font: props.theme.font,
  font_size: props.theme.font_size,
  container: props.theme.container,
});

// ---- fonts (mirror app/Support/Theme.php) ----
function fontType(family: string): string {
  return props.fonts.find((f) => f.value === family)?.type ?? 'sans';
}
function fontStack(family: string): string {
  const t = fontType(family);
  if (t === 'system') return "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
  const fb = t === 'serif' ? "Georgia, 'Times New Roman', serif" : t === 'mono' ? "ui-monospace, Menlo, monospace" : 'ui-sans-serif, system-ui, sans-serif';
  return `'${family}', ${fb}`;
}

const presets = [
  { name: 'Indigo', primary: '#5b5bd6', mode: 'light' },
  { name: 'Ocean', primary: '#2563eb', mode: 'light' },
  { name: 'Teal', primary: '#0d9488', mode: 'light' },
  { name: 'Forest', primary: '#16a34a', mode: 'light' },
  { name: 'Sunset', primary: '#f59e0b', mode: 'light' },
  { name: 'Rose', primary: '#e11d48', mode: 'light' },
  { name: 'Violet', primary: '#7c3aed', mode: 'light' },
  { name: 'Midnight', primary: '#818cf8', mode: 'dark' },
];

const containers = [
  { label: 'Compact', value: 1100 },
  { label: 'Default', value: 1240 },
  { label: 'Wide', value: 1400 },
  { label: 'Extra wide', value: 1600 },
  { label: 'Full width', value: 0 },
];

// ---- shade math (mirrors Theme.php) ----
function hexToRgb(hex: string): [number, number, number] {
  let h = hex.replace('#', '');
  if (h.length === 3) h = h.split('').map((c) => c + c).join('');
  if (!/^[0-9a-fA-F]{6}$/.test(h)) return [91, 91, 214];
  return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
}
const darken = (rgb: number[], f: number) => rgb.map((c) => Math.round(c * f));
const mixWhite = (rgb: number[], a: number) => rgb.map((c) => Math.round(c + (255 - c) * a));
const triplet = (rgb: number[]) => `${rgb[0]} ${rgb[1]} ${rgb[2]}`;

// ---- WCAG contrast (accessibility) for white text on the brand color ----
function luminance(rgb: number[]): number {
  const a = rgb.map((v) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
}
const contrast = computed(() => {
  const l1 = luminance([255, 255, 255]);
  const l2 = luminance(hexToRgb(form.primary));
  const [hi, lo] = l1 > l2 ? [l1, l2] : [l2, l1];
  const ratio = (hi + 0.05) / (lo + 0.05);
  const level = ratio >= 7 ? 'AAA' : ratio >= 4.5 ? 'AA' : ratio >= 3 ? 'AA Large only' : 'Fail';
  return { ratio: ratio.toFixed(2), level, ok: ratio >= 4.5, warn: ratio >= 3 && ratio < 4.5 };
});

function ensureFont(family: string) {
  const id = 'convoro-preview-font';
  const existing = document.getElementById(id) as HTMLLinkElement | null;
  if (family === 'System' || family === 'Inter') { existing?.remove(); return; }
  const href = `https://fonts.googleapis.com/css2?family=${family.replace(/ /g, '+')}:wght@400;500;600;700&display=swap`;
  if (existing) { existing.href = href; return; }
  const link = document.createElement('link');
  link.id = id; link.rel = 'stylesheet'; link.href = href;
  document.head.appendChild(link);
}

function applyLive() {
  const root = document.documentElement.style;
  const p = hexToRgb(form.primary);
  root.setProperty('--c-primary', triplet(p));
  root.setProperty('--c-primary-600', triplet(darken(p, 0.9)));
  root.setProperty('--c-primary-700', triplet(darken(p, 0.8)));
  root.setProperty('--c-primary-soft', triplet(mixWhite(p, 0.86)));
  root.setProperty('--c-radius', `${form.radius}px`);
  root.setProperty('--c-container', form.container > 0 ? `${form.container}px` : '100%');
  root.setProperty('--c-font', fontStack(form.font));
  root.setProperty('--c-font-size', `${form.font_size}px`);
  ensureFont(form.font);
  document.documentElement.dataset.theme = form.mode === 'system'
    ? ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light')
    : form.mode;
}

watch(() => [form.primary, form.radius, form.mode, form.font, form.font_size, form.container], applyLive, { immediate: true });

onBeforeUnmount(() => {
  ['--c-primary', '--c-primary-600', '--c-primary-700', '--c-primary-soft', '--c-radius', '--c-container', '--c-font', '--c-font-size']
    .forEach((v) => document.documentElement.style.removeProperty(v));
  document.getElementById('convoro-preview-font')?.remove();
  document.documentElement.dataset.theme = props.theme.mode; // restore saved
});

function applyPreset(p: { primary: string; mode: string }) {
  form.primary = p.primary;
  form.mode = p.mode;
}

function save() {
  form.post('/admin/theme', { preserveScroll: true });
}
</script>

<template>
  <Head :title="t('Admin · Theme')" />
  <AdminLayout>
    <template #title>{{ t('Theme editor') }}</template>
    <template #subtitle>{{ t('Changes preview live across the whole site — Save to publish for everyone') }}</template>

    <div class="grid gap-6 xl:grid-cols-[380px_1fr]">
      <!-- Controls -->
      <form class="space-y-4" @submit.prevent="save">
        <!-- Presets -->
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-ink-2">{{ t('Presets') }}</h3>
          <div class="grid grid-cols-4 gap-2">
            <button v-for="p in presets" :key="p.name" type="button" class="group rounded-xl border border-line p-2 text-center hover:border-line" @click="applyPreset(p)">
              <span class="mx-auto block h-8 w-8 rounded-lg" :style="{ background: p.primary }" />
              <span class="mt-1 block text-[11px] text-ink-2 group-hover:text-ink">{{ t(p.name) }}</span>
            </button>
          </div>
        </section>

        <!-- Colors -->
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-ink-2">{{ t('Colors') }}</h3>
          <label class="block text-sm font-medium text-ink-2">{{ t('Brand color') }}</label>
          <div class="mt-2 flex items-center gap-3">
            <input v-model="form.primary" type="color" class="h-10 w-12 cursor-pointer rounded border-line bg-transparent" />
            <input v-model="form.primary" type="text" class="w-32 rounded-lg border-line bg-appbg font-mono text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" />
          </div>

          <!-- WCAG / ADA contrast check for white button text -->
          <div class="mt-3 flex items-center gap-2 rounded-lg border border-line bg-appbg px-3 py-2">
            <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-bold"
                  :class="contrast.ok ? 'bg-emerald-500/20 text-emerald-400' : contrast.warn ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400'">
              {{ contrast.ok ? '✓' : '!' }}
            </span>
            <span class="text-sm text-ink-2">{{ t('White text contrast {ratio}:1', { ratio: contrast.ratio }) }}</span>
            <span class="ml-auto text-xs font-semibold"
                  :class="contrast.ok ? 'text-emerald-400' : contrast.warn ? 'text-amber-400' : 'text-red-400'">{{ t('WCAG {level}', { level: t(contrast.level) }) }}</span>
          </div>
          <p v-if="!contrast.ok" class="mt-1.5 text-xs text-ink-muted">
            {{ t('Aim for 4.5:1 (AA) so button labels stay readable for everyone — try a darker brand color.') }}
          </p>
        </section>

        <!-- Appearance -->
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-ink-2">{{ t('Appearance') }}</h3>
          <div class="grid grid-cols-2 gap-2">
            <button v-for="m in ['light','dark','system']" :key="m" type="button" class="rounded-lg border px-3 py-2 text-sm font-semibold capitalize" :class="form.mode === m ? 'border-indigo-500 bg-indigo-500/10 text-ink' : 'border-line text-ink-2 hover:text-ink'" @click="form.mode = m">{{ t(m) }}</button>
          </div>
        </section>

        <!-- Typography -->
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-ink-2">{{ t('Typography') }}</h3>
          <label class="block text-sm font-medium text-ink-2">{{ t('Font') }}</label>
          <select v-model="form.font" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500">
            <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
          <label class="mt-4 block text-sm font-medium text-ink-2">{{ t('Base size — {size}px', { size: form.font_size }) }}</label>
          <input v-model.number="form.font_size" type="range" min="12" max="20" class="mt-2 w-full accent-indigo-500" />
        </section>

        <!-- Layout -->
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-ink-2">{{ t('Layout') }}</h3>
          <label class="block text-sm font-medium text-ink-2">{{ t('Corner radius — {radius}px', { radius: form.radius }) }}</label>
          <input v-model.number="form.radius" type="range" min="0" max="28" class="mt-2 w-full accent-indigo-500" />
          <label class="mt-4 block text-sm font-medium text-ink-2">{{ t('Content width') }}</label>
          <select v-model.number="form.container" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500">
            <option v-for="c in containers" :key="c.value" :value="c.value">{{ t(c.label) }}{{ c.value ? ` (${c.value}px)` : '' }}</option>
          </select>
        </section>

        <div class="sticky bottom-4 flex items-center gap-3 rounded-2xl border border-line bg-surface p-4">
          <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">{{ t('Save theme') }}</button>
          <span v-if="form.recentlySuccessful" class="text-sm text-emerald-400">{{ t('Saved for everyone.') }}</span>
          <span class="ml-auto text-xs text-ink-muted">{{ t('Live preview →') }}</span>
        </div>
      </form>

      <!-- Live preview (real Convoro tokens; reflects light/dark + font + radius + colors) -->
      <div class="overflow-hidden rounded-2xl border border-line">
        <div class="bg-appbg p-5 text-ink" style="font-family: var(--c-font); font-size: var(--c-font-size);">
          <!-- mock header -->
          <div class="flex items-center gap-3 rounded-c border border-line bg-surface px-4 py-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-c bg-primary font-bold text-ink">C</span>
            <span class="font-extrabold tracking-tight">Convoro</span>
            <span class="ml-auto rounded-full bg-primary px-3 py-1 text-xs font-semibold text-ink">{{ t('Join') }}</span>
          </div>

          <!-- mock topic card -->
          <div class="mt-4 rounded-c border border-line bg-surface p-5">
            <div class="flex items-center gap-2">
              <span class="h-9 w-9 rounded-full bg-primary-soft"></span>
              <div>
                <div class="font-bold">{{ t('Welcome to the community 👋') }}</div>
                <div class="text-sm text-ink-muted">{{ t('Posted by {name} · {count} replies', { name: 'Riley', count: 3 }) }}</div>
              </div>
            </div>
            <p class="mt-3 text-ink-2">{{ t('The quick brown fox jumps over the lazy dog. This sample shows your font, size, colors and corners in context.') }}</p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
              <button class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-ink hover:bg-primary-600">{{ t('Reply') }}</button>
              <button class="rounded-c bg-primary-soft px-4 py-2 text-sm font-semibold text-primary-700">{{ t('React') }}</button>
              <span class="rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary-700">#announcement</span>
              <a class="ml-1 text-sm font-semibold text-primary-700 hover:underline">{{ t('View thread →') }}</a>
            </div>
          </div>

          <div class="mt-3 grid grid-cols-3 gap-3">
            <div v-for="n in 3" :key="n" class="rounded-c border border-line bg-surface p-3 text-sm">
              <div class="h-2 w-2/3 rounded bg-primary-soft"></div>
              <div class="mt-2 text-ink-muted">{{ t('Card {n}', { n }) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
