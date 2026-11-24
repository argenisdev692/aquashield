/**
 * Shared motion vocabulary for the Welcome screen — the React port of the
 * Vue `motion-v` variants that used to live inline in each component.
 *
 * Frontend invariant: variants and transitions come from here, never inline.
 * The entrance stagger, the rise, the word reveal, the scroll reveal and the
 * product-frame settle all read from this module so a motion change is one edit.
 *
 * Every variant is reduced-motion aware — the same `prefers-reduced-motion`
 * branch the Vue `motion-v` computed variants carried — so the OS-level kill
 * switch collapses the stagger, the rise and the word blur instead of animating.
 */
import { useReducedMotion, type Variants } from 'framer-motion';

export const MOTION_DURATION = 0.45;
export const MOTION_EASE = 'circOut';

/** Hero / mockup container: one entrance stagger across children. */
export function useEntrance(): Variants {
    const reduceMotion = useReducedMotion();

    return {
        hidden: {},
        show: {
            transition: {
                delayChildren: reduceMotion ? 0 : 0.08,
                staggerChildren: reduceMotion ? 0 : 0.09,
            },
        },
    };
}

/** Fade + rise — the workhorse entrance for single blocks. */
export function useRise(): Variants {
    const reduceMotion = useReducedMotion();

    return {
        hidden: { opacity: 0, y: reduceMotion ? 0 : 22 },
        show: {
            opacity: 1,
            y: 0,
            transition: { duration: MOTION_DURATION, ease: MOTION_EASE },
        },
    };
}

/** Headline line: carries the per-line token stagger. */
export function useLineReveal(): Variants {
    const reduceMotion = useReducedMotion();

    return {
        hidden: {},
        show: { transition: { staggerChildren: reduceMotion ? 0 : 0.05 } },
    };
}

/** Word-level kinetic reveal: lift + blur burn-off, one-shot on load. */
export function useWord(): Variants {
    const reduceMotion = useReducedMotion();

    return {
        hidden: {
            opacity: 0,
            y: reduceMotion ? 0 : '0.55em',
            filter: reduceMotion ? 'blur(0px)' : 'blur(6px)',
        },
        show: {
            opacity: 1,
            y: 0,
            filter: 'blur(0px)',
            transition: { duration: 0.5, ease: MOTION_EASE },
        },
    };
}

/** Generic in-view reveal (Why bar, paths heading). */
export function useReveal(index = 0): Variants {
    const reduceMotion = useReducedMotion();

    return {
        hidden: { opacity: 0, y: reduceMotion ? 0 : 24 },
        show: {
            opacity: 1,
            y: 0,
            transition: {
                duration: MOTION_DURATION,
                ease: MOTION_EASE,
                delay: reduceMotion ? 0 : index * 0.08,
            },
        },
    };
}

/** Product-frame settle: the mockup plate materialises (translateY + 1.5% scale). */
export function useSettle(): Variants {
    const reduceMotion = useReducedMotion();

    return {
        hidden: reduceMotion ? { opacity: 0 } : { opacity: 0, y: 26, scale: 0.985 },
        show: reduceMotion
            ? { opacity: 1 }
            : {
                  opacity: 1,
                  y: 0,
                  scale: 1,
                  transition: { duration: 0.55, ease: MOTION_EASE },
              },
    };
}
