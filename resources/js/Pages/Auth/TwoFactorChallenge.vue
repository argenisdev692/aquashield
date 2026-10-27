<script setup lang="ts">
import { ref, useTemplateRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { AppButton, FormErrorSummary, TextField } from '@/Components/Forms';
import Message from '@/volt/Message.vue';
import Tabs from '@/volt/Tabs.vue';
import { useZodForm } from '@/lib/forms';

const props = defineProps<{ status?: string }>();

const schema = z
    .object({
        code: z.string(),
        recovery_code: z.string(),
    })
    .refine((values) => values.code.trim() !== '' || values.recovery_code.trim() !== '', {
        path: ['code'],
        message: 'Enter an authenticator or recovery code.',
    });

const twoFactorForm = useTemplateRef<HTMLFormElement>('twoFactorForm');

const { form, formEl, errors, submit, touch, clearError } = useZodForm({
    initial: { code: '', recovery_code: '' },
    schema,
    formEl: twoFactorForm,
});

const activeTab = ref('code');

const onSubmit = (): void => {
    submit(() => form.post('/two-factor-challenge'));
};
</script>

<template>
    <Head title="Two-factor authentication" />

    <AuthLayout title="Two-factor authentication" subtitle="Confirm your identity">
        <Message v-if="props.status" severity="info" class="mb-4">{{ props.status }}</Message>

        <form ref="twoFactorForm" class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
            <Message severity="warn">
                After several failed attempts your account will be temporarily locked.
            </Message>

            <FormErrorSummary :errors="errors" :form-el="formEl" />

            <Tabs v-model:value="activeTab">
                <TabList>
                    <Tab value="code">Authenticator code</Tab>
                    <Tab value="recovery">Recovery code</Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="code">
                        <div class="pt-2">
                            <TextField
                                v-model="form.code"
                                name="code"
                                label="Code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                autofocus
                                :error="errors.code"
                                @update:model-value="clearError('code')"
                                @blur="touch('code')"
                            />
                        </div>
                    </TabPanel>

                    <TabPanel value="recovery">
                        <div class="pt-2">
                            <TextField
                                v-model="form.recovery_code"
                                name="recovery_code"
                                label="Recovery code"
                                autocomplete="one-time-code"
                                :error="errors.recovery_code"
                                @update:model-value="clearError('recovery_code')"
                                @blur="touch('recovery_code')"
                            />
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>

            <AppButton type="submit" :loading="form.processing" class="w-full">
                Continue
            </AppButton>
        </form>

        <template #footer>
            <Link href="/login" class="underline">Back to sign in</Link>
        </template>
    </AuthLayout>
</template>
