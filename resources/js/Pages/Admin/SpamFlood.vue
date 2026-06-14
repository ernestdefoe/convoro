<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  values: {
    enabled: boolean; exempt_trusted: boolean;
    min_seconds: number; new_user_seconds: number;
    max_posts_per_hour: number; max_topics_per_hour: number;
    duplicate_minutes: number; max_links: number;
    banned_words: string; banned_domains: string; content_action: string;
    first_post_approval: boolean; first_post_count: number;
  };
  pending: number;
}>();

const form = useForm({ ...props.values });

function save() {
  form.post('/admin/spam-flood', { preserveScroll: true });
}
</script>

<template>
  <Head :title="t('Admin · Spam & flood')" />
  <AdminLayout>
    <template #title>{{ t('Spam & flood') }}</template>
    <template #subtitle>{{ t('Slow mode, rate limits and content filters that keep spam and floods out') }}</template>

    <form class="max-w-2xl space-y-6" @submit.prevent="save">
      <!-- Master switch -->
      <div class="rounded-2xl border border-line bg-surface p-6 space-y-4">
        <label class="flex items-center gap-3">
          <input v-model="form.enabled" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
          <span class="text-sm font-semibold text-ink">{{ t('Enable spam, flood & abuse controls') }}</span>
        </label>
        <p class="text-xs text-ink-muted">{{ t('Administrators are never affected by these rules. When a post is blocked the member sees a short explanation; held posts wait in Moderation for approval.') }}</p>
        <label class="flex items-center gap-3">
          <input v-model="form.exempt_trusted" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
          <span class="text-sm text-ink-2">{{ t('Exempt trusted members (Member level and above) from the flood limits and slow mode') }}</span>
        </label>
        <p v-if="pending > 0" class="text-xs">
          <Link href="/admin/moderation" class="font-semibold text-amber-600 hover:text-amber-500 dark:text-amber-400">
            {{ t(':n post(s) are waiting for approval →', { n: pending }) }}
          </Link>
        </p>
      </div>

      <!-- Slow mode -->
      <div class="rounded-2xl border border-line bg-surface p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Slow mode') }}</h2>
        <p class="text-xs text-ink-muted">{{ t('A minimum gap between a member’s posts. Set per-category cooldowns when editing a category.') }}</p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-ink-2">{{ t('Seconds between posts') }}</label>
            <input v-model.number="form.min_seconds" type="number" min="0" max="3600" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
            <p class="mt-1 text-xs text-ink-muted">{{ t('0 = off') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-ink-2">{{ t('New members — seconds between posts') }}</label>
            <input v-model.number="form.new_user_seconds" type="number" min="0" max="3600" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
            <p class="mt-1 text-xs text-ink-muted">{{ t('Applied to brand-new (level 0) accounts.') }}</p>
          </div>
        </div>
      </div>

      <!-- Flood limits -->
      <div class="rounded-2xl border border-line bg-surface p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Flood limits') }}</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-ink-2">{{ t('Max posts per hour') }}</label>
            <input v-model.number="form.max_posts_per_hour" type="number" min="0" max="1000" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-ink-2">{{ t('Max new topics per hour') }}</label>
            <input v-model.number="form.max_topics_per_hour" type="number" min="0" max="500" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-ink-2">{{ t('Block identical reposts for (minutes)') }}</label>
            <input v-model.number="form.duplicate_minutes" type="number" min="0" max="1440" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
          </div>
        </div>
        <p class="text-xs text-ink-muted">{{ t('0 disables that limit.') }}</p>
      </div>

      <!-- Content filters -->
      <div class="rounded-2xl border border-line bg-surface p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Content filters') }}</h2>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Banned words & phrases') }}</label>
          <p class="text-xs text-ink-muted">{{ t('One per line (or comma-separated). Single words match whole words; multi-word entries match anywhere. Case-insensitive.') }}</p>
          <textarea v-model="form.banned_words" rows="4" class="mt-1.5 w-full rounded-lg border-line bg-appbg font-mono text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" :placeholder="t('cheap rolex\nbuy followers')" />
        </div>
        <div>
          <label class="block text-sm font-medium text-ink-2">{{ t('Banned link domains') }}</label>
          <p class="text-xs text-ink-muted">{{ t('One per line. Matches the domain and any subdomain (e.g. spam.com also blocks shop.spam.com).') }}</p>
          <textarea v-model="form.banned_domains" rows="3" class="mt-1.5 w-full rounded-lg border-line bg-appbg font-mono text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" :placeholder="t('spam.example\nt.me')" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-ink-2">{{ t('Max links per post') }}</label>
            <input v-model.number="form.max_links" type="number" min="0" max="100" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
            <p class="mt-1 text-xs text-ink-muted">{{ t('0 = unlimited') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-ink-2">{{ t('When a filter matches') }}</label>
            <select v-model="form.content_action" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500">
              <option value="block">{{ t('Block the post') }}</option>
              <option value="hold">{{ t('Hold for approval') }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- First-post approval -->
      <div class="rounded-2xl border border-line bg-surface p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('First-post approval') }}</h2>
        <label class="flex items-center gap-3">
          <input v-model="form.first_post_approval" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
          <span class="text-sm text-ink-2">{{ t('Hold a new member’s first posts until a moderator approves them') }}</span>
        </label>
        <div v-if="form.first_post_approval">
          <label class="block text-sm font-medium text-ink-2">{{ t('How many first posts to hold') }}</label>
          <input v-model.number="form.first_post_count" type="number" min="1" max="50" class="mt-1.5 w-32 rounded-lg border-line bg-appbg text-ink focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60">{{ t('Save changes') }}</button>
        <span v-if="form.recentlySuccessful" class="text-sm font-medium text-emerald-500">{{ t('Saved') }}</span>
      </div>
    </form>
  </AdminLayout>
</template>
