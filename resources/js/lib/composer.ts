import { ref } from 'vue';

// Global state for the bottom-sheet "Start a topic" composer. Mirrors the
// authModal store: one <ComposerSheet> is mounted in AppLayout and driven from
// anywhere via useComposer().open().
const state = ref<{ open: boolean; draftId: number | null }>({ open: false, draftId: null });

export function useComposer() {
  return {
    state,
    open: (opts?: { draftId?: number | null }) => {
      state.value = { open: true, draftId: opts?.draftId ?? null };
    },
    close: () => {
      state.value.open = false;
    },
  };
}
