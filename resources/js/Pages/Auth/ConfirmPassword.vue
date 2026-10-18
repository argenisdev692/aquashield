<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/volt/Button.vue';
import Password from '@/volt/Password.vue';

const form = useForm({ password: '' });

const submit = () => {
    form.post('/user/confirm-password', {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Confirm password" />

    <AuthLayout title="Confirm password" subtitle="This is a secure area">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <p class="text-sm text-surface-600 dark:text-surface-300">
                Re-enter your password to confirm your identity before continuing.
            </p>

            <div class="flex flex-col gap-1">
                <label for="password" class="text-sm font-medium">Password</label>
                <Password
                    input-id="password"
                    v-model="form.password"
                    autocomplete="current-password"
                    toggle-mask
                    :feedback="false"
                    required
                    autofocus
                />
                <span v-if="form.errors.password" class="text-sm text-aq-danger">{{
                    form.errors.password
                }}</span>
            </div>

            <Button type="submit" :loading="form.processing" class="w-full">
                <span>Confirm</span>
            </Button>
        </form>
    </AuthLayout>
</template>
