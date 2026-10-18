<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/volt/Button.vue';
import Inputtext from '@/volt/Inputtext.vue';
import Password from '@/volt/Password.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Create account" />

    <AuthLayout title="Create account" subtitle="Sign up to get started">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <label for="name" class="text-sm font-medium">Name</label>
                <Inputtext
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    required
                    autofocus
                />
                <span v-if="form.errors.name" class="text-sm text-aq-danger">{{
                    form.errors.name
                }}</span>
            </div>

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
                <label for="password" class="text-sm font-medium">Password</label>
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
                <span>Create account</span>
            </Button>
        </form>

        <template #footer>
            Already have an account?
            <Link href="/login" class="underline">Sign in</Link>
        </template>
    </AuthLayout>
</template>
