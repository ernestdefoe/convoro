<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

type Invite = {
  id: number; code: string; email: string | null; isLink: boolean;
  uses: number; maxUses: number; url: string;
  expired: boolean; exhausted: boolean; createdAt: string;
};

const props = defineProps<{ invites: Invite[]; enabled: boolean; remaining: number }>();

const link = computed(() => props.invites.find((i) => i.isLink && !i.expired && !i.exhausted));
const emailInvites = computed(() => props.invites.filter((i) => !i.isLink));

const emailForm = useForm({ email: '' });
function sendEmail() {
  emailForm.post('/invites/email', { preserveScroll: true, onSuccess: () => emailForm.reset('email') });
}

function makeLink() {
  router.post('/invites/link', {}, { preserveScroll: true });
}

function revoke(id: number) {
  router.delete(`/invites/${id}`, { preserveScroll: true });
}

const copied = ref(false);
function copy(url: string) {
  navigator.clipboard?.writeText(url).then(() => {
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  });
}

function status(i: Invite): string {
  if (i.expired) return 'Expired';
  if (i.exhausted) return 'Accepted';
  return 'Pending';
}
</script>

<template>
  <Head title="Invite people" />
  <AppLayout>
    <div class="mx-auto max-w-[680px]">
      <h1 class="mb-1 text-2xl font-extrabold tracking-tight">Invite people</h1>
      <p class="mb-6 text-sm text-ink-muted">Grow the community — share a link or send an email invite.</p>

      <div v-if="!enabled" class="rounded-c border border-line bg-surface p-5 text-sm text-ink-2">
        Invitations are currently turned off by the administrators.
      </div>

      <template v-else>
        <!-- Shareable link -->
        <section class="mb-5 rounded-c border border-line bg-surface p-5">
          <h2 class="text-sm font-bold text-ink">Shareable invite link</h2>
          <p class="mt-1 text-sm text-ink-muted">Anyone with this link can join, until it reaches its limit.</p>

          <div v-if="link" class="mt-3 flex items-center gap-2">
            <input :value="link.url" readonly
              class="min-w-0 flex-1 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink-2" />
            <button type="button" @click="copy(link.url)"
              class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">
              {{ copied ? 'Copied!' : 'Copy' }}
            </button>
          </div>
          <div v-if="link" class="mt-2 text-xs text-ink-muted">{{ link.uses }} / {{ link.maxUses }} uses</div>

          <button v-else type="button" @click="makeLink"
            class="mt-3 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">
            Create invite link
          </button>
        </section>

        <!-- Email invite -->
        <section class="mb-5 rounded-c border border-line bg-surface p-5">
          <h2 class="text-sm font-bold text-ink">Invite by email</h2>
          <p class="mt-1 text-sm text-ink-muted">We'll email them a personal invite. {{ remaining }} left.</p>
          <form class="mt-3 flex items-start gap-2" @submit.prevent="sendEmail">
            <div class="flex-1">
              <input v-model="emailForm.email" type="email" required placeholder="friend@example.com"
                class="w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:ring-primary" />
              <p v-if="emailForm.errors.email" class="mt-1 text-xs text-danger">{{ emailForm.errors.email }}</p>
            </div>
            <button type="submit" :disabled="emailForm.processing || remaining < 1"
              class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60">
              Send invite
            </button>
          </form>
        </section>

        <!-- Sent email invites -->
        <section v-if="emailInvites.length" class="overflow-hidden rounded-c border border-line bg-surface">
          <h2 class="border-b border-line px-5 py-3 text-xs font-semibold uppercase tracking-wide text-ink-muted">Sent invites</h2>
          <div v-for="i in emailInvites" :key="i.id" class="flex items-center gap-3 border-b border-line/60 px-5 py-3 last:border-0">
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-semibold text-ink">{{ i.email }}</div>
              <div class="text-xs text-ink-muted">{{ i.createdAt }}</div>
            </div>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :class="i.exhausted ? 'bg-primary/15 text-primary' : i.expired ? 'bg-surface-2 text-ink-muted' : 'bg-surface-2 text-ink-2'">
              {{ status(i) }}
            </span>
            <button v-if="!i.exhausted" type="button" @click="revoke(i.id)"
              class="rounded-lg border border-line px-2.5 py-1 text-xs font-semibold text-ink-2 hover:bg-surface-2">Revoke</button>
          </div>
        </section>
      </template>
    </div>
  </AppLayout>
</template>
