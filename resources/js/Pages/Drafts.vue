<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useComposer } from '@/lib/composer';
import { t as tr } from '@/lib/i18n';

defineProps<{ drafts: { id: number; title: string; excerpt: string; scheduledAt: string | null; scheduledLabel: string | null; isScheduled: boolean; updated: string; hasPoll: boolean }[] }>();

const composer = useComposer();

function publish(id: number) {
  router.post(`/drafts/${id}/publish`, {}, { preserveScroll: true });
}
function remove(id: number) {
  if (confirm(tr('Delete this draft? This can’t be undone.'))) router.delete(`/drafts/${id}`, { preserveScroll: true });
}
</script>

<template>
  <Head :title="tr('Drafts')" />
  <AppLayout>
    <div class="mx-auto max-w-2xl px-4 py-8">
      <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">{{ tr('Drafts & scheduled') }}</h1>
        <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="composer.open()">{{ tr('Start a topic') }}</button>
      </div>

      <div v-if="drafts.length" class="space-y-3">
        <div v-for="d in drafts" :key="d.id" class="rounded-2xl border border-line bg-surface p-4">
          <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="truncate font-bold text-ink">{{ d.title }}</h3>
                <span v-if="d.isScheduled" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-bold text-primary">🕑 {{ tr('Scheduled') }} {{ d.scheduledLabel }}</span>
                <span v-else class="rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-bold text-ink-muted">{{ tr('Draft') }}</span>
                <span v-if="d.hasPoll" class="rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-semibold text-ink-muted">📊 {{ tr('Poll') }}</span>
              </div>
              <p v-if="d.excerpt" class="mt-1 line-clamp-2 text-sm text-ink-2">{{ d.excerpt }}</p>
              <p class="mt-1 text-xs text-ink-muted">{{ tr('Edited {when}', { when: d.updated }) }}</p>
            </div>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
            <button type="button" class="rounded-lg bg-surface-2 px-3 py-1.5 text-sm font-semibold text-ink hover:bg-appbg" @click="composer.open({ draftId: d.id })">{{ tr('Edit') }}</button>
            <button type="button" class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-600" @click="publish(d.id)">{{ d.isScheduled ? tr('Publish now') : tr('Publish') }}</button>
            <button type="button" class="ml-auto rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-2 hover:text-red-500" @click="remove(d.id)">{{ tr('Delete') }}</button>
          </div>
        </div>
      </div>

      <EmptyState v-else icon="📝" :title="tr('No drafts yet')" :body="tr('Save a topic as a draft to finish later, or schedule it to publish at a set time.')">
        <button type="button" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-600" @click="composer.open()">{{ tr('Start a topic') }}</button>
      </EmptyState>
    </div>
  </AppLayout>
</template>
