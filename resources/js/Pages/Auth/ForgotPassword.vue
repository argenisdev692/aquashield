<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { AppButton, FormErrorSummary, TextField } from '@/Components/Forms';
import Message from '@/volt/Message.vue';
import { useZodForm } from '@/lib/forms';

defineProps<{ status?: string }>();

const schema = z.object({
    email: z.email('Enter a valid email address'),
});

const forgotForm = useTemplateRef<HTMLFormElement>('forgotForm');

const { form, formEl, errors, submit, touch, clearError } = useZodForm({
    initial: { email: '' },
    schema,
    formEl: forgotForm,
});

const onSubmit = (): void => {
    submit(() => form.post('/forgot-password'));
};
</script>

<template>
    <Head title="Reset password" />

    <AuthLayout title="Reset password" subtitle="We'll email you a reset link">
        <Message v-if="status" severity="success" class="mb-4">{{ status }}</Message>

        <form ref="forgotForm" class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
            <FormErrorSummary :errors="errors" :form-el="formEl" />

            <TextField
                v-model="form.email"
                name="email"
                label="Email"
                type="email"
                autocomplete="username"
                required
                autofocus
                :error="errors.email"
                @update:model-value="clearError('email')"
                @blur="touch('email')"
            />

            <AppButton type="submit" :loading="form.processing" class="w-full">
                Email password reset link
            </AppButton>
        </form>

        <template #footer>
            <Link href="/login" class="underline">Back to sign in</Link>
        </template>
    </AuthLayout>
</template>
