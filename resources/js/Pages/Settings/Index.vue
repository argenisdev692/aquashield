<script setup lang="ts">
/**
 * Settings/Index — the settings hub.
 *
 * A small map of the account surface: identity, address, password and
 * two-factor. Each card deep-links into the matching tab on
 * `/settings/profile`, so the hub adds orientation without duplicating a form
 * or fetching anything.
 */
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PASSWORD_MAX_LENGTH, PASSWORD_MIN_LENGTH } from '@/lib/auth';
import Avatar from '@/volt/Avatar.vue';
import Tag from '@/volt/Tag.vue';

defineOptions({
    layout: [AppLayout, { title: 'Settings' }],
});

const props = defineProps<{
    user: App.Data.Auth.UserData;
}>();

interface TagStatus {
    label: string;
    severity: 'success' | 'warn' | 'secondary';
    icon: string;
}

interface SettingsDestination {
    href: string;
    title: string;
    description: string;
    icon: string;
    status: TagStatus;
}

const initials = computed<string>(() => {
    const parts = props.user.name.trim().split(/\s+/).filter(Boolean);

    return (
        parts
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'AS'
    );
});

const passwordHint = computed<string>(
    () => `${PASSWORD_MIN_LENGTH}–${PASSWORD_MAX_LENGTH} characters`,
);

const destinations = computed<SettingsDestination[]>(() => [
    {
        href: '/settings/profile#profile',
        title: 'Identity',
        description: 'Photo, name, username, email, phone and date of birth.',
        icon: 'pi pi-user',
        status: {
            label: props.user.email_verified ? 'Email verified' : 'Verify email',
            severity: props.user.email_verified ? 'success' : 'warn',
            icon: props.user.email_verified ? 'pi-check-circle' : 'pi-exclamation-triangle',
        },
    },
    {
        href: '/settings/profile#address',
        title: 'Address',
        description: 'US address with Google autocomplete and verified coordinates.',
        icon: 'pi pi-map-marker',
        status: {
            label: props.user.latitude !== null ? 'Verified' : 'Not set',
            severity: props.user.latitude !== null ? 'success' : 'secondary',
            icon: 'pi-map-marker',
        },
    },
    {
        href: '/settings/profile#password',
        title: 'Password',
        description: `Rotate the password and review the current policy (${passwordHint.value}).`,
        icon: 'pi pi-key',
        status: {
            label: passwordHint.value,
            severity: 'secondary',
            icon: 'pi-key',
        },
    },
    {
        href: '/settings/profile#security',
        title: 'Two-factor authentication',
        description: 'Authenticator app, QR setup and recovery codes.',
        icon: 'pi pi-shield',
        status: {
            label: props.user.two_factor_enabled ? 'On' : 'Off',
            severity: props.user.two_factor_enabled ? 'success' : 'secondary',
            icon: 'pi-shield',
        },
    },
]);
</script>

<template>
    <Head title="Settings — AquaShield" />

    <div class="mx-auto w-full max-w-5xl space-y-6">
        <section
            class="flex flex-col gap-4 rounded-2xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:bg-surface-900"
        >
            <div class="flex items-center gap-4">
                <Avatar
                    v-if="props.user.profile_photo_url"
                    :image="props.user.profile_photo_url"
                    shape="circle"
                    size="large"
                    class="border border-aq-shell-line"
                />
                <Avatar
                    v-else
                    :label="initials"
                    shape="circle"
                    size="large"
                    class="border border-aq-shell-line bg-aq-accent/15 font-semibold text-aq-accent"
                />

                <div class="min-w-0">
                    <p
                        class="text-xs font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                    >
                        Account
                    </p>
                    <h2 class="truncate text-lg font-semibold text-surface-900 dark:text-surface-0">
                        {{ props.user.name }}
                    </h2>
                    <p class="truncate text-sm text-surface-600 dark:text-surface-300">
                        {{ props.user.email }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                <Tag :severity="props.user.email_verified ? 'success' : 'warn'">
                    <i
                        class="pi mr-1 text-[0.65rem]"
                        :class="
                            props.user.email_verified
                                ? 'pi-check-circle'
                                : 'pi-exclamation-triangle'
                        "
                        aria-hidden="true"
                    />
                    {{ props.user.email_verified ? 'Verified' : 'Verify email' }}
                </Tag>

                <Tag :severity="props.user.two_factor_enabled ? 'success' : 'secondary'">
                    <i class="pi pi-shield mr-1 text-[0.65rem]" aria-hidden="true" />
                    {{ props.user.two_factor_enabled ? '2FA on' : '2FA off' }}
                </Tag>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2">
            <Link
                v-for="destination in destinations"
                :key="destination.href"
                :href="destination.href"
                class="group flex flex-col gap-4 rounded-2xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm transition-colors duration-150 hover:border-aq-accent/40 hover:bg-aq-accent/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none dark:bg-surface-900 dark:hover:bg-aq-accent/10"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-aq-accent/10 text-aq-accent"
                        aria-hidden="true"
                    >
                        <i class="pi text-sm" :class="destination.icon" />
                    </span>

                    <i
                        class="pi pi-chevron-right text-xs text-surface-400 transition-transform duration-150 group-hover:translate-x-0.5 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                </div>

                <div>
                    <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">
                        {{ destination.title }}
                    </h3>
                    <p class="mt-1 text-sm text-surface-600 dark:text-surface-300">
                        {{ destination.description }}
                    </p>
                </div>

                <div class="mt-auto">
                    <Tag :severity="destination.status.severity">
                        <i
                            class="pi mr-1 text-[0.65rem]"
                            :class="destination.status.icon"
                            aria-hidden="true"
                        />
                        {{ destination.status.label }}
                    </Tag>
                </div>
            </Link>
        </div>
    </div>
</template>
