<script setup lang="ts">
import { ref, useTemplateRef, watch } from 'vue';
import FieldShell from './FieldShell.vue';

/**
 * Segmented one-time-code input.
 *
 * The model is the plain digit string, so a page keeps using the same Zod
 * schema and `useZodForm` contract as every other field. Six boxes are one
 * control, not six: arrow keys, Backspace, paste and iOS/Android one-time-code
 * autofill all move across the group, and `blur` is emitted only when focus
 * leaves the group so validation does not fire between digits.
 */

interface Props {
    label: string;
    name: string;
    length?: number;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    autofocus?: boolean;
    autocomplete?: string;
}

const props = withDefaults(defineProps<Props>(), {
    length: 6,
    autocomplete: 'one-time-code',
    required: false,
    disabled: false,
    autofocus: false,
});

const model = defineModel<string>({ default: '' });
const emit = defineEmits<{ blur: [] }>();

const group = useTemplateRef<HTMLDivElement>('group');
const inputs = ref<(HTMLInputElement | null)[]>([]);

const normalise = (value: string): string[] => {
    const clean = value.replace(/\D/g, '').slice(0, props.length);

    return Array.from({ length: props.length }, (_, index) => clean[index] ?? '');
};

const digits = ref<string[]>(normalise(model.value));

watch(
    () => model.value,
    (value) => {
        const next = normalise(value);

        if (next.join('') !== digits.value.join('')) {
            digits.value = next;
        }
    },
);

watch(digits, (value) => {
    const joined = value.join('');

    if (joined !== model.value) {
        model.value = joined;
    }
});

const setInput = (index: number, element: unknown): void => {
    inputs.value[index] = element instanceof HTMLInputElement ? element : null;
};

const focusAt = (index: number): void => {
    const target = inputs.value[index];

    if (target === null || target === undefined) {
        return;
    }

    target.focus();
    target.select();
};

const fill = (raw: string, start: number): void => {
    const chars = raw.replace(/\D/g, '').slice(0, props.length).split('');

    if (chars.length === 0) {
        return;
    }

    const next = [...digits.value];

    chars.forEach((char, offset) => {
        const target = start + offset;

        if (target < props.length) {
            next[target] = char;
        }
    });

    digits.value = next;
    focusAt(Math.min(start + chars.length, props.length - 1));
};

const writeDigit = (index: number, digit: string): void => {
    const next = [...digits.value];
    next[index] = digit;
    digits.value = next;
};

const onInput = (index: number, event: Event): void => {
    const target = event.target;

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    const raw = target.value.replace(/\D/g, '');

    if (raw.length === 0) {
        writeDigit(index, '');

        return;
    }

    // Autofill and paste can deliver the whole code to one box. Spread it
    // across the group from the start so a 6-digit paste is never truncated.
    if (raw.length > 1) {
        fill(raw, raw.length >= props.length ? 0 : index);

        return;
    }

    writeDigit(index, raw);

    if (index < props.length - 1) {
        focusAt(index + 1);
    }
};

const onKeydown = (index: number, event: KeyboardEvent): void => {
    if (event.key === 'Backspace') {
        event.preventDefault();

        if (digits.value[index] !== '') {
            writeDigit(index, '');

            return;
        }

        if (index > 0) {
            writeDigit(index - 1, '');
            focusAt(index - 1);
        }

        return;
    }

    if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        focusAt(index - 1);

        return;
    }

    if (event.key === 'ArrowRight' && index < props.length - 1) {
        event.preventDefault();
        focusAt(index + 1);
    }
};

const onPaste = (index: number, event: ClipboardEvent): void => {
    const text = event.clipboardData?.getData('text') ?? '';
    const clean = text.replace(/\D/g, '');

    if (clean === '') {
        return;
    }

    event.preventDefault();
    fill(clean, clean.length >= props.length ? 0 : index);
};

const onFocus = (event: FocusEvent): void => {
    if (event.target instanceof HTMLInputElement) {
        event.target.select();
    }
};

const onFocusOut = (event: FocusEvent): void => {
    const next = event.relatedTarget;

    if (next instanceof Node && group.value?.contains(next) === true) {
        return;
    }

    emit('blur');
};
</script>

<template>
    <FieldShell
        v-slot="{ id, describedBy, invalid }"
        :label="props.label"
        :name="props.name"
        :error="props.error"
        :hint="props.hint"
        :required="props.required"
        class="items-center text-center"
    >
        <div
            ref="group"
            role="group"
            :aria-label="props.label"
            class="flex w-full max-w-xs justify-center gap-1.5 sm:gap-2"
            @focusout="onFocusOut"
        >
            <input
                v-for="(digit, index) in digits"
                :key="index"
                :ref="(element) => setInput(index, element)"
                :id="index === 0 ? id : undefined"
                :value="digit"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                :name="index === 0 ? props.name : undefined"
                :autocomplete="index === 0 ? props.autocomplete : 'off'"
                :disabled="props.disabled"
                :autofocus="index === 0 && props.autofocus"
                :aria-label="`${props.label} digit ${index + 1} of ${props.length}`"
                :aria-describedby="describedBy"
                :aria-invalid="invalid ? 'true' : undefined"
                :class="
                    invalid
                        ? 'border-aq-danger focus-visible:outline-aq-danger transition-colors duration-150 motion-reduce:transition-none'
                        : 'border-aq-line focus-visible:outline-aq-accent transition-colors duration-150 motion-reduce:transition-none'
                "
                class="h-11 min-w-0 flex-1 basis-0 rounded-lg border bg-aq-elevated/40 text-center text-lg font-semibold text-aq-ink tabular-nums transition-colors duration-150 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transition-none"
                @input="onInput(index, $event)"
                @keydown="onKeydown(index, $event)"
                @paste="onPaste(index, $event)"
                @focus="onFocus"
            />
        </div>
    </FieldShell>
</template>
