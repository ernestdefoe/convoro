<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import AccessTokensForm from './Partials/AccessTokensForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import DigestPreferenceForm from './Partials/DigestPreferenceForm.vue';
import NotificationPreferencesForm from './Partials/NotificationPreferencesForm.vue';
import ProfileDetailsForm from './Partials/ProfileDetailsForm.vue';
import PushToggle from './Partials/PushToggle.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import Slot from '@/Components/ext/Slot.vue';
import { Head } from '@inertiajs/vue3';
import { t } from '@/lib/i18n';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const card = 'rounded-c border border-line bg-surface p-6 sm:p-8';
</script>

<template>
    <Head :title="t('Settings')" />

    <AppLayout>
        <div class="mx-auto max-w-2xl">
            <h1 class="mb-6 text-2xl font-extrabold tracking-tight text-ink">{{ t('Settings') }}</h1>

            <div class="space-y-5">
                <div :class="card">
                    <ProfileDetailsForm />
                </div>
                <div :class="card">
                    <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
                </div>
                <div :class="card">
                    <NotificationPreferencesForm />
                </div>
                <div :class="card">
                    <DigestPreferenceForm />
                </div>
                <div :class="card">
                    <PushToggle />
                </div>
                <div :class="card">
                    <UpdatePasswordForm />
                </div>

                <!-- Connected accounts — provided by the Social Login extension. -->
                <Slot name="settings:account" />

                <div :class="card">
                    <AccessTokensForm />
                </div>
                <div :class="card">
                    <DeleteUserForm />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
