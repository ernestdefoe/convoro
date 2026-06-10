<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const page = usePage();
const isAdmin = computed(() => !!(page.props as any).auth?.isAdmin);
const fonts = computed(() => ((page.props as any).themeFonts ?? []) as { value: string; label: string }[]);
const t = computed(() => (page.props as any).site?.theme ?? {});

const open = ref(false);
const saving = ref(false);

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
}

function toggle() {
  if (!open.value) { load(); }
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
        <h2 class="text-base font-extrabold">Theme</h2>
        <span class="rounded-full bg-primary/15 px-2 py-0.5 text-[11px] font-bold text-primary">Live</span>
        <button type="button" class="ml-auto text-ink-muted hover:text-ink" @click="open = false" aria-label="Close">✕</button>
      </div>

      <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
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

      <div class="border-t border-line px-5 py-4">
        <button type="button" :disabled="saving" @click="save" class="w-full rounded-c bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-60">
          {{ saving ? 'Saving…' : 'Save theme' }}
        </button>
        <p class="mt-2 text-center text-[11px] text-ink-muted">Changes preview live. Save to publish for everyone.</p>
      </div>
    </div>
  </div>
</template>
