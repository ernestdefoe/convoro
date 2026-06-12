<script setup lang="ts">
import { isStandalone, pushSupported, subscribeToPush } from '@/lib/push';
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { t } from '@/lib/i18n';

const DISMISS_KEY = 'convoro_pwa_banner_dismissed';

const page = usePage();
const vapidKey = (): string => (page.props as any).pushKey ?? '';
const loggedIn = (): boolean => !!(page.props as any).auth?.user;

const show = ref(false);
const mode = ref<'install' | 'ios' | 'push'>('install');
const busy = ref(false);
let deferred: any = null;

const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) && !(window as any).MSStream;

function dismissed(): boolean {
  try { return localStorage.getItem(DISMISS_KEY) === '1'; } catch { return false; }
}
function dismiss() {
  show.value = false;
  try { localStorage.setItem(DISMISS_KEY, '1'); } catch { /* ignore */ }
}

function onBeforeInstall(e: Event) {
  e.preventDefault();
  deferred = e;
  if (!dismissed() && !isStandalone()) { mode.value = 'install'; show.value = true; }
}

onMounted(() => {
  if (dismissed()) return;
  if ((page.props as any).site?.pwaBanner === false) return; // disabled in admin settings
  window.addEventListener('beforeinstallprompt', onBeforeInstall);

  // iOS Safari fires no install event — offer manual "Add to Home Screen".
  if (isIos() && !isStandalone()) { mode.value = 'ios'; show.value = true; return; }

  // Already installed + logged in + push available but not yet granted → nudge to enable.
  if (isStandalone() && loggedIn() && pushSupported() && vapidKey() && Notification.permission === 'default') {
    mode.value = 'push';
    show.value = true;
  }
});

onBeforeUnmount(() => window.removeEventListener('beforeinstallprompt', onBeforeInstall));

async function install() {
  if (!deferred) { dismiss(); return; }
  busy.value = true;
  try {
    deferred.prompt();
    await deferred.userChoice.catch(() => {});
  } finally {
    deferred = null;
    busy.value = false;
    dismiss();
  }
}

async function enablePush() {
  busy.value = true;
  try { await subscribeToPush(vapidKey()); } finally { busy.value = false; dismiss(); }
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="translate-y-4 opacity-0"
    leave-active-class="transition duration-200 ease-in"
    leave-to-class="translate-y-4 opacity-0"
  >
    <div
      v-if="show"
      class="fixed inset-x-3 bottom-3 z-[60] mx-auto max-w-md rounded-c border border-line bg-surface p-4 shadow-2xl shadow-black/20 sm:inset-x-auto sm:right-4"
      role="dialog"
      :aria-label="t('Install Convoro')"
    >
      <div class="flex items-start gap-3">
        <img src="/icons/icon-192.png" alt="" width="44" height="44" class="rounded-xl" />
        <div class="min-w-0 flex-1">
          <template v-if="mode === 'install'">
            <p class="text-sm font-bold text-ink">{{ t('Install the Convoro app') }}</p>
            <p class="mt-0.5 text-sm text-ink-muted">{{ t('Add it to your home screen for a faster, full-screen experience with push notifications.') }}</p>
            <div class="mt-3 flex gap-2">
              <button type="button" :disabled="busy" class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60" @click="install">{{ t('Install') }}</button>
              <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="dismiss">{{ t('Not now') }}</button>
            </div>
          </template>

          <template v-else-if="mode === 'ios'">
            <p class="text-sm font-bold text-ink">{{ t('Install Convoro') }}</p>
            <p class="mt-0.5 text-sm text-ink-muted">{{ t('Tap the Share button, then') }} <strong>{{ t('Add to Home Screen') }}</strong> {{ t('to install the app and unlock notifications.') }}</p>
            <div class="mt-3">
              <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="dismiss">{{ t('Got it') }}</button>
            </div>
          </template>

          <template v-else>
            <p class="text-sm font-bold text-ink">{{ t('Turn on notifications') }}</p>
            <p class="mt-0.5 text-sm text-ink-muted">{{ t('Get notified about replies, mentions and reactions.') }}</p>
            <div class="mt-3 flex gap-2">
              <button type="button" :disabled="busy" class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60" @click="enablePush">{{ t('Enable') }}</button>
              <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="dismiss">{{ t('No thanks') }}</button>
            </div>
          </template>
        </div>
        <button type="button" :aria-label="t('Dismiss')" class="text-ink-muted hover:text-ink" @click="dismiss">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
      </div>
    </div>
  </Transition>
</template>
