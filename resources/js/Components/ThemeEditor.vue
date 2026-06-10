<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const page = usePage();
const isAdmin = computed(() => !!(page.props as any).auth?.isAdmin);
const fonts = computed(() => ((page.props as any).themeFonts ?? []) as { value: string; label: string }[]);
const t = computed(() => (page.props as any).site?.theme ?? {});

const open = ref(false);
const saving = ref(false);
const tab = ref<'theme' | 'a11y'>('theme');

// ── Accessibility audit (live, recomputed as colors change) ──────────────
type Pair = { label: string; fg: string; bg: string; min: number };
const A11Y_PAIRS: Pair[] = [
  { label: 'Body text on background', fg: '--c-text', bg: '--c-bg', min: 4.5 },
  { label: 'Body text on cards', fg: '--c-text', bg: '--c-surface', min: 4.5 },
  { label: 'Secondary text on cards', fg: '--c-text-2', bg: '--c-surface', min: 4.5 },
  { label: 'Muted text on background', fg: '--c-muted', bg: '--c-bg', min: 3 },
  { label: 'White text on brand buttons', fg: '#fff', bg: '--c-primary', min: 4.5 },
  { label: 'Links/badges (brand on soft)', fg: '--c-primary-700', bg: '--c-primary-soft', min: 4.5 },
];
const guidelines = [
  { label: 'Keyboard navigation + visible focus rings', status: 'pass' },
  { label: 'Semantic landmarks (header, nav, main)', status: 'pass' },
  { label: 'Skip-to-content link', status: 'pass' },
  { label: 'All form fields have labels', status: 'pass' },
  { label: 'Light and dark themes available', status: 'pass' },
  { label: 'Respects “reduce motion” preference', status: 'pass' },
  { label: 'Alt text captured on uploaded images', status: 'todo' },
  { label: 'Full screen-reader audit (ARIA roles)', status: 'partial' },
];
const a11yResults = ref<{ label: string; ratio: number; min: number; ok: boolean }[]>([]);

function a11yRgb(token: string): number[] {
  if (token === '#fff') return [255, 255, 255];
  const v = getComputedStyle(document.documentElement).getPropertyValue(token).trim();
  const parts = v.split(/\s+/).map(Number);
  return parts.length === 3 && parts.every((n) => !Number.isNaN(n)) ? parts : [0, 0, 0];
}
function a11yLum(c: number[]): number {
  const a = c.map((v) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
}
function a11yRatio(fg: string, bg: string): number {
  const l1 = a11yLum(a11yRgb(fg));
  const l2 = a11yLum(a11yRgb(bg));
  const [hi, lo] = l1 > l2 ? [l1, l2] : [l2, l1];
  return (hi + 0.05) / (lo + 0.05);
}
function auditA11y() {
  a11yResults.value = A11Y_PAIRS.map((p) => {
    const r = a11yRatio(p.fg, p.bg);
    return { label: p.label, ratio: Math.round(r * 100) / 100, min: p.min, ok: r >= p.min };
  });
}
const a11yScore = computed(() => {
  const cPass = a11yResults.value.filter((r) => r.ok).length;
  const cTotal = a11yResults.value.length || 1;
  const gPass = guidelines.filter((g) => g.status === 'pass').length;
  const gPartial = guidelines.filter((g) => g.status === 'partial').length;
  return Math.round(((cPass + gPass + gPartial * 0.5) / (cTotal + guidelines.length)) * 100);
});
const a11yGrade = computed(() => (a11yScore.value >= 90 ? 'Excellent' : a11yScore.value >= 75 ? 'Good' : a11yScore.value >= 50 ? 'Fair' : 'Needs work'));
const a11yStatusStyle = (s: string) =>
  s === 'pass' ? 'bg-emerald-500/20 text-emerald-400' : s === 'partial' ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-500/20 text-slate-400';
const a11yStatusLabel = (s: string) => (s === 'pass' ? 'Pass' : s === 'partial' ? 'Partial' : 'To do');

const form = reactive({
  primary: '#5b5bd6',
  radius: 12,
  mode: 'light' as 'light' | 'dark',
  font: 'Inter',
  font_size: 16,
  container: 1240,
  avatar_shape: 'circle' as 'circle' | 'rounded' | 'square',
  post_style: 'card' as 'card' | 'bordered' | 'flat',
});

function load() {
  const v = t.value;
  form.primary = v.primary ?? '#5b5bd6';
  form.radius = v.radius ?? 12;
  form.mode = v.mode ?? 'light';
  form.font = v.font ?? 'Inter';
  form.font_size = v.fontSize ?? 16;
  form.container = v.container ?? 1240;
  form.avatar_shape = v.avatarShape ?? 'circle';
  form.post_style = v.postStyle ?? 'card';
}

function hexRgb(hex: string): string {
  const h = hex.replace('#', '');
  const f = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
  const n = parseInt(f, 16);
  return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`;
}

// Live preview — write straight to the document so it updates the real site.
function apply() {
  const r = document.documentElement;
  r.style.setProperty('--c-primary', hexRgb(form.primary));
  r.style.setProperty('--c-radius', form.radius + 'px');
  r.style.setProperty('--c-container', form.container > 0 ? form.container + 'px' : '100%');
  r.style.setProperty('--c-font-size', form.font_size + 'px');
  r.style.setProperty('--c-avatar-radius', form.avatar_shape === 'square' ? '6px' : form.avatar_shape === 'rounded' ? '14px' : '9999px');
  if (form.font) r.style.setProperty('--c-font', `'${form.font}', ui-sans-serif, system-ui, sans-serif`);
  r.dataset.theme = form.mode;
  r.dataset.postStyle = form.post_style;
  auditA11y();
}

function toggle() {
  if (!open.value) { load(); auditA11y(); }
  open.value = !open.value;
}

function save() {
  saving.value = true;
  router.post('/admin/theme', { ...form }, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => (saving.value = false),
  });
}

const avatarShapes = [
  { v: 'circle', label: 'Circle' },
  { v: 'rounded', label: 'Rounded' },
  { v: 'square', label: 'Square' },
] as const;
const postStyles = [
  { v: 'card', label: 'Card' },
  { v: 'bordered', label: 'Bordered' },
  { v: 'flat', label: 'Flat' },
] as const;
const containers = [
  { v: 1100, label: 'Narrow' },
  { v: 1240, label: 'Default' },
  { v: 1400, label: 'Wide' },
  { v: 1600, label: 'Extra wide' },
  { v: 0, label: 'Full' },
];
</script>

<template>
  <div v-if="isAdmin">
    <!-- Launcher -->
    <button v-if="!open" type="button" @click="toggle"
      class="fixed bottom-5 left-5 z-[70] flex h-12 w-12 items-center justify-center rounded-full bg-primary text-white shadow-xl shadow-primary/40 hover:bg-primary-600"
      title="Theme editor" aria-label="Open theme editor">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 1 0 0 20 2 2 0 0 0 2-2 1.5 1.5 0 0 1 1.5-1.5H18a4 4 0 0 0 4-4 10 10 0 0 0-10-10z"/></svg>
    </button>

    <!-- Drawer -->
    <div v-if="open" class="fixed inset-y-0 left-0 z-[71] flex w-[320px] max-w-[88vw] flex-col border-r border-line bg-surface shadow-2xl">
      <div class="flex items-center gap-2 border-b border-line px-5 py-4">
        <h2 class="text-base font-extrabold">Appearance</h2>
        <span class="rounded-full bg-primary/15 px-2 py-0.5 text-[11px] font-bold text-primary">Live</span>
        <button type="button" class="ml-auto text-ink-muted hover:text-ink" @click="open = false" aria-label="Close">✕</button>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 border-b border-line px-3 pt-2">
        <button type="button" @click="tab = 'theme'"
          class="rounded-t-lg px-3 py-2 text-sm font-semibold"
          :class="tab === 'theme' ? 'bg-surface-2 text-ink' : 'text-ink-muted hover:text-ink'">Theme</button>
        <button type="button" @click="tab = 'a11y'; auditA11y()"
          class="flex items-center gap-1.5 rounded-t-lg px-3 py-2 text-sm font-semibold"
          :class="tab === 'a11y' ? 'bg-surface-2 text-ink' : 'text-ink-muted hover:text-ink'">
          Accessibility
          <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
            :class="a11yScore >= 90 ? 'bg-emerald-500/20 text-emerald-400' : a11yScore >= 75 ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400'">{{ a11yScore }}%</span>
        </button>
      </div>

      <!-- THEME tab -->
      <div v-show="tab === 'theme'" class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
        <!-- Mode -->
        <div>
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted">Appearance</label>
          <div class="flex gap-2">
            <button v-for="m in ['light','dark']" :key="m" type="button" @click="form.mode = m as any; apply()"
              class="flex-1 rounded-lg border px-3 py-2 text-sm font-semibold capitalize"
              :class="form.mode === m ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ m }}</button>
          </div>
        </div>

        <!-- Primary color -->
        <div>
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted">Primary color</label>
          <div class="flex items-center gap-3">
            <input type="color" v-model="form.primary" @input="apply" class="h-10 w-14 cursor-pointer rounded border border-line bg-surface p-1" />
            <input type="text" v-model="form.primary" @input="apply" class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
          </div>
        </div>

        <!-- Corner radius -->
        <div>
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted">Corner radius — {{ form.radius }}px</label>
          <input type="range" min="0" max="24" v-model.number="form.radius" @input="apply" class="w-full accent-primary" />
        </div>

        <!-- Font -->
        <div>
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted">Font</label>
          <select v-model="form.font" @change="apply" class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary">
            <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
          <label class="mb-1 mt-3 block text-xs font-bold uppercase tracking-wide text-ink-muted">Text size — {{ form.font_size }}px</label>
          <input type="range" min="12" max="20" v-model.number="form.font_size" @input="apply" class="w-full accent-primary" />
        </div>

        <!-- Avatar shape -->
        <div>
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted">Avatar shape</label>
          <div class="flex gap-2">
            <button v-for="s in avatarShapes" :key="s.v" type="button" @click="form.avatar_shape = s.v; apply()"
              class="flex flex-1 flex-col items-center gap-1.5 rounded-lg border px-2 py-2 text-xs font-semibold"
              :class="form.avatar_shape === s.v ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">
              <span class="h-6 w-6 bg-ink-muted" :style="{ borderRadius: s.v === 'square' ? '4px' : s.v === 'rounded' ? '8px' : '9999px' }"></span>
              {{ s.label }}
            </button>
          </div>
        </div>

        <!-- Post container style -->
        <div>
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted">Post containers</label>
          <div class="flex gap-2">
            <button v-for="s in postStyles" :key="s.v" type="button" @click="form.post_style = s.v; apply()"
              class="flex-1 rounded-lg border px-2 py-2 text-xs font-semibold"
              :class="form.post_style === s.v ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ s.label }}</button>
          </div>
        </div>

        <!-- Width -->
        <div>
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted">Content width</label>
          <select v-model.number="form.container" @change="apply" class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary">
            <option v-for="c in containers" :key="c.v" :value="c.v">{{ c.label }}</option>
          </select>
        </div>
      </div>

      <!-- ACCESSIBILITY tab -->
      <div v-show="tab === 'a11y'" class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
        <div class="flex items-center gap-4 rounded-xl border border-line bg-surface-2 p-4">
          <div class="text-3xl font-extrabold text-ink">{{ a11yScore }}%</div>
          <div>
            <div class="text-sm font-bold text-ink">{{ a11yGrade }}</div>
            <div class="text-[11px] text-ink-muted">WCAG / ADA compliance for the live theme</div>
          </div>
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted">Color contrast (live)</label>
          <ul class="divide-y divide-line rounded-xl border border-line">
            <li v-for="r in a11yResults" :key="r.label" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
              <span class="text-ink-2">{{ r.label }}</span>
              <span class="flex shrink-0 items-center gap-2">
                <span class="font-mono text-xs text-ink-muted">{{ r.ratio }}:1</span>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="r.ok ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">{{ r.ok ? 'AA' : 'Fail' }}</span>
              </span>
            </li>
          </ul>
          <p class="mt-2 text-[11px] text-ink-muted">Tweak colors on the Theme tab — these update instantly.</p>
        </div>

        <div>
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted">Best practices</label>
          <ul class="divide-y divide-line rounded-xl border border-line">
            <li v-for="g in guidelines" :key="g.label" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
              <span class="text-ink-2">{{ g.label }}</span>
              <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="a11yStatusStyle(g.status)">{{ a11yStatusLabel(g.status) }}</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="border-t border-line px-5 py-4">
        <button type="button" :disabled="saving" @click="save" class="w-full rounded-c bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-60">
          {{ saving ? 'Saving…' : 'Save theme' }}
        </button>
        <p class="mt-2 text-center text-[11px] text-ink-muted">Changes preview live. Save to publish for everyone.</p>
      </div>
    </div>
  </div>
</template>
