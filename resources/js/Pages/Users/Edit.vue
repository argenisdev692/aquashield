<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { FormActions, FormErrorSummary } from '@/Components/Forms';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useZodForm } from '@/lib/forms';
import { useUserListInvalidation } from '@/Modules/Users/composables/useUsers';
import { toUserProfileForm, updateUserSchema } from '@/Modules/Users/schemas/userProfile';
import UserProfileFields from './components/UserProfileFields.vue';
import UserStatusBadge from './components/UserStatusBadge.vue';
import { formatDateTime } from './components/userDisplay';

defineOptions({
    layout: [AppLayout, { title: 'Edit user' }],
});

const props = defineProps<{
    user: Modules.Users.Data.ManagedUserData;
    googleMapsApiKey: string;
}>();

const toast = useToast();
const formEl = useTemplateRef<HTMLFormElement>('editUserForm');
const invalidateLists = useUserListInvalidation();

const { form, errors, submit, touch, clearError } = useZodForm({
    initial: toUserProfileForm(props.user),
    schema: updateUserSchema,
    formEl,
});

const onSubmit = (): void => {
    submit(() =>
        form.put(`/users/${props.user.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                void invalidateLists();
                toast.add({
                    severity: 'success',
                    summary: 'Profile updated',
                    detail: 'The changes are visible on the user page.',
                    life: 4000,
                });
            },
        }),
    );
};
</script>

<template>
    <Head :title="`Edit ${props.user.name}`" />

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-surface-600 dark:text-surface-300">
                Update the profile details for
                <span class="font-medium text-surface-900 dark:text-surface-0">
                    {{ props.user.name }}
                </span>
                . Roles and direct permissions are managed separately.
            </p>
        </div>

        <Link
            :href="`/users/${props.user.uuid}`"
            class="inline-flex h-9 items-center gap-2 rounded-md border border-content-border bg-surface-0 px-3 text-sm font-medium text-surface-700 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:bg-surface-800 dark:text-surface-100 dark:hover:bg-surface-700 motion-reduce:transition-none"
        >
            <i class="pi pi-arrow-left text-xs" aria-hidden="true" />
            Back to user
        </Link>
    </div>

    <form
        ref="editUserForm"
        novalidate
        class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
        @submit.prevent="onSubmit"
    >
        <div class="space-y-6">
            <FormErrorSummary :errors="errors" :form-el="formEl" />

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
                aria-labelledby="edit-profile-heading"
            >
                <h2
                    id="edit-profile-heading"
                    class="mb-5 text-base font-semibold text-surface-900 dark:text-surface-0"
                >
                    Profile
                </h2>

                <UserProfileFields
                    v-model:first-name="form.first_name"
                    v-model:last-name="form.last_name"
                    v-model:username="form.username"
                    v-model:email="form.email"
                    v-model:phone="form.phone"
                    v-model:date-of-birth="form.date_of_birth"
                    v-model:address="form.address"
                    v-model:address2="form.address_2"
                    v-model:zip-code="form.zip_code"
                    v-model:city="form.city"
                    v-model:state="form.state"
                    v-model:country="form.country"
                    v-model:latitude="form.latitude"
                    v-model:longitude="form.longitude"
                    :api-key="props.googleMapsApiKey"
                    :errors="errors"
                    @touch="touch"
                    @clear="clearError"
                />
            </section>

            <FormActions
                submit-label="Save changes"
                cancel-label="Cancel"
                :busy="form.processing"
                @cancel="router.visit(`/users/${props.user.uuid}`)"
            />
        </div>

        <aside class="space-y-4">
            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                        Account
                    </h2>
                    <UserStatusBadge
                        :deleted-at="props.user.deleted_at"
                        :email-verified="props.user.email_verified"
                    />
                </div>

                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-surface-500 dark:text-surface-400">Created</dt>
                        <dd class="text-end text-surface-800 tabular-nums dark:text-surface-100">
                            {{ formatDateTime(props.user.created_at) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-surface-500 dark:text-surface-400">Updated</dt>
                        <dd class="text-end text-surface-800 tabular-nums dark:text-surface-100">
                            {{ formatDateTime(props.user.updated_at) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">Access</h2>
                <p class="mt-1.5 text-sm text-surface-600 dark:text-surface-300">
                    Roles and direct permissions are changed on the user page, where each write is
                    authorised and audited separately.
                </p>
                <Link
                    :href="`/users/${props.user.uuid}`"
                    class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary underline-offset-2 transition-colors duration-150 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
                >
                    Open user page
                    <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
                </Link>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">Password</h2>
                <p class="mt-1.5 text-sm text-surface-600 dark:text-surface-300">
                    Administrators never set or see a password. If the user needs a new one, send a
                    single-use password-set link from their user page; the link expires and the user
                    chooses the password.
                </p>
            </section>
        </aside>
    </form>
</template>
