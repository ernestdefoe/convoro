<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from '@/lib/toast';
import { t } from '@/lib/i18n';

defineProps<{
    mustVerifyEmail?: Boolean;
    status?: String;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

// Autosave (debounced). Inline errors handle invalid input; we only toast success.
let timer: ReturnType<typeof setTimeout> | null = null;
watch(
    () => [form.name, form.email],
    () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            form.patch(route('profile.update'), {
                preserveScroll: true,
                onSuccess: () => toast(t('Profile saved')),
            });
        }, 800);
    },
);
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ t('Profile Information') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ t("Update your account's profile information and email address.") }}
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" :value="t('Name')" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" :value="t('Email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                    {{ t('Your email address is unverified.') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                    >
                        {{ t('Click here to re-send the verification email.') }}
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600 dark:text-green-400"
                >
                    {{ t('A new verification link has been sent to your email address.') }}
                </div>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                <span v-if="form.processing">{{ t('Saving…') }}</span>
                <span v-else>{{ t('Changes save automatically.') }}</span>
            </p>
        </form>
    </section>
</template>
