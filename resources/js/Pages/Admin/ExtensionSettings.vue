<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface SettingField { key: string; label: string; type?: string; default?: unknown; help?: string; options?: { value: string; label: string }[] }
interface Ext {
  id: string; name: string; version: string; description: string; author: string;
  type: string; enabled: boolean; settings: SettingField[];
  values: Record<string, unknown>; adminUrl: string | null;
}

const props = defineProps<{ ext: Ext }>();

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const formValues = ref<Record<string, any>>({ ...props.ext.values });
const saving = ref(false);

function save() {
  saving.value = true;
  router.post('/admin/marketplace/settings', { id: props.ext.id, values: formValues.value }, {
    preserveScroll: true,
    onFinish: () => (saving.value = false),
  });
}
</script>

<template>
  <AdminLayout>
    <Head :title="`${ext.name} settings`" />
    <template #title>{{ ext.name }}</template>
    <template #subtitle>Settings · {{ ext.type }} · v{{ ext.version }}<span v-if="ext.author"> · by {{ ext.author }}</span></template>

    <div class="mb-4">
      <Link href="/admin/marketplace" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-400 hover:text-white">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
        Marketplace
      </Link>
    </div>

    <div class="max-w-2xl space-y-5">
      <div class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <div class="flex items-start gap-3">
          <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-lg"
            :class="ext.type === 'theme' ? 'bg-fuchsia-500/15 text-fuchsia-300' : 'bg-indigo-500/15 text-indigo-300'">
            {{ ext.type === 'theme' ? '🎨' : '🧩' }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm text-slate-300">{{ ext.description }}</p>
            <div class="mt-2 flex items-center gap-2">
              <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold"
                :class="ext.enabled ? 'bg-emerald-500/15 text-emerald-300' : 'bg-white/5 text-slate-400'">
                {{ ext.enabled ? 'Enabled' : 'Disabled' }}
              </span>
              <span class="text-[11px] text-slate-500">{{ ext.id }}</span>
            </div>
          </div>
        </div>
        <a v-if="ext.adminUrl" :href="ext.adminUrl"
          class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-500/15 px-3 py-2 text-sm font-semibold text-indigo-300 hover:bg-indigo-500/25">
          Open management page
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M7 7h10v10" /></svg>
        </a>
      </div>

      <form v-if="ext.settings.length" class="rounded-2xl border border-white/5 bg-[#14172a] p-5 space-y-4" @submit.prevent="save">
        <div v-for="field in ext.settings" :key="field.key">
          <label v-if="field.type === 'boolean'" class="flex items-center gap-3">
            <input type="checkbox" v-model="formValues[field.key]" class="rounded border-white/10 bg-[#0f1120] text-indigo-500 focus:ring-indigo-500" />
            <span class="text-sm font-medium text-slate-200">{{ field.label }}</span>
          </label>

          <template v-else>
            <label class="block text-sm font-medium text-slate-300">{{ field.label }}</label>
            <textarea v-if="field.type === 'textarea'" v-model="formValues[field.key]" rows="3"
              class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
            <select v-else-if="field.type === 'select'" v-model="formValues[field.key]"
              class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
              <option v-for="o in field.options || []" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <input v-else :type="field.type === 'number' ? 'number' : (field.type === 'color' ? 'color' : 'text')"
              v-model="formValues[field.key]"
              class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
          </template>
          <p v-if="field.help" class="mt-1 text-xs text-slate-500">{{ field.help }}</p>
        </div>

        <div class="flex items-center gap-3 pt-1">
          <button type="submit" :disabled="saving" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">
            {{ saving ? 'Saving…' : 'Save settings' }}
          </button>
        </div>
      </form>

      <p v-else class="rounded-2xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-500">
        This {{ ext.type }} has no configurable settings.
      </p>
    </div>
  </AdminLayout>
</template>
