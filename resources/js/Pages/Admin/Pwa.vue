<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  values: { short_name: string; banner: boolean };
  icons: string[];
}>();

const page = usePage();
const status = computed(() => (page.props as any).flash?.status as string | undefined);

const form = useForm({ short_name: props.values.short_name, banner: props.values.banner });
function save() { form.post('/admin/pwa', { preserveScroll: true }); }

const iconForm = useForm<{ icon: File | null }>({ icon: null });
function onIcon(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0] ?? null;
  iconForm.icon = f;
  if (f) iconForm.post('/admin/pwa/icon', { preserveScroll: true, forceFormData: true });
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
        <p class="mt-4 text-xs text-ink-muted">{{ t('The theme color comes from the brand color in') }} <strong>{{ t('Theme') }}</strong>. {{ t('Push notifications use VAPID (configured server-side).') }}</p>
      </section>

      <!-- Icons -->
      <section class="rounded-2xl border border-line bg-surface p-6">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ t('App icons') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ t('Upload one square image — every PWA/favicon size is generated automatically (WebP/PNG).') }}</p>
        <div class="flex flex-wrap items-center gap-3">
          <img v-for="src in icons" :key="src" :src="src" class="h-12 w-12 rounded-lg border border-line" alt="" />
        </div>
        <div class="mt-4">
          <input type="file" accept="image/*" class="text-sm text-ink-2" @change="onIcon" />
          <span v-if="iconForm.processing" class="ml-2 text-sm text-ink-2">{{ t('Generating…') }}</span>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
