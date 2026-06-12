<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { onMounted, ref } from 'vue';
import { t as tr } from '@/lib/i18n';

type Token = { id: number; name: string; lastUsed: string; created: string };

const tokens = ref<Token[]>([]);
const name = ref('');
const busy = ref(false);
const error = ref('');
const justCreated = ref<string | null>(null);
const copied = ref(false);

function xsrf(): string {
  const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}
async function api(method: string, url: string, body?: unknown) {
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) throw new Error('Request failed');
  return res.json();
}

async function load() {
  try { tokens.value = await api('GET', '/user/tokens'); } catch { /* ignore */ }
}
onMounted(load);

async function create() {
  error.value = '';
  if (!name.value.trim()) return;
  busy.value = true;
  try {
    const res = await api('POST', '/user/tokens', { name: name.value.trim() });
    justCreated.value = res.plainTextToken;
    copied.value = false;
    tokens.value.unshift(res.token);
    name.value = '';
  } catch {
    error.value = tr('Could not create the token.');
  } finally {
    busy.value = false;
  }
}

async function revoke(t: Token) {
  if (!confirm(tr('Revoke “{name}”? Apps using it will stop working.', { name: t.name }))) return;
  try {
    await api('DELETE', `/user/tokens/${t.id}`);
    tokens.value = tokens.value.filter((x) => x.id !== t.id);
  } catch { /* ignore */ }
}

async function copy() {
  if (!justCreated.value) return;
  try { await navigator.clipboard.writeText(justCreated.value); copied.value = true; } catch { /* ignore */ }
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ tr('API access tokens') }}</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ tr('Personal tokens let external apps or scripts access the API as you. Treat them like passwords.') }}
      </p>
    </header>

    <!-- newly created token (shown once) -->
    <div v-if="justCreated" class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
      <p class="text-sm font-medium text-amber-800 dark:text-amber-300">{{ tr('Copy your new token now — it won’t be shown again.') }}</p>
      <div class="mt-2 flex items-center gap-2">
        <code class="flex-1 truncate rounded bg-white px-2 py-1.5 font-mono text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ justCreated }}</code>
        <button type="button" class="rounded-md bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-700" @click="copy">{{ copied ? tr('Copied!') : tr('Copy') }}</button>
      </div>
    </div>

    <!-- create form -->
    <form class="mt-6 flex items-end gap-3" @submit.prevent="create">
      <div class="flex-1">
        <InputLabel for="token_name" :value="tr('Token name')" />
        <input id="token_name" v-model="name" type="text" maxlength="60" :placeholder="tr('e.g. My script')"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
      </div>
      <PrimaryButton :disabled="busy">{{ tr('Create') }}</PrimaryButton>
    </form>
    <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>

    <!-- existing tokens -->
    <ul class="mt-6 divide-y divide-gray-200 dark:divide-gray-700">
      <li v-for="t in tokens" :key="t.id" class="flex items-center justify-between py-3">
        <div>
          <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ t.name }}</div>
          <div class="text-xs text-gray-500 dark:text-gray-400">{{ tr('Created {created} · last used {lastUsed}', { created: t.created, lastUsed: t.lastUsed }) }}</div>
        </div>
        <button type="button" class="text-sm font-semibold text-red-600 hover:text-red-700" @click="revoke(t)">{{ tr('Revoke') }}</button>
      </li>
      <li v-if="!tokens.length" class="py-3 text-sm text-gray-500 dark:text-gray-400">{{ tr('No tokens yet.') }}</li>
    </ul>
  </section>
</template>
