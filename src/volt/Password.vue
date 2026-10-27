<template>
    <Password
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge,
        }"
    >
        <template #maskicon="{ toggleCallback }">
            <button
                type="button"
                class="text-surface-500 dark:text-surface-400 absolute inset-y-0 end-0 flex items-center rounded-e-md px-3 transition-colors duration-200 hover:text-surface-700 focus-visible:text-surface-700 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-500 dark:hover:text-surface-300 dark:focus-visible:text-surface-300"
                aria-label="Hide password"
                @click="toggleCallback"
            >
                <EyeSlashIcon class="h-4 w-4" aria-hidden="true" />
            </button>
        </template>
        <template #unmaskicon="{ toggleCallback }">
            <button
                type="button"
                class="text-surface-500 dark:text-surface-400 absolute inset-y-0 end-0 flex items-center rounded-e-md px-3 transition-colors duration-200 hover:text-surface-700 focus-visible:text-surface-700 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-500 dark:hover:text-surface-300 dark:focus-visible:text-surface-300"
                aria-label="Show password"
                @click="toggleCallback"
            >
                <EyeIcon class="h-4 w-4" aria-hidden="true" />
            </button>
        </template>
        <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
            <slot :name="slotName" v-bind="slotProps ?? {}" />
        </template>
    </Password>
</template>

<script setup lang="ts">
import EyeIcon from '@primevue/icons/eye';
import EyeSlashIcon from '@primevue/icons/eyeslash';
import Password, { type PasswordPassThroughOptions, type PasswordProps } from 'primevue/password';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ PasswordProps {}
defineProps<Props>();

const theme = ref<PasswordPassThroughOptions>({
    root: `relative flex w-full`,
    pcInputText: {
        root: `appearance-none rounded-md outline-hidden
        bg-surface-0 dark:bg-surface-950
        p-filled:bg-surface-50 dark:p-filled:bg-surface-800
        text-surface-700 dark:text-surface-0
        placeholder:text-surface-500 dark:placeholder:text-surface-400
        border border-surface-300 dark:border-surface-700
        enabled:hover:border-surface-400 dark:enabled:hover:border-surface-600
        enabled:focus:border-primary
        disabled:bg-surface-200 disabled:text-surface-500
        dark:disabled:bg-surface-700 dark:disabled:text-surface-400
        p-invalid:border-aq-danger
        p-invalid:placeholder:text-aq-danger-strong
        px-3 py-2 w-full p-has-e-icon:pe-10
        p-small:text-sm p-small:px-[0.625rem] p-small:py-[0.375rem]
        p-large:text-lg p-large:px-[0.875rem] p-large:py-[0.625rem]
        transition-colors duration-200 shadow-[0_1px_2px_0_rgba(18,18,23,0.05)]`,
    },
    hiddenAccesible: `sr-only`,
    overlay: `p-3 rounded-md p-portal-self:min-w-full
        bg-surface-0 dark:bg-surface-900
        border border-surface-200 dark:border-surface-700
        text-surface-700 dark:text-surface-0
        shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1),0_2px_4px_-2px_rgba(0,0,0,0.1)]`,
    content: `flex flex-col gap-2`,
    meter: `h-3 bg-surface-200 dark:bg-surface-700 rounded-md`,
    meterLabel: `h-full w-0 transition-[width] duration-1000 ease-in-out rounded-md
        p-weak:bg-aq-danger
        p-medium:bg-amber-500 dark:p-medium:bg-amber-400
        p-strong:bg-green-500 dark:p-strong:bg-green-400`,
    meterText: ``,
    transition: {
        enterFromClass: 'opacity-0 scale-y-75',
        enterActiveClass: 'transition duration-120 ease-[cubic-bezier(0,0,0.2,1)]',
        leaveActiveClass: 'transition-opacity duration-100 ease-linear',
        leaveToClass: 'opacity-0',
    },
});
</script>
