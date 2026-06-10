<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  values: {
    configured: boolean;
    transport: string;
    from_address: string;
    from_name: string;
    smtp_host: string;
    smtp_port: number;
    smtp_username: string;
    smtp_password_set: boolean;
    smtp_encryption: string;
  };
}>();

const page = usePage();
const mailTest = computed(() => (page.props as any).flash?.mailTest ?? null);

const form = useForm({
  transport: props.values.transport || 'sendmail',
  from_address: props.values.from_address ?? '',
  from_name: props.values.from_name ?? '',
  smtp_host: props.values.smtp_host ?? '',
  smtp_port: props.values.smtp_port ?? 587,
  smtp_username: props.values.smtp_username ?? '',
  smtp_password: '',
  smtp_encryption: props.values.smtp_encryption || 'tls',
});

function save() {
  form.post('/admin/email', { preserveScroll: true });
}

const testTo = ref(props.values.from_address ?? '');
const sending = ref(false);
function sendTest() {
  if (!testTo.value) return;
  sending.value = true;
  router.post('/admin/email/test', { email: testTo.value }, {
    preserveScroll: true,
    onFinish: () => (sending.value = false),
  });
}
</script>

<template>
  <Head title="Admin · Email" />
  <AdminLayout>
    <template #title>Email</template>
    <template #subtitle>How your community sends mail — notifications, digests, password resets</template>

    <form class="max-w-2xl space-y-6" @submit.prevent="save">
      <!-- Transport -->
      <div class="rounded-2xl border border-white/5 bg-[#14172a] p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Sending method</h2>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4"
            :class="form.transport === 'sendmail' ? 'border-indigo-500 bg-indigo-500/10' : 'border-white/10 hover:border-white/20'">
            <input v-model="form.transport" type="radio" value="sendmail" class="mt-0.5 text-indigo-500 focus:ring-indigo-500" />
            <span>
              <span class="block text-sm font-semibold text-slate-100">Server mail (PHP)</span>
              <span class="block text-xs text-slate-400">Uses the host's built-in <code>sendmail</code>/PHP mail. No setup — works on most shared hosting.</span>
            </span>
          </label>
          <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4"
            :class="form.transport === 'smtp' ? 'border-indigo-500 bg-indigo-500/10' : 'border-white/10 hover:border-white/20'">
            <input v-model="form.transport" type="radio" value="smtp" class="mt-0.5 text-indigo-500 focus:ring-indigo-500" />
            <span>
              <span class="block text-sm font-semibold text-slate-100">SMTP</span>
              <span class="block text-xs text-slate-400">Send through a mail provider (Gmail, Mailgun, SES, Postmark…). More reliable deliverability.</span>
            </span>
          </label>
        </div>
      </div>

      <!-- From identity -->
      <div class="rounded-2xl border border-white/5 bg-[#14172a] p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">From address</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-300">From email</label>
            <input v-model="form.from_address" type="email" placeholder="community@example.com"
              class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
            <p v-if="form.errors.from_address" class="mt-1 text-xs text-red-400">{{ form.errors.from_address }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-300">From name</label>
            <input v-model="form.from_name" type="text" placeholder="(defaults to community name)"
              class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
        </div>
      </div>

      <!-- SMTP details -->
      <div v-if="form.transport === 'smtp'" class="rounded-2xl border border-white/5 bg-[#14172a] p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">SMTP server</h2>
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-300">Host</label>
            <input v-model="form.smtp_host" type="text" placeholder="smtp.example.com"
              class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-300">Port</label>
            <input v-model.number="form.smtp_port" type="number" min="1" max="65535"
              class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-300">Username</label>
            <input v-model="form.smtp_username" type="text" autocomplete="off"
              class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-300">Password</label>
            <input v-model="form.smtp_password" type="password" autocomplete="new-password"
              :placeholder="props.values.smtp_password_set ? '•••••••• (leave blank to keep)' : ''"
              class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-300">Encryption</label>
          <select v-model="form.smtp_encryption" class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
            <option value="tls">TLS / STARTTLS (port 587)</option>
            <option value="ssl">SSL (port 465)</option>
            <option value="none">None</option>
          </select>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">Save changes</button>
        <span v-if="form.recentlySuccessful" class="text-sm text-emerald-400">Saved.</span>
      </div>
    </form>

    <!-- Test -->
    <div class="mt-8 max-w-2xl rounded-2xl border border-white/5 bg-[#14172a] p-6">
      <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Send a test email</h2>
      <p class="mt-1 text-xs text-slate-500">Save your settings first, then send a test to confirm mail is going out.</p>
      <div class="mt-3 flex flex-wrap items-center gap-3">
        <input v-model="testTo" type="email" placeholder="you@example.com"
          class="w-64 rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
        <button type="button" :disabled="sending || !testTo" @click="sendTest"
          class="rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/5 disabled:opacity-60">
          {{ sending ? 'Sending…' : 'Send test' }}
        </button>
      </div>
      <p v-if="mailTest" class="mt-3 text-sm" :class="mailTest.ok ? 'text-emerald-400' : 'text-red-400'">{{ mailTest.message }}</p>
    </div>
  </AdminLayout>
</template>
