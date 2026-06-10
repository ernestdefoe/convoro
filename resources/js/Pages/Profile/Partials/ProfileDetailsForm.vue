<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { uploadImage } from '@/lib/upload';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const page = usePage();
const user = (page.props as any).auth?.user ?? {};

const form = useForm({
  bio: user.bio ?? '',
  avatar: user.avatar_path ?? '',
  cover: user.cover_path ?? '',
});

const uploading = ref<'avatar' | 'cover' | null>(null);

async function pick(kind: 'avatar' | 'cover', e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
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
  form.post(route('profile.details'), { preserveScroll: true });
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Profile</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Your cover image, avatar and bio shown on your public profile.</p>
    </header>

    <form class="mt-6 space-y-6" @submit.prevent="submit">
      <!-- Cover -->
      <div>
        <InputLabel value="Cover image" />
        <div class="mt-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="h-28 w-full bg-gradient-to-r from-indigo-500 to-violet-500">
            <img v-if="form.cover" :src="form.cover" alt="" class="h-28 w-full object-cover" />
          </div>
        </div>
        <input type="file" accept="image/*" class="mt-2 text-sm text-gray-600 dark:text-gray-400" @change="(e) => pick('cover', e)" />
        <span v-if="uploading === 'cover'" class="ml-2 text-sm text-gray-500">Uploading…</span>
      </div>

      <!-- Avatar -->
      <div>
        <InputLabel value="Avatar" />
        <div class="mt-2 flex items-center gap-4">
          <img v-if="form.avatar" :src="form.avatar" alt="" class="h-16 w-16 rounded-full object-cover" />
          <span v-else class="grid h-16 w-16 place-items-center rounded-full bg-indigo-500 font-bold text-white">{{ (user.name ?? '?')[0] }}</span>
          <input type="file" accept="image/*" class="text-sm text-gray-600 dark:text-gray-400" @change="(e) => pick('avatar', e)" />
          <span v-if="uploading === 'avatar'" class="text-sm text-gray-500">Uploading…</span>
        </div>
      </div>

      <!-- Bio -->
      <div>
        <InputLabel for="bio" value="Bio" />
        <textarea id="bio" v-model="form.bio" rows="3" maxlength="500"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
          placeholder="Tell the community about yourself…"></textarea>
        <p class="mt-1 text-xs text-gray-500">{{ form.bio.length }}/500</p>
      </div>

      <div class="flex items-center gap-4">
        <PrimaryButton :disabled="form.processing || !!uploading">Save</PrimaryButton>
        <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
          <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-400">Saved.</p>
        </Transition>
      </div>
    </form>
  </section>
</template>
