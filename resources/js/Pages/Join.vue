<script setup lang="ts">
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps<{
  valid: boolean;
  reason: string | null;
  inviter?: string | null;
  email?: string | null;
  code?: string | null;
}>();

const page = usePage();
const siteName = computed(() => (page.props as any).site?.name || 'Convoro');

const form = useForm({
  name: '',
  email: props.email ?? '',
  password: '',
  password_confirmation: '',
  invite: props.code ?? '',
});

function submit() {
  form.post('/register');
}

const message = computed(() => {
  if (props.reason === 'already_member') return "You're already signed in — invites are for new members.";
  if (props.reason === 'used') return 'This invite has already been used or has expired.';
  return 'This invite link is invalid.';
});
</script>

<template>
  <Head title="Join" />
  <AppLayout>
    <div class="mx-auto max-w-[460px] py-6">
      <div class="rounded-c border border-line bg-surface p-7 shadow-sm">
        <template v-if="valid">
          <div class="mb-5 text-center">
            <h1 class="text-2xl font-extrabold tracking-tight">You're invited 🎉</h1>
            <p class="mt-1 text-sm text-ink-muted">
              <span v-if="inviter"><strong class="text-ink-2">{{ inviter }}</strong> invited you to join </span>
              <span v-else>You've been invited to join </span>
              <strong class="text-ink-2">{{ siteName }}</strong>.
            </p>
          </div>

          <form class="flex flex-col gap-3" @submit.prevent="submit">
            <div>
              <label class="mb-1 block text-sm font-semibold text-ink-2" for="name">Name</label>
              <input id="name" v-model="form.name" type="text" autocomplete="name" required
                class="w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:ring-primary" />
              <p v-if="form.errors.name" class="mt-1 text-xs text-danger">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-semibold text-ink-2" for="email">Email</label>
              <input id="email" v-model="form.email" type="email" autocomplete="email" required
                :readonly="!!email"
                class="w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:ring-primary read-only:opacity-70" />
              <p v-if="form.errors.email" class="mt-1 text-xs text-danger">{{ form.errors.email }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-semibold text-ink-2" for="password">Password</label>
              <input id="password" v-model="form.password" type="password" autocomplete="new-password" required
                class="w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:ring-primary" />
              <p v-if="form.errors.password" class="mt-1 text-xs text-danger">{{ form.errors.password }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-semibold text-ink-2" for="password_confirmation">Confirm password</label>
              <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required
                class="w-full rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:ring-primary" />
            </div>
            <button type="submit" :disabled="form.processing"
              class="mt-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-60">
              Accept invite &amp; create account
            </button>
          </form>
        </template>

        <template v-else>
          <div class="py-6 text-center">
            <div class="text-3xl">✉️</div>
            <h1 class="mt-2 text-xl font-extrabold tracking-tight">Invite unavailable</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ message }}</p>
            <Link href="/" class="mt-5 inline-block rounded-lg border border-line bg-surface-2 px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface">Go to {{ siteName }}</Link>
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>
