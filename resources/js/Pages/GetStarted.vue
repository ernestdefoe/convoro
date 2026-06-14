<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConvoroLogo from '@/Components/ConvoroLogo.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  plans: any[];
  selectedPlan: string | null;
  mode: string;
  hostingAvailable: boolean;
}>();

const form = useForm({
  mode: props.hostingAvailable ? (props.mode || 'managed') : 'self',
  plan: props.selectedPlan || (props.plans[1]?.id ?? props.plans[0]?.id ?? 'starter'),
  community_name: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const chosen = computed(() => props.plans.find((p) => p.id === form.plan) || props.plans[0]);
const managed = computed(() => form.mode === 'managed');

function submit() {
  form.post('/get-started', { onError: () => { /* errors render inline */ } });
}
const field = 'mt-1 w-full rounded-lg border-line bg-appbg text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary';
</script>

<template>
  <Head :title="t('Get started')" />
  <div class="min-h-screen bg-appbg text-ink">
    <header class="border-b border-line bg-surface/80 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-5xl items-center px-6">
        <ConvoroLogo />
        <Link href="/login" class="ml-auto text-sm font-semibold text-ink-2 hover:text-ink">{{ t('Log in') }}</Link>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-10">
      <h1 class="text-3xl font-extrabold">{{ t('Start your Convoro community') }}</h1>
      <p class="mt-2 text-ink-2">{{ t('One account gets you a community on our support forum. Let us host it for you, or host it yourself — your call.') }}</p>

      <!-- mode toggle -->
      <div v-if="hostingAvailable" class="mt-6 inline-flex rounded-xl border border-line p-1">
        <button type="button" class="rounded-lg px-4 py-2 text-sm font-bold" :class="managed ? 'bg-primary text-white' : 'text-ink-muted hover:text-ink'" @click="form.mode = 'managed'">{{ t('Managed hosting') }}</button>
        <button type="button" class="rounded-lg px-4 py-2 text-sm font-bold" :class="!managed ? 'bg-primary text-white' : 'text-ink-muted hover:text-ink'" @click="form.mode = 'self'">{{ t('Self-host (free)') }}</button>
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <!-- left: plans (managed only) -->
        <section v-if="managed" class="lg:col-span-3">
          <h2 class="text-sm font-bold text-ink-2">{{ t('Choose a plan') }}</h2>
          <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <button v-for="p in plans" :key="p.id" type="button" @click="form.plan = p.id"
              class="rounded-2xl border p-4 text-left transition"
              :class="form.plan === p.id ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-line hover:border-primary/40'">
              <div class="text-sm font-black text-ink">{{ p.name }}</div>
              <div class="mt-1"><span class="text-2xl font-black text-ink">{{ p.price }}</span><span class="text-xs text-ink-muted">/mo</span></div>
              <div class="mt-1 text-xs font-semibold text-primary">{{ p.storageGb }} GB storage</div>
              <p class="mt-2 text-[11px] text-ink-muted">{{ p.blurb }}</p>
            </button>
          </div>
          <ul class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm text-ink-2">
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Unlimited members') }}</li>
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Unlimited bandwidth') }}</li>
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ chosen.storageGb }} GB {{ t('storage') }}</li>
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Custom domain + HTTPS') }}</li>
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Import from any forum') }}</li>
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Live theme editor + extensions') }}</li>
          </ul>
        </section>

        <!-- self-host blurb -->
        <section v-else class="lg:col-span-3">
          <div class="rounded-2xl border border-line bg-surface p-6">
            <h2 class="text-lg font-extrabold text-ink">{{ t('Host Convoro yourself — free') }}</h2>
            <p class="mt-2 text-sm text-ink-2">{{ t('Create a free account on our community for support and the extension directory. Then download Convoro, run it on your own hosting, and import your existing forum from Admin → Import — including straight from a database file.') }}</p>
            <ul class="mt-4 space-y-1.5 text-sm text-ink-2">
              <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Free, open-source — runs on cheap shared hosting') }}</li>
              <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Import from phpBB, XenForo, vBulletin, Flarum and more') }}</li>
              <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('A community account for help + the extension marketplace') }}</li>
            </ul>
            <a href="/download" class="mt-4 inline-block rounded-lg border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-surface-2">{{ t('Download Convoro') }}</a>
          </div>
        </section>

        <!-- right: signup form -->
        <section class="lg:col-span-2">
          <form class="rounded-2xl border border-line bg-surface p-6" @submit.prevent="submit">
            <h2 class="text-sm font-bold text-ink">{{ managed ? t('Create your community') : t('Create your free account') }}</h2>

            <div v-if="managed" class="mt-3">
              <label class="text-xs font-semibold text-ink-2">{{ t('Community name') }}</label>
              <input v-model="form.community_name" type="text" :placeholder="t('My Community')" :class="field" />
              <p v-if="form.errors.community_name" class="mt-1 text-xs text-red-500">{{ form.errors.community_name }}</p>
            </div>

            <div class="mt-3">
              <label class="text-xs font-semibold text-ink-2">{{ t('Your name') }}</label>
              <input v-model="form.name" type="text" :class="field" />
              <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
            </div>
            <div class="mt-3">
              <label class="text-xs font-semibold text-ink-2">{{ t('Email') }}</label>
              <input v-model="form.email" type="email" :class="field" />
              <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
            </div>
            <div class="mt-3">
              <label class="text-xs font-semibold text-ink-2">{{ t('Password') }}</label>
              <input v-model="form.password" type="password" :class="field" />
              <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
            </div>
            <div class="mt-3">
              <label class="text-xs font-semibold text-ink-2">{{ t('Confirm password') }}</label>
              <input v-model="form.password_confirmation" type="password" :class="field" />
            </div>

            <button type="submit" :disabled="form.processing" class="mt-5 w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-50">
              <template v-if="managed">{{ form.processing ? t('Setting up…') : t('Create my community — {price}/mo', { price: chosen.price }) }}</template>
              <template v-else>{{ form.processing ? t('Creating…') : t('Create free account') }}</template>
            </button>
            <p class="mt-3 text-center text-[11px] text-ink-muted">{{ t('By continuing you agree to the terms. Already have an account?') }} <Link href="/login" class="text-primary hover:underline">{{ t('Log in') }}</Link></p>
          </form>
        </section>
      </div>
    </main>
  </div>
</template>
