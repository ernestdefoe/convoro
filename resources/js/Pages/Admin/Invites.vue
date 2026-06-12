<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  inviteOnly: boolean;
  registerBase: string;
  invites: { id: number; code: string; note: string | null; email: string | null; uses: number; maxUses: number; expiresAt: string | null; usable: boolean; by: string | null; created: string }[];
}>();

const page = usePage();
const status = computed(() => (page.props as any).flash?.status as string | undefined);

const only = ref(props.inviteOnly);
function toggleOnly() {
  router.post('/admin/invites/only', { invite_only: only.value }, { preserveScroll: true });
}

const form = useForm({ note: '', email: '', max_uses: 1, expires_days: null as number | null });
function create() {
  form.post('/admin/invites', { preserveScroll: true, onSuccess: () => form.reset() });
}

function revoke(id: number) {
  if (confirm(t('Revoke this invite? Its link will stop working.'))) {
    router.delete(`/admin/invites/${id}`, { preserveScroll: true });
  }
}

const copied = ref<number | null>(null);
function copyLink(inv: { id: number; code: string }) {
  navigator.clipboard?.writeText(props.registerBase + inv.code);
  copied.value = inv.id;
  setTimeout(() => (copied.value = null), 1500);
}

const field = 'mt-1 w-full rounded-lg border-line bg-appbg text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary text-sm';
</script>

<template>
  <Head :title="t('Admin · Invites')" />
  <AdminLayout>
    <template #title>{{ t('Invites') }}</template>
    <template #subtitle>{{ t('Invite codes & invite-only registration') }}</template>

    <div v-if="status" class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ status }}</div>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
      <div class="space-y-6">
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="mb-1 text-sm font-bold text-ink">{{ t('Registration mode') }}</h3>
          <p class="mb-3 text-xs text-ink-muted">{{ t('When invite-only is on, new members must enter a valid invite code to sign up.') }}</p>
          <label class="flex items-center gap-2 text-sm text-ink-2">
            <input v-model="only" type="checkbox" class="rounded border-line text-primary focus:ring-primary" @change="toggleOnly" />
            {{ t('Invite-only registration') }}
          </label>
        </section>

        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="mb-3 text-sm font-bold text-ink">{{ t('Create an invite') }}</h3>
          <label class="block text-xs font-semibold text-ink-2">{{ t('Note') }} <span class="font-normal text-ink-muted">{{ t('(optional)') }}</span></label>
          <input v-model="form.note" :class="field" :placeholder="t('e.g. Beta testers')" />
          <label class="mt-3 block text-xs font-semibold text-ink-2">{{ t('Reserve for email') }} <span class="font-normal text-ink-muted">{{ t('(optional)') }}</span></label>
          <input v-model="form.email" type="email" :class="field" placeholder="friend@example.com" />
          <div class="mt-3 grid grid-cols-2 gap-3">
            <div><label class="block text-xs font-semibold text-ink-2">{{ t('Max uses') }}</label><input v-model.number="form.max_uses" type="number" min="1" :class="field" /></div>
            <div><label class="block text-xs font-semibold text-ink-2">{{ t('Expires in (days)') }}</label><input v-model.number="form.expires_days" type="number" min="1" :class="field" :placeholder="t('never')" /></div>
          </div>
          <button class="mt-4 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="create">{{ t('Create invite') }}</button>
        </section>
      </div>

      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-3 text-sm font-bold text-ink">{{ t('Invites') }}</h3>
        <div v-if="!invites.length" class="text-sm text-ink-muted">{{ t('No invites yet.') }}</div>
        <ul v-else class="divide-y divide-line">
          <li v-for="inv in invites" :key="inv.id" class="flex items-center gap-3 py-3">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <code class="rounded bg-appbg px-2 py-0.5 text-sm font-bold text-ink select-all">{{ inv.code }}</code>
                <span v-if="!inv.usable" class="rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-bold uppercase text-red-300">{{ t('used up') }}</span>
              </div>
              <div class="mt-0.5 truncate text-xs text-ink-muted">
                {{ t('{used}/{max} used', { used: inv.uses, max: inv.maxUses }) }}<span v-if="inv.note"> · {{ inv.note }}</span><span v-if="inv.email"> · {{ inv.email }}</span><span v-if="inv.expiresAt"> · {{ t('expires {when}', { when: inv.expiresAt }) }}</span>
              </div>
            </div>
            <button class="rounded-lg bg-surface-2 px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-appbg" @click="copyLink(inv)">{{ copied === inv.id ? t('Copied!') : t('Copy link') }}</button>
            <button class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-red-400 hover:bg-red-500/10" @click="revoke(inv.id)">{{ t('Revoke') }}</button>
          </li>
        </ul>
      </section>
    </div>
  </AdminLayout>
</template>
