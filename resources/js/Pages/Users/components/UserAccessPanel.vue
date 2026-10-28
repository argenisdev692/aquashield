<script setup lang="ts">
import { computed, useTemplateRef, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { AppButton, FormErrorSummary } from '@/Components/Forms';
import type { SelectOption } from '@/Components/Forms';
import { useZodForm } from '@/lib/forms';
import { useUserListInvalidation } from '@/Modules/Users/composables/useUsers';
import { userPermissionsSchema, userRolesSchema } from '@/Modules/Users/schemas/userAccess';
import MultiSelectField from './MultiSelectField.vue';
import Tag from '@/volt/Tag.vue';

/**
 * Role and direct-permission editing for one user.
 *
 * Two forms, two endpoints, two audit events — mirroring the server's deliberate
 * split. A role grant and a direct grant have different permissions and
 * different failure semantics, so a single "save access" form would hide which
 * one failed.
 */

const props = defineProps<{
    user: Modules.Users.Data.ManagedUserData;
    catalog: Modules.Users.Data.UserAccessCatalogData;
    canAssignRoles: boolean;
    canAssignPermissions: boolean;
}>();

const toast = useToast();
const invalidateLists = useUserListInvalidation();

const rolesEl = useTemplateRef<HTMLFormElement>('rolesForm');
const permissionsEl = useTemplateRef<HTMLFormElement>('permissionsForm');

const {
    form: rolesForm,
    errors: roleErrors,
    submit: submitRoles,
    clearError: clearRoleError,
} = useZodForm({
    initial: { roles: [...props.user.roles] },
    schema: userRolesSchema,
    formEl: rolesEl,
});

const {
    form: permissionsForm,
    errors: permissionErrors,
    submit: submitPermissions,
    clearError: clearPermissionError,
} = useZodForm({
    initial: { permissions: [...props.user.direct_permissions] },
    schema: userPermissionsSchema,
    formEl: permissionsEl,
});

/**
 * The redirect after a successful assignment replaces the page props; keep the
 * local form aligned with the server's ordering without touching in-progress
 * edits (the watcher only fires when the server value actually changes).
 */
watch(
    () => props.user.roles,
    (roles) => {
        rolesForm.roles = [...roles];
    },
    { deep: true },
);

watch(
    () => props.user.direct_permissions,
    (permissions) => {
        permissionsForm.permissions = [...permissions];
    },
    { deep: true },
);

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

/**
 * What the account can actually do, live as the assigner edits. The server
 * still computes the authoritative set; this is a preview, not a control.
 */
const effectivePermissions = computed<number>(() => {
    const permissions = new Set<string>(props.user.direct_permissions);

    for (const roleName of rolesForm.roles) {
        const role = props.catalog.roles.find((option) => option.name === roleName);

        for (const permission of role?.permissions ?? []) {
            permissions.add(permission);
        }
    }

    return permissions.size;
});

const saveRoles = (): void => {
    submitRoles(() =>
        rolesForm.put(`/users/${props.user.uuid}/roles`, {
            preserveScroll: true,
            onSuccess: () => {
                void invalidateLists();
                toast.add({
                    severity: 'success',
                    summary: 'Roles updated',
                    detail: 'The new role set is already in effect.',
                    life: 4000,
                });
            },
        }),
    );
};

const savePermissions = (): void => {
    submitPermissions(() =>
        permissionsForm.put(`/users/${props.user.uuid}/permissions`, {
            preserveScroll: true,
            onSuccess: () => {
                void invalidateLists();
                toast.add({
                    severity: 'success',
                    summary: 'Permissions updated',
                    detail: 'Direct permissions were saved on top of the role set.',
                    life: 4000,
                });
            },
        }),
    );
};
</script>

<template>
    <section class="space-y-5" aria-labelledby="access-heading">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2
                    id="access-heading"
                    class="text-base font-semibold text-surface-900 dark:text-surface-0"
                >
                    Access
                </h2>
                <p class="mt-0.5 text-sm text-surface-600 dark:text-surface-300">
                    A role grants every permission it contains. Direct permissions are added on top.
                </p>
            </div>

            <p class="text-sm text-surface-600 dark:text-surface-300" aria-live="polite">
                <span class="font-semibold text-surface-900 tabular-nums dark:text-surface-0">
                    {{ effectivePermissions }}
                </span>
                effective permissions
            </p>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-0">Roles</h3>

                <form
                    v-if="canAssignRoles"
                    ref="rolesForm"
                    novalidate
                    class="mt-3 space-y-3"
                    @submit.prevent="saveRoles"
                >
                    <FormErrorSummary :errors="roleErrors" :form-el="rolesEl" />

                    <MultiSelectField
                        v-model="rolesForm.roles"
                        name="roles"
                        label="Assigned roles"
                        :options="roleOptions"
                        hint="The number after each role is the permissions it grants. You can only grant access you hold."
                        :error="roleErrors.roles"
                        @update:model-value="clearRoleError('roles')"
                    />

                    <AppButton type="submit" :loading="rolesForm.processing">
                        Save roles
                    </AppButton>
                </form>

                <div v-else class="mt-3 flex flex-wrap gap-2">
                    <Tag v-for="role in props.user.roles" :key="role" severity="secondary">
                        {{ role }}
                    </Tag>
                    <p v-if="props.user.roles.length === 0" class="text-sm text-surface-500">
                        No roles assigned.
                    </p>
                </div>
            </div>

            <div
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                    Direct permissions
                </h3>

                <form
                    v-if="canAssignPermissions"
                    ref="permissionsForm"
                    novalidate
                    class="mt-3 space-y-3"
                    @submit.prevent="savePermissions"
                >
                    <FormErrorSummary :errors="permissionErrors" :form-el="permissionsEl" />

                    <MultiSelectField
                        v-model="permissionsForm.permissions"
                        name="permissions"
                        label="Direct permissions"
                        :options="permissionOptions"
                        hint="These sit on top of the role set and are audited separately."
                        :error="permissionErrors.permissions"
                        @update:model-value="clearPermissionError('permissions')"
                    />

                    <AppButton type="submit" :loading="permissionsForm.processing">
                        Save permissions
                    </AppButton>
                </form>

                <div v-else class="mt-3 flex flex-wrap gap-2">
                    <Tag
                        v-for="permission in props.user.direct_permissions"
                        :key="permission"
                        severity="info"
                    >
                        {{ permission }}
                    </Tag>
                    <p
                        v-if="props.user.direct_permissions.length === 0"
                        class="text-sm text-surface-500"
                    >
                        No direct permissions.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
