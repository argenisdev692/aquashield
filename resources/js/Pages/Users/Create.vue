<script setup lang="ts">
import { computed, useTemplateRef } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { FormActions, FormErrorSummary } from '@/Components/Forms';
import type { SelectOption } from '@/Components/Forms';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useZodForm } from '@/lib/forms';
import {
    createUserSchema,
    toUserProfileForm,
    type CreateUserForm,
} from '@/Modules/Users/schemas/userProfile';
import { useUserListInvalidation } from '@/Modules/Users/composables/useUsers';
import MultiSelectField from './components/MultiSelectField.vue';
import UserProfileFields from './components/UserProfileFields.vue';

defineOptions({
    layout: [AppLayout, { title: 'New user' }],
});

const props = defineProps<{
    catalog: Modules.Users.Data.UserAccessCatalogData;
    googleMapsApiKey: string;
}>();

const toast = useToast();
const formEl = useTemplateRef<HTMLFormElement>('createUserForm');
const invalidateLists = useUserListInvalidation();

const initial: CreateUserForm = {
    ...toUserProfileForm(),
    roles: [],
    permissions: [],
};

const { form, errors, submit, touch, clearError } = useZodForm({
    initial,
    schema: createUserSchema,
    formEl,
});

const roleOptions = computed<SelectOption[]>(() =>
    props.catalog.roles.map((role) => ({
        label: `${role.name} · ${role.permissions.length}`,
        value: role.name,
    })),
);

const permissionOptions = computed<SelectOption[]>(() =>
    props.catalog.permissions.map((permission) => ({
        label: permission,
        value: permission,
    })),
);

const onSubmit = (): void => {
    submit(() =>
        form.post('/users', {
            preserveScroll: true,
            onSuccess: () => {
                void invalidateLists();
                toast.add({
                    severity: 'success',
                    summary: 'User created',
                    detail: 'An invitation with a password-set link is on its way.',
                    life: 5000,
                });
            },
        }),
    );
};
</script>

<template>
    <Head title="New user" />

    <p class="mb-6 max-w-3xl text-sm text-surface-600 dark:text-surface-300">
        Create an account for a teammate, then grant the narrowest access they need. The account is
        verified by you and receives a password-set link by email — no temporary password changes
        hands.
    </p>

    <form
        ref="createUserForm"
        novalidate
        class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
        @submit.prevent="onSubmit"
    >
        <div class="space-y-6">
            <FormErrorSummary :errors="errors" :form-el="formEl" />

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
                aria-labelledby="profile-heading"
            >
                <h2
                    id="profile-heading"
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

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
                aria-labelledby="new-access-heading"
            >
                <div class="mb-5">
                    <h2
                        id="new-access-heading"
                        class="text-base font-semibold text-surface-900 dark:text-surface-0"
                    >
                        Initial access
                    </h2>
                    <p class="mt-1 text-sm text-surface-600 dark:text-surface-300">
                        Optional. You can also assign access after the account exists; the ceiling
                        rule is the same either way.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <MultiSelectField
                        v-model="form.roles"
                        name="roles"
                        label="Roles"
                        :options="roleOptions"
                        hint="The number after each role is the permissions it grants."
                        :error="errors.roles"
                        @update:model-value="clearError('roles')"
                    />

                    <MultiSelectField
                        v-model="form.permissions"
                        name="permissions"
                        label="Direct permissions"
                        :options="permissionOptions"
                        hint="Added on top of the roles. You can only grant what you hold."
                        :error="errors.permissions"
                        @update:model-value="clearError('permissions')"
                    />
                </div>
            </section>

            <FormActions
                submit-label="Create user"
                cancel-label="Cancel"
                :busy="form.processing"
                @cancel="router.visit('/users')"
            />
        </div>

        <aside class="space-y-4">
            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                    What happens next
                </h2>
                <ol class="mt-3 space-y-3 text-sm text-surface-600 dark:text-surface-300">
                    <li class="flex gap-2.5">
                        <span
                            class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-primary-100 text-[11px] font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
                            aria-hidden="true"
                        >
                            1
                        </span>
                        <span>The account is created and marked verified.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <span
                            class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-primary-100 text-[11px] font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
                            aria-hidden="true"
                        >
                            2
                        </span>
                        <span>A queued invitation carries a password-set link.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <span
                            class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-primary-100 text-[11px] font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
                            aria-hidden="true"
                        >
                            3
                        </span>
                        <span>The grant and the creation are written to the audit log.</span>
                    </li>
                </ol>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                    Access ceiling
                </h2>
                <p class="mt-1.5 text-sm text-surface-600 dark:text-surface-300">
                    You cannot grant a role or permission you do not hold, edit an account above
                    your own access, or remove the last super admin. The server re-checks all three
                    on submit.
                </p>
            </section>
        </aside>
    </form>
</template>
