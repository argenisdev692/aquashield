<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/volt/Button.vue';
import Inputtext from '@/volt/Inputtext.vue';
import Message from '@/volt/Message.vue';
import Tabs from '@/volt/Tabs.vue';

const props = defineProps<{ status?: string }>();

const form = useForm({ code: '', recovery_code: '' });

const activeTab = ref('code');

const submit = () => {
    form.post('/two-factor-challenge');
};
</script>

<template>
    <Head title="Two-factor authentication" />

    <AuthLayout title="Two-factor authentication" subtitle="Confirm your identity">
        <Message v-if="props.status" severity="info" class="mb-4">{{ props.status }}</Message>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <Message severity="warn">
                After several failed attempts your account will be temporarily locked.
            </Message>

            <Tabs v-model:value="activeTab">
                <TabList>
                    <Tab value="code">Authenticator code</Tab>
                    <Tab value="recovery">Recovery code</Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="code">
                        <div class="flex flex-col gap-1 pt-2">
                            <label for="code" class="text-sm font-medium">Code</label>
                            <Inputtext
                                id="code"
                                v-model="form.code"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                autofocus
                            />
                            <span v-if="form.errors.code" class="text-sm text-aq-danger">{{
                                form.errors.code
                            }}</span>
                        </div>
                    </TabPanel>

                    <TabPanel value="recovery">
                        <div class="flex flex-col gap-1 pt-2">
                            <label for="recovery_code" class="text-sm font-medium"
                                >Recovery code</label
                            >
                            <Inputtext
                                id="recovery_code"
                                v-model="form.recovery_code"
                                type="text"
                                autocomplete="one-time-code"
                            />
                            <span v-if="form.errors.recovery_code" class="text-sm text-aq-danger">
                                {{ form.errors.recovery_code }}
                            </span>
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>

            <Button type="submit" :loading="form.processing" class="w-full">
                <span>Continue</span>
            </Button>
        </form>

        <template #footer>
            <Link href="/login" class="underline">Back to sign in</Link>
        </template>
    </AuthLayout>
</template>
