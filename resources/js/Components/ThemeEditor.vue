<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { toast } from '@/lib/toast';
import { t as tr } from '@/lib/i18n';
import { convoro } from '@/lib/convoro-ext';

const page = usePage();
const isAdmin = computed(() => !!(page.props as any).auth?.isAdmin);
const fonts = computed(() => ((page.props as any).themeFonts ?? []) as { value: string; label: string }[]);
const t = computed(() => (page.props as any).site?.theme ?? {});
const site = computed(() => (page.props as any).site ?? {});

const open = ref(false);
const saving = ref(false);
const ready = ref(false); // true once the panel is open + populated (gates autosave)
const tab = ref<'theme' | 'widgets' | 'a11y'>('theme');

// ── Accessibility audit (live, recomputed as colors change) ──────────────
// `fix` points at the Theme-tab control that resolves the failing pair, plus a
// short hint shown when the user clicks through.
type FixTarget = 'appearance' | 'brand' | 'text';
type Pair = { label: string; fg: string; bg: string; min: number; fix: FixTarget; hint: string };
const A11Y_PAIRS: Pair[] = [
  { label: tr('Body text on background'), fg: '--c-text', bg: '--c-bg', min: 4.5, fix: 'appearance', hint: tr('Switch the appearance mode — text/background colors come from the light or dark preset.') },
  { label: tr('Body text on cards'), fg: '--c-text', bg: '--c-surface', min: 4.5, fix: 'appearance', hint: tr('Switch the appearance mode — card colors come from the light or dark preset.') },
  { label: tr('Secondary text on cards'), fg: '--c-text-2', bg: '--c-surface', min: 4.5, fix: 'appearance', hint: tr('Switch the appearance mode to improve secondary-text contrast.') },
  { label: tr('Muted text on background'), fg: '--c-muted', bg: '--c-bg', min: 3, fix: 'text', hint: tr('Darken the “Muted text color” below until this passes.') },
  { label: tr('White text on brand buttons'), fg: '#fff', bg: '--c-primary', min: 4.5, fix: 'brand', hint: tr('Darken the Primary color so white button text meets contrast.') },
  { label: tr('Links/badges (brand on soft)'), fg: '--c-primary-700', bg: '--c-primary-soft', min: 4.5, fix: 'brand', hint: tr('Adjust the Primary color so links and badges meet contrast.') },
];
const guidelines = [
  { label: tr('Keyboard navigation + visible focus rings'), status: 'pass' },
  { label: tr('Semantic landmarks (header, nav, main)'), status: 'pass' },
  { label: tr('Skip-to-content link'), status: 'pass' },
  { label: tr('All form fields have labels'), status: 'pass' },
  { label: tr('Light and dark themes available'), status: 'pass' },
  { label: tr('Respects “reduce motion” preference'), status: 'pass' },
  { label: tr('Alt text captured on uploaded images'), status: 'todo' },
  { label: tr('Full screen-reader audit (ARIA roles)'), status: 'partial' },
];
const a11yResults = ref<{ label: string; ratio: number; min: number; ok: boolean; fix: FixTarget; hint: string }[]>([]);
const flashSection = ref<FixTarget | ''>('');
const a11yHint = ref('');

// Jump from a failing check to the Theme-tab control that fixes it.
function jumpToFix(section: FixTarget, hint = '') {
  tab.value = 'theme';
  a11yHint.value = hint;
  nextTick(() => {
    const el = document.getElementById('sec-' + section);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    flashSection.value = section;
    setTimeout(() => { if (flashSection.value === section) flashSection.value = ''; }, 1800);
  });
}

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
    return { label: p.label, ratio: Math.round(r * 100) / 100, min: p.min, ok: r >= p.min, fix: p.fix, hint: p.hint };
  });
}
const a11yScore = computed(() => {
  const cPass = a11yResults.value.filter((r) => r.ok).length;
  const cTotal = a11yResults.value.length || 1;
  const gPass = guidelines.filter((g) => g.status === 'pass').length;
  const gPartial = guidelines.filter((g) => g.status === 'partial').length;
  return Math.round(((cPass + gPass + gPartial * 0.5) / (cTotal + guidelines.length)) * 100);
});
const a11yGrade = computed(() => (a11yScore.value >= 90 ? tr('Excellent') : a11yScore.value >= 75 ? tr('Good') : a11yScore.value >= 50 ? tr('Fair') : tr('Needs work')));
const a11yStatusStyle = (s: string) =>
  s === 'pass' ? 'bg-emerald-500/20 text-emerald-400' : s === 'partial' ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-500/20 text-slate-400';
const a11yStatusLabel = (s: string) => (s === 'pass' ? tr('Pass') : s === 'partial' ? tr('Partial') : tr('To do'));

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
  footer_wave: true,
  header_bg: 'surface' as 'surface' | 'brand' | 'custom',
  header_color: '#5b5bd6',
  density: 'comfortable' as 'compact' | 'comfortable' | 'spacious',
  link_color: 'primary' as 'primary' | 'accent',
  muted: '' as string, // optional muted-text color override ('' = theme default)
  background_image: '',
  background_style: 'cover' as 'cover' | 'tile',
  widget_header_color: '' as string, // '' = follow primary
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
  form.footer_wave = v.footerWave !== false;
  form.header_bg = v.headerBg ?? 'surface';
  form.header_color = v.headerColor ?? '#5b5bd6';
  form.density = v.density ?? 'comfortable';
  form.link_color = v.linkColor ?? 'primary';
  form.muted = v.muted ?? '';
  form.background_image = v.backgroundImage ?? '';
  form.background_style = v.backgroundStyle ?? 'cover';
  form.widget_header_color = v.widgetHeaderColor ?? '';
  form.custom_css = v.customCss ?? '';
  form.logo = site.value.logo ?? '';
  form.favicon = site.value.favicon ?? '';

  // Sidebar widgets: order + visibility come from the saved layout; the live
  // registry (window.Convoro) supplies labels and surfaces newly-installed
  // widget extensions automatically.
  const layout = (site.value.widgets ?? []) as { key: string; enabled?: boolean }[];
  wOrder.value = layout.map((w) => w.key);
  wDisabled.value = layout.filter((w) => w.enabled === false).map((w) => w.key);
  reconcileWidgets();
  aboutTitle.value = site.value.widgetAbout?.title ?? '';
  aboutHtml.value = site.value.widgetAbout?.html ?? '';
}

function hexRgb(hex: string): string {
  const h = hex.replace('#', '');
  const f = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
  const n = parseInt(f, 16);
  return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`;
}
// Current resolved muted color as hex, so the picker shows a sensible default
// even when there's no explicit override yet.
function currentMutedHex(): string {
  const v = getComputedStyle(document.documentElement).getPropertyValue('--c-muted').trim();
  const p = v.split(/\s+/).map(Number);
  if (p.length === 3 && p.every((n) => !Number.isNaN(n))) {
    return '#' + p.map((n) => Math.max(0, Math.min(255, n)).toString(16).padStart(2, '0')).join('');
  }
  return '#8a90a6';
}
const mutedValue = computed(() => form.muted || currentMutedHex());

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
  if (form.muted) r.style.setProperty('--c-muted', hexRgb(form.muted));
  else r.style.removeProperty('--c-muted');
  // Widget-header tint token (blank = follow primary).
  r.style.setProperty('--c-widget-header', form.widget_header_color ? hexRgb(form.widget_header_color) : 'var(--c-primary)');
  // Forum background image — live preview on the <html> canvas.
  const safe = (form.background_image || '').replace(/["'<>\\]/g, '');
  if (safe) {
    r.style.backgroundImage = `url("${safe}")`;
    r.style.backgroundSize = form.background_style === 'tile' ? 'auto' : 'cover';
    r.style.backgroundRepeat = form.background_style === 'tile' ? 'repeat' : 'no-repeat';
    r.style.backgroundPosition = 'center top';
    r.style.backgroundAttachment = 'fixed';
    document.body.style.backgroundColor = 'transparent';
  } else {
    r.style.backgroundImage = '';
    r.style.backgroundAttachment = '';
    document.body.style.backgroundColor = '';
  }
  r.dataset.theme = form.mode;
  r.dataset.postStyle = form.post_style;
  r.dataset.footerWave = form.footer_wave ? 'on' : 'off';
  auditA11y();
}

function toggle() {
  if (!open.value) {
    ready.value = false;
    load();
    apply();
    open.value = true;
    nextTick(() => { ready.value = true; });
  } else {
    open.value = false;
  }
}

function save() {
  saving.value = true;
  router.post('/admin/theme', { ...form, button_radius: form.button_radius === '' ? null : form.button_radius }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => toast(tr('Theme saved')),
    onError: () => toast(tr("Couldn't save theme"), 'error'),
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

// ── Sidebar widgets (extension-driven) ───────────────────────────────────
// Every sidebar widget — built-in or add-on — registers into the
// `forum:sidebar` slot of window.Convoro. The editor manages an ordered list
// of keys (wOrder) plus a hidden set (wDisabled); labels come from the live
// registry so newly-installed widgets appear here without code changes.
const SIDEBAR_SLOT = 'forum:sidebar';
const wOrder = ref<string[]>([]);
const wDisabled = ref<string[]>([]);
const aboutHtml = ref('');
const aboutTitle = ref('');
const dragFrom = ref<number | null>(null);
const dragOver = ref<number | null>(null);

// Append any registered widget not yet in the saved order (e.g. just installed).
function reconcileWidgets() {
  convoro.registeredWidgets(SIDEBAR_SLOT).forEach((r) => {
    if (!wOrder.value.includes(r.key)) wOrder.value.push(r.key);
  });
}

// The list rendered in the tab: saved order, with labels + on/off resolved.
const displayWidgets = computed(() => {
  const reg = new Map(convoro.registeredWidgets(SIDEBAR_SLOT).map((r) => [r.key, r]));
  return wOrder.value.map((key) => {
    const r = reg.get(key);
    return { key, label: r ? tr(r.label) : key, enabled: !wDisabled.value.includes(key), known: !!r };
  });
});

// Pull newly-loaded widget extensions into the list while the panel is open.
watch(
  () => convoro.registeredWidgets(SIDEBAR_SLOT).length,
  () => { if (open.value) reconcileWidgets(); },
);

const currentLayout = computed(() =>
  wOrder.value.map((key) => ({ key, enabled: !wDisabled.value.includes(key) })),
);

function toggleWidget(key: string) {
  const i = wDisabled.value.indexOf(key);
  if (i === -1) wDisabled.value.push(key);
  else wDisabled.value.splice(i, 1);
}
function onDragStart(i: number, e: DragEvent) {
  dragFrom.value = i;
  if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(i)); }
}
function onDragOver(i: number) { if (dragFrom.value !== null) dragOver.value = i; }
function onDrop(i: number) {
  if (dragFrom.value === null || dragFrom.value === i) { dragFrom.value = null; dragOver.value = null; return; }
  const list = wOrder.value;
  const [moved] = list.splice(dragFrom.value, 1);
  list.splice(i, 0, moved);
  dragFrom.value = null;
  dragOver.value = null;
}
function onDragEnd() { dragFrom.value = null; dragOver.value = null; }

// Push the current layout + About content into the runtime for live preview.
function previewWidgets() {
  convoro.setLayout(SIDEBAR_SLOT, { order: wOrder.value.slice(), disabled: wDisabled.value.slice() });
  convoro.setData({ aboutHtml: aboutHtml.value, aboutTitle: aboutTitle.value });
  convoro.emit('convoro:data');
}
function saveWidgets() {
  saving.value = true;
  router.post('/admin/theme/widgets', {
    widgets: currentLayout.value,
    about_html: aboutHtml.value,
    about_title: aboutTitle.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => toast(tr('Widgets saved')),
    onError: () => toast(tr("Couldn't save widgets"), 'error'),
    onFinish: () => (saving.value = false),
  });
}

// ── Autosave (front-end live editor) ─────────────────────────────────────
// `ready` gates the watchers so the form/widget population done by load()
// (when the panel opens) doesn't trigger a spurious save.
let themeTimer: ReturnType<typeof setTimeout> | null = null;
let widgetTimer: ReturnType<typeof setTimeout> | null = null;

watch(form, () => {
  if (!ready.value || !open.value) return;
  if (themeTimer) clearTimeout(themeTimer);
  themeTimer = setTimeout(save, 700);
}, { deep: true });

watch([wOrder, wDisabled, aboutHtml, aboutTitle], () => {
  if (!ready.value || !open.value) return;
  previewWidgets(); // live preview is immediate; persistence is debounced
  if (widgetTimer) clearTimeout(widgetTimer);
  widgetTimer = setTimeout(saveWidgets, 700);
}, { deep: true });

const avatarShapes = [
  { v: 'circle', label: tr('Circle') },
  { v: 'rounded', label: tr('Rounded') },
  { v: 'square', label: tr('Square') },
] as const;
const postStyles = [
  { v: 'card', label: tr('Card') },
  { v: 'bordered', label: tr('Bordered') },
  { v: 'flat', label: tr('Flat') },
] as const;
const headerStyles = [
  { v: 'surface', label: tr('Default') },
  { v: 'brand', label: tr('Brand') },
  { v: 'custom', label: tr('Custom') },
] as const;
const densities = [
  { v: 'compact', label: tr('Compact') },
  { v: 'comfortable', label: tr('Cozy') },
  { v: 'spacious', label: tr('Spacious') },
] as const;
const containers = [
  { v: 1100, label: tr('Narrow') },
  { v: 1240, label: tr('Default') },
  { v: 1400, label: tr('Wide') },
  { v: 1600, label: tr('Extra wide') },
  { v: 0, label: tr('Full') },
];
const labelCls = 'mb-2 block text-xs font-bold uppercase tracking-wide text-ink-muted';
</script>

<template>
  <div v-if="isAdmin">
    <!-- Launcher -->
    <button v-if="!open" type="button" @click="toggle"
      class="fixed bottom-24 left-5 z-[70] hidden h-12 w-12 items-center justify-center rounded-full bg-primary text-white shadow-xl shadow-primary/40 hover:bg-primary-600 md:flex"
      :title="tr('Theme editor')" :aria-label="tr('Open theme editor')">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 1 0 0 20 2 2 0 0 0 2-2 1.5 1.5 0 0 1 1.5-1.5H18a4 4 0 0 0 4-4 10 10 0 0 0-10-10z"/></svg>
    </button>

    <!-- Drawer (no-frost: solid surfaces so the controls stay readable) -->
    <div v-if="open" class="no-frost fixed inset-y-0 left-0 z-[71] flex w-[340px] max-w-[90vw] flex-col border-r border-line bg-surface shadow-2xl">
      <div class="flex items-center gap-2 border-b border-line px-5 py-4">
        <h2 class="text-base font-extrabold">{{ tr('Customize') }}</h2>
        <span class="rounded-full bg-primary/15 px-2 py-0.5 text-[11px] font-bold text-primary">{{ tr('Live') }}</span>
        <button type="button" class="ml-auto text-ink-muted hover:text-ink" @click="open = false" :aria-label="tr('Close')">✕</button>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 border-b border-line px-3 pt-2">
        <button type="button" @click="tab = 'theme'" class="rounded-t-lg px-3 py-2 text-sm font-semibold"
          :class="tab === 'theme' ? 'bg-surface-2 text-ink' : 'text-ink-muted hover:text-ink'">{{ tr('Theme') }}</button>
        <button type="button" @click="tab = 'widgets'" class="rounded-t-lg px-3 py-2 text-sm font-semibold"
          :class="tab === 'widgets' ? 'bg-surface-2 text-ink' : 'text-ink-muted hover:text-ink'">{{ tr('Widgets') }}</button>
        <button type="button" @click="tab = 'a11y'; auditA11y()" class="flex items-center gap-1.5 rounded-t-lg px-3 py-2 text-sm font-semibold"
          :class="tab === 'a11y' ? 'bg-surface-2 text-ink' : 'text-ink-muted hover:text-ink'">
          {{ tr('A11y') }}
          <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
            :class="a11yScore >= 90 ? 'bg-emerald-500/20 text-emerald-400' : a11yScore >= 75 ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400'">{{ a11yScore }}%</span>
        </button>
      </div>

      <!-- THEME tab -->
      <div v-show="tab === 'theme'" class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
        <!-- A11y fix hint (shown after jumping from a failing check) -->
        <div v-if="a11yHint" class="flex items-start gap-2 rounded-xl border border-primary/40 bg-primary/10 px-3 py-2.5 text-xs text-ink">
          <svg class="mt-0.5 shrink-0 text-primary" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v-4M12 8h.01" /><circle cx="12" cy="12" r="10" /></svg>
          <span class="flex-1">{{ a11yHint }}</span>
          <button type="button" class="shrink-0 text-ink-muted hover:text-ink" @click="a11yHint = ''" :aria-label="tr('Dismiss')">✕</button>
        </div>
        <!-- Mode -->
        <div id="sec-appearance" class="scroll-mt-3 rounded-xl" :class="{ 'a11y-flash': flashSection === 'appearance' }">
          <label :class="labelCls">{{ tr('Appearance') }}</label>
          <div class="flex gap-2">
            <button v-for="m in ['light','dark']" :key="m" type="button" @click="form.mode = m as any; apply()"
              class="flex-1 rounded-lg border px-3 py-2 text-sm font-semibold capitalize"
              :class="form.mode === m ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ tr(m) }}</button>
          </div>
        </div>

        <!-- Brand colors -->
        <div id="sec-brand" class="scroll-mt-3 rounded-xl" :class="{ 'a11y-flash': flashSection === 'brand' }">
          <label :class="labelCls">{{ tr('Primary color') }}</label>
          <div class="flex items-center gap-3">
            <input type="color" v-model="form.primary" @input="apply" class="h-10 w-14 cursor-pointer rounded border border-line bg-surface p-1" />
            <input type="text" v-model="form.primary" @input="apply" class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
          </div>
          <label :class="labelCls" class="mt-3">{{ tr('Accent color') }}</label>
          <div class="flex items-center gap-3">
            <input type="color" v-model="form.accent" @input="apply" class="h-10 w-14 cursor-pointer rounded border border-line bg-surface p-1" />
            <input type="text" v-model="form.accent" @input="apply" class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
          </div>
          <div class="mt-3 flex items-center gap-2">
            <span class="text-xs font-semibold text-ink-muted">{{ tr('Links use') }}</span>
            <button v-for="l in ['primary','accent']" :key="l" type="button" @click="form.link_color = l as any; apply()"
              class="rounded-lg border px-2.5 py-1 text-xs font-semibold capitalize"
              :class="form.link_color === l ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ tr(l) }}</button>
          </div>
        </div>

        <!-- Text colors -->
        <div id="sec-text" class="scroll-mt-3 rounded-xl" :class="{ 'a11y-flash': flashSection === 'text' }">
          <label :class="labelCls">{{ tr('Muted text color') }}</label>
          <div class="flex items-center gap-3">
            <input type="color" :value="mutedValue" @input="(e) => { form.muted = (e.target as HTMLInputElement).value; apply(); }"
              class="h-10 w-14 cursor-pointer rounded border border-line bg-surface p-1" />
            <input type="text" :value="mutedValue" @input="(e) => { form.muted = (e.target as HTMLInputElement).value; apply(); }"
              class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
            <button v-if="form.muted" type="button" :title="tr('Reset to theme default')"
              class="shrink-0 rounded-lg border border-line px-2 py-1 text-[11px] font-semibold text-ink-2 hover:bg-surface-2"
              @click="form.muted = ''; apply()">{{ tr('Reset') }}</button>
          </div>
          <p class="mt-1 text-[11px] text-ink-muted">{{ tr('Used for hints, timestamps and secondary labels. Darken it to pass the “Muted text” contrast check.') }}</p>
        </div>

        <!-- Header -->
        <div>
          <label :class="labelCls">{{ tr('Header background') }}</label>
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

        <!-- Forum background image -->
        <div>
          <label :class="labelCls">{{ tr('Forum background image') }}</label>
          <input type="url" v-model="form.background_image" @input="apply" :placeholder="tr('https://… image URL (leave blank for none)')"
            class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
          <div v-if="form.background_image" class="mt-2 flex items-center gap-2">
            <button type="button" @click="form.background_style = 'cover'; apply()"
              class="flex-1 rounded-lg border px-2 py-1.5 text-xs font-semibold"
              :class="form.background_style === 'cover' ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ tr('Cover') }}</button>
            <button type="button" @click="form.background_style = 'tile'; apply()"
              class="flex-1 rounded-lg border px-2 py-1.5 text-xs font-semibold"
              :class="form.background_style === 'tile' ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ tr('Tile') }}</button>
            <button type="button" :title="tr('Remove')" @click="form.background_image = ''; apply()"
              class="shrink-0 rounded-lg border border-line px-2 py-1.5 text-[11px] font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Clear') }}</button>
          </div>
          <p class="mt-1 text-[11px] text-ink-muted">{{ tr('Shows behind the whole forum. Content cards stay solid so text remains readable.') }}</p>
        </div>

        <!-- Widget header color -->
        <div>
          <label :class="labelCls">{{ tr('Widget header color') }}</label>
          <div class="flex items-center gap-3">
            <input type="color" :value="form.widget_header_color || form.primary"
              @input="(e) => { form.widget_header_color = (e.target as HTMLInputElement).value; apply(); }"
              class="h-9 w-12 cursor-pointer rounded border border-line bg-surface p-1" />
            <input type="text" v-model="form.widget_header_color" @input="apply" :placeholder="tr('follows primary')"
              class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
            <button v-if="form.widget_header_color" type="button" :title="tr('Follow primary')"
              class="shrink-0 rounded-lg border border-line px-2 py-1 text-[11px] font-semibold text-ink-2 hover:bg-surface-2"
              @click="form.widget_header_color = ''; apply()">{{ tr('Reset') }}</button>
          </div>
          <p class="mt-1 text-[11px] text-ink-muted">{{ tr('Tints the header bar of sidebar widgets. Leave blank to follow your primary color.') }}</p>
        </div>

        <!-- Radii -->
        <div>
          <label :class="labelCls">{{ tr('Corner radius — {radius}px', { radius: form.radius }) }}</label>
          <input type="range" min="0" max="24" v-model.number="form.radius" @input="apply" class="w-full accent-primary" />
          <label :class="labelCls" class="mt-3">{{ form.button_radius === '' ? tr('Button radius — match') : tr('Button radius — {radius}px', { radius: form.button_radius }) }}</label>
          <div class="flex items-center gap-2">
            <input type="range" min="0" max="24" :value="form.button_radius === '' ? form.radius : form.button_radius"
              @input="(e) => { form.button_radius = Number((e.target as HTMLInputElement).value); apply(); }" class="w-full accent-primary" />
            <button type="button" class="shrink-0 rounded-lg border border-line px-2 py-1 text-[11px] font-semibold text-ink-2 hover:bg-surface-2" @click="form.button_radius = ''; apply()">{{ tr('Match') }}</button>
          </div>
        </div>

        <!-- Fonts -->
        <div>
          <label :class="labelCls">{{ tr('Body font') }}</label>
          <select v-model="form.font" @change="apply" class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary">
            <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
          <label :class="labelCls" class="mt-3">{{ tr('Heading font') }}</label>
          <select v-model="form.heading_font" @change="apply" class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary">
            <option value="">{{ tr('Same as body') }}</option>
            <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
          </select>
          <label :class="labelCls" class="mt-3">{{ tr('Text size — {size}px', { size: form.font_size }) }}</label>
          <input type="range" min="12" max="20" v-model.number="form.font_size" @input="apply" class="w-full accent-primary" />
        </div>

        <!-- Density -->
        <div>
          <label :class="labelCls">{{ tr('Density') }}</label>
          <div class="flex gap-2">
            <button v-for="d in densities" :key="d.v" type="button" @click="form.density = d.v; apply()"
              class="flex-1 rounded-lg border px-2 py-2 text-xs font-semibold"
              :class="form.density === d.v ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ d.label }}</button>
          </div>
        </div>

        <!-- Avatar shape -->
        <div>
          <label :class="labelCls">{{ tr('Avatar shape') }}</label>
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
          <label :class="labelCls">{{ tr('Post containers') }}</label>
          <div class="flex gap-2">
            <button v-for="s in postStyles" :key="s.v" type="button" @click="form.post_style = s.v; apply()"
              class="flex-1 rounded-lg border px-2 py-2 text-xs font-semibold"
              :class="form.post_style === s.v ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ s.label }}</button>
          </div>
        </div>

        <!-- Footer wave -->
        <div>
          <label :class="labelCls">{{ tr('Footer wave') }}</label>
          <div class="flex gap-2">
            <button type="button" @click="form.footer_wave = true; apply()"
              class="flex-1 rounded-lg border px-2 py-2 text-xs font-semibold"
              :class="form.footer_wave ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ tr('On') }}</button>
            <button type="button" @click="form.footer_wave = false; apply()"
              class="flex-1 rounded-lg border px-2 py-2 text-xs font-semibold"
              :class="!form.footer_wave ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:bg-surface-2'">{{ tr('Off') }}</button>
          </div>
        </div>

        <!-- Width -->
        <div>
          <label :class="labelCls">{{ tr('Content width') }}</label>
          <select v-model.number="form.container" @change="apply" class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary">
            <option v-for="c in containers" :key="c.v" :value="c.v">{{ c.label }}</option>
          </select>
        </div>

        <!-- Branding: logo + favicon -->
        <div>
          <label :class="labelCls">{{ tr('Logo') }}</label>
          <div class="flex items-center gap-3">
            <div class="grid h-10 min-w-[40px] place-items-center rounded-lg border border-line bg-surface-2 px-2">
              <img v-if="form.logo" :src="form.logo" class="h-7 max-w-[120px] object-contain" :alt="tr('logo')" />
              <span v-else class="text-[11px] text-ink-muted">{{ tr('None') }}</span>
            </div>
            <label class="cursor-pointer rounded-lg border border-line px-3 py-2 text-xs font-semibold text-ink-2 hover:bg-surface-2">
              {{ uploading === 'logo' ? tr('Uploading…') : tr('Upload') }}<input type="file" accept="image/*" class="hidden" @change="(e) => upload('logo', e)" />
            </label>
            <button v-if="form.logo" type="button" class="text-xs font-semibold text-ink-muted hover:text-red-400" @click="form.logo = ''">{{ tr('Clear') }}</button>
          </div>
          <label :class="labelCls" class="mt-3">{{ tr('Favicon') }}</label>
          <div class="flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-lg border border-line bg-surface-2">
              <img v-if="form.favicon" :src="form.favicon" class="h-6 w-6 object-contain" :alt="tr('favicon')" />
              <span v-else class="text-[11px] text-ink-muted">—</span>
            </div>
            <label class="cursor-pointer rounded-lg border border-line px-3 py-2 text-xs font-semibold text-ink-2 hover:bg-surface-2">
              {{ uploading === 'favicon' ? tr('Uploading…') : tr('Upload') }}<input type="file" accept="image/*" class="hidden" @change="(e) => upload('favicon', e)" />
            </label>
            <button v-if="form.favicon" type="button" class="text-xs font-semibold text-ink-muted hover:text-red-400" @click="form.favicon = ''">{{ tr('Clear') }}</button>
          </div>
        </div>

        <!-- Custom CSS -->
        <div>
          <label :class="labelCls">{{ tr('Custom CSS') }}</label>
          <textarea v-model="form.custom_css" rows="5" spellcheck="false" placeholder=".q-post { ... }"
            class="w-full rounded-lg border-line bg-surface-2 font-mono text-xs text-ink focus:border-primary focus:ring-primary"></textarea>
          <p class="mt-1 text-[11px] text-ink-muted">{{ tr('Applied site-wide after the theme tokens. Save to preview.') }}</p>
        </div>
      </div>

      <!-- WIDGETS tab -->
      <div v-show="tab === 'widgets'" class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
        <div>
          <label :class="labelCls">{{ tr('Sidebar widgets') }}</label>
          <p class="mb-3 text-[11px] text-ink-muted">{{ tr('Drag to reorder, toggle to show or hide. Changes preview live.') }}</p>

          <div v-if="!displayWidgets.length" class="rounded-xl border border-dashed border-line p-6 text-center text-sm text-ink-muted">
            {{ tr('No widgets installed yet.') }}
          </div>
          <div v-else class="space-y-2">
            <div v-for="(w, i) in displayWidgets" :key="w.key"
              @dragover.prevent="onDragOver(i)" @drop="onDrop(i)"
              class="rounded-xl border bg-surface-2 p-3 transition"
              :class="[
                dragFrom === i ? 'opacity-40' : '',
                dragOver === i && dragFrom !== i ? 'border-primary ring-2 ring-primary/50' : 'border-line',
              ]">
              <div class="flex items-center gap-2">
                <span draggable="true" @dragstart="onDragStart(i, $event)" @dragend="onDragEnd"
                  class="cursor-grab select-none text-ink-muted active:cursor-grabbing" :title="tr('drag to reorder')">⠿</span>
                <span class="text-sm font-semibold text-ink" :class="!w.enabled ? 'opacity-50' : ''">{{ w.label }}</span>
                <button type="button" role="switch" :aria-checked="w.enabled" @click.stop="toggleWidget(w.key)"
                  class="relative ml-auto h-5 w-9 shrink-0 rounded-full transition-colors"
                  :class="w.enabled ? 'bg-primary' : 'bg-line'">
                  <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all"
                    :class="w.enabled ? 'left-[18px]' : 'left-0.5'"></span>
                </button>
              </div>

              <!-- "About / Custom HTML" widget edits its content inline -->
              <div v-if="w.key === 'convoro-about' && w.enabled" class="mt-3 space-y-2">
                <input v-model="aboutTitle" :placeholder="tr('Heading (optional)')"
                  class="w-full rounded-lg border-line bg-surface text-sm text-ink focus:border-primary focus:ring-primary" />
                <textarea v-model="aboutHtml" rows="4" :placeholder="tr('Text or HTML…')"
                  class="w-full rounded-lg border-line bg-surface text-xs text-ink focus:border-primary focus:ring-primary"></textarea>
              </div>
            </div>
          </div>
          <p class="mt-2 text-[11px] text-ink-muted">{{ tr('Renders in the right rail of the community page for every visitor.') }}</p>
        </div>
      </div>

      <!-- ACCESSIBILITY tab -->
      <div v-show="tab === 'a11y'" class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
        <div class="flex items-center gap-4 rounded-xl border border-line bg-surface-2 p-4">
          <div class="text-3xl font-extrabold text-ink">{{ a11yScore }}%</div>
          <div>
            <div class="text-sm font-bold text-ink">{{ a11yGrade }}</div>
            <div class="text-[11px] text-ink-muted">{{ tr('WCAG / ADA compliance for the live theme') }}</div>
          </div>
        </div>
        <div>
          <label :class="labelCls">{{ tr('Color contrast (live)') }}</label>
          <ul class="divide-y divide-line rounded-xl border border-line">
            <li v-for="r in a11yResults" :key="r.label"
              :class="['flex items-center justify-between gap-2 px-3 py-2 text-sm', !r.ok ? 'cursor-pointer hover:bg-surface-2' : '']"
              :role="!r.ok ? 'button' : undefined" :tabindex="!r.ok ? 0 : undefined"
              @click="!r.ok && jumpToFix(r.fix, r.hint)"
              @keydown.enter="!r.ok && jumpToFix(r.fix, r.hint)">
              <span class="text-ink-2">{{ r.label }}</span>
              <span class="flex shrink-0 items-center gap-2">
                <span class="font-mono text-xs text-ink-muted">{{ r.ratio }}:1</span>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="r.ok ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">{{ r.ok ? tr('AA') : tr('Fail') }}</span>
                <span v-if="!r.ok" class="flex items-center gap-0.5 text-[11px] font-semibold text-primary">{{ tr('Fix') }}
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                </span>
              </span>
            </li>
          </ul>
          <p class="mt-2 text-[11px] text-ink-muted">{{ tr('Click a failing check to jump to the setting that fixes it — changes preview instantly.') }}</p>
        </div>
        <div>
          <label :class="labelCls">{{ tr('Best practices') }}</label>
          <ul class="divide-y divide-line rounded-xl border border-line">
            <li v-for="g in guidelines" :key="g.label" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
              <span class="text-ink-2">{{ g.label }}</span>
              <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="a11yStatusStyle(g.status)">{{ a11yStatusLabel(g.status) }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-center gap-2 border-t border-line px-5 py-4 text-[12px]">
        <template v-if="saving">
          <svg class="animate-spin text-ink-muted" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56" /></svg>
          <span class="font-semibold text-ink-2">{{ tr('Saving…') }}</span>
        </template>
        <template v-else>
          <svg class="text-emerald-500" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
          <span class="text-ink-muted">{{ tr('Changes save & publish automatically') }}</span>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Highlight a Theme-tab section when the user jumps to it from a failing a11y
 * check — a clear pulsing ring + background tint + a "Fix this" pin so the
 * exact control is unmistakable. Negative margin + padding give it breathing
 * room without shifting surrounding controls. */
.a11y-flash {
  margin: -10px -12px;
  padding: 10px 12px;
  border-radius: 14px;
  position: relative;
  animation: a11yFlash 3s ease;
}
.a11y-flash::after {
  content: 'Adjust this setting';
  position: absolute;
  top: -10px;
  left: 12px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #fff;
  background: rgb(91, 91, 214);
  padding: 2px 8px;
  border-radius: 999px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
  animation: a11yPin 3s ease forwards;
}
@keyframes a11yFlash {
  0% { box-shadow: 0 0 0 0 rgba(91, 91, 214, 0); background: rgba(91, 91, 214, 0); }
  10% { box-shadow: 0 0 0 3px rgba(91, 91, 214, 0.85); background: rgba(91, 91, 214, 0.14); }
  75% { box-shadow: 0 0 0 3px rgba(91, 91, 214, 0.55); background: rgba(91, 91, 214, 0.1); }
  100% { box-shadow: 0 0 0 0 rgba(91, 91, 214, 0); background: rgba(91, 91, 214, 0); }
}
@keyframes a11yPin {
  0%, 75% { opacity: 1; transform: translateY(0); }
  100% { opacity: 0; transform: translateY(-4px); }
}
</style>
