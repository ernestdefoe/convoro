<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{ licenseKey: string | null; sessionId: string | null }>();

const key = ref(props.licenseKey);
const copied = ref(false);
let timer: number | undefined;

// The Stripe webhook issues the key asynchronously — poll until it lands.
async function poll() {
  if (key.value || !props.sessionId) return;
  try {
    const r = await fetch(`/convorocp/license-status?session_id=${encodeURIComponent(props.sessionId)}`, {
      headers: { Accept: 'application/json' },
    });
    const d = await r.json();
    if (d.key) { key.value = d.key; if (timer) clearInterval(timer); }
  } catch (e) { /* keep polling */ }
}
onMounted(() => { if (!key.value && props.sessionId) timer = window.setInterval(poll, 2500); });
onUnmounted(() => { if (timer) clearInterval(timer); });

function copyKey() {
  if (!key.value) return;
  navigator.clipboard?.writeText(key.value).then(() => {
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  });
}
</script>

<template>
  <Head title="Welcome to ConvoroCP" />
  <MarketingLayout>
    <section class="mx-auto max-w-2xl px-6 py-20 text-center">
      <div class="text-5xl">🎉</div>
      <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ t('You’re subscribed') }}</h1>
      <p class="mx-auto mt-3 max-w-xl text-lg text-ink-2">{{ t('Thanks for subscribing to ConvoroCP. Here’s your license key — activate it in your panel to unlock it permanently.') }}</p>

      <div class="mt-8 rounded-2xl border border-line bg-surface p-6">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-muted">{{ t('Your license key') }}</div>
        <div v-if="key" class="mt-3 flex items-center justify-center gap-3">
          <code class="rounded-lg bg-surface-2 px-4 py-2 font-mono text-lg font-bold tracking-wide">{{ key }}</code>
          <button type="button" @click="copyKey" class="rounded-lg border border-line px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ copied ? t('Copied') : t('Copy') }}</button>
        </div>
        <div v-else class="mt-3 text-ink-2">
          <span class="inline-block animate-pulse">{{ t('Generating your key… this takes a few seconds.') }}</span>
        </div>
      </div>

      <div class="mt-8 text-left">
        <h2 class="text-sm font-bold uppercase tracking-wide text-primary">{{ t('Activate it') }}</h2>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-ink-2">
          <li>{{ t('Open your ConvoroCP panel and sign in as the operator.') }}</li>
          <li>{{ t('Go to the License page in the sidebar.') }}</li>
          <li>{{ t('Paste your key and save — the panel verifies it and unlocks immediately.') }}</li>
        </ol>
        <p class="mt-4 text-sm text-ink-muted">{{ t('A receipt has been emailed to you. The panel re-checks your license daily; if you cancel, it returns to the license screen but keeps your sites running.') }}</p>
      </div>
    </section>
  </MarketingLayout>
</template>
