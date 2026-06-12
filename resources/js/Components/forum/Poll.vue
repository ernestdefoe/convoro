<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useAuthModal } from '@/lib/authModal';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{ poll: any }>();
const page = usePage();
const authModal = useAuthModal();
const loggedIn = computed(() => !!(page.props as any).auth?.user);

const local = ref<any>({ ...props.poll });
const selected = ref<number[]>([...(props.poll.myVotes || [])]);
const showResults = ref<boolean>(props.poll.hasVoted || props.poll.closed);
const submitting = ref(false);

const canVote = computed(() => loggedIn.value && !local.value.closed);

function toggle(id: number) {
  if (!canVote.value) { if (!loggedIn.value) authModal.open('login'); return; }
  if (local.value.multiple) {
    const i = selected.value.indexOf(id);
    if (i >= 0) selected.value.splice(i, 1);
    else if (selected.value.length < local.value.maxChoices) selected.value.push(id);
  } else {
    selected.value = [id];
  }
}

async function submit() {
  if (!selected.value.length || submitting.value) return;
  submitting.value = true;
  try {
    const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const r = await fetch(`/polls/${local.value.id}/vote`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
      credentials: 'same-origin',
      body: JSON.stringify({ options: selected.value }),
    });
    if (r.ok) { const d = await r.json(); local.value = d.poll; selected.value = [...d.poll.myVotes]; showResults.value = true; }
  } catch { /* ignore */ } finally { submitting.value = false; }
}
</script>

<template>
  <div class="rounded-2xl border border-line bg-surface p-5">
    <div class="mb-1 flex items-center gap-2">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="text-primary"><path d="M3 3v18h18M8 17V9M13 17V5M18 17v-6" /></svg>
      <h3 class="text-base font-bold text-ink">{{ local.question }}</h3>
    </div>
    <p class="mb-4 text-xs text-ink-muted">
      <span>{{ local.multiple ? tr('Select up to {n}', { n: local.maxChoices }) : tr('Choose one') }}</span>
      · <span>{{ local.totalVotes === 1 ? tr('1 vote') : tr('{n} votes', { n: local.totalVotes }) }}</span>
      <template v-if="local.closed"> · <span class="font-semibold text-rose-500">{{ tr('Closed') }}</span></template>
      <template v-else-if="local.closesAtLabel"> · <span>{{ tr('closes {when}', { when: local.closesAtLabel }) }}</span></template>
    </p>

    <!-- Voting -->
    <div v-if="!showResults" class="space-y-2">
      <button v-for="o in local.options" :key="o.id" type="button"
        class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left text-sm font-medium transition"
        :class="selected.includes(o.id) ? 'border-primary bg-primary/10 text-ink' : 'border-line text-ink-2 hover:border-primary/50 hover:bg-surface-2'"
        @click="toggle(o.id)">
        <span class="grid h-5 w-5 shrink-0 place-items-center border-2 transition"
          :class="[local.multiple ? 'rounded-md' : 'rounded-full', selected.includes(o.id) ? 'border-primary bg-primary text-white' : 'border-line']">
          <svg v-if="selected.includes(o.id)" width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z" /></svg>
        </span>
        <span class="min-w-0">{{ o.text }}</span>
      </button>
      <div class="flex flex-wrap items-center gap-3 pt-1">
        <button :disabled="!selected.length || submitting || !canVote" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/20 hover:bg-primary-600 disabled:opacity-50" @click="submit">
          {{ submitting ? tr('Voting…') : tr('Vote') }}
        </button>
        <button type="button" class="text-sm font-semibold text-ink-2 hover:text-ink" @click="showResults = true">{{ tr('View results') }}</button>
        <span v-if="!loggedIn" class="text-xs text-ink-muted">{{ tr('Sign in to vote.') }}</span>
      </div>
    </div>

    <!-- Results -->
    <div v-else class="space-y-2.5">
      <div v-for="o in local.options" :key="o.id" class="relative overflow-hidden rounded-xl border"
        :class="local.myVotes.includes(o.id) ? 'border-primary/50' : 'border-line'">
        <div class="absolute inset-y-0 left-0 transition-all duration-700 ease-out"
          :class="local.myVotes.includes(o.id) ? 'bg-gradient-to-r from-primary/45 to-primary/20' : 'bg-gradient-to-r from-primary/20 to-primary/[0.06]'"
          :style="{ width: o.percent + '%' }"></div>
        <div class="relative flex items-center justify-between gap-3 px-4 py-2.5">
          <span class="flex min-w-0 items-center gap-2 text-sm font-semibold text-ink">
            <svg v-if="local.myVotes.includes(o.id)" width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0 text-primary"><path d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z" /></svg>
            <span class="truncate">{{ o.text }}</span>
          </span>
          <span class="shrink-0 text-sm font-bold text-ink-2"><span class="text-ink">{{ o.percent }}%</span> <span class="text-ink-muted">· {{ o.votes }}</span></span>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-3 pt-1 text-xs text-ink-muted">
        <span>{{ local.voterCount === 1 ? tr('1 voter') : tr('{n} voters', { n: local.voterCount }) }}</span>
        <button v-if="local.hasVoted && !local.closed && loggedIn" type="button" class="font-semibold text-primary hover:text-primary-600" @click="showResults = false">{{ tr('Change vote') }}</button>
      </div>
    </div>
  </div>
</template>
