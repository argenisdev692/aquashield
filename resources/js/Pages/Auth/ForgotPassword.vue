<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/volt/Button.vue';
import Inputtext from '@/volt/Inputtext.vue';
import Message from '@/volt/Message.vue';

defineProps<{ status?: string }>();

const form = useForm({ email: '' });

const submit = () => form.post('/forgot-password');
</script>

<template>
    <Head title="Reset password" />

    <AuthLayout title="Reset password" subtitle="We'll email you a reset link">
        <Message v-if="status" severity="success" class="mb-4">{{ status }}</Message>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-medium">Email</label>
                <Inputtext
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus
                />
                <span v-if="form.errors.email" class="text-sm text-aq-danger">{{
                    form.errors.email
                }}</span>
            </div>

            <Button type="submit" :loading="form.processing" class="w-full">
                <span>Email password reset link</span>
            </Button>
        </form>

        <template #footer>
            <Link href="/login" class="underline">Back to sign in</Link>
        </template>
    </AuthLayout>
</template>
