<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const page = usePage();
const isAdmin = computed(() => !!(page.props as any).auth?.isAdmin);
const fonts = computed(() => ((page.props as any).themeFonts ?? []) as { value: string; label: string }[]);
const t = computed(() => (page.props as any).site?.theme ?? {});
const site = computed(() => (page.props as any).site ?? {});

const open = ref(false);
const saving = ref(false);
const tab = ref<'theme' | 'widgets' | 'a11y'>('theme');

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

// ── Theme form ────────────────────────────────────────────────────────────
const form = reactive({
  primary: '#5b5bd6',
  accent: '#8b5cf6',
  radius: 12,
  button_radius: '' as number | '',
  mode: 'light' as 'light' | 'dark',
  font: 'Inter',
  heading_font: '',
  font_size: 16,
  container: 1240,
  avatar_shape: 'circle' as 'circle' | 'rounded' | 'square',
  post_style: 'card' as 'card' | 'bordered' | 'flat',
  header_bg: 'surface' as 'surface' | 'brand' | 'custom',
  header_color: '#5b5bd6',
  density: 'comfortable' as 'compact' | 'comfortable' | 'spacious',
  link_color: 'primary' as 'primary' | 'accent',
  custom_css: '',
  logo: '',
  favicon: '',
});

function load() {
  const v = t.value;
  form.primary = v.primary ?? '#5b5bd6';
  form.accent = v.accent ?? '#8b5cf6';
  form.radius = v.radius ?? 12;
  form.button_radius = (v.buttonRadius ?? '') === '' ? '' : Number(v.buttonRadius);
  form.mode = v.mode ?? 'light';
  form.font = v.font ?? 'Inter';
  form.heading_font = v.headingFont ?? '';
  form.font_size = v.fontSize ?? 16;
  form.container = v.container ?? 1240;
  form.avatar_shape = v.avatarShape ?? 'circle';
  form.post_style = v.postStyle ?? 'card';
  form.header_bg = v.headerBg ?? 'surface';
  form.header_color = v.headerColor ?? '#5b5bd6';
  form.density = v.density ?? 'comfortable';
  form.link_color = v.linkColor ?? 'primary';
  form.custom_css = v.customCss ?? '';
  form.logo = site.value.logo ?? '';
  form.favicon = site.value.favicon ?? '';
  widgets.value = JSON.parse(JSON.stringify(site.value.widgets ?? []));
}

function hexRgb(hex: string): string {
  const h = hex.replace('#', '');
  const f = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
  const n = parseInt(f, 16);
  return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`;
}
function darkenRgb(hex: string, factor: number): string {
  return hexRgb(hex).split(' ').map((c) => Math.round(Number(c) * factor)).join(' ');
}

// Live preview — write straight to the document so it updates the real site.
function apply() {
  const r = document.documentElement;
  const btn = form.button_radius === '' ? form.radius : form.button_radius;
  r.style.setProperty('--c-primary', hexRgb(form.primary));
  r.style.setProperty('--c-accent', hexRgb(form.accent));
  r.style.setProperty('--c-accent-600', darkenRgb(form.accent, 0.9));
  r.style.setProperty('--c-radius', form.radius + 'px');
  r.style.setProperty('--c-radius-btn', btn + 'px');
  r.style.setProperty('--c-container', form.container > 0 ? form.container + 'px' : '100%');
  r.style.setProperty('--c-font-size', form.font_size + 'px');
  r.style.setProperty('--c-density', form.density === 'compact' ? '0.75' : form.density === 'spacious' ? '1.25' : '1');
  r.style.setProperty('--c-link', form.link_color === 'accent' ? 'var(--c-accent)' : 'var(--c-primary)');
  r.style.setProperty('--c-avatar-radius', form.avatar_shape === 'square' ? '6px' : form.avatar_shape === 'rounded' ? '14px' : '9999px');
  if (form.font) r.style.setProperty('--c-font', `'${form.font}', ui-sans-serif, system-ui, sans-serif`);
  r.style.setProperty('--c-font-heading', form.heading_font ? `'${form.heading_font}', var(--c-font)` : 'var(--c-font)');
  // Header bg + contrast text
  if (form.header_bg === 'brand') {
    r.style.setProperty('--c-header-bg', `rgb(${hexRgb(form.primary)})`);
    r.style.setProperty('--c-header-text', '255 255 255');
  } else if (form.header_bg === 'custom') {
    r.style.setProperty('--c-header-bg', `rgb(${hexRgb(form.header_color)})`);
    r.style.setProperty('--c-header-text', '255 255 255');
  } else {
    r.style.setProperty('--c-header-bg', 'rgb(var(--c-surface))');
    r.style.setProperty('--c-header-text', 'var(--c-text)');
  }
  r.dataset.theme = form.mode;
  r.dataset.postStyle = form.post_style;
  auditA11y();
}

function toggle() {
  if (!open.value) { load(); apply(); }
  open.value = !open.value;
}

function save() {
  saving.value = true;
  router.post('/admin/theme', { ...form, button_radius: form.button_radius === '' ? null : form.button_radius }, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => (saving.value = false),
  });
}

// ── Logo / favicon upload ───────────────────────────────────────────────
const uploading = ref('');
async function upload(field: 'logo' | 'favicon', e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  uploading.value = field;
  const body = new FormData();
  body.append('file', file);
  try {
    const res = await fetch('/admin/uploads/image', {
      method: 'POST',
      body,
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      credentials: 'same-origin',
    });
    const data = await res.json();
    if (data.url) form[field] = data.url;
  } catch { /* ignore */ } finally {
    uploading.value = '';
  }
}

// ── Widgets (drag & drop) ────────────────────────────────────────────────
type Widget = { id: string; type: string; title: string; body: string };
const widgets = ref<Widget[]>([]);
const WIDGET_TYPES = [
  { type: 'text', label: 'Text / HTML', defaultTitle: 'About' },
  { type: 'stats', label: 'Community stats', defaultTitle: 'Community stats' },
  { type: 'online_now', label: 'Online now', defaultTitle: 'Online now' },
  { type: 'newest_members', label: 'Newest members', defaultTitle: 'Newest members' },
  { type: 'top_posters', label: 'Top posters', defaultTitle: 'Top posters' },
  { type: 'categories', label: 'Categories', defaultTitle: 'Categories' },
];
const dragFrom = ref<number | null>(null);

function newId() {
  return 'w' + Date.now().toString(36) + Math.floor(Math.random() * 1e6).toString(36);
}
function addWidget(type: string) {
  const meta = WIDGET_TYPES.find((w) => w.type === type);
  widgets.value.push({ id: newId(), type, title: meta?.defaultTitle ?? '', body: '' });
}
function removeWidget(i: number) {
  widgets.value.splice(i, 1);
}
function onDragStart(i: number) { dragFrom.value = i; }
function onDrop(i: number) {
  if (dragFrom.value === null || dragFrom.value === i) { dragFrom.value = null; return; }
  const list = widgets.value;
  const [moved] = list.splice(dragFrom.value, 1);
  list.splice(i, 0, moved);
  dragFrom.value = null;
}
function saveWidgets() {
  saving.value = true;
  router.post('/admin/theme/widgets', { widgets: widgets.value }, {
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
const headerStyles = [
  { v: 'surface', label: 'Default' },
  { v: 'brand', label: 'Brand' },
  { v: 'custom', label: 'Custom' },
] as const;
const densities = [
  { v: 'compact', label: 'Compact' },
  { v: 'comfortable', label: 'Cozy' },
  { v: 'spacious', label: 'Spacious' },
] as const;
const containers = [
  { v: 1100, label: 'Narrow' },
  { v: 1240, label: 'Default' },
  { v: 1400, label: 'Wide' },
  { v: 1600, label: 'Extra wide' },
  { v: 0, label: 'Full' },
];
const labelCls = 'mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted';
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
    <div v-if="open" class="fixed inset-y-0 left-0 z-[71] flex w-[340px] max-w-[90vw] flex-col border-r border-line bg-surface shadow-2xl">
      <div class="flex items-center gap-2 border-b border-line px-5 py-4">
        <h2 class="text-base font-extrabold">Customize</h2>
        <span class="rounded-full bg-primary/15 px-2 py-0.5 text-[11px] font-bold text-primary">Live</span>
        <button type="button" class="ml-auto text-ink-muted hover:text-ink" @click="open = false" aria-label="Close">✕</button>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 border-b border-line px-3 pt-2">
        <button type="button" @click="tab = 'theme'" class="rounded-t-lg px-3 py-2 text-sm font-semibold"
          :class="tab === 'theme' ? 'bg-surface-2 text-ink' : 'text-ink-muted hover:text-ink'">Theme</button>
        <button type="button" @click="tab = 'widgets'" class="rounded-t-lg px-3 py-2 text-sm font-semibold"
          :class="tab === 'widgets' ? 'bg-surface-2 text-ink' : 'text-ink-muted hover:text-ink'">Widgets</button>
        <button type="button" @click="tab = 'a11y'; auditA11y()" class="flex items-center gap-1.5 rounded-t-lg px-3 py-2 text-sm font-semibold"
          :class="tab === 'a11y' ? 'bg-surface-2 text-ink' : 'text-ink-muted hover:text-ink'">
          A11y
          <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
            :class="a11yScore >= 90 ? 'bg-emerald-500/20 text-emerald-400' : a11yScore >= 75 ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400'">{{ a11yScore }}%</span>
        </button>
      </div>

      <!-- THEME tab -->
      <div v-show="tab === 'theme'" class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
        <!-- Mode -->
        <div>
          <label :class="labelCls">Appearance</label>
          <div class="flex gap-2">
            <button v-for="m in ['light','dark']" :key="m" type="button" @click="form.mode = m as any; apply()"
              class="flex-1 rounded-lg border px-3 py-2 text-sm font-semibold capitalize"
              :class="form.mode === m ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ m }}</button>
          </div>
        </div>

        <!-- Brand colors -->
        <div>
          <label :class="labelCls">Primary color</label>
          <div class="flex items-center gap-3">
            <input type="color" v-model="form.primary" @input="apply" class="h-10 w-14 cursor-pointer rounded border border-line bg-surface p-1" />
            <input type="text" v-model="form.primary" @input="apply" class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
          </div>
          <label :class="labelCls" class="mt-3">Accent color</label>
          <div class="flex items-center gap-3">
            <input type="color" v-model="form.accent" @input="apply" class="h-10 w-14 cursor-pointer rounded border border-line bg-surface p-1" />
            <input type="text" v-model="form.accent" @input="apply" class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
          </div>
          <div class="mt-3 flex items-center gap-2">
            <span class="text-xs font-semibold text-ink-muted">Links use</span>
            <button v-for="l in ['primary','accent']" :key="l" type="button" @click="form.link_color = l as any; apply()"
              class="rounded-lg border px-2.5 py-1 text-xs font-semibold capitalize"
              :class="form.link_color === l ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ l }}</button>
          </div>
        </div>

        <!-- Header -->
        <div>
          <label :class="labelCls">Header background</label>
          <div class="flex gap-2">
            <button v-for="h in headerStyles" :key="h.v" type="button" @click="form.header_bg = h.v; apply()"
              class="flex-1 rounded-lg border px-2 py-2 text-xs font-semibold"
              :class="form.header_bg === h.v ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ h.label }}</button>
          </div>
          <div v-if="form.header_bg === 'custom'" class="mt-2 flex items-center gap-3">
            <input type="color" v-model="form.header_color" @input="apply" class="h-9 w-12 cursor-pointer rounded border border-line bg-surface p-1" />
            <input type="text" v-model="form.header_color" @input="apply" class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
          </div>
        </div>

        <!-- Radii -->
        <div>
          <label :class="labelCls">Corner radius — {{ form.radius }}px</label>
          <input type="range" min="0" max="24" v-model.number="form.radius" @input="apply" class="w-full accent-primary" />
          <label :class="labelCls" class="mt-3">Button radius — {{ form.button_radius === '' ? 'match' : form.button_radius + 'px' }}</label>
          <div class="flex items-center gap-2">
            <input type="range" min="0" max="24" :value="form.button_radius === '' ? form.radius : form.button_radius"
              @input="(e) => { form.button_radius = Number((e.target as HTMLInputElement).value); apply(); }" class="w-full accent-primary" />
            <button type="button" class="shrink-0 rounded-lg border border-line px-2 py-1 text-[11px] font-semibold text-ink-2 hover:bg-surface-2" @click="form.button_radius = ''; apply()">Match</button>
          </div>
        </div>

        <!-- Fonts -->
        <div>
          <label :class="labelCls">Body font</label>
          <select v-model="form.font" @change="apply" class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary">
            <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
          <label :class="labelCls" class="mt-3">Heading font</label>
          <select v-model="form.heading_font" @change="apply" class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">Same as body</option>
            <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
          <label :class="labelCls" class="mt-3">Text size — {{ form.font_size }}px</label>
          <input type="range" min="12" max="20" v-model.number="form.font_size" @input="apply" class="w-full accent-primary" />
        </div>

        <!-- Density -->
        <div>
          <label :class="labelCls">Density</label>
          <div class="flex gap-2">
            <button v-for="d in densities" :key="d.v" type="button" @click="form.density = d.v; apply()"
              class="flex-1 rounded-lg border px-2 py-2 text-xs font-semibold"
              :class="form.density === d.v ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ d.label }}</button>
          </div>
        </div>

        <!-- Avatar shape -->
        <div>
          <label :class="labelCls">Avatar shape</label>
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
          <label :class="labelCls">Post containers</label>
          <div class="flex gap-2">
            <button v-for="s in postStyles" :key="s.v" type="button" @click="form.post_style = s.v; apply()"
              class="flex-1 rounded-lg border px-2 py-2 text-xs font-semibold"
              :class="form.post_style === s.v ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ s.label }}</button>
          </div>
        </div>

        <!-- Width -->
        <div>
          <label :class="labelCls">Content width</label>
          <select v-model.number="form.container" @change="apply" class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary">
            <option v-for="c in containers" :key="c.v" :value="c.v">{{ c.label }}</option>
          </select>
        </div>

        <!-- Branding: logo + favicon -->
        <div>
          <label :class="labelCls">Logo</label>
          <div class="flex items-center gap-3">
            <div class="grid h-10 min-w-[40px] place-items-center rounded-lg border border-line bg-surface-2 px-2">
              <img v-if="form.logo" :src="form.logo" class="h-7 max-w-[120px] object-contain" alt="logo" />
              <span v-else class="text-[11px] text-ink-muted">None</span>
            </div>
            <label class="cursor-pointer rounded-lg border border-line px-3 py-2 text-xs font-semibold text-ink-2 hover:bg-surface-2">
              {{ uploading === 'logo' ? 'Uploading…' : 'Upload' }}<input type="file" accept="image/*" class="hidden" @change="(e) => upload('logo', e)" />
            </label>
            <button v-if="form.logo" type="button" class="text-xs font-semibold text-ink-muted hover:text-red-400" @click="form.logo = ''">Clear</button>
          </div>
          <label :class="labelCls" class="mt-3">Favicon</label>
          <div class="flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-lg border border-line bg-surface-2">
              <img v-if="form.favicon" :src="form.favicon" class="h-6 w-6 object-contain" alt="favicon" />
              <span v-else class="text-[11px] text-ink-muted">—</span>
            </div>
            <label class="cursor-pointer rounded-lg border border-line px-3 py-2 text-xs font-semibold text-ink-2 hover:bg-surface-2">
              {{ uploading === 'favicon' ? 'Uploading…' : 'Upload' }}<input type="file" accept="image/*" class="hidden" @change="(e) => upload('favicon', e)" />
            </label>
            <button v-if="form.favicon" type="button" class="text-xs font-semibold text-ink-muted hover:text-red-400" @click="form.favicon = ''">Clear</button>
          </div>
        </div>

        <!-- Custom CSS -->
        <div>
          <label :class="labelCls">Custom CSS</label>
          <textarea v-model="form.custom_css" rows="5" spellcheck="false" placeholder=".q-post { ... }"
            class="w-full rounded-lg border-line bg-surface-2 font-mono text-xs text-ink focus:border-primary focus:ring-primary"></textarea>
          <p class="mt-1 text-[11px] text-ink-muted">Applied site-wide after the theme tokens. Save to preview.</p>
        </div>
      </div>

      <!-- WIDGETS tab -->
      <div v-show="tab === 'widgets'" class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
        <div>
          <label :class="labelCls">Add a widget</label>
          <div class="grid grid-cols-2 gap-2">
            <button v-for="w in WIDGET_TYPES" :key="w.type" type="button" @click="addWidget(w.type)"
              class="rounded-lg border border-line px-2.5 py-2 text-left text-xs font-semibold text-ink-2 hover:border-primary hover:bg-primary/5 hover:text-primary">
              + {{ w.label }}
            </button>
          </div>
        </div>

        <div>
          <label :class="labelCls">Forum sidebar — drag to reorder</label>
          <div v-if="!widgets.length" class="rounded-xl border border-dashed border-line p-6 text-center text-sm text-ink-muted">
            No widgets yet. Add some above — the default stats card shows until you do.
          </div>
          <div v-else class="space-y-2">
            <div v-for="(w, i) in widgets" :key="w.id" draggable="true"
              @dragstart="onDragStart(i)" @dragover.prevent @drop="onDrop(i)"
              class="rounded-xl border border-line bg-surface-2 p-3">
              <div class="flex items-center gap-2">
                <span class="cursor-grab text-ink-muted" title="Drag to reorder">⠿</span>
                <span class="text-sm font-semibold capitalize text-ink">{{ w.type.replace('_', ' ') }}</span>
                <button type="button" class="ml-auto text-xs font-semibold text-ink-muted hover:text-red-400" @click="removeWidget(i)">Remove</button>
              </div>
              <input v-model="w.title" placeholder="Title (optional)"
                class="mt-2 w-full rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary" />
              <textarea v-if="w.type === 'text'" v-model="w.body" rows="3" placeholder="Text or HTML…"
                class="mt-2 w-full rounded-lg border-line bg-surface text-xs text-ink focus:border-primary focus:ring-primary"></textarea>
            </div>
          </div>
          <p class="mt-2 text-[11px] text-ink-muted">Renders in the right rail of the community page for every visitor.</p>
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
          <label :class="labelCls">Color contrast (live)</label>
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
          <label :class="labelCls">Best practices</label>
          <ul class="divide-y divide-line rounded-xl border border-line">
            <li v-for="g in guidelines" :key="g.label" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
              <span class="text-ink-2">{{ g.label }}</span>
              <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="a11yStatusStyle(g.status)">{{ a11yStatusLabel(g.status) }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Footer -->
      <div class="border-t border-line px-5 py-4">
        <button v-if="tab === 'widgets'" type="button" :disabled="saving" @click="saveWidgets"
          class="w-full rounded-c bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-60">
          {{ saving ? 'Saving…' : 'Save widgets' }}
        </button>
        <button v-else type="button" :disabled="saving" @click="save"
          class="w-full rounded-c bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-60">
          {{ saving ? 'Saving…' : 'Save theme' }}
        </button>
        <p class="mt-2 text-center text-[11px] text-ink-muted">Changes preview live. Save to publish for everyone.</p>
      </div>
    </div>
  </div>
</template>
