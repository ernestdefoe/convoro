<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  activeCount: number;
  maxActive: number;
  slotsFree: boolean;
  waitlistCount: number;
  ttlDays: number;
}>();

const page = usePage();
const status = computed(() => (page.props as any).flash?.status as string | undefined);

const form = useForm({ email: '' });
function submit() {
  form.post('/request-demo', { preserveScroll: true, onSuccess: () => form.reset('email') });
}
</script>

<template>
  <Head :title="t('Request a demo')" />
  <AppLayout>
    <div class="mx-auto max-w-xl px-4 py-12">
      <h1 class="text-3xl font-extrabold text-ink">{{ t('Try Convoro, free') }}</h1>
      <p class="mt-2 text-ink-2">
        {{ t('We’ll spin up a private community just for you — you’re the admin, so explore everything. It’s yours for {n} days, then it cleans itself up.', { n: ttlDays }) }}
      </p>

      <div v-if="status" class="mt-6 rounded-xl border border-primary/30 bg-primary/10 px-4 py-3 text-sm font-medium text-ink">
        {{ status }}
      </div>

      <form class="mt-6 rounded-2xl border border-line bg-surface p-6" @submit.prevent="submit">
        <label class="block text-sm font-bold text-ink" for="demo-email">{{ t('Your email address') }}</label>
        <p class="mt-1 text-xs text-ink-muted">{{ t('We’ll email your login details here once the demo is ready.') }}</p>
        <input
          id="demo-email"
          v-model="form.email"
          type="email"
          required
          :placeholder="t('you@example.com')"
          class="mt-3 w-full rounded-lg border-line bg-appbg text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary"
        />
        <p v-if="form.errors.email" class="mt-1.5 text-xs font-medium text-red-500">{{ form.errors.email }}</p>

        <button
          type="submit"
          :disabled="form.processing"
          class="mt-4 w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50"
        >
          {{ form.processing ? t('Setting up your demo…') : (slotsFree ? t('Create my demo') : t('Join the waitlist')) }}
        </button>

        <p class="mt-3 text-center text-xs text-ink-muted">
          <template v-if="slotsFree">
            {{ t('{used} of {max} demo slots in use', { used: activeCount, max: maxActive }) }}
          </template>
          <template v-else>
            {{ t('All {max} demos are busy right now — {n} on the waitlist. We’ll email you when a slot opens.', { max: maxActive, n: waitlistCount }) }}
          </template>
        </p>
      </form>

      <ul class="mt-6 space-y-2 text-sm text-ink-2">
        <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Fully functional — post, react, theme, install extensions') }}</li>
        <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Outgoing email is disabled inside the demo, so click freely') }}</li>
        <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('We’ll remind you 24 hours before it expires') }}</li>
      </ul>
    </div>
  </AppLayout>
</template>
