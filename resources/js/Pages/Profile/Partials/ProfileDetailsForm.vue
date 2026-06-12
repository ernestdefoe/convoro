<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import UploadButton from '@/Components/UploadButton.vue';
import { uploadImage } from '@/lib/upload';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from '@/lib/toast';
import { t } from '@/lib/i18n';

const page = usePage();
const user = (page.props as any).auth?.user ?? {};

const form = useForm({
  bio: user.bio ?? '',
  avatar: user.avatar_path ?? '',
  cover: user.cover_path ?? '',
});

const uploading = ref<'avatar' | 'cover' | null>(null);

async function pick(kind: 'avatar' | 'cover', file: File) {
  uploading.value = kind;
  try {
    const { url } = await uploadImage(file);
    form[kind] = url;
  } catch {
    /* ignore */
  } finally {
    uploading.value = null;
  }
}

function submit() {
  form.post(route('profile.details'), { preserveScroll: true, onSuccess: () => toast(t('Profile saved')) });
}

// Autosave (debounced) on bio / avatar / cover changes.
let timer: ReturnType<typeof setTimeout> | null = null;
watch(
  () => [form.bio, form.avatar, form.cover],
  () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(submit, 800);
  },
);
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ t('Profile') }}</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ t('Your cover image, avatar and bio shown on your public profile.') }}</p>
    </header>

    <form class="mt-6 space-y-6" @submit.prevent="submit">
      <!-- Cover -->
      <div>
        <InputLabel :value="t('Cover image')" />
        <div class="mt-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="h-28 w-full bg-gradient-to-r from-indigo-500 to-violet-500">
            <img v-if="form.cover" :src="form.cover" alt="" class="h-28 w-full object-cover" />
          </div>
        </div>
        <UploadButton class="mt-2" :uploading="uploading === 'cover'" :label="t('Choose cover')" accept="image/*" @file="(f) => pick('cover', f)" />
      </div>

      <!-- Avatar -->
      <div>
        <InputLabel :value="t('Avatar')" />
        <div class="mt-2 flex items-center gap-4">
          <img v-if="form.avatar" :src="form.avatar" alt="" class="h-16 w-16 rounded-full object-cover" />
          <span v-else class="grid h-16 w-16 place-items-center rounded-full bg-indigo-500 font-bold text-white">{{ (user.name ?? '?')[0] }}</span>
          <UploadButton :uploading="uploading === 'avatar'" :label="t('Choose avatar')" accept="image/*" @file="(f) => pick('avatar', f)" />
        </div>
      </div>

      <!-- Bio -->
      <div>
        <InputLabel for="bio" :value="t('Bio')" />
        <textarea id="bio" v-model="form.bio" rows="3" maxlength="500"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
          :placeholder="t('Tell the community about yourself…')"></textarea>
        <p class="mt-1 text-xs text-gray-500">{{ form.bio.length }}/500</p>
      </div>

      <p class="text-sm text-gray-500 dark:text-gray-400">
        <span v-if="form.processing || uploading">{{ t('Saving…') }}</span>
        <span v-else>{{ t('Changes save automatically.') }}</span>
      </p>
    </form>
  </section>
</template>
