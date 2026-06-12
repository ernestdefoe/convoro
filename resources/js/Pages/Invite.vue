<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  link: string;
  code: string;
  joined: number;
  sent: { email: string; joined: boolean; sentAt: string }[];
}>();

const copied = ref(false);
async function copyLink() {
  try { await navigator.clipboard.writeText(props.link); copied.value = true; setTimeout(() => (copied.value = false), 1800); } catch { /* ignore */ }
}

const raw = ref('');
const form = useForm({ emails: [] as string[], message: '' });
const parsedEmails = computed(() => raw.value.split(/[\s,;]+/).map((s) => s.trim()).filter((s) => /.+@.+\..+/.test(s)));

function send() {
  form.emails = parsedEmails.value;
  if (!form.emails.length) return;
  form.post('/invite/send', { preserveScroll: true, onSuccess: () => { raw.value = ''; form.reset('message'); } });
}
</script>

<template>
  <Head :title="t('Invite friends')" />
  <AppLayout>
    <div class="mx-auto max-w-2xl px-4 py-8">
      <h1 class="text-2xl font-extrabold text-ink">{{ t('Invite your friends') }}</h1>
      <p class="mt-1.5 text-ink-2">{{ t('Share your personal link or send invites by email. When someone joins through you, they’ll show up here.') }}</p>

      <!-- Referral link -->
      <section class="mt-6 rounded-2xl border border-line bg-surface p-5">
        <h2 class="text-sm font-bold text-ink">{{ t('Your invite link') }}</h2>
        <p class="mt-1 text-xs text-ink-muted">{{ t('Anyone who signs up with this link is credited to you.') }}</p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
          <input :value="link" readonly class="w-full truncate rounded-lg border-line bg-appbg text-sm text-ink-2" @focus="($event.target as HTMLInputElement).select()" />
          <button class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="copyLink">
            {{ copied ? t('Copied!') : t('Copy link') }}
          </button>
        </div>
      </section>

      <!-- Email invites -->
      <section class="mt-5 rounded-2xl border border-line bg-surface p-5">
        <h2 class="text-sm font-bold text-ink">{{ t('Invite by email') }}</h2>
        <p class="mt-1 text-xs text-ink-muted">{{ t('Enter up to 10 email addresses, separated by commas or new lines.') }}</p>
        <textarea v-model="raw" rows="3" :placeholder="t('friend@example.com, another@example.com')"
          class="mt-3 w-full rounded-lg border-line bg-appbg text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary"></textarea>
        <textarea v-model="form.message" rows="2" :maxlength="500" :placeholder="t('Add a personal note (optional)')"
          class="mt-2 w-full rounded-lg border-line bg-appbg text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary"></textarea>
        <div class="mt-3 flex items-center gap-3">
          <button :disabled="!parsedEmails.length || form.processing" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="send">
            {{ form.processing ? t('Sending…') : t('Send {n} invite | Send {n} invites', { n: parsedEmails.length || 0 }) }}
          </button>
          <span v-if="parsedEmails.length" class="text-xs text-ink-muted">{{ parsedEmails.length }} {{ t('valid') }}</span>
        </div>
      </section>

      <!-- Results -->
      <section class="mt-5 grid grid-cols-2 gap-3">
        <div class="rounded-2xl border border-line bg-surface p-5 text-center">
          <div class="text-3xl font-black text-primary">{{ joined }}</div>
          <div class="mt-1 text-xs font-semibold text-ink-muted">{{ t('joined through you') }}</div>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-5 text-center">
          <div class="text-3xl font-black text-ink">{{ sent.length }}</div>
          <div class="mt-1 text-xs font-semibold text-ink-muted">{{ t('email invites sent') }}</div>
        </div>
      </section>

      <section v-if="sent.length" class="mt-5 rounded-2xl border border-line bg-surface p-5">
        <h2 class="mb-2 text-sm font-bold text-ink">{{ t('Sent invitations') }}</h2>
        <ul class="divide-y divide-line">
          <li v-for="(s, i) in sent" :key="i" class="flex items-center justify-between py-2.5 text-sm">
            <span class="truncate text-ink-2">{{ s.email }}</span>
            <span v-if="s.joined" class="shrink-0 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ t('Joined') }}</span>
            <span v-else class="shrink-0 text-xs text-ink-muted">{{ t('Pending') }} · {{ s.sentAt }}</span>
          </li>
        </ul>
      </section>
    </div>
  </AppLayout>
</template>
