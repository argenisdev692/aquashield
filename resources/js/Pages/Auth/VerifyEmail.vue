<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/volt/Button.vue';
import Message from '@/volt/Message.vue';

const props = defineProps<{ status?: string }>();

const form = useForm({});

const isVerified = computed(() => props.status === 'verified');

const submit = () => form.post('/email/verification');
</script>

<template>
    <Head title="Verify your email" />

    <AuthLayout title="Verify your email" subtitle="Confirm your address to continue">
        <Message v-if="isVerified" severity="success" class="mb-4">
            Your email has been verified successfully.
        </Message>

        <Message
            v-else-if="props.status === 'verification-link-sent'"
            severity="success"
            class="mb-4"
        >
            A new verification link has been sent to your email.
        </Message>

        <p v-else class="text-sm text-surface-600 dark:text-surface-300">
            Didn't receive the email? Click "Resend verification" to send a new one.
        </p>

        <form v-if="!isVerified" class="mt-4" @submit.prevent="submit">
            <Button type="submit" :loading="form.processing" class="w-full">
                <span>Resend verification</span>
            </Button>
        </form>

        <template #footer>
            <Link href="/logout" method="post" as="button" class="underline">Log out</Link>
        </template>
    </AuthLayout>
</template>
