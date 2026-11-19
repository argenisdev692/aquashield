<script setup lang="ts">
/**
 * PasswordSection — the fields and strength meter for `PUT /user/password`.
 *
 * The form element stays in the page so the page owns the submit action; this
 * component owns the password-specific presentation (policy hint, the four-step
 * meter and the three controlled fields).
 */
import { computed } from 'vue';
import { PasswordField } from '@/Components/Forms';
import { PASSWORD_HINT, PASSWORD_MAX_LENGTH, PASSWORD_MIN_LENGTH } from '@/lib/auth';
import type { FormErrorMap } from '@/lib/forms';
import type { UpdatePasswordForm } from '@/Modules/Users/schemas/profileSettings';

const props = defineProps<{
    form: UpdatePasswordForm;
    errors: FormErrorMap;
}>();

const emit = defineEmits<{ blur: [field: string] }>();

const scorePassword = (value: string): number => {
    if (value === '') {
        return 0;
    }

    let score = value.length >= PASSWORD_MIN_LENGTH ? 1 : 0;

    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) {
        score += 1;
    }

    if (/\d/.test(value)) {
        score += 1;
    }

    if (/[^A-Za-z0-9]/.test(value)) {
        score += 1;
    }

    return Math.min(score, 4);
};

const STRENGTH_LABELS = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'] as const;

const score = computed<number>(() => scorePassword(props.form.password));
const label = computed<(typeof STRENGTH_LABELS)[number]>(() => STRENGTH_LABELS[score.value]);

const barClass = (step: number): string => {
    if (step >= score.value) {
        return 'bg-surface-200 dark:bg-surface-700';
    }

    if (score.value >= 3) {
        return 'bg-aq-success';
    }

    return score.value === 2 ? 'bg-aq-warning' : 'bg-aq-danger';
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <PasswordField
            v-model="props.form.current_password"
            name="current_password"
            label="Current password"
            autocomplete="current-password"
            required
            :error="props.errors.current_password"
            @blur="emit('blur', 'current_password')"
        />

        <div class="flex flex-col gap-4">
            <PasswordField
                v-model="props.form.password"
                name="password"
                label="New password"
                autocomplete="new-password"
                required
                :hint="PASSWORD_HINT"
                :minlength="PASSWORD_MIN_LENGTH"
                :maxlength="PASSWORD_MAX_LENGTH"
                :error="props.errors.password"
                @blur="emit('blur', 'password')"
            />

            <div class="flex flex-col gap-1.5" aria-live="polite">
                <div class="flex items-center gap-2" aria-hidden="true">
                    <span
                        v-for="step in 4"
                        :key="step"
                        class="h-1.5 flex-1 rounded-full transition-colors duration-200 motion-reduce:transition-none"
                        :class="barClass(step - 1)"
                    />
                </div>

                <p class="text-xs text-surface-600 dark:text-surface-300">
                    Password strength: <span class="font-medium">{{ label }}</span>
                </p>
            </div>
        </div>

        <PasswordField
            v-model="props.form.password_confirmation"
            name="password_confirmation"
            label="Confirm new password"
            autocomplete="new-password"
            required
            :minlength="PASSWORD_MIN_LENGTH"
            :maxlength="PASSWORD_MAX_LENGTH"
            :error="props.errors.password_confirmation"
            @blur="emit('blur', 'password_confirmation')"
        />
    </div>
</template>
