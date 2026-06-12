<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  values: { enabled: boolean; label: string; issuer: string; client_id: string; has_secret: boolean; scopes: string; auto_create: boolean; allowed_domain: string };
  callbackUrl: string;
}>();

const form = useForm({
  enabled: props.values.enabled,
  label: props.values.label ?? '',
  issuer: props.values.issuer ?? '',
  client_id: props.values.client_id ?? '',
  client_secret: '',
  scopes: props.values.scopes ?? 'openid profile email',
  auto_create: props.values.auto_create,
  allowed_domain: props.values.allowed_domain ?? '',
});

function save() {
  form.post('/admin/sso', { preserveScroll: true, onSuccess: () => form.reset('client_secret') });
}

const copied = ref(false);
async function copyCallback() {
  try { await navigator.clipboard.writeText(props.callbackUrl); copied.value = true; setTimeout(() => (copied.value = false), 1600); } catch { /* ignore */ }
}

const field = 'mt-1.5 w-full rounded-lg border-line bg-appbg text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary';
</script>

<template>
  <Head :title="t('Admin · Single sign-on')" />
  <AdminLayout>
    <template #title>{{ t('Single sign-on') }}</template>
    <template #subtitle>{{ t('Let members sign in with your identity provider via OpenID Connect') }}</template>

    <div class="max-w-2xl space-y-6">
      <section class="rounded-2xl border border-line bg-surface p-5">
        <label class="flex items-center gap-2 text-sm font-semibold text-ink">
          <input v-model="form.enabled" type="checkbox" class="rounded border-line text-primary focus:ring-primary" />
          {{ t('Enable single sign-on') }}
        </label>
        <p class="mt-1.5 text-xs text-ink-muted">{{ t('Works with any OpenID Connect provider — Okta, Auth0, Azure AD, Google Workspace, Authentik, Keycloak and more.') }}</p>
      </section>

      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="text-sm font-bold text-ink">{{ t('Provider') }}</h3>

        <div class="mt-3">
          <label class="text-xs font-semibold text-ink-2">{{ t('Button label') }}</label>
          <input v-model="form.label" :class="field" placeholder="Okta" />
          <p class="mt-1 text-xs text-ink-muted">{{ t('Shown on the sign-in button, e.g. “Sign in with Okta”.') }}</p>
        </div>

        <div class="mt-4">
          <label class="text-xs font-semibold text-ink-2">{{ t('Issuer URL') }}</label>
          <input v-model="form.issuer" :class="field" placeholder="https://your-org.okta.com" />
          <p v-if="form.errors.issuer" class="mt-1 text-xs text-red-500">{{ form.errors.issuer }}</p>
          <p class="mt-1 text-xs text-ink-muted">{{ t('We read the endpoints from {issuer}/.well-known/openid-configuration.', { issuer: '<issuer>' }) }}</p>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="text-xs font-semibold text-ink-2">{{ t('Client ID') }}</label>
            <input v-model="form.client_id" :class="field" />
          </div>
          <div>
            <label class="text-xs font-semibold text-ink-2">{{ t('Client secret') }}</label>
            <input v-model="form.client_secret" type="password" :class="field" :placeholder="values.has_secret ? '••••••••  ' + t('(unchanged)') : ''" />
          </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="text-xs font-semibold text-ink-2">{{ t('Scopes') }}</label>
            <input v-model="form.scopes" :class="field" placeholder="openid profile email" />
          </div>
          <div>
            <label class="text-xs font-semibold text-ink-2">{{ t('Restrict to email domain') }} <span class="font-normal text-ink-muted">{{ t('(optional)') }}</span></label>
            <input v-model="form.allowed_domain" :class="field" placeholder="example.com" />
          </div>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm text-ink-2">
          <input v-model="form.auto_create" type="checkbox" class="rounded border-line text-primary focus:ring-primary" />
          {{ t('Create an account automatically the first time someone signs in') }}
        </label>
      </section>

      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="text-sm font-bold text-ink">{{ t('Redirect URI') }}</h3>
        <p class="mt-1 text-xs text-ink-muted">{{ t('Add this exact URL to your provider’s allowed redirect/callback URIs.') }}</p>
        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
          <input :value="callbackUrl" readonly class="w-full truncate rounded-lg border-line bg-appbg text-sm text-ink-2" @focus="($event.target as HTMLInputElement).select()" />
          <button class="shrink-0 rounded-lg bg-surface-2 px-3 py-2 text-sm font-semibold text-ink hover:bg-appbg" @click="copyCallback">{{ copied ? t('Copied!') : t('Copy') }}</button>
        </div>
      </section>

      <div>
        <button :disabled="form.processing" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="save">
          {{ form.processing ? t('Saving…') : t('Save') }}
        </button>
      </div>
    </div>
  </AdminLayout>
</template>
