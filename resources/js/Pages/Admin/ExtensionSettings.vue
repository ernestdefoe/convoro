<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, onBeforeUnmount } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { toast } from '@/lib/toast';
import { t } from '@/lib/i18n';

interface SettingField { key: string; label: string; type?: string; default?: unknown; help?: string; options?: { value: string; label: string }[] }
interface Ext {
  id: string; name: string; version: string; description: string; author: string;
  type: string; enabled: boolean; settings: SettingField[];
  values: Record<string, unknown>; adminUrl: string | null; icon?: string | null;
}

const props = defineProps<{ ext: Ext; categories?: { id: number; name: string }[] }>();

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const formValues = ref<Record<string, any>>({ ...props.ext.values });

// A 'categories' field is stored as a CSV of ids but edited as a number[]; hydrate it.
for (const f of props.ext.settings) {
  if (f.type === 'categories') {
    const v = formValues.value[f.key];
    formValues.value[f.key] = typeof v === 'string' && v !== ''
      ? v.split(',').map((s) => parseInt(s, 10)).filter((n) => !isNaN(n))
      : Array.isArray(v) ? v : [];
  }
}
function toggleCategory(key: string, id: number) {
  const arr: number[] = Array.isArray(formValues.value[key]) ? formValues.value[key] : [];
  const i = arr.indexOf(id);
  formValues.value[key] = i >= 0 ? arr.filter((x) => x !== id) : [...arr, id];
}
const status = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
let timer: ReturnType<typeof setTimeout> | null = null;

function save() {
  status.value = 'saving';
  router.post('/admin/marketplace/settings', { id: props.ext.id, values: formValues.value }, {
    preserveScroll: true,
    preserveState: true,
    only: [],
    onSuccess: () => {
      status.value = 'saved';
      toast(t('Settings saved'));
      setTimeout(() => { if (status.value === 'saved') status.value = 'idle'; }, 2000);
    },
    onError: () => {
      status.value = 'error';
      toast(t("Couldn't save — will retry"), 'error');
    },
  });
}

// Auto-save: debounce text edits; checkboxes/selects effectively save on next tick too.
watch(formValues, () => {
  if (timer) clearTimeout(timer);
  status.value = 'saving';
  timer = setTimeout(save, 700);
}, { deep: true });

// --- In-shell embed of the extension's own admin page -----------------------
// The extension admin pages are same-origin, so we render them in a borderless
// auto-height iframe inside the admin content pane (instead of navigating away)
// and inject a small stylesheet that strips the page's own header/back-link so
// it reads as a native admin panel.
const frame = ref<HTMLIFrameElement | null>(null);
let ro: ResizeObserver | null = null;
let themeObserver: MutationObserver | null = null;
let injectedStyle: HTMLStyleElement | null = null;

// The iframe inherits a baked copy of the admin's resolved theme tokens so that
// third-party admin pages (which lack our light/dark CSS vars) still adopt the
// settings-page colors. Because these are absolute values on :root, they must be
// rebuilt whenever the admin toggles light/dark — otherwise the stale (load-time)
// palette overrides the iframe's own [data-theme] rules and the embed stays light.
function buildEmbedCss(): string {
  const cs = getComputedStyle(document.documentElement);
  const tok = (n: string) => cs.getPropertyValue(n).trim();
  const isDark = (document.documentElement.dataset.theme || document.body.dataset.theme) === 'dark';
  return `
      :root{
        --c-bg:${tok('--c-bg')};--c-surface:${tok('--c-surface')};--c-surface-2:${tok('--c-surface-2')};
        --c-border:${tok('--c-border')};--c-text:${tok('--c-text')};--c-text-2:${tok('--c-text-2')};
        --c-muted:${tok('--c-muted')};--c-primary:${tok('--c-primary')};
        color-scheme:${isDark ? 'dark' : 'light'};
      }
      .top{display:none!important}
      a[href="/admin/marketplace"]{display:none!important}
      .wrap,.container{max-width:none!important;margin:0!important;padding:18px 20px!important}
      html,body{background:transparent!important;color:rgb(var(--c-text))!important;min-height:0!important}
      h1,h2,h3,h4{color:rgb(var(--c-text))!important}
      .card,.panel,.box,.stat,.tile,.well{background:rgb(var(--c-surface))!important;border-color:rgb(var(--c-border))!important;color:rgb(var(--c-text))!important}
      .sub,.hint,.muted,.meta,.note{color:rgb(var(--c-muted))!important}
      label,.f,.chk,.lbl,legend,th{color:rgb(var(--c-text-2))!important}
      input:not([type=checkbox]):not([type=radio]),textarea,select{background:rgb(var(--c-surface-2))!important;border-color:rgb(var(--c-border))!important;color:rgb(var(--c-text))!important}
      .toggle,.switch,.option-row,.field-row,.input-row{background:rgb(var(--c-surface-2))!important;border-color:rgb(var(--c-border))!important;color:rgb(var(--c-text))!important}
      a:not(.btn){color:rgb(var(--c-primary))!important}
      .btn.ghost{border-color:rgb(var(--c-border))!important;color:rgb(var(--c-text-2))!important}
      table,th,td,hr,.row,.item,.divider{border-color:rgb(var(--c-border))!important}
      ::-webkit-scrollbar{width:0;height:0}
    `;
}

function onFrameLoad() {
  const f = frame.value;
  if (!f) return;
  try {
    const doc = f.contentDocument;
    if (!doc) return;

    injectedStyle = doc.createElement('style');
    injectedStyle.textContent = buildEmbedCss();
    doc.head.appendChild(injectedStyle);

    // Re-mirror the tokens whenever the admin flips light/dark, so the embed
    // follows the theme live instead of only after a refresh.
    themeObserver?.disconnect();
    themeObserver = new MutationObserver(() => {
      if (injectedStyle) injectedStyle.textContent = buildEmbedCss();
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    const isCustomPage = !!doc.querySelector('.top, .wrap, .container');
    const resize = () => {
      try { f.style.height = (doc.documentElement.scrollHeight || doc.body.scrollHeight) + 'px'; } catch { /* noop */ }
    };

    if (isCustomPage) {
      // Our own pages: size exactly to content, no inner scrollbar.
      f.style.minHeight = '0';
      resize();
      if (doc.body) { ro = new ResizeObserver(resize); ro.observe(doc.body); }
    } else {
      // Third-party SPA (e.g. Horizon): give it a tall fixed canvas.
      f.style.minHeight = '78vh';
    }
  } catch {
    // Cross-origin or sandboxed — fall back to a tall fixed canvas.
    f.style.minHeight = '78vh';
  }
}

onBeforeUnmount(() => { ro?.disconnect(); ro = null; themeObserver?.disconnect(); themeObserver = null; });
</script>

<template>
  <AdminLayout>
    <Head :title="`${ext.name}`" />
    <template #title>{{ ext.name }}</template>
    <template #subtitle>{{ ext.type === 'theme' ? t('Theme') : t('Extension') }} · v{{ ext.version }}<span v-if="ext.author"> · {{ t('by {author}', { author: ext.author }) }}</span></template>

    <div class="mb-4">
      <Link href="/admin/marketplace" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-2 hover:text-ink">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
        {{ t('Marketplace') }}
      </Link>
    </div>

    <!-- Extension ships its own admin page → embed it seamlessly in the pane. -->
    <div v-if="ext.adminUrl" class="overflow-hidden rounded-2xl border border-line bg-appbg">
      <iframe
        ref="frame"
        :src="ext.adminUrl + (ext.adminUrl.includes('?') ? '&' : '?') + 'embed=1'"
        :title="t('Extension settings')"
        class="block w-full border-0"
        style="min-height: 60vh"
        @load="onFrameLoad"
      />
    </div>

    <!-- Otherwise render the declarative settings form. -->
    <div v-else class="max-w-2xl space-y-5">
      <div class="rounded-2xl border border-line bg-surface p-5">
        <div class="flex items-start gap-3">
          <div class="grid h-11 w-11 shrink-0 place-items-center text-lg font-extrabold [&_svg]:h-11 [&_svg]:w-11 [&_svg]:rounded-xl"
            :class="ext.type === 'theme' ? 'text-fuchsia-300' : 'text-indigo-300'">
            <span v-if="ext.icon" class="grid h-11 w-11 place-items-center" v-html="ext.icon"></span>
            <span v-else>{{ (ext.name || '?').charAt(0).toUpperCase() }}</span>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm text-ink-2">{{ ext.description }}</p>
            <div class="mt-2 flex items-center gap-2">
              <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold"
                :class="ext.enabled ? 'bg-emerald-500/15 text-emerald-300' : 'bg-surface-2 text-ink-2'">
                {{ ext.enabled ? t('Enabled') : t('Disabled') }}
              </span>
              <span class="text-[11px] text-ink-muted">{{ ext.id }}</span>
            </div>
          </div>
        </div>
      </div>

      <form v-if="ext.settings.length" class="rounded-2xl border border-line bg-surface p-5 space-y-4" @submit.prevent="save">
        <div v-for="field in ext.settings" :key="field.key">
          <label v-if="field.type === 'boolean'" class="flex items-center gap-3">
            <input type="checkbox" v-model="formValues[field.key]" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
            <span class="text-sm font-medium text-ink">{{ field.label }}</span>
          </label>

          <template v-else-if="field.type === 'categories'">
            <label class="block text-sm font-medium text-ink-2">{{ field.label }}</label>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
              <button v-for="c in categories || []" :key="c.id" type="button" @click="toggleCategory(field.key, c.id)"
                class="rounded-full border px-3 py-1 text-[13px] font-semibold transition"
                :class="(formValues[field.key] || []).includes(c.id)
                  ? 'border-indigo-500 bg-indigo-500/15 text-indigo-300'
                  : 'border-line bg-appbg text-ink-2 hover:border-indigo-400'">
                {{ c.name }}
              </button>
              <span v-if="!(categories || []).length" class="text-xs text-ink-muted">{{ t('No categories yet.') }}</span>
            </div>
          </template>

          <template v-else>
            <label class="block text-sm font-medium text-ink-2">{{ field.label }}</label>
            <textarea v-if="field.type === 'textarea'" v-model="formValues[field.key]" rows="3"
              class="mt-1.5 w-full rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" />
            <select v-else-if="field.type === 'select'" v-model="formValues[field.key]"
              class="mt-1.5 w-full rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500">
              <option v-for="o in field.options || []" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <input v-else :type="field.type === 'number' ? 'number' : (field.type === 'color' ? 'color' : (field.type === 'password' ? 'password' : 'text'))"
              v-model="formValues[field.key]" :autocomplete="field.type === 'password' ? 'new-password' : undefined"
              class="mt-1.5 w-full rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" />
          </template>
          <p v-if="field.help" class="mt-1 text-xs text-ink-muted">{{ field.help }}</p>
        </div>

        <div class="flex items-center gap-2 pt-1 text-sm">
          <span v-if="status === 'saving'" class="text-ink-muted">{{ t('Saving…') }}</span>
          <span v-else-if="status === 'saved'" class="font-semibold text-emerald-500">{{ t('✓ All changes saved') }}</span>
          <span v-else-if="status === 'error'" class="text-red-500">{{ t("Couldn't save — will retry on next change") }}</span>
          <span v-else class="text-ink-muted">{{ t('Changes save automatically') }}</span>
        </div>
      </form>

      <p v-else class="rounded-2xl border border-dashed border-line p-8 text-center text-sm text-ink-muted">
        {{ t('This {type} has no configurable settings.', { type: ext.type }) }}
      </p>
    </div>
  </AdminLayout>
</template>
