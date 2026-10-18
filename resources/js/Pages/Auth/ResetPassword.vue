<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/volt/Button.vue';
import Inputtext from '@/volt/Inputtext.vue';
import Password from '@/volt/Password.vue';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset password" />

    <AuthLayout title="Reset password" subtitle="Choose a new password">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-medium">Email</label>
                <Inputtext
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                />
                <span v-if="form.errors.email" class="text-sm text-aq-danger">{{
                    form.errors.email
                }}</span>
            </div>

            <div class="flex flex-col gap-1">
                <label for="password" class="text-sm font-medium">New password</label>
                <Password
                    input-id="password"
                    v-model="form.password"
                    autocomplete="new-password"
                    toggle-mask
                    :feedback="false"
                    required
                />
                <span v-if="form.errors.password" class="text-sm text-aq-danger">{{
                    form.errors.password
                }}</span>
            </div>

            <div class="flex flex-col gap-1">
                <label for="password_confirmation" class="text-sm font-medium"
                    >Confirm password</label
                >
                <Password
                    input-id="password_confirmation"
                    v-model="form.password_confirmation"
                    autocomplete="new-password"
                    toggle-mask
                    :feedback="false"
                    required
                />
            </div>

            <Button type="submit" :loading="form.processing" class="w-full">
                <span>Reset password</span>
            </Button>
        </form>
    </AuthLayout>
</template>
