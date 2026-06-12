<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { currentSubscription, pushSupported, subscribeToPush, unsubscribeFromPush } from '@/lib/push';
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { t } from '@/lib/i18n';

const page = usePage();
const vapidKey = (): string => (page.props as any).pushKey ?? '';

const supported = ref(false);
const subscribed = ref(false);
const busy = ref(false);
const error = ref('');

onMounted(async () => {
  supported.value = pushSupported() && !!vapidKey();
  if (!supported.value) return;
  subscribed.value = !!(await currentSubscription());
});

async function enable() {
  error.value = '';
  busy.value = true;
  try {
    const ok = await subscribeToPush(vapidKey());
    if (ok) subscribed.value = true;
    else error.value = t('Permission was denied or push is unavailable.');
  } catch (e: any) {
    error.value = e?.message ?? t('Could not enable push.');
  } finally {
    busy.value = false;
  }
}

async function disable() {
  busy.value = true;
  try {
    await unsubscribeFromPush();
    subscribed.value = false;
  } catch (e: any) {
    error.value = e?.message ?? t('Could not disable push.');
  } finally {
    busy.value = false;
  }
}

const testing = ref(false);
const testMsg = ref('');
async function sendTest() {
  testing.value = true;
  testMsg.value = '';
  try {
    const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const r = await fetch('/push/test', { method: 'POST', headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    testMsg.value = r.ok ? t('Sent! Check your device — it can take a moment.') : t('Could not send. Make sure push is enabled on this device.');
  } catch {
    testMsg.value = t('Could not send the test notification.');
  } finally {
    testing.value = false;
  }
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ t('Push notifications') }}</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ t("Get browser notifications for replies, mentions and reactions — even when {app} isn't open.", { app: 'Convoro' }) }}
      </p>
    </header>

    <div class="mt-6">
      <p v-if="!supported" class="text-sm text-gray-500 dark:text-gray-400">
        {{ t("Push isn't available in this browser (or the site isn't served over HTTPS).") }}
      </p>

      <template v-else>
        <PrimaryButton v-if="!subscribed" :disabled="busy" @click="enable">
          {{ busy ? t('Enabling…') : t('Enable push notifications') }}
        </PrimaryButton>
        <SecondaryButton v-else :disabled="busy" @click="disable">
          {{ busy ? t('Disabling…') : t('Disable push notifications') }}
        </SecondaryButton>

        <p v-if="subscribed" class="mt-2 text-sm text-green-600 dark:text-green-400">{{ t('Push is enabled on this device.') }}</p>
        <p v-if="error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>

        <div v-if="subscribed" class="mt-4">
          <SecondaryButton :disabled="testing" @click="sendTest">{{ testing ? t('Sending…') : t('Send a test notification') }}</SecondaryButton>
          <p v-if="testMsg" class="mt-2 text-sm text-ink-2">{{ testMsg }}</p>
        </div>
      </template>
    </div>
  </section>
</template>
