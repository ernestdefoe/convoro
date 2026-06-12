<script setup lang="ts">
import ConvoroLogo from '@/Components/ConvoroLogo.vue';
import Slot from '@/Components/ext/Slot.vue';
import { useAuthModal } from '@/lib/authModal';
import { t } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const { state, close, toggleMode } = useAuthModal();
const mode = computed(() => state.value.mode);

const inviteOnly = computed(() => !!(usePage().props as any).inviteOnly);
const sso = computed(() => (usePage().props as any).sso ?? { enabled: false });
const urlInvite = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('invite') ?? '' : '';

const login = useForm({ email: '', password: '', remember: false });
const register = useForm({ name: '', email: '', password: '', password_confirmation: '', invite: urlInvite });

// Reset fields/errors whenever the modal closes.
watch(() => state.value.open, (open) => {
  if (!open) { login.reset(); login.clearErrors(); register.reset(); register.clearErrors(); }
});

function submitLogin() {
  login.post('/login', { onSuccess: () => close(), onFinish: () => login.reset('password') });
}
function submitRegister() {
  register.post('/register', { onSuccess: () => close(), onFinish: () => register.reset('password', 'password_confirmation') });
}

const field = 'mt-1 w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary';
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0"
    leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0"
  >
    <div v-if="state.open" class="fixed inset-0 z-[80] flex items-center justify-center p-4" @keydown.esc="close">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close"></div>

      <div class="relative w-full max-w-md overflow-hidden rounded-c border border-line bg-surface shadow-2xl">
        <button type="button" class="absolute right-3 top-3 text-ink-muted hover:text-ink" aria-label="Close" @click="close">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>

        <div class="px-7 pb-7 pt-8">
          <div class="mb-5 flex flex-col items-center text-center">
            <ConvoroLogo :size="40" />
            <h2 class="mt-3 text-xl font-extrabold tracking-tight text-ink">
              {{ t(mode === 'login' ? 'Welcome back' : 'Join the community') }}
            </h2>
          </div>

          <!-- Single sign-on (OpenID Connect), when configured. -->
          <a v-if="sso.enabled" href="/auth/oidc/redirect"
            class="flex w-full items-center justify-center gap-2 rounded-c border border-line bg-surface-2 px-4 py-2.5 text-sm font-semibold text-ink hover:bg-appbg">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" /></svg>
            {{ sso.label }}
          </a>

          <!-- Social login — provided by the Social Login extension via this slot. -->
          <Slot name="auth:providers" />

          <!-- Login -->
          <form v-if="mode === 'login'" class="space-y-4" @submit.prevent="submitLogin">
            <div>
              <label class="text-sm font-semibold text-ink-2">{{ t('Email') }}</label>
              <input v-model="login.email" type="email" autocomplete="email" :class="field" />
              <p v-if="login.errors.email" class="mt-1 text-sm text-red-500">{{ login.errors.email }}</p>
            </div>
            <div>
              <label class="text-sm font-semibold text-ink-2">{{ t('Password') }}</label>
              <input v-model="login.password" type="password" autocomplete="current-password" :class="field" />
              <p v-if="login.errors.password" class="mt-1 text-sm text-red-500">{{ login.errors.password }}</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-2">
              <input v-model="login.remember" type="checkbox" class="rounded border-line text-primary focus:ring-primary" /> {{ t('Remember me') }}
            </label>
            <button type="submit" :disabled="login.processing" class="w-full rounded-c bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60">
              {{ login.processing ? t('Signing in…') : t('Sign in') }}
            </button>
          </form>

          <!-- Register -->
          <form v-else class="space-y-4" @submit.prevent="submitRegister">
            <div>
              <label class="text-sm font-semibold text-ink-2">{{ t('Name') }}</label>
              <input v-model="register.name" type="text" autocomplete="name" :class="field" />
              <p v-if="register.errors.name" class="mt-1 text-sm text-red-500">{{ register.errors.name }}</p>
            </div>
            <div>
              <label class="text-sm font-semibold text-ink-2">{{ t('Email') }}</label>
              <input v-model="register.email" type="email" autocomplete="email" :class="field" />
              <p v-if="register.errors.email" class="mt-1 text-sm text-red-500">{{ register.errors.email }}</p>
            </div>
            <div>
              <label class="text-sm font-semibold text-ink-2">{{ t('Password') }}</label>
              <input v-model="register.password" type="password" autocomplete="new-password" :class="field" />
              <p v-if="register.errors.password" class="mt-1 text-sm text-red-500">{{ register.errors.password }}</p>
            </div>
            <div>
              <label class="text-sm font-semibold text-ink-2">{{ t('Confirm password') }}</label>
              <input v-model="register.password_confirmation" type="password" autocomplete="new-password" :class="field" />
            </div>
            <div v-if="inviteOnly">
              <label class="text-sm font-semibold text-ink-2">{{ t('Invite code') }}</label>
              <input v-model="register.invite" type="text" :placeholder="t('Required to join')" :class="field" />
              <p v-if="register.errors.invite" class="mt-1 text-sm text-red-500">{{ register.errors.invite }}</p>
            </div>
            <button type="submit" :disabled="register.processing" class="w-full rounded-c bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60">
              {{ register.processing ? t('Creating account…') : t('Create account') }}
            </button>
          </form>

          <p class="mt-5 text-center text-sm text-ink-muted">
            <template v-if="mode === 'login'">{{ t('New here?') }} <button type="button" class="font-semibold text-primary-700 hover:underline" @click="toggleMode">{{ t('Create an account') }}</button></template>
            <template v-else>{{ t('Already a member?') }} <button type="button" class="font-semibold text-primary-700 hover:underline" @click="toggleMode">{{ t('Sign in') }}</button></template>
          </p>
        </div>
      </div>
    </div>
  </Transition>
</template>
