<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';

/**
 * A soft accent glow that trails the pointer.
 *
 * Decorative only: `aria-hidden`, `pointer-events: none`, teleported to
 * <body> and pinned at z-index 60 (app.css, `.cursor-orb`) — above page
 * chrome and sticky headers (z-40/z-50), below every PrimeVue overlay
 * (dialogs, dropdowns and toasts mount at z-1000+), so it never glows on
 * top of a modal. Styling lives in `app.css`; this component only feeds it
 * coordinates.
 *
 * It stays invisible until the pointer actually moves, so it never flashes
 * at the 0,0 corner on load, and it never attaches a listener at all on
 * coarse pointers or under a reduced-motion preference — a glow chasing a
 * finger is meaningless on touch, and incidental motion is exactly what
 * WCAG 2.2 SC 2.3.3 asks us to drop.
 *
 * Writes are coalesced to one per animation frame: `pointermove` can fire
 * far more often than the display refreshes, and every extra write is a
 * style recalculation nobody sees.
 */
const orb = useTemplateRef<HTMLDivElement>('orb');
const isActive = ref(false);

/**
 * Teleported to `<body>` and rendered only after mount.
 *
 * Both matter. The glass surfaces paint `backdrop-filter`, and a filtered
 * ancestor becomes the containing block for `position: fixed` descendants —
 * mounted inline, the orb would be trapped inside that surface instead of
 * tracking the viewport. Skipping the initial render also keeps a purely
 * decorative node out of the first paint.
 */
const isMounted = ref(false);

let frame = 0;
let pointerX = 0;
let pointerY = 0;

function paint(): void {
    frame = 0;

    const element = orb.value;

    if (element === null) {
        return;
    }

    element.style.setProperty('--cursor-x', `${pointerX}px`);
    element.style.setProperty('--cursor-y', `${pointerY}px`);

    isActive.value = true;
}

function handlePointerMove(event: PointerEvent): void {
    pointerX = event.clientX;
    pointerY = event.clientY;

    if (frame === 0) {
        frame = requestAnimationFrame(paint);
    }
}

onMounted(() => {
    isMounted.value = true;

    const isCoarse = window.matchMedia('(pointer: coarse)').matches;
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (isCoarse || prefersReducedMotion) {
        return;
    }

    window.addEventListener('pointermove', handlePointerMove, {
        passive: true,
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('pointermove', handlePointerMove);

    if (frame !== 0) {
        cancelAnimationFrame(frame);
        frame = 0;
    }
});
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <div
            ref="orb"
            :class="['cursor-orb', isActive && 'cursor-orb--active']"
            aria-hidden="true"
        />
    </Teleport>
</template>
