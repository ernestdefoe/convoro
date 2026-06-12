<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { uploadImage } from '@/lib/upload';
import UploadButton from '@/Components/UploadButton.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  values: { name: string; tagline: string; logo: string; favicon: string; default_view: string; realtime: boolean; digests: boolean; pwa_banner: boolean; pwa_short_name: string; fa_kit_url: string; seo_description: string; seo_image: string };
}>();

const form = useForm({
  name: props.values.name,
  tagline: props.values.tagline,
  logo: props.values.logo ?? '',
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
          <label class="block text-sm font-medium text-ink-2">{{ t('Logo') }}</label>
          <p class="text-xs text-ink-muted">{{ t('Shown in the header. Leave empty to use the default Convoro mark.') }}</p>
          <div class="mt-2 flex items-center gap-3">
            <div class="flex h-10 items-center rounded-lg bg-surface-2 px-3">
              <img v-if="form.logo" :src="form.logo" :alt="t('logo')" class="h-7" />
              <span v-else class="text-xs text-ink-muted">{{ t('No logo') }}</span>
            </div>
            <UploadButton :uploading="uploadingLogo" accept="image/png,image/jpeg,image/webp" :label="t('Choose logo')" @file="pickLogo" />
            <button v-if="form.logo" type="button" class="text-sm text-ink-2 hover:text-red-400" @click="form.logo = ''">{{ t('Remove') }}</button>
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
  </AdminLayout>
</template>
