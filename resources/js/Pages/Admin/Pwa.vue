<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import UploadButton from '@/Components/UploadButton.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  values: { short_name: string; banner: boolean };
  icons: string[];
  push: { configured: boolean; publicKey: string; subscriptions: number };
}>();

const page = usePage();
const status = computed(() => (page.props as any).flash?.status as string | undefined);

const form = useForm({ short_name: props.values.short_name, banner: props.values.banner });
function save() { form.post('/admin/pwa', { preserveScroll: true }); }

const iconForm = useForm<{ icon: File | null }>({ icon: null });
function onIcon(f: File) {
  iconForm.icon = f;
  iconForm.post('/admin/pwa/icon', { preserveScroll: true, forceFormData: true });
}

const vapidForm = useForm({});
function regenerateVapid() {
  const msg = props.push.configured
    ? t('Generate new push keys? Every member with push enabled will need to turn it back on. Use this only if push is broken.')
    : t('Generate push notification keys now?');
  if (confirm(msg)) vapidForm.post('/admin/pwa/vapid', { preserveScroll: true });
}
</script>

<template>
  <Head :title="t('Admin · PWA')" />
  <AdminLayout>
    <template #title>{{ t('Progressive Web App') }}</template>
    <template #subtitle>{{ t('Install experience, icons & push') }}</template>

    <div v-if="status" class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ status }}</div>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Settings -->
      <section class="rounded-2xl border border-line bg-surface p-6">
        <h3 class="mb-4 text-sm font-bold text-ink">{{ t('Install settings') }}</h3>
        <form class="space-y-5" @submit.prevent="save">
          <div>
            <label class="block text-sm font-medium text-ink-2">{{ t('App short name (home-screen label)') }}</label>
            <input v-model="form.short_name" type="text" maxlength="30" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
          <label class="flex items-center gap-3">
            <input v-model="form.banner" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
            <span class="text-sm text-ink-2">{{ t('Show the “install app” banner on supported devices') }}</span>
          </label>
          <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">{{ t('Save') }}</button>
        </form>
        <p class="mt-4 text-xs text-ink-muted">{{ t('The theme color comes from the brand color in') }} <strong>{{ t('Theme') }}</strong>.</p>
      </section>

      <!-- Icons -->
      <section class="rounded-2xl border border-line bg-surface p-6">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ t('App icons') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ t('Upload one square image — every PWA/favicon size is generated automatically (WebP/PNG).') }}</p>
        <div class="flex flex-wrap items-center gap-3">
          <img v-for="src in icons" :key="src" :src="src" class="h-12 w-12 rounded-lg border border-line" alt="" />
        </div>
        <div class="mt-4">
          <UploadButton :uploading="iconForm.processing" :label="t('Choose app icon')" accept="image/png,image/jpeg,image/webp" @file="onIcon" />
        </div>
      </section>
    </div>

    <!-- Push notification keys (VAPID) -->
    <section class="mt-6 rounded-2xl border border-line bg-surface p-6">
      <h3 class="text-sm font-bold text-ink">{{ t('Push notification keys (VAPID)') }}</h3>
      <p class="mt-1 text-xs text-ink-muted">{{ t('VAPID keys sign browser push notifications. They normally come from your server config — regenerate them here only if push has stopped working (e.g. corrupted keys).') }}</p>

      <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <span class="flex items-center gap-2">
          <span class="h-2 w-2 rounded-full" :class="push.configured ? 'bg-emerald-500' : 'bg-amber-500'"></span>
          <span class="text-ink-2">{{ push.configured ? t('Push keys are configured') : t('No push keys yet') }}</span>
        </span>
        <span v-if="push.publicKey" class="font-mono text-xs text-ink-muted">{{ t('Public key') }}: {{ push.publicKey }}</span>
        <span class="text-ink-muted">{{ t('{n} device(s) subscribed', { n: push.subscriptions }) }}</span>
      </div>

      <div class="mt-4">
        <button type="button" :disabled="vapidForm.processing" class="rounded-lg border border-line bg-surface-2 px-4 py-2 text-sm font-semibold text-ink hover:bg-appbg disabled:opacity-60" @click="regenerateVapid">
          {{ vapidForm.processing ? t('Generating…') : (push.configured ? t('Regenerate push keys') : t('Generate push keys')) }}
        </button>
        <p class="mt-2 text-xs text-amber-500">{{ t('Heads up: regenerating invalidates every existing subscription — members will have to re-enable push on their devices.') }}</p>
      </div>
    </section>
  </AdminLayout>
</template>
