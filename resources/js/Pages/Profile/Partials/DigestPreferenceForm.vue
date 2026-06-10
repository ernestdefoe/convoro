<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const current = (page.props as any).auth?.user?.digest_frequency ?? 'weekly';

const form = useForm({ digest_frequency: current });

const options = [
  { value: 'off', label: 'Off', hint: 'No digest emails.' },
  { value: 'daily', label: 'Daily', hint: 'A summary every morning.' },
  { value: 'weekly', label: 'Weekly', hint: 'A summary once a week.' },
];

function submit() {
  form.post(route('notifications.preferences'), { preserveScroll: true });
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Email digest</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Choose how often we email you a summary of new topics and unread notifications.
      </p>
    </header>

    <form class="mt-6 space-y-6" @submit.prevent="submit">
      <div>
        <InputLabel value="Frequency" />
        <div class="mt-2 space-y-2">
          <label
            v-for="o in options"
            :key="o.value"
            class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700"
            :class="form.digest_frequency === o.value ? 'border-indigo-500 ring-1 ring-indigo-500' : ''"
          >
            <input
              type="radio"
              class="mt-0.5 text-indigo-600 focus:ring-indigo-500"
              :value="o.value"
              v-model="form.digest_frequency"
            />
            <span>
              <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ o.label }}</span>
              <span class="block text-sm text-gray-500 dark:text-gray-400">{{ o.hint }}</span>
            </span>
          </label>
        </div>
      </div>

      <div class="flex items-center gap-4">
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        <Transition
          enter-active-class="transition ease-in-out"
          enter-from-class="opacity-0"
          leave-active-class="transition ease-in-out"
          leave-to-class="opacity-0"
        >
          <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-400">Saved.</p>
        </Transition>
      </div>
    </form>
  </section>
</template>
