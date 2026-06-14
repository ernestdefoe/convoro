<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t as tr } from '@/lib/i18n';

defineProps<{
  tenants: any[];
  account: { name: string; email: string };
}>();

const form = useForm({ name: '' });
function create() {
  form.post('/panel/communities', { preserveScroll: true, onSuccess: () => form.reset('name') });
}
const flash = computed(() => (usePage().props as any).flash?.status as string | undefined);

const statusTone: Record<string, string> = {
  active: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
  provisioning: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
  failed: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
};
</script>

<template>
  <Head :title="tr('Managed hosting')" />
  <AppLayout>
    <div class="mx-auto max-w-2xl">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold uppercase tracking-wide text-primary">{{ tr('Proof of concept') }}</span>
        <a href="/panel/order" class="text-sm font-semibold text-primary hover:text-primary-600">{{ tr('View plans →') }}</a>
      </div>
      <h1 class="mt-1 text-2xl font-black tracking-tight">{{ tr('Your communities') }}</h1>
      <p class="mt-1 text-sm text-ink-2">
        {{ tr('Signed in as {name} ({email}). Spin up a hosted community — you’re made its admin automatically, with the same Convoro Account.', { name: account.name, email: account.email }) }}
      </p>

      <div v-if="flash" class="mt-4 rounded-lg border border-primary/30 bg-primary/[0.06] px-4 py-2.5 text-sm font-medium text-ink">{{ flash }}</div>

      <!-- Create -->
      <form class="mt-5 flex flex-wrap gap-2 rounded-c border border-line bg-surface p-4" @submit.prevent="create">
        <input v-model="form.name" type="text" maxlength="60" :placeholder="tr('Community name, e.g. Acme Makers')"
          class="min-w-[220px] flex-1 rounded-lg border-line bg-surface-2 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
        <button type="submit" :disabled="form.processing || !form.name.trim()"
          class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-50">
          {{ form.processing ? tr('Provisioning…') : tr('Create community') }}
        </button>
      </form>

      <!-- List -->
      <div class="mt-6 space-y-3">
        <div v-for="t in tenants" :key="t.id" class="rounded-c border border-line bg-surface p-4">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="font-bold text-ink">{{ t.name }}</h2>
            <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="statusTone[t.status]">{{ t.status }}</span>
            <span class="text-xs text-ink-muted">{{ t.createdAgo }}</span>
            <div v-if="t.status === 'active'" class="ml-auto flex items-center gap-2">
              <a :href="`/panel/communities/${t.id}/manage`" class="rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Manage') }}</a>
              <button type="button" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600"
                @click="router.post(`/panel/communities/${t.id}/enter`)">{{ tr('Open community →') }}</button>
            </div>
          </div>

          <p v-if="t.error" class="mt-1 text-sm text-rose-500">{{ t.error }}</p>

          <!-- Proof: this came from the tenant's OWN database -->
          <div v-if="t.status === 'active'" class="mt-2 grid gap-1.5 text-sm text-ink-2">
            <div class="flex items-center gap-2">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-emerald-500"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <span>{{ tr('You are an admin here') }}</span>
              <span class="text-ink-muted">·</span>
              <span v-for="a in t.admins" :key="a" class="font-mono text-xs text-ink-muted">{{ a }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-muted">
              <span>{{ tr('{n} member(s)', { n: t.users }) }}</span>
              <span>{{ tr('{n} categorie(s) seeded', { n: t.categories }) }}</span>
              <span class="font-mono">{{ tr('isolated DB:') }} {{ t.database }}</span>
            </div>
          </div>
        </div>

        <p v-if="!tenants.length" class="rounded-c border border-dashed border-line py-10 text-center text-sm text-ink-muted">
          {{ tr('No communities yet — create one above.') }}
        </p>
      </div>

      <p class="mt-6 text-xs leading-relaxed text-ink-muted">
        {{ tr('POC scope: each community is provisioned into its own isolated database, the full forum schema is migrated, base content is seeded, and your Convoro Account is set as admin. Production adds a deploy + DNS step (a real subdomain) and SSO so this same login works across the support forums too.') }}
      </p>
    </div>
  </AppLayout>
</template>
