<script setup lang="ts">
/**
 * The brand moment: the shield assembles out of water.
 *
 * Placement is deliberate. The hero is text-only and the fold belongs to the
 * headline; a WebGL canvas there would compete with the LCP and delay the
 * one thing the entry screen is for. This band sits after the product proof,
 * so the 3D is a reward for scrolling, not a toll on arriving.
 *
 * The still mark is the real content. The scene is an enhancement layered on
 * top of it, and every gate below falls back to the poster:
 *   - prefers-reduced-motion never mounts the canvas
 *   - Save-Data and low-memory devices never mount it
 *   - the canvas is requested only while the band is within 240px of view
 *   - leaving the band unmounts the renderer, so no GPU work happens offscreen
 *
 * The scene chunk carries three.js, which is why it is a separate dynamic
 * import inside a component that is itself dynamic: three never enters the
 * Welcome route's initial graph.
 */
import {
    computed,
    defineAsyncComponent,
    onMounted,
    onUnmounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import { useReducedMotion } from 'motion-v';

const BrandMarkScene = defineAsyncComponent(() => import('@/Components/BrandMarkScene.vue'));

/* Device memory is Chromium-only and not in lib.dom; narrow it once here
 * instead of casting at every read. */
type BudgetNavigator = Navigator & {
    deviceMemory?: number;
    connection?: { saveData?: boolean };
};

const reduceMotion = useReducedMotion();
const stage = useTemplateRef<HTMLDivElement>('stage');

const canRender = ref(false);
const inView = ref(false);
const sceneReady = ref(false);

const allowsScene = (): boolean => {
    const navigatorWithBudget = navigator as BudgetNavigator;

    if (navigatorWithBudget.connection?.saveData === true) {
        return false;
    }

    return !(
        typeof navigatorWithBudget.deviceMemory === 'number' && navigatorWithBudget.deviceMemory < 4
    );
};

const showScene = computed(() => canRender.value && inView.value);

let observer: IntersectionObserver | undefined;

onMounted(() => {
    if (reduceMotion.value || !allowsScene()) {
        return;
    }

    canRender.value = true;

    observer = new IntersectionObserver(
        (entries) => {
            const entry = entries[0];

            if (entry) {
                inView.value = entry.isIntersecting;
            }
        },
        { rootMargin: '240px 0px' },
    );

    if (stage.value) {
        observer.observe(stage.value);
    }
});

onUnmounted(() => {
    observer?.disconnect();
});

/* The poster returns the moment the renderer leaves, so re-entry never
 * shows an empty square while the chunk is re-fetched. */
watch(showScene, (visible) => {
    if (!visible) {
        sceneReady.value = false;
    }
});
</script>

<template>
    <section aria-labelledby="brand-moment-heading" class="relative overflow-hidden bg-aq-void">
        <div
            class="mx-auto grid w-full max-w-6xl items-center gap-12 px-6 py-12 sm:px-8 lg:grid-cols-2 lg:gap-16 lg:px-12"
        >
            <div class="order-2 lg:order-1">
                <p class="text-xs font-semibold tracking-[0.18em] text-aq-accent uppercase">
                    The mark
                </p>

                <h2
                    id="brand-moment-heading"
                    class="mt-4 max-w-md text-3xl font-bold tracking-[-0.02em] text-balance text-aq-ink sm:text-4xl"
                >
                    One shield. Every job.
                </h2>

                <p class="mt-5 max-w-md leading-relaxed text-pretty text-aq-ink-soft">
                    Dispatch, jobs, customers, invoices and claims — one surface. The shield is the
                    reminder that every ticket is someone's home.
                </p>
            </div>

            <div ref="stage" class="order-1 lg:order-2">
                <div class="relative mx-auto aspect-square w-full max-w-[24rem]">
                    <!-- The poster is the mark, not a stand-in illustration.
                         It holds the exact box the cloud occupies, so the
                         swap never shifts layout. -->
                    <div
                        class="absolute inset-0 flex items-center justify-center transition-opacity duration-500 motion-reduce:transition-none"
                        :class="sceneReady ? 'opacity-0' : 'opacity-100'"
                    >
                        <span aria-hidden="true" class="aq-mark aspect-[102/146] h-2/3">
                            <span class="aq-mark__water"></span>
                        </span>
                    </div>

                    <div
                        v-if="showScene"
                        class="absolute inset-0 transition-opacity duration-700 motion-reduce:transition-none"
                        :class="sceneReady ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    >
                        <BrandMarkScene @ready="sceneReady = true" />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
