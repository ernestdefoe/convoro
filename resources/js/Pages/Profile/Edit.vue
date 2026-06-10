<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import DigestPreferenceForm from './Partials/DigestPreferenceForm.vue';
import ProfileDetailsForm from './Partials/ProfileDetailsForm.vue';
import PushToggle from './Partials/PushToggle.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const card = 'rounded-c border border-line bg-surface p-6 sm:p-8';
</script>

<template>
    <Head title="Settings" />

    <AppLayout>
        <div class="mx-auto max-w-2xl">
            <h1 class="mb-6 text-2xl font-extrabold tracking-tight text-ink">Settings</h1>

            <div class="space-y-5">
                <div :class="card">
                    <ProfileDetailsForm />
                </div>
                <div :class="card">
                    <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
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
                <div :class="card">
                    <DeleteUserForm />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
