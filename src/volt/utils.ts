import { twMerge } from 'tailwind-merge';
import { mergeProps } from 'vue';

/**
 * Volt's class-aware pass-through merge.
 *
 * PrimeVue calls this hook with three untyped objects (its own published type
 * for the merge option is untyped). They are typed as a plain prop record
 * here, so the loose library boundary stops at this signature instead of
 * leaking loose types into the components — `vue-tsc` stays strict from here
 * down.
 *
 * Only `class` is deduplicated through tailwind-merge; every other key, and the
 * third argument (PrimeVue's merge context), is forwarded untouched so the
 * result is the same props object the components had before.
 */

/** Same shape Vue's own `mergeProps` consumes: a record of vnode props. */
type PropsRecord = Record<string, unknown>;

/**
 * tailwind-merge only understands class strings. When both sides are strings
 * (every `pt` entry in `src/volt` is a template literal) the conflict-aware
 * merge wins; otherwise Vue's own class concatenation handles arrays, objects
 * and `undefined` without dropping a value.
 */
const mergeClasses = (globalClass: unknown, selfClass: unknown): unknown => {
    if (typeof globalClass === 'string' && typeof selfClass === 'string') {
        return twMerge(globalClass, selfClass);
    }

    return mergeProps({ class: globalClass }, { class: selfClass }).class;
};

export const ptViewMerge = (
    globalPTProps: PropsRecord = {},
    selfPTProps: PropsRecord = {},
    datasets: PropsRecord = {},
): PropsRecord => {
    const { class: globalClass, ...globalRest } = globalPTProps;
    const { class: selfClass, ...selfRest } = selfPTProps;

    return mergeProps(
        { class: mergeClasses(globalClass, selfClass) },
        globalRest,
        selfRest,
        datasets,
    );
};
