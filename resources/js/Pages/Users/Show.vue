<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { TableButton } from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout.vue';
import { UserRequestError, useUserMutations } from '@/Modules/Users/composables/useUsers';
import Avatar from '@/volt/Avatar.vue';
import Tag from '@/volt/Tag.vue';
import UserAccessPanel from './components/UserAccessPanel.vue';
import UserStatusBadge from './components/UserStatusBadge.vue';
import { formatCalendarDate, formatDateTime, initials } from './components/userDisplay';

defineOptions({
    layout: [AppLayout, { title: 'User details' }],
});

const props = defineProps<{
    user: Modules.Users.Data.ManagedUserData;
    catalog: Modules.Users.Data.UserAccessCatalogData;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
    canAssignRoles: boolean;
    canAssignPermissions: boolean;
}>();

const toast = useToast();
const confirm = useConfirm();
const { deleteUser, restoreUser, busy } = useUserMutations();
const sendingReset = ref(false);

const isDeleted = computed<boolean>(() => props.user.deleted_at !== null);

const addressLine = computed<string>(
    () =>
        [props.user.address, props.user.address_2, props.user.zip_code]
            .filter((part): part is string => part !== null && part !== '')
            .join(', ') || '—',
);

const display = (value: string | null): string => (value === null || value === '' ? '—' : value);

const notifyError = (caught: unknown, fallback: string): void => {
    toast.add({
        severity: 'error',
        summary: 'Action failed',
        detail: caught instanceof UserRequestError ? caught.message : fallback,
        life: 5000,
    });
};

const removeUser = async (): Promise<void> => {
    try {
        await deleteUser(props.user.uuid);
        toast.add({
            severity: 'success',
            summary: 'User deleted',
            detail: `${props.user.name} can be restored from the deleted view.`,
            life: 4000,
        });
        router.visit('/users');
    } catch (caught: unknown) {
        notifyError(caught, 'The user could not be deleted.');
    }
};

const restoreUserRow = async (): Promise<void> => {
    try {
        await restoreUser(props.user.uuid);
        toast.add({
            severity: 'success',
            summary: 'User restored',
            detail: `${props.user.name} can sign in again.`,
            life: 4000,
        });
        router.reload();
    } catch (caught: unknown) {
        notifyError(caught, 'The user could not be restored.');
    }
};

const confirmDelete = (): void => {
    confirm.require({
        header: 'Delete user',
        message: `Delete ${props.user.name} <${props.user.email}>? Their sessions end immediately, and you can restore the account from the deleted view.`,
        acceptLabel: 'Delete',
        rejectLabel: 'Cancel',
        accept: () => {
            void removeUser();
        },
    });
};

const confirmRestore = (): void => {
    confirm.require({
        header: 'Restore user',
        message: `Restore ${props.user.name} <${props.user.email}>? They will be able to sign in again with their existing access.`,
        acceptLabel: 'Restore',
        rejectLabel: 'Cancel',
        accept: () => {
            void restoreUserRow();
        },
    });
};

const sendResetLink = (): void => {
    router.post(
        `/users/${props.user.uuid}/password-reset`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                sendingReset.value = true;
            },
            onFinish: () => {
                sendingReset.value = false;
            },
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Password-set link queued',
                    detail: `The single-use link is on its way to ${props.user.email}. It expires in 60 minutes.`,
                    life: 5000,
                });
            },
        },
    );
};

const confirmSendReset = (): void => {
    confirm.require({
        header: 'Send password-set link',
        message: `Send a single-use link to ${props.user.email}? It expires in 60 minutes. You will not see or set the password; the user chooses it.`,
        acceptLabel: 'Send link',
        rejectLabel: 'Cancel',
        accept: sendResetLink,
    });
};

/* ---------------------------------------------------------------- copy id */

const copied = ref(false);
let copyTimer: number | null = null;

const copyUuid = async (): Promise<void> => {
    if (navigator.clipboard === undefined) {
        toast.add({
            severity: 'info',
            summary: 'Copy is unavailable',
            detail: 'Select the user ID and copy it manually.',
            life: 4000,
        });

        return;
    }

    await navigator.clipboard.writeText(props.user.uuid);
    copied.value = true;

    if (copyTimer !== null) {
        window.clearTimeout(copyTimer);
    }

    copyTimer = window.setTimeout(() => {
        copied.value = false;
    }, 2000);
};

onUnmounted(() => {
    if (copyTimer !== null) {
        window.clearTimeout(copyTimer);
    }
});
</script>

<template>
    <Head :title="props.user.name" />

    <nav aria-label="Breadcrumb" class="mb-4">
        <ol
            class="flex flex-wrap items-center gap-1.5 text-sm text-surface-500 dark:text-surface-400"
        >
            <li>
                <Link
                    href="/users"
                    class="rounded-sm underline-offset-2 transition-colors duration-150 hover:text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
                >
                    Users
                </Link>
            </li>
            <li aria-hidden="true">
                <i class="pi pi-angle-right text-[10px]" />
            </li>
            <li
                class="truncate font-medium text-surface-800 dark:text-surface-100"
                aria-current="page"
            >
                {{ props.user.name }}
            </li>
        </ol>
    </nav>

    <div
        v-if="isDeleted"
        role="status"
        class="mb-4 flex flex-col gap-3 rounded-xl border border-aq-danger/40 bg-aq-danger/5 px-4 py-3 sm:flex-row sm:items-center"
    >
        <i class="pi pi-exclamation-triangle text-aq-danger-strong" aria-hidden="true" />
        <p class="min-w-0 flex-1 text-sm text-aq-danger-strong">
            This account is deleted. Sign-in is blocked until it is restored.
        </p>
        <TableButton
            v-if="props.canRestore"
            variant="primary"
            :disabled="busy"
            @click="confirmRestore"
        >
            Restore account
        </TableButton>
    </div>

    <section
        class="relative overflow-hidden rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
        aria-labelledby="user-heading"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <Avatar
                :label="initials(props.user.name)"
                size="xlarge"
                shape="circle"
                class="shrink-0 bg-aq-accent/15 text-lg font-semibold text-aq-accent dark:bg-aq-accent/20"
            />

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2
                        id="user-heading"
                        class="truncate text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0"
                    >
                        {{ props.user.name }}
                    </h2>
                    <UserStatusBadge
                        :deleted-at="props.user.deleted_at"
                        :email-verified="props.user.email_verified"
                    />
                </div>

                <p class="mt-1 truncate text-sm text-surface-600 dark:text-surface-300">
                    {{ props.user.email }}
                    <template v-if="props.user.username !== null">
                        · @{{ props.user.username }}
                    </template>
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                    <Tag v-for="role in props.user.roles" :key="role" severity="secondary">
                        {{ role }}
                    </Tag>
                    <Tag
                        v-if="props.user.direct_permissions.length > 0"
                        severity="info"
                        :aria-label="`${props.user.direct_permissions.length} direct permissions`"
                    >
                        +{{ props.user.direct_permissions.length }} direct
                    </Tag>
                    <span
                        v-if="
                            props.user.roles.length === 0 &&
                            props.user.direct_permissions.length === 0
                        "
                        class="text-sm text-surface-500"
                    >
                        No access assigned
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                <Link
                    v-if="props.canUpdate && !isDeleted"
                    :href="`/users/${props.user.uuid}/edit`"
                    class="inline-flex h-9 items-center gap-2 rounded-md border border-primary bg-primary px-3 text-sm font-medium text-primary-contrast transition-colors duration-150 hover:border-primary-emphasis hover:bg-primary-emphasis focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
                >
                    <i class="pi pi-pencil text-xs" aria-hidden="true" />
                    Edit profile
                </Link>

                <TableButton
                    v-if="props.canUpdate && !isDeleted"
                    variant="neutral"
                    :disabled="sendingReset || busy"
                    @click="confirmSendReset"
                >
                    <i class="pi pi-envelope text-xs" aria-hidden="true" />
                    Send password-set link
                </TableButton>

                <TableButton
                    v-if="props.canDelete && !isDeleted"
                    variant="danger"
                    :disabled="busy"
                    @click="confirmDelete"
                >
                    Delete
                </TableButton>
            </div>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
                aria-labelledby="profile-heading"
            >
                <h2
                    id="profile-heading"
                    class="text-base font-semibold text-surface-900 dark:text-surface-0"
                >
                    Profile
                </h2>

                <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            First name
                        </dt>
                        <dd class="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                            {{ display(props.user.first_name) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Last name
                        </dt>
                        <dd class="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                            {{ display(props.user.last_name) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Email
                        </dt>
                        <dd class="mt-0.5 truncate text-sm text-surface-900 dark:text-surface-0">
                            {{ display(props.user.email) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Phone
                        </dt>
                        <dd
                            class="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0"
                        >
                            {{ display(props.user.phone) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Date of birth
                        </dt>
                        <dd
                            class="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0"
                        >
                            {{ formatCalendarDate(props.user.date_of_birth) }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Gender
                        </dt>
                        <dd class="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                            {{ display(props.user.gender) }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Address
                        </dt>
                        <dd class="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                            {{ addressLine }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Location
                        </dt>
                        <dd class="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                            {{
                                [props.user.city, props.user.state, props.user.country]
                                    .filter((part): part is string => part !== null && part !== '')
                                    .join(', ') || '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </section>

            <UserAccessPanel
                :user="props.user"
                :catalog="props.catalog"
                :can-assign-roles="props.canAssignRoles && !isDeleted"
                :can-assign-permissions="props.canAssignPermissions && !isDeleted"
            />
        </div>

        <aside class="space-y-4">
            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">Account</h2>

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
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-surface-500 dark:text-surface-400">Email verified</dt>
                        <dd class="text-surface-800 dark:text-surface-100">
                            {{ props.user.email_verified ? 'Yes' : 'No' }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 border-t border-aq-shell-line pt-3">
                    <p
                        class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                    >
                        User ID
                    </p>
                    <div class="mt-1 flex items-center gap-2">
                        <code
                            class="min-w-0 flex-1 truncate rounded bg-surface-100 px-2 py-1 font-mono text-[11px] text-surface-700 dark:bg-surface-800 dark:text-surface-200"
                        >
                            {{ props.user.uuid }}
                        </code>
                        <TableButton
                            variant="ghost"
                            size="sm"
                            icon-only
                            aria-label="Copy user ID"
                            @click="copyUuid"
                        >
                            <i class="pi pi-copy text-xs" aria-hidden="true" />
                        </TableButton>
                    </div>
                    <p
                        class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                        aria-live="polite"
                    >
                        {{ copied ? 'Copied to clipboard.' : 'Used in support and audit lookups.' }}
                    </p>
                </div>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                    Access summary
                </h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-surface-500 dark:text-surface-400">Roles</dt>
                        <dd class="text-surface-800 tabular-nums dark:text-surface-100">
                            {{ props.user.roles.length }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-surface-500 dark:text-surface-400">Direct permissions</dt>
                        <dd class="text-surface-800 tabular-nums dark:text-surface-100">
                            {{ props.user.direct_permissions.length }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-surface-500 dark:text-surface-400">
                    Effective access is the union of the role set and the direct grants. Every
                    change is audited separately.
                </p>
            </section>
        </aside>
    </div>
</template>
