<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { t } from '@/lib/i18n';

const page = usePage();
const u = (page.props as any).auth?.user ?? {};
const current = u.digest_frequency ?? 'weekly';

const form = useForm({
  digest_frequency: current,
  notify_email: u.notify_email ?? true,
});

const options = [
  { value: 'off', label: t('Off'), hint: t('No digest emails.') },
  { value: 'daily', label: t('Daily'), hint: t('A summary every morning.') },
  { value: 'weekly', label: t('Weekly'), hint: t('A summary once a week.') },
];

function submit() {
  form.post(route('notifications.preferences'), { preserveScroll: true });
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ t('Email digest') }}</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ t('Choose how often we email you a summary of new topics and unread notifications.') }}
      </p>
    </header>

    <form class="mt-6 space-y-6" @submit.prevent="submit">
      <div>
        <InputLabel :value="t('Frequency')" />
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

      <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
        <input type="checkbox" class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500" v-model="form.notify_email" />
        <span>
          <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ t('Instant email notifications') }}</span>
          <span class="block text-sm text-gray-500 dark:text-gray-400">{{ t('Email me when someone replies to me, mentions me, posts on my profile, or sends a message.') }}</span>
        </span>
      </label>

      <div class="flex items-center gap-4">
        <PrimaryButton :disabled="form.processing">{{ t('Save') }}</PrimaryButton>
        <Transition
          enter-active-class="transition ease-in-out"
          enter-from-class="opacity-0"
          leave-active-class="transition ease-in-out"
          leave-to-class="opacity-0"
        >
          <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-400">{{ t('Saved.') }}</p>
        </Transition>
      </div>
    </form>
  </section>
</template>
