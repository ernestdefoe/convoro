<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { uploadImage } from '@/lib/upload';
import UploadButton from '@/Components/UploadButton.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  values: { name: string; tagline: string; logo: string; logo_dark: string; favicon: string; default_view: string; realtime: boolean; digests: boolean; pwa_banner: boolean; pwa_short_name: string; fa_kit_url: string; seo_description: string; seo_image: string };
  mobileNav: { enabled: boolean; tabs: string[]; catalog: string[] };
  federation: { enabled: boolean; username: string; handle: string; followers: number };
}>();

// ---- Federation (ActivityPub) ----
const fed = useForm({ enabled: props.federation.enabled, username: props.federation.username });
const fedSaved = ref(false);
function saveFederation() {
  fed.post('/admin/settings/federation', {
    preserveScroll: true,
    onSuccess: () => { fedSaved.value = true; setTimeout(() => (fedSaved.value = false), 2500); },
  });
}

// ---- Mobile bottom tab bar ----
const TAB_LABELS: Record<string, string> = {
  home: t('Home'), search: t('Search'), compose: t('Post (compose)'), notifications: t('Notifications'),
  messages: t('Messages'), members: t('Members'), account: t('Account / You'),
};
const mEnabled = ref(props.mobileNav.enabled);
// Rows in display order: enabled tabs first (in saved order), then the rest (off).
const mRows = ref<{ key: string; on: boolean }[]>([
  ...props.mobileNav.tabs.map((k) => ({ key: k, on: true })),
  ...props.mobileNav.catalog.filter((k) => !props.mobileNav.tabs.includes(k)).map((k) => ({ key: k, on: false })),
]);
function moveRow(i: number, dir: -1 | 1) {
  const j = i + dir;
  if (j < 0 || j >= mRows.value.length) return;
  const a = mRows.value.slice();
  [a[i], a[j]] = [a[j], a[i]];
  mRows.value = a;
}
const mobileSaving = ref(false);
const mobileSaved = ref(false);
function saveMobile() {
  mobileSaving.value = true;
  router.post('/admin/settings/mobile-nav', {
    enabled: mEnabled.value,
    tabs: mRows.value.filter((r) => r.on).map((r) => r.key),
  }, {
    preserveScroll: true,
    onSuccess: () => { mobileSaved.value = true; setTimeout(() => (mobileSaved.value = false), 2500); },
    onFinish: () => { mobileSaving.value = false; },
  });
}

const form = useForm({
  name: props.values.name,
  tagline: props.values.tagline,
  logo: props.values.logo ?? '',
  logo_dark: props.values.logo_dark ?? '',
  favicon: props.values.favicon ?? '',
  default_view: props.values.default_view,
  realtime: props.values.realtime,
  digests: props.values.digests,
  pwa_banner: props.values.pwa_banner,
  pwa_short_name: props.values.pwa_short_name,
  fa_kit_url: props.values.fa_kit_url ?? '',
  seo_description: props.values.seo_description ?? '',
  seo_image: props.values.seo_image ?? '',
});

const uploadingLogo = ref(false);
async function pickLogo(file: File) {
  uploadingLogo.value = true;
  try { const { url } = await uploadImage(file); form.logo = url; } catch { /* ignore */ } finally { uploadingLogo.value = false; }
}

const uploadingLogoDark = ref(false);
async function pickLogoDark(file: File) {
  uploadingLogoDark.value = true;
  try { const { url } = await uploadImage(file); form.logo_dark = url; } catch { /* ignore */ } finally { uploadingLogoDark.value = false; }
}

const uploadingFavicon = ref(false);
async function pickFavicon(file: File) {
  uploadingFavicon.value = true;
  try { const { url } = await uploadImage(file); form.favicon = url; } catch { /* ignore */ } finally { uploadingFavicon.value = false; }
}

const uploadingShare = ref(false);
async function pickShare(file: File) {
  uploadingShare.value = true;
  try { const { url } = await uploadImage(file); form.seo_image = url; } catch { /* ignore */ } finally { uploadingShare.value = false; }
}

function save() {
  form.post('/admin/settings', { preserveScroll: true });
}
</script>

<template>
  <Head :title="t('Admin · Settings')" />
  <AdminLayout>
    <template #title>{{ t('Settings') }}</template>
    <template #subtitle>{{ t('General community configuration') }}</template>

    <form class="max-w-2xl space-y-6" @submit.prevent="save">
      <div class="rounded-2xl border border-line bg-surface p-6 space-y-5">
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Community name') }}</label>
          <input v-model="form.name" type="text" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Tagline') }}</label>
          <input v-model="form.tagline" type="text" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Logo (light mode)') }}</label>
          <p class="text-xs text-ink-muted">{{ t('Shown in the header on light backgrounds. Leave empty to use the default Convoro mark.') }}</p>
          <div class="mt-2 flex items-center gap-3">
            <div class="flex h-10 items-center rounded-lg bg-white px-3 ring-1 ring-line">
              <img v-if="form.logo" :src="form.logo" :alt="t('logo')" class="h-7" />
              <span v-else class="text-xs text-gray-400">{{ t('No logo') }}</span>
            </div>
            <UploadButton :uploading="uploadingLogo" accept="image/png,image/jpeg,image/webp,image/svg+xml" :label="t('Choose logo')" @file="pickLogo" />
            <button v-if="form.logo" type="button" class="text-sm text-ink-2 hover:text-red-400" @click="form.logo = ''">{{ t('Remove') }}</button>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Logo (dark mode)') }}</label>
          <p class="text-xs text-ink-muted">{{ t('Optional. Shown when the theme is dark. Leave empty to use the light-mode logo in both modes.') }}</p>
          <div class="mt-2 flex items-center gap-3">
            <div class="flex h-10 items-center rounded-lg bg-slate-900 px-3 ring-1 ring-line">
              <img v-if="form.logo_dark" :src="form.logo_dark" :alt="t('logo')" class="h-7" />
              <span v-else class="text-xs text-slate-400">{{ t('No dark logo') }}</span>
            </div>
            <UploadButton :uploading="uploadingLogoDark" accept="image/png,image/jpeg,image/webp,image/svg+xml" :label="t('Choose dark logo')" @file="pickLogoDark" />
            <button v-if="form.logo_dark" type="button" class="text-sm text-ink-2 hover:text-red-400" @click="form.logo_dark = ''">{{ t('Remove') }}</button>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Favicon') }}</label>
          <p class="text-xs text-ink-muted">{{ t('The little icon in the browser tab. A square PNG (at least 64×64) works best. Leave empty to use the default Convoro icon.') }}</p>
          <div class="mt-2 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2">
              <img v-if="form.favicon" :src="form.favicon" :alt="t('favicon')" class="h-6 w-6 rounded" />
              <span v-else class="text-[10px] text-ink-muted">{{ t('None') }}</span>
            </div>
            <UploadButton :uploading="uploadingFavicon" accept="image/png,image/x-icon,image/svg+xml,image/webp" :label="t('Choose favicon')" @file="pickFavicon" />
            <button v-if="form.favicon" type="button" class="text-sm text-ink-2 hover:text-red-400" @click="form.favicon = ''">{{ t('Remove') }}</button>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Default forum view') }}</label>
          <select v-model="form.default_view" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500">
            <option value="feed">{{ t('Feed') }}</option>
            <option value="grid">{{ t('Grid') }}</option>
          </select>
        </div>
        <label class="flex items-center gap-3">
          <input v-model="form.realtime" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
          <span class="text-sm text-ink-2">{{ t('Enable live threads (realtime) — requires a WebSocket server; leave off on shared hosting') }}</span>
        </label>
      </div>

      <div class="rounded-2xl border border-line bg-surface p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Email digests') }}</h2>
        <label class="flex items-center gap-3">
          <input v-model="form.digests" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
          <span class="text-sm text-ink-2">{{ t('Send digest emails (members choose their frequency in their profile)') }}</span>
        </label>
      </div>

      <div class="rounded-2xl border border-line bg-surface p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Progressive Web App') }}</h2>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('App short name (home-screen label)') }}</label>
          <input v-model="form.pwa_short_name" type="text" maxlength="30" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <label class="flex items-center gap-3">
          <input v-model="form.pwa_banner" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
          <span class="text-sm text-ink-2">{{ t('Show the “install app” banner to visitors on supported devices') }}</span>
        </label>
      </div>

      <div class="rounded-2xl border border-line bg-surface p-6 space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Icons') }}</h2>
        <p class="text-sm text-ink-muted">{{ t('Convoro bundles Font Awesome Free. To use a Pro or custom') }} <a href="https://fontawesome.com/kits" target="_blank" class="text-indigo-400 underline">{{ t('Font Awesome Kit') }}</a>{{ t(', paste its script URL here.') }}</p>
        <input v-model="form.fa_kit_url" type="url" placeholder="https://kit.fontawesome.com/xxxxxxxx.js"
          class="w-full rounded-lg border-line bg-appbg font-mono text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" />
      </div>

      <div class="rounded-2xl border border-line bg-surface p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('SEO & sharing') }}</h2>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Default meta description') }}</label>
          <p class="text-xs text-ink-muted">{{ t('Shown in search results and link previews. Falls back to your tagline.') }}</p>
          <textarea v-model="form.seo_description" rows="2" maxlength="300" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Social share image') }}</label>
          <p class="text-xs text-ink-muted">{{ t('Used for link previews (Open Graph / Twitter). 1200×630 works best. Falls back to your logo.') }}</p>
          <div class="mt-2 flex items-center gap-3">
            <div class="flex h-14 w-24 items-center justify-center overflow-hidden rounded-lg bg-surface-2">
              <img v-if="form.seo_image" :src="form.seo_image" :alt="t('share image')" class="h-full w-full object-cover" />
              <span v-else class="text-xs text-ink-muted">{{ t('None') }}</span>
            </div>
            <UploadButton :uploading="uploadingShare" accept="image/png,image/jpeg,image/webp" :label="t('Choose image')" @file="pickShare" />
            <button v-if="form.seo_image" type="button" class="text-sm text-ink-2 hover:text-red-400" @click="form.seo_image = ''">{{ t('Remove') }}</button>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">{{ t('Save changes') }}</button>
        <span v-if="form.recentlySuccessful" class="text-sm text-emerald-400">{{ t('Saved.') }}</span>
      </div>
    </form>

    <!-- Mobile bottom tab bar -->
    <div class="mt-6 max-w-2xl rounded-2xl border border-line bg-surface p-6 space-y-4">
      <div>
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Mobile tab bar') }}</h2>
        <p class="mt-1 text-sm text-ink-muted">{{ t('The bottom navigation bar shown on phones. Turn it on or off, choose which tabs appear, and drag the order with the arrows.') }}</p>
      </div>
      <label class="flex items-center gap-3">
        <input v-model="mEnabled" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
        <span class="text-sm text-ink-2">{{ t('Show the mobile tab bar') }}</span>
      </label>

      <ul class="divide-y divide-line rounded-lg border border-line" :class="mEnabled ? '' : 'pointer-events-none opacity-50'">
        <li v-for="(row, i) in mRows" :key="row.key" class="flex items-center gap-3 px-3 py-2.5">
          <div class="flex flex-col">
            <button type="button" class="text-ink-muted hover:text-ink disabled:opacity-30" :disabled="i === 0" :aria-label="t('Move up')" @click="moveRow(i, -1)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 15l6-6 6 6" /></svg>
            </button>
            <button type="button" class="text-ink-muted hover:text-ink disabled:opacity-30" :disabled="i === mRows.length - 1" :aria-label="t('Move down')" @click="moveRow(i, 1)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6" /></svg>
            </button>
          </div>
          <span class="flex-1 text-sm font-medium text-ink">{{ TAB_LABELS[row.key] || row.key }}</span>
          <label class="relative inline-flex cursor-pointer items-center">
            <input v-model="row.on" type="checkbox" class="peer sr-only" />
            <div class="h-5 w-9 rounded-full bg-surface-2 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all peer-checked:bg-indigo-500 peer-checked:after:translate-x-4"></div>
          </label>
        </li>
      </ul>
      <p class="text-xs text-ink-muted">{{ t('Tip: the “Post” tab appears as a raised button in the centre. Two or more tabs are needed for the bar to show.') }}</p>

      <div class="flex items-center gap-3">
        <button type="button" :disabled="mobileSaving" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60" @click="saveMobile">{{ t('Save tab bar') }}</button>
        <span v-if="mobileSaved" class="text-sm text-emerald-400">{{ t('Saved.') }}</span>
      </div>
    </div>

    <!-- Federation (ActivityPub) -->
    <div class="mt-6 max-w-2xl rounded-2xl border border-line bg-surface p-6 space-y-4">
      <div>
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Federation (ActivityPub)') }}</h2>
        <p class="mt-1 text-sm text-ink-muted">{{ t('Let people on Mastodon, Lemmy and the wider fediverse follow your community and see each new discussion in their feed.') }}</p>
      </div>
      <label class="flex items-center gap-3">
        <input v-model="fed.enabled" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
        <span class="text-sm text-ink-2">{{ t('Enable federation') }}</span>
      </label>
      <div>
        <label class="block text-sm font-medium text-ink-2">{{ t('Handle username') }}</label>
        <p class="text-xs text-ink-muted">{{ t('The part before the @ in your community’s fediverse address.') }}</p>
        <input v-model="fed.username" type="text" maxlength="40" placeholder="community" class="mt-1.5 w-full max-w-xs rounded-lg border-line bg-appbg font-mono text-ink focus:border-indigo-500 focus:ring-indigo-500" />
      </div>
      <div class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg bg-surface-2 px-3 py-2.5 text-sm">
        <span class="text-ink-muted">{{ t('Your address') }}: <span class="font-mono text-ink">{{ fed.username || 'community' }}@{{ federation.handle.split('@').pop() }}</span></span>
        <span class="text-ink-muted">{{ t('{n} follower(s)', { n: federation.followers }) }}</span>
      </div>
      <p v-if="(fed.errors as any).federation" class="text-sm text-red-400">{{ (fed.errors as any).federation }}</p>
      <div class="flex items-center gap-3">
        <button type="button" :disabled="fed.processing" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60" @click="saveFederation">{{ t('Save federation') }}</button>
        <span v-if="fedSaved" class="text-sm text-emerald-400">{{ t('Saved.') }}</span>
      </div>
    </div>
  </AdminLayout>
</template>
