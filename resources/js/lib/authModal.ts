import { ref } from 'vue';

type Mode = 'login' | 'register';

const state = ref<{ open: boolean; mode: Mode }>({ open: false, mode: 'login' });

export function useAuthModal() {
  return {
    state,
    open: (mode: Mode = 'login') => { state.value = { open: true, mode }; },
    close: () => { state.value.open = false; },
    toggleMode: () => { state.value.mode = state.value.mode === 'login' ? 'register' : 'login'; },
  };
}
