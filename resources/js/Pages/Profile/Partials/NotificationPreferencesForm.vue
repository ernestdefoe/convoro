<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { t } from '@/lib/i18n';

const page = usePage();
const initial = ((page.props as any).notificationPrefs ?? {}) as Record<string, { email: boolean; push: boolean }>;

const TYPES = [
  { key: 'reply', label: t('Replies to your topics & posts') },
  { key: 'mention', label: t('Mentions (@you)') },
  { key: 'message', label: t('Direct messages') },
  { key: 'reaction', label: t('Reactions to your posts') },
  { key: 'wall', label: t('Posts on your profile') },
  { key: 'badge', label: t('Badges you earn') },
  { key: 'event', label: t('Event reminders') },
];

// Clone so the form owns its own state, filling any missing keys.
const seed: Record<string, { email: boolean; push: boolean }> = {};
for (const row of TYPES) {
  seed[row.key] = {
    email: !!initial[row.key]?.email,
    push: initial[row.key]?.push ?? true,
  };
}
const form = useForm({ prefs: seed });

function save() {
  form.post('/notifications/channel-prefs', { preserveScroll: true });
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-ink">{{ t('Notifications') }}</h2>
      <p class="mt-1 text-sm text-ink-2">{{ t('Choose what you get notified about, and how. In-app alerts (the bell) are always on.') }}</p>
    </header>

    <form class="mt-6" @submit.prevent="save">
      <div class="grid grid-cols-[1fr_4rem_4rem] items-center gap-y-3">
        <span></span>
        <span class="text-center text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('Email') }}</span>
        <span class="text-center text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('Push') }}</span>

        <template v-for="row in TYPES" :key="row.key">
          <span class="border-t border-line py-2.5 text-sm text-ink-2">{{ row.label }}</span>
          <label class="flex justify-center border-t border-line py-2.5">
            <input v-model="form.prefs[row.key].email" type="checkbox" class="h-4 w-4 rounded border-line bg-appbg text-primary focus:ring-primary" />
          </label>
          <label class="flex justify-center border-t border-line py-2.5">
            <input v-model="form.prefs[row.key].push" type="checkbox" class="h-4 w-4 rounded border-line bg-appbg text-primary focus:ring-primary" />
          </label>
        </template>
      </div>

      <div class="mt-5 flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60">{{ t('Save preferences') }}</button>
        <span v-if="form.recentlySuccessful" class="text-sm text-emerald-500">{{ t('Saved.') }}</span>
      </div>
    </form>
  </section>
</template>
